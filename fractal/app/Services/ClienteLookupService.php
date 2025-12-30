<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

class ClienteLookupService
{
    public function __construct(
        private readonly TercerosApiService $api
    ) {}

    /**
     * Verifica si un tercero existe en la API.
     */
    public function exists(?string $documento, ?string $tipoDoc = 'CC'): bool
    {
        $documento = trim((string) $documento);
        $tipoDoc   = $tipoDoc ? trim($tipoDoc) : 'CC';

        if ($documento === '') {
            return false;
        }

        try {
            $codigoTipoDoc = self::mapTipoDocumentoToCodigo($tipoDoc);
            $resp   = $this->api->buscarPorCedula($documento, $codigoTipoDoc);
            $found  = isset($resp['datos']['nits'][0]);

            Log::info('ClienteLookupService.exists', [
                'documento' => $documento,
                'tipoDoc'   => $tipoDoc,
                'codigo'    => $codigoTipoDoc,
                'found'     => $found,
            ]);

            return $found;
        } catch (Throwable $e) {
            Log::error('ClienteLookupService.exists error: '.$e->getMessage(), [
                'exception' => $e,
                'documento' => $documento,
                'tipoDoc'   => $tipoDoc,
            ]);
            return false;
        }
    }

    /**
     * Devuelve los datos completos de un tercero, o null si no existe.
     */
    public function fetch(?string $documento, ?string $tipoDoc = 'CC'): ?array
    {
        $documento = trim((string) $documento);
        if ($documento === '') {
            return null;
        }

        try {
            $codigoTipoDoc = self::mapTipoDocumentoToCodigo($tipoDoc ?? 'CC');
            $resp = $this->api->buscarPorCedula($documento, $codigoTipoDoc);
            return $resp['datos']['nits'][0] ?? null;
        } catch (Throwable $e) {
            Log::error('ClienteLookupService.fetch error: '.$e->getMessage(), [
                'exception' => $e,
                'documento' => $documento,
                'tipoDoc'   => $tipoDoc,
            ]);
            return null;
        }
    }

    /**
     * Mapea el tipo de documento (sigla) al código esperado por la API.
     */
    public static function mapTipoDocumentoToCodigo(string $tipoDoc): string
    {
        return match (strtoupper(trim($tipoDoc))) {
            'CC'  => '13',
            'TI'  => '12',
            'CE'  => '22',
            'PA'  => '41',
            'RC'  => '11',
            'NIT' => '31',
            default => '13',
        };
    }
}
