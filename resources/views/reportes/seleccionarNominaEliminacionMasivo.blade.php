@extends('layouts.admin')
@section('title', 'Nómina eliminacion')
@section('menuLateral')
    @include('layouts.partials.menu', [
        'dataUsu' => $dataUsu
    ])
@endsection

@section('contenido')
<div class="row">
    <div class="col-12">
        <h1 class="granAzul">Nómina eliminación masivo</h1>
    </div>
</div>
<div class="row">
    <div class="col-12">
        <div class="cajaGeneral">
            <form method="POST" id="formDocumentoSS" autocomplete="off" enctype="multipart/form-data" class="formGeneral" action="/reportes/nominaEliminacionMasivo">
                @csrf
                <div class="row">
                    <div class="col-3">
                        <div class="form-group">
                            <label for="infoEmpresa" class="control-label">Empresa</label>
                            <select class="form-control" required id="infoEmpresa" name="idEmpresa">
                                <option value=""></option>        
                                @foreach ($empresas as $empresa)
                                    <option value="{{$empresa->idempresa}}">{{$empresa->razonSocial}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>    
                    <div class="col-3">
                        <div class="form-group">
                            <label for="nomina" required class="control-label">N&oacute;mina:</label>
                            <select class="form-control" id="infoNomina" name="infoNomina">
                                <option value=""></option>
                            </select>
                        </div>               
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="fechaReporte" class="control-label">Fecha Informe</label>
                            <input type="date" class="form-control" id="fechaReporte" name="fechaReporte" required />
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="seleccionarArchivo">
                            <label for="archivoCSV">Seleccione un archivo CSV</label>
                            <input type="file" name="archivoCSV" id="archivoCSV" required="" accept=".csv">
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="text-center"><input type="button" id="enviarDocumento" value="DESCARGAR" class="btnSubmitGen" /></div>
                    </div>
                </div>                
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="liquidacionPendienteModal" tabindex="-1" role="dialog" aria-labelledby="liquidacionPendiente" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <div class="cerrarPop" data-dismiss="modal"></div>
                <div class="camposVaciosText">
                    <h4 id="mensaje_al">Aun cuenta con liquidaciones sin terminar, desea continuar?</h4>
                    <div class="text-center">
                        <a href="#" data-accion="" data-form="" id="btnContinuarLiqPen" class="btn btn-secondary">Continuar</a>
                        <a data-dismiss="modal" class="btn btn-primary" href="#">Volver</a>
                    </div>                    
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="busquedaEmpleadoModal" tabindex="-1" role="dialog" aria-labelledby="empleadoModal" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <div class="cerrarPop" data-dismiss="modal"></div>
                <div class="resFormBusEmpleado"></div>
            </div>
        </div>
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

        $("#enviarDocumento").click(function(){
            
            const idEmpresa = $("#infoEmpresa").val();
            const fechaDocumento = $("#fechaReporte").val();
            if(idEmpresa != "" && fechaDocumento != ""){
                cargando();
                $.ajax({
                    type:'GET',
                    url: "/reportes/verificarSiPendientes/" + idEmpresa + "/" + fechaDocumento,
                    success:function(data){
                        $("#cargando").css("display", "none");
                        if(data.success){
                            $("#formDocumentoSS").submit();
                        }
                        else{
                            $("#mensaje_al").html(data.mensaje);
                            $("#liquidacionPendienteModal").modal("show");
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
            else{
                retornarAlerta(
                        "Error",
                        "Seleccione una fecha y una fecha",
                        'error',
                        'Aceptar'
                    );
            }
            
        });    
        $("#btnContinuarLiqPen").click(function(){
            $("#formDocumentoSS").submit();
        });


        $("body").on("click", "#busquedaEmpleado", function() {
            cargando();
            $.ajax({
                type: 'GET',
                url: "/empleado/cargarFormEmpleadosxNomina?idNomina=" + $("#infoNomina").val() + "&idEmpresa=" +  $("#infoEmpresa").val(),
                success: function(data) {
                    $("#cargando").css("display", "none");
                    $(".resFormBusEmpleado").html(data);
                    $('#busquedaEmpleadoModal').modal('show');
                },
                error: function(data) {
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
        });

        $("body").on("submit", "#filtrarEmpleado", function(e) {
            e.preventDefault();
            cargando();

            var formdata = $('#filtrarEmpleado').serialize();
            $.ajax({
                type: 'GET',
                url: $(this).attr("action"),
                data: formdata,
                success: function(data) {
                    $("#cargando").css("display", "none");
                    $(".resFormBusEmpleado").html(data);
                },
                error: function(data) {
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

        });
        $("body").on("click", ".resFormBusEmpleado .pagination a", function(e) {
            e.preventDefault();
            cargando();
            $.ajax({
                type: 'GET',
                url: $(this).attr("href"),
                success: function(data) {
                    $("#cargando").css("display", "none");
                    $(".resFormBusEmpleado").html(data);
                },
                error: function(data) {
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
        });
        $("body").on("click", ".resFormBusEmpleado a.seleccionarEmpleado", function(e) {
            e.preventDefault();
            $("#nombreEmpleado").val($(this).html().trim());
            $("#nombreEmpleado").trigger("change");
            $("#idEmpleado").val($(this).attr("data-id"));
            $("#idPeriodo").val($(this).attr("data-idPeriodo"));
            $('#busquedaEmpleadoModal').modal('hide');
            
        });

        $("body").on("change", "#infoEmpresa", function(e) {
            e.preventDefault();

            $("#agenteRetenedor").val($(this).find("option[value=" + $(this).val() + "]").html());
            $("#agenteRetenedor").trigger("change");
            $("#infoNomina").html('<option value=""></option>');
            $("#infoNomina").trigger("change");

            const idEmpresa = $(this).val();
            if (idEmpresa != "") {
                cargando();
                $.ajax({
                    type: 'GET',
                    url: "/empleado/cargarDatosPorEmpresa/" + idEmpresa,
                    success: function(data) {
                        $("#cargando").css("display", "none");
                        $("#infoNomina").html(data.opcionesNomina);
                    },
                    error: function(data) {
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

        });
    });
    
    </script>
@endsection