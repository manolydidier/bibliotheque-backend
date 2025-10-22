<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;

class FileProxyController extends Controller
{
    /**
     * /file-proxy?url=... ou /file-proxy?path=storage/...
     * - path => lit sur le disk 'public'
     * - url  => autorisé si hôte whitelisté (APP_URL host + FILE_PROXY_HOSTS)
     * Supporte GET/HEAD/OPTIONS + Range + CORS.
     */
    public function handle(Request $request)
    {
        // Preflight CORS
        if ($request->isMethod('options')) {
            Log::info('file-proxy.preflight', ['origin' => $request->header('Origin')]);
            return $this->corsResponse();
        }

        Log::info('file-proxy.hit', [
            'method' => $request->method(),
            'url_q'  => $request->query('url'),
            'path_q' => $request->query('path'),
            'ip'     => $request->ip(),
            'ua'     => $request->userAgent(),
        ]);

        $url  = trim((string) $request->query('url', ''));
        $path = trim((string) $request->query('path', ''));

        if ($path === '' && $url === '') {
            abort(400, 'Missing url or path');
        }

        // Si path fourni, on force le flux local (disk "public")
        if ($path !== '') {
            return $this->streamLocal($path, $request);
        }

        // URL vers /storage du même site → traite en local
        $localPath = $this->tryExtractLocalPathFromUrl($url);
        if ($localPath) {
            return $this->streamLocal($localPath, $request);
        }

        // Sinon, flux distant (via whitelist)
        return $this->streamRemote($url, $request);
    }

    private function corsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
            'Access-Control-Allow-Headers' => 'Origin, X-Requested-With, Content-Type, Accept, Range',
            'Vary' => 'Origin',
            // Utiles pour viewers/iframes externes :
            'Cross-Origin-Resource-Policy' => 'cross-origin',
            // Evite le buffering Nginx sur gros fichiers :
            'X-Accel-Buffering' => 'no',
        ];
    }

    private function corsResponse(int $status = 204)
    {
        return response('', $status, $this->corsHeaders());
    }

    private function tryExtractLocalPathFromUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') return null;

        // /storage/... (chemin absolu)
        if (preg_match('#^/storage/#i', $url)) {
            return ltrim($url, '/');
        }

        // Même host que APP_URL
        $appUrl = rtrim(config('app.url'), '/');
        if ($appUrl && str_starts_with($url, $appUrl . '/storage/')) {
            return ltrim(substr($url, strlen($appUrl) + 1), '/'); // retire "appUrl/"
        }

        // Dev local : localhost/127.0.0.1
        if (preg_match('#^https?://(127\.0\.0\.1|localhost)(:\d+)?/storage/#i', $url)) {
            $parsed = parse_url($url);
            return ltrim($parsed['path'] ?? '', '/');
        }

        return null;
    }

    private function isAllowedRemoteHost(string $url): bool
    {
        $p = @parse_url($url);
        if (!$p || !isset($p['scheme'], $p['host'])) return false;
        if (!in_array(strtolower($p['scheme']), ['http','https'], true)) return false;

        // Host autorisés : host de APP_URL + env FILE_PROXY_HOSTS="a.example.com,b.example.com"
        $allowed = [];

        $appUrl = rtrim(config('app.url'), '/');
        if ($appUrl) {
            $ah = parse_url($appUrl, PHP_URL_HOST);
            if ($ah) $allowed[] = strtolower($ah);
        }

        $extra = array_filter(array_map('trim', explode(',', (string) env('FILE_PROXY_HOSTS', ''))));
        foreach ($extra as $h) $allowed[] = strtolower($h);

        // Dev local : autoriser localhost/127.0.0.1 si FILE_PROXY_ALLOW_LOOPBACK=true
        $allowLoopback = filter_var(env('FILE_PROXY_ALLOW_LOOPBACK', false), FILTER_VALIDATE_BOOLEAN);
        if ($allowLoopback) {
            $allowed = array_merge($allowed, ['127.0.0.1','localhost']);
        }

        return in_array(strtolower($p['host']), $allowed, true);
    }

    private function normalizeLocalPath(string $p): string
    {
        $p = ltrim($p, '/');
        if (str_starts_with($p, 'storage/')) {
            $p = substr($p, strlen('storage/'));
        }
        // interdit .. /
        $p = preg_replace('#\.\.+#', '.', $p);
        return ltrim($p, '/');
    }

    private function mimeFromPath(string $path, ?string $fallback = null): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'geojson') return 'application/geo+json';
        if ($ext === 'json')    return 'application/json';
        if ($ext === 'zip')     return 'application/zip';
        return $fallback ?: 'application/octet-stream';
    }

    private function streamLocal(string $relativeStoragePath, Request $req)
    {
        $disk = Storage::disk('public');
        $path = $this->normalizeLocalPath($relativeStoragePath);

        if (!$disk->exists($path)) {
            abort(404, 'File not found');
        }

        $size = (int) $disk->size($path);
        $mime = $this->mimeFromPath($path, $disk->mimeType($path) ?: null);

        $range = $req->header('Range');
        $start = 0;
        $end   = $size > 0 ? $size - 1 : 0;
        $status = 200;
        $headers = array_merge([
            'Content-Type'  => $mime,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=0',
            'X-File-Proxy'  => 'local',
            'X-File-Proxy-Path' => $path,
            'X-File-Proxy-CT'   => $mime,
        ], $this->corsHeaders());

        if ($range && preg_match('/bytes=(\d+)-(\d*)/i', $range, $m)) {
            $start = (int) $m[1];
            if ($m[2] !== '') $end = min((int)$m[2], $end);
            if ($start > $end) $start = 0;
            $status = 206; // partial
            $headers['Content-Range']  = "bytes $start-$end/$size";
            $headers['Content-Length'] = (string) ($end - $start + 1);
        } else {
            $headers['Content-Length'] = (string) $size;
        }

        Log::info('file-proxy.local', ['path' => $path, 'size' => $size, 'range' => $req->header('Range')]);

        // HEAD: uniquement les headers
        if ($req->isMethod('head')) {
            return response('', $status, $headers);
        }

        return new StreamedResponse(function () use ($disk, $path, $start, $end) {
            $stream = $disk->readStream($path);
            if ($stream === false) return;

            if ($start > 0) @fseek($stream, $start);
            $remaining = $end - $start + 1;

            while (!feof($stream) && $remaining > 0) {
                $chunk = fread($stream, min(8192, $remaining));
                if ($chunk === false) break;
                echo $chunk;
                $remaining -= strlen($chunk);
                @ob_flush(); flush();
            }

            fclose($stream);
        }, $status, $headers);
    }

    private function streamRemote(string $url, Request $req)
    {
        abort_unless($this->isAllowedRemoteHost($url), 403, 'Remote host not allowed');

        $headers = [];
        if ($req->hasHeader('Range')) {
            $headers['Range'] = $req->header('Range');
        }

        $response = Http::withHeaders($headers)->withOptions([
            'stream' => true,
            'timeout' => 60,
        ])->get($url);

        if ($response->failed()) {
            abort($response->status() ?: 502, 'Unable to fetch remote resource');
        }

        $status = $response->status();

        // Mime correct si serveur en face envoie text/plain
        $remoteCt = $response->header('Content-Type');
        $ct = $remoteCt ?: $this->mimeFromPath(parse_url($url, PHP_URL_PATH) ?? '');

        $out = array_merge([
            'Content-Type'  => $ct,
            'Cache-Control' => 'public, max-age=0',
            'X-File-Proxy'  => 'remote',
            'X-File-Proxy-URL' => $url,
            'X-File-Proxy-CT'  => $ct,
        ], $this->corsHeaders());

        foreach (['Content-Length','Content-Range','Accept-Ranges','Content-Disposition','Last-Modified','ETag'] as $h) {
            if ($v = $response->header($h)) $out[$h] = $v;
        }

        Log::info('file-proxy.remote', [
            'url' => $url,
            'status' => $status,
            'range' => $req->header('Range'),
        ]);

        if ($req->isMethod('head')) {
            return response('', $status, $out);
        }

        return response()->stream(function () use ($response) {
            foreach ($response->stream() as $chunk) {
                echo $chunk;
                @ob_flush(); flush();
            }
        }, $status, $out);
    }
}
