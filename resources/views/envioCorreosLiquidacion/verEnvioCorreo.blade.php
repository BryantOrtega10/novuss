@extends('layouts.admin')
@section('title', 'Ver envio de correos')
@section('menuLateral')
    @include('layouts.partials.menu', [
        'dataUsu' => $dataUsu
    ])
@endsection

@section('contenido')
<h1 class="granAzul">Ver envio de correos</h1>
<div class="row">
    <div class="col-12">
        <div class="progress" style="height: 40px;">
            <div class="progress-bar" role="progressbar" style="width: {{ ceil(($envioxLiquidacion->numActual / $envioxLiquidacion->numRegistros)*100)}}%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">{{ ceil(($envioxLiquidacion->numActual / $envioxLiquidacion->numRegistros)*100)}}%</div>
        </div>
    </div>
    <input type="hidden" id="idEnvioCorreoLiq" value="{{$envioxLiquidacion->idEnvioCorreoLiq}}" />
    <input type="hidden" id="estado" value="{{$envioxLiquidacion->fkEstado}}" />
    
    <input type="hidden" id="realizarConsulta" @if ($envioxLiquidacion->fkEstado == "5")
        value="0" 
        @else
        value="1" 
    @endif />
    
    <div class="col-12">
        <table class="table table-hover table-striped">
            <thead>
                <tr>
                    <th scope="col">Documento</th>
                    <th scope="col">Empleado</th>
                    <th scope="col">Estado</th>
                </tr>
            </thead>
            <tbody id="datos">
                @foreach ($empleados as $empleado)
                <tr>
                    <th scope="row">{{ $empleado->tipoidentificacion }} - {{ $empleado->numeroIdentificacion }}</th>
                    <td>{{ $empleado->primerApellido }} {{ $empleado->segundoApellido }} {{ $empleado->primerNombre }} {{ $empleado->segundoNombre }}</td>
                    <td>{{ $empleado->estado }} {{ $empleado->mensaje }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<script type="text/javascript">
function cargando() {
    if (typeof $("#cargando")[0] !== 'undefined') {
        $("#cargando").css("display", "flex");
    } else {
        $("body").append('<div id="cargando" style="display: flex;">Cargando...</div>');
    }
}
$(document).ready(function() {
    function cargarAjax(){
        if(parseInt($("#realizarConsulta").val()) == 1){
            cargando();
            const idEnvioCorreoLiq = $("#idEnvioCorreoLiq").val();
            $.ajax({
                type:'GET',
                url: "/nomina/envioCorreos/enviarProximosRegistro/" + idEnvioCorreoLiq,
                success:function(data){
                    $("#cargando").css("display", "none");
                    if(data.success){
                        if(data.seguirSubiendo){
                            $("#realizarConsulta").val("1");
                            setTimeout(() => {
                                cargarAjax();
                            }, 500);
                        }
                        else{
                            $("#realizarConsulta").val("0");
                            window.location.reload();
                        }
                        $(".progress-bar").css("width",data.porcentaje);
                        $(".progress-bar").html(data.porcentaje);
                        $("#datos").html(data.mensaje);
                    }
                    
                },
                error: function(data){
                    $("#cargando").css("display", "none");
                    retornarAlerta(
                        data.responseJSON.exception,
                        data.responseJSON.message + ", en la linea: " + data.responseJSON.line,
                        'error',
                        'Aceptar'
                    );
                    console.log("error");
                    console.log(data);
                }
            });		
        }
    }
    cargarAjax();

});

</script>


@endsection
