<table class="table table-hover table-striped">
    <tr>
        <th>#</th>
        <th width="120">Fecha Inicio</th>
        <th width="120">Fecha Fin</th>
        <th>Empresa</th>
        <th>Nomina</th>
        <th>Cargo</th>
        <th>Tipo Contrato</th>
        <th>Salario final</th>
    </tr>
    @php
    $i = 1;        
    @endphp

    @foreach ($periodos as $periodo)
        <tr>
            <td>{{$i}}</td>
            <td>{{$periodo->fechaInicio ?? $empleado->fechaIngreso}}</td>    
            <td>{{$periodo->fechaFin ?? ""}}</td>
            <td>{{$periodo->nombreEmpresa ?? $empleado->nombreEmpresa}}</td>
            <td>{{$periodo->nombreNomina ?? $empleado->nombreNomina}}</td>
            <td>{{$periodo->nombreCargo ?? $empleado->nombreCargo}}</td>
            <td>{{$periodo->nombreTipoContrato ?? $tipoContrato->nombreTipoContrato}}</td>
            <td>$ {{number_format(($periodo->salario ?? $periodo->conceptoFValor), 0, ",", ".") }}</td>
        </tr>     
        @php
            $i++;
        @endphp
    @endforeach    
    
</table>