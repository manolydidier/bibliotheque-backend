<!doctype html>
<html lang="fr">
  <head>
    <meta charset="utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Partage d'article</title>
    <style>
      /* Reset & Base */
      * { box-sizing: border-box; }
      body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; }
      
      /* Dark mode support */
      @media (prefers-color-scheme: dark) {
        .email-body { background-color: #0f172a !important; }
        .card { background-color: #1e293b !important; }
        .card-header { background: linear-gradient(135deg, #3b82f6, #06b6d4) !important; }
        .title { color: #f8fafc !important; }
        .text { color: #cbd5e1 !important; }
        .footer { background-color: #1e293b !important; border-top-color: #334155 !important; }
        .footer-text { color: #94a3b8 !important; }
      }
      
      /* Responsive */
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
    <!-- Email Body -->
    <div class="email-body" style="background-color:#f1f5f9; padding:32px 16px; min-height:100vh;">
      <!-- Container -->
      <div class="container" style="max-width:600px; margin:0 auto; width:100%;">
        
        <!-- Main Card -->
        <div class="card" style="background-color:#ffffff; border-radius:20px; box-shadow:0 20px 50px rgba(15,23,42,0.08); overflow:hidden; border:1px solid rgba(226,232,240,0.8);">
          
          <!-- Header with Gradient -->
          <div class="card-header" style="background:linear-gradient(135deg, #2563eb 0%, #0ea5e9 100%); padding:32px 28px; position:relative;">
            <!-- Decorative Pattern -->
            <div style="position:absolute; top:0; right:0; width:120px; height:120px; background:radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px); background-size:20px 20px; opacity:0.3;"></div>
            
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="position:relative; z-index:1;">
              <tr>
                <td style="vertical-align:middle;">
                  <div style="display:flex; align-items:center; margin-bottom:8px;">
                    <!-- Icon -->
                    <div style="width:32px; height:32px; background:rgba(255,255,255,0.2); border-radius:8px; display:flex; align-items:center; justify-content:center; margin-right:12px;">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#ffffff;">
                        <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
                        <polyline points="16,6 12,2 8,6"/>
                        <line x1="12" y1="2" x2="12" y2="15"/>
                      </svg>
                    </div>
                    <h1 style="color:#ffffff; font-size:20px; font-weight:700; margin:0; letter-spacing:-0.02em;">
                      Article partagé avec vous
                    </h1>
                  </div>
                </td>
                <td align="right" style="vertical-align:top;">
                  <div style="color:rgba(255,255,255,0.8); font-size:13px; font-weight:500; padding:6px 12px; background:rgba(255,255,255,0.15); border-radius:20px; backdrop-filter:blur(10px);">
                    {{ now()->format('d/m/Y à H:i') }}
                  </div>
                </td>
              </tr>
            </table>
          </div>

          <!-- Article Content -->
          @if(!empty($article))
            <!-- Title -->
            <div class="content-padding" style="padding:32px 28px 16px 28px;">
              <h2 class="title" style="margin:0; font-size:24px; line-height:32px; color:#0f172a; font-weight:700; letter-spacing:-0.02em;">
                {{ $article->title }}
              </h2>
            </div>
            
            <!-- Excerpt -->
            @if(!empty($article->excerpt))
              <div class="content-padding" style="padding:0 28px 20px 28px;">
                <div style="background:linear-gradient(90deg, #f1f5f9, #e2e8f0); height:1px; margin-bottom:16px;"></div>
                <p class="text" style="margin:0; color:#64748b; font-size:16px; line-height:24px; font-style:italic;">
                  "{{ $article->excerpt }}"
                </p>
              </div>
            @endif
          @endif

          <!-- Custom Message Body -->
          @php
            $safeBody = nl2br(e($body ?? ''));
          @endphp
          @if(!empty($safeBody))
            <div class="content-padding" style="padding:0 28px 24px 28px;">
              @if(!empty($article))
                <div style="background:linear-gradient(90deg, #f1f5f9, #e2e8f0); height:1px; margin-bottom:20px;"></div>
              @endif
              
              <!-- Message Label -->
              <div style="display:flex; align-items:center; margin-bottom:12px;">
                <div style="width:20px; height:20px; background:#e0f2fe; border-radius:6px; display:flex; align-items:center; justify-content:center; margin-right:8px;">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#0284c7;">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                  </svg>
                </div>
                <span style="color:#64748b; font-size:13px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">Message personnel</span>
              </div>
              
              <div style="background:#f8fafc; border-left:4px solid #0ea5e9; padding:16px 20px; border-radius:0 12px 12px 0;">
                <div class="text" style="font-size:15px; line-height:22px; color:#374151;">{!! $safeBody !!}</div>
              </div>
            </div>
          @endif

          <!-- Call to Action -->
          @php
            $ctaUrl = $url ?? ($article->getUrl() ?? null);
          @endphp
          @if(!empty($ctaUrl))
            <div class="content-padding" style="padding:8px 28px 32px 28px;">
              <!-- CTA Button -->
              <div style="text-align:center; margin-bottom:16px;">
                <a href="{{ $ctaUrl }}" target="_blank" rel="noreferrer" class="cta-button"
                   style="display:inline-block; padding:16px 32px; background:linear-gradient(135deg, #2563eb, #1d4ed8); color:#ffffff; text-decoration:none; border-radius:12px; font-weight:600; font-size:15px; box-shadow:0 4px 12px rgba(37,99,235,0.3); transition:all 0.2s ease; letter-spacing:0.01em;">
                  📖 Lire l'article complet
                </a>
              </div>
              
              <!-- Fallback Link -->
              <div style="text-align:center; padding:12px; background:#f8fafc; border-radius:12px; border:1px dashed #cbd5e1;">
                <div style="font-size:12px; color:#64748b; margin-bottom:4px;">Lien direct :</div>
                <a href="{{ $ctaUrl }}" target="_blank" rel="noreferrer" 
                   style="color:#2563eb; font-size:13px; word-break:break-all; font-family:ui-monospace, 'SF Mono', Consolas, monospace;">
                  {{ $ctaUrl }}
                </a>
              </div>
            </div>
          @endif

        </div>

        <!-- Footer -->
        <div class="footer" style="background:#ffffff; margin-top:20px; padding:20px 28px; border-radius:16px; border:1px solid #e2e8f0; text-align:center;">
          <div class="footer-text" style="color:#64748b; font-size:13px; line-height:18px;">
            <div style="margin-bottom:8px;">
              📧 Cet email vous a été envoyé via notre système de partage
              @if(!empty($article))
                <span style="color:#94a3b8;">(Réf. #{{ $article->id }})</span>
              @endif
            </div>
            <div style="color:#94a3b8; font-size:12px;">
              &copy; {{ date('Y') }} {{ config('app.name') }} — Tous droits réservés
            </div>
          </div>
        </div>

        <!-- Spacer for mobile -->
        <div style="height:32px;"></div>
      </div>
    </div>
  </body>
</html>