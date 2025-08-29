@component('mail::message')
# {{ $lang === 'fr' ? 'Code de vérification' : 'Verification Code' }}

{{ $lang === 'fr'
  ? "Votre code est : **{$code}**. Il expire dans {$ttl} secondes."
  : "Your code is: **{$code}**. It expires in {$ttl} seconds."
}}

@component('mail::panel')
{{ $code }}
@endcomponent

{{ $lang === 'fr' ? 'Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet e-mail.' : 'If you did not request this, you can ignore this email.' }}

{{ config('app.name') }}
@endcomponent