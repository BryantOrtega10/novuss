@extends('layouts.admin')
@section('title', 'Reporte de vacaciones')
@section('menuLateral')
    @include('layouts.partials.menu', [
        'dataUsu' => $dataUsu
    ])
@endsection

@section('contenido')
<div class="row">
    <div class="col-12">
        <h1 class="granAzul">Reporte de vacaciones</h1>
    </div>
</div>
<div class="row">
    <div class="col-12">
        <div class="cajaGeneral">
            <form method="POST" id="formCierre" autocomplete="off" class="formGeneral" action="/reportes/reporteVacaciones">
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
                        <div class="form-group hasText">
                            <label for="tipoReporte" class="control-label">Tipo reporte</label>
                            <select class="form-control" id="tipoReporte" name="tipoReporte">
                                <option value="PDF">PDF</option>
                                <option value="EXCEL">EXCEL</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="fechaFin" class="control-label">Fecha</label>
                            <input type="date" class="form-control" id="fechaFin" name="fechaFin" />
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="text-center"><input type="submit" value="Generar reporte de vacaciones" class="btnSubmitGen" /></div>
                    </div>
                </div>                
            </form>
        </div>
    </div>
</div>

@endsection