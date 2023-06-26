@extends('layouts.admin')
@section('title', 'Concepto')
@section('menuLateral')
    @include('layouts.partials.menu', [
        'dataUsu' => $dataUsu
    ])
@endsection

@section('contenido')
<div class="row">
    <div class="col-8">
        <h1 class="granAzul">Concepto</h1>
    </div>
    @if (in_array("125",$dataUsu->permisosUsuario))
    <div class="col-2 text-right">
        <a class="btn btnAzulGen btnGeneral text-center"href="/concepto/exportar"> <i class="fas fa-download"></i> Exportar</a>
    </div>
    @endif
    @if (in_array("123",$dataUsu->permisosUsuario))
    <div class="col-2 text-right">
        <a class="btn btnAzulGen btnGeneral text-center" href="#" id="addConcepto">Agregar</a>
    </div>
    @endif
</div>

<div class="cajaGeneral">
    <form autocomplete="off" action="/concepto/" method="GET" id="filtrarEmpleado"  accept-charset="UTF-8">
        
        <div class="row">
            <div class="col-4"><input type="text" id="busc_nombre" name="nombre" placeholder="Nombre" @isset($req->nombre) value="{{$req->nombre}}" @endisset/></div>
            <div class="col-4">
                <select class="form-control" name="naturaleza" id="busc_naturaleza">
                    <option value="">Seleccione uno</option>
                    @foreach($naturalezas as $naturaleza)
                        <option value="{{$naturaleza->idnaturalezaConcepto}}" @isset($req->naturaleza) @if ($req->naturaleza == $naturaleza->idnaturalezaConcepto) selected @endif @endisset>{{$naturaleza->nombre}}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-4"><input type="submit" value="Consultar"/> <input type="reset" class="recargar" value="" /> </div>
        </div>        
    </form>
    <br>
        <div class="table-responsive">
            <table class="table table-hover table-striped" id = "conceptos">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Nombre</th>
                        <th scope="col">Naturaleza</th>
                        <th scope="col">Tipo</th>
                        <th scope="col">N&oacute;mina electrónica</th>
                        <th scope="col">Condiciones</th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($conceptos as $concepto)
                    <tr>
                        <th scope="row">{{ $concepto->idconcepto }}</th>
                        <td>{{ $concepto->nombre }}</td>
                        <td>{{ $concepto->naturaleza }}</td>
                        <td>{{ $concepto->tipoConcepto }}</td>
                        <td>{{ $concepto->codigo ?? "SIN ASIGNAR" }}</td>
                        <td><a href="/concepto/condiciones/{{ $concepto->idconcepto }}">Condiciones</a></td>
                        <td>
                            @if (in_array("145",$dataUsu->permisosUsuario))
                            <a href="/concepto/getForm/ver/{{ $concepto->idconcepto }}" class="ver"><i class="fas fa-eye"></i></a>
                            @endif
                            @if (in_array("124",$dataUsu->permisosUsuario))
                            <a href="/concepto/getForm/edit/{{ $concepto->idconcepto }}" class="editar"><i class="fas fa-edit"></i></a>
                            @endif
                            @if (in_array("126",$dataUsu->permisosUsuario))
                            <a href="/concepto/getForm/copy/{{ $concepto->idconcepto }}" class="editar"><i class="fas fa-copy"></i></a>
                            @endif
                            
                        
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{-- {{ $conceptos->appends($arrConsulta)->links() }} --}}
</div>
<div class="modal fade" id="conceptoModal" tabindex="-1" role="dialog" aria-labelledby="conceptoModal" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <div class="cerrarPop" data-dismiss="modal"></div>
                <div class="respForm" data-para='concepto'></div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="formulaConceptoModal" tabindex="-1" role="dialog" aria-labelledby="conceptoModal" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <div class="cerrarPop" data-dismiss="modal"></div>
                <div class="respForm" data-para='formulaConcepto'></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="formulaConceptoSSModal" tabindex="-1" role="dialog" aria-labelledby="conceptoModal" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <div class="cerrarPop" data-dismiss="modal"></div>
                <div class="respForm" data-para='formulaConceptoSS'></div>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript" src="{{ URL::asset('js/concepto.js') }}"></script>
@endsection
