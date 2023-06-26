@extends('layouts.admin')
@section('title', 'Cambiar conceptos fijos')
@section('menuLateral')
    @include('layouts.partials.menu', [
        'dataUsu' => $dataUsu
    ])
@endsection

@section('contenido')
<div class="row">
    <div class="col-12">
        <h1 class="granAzul">Cambiar conceptos fijos</h1>
    </div>
</div>
<div class="row">
    <div class="col-12">
        <div class="cajaGeneral">
            <form method="POST" autocomplete="off" class="formGeneral" action="/nomina/subirCambioConceptoFijo" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-3">
                        <div class="form-group">
                            <label for="infoEmpresa" class="control-label">Empresa</label>
                            <select class="form-control" id="infoEmpresa" required name="empresa">
                                <option value=""></option>        
                                @foreach ($empresas as $empresa)
                                    <option value="{{$empresa->idempresa}}">{{$empresa->razonSocial}}</option>
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
                        <div class="text-center"><input type="submit" value="Cambiar conceptos fijos" class="btnSubmitGen" /></div>
                    </div>
                </div>                
            </form>
        </div>
    </div>
</div>

@endsection