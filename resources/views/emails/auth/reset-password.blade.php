<!doctype html>
<html lang="{{ $langue }}">
  <head>
    <meta charset="utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $langue === 'en' ? 'Reset password' : 'Réinitialisation du mot de passe' }}</title>
    <style>
      /* Base */
      * { box-sizing: border-box; }
      body { margin:0; padding:0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; background:#f1f5f9; }

      /* Dark mode */
      @media (prefers-color-scheme: dark) {
        body { background:#0f172a !important; }
        .card { background:#0b1220 !important; border-color:#1f2a44 !important; }
        .headbar { background:linear-gradient(135deg,#1e40af,#2563eb)!important; }
        .title,.text { color:#e5e7eb !important; }
        .muted { color:#9ca3af !important; }
        .divider { background:linear-gradient(90deg,#1f2937,#374151)!important; }
        .alert { background:#0b1b34 !important; border-color:#3b82f6 !important; }
        .stripe { background: repeating-linear-gradient(
          -45deg,
          rgba(37,99,235,.15),
          rgba(37,99,235,.15) 10px,
          rgba(37,99,235,.05) 10px,
          rgba(37,99,235,.05) 20px
        ) !important; }
        .footer { background:#0b1220 !important; border-top-color:#1f2a44 !important; }
      }

      /* Responsive */
      @media screen and (max-width:640px){
        .container{ width:100%!important; padding:12px!important;}
        .card{ border-radius:14px!important;}
        .headbar{ padding:18px 14px!important;}
        .section{ padding:18px 14px!important;}
        .cta{ padding:14px 22px!important; font-size:16px!important;}
      }

      /* Utilities */
      .shadow-xl{ box-shadow:0 20px 50px rgba(15,23,42,.10);}
      .rounded-2xl{ border-radius:22px;}
      .rounded-xl{ border-radius:18px;}
      .rounded-lg{ border-radius:12px;}
      .rounded-md{ border-radius:10px;}
      .border{ border:1px solid rgba(226,232,240,.9);}
      .headbar{ background:linear-gradient(135deg,#2563eb 0%, #1d4ed8 100%); }
      .divider{ height:1px; background:linear-gradient(90deg,#e2e8f0,#cbd5e1); }
      .muted{ color:#64748b; }
      .title{ color:#0f172a; }
      .text{ color:#111827; }
      .badge{ color:#fff; background:rgba(255,255,255,.18); padding:6px 10px; border-radius:999px; font-weight:600; font-size:13px; }
      .stripe{ background: repeating-linear-gradient(
          -45deg,
          rgba(37,99,235,.10),
          rgba(37,99,235,.10) 10px,
          rgba(37,99,235,.03) 10px,
          rgba(37,99,235,.03) 20px
        ); }
      .alert{ background:#eff6ff; border-left:4px solid #2563eb; padding:16px 18px; }
      .mono{ font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; }
      a{ color:#1d4ed8; text-decoration:none; }
      .cta{
        display:inline-block; padding:16px 28px;
        background:linear-gradient(135deg,#2563eb,#1d4ed8);
        color:#fff; font-weight:800; border-radius:12px; letter-spacing:.01em;
        box-shadow:0 6px 16px rgba(37,99,235,.35);
      }
      .cta:hover{ filter:brightness(1.04); }
      .chip{
        display:inline-flex; align-items:center; gap:8px;
        background:#e0f2fe; color:#075985; padding:6px 10px; border-radius:999px; font-weight:700; font-size:12px;
      }
      .pill{
        display:inline-block; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700;
        background:#eef2ff; color:#3730a3;
      }
      .ring{
        box-shadow: inset 0 0 0 1px rgba(37,99,235,.18), 0 10px 30px rgba(37,99,235,.08);
      }
    </style>
  </head>
  <body>
    <div style="padding:32px 16px;">
      <div class="container" style="max-width:640px; margin:0 auto;">

        <!-- BANDEAU ALERTE -->
        <div class="rounded-xl stripe ring" style="padding:10px 14px; margin-bottom:12px;">
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
            <tr>
              <td style="vertical-align:middle;">
                <div style="display:flex; align-items:center; gap:10px;">
                  <div style="width:22px; height:22px; border-radius:6px; background:#2563eb; display:flex; align-items:center; justify-content:center;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
                      <path d="M12 9v4m0 4h.01"/><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                  </div>
                  <strong class="text" style="font-size:14px;">
                    {{ $langue === 'en' ? 'Security alert — action required' : 'Alerte sécurité — action requise' }}
                  </strong>
                </div>
              </td>
              <td align="right">
                <span class="pill">{{ $langue === 'en' ? 'Expires in' : 'Expire dans' }} {{ $expire }} {{ $langue === 'en' ? 'min' : 'min' }}</span>
              </td>
            </tr>
          </table>
        </div>

        <!-- CARTE PRINCIPALE -->
        <div class="card rounded-2xl border shadow-xl" style="background:#fff; overflow:hidden;">
          <!-- Header -->
          <div class="headbar" style="padding:24px 22px; position:relative;">
            <div style="position:absolute; inset:0; opacity:.18; pointer-events:none;">
              <div style="position:absolute; right:-14px; top:-10px; width:140px; height:140px; background:
                radial-gradient(circle at 30% 30%, rgba(255,255,255,.45) 2px, transparent 2px) 0 0/16px 16px;"></div>
            </div>
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="position:relative; z-index:1;">
              <tr>
                <td>
                  <div style="display:flex; align-items:center; gap:12px;">
                    <div style="width:36px; height:36px; background:rgba(255,255,255,.20); border-radius:10px; display:flex; align-items:center; justify-content:center;">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
                        <path d="M12 11v6M12 7h.01M5 21h14a2 2 0 0 0 2-2V9l-7-5H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z"/>
                      </svg>
                    </div>
                    <h1 style="margin:0; color:#fff; font-size:20px; font-weight:900; letter-spacing:-.02em;">
                      {{ $langue === 'en' ? 'Password reset request' : 'Demande de réinitialisation du mot de passe' }}
                    </h1>
                  </div>
                </td>
                <td align="right" style="vertical-align:top;">
                  <span class="badge">{{ now()->format('d/m/Y H:i') }}</span>
                </td>
              </tr>
            </table>
          </div>

          <!-- Bloc “Action requise” -->
          <div class="section" style="padding:18px 22px 8px 22px;">
            <div class="alert rounded-lg" style="display:flex; gap:12px; align-items:flex-start;">
              <div style="width:24px; height:24px; background:#1d4ed8; border-radius:6px; display:flex; align-items:center; justify-content:center; margin-top:2px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
                  <circle cx="12" cy="12" r="10"></circle>
                  <line x1="12" y1="8" x2="12" y2="8"></line>
                  <line x1="12" y1="12" x2="12" y2="16"></line>
                </svg>
              </div>
              <div class="text" style="font-size:15px; line-height:22px;">
                @if($langue === 'en')
                  We received a request to reset the password for <strong>{{ $email }}</strong>.<br>
                  To continue, click the button below. The link expires in <strong>{{ $expire }} minutes</strong>.
                @else
                  Nous avons reçu une demande de réinitialisation du mot de passe pour <strong>{{ $email }}</strong>.<br>
                  Pour continuer, cliquez sur le bouton ci-dessous. Le lien expire dans <strong>{{ $expire }} minutes</strong>.
                @endif
              </div>
            </div>
          </div>

          <!-- CTA -->
          <div class="section" style="padding:10px 22px 22px 22px;">
            <div style="text-align:center; margin-top:8px; margin-bottom:14px;">
              <a href="{{ $resetUrl }}" class="cta" target="_blank" rel="noreferrer">
                {{ $langue === 'en' ? 'Reset my password' : 'Réinitialiser mon mot de passe' }}
              </a>
            </div>

            <!-- Fallback -->
            <div class="rounded-xl" style="padding:12px; border:1px dashed #cbd5e1; background:#f8fafc; text-align:center;">
              <div class="muted" style="font-size:12px; margin-bottom:6px;">
                {{ $langue === 'en' ? 'Direct link:' : 'Lien direct :' }}
              </div>
              <a href="{{ $resetUrl }}" target="_blank" rel="noreferrer" class="mono" style="font-size:13px; word-break:break-all; color:#1d4ed8;">
                {{ $resetUrl }}
              </a>
            </div>

            <!-- Note sécurité -->
            <p class="muted" style="font-size:12px; margin-top:12px; text-align:center;">
              @if($langue === 'en')
                If you didn’t request this, you can safely ignore this email.
              @else
                Si vous n’êtes pas à l’origine de cette demande, vous pouvez ignorer cet e-mail.
              @endif
            </p>
          </div>
        </div>

        <!-- Footer -->
        <div class="rounded-xl border" style="margin-top:16px; padding:16px 18px; background:#ffffff; border-top-width:1px; text-align:center;">
          <div class="muted" style="font-size:13px; line-height:20px;">
            <div style="margin-bottom:6px;">
              {{ config('app.name') }} — {{ $langue === 'en' ? 'Security notice' : 'Alerte sécurité' }}
            </div>
            <div class="muted" style="font-size:12px;">
              &copy; {{ date('Y') }} {{ config('app.name') }} — {{ $langue === 'en' ? 'All rights reserved' : 'Tous droits réservés' }}
            </div>
          </div>
        </div>

      </div>
    </div>
  </body>
</html>
