@extends('layouts.admin')
@section('title', 'Unir SS y contabilidad general')
@section('menuLateral')
    @include('layouts.partials.menu', [
        'dataUsu' => $dataUsu
    ])
@endsection

@section('contenido')
<div class="row">
    <div class="col-12">
        <h1 class="granAzul">Unir SS y contabilidad general</h1>
    </div>
</div>
<div class="row">
    <div class="col-12">
        <div class="cajaGeneral">
            <form method="POST" id="formDocumentoSS" autocomplete="off" class="formGeneral" action="/nomina/unirSSyContabilidadGeneral">
                @csrf
                <div class="row">
                    <div class="col-3">
                        <div class="form-group">
                            <label for="infoEmpresa" class="control-label">Empresa</label>
                            <select class="form-control" id="infoEmpresa" name="empresa">
                                <option value=""></option>        
                                @foreach ($empresas as $empresa)
                                    <option value="{{$empresa->idempresa}}">{{$empresa->razonSocial}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>    
                    <div class="col-3">
                        <div class="form-group">
                            <label for="fechaDocumento" class="control-label">Fecha Sincronizar</label>
                            <input type="date" class="form-control" id="fechaDocumento" name="fechaDocumento" />
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="text-center"><input type="submit" value="UnirSS" class="btnSubmitGen" /></div>
                    </div>
                </div>                
            </form>
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

       

        
    });
    
    </script>
@endsection