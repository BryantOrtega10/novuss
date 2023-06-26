@extends('layouts.admin')
@section('title', 'Empresa')
@section('menuLateral')
    @include('layouts.partials.menu', [
        'dataUsu' => $dataUsu
    ])
@endsection

@section('contenido')
<div class="row">
    <div class="col-8">
        <h1 class="granAzul">Empresas</h1>
    </div>
    @if (in_array("146",$dataUsu->permisosUsuario))
    <div class="col-2 text-right">
        <a class="btn btnAzulGen btnGeneral text-center" href="/empresa/exportar" id="exportarEmpresa"><i class="fas fa-download"></i> Exportar</a>
    </div>
    @endif
    @if (in_array("103",$dataUsu->permisosUsuario))
    <div class="col-2 text-right">
        <a class="btn btnAzulGen btnGeneral text-center" href="#" id="addEmpresa">Agregar</a>
    </div>
    @endif
</div>
<div class="cajaGeneral">
<div class="table-responsive">
    <table class="table table-hover table-striped" id = "empresas">
        <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col">Razón social</th>
                <th scope="col">NIT</th>
                <th scope="col">CIIU</th>
                <th scope="col">Correo</th>
                <th scope="col"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($empresas as $empresa)
            <tr>
                <th scope="row">{{ $empresa->idempresa }}</th>
                <td>{{ $empresa->razonSocial }}</td>
                <td>{{ $empresa->documento }} - {{$empresa->digitoVerificacion}}</td>
                <td>{{ $empresa->ciiu ?? "Sin seleccionar" }}</td>
                <td>{{ $empresa->email1 }}</td>
                <td>
                    <div class="dropdown">
                        <i class="fas fa-ellipsis-v dropdown-toggle" data-toggle="dropdown" aria-haspopup="false" aria-expanded="false" id="dropdownMenuButton"></i>
                        <div class="dropdown-menu"  aria-labelledby="dropdownMenuButton">
                            @if (in_array("104",$dataUsu->permisosUsuario))
                            <a dataId ="{{ $empresa->idempresa }}" class="dropdown-item centro_costo" href = "/empresa/centroCosto/{{$empresa->idempresa}}"><i class="fas fa-dollar-sign"></i> Centros de costo</a>
                            @endif
                            @if (in_array("109",$dataUsu->permisosUsuario))
                            <a dataId ="{{ $empresa->idempresa }}" class="dropdown-item nome" href = "/empresa/nomina/{{$empresa->idempresa}}"><i class="fas fa-money-bill-alt"></i> Nómina</a>
                            @endif
                            @if (in_array("113",$dataUsu->permisosUsuario))                            
                            <a dataId ="{{ $empresa->idempresa }}" class="dropdown-item smtp" href = "/empresa/smtp/{{$empresa->idempresa}}"><i class="far fa-envelope"></i> Configuración SMTP</a>
                            @endif
                            @if (in_array("114",$dataUsu->permisosUsuario))
                            <a dataId ="{{ $empresa->idempresa }}" class="dropdown-item centroTrabajo" href = "/empresa/centroTrabajo/{{$empresa->idempresa}}"><i class="fas fa-briefcase"></i> Centros de trabajo</a>
                            @endif
                            
                            <a dataId ="{{ $empresa->idempresa }}" class="dropdown-item" href = "/mensajes/mensajesxEmpresa/{{$empresa->idempresa}}"><i class="fas fa-envelope-open-text"></i> Mensajes</a>
                            
                            @if (in_array("118",$dataUsu->permisosUsuario))
                            <a dataId ="{{ $empresa->idempresa }}" class="dropdown-item" href = "/empresa/permisosPortal/{{$empresa->idempresa}}"><i class="fas fa-lock"></i> Permisos portal empleado</a>                            
                            @endif
                            
                            <a dataId ="{{ $empresa->idempresa }}" class="dropdown-item detalle"><i class="far fa-eye"></i> Ver Empresa</a>
                            
                            @if (in_array("119",$dataUsu->permisosUsuario))
                            <a dataId ="{{ $empresa->idempresa }}" class="dropdown-item editar"><i class="fas fa-edit"></i> Editar Empresa</a>
                            @endif
                            @if (in_array("120",$dataUsu->permisosUsuario))
                            <a dataId ="{{ $empresa->idempresa }}" class="dropdown-item color_rojo eliminar"><i class="fas fa-trash"></i> Eliminar Empresa</a>
                            @endif
                            
                        </div>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
</div>
<div class="modal fade" id="empresasModal" tabindex="-1" role="dialog" aria-labelledby="variableModal" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <div class="cerrarPop" data-dismiss="modal"></div>
                <div class="respForm" data-para='empresas'></div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript" src="{{ URL::asset('js/empresas.js') }}"></script>
@endsection
