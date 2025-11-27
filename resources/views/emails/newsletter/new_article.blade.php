<!doctype html>
<html lang="{{ $lang ?? 'fr' }}">
  <head>
    <meta charset="utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($lang ?? 'fr') === 'en' ? 'New article available' : 'Nouvel article disponible' }}</title>
    <style>
      * { box-sizing: border-box; }
      body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; }

      @media (prefers-color-scheme: dark) {
        .email-body { background-color: #0f172a !important; }
        .card { background-color: #1e293b !important; }
        .card-header { background: linear-gradient(135deg, #22c55e, #16a34a) !important; }
        .title { color: #f8fafc !important; }
        .text { color: #cbd5e1 !important; }
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

        {{-- CARD PRINCIPALE --}}
        <div class="card" style="background-color:#ffffff; border-radius:20px; box-shadow:0 20px 50px rgba(15,23,42,0.08); overflow:hidden; border:1px solid rgba(226,232,240,0.8); position:relative;">

          {{-- Confettis décoratifs --}}
          <div style="position:absolute; inset:0; pointer-events:none; overflow:hidden;">
            <div style="position:absolute; top:-8px; left:18%; width:8px; height:16px; background:#f97316; border-radius:4px; transform:rotate(-18deg); opacity:0.8;"></div>
            <div style="position:absolute; top:28px; left:6%; width:10px; height:10px; background:#22c55e; border-radius:2px; transform:rotate(18deg); opacity:0.8;"></div>
            <div style="position:absolute; top:6px; right:20%; width:9px; height:18px; background:#6366f1; border-radius:4px; transform:rotate(14deg); opacity:0.85;"></div>
            <div style="position:absolute; top:42px; right:8%; width:11px; height:11px; background:#eab308; border-radius:50%; opacity:0.7;"></div>
          </div>

          {{-- HEADER GRADIENT --}}
          <div class="card-header" style="background:linear-gradient(135deg, #22c55e 0%, #16a34a 100%); padding:32px 28px; position:relative;">
            <div style="position:absolute; top:0; right:0; width:120px; height:120px; background:radial-gradient(circle, rgba(255,255,255,0.12) 1px, transparent 1px); background-size:20px 20px; opacity:0.3;"></div>
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="position:relative; z-index:1;">
              <tr>
                <td style="vertical-align:middle;">
                  <div style="display:flex; align-items:center; margin-bottom:8px;">
                    <div style="width:32px; height:32px; background:rgba(255,255,255,0.2); border-radius:8px; display:flex; align-items:center; justify-content:center; margin-right:12px;">
                      {{-- Icône "nouveau" --}}
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#ffffff;">
                        <path d="M12 5v14" />
                        <path d="M5 12h14" />
                      </svg>
                    </div>
                    <h1 style="color:#ffffff; font-size:20px; font-weight:700; margin:0; letter-spacing:-0.02em;">
                      {{ ($lang ?? 'fr') === 'en' ? 'New content in the library' : 'Nouveau contenu dans la bibliothèque' }}
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

          {{-- CONTENU --}}
          @php
            $frontendUrl = rtrim(config('app.frontend_url', 'http://127.0.0.1:5173'), '/');
            $articleUrl  = $frontendUrl.'/articles/'.$article->slug;
            $name = $subscription->name ?? null;
          @endphp

          <div class="content-padding" style="padding:28px;">
            <h2 class="title" style="margin:0 0 8px 0; font-size:24px; line-height:32px; color:#0f172a; font-weight:700; letter-spacing:-0.02em;">
              @if(($lang ?? 'fr') === 'en')
                Hello{{ $name ? ' '.$name : '' }},
              @else
                Bonjour{{ $name ? ' '.$name : '' }},
              @endif
            </h2>

            <p class="text" style="margin:0; color:#475569; font-size:15px; line-height:22px;">
              @if(($lang ?? 'fr') === 'en')
                A new article has just been published in the online library.
              @else
                Un nouvel article vient d’être publié dans la bibliothèque en ligne.
              @endif
            </p>

            {{-- Bloc central avec le titre de l’article --}}
            <div style="margin:20px 0 10px 0; text-align:center;">
              <div style="
                display:inline-block; padding:16px 20px; border-radius:14px;
                background:linear-gradient(180deg, #f8fafc, #eef2f7); border:1px solid #e2e8f0;
                font-weight:700; font-size:18px; color:#0f172a;
                box-shadow:0 6px 20px rgba(15,23,42,0.06);
                font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                {{ $article->title }}
              </div>
            </div>

            @if(!empty($article->excerpt))
              <p class="text" style="margin:6px 0 0 0; color:#64748b; font-size:13px; line-height:20px; text-align:center;">
                {{ $article->excerpt }}
              </p>
            @endif

            <div style="background:linear-gradient(90deg, #f1f5f9, #e2e8f0); height:1px; margin:20px 0;"></div>

            {{-- CTA vers l’article (front React/Vite) --}}
            <div style="text-align:center; margin-bottom:8px;">
              <a href="{{ $articleUrl }}" target="_blank" rel="noreferrer" class="cta-button"
                 style="display:inline-block; padding:14px 28px; background:linear-gradient(135deg, #22c55e, #16a34a); color:#ffffff; text-decoration:none; border-radius:12px; font-weight:600; font-size:15px; box-shadow:0 4px 12px rgba(22,163,74,0.3); transition:all 0.2s ease; letter-spacing:0.01em;">
                @if(($lang ?? 'fr') === 'en')
                  Read the article
                @else
                  Lire l’article
                @endif
              </a>
            </div>

            <div style="text-align:center; padding:10px; background:#f8fafc; border-radius:12px; border:1px dashed #cbd5e1;">
              <div style="font-size:12px; color:#64748b; margin-bottom:4px;">
                @if(($lang ?? 'fr') === 'en')
                  Or copy this link:
                @else
                  Ou copiez ce lien :
                @endif
              </div>
              <a href="{{ $articleUrl }}" target="_blank" rel="noreferrer"
                 style="color:#16a34a; font-size:13px; word-break:break-all; font-family:ui-monospace, 'SF Mono', Consolas, monospace;">
                {{ $articleUrl }}
              </a>
            </div>

            {{-- Note info --}}
            <div style="margin-top:16px; background:#ecfdf3; border:1px solid #bbf7d0; color:#166534; padding:12px 14px; border-radius:12px;">
              <div style="font-size:13px; line-height:20px;">
                @if(($lang ?? 'fr') === 'en')
                  You are receiving this notification because you subscribed to the online library newsletter.
                @else
                  Vous recevez cette notification car vous êtes abonné(e) à la newsletter de la bibliothèque en ligne.
                @endif
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </body>
</html>
