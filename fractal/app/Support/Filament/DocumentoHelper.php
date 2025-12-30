<?php
declare(strict_types=1);

namespace App\Support;

class DocumentoHelper
{
    public static function mapTipoDocumentoToCodigo(string $tipo): string
    {
        return match (strtoupper(trim($tipo))) {
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
