@extends('layouts.admin')
@section('title', 'Prestamos')
@section('menuLateral')
    @include('layouts.partials.menu', [
        'dataUsu' => $dataUsu
    ])
@endsection

@section('contenido')
<div class="row">
    <div class="col-6">
        <h1 class="granAzul">Prestamos</h1>
    </div>
    <div class="col-2 text-right">
        <a href="/reportes/prestamos" class="btn btn-primary">Reportes de Prestamos/Embargo</a>
    </div>
    @if (in_array("93",$dataUsu->permisosUsuario))
    <div class="col-2 text-right">
        <a href="/prestamos/agregar" class="btn btn-primary" id="addPrestamo">Agregar Prestamo</a>
    </div>
    <div class="col-2 text-right">
        <a href="/prestamos/agregarEmbargo" class="btn btn-primary" id="addEmbargo">Agregar Embargo</a>
    </div>        
    @endif
</div>
<div class="row">
    <div class="col-12">
        <div class="cajaGeneral">
            <!--<form autocomplete="off" action="/prestamos/" method="GET" class="formGeneral" id="filtrar">
                <div class="row">
                    <div class="col-3">
                        <div class="form-group @isset($req->nombre) hasText @endisset">
                            <label for="nombre" class="control-label">Nombre:</label>
                            <input type="text" class="form-control" name="nombre" id="nombre" @isset($req->nombre) value="{{$req->nombre}}" @endisset/>
                        </div>               
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($req->numDoc) hasText @endisset">
                            <label for="numDoc" class="control-label">Número Identificación:</label>
                            <input type="text" class="form-control" id="numDoc" name="numDoc" @isset($req->numDoc) value="{{$req->numDoc}}" @endisset/>
                        </div>               
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($req->estado) hasText @endisset">
                            <label for="estado" class="control-label">Estado:</label>
                            <select name="estado" class="form-control" id="estado">
                                <option value=""></option>
                                @foreach ($estados as $estado)
                                    <option value="{{ $estado->idestado }}" @isset($req->estado) @if ($req->estado == $estado->idestado) selected
                                        @endif @endisset>{{ $estado->nombre }}</option>
                                @endforeach
                            </select>
                        </div>               
                    </div>
                    <div class="col-3"  ><input type="submit" value="Consultar"/> <input type="reset" class="recargar" value="" style="margin-left: 5px;"/>  </div>
                </div>
            </form>-->
            <table class="table table-hover table-striped" id="prestamos_table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Identificación</th>
                        <th>Nombres</th>
                        <th>Clase cuota</th>
                        <th>Monto Inicial</th>
                        <th>Saldo</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($prestamos as $prestamo)
                <tr>
                    <td>{{$prestamo->idPrestamo}}</td>
                    <td>{{$prestamo->numeroIdentificacion}}</td>
                    <td>{{$prestamo->primerApellido." ".$prestamo->segundoApellido." ".$prestamo->primerNombre." ".$prestamo->segundoNombre}}</td>
                    <td>{{$prestamo->nombreConcepto}}</td>
                    <td>${{number_format($prestamo->montoInicial, 0, ",", ".")}}</td>
                    <td>${{number_format($prestamo->saldoActual, 0, ",", ".")}}</td>
                    <td>{{$prestamo->nombreEstado}}</td>
                    <td>
                        @if (in_array("94",$dataUsu->permisosUsuario))
                        <a class="modificarPrestamo" href="/prestamos/getForm/edit/{{$prestamo->idPrestamo}}"><i class="fas fa-edit"></i></a>
                        @endif
                        @if (in_array("95",$dataUsu->permisosUsuario))
                        <a class="eliminarPrestamo" href="/prestamos/eliminar/{{$prestamo->idPrestamo}}"><i class="fas fa-trash"></i></a>
                        @endif
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="modal fade" id="prestamoModal" tabindex="-1" role="dialog" aria-labelledby="prestamoModal" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <div class="cerrarPop" data-dismiss="modal"></div>
                <div class="respForm"></div>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript" src="{{ URL::asset('js/jquery.inputmask.js') }}"></script>
<script type="text/javascript" src="{{ URL::asset('js/jquery.inputmask.numeric.extensions.js') }}"></script>
<script type="text/javascript" src="{{ URL::asset('js/prestamo.js') }}"></script>
<script type="text/javascript">
$(document).ready(function(e){
    $(".recargar").click(function(e){
        e.preventDefault();
        window.open("/prestamos","_self");
    });
})
</script>
@endsection
