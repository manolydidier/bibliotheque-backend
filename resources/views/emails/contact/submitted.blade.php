<!doctype html>
<html lang="fr">
  <head>
    <meta charset="utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nouveau message de contact</title>
    <style>
      /* Reset & Base */
      * { box-sizing: border-box; }
      body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; }
      
      /* Dark mode support */
      @media (prefers-color-scheme: dark) {
        .email-body { background-color: #0f172a !important; }
        .card { background-color: #0b1220 !important; border-color: #1e293b !important; }
        .card-header { background: linear-gradient(135deg, #3b82f6, #06b6d4) !important; }
        .title { color: #f8fafc !important; }
        .text { color: #cbd5e1 !important; }
        .label { color: #e5e7eb !important; }
        .value { color: #e5e7eb !important; }
        .footer { background-color: #020617 !important; border-top-color: #111827 !important; }
        .footer-text { color: #94a3b8 !important; }
      }
      
      /* Responsive */
      @media screen and (max-width: 640px) {
        .container { width: 100% !important; padding: 12px !important; }
        .card { border-radius: 14px !important; }
        .card-header { padding: 20px 16px !important; }
        .content-padding { padding: 20px 16px !important; }
        .title { font-size: 20px !important; line-height: 26px !important; }
      }
    </style>
  </head>
  <body style="margin:0; padding:0;">
    @php
      // Sécurisation du message (retours à la ligne)
      $safeMessage = nl2br(e($m->message ?? ''));
    @endphp

    <!-- Email Body -->
    <div class="email-body" style="background-color:#f1f5f9; padding:32px 16px; min-height:100vh;">
      <!-- Container -->
      <div class="container" style="max-width:600px; margin:0 auto; width:100%;">
        
        <!-- Main Card -->
        <div class="card" style="background-color:#ffffff; border-radius:20px; box-shadow:0 20px 50px rgba(15,23,42,0.08); overflow:hidden; border:1px solid rgba(226,232,240,0.9);">
          
          <!-- Header with Gradient -->
          <div class="card-header" style="background:linear-gradient(135deg, #2563eb 0%, #0ea5e9 100%); padding:28px 24px; position:relative;">
            <!-- Decorative Pattern -->
            <div style="position:absolute; top:0; right:0; width:120px; height:120px; background:radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px); background-size:20px 20px; opacity:0.3;"></div>
            
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="position:relative; z-index:1;">
              <tr>
                <td style="vertical-align:middle;">
                  <div style="display:flex; align-items:center; margin-bottom:8px;">
                    <!-- Icon -->
                    <div style="width:32px; height:32px; background:rgba(255,255,255,0.2); border-radius:8px; display:flex; align-items:center; justify-content:center; margin-right:12px;">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#ffffff;">
                        <rect x="3" y="5" width="18" height="14" rx="2" ry="2"></rect>
                        <polyline points="3,7 12,13 21,7"></polyline>
                      </svg>
                    </div>
                    <h1 class="title" style="color:#ffffff; font-size:20px; font-weight:700; margin:0; letter-spacing:-0.02em;">
                      Nouveau message de contact
                    </h1>
                  </div>
                  <div style="color:rgba(255,255,255,0.85); font-size:13px; margin-top:2px;">
                    Reçu via le formulaire public de la bibliothèque
                  </div>
                </td>
                <td align="right" style="vertical-align:top;">
                  <div style="color:rgba(255,255,255,0.9); font-size:12px; font-weight:500; padding:6px 12px; background:rgba(15,23,42,0.35); border-radius:999px; backdrop-filter:blur(10px);">
                    {{ now()->format('d/m/Y à H:i') }}
                  </div>
                </td>
              </tr>
            </table>
          </div>

          <!-- Coordonnées expéditeur -->
          <div class="content-padding" style="padding:24px 24px 8px 24px;">
            <div style="margin-bottom:12px;">
              <span class="label" style="display:block; font-size:12px; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; color:#6b7280; margin-bottom:4px;">
                Coordonnées de l’expéditeur
              </span>
              <div style="background:#f8fafc; border-radius:14px; padding:14px 16px; border:1px solid #e5e7eb;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                  <tr>
                    <td style="padding:4px 0;">
                      <span class="label" style="font-size:13px; color:#6b7280; display:inline-block; min-width:80px;">Nom :</span>
                      <span class="value" style="font-size:14px; color:#0f172a; font-weight:500;">
                        {{ $m->name }}
                      </span>
                    </td>
                  </tr>
                  <tr>
                    <td style="padding:4px 0;">
                      <span class="label" style="font-size:13px; color:#6b7280; display:inline-block; min-width:80px;">Email :</span>
                      <span class="value" style="font-size:14px; color:#1d4ed8; font-weight:500;">
                        {{ $m->email }}
                      </span>
                    </td>
                  </tr>
                  <tr>
                    <td style="padding:4px 0;">
                      <span class="label" style="font-size:13px; color:#6b7280; display:inline-block; min-width:80px;">Type :</span>
                      <span class="value" style="font-size:14px; color:#0f172a;">
                        {{ $m->type ?: 'Non précisé' }}
                      </span>
                    </td>
                  </tr>
                </table>
              </div>
            </div>
          </div>

          <!-- Sujet -->
          <div class="content-padding" style="padding:0 24px 8px 24px;">
            <span class="label" style="display:block; font-size:12px; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; color:#6b7280; margin-bottom:6px;">
              Sujet
            </span>
            <div style="border-radius:12px; border:1px solid #e5e7eb; padding:10px 14px; background:linear-gradient(90deg,#f9fafb,#f3f4f6);">
              <div class="text" style="font-size:15px; line-height:22px; color:#111827; font-weight:600;">
                {{ $m->subject }}
              </div>
            </div>
          </div>

          <!-- Message -->
          <div class="content-padding" style="padding:12px 24px 20px 24px;">
            <span class="label" style="display:block; font-size:12px; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; color:#6b7280; margin-bottom:6px;">
              Message
            </span>
            <div style="background:#f8fafc; border-left:4px solid #0ea5e9; padding:14px 16px; border-radius:0 12px 12px 12px;">
              <div class="text" style="font-size:14px; line-height:22px; color:#374151;">
                {!! $safeMessage !!}
              </div>
            </div>
          </div>

          <!-- Infos techniques (IP / User Agent) -->
          @if(!empty($m->ip_address) || !empty($m->user_agent))
            <div class="content-padding" style="padding:0 24px 20px 24px;">
              <span class="label" style="display:block; font-size:12px; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; color:#6b7280; margin-bottom:6px;">
                Informations techniques
              </span>
              <div style="border-radius:12px; border:1px dashed #cbd5e1; padding:10px 12px; background:#f9fafb;">
                @if(!empty($m->ip_address))
                  <div style="font-size:12px; color:#4b5563; margin-bottom:4px;">
                    <strong>IP :</strong> {{ $m->ip_address }}
                  </div>
                @endif
                @if(!empty($m->user_agent))
                  <div style="font-size:12px; color:#4b5563; margin-top:4px;">
                    <strong>User agent :</strong><br>
                    <span style="font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size:11px; color:#6b7280;">
                      {{ $m->user_agent }}
                    </span>
                  </div>
                @endif
              </div>
            </div>
          @endif

        </div>

        <!-- Footer -->
        <div class="footer" style="background:#ffffff; margin-top:18px; padding:16px 24px; border-radius:16px; border:1px solid #e2e8f0; text-align:center;">
          <div class="footer-text" style="color:#64748b; font-size:12px; line-height:18px;">
            <div style="margin-bottom:6px;">
              📧 Cet email a été généré automatiquement depuis le formulaire de contact public.
            </div>
            <div style="color:#94a3b8; font-size:11px;">
              &copy; {{ date('Y') }} {{ config('app.name') }} — Tous droits réservés.
            </div>
          </div>
        </div>

        <!-- Spacer for mobile -->
        <div style="height:24px;"></div>
      </div>
    </div>
  </body>
</html>
