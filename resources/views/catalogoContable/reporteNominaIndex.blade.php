@extends('layouts.admin')
@section('title', 'Reporte contable')
@section('menuLateral')
    @include('layouts.partials.menu', [
        'dataUsu' => $dataUsu
    ])
@endsection

@section('contenido')
<div class="row">
    <div class="col-12">
        <h1 class="granAzul">Reporte contable</h1>
    </div>
</div>
<div class="row">
    <div class="col-12">
        <div class="cajaGeneral">
            <form method="POST" id="formReporteNomina" autocomplete="off" class="formGeneral" action="/catalogo-contable/colaReporte">
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
                            <label for="fechaReporte" class="control-label">Fecha Reporte</label>
                            <input type="date" class="form-control" id="fechaReporte" name="fechaReporte" />
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="text-center"><input type="submit" value="Generar Reporte" class="btnSubmitGen" /></div>
                    </div>
                </div>                
            </form>

            @if (sizeof($reportes) > 0)
                <h3>Reportes incompletos</h3>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>Fecha</th>
                            <th>Porcentaje</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reportes as $reporte)
                            <tr>
                                <td>{{$reporte->empresa}}</td>
                                <td>{{$reporte->fecha}}</td>
                                <td>@if ($reporte->totalRegistros > 0)
                                    {{(ceil(($reporte->registroActual / $reporte->totalRegistros)*100)."%")}}
                                    @else
                                    Formato vacio
                                    @endif                                    
                                    </td>
                                <td>@if ($reporte->totalRegistros > 0)
                                    <a href="/catalogo-contable/reporte-contable/{{$reporte->id}}">Ver</a>
                                    @else
                                    Formato vacio
                                    @endif                                    
                                </td>
                            </tr>    
                        @endforeach
                        
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>

@endsection