@component('mail::message')
# Tu código de acceso

Tu token es **{{ $token }}**.

@component('mail::button', ['url' => $url])
Verificar Token
@endcomponent

Este enlace expirará en 5 minutos.

Gracias,<br>
{{ config('app.name') }}
@endcomponent
