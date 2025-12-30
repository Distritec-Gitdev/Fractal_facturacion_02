<?php

namespace App\Repositories\Interfaces;

interface TercerosRepositoryInterface
{
    public function buscarPorCedula(string $cedula, string $tipoDocumento);
    public function crearTercero(array $datos);

     // 👉 NUEVO: búsqueda por query (cedula o nombre) devolviendo TODAS las coincidencias
    public function buscarCoincidencias(string $query): array;
} 