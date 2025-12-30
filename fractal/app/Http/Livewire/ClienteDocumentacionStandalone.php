<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Imagenes;
use App\Models\Cliente;
use App\Models\ClientesContacto;
use Illuminate\Support\Facades\DB;

class ClienteDocumentacionStandalone extends Component
{
    use WithFileUploads;

    public Cliente $cliente;
    public Imagenes $imagenes;
    public array $data = [];
    public string $successMessage = '';
    public array $fields = [];

    public function mount(Cliente $cliente)
    {
        $this->cliente = $cliente;
        $this->imagenes = Imagenes::firstOrNew(['id_cliente' => $cliente->id_cliente]);
        $this->fields = $this->getFieldKeys();

        Log::debug('📦 ClienteDocumentacionStandalone mounted', [
            'cliente_id' => $this->cliente->id_cliente,
            'initialData' => $this->data,
        ]);

        $this->dispatch('component-mounted', [
            'cliente_id' => $this->cliente->id_cliente,
        ]);
    }

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
        $this->resetErrorBag();
        Log::debug('🔄 guardarDocumentos invoked', ['data' => $this->data]);

        foreach ($this->fields as $key) {
            if ($this->imagenes->{$key} && empty($this->data[$key])) {
                continue;
            }
            if (empty($this->data[$key]) || ! ($this->data[$key] instanceof UploadedFile)) {
                $this->addError("data.{$key}", "El campo '{$key}' es obligatorio.");
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            Log::warning('⚠️ Validation errors', $this->getErrorBag()->getMessages());
            $this->dispatch('validation-errors', $this->getErrorBag()->getMessages());
            return;
        }

        foreach ($this->fields as $key) {
            if (! empty($this->data[$key]) && $this->data[$key] instanceof UploadedFile) {
                $file      = $this->data[$key];
                $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
                $ext       = $file->getClientOriginalExtension();
                $filename  = "{$this->cliente->id_cliente}_{$key}_{$timestamp}.{$ext}";

                $file->storeAs("public/pdfs/{$this->cliente->id_cliente}", $filename);
                $this->imagenes->{$key} = $filename;

                Log::info("🗂 Stored {$key}", ['filename' => $filename]);
            }
        }

        $this->imagenes->save();
        Log::info('✅ All images saved', $this->imagenes->toArray());

        
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

        $this->data           = [];
        $this->successMessage = '✅ Documentos guardados correctamente.';

        $this->dispatch('documentos-guardados', [
            'message' => $this->successMessage,
        ]);
    }

    public function render()
    {
        Log::debug('🏷 Rendering ClienteDocumentacionStandalone view');
        $this->dispatch('component-rendered', [
            'time' => now()->toDateTimeString(),
        ]);

        return view('livewire.cliente-documentacion-standalone')
            ->layout('components.layouts.app');
    }
}
