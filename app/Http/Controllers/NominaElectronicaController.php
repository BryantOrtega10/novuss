<?php

namespace App\Http\Controllers;

use Facade\Ignition\DumpRecorder\Dump;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use stdClass;
use ZipArchive;

class NominaElectronicaController extends Controller
{

    public function verFormNominaElectronica(){
        $dataUsu = UsuarioController::dataAdminLogueado();
        $empresas = DB::table("empresa", "e");
        if(isset($dataUsu) && $dataUsu->fkRol == 2){            
            $empresas = $empresas->whereIn("idempresa", $dataUsu->empresaUsuario);
        }
        $empresas = $empresas->orderBy("razonSocial")->get();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu 'Nómina electrónica'");

        return view('/reportes.seleccionarNominaElectronica',[
            'empresas' => $empresas,
            "dataUsu" => $dataUsu            
        ]);
    }


    public function verFormNominaReemplazo(){
        $dataUsu = UsuarioController::dataAdminLogueado();
        $empresas = DB::table("empresa", "e");
        if(isset($dataUsu) && $dataUsu->fkRol == 2){            
            $empresas = $empresas->whereIn("idempresa", $dataUsu->empresaUsuario);
        }
        $empresas = $empresas->orderBy("razonSocial")->get();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu 'Nómina electrónica de reemplazo'");

        return view('/reportes.seleccionarNominaReemplazo',[
            'empresas' => $empresas,
            "dataUsu" => $dataUsu            
        ]);
    }

    public function verFormNominaReemplazoMasivo(){
        $dataUsu = UsuarioController::dataAdminLogueado();
        $empresas = DB::table("empresa", "e");
        if(isset($dataUsu) && $dataUsu->fkRol == 2){            
            $empresas = $empresas->whereIn("idempresa", $dataUsu->empresaUsuario);
        }
        $empresas = $empresas->orderBy("razonSocial")->get();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu 'Nómina electrónica de reemplazo masivo'");

        return view('/reportes.seleccionarNominaReemplazoMasivo',[
            'empresas' => $empresas,
            "dataUsu" => $dataUsu            
        ]);
    }

    public function verFormNominaEliminacionMasivo(){
        $dataUsu = UsuarioController::dataAdminLogueado();
        $empresas = DB::table("empresa", "e");
        if(isset($dataUsu) && $dataUsu->fkRol == 2){            
            $empresas = $empresas->whereIn("idempresa", $dataUsu->empresaUsuario);
        }
        $empresas = $empresas->orderBy("razonSocial")->get();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu 'Nómina electrónica de eliminación masivo'");

        return view('/reportes.seleccionarNominaEliminacionMasivo',[
            'empresas' => $empresas,
            "dataUsu" => $dataUsu            
        ]);
    }
    
    public function arreglosGenerales($fechaReporte, $idEmpresa, $arrReemplazo = array(), $send_email = false, $cesantias_sin_pagar = false){
        $empresa = DB::table("empresa","e")->where("e.idempresa","=",$idEmpresa)->first();
        $prefijo = $empresa->PrefijoNominaElectronica;
        $prefijoReemplazo = $empresa->PrefijoNominaElectronicaReemplazo;
        $TipoXML = "102"; //102 NominaIndividual, 103 NominaIndividualDeAjuste
        if(isset($arrReemplazo["empleado"]) || isset($arrReemplazo["data"])){
            $TipoXML = "103"; //102 NominaIndividual, 103 NominaIndividualDeAjuste
        }        
        $SoftwarePin = "4123412";
        $SoftwareDianId = $empresa->SoftwareDianId;
        $SoftwareTestSetId = $empresa->SoftwareTestSetId;
        $TipAmb = $empresa->TipAmbNominaElectronica; //1 Producción, 2 Pruebas
        $NIT_proveedor = "";
        $DV_proveedor = "";
        $SoftwareID_proveedor = "";
        $SoftwareSC_proveedor = "";
        $HoraGen = date("H:i:s")."‐05:00";
        $arrPeriodoNomina = array(
            "15" => "4",
            "30" => "5"
        );
        $arrMetodo = array(
            "Transferencia" => 31,
            "Efectivo" => 10,
            "Cheque" => 20,
            "Otra forma pago" => "ZZZ"
        );


        $arrMetodoJSON_DATAICO = array(
            "Transferencia" => "TRANSFERENCIA_DEBITO",
            "Efectivo" => "EFECTIVO",
            "Cheque" => "CHEQUE",
            "Otra forma pago" => "ZZZ"
        );
        
        
        $arrPeriodoNominaJSON_DATAICO = array(
            "15" => "QUINCENAL",
            "30" => "MENSUAL"
        );
       

        $fechaInicioMesActual = date("Y-m-01", strtotime($fechaReporte));
        $fechaFinMesActual = date("Y-m-t", strtotime($fechaReporte));
        
        $empleadosGen = DB::table('empleado', 'e')
        ->select("e.*", "dp.*", "ti.siglaPila", "ti.cod_nomina_elec", "ti.cod_nomina_elec_dataico","p.fkEstado as estado", "p.fechaInicio as fechaInicioPeriodo"
        , "p.fechaFin as fechaFinPeriodo", "p.fkNomina as nominaPeriodo", "p.idPeriodo", "p.fkTipoCotizante as fkTipoCotizantePeriodo"
        ,"emp.fkUbicacion as ubicacionEmpresa", "n.periodo as periodoNomina",
        "emp.razonSocial as nombreEmpresa","emp.documento as nitEmpresa", "emp.digitoVerificacion as digitoVerificacionEmpresa", 
        "emp.direccion as direccionEmpresa","tc.tipo_con_nomina_elec", "tc.tipo_con_nomina_elec_dataico", "p.salario as salarioPeriodoPago", 
        "ter_entidad.razonSocial as nombreEntidad",  DB::raw("GROUP_CONCAT(ln.fechaLiquida) as fechasLiquidacion"),
        "ter_entidad_periodo.razonSocial as nombreEntidadPeriodo","p.fkCargo as fkCargoPeriodo",
        "p.fkTipoContrato as fkTipoContratoPeriodo",
        "p.esPensionado as esPensionadoPeriodo",
        "p.tipoRegimen as tipoRegimenPeriodo",
        "p.tipoRegimenPensional as tipoRegimenPensionalPeriodo",
        "p.fkUbicacionLabora as fkUbicacionLaboraPeriodo",
        "p.fkLocalidad as fkLocalidadPeriodo",
        "p.sabadoLaborable as sabadoLaborablePeriodo",
        "p.formaPago as formaPagoPeriodo",
        "p.fkEntidad as fkEntidadPeriodo",
        "p.numeroCuenta as numeroCuentaPeriodo",
        "p.tipoCuenta as tipoCuentaPeriodo",
        "p.otraFormaPago as otraFormaPagoPeriodo",
        "p.fkTipoOtroDocumento as fkTipoOtroDocumentoPeriodo",
        "p.otroDocumento as otroDocumentoPeriodo",
        "p.procedimientoRetencion as procedimientoRetencionPeriodo",
        "p.porcentajeRetencion as porcentajeRetencionPeriodo",
        "p.fkNivelArl as fkNivelArlPeriodo",
        "p.fkCentroTrabajo as fkCentroTrabajoPeriodo",
        "p.aplicaSubsidio as aplicaSubsidioPeriodo")
        ->join("datospersonales AS dp", "e.fkDatosPersonales", "=" , "dp.idDatosPersonales")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion","=","dp.fkTipoIdentificacion")
        ->join("periodo as p","p.fkEmpleado", "=","e.idempleado")
        ->join("nomina as n", "n.idNomina", "=","p.fkNomina")
        ->leftJoin('boucherpago as bp', function ($join) {
            $join->on('bp.fkEmpleado', '=', 'e.idempleado')
                ->on('bp.fkPeriodoActivo', '=', 'p.idPeriodo');                
        })
        ->join("tipocontrato as tc","idtipoContrato","=","p.fkTipoContrato","left")
        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
        ->join("empresa as emp","emp.idEmpresa", "=","n.fkEmpresa")
        ->join("tercero as ter_entidad","ter_entidad.idTercero","=","e.fkEntidad","left")
        ->join("tercero as ter_entidad_periodo","ter_entidad_periodo.idTercero","=","p.fkEntidad","left")
        ->where("n.fkEmpresa","=",$idEmpresa)
        ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9"]) //1 - Normal, 2- Retiro
        ->where("ln.fkEstado","=","5")
        ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
        //->where("dp.numeroIdentificacion","=","1072961374")
        ;

        if(isset($arrReemplazo["empleado"])){
            $empleadosGen = $empleadosGen->where("e.idempleado","=",$arrReemplazo["empleado"]);
        }
        
        if(isset($arrReemplazo["data"])){
            $empleadosGen = $empleadosGen->whereIn("dp.numeroIdentificacion",array_keys($arrReemplazo["data"]));
        }
        
        $empleadosGen = $empleadosGen->distinct()
        ->orderby("e.idempleado")
        ->groupBy("p.idPeriodo")
        ->get();
        
        $consecutivo = $empresa->ConsecutivoNominaElectronica;
        $consecutivoReemplazo = $empresa->ConsecutivoNominaElectronicaReemplazo;

        $arrNominas = array();
        $arrPreExcel = array();
        
        $nies = DB::table("grupos_nomina_electronica","gne")->get();
        $arrTitulos = array();
        array_push($arrTitulos, "Consecutivo");
        array_push($arrTitulos, "FechaIngreso");
        array_push($arrTitulos, "FechaRetiro");
        array_push($arrTitulos, "FechaLiquidacionInicio");
        array_push($arrTitulos, "FechaLiquidacionFin");
        array_push($arrTitulos, "TiempoLaborado");
        array_push($arrTitulos, "TipoTrabajador");
        array_push($arrTitulos, "SubTipoTrabajador");
        array_push($arrTitulos, "AltoRiesgoPension");
        array_push($arrTitulos, "TipoDocumento");
        array_push($arrTitulos, "NumeroDocumento");
        array_push($arrTitulos, "PrimerApellido");
        array_push($arrTitulos, "SegundoApellido");
        array_push($arrTitulos, "PrimerNombre");
        array_push($arrTitulos, "OtrosNombres");
        array_push($arrTitulos, "LugarTrabajoPais");
        array_push($arrTitulos, "LugarTrabajoDepartamentoEstado");
        array_push($arrTitulos, "LugarTrabajoMunicipioCiudad");
        array_push($arrTitulos, "LugarTrabajoDireccion");
        array_push($arrTitulos, "SalarioIntegral");
        array_push($arrTitulos, "TipoContrato");
        array_push($arrTitulos, "Sueldo");
        array_push($arrTitulos, "CodigoTrabajador");
        array_push($arrTitulos, "Forma");
        array_push($arrTitulos, "Metodo");
        array_push($arrTitulos, "Banco");
        array_push($arrTitulos, "TipoCuenta");
        array_push($arrTitulos, "NumeroCuenta");
        array_push($arrTitulos, "FechasPagos");
        
        foreach($nies as $nie){
            array_push($arrTitulos, $nie->idGrupoNominaElectronica);
        }
        array_push($arrTitulos, "DevengadosTotal");
        array_push($arrTitulos, "DeduccionesTotal");
        array_push($arrTitulos, "ComprobanteTotal");

        $arrFinalJSON_DATAICO = array();

        foreach($empleadosGen as $empleado){

            $arrFila = array();

            $arrFila["Consecutivo"] = $consecutivo;
            if(isset($arrReemplazo["empleado"]) || isset($arrReemplazo["data"])){
                $arrFila["Consecutivo"] = $consecutivoReemplazo;
            }


            $arrJSON_DATAICO = array();
            $nominaIndividial = new Etiqueta("NominaIndividual",array( 
                "xmlns"=>"dian:gov:co:facturaelectronica:NominaIndividual",
                "xmlns:xs"=>"http://www.w3.org/2001/XMLSchema-instance",
                "xmlns:ds"=>"http://www.w3.org/2000/09/xmldsig#",
                "xmlns:ext"=>"urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2",
                "xmlns:xades"=>"http://uri.etsi.org/01903/v1.3.2#",
                "xmlns:xades141"=>"http://uri.etsi.org/01903/v1.4.1#",
                "xmlns:xsi"=>"http://www.w3.org/2001/XMLSchema-instance",
                "SchemaLocation"=>"",
                "xsi:schemaLocation"=>"dian:gov:co:facturaelectronica:NominaIndividual NominaIndividualElectronicaXSD.xsd"
            ));
    
    
            array_push($nominaIndividial->hijos, new Etiqueta("ext:UBLExtensions"));

            $empleado->fkTipoCotizante = ($empleado->fkTipoCotizantePeriodo ?? $empleado->fkTipoCotizante);
            $empleado->fkCargo = ($empleado->fkCargoPeriodo ?? $empleado->fkCargo);
            
            $empleado->esPensionado = ($empleado->esPensionadoPeriodo ?? $empleado->esPensionado);
            $empleado->tipoRegimen = ($empleado->tipoRegimenPeriodo ?? $empleado->tipoRegimen);
            $empleado->tipoRegimenPensional = ($empleado->tipoRegimenPensionalPeriodo ?? $empleado->tipoRegimenPensional);
            $empleado->fkUbicacionLabora = ($empleado->fkUbicacionLaboraPeriodo ?? $empleado->fkUbicacionLabora);
            $empleado->fkLocalidad = ($empleado->fkLocalidadPeriodo ?? $empleado->fkLocalidad);
            $empleado->sabadoLaborable = ($empleado->sabadoLaborablePeriodo ?? $empleado->sabadoLaborable);
            $empleado->formaPago = ($empleado->formaPagoPeriodo ?? $empleado->formaPago);
            $empleado->fkEntidad = ($empleado->fkEntidadPeriodo ?? $empleado->fkEntidad);
            $empleado->numeroCuenta = ($empleado->numeroCuentaPeriodo ?? $empleado->numeroCuenta);
            $empleado->tipoCuenta = ($empleado->tipoCuentaPeriodo ?? $empleado->tipoCuenta);
            $empleado->otraFormaPago = ($empleado->otraFormaPagoPeriodo ?? $empleado->otraFormaPago);
            $empleado->fkTipoOtroDocumento = ($empleado->fkTipoOtroDocumentoPeriodo ?? $empleado->fkTipoOtroDocumento);
            $empleado->otroDocumento = ($empleado->otroDocumentoPeriodo ?? $empleado->otroDocumento);
            $empleado->procedimientoRetencion = ($empleado->procedimientoRetencionPeriodo ?? $empleado->procedimientoRetencion);
            $empleado->porcentajeRetencion = ($empleado->porcentajeRetencionPeriodo ?? $empleado->porcentajeRetencion);
            $empleado->fkNivelArl = ($empleado->fkNivelArlPeriodo ?? $empleado->fkNivelArl);
            $empleado->fkCentroTrabajo = ($empleado->fkCentroTrabajoPeriodo ?? $empleado->fkCentroTrabajo);
            $empleado->aplicaSubsidio = ($empleado->aplicaSubsidioPeriodo ?? $empleado->aplicaSubsidio);
            $empleado->nombreEntidad = ($empleado->nombreEntidadPeriodo ?? $empleado->nombreEntidad);
            
            
            $ValDev = 0;
            $ValDed = 0;
            $ValTolNE = 0;
            $NitNE = "000000000";

            $periodo = new Etiqueta("Periodo");
            $periodo->atributos["FechaIngreso"] = $empleado->fechaInicioPeriodo;

            $arrFila["FechaIngreso"] = $empleado->fechaInicioPeriodo;

            $novedadesRetiro = DB::table("novedad","n")
            ->select("r.fecha","r.fechaReal")
            ->join("retiro as r", "r.idRetiro", "=","n.fkRetiro")
            ->where("n.fkEmpleado","=", $empleado->idempleado)
            ->where("n.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->whereIn("n.fkEstado",["8"]) // Pagada-> no que este eliminada
            ->whereNotNull("n.fkRetiro")
            ->whereBetween("n.fechaRegistro",[$fechaInicioMesActual, $fechaFinMesActual])
            ->orderBy("r.fecha","desc")
            ->first();
            if(date('Y-m-01',strtotime($empleado->fechaInicioPeriodo)) == $fechaInicioMesActual){
                $fechaI = $empleado->fechaInicioPeriodo;
            }
            else{
                $fechaI = $fechaInicioMesActual;
            }
            $fechaRetiroJSON = null;

            if(isset($novedadesRetiro)){
                $periodo->atributos["FechaRetiro"] = $novedadesRetiro->fechaReal;
                $arrFila["FechaRetiro"] = $novedadesRetiro->fechaReal;
                $fechaF = $novedadesRetiro->fechaReal;
                $fechaRetiroJSON = $novedadesRetiro->fechaReal;
            }
            else{
                $fechaF = $fechaFinMesActual;
            }

            if(substr($fechaF, 8, 2) == "31" || (substr($fechaF, 8, 2) == "28" && substr($fechaF, 5, 2) == "02") || (substr($fechaF, 8, 2) == "29" && substr($fechaF, 5, 2) == "02")  ){
                $fechaF = substr($fechaF,0,8)."30";
            }


            $fechasLq = explode(",",$empleado->fechasLiquidacion);
            $fechasLq = array_unique($fechasLq);
            $fechasLqDataico = $fechasLq;
            foreach($fechasLqDataico as $row => $fechaLqDataico){
                $fechasLqDataico[$row] = date("d/m/Y",strtotime($fechaLqDataico));
            }
            
            $arrJSON_DATAICO["notes"] = [];
            
            if($send_email){                
                $arrJSON_DATAICO["send-email"] = true;
            }
            $arrJSON_DATAICO["payment-date"] = $fechasLqDataico;
            $arrJSON_DATAICO["initial-settlement-date"] = date("d/m/Y", strtotime($fechaInicioMesActual));
            $arrJSON_DATAICO["periodicity"] = $arrPeriodoNominaJSON_DATAICO[$empleado->periodoNomina];
            $arrJSON_DATAICO["final-settlement-date"] = date("d/m/Y", strtotime($fechaFinMesActual));
            if(isset($arrReemplazo["empleado"]) || isset($arrReemplazo["data"])){
                $arrJSON_DATAICO["prefix"] = $prefijoReemplazo;
            }
            else{
                $arrJSON_DATAICO["prefix"] = $prefijo;
            }
            
            

            

            $periodo->atributos["FechaLiquidacionInicio"] = date("Y-m-d", strtotime($fechaInicioMesActual));
            $periodo->atributos["FechaLiquidacionFin"] = date("Y-m-d", strtotime($fechaFinMesActual));
            $periodo->atributos["TiempoLaborado"] = $this->days_360($fechaI, $fechaF) + 1;
            $periodo->atributos["FechaGen"] = date("Y-m-d", strtotime($fechaReporte));

            $arrFila["FechaLiquidacionInicio"] = date("Y-m-d", strtotime($fechaInicioMesActual));
            $arrFila["FechaLiquidacionFin"] = date("Y-m-d", strtotime($fechaFinMesActual));
            $arrFila["TiempoLaborado"] = $this->days_360($fechaI, $fechaF) + 1;
            


            array_push($nominaIndividial->hijos, $periodo);

            $NumeroSecuenciaXML = new Etiqueta("NumeroSecuenciaXML",[
                "CodigoTrabajador" => $empleado->idempleado,
                "Prefijo" => $prefijo,
                "Consecutivo" => $consecutivo,
                "Numero" => $prefijo.$consecutivo
            ]);

            if(isset($arrReemplazo["empleado"]) || isset($arrReemplazo["data"])){
                $NumeroSecuenciaXML = new Etiqueta("NumeroSecuenciaXML",[
                    "CodigoTrabajador" => $empleado->idempleado,
                    "Prefijo" => $prefijoReemplazo,
                    "Consecutivo" => $consecutivoReemplazo,
                    "Numero" => $prefijo.$consecutivoReemplazo
                ]);
            }

            array_push($nominaIndividial->hijos, $NumeroSecuenciaXML);


            $LugarGeneracionXML = new Etiqueta("LugarGeneracionXML",[
                "Pais" => "CO",
                "DepartamentoEstado" => substr($empleado->ubicacionEmpresa, 2, 2),
                "MunicipioCiudad" => substr($empleado->ubicacionEmpresa, 2, 5),
                "Idioma" => "es"
            ]);
            array_push($nominaIndividial->hijos, $LugarGeneracionXML);
            
            $ProveedorXML = new Etiqueta("ProveedorXML",[
                "NIT" => $NIT_proveedor,
                "DV" => $DV_proveedor,
                "SoftwareID" => $SoftwareID_proveedor,
                "SoftwareSC" => $SoftwareSC_proveedor
            ]);
            
            array_push($nominaIndividial->hijos, $ProveedorXML);
            
            

            $Empleador = new Etiqueta("Empleador", [
                "RazonSocial" => $empleado->nombreEmpresa,
                "NIT" => $empleado->nitEmpresa,
                "DV" => $empleado->digitoVerificacionEmpresa,
                "Pais" => "CO",
                "DepartamentoEstado" => substr($empleado->ubicacionEmpresa, 2, 2),
                "MunicipioCiudad" => substr($empleado->ubicacionEmpresa, 2, 5),
                "Direccion" => $empleado->direccionEmpresa                
            ]);

            array_push($nominaIndividial->hijos, $Empleador);

            $contratoActivo = DB::table('contrato')
            ->join("tipocontrato","idtipoContrato","=","contrato.fkTipoContrato")
            ->where("fkEmpleado","=",$empleado->idempleado)
            ->where("fkPeriodoActivo","=",$empleado->idPeriodo)
            ->whereNotNull("contrato.fkTipoContrato")
            ->whereIn("fkEstado",array("1","2","4"))->first();
            
            if(!isset($empleado->tipo_con_nomina_elec)){
                if(!isset($contratoActivo->tipo_con_nomina_elec)){
                    dd($empleado);
                }
                $empleado->tipo_con_nomina_elec = $contratoActivo->tipo_con_nomina_elec;
            }
            

            $Trabajador = new Etiqueta("Trabajador", [
                "TipoTrabajador" => intval($empleado->fkTipoCotizante),
                "SubTipoTrabajador" => intval($empleado->esPensionado),
                "AltoRiesgoPension" => "false",
                "TipoDocumento" => $empleado->cod_nomina_elec,
                "NumeroDocumento" => $empleado->numeroIdentificacion,
                "PrimerApellido" => $empleado->primerApellido,
                "SegundoApellido" => $empleado->segundoApellido,
                "PrimerNombre" => $empleado->primerNombre,
                "OtrosNombres" => $empleado->segundoNombre,
                "LugarTrabajoPais" => "CO",
                "LugarTrabajoDepartamentoEstado" => substr($empleado->ubicacionEmpresa, 2, 2),
                "LugarTrabajoMunicipioCiudad" => substr($empleado->ubicacionEmpresa, 2, 5),
                "LugarTrabajoDireccion" => $empleado->direccionEmpresa,
                "SalarioIntegral" => ($empleado->tipoRegimen == "Ley 50" ? "false" : "true"),
                "TipoContrato" => $empleado->tipo_con_nomina_elec
            ]);
            
            $arrFila["TipoTrabajador"] = intval($empleado->fkTipoCotizante);
            $arrFila["SubTipoTrabajador"] = intval($empleado->esPensionado);
            $arrFila["AltoRiesgoPension"] = "false";
            $arrFila["TipoDocumento"] = $empleado->cod_nomina_elec;
            $arrFila["NumeroDocumento"] = $empleado->numeroIdentificacion;
            $arrFila["PrimerApellido"] = $empleado->primerApellido;
            $arrFila["SegundoApellido"] = $empleado->segundoApellido;
            $arrFila["PrimerNombre"] = $empleado->primerNombre;
            $arrFila["OtrosNombres"] = $empleado->segundoNombre;
            $arrFila["LugarTrabajoPais"] = "CO";
            $arrFila["LugarTrabajoDepartamentoEstado"] = substr($empleado->ubicacionEmpresa, 2, 2);
            $arrFila["LugarTrabajoMunicipioCiudad"] = substr($empleado->ubicacionEmpresa, 2, 5);
            $arrFila["LugarTrabajoDireccion"] = $empleado->direccionEmpresa;
            $arrFila["SalarioIntegral"] = ($empleado->tipoRegimen == "Ley 50" ? "false" : "true");
            $arrFila["TipoContrato"] = $empleado->tipo_con_nomina_elec;

            if(!isset($empleado->salarioPeriodoPago)){
                $conceptoSalario = DB::table("conceptofijo")
                ->where("fkEmpleado","=",$empleado->idempleado)
                ->where("fkPeriodoActivo","=",$empleado->idPeriodo)
                ->whereIn("fkConcepto",[1,2,53,54,154])
                ->orderBy("idConceptoFijo","desc")
                ->first();
                $empleado->salarioPeriodoPago = $conceptoSalario->valor;
            } 

            $arrJSON_DATAICO["salary"] = round($empleado->salarioPeriodoPago);
            $arrJSON_DATAICO["software"] = [
                "pin" => $SoftwarePin,
                "test-set-id" => $SoftwareTestSetId,
                "dian-id" => $SoftwareDianId
            ];

            
            $arrJSON_DATAICO["issue-date"] = date("d/m/Y", strtotime($fechaReporte));

            if(isset($arrReemplazo["empleado"])){
                $arrJSON_DATAICO["replacement-for"]["number"] = $arrReemplazo["numero"];
                $arrJSON_DATAICO["replacement-for"]["prefix"] = $prefijo;
                $arrJSON_DATAICO["replacement-for"]["cune"] = $arrReemplazo["cune"];
                $arrJSON_DATAICO["replacement-for"]["issue-date"] =  date("d/m/Y", strtotime($fechaReporte));
            }

            if(isset($arrReemplazo["data"])){
                $reemplazo = $arrReemplazo["data"][$empleado->numeroIdentificacion];
                $arrJSON_DATAICO["replacement-for"]["number"] = $reemplazo["NUMERO"];
                $arrJSON_DATAICO["replacement-for"]["prefix"] = $prefijo;
                $arrJSON_DATAICO["replacement-for"]["cune"] = $reemplazo["CUNE"];
                $arrJSON_DATAICO["replacement-for"]["issue-date"] =  date("d/m/Y", strtotime($fechaReporte));
            }

            $arrJSON_DATAICO["employee"]["other-names"] = ($empleado->segundoNombre ?? "");
            $arrJSON_DATAICO["employee"]["second-last-name"] = ($empleado->segundoApellido ?? "");
            $arrJSON_DATAICO["employee"]["first-name"] = $empleado->primerNombre;
            $arrJSON_DATAICO["employee"]["integral-salary"] = ($empleado->tipoRegimen == "Ley 50" ? false : true);
            if(isset($fechaRetiroJSON)){
                $arrJSON_DATAICO["employee"]["fire-date"] = date("d/m/Y", strtotime($fechaRetiroJSON));
            }            
            $arrJSON_DATAICO["employee"]["email"] = ((!isset($empleado->correo) || empty($empleado->correo)) ? $empresa->email1 : $empleado->correo);
            
            
            
            $Trabajador->atributos["Sueldo"] = round($empleado->salarioPeriodoPago);
            $Trabajador->atributos["CodigoTrabajador"] = $empleado->idPeriodo;
            
            $arrFila["Sueldo"] = round($empleado->salarioPeriodoPago);
            $arrFila["CodigoTrabajador"] = $empleado->idPeriodo;

            array_push($nominaIndividial->hijos, $Trabajador);
            
            


            $Pago = new Etiqueta("Pago", [
                "Forma" => "1", //Contado (En version 1 no hay mas opciones)
                "Metodo" => $arrMetodo[$empleado->formaPago],
            ]);
            $arrFila["Forma"] = "1";
            $arrFila["Metodo"] = $arrMetodo[$empleado->formaPago];

            if($empleado->formaPago == "Transferencia"){
                $Pago->atributos["Banco"] = $empleado->nombreEntidad;
                $Pago->atributos["TipoCuenta"] = $empleado->tipoCuenta;
                $Pago->atributos["NumeroCuenta"] = $empleado->numeroCuenta;

                $arrFila["Banco"] = $empleado->nombreEntidad;
                $arrFila["TipoCuenta"] = $empleado->tipoCuenta;
                $arrFila["NumeroCuenta"] = $empleado->numeroCuenta;
                $arrJSON_DATAICO["employee"]["account-number"] = $empleado->numeroCuenta;
            }
            $arrJSON_DATAICO["employee"]["last-name"] = $empleado->primerApellido;
            $tipoCotizante = DB::table("tipo_cotizante")->where("idTipoCotizante","=",$empleado->fkTipoCotizante)->first();
            $arrJSON_DATAICO["employee"]["worker-type"] = $tipoCotizante->nomina_electronica;
            $arrJSON_DATAICO["employee"]["address"] = [
                "line" => $empleado->direccionEmpresa,
                "city" => substr($empleado->ubicacionEmpresa, 4, 3),
                "department" => substr($empleado->ubicacionEmpresa, 2, 2)
            ];

            
            if(!isset($empleado->tipo_con_nomina_elec_dataico)){
                $empleado->tipo_con_nomina_elec_dataico = $contratoActivo->tipo_con_nomina_elec_dataico;
            }
            $arrJSON_DATAICO["employee"]["identification"] = $empleado->numeroIdentificacion;
            $arrJSON_DATAICO["employee"]["payment-means"] = $arrMetodoJSON_DATAICO[$empleado->formaPago];
            $arrJSON_DATAICO["employee"]["high-risk"] = false;
            $arrJSON_DATAICO["employee"]["sub-code"] = ($empleado->esPensionado == 0 ? "NO_APLICA" : "DEPENDIENTE_PENSIONADO_POR_VEJEZ_ACTIVO");
            $arrJSON_DATAICO["employee"]["start-date"] = date("d/m/Y", strtotime($empleado->fechaInicioPeriodo));
            $arrJSON_DATAICO["employee"]["identification-type"] = $empleado->cod_nomina_elec_dataico;
            $arrJSON_DATAICO["employee"]["contract-type"] = $empleado->tipo_con_nomina_elec_dataico;
            $arrJSON_DATAICO["employee"]["code"] = $empleado->idPeriodo."";
            
            if($empleado->formaPago == "Transferencia"){
                $arrJSON_DATAICO["employee"]["bank"] = $empleado->nombreEntidad;
                $arrJSON_DATAICO["employee"]["account-type-kw"] = $empleado->tipoCuenta;
            }

            if(isset($arrReemplazo["empleado"]) || isset($arrReemplazo["data"])){
                $arrJSON_DATAICO["number"] = $consecutivoReemplazo;
            }
            else{
                $arrJSON_DATAICO["number"] = $consecutivo;
            }

            
            $arrJSON_DATAICO["env"] = ($TipAmb  == "2" ? "PRUEBAS" : "PRODUCCION");
            
            array_push($nominaIndividial->hijos, $Pago);
            
            $FechasPagos = new Etiqueta("FechasPagos",[]);
            $arrFila["FechasPagos"] = $empleado->fechasLiquidacion;
            
            foreach($fechasLq as $fechaLq){
                array_push($FechasPagos->hijos, new Etiqueta("FechaPago",[], [], $fechaLq));
                
            }
            array_push($nominaIndividial->hijos, $FechasPagos);


            $SueldoTrabajadoSuma = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","1")//
            ->first();
            
            $Devengados = new Etiqueta("Devengados");
            $arrJSON_DATAICO["accruals"] = array();
            
            array_push($arrJSON_DATAICO["accruals"], [
                "code" => "BASICO",
                "amount" => round($SueldoTrabajadoSuma->suma_valor ?? 0 ),
                "days" => round($SueldoTrabajadoSuma->suma_cantidad ?? 0)
            ]);
            $dias_mes = round($SueldoTrabajadoSuma->suma_cantidad ?? 0);
            

            $Basico =  new Etiqueta("Basico",[
                "DiasTrabajados" => round($SueldoTrabajadoSuma->suma_cantidad ?? 0),
                "SueldoTrabajado" => round($SueldoTrabajadoSuma->suma_valor ?? 0) //Lo que se pago en la quincena
            ]);

            $arrFila[1] = $SueldoTrabajadoSuma->suma_valor;
            $ValDev+= $SueldoTrabajadoSuma->suma_valor ?? 0;
            array_push($Devengados->hijos, $Basico);
           

            
            
            

            $Transporte =  new Etiqueta("Transporte");

            $AuxilioTransporteSuma = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","2")//
            ->first();

            $ViaticoManuAlojSSuma = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","3")//
            ->first();
            
            $ViaticoManuAlojNSSuma = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","4")//
            ->first();

            if(isset($AuxilioTransporteSuma->suma_valor) && $AuxilioTransporteSuma->suma_valor != 0){
                $Transporte->atributos["AuxilioTransporte"] = $AuxilioTransporteSuma->suma_valor;
                $ValDev+= $AuxilioTransporteSuma->suma_valor ?? 0;
                $arrFila[2] = $AuxilioTransporteSuma->suma_valor;

                array_push($arrJSON_DATAICO["accruals"], [
                    "code" => "AUXILIO_DE_TRANSPORTE",
                    "amount" => round($AuxilioTransporteSuma->suma_valor)
                ]);

            }
            
            if(isset($ViaticoManuAlojSSuma->suma_valor) && $ViaticoManuAlojSSuma->suma_valor != 0){
                $Transporte->atributos["ViaticoManuAlojS"] = $ViaticoManuAlojSSuma->suma_valor;
                $ValDev+= $ViaticoManuAlojSSuma->suma_valor ?? 0;
                $arrFila[3] = $ViaticoManuAlojSSuma->suma_valor;
            }
            if(isset($ViaticoManuAlojNSSuma->suma_valor) && $ViaticoManuAlojNSSuma->suma_valor != 0){
                $Transporte->atributos["ViaticoManuAlojNS"] = $ViaticoManuAlojNSSuma->suma_valor;
                $ValDev+= $ViaticoManuAlojNSSuma->suma_valor ?? 0;
                $arrFila[4] = $ViaticoManuAlojNSSuma->suma_valor;
            }

            if((isset($ViaticoManuAlojSSuma->suma_valor) && $ViaticoManuAlojSSuma->suma_valor != 0) || (isset($ViaticoManuAlojNSSuma->suma_valor) && $ViaticoManuAlojNSSuma->suma_valor != 0)){
                $viatico_dataico = array();
                $viatico_dataico["code"] = "VIATICO";
                if(isset($ViaticoManuAlojSSuma->suma_valor) && $ViaticoManuAlojSSuma->suma_valor != 0){
                    $viatico_dataico["amount"] = round($ViaticoManuAlojSSuma->suma_valor);
                }
                if(isset($ViaticoManuAlojNSSuma->suma_valor) && $ViaticoManuAlojNSSuma->suma_valor != 0){
                    $viatico_dataico["amount-ns"] = round($ViaticoManuAlojNSSuma->suma_valor);
                }
                array_push($arrJSON_DATAICO["accruals"], $viatico_dataico);
            }

            if(sizeof($Transporte->atributos) > 0){
                array_push($Devengados->hijos, $Transporte);
            }
            

            
            
            $HEDsConsulta = DB::table("item_boucher_pago","ibp")
            ->select("ibp.valor", "ibp.cantidad")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","5")//
            ->get();

            if(sizeof($HEDsConsulta) > 0 ){
                $HEDs = new Etiqueta("HEDs");
              
                foreach($HEDsConsulta as $HEDConsulta){
                    $HED = new Etiqueta("HED",[
                        "Cantidad" => $HEDConsulta->cantidad,
                        "Porcentaje" => "25.00",
                        "Pago" => $HEDConsulta->valor
                    ]);
                    $arrFila[5] = (isset($arrFila[5]) ? ($arrFila[5] + $HEDConsulta->valor) : $HEDConsulta->valor);
                    array_push($HEDs->hijos, $HED);
                    $ValDev+= $HEDConsulta->valor ?? 0;

                    $HORA_EXTRA_DIURNA_DATAICO["code"] = "HORA_EXTRA_DIURNA";
                    $HORA_EXTRA_DIURNA_DATAICO["amount"] = $HEDConsulta->valor;
                    $HORA_EXTRA_DIURNA_DATAICO["hours"] = round($HEDConsulta->cantidad,2)."";
                    array_push($arrJSON_DATAICO["accruals"], $HORA_EXTRA_DIURNA_DATAICO);
                }
                array_push($Devengados->hijos, $HEDs);
            }
            

            $HENsConsulta = DB::table("item_boucher_pago","ibp")
            ->select("ibp.valor", "ibp.cantidad")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","6")//
            ->get();

            if(sizeof($HENsConsulta) > 0 ){
                $HENs = new Etiqueta("HENs");
              
                foreach($HENsConsulta as $HENConsulta){
                    $HEN = new Etiqueta("HEN",[
                        "Cantidad" => $HENConsulta->cantidad,
                        "Porcentaje" => "75.00",
                        "Pago" => $HENConsulta->valor
                    ]);
                    $arrFila[6] = (isset($arrFila[6]) ? ($arrFila[6] + $HENConsulta->valor) : $HENConsulta->valor);
                    array_push($HENs->hijos, $HEN);
                    $ValDev+= $HENConsulta->valor ?? 0;

                    $HORA_EXTRA_NOCTURNA_DATAICO["code"] = "HORA_EXTRA_NOCTURNA";
                    $HORA_EXTRA_NOCTURNA_DATAICO["amount"] = $HENConsulta->valor;
                    $HORA_EXTRA_NOCTURNA_DATAICO["hours"] = round($HENConsulta->cantidad,2)."";
                    array_push($arrJSON_DATAICO["accruals"], $HORA_EXTRA_NOCTURNA_DATAICO);
                }
                array_push($Devengados->hijos, $HENs);
            }


            $HRNsConsulta = DB::table("item_boucher_pago","ibp")
            ->select("ibp.valor", "ibp.cantidad")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","7")//
            ->get();

            if(sizeof($HRNsConsulta) > 0 ){
                $HRNs = new Etiqueta("HRNs");
              
                foreach($HRNsConsulta as $HRNConsulta){

                    $HRN = new Etiqueta("HRN",[
                        "Cantidad" => $HRNConsulta->cantidad,
                        "Porcentaje" => "35.00",
                        "Pago" => $HRNConsulta->valor
                    ]);
                    $arrFila[7] = (isset($arrFila[7]) ? ($arrFila[7] + $HRNConsulta->valor) : $HRNConsulta->valor);
                    array_push($HRNs->hijos, $HRN);
                    $ValDev+= $HRNConsulta->valor ?? 0;

                    $HORA_RECARGO_NOCTURNO_DATAICO["code"] = "HORA_RECARGO_NOCTURNO";
                    $HORA_RECARGO_NOCTURNO_DATAICO["amount"] = $HRNConsulta->valor;
                    $HORA_RECARGO_NOCTURNO_DATAICO["hours"] = round($HRNConsulta->cantidad,2)."";
                    array_push($arrJSON_DATAICO["accruals"], $HORA_RECARGO_NOCTURNO_DATAICO);
                }
                array_push($Devengados->hijos, $HRNs);
            }

            $HEDDFsConsulta = DB::table("item_boucher_pago","ibp")
            ->select("ibp.valor", "ibp.cantidad")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","8")//
            ->get();

            if(sizeof($HEDDFsConsulta) > 0 ){
                $HEDDFs = new Etiqueta("HEDDFs");
              
                foreach($HEDDFsConsulta as $HEDDFConsulta){

                    $HEDDF = new Etiqueta("HEDDF",[
                        "Cantidad" => $HEDDFConsulta->cantidad,
                        "Porcentaje" => "100.00",
                        "Pago" => $HEDDFConsulta->valor
                    ]);
                    $arrFila[8] = (isset($arrFila[8]) ? ($arrFila[8] + $HEDDFConsulta->valor) : $HEDDFConsulta->valor);
                    array_push($HEDDFs->hijos, $HEDDF);
                    $ValDev+= $HEDDFConsulta->valor ?? 0;

                    $HORA_EXTRA_DIURNA_DF_DATAICO["code"] = "HORA_EXTRA_DIURNA_DF";
                    $HORA_EXTRA_DIURNA_DF_DATAICO["amount"] = $HEDDFConsulta->valor;
                    $HORA_EXTRA_DIURNA_DF_DATAICO["hours"] = round($HEDDFConsulta->cantidad,2)."";
                    array_push($arrJSON_DATAICO["accruals"], $HORA_EXTRA_DIURNA_DF_DATAICO);
                }
                array_push($Devengados->hijos, $HEDDFs);
            }


            $HRDDFsConsulta = DB::table("item_boucher_pago","ibp")
            ->select("ibp.valor", "ibp.cantidad")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","9")//
            ->get();

            if(sizeof($HRDDFsConsulta) > 0 ){
                $HRDDFs = new Etiqueta("HRDDFs");
              
                foreach($HRDDFsConsulta as $HRDDFConsulta){

                    $HRDDF = new Etiqueta("HRDDF",[
                        "Cantidad" => $HRDDFConsulta->cantidad,
                        "Porcentaje" => "75.00",
                        "Pago" => $HRDDFConsulta->valor
                    ]);
                    $arrFila[9] = (isset($arrFila[9]) ? ($arrFila[9] + $HRDDFConsulta->valor) : $HRDDFConsulta->valor);
                    array_push($HRDDFs->hijos, $HRDDF);
                    $ValDev+= $HRDDFConsulta->valor ?? 0;

                    $HORA_RECARGO_DIURNA_DF_DATAICO["code"] = "HORA_RECARGO_DIURNA_DF";
                    $HORA_RECARGO_DIURNA_DF_DATAICO["amount"] = $HRDDFConsulta->valor;
                    $HORA_RECARGO_DIURNA_DF_DATAICO["hours"] = round($HRDDFConsulta->cantidad)."";
                    array_push($arrJSON_DATAICO["accruals"], $HORA_RECARGO_DIURNA_DF_DATAICO);
                }
                array_push($Devengados->hijos, $HRDDFs);
            }

            
            $HENDFsConsulta = DB::table("item_boucher_pago","ibp")
            ->select("ibp.valor", "ibp.cantidad")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","10")//
            ->get();

            if(sizeof($HENDFsConsulta) > 0 ){
                $HENDFs = new Etiqueta("HENDFs");
              
                foreach($HENDFsConsulta as $HENDFConsulta){

                    $HENDF = new Etiqueta("HENDF",[
                        "Cantidad" => $HENDFConsulta->cantidad,
                        "Porcentaje" => "150.00",
                        "Pago" => $HENDFConsulta->valor
                    ]);
                    $arrFila[10] = (isset($arrFila[10]) ? ($arrFila[10] + $HENDFConsulta->valor) : $HENDFConsulta->valor);
                    array_push($HENDFs->hijos, $HENDF);
                    $ValDev+= $HENDFConsulta->valor ?? 0;

                    $HORA_EXTRA_NOCTURNA_DF_DATAICO["code"] = "HORA_EXTRA_NOCTURNA_DF";
                    $HORA_EXTRA_NOCTURNA_DF_DATAICO["amount"] = $HENDFConsulta->valor;
                    $HORA_EXTRA_NOCTURNA_DF_DATAICO["hours"] = round($HENDFConsulta->cantidad,2)."";
                    array_push($arrJSON_DATAICO["accruals"], $HORA_EXTRA_NOCTURNA_DF_DATAICO);
                }
                array_push($Devengados->hijos, $HENDFs);
            }


            $HRNDFsConsulta = DB::table("item_boucher_pago","ibp")
            ->select("ibp.valor", "ibp.cantidad")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","11")//
            ->get();

            if(sizeof($HRNDFsConsulta) > 0 ){
                $HRNDFs = new Etiqueta("HRNDFs");
              
                foreach($HRNDFsConsulta as $HRNDFConsulta){

                    $HRNDF = new Etiqueta("HRNDF",[
                        "Cantidad" => $HRNDFConsulta->cantidad,
                        "Porcentaje" => "110.00",
                        "Pago" => $HRNDFConsulta->valor
                    ]);
                    $arrFila[11] = (isset($arrFila[11]) ? ($arrFila[11] + $HRNDFConsulta->valor) : $HRNDFConsulta->valor);
                    array_push($HRNDFs->hijos, $HRNDF);
                    $ValDev+= $HRNDFConsulta->valor ?? 0;

                    $HORA_RECARGO_NOCTURNO_DF_DATAICO = array();
                    $HORA_RECARGO_NOCTURNO_DF_DATAICO["code"] = "HORA_RECARGO_NOCTURNO_DF";
                    $HORA_RECARGO_NOCTURNO_DF_DATAICO["amount"] = $HRNDFConsulta->valor;
                    $HORA_RECARGO_NOCTURNO_DF_DATAICO["hours"] = round($HRNDFConsulta->cantidad,2)."";
                    array_push($arrJSON_DATAICO["accruals"], $HORA_RECARGO_NOCTURNO_DF_DATAICO);
                }
                array_push($Devengados->hijos, $HRNDFs);
            }

            
            
            
            /*$PrimasConsultaValor = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","14")//
            ->first();            

            if(isset($PrimasConsultaValor->suma_valor)){
                $PrimasConsultaCantidad = DB::table("item_boucher_pago","ibp")
                ->select("ibp.valor", "ibp.cantidad")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
                ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
                ->where("bp.fkEmpleado","=", $empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
                ->where("ln.fkEstado","=","5")//Terminada
                ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
                ->where("gne.idGrupoNominaElectronica","=","14")//
                ->orderBy("ibp.idItemBoucherPago","desc")
                ->first();
                
                if($PrimasConsultaCantidad->cantidad > 180) 
                {
                    $PrimasConsultaCantidad->cantidad = 180;
                }
               
                $Primas = new Etiqueta("Primas",[
                    "Cantidad" => round($PrimasConsultaCantidad->cantidad)  ,
                    "Pago" => $PrimasConsultaValor->suma_valor
                ]);
                $arrFila[14] = (isset($arrFila[14]) ? ($arrFila[14] + $PrimasConsultaValor->suma_valor) : $PrimasConsultaValor->suma_valor);
                array_push($Devengados->hijos, $Primas);
                $ValDev+= $PrimasConsultaValor->suma_valor ?? 0;
                $PRIMA_DATAICO = array();
                $PRIMA_DATAICO["code"] = "PRIMA";
                $PRIMA_DATAICO["amount"] = $PrimasConsultaValor->suma_valor;
                $PRIMA_DATAICO["days"] = round($PrimasConsultaCantidad->cantidad);
                array_push($arrJSON_DATAICO["accruals"], $PRIMA_DATAICO);

            }*/

            //Provision PRIMA

            $fechaFMes = $fechaFinMesActual;
            if(isset($fechaRetiroJSON)){
                $fechaFMes = $fechaRetiroJSON;
            }
            $cantidadProv = $this->days_360($fechaI, $fechaFMes);
            $cantidadProv++;
            
            $PrimasConsultaValor = DB::table("provision","p")
            ->select(DB::raw("p.valor as suma_valor,p.idProvision"))
            ->join("concepto as c", "c.idconcepto","=","p.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("p.fkEmpleado","=", $empleado->idempleado)
            ->where("p.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("p.anio","=",intval(date("Y",strtotime($fechaFinMesActual))))
            ->where("p.mes","=",intval(date("m",strtotime($fechaFinMesActual))))            
            ->where("gne.idGrupoNominaElectronica","=","14")//
            ->first();   

            if(isset($PrimasConsultaValor->suma_valor) && $PrimasConsultaValor->suma_valor > 0){                
                $Primas = new Etiqueta("Primas",[
                    "Cantidad" => round($cantidadProv)  ,
                    "Pago" => $PrimasConsultaValor->suma_valor
                ]);

                $arrFila[14] = (isset($arrFila[14]) ? ($arrFila[14] + $PrimasConsultaValor->suma_valor) : $PrimasConsultaValor->suma_valor);
                array_push($Devengados->hijos, $Primas);
                $ValDev+= $PrimasConsultaValor->suma_valor ?? 0;
                $PRIMA_DATAICO = array();
                $PRIMA_DATAICO["code"] = "PRIMA";
                $PRIMA_DATAICO["amount-ns"] = $PrimasConsultaValor->suma_valor;
                $PRIMA_DATAICO["days"] = round($cantidadProv);
                array_push($arrJSON_DATAICO["accruals"], $PRIMA_DATAICO);
            }



            /*$CesantiasConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","15")//
            ->first();


            if(substr($fechaInicioMesActual,5,2) == "12"){
                $CesantiasTrasladoConsulta = DB::table("item_boucher_pago_fuera_nomina","ibp")
                ->select(DB::raw("sum(ibp.valor) as suma_valor"))
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
                ->where("bp.fkEmpleado","=", $empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
                ->where("ln.fkEstado","=","5")
                ->whereRaw("(ln.fechaFin <= '".date("Y-m-d", strtotime($fechaFinMesActual." +2 month"))."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                ->whereIn("c.idconcepto",["67","84"])
                ->first();

                
                if($empleado->estado == "2" && !isset($CesantiasTrasladoConsulta->suma_valor)){ // Retirado
                    $CesantiasTrasladoConsulta = DB::table("item_boucher_pago","ibp")
                    ->select(DB::raw("sum(ibp.valor) as suma_valor"))
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
                    ->where("bp.fkEmpleado","=", $empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
                    ->where("ln.fkEstado","=","5")
                    ->whereRaw("(ln.fechaFin <= '".date("Y-m-d", strtotime($fechaFinMesActual." +2 month"))."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                    ->whereIn("c.idconcepto",["67","84"])
                    ->first();
                    //dd($empleado, $CesantiasTrasladoConsulta->suma_valor);
                }
                
                
                if(!isset($CesantiasTrasladoConsulta->suma_valor) && $cesantias_sin_pagar){

                    $CesantiasTrasladoConsulta = DB::table("saldo","s")
                    ->select("s.valor as suma_valor")
                    ->where("s.fkEmpleado","=", $empleado->idempleado)
                    ->where("s.fkPeriodoActivo","=",$empleado->idPeriodo)
                    ->where("s.fkEstado","=","7")
                    ->where("s.mesAnterior","=",1)
                    ->where("s.anioAnterior","=",date("Y"))
                    ->whereIn("s.fkConcepto",["67"])
                    ->first();
                }

            }
            
            
            if(!isset($CesantiasConsulta->suma_valor)){
                $CesantiasConsulta->suma_valor = 0;
            }
            if(isset($CesantiasTrasladoConsulta) && $CesantiasTrasladoConsulta->suma_valor != 0){
                $CesantiasConsulta->suma_valor += $CesantiasTrasladoConsulta->suma_valor;
            }

            $PagoInteresesConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","16")//
            ->first();

            $InteresesConsultaCantidad = DB::table("item_boucher_pago","ibp")
            ->select("ibp.valor", "ibp.cantidad")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","16")//
            ->orderBy("ibp.idItemBoucherPago","desc")
            ->first();
            
            if(substr($fechaInicioMesActual,5,2) == "12" && !isset($PagoInteresesConsulta->suma_valor)){
                $PagoInteresesConsulta = DB::table("item_boucher_pago","ibp")
                ->select(DB::raw("sum(ibp.valor) as suma_valor"))
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=", $empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
                ->where("ln.fkEstado","=","5")//Terminada
                ->whereRaw("(ln.fechaFin <= '".date("Y-m-d", strtotime($fechaFinMesActual." +2 month"))."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
                ->where("ibp.fkConcepto","=","68")//68	INTERESES AÑO ANTERIOR
                ->first();
                

                if(!isset($PagoInteresesConsulta->suma_valor) && $cesantias_sin_pagar){

                    $PagoInteresesConsulta = DB::table("saldo","s")
                    ->select("s.valor as suma_valor")
                    ->where("s.fkEmpleado","=", $empleado->idempleado)
                    ->where("s.fkPeriodoActivo","=",$empleado->idPeriodo)
                    ->where("s.fkEstado","=","7")
                    ->where("s.mesAnterior","=",1)
                    ->where("s.anioAnterior","=",date("Y"))
                    ->whereIn("s.fkConcepto",["68"])
                    ->first();
                }

                $InteresesConsultaCantidad = new stdClass();
                $InteresesConsultaCantidad->cantidad = 0;
            }



            if(isset($CesantiasConsulta->suma_valor) && $CesantiasConsulta->suma_valor != 0){

                
                if($InteresesConsultaCantidad->cantidad == 0){
                    $diasSiEs0 = $this->days_360($empleado->fechaInicioPeriodo, date("Y-12-31",strtotime("-1 year"))) + 1;
                    if($diasSiEs0 > 360){
                        $diasSiEs0 = 360;
                    }
                    $InteresesConsultaCantidad->cantidad = $diasSiEs0;
                }

                $interesesPorcen = ($InteresesConsultaCantidad->cantidad ?? 0) * 0.12 / 360;                
                $interesesPorcen = round($interesesPorcen * 10000);                
                $interesesPorcen = $interesesPorcen / 100;
                
                $Cesantias = new Etiqueta("Cesantias",[
                    "Pago" => round($CesantiasConsulta->suma_valor),
                    "Porcentaje" => $interesesPorcen,
                    "PagoIntereses" => round($PagoInteresesConsulta->suma_valor)
                ]);
                $arrFila[15] = $CesantiasConsulta->suma_valor;
                $arrFila[16] = $PagoInteresesConsulta->suma_valor;
                array_push($Devengados->hijos, $Cesantias);
                $ValDev+= $PagoInteresesConsulta->suma_valor ?? 0;
                $ValDev+= $CesantiasConsulta->suma_valor ?? 0;
                $interesesPorcen = $interesesPorcen."";
                $posPunto = strpos($interesesPorcen,".");
                
                if($posPunto === false){
                    $interesesPorcen = $interesesPorcen.".00";
                }
                else{
                    $interesesPorcen = substr($interesesPorcen,0, $posPunto).".".substr($interesesPorcen, $posPunto + 1, 2);
                }
                
                //dd($interesesPorcen,, strpos($interesesPorcen,"."));
                
                $CESANTIAS_DATAICO = array();

                $CESANTIAS_DATAICO["code"] = "CESANTIAS";
                $CESANTIAS_DATAICO["amount"] = round($CesantiasConsulta->suma_valor);
                $CESANTIAS_DATAICO["cesantias-interest"] = round($PagoInteresesConsulta->suma_valor);               
                $CESANTIAS_DATAICO["percentage"] = $interesesPorcen;

                array_push($arrJSON_DATAICO["accruals"], $CESANTIAS_DATAICO);
            }   */
            
            $CesantiasConsultaValor = DB::table("provision","p")
            ->select(DB::raw("p.valor as suma_valor,p.idProvision"))
            ->join("concepto as c", "c.idconcepto","=","p.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("p.fkEmpleado","=", $empleado->idempleado)
            ->where("p.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("p.anio","=",intval(date("Y",strtotime($fechaFinMesActual))))
            ->where("p.mes","=",intval(date("m",strtotime($fechaFinMesActual))))            
            ->where("gne.idGrupoNominaElectronica","=","15")
            ->first();   

            $IntCesantiasConsultaValor = DB::table("provision","p")
            ->select(DB::raw("p.valor as suma_valor,p.idProvision"))
            ->join("concepto as c", "c.idconcepto","=","p.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("p.fkEmpleado","=", $empleado->idempleado)
            ->where("p.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("p.anio","=",intval(date("Y",strtotime($fechaFinMesActual))))
            ->where("p.mes","=",intval(date("m",strtotime($fechaFinMesActual))))            
            ->where("gne.idGrupoNominaElectronica","=","16")
            ->first();   

            if(
                isset($CesantiasConsultaValor->suma_valor) && isset($IntCesantiasConsultaValor->suma_valor) &&
                $CesantiasConsultaValor->suma_valor > 0
            ){ 


                if(strtotime($empleado->fechaInicioPeriodo) > strtotime(date("Y-01-01",strtotime($fechaFinMesActual)))){
                    $dias = $this->days_360($empleado->fechaInicioPeriodo, $fechaFMes) + 1;
                }
                else{
                    $dias = $this->days_360(date("Y-01-01",strtotime($fechaFinMesActual)), $fechaFMes) + 1;
                }

                
                if($dias > 360){
                    $dias = 360;
                }
                $cantidadInt = $dias;

                $interesesPorcen = $cantidadInt * 0.12 / 360;                
                $interesesPorcen = round($interesesPorcen * 10000);                
                $interesesPorcen = $interesesPorcen / 100;
                
                $Cesantias = new Etiqueta("Cesantias",[
                    "Pago" => round($CesantiasConsultaValor->suma_valor),
                    "Porcentaje" => $interesesPorcen,
                    "PagoIntereses" => round($IntCesantiasConsultaValor->suma_valor)
                ]);
                $arrFila[15] = $CesantiasConsultaValor->suma_valor;
                $arrFila[16] = $IntCesantiasConsultaValor->suma_valor;
                array_push($Devengados->hijos, $Cesantias);
                $ValDev+= $IntCesantiasConsultaValor->suma_valor ?? 0;
                $ValDev+= $CesantiasConsultaValor->suma_valor ?? 0;
                $interesesPorcen = $interesesPorcen."";
                $posPunto = strpos($interesesPorcen,".");
                
                if($posPunto === false){
                    $interesesPorcen = $interesesPorcen.".00";
                }
                else{
                    $interesesPorcen = substr($interesesPorcen,0, $posPunto).".".substr($interesesPorcen, $posPunto + 1, 2);
                }
                
                //dd($interesesPorcen,, strpos($interesesPorcen,"."));
                
                $CESANTIAS_DATAICO = array();

                $CESANTIAS_DATAICO["code"] = "CESANTIAS";
                $CESANTIAS_DATAICO["amount-ns"] = round($CesantiasConsultaValor->suma_valor);
                $CESANTIAS_DATAICO["cesantias-interest"] = round($IntCesantiasConsultaValor->suma_valor);               
                $CESANTIAS_DATAICO["percentage"] = $interesesPorcen;
                array_push($arrJSON_DATAICO["accruals"], $CESANTIAS_DATAICO);
            }

            


            $IncapacidadesConsulta = DB::table("item_boucher_pago","ibp")
            ->select("ibp.idItemBoucherPago", "ibp.valor", DB::raw("sum(i.numDias) as cantidad"),"i.fkTipoAfilicacion")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->join("item_boucher_pago_novedad as ibpn","ibpn.fkItemBoucher","=","ibp.idItemBoucherPago")
            ->join("novedad as n","n.idNovedad","=","ibpn.fkNovedad")
            ->join("incapacidad as i","i.idIncapacidad","=","n.fkIncapacidad")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","17")//
            ->whereNotIn("i.tipoIncapacidad",["Maternidad", "Paternidad"])
            ->groupBy('ibp.idItemBoucherPago')
            ->get();
            
            if(sizeof($IncapacidadesConsulta) > 0){
                $Incapacidades = new Etiqueta("Incapacidades");

                foreach($IncapacidadesConsulta as $IncapacidadConsulta){

                    $tipoIncapacidad = 0;
                    
                    if($IncapacidadConsulta->fkTipoAfilicacion == "3"){
                        $tipoIncapacidad = 1;
                        $tipoIncapacidadDATAICO = "LABORAL";
                    }
                    else{
                        $tipoIncapacidadDATAICO = "COMUN";
                        $tipoIncapacidad = 3;
                    }


                    $Incapacidad = new Etiqueta("Incapacidad",[
                        "Cantidad" => round($IncapacidadConsulta->cantidad),
                        "Tipo" => $tipoIncapacidad,
                        "Pago" => $IncapacidadConsulta->valor
                    ]);
                    $arrFila[17] = (isset($arrFila[17]) ? ($arrFila[17] + $IncapacidadConsulta->valor) : $IncapacidadConsulta->valor);
                    array_push($Incapacidades->hijos, $Incapacidad);

                    $ValDev+= $IncapacidadConsulta->valor ?? 0;
                    $INCAPACIDAD_DATAICO = array();
                    $INCAPACIDAD_DATAICO["code"] = "INCAPACIDAD";
                    $INCAPACIDAD_DATAICO["amount"] = round($IncapacidadConsulta->valor);
                    $INCAPACIDAD_DATAICO["days"] = round($IncapacidadConsulta->cantidad);
                    $dias_mes += $IncapacidadConsulta->cantidad;
                    $INCAPACIDAD_DATAICO["medical-leave-type"] = $tipoIncapacidadDATAICO;
                    array_push($arrJSON_DATAICO["accruals"], $INCAPACIDAD_DATAICO);
                    
                }
                array_push($Devengados->hijos, $Incapacidades);
            }

           



            $LicenciaMPConsulta = DB::table("item_boucher_pago","ibp")
            ->select("ibp.valor", "ibp.cantidad")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","18")//
            ->get();
            $Licencias = new Etiqueta("Licencias");
            if(sizeof($LicenciaMPConsulta) > 0){
                foreach($LicenciaMPConsulta as $LicMPConsulta){
                    $LICENCIA_PATERNIDAD_DATAICO = array();
                    $LicenciaMP = new Etiqueta("LicenciaMP",[
                        "Cantidad" => round($LicMPConsulta->cantidad),
                        "Pago" => $LicMPConsulta->valor
                    ]);
                    $arrFila[18] = (isset($arrFila[18]) ? ($arrFila[18] + $LicMPConsulta->valor) : $LicMPConsulta->valor);
                    array_push($Licencias->hijos, $LicenciaMP);
                    $ValDev+= $LicMPConsulta->valor ?? 0;   

                    $LICENCIA_PATERNIDAD_DATAICO["code"] = "LICENCIA_PATERNIDAD";
                    $LICENCIA_PATERNIDAD_DATAICO["amount"] = round($LicMPConsulta->valor);
                    $LICENCIA_PATERNIDAD_DATAICO["days"] = round($LicMPConsulta->cantidad);
                    $dias_mes += $LicMPConsulta->cantidad;
                    array_push($arrJSON_DATAICO["accruals"], $LICENCIA_PATERNIDAD_DATAICO);
                }
            }


            $LicenciaRConsulta = DB::table("item_boucher_pago","ibp")
            ->select("ibp.valor", "ibp.cantidad")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","19")//
            ->get();

            if(sizeof($LicenciaRConsulta) > 0){
                foreach($LicenciaRConsulta as $LicRConsulta){
                    $LICENCIA_REMUNERADA_DATAICO = array();
                    $LicenciaR = new Etiqueta("LicenciaR",[
                        "Cantidad" => round($LicRConsulta->cantidad),
                        "Pago" => $LicRConsulta->valor
                    ]);
                    $arrFila[19] = (isset($arrFila[19]) ? ($arrFila[19] + $LicRConsulta->valor) : $LicRConsulta->valor);
                    array_push($Licencias->hijos, $LicenciaR);    
                    $ValDev+= $LicRConsulta->valor ?? 0;

                    $LICENCIA_REMUNERADA_DATAICO["code"] = "LICENCIA_REMUNERADA";
                    $LICENCIA_REMUNERADA_DATAICO["amount"] = round($LicRConsulta->valor);
                    $LICENCIA_REMUNERADA_DATAICO["days"] = round($LicRConsulta->cantidad);
                    $dias_mes += $LicRConsulta->cantidad;
                    array_push($arrJSON_DATAICO["accruals"], $LICENCIA_REMUNERADA_DATAICO);
                }
            }


            $LicenciaNRConsulta = DB::table("item_boucher_pago","ibp")
            ->select("ibp.cantidad")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","20")//
            ->get();

            if(sizeof($LicenciaNRConsulta) > 0){
                foreach($LicenciaNRConsulta as $LicNRConsulta){
                    $LICENCIA_NO_REMUNERADA_DATAICO = array();
                    $LicenciaNR = new Etiqueta("LicenciaNR",[
                        "Cantidad" => round($LicNRConsulta->cantidad)
                    ]);
                    $arrFila[20] = (isset($arrFila[20]) ? ($arrFila[20] + $LicNRConsulta->cantidad) : $LicNRConsulta->cantidad);
                    array_push($Licencias->hijos, $LicenciaNR);         

                    $LICENCIA_NO_REMUNERADA_DATAICO["code"] = "LICENCIA_NO_REMUNERADA";
                    $LICENCIA_NO_REMUNERADA_DATAICO["days"] = round($LicNRConsulta->cantidad);
                    $dias_mes += $LicNRConsulta->cantidad;
                    array_push($arrJSON_DATAICO["accruals"], $LICENCIA_NO_REMUNERADA_DATAICO);
                }
            }


            
            if(sizeof($Licencias->hijos) > 0){
                array_push($Devengados->hijos, $Licencias);
            }
            


            $Bonificaciones = new Etiqueta("Bonificaciones");

            $BonificacionSConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","21")//
            ->first();

            $BonificacionNSConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","22")//
            ->first();

            

            if(isset($BonificacionSConsulta->suma_valor)){
                $Bonificaciones->atributos["BonificacionS"] = $BonificacionSConsulta->suma_valor ?? 0;
                $ValDev+= $BonificacionSConsulta->suma_valor ?? 0;
                $arrFila[21] = $BonificacionSConsulta->suma_valor;
            }
            if(isset($BonificacionNSConsulta->suma_valor)){
                $Bonificaciones->atributos["BonificacionNS"] = $BonificacionNSConsulta->suma_valor ?? 0;
                $ValDev+= $BonificacionNSConsulta->suma_valor ?? 0;
                $arrFila[22] = $BonificacionNSConsulta->suma_valor;
            }

            if((isset($BonificacionSConsulta->suma_valor)) || isset($BonificacionNSConsulta->suma_valor)){
                $bonificacion_dataico = array();
                $bonificacion_dataico["code"] = "BONIFICACION";
                if(isset($BonificacionSConsulta->suma_valor)){
                    $bonificacion_dataico["amount"] = round($BonificacionSConsulta->suma_valor);
                }
                
                if(isset($BonificacionNSConsulta->suma_valor)){
                    $bonificacion_dataico["amount-ns"] = round($BonificacionNSConsulta->suma_valor);
                }

                array_push($arrJSON_DATAICO["accruals"], $bonificacion_dataico);
                //dd($BonificacionSConsulta, $BonificacionNSConsulta, $bonificacion_dataico);
            }

            if(sizeof($Bonificaciones->atributos) > 0){
                array_push($Devengados->hijos, $Bonificaciones);
            }
            
            /*
            
            $VacacionesComunesConsulta = DB::table("item_boucher_pago","ibp")
            ->selectRaw("sum(ibp.valor) as valor, sum(ibp.cantidad) as cantidad")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","12")//
            ->first();

            if(isset($VacacionesComunesConsulta->cantidad)){
                $VacacionComunesConsulta = $VacacionesComunesConsulta;
                if(($dias_mes + $VacacionComunesConsulta->cantidad) > 30){
                    $VacacionComunesConsulta->cantidad = 30 - $dias_mes;
                }
                $valorNovedad = $VacacionComunesConsulta->valor;
                $diasCompensar = $VacacionComunesConsulta->cantidad;
                $VacacionesComunes = new Etiqueta("VacacionesComunes",[
                    "Cantidad" => round($diasCompensar),
                    "Pago" => $valorNovedad
                ]);

                $arrFila[12] = (isset($arrFila[12]) ? ($arrFila[12] + $valorNovedad) : $valorNovedad);
                array_push($Vacaciones->hijos, $VacacionesComunes);
                $ValDev+= $valorNovedad ?? 0;                   
                $VACACION_DATAICO = array();
                $VACACION_DATAICO["code"] = "VACACION";
                $VACACION_DATAICO["amount"] = $valorNovedad;
                $VACACION_DATAICO["days"] = round($diasCompensar);
                array_push($arrJSON_DATAICO["accruals"], $VACACION_DATAICO);
            }
            */






            $Vacaciones = new Etiqueta("Vacaciones");
            $sqlWhere = "( 
                ('".$fechaInicioMesActual."' BETWEEN v.fechaInicio AND v.fechaFin) OR
                ('".$fechaFinMesActual."' BETWEEN v.fechaInicio AND v.fechaFin) OR
                (v.fechaInicio BETWEEN '".$fechaInicioMesActual."' AND '".$fechaFinMesActual."') OR
                (v.fechaFin BETWEEN '".$fechaInicioMesActual."' AND '".$fechaFinMesActual."')
            )";

            $VacacionesComunesConsulta = DB::table("novedad","n")
            ->join("vacaciones as v","v.idVacaciones","=", "n.fkVacaciones")
            ->join("concepto as c", "c.idconcepto","=","n.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("n.fkEmpleado","=", $empleado->idempleado)
            ->where("n.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->whereIn("n.fkEstado",["8","16"]) // Pagada-> no que este eliminada o parcialmente paga (para las de pago parcial)
            ->whereNotNull("n.fkVacaciones")
            //->where("n.fkConcepto","=", "29")
            ->where("gne.idGrupoNominaElectronica","=","12")
            ->whereRaw($sqlWhere)
            ->get();


            // $VacacionesComunesConsulta = DB::table("item_boucher_pago","ibp")
            // ->select("ibp.valor", "ibp.cantidad")
            // ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            // ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            // ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            // ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            // ->where("bp.fkEmpleado","=", $empleado->idempleado)
            // ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            // ->where("ln.fkEstado","=","5")//Terminada
            // ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            // ->where("gne.idGrupoNominaElectronica","=","12")//
            // ->get();

            

            if(sizeof($VacacionesComunesConsulta) > 0){
                
                foreach($VacacionesComunesConsulta as $VacacionComunConsulta){

                    if(strtotime($VacacionComunConsulta->fechaInicio)>=strtotime($fechaInicioMesActual)
                        &&  strtotime($VacacionComunConsulta->fechaInicio)<=strtotime($fechaFinMesActual) 
                        &&  strtotime($VacacionComunConsulta->fechaFin)>=strtotime($fechaFinMesActual))
                    {
                        
                        $diaI = $VacacionComunConsulta->fechaInicio;
                        $diaF = $fechaFinMesActual;
                        if(substr($diaF, 8, 2) == "31"){
                            $diaF = date("Y-m-30",strtotime($diaF));
                        }
                        if(substr($diaI, 8, 2) == "31"){
                            $diaI = date("Y-m-30",strtotime($diaI));
                        }
                        $diasCompensar = $this->days_360($diaI, $diaF) + 1;
                        $diasPagoVac = $diasCompensar;                    
                    
                    }
                    else if(strtotime($VacacionComunConsulta->fechaFin)>=strtotime($fechaInicioMesActual)  
                    &&  strtotime($VacacionComunConsulta->fechaFin)<=strtotime($fechaFinMesActual) 
                    &&  strtotime($VacacionComunConsulta->fechaInicio)<=strtotime($fechaInicioMesActual))
                    {
                    
                        
                        $diaI = $fechaInicioMesActual;
                        $diaF = $VacacionComunConsulta->fechaFin;
                        if(substr($diaF, 8, 2) == "31"){
                            $diaF = date("Y-m-30",strtotime($diaF));
                        }
                        
                        $diasCompensar = $this->days_360($fechaInicioMesActual, $VacacionComunConsulta->fechaFin) + 1;
                        $diasPagoVac = $diasCompensar;
                    }
                    else if(strtotime($VacacionComunConsulta->fechaInicio)<=strtotime($fechaInicioMesActual)  
                    &&  strtotime($VacacionComunConsulta->fechaFin)>=strtotime($fechaFinMesActual)) 
                    {
                        
                        
                        $diaI = $fechaInicioMesActual;
                        $diaF = $fechaFinMesActual;
                        if(substr($diaF, 8, 2) == "31"){
                            $diaF = date("Y-m-30",strtotime($diaF));
                        }
                        $diasCompensar = $this->days_360($fechaInicioMesActual, $diaF) + 1;
                        $diasPagoVac = $diasCompensar;
                    }
                    else if(strtotime($fechaInicioMesActual)<=strtotime($VacacionComunConsulta->fechaInicio)  
                    &&  strtotime($fechaFinMesActual)>=strtotime($VacacionComunConsulta->fechaFin)) 
                    {
                    
                        $diaI = $VacacionComunConsulta->fechaInicio;
                        $diaF = $VacacionComunConsulta->fechaFin;
                        if(substr($diaF, 8, 2) == "31"){
                            $diaF = date("Y-m-30",strtotime($diaF));
                        }
                        if(substr($diaI, 8, 2) == "31"){
                            $diaI = date("Y-m-30",strtotime($diaI));
                        }


                        $diasCompensar = $this->days_360($diaI, $diaF) + 1;
                        $diasPagoVac = $diasCompensar;
                    }
                   
                    $diasTotales = $VacacionComunConsulta->diasCompensar;
                    $VacacionComunConsulta->diasCompensar = intval( $diasCompensar);
                    $itemBoucherNovedad = DB::table("item_boucher_pago_novedad", "ibpn")
                    ->join("item_boucher_pago as ibp","ibp.idItemBoucherPago", "=","ibpn.fkItemBoucher")
                    ->join("boucherpago as bp","bp.idBoucherPago", "=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->selectRaw("sum(ibpn.valor) as valor")
                    ->where("ibpn.fkNovedad", "=",$VacacionComunConsulta->idNovedad)
                    ->whereBetween("ln.fechaLiquida",[$fechaInicioMesActual, $fechaFinMesActual])
                    ->first();                         
                    
                    if($VacacionComunConsulta->pagoAnticipado == 1){
                        if(isset($itemBoucherNovedad) && $itemBoucherNovedad->valor > 0){
                            $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                            $restaIbc = $valorNovedad;
                            //dd($valorNovedad, $novedadVac->fechaFin);
                            if($diasPagoVac>0){
    
                                
                                if(strtotime($fechaFinMesActual) > strtotime($VacacionComunConsulta->fechaInicio) && substr($VacacionComunConsulta->fechaFin, 8, 2) == "31"){
                                    //Comentado para hidroyunda
                                    $diasPagoVac++;                                   
                                }
                                else if(strtotime($fechaFinMesActual) > strtotime($VacacionComunConsulta->fechaInicio) && date("t", strtotime($VacacionComunConsulta->fechaInicio)) == "31" && strtotime($VacacionComunConsulta->fechaFin) > strtotime(date("Y-m-t", strtotime($VacacionComunConsulta->fechaInicio)))){
                                    //Si este mes tiene 31 dias y pasa de mes
                                    //Comentado para hidroyunda
                                    
                                    $diasPagoVac++;   
                                }
                                $valorNovedad = $diasPagoVac*$valorNovedad/$diasTotales;
                            }
                            else{
                                $diasPagoVac = 1;
                                $valorNovedad = $diasPagoVac*$valorNovedad/$diasTotales;
                            }
                            
                        }
                        else{
                            $itemBoucherNovedad = DB::table("item_boucher_pago_novedad", "ibpn")
                            ->where("ibpn.fkNovedad", "=",$VacacionComunConsulta->idNovedad)
                            ->first();
                            $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                            $valorNovedad = $diasPagoVac*$valorNovedad/$diasTotales;
                        }
                    }
                    else{                    
                        $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);    
                    }

                    
                    $valorNovedad = $valorNovedad."";
                    $posPunto = strpos($valorNovedad,".");
                    
                    if($posPunto === false){
                        $valorNovedad = $valorNovedad;
                    }
                    else{
                        $decimales = substr($valorNovedad, $posPunto + 1, 1);
                        $valorNovedad = substr($valorNovedad,0, $posPunto);
                        if($decimales >= 5){
                            $valorNovedad++;
                        }
                        
                    }
                    $valorNovedad = 0;
                    $VacacionesComunes = new Etiqueta("VacacionesComunes",[
                        "Cantidad" => round($diasCompensar),
                        "Pago" => $valorNovedad
                    ]);

                    $arrFila[12] = (isset($arrFila[12]) ? ($arrFila[12] + $valorNovedad) : $valorNovedad);
                    array_push($Vacaciones->hijos, $VacacionesComunes);
                    $ValDev+= $valorNovedad ?? 0;                   
                    $VACACION_DATAICO = array();
                    $VACACION_DATAICO["code"] = "VACACION";
                    $VACACION_DATAICO["amount"] = $valorNovedad;
                    $VACACION_DATAICO["days"] = round($diasCompensar);
                    array_push($arrJSON_DATAICO["accruals"], $VACACION_DATAICO);
                }

            }
            //dd($arrJSON_DATAICO["accruals"]);
            $VacacionesCompesadasConsulta = DB::table("item_boucher_pago","ibp")
            ->select("ibp.valor", "ibp.cantidad")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","13")//
            ->get();

            if(sizeof($VacacionesCompesadasConsulta) > 0){
              
                foreach($VacacionesCompesadasConsulta as $VacacionCompesadaConsulta){
                    $VacacionesCompensadas = new Etiqueta("VacacionesCompensadas",[
                        "Cantidad" => round($VacacionCompesadaConsulta->cantidad),
                        "Pago" => $VacacionCompesadaConsulta->valor
                    ]);
                    $arrFila[13] = (isset($arrFila[13]) ? ($arrFila[13] + $VacacionCompesadaConsulta->valor) : $VacacionCompesadaConsulta->valor);
                    array_push($Vacaciones->hijos, $VacacionesCompensadas);
                    $ValDev+= $VacacionCompesadaConsulta->valor ?? 0;

                    if($VacacionCompesadaConsulta->cantidad > 30){
                        $VacacionCompesadaConsulta->cantidad = 30;
                    }

                    $VACACION_COMPENSADA_DATAICO = array();
                    $VACACION_COMPENSADA_DATAICO["code"] = "VACACION_COMPENSADA";
                    $VACACION_COMPENSADA_DATAICO["amount"] = $VacacionCompesadaConsulta->valor;
                    $VACACION_COMPENSADA_DATAICO["days"] = round($VacacionCompesadaConsulta->cantidad);
                    array_push($arrJSON_DATAICO["accruals"], $VACACION_COMPENSADA_DATAICO);
                }
            }

            if(sizeof($VacacionesCompesadasConsulta) > 0 || isset($VacacionesComunesConsulta)){
                array_push($Devengados->hijos, $Vacaciones);
            }



            $Auxilios = new Etiqueta("Auxilios");

            $AuxilioSConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","23")//
            ->first();

            $AuxilioNSConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","24")//
            ->first();

            if(isset($AuxilioSConsulta->suma_valor)){
                $Auxilios->atributos["AuxilioS"] = $AuxilioSConsulta->suma_valor ?? 0;
                $ValDev+= $AuxilioSConsulta->suma_valor ?? 0;
                $arrFila[23] = $AuxilioSConsulta->suma_valor;
            }
            if(isset($AuxilioNSConsulta->suma_valor)){
                $Auxilios->atributos["AuxilioNS"] = $AuxilioNSConsulta->suma_valor ?? 0;
                $ValDev+= $AuxilioNSConsulta->suma_valor ?? 0;
                $arrFila[24] = $AuxilioNSConsulta->suma_valor;
            }
            if((isset($AuxilioSConsulta->suma_valor)) || isset($AuxilioNSConsulta->suma_valor)){
                $auxilio_dataico = array();
                $auxilio_dataico["code"] = "AUXILIO";
                if(isset($AuxilioSConsulta->suma_valor)){
                    $auxilio_dataico["amount"] = round($AuxilioSConsulta->suma_valor);
                }
                if(isset($AuxilioNSConsulta->suma_valor)){
                    $auxilio_dataico["amount-ns"] = round($AuxilioNSConsulta->suma_valor);
                }
                array_push($arrJSON_DATAICO["accruals"], $auxilio_dataico);
            }

            if(sizeof($Auxilios->atributos) > 0){
                array_push($Devengados->hijos, $Auxilios);
            }



            $OtrosConceptos = new Etiqueta("OtrosConceptos");
            //Consulto primero las provisiones de vacaciones para luego compararlo con alguna vacacion de retiro
            $ProvVacacionesConsultaValor = DB::table("provision","p")
            ->select(DB::raw("p.valor as suma_valor,p.idProvision"))
            ->join("concepto as c", "c.idconcepto","=","p.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("p.fkEmpleado","=", $empleado->idempleado)
            ->where("p.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("p.anio","=",intval(date("Y",strtotime($fechaFinMesActual))))
            ->where("p.mes","=",intval(date("m",strtotime($fechaFinMesActual))))            
            ->where("gne.idGrupoNominaElectronica","=","26")
            ->first();   
            if(isset($fechaRetiroJSON)){
                $ProvVacacionesConsultaValor = DB::table("provision","p")
                ->select(DB::raw("sum(p.valor) as suma_valor"))
                ->join("concepto as c", "c.idconcepto","=","p.fkConcepto")
                ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
                ->where("p.fkEmpleado","=", $empleado->idempleado)
                ->where("p.fkPeriodoActivo","=",$empleado->idPeriodo)
                ->where("p.anio","=",intval(date("Y",strtotime($fechaFinMesActual))))
                ->where("p.mes","<",intval(date("m",strtotime($fechaFinMesActual))))            
                ->where("gne.idGrupoNominaElectronica","=","26")
                ->first();               
            }





            $ConceptoSConsulta = DB::table("item_boucher_pago","ibp")
            ->select("ibp.valor","c.nombre as nombreConcepto", "ibp.cantidad", "c.idconcepto")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","25")//
            ->get();

            $ConceptoNSConsulta = DB::table("item_boucher_pago","ibp")
            ->select("ibp.valor","c.nombre as nombreConcepto", "ibp.cantidad", "c.idconcepto")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","26")//
            ->get();
            $vacacionComoDeduccion = null;
            //dd($ConceptoSConsulta, $ConceptoNSConsulta);
            foreach($ConceptoSConsulta as $ConcSConsulta){

                if($ConcSConsulta->idconcepto == "30"){
                    
                    if(isset($ProvVacacionesConsultaValor->suma_valor)){
                        #$ProvVacacionesConsultaValor->suma_valor -= $ConcSConsulta->valor;
                        $ProvVacacionesConsultaValor->suma_valor = $ConcSConsulta->valor - $ProvVacacionesConsultaValor->suma_valor;
                    }
                    
                    if($ConcSConsulta->valor < 0){
                        $ProvVacacionesConsultaValor->suma_valor = 0;
                        $ConcSConsulta->valor = $ProvVacacionesConsultaValor->suma_valor;
                        $vacacionComoDeduccion = $ConcSConsulta;                        
                    }
                    continue;
                }
                
                $OtroConcepto = new Etiqueta("OtroConcepto",[
                    "DescripcionConcepto" => ucfirst(strtolower($ConcSConsulta->nombreConcepto)),
                    "ConceptoS" => $ConcSConsulta->valor
                ]);
                $arrFila[25] = (isset($arrFila[25]) ? ($arrFila[25] + $ConcSConsulta->valor) : $ConcSConsulta->valor);
                $ValDev+= $ConcSConsulta->valor ?? 0;
                array_push($OtrosConceptos->hijos, $OtroConcepto);
                $OTRO_CONCEPTO_DATAICO = array();
                $OTRO_CONCEPTO_DATAICO["code"] = "OTRO_CONCEPTO";
                $OTRO_CONCEPTO_DATAICO["description"] = ucfirst(strtolower($ConcSConsulta->nombreConcepto));
                
                $OTRO_CONCEPTO_DATAICO["amount"] = round($ConcSConsulta->valor);
                
                array_push($arrJSON_DATAICO["accruals"], $OTRO_CONCEPTO_DATAICO);
            }

            foreach($ConceptoNSConsulta as $ConcNSConsulta){                
                $OtroConcepto = new Etiqueta("OtroConcepto",[
                    "DescripcionConcepto" => ucfirst(strtolower($ConcNSConsulta->nombreConcepto)),
                    "ConceptoNS" => $ConcNSConsulta->valor
                ]);
                $arrFila[26] = (isset($arrFila[26]) ? ($arrFila[26] + $ConcNSConsulta->valor) : $ConcNSConsulta->valor);
                $ValDev+= $ConcNSConsulta->valor ?? 0;
                array_push($OtrosConceptos->hijos, $OtroConcepto);
                $OTRO_CONCEPTO_NS_DATAICO = array();
                $OTRO_CONCEPTO_NS_DATAICO["code"] = "OTRO_CONCEPTO";
                $OTRO_CONCEPTO_NS_DATAICO["description"] = ucfirst(strtolower($ConcNSConsulta->nombreConcepto));
                $OTRO_CONCEPTO_NS_DATAICO["amount-ns"] = round($ConcNSConsulta->valor);
                array_push($arrJSON_DATAICO["accruals"], $OTRO_CONCEPTO_NS_DATAICO);
            }

            if(isset($ProvVacacionesConsultaValor->suma_valor) && $ProvVacacionesConsultaValor->suma_valor > 0 ){

                /*if($ProvVacacionesConsultaValor->suma_valor < 0){
                    $ProvVacacionesConsultaValor->suma_valor = 0;
                }*/

                $OtroConcepto = new Etiqueta("OtroConcepto",[
                    "DescripcionConcepto" => "Provision Vacaciones",
                    "ConceptoNS" => $ProvVacacionesConsultaValor->suma_valor
                ]);
                
                $arrFila[26] = (isset($arrFila[26]) ? ($arrFila[26] + $ProvVacacionesConsultaValor->suma_valor) : $ProvVacacionesConsultaValor->suma_valor);
                $ValDev+= $ProvVacacionesConsultaValor->suma_valor ?? 0;
                array_push($OtrosConceptos->hijos, $OtroConcepto);
                $OTRO_CONCEPTO_NS_DATAICO = array();
                $OTRO_CONCEPTO_NS_DATAICO["code"] = "OTRO_CONCEPTO";
                $OTRO_CONCEPTO_NS_DATAICO["description"] = "Provision Vacaciones";
                $OTRO_CONCEPTO_NS_DATAICO["amount-ns"] = round($ProvVacacionesConsultaValor->suma_valor);
                array_push($arrJSON_DATAICO["accruals"], $OTRO_CONCEPTO_NS_DATAICO);
            }


            if(sizeof($OtrosConceptos->hijos) > 0){
                array_push($Devengados->hijos, $OtrosConceptos);
            }



            $Compensaciones = new Etiqueta("Compensaciones");

            $CompensacionOConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","27")//
            ->first();

            $CompensacionEConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","28")//
            ->first();

           
            $Compensacion = new Etiqueta("Compensacion");
            $Compensacion->atributos["CompensacionO"] = $CompensacionOConsulta->suma_valor ?? 0;
            $Compensacion->atributos["CompensacionE"] = $CompensacionEConsulta->suma_valor ?? 0;

            $arrFila[27] = $CompensacionOConsulta->suma_valor;
            $arrFila[28] = $CompensacionEConsulta->suma_valor;

            $ValDev+= $CompensacionOConsulta->suma_valor ?? 0;
            $ValDev+= $CompensacionEConsulta->suma_valor ?? 0;

            if(isset($CompensacionOConsulta->suma_valor) || isset($CompensacionEConsulta->suma_valor)){
                array_push($Compensaciones->hijos, $Compensacion);
                array_push($Devengados->hijos, $Compensaciones);

                $COMPENSACION_DATAICO = array();
                $COMPENSACION_DATAICO["code"] = "COMPENSACION";
                if(isset($CompensacionOConsulta->suma_valor)){
                    $COMPENSACION_DATAICO["ordinary-compensation"] = round($CompensacionOConsulta->suma_valor);
                }
                if(isset($CompensacionEConsulta->suma_valor)){
                    $COMPENSACION_DATAICO["extraordinary-compensation"] = round($CompensacionEConsulta->suma_valor);
                }
                array_push($arrJSON_DATAICO["accruals"], $COMPENSACION_DATAICO);
            }
           
            


            $BonoEPCTVs = new Etiqueta("BonoEPCTVs");
            
            
            $PagoSConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","29")//
            ->first();

            $PagoNSConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","30")//
            ->first();


            $PagoAlimentacionSConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","31")//
            ->first();

            $PagoAlimentacionNSConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","32")//
            ->first();


            $PagoAlimentacionNSConsultaFueraNomina = DB::table("item_boucher_pago_fuera_nomina","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","32")//
            ->first();

            
            if(isset($PagoAlimentacionNSConsultaFueraNomina->suma_valor)){
                if(!isset($PagoAlimentacionNSConsulta->suma_valor)){
                    $PagoAlimentacionNSConsulta->suma_valor = 0;
                }
                $PagoAlimentacionNSConsulta->suma_valor += $PagoAlimentacionNSConsultaFueraNomina->suma_valor;
            }



            
            $BonoEPCTV = new Etiqueta("BonoEPCTV");
            if(isset($PagoSConsulta->suma_valor)){
                $BonoEPCTV->atributos["PagoS"] = $PagoSConsulta->suma_valor;
                $arrFila[29] = $PagoSConsulta->suma_valor;
                $ValDev+= $PagoSConsulta->suma_valor ?? 0;
            }
            if(isset($PagoNSConsulta->suma_valor)){
                $BonoEPCTV->atributos["PagoNS"] = $PagoNSConsulta->suma_valor;
                $arrFila[30] = $PagoNSConsulta->suma_valor;
                $ValDev+= $PagoNSConsulta->suma_valor ?? 0;
            }
            if(isset($PagoAlimentacionSConsulta->suma_valor)){
                $BonoEPCTV->atributos["PagoAlimentacionS"] = $PagoAlimentacionSConsulta->suma_valor;
                $arrFila[31] = $PagoAlimentacionSConsulta->suma_valor;
                $ValDev+= $PagoAlimentacionSConsulta->suma_valor ?? 0;
            }
            if(isset($PagoAlimentacionNSConsulta->suma_valor)){
                $BonoEPCTV->atributos["PagoAlimentacionNS"] = $PagoAlimentacionNSConsulta->suma_valor;
                $arrFila[32] = $PagoAlimentacionNSConsulta->suma_valor;
                $ValDev+= $PagoAlimentacionNSConsulta->suma_valor ?? 0;
            }
            if(sizeof($BonoEPCTV->atributos) > 0){
                array_push($BonoEPCTVs->hijos, $BonoEPCTV);
                array_push($Devengados->hijos, $BonoEPCTVs);
            }
            if((isset($PagoSConsulta->suma_valor)) || (isset($PagoNSConsulta->suma_valor))){
                $bono_epctv_dataico = array();
                $bono_epctv_dataico["code"] = "BONO_EPCTV";
                if(isset($PagoSConsulta->suma_valor)){
                    $bono_epctv_dataico["amount"] = round($PagoSConsulta->suma_valor);
                }
                if(isset($PagoNSConsulta->suma_valor)){
                    $bono_epctv_dataico["amount-ns"] = round($PagoNSConsulta->suma_valor);
                }
                array_push($arrJSON_DATAICO["accruals"], $bono_epctv_dataico);
            }

            if((isset($PagoAlimentacionSConsulta->suma_valor)) || (isset($PagoAlimentacionNSConsulta->suma_valor))){
                $bono_epctv_dataico_alimentacion = array();
                $bono_epctv_dataico_alimentacion["code"] = "BONO_EPCTV_ALIMENTACION";
                if(isset($PagoAlimentacionSConsulta->suma_valor)){
                    $bono_epctv_dataico_alimentacion["amount"] = round($PagoAlimentacionSConsulta->suma_valor);
                }
                if(isset($PagoAlimentacionNSConsulta->suma_valor)){
                    $bono_epctv_dataico_alimentacion["amount-ns"] = round($PagoAlimentacionNSConsulta->suma_valor);
                }
                array_push($arrJSON_DATAICO["accruals"], $bono_epctv_dataico_alimentacion);
            }

            
            $Comisiones = new Etiqueta("Comisiones");
            
            $ComisionesConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","33")//
            ->first();
            if(isset($ComisionesConsulta->suma_valor)){
                $Comision = new Etiqueta("Comision",[],[], $ComisionesConsulta->suma_valor);
                $arrFila[33] = $ComisionesConsulta->suma_valor;
                array_push($Comisiones->hijos, $Comision);
                array_push($Devengados->hijos, $Comisiones);
                $ValDev+= $ComisionesConsulta->suma_valor ?? 0;

                $comision_dataico["code"] = "COMISION";
                $comision_dataico["amount"] = round($ComisionesConsulta->suma_valor);
                array_push($arrJSON_DATAICO["accruals"], $comision_dataico);
            }


            $PagosTerceros = new Etiqueta("PagosTerceros");
            
            $PagoTerceroConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","34")//
            ->first();

            
            if(isset($PagoTerceroConsulta->suma_valor)){
                $PagoTercero = new Etiqueta("PagoTercero",[],[], $PagoTerceroConsulta->suma_valor);
                $arrFila[34] = $PagoTerceroConsulta->suma_valor;
                array_push($PagosTerceros->hijos, $PagoTercero);
                array_push($Devengados->hijos, $PagosTerceros);
                $ValDev+= $PagoTerceroConsulta->suma_valor ?? 0;

                $pago_tercero["code"] = "PAGO_TERCERO";
                $pago_tercero["amount"] = round($PagoTerceroConsulta->suma_valor);
                array_push($arrJSON_DATAICO["accruals"], $pago_tercero);
            }


            $Anticipos = new Etiqueta("Anticipos");
            
            $AnticipoConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","35")//
            ->first();
            if(isset($AnticipoConsulta->suma_valor)){
                $Anticipo = new Etiqueta("Anticipo",[],[], $AnticipoConsulta->suma_valor);
                $arrFila[35] = $AnticipoConsulta->suma_valor;
                array_push($Anticipos->hijos, $Anticipo);
                array_push($Devengados->hijos, $Anticipos);
                $ValDev+= $AnticipoConsulta->suma_valor ?? 0;

                $ANTICIPO_DATAICO["code"] = "ANTICIPO";
                $ANTICIPO_DATAICO["amount"] = round($AnticipoConsulta->suma_valor);
                array_push($arrJSON_DATAICO["accruals"], $ANTICIPO_DATAICO);
            }

            
            
            $DotacionConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","36")//
            ->first();
            if(isset($DotacionConsulta->suma_valor)){
                $Dotacion = new Etiqueta("Dotacion",[],[], $DotacionConsulta->suma_valor);
                $arrFila[36] = $DotacionConsulta->suma_valor;
                array_push($Devengados->hijos, $Dotacion);
                $ValDev+= $DotacionConsulta->suma_valor ?? 0;

                $DOTACION_DATAICO["code"] = "DOTACION";
                $DOTACION_DATAICO["amount"] = round($DotacionConsulta->suma_valor);
                array_push($arrJSON_DATAICO["accruals"], $DOTACION_DATAICO);
            }


            $ApoyoSostConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","37")//
            ->first();
            if(isset($ApoyoSostConsulta->suma_valor)){
                $ApoyoSost = new Etiqueta("ApoyoSost",[],[], $ApoyoSostConsulta->suma_valor);
                $arrFila[37] = $ApoyoSostConsulta->suma_valor;
                array_push($Devengados->hijos, $ApoyoSost);
                $ValDev+= $ApoyoSostConsulta->suma_valor ?? 0;

                $APOYO_PRACTICA_DATAICO["code"] = "APOYO_PRACTICA";
                $APOYO_PRACTICA_DATAICO["amount"] = round($ApoyoSostConsulta->suma_valor);
                array_push($arrJSON_DATAICO["accruals"], $APOYO_PRACTICA_DATAICO);
            }
            

            $TeletrabajoConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","38")//
            ->first();
            if(isset($TeletrabajoConsulta->suma_valor)){
                $Teletrabajo = new Etiqueta("Teletrabajo",[],[], $TeletrabajoConsulta->suma_valor);
                $arrFila[38] = $TeletrabajoConsulta->suma_valor;
                array_push($Devengados->hijos, $Teletrabajo);
                $ValDev+= $TeletrabajoConsulta->suma_valor ?? 0;

                $TELETRABAJO_DATAICO["code"] = "TELETRABAJO";
                $TELETRABAJO_DATAICO["amount"] = round($TeletrabajoConsulta->suma_valor);
                array_push($arrJSON_DATAICO["accruals"], $TELETRABAJO_DATAICO);
            }


            $BonifRetiroConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","39")//
            ->first();
            if(isset($BonifRetiroConsulta->suma_valor)){
                $BonifRetiro = new Etiqueta("BonifRetiro",[],[], $BonifRetiroConsulta->suma_valor);
                $arrFila[39] = $BonifRetiroConsulta->suma_valor;
                array_push($Devengados->hijos, $BonifRetiro);
                $ValDev+= $BonifRetiroConsulta->suma_valor ?? 0;

                $BONIFICACION_RETIRO_DATAICO["code"] = "BONIFICACION_RETIRO";
                $BONIFICACION_RETIRO_DATAICO["amount"] = round($BonifRetiroConsulta->suma_valor);
                array_push($arrJSON_DATAICO["accruals"], $BONIFICACION_RETIRO_DATAICO);
            }


            $IndemnizacionConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","40")//
            ->first();
            if(isset($IndemnizacionConsulta->suma_valor)){
                $Indemnizacion = new Etiqueta("Indemnizacion",[],[], $IndemnizacionConsulta->suma_valor);
                $arrFila[40] = $IndemnizacionConsulta->suma_valor;
                array_push($Devengados->hijos, $Indemnizacion);
                $ValDev+= $IndemnizacionConsulta->suma_valor ?? 0;

                $INDEMNIZACION_DATAICO["code"] = "INDEMNIZACION";
                $INDEMNIZACION_DATAICO["amount"] = round($IndemnizacionConsulta->suma_valor);
                array_push($arrJSON_DATAICO["accruals"], $INDEMNIZACION_DATAICO);
            }

            $ReintegroPagoConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","41")//
            ->first();
            if(isset($ReintegroPagoConsulta->suma_valor)){
                $Reintegro = new Etiqueta("Reintegro",[],[], $ReintegroPagoConsulta->suma_valor);
                $arrFila[41] = $ReintegroPagoConsulta->suma_valor;
                array_push($Devengados->hijos, $Reintegro);
                $ValDev+= $ReintegroPagoConsulta->suma_valor ?? 0;

                $REINTEGRO_DATAICO["code"] = "REINTEGRO";
                $REINTEGRO_DATAICO["amount"] = round($ReintegroPagoConsulta->suma_valor);
                array_push($arrJSON_DATAICO["accruals"], $REINTEGRO_DATAICO);
            }
            array_push($nominaIndividial->hijos, $Devengados);
            
            $arrJSON_DATAICO["deductions"] = array();

            $Deducciones = new Etiqueta("Deducciones");

            
            $SaludConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","42")//
            ->first();
            $porcentaje = 4;
            if($empleado->fkTipoCotizante == "12" || $empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23"){
                $porcentaje = 0;
            }
            $Salud = new Etiqueta("Salud",[
                "Porcentaje" => $porcentaje,
                "Deduccion" => (($SaludConsulta->suma_valor ?? 0)*-1)
            ]);
            $arrFila[42] = $SaludConsulta->suma_valor;
            $ValDed+= $SaludConsulta->suma_valor ?? 0;


            $SALUD_DATAICO["code"] = "SALUD";
            $SALUD_DATAICO["amount"] = (($SaludConsulta->suma_valor ?? 0)*-1);      
            $SALUD_DATAICO["percentage"] = $porcentaje;      
            if($SALUD_DATAICO["amount"] != 0){
                array_push($arrJSON_DATAICO["deductions"], $SALUD_DATAICO);
            }
            
            
            array_push($Deducciones->hijos, $Salud);

            $FondoPensionConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","43")//
            ->first();
            $porcentaje = 4;
            if($empleado->fkTipoCotizante == "12" || $empleado->fkTipoCotizante == "23" || $empleado->fkTipoCotizante == "19" || $empleado->esPensionado != 0){
                $porcentaje = 0;
            }

            $FondoPension = new Etiqueta("FondoPension",[
                "Porcentaje" => $porcentaje,
                "Deduccion" => (($FondoPensionConsulta->suma_valor ?? 0)*-1)
            ]);
            $arrFila[43] = $FondoPensionConsulta->suma_valor;
            array_push($Deducciones->hijos, $FondoPension);
            $ValDed+= $FondoPensionConsulta->suma_valor ?? 0;

            $FONDO_PENSION_DATAICO["code"] = "FONDO_PENSION";
            $FONDO_PENSION_DATAICO["amount"] = (($FondoPensionConsulta->suma_valor ?? 0)*-1);      
            $FONDO_PENSION_DATAICO["percentage"] = $porcentaje;      
            
            if($FondoPensionConsulta->suma_valor != 0){
                array_push($arrJSON_DATAICO["deductions"], $FONDO_PENSION_DATAICO);
            }
            


            $FondoSPConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","44")//
            ->first();

            $FondoSPConsultaPorcentaje = DB::table("item_boucher_pago","ibp")
            ->select("ibp.porcentaje")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","44")//
            ->orderBy("ibp.idItemBoucherPago","desc")
            ->first();
           
            $FondoSubConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","45")//
            ->first();

            $FondoSubConsultaPorcentaje = DB::table("item_boucher_pago","ibp")
            ->select("ibp.porcentaje")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","45")//
            ->orderBy("ibp.idItemBoucherPago","desc")
            ->first();
            $FondoSP = new Etiqueta("FondoSP");

            if(isset($FondoSPConsulta->suma_valor)){
                $FondoSP->atributos["Porcentaje"] = $FondoSPConsultaPorcentaje->porcentaje ?? 0;
                $FondoSP->atributos["DeduccionSP"] = ($FondoSPConsulta->suma_valor ?? 0) * -1;
                $arrFila[44] = $FondoSPConsulta->suma_valor;
                $ValDed+= $FondoSPConsulta->suma_valor ?? 0;

                $FONDO_SOLIDARIDAD_PENSIONAL_DATAICO["code"] = "FONDO_SOLIDARIDAD_PENSIONAL";
                $FONDO_SOLIDARIDAD_PENSIONAL_DATAICO["amount"] = ($FondoSPConsulta->suma_valor ?? 0) * -1;      
                $FONDO_SOLIDARIDAD_PENSIONAL_DATAICO["percentage"] = ($FondoSPConsultaPorcentaje->porcentaje ?? "1.00");
                        
                array_push($arrJSON_DATAICO["deductions"], $FONDO_SOLIDARIDAD_PENSIONAL_DATAICO);
                
            }
            if(isset($FondoSubConsultaPorcentaje->suma_valor)){
                $FondoSP->atributos["PorcentajeSub"] = $FondoSubConsultaPorcentaje->porcentaje ?? 0;
                $FondoSP->atributos["DeduccionSub"] = ($FondoSubConsulta->suma_valor ?? 0) *-1;
                $arrFila[45] = $FondoSubConsulta->suma_valor;
                $ValDed+= $FondoSubConsulta->suma_valor ?? 0;

                $FONDO_SUBSISTENCIA_DATAICO["code"] = "FONDO_SUBSISTENCIA";
                $FONDO_SUBSISTENCIA_DATAICO["amount"] = ($FondoSubConsulta->suma_valor ?? 0) *-1;      
                $FONDO_SUBSISTENCIA_DATAICO["percentage"] = $FondoSubConsultaPorcentaje->porcentaje;      
                        
                array_push($arrJSON_DATAICO["deductions"], $FONDO_SUBSISTENCIA_DATAICO);
            }
            if(sizeof($FondoSP->atributos) > 0){
                array_push($Deducciones->hijos, $FondoSP);
            }
            

            $SindicatoConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","46")//
            ->first();

            $SindicatoConsultaPorcentaje = DB::table("item_boucher_pago","ibp")
            ->select("ibp.porcentaje")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","46")//
            ->orderBy("ibp.idItemBoucherPago","desc")
            ->first();
            $Sindicatos = new Etiqueta("Sindicatos");
            $Sindicato = new Etiqueta("Sindicato");

            if(isset($SindicatoConsulta->suma_valor)){
                $Sindicato->atributos["Porcentaje"] = $SindicatoConsultaPorcentaje->porcentaje ?? 0;
                $Sindicato->atributos["Deduccion"] = ($SindicatoConsulta->suma_valor ?? 0) * -1;
                $arrFila[46] = $SindicatoConsulta->suma_valor;
                $ValDed+= $SindicatoConsulta->suma_valor ?? 0;

                $SINDICATO_DATAICO["code"] = "SINDICATO";
                $SINDICATO_DATAICO["amount"] = ($SindicatoConsulta->suma_valor ?? 0) * -1;      
                $SINDICATO_DATAICO["percentage"] = $SindicatoConsultaPorcentaje->porcentaje;      
                array_push($arrJSON_DATAICO["deductions"], $SINDICATO_DATAICO);
            }
          
            if(sizeof($Sindicato->atributos) > 0){
                array_push($Sindicatos->hijos, $Sindicato);
                array_push($Deducciones->hijos, $Sindicatos);
            }


            $SancionPublicConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","47")//
            ->first();

            $SancionPrivConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","48")//
            ->first();

            $Sanciones = new Etiqueta("Sanciones");
            $Sancion = new Etiqueta("Sancion");
            if(isset($SancionPublicConsulta->suma_valor)){
                $Sancion->atributos["SancionPublic"] = ($SancionPublicConsulta->suma_valor ?? 0) * -1;
                $ValDed+= $SancionPublicConsulta->suma_valor ?? 0;
                $arrFila[47] = $SancionPublicConsulta->suma_valor;

                $SANCION_DATAICO["code"] = "SANCION";
                $SANCION_DATAICO["public-sanction"] = ($SancionPublicConsulta->suma_valor ?? 0) * -1;      
                array_push($arrJSON_DATAICO["deductions"], $SANCION_DATAICO);
            }

            if(isset($SancionPrivConsulta->suma_valor)){
                $Sancion->atributos["SancionPriv"] = ($SancionPrivConsulta->suma_valor ?? 0) * -1;
                $ValDed+= $SancionPrivConsulta->suma_valor ?? 0;
                $arrFila[48] = $SancionPrivConsulta->suma_valor;

                $SANCION_PRIV_DATAICO["code"] = "SANCION";
                $SANCION_PRIV_DATAICO["private-sanction"] = ($SancionPrivConsulta->suma_valor ?? 0) * -1;
                array_push($arrJSON_DATAICO["deductions"], $SANCION_PRIV_DATAICO);
            }

            if(sizeof($Sancion->atributos) > 0){
                array_push($Sanciones->hijos, $Sancion);
                array_push($Deducciones->hijos, $Sanciones);
            }



            $LibranzasConsulta = DB::table("item_boucher_pago","ibp")
            ->select("ibp.idItemBoucherPago", "ibp.valor", "c.nombre as nombreConcepto")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            //->join("item_boucher_pago_prestamo as ibpp", "ibpp.fkItemBoucher","=","ibp.idItemBoucherPago", "left")
            //->join("prestamo as p", "p.idPrestamo","=","ibpp.fkPrestamo", "left")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","49")//
            ->get();
            
            
            //dd($LibranzasConsulta);

            $Libranzas = new Etiqueta("Libranzas");
            foreach($LibranzasConsulta as $LibranzaConsulta){
                
                $Libranza = new Etiqueta("Libranza",[
                    "Descripcion" => ucfirst(strtolower($LibranzaConsulta->nombreConcepto)),
                    "Deduccion" => ($LibranzaConsulta->valor ?? 0) * -1
                ]);
                $arrFila[49] = (isset($arrFila[49]) ? ($arrFila[49] + $LibranzaConsulta->valor) : $LibranzaConsulta->valor);                
                $ValDed+= $LibranzaConsulta->valor ?? 0;
                array_push($Libranzas->hijos, $Libranza);

                $LIBRANZA_DATAICO["code"] = "LIBRANZA";
                $LIBRANZA_DATAICO["amount"] = ($LibranzaConsulta->valor ?? 0) * -1;
                $LIBRANZA_DATAICO["description"] = ucfirst(strtolower($LibranzaConsulta->nombreConcepto));
                array_push($arrJSON_DATAICO["deductions"], $LIBRANZA_DATAICO);
            }
            if(sizeof($Libranzas->hijos) > 0){
                array_push($Deducciones->hijos, $Libranzas);
            }
            
          
            $PagoTerceroConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","50")//
            ->first();
            if(isset($PagoTerceroConsulta->suma_valor)){
                $PagosTerceros = new Etiqueta("PagosTerceros");
                $PagoTercero = new Etiqueta("PagoTercero",[],[], ($PagoTerceroConsulta->suma_valor ?? 0) * -1);
                $arrFila[50] = $PagoTerceroConsulta->suma_valor;
                array_push($PagosTerceros->hijos, $PagoTercero);
                array_push($Deducciones->hijos, $PagosTerceros);
                $ValDed+= $PagoTerceroConsulta->suma_valor ?? 0;

                $PAGO_TERCERO_DATAICO["code"] = "PAGO_TERCERO";
                $PAGO_TERCERO_DATAICO["amount"] = ($PagoTerceroConsulta->suma_valor ?? 0) * -1;                
                array_push($arrJSON_DATAICO["deductions"], $PAGO_TERCERO_DATAICO);
            }



            $AnticiposConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","51")//
            ->first();
            if(isset($AnticiposConsulta->suma_valor)){
                $Anticipos = new Etiqueta("Anticipos");
                $Anticipo = new Etiqueta("Anticipo",[],[], ($AnticiposConsulta->suma_valor ?? 0) * -1 );
                $arrFila[51] = $AnticiposConsulta->suma_valor;
                array_push($Anticipos->hijos, $Anticipo);
                array_push($Deducciones->hijos, $Anticipos);
                $ValDed+= $AnticiposConsulta->suma_valor ?? 0;

                $ANTICIPO_DATAICO["code"] = "ANTICIPO";
                $ANTICIPO_DATAICO["amount"] = ($AnticiposConsulta->suma_valor ?? 0) * -1;
                array_push($arrJSON_DATAICO["deductions"], $ANTICIPO_DATAICO);
            }


            $OtrasDeduccionesConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","52")//
            ->first();

            if(isset($OtrasDeduccionesConsulta->suma_valor)){
                $OtrasDeducciones = new Etiqueta("OtrasDeducciones");
                $OtraDeduccion = new Etiqueta("OtraDeduccion",[],[], ($OtrasDeduccionesConsulta->suma_valor ?? 0) * -1 );
                $arrFila[52] = $OtrasDeduccionesConsulta->suma_valor;
                array_push($OtrasDeducciones->hijos, $OtraDeduccion);
                array_push($Deducciones->hijos, $OtrasDeducciones);

                $ValDed+= $OtrasDeduccionesConsulta->suma_valor ?? 0;
            }

            if(isset($vacacionComoDeduccion)){
                $OtrasDeducciones = new Etiqueta("OtrasDeducciones");
                $OtraDeduccion = new Etiqueta("OtraDeduccion",[],[], ($vacacionComoDeduccion->valor ?? 0) * -1 );
                $arrFila[52] = $vacacionComoDeduccion->valor;
                array_push($OtrasDeducciones->hijos, $OtraDeduccion);
                array_push($Deducciones->hijos, $OtrasDeducciones);

                $ValDed+= $vacacionComoDeduccion->valor ?? 0;

                $OTRA_DEDUCCION_DATAICO["code"] = "OTRA_DEDUCCION";
                $OTRA_DEDUCCION_DATAICO["amount"] = ($vacacionComoDeduccion->valor ?? 0) * -1;
                $OTRA_DEDUCCION_DATAICO["description"] = ucfirst(strtolower($vacacionComoDeduccion->nombreConcepto));
                array_push($arrJSON_DATAICO["deductions"], $OTRA_DEDUCCION_DATAICO);                
            }




            $OtrasDeduccionesUnidades = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as valor"), "c.nombre")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","52")//
            ->groupBy("c.nombre")
            ->get();

            
            foreach($OtrasDeduccionesUnidades as $OtrasDeduccionesUnidad){

                if($OtrasDeduccionesUnidad->valor != 0){
                    $OTRA_DEDUCCION_DATAICO["code"] = "OTRA_DEDUCCION";
                    $OTRA_DEDUCCION_DATAICO["amount"] = ($OtrasDeduccionesUnidad->valor ?? 0) * -1;
                    $OTRA_DEDUCCION_DATAICO["description"] = ucfirst(strtolower($OtrasDeduccionesUnidad->nombre));
                    array_push($arrJSON_DATAICO["deductions"], $OTRA_DEDUCCION_DATAICO);
                }
                
            }

            $PensionVoluntariaConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","53")//
            ->first();
            if(isset($PensionVoluntariaConsulta->suma_valor)){
                $PensionVoluntaria = new Etiqueta("PensionVoluntaria",[],[], ($PensionVoluntariaConsulta->suma_valor ?? 0) * -1 );
                $arrFila[53] = $PensionVoluntariaConsulta->suma_valor;
                array_push($Deducciones->hijos, $PensionVoluntaria);
                $ValDed+= $PensionVoluntariaConsulta->suma_valor ?? 0;

                $PENSION_VOLUNTARIA_DATAICO["code"] = "PENSION_VOLUNTARIA";
                $PENSION_VOLUNTARIA_DATAICO["amount"] = ($PensionVoluntariaConsulta->suma_valor ?? 0) * -1;
                array_push($arrJSON_DATAICO["deductions"], $PENSION_VOLUNTARIA_DATAICO);
            }

            $RetencionFuenteConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","54")//
            ->first();
            if(isset($RetencionFuenteConsulta->suma_valor)){
                $RetencionFuente = new Etiqueta("RetencionFuente",[],[], ($RetencionFuenteConsulta->suma_valor ?? 0) * -1 );
                $arrFila[54] = $RetencionFuenteConsulta->suma_valor;
                array_push($Deducciones->hijos, $RetencionFuente);
                $ValDed+= $RetencionFuenteConsulta->suma_valor ?? 0;

                $RETENCION_FUENTE_DATAICO["code"] = "RETENCION_FUENTE";
                $RETENCION_FUENTE_DATAICO["amount"] = ($RetencionFuenteConsulta->suma_valor ?? 0) * -1;
                array_push($arrJSON_DATAICO["deductions"], $RETENCION_FUENTE_DATAICO);
            }
            
            $AFCConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","55")//
            ->first();
            if(isset($AFCConsulta->suma_valor)){
                $AFC = new Etiqueta("AFC",[],[], ($AFCConsulta->suma_valor ?? 0) * -1 );
                $arrFila[55] = $AFCConsulta->suma_valor;
                array_push($Deducciones->hijos, $AFC);
                $ValDed+= $AFCConsulta->suma_valor ?? 0;

                $AFC_DATAICO["code"] = "AFC";
                $AFC_DATAICO["amount"] = ($AFCConsulta->suma_valor ?? 0) * -1;
                array_push($arrJSON_DATAICO["deductions"], $AFC_DATAICO);
            }

            $CooperativaConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","56")//
            ->first();
            if(isset($CooperativaConsulta->suma_valor)){
                $Cooperativa = new Etiqueta("Cooperativa",[],[], ($CooperativaConsulta->suma_valor ?? 0) * -1 );
                $arrFila[56] = $CooperativaConsulta->suma_valor;
                array_push($Deducciones->hijos, $Cooperativa);
                $ValDed+= $CooperativaConsulta->suma_valor ?? 0;

                $COOPERATIVA_DATAICO["code"] = "COOPERATIVA";
                $COOPERATIVA_DATAICO["amount"] = ($CooperativaConsulta->suma_valor ?? 0) * -1;
                array_push($arrJSON_DATAICO["deductions"], $COOPERATIVA_DATAICO);
            }

            $EmbargoFiscalConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","57")//
            ->first();
            if(isset($EmbargoFiscalConsulta->suma_valor)){
                $EmbargoFiscal = new Etiqueta("EmbargoFiscal",[],[], ($EmbargoFiscalConsulta->suma_valor ?? 0) * -1 );
                $arrFila[57] = $EmbargoFiscalConsulta->suma_valor;
                array_push($Deducciones->hijos, $EmbargoFiscal);
                $ValDed+= $EmbargoFiscalConsulta->suma_valor ?? 0;

                $EMBARGO_FISCAL_DATAICO["code"] = "EMBARGO_FISCAL";
                $EMBARGO_FISCAL_DATAICO["amount"] = ($EmbargoFiscalConsulta->suma_valor ?? 0) * -1;
                array_push($arrJSON_DATAICO["deductions"], $EMBARGO_FISCAL_DATAICO);
            }


            $PlanComplementariosConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","58")//
            ->first();
            if(isset($PlanComplementariosConsulta->suma_valor)){
                $PlanComplementarios = new Etiqueta("PlanComplementarios",[],[], ($PlanComplementariosConsulta->suma_valor ?? 0) * -1 );
                $arrFila[58] = $PlanComplementariosConsulta->suma_valor;
                array_push($Deducciones->hijos, $PlanComplementarios);
                $ValDed+= $PlanComplementariosConsulta->suma_valor ?? 0;

                $PLANES_COMPLEMENTARIOS_DATAICO["code"] = "PLANES_COMPLEMENTARIOS";
                $PLANES_COMPLEMENTARIOS_DATAICO["amount"] = ($PlanComplementariosConsulta->suma_valor ?? 0) * -1;
                array_push($arrJSON_DATAICO["deductions"], $PLANES_COMPLEMENTARIOS_DATAICO);
            }


            $EducacionConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","59")//
            ->first();
            if(isset($EducacionConsulta->suma_valor)){
                $Educacion = new Etiqueta("Educacion",[],[], ($EducacionConsulta->suma_valor ?? 0) * -1 );
                $arrFila[59] = $EducacionConsulta->suma_valor;
                array_push($Deducciones->hijos, $Educacion);
                $ValDed+= $EducacionConsulta->suma_valor ?? 0;

                $EDUCACION_DATAICO["code"] = "EDUCACION";
                $EDUCACION_DATAICO["amount"] = ($EducacionConsulta->suma_valor ?? 0) * -1;
                array_push($arrJSON_DATAICO["deductions"], $EDUCACION_DATAICO);
            }
            

            $ReintegroConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","60")//
            ->first();
            if(isset($ReintegroConsulta->suma_valor)){
                $Reintegro = new Etiqueta("Reintegro",[],[], ($ReintegroConsulta->suma_valor ?? 0) * -1 );
                $arrFila[60] = $ReintegroConsulta->suma_valor;
                array_push($Deducciones->hijos, $Reintegro);
                $ValDed+= $ReintegroConsulta->suma_valor ?? 0;

                $REINTEGRO_DATAICO["code"] = "REINTEGRO";
                $REINTEGRO_DATAICO["amount"] = ($ReintegroConsulta->suma_valor ?? 0) * -1;
                array_push($arrJSON_DATAICO["deductions"], $REINTEGRO_DATAICO);
            }
            

            $DeudaConsulta = DB::table("item_boucher_pago","ibp")
            ->select(DB::raw("sum(ibp.valor) as suma_valor"), DB::raw("sum(ibp.cantidad) as suma_cantidad"))
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("concepto as c", "c.idconcepto","=","ibp.fkConcepto")
            ->join("grupos_nomina_electronica as gne", "gne.idGrupoNominaElectronica","=","c.fkGrupoNomina")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")  
            ->where("gne.idGrupoNominaElectronica","=","61")//
            ->first();

            if(isset($DeudaConsulta->suma_valor)){
                $Deuda = new Etiqueta("Deuda",[],[], ($DeudaConsulta->suma_valor ?? 0) * -1 );
                $arrFila[61] = $DeudaConsulta->suma_valor;
                array_push($Deducciones->hijos, $Deuda);
                $ValDed+= $DeudaConsulta->suma_valor ?? 0;

                $DEUDA_DATAICO["code"] = "DEUDA";
                $DEUDA_DATAICO["amount"] = ($DeudaConsulta->suma_valor ?? 0) * -1;
                array_push($arrJSON_DATAICO["deductions"], $DEUDA_DATAICO);
            }
            
            array_push($nominaIndividial->hijos, $Deducciones);

            $ValDed = $ValDed * -1;
            
            $ValTolNE = $ValDev - $ValDed;

            $DevengadosTotal = new Etiqueta("DevengadosTotal",[],[], round($ValDev) );
            $DeduccionesTotal = new Etiqueta("DeduccionesTotal",[],[], round($ValDed) );
            $ComprobanteTotal = new Etiqueta("ComprobanteTotal",[],[], round($ValTolNE) );
      
            $arrFila["DevengadosTotal"] = round($ValDev);
            $arrFila["DeduccionesTotal"] = round($ValDed);
            $arrFila["ComprobanteTotal"] = round($ValTolNE);


            $CUNE = hash("sha384", $prefijo.$consecutivo.date("Y-m-d", strtotime($fechaReporte)).$HoraGen.$ValDev.$ValDed.$ValTolNE.$NitNE.$empleado->numeroIdentificacion.$TipoXML.$SoftwarePin);
            $CodigoQR = new Etiqueta("CodigoQR",[], [], "https://catalogo‐vpfe.dian.gov.co/document/searchqr?documentkey=".$CUNE);
            //array_push($nominaIndividial->hijos, $CodigoQR);
            array_splice($nominaIndividial->hijos, 5, 0, [$CodigoQR]);
            $InformacionGeneral = new Etiqueta("InformacionGeneral",[
                "Version" => "V1.0: Documento Soporte de Pago de Nómina Electrónica",
                "Ambiente" => $TipAmb, //1 Producción, 2 Pruebas
                "TipoXML" => $TipoXML, //102 NominaIndividual, 103 NominaIndividualDeAjuste
                "CUNE" => $CUNE,
                "EncripCUNE" => "CUNE‐SHA384",
                "FechaGen" => date("Y-m-d", strtotime($fechaReporte)),
                "HoraGen" => $HoraGen,
                "PeriodoNomina" => $arrPeriodoNomina[$empleado->periodoNomina],
                "TipoMoneda" => "COP"
            ]);
            array_splice($nominaIndividial->hijos, 6, 0, [$InformacionGeneral]);
            //array_push($nominaIndividial->hijos, $InformacionGeneral);


            array_push($nominaIndividial->hijos, $DevengadosTotal);
            array_push($nominaIndividial->hijos, $DeduccionesTotal);
            array_push($nominaIndividial->hijos, $ComprobanteTotal);
            
            array_push($arrPreExcel, $arrFila);
            array_push($arrFinalJSON_DATAICO, $arrJSON_DATAICO);
            
            $arrNominas[$empleado->numeroIdentificacion] = $nominaIndividial;
            $consecutivo++;
            $consecutivoReemplazo++;
        }
        return [
            "consecutivo" => $consecutivo,
            "consecutivoReemplazo" => $consecutivoReemplazo,
            "arrNominas" => $arrNominas,
            "arrFinalJSON_DATAICO" => $arrFinalJSON_DATAICO,
            "arrPreExcel" => $arrPreExcel,
            "arrTitulos" => $arrTitulos,
            "nies" => $nies
        ];

    }

    public function generarReporteEliminacionMasivoxEmpresa(Request $request){
        $idEmpresa = $request->idEmpresa;
        $fechaReporte = $request->fechaReporte;
        date_default_timezone_set("America/Bogota");
        $data = array();
        if ($request->hasFile('archivoCSV')) {
            
            $file = $request->file('archivoCSV')->get();
            $file = str_replace("\r","\n",$file);
            $reader = Reader::createFromString($file);
            $reader->setDelimiter(';');
            
            foreach ($reader as $index => $row) {
                if(trim($row[0]) != "" && trim($row[1]) != "" && trim($row[2]) != ""){
                    $data[$row[0]] = [
                        "CC" => $row[0],
                        "CUNE" => $row[1],
                        "NUMERO" => $row[2]
                    ];
                }
                    
            }
        }
        $empresa = DB::table("empresa","e")->where("e.idempresa","=",$idEmpresa)->first();
        $prefijo = $empresa->PrefijoNominaElectronicaEliminacion;
        $prefijoNormal = $empresa->PrefijoNominaElectronica;

        $consecutivo = $empresa->ConsecutivoNominaElectronicaEliminacion;
        $SoftwarePin = "4123412";
        $SoftwareDianId = $empresa->SoftwareDianId;
        $SoftwareTestSetId = $empresa->SoftwareTestSetId;
        $TipAmb = $empresa->TipAmbNominaElectronica; //1 Producción, 2 Pruebas
        $fechaInicioMesActual = date("Y-m-01", strtotime($fechaReporte));
        $fechaFinMesActual = date("Y-m-t", strtotime($fechaReporte));
        
        $empleadosGen = DB::table('empleado', 'e')
        ->select("e.*", "dp.*", "ti.siglaPila", "ti.cod_nomina_elec", "ti.cod_nomina_elec_dataico","p.fkEstado as estado", "p.fechaInicio as fechaInicioPeriodo"
        , "p.fechaFin as fechaFinPeriodo", "p.fkNomina as nominaPeriodo", "p.idPeriodo", "p.fkTipoCotizante as fkTipoCotizantePeriodo"
        ,"emp.fkUbicacion as ubicacionEmpresa", "n.periodo as periodoNomina",
        "emp.razonSocial as nombreEmpresa","emp.documento as nitEmpresa", "emp.digitoVerificacion as digitoVerificacionEmpresa", 
        "emp.direccion as direccionEmpresa","tc.tipo_con_nomina_elec", "tc.tipo_con_nomina_elec_dataico", "p.salario as salarioPeriodoPago", 
        "ter_entidad.razonSocial as nombreEntidad",  DB::raw("GROUP_CONCAT(ln.fechaLiquida) as fechasLiquidacion"),
        "ter_entidad_periodo.razonSocial as nombreEntidadPeriodo","p.fkCargo as fkCargoPeriodo",
        "p.fkTipoContrato as fkTipoContratoPeriodo",
        "p.esPensionado as esPensionadoPeriodo",
        "p.tipoRegimen as tipoRegimenPeriodo",
        "p.tipoRegimenPensional as tipoRegimenPensionalPeriodo",
        "p.fkUbicacionLabora as fkUbicacionLaboraPeriodo",
        "p.fkLocalidad as fkLocalidadPeriodo",
        "p.sabadoLaborable as sabadoLaborablePeriodo",
        "p.formaPago as formaPagoPeriodo",
        "p.fkEntidad as fkEntidadPeriodo",
        "p.numeroCuenta as numeroCuentaPeriodo",
        "p.tipoCuenta as tipoCuentaPeriodo",
        "p.otraFormaPago as otraFormaPagoPeriodo",
        "p.fkTipoOtroDocumento as fkTipoOtroDocumentoPeriodo",
        "p.otroDocumento as otroDocumentoPeriodo",
        "p.procedimientoRetencion as procedimientoRetencionPeriodo",
        "p.porcentajeRetencion as porcentajeRetencionPeriodo",
        "p.fkNivelArl as fkNivelArlPeriodo",
        "p.fkCentroTrabajo as fkCentroTrabajoPeriodo",
        "p.aplicaSubsidio as aplicaSubsidioPeriodo")
        ->join("datospersonales AS dp", "e.fkDatosPersonales", "=" , "dp.idDatosPersonales")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion","=","dp.fkTipoIdentificacion")
        ->join("periodo as p","p.fkEmpleado", "=","e.idempleado")
        ->join("nomina as n", "n.idNomina", "=","p.fkNomina")
        ->leftJoin('boucherpago as bp', function ($join) {
            $join->on('bp.fkEmpleado', '=', 'e.idempleado')
                ->on('bp.fkPeriodoActivo', '=', 'p.idPeriodo');                
        })
        ->join("tipocontrato as tc","idtipoContrato","=","p.fkTipoContrato","left")
        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
        ->join("empresa as emp","emp.idEmpresa", "=","n.fkEmpresa")
        ->join("tercero as ter_entidad","ter_entidad.idTercero","=","e.fkEntidad","left")
        ->join("tercero as ter_entidad_periodo","ter_entidad_periodo.idTercero","=","p.fkEntidad","left")
        ->where("n.fkEmpresa","=",$idEmpresa)
        ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9"]) //1 - Normal, 2- Retiro
        ->where("ln.fkEstado","=","5")
        ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
        //->where("dp.numeroIdentificacion","=","13865062")
        ;        
        $empleadosGen = $empleadosGen->whereIn("dp.numeroIdentificacion",array_keys($data));
                
        $empleadosGen = $empleadosGen->distinct()
        ->orderby("e.idempleado")
        ->groupBy("p.idPeriodo")
        ->get();

        $arrFinalJSON_DATAICO = array();

        foreach($empleadosGen as $empleado){
            $arrJSON_DATAICO = array();
            $empleado->fkTipoCotizante = ($empleado->fkTipoCotizantePeriodo ?? $empleado->fkTipoCotizante);
            $empleado->fkCargo = ($empleado->fkCargoPeriodo ?? $empleado->fkCargo);
            
            $empleado->esPensionado = ($empleado->esPensionadoPeriodo ?? $empleado->esPensionado);
            $empleado->tipoRegimen = ($empleado->tipoRegimenPeriodo ?? $empleado->tipoRegimen);
            $empleado->tipoRegimenPensional = ($empleado->tipoRegimenPensionalPeriodo ?? $empleado->tipoRegimenPensional);
            $empleado->fkUbicacionLabora = ($empleado->fkUbicacionLaboraPeriodo ?? $empleado->fkUbicacionLabora);
            $empleado->fkLocalidad = ($empleado->fkLocalidadPeriodo ?? $empleado->fkLocalidad);
            $empleado->sabadoLaborable = ($empleado->sabadoLaborablePeriodo ?? $empleado->sabadoLaborable);
            $empleado->formaPago = ($empleado->formaPagoPeriodo ?? $empleado->formaPago);
            $empleado->fkEntidad = ($empleado->fkEntidadPeriodo ?? $empleado->fkEntidad);
            $empleado->numeroCuenta = ($empleado->numeroCuentaPeriodo ?? $empleado->numeroCuenta);
            $empleado->tipoCuenta = ($empleado->tipoCuentaPeriodo ?? $empleado->tipoCuenta);
            $empleado->otraFormaPago = ($empleado->otraFormaPagoPeriodo ?? $empleado->otraFormaPago);
            $empleado->fkTipoOtroDocumento = ($empleado->fkTipoOtroDocumentoPeriodo ?? $empleado->fkTipoOtroDocumento);
            $empleado->otroDocumento = ($empleado->otroDocumentoPeriodo ?? $empleado->otroDocumento);
            $empleado->procedimientoRetencion = ($empleado->procedimientoRetencionPeriodo ?? $empleado->procedimientoRetencion);
            $empleado->porcentajeRetencion = ($empleado->porcentajeRetencionPeriodo ?? $empleado->porcentajeRetencion);
            $empleado->fkNivelArl = ($empleado->fkNivelArlPeriodo ?? $empleado->fkNivelArl);
            $empleado->fkCentroTrabajo = ($empleado->fkCentroTrabajoPeriodo ?? $empleado->fkCentroTrabajo);
            $empleado->aplicaSubsidio = ($empleado->aplicaSubsidioPeriodo ?? $empleado->aplicaSubsidio);
            $empleado->nombreEntidad = ($empleado->nombreEntidadPeriodo ?? $empleado->nombreEntidad);

            $arrJSON_DATAICO["software"] = [
                "pin" => $SoftwarePin,
                "test-set-id" => $SoftwareTestSetId,
                "dian-id" => $SoftwareDianId
            ];

            $arrJSON_DATAICO["env"] = ($TipAmb  == "2" ? "PRUEBAS" : "PRODUCCION");

            $arrJSON_DATAICO["issue-date"] = date("d/m/Y", strtotime($fechaReporte));
            $arrJSON_DATAICO["notes"] = [];
            $arrJSON_DATAICO["number"] = $consecutivo;
            $arrJSON_DATAICO["prefix"] = $prefijo;

            $reemplazo = $data[$empleado->numeroIdentificacion];
            $arrJSON_DATAICO["replacement-for"]["number"] = $reemplazo["NUMERO"];
            $arrJSON_DATAICO["replacement-for"]["prefix"] = $prefijoNormal;
            $arrJSON_DATAICO["replacement-for"]["cune"] = $reemplazo["CUNE"];
            $arrJSON_DATAICO["replacement-for"]["issue-date"] =  date("d/m/Y", strtotime($fechaReporte));
            array_push($arrFinalJSON_DATAICO, $arrJSON_DATAICO);
            $consecutivo++;
        }

        DB::table("empresa","e")->where("e.idempresa","=",$idEmpresa)->update([
            "ConsecutivoNominaElectronicaEliminacion" => $consecutivo
        ]);

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", generó un reporte de eliminación de nómina electrónica");

        $file = 'nomina_eliminacion_'.$idEmpresa.'-'.$fechaReporte.'-'.date("H_i_s").'.json';
        header("Content-Type: application/json");
        header("Content-Disposition: attachment; filename=".$file);
        $jsonConEntries = array("entries" => $arrFinalJSON_DATAICO);
        echo json_encode($jsonConEntries,JSON_UNESCAPED_SLASHES);
    }



    public function generarReporteReemplazoxEmpresa(Request $request){
        $idEmpresa = $request->idEmpresa;
        $fechaReporte = $request->fechaReporte;
        date_default_timezone_set("America/Bogota");
        $arrRes = $this->arreglosGenerales($fechaReporte, $idEmpresa,[
            "empleado" => $request->idEmpleado,
            "numero" => $request->numero,
            "cune" => $request->cune
        ]);
        $arrFinalJSON_DATAICO = ($arrRes["arrFinalJSON_DATAICO"][0] ?? []);

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", generó un reporte de reemplazo de nómina electrónica");

        $file = 'nomina_reemplazo_'.$idEmpresa.'-'.$fechaReporte.'-'.$request->numero.'-'.date("H_i_s").'.json';
        header("Content-Type: application/json");
        header("Content-Disposition: attachment; filename=".$file);
        echo json_encode($arrFinalJSON_DATAICO,JSON_UNESCAPED_SLASHES);
    }

    public function generarReporteReemplazoMasivoxEmpresa(Request $request){
        $idEmpresa = $request->idEmpresa;
        $fechaReporte = $request->fechaReporte;
        date_default_timezone_set("America/Bogota");
        $data = array();
        if ($request->hasFile('archivoCSV')) {
            
            $file = $request->file('archivoCSV')->get();
            $file = str_replace("\r","\n",$file);
            $reader = Reader::createFromString($file);
            $reader->setDelimiter(';');
            
            foreach ($reader as $index => $row) {
                if(trim($row[0]) != "" && trim($row[1]) != "" && trim($row[2]) != ""){
                    $data[$row[0]] = [
                        "CC" => $row[0],
                        "CUNE" => $row[1],
                        "NUMERO" => $row[2]
                    ];
                }
                    
            }
        }

        
        $arrExcel = array();
        $arrRes = $this->arreglosGenerales($fechaReporte, $idEmpresa,[
            "data" => $data
        ]);
        
        $consecutivoReemplazo = $arrRes["consecutivoReemplazo"];
        $arrFinalJSON_DATAICO = ($arrRes["arrFinalJSON_DATAICO"] ?? []);
        $arrPreExcel = $arrRes["arrPreExcel"] ?? [];
        $arrTitulos = $arrRes["arrTitulos"];
        $nies = $arrRes["nies"];

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", generó un reporte de reemplazo de nómina electrónica masivo");

        if($request->tipoReporte == "JSON"){
            DB::table("empresa","e")->where("e.idempresa","=",$idEmpresa)->update([
                "ConsecutivoNominaElectronicaReemplazo" => $consecutivoReemplazo
            ]);

            $file = 'nomina_reemplazo_'.$idEmpresa.'-'.$fechaReporte.'-'.date("H_i_s").'.json';
            header("Content-Type: application/json");
            header("Content-Disposition: attachment; filename=".$file);
            $jsonConEntries = array("entries" => $arrFinalJSON_DATAICO);
            echo json_encode($jsonConEntries,JSON_UNESCAPED_SLASHES);
        }
        else{
            foreach($arrPreExcel as $arrItem){
                $arrFilaFin = array();
                foreach($arrItem as $row => $val){
                    if(in_array($row, $arrTitulos)){
                        $arrFilaFin[array_search($row, $arrTitulos)] = $val;
                    }
                }
                array_push($arrExcel, $arrFilaFin);
            }
            foreach($nies as $row => $nie){
                if(in_array($nie->idGrupoNominaElectronica, $arrTitulos)){
                    $arrTitulos[array_search($nie->idGrupoNominaElectronica, $arrTitulos)] = $nie->codigo;
                }                
            }
            array_splice($arrExcel, 0, 0, [$arrTitulos]);
            $file = 'nomina_reemplazo_'.$idEmpresa.'-'.$fechaReporte.'-'.date("H_i_s").'.xls';
            header("Content-Type: application/vnd.ms-excel");
            header("Content-Disposition: attachment; filename=".$file);
            echo "<table>";
            foreach($arrExcel as $arrFila){
                echo "<tr>";                
                foreach($arrTitulos as $rowT => $foo){
                    if(isset($arrFila[$rowT])){
                        if($rowT == 26){
                            echo "<td>=\"".utf8_decode($arrFila[$rowT])."\"</td>";
                        }
                        else{
                            echo "<td>".utf8_decode($arrFila[$rowT])."</td>";
                        }                        
                    }
                    else{
                        echo "<td></td>";
                    }
                }
                echo "</tr>";
            }
            echo "</table>";            
        }
    }

    public function generarReportexEmpresa(Request $request){
        $idEmpresa = $request->idEmpresa;
        $fechaReporte = $request->fechaReporte;
        $send_email = ($request->send_email == "1" ? true : false);
        $cesantias_sin_pagar = ($request->cesantias_sin_pagar == "1" ? true : false);
        

        date_default_timezone_set("America/Bogota");
        $arrExcel = array();
        $arrRes = $this->arreglosGenerales($fechaReporte, $idEmpresa, [] , $send_email, $cesantias_sin_pagar);
        
        $consecutivo = $arrRes["consecutivo"];
        $arrNominas = $arrRes["arrNominas"];
        $arrFinalJSON_DATAICO = $arrRes["arrFinalJSON_DATAICO"];
        $arrPreExcel = $arrRes["arrPreExcel"];
        $arrTitulos = $arrRes["arrTitulos"];
        $nies = $arrRes["nies"];

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", generó un reporte de nómina electrónica, tipo de reporte:".$request->tipoReporte);

        if($request->tipoReporte == "ZIP"){
            DB::table("empresa","e")->where("e.idempresa","=",$idEmpresa)->update([
                "ConsecutivoNominaElectronica" => $consecutivo
            ]);             
            $public_dir=public_path();
            $file = 'nomina_electronica_'.$idEmpresa.'-'.$fechaReporte.'-'.date("H_i_s").'.xml.zip';
            $zip = new ZipArchive;
            $res = $zip->open($public_dir . '/' . $file, ZipArchive::CREATE);
            if ($res === TRUE) {
                foreach($arrNominas as $row => $arrNomina){
                    $zip->addFromString($row.".xml", "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".$arrNomina->generarXML()."\n");
                }            
                $zip->close();
            }
            $headers = array(
                'Content-Type' => 'application/octet-stream',
            );
            $filetopath=$public_dir.'/'.$file;

            if(file_exists($filetopath)){
                return response()->download($filetopath,$file,$headers);
            }
            else{
                return response()->json([
                    "success" => false,
                    "path" => public_path($file)
                ]);
            }
        }
        else if($request->tipoReporte == "JSON"){
            DB::table("empresa","e")->where("e.idempresa","=",$idEmpresa)->update([
                "ConsecutivoNominaElectronica" => $consecutivo
            ]);
            $file = 'nomina_electronica_'.$idEmpresa.'-'.$fechaReporte.'-'.date("H_i_s").'.json';
            header("Content-Type: application/json");
            header("Content-Disposition: attachment; filename=".$file);
            $jsonConEntries = array("entries" => $arrFinalJSON_DATAICO);
            echo json_encode($jsonConEntries,JSON_UNESCAPED_SLASHES);
        }
        else{
            foreach($arrPreExcel as $arrItem){
                $arrFilaFin = array();
                foreach($arrItem as $row => $val){
                    if(in_array($row, $arrTitulos)){
                        $arrFilaFin[array_search($row, $arrTitulos)] = $val;
                    }
                }
                array_push($arrExcel, $arrFilaFin);
            }
            foreach($nies as $row => $nie){
                if(in_array($nie->idGrupoNominaElectronica, $arrTitulos)){
                    $arrTitulos[array_search($nie->idGrupoNominaElectronica, $arrTitulos)] = $nie->codigo;
                }                
            }
            array_splice($arrExcel, 0, 0, [$arrTitulos]);
            $file = 'nomina_electronica_'.$idEmpresa.'-'.$fechaReporte.'-'.date("H_i_s").'.xls';
            header("Content-Type: application/vnd.ms-excel");
            header("Content-Disposition: attachment; filename=".$file);
            echo "<table>";
            foreach($arrExcel as $arrFila){
                echo "<tr>";                
                foreach($arrTitulos as $rowT => $foo){
                    if(isset($arrFila[$rowT])){
                        if($rowT == 26){
                            echo "<td>=\"".utf8_decode($arrFila[$rowT])."\"</td>";
                        }
                        else{
                            echo "<td>".utf8_decode($arrFila[$rowT])."</td>";
                        }                        
                    }
                    else{
                        echo "<td></td>";
                    }
                }
                echo "</tr>";
            }
            echo "</table>";            
        }

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
class Etiqueta{
    public $nombre, $hijos, $atributos, $valor;
    public function __construct($nombre,$atributos = array(), $hijos = array(), $valor=""){
        $this->nombre = $nombre;
        $this->hijos = $hijos;
        $this->atributos = $atributos;
        $this->valor = $valor;
    }


    public function generarXML(){
        if($this->valor == ""){
            $texto = '<'.$this->nombre;
            foreach($this->atributos as $id => $val){
                $texto.= "\n ".$id.'="'.$val.'"';
            }
           
            if(sizeof($this->hijos) > 0){
                $texto.='>';
                foreach($this->hijos as $etiHija){
                    
                    $texto.=$etiHija->generarXML();
                }
                $texto.="</".$this->nombre.">";
                
            }
            else{
                $texto.="/>\n";
            }
            return $texto;
        }else{
            return "<".$this->nombre.">".$this->valor."</".$this->nombre.">\n";
        }
        
    }
    
}