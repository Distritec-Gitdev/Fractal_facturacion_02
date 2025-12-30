@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>PDFs Generados</h4>
                </div>
                <div class="card-body">
                    <div id="pdfs-list">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    fetch('/admin/pdfs')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('pdfs-list');
            
            if (data.length === 0) {
                container.innerHTML = '<div class="alert alert-info">No hay PDFs generados aún.</div>';
                return;
            }
            
            let html = '<div class="table-responsive"><table class="table table-striped">';
            html += '<thead><tr><th>Nombre del Archivo</th><th>Tamaño</th><th>Fecha de Creación</th><th>Acciones</th></tr></thead>';
            html += '<tbody>';
            
            data.forEach(pdf => {
                const sizeInKB = Math.round(pdf.tamaño / 1024);
                html += `<tr>
                    <td>${pdf.nombre}</td>
                    <td>${sizeInKB} KB</td>
                    <td>${pdf.fecha_creacion}</td>
                    <td>
                        <a href="/admin/pdfs/descargar/${encodeURIComponent(pdf.nombre)}" 
                           class="btn btn-sm btn-primary">
                            <i class="fas fa-download"></i> Descargar
                        </a>
                    </td>
                </tr>`;
            });
            
            html += '</tbody></table></div>';
            container.innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('pdfs-list').innerHTML = 
                '<div class="alert alert-danger">Error al cargar los PDFs.</div>';
        });
});
</script>
@endsection 