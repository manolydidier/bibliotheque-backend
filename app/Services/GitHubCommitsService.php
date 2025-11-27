<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
class GitHubCommitsService
{
    protected string $owner;
    protected string $frontRepo;
    protected string $backRepo;
    protected int $limit;
    protected ?string $token;

    public function __construct()
    {
        $this->owner     = config('services.github.owner', env('GITHUB_OWNER', 'manolydidier'));
        $this->frontRepo = config('services.github.front_repo', env('GITHUB_FRONT_REPO', 'bibliotheque-frontend'));
        $this->backRepo  = config('services.github.back_repo', env('GITHUB_BACK_REPO', 'bibliotheque-backend'));
        $this->limit     = (int) env('GITHUB_COMMITS_LIMIT', 3);
        $this->token     = env('GITHUB_TOKEN'); // optionnel
    }

    protected function client()
    {
        $headers = [
            'Accept'     => 'application/vnd.github+json',
            'User-Agent' => 'md2i-plateforme-biblio',
        ];

        if ($this->token) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        return Http::withHeaders($headers)->baseUrl('https://api.github.com');
    }

      protected function fetchCommits(string $repo): array
    {
        $response = $this->client()->get("/repos/{$this->owner}/{$repo}/commits", [
            'per_page' => $this->limit,
        ]);

       

        return $response->json();
    }
    public function getLatestCommits(): array
    {
        $front = $this->fetchCommits($this->frontRepo);
        $back  = $this->fetchCommits($this->backRepo);

        $normalize = function (array $raw, string $source) {
            return collect($raw)->map(function ($item) use ($source) {
                return [
                    'sha'        => $item['sha'] ?? null,
                    'short_sha'  => isset($item['sha']) ? substr($item['sha'], 0, 7) : null,
                    'message'    => $item['commit']['message'] ?? 'Commit',
                    'author'     => $item['commit']['author']['name'] ?? null,
                    'date'       => $item['commit']['author']['date'] ?? null,
                    'url'        => $item['html_url'] ?? null,
                    'repo'       => $source,
                ];
            })->toArray();
        };

        return [
            'frontend' => $normalize($front, 'frontend'),
            'backend'  => $normalize($back, 'backend'),
        ];
    }
}
