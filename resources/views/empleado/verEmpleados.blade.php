@extends('layouts.admin')
@section('title', 'Seleccionar empleado')
@section('menuLateral')
    @include('layouts.partials.menu', [
    'dataUsu' => $dataUsu
    ])
@endsection

@section('contenido')
    <div class="row">
        <div class="col-12">
            <h1 class="granAzul">Crear - Consultar - Modificar Empleados</h1>
        </div>
    </div>
    <div class="cajaGeneral">
        <div class="row">
            <div class="col-12">
                <div class="row">
                    <div class="col-9">
                        <h2>Filtros de B&uacute;squeda</h2>
                    </div>
                    <div class="col-3 text-right">
                        @if (in_array("51",$dataUsu->permisosUsuario))
                            <a href="/empleado/formCrear/1" class="btnGeneral btnAzulGen btnMed text-center"> <i
                            class="far fa-user"></i> Crear Empleado</a>
                        @endif
                    </div>
                </div>
                <hr>
                <form autocomplete="off" action="/empleado" method="GET" id="filtrarEmpleado" class="formGeneral">
                    @csrf
                    <div class="row">
                        <div class="col-2">
                            <div class="form-group @isset($req->numDocNombre) hasText @endisset">
                                <label for="numDocNombre" class="control-label">Número Identificación y Nombre:</label>
                                <input type="text" class="form-control" id="numDocNombre" name="numDocNombre" @isset($req->numDocNombre)
                                value="{{ $req->numDocNombre }}" @endisset/>
                            </div>
                        </div>
                        
                        <div class="col-2">
                            <div class="form-group @isset($req->empresa) hasText @endisset">
                                <label for="infoEmpresa" class="control-label">Empresa</label>
                                <select class="form-control" id="infoEmpresa" name="empresa">
                                    <option value=""></option>
                                    @foreach ($empresas as $empresa)
                                        @if (isset($dataUsu) && $dataUsu->fkRol == 2 && in_array($empresa->idempresa,$dataUsu->empresaUsuario))
                                            <option value="{{ $empresa->idempresa }}" @isset($req->empresa) @if ($req->empresa == $empresa->idempresa) selected
                                            @endif @endisset>{{ $empresa->razonSocial }}</option>
                                        @elseif($dataUsu->fkRol == 3)
                                            <option value="{{ $empresa->idempresa }}" @isset($req->empresa) @if ($req->empresa == $empresa->idempresa) selected
                                            @endif @endisset>{{ $empresa->razonSocial }}</option>
                                        @endif


                                        
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-2">
                            <div class="form-group @isset($req->nomina) hasText @endisset">
                                <label for="infoNomina" class="control-label">Nomina</label>
                                <select class="form-control" id="infoNomina" name="nomina">
                                    <option value=""></option>
                                    @foreach ($nominas as $nomina)                                       
                                        <option value="{{ $nomina->idNomina }}" @isset($req->nomina) @if ($req->nomina == $nomina->idNomina) selected
                                        @endif @endisset>{{ $nomina->nombre }}</option>                                        
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-2">
                            <div class="form-group @isset($req->centroCosto) hasText @endisset">
                                <label for="centroCosto" class="control-label">Centro de costo:</label>
                                <select name="centroCosto" class="form-control" id="centroCosto">
                                    <option value=""></option>
                                    @foreach ($centrosDeCosto as $centroDeCosto)
                                        <option value="{{ $centroDeCosto->idcentroCosto }}" @isset($req->centroCosto)
                                                @if ($req->centroCosto == $centroDeCosto->idcentroCosto) selected
                                                @endif @endisset>{{ $centroDeCosto->nombre }}
                                            </option>
                                        @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-2">
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
                        <div class="col-2">
                            <div class="row">
                                <div class="col-7">
                                    <input type="submit" value="Consultar" />
                                </div>
                                <div class="col-5">
                                    <input type="reset" class="recargar" value="" />
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="col-12">
                <h2>Resultado B&uacute;squeda</h2>
                <hr>
                <h3 class="grisClaro">Se encontraron {{ $numResultados }} resultados.</h3>
                <table class="table table-hover table-striped">
                    <tr>
                        <th scope="col"  class="text-left">ID</th>
                        <th scope="col">Nombre</th>                        
                        <th scope="col">Empresa</th>
                        <th scope="col">Nomina</th>
                        <th scope="col">Centro costo</th>
                        <th scope="col">Estado</th>
                        <th scope="col"></th>
                    </tr>
                    @foreach ($empleados as $empleado)
                        <tr>
                            <th class="text-left">{{ $empleado->numeroIdentificacion }}</th>
                            <td>
                                {{ $empleado->primerApellido . ' ' . $empleado->segundoApellido . ' ' . $empleado->primerNombre . ' ' . $empleado->segundoNombre }}
                            </td>                            
                            <td>{{ $empleado->nombreEmpresa }}</td>
                            <td>{{ $empleado->nombreNomina }}</td>
                            <td>{{ $empleado->centroCosto }}</td>
                            <td>
                                @if ($empleado->reintegros > 0 && $empleado->fkEstado != '2')
                                    <div class="estdoEmp{{ $empleado->claseEstado }}">{{ $empleado->estado }}</div>
                                    <div class="estdoEmp reintegro"><a href="/empleado/verPeriodos/{{ $empleado->idempleado }}" class="ver_reintegro">REINTEGRO ({{$empleado->reintegros}})</a></div>                                    
                                @else
                                    <div class="estdoEmp{{ $empleado->claseEstado }}">{{ $empleado->estado }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group">
                                    <i class="fas fa-ellipsis-v dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"></i>
                                  
                                    <div class="dropdown-menu dropdown-menu-right">                                        
                                        @if (in_array("52",$dataUsu->permisosUsuario))
                                        <a href="/empleado/formModificar/{{ $empleado->idempleado }}/{{ $empleado->idPeriodo }}" class="dropdown-item editar">
                                            <i class="fas fa-edit"></i> Editar</a>
                                        @endif
                                        @if (in_array("53",$dataUsu->permisosUsuario))
                                        <a href="/empleado/formVer/{{ $empleado->idempleado }}/{{ $empleado->idPeriodo }}" class="dropdown-item  ver">
                                            <i class="fas fa-eye"></i> Ver</a>
                                        @endif
                                        
                                        <a href="/empleado/mostrarPorqueFalla/{{ $empleado->idempleado }}/{{ $empleado->idPeriodo }}" class="verPorqueFalla dropdown-item ">
                                            <i class="fas fa-question-circle"></i> Campos pendientes</a>
                                        
                                        @if ($empleado->fkEstadoPeriodo == '2')
                                            @if (in_array("8",$dataUsu->permisosUsuario))
                                            <a href="/empleado/formReintegro/{{ $empleado->idempleado }}" data-id="{{ $empleado->idempleado }}" class="dropdown-item ">
                                                <i class="fas fa-redo"></i> Reintegrar</a>
                                            @endif
                                            @if (in_array("54",$dataUsu->permisosUsuario))
                                            <a href="#" class="eliminarDefUsuario dropdown-item " data-id="{{ $empleado->idempleado }}" data-idPeriodo="{{ $empleado->idPeriodo }}">
                                                <i class="fas fa-trash"></i> Eliminar</a>
                                            @endif
                                        @else
                                            @if (in_array("54",$dataUsu->permisosUsuario))
                                            <a href="#" class="eliminarUsuario dropdown-item" data-id="{{ $empleado->idempleado }}" data-idPeriodo="{{ $empleado->idPeriodo }}">
                                                <i class="fas fa-user-minus"></i> Desactivar</a>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                                
                                

                            </td>
                        </tr>
                    @endforeach
                </table>
                {{ $empleados->appends($arrConsulta)->links() }}
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="mostrarPorqueFalla" tabindex="-1" role="dialog" aria-labelledby="mostrarPorqueFalla"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="cerrarPop" data-dismiss="modal"></div>
                    <div id="respMensaje"></div>
                    <div class="text-center">
                        <a data-dismiss="modal" class="btn btn-secondary" href="#">Aceptar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
        <script type="text/javascript" src="{{ URL::asset('js/empleado/empleado.js') }}"></script>
        
    @endsection
