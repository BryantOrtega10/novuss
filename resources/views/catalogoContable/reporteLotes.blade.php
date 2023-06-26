@extends('layouts.admin')
@section('title', 'Reporte contable')
@section('menuLateral')
    @include('layouts.partials.menu', [
        'dataUsu' => $dataUsu
    ])
@endsection

@section('contenido')
<div class="row">
    <div class="col-12">
        <h1 class="granAzul">Reporte contable</h1>
    </div>
</div>
<div class="row">
    <div class="col-12">
        <input type="hidden" id="estadoReporte" value="{{$reporte->estado}}" />
        <input type="hidden" id="idReporte" value="{{$reporte->id}}" />
        <div class="cajaGeneral">
            @if ($reporte->estado == 0)
                <span>Generando reporte... Espere un momento</span>    
            @else
                @if ($reporte->estado == 1)
                <a href="/catalogo-contable/descargarReporte/{{$reporte->id}}" id="descargarReporte" class="btn btn-primary">Descargar</a>
                <br><br>
                @else
                Este archivo ya fue descargado genere otro.
                @endif
            @endif
            <h2>Progreso</h2>
            
                @if ($reporte->totalRegistros > 0)
                <div class="progress" style="height: 40px;">
                    <div class="progress-bar" role="progressbar" style="width: {{ ceil(($reporte->registroActual / $reporte->totalRegistros)*100)}}%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">{{ ceil(($reporte->registroActual / $reporte->totalRegistros)*100)}}%</div>
                </div>
                @else
                Reporte Vacio
                @endif    
            </div>
            
        </div>
    </div>
</div>
<script>
    window.addEventListener("load", () => {
        const loadData = () => {
            fetch(`/catalogo-contable/generarReporteNomina/${document.getElementById('idReporte').value}`, {
                method: 'GET',
            })
            .then(response => response.json())
            .then((data) => {
                $(".progress-bar").css("width", data.porcentaje);
                $(".progress-bar").html(data.porcentaje);
                if(data.estado == 1){
                    window.location.reload();
                }
                else{
                    loadData();
                }               
            })
            .catch(error => console.log(error));
        };

        if(typeof document.getElementById('descargarReporte') !== 'undefined' && document.getElementById('descargarReporte') != null){
            document.getElementById('descargarReporte').addEventListener("click", (e) => {
                e.preventDefault();
                window.open(document.getElementById('descargarReporte').href,"_blank");
                window.open('/catalogo-contable/reporteNomina',"_self");
            })
        }


        if(document.getElementById('estadoReporte').value == '0'){
            loadData();
        }
    });

</script>

@endsection