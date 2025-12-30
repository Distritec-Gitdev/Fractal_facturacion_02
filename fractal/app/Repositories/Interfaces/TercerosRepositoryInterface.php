<?php

namespace App\Repositories\Interfaces;

interface TercerosRepositoryInterface
{
    public function buscarPorCedula(string $cedula, string $tipoDocumento);
    public function crearTercero(array $datos);
} 