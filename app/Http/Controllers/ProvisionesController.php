<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProvisionesController extends Controller
{
    public function recalcularProvisiones($idLiquidacionNomina){
       
        $boucherpagos = DB::table("boucherpago")
        ->where("fkLiquidacion","=",$idLiquidacionNomina)
        //->where ("fkPeriodoActivo","=",'4847')
        ->get();

        foreach($boucherpagos as $boucherpago){
            if(isset($boucherpago->horasPeriodo)){
                $horas= $boucherpago->horasPeriodo/$boucherpago->periodoPago;
                $this->calcularProvisionesEmpleado($boucherpago->fkEmpleado, $boucherpago->fkPeriodoActivo, $boucherpago->fkLiquidacion, $boucherpago->idBoucherPago, $horas, $boucherpago->periodoPago);
            }
            else{
                $this->calcularProvisionesEmpleado($boucherpago->fkEmpleado, $boucherpago->fkPeriodoActivo, $boucherpago->fkLiquidacion, $boucherpago->idBoucherPago);
            }
            
        }
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", recalculó las provisiones para la liquidación:".$idLiquidacionNomina);

        $usu = UsuarioController::dataAdminLogueado();
            return view("/layouts.respuestaGen",[
                "dataUsu" => $usu,
                "titulo" => "Se recalcularon las provisiones de la liquidación",
                "mensaje" => "Se recalcularon las provisiones de la liquidación"
            ]);
    }

    public function calcularProvisionesEmpleado($idEmpleado, $idPeriodo, $idLiquidacionNomina, $idBoucherPago, $numeroHoras = null, $numeroDias = null){
        try{
            $empleado = DB::table('empleado')->where("idempleado","=", $idEmpleado)->first();
            
            $liquidacionNomina = DB::table('liquidacionnomina')->where("idLiquidacionNomina", "=", $idLiquidacionNomina)->first();
            
            $boucherpagos = DB::table("boucherpago")
            ->where("idBoucherPago","=",$idBoucherPago)
            //->where ("fkPeriodoActivo","=",'719')
            ->first();
            $periodoActivoReintegro = DB::table("periodo")
            ->where("idPeriodo","=",$boucherpagos->fkPeriodoActivo)
            ->first();
            
            

            $empleado->fkEmpresa = ($periodoActivoReintegro->fkEmpresa ?? $empleado->fkEmpresa);
            $empleado->fkNomina =($periodoActivoReintegro->fkNomina ?? $empleado->fkNomina);
            $empleado->fechaIngreso =($periodoActivoReintegro->fechaInicio ?? $empleado->fechaIngreso);
            
            $empleado->fkCargo =($periodoActivoReintegro->fkCargo ?? $empleado->fkCargo);
            $empleado->fkTipoCotizante =($periodoActivoReintegro->fkTipoCotizante ?? $empleado->fkTipoCotizante);
            $empleado->esPensionado =($periodoActivoReintegro->esPensionado ?? $empleado->esPensionado);
            $empleado->tipoRegimen =($periodoActivoReintegro->tipoRegimen ?? $empleado->tipoRegimen);

            $empleado->tipoRegimenPensional =($periodoActivoReintegro->tipoRegimenPensional ?? $empleado->tipoRegimenPensional);
            $empleado->fkUbicacionLabora =($periodoActivoReintegro->fkUbicacionLabora ?? $empleado->fkUbicacionLabora);
            $empleado->fkLocalidad =($periodoActivoReintegro->fkLocalidad ?? $empleado->fkLocalidad);
            $empleado->sabadoLaborable =($periodoActivoReintegro->sabadoLaborable ?? $empleado->sabadoLaborable);
            $empleado->formaPago =($periodoActivoReintegro->formaPago ?? $empleado->formaPago);
            $empleado->fkEntidad =($periodoActivoReintegro->fkEntidad ?? $empleado->fkEntidad);
            $empleado->numeroCuenta =($periodoActivoReintegro->numeroCuenta ?? $empleado->numeroCuenta);
            $empleado->tipoCuenta =($periodoActivoReintegro->tipoCuenta ?? $empleado->tipoCuenta);
            $empleado->otraFormaPago =($periodoActivoReintegro->otraFormaPago ?? $empleado->otraFormaPago);
            $empleado->fkTipoOtroDocumento =($periodoActivoReintegro->fkTipoOtroDocumento ?? $empleado->fkTipoOtroDocumento);
            $empleado->otroDocumento =($periodoActivoReintegro->otroDocumento ?? $empleado->otroDocumento);
            $empleado->procedimientoRetencion =($periodoActivoReintegro->procedimientoRetencion ?? $empleado->procedimientoRetencion);
            $empleado->porcentajeRetencion =($periodoActivoReintegro->porcentajeRetencion ?? $empleado->porcentajeRetencion);
            $empleado->fkNivelArl =($periodoActivoReintegro->fkNivelArl ?? $empleado->fkNivelArl);
            $empleado->fkCentroTrabajo =($periodoActivoReintegro->fkCentroTrabajo ?? $empleado->fkCentroTrabajo);
            $empleado->aplicaSubsidio =($periodoActivoReintegro->aplicaSubsidio ?? $empleado->aplicaSubsidio);

            if(isset($periodoActivoReintegro->fkEmpresa)){
                $empleado->fkEmpresa = $periodoActivoReintegro->fkEmpresa;
                $empleado->fkNomina = $periodoActivoReintegro->fkNomina;
            }

            if(isset($periodoActivoReintegro->fkUbicacionLabora)){
                $empleado->fkUbicacionLabora = $periodoActivoReintegro->fkUbicacionLabora;
            }
            $nomina = DB::table("nomina")->where("idNomina", "=", $liquidacionNomina->fkNomina)->first();
            $tipoliquidacion = $liquidacionNomina->fkTipoLiquidacion;
            $fechaInicio = $liquidacionNomina->fechaInicio;
            $fechaFin = $liquidacionNomina->fechaFin;
            
            $empresa = DB::table('empresa', "em")
            ->join("nomina as n","n.fkEmpresa", "=", "em.idempresa")        
            ->join("liquidacionnomina as ln","ln.fkNomina", "=", "n.idNomina")        
            ->where("ln.idLiquidacionNomina","=", $idLiquidacionNomina)->first();

            $liquidacionesParaSalarioMes = DB::table("liquidacionnomina", "ln")
            ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.salarioPeriodoPago) as salarioPago")
            ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$idPeriodo)
            ->where("bp.salarioPeriodoPago", ">", "0")
            ->where("ln.fechaInicio",">=",$fechaInicio)
            ->where("ln.fechaFin","<=",$fechaFin)
            ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","8"])
            ->first();
            //dd($liquidacionesParaSalarioMes);
            //dd($liquidacionesParaSalarioMes);
            $salarioMes = (30 * ($liquidacionesParaSalarioMes->salarioPago ?? 0))/($liquidacionesParaSalarioMes->periodPago ?? 1);
            if($salarioMes == 0){
                $liquidacionesParaSalarioMes = DB::table("liquidacionnomina", "ln")
                ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.salarioPeriodoPago) as salarioPago")
                ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$idPeriodo)
                ->where("bp.salarioPeriodoPago", ">", "0")
                ->where("ln.fechaInicio",">=",date("Y-m-01",strtotime($fechaInicio." -30 days")))
                ->where("ln.fechaFin","<=",$fechaFin)
                ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","8"])
                ->first();
                $salarioMes = (30 * ($liquidacionesParaSalarioMes->salarioPago ?? 0))/($liquidacionesParaSalarioMes->periodPago ?? 1);
            }


            //dd($salarioMes);
            
            //INICIO CALCULO PROVISIONES
            $liquidacionPrima = 0;
            $liquidacionCesantias = 0;
            $liquidacionIntCesantias = 0;
            $liquidacionVac = 0;
            $arrComoCalculaProv = array();
            
            if($empleado->tipoRegimen == "Ley 50" && $empleado->fkTipoCotizante != "23" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "12"){
                
                $novedadesRetiro = DB::table("novedad","n")
                ->join("retiro AS r", "r.idRetiro","=","n.fkRetiro")
                ->where("n.fkEmpleado", "=", $empleado->idempleado)
                ->where("n.fkPeriodoActivo", "=", $idPeriodo)
                ->whereIn("n.fkEstado",["7","8"])
                ->whereNotNull("n.fkRetiro")
                ->whereBetween("n.fechaRegistro",[$fechaInicio, $fechaFin])
                ->first();
                
                if(isset($novedadesRetiro)){
                    $fechaFin = $novedadesRetiro->fechaReal;
                }       

                if(substr($fechaFin, 8, 2) == "31" || (substr($fechaFin, 8, 2) == "28" && substr($fechaFin, 5, 2) == "02") || (substr($fechaFin, 8, 2) == "29" && substr($fechaFin, 5, 2) == "02")  ){
                    $fechaFin = substr($fechaFin,0,8)."30";
                }
                
                $arrResPrima = $this->calcularPrimaProv($fechaInicio, $fechaFin, $empleado, $idPeriodo, $salarioMes);
                
                $liquidacionPrima = $arrResPrima["liquidacionPrima"];
                
                $arrComoCalculaProv[58] = ($arrComoCalcula[58] ?? array());
                array_push($arrComoCalculaProv[58], "Valor Salario: $".number_format($arrResPrima["salarioPrima"],0,",","."));
                array_push($arrComoCalculaProv[58], "Valor promedio Salarial: $".number_format($arrResPrima["salarialPrima"],0,",","."));
                array_push($arrComoCalculaProv[58], "Valor Base: $".number_format($arrResPrima["basePrima"],0,",","."));
                array_push($arrComoCalculaProv[58], "Fecha inicial: ".$arrResPrima["fechaInicialPrima"]);
                array_push($arrComoCalculaProv[58], "Fecha final: ".$fechaFin);
                array_push($arrComoCalculaProv[58], "Días: ".$arrResPrima["totalPeriodoPago"]);
                array_push($arrComoCalculaProv[58], "Valor liquidación prima: $".number_format($arrResPrima["liquidacionPrima"],0,",","."));
                
                $mesActual = date("m",strtotime($fechaInicio));
                $anioActual = date("Y",strtotime($fechaInicio));    
                if($tipoliquidacion != "7" && $tipoliquidacion != "10" && $tipoliquidacion != "11" && $tipoliquidacion != "8"){
                    if($mesActual >= 1 && $mesActual <= 6){            
                        $historicoProvisionPrima = DB::table("provision","p")
                        ->selectRaw("sum(p.valor) as sumaValor")
                        ->where("p.fkEmpleado","=",$empleado->idempleado)
                        ->where("p.fkPeriodoActivo","=",$idPeriodo)
                        ->where("p.mes","<",date("m",strtotime($fechaInicio)))
                        ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                        ->where("p.fkConcepto","=","73")
                        ->first();   

                        $pagoPrimaItems = DB::table("item_boucher_pago", "ibp")
                        ->selectRaw("Sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$idPeriodo)
                        ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                        ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")              
                        ->whereRaw("MONTH(ln.fechaInicio) < '".date("m",strtotime($fechaInicio))."'")
                        ->whereIn("ibp.fkConcepto",["58"]) //58 - PRIMA
                        ->first();

                        $saldoPrima = DB::table("saldo")
                        ->where("fkEmpleado","=",$empleado->idempleado)
                        ->where("anioAnterior","=",date("Y",strtotime($fechaInicio)))
                        ->where("fkConcepto","=", "73")
                        ->where("fkEstado","=","7")
                        ->first();
                        

                        array_push($arrComoCalculaProv[58], "Se suma a la liquidación anterior junto con la suma de pagos anteriores : $".number_format(( $pagoPrimaItems->suma ?? 0),0,",","."));
                        array_push($arrComoCalculaProv[58], "Se resta el historico de provisiones por un valor de : $".number_format(( $historicoProvisionPrima->sumaValor ?? 0),0,",","."));
                        array_push($arrComoCalculaProv[58], "Se resta el saldo por un valor de : $".number_format(( $saldoPrima->valor ?? 0),0,",","."));
                        
                        $provisionPrimaValor = $liquidacionPrima + ($pagoPrimaItems->suma ?? 0) - (isset($historicoProvisionPrima->sumaValor) ? $historicoProvisionPrima->sumaValor : 0) - ( $saldoPrima->valor ?? 0);

                        array_push($arrComoCalculaProv[58], "Finalmente se tiene una provision de : $".number_format(( $provisionPrimaValor ?? 0),0,",","."));

                    }
                    else if($mesActual >= 7 && $mesActual <= 12){
                        $historicoProvisionPrima = DB::table("provision","p")
                        ->selectRaw("sum(p.valor) as sumaValor")
                        ->where("p.fkEmpleado","=",$empleado->idempleado)
                        ->where("p.fkPeriodoActivo","=",$idPeriodo)
                        ->where("p.mes","<",date("m",strtotime($fechaInicio)))
                        ->where("p.mes",">","6")
                        ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                        ->where("p.fkConcepto","=","73")
                        ->first();

                        $pagoPrimaItems = DB::table("item_boucher_pago", "ibp")
                        ->selectRaw("Sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$idPeriodo)
                        ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                        ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")              
                        ->whereRaw("MONTH(ln.fechaInicio) < '".date("m",strtotime($fechaInicio))."'")
                        ->whereRaw("MONTH(ln.fechaInicio) > '6'")
                        ->whereIn("ibp.fkConcepto",["58"]) //58 - PRIMA
                        ->first();
                        $saldoPrima = DB::table("saldo")
                        ->where("fkEmpleado","=",$empleado->idempleado)
                        ->where("anioAnterior","=",date("Y",strtotime($fechaInicio)))
                        ->where("fkConcepto","=", "73")
                        ->where("fkEstado","=","7")
                        ->first();
                        array_push($arrComoCalculaProv[58], "Se suma a la liquidación anterior junto con la suma de pagos anteriores : $".number_format(( $pagoPrimaItems->suma ?? 0),0,",","."));
                        array_push($arrComoCalculaProv[58], "Se resta el historico de provisiones por un valor de : $".number_format(( $historicoProvisionPrima->sumaValor ?? 0),0,",","."));
                        array_push($arrComoCalculaProv[58], "Se resta el saldo por un valor de : $".number_format(( $saldoPrima->valor ?? 0),0,",","."));
                        $provisionPrimaValor = $liquidacionPrima + ($pagoPrimaItems->suma ?? 0) - (isset($historicoProvisionPrima->sumaValor) ? $historicoProvisionPrima->sumaValor : 0) - ( $saldoPrima->valor ?? 0);
                        array_push($arrComoCalculaProv[58], "Finalmente se tiene una provision de : $".number_format(( $provisionPrimaValor ?? 0),0,",","."));


                    }
                    $provisionPrima = DB::table("provision","p")
                    ->where("p.fkEmpleado","=",$empleado->idempleado)
                    ->where("p.fkPeriodoActivo","=",$idPeriodo)
                    ->where("p.mes","=",date("m",strtotime($fechaInicio)))
                    ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                    ->where("p.fkConcepto","=","73")
                    ->get();

    
                    $arrProvisionPrima = array(
                        "fkPeriodoActivo" => $idPeriodo,
                        "fkConcepto" => "73",                
                        "fkEmpleado"=> $empleado->idempleado,
                        "mes" => date("m",strtotime($fechaInicio)),
                        "anio"  => date("Y",strtotime($fechaInicio)),
                        "valor" => round($provisionPrimaValor),
                        "comoCalculo" => implode("<br>",$arrComoCalculaProv[58])
                    );
                    //dd($arrProvisionPrima);
                    
                    
                    if(sizeof($provisionPrima)>0){
                        DB::table("provision")
                        ->where("idProvision","=", $provisionPrima[0]->idProvision)
                        ->update($arrProvisionPrima);
                    }
                    else{
                        DB::table("provision")
                        ->insert($arrProvisionPrima);
                    }
                }



                //INICIO CESANTIAS 
                $fechaInicioMes = date("Y-m-01", strtotime($fechaInicio));
                
                if($tipoliquidacion != "7" && $tipoliquidacion != "10" && $tipoliquidacion != "11" && $tipoliquidacion != "8"){
                    
                    $primeraLiquidacionNoDatosPasados = DB::table("liquidacionnomina", "ln")
                    ->join("boucherpago as bp", "bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                    ->where("bp.fkPeriodoActivo","=",$idPeriodo)
                    ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9"])
                    ->first();

                    $ultimaLiquidacionDatosPasados = DB::table("liquidacionnomina", "ln")
                    ->join("boucherpago as bp", "bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                    ->where("bp.fkPeriodoActivo","=",$idPeriodo)
                    ->whereIn("ln.fkTipoLiquidacion",["8"])
                    ->orderBy("bp.idBoucherPago","desc")
                    ->first();

                    
                    $primerLiq = ($primeraLiquidacionNoDatosPasados->idLiquidacionNomina ?? 0);
                    $primerLiqFecha = ($ultimaLiquidacionDatosPasados->fechaLiquida ?? "1990-01-01");
                    

                    $fechaInicioMes = date("Y-m-01", strtotime($fechaInicio));


                    $nomina = DB::table("nomina")->where("idNomina", "=", $liquidacionNomina->fkNomina)->first();
                    
                    
                 
               
                    //INICIO SALARIAL GANADO ESTE MES
                   
                    
                    if(isset($novedadesRetiro)){                        
                        $cambioSalario = DB::table("cambiosalario")
                        ->where("fkPeriodoActivo","=",$idPeriodo)
                        ->where("fechaCambio",">",date("Y-m-d", strtotime($fechaFin." -3 months + 1 day")))
                        ->first();
                    }
                    else{
                        $cambioSalario = DB::table("cambiosalario")
                        ->where("fkPeriodoActivo","=",$idPeriodo)
                        ->where("fechaCambio",">",date("Y-m-d", strtotime($fechaFin." -3 months + 1 day")))
                        ->first();
                    }
                    

                    if(isset($cambioSalario)){
                        $liquidacionesMesesAnterioresCesantias = DB::table("liquidacionnomina", "ln")
                        ->selectRaw("bp.idBoucherPago, bp.periodoPago as periodPago, bp.salarioPeriodoPago as salarioPago, bp.diasTrabajados as diasTrabajados, bp.diasIncapacidad as diasIncapacidad")
                        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$idPeriodo)
                        ->where("ln.fechaInicio","<=",date("Y-m-t", strtotime($fechaFin." -1 month")))
                        ->whereRaw("(bp.salarioPeriodoPago > 0 or bp.diasIncapacidad > 0)")
                        ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")
                        ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","12"])
                        ->orderBy("bp.idBoucherPago","desc");
                        $liquidacionesMesesAnterioresCesantias = $liquidacionesMesesAnterioresCesantias->get();
                        
                        $periodPagoCesantiasMesesAnt = 0;
                        $salarioPagoCesantiasMesesAnt = 0;            

                        foreach($liquidacionesMesesAnterioresCesantias as $liquidacionMesAnteriorCesantias){


                            $periodPagoCesantiasMesesAnt = $periodPagoCesantiasMesesAnt + $liquidacionMesAnteriorCesantias->periodPago;
                            $salarioPagoCesantiasMesesAnt = $salarioPagoCesantiasMesesAnt + $liquidacionMesAnteriorCesantias->salarioPago;
                        }

                        $liquidacionesUltimoMesCesantias = DB::table("liquidacionnomina", "ln")
                        ->selectRaw("bp.idBoucherPago, bp.periodoPago as periodPago, bp.salarioPeriodoPago as salarioPago, bp.diasTrabajados as diasTrabajados, bp.diasIncapacidad as diasIncapacidad")
                        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$idPeriodo)
                        ->whereRaw("MONTH(ln.fechaInicio) = '".date("m",strtotime($fechaFin))."'")
                        ->whereRaw("(bp.salarioPeriodoPago > 0 or bp.diasIncapacidad > 0)")
                        ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")
                        ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","12"])
                        ->orderBy("bp.idBoucherPago","desc")->get();
                        
                        //dump($salarioPagoCesantiasMesesAnt, $periodPagoCesantiasMesesAnt);
                        if($nomina->periodo == 15){
                            
                            if(sizeof($liquidacionesUltimoMesCesantias) > 1){
                                $salarioUltimoMes = 0;
                                $periodoUltimoMes = 0;
                                foreach($liquidacionesUltimoMesCesantias as $liquidacionUltimoMesCesantias){
                                    $salarioUltimoMes += $liquidacionUltimoMesCesantias->salarioPago;
                                    $periodoUltimoMes += $liquidacionUltimoMesCesantias->periodPago;                                    
                                }
                                $salarioUltimoMes = $salarioUltimoMes*30/$periodoUltimoMes;
                                
                                $periodPagoCesantiasMesesAnt = $periodPagoCesantiasMesesAnt + 30;
                                $salarioPagoCesantiasMesesAnt = $salarioPagoCesantiasMesesAnt + $salarioUltimoMes;
                            }
                            else{
                                //Se retira en la primera quincena
                                foreach($liquidacionesUltimoMesCesantias as $liquidacionUltimoMesCesantias){
                                    $salarioUltimoMes = $liquidacionUltimoMesCesantias->salarioPago*30/$liquidacionUltimoMesCesantias->periodPago;

                                   // dump($liquidacionUltimoMesCesantias, $salarioUltimoMes);
                                    $periodPagoCesantiasMesesAnt = $periodPagoCesantiasMesesAnt + 30;
                                    $salarioPagoCesantiasMesesAnt = $salarioPagoCesantiasMesesAnt + $salarioUltimoMes;
                                }
                                //dump($salarioPagoCesantiasMesesAnt, $periodPagoCesantiasMesesAnt);
                            }
                            

                        }
                        else{
                            foreach($liquidacionesUltimoMesCesantias as $liquidacionUltimoMesCesantias){
                                $salarioUltimoMes = $liquidacionUltimoMesCesantias->salarioPago*30/$liquidacionUltimoMesCesantias->periodPago;
                                $periodPagoCesantiasMesesAnt = $periodPagoCesantiasMesesAnt + 30;
                                $salarioPagoCesantiasMesesAnt = $salarioPagoCesantiasMesesAnt + $salarioUltimoMes;
                            }
                        }
                        
                        
                        $totalPeriodoPagoCes = $periodPagoCesantiasMesesAnt;
                        
                        
                        $maximoRetroActivoDesde = (date("m",strtotime($fechaInicio)) - 3);
                        if($maximoRetroActivoDesde < 1){
                            $maximoRetroActivoDesde = 1;
                        }
                        $retroActivoMesesAnterioresCes = DB::table("liquidacionnomina", "ln")
                        ->selectRaw("sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                        ->join("item_boucher_pago as ibp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$idPeriodo)
                        ->where("ln.fechaInicio","<=",$fechaFin)
                        ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."' and MONTH(ln.fechaInicio) > '".$maximoRetroActivoDesde."'")
                        ->where("ibp.fkConcepto","=","49") // 49 - RETROACTIVO SALARIO
                        ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9"])
                        ->first();
                        
                        $salarioCes = $salarioPagoCesantiasMesesAnt + ($retroActivoMesesAnterioresCes->suma ?? 0);            
                        if($totalPeriodoPagoCes == 0){
                            return;
                        }
                        $salarioCes = ($salarioCes / $totalPeriodoPagoCes)*30;
                        //dd($salarioCes);
                        
                    }
                    else{
                        $conceptosFijosEmpl = DB::table("conceptofijo", "cf")
                        ->where("cf.fkPeriodoActivo","=", $idPeriodo)
                        ->where("cf.fkConcepto","=", "1")
                        ->orderBy("cf.idConceptoFijo","desc")
                        ->distinct()
                        ->first();

                        if(isset($conceptosFijosEmpl)){
                            $salarioMes = $conceptosFijosEmpl->valor; 
                        }
                        $salarioCes = $salarioMes;
                    }
                        
                    if($empleado->fkTipoCotizante == 51){
                        $liquidacionesMesesAnterioresCes51 = DB::table("liquidacionnomina", "ln")
                        ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.diasTrabajados) as diasTrabajadosPer, sum(bp.diasIncapacidad) as diasIncapacidadPer, sum(bp.salarioPeriodoPago) as salarioPago, min(ln.fechaInicio) as minimaFecha")
                        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$idPeriodo)
                        ->where("ln.fechaInicio","<=",$fechaFin)
                        ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")
                        ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9"])
                        ->first();

                        //INICIO CALCULAR EL PROMEDIO SALARIO EN PERIODO ACTUAL    
                        
                        //$totalPeriodoPago51 = $periodoPagoMesActual + (isset($liquidacionesMesesAnterioresCes51->periodPago) ? $liquidacionesMesesAnterioresCes51->periodPago : 0);
                        $totalPeriodoPagoParaSalario51 = (isset($liquidacionesMesesAnterioresCes51->periodPago) ? $liquidacionesMesesAnterioresCes51->periodPago : 0);
                        
                        $retroActivoMesesAnterioresCes51 = DB::table("liquidacionnomina", "ln")
                        ->selectRaw("sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                        ->join("item_boucher_pago as ibp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$idPeriodo)
                        ->where("ln.fechaInicio","<=",$fechaFin)
                        ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")
                        ->where("ibp.fkConcepto","=","49") // 49 - RETROACTIVO SALARIO
                        ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9"])
                        ->first();
                        
                        
                        $salarioCes = ($liquidacionesMesesAnterioresCes51->salarioPago ?? 0) + ($retroActivoMesesAnterioresCes51->suma ?? 0);
                        $salarioCes = ($salarioCes / $totalPeriodoPagoParaSalario51)*30;

                    }
                    
                    if($empleado->aplicaSubsidio == "1" && $empleado->tipoRegimen == 'Ley 50'){
                        $variablesSalarioMinimo = DB::table("variable")->where("idVariable","=","1")->first();
                        
                        if($salarioMes == 0){
                            $salarioCesParaSubsidio = $salarioCes;                
                        }
                        else{
                            $salarioCesParaSubsidio = $salarioMes;                
                        }
                        if($salarioCesParaSubsidio < (2 * $variablesSalarioMinimo->valor)){
                            $variablesSubTrans = DB::table("variable")->where("idVariable","=","2")->first();
                            $salarioCes = $salarioCes + $variablesSubTrans->valor;
                        }
                    }
                    else{
                        //Verificar que el concepto 124	AUXILIO DE CONECTIVIDAD DIGITAL exista dentro de sus conceptos fijos, de ser asi agregarlo al salario
                        $conceptosFijoAuxilioConectividad = DB::table("conceptofijo")
                        ->where("fkConcepto","=",'124')
                        ->where("fkEstado","=","1")
                        ->where("fkEmpleado","=",$empleado->idempleado)
                        ->first();
                        if(isset($conceptosFijoAuxilioConectividad)){
                            $variablesSalarioMinimo = DB::table("variable")->where("idVariable","=","1")->first();
                            if($salarioMes == 0){
                                $salarioCesParaSubsidio = $salarioCes;                
                            }
                            else{
                                $salarioCesParaSubsidio = $salarioMes;                
                            }
                            if($salarioCesParaSubsidio < (2 * $variablesSalarioMinimo->valor)){
                                $variablesSubTrans = DB::table("variable")->where("idVariable","=","2")->first();
                                $salarioCes = $salarioCes + $variablesSubTrans->valor;
                            }
                        }                
                    }

                    $periodo = DB::table("periodo")
                    ->where("idPeriodo", "=", $idPeriodo)->first();
                    $fechaInicialCes = $periodo->fechaInicio;
                    if(strtotime($fechaInicialCes)< strtotime(date($anioActual."-01-01"))){
                        $fechaInicialCes = date($anioActual."-01-01");
                    }
                    $fechaFinalCes = $fechaFin;
                    $novedadesRetiro = DB::table("novedad","n")
                    ->join("retiro AS r", "r.idRetiro","=","n.fkRetiro")
                    ->where("n.fkEmpleado", "=", $empleado->idempleado)
                    ->where("n.fkPeriodoActivo", "=", $idPeriodo)
                    ->where("n.fkEstado","=","8")
                    ->whereNotNull("n.fkRetiro")
                    ->whereBetween("n.fechaRegistro",[$fechaInicio, $fechaFin])
                    ->first();
                    
                    if(isset($novedadesRetiro)){
                        $fechaFinalCes = $novedadesRetiro->fecha;
                    }     

                    $liquidacionesMesesAnterioresCompleta = DB::table("liquidacionnomina", "ln")
                    ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.salarioPeriodoPago) as salarioPago, min(ln.fechaInicio) as minimaFecha")
                    ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$idPeriodo)
                    ->where("ln.fechaInicio","<=",$fechaFin)
                    ->where("bp.salarioPeriodoPago",">",0)
                    ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")                    
                    ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","8"])
                    ->first();
                    $totalPeriodoPagoAnioActual = $liquidacionesMesesAnterioresCompleta->periodPago;
                    
                    $fechaFinMes = date("Y-m-t", strtotime($fechaFin));
                    $itemsBoucherSalarialMesesAnterioresCes = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.valor) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$idPeriodo)
                    ->where("ln.fechaInicio","<=",$fechaFinMes)
                    ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")                
                    ->where("gcc.fkGrupoConcepto","=","11") //11 - Salarial
                    ->first();
                    
                    $salarialCes = $itemsBoucherSalarialMesesAnterioresCes->suma;
                    
                    
                    if(substr($fechaFinalCes, 8, 2) == "31" || (substr($fechaFinalCes, 8, 2) == "28" && substr($fechaFinalCes, 5, 2) == "02") || (substr($fechaFinalCes, 8, 2) == "29" && substr($fechaFinalCes, 5, 2) == "02")  ){
                        $fechaFinalCes = substr($fechaFinalCes,0,8)."30";
                    }
                    $totalPeriodoPagoAnioActualReal = $this->days_360($fechaInicialCes, $fechaFinalCes);
                    $totalPeriodoPagoAnioActualReal++;
                    
                    
                    //INICIO QUITAR LRN PARA CESANTIAS
                    if($empresa->LRN_cesantias == "1"){

                        $novedadesAus = DB::table("novedad","n")
                        ->selectRaw("sum(a.cantidadDias) as suma")
                        ->join("ausencia as a","a.idAusencia", "=", "n.fkAusencia")
                        ->whereNotNull("n.fkAusencia")            
                        ->where("n.fkEmpleado","=",$empleado->idempleado)
                        ->where("n.fkPeriodoActivo","=",$idPeriodo)
                        ->whereRaw("YEAR(n.fechaRegistro) = '".$anioActual."'")
                        ->whereIn("n.fkEstado",["7", "8"]) // Pagada o sin pagar-> no que este eliminada
                        ->first();
                        $totalPeriodoPagoAnioActualReal = $totalPeriodoPagoAnioActualReal - $novedadesAus->suma;
                    }
                    
                    if($totalPeriodoPagoAnioActualReal != 0){
                        $salarialCes = ($salarialCes / $totalPeriodoPagoAnioActualReal)*30;
                    }

                    $centroCostoEmpleadoParaCes = DB::table("centrocosto","c")
                    ->join("empleado_centrocosto as ecc","ecc.fkCentroCosto","=","c.idcentroCosto")
                    ->where("ecc.fkPeriodoActivo","=",$idPeriodo)
                    ->where("ecc.fkEmpleado","=",$empleado->idempleado)
                    ->first();

                    if(isset($centroCostoEmpleadoParaCes->diasCesantias)){
                        $nomina->diasCesantias = $centroCostoEmpleadoParaCes->diasCesantias;
                    }

                    $salarialCes = round($salarialCes);

                    $baseCes = $salarioCes + $salarialCes;

                    $diasCes = ($totalPeriodoPagoAnioActualReal*$nomina->diasCesantias)/360;
                    $diasCes = round($diasCes,6);
                    $baseUnDiaCes = ($baseCes / 30);
                    $base3036Dias = $baseUnDiaCes;
                    $liquidacionCesantias = ($base3036Dias*$diasCes);
                    $liquidacionCesantias = round($liquidacionCesantias);
                    
                    $arrComoCalculaProv[66] = array();

                    array_push($arrComoCalculaProv[66], "Valor Salario: $".number_format($salarioCes,0,",","."));
                    array_push($arrComoCalculaProv[66], "Valor promedio Salarial: $".number_format($salarialCes,0,",","."));
                    array_push($arrComoCalculaProv[66], "Valor Base: $".number_format($baseCes,0,",","."));
                    array_push($arrComoCalculaProv[66], "Fecha inicial: ".$fechaInicialCes);
                    array_push($arrComoCalculaProv[66], "Fecha final: ".$fechaFinalCes);
                    array_push($arrComoCalculaProv[66], "Días: ".$totalPeriodoPagoAnioActualReal);
                    array_push($arrComoCalculaProv[66], "Nómina configurada a: ".$nomina->diasCesantias." días de cesantias por año");
                    array_push($arrComoCalculaProv[66], "Valor liquidación : $".number_format($liquidacionCesantias,0,",","."));


                    
                    $historicoProvisionCesantias = DB::table("provision","p")
                    ->selectRaw("sum(p.valor) as sumaValor")
                    ->where("p.fkEmpleado","=",$empleado->idempleado)
                    ->where("p.fkPeriodoActivo","=",$idPeriodo)
                    ->where("p.mes","<",date("m",strtotime($fechaInicio)))
                    ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                    ->where("p.fkConcepto","=","71")
                    ->first();  

                        

                    $pagoCesantiasItems = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.valor) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$idPeriodo)
                    ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")    
                    ->whereRaw("MONTH(ln.fechaInicio) < '".date("m",strtotime($fechaInicio))."'")  
                    ->whereIn("ibp.fkConcepto",["66"]) //66 - CESANTIAS
                    ->first();

                    $saldoCesantias = DB::table("saldo")
                    ->where("fkEmpleado","=",$empleado->idempleado)
                    ->where("anioAnterior","=",date("Y",strtotime($fechaInicio)))
                    ->where("fkConcepto","=", "71")
                    ->where("fkEstado","=","7")
                    ->first();

                

                    array_push($arrComoCalculaProv[66], "Se suma a la liquidación anterior junto con la suma de pagos anteriores : $".number_format(( $pagoCesantiasItems->suma ?? 0),0,",","."));
                    array_push($arrComoCalculaProv[66], "Se resta el historico de provisiones por un valor de : $".number_format(( $historicoProvisionCesantias->sumaValor ?? 0),0,",","."));
                    array_push($arrComoCalculaProv[66], "Se resta el saldo por un valor de : $".number_format(( $saldoCesantias->valor ?? 0),0,",","."));

                    $provisionCesantiasValor = $liquidacionCesantias + ( $pagoCesantiasItems->suma ?? 0) - $historicoProvisionCesantias->sumaValor - ($saldoCesantias->valor ?? 0);            
                    array_push($arrComoCalculaProv[66], "Finalmente se tiene una provision de : $".number_format(( $provisionCesantiasValor ?? 0),0,",","."));


                    $provisionCesantias = DB::table("provision","p")
                        ->where("p.fkEmpleado","=",$empleado->idempleado)
                        ->where("p.fkPeriodoActivo","=",$idPeriodo)
                        ->where("p.mes","=",date("m",strtotime($fechaInicio)))
                        ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                        ->where("p.fkConcepto","=","71")
                        ->get();               

                    $arrProvisionCesantias = array(
                        "fkPeriodoActivo" => $idPeriodo,
                        "fkConcepto" => "71",
                        "fkEmpleado"=> $empleado->idempleado,
                        "mes" => date("m",strtotime($fechaInicio)),
                        "anio"  => date("Y",strtotime($fechaInicio)),
                        "valor" => round($provisionCesantiasValor),
                        "comoCalculo" => implode("<br>",$arrComoCalculaProv[66])
                    );
                    if(sizeof($provisionCesantias)>0){
                        DB::table("provision")
                        ->where("idProvision","=", $provisionCesantias[0]->idProvision)
                        ->update($arrProvisionCesantias);
                    }
                    else{
                        DB::table("provision")
                        ->insert($arrProvisionCesantias);
                    }
                    //Intereses

                    //Obtener la primera liquidacion de nomina de la persona 
                  

                    $totalPeriodoPagoAnioActual =  $totalPeriodoPagoAnioActualReal;
                
                    $fechaFinIntCes = $fechaFin; 
                    $novedadesRetiro = DB::table("novedad","n")
                    ->join("retiro AS r", "r.idRetiro","=","n.fkRetiro")
                    ->where("n.fkEmpleado", "=", $empleado->idempleado)
                    ->where("n.fkPeriodoActivo", "=", $idPeriodo)
                    ->where("n.fkEstado","=","8")
                    ->whereNotNull("n.fkRetiro")
                    ->whereBetween("n.fechaRegistro",[$fechaInicio, $fechaFin])
                    ->first();
                    
                    if(isset($novedadesRetiro)){
                        $fechaFinIntCes = $novedadesRetiro->fecha;
                    }       

                    if($empleado->fkTipoCotizante == 51){                              
                        if(substr($fechaFin, 8, 2) == "31" || (substr($fechaFin, 8, 2) == "28" && substr($fechaFin, 5, 2) == "02") || (substr($fechaFin, 8, 2) == "29" && substr($fechaFin, 5, 2) == "02")  ){
                            $fechaFinIntCes = substr($fechaFin,0,8)."30";
                        }

                        if(strtotime($empleado->fechaIngreso) < strtotime(date("Y-01-01",strtotime($fechaInicio)))){
                            $totalPeriodoPagoAnioActual = $this->days_360(date("Y-01-01",strtotime($fechaInicio)),$fechaFinIntCes) + 1;
                        }
                        else{
                            $totalPeriodoPagoAnioActual = $this->days_360($empleado->fechaIngreso,$fechaFinIntCes) + 1;
                        }                
                    }
                    
                    $interesesPorcen = $totalPeriodoPagoAnioActual * 0.12 / 360;
                    $interesesPorcen = round($interesesPorcen, 4);

                
                    $liquidacionIntCesantias = ($liquidacionCesantias) * $interesesPorcen;
                    
                    $arrComoCalculaProv[69] = array();

                    array_push($arrComoCalculaProv[69], "Valor liquidación cesantias: $".number_format($liquidacionCesantias,0,",","."));
                    array_push($arrComoCalculaProv[69], "Se calcula el porcentaje para el periodo actual: (".$totalPeriodoPagoAnioActual." días * 12%) / 360");
                    array_push($arrComoCalculaProv[69], "Multiplicado por un porcentaje de ".($interesesPorcen*100)."%");
                    array_push($arrComoCalculaProv[69], "Fecha inicial: ".$fechaInicialCes);
                    array_push($arrComoCalculaProv[69], "Fecha final: ".$fechaFinIntCes);
                    array_push($arrComoCalculaProv[69], "Días: ".$totalPeriodoPagoAnioActual);
                    array_push($arrComoCalculaProv[69], "Valor liquidación : $".number_format($liquidacionIntCesantias,0,",","."));


                    $historicoProvisionIntCesantias = DB::table("provision","p")
                    ->selectRaw("sum(p.valor) as sumaValor")
                    ->where("p.fkEmpleado","=",$empleado->idempleado)
                    ->where("p.fkPeriodoActivo","=",$idPeriodo)
                    ->where("p.mes","<",date("m",strtotime($fechaInicio)))
                    ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                    ->where("p.fkConcepto","=","72")
                    ->first();

                    $saldoIntCesantias = DB::table("saldo")
                    ->where("fkEmpleado","=",$empleado->idempleado)
                    ->where("anioAnterior","=",date("Y",strtotime($fechaInicio)))
                    ->where("fkConcepto","=", "72")
                    ->where("fkEstado","=","7")
                    ->first();

                    $pagoInteresesItems = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.valor) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$idPeriodo)
                    ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")        
                    ->whereRaw("MONTH(ln.fechaInicio) < '".date("m",strtotime($fechaInicio))."'")        
                    ->whereIn("ibp.fkConcepto",["69"]) //69 - INTERESES CESANTIAS	
                    ->first();


                        
                    

                    array_push($arrComoCalculaProv[69], "Se suma a la liquidación anterior junto con el pago de cesantias anteriores : $".number_format(( $pagoInteresesItems->suma ?? 0),0,",","."));
                    array_push($arrComoCalculaProv[69], "Se resta el historico de provisiones por un valor de : $".number_format(( $historicoProvisionIntCesantias->sumaValor ?? 0),0,",","."));
                    array_push($arrComoCalculaProv[69], "Se resta el saldo por un valor de : $".number_format((isset($saldoIntCesantias->valor) ? $saldoIntCesantias->valor : 0),0,",","."));
                    $provisionIntCesantiasValor = $liquidacionIntCesantias + ($pagoInteresesItems->suma ?? 0)  - $historicoProvisionIntCesantias->sumaValor - (isset($saldoIntCesantias->valor) ? $saldoIntCesantias->valor : 0);
                    array_push($arrComoCalculaProv[69], "Finalmente se tiene una provision de : $".number_format(( $provisionIntCesantiasValor ?? 0),0,",","."));


                    
                    
                    $provisionIntCesantias = DB::table("provision","p")
                    ->where("p.fkEmpleado","=",$empleado->idempleado)
                    ->where("p.fkPeriodoActivo","=",$idPeriodo)
                    ->where("p.mes","=",date("m",strtotime($fechaInicio)))
                    ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                    ->where("p.fkConcepto","=","72")
                    ->get();

                    $arrProvisionIntCesantias = array(
                        "fkPeriodoActivo" => $idPeriodo,
                        "fkConcepto" => "72",
                        "fkEmpleado"=> $empleado->idempleado,
                        "mes" => date("m",strtotime($fechaInicio)),
                        "anio"  => date("Y",strtotime($fechaInicio)),
                        "valor" => round($provisionIntCesantiasValor),
                        "comoCalculo" => implode("<br>",$arrComoCalculaProv[69])
                    );
                    if(sizeof($provisionIntCesantias)>0){
                        DB::table("provision")
                        ->where("idProvision","=", $provisionIntCesantias[0]->idProvision)
                        ->update($arrProvisionIntCesantias);
                    }
                    else{
                        DB::table("provision")
                        ->insert($arrProvisionIntCesantias);
                    }

                    //Vacaciones
                    $fechaFinPeriodoVac = $fechaFin;
                    if(substr($fechaFin, 8, 2) == "31" || (substr($fechaFin, 8, 2) == "28" && substr($fechaFin, 5, 2) == "02") || (substr($fechaFin, 8, 2) == "29" && substr($fechaFin, 5, 2) == "02")){
                        $fechaFinPeriodoVac = substr($fechaFin, 0, 8)."30";
                    }
                    
                    $periodoPagoVac = $this->days_360($periodo->fechaInicio,$fechaFinPeriodoVac) + 1 ;
                    
                   
                    $arrComoCalculaProv[30] = array();
                    array_push($arrComoCalculaProv[30],"Calculo el total de diás con fecha inicio: ".$periodo->fechaInicio." y fecha fin: ".$fechaFinPeriodoVac." para un total de ".$periodoPagoVac." días");


                    //INICIO QUITAR LRN VAC
                    $novedadesAus = DB::table("novedad","n")
                    ->selectRaw("sum(a.cantidadDias) as suma")
                    ->join("ausencia as a","a.idAusencia", "=", "n.fkAusencia")
                    ->whereNotNull("n.fkAusencia")            
                    ->where("n.fkEmpleado","=",$empleado->idempleado)
                    ->where("n.fkPeriodoActivo","=",$idPeriodo)
                    ->whereIn("n.fkEstado",["7", "8"]) // Pagada o sin pagar-> no que este eliminada
                    ->first();
                    $periodoPagoVac = $periodoPagoVac - $novedadesAus->suma;
                    if($novedadesAus->suma != 0){
                        array_push($arrComoCalculaProv[30],"Al periodo anterior se le restan ".$novedadesAus->suma." días de ausencia");
                    }
                    
                    //FIN QUITAR LRN PARA VAC
                    
                    
                    
                    
                    
                   
                    
                    
                    
                    $diasVac = $periodoPagoVac * 15 / 360;
                    array_push($arrComoCalculaProv[30],"Se calcula la cantidad de dias disponibles de vacaciones = ".$diasVac." días disponibles");

                    
                    //INICIO QUITAR DIAS VAC
                    $novedadesVacacion = DB::table("novedad","n")
                    ->join("vacaciones as v","v.idVacaciones","=","n.fkVacaciones")
                    ->where("n.fkEmpleado","=",$empleado->idempleado)
                    ->where("n.fkPeriodoActivo","=",$idPeriodo)
                    ->whereIn("n.fkEstado",["7", "8","16"]) // Pagada o sin pagar-> no que este eliminada
                    ->where("n.fechaRegistro","<=",$fechaFin)
                    ->where("n.fechaRegistro",">=",$periodo->fechaInicio)
                    ->whereNotNull("n.fkVacaciones")
                    ->get();
            

                    foreach($novedadesVacacion as $novedadVacacion){
                        array_push($arrComoCalculaProv[30],"Se restan = ". ($novedadVacacion->fkConcepto == "28" ? $novedadVacacion->diasCompensar : $novedadVacacion->diasCompletos)." días de los disponibles");
                        $diasVac = $diasVac - ($novedadVacacion->fkConcepto == "28" ? $novedadVacacion->diasCompensar : $novedadVacacion->diasCompletos);
                        
                    }
                    
                    if(isset($diasVac) && $diasVac < 0 && $empresa->vacacionesNegativas == 0){
                        $diasVac = 0;
                    }
                    $diasVac = round($diasVac, 2);
                    array_push($arrComoCalculaProv[30],"Finalmente se tienen dias disponibles de vacaciones = ".$diasVac." días disponibles");

                    //FIN QUITAR DIAS VAC


                    $fechaInicioParaVacaciones = date("Y-m-d", strtotime($fechaFin." - 1 YEAR"));
                    //$fechaInicioAnio= date("Y-01-01", strtotime($fechaFin));

                    if(strtotime($fechaInicioParaVacaciones) < strtotime($empleado->fechaIngreso) ){
                        $fechaInicioParaVacaciones = date("Y-m-01", strtotime($empleado->fechaIngreso));
                    }
                    /*if(strtotime($fechaInicioParaVacaciones) < strtotime($fechaInicioAnio) ){
                        $fechaInicioParaVacaciones = $fechaInicioAnio;
                    }*/
                    
                    $itemsBoucherSalarialMesesAnterioresVac = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.valor) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$idPeriodo)
                    ->where("ln.fechaInicio","<=",$fechaFin)
                    ->where("ln.fechaInicio",">=",$fechaInicioParaVacaciones)
                    ->where("gcc.fkGrupoConcepto","=","13") //13 - Salarial vacaciones
                    ->first();
                    
                    $salarialVac = $itemsBoucherSalarialMesesAnterioresVac->suma;
                    
                  
                    $diff = $this->days_360($periodo->fechaInicio, $fechaFinPeriodoVac) + 1;
                    $diasTomar = $diff;
                    if($diff> 360){
                        $diasTomar = 360;
                    }
                    
                    if($diasTomar != 0){
                        $salarialVac = ($salarialVac / $diasTomar)*30;
                    }
                    else{
                        $salarialVac = 0;
                    }
               
                    
                    $salarioVac = $salarioMes;

                    
                    if($empleado->fkTipoCotizante == 51){
                        //Todas mis liquidaciones 12 meses atras
                        $fechaFinVac51 = date("Y-m-d", strtotime($fechaInicioMes." - 1 YEAR"));
                        $liquidacionesMesesAnterioresVac51 = DB::table("liquidacionnomina", "ln")
                        ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.diasTrabajados) as diasTrabajadosPer, sum(bp.diasIncapacidad) as diasIncapacidadPer, sum(bp.salarioPeriodoPago) as salarioPago, min(ln.fechaInicio) as minimaFecha")
                        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$idPeriodo)
                        ->where("ln.fechaInicio","<=",$fechaFin)
                        ->where("ln.fechaInicio",">=",$fechaFinVac51)
                        ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9"])
                        ->first();

                        //INICIO CALCULAR EL PROMEDIO SALARIO EN PERIODO ACTUAL    
                        

                        //$totalPeriodoPago51 = $periodoPagoMesActual + (isset($liquidacionesMesesAnterioresCes51->periodPago) ? $liquidacionesMesesAnterioresCes51->periodPago : 0);
                        $totalPeriodoPagoParaSalario51 = (isset($liquidacionesMesesAnterioresVac51->periodPago) ? $liquidacionesMesesAnterioresVac51->periodPago : 0);
                        
                        $retroActivoMesesAnterioresVac51 = DB::table("liquidacionnomina", "ln")
                        ->selectRaw("sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                        ->join("item_boucher_pago as ibp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$idPeriodo)
                        ->where("ln.fechaInicio","<=",$fechaFin)
                        ->where("ln.fechaInicio",">=",$fechaFinVac51)
                        ->where("ibp.fkConcepto","=","49") // 49 - RETROACTIVO SALARIO
                        ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9"])
                        ->first();
                        
                    
                        $salarioVac = ($liquidacionesMesesAnterioresVac51->salarioPago ?? 0) + ($retroActivoMesesAnterioresVac51->suma ?? 0);
                        $salarioVac = ($salarioVac / $totalPeriodoPagoParaSalario51)*30;

                    }


                    $baseVac = $salarioVac + $salarialVac;
                    
                    $liquidacionVac = ($baseVac/30)*$diasVac;

                    
                    array_push($arrComoCalculaProv[30],"Valor salario $".number_format($salarioVac,0,",","."));
                    array_push($arrComoCalculaProv[30],"Valor salarial $".number_format($salarialVac,0,",","."));
                    array_push($arrComoCalculaProv[30],"Valor base $".number_format($baseVac,0,",","."));
                    array_push($arrComoCalculaProv[30],"Dias: ".$diasVac." días");
                    array_push($arrComoCalculaProv[30],"Valor liquidación $".number_format($liquidacionVac,0,",","."));


                    $historicoProvisionVac = DB::table("provision","p")
                    ->selectRaw("sum(p.valor) as sumaValor")
                    ->where("p.fkEmpleado","=",$empleado->idempleado)
                    ->where("p.fkPeriodoActivo","=",$idPeriodo)
                    ->where("p.mes","<",date("m",strtotime($fechaInicio)))
                    ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                    ->where("p.fkConcepto","=","74")
                    ->first();

                    $saldoVacaciones = DB::table("saldo")
                    ->where("fkPeriodoActivo","=",$idPeriodo)
                    ->where("anioAnterior","=",date("Y",strtotime($fechaInicio)))
                    ->where("fkConcepto","=", "74")
                    ->where("fkEstado","=","7")
                    ->first();
                    
                    
                    $pagoVacacionesItems = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.valor) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$idPeriodo)
                    ->where("ln.fechaInicio","<=",$fechaFin)
                    ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")
                    ->whereIn("ibp.fkConcepto",["28","29"]) //VACACIONES
                    ->first();
                
                   
                    array_push($arrComoCalculaProv[30], "Se suma a la liquidación anterior junto con el pago de vacaciones anteriores : $".number_format(( $pagoVacacionesItems->suma ?? 0),0,",","."));
                    array_push($arrComoCalculaProv[30], "Se resta el historico de provisiones por un valor de : $".number_format(( $historicoProvisionVac->sumaValor ?? 0),0,",","."));
                    array_push($arrComoCalculaProv[30], "Se resta el saldo por un valor de : $".number_format((isset($saldoVacaciones->valor) ? $saldoVacaciones->valor : 0),0,",","."));

                    $provAnteriores = $historicoProvisionVac->sumaValor + (isset($saldoVacaciones) ? $saldoVacaciones->valor : 0) - ($pagoVacacionesItems->suma ?? 0);
                                        
                    $provisionVacValor = $liquidacionVac - $provAnteriores;
                    array_push($arrComoCalculaProv[30], "Finalmente se tiene una provision de : $".number_format(( $provisionVacValor ?? 0),0,",","."));

                    $provisionVacaciones = DB::table("provision","p")
                        ->where("p.fkEmpleado","=",$empleado->idempleado)
                        ->where("p.fkPeriodoActivo","=",$idPeriodo)
                        ->where("p.mes","=",date("m",strtotime($fechaInicio)))
                        ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                        ->where("p.fkConcepto","=","74")
                        ->get();
            
                    $arrProvisionVacaciones = array(
                        "fkPeriodoActivo" => $idPeriodo,
                        "fkConcepto" => "74",
                        "fkEmpleado"=> $empleado->idempleado,
                        "mes" => date("m",strtotime($fechaInicio)),
                        "anio"  => date("Y",strtotime($fechaInicio)),
                        "valor" => round($provisionVacValor),
                        "comoCalculo" => implode("<br>",$arrComoCalculaProv[30])
                    );
                    if(sizeof($provisionVacaciones)>0){
                        DB::table("provision")
                        ->where("idProvision","=", $provisionVacaciones[0]->idProvision)
                        ->update($arrProvisionVacaciones);
                    }
                    else{
                        DB::table("provision")
                        ->insert($arrProvisionVacaciones);
                    }
                }
            }
            else if($empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "23"){ //CALCULO PARA SALARIO INTEGRAL DE PROVISION DE VACACIONES

                //Vacaciones
                $fechaInicioMes = date("Y-m-01", strtotime($fechaInicio));
                $anioActual = intval(date("Y",strtotime($fechaInicio)));
                $mesActual = intval(date("m",strtotime($fechaInicio)));
                
                $novedadesRetiro = DB::table("novedad","n")
                ->join("retiro AS r", "r.idRetiro","=","n.fkRetiro")
                ->where("n.fkEmpleado", "=", $empleado->idempleado)
                ->where("n.fkPeriodoActivo", "=", $idPeriodo);
                if($liquidacionNomina->fkEstado == '6'){
                    $novedadesRetiro = $novedadesRetiro->where("n.fkEstado","=","7");
                }
                else{
                    $novedadesRetiro = $novedadesRetiro->where("n.fkEstado","=","8");
                }
                $novedadesRetiro = $novedadesRetiro->whereNotNull("n.fkRetiro")
                ->whereBetween("n.fechaRegistro",[$fechaInicio, $fechaFin])
                ->first();

                $periodo = DB::table("periodo")
                ->where("idPeriodo", "=", $idPeriodo)->first();
                
                if(isset($novedadesRetiro)){
                    $fechaFin = $novedadesRetiro->fecha;
                }       

                if(substr($fechaFin, 8, 2) == "31" || (substr($fechaFin, 8, 2) == "28" && substr($fechaFin, 5, 2) == "02") || (substr($fechaFin, 8, 2) == "29" && substr($fechaFin, 5, 2) == "02")  ){
                    
                    $fechaFin = substr($fechaFin,0,8)."30";

                }
                $fechaFinPeriodoVac=$fechaFin;
                
                $periodoPagoVac = $this->days_360($periodo->fechaInicio, $fechaFinPeriodoVac) + 1 ;
                

                $arrComoCalculaProv[30] = array();
                array_push($arrComoCalculaProv[30],"Calculo el total de días con fecha inicio: ".$periodo->fechaInicio." y fecha fin: ".$fechaFinPeriodoVac." para un total de ".$periodoPagoVac." días");
                //INICIO QUITAR LRN VAC
                $novedadesAus = DB::table("novedad","n")
                ->selectRaw("sum(a.cantidadDias) as suma")
                ->join("ausencia as a","a.idAusencia", "=", "n.fkAusencia")
                ->whereNotNull("n.fkAusencia")            
                ->where("n.fkEmpleado","=",$empleado->idempleado)
                ->where("n.fkPeriodoActivo","=",$idPeriodo)
                ->whereIn("n.fkEstado",["7", "8", "16"]) // Pagada o sin pagar-> no que este eliminada
                ->first();

                
                $periodoPagoVac = $periodoPagoVac - $novedadesAus->suma;
                if($novedadesAus->suma != 0){
                    array_push($arrComoCalculaProv[30],"Al periodo anterior se le restan ".$novedadesAus->suma." días de ausencia");
                }
                //FIN QUITAR LRN PARA VAC
                


                
                               
                

                $diasVac = $periodoPagoVac * 15 / 360;
                array_push($arrComoCalculaProv[30],"Se calcula la cantidad de dias disponibles de vacaciones = ".$diasVac." días disponibles");
                //INICIO QUITAR DIAS VAC
                $novedadesVacacion = DB::table("novedad","n")
                ->join("vacaciones as v","v.idVacaciones","=","n.fkVacaciones")
                ->where("n.fkEmpleado","=",$empleado->idempleado)
                ->where("n.fkPeriodoActivo","=",$idPeriodo)
                ->whereIn("n.fkEstado",["7", "8","16"]) // Pagada o sin pagar-> no que este eliminada
                ->where("n.fechaRegistro",">=",$periodo->fechaInicio)
                ->whereNotNull("n.fkVacaciones")
                ->get();
                
                
                foreach($novedadesVacacion as $novedadVacacion){
                    array_push($arrComoCalculaProv[30],"Se restan = ". $novedadVacacion->diasCompletos." días de los disponibles");
                    $diasVac = $diasVac - $novedadVacacion->diasCompletos;
                }
                
                //NO APLICAN PARA RET - (Creo)
                if(isset($diasVac) && $diasVac < 0 && $empresa->vacacionesNegativas == 0){
                    $diasVac = 0;
                }
                $diasVac = round($diasVac, 2);
                array_push($arrComoCalculaProv[30],"Finalmente se tienen dias disponibles de vacaciones = ".$diasVac." días disponibles");
                //FIN QUITAR DIAS VAC
                


            



                // $diasVac = $totalPeriodoPagoAnioActual * 15 / 360;

                $liquidacionesMesesAnterioresCompleta = DB::table("liquidacionnomina", "ln")
                ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.salarioPeriodoPago) as salarioPago")
                ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$idPeriodo)
                ->where("ln.fechaInicio","<=",$fechaInicioMes)
                ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")       
                ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9"])         
                ->first();

                $primeraLiquidacion = DB::table("liquidacionnomina", "ln")
                ->selectRaw("min(ln.fechaInicio) as primeraFecha")
                ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9"])   
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$idPeriodo)
                ->first();

                $minimaFecha = date("Y-m-d");
                
                if(isset($primeraLiquidacion)){
                    $minimaFecha = $primeraLiquidacion->primeraFecha;
                }
                $diasAgregar = 0;
                //Verificar si dicha nomina es menor a la fecha de ingreso
                if(strtotime($periodo->fechaInicio) < strtotime($minimaFecha)){
                    $diasAgregar = $this->days_360($periodo->fechaInicio, $minimaFecha);
                }
                
               
                $periodoNuevo = $this->days_360($fechaInicio,$fechaFin);
                
                    

                $periodoPagoMesActual = $periodoNuevo + $diasAgregar;

                $totalPeriodoPagoAnioActual = $periodoPagoMesActual + $liquidacionesMesesAnterioresCompleta->periodPago;
                
                $fechaInicioParaVacaciones = date("Y-m-d", strtotime($fechaFin." - 1 YEAR"));
                //$fechaInicioAnio= date("Y-01-01", strtotime($fechaFin));

                if(strtotime($fechaInicioParaVacaciones) < strtotime($empleado->fechaIngreso) ){
                    $fechaInicioParaVacaciones = date("Y-m-01", strtotime($empleado->fechaIngreso));
                }
                /*if(strtotime($fechaInicioParaVacaciones) < strtotime($fechaInicioAnio) ){
                    $fechaInicioParaVacaciones = $fechaInicioAnio;
                }*/
                
                $itemsBoucherSalarialMesesAnterioresVac = DB::table("item_boucher_pago", "ibp")
                ->selectRaw("Sum(ibp.valor) as suma")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$idPeriodo)
                ->where("ln.fechaInicio","<=",$fechaFin)
                ->whereRaw("ln.fechaInicio >= '".$fechaInicioParaVacaciones."'")                
                ->where("gcc.fkGrupoConcepto","=","13") //13 - Salarial vacaciones
                ->first();  
                $salarialVac = ($itemsBoucherSalarialMesesAnterioresVac->suma ?? 0);

                
                $diff = $this->days_360($periodo->fechaInicio, $fechaFinPeriodoVac) + 1;
                $diasTomar = $diff;
                if($diff> 360){
                    $diasTomar = 360;
                }
                /*if($empleado->idempleado==4557){
                    //dd($salarialVac);
                }*/
                
                $salarialVac = ($salarialVac / $diasTomar)*30;

                
                $salarioVac = $salarioMes;
                if($salarioMes == 0){

                }
                
                


                
                $baseVac = $salarioVac + $salarialVac;
                
                $liquidacionVac = ($baseVac/30)*$diasVac;

                array_push($arrComoCalculaProv[30],"Valor salario $".number_format($salarioVac,0,",","."));
                array_push($arrComoCalculaProv[30],"Valor salarial $".number_format($salarialVac,0,",","."));
                array_push($arrComoCalculaProv[30],"Valor base $".number_format($baseVac,0,",","."));
                array_push($arrComoCalculaProv[30],"Dias: ".$diasVac." días");
                array_push($arrComoCalculaProv[30],"Valor liquidación $".number_format($liquidacionVac,0,",","."));

                $historicoProvisionVac = DB::table("provision","p")
                ->selectRaw("sum(p.valor) as sumaValor")
                ->where("p.fkEmpleado","=",$empleado->idempleado)
                ->where("p.fkPeriodoActivo","=",$idPeriodo)
                ->where("p.mes","<",date("m",strtotime($fechaInicio)))
                ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                ->where("p.fkConcepto","=","74")
                ->first();

                $saldoVacaciones = DB::table("saldo")
                ->where("fkPeriodoActivo","=",$idPeriodo)
                ->where("anioAnterior","=",date("Y",strtotime($fechaInicio)))
                ->where("fkConcepto","=", "74")
                ->where("fkEstado","=","7")
                ->first();

                $pagoVacacionesItems = DB::table("item_boucher_pago", "ibp")
                ->selectRaw("Sum(ibp.valor) as suma")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$idPeriodo)
                ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")
                ->whereRaw("ln.fechaInicio <= '".$fechaInicio."'")
                ->whereIn("ibp.fkConcepto",["28","29"]) //VACACIONES
                ->first();

                

                
                array_push($arrComoCalculaProv[30], "Se suma a la liquidación anterior junto con el pago de vacaciones anteriores : $".number_format(( $pagoVacacionesItems->suma ?? 0),0,",","."));
                array_push($arrComoCalculaProv[30], "Se resta el historico de provisiones por un valor de : $".number_format(( $historicoProvisionVac->sumaValor ?? 0),0,",","."));
                array_push($arrComoCalculaProv[30], "Se resta el saldo por un valor de : $".number_format((isset($saldoVacaciones) ? $saldoVacaciones->valor : 0),0,",","."));

                $provAnteriores = $historicoProvisionVac->sumaValor + (isset($saldoVacaciones) ? $saldoVacaciones->valor : 0) - ($pagoVacacionesItems->suma ?? 0);
            
                $provisionVacValor = $liquidacionVac - $provAnteriores;
                array_push($arrComoCalculaProv[30], "Finalmente se tiene una provision de : $".number_format(( $provisionVacValor ?? 0),0,",","."));
            
                //Anterior
                //$provisionVacValor = $liquidacionVac + ($pagoVacacionesItems->suma ?? 0) - $historicoProvisionVac->sumaValor;

            
                $provisionVacaciones = DB::table("provision","p")
                    ->where("p.fkEmpleado","=",$empleado->idempleado)
                    ->where("p.fkPeriodoActivo","=",$idPeriodo)
                    ->where("p.mes","=",date("m",strtotime($fechaInicio)))
                    ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                    ->where("p.fkConcepto","=","74")
                    ->get();

        
                $arrProvisionVacaciones = array(
                    "fkPeriodoActivo" => $idPeriodo,
                    "fkConcepto" => "74",
                    "fkEmpleado"=> $empleado->idempleado,
                    "mes" => date("m",strtotime($fechaInicio)),
                    "anio"  => date("Y",strtotime($fechaInicio)),
                    "valor" => round($provisionVacValor),
                    "comoCalculo" => implode("<br>",$arrComoCalculaProv[30])
                );
                if(sizeof($provisionVacaciones)>0){
                    DB::table("provision")
                    ->where("idProvision","=", $provisionVacaciones[0]->idProvision)
                    ->update($arrProvisionVacaciones);
                }
                else{
                    DB::table("provision")
                    ->insert($arrProvisionVacaciones);
                }
            }

        }catch (Exception $e) {
            dd($e->getMessage()." en la linea ".$e->getLine());
        }
    }

    public function calcularPrimaProv($fechaInicio, $fechaFin, $empleado, $idPeriodo, $salarioMesF){
      
        $periodo = DB::table("periodo")
        ->where("idPeriodo", "=", $idPeriodo)->first();

        $anioActual = intval(date("Y",strtotime($fechaInicio)));
        $mesActual = intval(date("m",strtotime($fechaInicio)));
        $salarioPrima = 0;
        $basePrima = 0;
        $totalPeriodoPago = 0;
        $provisionPrimaValor = 0;
        $fechaInicialPrima = "";
        $fechaFinalPrima = "";
        $mesProyeccion = intval(substr($fechaFin,5,2));
        
        if(substr($fechaFin, 5, 2) == "02" && substr($fechaFin, 8, 2) == "30"){
            $fechaFin = substr($fechaFin,0,8)."28";
            $fechaFin = date("Y-m-t",strtotime($fechaFin));
        }
        
        if($mesProyeccion >= 1 && $mesProyeccion <= 6){


            
            $fechaInicialPrima = $periodo->fechaInicio;
            if(strtotime($fechaInicialPrima)< strtotime(date($anioActual."-01-01"))){
                $fechaInicialPrima = date($anioActual."-01-01");
            }
            $fechaFinalPrima = $fechaFin;
            $fechaFinalPrima1 = $fechaFin;
            $fechaFinalPrima2 = date("Y-m-t",strtotime($fechaFin));

            if(substr($fechaFinalPrima1, 8, 2) == "30" && in_array(substr($fechaFinalPrima1, 5, 2),["12","10","08","07"])){
                $fechaFinalPrima1 = substr($fechaFinalPrima1,0,8)."31";
            }
            else if(substr($fechaFinalPrima1, 8, 2) == "31" && in_array(substr($fechaFinalPrima1, 5, 2),["12","10","08","07"])){
                $fechaFinalPrima1 = substr($fechaFinalPrima1,0,8)."30";
            }
            if(substr($fechaFinalPrima1, 5, 2) == "02" && substr($fechaFinalPrima1, 8, 2) == "30"){
                $fechaFinalPrima1 = date("Y-m-t",strtotime($fechaFin));
            }
            
            $liquidacionesMesesAnterioresPrima = DB::table("liquidacionnomina", "ln")
            ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.diasTrabajados) as diasTrabajadosPer, sum(bp.diasIncapacidad) as diasIncapacidadPer, sum(bp.salarioPeriodoPago) as salarioPago, min(ln.fechaInicio) as minimaFecha")
            ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$idPeriodo)
            ->whereRaw("(ln.fechaFin <= '".$fechaFinalPrima."' or ln.fechaFin <= '".$fechaFinalPrima1."' or ln.fechaFin <= '".$fechaFinalPrima2."')")
            ->where("bp.salarioPeriodoPago",">",0)
            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")
            ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","8","12"])
            ->orderBy("ln.fechaLiquida")
            ->first();
            
            $liquidacionesMesesAnterioresPrimaDetalle = DB::table("liquidacionnomina", "ln")
            ->selectRaw("bp.periodoPago, bp.diasTrabajados, bp.diasIncapacidad, bp.salarioPeriodoPago, ln.fechaInicio, ln.idLiquidacionNomina")
            ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$idPeriodo)
            ->where("ln.fechaInicio","<=",$fechaFinalPrima)
            ->where("ln.fechaFin","<=",$fechaFinalPrima)
            ->where("bp.salarioPeriodoPago","<>",0)     
            ->where("bp.ibc_eps","<>",0)     
            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")
            ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","8","12"])
            ->orderBy("ln.fechaLiquida")
            ->get();

            $fechaIniMesAct = date("Y-m-01",strtotime($fechaFinalPrima));
            $liquidacionesMesesAnterioresPrimaMesAct = DB::table("liquidacionnomina", "ln")
            ->selectRaw("bp.periodoPago, bp.diasTrabajados, bp.diasIncapacidad, bp.salarioPeriodoPago, ln.fechaInicio, ln.idLiquidacionNomina")
            ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$idPeriodo)
            ->where("ln.fechaFin",">",$fechaFinalPrima)
            ->where("ln.fechaInicio","=",$fechaIniMesAct)
            ->where("bp.salarioPeriodoPago",">",0)     
            ->where("bp.ibc_eps",">",0)     
            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")
            ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","8","12"])
            ->orderBy("ln.fechaLiquida")
            ->first();

            if(isset($liquidacionesMesesAnterioresPrimaMesAct)){
                $dias = $this->days_360($fechaIniMesAct, $fechaFinalPrima) + 1;
                $calcularSalMesAct = $liquidacionesMesesAnterioresPrimaMesAct->salarioPeriodoPago*$dias/$liquidacionesMesesAnterioresPrimaMesAct->periodoPago;
                $liquidacionesMesesAnterioresPrima->periodPago += $dias;
                $liquidacionesMesesAnterioresPrima->salarioPago += $calcularSalMesAct;
                //dd($liquidacionesMesesAnterioresPrima, $liquidacionesMesesAnterioresPrimaMesAct, $calcularSalMesAct, $dias);
            }
           
            //INICIO CALCULAR EL PROMEDIO SALARIO EN PERIODO ACTUAL    
            

            $totalPeriodoPago = (isset($liquidacionesMesesAnterioresPrima->periodPago) ? $liquidacionesMesesAnterioresPrima->periodPago : 0);
      
            

            $retroActivoMesesAnterioresPrima = DB::table("liquidacionnomina", "ln")
            ->selectRaw("sum(ibp.valor) as suma")
            ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
            ->join("item_boucher_pago as ibp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$idPeriodo)
            ->where("ln.fechaInicio","<=",$fechaFinalPrima)
            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")
            ->where("ibp.fkConcepto","=","49") // 49 - RETROACTIVO SALARIO
            ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9"])
            ->first();
        
            
            

            $salarioPrima = ($liquidacionesMesesAnterioresPrima->salarioPago ?? 0) + ($retroActivoMesesAnterioresPrima->suma ?? 0);   
            
            //dd($totalPeriodoPago, $fechaInicio, $fechaFin);
            if($totalPeriodoPago == 0){
                
                return [
                    "liquidacionPrima" => 0,
                    "fechaInicialPrima" => $fechaInicialPrima,
                    "fechaFinalPrima" => $fechaFinalPrima,
                    "totalPeriodoPago" => 0,
                    "basePrima" => 0, 
                    "salarioPrima" => 0,
                    "salarialPrima" => 0,
                    "saldoPrima" => 0
                ];
            }
            $salarioPrima = ($salarioPrima / $totalPeriodoPago)*30;            
            
            //AGREGAR SUBSIDIO DE TRANSPORTE AL SALARIO DE SER REQUERIDO
            if($empleado->aplicaSubsidio == "1" && $empleado->tipoRegimen == 'Ley 50'){
                $variablesSalarioMinimo = DB::table("variable")->where("idVariable","=","1")->first();
                if($salarioMesF == 0){
                    $salarioPrimaParaSubsidio = $salarioPrima;                
                }
                else{
                    $salarioPrimaParaSubsidio = $salarioMesF;                
                }
                
                if($salarioPrimaParaSubsidio < (2 * $variablesSalarioMinimo->valor)){
                    $variablesSubTrans = DB::table("variable")->where("idVariable","=","2")->first();
                    $salarioPrima = $salarioPrima + $variablesSubTrans->valor;
                }                
            }
            else{
                //Verificar que el concepto 124	AUXILIO DE CONECTIVIDAD DIGITAL exista dentro de sus conceptos fijos, de ser asi agregarlo al salario
                $conceptosFijoAuxilioConectividad = DB::table("conceptofijo")
                ->where("fkConcepto","=",'124')
                ->where("fkEstado","=","1")
                ->where("fkEmpleado","=",$empleado->idempleado)
                ->first();
                if(isset($conceptosFijoAuxilioConectividad)){
                    $variablesSalarioMinimo = DB::table("variable")->where("idVariable","=","1")->first();
                    if($salarioMesF == 0){
                        $salarioPrimaParaSubsidio = $salarioPrima;                
                    }
                    else{
                        $salarioPrimaParaSubsidio = $salarioMesF;                
                    }        
                    if($salarioPrimaParaSubsidio < (2 * $variablesSalarioMinimo->valor)){
                        $variablesSubTrans = DB::table("variable")->where("idVariable","=","2")->first();
                        $salarioPrima = $salarioPrima + $variablesSubTrans->valor;
                    }
                }                
            }
            
            //FIN AGREGAR SUBSIDIO DE TRANSPORTE AL SALARIO DE SER REQUERIDO
            //FIN CALCULAR EL PROMEDIO SALARIO EN PERIODO ACTUAL            

            //INICIO CALCULAR EL PROMEDIO SALARIAL EN PERIODO ACTUAL            
            $itemsBoucherSalarialMesesAnteriores = DB::table("item_boucher_pago", "ibp")
            ->selectRaw("Sum(ibp.valor) as suma")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$idPeriodo)
            ->where("ln.fechaInicio","<=",$fechaFinalPrima2)
            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")                
            ->where("gcc.fkGrupoConcepto","=","11") //11 - Salarial
            ->first();
            $salarialPrima = $itemsBoucherSalarialMesesAnteriores->suma;
            //dd($salarialPrima, $fechaFinalPrima);
            if(substr($fechaFinalPrima, 5, 2) == "02" && intval(substr($fechaFinalPrima, 8, 2)) >= 28){
                $fechaFinalPrima = substr($fechaFin,0,8)."30";
            }
            

            $totalPeriodoPagoReal = $this->days_360($fechaInicialPrima, $fechaFinalPrima);
            $totalPeriodoPagoReal++;
            
            if($totalPeriodoPagoReal != 0){
                $salarialPrima = ($salarialPrima / $totalPeriodoPagoReal)*30;
            }            
            //FIN CALCULAR EL PROMEDIO SALARIAL EN PERIODO ACTUAL      
            $basePrima = $salarioPrima + $salarialPrima;               
            $liquidacionPrima = ($basePrima / 360) * $totalPeriodoPagoReal;
            $totalPeriodoPago = $totalPeriodoPagoReal;
        }
        else if($mesProyeccion >= 7 && $mesProyeccion <= 12){


            $fechaInicialPrima = $periodo->fechaInicio;
            if(strtotime($fechaInicialPrima)< strtotime(date($anioActual."-07-01"))){
                $fechaInicialPrima = date($anioActual."-07-01");
            }
            
            $fechaFinalPrima = $fechaFin;
            $fechaFinalPrima1 = $fechaFin;
            $fechaFinalPrima2 = date("y-m-t",strtotime($fechaFin));

            if(substr($fechaFinalPrima1, 8, 2) == "30" && in_array(substr($fechaFinalPrima1, 5, 2),["12","10","08","07"])){
                $fechaFinalPrima1 = substr($fechaFinalPrima1,0,8)."31";
            }
            else if(substr($fechaFinalPrima1, 8, 2) == "31" && in_array(substr($fechaFinalPrima1, 5, 2),["12","10","08","07"])){
                $fechaFinalPrima1 = substr($fechaFinalPrima1,0,8)."30";
            }
            
            if(substr($fechaFinalPrima1, 5, 2) == "02" && substr($fechaFinalPrima1, 8, 2) == "30"){
                $fechaFinalPrima1 = date("y-m-t",strtotime($fechaFin));
            }


            $liquidacionesMesesAnterioresPrima = DB::table("liquidacionnomina", "ln")
            ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.diasTrabajados) as diasTrabajadosPer, sum(bp.diasIncapacidad) as diasIncapacidadPer, sum(bp.salarioPeriodoPago) as salarioPago, min(ln.fechaInicio) as minimaFecha")
            ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$idPeriodo)
            ->whereRaw("(ln.fechaFin <= '".$fechaFinalPrima."' or ln.fechaFin <= '".$fechaFinalPrima1."' or ln.fechaFin <= '".$fechaFinalPrima2."')")
            ->where("bp.salarioPeriodoPago",">",0)
            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."' and MONTH(ln.fechaInicio) > 6")
            ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","8","12"])
            ->orderBy("ln.fechaLiquida")
            ->first();
            
            $liquidacionesMesesAnterioresPrimaDetalle = DB::table("liquidacionnomina", "ln")
            ->selectRaw("bp.periodoPago, bp.diasTrabajados, bp.diasIncapacidad, bp.salarioPeriodoPago, ln.fechaInicio, ln.idLiquidacionNomina")
            ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$idPeriodo)
            ->where("ln.fechaInicio","<=",$fechaFinalPrima)
            ->where("ln.fechaFin","<=",$fechaFinalPrima)
            ->where("bp.salarioPeriodoPago","<>",0)     
            ->where("bp.ibc_eps","<>",0)     
            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."' and MONTH(ln.fechaInicio) > 6")
            ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","8","12"])
            ->orderBy("ln.fechaLiquida")
            ->get();

            $fechaIniMesAct = date("Y-m-01",strtotime($fechaFinalPrima));
            $liquidacionesMesesAnterioresPrimaMesAct = DB::table("liquidacionnomina", "ln")
            ->selectRaw("bp.periodoPago, bp.diasTrabajados, bp.diasIncapacidad, bp.salarioPeriodoPago, ln.fechaInicio, ln.idLiquidacionNomina")
            ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$idPeriodo)
            ->where("ln.fechaFin",">",$fechaFinalPrima2)
            ->where("ln.fechaInicio","=",$fechaIniMesAct)
            ->where("bp.salarioPeriodoPago",">",0)     
            ->where("bp.ibc_eps",">",0)     
            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."' and MONTH(ln.fechaInicio) > 6")
            ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","8","12"])
            ->orderBy("ln.fechaLiquida")
            ->first();
            
            if(isset($liquidacionesMesesAnterioresPrimaMesAct)){
                $dias = $this->days_360($fechaIniMesAct, $fechaFinalPrima) + 1;
                $calcularSalMesAct = $liquidacionesMesesAnterioresPrimaMesAct->salarioPeriodoPago*$dias/$liquidacionesMesesAnterioresPrimaMesAct->periodoPago;
                $liquidacionesMesesAnterioresPrima->periodPago += $dias;
                $liquidacionesMesesAnterioresPrima->salarioPago += $calcularSalMesAct;
                //dd($liquidacionesMesesAnterioresPrima, $liquidacionesMesesAnterioresPrimaMesAct, $calcularSalMesAct, $dias);
            }
            //INICIO CALCULAR EL PROMEDIO SALARIO EN PERIODO ACTUAL 
            $totalPeriodoPago = (isset($liquidacionesMesesAnterioresPrima->periodPago) ? $liquidacionesMesesAnterioresPrima->periodPago : 0);

            
            $retroActivoMesesAnterioresPrima = DB::table("liquidacionnomina", "ln")
            ->selectRaw("sum(ibp.valor) as suma")
            ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
            ->join("item_boucher_pago as ibp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$idPeriodo)
            ->where("ln.fechaInicio","<=",$fechaFinalPrima)
            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."' and MONTH(ln.fechaInicio) > 6")
            ->where("ibp.fkConcepto","=","49") // 49 - RETROACTIVO SALARIO
            ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9"])
            ->first();

            
            $salarioPrima = ($liquidacionesMesesAnterioresPrima->salarioPago ?? 0) + ($retroActivoMesesAnterioresPrima->suma ?? 0);
            
            if($totalPeriodoPago == 0){
                return [
                    "liquidacionPrima" => 0,
                    "fechaInicialPrima" => $fechaInicialPrima,
                    "fechaFinalPrima" => $fechaFinalPrima,
                    "totalPeriodoPago" => 0,
                    "basePrima" => 0, 
                    "salarioPrima" => 0,
                    "salarialPrima" => 0,
                    "saldoPrima" => 0
                ];
              
            }
            
            $salarioPrima = ($salarioPrima / $totalPeriodoPago)*30;
            
            
            
           

            //AGREGAR SUBSIDIO DE TRANSPORTE AL SALARIO DE SER REQUERIDO
            if($empleado->aplicaSubsidio == "1" && $empleado->tipoRegimen == 'Ley 50'){
                $variablesSalarioMinimo = DB::table("variable")->where("idVariable","=","1")->first();
                //$salarioPrimaParaSubsidio = ($salarioPrima/$periodoPagoMesActual)*30;
                if($salarioMesF == 0){
                    $salarioPrimaParaSubsidio = $salarioPrima;                
                }
                else{
                    $salarioPrimaParaSubsidio = $salarioMesF;                
                }   

                if($salarioPrimaParaSubsidio < (2 * $variablesSalarioMinimo->valor)){
                    $variablesSubTrans = DB::table("variable")->where("idVariable","=","2")->first();
                    $salarioPrima = $salarioPrima + $variablesSubTrans->valor;
                }
            }
            else{
                //Verificar que el concepto 124	AUXILIO DE CONECTIVIDAD DIGITAL exista dentro de sus conceptos fijos, de ser asi agregarlo al salario
                $conceptosFijoAuxilioConectividad = DB::table("conceptofijo")
                ->where("fkConcepto","=",'124')
                ->where("fkEstado","=","1")
                ->where("fkEmpleado","=",$empleado->idempleado)
                ->first();
                if(isset($conceptosFijoAuxilioConectividad)){
                    $variablesSalarioMinimo = DB::table("variable")->where("idVariable","=","1")->first();
                    if($salarioMesF == 0){
                        $salarioPrimaParaSubsidio = $salarioPrima;                
                    }
                    else{
                        $salarioPrimaParaSubsidio = $salarioMesF;                
                    }              
                    if($salarioPrimaParaSubsidio < (2 * $variablesSalarioMinimo->valor)){
                        $variablesSubTrans = DB::table("variable")->where("idVariable","=","2")->first();
                        $salarioPrima = $salarioPrima + $variablesSubTrans->valor;
                    }
                }                
            }
            //FIN AGREGAR SUBSIDIO DE TRANSPORTE AL SALARIO DE SER REQUERIDO
            //FIN CALCULAR EL PROMEDIO SALARIO EN PERIODO ACTUAL    


            //INICIO CALCULAR EL PROMEDIO SALARIAL EN PERIODO ACTUAL      
            $itemsBoucherSalarialMesesAnteriores = DB::table("item_boucher_pago", "ibp")
            ->selectRaw("Sum(ibp.valor) as suma")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$idPeriodo)
            ->where("ln.fechaInicio","<=",$fechaFinalPrima2)
            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."' and MONTH(ln.fechaInicio) > 6")                
            ->where("gcc.fkGrupoConcepto","=","11") //11 - Salarial
            ->first();
            
            $salarialPrima = $itemsBoucherSalarialMesesAnteriores->suma;
            $totalPeriodoPagoReal = $this->days_360($fechaInicialPrima, $fechaFinalPrima);
            $totalPeriodoPagoReal++;
            $salarialPrima = ($salarialPrima / $totalPeriodoPagoReal)*30;
            //FIN CALCULAR EL PROMEDIO SALARIAL EN PERIODO ACTUAL      
            $basePrima = $salarioPrima + $salarialPrima;
            $liquidacionPrima = ($basePrima / 360) * $totalPeriodoPagoReal;
            $totalPeriodoPago = $totalPeriodoPagoReal;
        }
        
        $saldoPrima = DB::table("saldo")
        ->where("fkEmpleado","=",$empleado->idempleado);
        if($mesProyeccion >= 1 && $mesProyeccion <= 6){
            $saldoPrima = $saldoPrima->where("mesAnterior","<=","6");
        }
        else if($mesProyeccion >= 7 && $mesProyeccion <= 12){
            $saldoPrima = $saldoPrima->where("mesAnterior",">","6");
        }
        $saldoPrima = $saldoPrima->where("anioAnterior","=",date("Y",strtotime($fechaInicio)))
        ->where("fkConcepto","=", "73")
        ->where("fkEstado","=","7")
        ->first();
        

        //dump($salarioPrima);
        
        
        return [
            "liquidacionPrima" => $liquidacionPrima,
            "fechaInicialPrima" => $fechaInicialPrima,
            "fechaFinalPrima" => $fechaFinalPrima,
            "totalPeriodoPago" => $totalPeriodoPago,
            "basePrima" => $basePrima, 
            "salarioPrima" => $salarioPrima,
            "salarialPrima" => $salarialPrima,
            "saldoPrima" => $saldoPrima
        ];
    }
    public function days_360($fecha1,$fecha2,$europeo=true) {
        //try switch dates: min to max
        if( $fecha1 > $fecha2 ) {
        $temf = $fecha1;
        $fecha1 = $fecha2;
        $fecha2 = $temf;
        }
    
        list($yy1, $mm1, $dd1) = explode('-', $fecha1);
        list($yy2, $mm2, $dd2) = explode('-', $fecha2);
    
        if( $dd1==31) { $dd1 = 30; }
    
        if(!$europeo) {
        if( ($dd1==30) and ($dd2==31) ) {
            $dd2=30;
        } else {
            if( $dd2==31 ) {
            $dd2=30;
            }
        }
        }
    
        if( ($dd1<1) or ($dd2<1) or ($dd1>30) or ($dd2>31) or
            ($mm1<1) or ($mm2<1) or ($mm1>12) or ($mm2>12) or
            ($yy1>$yy2) ) {
        return(-1);
        }
        if( ($yy1==$yy2) and ($mm1>$mm2) ) { return(-1); }
        if( ($yy1==$yy2) and ($mm1==$mm2) and ($dd1>$dd2) ) { return(-1); }
    
        //Calc
        $yy = $yy2-$yy1;
        $mm = $mm2-$mm1;
        $dd = $dd2-$dd1;
    
        return( ($yy*360)+($mm*30)+$dd );
    }
}
