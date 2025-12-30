<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Consignacion;
use App\Models\Cliente;

class Consignaciones extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $consignacion_id;
    public $numero_consignacion;
    public $cliente_id;
    public $fecha_consignacion;
    public $monto;
    public $banco;
    public $referencia;
    public $estado = 'pendiente';
    public $observaciones;

    protected $paginationTheme = 'bootstrap';

    protected $rules = [
        'cliente_id' => 'required|exists:clientes,id',
        'fecha_consignacion' => 'required|date',
        'monto' => 'required|numeric|min:0',
        'banco' => 'nullable|string|max:255',
        'referencia' => 'nullable|string|max:255',
        'observaciones' => 'nullable|string',
    ];

    public function render()
    {
        $consignaciones = Consignacion::with('cliente')
            ->when($this->search, function($query) {
                $query->where('numero_consignacion', 'like', '%' . $this->search . '%')
                      ->orWhere('referencia', 'like', '%' . $this->search . '%')
                      ->orWhereHas('cliente', function($q) {
                          $q->where('nombre', 'like', '%' . $this->search . '%');
                      });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $clientes = Cliente::orderBy('nombre')->get();

        return view('livewire.consignaciones', [
            'consignaciones' => $consignaciones,
            'clientes' => $clientes
        ]);
    }

    public function create()
    {
        $this->resetInputFields();
        $this->showModal = true;
    }

    public function store()
    {
        $this->validate();

        $data = [
            'numero_consignacion' => 'CONS-' . date('Ymd') . '-' . rand(1000, 9999),
            'cliente_id' => $this->cliente_id,
            'fecha_consignacion' => $this->fecha_consignacion,
            'monto' => $this->monto,
            'banco' => $this->banco,
            'referencia' => $this->referencia,
            'estado' => $this->estado,
            'observaciones' => $this->observaciones,
        ];

        if ($this->consignacion_id) {
            $consignacion = Consignacion::find($this->consignacion_id);
            $consignacion->update($data);
            session()->flash('message', 'Consignación actualizada exitosamente');
        } else {
            Consignacion::create($data);
            session()->flash('message', 'Consignación creada exitosamente');
        }

        $this->closeModal();
    }

    public function edit($id)
    {
        $consignacion = Consignacion::findOrFail($id);
        $this->consignacion_id = $consignacion->id;
        $this->numero_consignacion = $consignacion->numero_consignacion;
        $this->cliente_id = $consignacion->cliente_id;
        $this->fecha_consignacion = $consignacion->fecha_consignacion->format('Y-m-d');
        $this->monto = $consignacion->monto;
        $this->banco = $consignacion->banco;
        $this->referencia = $consignacion->referencia;
        $this->estado = $consignacion->estado;
        $this->observaciones = $consignacion->observaciones;
        $this->showModal = true;
    }

    public function delete($id)
    {
        Consignacion::find($id)->delete();
        session()->flash('message', 'Consignación eliminada exitosamente');
    }

    public function verificar($id)
    {
        Consignacion::find($id)->update(['estado' => 'verificada']);
        session()->flash('message', 'Consignación verificada');
    }

    public function rechazar($id)
    {
        Consignacion::find($id)->update(['estado' => 'rechazada']);
        session()->flash('message', 'Consignación rechazada');
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetInputFields();
    }

    private function resetInputFields()
    {
        $this->consignacion_id = null;
        $this->numero_consignacion = '';
        $this->cliente_id = '';
        $this->fecha_consignacion = '';
        $this->monto = '';
        $this->banco = '';
        $this->referencia = '';
        $this->estado = 'pendiente';
        $this->observaciones = '';
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }
}