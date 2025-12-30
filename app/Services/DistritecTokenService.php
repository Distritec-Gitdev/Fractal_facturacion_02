<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DistritecTokenService
{
    protected string $cacheKey = 'yeminus_dynamic_token';

    /**
     * Obtiene el token dinámico desde Yeminus.
     *
     * - Usa las mismas variables que Cartera:
     *   - DISTRITEC_TOKEN_URL
     *   - DISTRITEC_API_USERNAME
     *   - DISTRITEC_API_PASSWORD
     * - Mismo grant_type: password
     * - Mismo formato: asForm()
     * - Usa cache igual que en Cartera.
     */
    public function getToken(bool $forceRefresh = false): string
    {
        if (! $forceRefresh) {
            $cached = Cache::get($this->cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        $url      = env('DISTRITEC_TOKEN_URL');
        $username = env('DISTRITEC_API_USERNAME');
        $password = env('DISTRITEC_API_PASSWORD');

        if (! $url || ! $username || ! $password) {
            Log::error('DistritecTokenService → Faltan variables de entorno para token dinámico', [
                'url_null'      => empty($url),
                'username_null' => empty($username),
                'password_null' => empty($password),
            ]);

            throw new \RuntimeException('No se puede solicitar token dinámico, faltan variables de entorno.');
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post($url, [
                    'username'   => $username,
                    'password'   => $password,
                    'grant_type' => 'password',
                ]);

            Log::info('DistritecTokenService: respuesta cruda de endpoint de token', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException(
                    'Error al solicitar token dinámico, status: ' . $response->status()
                );
            }

            $accessToken = $response->json('access_token');

            if (! $accessToken) {
                Log::error('DistritecTokenService: respuesta sin access_token', [
                    'json' => $response->json(),
                ]);

                throw new \RuntimeException('No se encontró access_token en la respuesta del token dinámico');
            }

            // Igual que en Cartera
            $expiresIn = (int) ($response->json('expires_in') ?? 3600);
            $ttl       = max($expiresIn - 60, 60);

            Cache::put($this->cacheKey, $accessToken, $ttl);

            return $accessToken;

        } catch (\Throwable $e) {
            Log::error('DistritecTokenService: excepción al solicitar token dinámico', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
