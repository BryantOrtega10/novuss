@extends('layouts.admin')
@section('title', 'Ver Novedades')
@section('menuLateral')
    @include('layouts.partials.menu', [
        'dataUsu' => $dataUsu
    ])
@endsection

@section('contenido')
    <h1 class="ordenSuperior">Ver Novedad</h1>
    <div class="cajaGeneral">
        <div class="subTitulo">
            <h2>Horas extra</h2>
            <hr />
        </div>
        <form action="" method="POST" class="formGeneral" id="formDatosNovedad" autocomplete="off">
            @csrf
            <input type="hidden" name="fkTipoNovedad" value="{{$novedad->fkTipoNovedad }}" />
            <input type="hidden" name="idNovedad" value="{{$novedad->idNovedad }}" />
            <input type="hidden" name="idHorasExtra" value="{{$novedad->fkHorasExtra}}" />
            <input type="hidden" name="fkNomina" value="{{$novedad->fkNomina}}" id="nomina" />
            <div class="row">
                <div class="col-3">
                    <div class="form-group hasText">
                        <label for="nomina" class="control-label">N&oacute;mina:</label>
                        <input type="text" id="nomina" value="{{$novedad->nombreNomina}}" readonly/>
                    </div>
                    <div class="respTipoNomina">
                        {{$novedad->periodoNomina." ".$novedad->tipoPeriodoNomina}}
                    </div>
                </div>
                <div class="col-3">
                    <div class="form-group hasText">
                        <label for="fecha" class="control-label">Fecha novedad:</label>
                        <input type="date" class="form-control" id="fecha" readonly name="fecha" value="{{$novedad->fechaRegistro}}"/>
                    </div>
                </div>
                <div class="col-3">
                    <div class="form-group hasText">
                        <label for="tipo_novedad" class="control-label">Tipo novedad:</label>
                        <input type="text" id="tipo_novedad" value="{{$novedad->tipoNovedadNombre}}" readonly/>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-3">
                    <div class="form-group  hasText" >
                        <label for="nombreEmpleado" class="control-label">Empleado:</label>
                        <input type="text" readonly class="form-control" id="nombreEmpleado" name="nombreEmpleado"
                            value="{{$novedad->primerNombre." ".$novedad->segundoNombre." ".$novedad->primerApellido." ".$novedad->segundoApellido}}" />
                        <input type="hidden" class="form-control" id="idEmpleado" name="idEmpleado" value="{{$novedad->fkEmpleado}}" />
                    </div>
                </div>
                <div class="col-3">
                    <div class="form-group hasText">
                        <label for="concepto" class="control-label">Concepto:</label>
                        <input type="text" id="concepto" value="{{$novedad->nombreConcepto}}" readonly/>
                    </div>
                </div>
                <div class="col-3">
                    <div class="form-group hasText">
                        <label for="cantidadHoras" class="control-label">Cantidad Horas:</label>
                        <input type="text" class="form-control" id="cantidadHoras" name="cantidadHoras" readonly value="{{$horas_extra->cantidadHoras }}"/>
                    </div>
                </div>
            </div>
            <div class="alert alert-danger print-error-msg-DatosNovedad" style="display:none">
                <ul></ul>
            </div>
            
        </form>
    </div>
    <div class="modal fade" id="errorNominaModal" tabindex="-1" role="dialog" aria-labelledby="errorNominaModal" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="cerrarPop" data-dismiss="modal"></div>
                    <div id="respError"></div>                
                    <div class="text-center">
                        <a data-dismiss="modal" class="btn btn-secondary" href="#">Aceptar</a>
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
    <div class="modal fade" id="busquedaCodDiagnosticoModal" tabindex="-1" role="dialog" aria-labelledby="ubicacionModal" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="cerrarPop" data-dismiss="modal"></div>
                    <div class="resFormBusCodDiagnostico"></div>
                </div>
            </div>
        </div>
    </div>
    <script type="text/javascript" src="{{ URL::asset('js/jquery.inputmask.js') }}"></script>
    <script type="text/javascript" src="{{ URL::asset('js/jquery.inputmask.numeric.extensions.js') }}"></script>
    <script type="text/javascript" src="{{ URL::asset('js/novedades/modificarNovedades.js') }}"></script>
    
@endsection