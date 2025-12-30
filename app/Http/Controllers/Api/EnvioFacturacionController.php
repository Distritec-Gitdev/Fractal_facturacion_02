<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DistritecTokenService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\ReciboFacturaCacheService;
use Illuminate\Http\Request;

class EnvioFacturacionController extends Controller
{
    protected DistritecTokenService $tokenService;

    public function __construct(DistritecTokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * Headers base que Yeminus exige
     */
    protected function baseHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'id_empresa'   => env('DISTRITEC_EMPRESA'),
            'usuario'      => env('DISTRITEC_USUARIO'),
        ];
    }

    /**
     * Log estándar de respuesta
     */
    protected function logResponse(string $tag, Response $response): void
    {
        Log::info("{$tag} → RESPONSE", [
            'status' => $response->status(),
            'body'   => $response->json() ?? $response->body(),
        ]);
    }

    /**
     * POST con token + retry si 401
     */
    protected function postWithTokenRetry(string $envKey, array $payload): array
    {
        $url = env($envKey);

        if (! $url) {
            throw new \RuntimeException("{$envKey} no está configurada en el .env");
        }

        $headers = $this->baseHeaders();

        Log::info("{$envKey} → REQUEST", [
            'url'     => $url,
            'payload' => $payload,
        ]);

        // 1) Token cacheado
        $token = $this->tokenService->getToken();

        $response = Http::timeout(60)
            ->withToken($token)
            ->withHeaders($headers)
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

        $this->logResponse($envKey, $response);

        // 2) Retry con token nuevo si 401
        if ($response->status() === 401) {
            Log::warning("{$envKey} → 401 con token cacheado, reintentando con token nuevo...");

            $token = $this->tokenService->getToken(true);

            $response = Http::timeout(60)
                ->withToken($token)
                ->withHeaders($headers)
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);

            $this->logResponse("{$envKey} (retry)", $response);
        }

        if (! $response->successful()) {
            Log::error("{$envKey} → ERROR", [
                'status' => $response->status(),
                'body'   => $response->json() ?? $response->body(),
            ]);

            throw new \RuntimeException("Error API Distritec ({$envKey}), status: " . $response->status());
        }

        return $response->json() ?? [
            'raw'    => $response->body(),
            'status' => $response->status(),
        ];
    }

    /**
     * Llama a la API de Yeminus para crear la factura.
     */
    public function crearFactura(array $payload): array
    {
        return $this->postWithTokenRetry('API_CREAR_FACTURA', $payload);
    }

    /**
     * Llama a la API de Yeminus para pagar la factura.
     */
    public function pagarFactura(array $payload): array
    {
        return $this->postWithTokenRetry('API_PAGAR_FACTURA', $payload);
    }
}
