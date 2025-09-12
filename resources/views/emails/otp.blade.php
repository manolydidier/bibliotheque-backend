<!doctype html>
<html lang="{{ $lang ?? 'fr' }}">
  <head>
    <meta charset="utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($lang ?? 'fr') === 'en' ? 'Verification code' : 'Code de vérification' }}</title>
    <style>
      * { box-sizing: border-box; }
      body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; }

      @media (prefers-color-scheme: dark) {
        .email-body { background-color: #0f172a !important; }
        .card { background-color: #1e293b !important; }
        .card-header { background: linear-gradient(135deg, #3b82f6, #06b6d4) !important; }
        .title { color: #f8fafc !important; }
        .text { color: #cbd5e1 !important; }
        .footer { background-color: #1e293b !important; border-top-color: #334155 !important; }
        .footer-text { color: #94a3b8 !important; }
      }

      @media screen and (max-width: 640px) {
        .container { width: 100% !important; padding: 12px !important; }
        .card { border-radius: 12px !important; }
        .card-header { padding: 20px 16px !important; }
        .content-padding { padding: 20px 16px !important; }
        .title { font-size: 20px !important; line-height: 26px !important; }
        .cta-button { padding: 14px 24px !important; font-size: 16px !important; }
      }
    </style>
  </head>
  <body style="margin:0; padding:0;">
    <div class="email-body" style="background-color:#f1f5f9; padding:32px 16px; min-height:100vh;">
      <div class="container" style="max-width:600px; margin:0 auto; width:100%;">
        <div class="card" style="background-color:#ffffff; border-radius:20px; box-shadow:0 20px 50px rgba(15,23,42,0.08); overflow:hidden; border:1px solid rgba(226,232,240,0.8);">
          <div class="card-header" style="background:linear-gradient(135deg, #2563eb 0%, #0ea5e9 100%); padding:32px 28px; position:relative;">
            <div style="position:absolute; top:0; right:0; width:120px; height:120px; background:radial-gradient(circle, rgba(255,255,255,0.12) 1px, transparent 1px); background-size:20px 20px; opacity:0.3;"></div>
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="position:relative; z-index:1;">
              <tr>
                <td style="vertical-align:middle;">
                  <div style="display:flex; align-items:center; margin-bottom:8px;">
                    <div style="width:32px; height:32px; background:rgba(255,255,255,0.2); border-radius:8px; display:flex; align-items:center; justify-content:center; margin-right:12px;">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#ffffff;">
                        <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
                        <polyline points="16,6 12,2 8,6"/>
                        <line x1="12" y1="2" x2="12" y2="15"/>
                      </svg>
                    </div>
                    <h1 style="color:#ffffff; font-size:20px; font-weight:700; margin:0; letter-spacing:-0.02em;">
                      {{ ($lang ?? 'fr') === 'en' ? 'Your verification code' : 'Votre code de vérification' }}
                    </h1>
                  </div>
                </td>
                <td align="right" style="vertical-align:top;">
                  <div style="color:rgba(255,255,255,0.9); font-size:13px; font-weight:500; padding:6px 12px; background:rgba(255,255,255,0.15); border-radius:20px; backdrop-filter:blur(10px);">
                    {{ now()->format('d/m/Y \à H:i') }}
                  </div>
                </td>
              </tr>
            </table>
          </div>

          <div class="content-padding" style="padding:28px;">
            <h2 class="title" style="margin:0 0 8px 0; font-size:24px; line-height:32px; color:#0f172a; font-weight:700; letter-spacing:-0.02em;">
              {{ ($lang ?? 'fr') === 'en' ? 'Hello,' : 'Bonjour,' }}
            </h2>

            <p class="text" style="margin:0; color:#475569; font-size:15px; line-height:22px;">
              {{ ($lang ?? 'fr') === 'en'
                  ? 'Use the code below to continue.'
                  : 'Utilisez le code ci-dessous pour continuer.' }}
            </p>

            <div style="margin:18px 0 8px 0; text-align:center;">
              <div style="
                display:inline-block; padding:16px 24px; border-radius:14px;
                background:linear-gradient(180deg, #f8fafc, #eef2f7); border:1px solid #e2e8f0;
                font-weight:800; font-size:28px; letter-spacing:6px; color:#0f172a;
                box-shadow:0 6px 20px rgba(15,23,42,0.06);
                font-family: ui-monospace, 'SF Mono', Menlo, Consolas, monospace;">
                {{ $code ?? '000000' }}
              </div>
            </div>

            <p class="text" style="margin:6px 0 0 0; color:#64748b; font-size:13px; line-height:20px; text-align:center;">
              {{ $ttlTxt ?? (($lang ?? 'fr') === 'en' ? 'This code is valid for 10 minutes.' : 'Ce code est valable 10 minutes.') }}
            </p>

            <div style="background:linear-gradient(90deg, #f1f5f9, #e2e8f0); height:1px; margin:20px 0;"></div>

            @php $cta = $ctaUrl ?? config('app.url'); @endphp
            @if(!empty($cta))
              <div style="text-align:center; margin-bottom:8px;">
                <a href="{{ $cta }}" target="_blank" rel="noreferrer" class="cta-button"
                   style="display:inline-block; padding:14px 28px; background:linear-gradient(135deg, #2563eb, #1d4ed8); color:#ffffff; text-decoration:none; border-radius:12px; font-weight:600; font-size:15px; box-shadow:0 4px 12px rgba(37,99,235,0.3); transition:all 0.2s ease; letter-spacing:0.01em;">
                  {{ ($lang ?? 'fr') === 'en' ? 'Open the app' : "Ouvrir l’application" }}
                </a>
              </div>

              <div style="text-align:center; padding:10px; background:#f8fafc; border-radius:12px; border:1px dashed #cbd5e1;">
                <div style="font-size:12px; color:#64748b; margin-bottom:4px;">
                  {{ ($lang ?? 'fr') === 'en' ? 'Or copy this link:' : 'Ou copiez ce lien :' }}
                </div>
                <a href="{{ $cta }}" target="_blank" rel="noreferrer"
                   style="color:#2563eb; font-size:13px; word-break:break-all; font-family:ui-monospace, 'SF Mono', Consolas, monospace;">
                  {{ $cta }}
                </a>
              </div>
            @endif

            <div style="margin-top:16px; background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; padding:12px 14px; border-radius:12px;">
              <div style="font-size:13px; line-height:20px;">
                {{ ($lang ?? 'fr') === 'en'
                    ? "If you didn't request this code, you can safely ignore this email."
                    : "Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet e-mail." }}
              </div>
            </div>
          </div>
        </div>

        <div class="footer" style="background:#ffffff; margin-top:20px; padding:20px 28px; border-radius:16px; border:1px solid #e2e8f0; text-align:center;">
          <div class="footer-text" style="color:#64748b; font-size:13px; line-height:18px;">
            <div style="margin-bottom:8px;">
              &copy; {{ date('Y') }} {{ config('app.name') }} — {{ ($lang ?? 'fr') === 'en' ? 'All rights reserved' : 'Tous droits réservés' }}
            </div>
          </div>
        </div>

        <div style="height:32px;"></div>
      </div>
    </div>
  </body>
</html>
