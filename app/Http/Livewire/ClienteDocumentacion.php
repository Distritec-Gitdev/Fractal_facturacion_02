<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Imagenes;
use App\Models\ClientesContacto;
use Illuminate\Support\Facades\DB;
use App\Models\Token;
use Illuminate\Support\Facades\Route;

class ClienteDocumentacion extends Component
{
    use WithFileUploads;

    public $cliente;
    public $imagenes;
    public $data = [];
    public $successMessage = '';

  public bool $firmaHabilitada = false;

public function mount($cliente, $imagenes)
{
    $this->cliente  = $cliente;
    $this->imagenes = $imagenes;
    $this->firmaHabilitada = \App\Models\Token::where('id_cliente', $cliente->id_cliente)
        ->where('confirmacion_token', 2)
        ->exists();
}
    /**
     * Define qué campos se usan, según condiciones de cliente.
     */
    protected function getFieldKeys(): array
    {
        $fields = [
            'imagen_cedula_cara_delantera',
            'imagen_cedula_cara_trasera',
            'imagen_persona_con_telefono',
            'imagen_persona_con_cedula',
            'Carta_de_Garantías',
            'Carta_antifraude',
            'recibo_publico',
        ];

        $idPlataforma = optional($this->cliente->detalleClienteUltimo)->idplataforma;

        
        if ($this->cliente->ID_Tipo_credito == 2) {
            $fields = [
                'imagen_cedula_cara_delantera',
                'imagen_persona_con_telefono',
                'imagen_persona_con_cedula',
                'Carta_de_Garantías',
            ];
        } 

         
        // Si la plataforma NO está en [1,3,4,12], quitamos Carta_antifraude
        if (! in_array((int) $idPlataforma, [1, 3, 4, 12], true)) {
           $fields = array_diff($fields, ['Carta_antifraude']);
        }
        

        
        if ($this->cliente->ID_Identificacion_Cliente == 44) {
            $contacto = ClientesContacto::where('id_cliente', $this->cliente->id_cliente)->first();
            if (!($contacto && in_array($contacto->residencia_id_municipio, [54001, 54874]))) {
                $fields = array_diff($fields, ['recibo_publico']);
            }
        } else {
            $fields = array_diff($fields, ['recibo_publico']);
        }

        return $fields;
    }

    public function guardarDocumentos()
    {
        // Limpia errores previos
        $this->resetErrorBag();

        Log::debug('guardarDocumentos', ['data' => $this->data]);

        $fields = $this->getFieldKeys();

        // Validación: solo los campos sin archivo existente deben requerir subida
        foreach ($fields as $key) {
            // Si ya existe un archivo en DB y no cargamos uno nuevo, saltar
            if ($this->imagenes->{$key} && empty($this->data[$key])) {
                continue;
            }

            // Ahora es obligatorio upload
            if (empty($this->data[$key]) || !($this->data[$key] instanceof \Illuminate\Http\UploadedFile)) {
                $this->addError("data.{$key}", "El campo '{$key}' es obligatorio.");
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            Log::warning('Validation errors', $this->getErrorBag()->getMessages());
            return;
        }

        // Guardar archivos nuevos
        foreach ($fields as $key) {
            if (!empty($this->data[$key]) && $this->data[$key] instanceof \Illuminate\Http\UploadedFile) {
                $file      = $this->data[$key];
                $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
                $ext       = $file->getClientOriginalExtension();
                $filename  = "{$this->cliente->id_cliente}_{$key}_{$timestamp}.{$ext}";

                $file->storeAs("public/pdfs/{$this->cliente->id_cliente}", $filename);
                $this->imagenes->{$key} = $filename;

                Log::info("Stored {$key}", ['filename' => $filename]);
            }
            // si no cargó nuevo y ya existía, conserva el existente
        }

        $this->imagenes->save();
        Log::info('All images saved', $this->imagenes->toArray());


        // ✅ Si TODAS las casillas requeridas tienen archivo, marca gestion.contImagenes_ID_SI_NO = 1
        $fieldsRequeridos = $this->getFieldKeys(); // mismas reglas de tu componente
        $completo = collect($fieldsRequeridos)->every(function ($key) {
            // Debe existir un nombre de archivo persistido en $this->imagenes->{$key}
            return !empty($this->imagenes->{$key});
        });

        Log::debug('Validación de completitud de imágenes', [
            'cliente_id' => $this->cliente->id_cliente,
            'fields'     => $fieldsRequeridos,
            'completo'   => $completo,
        ]);

        if ($completo) {
            DB::table('gestion')->updateOrInsert(
                ['id_cliente' => $this->cliente->id_cliente],
                ['contImagenes_ID_SI_NO' => 1]
            );
            Log::info('Marcado contImagenes_ID_SI_NO = 1 en gestion', [
                'cliente_id' => $this->cliente->id_cliente,
            ]);
        }

        // Limpiar uploads temporales
        $this->data = [];
        $this->successMessage = '✅ Documentos guardados correctamente.';
    }

    public function render()
    {
        return view('livewire.modals.documentation-modal');
    }
}