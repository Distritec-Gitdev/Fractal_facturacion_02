<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Imagenes;
use App\Models\ClientesContacto;

class ClienteDocumentacion extends Component
{
    use WithFileUploads;

    public $cliente;
    public $imagenes;
    public $data = [];
    public $successMessage = '';

    public function mount($cliente, $imagenes)
    {
        $this->cliente  = $cliente;
        $this->imagenes = $imagenes;
        Log::debug('Mounted ClienteDocumentacion', ['cliente_id' => $cliente->id_cliente]);
    }

    /**
     * Devuelve los campos activos según las condiciones del cliente
     */
    protected function computeFields(): array
    {
        $fields = [
            'imagen_cedula_cara_delantera' => 'Cédula Cara Delantera',
            'imagen_cedula_cara_trasera'   => 'Cédula Cara Trasera',
            'imagen_persona_con_telefono'  => 'Persona con Teléfono',
            'imagen_persona_con_cedula'    => 'Persona con Cédula',
            'Carta_de_Garantías'           => 'Carta de Garantías',
            'Carta_antifraude'             => 'Carta Antifraude',
            'recibo_publico'               => 'Recibo Público',
        ];

        if ($this->cliente->ID_Tipo_credito == 2) {
            return [
                'imagen_cedula_cara_delantera' => 'Cédula Cara Delantera (ambas caras)',
                'imagen_persona_con_telefono'  => 'Persona con Teléfono',
                'imagen_persona_con_cedula'    => 'Persona con Cédula',
                'Carta_de_Garantías'           => 'Carta de Garantías',
            ];
        }

        if ($this->cliente->ID_Identificacion_Cliente == 44) {
            $contacto = ClientesContacto::where('id_cliente', $this->cliente->id_cliente)->first();
            if (! ($contacto && in_array($contacto->residencia_id_municipio, [54001, 54874]))) {
                unset($fields['recibo_publico']);
            }
        } else {
            unset($fields['recibo_publico']);
        }

        // Se mantienen Carta_de_Garantías y Carta_antifraude si no se ocultaron explícitamente
        return $fields;
    }

    public function guardarDocumentos()
    {
        // Reset de errores previos
        $this->resetErrorBag();

        $fields = array_keys($this->computeFields());
        Log::debug('Validating fields:', $fields);

        // Validar que cada campo tenga archivo
        foreach ($fields as $field) {
            if (empty($this->data[$field]) || ! ($this->data[$field] instanceof \Illuminate\Http\UploadedFile)) {
                $this->addError("data.{$field}", "El campo '{$field}' es obligatorio.");
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            Log::warning('Validation errors', $this->getErrorBag()->getMessages());
            return;
        }

        // Guardar archivos
        foreach ($fields as $field) {
            $file      = $this->data[$field];
            $ts        = Carbon::now()->format('Y-m-d_H-i-s');
            $ext       = $file->getClientOriginalExtension();
            $filename  = "{$this->cliente->id_cliente}_{$field}_{$ts}.{$ext}";

            $file->storeAs("public/pdfs/{$this->cliente->id_cliente}", $filename);
            $this->imagenes->{$field} = $filename;
            Log::info("File stored for {$field}", ['filename' => $filename]);
        }

        $this->imagenes->save();
        Log::info('All images saved', $this->imagenes->toArray());

        // Limpiar inputs y mostrar mensaje
        $this->data           = [];
        $this->successMessage = '✅ Documentos guardados correctamente.';
    }

    public function render()
    {
        return view('livewire.modals.documentation-modal', [
            'fields' => $this->computeFields(),
        ]);
    }
}