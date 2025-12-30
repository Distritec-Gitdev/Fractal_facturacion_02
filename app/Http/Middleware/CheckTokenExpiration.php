<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Token;

class CheckTokenExpiration
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->route('token');
        $tokenModel = Token::where('token', $token)->first();

        if (!$tokenModel) {
            return response('El enlace es inválido.', 403);
        }
        if (isset($tokenModel->expires_at) && $tokenModel->expires_at < now()) {
            return response('El enlace ha expirado.', 403);
        }
        if (isset($tokenModel->confirmacion_token) && $tokenModel->confirmacion_token == 1) {
            return response('El enlace ya fue utilizado.', 403);
        }
        // Si quieres marcarlo como usado aquí:
        // $tokenModel->update(['confirmacion_token' => 1]);
        return $next($request);
    }
}