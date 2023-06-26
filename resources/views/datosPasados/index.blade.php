@extends('layouts.admin')
@section('title', 'Carga datos pasados')
@section('menuLateral')
    @include('layouts.partials.menu', [
        'dataUsu' => $dataUsu
    ])
@endsection

@section('contenido')
<div class="row">
    <div class="col-12">
        <h1 class="granAzul">Carga datos pasados</h1>
    </div>
</div>
<div class="cajaGeneral">
    <div class="row">
        <div class="col-12">
            
                <form method="POST" id="" autocomplete="off" class="formGeneral" action="/datosPasados/subirArchivo" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-3">
                            <div class="form-group">
                                <label for="infoEmpresa" class="control-label">Empresa</label>
                                <select class="form-control" id="infoEmpresa" name="empresa" required>
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
                        <div class="col-3">
                            <div class="seleccionarArchivo">
                                <label for="archivoCSV">Seleccione un archivo CSV</label>
                                <input type="file" name="archivoCSV" id="archivoCSV" required  accept=".csv"/>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="text-center"><input type="submit" value="Cargar datos pasados" class="btnSubmitGen" /></div>
                        </div>
                    </div> 
                    
                    
                </form>
            
        </div>
        <div class="col-12">
            <table class="table table-hover table-striped ">
                <tr>
                    <th># Carga</th>
                    <th>Fecha Carga</th>
                    <th>Porcentaje</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
                @foreach ($cargasDatosPasados as $cargaDatoPasado)
                    <tr>
                        <td>{{$cargaDatoPasado->idCargaDatosPasados}}</td>
                        <td>{{$cargaDatoPasado->fechaCarga}}</td>
                        <td>{{ ceil(($cargaDatoPasado->numActual / $cargaDatoPasado->numRegistros)*100)}}%</td>
                        <td>{{$cargaDatoPasado->nombre}}</td>
                        <td><a href="/datosPasados/verCarga/{{$cargaDatoPasado->idCargaDatosPasados}}">Ver carga</a></td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
</div>
@endsection
