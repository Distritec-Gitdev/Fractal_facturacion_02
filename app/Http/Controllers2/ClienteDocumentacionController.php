<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cliente;
use App\Models\Imagenes;
use Carbon\Carbon;

class ClienteDocumentacionController extends Controller
{
    public function store(Request $request, Cliente $cliente)
    {
        // Definimos los campos y etiquetas para mensajes
        $fields = [
            'imagen_cedula_cara_delantera' => 'Cédula Cara Delantera',
            'imagen_cedula_cara_trasera'   => 'Cédula Cara Trasera',
            'imagen_persona_con_telefono'  => 'Persona con Teléfono',
            'imagen_persona_con_cedula'    => 'Persona con Cédula',
            'recibo_publico'               => 'Recibo Público',
        ];

        // Regla: todos obligatorios, máximo 10MB, jpeg/png/pdf
        $rules = [];
        foreach ($fields as $key => $label) {
            $rules[$key] = 'required|file|mimes:jpg,jpeg,png,pdf|max:10240';
        }

        $validated = $request->validate($rules);

        // Preparamos el modelo
        $imagenes = Imagenes::firstOrNew(['id_cliente' => $cliente->id_cliente]);

        // Guardado de archivos con nombre: {id}_{campo}_{YYYY-MM-DD_HH-MM-SS}.{ext}
        foreach ($validated as $field => $file) {
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $ext       = $file->getClientOriginalExtension();
            $filename  = "{$cliente->id_cliente}_{$field}_{$timestamp}.{$ext}";
            $file->storeAs("public/pdfs/{$cliente->id_cliente}", $filename);
            $imagenes->{$field} = $filename;
        }

        $imagenes->save();

        return back()->with('success', '✅ Documentos guardados correctamente.');
    }
}
