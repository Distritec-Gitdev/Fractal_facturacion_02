<?php

namespace App\Helpers;

use App\Models\ZDepartamentos;
use App\Models\ZMunicipios;

class FormHelpers
{
    public static function departamentosOptions()
    {
        return ZDepartamentos::all()->pluck('name_departamento', 'id');
    }

    public static function municipiosOptions($departamentoId = null)
    {
        if ($departamentoId) {
            return ZMunicipios::where('departamento_id', $departamentoId)->pluck('name_municipio', 'id');
        }
        return ZMunicipios::all()->pluck('name_municipio', 'id');
    }
} 