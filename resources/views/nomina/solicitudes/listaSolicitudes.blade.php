@extends('layouts.admin')
@section('title', 'Solicitudes de liquidaci&oacute;n')
@section('menuLateral')
    @include('layouts.partials.menu', [
        'dataUsu' => $dataUsu
    ])
@endsection

@section('contenido')
<div class="cajaGeneral text-left">
    <div class="row">
        <div class="col-7"> 
            <h1 class="granAzul">Solicitudes de liquidaci&oacute;n</h1>
        </div>
        <div class="col-5 text-right">
            @if (in_array("58",$dataUsu->permisosUsuario))
                <a class="btnGeneral btnAzulGen btnMed text-center" href="/nomina/agregarSolicitudLiquidacion">Agregar Solicitud</a>
            @endif
        </div>
    </div>
    
    <br><br>
    <form autocomplete="off" action="/nomina/solicitudLiquidacion/" method="GET" class="formGeneral" id="filtrar">
        @csrf    
        <div class="row">
            <div class="col-2">
                <div class="form-group @isset($req->fechaInicio) hasText @endisset">
                    <label for="fechaInicio" class="control-label">Fecha inicio:</label>
                    <input type="date" name="fechaInicio" class="form-control" placeholder="Fecha Inicio" @isset($req->fechaInicio) value="{{$req->fechaInicio}}" @endisset/>
                </div>
            </div>
            <div class="col-2">
                <div class="form-group @isset($req->fechaFin) hasText @endisset">
                    <label for="fechaFin" class="control-label">Fecha Fin:</label>
                    <input type="date" name="fechaFin" class="form-control" placeholder="Fecha Fin" @isset($req->fechaFin) value="{{$req->fechaFin}}" @endisset/>
                </div>
            </div>
            <div class="col-2">
                <div class="form-group @isset($req->nomina) hasText @endisset">
                    <label for="fechaInicio" class="control-label">Nomina:</label>
                    <select class="form-control" name="nomina">
                        <option value=""></option>
                        @foreach($nominas as $nomina)
                            <option value="{{$nomina->idNomina}}" @isset($req->nomina) @if ($req->nomina == $nomina->idNomina) selected @endif @endisset>{{$nomina->nombre}}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-2">
                <div class="form-group @isset($req->tipoLiquidacion) hasText @endisset">
                    <label for="tipoLiquidacion" class="control-label">Tipo:</label>
                    <select class="form-control" name="tipoLiquidacion">
                        <option value=""></option>
                        @foreach($tipoLiquidaciones as $tipoLiquidacion)
                            <option value="{{$tipoLiquidacion->idTipoLiquidacion}}" @isset($req->tipoLiquidacion) @if ($req->tipoLiquidacion == $tipoLiquidacion->idTipoLiquidacion) selected @endif @endisset>{{$tipoLiquidacion->nombre}}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="col-3"  ><input type="submit" value="Consultar"/>  <input type="reset" class="recargar" value="" style="margin-left: 5px;"/></div>
        </div>        
    </form>
    <br>


    
    <div class="table-responsive">
        <table class="table table-hover table-striped" id="solicitudes_tabla">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Fecha Liquida</th>
                    <th scope="col">Empresa</th>
                    <th scope="col">Nómina</th>
                    <th scope="col">Tipo</th>
                    <th scope="col">Estado</th>
                    <th scope="col"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($liquidaciones as $liquidacion)
                <tr>
                    <th scope="row">{{ $liquidacion->idLiquidacionNomina }}</th>
                    <td>{{ $liquidacion->fechaLiquida }}</td>
                    <td>{{ $liquidacion->razonSocial }}</td>
                    <td>{{ $liquidacion->nomNomina }}</td>
                    <td>{{ $liquidacion->tipoLiquidacion }}</td>
                    <td>{{ $liquidacion->estado }}</td>
                    <td><a href="/nomina/verSolicitudLiquidacion/{{$liquidacion->idLiquidacionNomina}}"><i class="fas fa-eye"></i></a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<script type="text/javascript">
    $(document).ready(function(e){

        $("#solicitudes_tabla").DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.10.22/i18n/Spanish.json'
            }
        });
        $(".recargar").click(function(){
            window.open("/nomina/solicitudLiquidacion","_self");
        });
    });
</script>
@endsection
