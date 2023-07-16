<?php
namespace App\Http\Controllers;
use stdClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use League\Csv\Writer;
use League\Csv\Reader;
use SplTempFileObject;          
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use DateTime;
use DateInterval;
use Dompdf\Options;
use Exception;
use Illuminate\Support\Facades\Log;

class ReportesNominaController extends Controller
{
    private $rutaBaseImagenes = "/home/wwnovu/public_html/"; 
    //private $rutaBaseImagenes = "G:/Trabajo/Gesath/web/gesathWeb/public_html/"; 
    
    public function verFormCertLaboral(){
        $dataUsu = UsuarioController::dataAdminLogueado();
        $empresas = DB::table("empresa", "e");
        if(isset($dataUsu) && $dataUsu->fkRol == 2){            
            $empresas = $empresas->whereIn("idempresa", $dataUsu->empresaUsuario);
        }
        $empresas = $empresas->orderBy("razonSocial")->get();
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu de 'Certificaciones laborales'");
        return view('/reportes.certificadoLab',["empresas" => $empresas, "dataUsu" => $dataUsu]);
    }

    public function certLaboral(Request $req) {
        setlocale(LC_ALL, "es_ES", 'Spanish_Spain', 'Spanish');
        $fechaCarta = ucwords(iconv('ISO-8859-2', 'UTF-8', strftime("%A, %d de %B de %Y", strtotime(date('Y-m-d')))));

        $empleado = DB::table("empleado", "e")
        ->select(
            "c.nombreCargo",
            "c2.nombreCargo as nombreCargoPeriodo",
            "dp.*",
            "e.*",
            "ti.nombre as tipoidentificacion",
            "n.nombre as nombreNomina",
            "emp.razonSocial as nombreEmpresa", DB::raw('CONCAT(emp.documento,"-",emp.digitoVerificacion) as nitEmpresa'), 
            "emp.telefonoFijo as telefonoEmpresa",
            "emp.email1 as correoEmpresa",
            "n.nombre as nombreNomina",
            DB::RAW('CONCAT_WS(" ",dp.primerApellido, dp.segundoApellido, dp.primerNombre, dp.segundoNombre) as nombreCompleto'),
            "emp.razonSocial as nombreEmpresa",
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
        ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales","left")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion","left")
        ->join("periodo as p","p.fkEmpleado", "=","e.idempleado","left")
        ->join("nomina as n","n.idNomina", "=","p.fkNomina","left")
        ->join("empresa as emp","emp.idempresa", "=","n.fkEmpresa","left")
        ->join("cargo as c","c.idCargo", "=","e.fkCargo","left")
        ->join("cargo as c2","c2.idCargo", "=","p.fkCargo","left")
        ->where("e.idempleado","=",$req->idEmpleado)
        ->where("p.idPeriodo","=",$req->idPeriodo)
        ->first();
        $empleado->nombreCargo = ($empleado->nombreCargoPeriodo ?? ($empleado->nombreCargo ?? ""));
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


        $contrato = DB::table("contrato","con")
        ->select("tc.nombre as tipoContrato")        
        ->join("tipocontrato as tc","tc.idtipoContrato","=","con.fkTipoContrato")
        ->where("con.fkEmpleado","=",$req->idEmpleado)     
        ->where("con.fkPeriodoActivo","=",$req->idPeriodo) 
        ->orderBy("idcontrato","desc")
        ->first();

        $arrDatos =  (array) $empleado;
        
        $periodos = DB::table("periodo")
        ->select(
               "periodo.*",
                "cargo.nombreCargo",
                "tipocontrato.nombre as nombreTipoContrato",
                "n.nombre as nombreNomina", 
                "n.fkEmpresa",
                "u.nombre as ciudadEmpresa",                
                "emp.razonSocial as nombreEmpresa", 
                DB::raw('CONCAT(emp.documento,"-",emp.digitoVerificacion) as nitEmpresa'), 
                "emp.telefonoFijo as telefonoEmpresa",
                "emp.email1 as correoEmpresa"
                )
        ->leftJoin("cargo","cargo.idCargo","=","periodo.fkCargo")
        ->leftJoin("tipocontrato","tipocontrato.idtipoContrato","=","periodo.fkTipoContrato")
        ->leftJoin("nomina as n", "n.idNomina","=","periodo.fkNomina")
        ->leftJoin("empresa as emp","emp.idempresa", "=","n.fkEmpresa")
        ->join("ubicacion as u","u.idubicacion", "=","emp.fkUbicacion","left")
        ->where("periodo.fkEmpleado","=",$req->idEmpleado)
        ->orderBy("n.fkEmpresa","desc")
        ->orderBy("idPeriodo","asc")        
        ->get();
        
        $arrPeriodosFinales = array();
        $arrContratos = array();

        foreach($periodos as $periodo){
            $arrPeriodosFinales[$periodo->fkEmpresa] = $arrPeriodosFinales[$periodo->fkEmpresa] ?? array();
            $arrContratos[$periodo->fkEmpresa] = $arrContratos[$periodo->fkEmpresa] ?? array();
            array_push($arrContratos[$periodo->fkEmpresa], $periodo);
            
            $arrPeriodosFinales[$periodo->fkEmpresa] = $periodo;




        }

        //dd($arrContratos);
        
        $html='
        <html>
        <body>
            <style>
            *{
                -webkit-hyphens: auto;
                -ms-hyphens: auto;
                hyphens: auto;
                font-family: sans-serif;                
            }
            td{
                text-align: left;
                font-size: 10px;
            }
            th{
                text-align: left;
                font-size: 10px;
            }
            .liquida td, .liquida th{
                font-size:11px;
            }
            
            @page { 
                margin: 0in;
                position: absolute;
            }
            .page {
                top: 2.25cm;
                right: 2cm;
                bottom: 2cm;
                left: 2cm;
                position: absolute;
                z-index: -1000;
                min-width: 18cm;
                min-height: 11.7in;
                
            }
            .page_break { 
                page-break-before: always; 
            }
        
            </style>
            ';
        foreach($arrPeriodosFinales as $idEmpresa => $periodo){

            $mensaje = DB::table("mensaje")->where("tipo","=", 6)
            ->where("fkEmpresa", "=",$periodo->fkEmpresa)
            ->first();
            if(!isset($mensaje)){
                $mensaje = DB::table("mensaje")->where("idMensaje","=", 6)->first();
            }
             
            if(isset($periodo->nombreEmpresa)){
                $arrDatos["nombreEmpresa"] = $periodo->nombreEmpresa;
            }

            if(isset($periodo->nitEmpresa)){
                $arrDatos["nitEmpresa"] = $periodo->nitEmpresa;
            }

            if(isset($periodo->telefonoEmpresa)){
                $arrDatos["telefonoEmpresa"] = $periodo->telefonoEmpresa;
            }
            
            if(isset($periodo->correoEmpresa)){
                $arrDatos["correoEmpresa"] = $periodo->correoEmpresa;
            }

            if(isset($periodo->nombreNomina)){
                $arrDatos["nombreNomina"] = $periodo->nombreNomina;
            }
            
            if(isset($periodo->nombreCargo)){
                $arrDatos["nombreCargo"] = $periodo->nombreCargo;
            }
            else{
                $arrDatos["nombreCargo"] = $empleado->nombreCargo;
            }

            if(isset($periodo->nombreTipoContrato)){
                $arrDatos["tipoContrato"] = $periodo->nombreTipoContrato;
            }
            else{
                $arrDatos["tipoContrato"] = $contrato->tipoContrato;
            }

            $arrDatos["fechaIngreso"] = $periodo->fechaInicio;

            if(isset($periodo->fechaFin)){
                $arrDatos["fechaRetiro"] = $periodo->fechaFin;
            }
            else{
                $arrDatos["fechaRetiro"] = "Actual";
            }

            $conceptoSalario = DB::table("conceptofijo")
            ->where("fkEmpleado","=",$empleado->idempleado)
            ->where("fkPeriodoActivo","=",$periodo->idPeriodo)
            ->whereIn("fkConcepto",[1,2,53,54,154])
            ->first();

            if(isset($periodo->salario)){
                $arrDatos["salario"] = "$".number_format($periodo->salario, 0, ",", ".");
                $arrDatos["salarioLetras"] = $this->convertir(intval($periodo->salario));
            }
            else{                
                $arrDatos["salario"] = "$".number_format($conceptoSalario->valor, 0, ",", ".");
                $arrDatos["salarioLetras"] = $this->convertir(intval($conceptoSalario->valor));
            }
            $arrDatos["fechaActual"] = $fechaCarta;


            $conceptosFijos = DB::table("conceptofijo","cf")
            ->select("cf.*","c.nombre as nombreConcepto")
            ->join("concepto as c", "c.idconcepto","=","cf.fkConcepto")
            ->where("cf.fkEmpleado","=",$empleado->idempleado)
            ->where("cf.fkPeriodoActivo","=",$periodo->idPeriodo)
            ->whereNotIn("cf.fkConcepto",[1,2,53,54,154])
            ->get();
            

            $arrDataConceptosFijos = array();
            $arrDataConLetraConceptosFijos = array();
            foreach($conceptosFijos as $conceptoFijo){
                array_push($arrDataConceptosFijos, $conceptoFijo->nombreConcepto." por un valor de $".number_format($conceptoFijo->valor, 0, ",", "."));
                array_push($arrDataConLetraConceptosFijos, $conceptoFijo->nombreConcepto." por un valor de ".$this->convertir(intval($conceptoFijo->valor))." pesos");
            }            
            $arrDatos["conceptosFijos"] = implode(", ",$arrDataConceptosFijos);
            $arrDatos["conceptosFijosLetras"] = implode(", ",$arrDataConLetraConceptosFijos);


            $arrDatos["contratos"] = '<table border="1" style="border-collapse: collapse;">
            <thead>
                <tr>
                    <th width="60" style="padding: 0 10px;">Fecha Inicio</th>
                    <th width="60" style="padding: 0 10px;">Fecha Fin</th>
                    <th style="padding: 0 10px;">N&oacute;mina</th>
                    <th style="padding: 0 10px;">Cargo</th>
                    <th style="padding: 0 10px;">Tipo Contrato</th>
                    <th style="padding: 0 10px;">Salario</th>
                </tr>
            </thead>
            <tbody>';
            foreach($arrContratos[$idEmpresa] as $contrato){
                $arrDatos["contratos"].= "<tr>
                    <td style='padding: 0 10px;'>".$contrato->fechaInicio."</td>
                    <td style='padding: 0 10px;'>".($contrato->fechaFin ?? "Actual")."</td>
                    <td style='padding: 0 10px;'>".$contrato->nombreNomina."</td>
                    <td style='padding: 0 10px;'>".($contrato->nombreCargo ?? $arrDatos["nombreCargo"])."</td>
                    <td style='padding: 0 10px;'>".($contrato->nombreTipoContrato ?? $arrDatos["tipoContrato"])."</td>
                    <td style='padding: 0 10px;'>$".number_format(($contrato->salario ?? $conceptoSalario->valor), 0, ",", ".")."</td>
                </tr>";
            }

            $arrDatos["contratos"].= "</tbody></table>";



            
            $mensaje->html = $this->reemplazarCampos($mensaje->html, $arrDatos);
            $mensaje->asunto = $this->reemplazarCampos($mensaje->asunto, $arrDatos);
            $mensaje->html = str_replace("https://novuss.co","/home/wwnovu/public_html",$mensaje->html);

            $html.='<div class="page">'.$mensaje->html.'</div><div class="page_break"></div>';
        }
        $html = substr($html, 0, strlen($html) - 30);
        $html.='
            </body>
        </html>
        ';

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", generó una 'Certificacion laboral'");


        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);

        //$dompdf = new Dompdf();
        

        $dompdf->loadHtml($html ,'UTF-8');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        // $dompdf->get_canvas()->get_cpdf()->setEncryption($empleado->numeroIdentificacion, $empleado->numeroIdentificacion);
        $dompdf->stream("Certificación Laboral", array('compress' => 1, 'Attachment' => 0));

    }

    private function reemplazarCampos($mensaje, $datos){
        $adminController = new AdminCorreosController();
        $arrayCampos = $adminController->arrayCampos;
        foreach($arrayCampos as $id => $campo){
            if(isset($datos[$id])){
                $mensaje = str_replace($campo, $datos[$id], $mensaje);
            }
            else{
                $mensaje = str_replace($campo, "", $mensaje);
            }
        }
        return $mensaje;
        
    }


    public function reporteNominaHorizontalIndex(){
        $dataUsu = UsuarioController::dataAdminLogueado();
        $empresas = DB::table("empresa", "e");
        if(isset($dataUsu) && $dataUsu->fkRol == 2){            
            $empresas = $empresas->whereIn("idempresa", $dataUsu->empresaUsuario);
        }
        $empresas = $empresas->orderBy("razonSocial")->get();
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu de 'Reporte nómina horizontal'");

        return view('/reportes.nominaHorizontal',["empresas" => $empresas, "dataUsu" => $dataUsu]);
    }
    public function reporteNominaAcumuladoIndex(){
        $dataUsu = UsuarioController::dataAdminLogueado();
        $empresas = DB::table("empresa", "e");
        if(isset($dataUsu) && $dataUsu->fkRol == 2){            
            $empresas = $empresas->whereIn("idempresa", $dataUsu->empresaUsuario);
        }
        $empresas = $empresas->orderBy("razonSocial")->get();
        $conceptos = DB::table("concepto","c")->orderBy("c.nombre")->get();
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu de 'Reporte nómina acumulado'");
        return view('/reportes.nominaAcumulado',["empresas" => $empresas, "conceptos" => $conceptos, "dataUsu" => $dataUsu]);
    }
    
    public function documentoNominaHorizontalWO($idLiquidacionNomina){

        $nominas = DB::table("item_boucher_pago", "ibp")
        ->select("c.idconcepto",
        "c.nombre",
        "ln.fechaLiquida",
        "ln.fechaInicio",
        "ln.fechaFin",
        "e.idempleado", 
        "dp.primerNombre",
        "dp.segundoNombre", 
        "dp.primerApellido",
        "dp.segundoApellido",
        "ti.nombre as tipoidentificacion", 
        "dp.numeroIdentificacion",
        "bp.diasTrabajados",
        "ibp.valor",
        "ibp.cantidad",
        "ccfijo.valor as valorSalario",
        "cargo.nombreCargo",
        "cargo2.nombreCargo as nombreCargoPeriodo",
        "n.nombre as nombreNomina", 
        "n.id_uni_nomina", 
        "emp.razonSocial as nombreEmpresa",
        "bp.fkPeriodoActivo",
        "cc.nombre as nombre_centro_costo",
        "cwo.id as id_cwo",
        "cwo.nombre as nombre_cwo",
        "cwo.unidad_medida as unidad_medida_cwo")        
        ->join("concepto as c","c.idconcepto", "=","ibp.fkConcepto")
        ->join("conceptos_wo as cwo","cwo.id", "=","c.fk_concepto_wo")
        ->join("boucherpago as bp","bp.idBoucherPago","=", "ibp.fkBoucherPago")
        ->join("periodo as p","p.idPeriodo","=", "bp.fkPeriodoActivo")
        ->leftJoin("empleado_centrocosto as ec","ec.fkPeriodoActivo","=","p.idPeriodo")
        ->leftJoin("centrocosto as cc","cc.idcentroCosto","=", "ec.fkCentroCosto")
        ->join("liquidacionnomina as ln", "ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
        ->join("empleado as e","e.idempleado", "=", "bp.fkEmpleado")
        ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion")
        ->leftJoin('conceptofijo as ccfijo', function ($join) {
            $join->on('ccfijo.fkEmpleado', '=', 'e.idempleado')
                ->on('ccfijo.fkPeriodoActivo', '=', 'bp.fkPeriodoActivo')
                ->whereIn('ccfijo.fkConcepto', ["1","2","53","54","154"]);
        })
        ->join("cargo","cargo.idCargo","=","e.fkCargo" ,"left")
        ->join("cargo as cargo2","cargo2.idCargo","=","p.fkCargo" ,"left")
        ->join("nomina as n","n.idNomina", "=","p.fkNomina")
        ->join("empresa as emp","emp.idempresa", "=","n.fkEmpresa")
        ->where("ln.idLiquidacionNomina","=",$idLiquidacionNomina)        
        ->orderBy("dp.primerApellido")
        ->orderBy("dp.segundoApellido")
        ->orderBy("dp.primerNombre")
        ->orderBy("dp.segundoNombre")
        ->orderBy("e.idempleado")
        ->orderBy("c.idconcepto")
        ->get();

        

        $pagosFueraNomina = DB::table("item_boucher_pago_fuera_nomina", "ibp")
        ->select("c.idconcepto","c.nombre","ln.fechaLiquida","ln.fechaInicio","ln.fechaFin","e.idempleado", 
        "dp.primerNombre","dp.segundoNombre", 
        "dp.primerApellido","dp.segundoApellido","ti.nombre as tipoidentificacion", 
        "dp.numeroIdentificacion", "bp.diasTrabajados", "ibp.valor","ibp.cantidad","ccfijo.valor as valorSalario", 
        "cargo.nombreCargo","bp.fkPeriodoActivo", "emp.razonSocial as nombreEmpresa", "n.nombre as nombreNomina", 
        "n.id_uni_nomina", "emp.razonSocial as nombreEmpresa",
        "bp.fkPeriodoActivo",
        "cc.nombre as nombre_centro_costo",
        "cwo.nombre as nombre_cwo",
        "cwo.unidad_medida as unidad_medida_cwo","cwo.id as id_cwo")
        ->join("concepto as c","c.idconcepto", "=","ibp.fkConcepto")
        ->join("conceptos_wo as cwo","cwo.id", "=","c.fk_concepto_wo")
        ->join("boucherpago as bp","bp.idBoucherPago","=", "ibp.fkBoucherPago")
        ->join("periodo as p","p.idPeriodo","=", "bp.fkPeriodoActivo")
        ->leftJoin("empleado_centrocosto as ec","ec.fkPeriodoActivo","=","p.idPeriodo")
        ->leftJoin("centrocosto as cc","cc.idcentroCosto","=", "ec.fkCentroCosto")
        ->join("liquidacionnomina as ln", "ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
        ->join("empleado as e","e.idempleado", "=", "bp.fkEmpleado")
        ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion")
        ->leftJoin('conceptofijo as ccfijo', function ($join) {
            $join->on('ccfijo.fkEmpleado', '=', 'e.idempleado')
                ->on('ccfijo.fkPeriodoActivo', '=', 'bp.fkPeriodoActivo')
                ->whereIn('ccfijo.fkConcepto', ["1","2","53","54","154"]);
        })
        ->join("cargo","cargo.idCargo","=","e.fkCargo" ,"left")
        ->join("cargo as cargo2","cargo2.idCargo","=","p.fkCargo" ,"left")
        ->join("nomina as n","n.idNomina", "=","p.fkNomina")
        ->join("empresa as emp","emp.idempresa", "=","n.fkEmpresa")
        ->where("ln.idLiquidacionNomina","=",$idLiquidacionNomina)
        ->orderBy("dp.primerApellido")
        ->orderBy("dp.segundoApellido")
        ->orderBy("dp.primerNombre")
        ->orderBy("dp.segundoNombre")
        ->orderBy("e.idempleado")
        ->orderBy("c.idconcepto")
        ->get();
        
        
        $liquidacionNomina = DB::table('liquidacionnomina')->where("idLiquidacionNomina", "=", $idLiquidacionNomina)->first();
        

        $arrMeses = [
            1 => "ENE",
            2 => "FEB",
            3 => "MAR",
            4 => "ABR",
            5 => "MAY",
            6 => "JUN",
            7 => "JUL",
            8 => "AGO",
            9 => "SEP",
            10 => "OCT",
            11 => "NOV",
            12 => "DIC"
        ];

        $arrMesesLargo = [
            1 => "enero",
            2 => "febrero",
            3 => "marzo",
            4 => "abril",
            5 => "mayo",
            6 => "junio",
            7 => "julio",
            8 => "agosto",
            9 => "septiembre",
            10 => "octubre",
            11 => "noviembre",
            12 => "diciembre"
        ];
       
        $matrizReporte = array();
        
        foreach($nominas as $nomina){
            
            $arrLinea = array();
            $mes_int = intval(substr($nomina->fechaInicio, 5, 2));
            $anio_int = intval(substr($nomina->fechaInicio, 2, 2));
            $dia_int = intval(substr($nomina->fechaInicio, 8, 2));
            
            
            $suf = ($dia_int == 1 ? "001" : "002");
            $nota = ($dia_int == 1 ? "Nomina de la 1ª quincena de ".$arrMesesLargo[$mes_int]." ".date("Y", strtotime($nomina->fechaInicio)) : "Nomina de la 2ª quincena de ".$arrMesesLargo[$mes_int]." ".date("Y", strtotime($nomina->fechaInicio)));

            if(substr($nomina->fechaInicio, 8, 2) == "01"){
                $nomina->fechaLiquida = substr($nomina->fechaInicio, 0, 8)."15";
            }
            else{
                $nomina->fechaLiquida = substr($nomina->fechaInicio, 0, 8)."30";
            }

            
            
            $arrLinea[$nomina->idempleado]["Detalle: Empresa"] = $nomina->nombreEmpresa;
            $arrLinea[$nomina->idempleado]["Detalle: Tipo Documento"] = "NO";
            $arrLinea[$nomina->idempleado]["Detalle: Prefijo"] = $arrMeses[$mes_int];
            $arrLinea[$nomina->idempleado]["Detalle: Documento Número"] = $anio_int.$suf;
            $arrLinea[$nomina->idempleado]["Detalle: Concepto"] = $nomina->nombre_cwo;
            $arrLinea[$nomina->idempleado]["Detalle: Tercero"] = $nomina->numeroIdentificacion;
            $arrLinea[$nomina->idempleado]["Detalle: Cantidad"] = ($nomina->unidad_medida_cwo != "Und." ? round($nomina->cantidad,2) : "1");
            $arrLinea[$nomina->idempleado]["Detalle: Cantidad"] = str_replace(".", ",", $arrLinea[$nomina->idempleado]["Detalle: Cantidad"]);
            $arrLinea[$nomina->idempleado]["Detalle: Unidad de Medida"] = $nomina->unidad_medida_cwo;
            $arrLinea[$nomina->idempleado]["Detalle: Centro de Costo"] = $nomina->nombre_centro_costo ?? "";
            $arrLinea[$nomina->idempleado]["Detalle: Valor"] = ($nomina->unidad_medida_cwo == "Und." ? round(($nomina->valor > 0 ? $nomina->valor : $nomina->valor*-1)) : "0");
            $arrLinea[$nomina->idempleado]["Detalle: Valor"] = str_replace(".", ",", $arrLinea[$nomina->idempleado]["Detalle: Valor"]);
            $arrLinea[$nomina->idempleado]["Detalle: Nota"] = $nota;
            $arrLinea[$nomina->idempleado]["Detalle: Vencimiento"] = date("d/m/Y", strtotime($nomina->fechaFin));

            array_push($matrizReporte,$arrLinea);
        }
        
        $matrizReporteFuera = array();
        
        foreach($pagosFueraNomina as $nomina){
            $arrLinea = array();
            $mes_int = intval(substr($nomina->fechaInicio, 5, 2));
            $anio_int = intval(substr($nomina->fechaInicio, 2, 2));
            $dia_int = intval(substr($nomina->fechaInicio, 8, 2));

            $suf = ($dia_int == 1 ? "001" : "002");
            $nota = ($dia_int == 1 ? "Nomina de la 1ª quincena de ".$arrMesesLargo[$mes_int]." ".date("Y", strtotime($nomina->fechaInicio)) : "Nomina de la 2ª quincena de ".$arrMesesLargo[$mes_int]." ".date("Y", strtotime($nomina->fechaInicio)));

            if(substr($nomina->fechaInicio, 8, 2) == "01"){
                $nomina->fechaLiquida = substr($nomina->fechaInicio, 0, 7)."15";
            }
            else{
                $nomina->fechaLiquida = substr($nomina->fechaInicio, 0, 7)."30";
            }
            
            
            $arrLinea[$nomina->idempleado]["Detalle: Empresa"] = $nomina->nombreEmpresa;
            $arrLinea[$nomina->idempleado]["Detalle: Tipo Documento"] = "NO";
            $arrLinea[$nomina->idempleado]["Detalle: Prefijo"] = $arrMeses[$mes_int];
            $arrLinea[$nomina->idempleado]["Detalle: Documento Número"] = $anio_int.$suf;
              $arrLinea[$nomina->idempleado]["Detalle: Concepto"] = $nomina->nombre_cwo;
            $arrLinea[$nomina->idempleado]["Detalle: Tercero"] = $nomina->numeroIdentificacion;
            $arrLinea[$nomina->idempleado]["Detalle: Cantidad"] = ($nomina->unidad_medida_cwo != "Und." ? round($nomina->cantidad,2) : "1");
            $arrLinea[$nomina->idempleado]["Detalle: Cantidad"] = str_replace(".", ",", $arrLinea[$nomina->idempleado]["Detalle: Cantidad"]);
            $arrLinea[$nomina->idempleado]["Detalle: Unidad de Medida"] = $nomina->unidad_medida_cwo;
            $arrLinea[$nomina->idempleado]["Detalle: Centro de Costo"] = $nomina->nombre_centro_costo ?? "";
            $arrLinea[$nomina->idempleado]["Detalle: Valor"] = ($nomina->unidad_medida_cwo == "Und." ? round(($nomina->valor > 0 ? $nomina->valor : $nomina->valor*-1)) : "0");
            $arrLinea[$nomina->idempleado]["Detalle: Valor"] = str_replace(".", ",", $arrLinea[$nomina->idempleado]["Detalle: Valor"]);
            $arrLinea[$nomina->idempleado]["Detalle: Nota"] = $nota;
            $arrLinea[$nomina->idempleado]["Detalle: Vencimiento"] = date("d/m/Y", strtotime($nomina->fechaFin));
            array_push($matrizReporte,$arrLinea);
        }


        
      

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", generó reporte nómina world office para la liquidación:".$idLiquidacionNomina);

        $file = "ReporteNominaWO_".$idLiquidacionNomina.".xls";
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=".$file);
        echo "<table>";
        
        $mostrarTitulo = true;
        foreach($matrizReporte as $data){
            foreach($data as $datos){
                if($mostrarTitulo){
                    $mostrarTitulo = false;
                    echo "<tr>";
                    foreach($datos as $row => $name){
                        echo "<td>".utf8_decode($row)."</td>";
                    }
                    echo "</tr>";
                }
                echo "<tr>";
                foreach($datos as $row => $name){
                    echo "<td>".utf8_decode($name)."</td>";
                }
                echo "</tr>";
            }            
        }
        echo "</table>";

    }
    public function documentoNominaHorizontal($idLiquidacionNomina){

        $nominas = DB::table("item_boucher_pago", "ibp")
        ->select("c.idconcepto",
        "c.nombre",
        "ln.fechaLiquida",
        "e.idempleado", 
        "dp.primerNombre",
        "dp.segundoNombre", 
        "dp.primerApellido",
        "dp.segundoApellido",
        "ti.nombre as tipoidentificacion", 
        "dp.numeroIdentificacion",
        "bp.diasTrabajados",
        "ibp.valor",
        "ln.fechaLiquida",
        "ccfijo.valor as valorSalario",
        "cargo.nombreCargo",
        "cargo2.nombreCargo as nombreCargoPeriodo",
        "n.nombre as nombreNomina", 
        "n.id_uni_nomina", 
        "emp.razonSocial as nombreEmpresa",
        "bp.fkPeriodoActivo")
        
        ->join("concepto as c","c.idconcepto", "=","ibp.fkConcepto")
        ->join("boucherpago as bp","bp.idBoucherPago","=", "ibp.fkBoucherPago")
        ->join("periodo as p","p.idPeriodo","=", "bp.fkPeriodoActivo")
        ->join("liquidacionnomina as ln", "ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
        ->join("empleado as e","e.idempleado", "=", "bp.fkEmpleado")
        ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion")
        ->leftJoin('conceptofijo as ccfijo', function ($join) {
            $join->on('ccfijo.fkEmpleado', '=', 'e.idempleado')
                ->on('ccfijo.fkPeriodoActivo', '=', 'bp.fkPeriodoActivo')
                ->whereIn('ccfijo.fkConcepto', ["1","2","53","54","154"]);
        })
        ->join("cargo","cargo.idCargo","=","e.fkCargo" ,"left")
        ->join("cargo as cargo2","cargo2.idCargo","=","p.fkCargo" ,"left")
        ->join("nomina as n","n.idNomina", "=","p.fkNomina")
        ->join("empresa as emp","emp.idempresa", "=","n.fkEmpresa")
        ->where("ln.idLiquidacionNomina","=",$idLiquidacionNomina)        
        ->orderBy("dp.primerApellido")
        ->orderBy("dp.segundoApellido")
        ->orderBy("dp.primerNombre")
        ->orderBy("dp.segundoNombre")
        ->orderBy("e.idempleado")
        ->orderBy("c.idconcepto")
        ->get();

       

        $pagosFueraNomina = DB::table("item_boucher_pago_fuera_nomina", "ibp")
        ->select("c.idconcepto","c.nombre","ln.fechaLiquida","e.idempleado", 
        "dp.primerNombre","dp.segundoNombre", 
        "dp.primerApellido","dp.segundoApellido","ti.nombre as tipoidentificacion", 
        "dp.numeroIdentificacion", "bp.diasTrabajados", "ibp.valor", "ln.fechaLiquida","ccfijo.valor as valorSalario", 
        "cargo.nombreCargo","bp.fkPeriodoActivo", "emp.razonSocial as nombreEmpresa", "n.nombre as nombreNomina", 
        "n.id_uni_nomina")
        ->join("concepto as c","c.idconcepto", "=","ibp.fkConcepto")
        ->join("boucherpago as bp","bp.idBoucherPago","=", "ibp.fkBoucherPago")
        ->join("periodo as p","p.idPeriodo","=", "bp.fkPeriodoActivo")
        ->join("liquidacionnomina as ln", "ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
        ->join("empleado as e","e.idempleado", "=", "bp.fkEmpleado")
        ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion")
        ->leftJoin('conceptofijo as ccfijo', function ($join) {
            $join->on('ccfijo.fkEmpleado', '=', 'e.idempleado')
                ->on('ccfijo.fkPeriodoActivo', '=', 'bp.fkPeriodoActivo')
                ->whereIn('ccfijo.fkConcepto', ["1","2","53","54","154"]);
        })
        ->join("cargo","cargo.idCargo","=","e.fkCargo" ,"left")
        ->join("cargo as cargo2","cargo2.idCargo","=","p.fkCargo" ,"left")
        ->join("nomina as n","n.idNomina", "=","p.fkNomina")
        ->join("empresa as emp","emp.idempresa", "=","n.fkEmpresa")
        ->where("ln.idLiquidacionNomina","=",$idLiquidacionNomina)
        ->orderBy("dp.primerApellido")
        ->orderBy("dp.segundoApellido")
        ->orderBy("dp.primerNombre")
        ->orderBy("dp.segundoNombre")
        ->orderBy("e.idempleado")
        ->orderBy("c.idconcepto")
        ->get();
        
        
        
        /*
        $itemsBoucherPagoFueraNominaCesTras = DB::table("item_boucher_pago_fuera_nomina","ibpfn")
        ->select("ibpfn.*","c.*")
        ->join("concepto AS c","c.idconcepto","=", "ibpfn.fkConcepto")
        ->join("boucherpago as bp","bp.idBoucherPago","=","ibpfn.fkBoucherPago")
        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
        ->whereRaw("MONTH(ln.fechaLiquida)= MONTH('".$empresayLiquidacion->fechaLiquida."')")
        ->whereRaw("YEAR(ln.fechaLiquida)= YEAR('".$empresayLiquidacion->fechaLiquida."')")
        ->where("bp.fkEmpleado","=", $empleado->idempleado)
        ->where("ln.idLiquidacionNomina","<>",$empresayLiquidacion->idLiquidacionNomina)
        ->where("ln.fkTipoLiquidacion","=","11")
        ->get();
        foreach($itemsBoucherPagoFueraNominaCesTras as $itemBoucherPagoFueraNominaCesTras){
            $itemsBoucherPagoFueraNomina->push($itemBoucherPagoFueraNominaCesTras);
        }

        */


       
        $matrizReporte = array();
        
        foreach($nominas as $nomina){
            $centroCosto = DB::table("centrocosto","cc")
            ->join("empleado_centrocosto as ec","ec.fkCentroCosto","=","cc.idcentroCosto")
            ->where("ec.fkEmpleado","=",$nomina->idempleado)
            ->where("ec.fkPeriodoActivo","=",$nomina->fkPeriodoActivo)
            ->first();


            $matrizReporte[$nomina->idempleado]["Fecha Liquidacion"] = $nomina->fechaLiquida;
            $matrizReporte[$nomina->idempleado]["Empresa"] = $nomina->nombreEmpresa;
            $matrizReporte[$nomina->idempleado]["ID Nomina"] = $nomina->id_uni_nomina ?? "";
            $matrizReporte[$nomina->idempleado]["Nomina"] = $nomina->nombreNomina;
            
            $matrizReporte[$nomina->idempleado]["ID Centro Costo"] = $centroCosto->id_uni_centro ?? "";
            $matrizReporte[$nomina->idempleado]["Centro Costo"] = $centroCosto->nombre ?? "";
            

            $matrizReporte[$nomina->idempleado]["Tipo Documento"] = $nomina->tipoidentificacion;
            $matrizReporte[$nomina->idempleado]["Documento"] = $nomina->numeroIdentificacion;            
            $matrizReporte[$nomina->idempleado]["Nombre"] = ucfirst(mb_strtolower($nomina->primerApellido))." ".ucfirst(mb_strtolower($nomina->segundoApellido))." ".ucfirst(mb_strtolower($nomina->primerNombre))." ".ucfirst(mb_strtolower($nomina->segundoNombre));
            $matrizReporte[$nomina->idempleado]["Cargo"] = ($nomina->nombreCargoPeriodo ?? $nomina->nombreCargo);
            
            $matrizReporte[$nomina->idempleado]["Sueldo"] = intval($nomina->valorSalario);
            $matrizReporte[$nomina->idempleado]["Dias"] = $nomina->diasTrabajados;
            
            
            $matrizReporte[$nomina->idempleado][$nomina->nombre] = $nomina->valor;

        }
        
        $matrizReporteFuera = array();
        
        foreach($pagosFueraNomina as $nomina){
            if(!isset( $matrizReporte[$nomina->idempleado])){
                $centroCosto = DB::table("centrocosto","cc")
                ->join("empleado_centrocosto as ec","ec.fkCentroCosto","=","cc.idcentroCosto")
                ->where("ec.fkEmpleado","=",$nomina->idempleado)
                ->where("ec.fkPeriodoActivo","=",$nomina->fkPeriodoActivo)
                ->first();


                $matrizReporte[$nomina->idempleado]["Fecha Liquidacion"] = $nomina->fechaLiquida;
                $matrizReporte[$nomina->idempleado]["Empresa"] = $nomina->nombreEmpresa;
                $matrizReporte[$nomina->idempleado]["ID Nomina"] = $nomina->id_uni_nomina ?? "";
                $matrizReporte[$nomina->idempleado]["Nomina"] = $nomina->nombreNomina;
                
                $matrizReporte[$nomina->idempleado]["ID Centro Costo"] = $centroCosto->id_uni_centro ?? "";
                $matrizReporte[$nomina->idempleado]["Centro Costo"] = $centroCosto->nombre ?? "";
                

                $matrizReporte[$nomina->idempleado]["Tipo Documento"] = $nomina->tipoidentificacion;
                $matrizReporte[$nomina->idempleado]["Documento"] = $nomina->numeroIdentificacion;            
                $matrizReporte[$nomina->idempleado]["Nombre"] = ucfirst(mb_strtolower($nomina->primerApellido))." ".ucfirst(mb_strtolower($nomina->segundoApellido))." ".ucfirst(mb_strtolower($nomina->primerNombre))." ".ucfirst(mb_strtolower($nomina->segundoNombre));
                $matrizReporte[$nomina->idempleado]["Cargo"] = ($nomina->nombreCargoPeriodo ?? $nomina->nombreCargo);
                
                $matrizReporte[$nomina->idempleado]["Sueldo"] = intval($nomina->valorSalario);
                $matrizReporte[$nomina->idempleado]["Dias"] = $nomina->diasTrabajados;
                
                
                $matrizReporte[$nomina->idempleado][$nomina->nombre] = $nomina->valor;
                $matrizReporteFuera[$nomina->idempleado][$nomina->nombre] = $nomina->valor;
            }
            else{
                $matrizReporteFuera[$nomina->idempleado][$nomina->nombre] = $nomina->valor;
            }

            

        }

        //dd($matrizReporte, $matrizReporteFuera);

        $arrDefLinea1 = array();
        $arrDef = array();
        foreach($matrizReporte as $matriz){
            foreach($matriz as $row => $datoInt){
                if(!is_int($datoInt) || $row=="Sueldo" || $row == "Dias" || $row == "ID Centro Costo" || $row == "ID Nomina"){
                    if(!in_array($row, $arrDefLinea1)){
                        array_push($arrDefLinea1, $row);
                    }
                }              
                
            }
        
        }
       

        foreach($matrizReporte as $matriz){ 
            foreach($matriz as $row => $datoInt){
                
                if(is_int($datoInt) && $datoInt > 0 && $row != "Sueldo" && $row != "Dias" && $row != "ID Centro Costo" && $row != "ID Nomina"){
                    
                    if(!in_array($row, $arrDefLinea1)){
                        array_push($arrDefLinea1, $row);
                        
                    }
                }
                            
            }
        }

        array_push($arrDefLinea1, "TOTAL PAGOS");


        foreach($matrizReporte as $matriz){ 
            foreach($matriz as $row => $datoInt){
                
                if(is_int($datoInt) && $datoInt < 0){
                    if(!in_array($row, $arrDefLinea1)){
                        array_push($arrDefLinea1, $row);
                    }                    
                }
                
            }
            
        }
    

        array_push($arrDefLinea1, "TOTAL DESCUENTO");
        array_push($arrDefLinea1, "NETO PAGAR");


        foreach($matrizReporteFuera as $matriz){ 
            foreach($matriz as $row => $datoInt){
                
                if(is_int($datoInt) && $row != "Sueldo" && $row != "Dias" && $row != "ID Centro Costo" && $row != "ID Nomina"){
                    
                    if(!in_array($row, $arrDefLinea1)){
                        array_push($arrDefLinea1, $row);
                        
                    }
                }
                            
            }
        }



        $idDefPagos = array_search("TOTAL PAGOS", $arrDefLinea1);
        $idDefDesc = array_search("TOTAL DESCUENTO", $arrDefLinea1);
        $idDefTotal = array_search("NETO PAGAR", $arrDefLinea1);

        
        
        foreach($matrizReporte as $rowReporte => $matriz){ 
        
            $arrFila = array();      
            
            foreach($matriz as $row => $datoInt){               
                $idDef = array_search($row, $arrDefLinea1);
                if($idDef !== false){

                    if(is_int($datoInt) && $row != "Dias" && $row != "Sueldo" && $row != "ID Centro Costo" && $row != "ID Nomina"){
                        $arrFila[$idDefTotal] = (isset($arrFila[$idDefTotal]) ? $arrFila[$idDefTotal]+ $datoInt : $datoInt);    
                        
                    }

                        
                    if(is_int($datoInt) && $datoInt<=0){
                        $arrFila[$idDefDesc]= (isset($arrFila[$idDefDesc]) ? $arrFila[$idDefDesc] + ($datoInt*-1) : ($datoInt*-1)); 
                        $arrFila[$idDef] = $datoInt*-1;    
                    }
                    else if(is_int($datoInt) && $datoInt>0 && $row != "Dias" && $row != "Sueldo" && $row != "ID Centro Costo" && $row != "ID Nomina"){
                        $arrFila[$idDefPagos] = (isset($arrFila[$idDefPagos]) ? $arrFila[$idDefPagos] + $datoInt : $datoInt);
                        $arrFila[$idDef] = $datoInt;    
                    }
                    else{
                        $arrFila[$idDef] = $datoInt;    
                    }
                    foreach($matrizReporteFuera as $rowReporte2 => $matriz2){ 
                        if($rowReporte == $rowReporte2){
                            foreach($matriz2 as $row2 => $datoInt2){       
                                $idDef2 = array_search($row2, $arrDefLinea1);
                                 if($idDef2 !== false){                        
                                     if(is_int($datoInt2) && $datoInt2<=0){
                                         $arrFila[$idDef2] = $datoInt2*-1;    
                                     }
                                     else if(is_int($datoInt2)){
                                         $arrFila[$idDef2] = $datoInt2;    
                                     }
                                     else{
                                         $arrFila[$idDef2] = $datoInt2;    
                                     }     
                                 }     
                                 
                             }
                        }
                        
                    } 
                }     
                
            }
                
            if(!empty($arrFila)){
                array_push($arrDef, $arrFila);  
            }
        }
        


        
        

        
    
    
        $reporteFinal = array();
        $reporteFinal[0] = $arrDefLinea1;
        
        foreach($arrDef as $datos){
            
            foreach($arrDefLinea1 as $row => $datoLinea1){
                if(!isset($datos[$row])){
                    $datos[$row] = 0;
                }
            }
            ksort($datos);
            array_push($reporteFinal, $datos);
        }

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", generó reporte nómina horizontal para la liquidación:".$idLiquidacionNomina);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename=NominaHorizontal_'.$idLiquidacionNomina.'.csv');

        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->setDelimiter(';');
        $csv->insertAll($reporteFinal);
        $csv->setOutputBOM(Reader::BOM_UTF8);
        $csv->output('NominaHorizontal_'.$idLiquidacionNomina.'.csv');

    }
    public function documentoNominaFechas(Request $req){


        
        $nominas = DB::table("item_boucher_pago", "ibp")
        ->select("c.idconcepto","c.nombre","ln.fechaLiquida","e.idempleado",
        "dp.primerNombre","dp.segundoNombre", 
        "dp.primerApellido","dp.segundoApellido","ti.nombre as tipoidentificacion", 
        "dp.numeroIdentificacion", "bp.diasTrabajados", "ibp.valor", "bp.idBoucherPago",
        "bp.fkPeriodoActivo","p.fechaInicio as fechaIngreso","p.fechaFin as fechaRetiro", 
        "cargo.nombreCargo", "ibp.cantidad", "ibp.tipoUnidad", "cargo2.nombreCargo as nombreCargoPeriodo")
        ->join("concepto as c","c.idconcepto", "=","ibp.fkConcepto")
        ->join("boucherpago as bp","bp.idBoucherPago","=", "ibp.fkBoucherPago")
        ->join("periodo as p", "p.idPeriodo","=","bp.fkPeriodoActivo")
        ->join("liquidacionnomina as ln", "ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
        ->join("empleado as e","e.idempleado", "=", "bp.fkEmpleado")
        ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion")
        ->join("nomina as n","n.idNomina", "=","p.fkNomina")
        ->join("cargo","cargo.idCargo","=","e.fkCargo","left")
        ->join("cargo as cargo2","cargo2.idCargo","=","p.fkCargo","left")
        ->whereBetween("ln.fechaLiquida",[$req->fechaInicio, $req->fechaFin])
        ->where("n.fkEmpresa", "=", $req->empresa);
        
        if(isset($req->infoNomina)){            
            $nominas = $nominas->where("ln.fkNomina", "=", $req->infoNomina);
        }
        if(isset($req->concepto)){            
            $nominas = $nominas->whereIn("c.idconcepto", $req->concepto);
        }
        if(isset($req->idEmpleado)){
            $nominas = $nominas->where("e.idempleado", "=", $req->idEmpleado);
        }
        if(isset($req->idPeriodo)){
            $nominas = $nominas->where("p.idPeriodo", "=", $req->idPeriodo);
        }
        $nominas = $nominas->orderBy("ln.fechaLiquida")
        ->orderBy("dp.primerApellido")
        ->orderBy("dp.segundoApellido")
        ->orderBy("dp.primerNombre")
        ->orderBy("dp.segundoNombre")
        ->orderBy("e.idempleado")
        ->orderBy("c.idconcepto")
        ->get();




        $nominasFueraNomina = DB::table("item_boucher_pago_fuera_nomina", "ibp")
        ->select("c.idconcepto","c.nombre","ln.fechaLiquida","e.idempleado",
        "dp.primerNombre","dp.segundoNombre", 
        "dp.primerApellido","dp.segundoApellido","ti.nombre as tipoidentificacion", 
        "dp.numeroIdentificacion", "bp.diasTrabajados", "ibp.valor", "bp.idBoucherPago",
        "bp.fkPeriodoActivo","p.fechaInicio as fechaIngreso","p.fechaFin as fechaRetiro", 
        "cargo.nombreCargo", "ibp.cantidad", "ibp.tipoUnidad", "cargo2.nombreCargo as nombreCargoPeriodo")
        ->join("concepto as c","c.idconcepto", "=","ibp.fkConcepto")
        ->join("boucherpago as bp","bp.idBoucherPago","=", "ibp.fkBoucherPago")
        ->join("periodo as p", "p.idPeriodo","=","bp.fkPeriodoActivo")
        ->join("liquidacionnomina as ln", "ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
        ->join("empleado as e","e.idempleado", "=", "bp.fkEmpleado")
        ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion")
        ->join("nomina as n","n.idNomina", "=","p.fkNomina")
        ->join("cargo","cargo.idCargo","=","e.fkCargo","left")
        ->join("cargo as cargo2","cargo2.idCargo","=","p.fkCargo","left")
        ->whereBetween("ln.fechaLiquida",[$req->fechaInicio, $req->fechaFin])
        ->where("n.fkEmpresa", "=", $req->empresa);

        if(isset($req->infoNomina)){            
            $nominasFueraNomina = $nominasFueraNomina->where("ln.fkNomina", "=", $req->infoNomina);
        }
        if(isset($req->concepto)){            
            $nominasFueraNomina = $nominasFueraNomina->whereIn("c.idconcepto", $req->concepto);
        }
        if(isset($req->idEmpleado)){
            $nominasFueraNomina = $nominasFueraNomina->where("e.idempleado", "=", $req->idEmpleado);
        }
        if(isset($req->idPeriodo)){
            $nominasFueraNomina = $nominasFueraNomina->where("p.idPeriodo", "=", $req->idPeriodo);
        }
        $nominasFueraNomina = $nominasFueraNomina->orderBy("ln.fechaLiquida")
        ->orderBy("dp.primerApellido")
        ->orderBy("dp.segundoApellido")
        ->orderBy("dp.primerNombre")
        ->orderBy("dp.segundoNombre")
        ->orderBy("e.idempleado")
        ->orderBy("c.idconcepto")
        ->get();

        $matrizReporte = array();
        
        foreach($nominas as $nomina){

            if($req->tipoReporte=="Mensual"){

                $nomina->fechaLiquida = date("Y-m",strtotime( $nomina->fechaLiquida));
                if(isset($matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida][$nomina->idconcepto])){
                    $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida][$nomina->idconcepto] = $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida][$nomina->idconcepto] + $nomina->valor;
                    $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida." UNIDADES"][$nomina->idconcepto] = $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida." UNIDADES"][$nomina->idconcepto] + $nomina->cantidad;
                    $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida." TIPO UNIDAD"][$nomina->idconcepto] = $nomina->tipoUnidad;
                }
                else{
                    $matrizReporte[$nomina->fkPeriodoActivo]["Tipo Documento"][$nomina->idconcepto] = $nomina->tipoidentificacion;
                    $matrizReporte[$nomina->fkPeriodoActivo]["Documento"][$nomina->idconcepto] = $nomina->numeroIdentificacion;            
                    $matrizReporte[$nomina->fkPeriodoActivo]["Empleado"][$nomina->idconcepto] = $nomina->primerApellido." ".$nomina->segundoApellido." ".$nomina->primerNombre." ".$nomina->segundoNombre;
                    $matrizReporte[$nomina->fkPeriodoActivo]["Cargo"][$nomina->idconcepto] = $nomina->nombreCargo;
                    $matrizReporte[$nomina->fkPeriodoActivo]["Fecha ingreso"][$nomina->idconcepto] = $nomina->fechaIngreso;            
                    $matrizReporte[$nomina->fkPeriodoActivo]["Fecha retiro"][$nomina->idconcepto] = $nomina->fechaRetiro;     
                    $matrizReporte[$nomina->fkPeriodoActivo]["Concepto"][$nomina->idconcepto] = $nomina->nombre;
                    $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida][$nomina->idconcepto] = (isset($matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida][$nomina->idconcepto]) ? $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida][$nomina->idconcepto] + $nomina->valor : $nomina->valor);
                    $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida." UNIDADES"][$nomina->idconcepto] = $nomina->cantidad;
                    $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida." TIPO UNIDAD"][$nomina->idconcepto] = $nomina->tipoUnidad;
                }
            }
            else{
                if(isset($matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida][$nomina->idconcepto])){
                    $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida][$nomina->idconcepto] = $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida][$nomina->idconcepto] + $nomina->valor;
                    $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida." UNIDADES"][$nomina->idconcepto] = $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida." UNIDADES"][$nomina->idconcepto] + $nomina->cantidad;
                    $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida." TIPO UNIDAD"][$nomina->idconcepto] = $nomina->tipoUnidad;
                }
                else{
                    $matrizReporte[$nomina->fkPeriodoActivo]["Tipo Documento"][$nomina->idconcepto] = $nomina->tipoidentificacion;
                    $matrizReporte[$nomina->fkPeriodoActivo]["Documento"][$nomina->idconcepto] = $nomina->numeroIdentificacion;            
                    $matrizReporte[$nomina->fkPeriodoActivo]["Empleado"][$nomina->idconcepto] = $nomina->primerApellido." ".$nomina->segundoApellido." ".$nomina->primerNombre." ".$nomina->segundoNombre;
                    $matrizReporte[$nomina->fkPeriodoActivo]["Cargo"][$nomina->idconcepto] = $nomina->nombreCargo;
                    $matrizReporte[$nomina->fkPeriodoActivo]["Fecha ingreso"][$nomina->idconcepto] = $nomina->fechaIngreso;            
                    $matrizReporte[$nomina->fkPeriodoActivo]["Fecha retiro"][$nomina->idconcepto] = $nomina->fechaRetiro;     
                    $matrizReporte[$nomina->fkPeriodoActivo]["Concepto"][$nomina->idconcepto] = $nomina->nombre;
                    $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida][$nomina->idconcepto] = (isset($matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida][$nomina->idconcepto]) ? $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida][$nomina->idconcepto] + $nomina->valor : $nomina->valor);
                    $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida." UNIDADES"][$nomina->idconcepto] = $nomina->cantidad;
                    $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida." TIPO UNIDAD"][$nomina->idconcepto] = $nomina->tipoUnidad;
                }
            }

            


        }
        
        foreach($nominasFueraNomina as $nomina){

            if($req->tipoReporte=="Mensual"){

                $nomina->fechaLiquida = date("Y-m",strtotime( $nomina->fechaLiquida));
                if(isset($matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida][$nomina->idconcepto])){


                    $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida][$nomina->idconcepto] = $matrizReporte[$nomina->idempleado][$nomina->fechaLiquida][$nomina->idconcepto] + $nomina->valor;
                    $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida." UNIDADES"][$nomina->idconcepto] = $matrizReporte[$nomina->idempleado][$nomina->fechaLiquida." UNIDADES"][$nomina->idconcepto] + $nomina->cantidad;
                    $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida." TIPO UNIDAD"][$nomina->idconcepto] = $nomina->tipoUnidad;
                }
                else{
                    $matrizReporte[$nomina->fkPeriodoActivo]["Tipo Documento"][$nomina->idconcepto] = $nomina->tipoidentificacion;
                    $matrizReporte[$nomina->fkPeriodoActivo]["Documento"][$nomina->idconcepto] = $nomina->numeroIdentificacion;            
                    $matrizReporte[$nomina->fkPeriodoActivo]["Empleado"][$nomina->idconcepto] = $nomina->primerApellido." ".$nomina->segundoApellido." ".$nomina->primerNombre." ".$nomina->segundoNombre;
                    $matrizReporte[$nomina->fkPeriodoActivo]["Cargo"][$nomina->idconcepto] = $nomina->nombreCargo;
                    $matrizReporte[$nomina->fkPeriodoActivo]["Fecha ingreso"][$nomina->idconcepto] = $nomina->fechaIngreso;            
                    $matrizReporte[$nomina->fkPeriodoActivo]["Fecha retiro"][$nomina->idconcepto] = $nomina->fechaRetiro;     
                    $matrizReporte[$nomina->fkPeriodoActivo]["Concepto"][$nomina->idconcepto] = $nomina->nombre;
                    $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida][$nomina->idconcepto] = (isset($matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida][$nomina->idconcepto]) ? $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida][$nomina->idconcepto] + $nomina->valor : $nomina->valor);
                    $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida." UNIDADES"][$nomina->idconcepto] = $nomina->cantidad;
                    $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida." TIPO UNIDAD"][$nomina->idconcepto] = $nomina->tipoUnidad;
                }
            }
            else{
                $matrizReporte[$nomina->fkPeriodoActivo]["Tipo Documento"][$nomina->idconcepto] = $nomina->tipoidentificacion;
                $matrizReporte[$nomina->fkPeriodoActivo]["Documento"][$nomina->idconcepto] = $nomina->numeroIdentificacion;            
                $matrizReporte[$nomina->fkPeriodoActivo]["Empleado"][$nomina->idconcepto] = $nomina->primerApellido." ".$nomina->segundoApellido." ".$nomina->primerNombre." ".$nomina->segundoNombre;
                $matrizReporte[$nomina->fkPeriodoActivo]["Cargo"][$nomina->idconcepto] = $nomina->nombreCargo;
                $matrizReporte[$nomina->fkPeriodoActivo]["Fecha ingreso"][$nomina->idconcepto] = $nomina->fechaIngreso;    
                $matrizReporte[$nomina->fkPeriodoActivo]["Fecha retiro"][$nomina->idconcepto] = $nomina->fechaRetiro;             
                $matrizReporte[$nomina->fkPeriodoActivo]["Concepto"][$nomina->idconcepto] = $nomina->nombre;
                $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida][$nomina->idconcepto] = $nomina->valor;
                $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida." UNIDADES"][$nomina->idconcepto] = $nomina->cantidad;
                $matrizReporte[$nomina->fkPeriodoActivo][$nomina->fechaLiquida." TIPO UNIDAD"][$nomina->idconcepto] = $nomina->tipoUnidad;
            }

            


        }
        $arrDefLinea1 = array();
        $arrDef = array();
        
        foreach($matrizReporte as $matriz){ 
            $arrFila = array();
            foreach($matriz as $row => $dato){
                foreach($dato as $rowInt => $datoInt){                   
                    if(!in_array($row, $arrDefLinea1)){
                        array_push($arrDefLinea1, $row);
                    }
                    $idDef = array_search($row, $arrDefLinea1);
                    $arrFila[$idDef][$rowInt] = $datoInt;         
                }
            }
            if(!empty($arrFila)){
                array_push($arrDef, $arrFila);  
            }            
        }
    
        
        
        $reporteFinal = array();
        $reporteFinal[0] = $arrDefLinea1;
        
        foreach($arrDef as $key => $datos){
            foreach($arrDefLinea1 as $row => $datoLinea1){
                if(!isset($datos[$row])){
                    $datos[$row] = 0;
                }
            }
            ksort($datos);
            $arrDef[$key] = $datos;
            
        }
        
        foreach($arrDef as $datos){
            $arrEntrega= array();
            foreach($datos as $row => $vData){
                foreach($datos[0] as $idBouc => $datosInt){
                    if(isset($vData[$idBouc])){
                        if(is_array($vData[$idBouc])){
                            $arrEntrega[$idBouc][$row] = $vData[$idBouc];
                        }
                        else{
                            $arrEntrega[$idBouc][$row]= $vData[$idBouc];
                        }
                    }
                    else{
                        $arrEntrega[$idBouc][$row] = " ";
                    }
                    
                    ksort($arrEntrega);
                }           
            }
            foreach($arrEntrega as $porfin){
                array_push($reporteFinal, $porfin);
            }
            
            
            
        }

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", generó reporte nómina horizontal por fechas");

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename=NominaHorizontal_'.$req->empresa.'_'.$req->fechaInicio.'_'.$req->fechaFin.'.csv');

        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->setDelimiter(';');
        $csv->insertAll($reporteFinal);
        $csv->setOutputBOM(Reader::BOM_UTF8);
        $csv->output('NominaHorizontal_'.$req->empresa.'_'.$req->fechaInicio.'_'.$req->fechaFin.'.csv');
    }
    public function documentoNominaHorizontalFechas(Request $req){


        if(substr(date("Y-m-t", strtotime($req->fechaFin)),8,2)=="31" && substr($req->fechaFin, 8,2) == "30"){
            $req->fechaFin = substr($req->fechaFin,0,8)."31";
        }

        $nominas = DB::table("item_boucher_pago", "ibp")
        ->selectRaw("c.idconcepto,c.nombre,ln.fechaLiquida,e.idempleado, 
        dp.primerNombre,dp.segundoNombre,
        dp.primerApellido,dp.segundoApellido,ti.nombre as tipoidentificacion, 
        dp.numeroIdentificacion, bp.diasTrabajados, ibp.valor, ibp.cantidad, 
        bp.idBoucherPago, bp.fkPeriodoActivo, ccfijo.valor as valorSalario,c.fkNaturaleza,
        emp.razonSocial as nom_empresa,n.nombre as nom_nomina,n.id_uni_nomina, cargo.nombreCargo, cargo2.nombreCargo as nombreCargoPeriodo,
        (SELECT centrocosto.nombre from centrocosto where idcentroCosto in
        (Select empleado_centrocosto.fkCentroCosto from empleado_centrocosto where empleado_centrocosto.fkEmpleado = e.idempleado
         and empleado_centrocosto.fkPeriodoActivo = bp.fkPeriodoActivo) 
        limit 0,1) as centroCosto,
        (SELECT centrocosto.id_uni_centro from centrocosto where idcentroCosto in
        (Select empleado_centrocosto.fkCentroCosto from empleado_centrocosto where empleado_centrocosto.fkEmpleado = e.idempleado
         and empleado_centrocosto.fkPeriodoActivo = bp.fkPeriodoActivo) 
        limit 0,1) as idCentroCosto,p.fkNomina as fkNominaPeriodo, e.fkNomina as fkNominaEmpleado")        
        ->join("concepto as c","c.idconcepto", "=","ibp.fkConcepto")
        ->join("boucherpago as bp","bp.idBoucherPago","=", "ibp.fkBoucherPago")
        ->join("liquidacionnomina as ln", "ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
        ->join("empleado as e","e.idempleado", "=", "bp.fkEmpleado")
        ->join("periodo as p","p.idperiodo", "=", "bp.fkPeriodoActivo")
        ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion")
        ->join("nomina as n","n.idNomina", "=","p.fkNomina")
        ->leftJoin('conceptofijo as ccfijo', function ($join) {
            $join->on('ccfijo.fkEmpleado', '=', 'e.idempleado')
                ->on('ccfijo.fkPeriodoActivo', '=', 'bp.fkPeriodoActivo')
                ->whereIn('ccfijo.fkConcepto', ["1","2","53","54","154"]);
        })
        ->join("empresa as emp","emp.idempresa", "=", "n.fkEmpresa")
        ->join("cargo","cargo.idCargo","=","e.fkCargo","left")
        ->join("cargo as cargo2","cargo2.idCargo","=","p.fkCargo","left")
        ->whereBetween("ln.fechaLiquida",[$req->fechaInicio, $req->fechaFin]);
        if(isset($req->nomina)){
            $nominas = $nominas->where("n.idNomina", "=", $req->nomina);
        }
        if(isset($req->empresa)){
            $nominas = $nominas->where("n.fkEmpresa", "=", $req->empresa);
        }
        //->where("dp.numeroIdentificacion","=","1018496288")
        $nominas = $nominas->orderBy("ln.fechaLiquida")
        ->orderBy("e.idempleado")
        ->orderBy("c.idconcepto")        
        ->get();



        $nominas_fuera_nom = DB::table("item_boucher_pago_fuera_nomina", "ibp")
        ->selectRaw("c.idconcepto,c.nombre,ln.fechaLiquida,e.idempleado, 
        dp.primerNombre,dp.segundoNombre,
        dp.primerApellido,dp.segundoApellido,ti.nombre as tipoidentificacion, 
        dp.numeroIdentificacion, bp.diasTrabajados, ibp.valor, ibp.cantidad, 
        bp.idBoucherPago, bp.fkPeriodoActivo, ccfijo.valor as valorSalario,c.fkNaturaleza,
        emp.razonSocial as nom_empresa,n.nombre as nom_nomina,n.id_uni_nomina, cargo.nombreCargo, cargo2.nombreCargo as nombreCargoPeriodo,
        (SELECT centrocosto.nombre from centrocosto where idcentroCosto in
        (Select empleado_centrocosto.fkCentroCosto from empleado_centrocosto where empleado_centrocosto.fkEmpleado = e.idempleado
         and empleado_centrocosto.fkPeriodoActivo = bp.fkPeriodoActivo) 
        limit 0,1) as centroCosto,
        (SELECT centrocosto.id_uni_centro from centrocosto where idcentroCosto in
        (Select empleado_centrocosto.fkCentroCosto from empleado_centrocosto where empleado_centrocosto.fkEmpleado = e.idempleado
         and empleado_centrocosto.fkPeriodoActivo = bp.fkPeriodoActivo) 
        limit 0,1) as idCentroCosto,p.fkNomina as fkNominaPeriodo, e.fkNomina as fkNominaEmpleado")        
        ->join("concepto as c","c.idconcepto", "=","ibp.fkConcepto")
        ->join("boucherpago as bp","bp.idBoucherPago","=", "ibp.fkBoucherPago")
        ->join("liquidacionnomina as ln", "ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
        ->join("empleado as e","e.idempleado", "=", "bp.fkEmpleado")
        ->join("periodo as p","p.idperiodo", "=", "bp.fkPeriodoActivo")
        ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion")
        ->join("nomina as n","n.idNomina", "=","p.fkNomina")
        ->leftJoin('conceptofijo as ccfijo', function ($join) {
            $join->on('ccfijo.fkEmpleado', '=', 'e.idempleado')
                ->on('ccfijo.fkPeriodoActivo', '=', 'bp.fkPeriodoActivo')
                ->whereIn('ccfijo.fkConcepto', ["1","2","53","54","154"]);
        })
        ->join("empresa as emp","emp.idempresa", "=", "n.fkEmpresa")
        ->join("cargo","cargo.idCargo","=","e.fkCargo","left")
        ->join("cargo as cargo2","cargo2.idCargo","=","p.fkCargo","left")
        ->whereBetween("ln.fechaLiquida",[$req->fechaInicio, $req->fechaFin]);
        if(isset($req->nomina)){
            $nominas_fuera_nom = $nominas_fuera_nom->where("n.idNomina", "=", $req->nomina);
        }
        if(isset($req->empresa)){
            $nominas_fuera_nom = $nominas_fuera_nom->where("n.fkEmpresa", "=", $req->empresa);
        }
        //->where("dp.numeroIdentificacion","=","1018496288")
        $nominas_fuera_nom = $nominas_fuera_nom->orderBy("ln.fechaLiquida")
        ->orderBy("e.idempleado")
        ->orderBy("c.idconcepto")        
        ->get();
        
        //dd($nominas_fuera_nom);

        $nominas = $nominas->concat($nominas_fuera_nom);
        //dd();

        $matrizReporte = array();
        foreach($nominas as $nomina){
            $matrizReporte[$nomina->fkPeriodoActivo]["Id Periodo"][$nomina->idBoucherPago] = $nomina->fkPeriodoActivo;
            $matrizReporte[$nomina->fkPeriodoActivo]["Fecha Liquidacion"][$nomina->idBoucherPago] = $nomina->fechaLiquida;
            $matrizReporte[$nomina->fkPeriodoActivo]["Empresa"][$nomina->idBoucherPago] = $nomina->nom_empresa;     
            $matrizReporte[$nomina->fkPeriodoActivo]["Id Nomina"][$nomina->idBoucherPago] = $nomina->id_uni_nomina;   
            $matrizReporte[$nomina->fkPeriodoActivo]["Nomina"][$nomina->idBoucherPago] = $nomina->nom_nomina;     
            
            
            $matrizReporte[$nomina->fkPeriodoActivo]["Id Centro costo"][$nomina->idBoucherPago] = $nomina->idCentroCosto;   
            $matrizReporte[$nomina->fkPeriodoActivo]["Centro costo"][$nomina->idBoucherPago] = $nomina->centroCosto;   
            // $matrizReporte[$nomina->fkPeriodoActivo]["Cargo"][$nomina->idBoucherPago] = ($nomina->nombreCargoPeriodo ?? $nomina->nombreCargo);   
            
            
            $matrizReporte[$nomina->fkPeriodoActivo]["Tipo Documento"][$nomina->idBoucherPago] = $nomina->tipoidentificacion;
            $matrizReporte[$nomina->fkPeriodoActivo]["Documento"][$nomina->idBoucherPago] = $nomina->numeroIdentificacion;     
            $matrizReporte[$nomina->fkPeriodoActivo]["Nombre"][$nomina->idBoucherPago] = ucfirst(mb_strtolower($nomina->primerApellido))." ".ucfirst(mb_strtolower($nomina->segundoApellido))." ".ucfirst(mb_strtolower($nomina->primerNombre))." ".ucfirst(mb_strtolower($nomina->segundoNombre));
            if($nomina->fkNominaPeriodo == $nomina->fkNominaEmpleado){
                // $matrizReporte[$nomina->fkPeriodoActivo]["Centro costo"][$nomina->idBoucherPago] = $nomina->centroCosto;   
                $matrizReporte[$nomina->fkPeriodoActivo]["Cargo"][$nomina->idBoucherPago] = ($nomina->nombreCargoPeriodo ?? $nomina->nombreCargo);   
            }
            else{
                // $matrizReporte[$nomina->fkPeriodoActivo]["Centro costo"][$nomina->idBoucherPago] = $nomina->centroCosto;   
                $matrizReporte[$nomina->fkPeriodoActivo]["Cargo"][$nomina->idBoucherPago] = $nomina->nombreCargoPeriodo;   
            }
            

            

            $matrizReporte[$nomina->fkPeriodoActivo]["Sueldo"][$nomina->idBoucherPago] = intval($nomina->valorSalario);
            if($nomina->idconcepto == "1" || $nomina->idconcepto == "2" || $nomina->idconcepto == "53" || $nomina->idconcepto == "54" || $nomina->idconcepto == "154"){
                $matrizReporte[$nomina->fkPeriodoActivo]["Dias"][$nomina->idBoucherPago] = intval($nomina->cantidad);
            }
            
            $matrizReporte[$nomina->fkPeriodoActivo][$nomina->nombre][$nomina->idBoucherPago]["valor"] = $nomina->valor;
            $matrizReporte[$nomina->fkPeriodoActivo][$nomina->nombre][$nomina->idBoucherPago]["naturaleza"] = $nomina->fkNaturaleza;
        }
        
        

        //Agregar titulos a arrDefLinea1


        //Agregar titulos de campos que no son arrays como fechas de liquidacion, empresa, etc.
        $arrDefLinea1 = array();
        $arrDef = array();
        foreach($matrizReporte as $matriz){ 
            foreach($matriz as $row => $dato){
                foreach($dato as $rowInt => $datoInt){
                    if(!is_array($datoInt)){
                        if(!in_array($row, $arrDefLinea1)){
                            array_push($arrDefLinea1, $row);
                        }
                    }              
                }
            }
        
        }

        //Agregar titulos al los conceptos de pago
        foreach($matrizReporte as $matriz){ 
            foreach($matriz as $row => $dato){
                foreach($dato as $rowInt => $datoInt){
                    if(is_array($datoInt)){
                        if($datoInt['naturaleza']=="1"){
                            if(!in_array($row, $arrDefLinea1)){
                                array_push($arrDefLinea1, $row);
                            }                            
                        }
                    }
                }
            }
        }
        array_push($arrDefLinea1, "TOTAL PAGOS");

        //Agregar titulos a conceptos de descuento
        foreach($matrizReporte as $matriz){ 
            foreach($matriz as $row => $dato){
                foreach($dato as $rowInt => $datoInt){
                    if(is_array($datoInt)){
                        if($datoInt['naturaleza']=="3"){
                            if(!in_array($row, $arrDefLinea1)){
                                array_push($arrDefLinea1, $row);
                            }                            
                        }
                    }
                }
            }
        }
        array_push($arrDefLinea1, "TOTAL DESCUENTO");
        array_push($arrDefLinea1, "NETO PAGAR");
        $idDefPagos = array_search("TOTAL PAGOS", $arrDefLinea1);
        $idDefDesc = array_search("TOTAL DESCUENTO", $arrDefLinea1);
        $idDefTotal = array_search("NETO PAGAR", $arrDefLinea1);
        
        foreach($matrizReporte as $matriz){ 
            foreach($matriz as $row => $dato){
                foreach($dato as $rowInt => $datoInt){
                    if(is_array($datoInt)){
                        if($datoInt['naturaleza']=="5" || $datoInt['naturaleza']=="6"){
                            if(!in_array($row, $arrDefLinea1)){
                                array_push($arrDefLinea1, $row);
                            }                            
                        }
                    }
                }
            }
        }

        //dd($matrizReporte);

        
        foreach($matrizReporte as $idPeriodo => $matriz){ 
            $arrFila = array();
            foreach($matriz as $rowTitulo => $dato){
                foreach($dato as $rowInt => $datoInt){                             
                    //Buscar el id de la columna en arrDefLinea1
                    $idDef = array_search($rowTitulo, $arrDefLinea1);

                    //Sumar los conceptos y totales
                    if(is_array($datoInt) && ($datoInt['naturaleza'] == "1" || $datoInt['naturaleza'] == "3") && $rowTitulo != "Dias" && $rowTitulo != "Sueldo"  && $rowTitulo != "Id Nomina"  && $rowTitulo != "Id Centro costo"){
                        $arrFila[$idDefTotal][$rowInt] = (isset($arrFila[$idDefTotal][$rowInt]) ? $arrFila[$idDefTotal][$rowInt] + $datoInt['valor'] : $datoInt['valor']);    
                    }  

                    if(is_array($datoInt) && $datoInt['naturaleza'] == "3"){
                        $arrFila[$idDefDesc][$rowInt] = (isset($arrFila[$idDefDesc][$rowInt]) ? $arrFila[$idDefDesc][$rowInt]  + ($datoInt['valor']*-1) : ($datoInt['valor']*-1)); 
                        $arrFila[$idDef][$rowInt] = $datoInt['valor']*-1;    
                    }
                    else if(is_array($datoInt) && $datoInt['naturaleza'] == "1" && $rowTitulo != "Dias" && $rowTitulo != "Sueldo" && $rowTitulo != "Id Nomina"  && $rowTitulo != "Id Centro costo"){
                        $arrFila[$idDefPagos][$rowInt] = (isset($arrFila[$idDefPagos][$rowInt]) ? $arrFila[$idDefPagos][$rowInt] + $datoInt['valor'] : $datoInt['valor']);
                        $arrFila[$idDef][$rowInt] = $datoInt['valor'];    
                    }
                    else{
                        if(is_array($datoInt) && $datoInt['naturaleza'] == "5"){
                            $arrFila[$idDef][$rowInt] = $datoInt['valor'];
                        }              
                        else if(is_array($datoInt) && $datoInt['naturaleza'] == "6"){
                            $arrFila[$idDef][$rowInt] = $datoInt['valor']*-1;
                        }
                        else{
                            $arrFila[$idDef][$rowInt] = $datoInt;
                        }
                        
                        
                    }
                    //Rowint = idboucher
                    //idDef = id columna en el archivo final
                }
                

            }
            if(!empty($arrFila)){
                array_push($arrDef, $arrFila);  
            }
        }
        
        //dd($arrDef);
   
        $reporteFinal = array();
        $reporteFinal[0] = $arrDefLinea1;
        
        foreach($arrDef as $key => $datos){
            foreach($arrDefLinea1 as $row => $datoLinea1){
                if(!isset($datos[$row])){
                    $datos[$row] = 0;
                }
            }
            ksort($datos);
            $arrDef[$key] = $datos;
            
        }
        
        foreach($arrDef as $datos){
            $arrEntrega= array();
            foreach($datos as $row => $vData){
                foreach($datos[0] as $idBouc => $datosInt){
                    if(isset($vData[$idBouc])){
                        if(is_array($vData[$idBouc])){
                            $arrEntrega[$idBouc][$row] = $vData[$idBouc];
                        }
                        else{
                            $arrEntrega[$idBouc][$row] = $vData[$idBouc];
                        }
                    }
                    else{
                        $arrEntrega[$idBouc][$row] = 0;
                    }
                    
                    ksort($arrEntrega);
                }           
            }
          
            foreach($arrEntrega as $porfin){
                array_push($reporteFinal, $porfin);
            }
        }
      
        $reporteDatosJuntos = array($reporteFinal[0]);
        $empleadoActual = 0;

        //Indice del periodo activo = 0
        
        for($i = 1; $i<sizeof($reporteFinal) ;$i++){

            if($empleadoActual != $reporteFinal[$i][0]){
                $empleadoActual = $reporteFinal[$i][0];
            }
    
            $existeEmp = -1;
            foreach($reporteDatosJuntos as $row => $reporteTemp){
                if($reporteTemp[0] == $empleadoActual){
                    //Existe el empleado en el reporte conjunto
                    $existeEmp = $row;
                }
            }
            
            
            if($existeEmp== -1){
                array_push($reporteDatosJuntos, $reporteFinal[$i]);
            }
            else{
                foreach($reporteFinal[$i] as $row => $columna){
                    if(is_numeric($columna) && $row!=0 &&$row!=3 && $row!=5 && $row!=8 && $row!=11 && is_numeric($reporteDatosJuntos[$existeEmp][$row])){
                        try{
                            $reporteDatosJuntos[$existeEmp][$row] = $reporteDatosJuntos[$existeEmp][$row] + $columna;
                        }
                        catch(Exception $e){
                            dd($e, $columna, $reporteDatosJuntos[$existeEmp][$row], $row,  $reporteDatosJuntos);
                        }
                    }
                }
            }

        }
        //dd($reporteDatosJuntos,"1");

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename=NominaHorizontal_'.$req->empresa.'_'.$req->fechaInicio.'_'.$req->fechaFin.'.csv');

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", generó reporte nómina horizontal por fechas");

        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->setDelimiter(';');
        $csv->insertAll($reporteDatosJuntos);
        $csv->setOutputBOM(Reader::BOM_UTF8);
        $csv->output('NominaHorizontal_'.$req->empresa.'_'.$req->fechaInicio.'_'.$req->fechaFin.'.csv');
    }

    public function boucherCorreo($idBoucherPago){

        $empresayLiquidacion = DB::table("empresa", "e")
        ->select("e.*", "ln.*", "n.nombre as nom_nombre", "bp.*")
        ->join("nomina as n","n.fkEmpresa", "e.idempresa")
        ->join("liquidacionnomina as ln","ln.fkNomina", "n.idNomina")
        ->join("boucherpago as bp","bp.fkLiquidacion", "ln.idLiquidacionNomina")
        ->where("bp.idBoucherPago","=",$idBoucherPago)
        ->first();
    
        $userSolicita = "";
        if(isset($empresayLiquidacion->fkUserSolicita)){
            $usuario = DB::table('users')->select(
                'users.username',
                'users.email',
                'datospersonales.primerNombre',
                'datospersonales.primerApellido',
                'datospersonales.foto',
                'empleado.idempleado'
            )->leftJoin('empleado', 'users.fkEmpleado', 'empleado.idempleado',"left")
            ->leftJoin('empresa', 'empresa.idempresa', 'empleado.fkEmpresa',"left")
            ->leftJoin('datospersonales', 'datospersonales.idDatosPersonales', 'empleado.fkDatosPersonales',"left")
            ->where('users.id', $empresayLiquidacion->fkUserSolicita)
            ->first();
            
            if(isset($usuario->primerNombre)){
                $userSolicita = $usuario->primerNombre." ".$usuario->primerApellido;
            }
            else{
                $userSolicita = $usuario->username;
            }
        }

        $userAprueba = "";
        if(isset($empresayLiquidacion->fkUserAprueba)){
            $usuario = DB::table('users')->select(
                'users.username',
                'users.email',
                'datospersonales.primerNombre',
                'datospersonales.primerApellido',
                'datospersonales.foto',
                'empleado.idempleado'
            )
            ->leftJoin('empleado', 'users.fkEmpleado', 'empleado.idempleado')
            ->leftJoin('empresa', 'empresa.idempresa', 'empleado.fkEmpresa')
            ->leftJoin('datospersonales', 'datospersonales.idDatosPersonales', 'empleado.fkDatosPersonales')
            ->where('users.id',"=", $empresayLiquidacion->fkUserAprueba)
            ->first();
            
            if(isset($usuario->primerNombre)){
                $userAprueba = $usuario->primerNombre." ".$usuario->primerApellido;
            }
            else{
                $userAprueba = $usuario->username;
            }
        }


        
        $empleado = DB::table("empleado","e")
        ->select("e.idempleado", "p.fechaInicio as fechaIngreso",
        "e.tipoRegimen","p.tipoRegimen as tipoRegimenPeriodo", "p.fkNomina",
        "dp.primerNombre","dp.segundoNombre", 
        "dp.primerApellido","dp.segundoApellido","ti.nombre as tipoidentificacion", 
        "dp.numeroIdentificacion", "cargo.nombreCargo", "cargo2.nombreCargo as nombreCargoPeriodo",
        "p.idPeriodo")
        ->join("boucherpago as bp","bp.fkEmpleado", "e.idempleado")
        ->join("periodo as p","p.idPeriodo", "bp.fkPeriodoActivo")
        ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion")
        ->join("cargo","cargo.idCargo","=","e.fkCargo", "left")
        ->join("cargo as cargo2","cargo2.idCargo","=","p.fkCargo", "left")
        ->where("bp.idBoucherPago","=",$idBoucherPago)
        ->first();

        $empleado->tipoRegimen = ($empleado->tipoRegimenPeriodo ?? $empleado->tipoRegimen);
        $empleado->nombreCargo = ($empleado->nombreCargoPeriodo ?? $empleado->nombreCargo);


        $nomina = DB::table("nomina","n")
        ->where("n.idNomina","=",$empleado->fkNomina)->first();
        $pension = DB::table("tercero", "t")->
        select(["t.razonSocial", "t.numeroIdentificacion", "t.idTercero", "ti.nombre as tipoidentificacion", "t.digitoVer"])
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","t.fkTipoIdentificacion")
        ->join("afiliacion as a","t.idTercero", "=","a.fkTercero")
        ->where("a.fkEmpleado","=",$empleado->idempleado)
        ->where("a.fkTipoAfilicacion","=","4") //4-Pensión Obligatoria 
        ->where("a.fkPeriodoActivo","=",$empresayLiquidacion->fkPeriodoActivo)
        ->first();

        $salud = DB::table("tercero", "t")->select(["t.razonSocial", "t.numeroIdentificacion", "t.idTercero",
        "ti.nombre as tipoidentificacion", "t.digitoVer"])
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","t.fkTipoIdentificacion")
        ->join("afiliacion as a","t.idTercero", "=","a.fkTercero")
        ->where("a.fkEmpleado","=",$empleado->idempleado)
        ->where("a.fkTipoAfilicacion","=","3") //3-Salud
        ->where("a.fkPeriodoActivo","=",$empresayLiquidacion->fkPeriodoActivo)
        ->first();

        $cesantiasEmp = DB::table("tercero", "t")->select(["t.razonSocial", "t.numeroIdentificacion", "t.idTercero",
            "ti.nombre as tipoidentificacion", "t.digitoVer"])
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","t.fkTipoIdentificacion")
        ->join("afiliacion as a","t.idTercero", "=","a.fkTercero")
        ->where("a.fkEmpleado","=",$empleado->idempleado)
        ->where("a.fkTipoAfilicacion","=","1") //2-CCF
        ->where("a.fkPeriodoActivo","=",$empresayLiquidacion->fkPeriodoActivo)
        ->first();

        $entidadBancaria = DB::table("tercero", "t")->select(["t.razonSocial", "e.numeroCuenta"])
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","t.fkTipoIdentificacion")
        ->join("empleado as e", "e.fkEntidad", "=","t.idTercero")
        ->where("e.idempleado","=",$empleado->idempleado)
        ->first();

        $entidadBancariaPeriodo = DB::table("tercero", "t")->select(["t.razonSocial", "p.numeroCuenta"])
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","t.fkTipoIdentificacion")
        ->join("periodo as p", "p.fkEntidad", "=","t.idTercero")
        ->where("p.idPeriodo","=",$empleado->idPeriodo)
        ->first();

        if(!isset($entidadBancaria)){
            $entidadBancaria = $entidadBancariaPeriodo;
        }
        if(isset($entidadBancaria)){
            $entidadBancaria->razonSocial = ($entidadBancariaPeriodo->razonSocial ?? $entidadBancaria->razonSocial);
            $entidadBancaria->numeroCuenta = ($entidadBancariaPeriodo->numeroCuenta ?? $entidadBancaria->numeroCuenta);
        }


        $idItemBoucherPago = DB::table("item_boucher_pago","ibp")
        ->join("concepto AS c","c.idconcepto","=", "ibp.fkConcepto")
        ->where("ibp.fkBoucherPago","=",$idBoucherPago)
        ->get();

        $itemsBoucherPagoFueraNomina = DB::table("item_boucher_pago_fuera_nomina","ibpfn")
        ->join("concepto AS c","c.idconcepto","=", "ibpfn.fkConcepto")
        ->where("ibpfn.fkBoucherPago","=",$idBoucherPago)
        ->get();

        $itemsBoucherPagoFueraNominaCesTras = DB::table("item_boucher_pago_fuera_nomina","ibpfn")
        ->select("ibpfn.*","c.*")
        ->join("concepto AS c","c.idconcepto","=", "ibpfn.fkConcepto")
        ->join("boucherpago as bp","bp.idBoucherPago","=","ibpfn.fkBoucherPago")
        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
        ->whereRaw("MONTH(ln.fechaLiquida)= MONTH('".$empresayLiquidacion->fechaLiquida."')")
        ->whereRaw("YEAR(ln.fechaLiquida)= YEAR('".$empresayLiquidacion->fechaLiquida."')")
        ->where("bp.fkEmpleado","=", $empleado->idempleado)
        ->where("ln.idLiquidacionNomina","<>",$empresayLiquidacion->idLiquidacionNomina)
        ->where("ln.fkTipoLiquidacion","=","11")
        ->get();
        foreach($itemsBoucherPagoFueraNominaCesTras as $itemBoucherPagoFueraNominaCesTras){
            $itemsBoucherPagoFueraNomina->push($itemBoucherPagoFueraNominaCesTras);
        }




        $periodoPasadoReintegro = DB::table("periodo")
        ->where("fkEstado","=","2")
        ->where("fkEmpleado", "=", $empleado->idempleado)
        ->where("fechaInicio","<=",$empresayLiquidacion->fechaInicio)
        ->where("fechaFin",">=",$empresayLiquidacion->fechaInicio)
        ->where("fkNomina","=",$empresayLiquidacion->fkNomina)
        ->first();
        
        if(isset($periodoPasadoReintegro)){
            $conceptoSalario = new stdClass;
            $conceptoSalario->valor = $periodoPasadoReintegro->salario;
        }
        else{
            $conceptoSalario = DB::table("conceptofijo")
            ->where("fkEmpleado","=",$empleado->idempleado)
            ->where("fkPeriodoActivo","=",$empresayLiquidacion->fkPeriodoActivo)            
            ->whereIn("fkConcepto",[1,2,53,54,154])->first();
        }
        

        

        //VACACIONES
        $novedadesVacacionActual = DB::table("novedad","n")
        ->select("v.*", "c.nombre","c.idconcepto", "ibpn.valor")
        ->join("concepto as c","c.idconcepto", "=","n.fkConcepto")
        ->join("vacaciones as v","v.idVacaciones","=","n.fkVacaciones")
        ->join("item_boucher_pago_novedad as ibpn","ibpn.fkNovedad","=","n.idNovedad")
        ->join("item_boucher_pago as ibp","ibp.idItemBoucherPago","=","ibpn.fkItemBoucher")
        ->where("ibp.fkBoucherPago","=",$idBoucherPago)
        ->whereIn("n.fkEstado",["8","7"]) // Pagada -> no que este eliminada
        ->whereNotNull("n.fkVacaciones")
        ->get();
        //$diasVac = $totalPeriodoPagoAnioActual * 15 / 360;



        $base64 = "";
        if(is_file($this->rutaBaseImagenes.'storage/logosEmpresas/'.$empresayLiquidacion->logoEmpresa)){
            $imagedata = file_get_contents($this->rutaBaseImagenes.'storage/logosEmpresas/'.$empresayLiquidacion->logoEmpresa);
                    // alternatively specify an URL, if PHP settings allow
            $base64 = base64_encode($imagedata);
        }
        else{
            unset($empresayLiquidacion->logoEmpresa);
        }
        $arrMeses = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];
        $mensajeGen = array();
        $mensajeGen[8] = DB::table("mensaje")->where("idMensaje","=","8")->first();
        $mensajeGen[9] = DB::table("mensaje")->where("idMensaje","=","9")->first();
        
       

        $dompdf = new Dompdf();
        $dompdf->getOptions()->setChroot($this->rutaBaseImagenes);
        $dompdf->getOptions()->setIsPhpEnabled(true);
        $html='
        <html>
        <body>
            <style>
            *{
                -webkit-hyphens: auto;
                -ms-hyphens: auto;
                hyphens: auto;
                font-family: sans-serif;                
            }
            td{
                text-align: left;
                font-size: 8px;
            }
            th{
                text-align: left;
                font-size: 8px;
            }
            .liquida td, .liquida th{
                font-size:9px;
            }
            
            @page { 
                margin: 0in;
            }
            .page {
                top: .3in;
                right: .3in;
                bottom: .3in;
                left: .3in;
                position: absolute;
                z-index: -1000;
                min-width: 7in;
                min-height: 11.7in;
                
            }
            .page_break { 
                page-break-before: always; 
            }
            .tituloTable td b{
                font-size: 14px;
            }
            </style>
            ';
            $novedadesRetiro = DB::table("novedad","n")
            ->select("r.fecha", "r.fechaReal","mr.nombre as motivoRet")
            ->join("retiro AS r", "r.idRetiro","=","n.fkRetiro")
            ->join("motivo_retiro as mr","mr.idMotivoRetiro","=","r.fkMotivoRetiro")
            ->where("n.fkEmpleado", "=", $empleado->idempleado)
            ->whereIn("n.fkEstado",["7", "8"])
            ->whereNotNull("n.fkRetiro")
            ->whereBetween("n.fechaRegistro",[$empresayLiquidacion->fechaInicio, $empresayLiquidacion->fechaFin])->first();


            if($empresayLiquidacion->fkTipoLiquidacion == "7"){
                $html.='<div class="page liquida">
                <div style="border: 2px solid #000; padding: 5px 10px; font-size: 15px; margin-bottom: 5px;">
                    <table class="tituloTable">
                        <tr>
                            <td rowspan="2">
                            '.(isset($empresayLiquidacion->logoEmpresa) ? '<img style="max-width: 50px; max-height: 50px; margin-right: 5px;" src="data:image/png;base64,'.$base64.'" class="logoEmpresa" />' : '').'
                            </td>
                            <td valign="bottom"><b>'.$empresayLiquidacion->razonSocial.'</b></td>
                        </tr>
                        <tr>
                            <td  valign="top"><b>'.number_format($empresayLiquidacion->documento,0, ",", ".").'-'.$empresayLiquidacion->digitoVerificacion.'</b></td>
                        </tr>
                    </table>
                    <center>
                        <h2 style="margin:0; margin-bottom: 0px; font-size: 20px;">COMPROBANTE DE PAGO DE NÓMINA</h2><br>
                    </center>
                    <table style="width: 100%;">
                        <tr>
                            <th>Nómina</th>
                            <td>'.$empresayLiquidacion->nom_nombre.'</td>
                            <th>Período liquidación</th>
                            <td>
                                '.date("Y",strtotime($empresayLiquidacion->fechaInicio))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaInicio)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaInicio)).' 
                                a
                                '.date("Y",strtotime($empresayLiquidacion->fechaFin))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaFin)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaFin)).' 
                            </td>
                        </tr>
                        <tr>
                            <th>Empleado</th>
                            <td>'.$empleado->primerApellido." ".$empleado->segundoApellido." ".$empleado->primerNombre." ".$empleado->segundoNombre.'</td>
                            <th>Salario</th>
                            <td>$ '.number_format($conceptoSalario->valor,0, ",", ".").'</td>
                        </tr>
                        <tr>
                            <th>Identificación</th>
                            <td>'.$empleado->tipoidentificacion.' '.$empleado->numeroIdentificacion.'</td>
                            <th>Cargo</th>
                            <td>'.$empleado->nombreCargo.'</td>
                        </tr>
                        <tr>
                            <th>Entidad Bancaria</th>
                            <td>'.(isset($entidadBancaria->razonSocial) ? $entidadBancaria->razonSocial : "").'</td>
                            <th>Cuenta</th>
                            <td>'.(isset($entidadBancaria->numeroCuenta) ? $entidadBancaria->numeroCuenta : "").'</td>
                        </tr>
                        <tr>
                            <th>EPS</th>
                            <td>'.$salud->razonSocial.'</td>
                            <th>Fondo Pensiones</th>
                            <td>'.(isset($pension->razonSocial) ? $pension->razonSocial : "").'</td>
                        </tr>
                        
                    </table>
                    <br>
                </div>
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                                <th style="background: #d89290; text-align: center;" colspan="2">Devengado</th>
                                <th style="background: #d89290; text-align: center;">Deducciones</th>                        
                            </tr>
                            <tr>
                                <th style="background: #CCC; text-align: center;">Conceptos Liquidados</th>
                                <th style="background: #CCC; text-align: center;">Cantidad</th>
                                <th style="background: #CCC; text-align: center;">Unidad</th>
                                <th style="background: #CCC; text-align: center;">Pagos</th>
                                <th style="background: #CCC; text-align: center;">Beneficios</th>
                                <th style="background: #CCC; text-align: center;">Descuentos</th>
                            </tr>';
                            $totalDesc = 0;
                            $totalPag = 0;
                
                            foreach($idItemBoucherPago as $itemBoucherPago){
                                $html.='<tr style="border-bottom: 1px solid #B0B0B0;">
                                    <td style="border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->nombre.'</td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.((15 / 180) * $itemBoucherPago->cantidad).'</td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->tipoUnidad.'</td>';
                                    
                                    if($itemBoucherPago->valor > 0){
                                        $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor,0, ",", ".").'</td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>';
                                        $totalPag = $totalPag + $itemBoucherPago->valor;
                                    }
                                    else{
                                        $html.='<td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor*-1,0, ",", ".").'</td>';
                                        $totalDesc = $totalDesc + $itemBoucherPago->valor;
                                    }

                                $html.='</tr>';
                            }
                            $html.='<tr>
                                        
                                        <th colspan="3" style="text-align: right;">Totales</th>
                                        <td style="text-align: right; border: 1px solid #B0B0B0;">$'.number_format($totalPag,0, ",", ".").'</td>
                                        <td style="text-align: right; border: 1px solid #B0B0B0;"></td>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" >$'.number_format($totalDesc*-1,0, ",", ".").'</td>
                                    </tr>
                            ';
                            $totalGen = $totalPag + $totalDesc;
                            if($totalGen<0){
                                $totalGen=0;
                            }
                            $html.='<tr>
                                        
                                        <th colspan="3" style="text-align: right;" >Neto a pagar en cuenta nómina</th>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" colspan="2">$'.number_format($totalGen,0, ",", ".").'</td>
                                        
                                    </tr>
                            ';
                            
                        $html.='</table>

                    </div>
                    <br>
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="2">Bases para cálculo de seguridad social</th>
                            </tr>
                            <tr>
                                <td>Ingreso Base Cotización Salud</td><td style="text-align: right;">$'.number_format($empresayLiquidacion->ibc_eps,0, ",", ".").'</td>
                            </tr>
                            <tr>
                                <td>Ingreso Base Cotización Pension</td><td style="text-align: right;">$'.number_format($empresayLiquidacion->ibc_afp,0, ",", ".").'</td>
                            </tr>
                        </table>
                    </div>
                    <br>
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="2">Mensaje Empresarial</th>
                            </tr>
                            <tr>
                                <td style="text-align: justify;">'.$mensajeGen[8]->html.'</td>
                            </tr>
                        </table>
                    </div>
                    <br>
                    <div style="position: absolute; bottom: 40px; width:100%;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td>COLABORADOR</td>
                                <td></td>
                                <td>LA EMPRESA</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td>Cédula o NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                                <td>NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                            </tr>
                            <tr>
                                <td colspan="3"></td>
                                <td>Fecha de elaboración:&nbsp;&nbsp;&nbsp; '.date("d/m/Y").'</td>
                            </tr>
                        </table>

                    </div>
                </div>';
            }
            else if(($empresayLiquidacion->fkTipoLiquidacion == "2" || $empresayLiquidacion->fkTipoLiquidacion == "3") && isset($novedadesRetiro->fecha)){
        
                $contrato = DB::table("contrato","c")
                ->join("tipocontrato as tc","tc.idtipoContrato", "=","c.fkTipoContrato")
                ->where("c.fkEmpleado","=",$empleado->idempleado)
                ->whereIn("c.fkEstado",["1","2","4"])->first();
                
                $cambioSalario = DB::table("cambiosalario","cs")
                ->where("cs.fkEmpleado","=",$empleado->idempleado)
                ->where("cs.fkEstado","=","5")
                ->first();
                $fechaUltimoCamSal = $empleado->fechaIngreso;
                if(isset($cambioSalario)){
                    $fechaUltimoCamSal = $cambioSalario->fechaCambio;
                }

                $fechaRet1 = $novedadesRetiro->fecha;
                if(substr($fechaRet1, 8, 2) == "31" || (substr($fechaRet1, 8, 2) == "28" && substr($fechaRet1, 5, 2) == "02") || (substr($fechaRet1, 8, 2) == "29" && substr($fechaRet1, 5, 2) == "02") ){
                    $fechaRet1 = $novedadesRetiro->fecha;
                }
                $diasLab = $this->days_360($empleado->fechaIngreso, $fechaRet1) + 1;
                $meses = intval($diasLab/30);
                $diasDemas = $diasLab - ($meses * 30);
                $tiempoTrabTxt = $meses." Meses ".$diasDemas." días";
                
                $fechaFinMesActual = date("Y-m-t", strtotime($novedadesRetiro->fechaReal));
                $fechaInicioMesActual = date("Y-m-01", strtotime($novedadesRetiro->fechaReal));
                $ultimoBoucher = DB::table("boucherpago", "bp")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9"])
                ->orderBy("bp.idBoucherPago","desc")
                ->first();
                
                
                if(!isset($ultimoBoucher)){
                    $ultimoBoucher = DB::table("boucherpago", "bp")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                    ->whereIn("ln.fkTipoLiquidacion",["3"])
                    ->orderBy("bp.idBoucherPago","desc")
                    ->first();
                }
                else{
                    $ultimoBoucherRetiro = DB::table("boucherpago", "bp")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                    ->whereIn("ln.fkTipoLiquidacion",["3"])
                    ->orderBy("bp.idBoucherPago","desc")
                    ->first();

                    $ultimoBoucher->ibc_afp = $ultimoBoucher->ibc_afp + ($ultimoBoucherRetiro->ibc_afp ?? 0);
                    $ultimoBoucher->ibc_eps = $ultimoBoucher->ibc_eps + ($ultimoBoucherRetiro->ibc_eps ?? 0);
                    $ultimoBoucher->ibc_arl = $ultimoBoucher->ibc_arl + ($ultimoBoucherRetiro->ibc_arl ?? 0);
                    $ultimoBoucher->ibc_ccf = $ultimoBoucher->ibc_ccf + ($ultimoBoucherRetiro->ibc_ccf ?? 0);
                    $ultimoBoucher->ibc_otros = $ultimoBoucher->ibc_otros + ($ultimoBoucherRetiro->ibc_otros ?? 0);
                }
                $IBL = $ultimoBoucher->ibc_eps;

                $html.='                    
                <div class="page liquida">
                    <div style="border: 2px solid #000; padding: 5px 10px; font-size: 15px; margin-bottom: 5px;">
                        <table class="tituloTable">
                            <tr>
                                <td rowspan="2">
                                '.(isset($empresayLiquidacion->logoEmpresa) ? '<img style="max-width: 50px; max-height: 50px; margin-right: 5px;" src="data:image/png;base64,'.$base64.'" class="logoEmpresa" />' : '').'
                                </td>
                                <td valign="bottom"><b>'.$empresayLiquidacion->razonSocial.'</b></td>
                            </tr>
                            <tr>
                                <td  valign="top"><b>'.number_format($empresayLiquidacion->documento,0, ",", ".").'-'.$empresayLiquidacion->digitoVerificacion.'</b></td>
                            </tr>
                        </table>
                        <center>
                            <h2 style="margin:0; margin-bottom: 0px; font-size: 20px;">LIQUIDACIÓN DE CONTRATO</h2>
                        </center>
                    </div>
                    <table style="width: 96%; text-align: left;">
                        <tr>
                            <th>
                                Nómina
                            </th>
                            <td>
                                '.$empresayLiquidacion->nom_nombre.'
                            </td>
                            <th>
                                Período liquidación
                            </th>
                            <td>
                                '.date("Y",strtotime($empresayLiquidacion->fechaInicio))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaInicio)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaInicio)).' 
                                a
                                '.date("Y",strtotime($empresayLiquidacion->fechaFin))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaFin)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaFin)).' 
                            </td>
                        </tr>
                        <tr>
                            <th>Empleado</th>
                            <td>'.$empleado->primerApellido." ".$empleado->segundoApellido." ".$empleado->primerNombre." ".$empleado->segundoNombre.'</td>
                            <th>Fecha ingreso</th>
                            <td>'.date("Y",strtotime($empleado->fechaIngreso))."/".$arrMeses[date("m",strtotime($empleado->fechaIngreso)) - 1].'/'.date("d",strtotime($empleado->fechaIngreso)).'</td>
                        </tr>
                        <tr>
                            <th>Identificación</th>
                            <td>'.$empleado->tipoidentificacion.' '.$empleado->numeroIdentificacion.'</td>
                            <th>Fecha Retiro</th>
                            <td>'.date("Y",strtotime($novedadesRetiro->fecha))."/".$arrMeses[date("m",strtotime($novedadesRetiro->fecha)) - 1].'/'.date("d",strtotime($novedadesRetiro->fecha)).'</td>
                        </tr>
                        <tr>
                            <th>Tipo Contrato</th>
                            <td>'.($contrato->nombre ?? "").'</td>
                            <th>Fecha Retiro Real</th>
                            <td>'.date("Y",strtotime($novedadesRetiro->fechaReal))."/".$arrMeses[date("m",strtotime($novedadesRetiro->fechaReal)) - 1].'/'.date("d",strtotime($novedadesRetiro->fechaReal)).'</td>
                        </tr>
                        <tr>
                            <th>Nómina</th>
                            <td>'.$empresayLiquidacion->nom_nombre.'</td>
                            <th>Fecha Último Aumento Salario</th>
                            <td>
                                '.date("Y",strtotime($fechaUltimoCamSal))."/".$arrMeses[date("m",strtotime($fechaUltimoCamSal)) - 1].'/'.date("d",strtotime($fechaUltimoCamSal)).' 
                            </td>
                        </tr>
                        <tr>
                            <th>Régimen</th>
                            <td>'.$empleado->tipoRegimen.'</td>
                            <th>Última Nómina Pagada</th>
                            <td>
                                '.date("Y",strtotime($empresayLiquidacion->fechaLiquida))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaLiquida)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaLiquida)).' 
                            </td>
                        </tr>
                        <tr>
                            <th>Tiempo Trabajado</th>
                            <td>'.$tiempoTrabTxt.'</td>
                            <th>Cargo</th>
                            <td>'.$empleado->nombreCargo.'</td>
                            </td>
                        </tr>
                        <tr>
                            <th>Salario</th>
                            <td>$ '.number_format($conceptoSalario->valor,0, ",", ".").'</td>
                            <th>EPS</th>
                            <td>'.$salud->razonSocial.'</td>
                        </tr>
                        <tr>
                            <th>Entidad Bancaria</th>
                            <td>'.(isset($entidadBancaria->razonSocial) ? $entidadBancaria->razonSocial : "").'</td>
                            <th>Cuenta</th>
                            <td>'.(isset($entidadBancaria->numeroCuenta) ? $entidadBancaria->numeroCuenta : "").'</td>
                        </tr>
                        <tr>
                            <th>Fondo Pensiones</th>
                            <td>'.(isset($pension->razonSocial) ? $pension->razonSocial : "").'</td>
                            <th>IBL Seguridad Social </th>
                            <td>$ '.number_format($this->roundSup($IBL,-2),0, ",", ".").'</td>
                        </tr>
                        <tr>
                            <th>Fondo Cesantías </th>
                            <td>'.(isset($cesantiasEmp->razonSocial) ? $cesantiasEmp->razonSocial : "").'</td>
                            <th>Motivo Retiro</th>
                            <td>'.$novedadesRetiro->motivoRet.'</td>
                        </tr>
                    </table>
                    <br>';
                    $basePrima = 0;
                    $baseCes = 0;
                    $baseVac = 0;

                    $fechaInicioCes = "";
                    $fechaInicioPrima = "";
                    $fechaInicioVac = $empleado->fechaIngreso;

                    $fechaFinCes = "";
                    $fechaFinPrima = "";
                    $fechaFinVac = $novedadesRetiro->fecha;

                    $diasCes = 0;
                    $diasPrima = 0;
                    $diasVac = 0;

                
                    foreach($idItemBoucherPago as $itemBoucherPago){
                        if($itemBoucherPago->fkConcepto == 30){
                            $baseVac = $itemBoucherPago->base;
                            $diasVac = $itemBoucherPago->cantidad;
                        }

                        if($itemBoucherPago->fkConcepto == 58){
                            $basePrima = $itemBoucherPago->base;
                            $fechaInicioPrima =  $itemBoucherPago->fechaInicio;
                            $fechaFinPrima =  $itemBoucherPago->fechaFin;                            
                            $diasPrima = (15 / 180) * $itemBoucherPago->cantidad;
                        }
                        
                        if($itemBoucherPago->fkConcepto == 66){
                            $baseCes = $itemBoucherPago->base;
                            $fechaInicioCes =  $itemBoucherPago->fechaInicio;
                            $fechaFinCes =  $itemBoucherPago->fechaFin;
                            $diasCes = (($itemBoucherPago->cantidad * $nomina->diasCesantias) / 360);
                        }
                        
                    }
                    $html.='<div style="border: 2px solid #000; padding: 0px 10px; margin-bottom: 5px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="6">Promedio Liquidación Prestaciones</th>
                            </tr>
                            <tr>
                                <th>Promedio Cesantías</th>
                                <td>$'.number_format($baseCes,0, ",", ".").'</td>
                                <th>Promedio Vacaciones</th>
                                <td>$'.number_format($baseVac,0, ",", ".").'</td>
                                <th>Promedio Prima</th>
                                <td>$'.number_format($basePrima,0, ",", ".").'</td>
                            </tr>
                        </table>
                    </div>';
                    $html.='<div style="border: 2px solid #000; padding: 0px 10px; margin-bottom: 5px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="5">Valores Consolidados</th>
                            </tr>
                            <tr>
                                <th  style="background: #CCC; text-align: center;">Tipo Consolidado </th>
                                <th  style="background: #CCC; text-align: center;">Fecha Inicio</th>
                                <th  style="background: #CCC; text-align: center;">Fecha Fin</th>
                                <th  style="background: #CCC; text-align: center;">Total Días</th>
                            </tr>
                            <tr>
                                <th>Cesantías consolidadas</th>
                                <td>'.$fechaInicioCes.'</td>
                                <td>'.$fechaFinCes.'</td>
                                <td>'.round($diasCes,2).'</td>
                            </tr>
                            <tr>
                                <th>Prima de servicios consolidadas</th>
                                <td>'.$fechaInicioPrima.'</td>
                                <td>'.$fechaFinPrima.'</td>
                                <td>'.round($diasPrima,2).'</td>
                            </tr>
                            <tr>
                                <th>Vacaciones consolidadas</th>
                                <td>'.$fechaInicioVac.'</td>
                                <td>'.$fechaFinVac.'</td>
                                <td>'.round($diasVac,2).'</td>
                            </tr>
                        </table>
                    </div>
                    <div style="border: 2px solid #000; padding: 0px 10px; margin-bottom: 5px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td colspan="4"></td>
                                <th style="background: #d89290; text-align: center;" colspan="2">Pagos y Descuentos</th>
                                <td></td>
                            </tr>
                            <tr>
                                <th style="background: #CCC; text-align: center;">Conceptos Liquidados</th>
                                <th style="background: #CCC; text-align: center;">Cantidad</th>
                                <th style="background: #CCC; text-align: center;">Unidad</th>
                                <th style="background: #CCC; text-align: center;">Base</th>
                                <th style="background: #CCC; text-align: center;">Pagos</th>
                                <th style="background: #CCC; text-align: center;">Descuentos</th>
                                <th style="background: #CCC; text-align: center;">Saldo Cuota</th>                                
                            </tr>';
                            $totalDesc = 0;
                            $totalPag = 0;
                            foreach($idItemBoucherPago as $itemBoucherPago){
                                $html.='<tr style="border-bottom: 1px solid #B0B0B0;">
                                    <td style="border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->nombre.'</td>';
                                    if($itemBoucherPago->fkConcepto == 58){
                                        $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.round($diasPrima,2).'</td>';
                                    }
                                    else if($itemBoucherPago->fkConcepto == 66 || $itemBoucherPago->fkConcepto == 69){
                                        
                                        $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.round($diasCes,2).'</td>';
                                    }
                                    else{
                                        $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->cantidad.'</td>';
                                    }

                                    $html.='
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->tipoUnidad.'</td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->base,0, ",", ".").'</td>';

                                    
                                    
                                    if($itemBoucherPago->valor > 0){
                                        $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor,0, ",", ".").'</td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>';
                                        $totalPag = $totalPag + $itemBoucherPago->valor;
                                    }
                                    else{
                                        $html.='<td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor*-1,0, ",", ".").'</td>';
                                        $totalDesc = $totalDesc + $itemBoucherPago->valor;
                                    }
                                    $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$0</td>';

                                $html.='</tr>';
                            }
                            $html.='<tr>
                                        
                                        <th colspan="4" style="text-align: right;">Totales</th>
                                        <th style="text-align: right; border: 1px solid #B0B0B0;">$'.number_format($totalPag,0, ",", ".").'</td>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" >$'.number_format($totalDesc*-1,0, ",", ".").'</td>
                                    </tr>
                            ';
                            $totalGen = $totalPag + $totalDesc;
                            if($totalGen<0){
                                $totalGen=0;
                            }
                            $html.='<tr>
                                        
                                        <th colspan="3" style="text-align: right;" >Neto a pagar</th>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" colspan="2">$'.number_format($totalGen,0, ",", ".").'</td>
                                        
                                    </tr>
                            ';
                            $valorText = $this->convertir(($totalPag + $totalDesc));
                            
                        $html.='</table>
                    </div>
                    <div style="border: 2px solid #000; padding: 10px 20px; font-size: 10px; font-weight: bold; margin-bottom: 5px;">
                        El valor neto a pagar es: '.strtoupper($valorText).' PESOS M/CTE
                    </div><br>';
                    if(sizeof($itemsBoucherPagoFueraNomina)>0){
                        $html.='<div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                        <tr>
                                <th style="background: #CCC; text-align: center;" colspan="5">Fuera de nómina</th>
                        </tr>
                        <tr>
                            <th style="background: #CCC; text-align: center;">Conceptos</th>
                            <th style="background: #CCC; text-align: center;">Cantidad</th>
                            <th style="background: #CCC; text-align: center;">Unidad</th>
                            <th style="background: #CCC; text-align: center;">Pagos</th>
                            <th style="background: #CCC; text-align: center;">Descuentos</th>
                        </tr>
                        ';
                        foreach($itemsBoucherPagoFueraNomina as $itemBoucherPagoFueraNomina){
                            $html.='<tr style="border-bottom: 1px solid #B0B0B0;">
                            <td style="border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->nombre.'</td>
                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->cantidad.'</td>
                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->tipoUnidad.'</td>';
                            
                            if($itemBoucherPagoFueraNomina->valor > 0){
                                $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPagoFueraNomina->valor,0, ",", ".").'</td>
                                    <td style="border-bottom: 1px solid #B0B0B0;"></td>';
                                
                            }
                            else{
                                $html.='<td style="border-bottom: 1px solid #B0B0B0;"></td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPagoFueraNomina->valor*-1,0, ",", ".").'</td>';
                                
                            }
                            $html.='</tr>';
                        }
                        $html.='</table></div><br> 
                        </div>
                        <div class="page_break"></div>
                        <div class="page">';
                    }
                    $html.='<div style="border: 2px solid #000; padding: 0px 10px; margin-bottom: 5px;">
                        <center><h4 style="margin:0px;" >Observaciones</h4></center>
                        <table>
                            <tr>
                                <th style="background: #CCC; text-align: center;">CONSTANCIAS - Se hace constar expresamente los siguiente:</th>
                            </tr>
                            <td style="font-size: 8px; text-align: justify;">'.$mensajeGen[9]->html.'</td>
                        </table>
                        <table style="width: 100%;">
                            <tr>
                                <th style="border: 1px solid #000; width:33%;">ELABORÓ: '.$userSolicita.'</th>
                                <th style="border: 1px solid #000; width:33%;">REVISÓ: '.$userAprueba.'</th>
                                <th style="border: 1px solid #000; width:33%;">APROBÓ:</th>
                            </tr>
                        </table>
                    </div>
                    <div style="position: absolute; bottom: 40px; width:100%;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td>COLABORADOR</td>
                                <td></td>
                                <td>LA EMPRESA</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td>Cédula o NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                                <td>NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                            </tr>
                            <tr>
                                <td colspan="3"></td>
                                <td>Fecha de elaboración:&nbsp;&nbsp;&nbsp; '.date("d/m/Y").'</td>
                            </tr>
                        </table>
                    </div>

                </div>';
            }
            else{
                $html.='<div class="page">
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                        ';
                        $html.='                        
                        <table class="tituloTable">
                            <tr>
                                <td rowspan="2">
                                '.(isset($empresayLiquidacion->logoEmpresa) ? '<img style="max-width: 50px; max-height: 50px; margin-right: 5px;" src="data:image/png;base64,'.$base64.'" class="logoEmpresa" />' : '').'
                                </td>
                                <td valign="bottom"><b>'.$empresayLiquidacion->razonSocial.'</b></td>
                            </tr>
                            <tr>
                                <td  valign="top"><b>'.number_format($empresayLiquidacion->documento,0, ",", ".").'-'.$empresayLiquidacion->digitoVerificacion.'</b></td>
                            </tr>
                        </table>
                        <center>
                            <h2 style="margin:0; margin-bottom: 10px;">Comprobante pago nómina</h2>
                        </center>
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th>
                                    Nómina
                                </th>
                                <td>
                                    '.$empresayLiquidacion->nom_nombre.'
                                </td>
                                <th>
                                    Periodo liquidación
                                </th>
                                <td>
                                    '.date("Y",strtotime($empresayLiquidacion->fechaInicio))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaInicio)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaInicio)).' 
                                    a
                                    '.date("Y",strtotime($empresayLiquidacion->fechaFin))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaFin)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaFin)).' 
                                </td>
                            </tr>
                            <tr>
                                <th>Empleado</th>
                                <td>'.$empleado->primerApellido." ".$empleado->segundoApellido." ".$empleado->primerNombre." ".$empleado->segundoNombre.'</td>
                                <th>Salario</th>
                                <td>$ '.number_format($conceptoSalario->valor,0, ",", ".").'</td>
                            </tr>
                            <tr>
                                <th>Identificación</th>
                                <td>'.$empleado->tipoidentificacion.' '.$empleado->numeroIdentificacion.'</td>
                                <th>Cargo</th>
                                <td>'.$empleado->nombreCargo.'</td>
                            </tr>
                            <tr>
                                <th>Entidad Bancaria</th>
                                <td>'.(isset($entidadBancaria->razonSocial) ? $entidadBancaria->razonSocial : "").'</td>
                                <th>Cuenta</th>
                                <td>'.(isset($entidadBancaria->numeroCuenta) ? $entidadBancaria->numeroCuenta : "").'</td>
                            </tr>
                            <tr>
                                <th>EPS</th>
                                <td>'.($salud->razonSocial ?? "").'</td>
                                <th>Fondo Pensiones</th>
                                <td>'.(isset($pension->razonSocial) ? $pension->razonSocial : "").'</td>
                            </tr>
                        </table>
                    </div><br>
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                                <th style="background: #d89290; text-align: center;" colspan="2">Devengado</th>
                                <th style="background: #d89290; text-align: center;">Deducciones</th>                        
                            </tr>
                            <tr>
                                <th style="background: #CCC; text-align: center;">Conceptos Liquidados</th>
                                <th style="background: #CCC; text-align: center;">Cantidad</th>
                                <th style="background: #CCC; text-align: center;">Unidad</th>
                                <th style="background: #CCC; text-align: center;">Pagos</th>
                                <th style="background: #CCC; text-align: center;">Beneficios</th>
                                <th style="background: #CCC; text-align: center;">Descuentos</th>
                            </tr>';
                            $totalDesc = 0;
                            $totalPag = 0;
                
                            foreach($idItemBoucherPago as $itemBoucherPago){
                                $html.='<tr style="border-bottom: 1px solid #B0B0B0;">
                                    <td style="border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->nombre.'</td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->cantidad.'</td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->tipoUnidad.'</td>';
                                    
                                    if($itemBoucherPago->valor > 0){
                                        $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor,0, ",", ".").'</td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>';
                                        $totalPag = $totalPag + $itemBoucherPago->valor;
                                    }
                                    else{
                                        $html.='<td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor*-1,0, ",", ".").'</td>';
                                        $totalDesc = $totalDesc + $itemBoucherPago->valor;
                                    }

                                $html.='</tr>';
                            }
                            


                            $html.='<tr>
                                        
                                        <th colspan="3" style="text-align: right;">Totales</th>
                                        <td style="text-align: right; border: 1px solid #B0B0B0;">$'.number_format($totalPag,0, ",", ".").'</td>
                                        <td style="text-align: right; border: 1px solid #B0B0B0;"></td>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" >$'.number_format($totalDesc*-1,0, ",", ".").'</td>
                                    </tr>
                            ';
                            $totalGen = $totalPag + $totalDesc;
                            if($totalGen<0){
                                $totalGen=0;
                            }
                            
                            $html.='<tr>
                                        
                                        <th colspan="3" style="text-align: right;" >Neto a pagar en cuenta nómina</th>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" colspan="2">$'.number_format($totalGen,0, ",", ".").'</td>
                                        
                                    </tr>
                            ';
                            
                        $html.='</table>

                    </div>
                    <br>';

                    if(sizeof($itemsBoucherPagoFueraNomina)>0){
                        $html.='<div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                        <tr>
                                <th style="background: #CCC; text-align: center;" colspan="5">Fuera de nómina</th>
                        </tr>
                        <tr>
                            <th style="background: #CCC; text-align: center;">Conceptos</th>
                            <th style="background: #CCC; text-align: center;">Cantidad</th>
                            <th style="background: #CCC; text-align: center;">Unidad</th>
                            <th style="background: #CCC; text-align: center;">Pagos</th>
                            <th style="background: #CCC; text-align: center;">Descuentos</th>
                        </tr>
                        ';
                        foreach($itemsBoucherPagoFueraNomina as $itemBoucherPagoFueraNomina){
                            $html.='<tr style="border-bottom: 1px solid #B0B0B0;">
                            <td style="border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->nombre.'</td>
                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->cantidad.'</td>
                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->tipoUnidad.'</td>';
                            
                            if($itemBoucherPagoFueraNomina->valor > 0){
                                $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPagoFueraNomina->valor,0, ",", ".").'</td>
                                    <td style="border-bottom: 1px solid #B0B0B0;"></td>';
                                
                            }
                            else{
                                $html.='<td style="border-bottom: 1px solid #B0B0B0;"></td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPagoFueraNomina->valor*-1,0, ",", ".").'</td>';
                                
                            }
                            $html.='</tr>';
                        }
                        $html.='</table></div><br>';
                    }

                    $html.='<div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="2">Bases para cálculo de seguridad social</th>
                            </tr>
                            <tr>
                                <td>Ingreso Base Cotización Salud</td><td style="text-align: right;">$'.number_format($empresayLiquidacion->ibc_eps,0, ",", ".").'</td>
                            </tr>
                            <tr>
                                <td>Ingreso Base Cotización Pension</td><td style="text-align: right;">$'.number_format($empresayLiquidacion->ibc_afp,0, ",", ".").'</td>
                            </tr>
                        </table>
                    </div>
                    <br>
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="2">Mensaje Empresarial</th>
                            </tr>
                            <tr>
                                <td style="text-align: justify;">'.$mensajeGen[8]->html.'</td>
                            </tr>
                        </table>
                    </div>
                    <br>
                    <div style="position: absolute; bottom: 40px; width:100%;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td>COLABORADOR</td>
                                <td></td>
                                <td>LA EMPRESA</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td>Cédula o NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                                <td>NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                            </tr>
                            <tr>
                                <td colspan="3"></td>
                                <td>Fecha de elaboración:&nbsp;&nbsp;&nbsp; '.date("d/m/Y").'</td>
                            </tr>
                        </table>

                    </div>
                </div>
                ';
            }
            if(sizeof($novedadesVacacionActual) > 0){
                
                $novedadesRetiro = DB::table("novedad","n")
                ->select("r.fecha")
                ->join("retiro AS r", "r.idRetiro","=","n.fkRetiro")
                ->where("n.fkEmpleado", "=", $empleado->idempleado)
                ->whereRaw("n.fkPeriodoActivo in(
                    SELECT p.idPeriodo from periodo as p where p.fkEmpleado = '".$empleado->idempleado."' and p.fkEstado = '1'
                )")
                ->whereIn("n.fkEstado",["7", "8"])
                ->whereNotNull("n.fkRetiro")
                ->whereBetween("n.fechaRegistro",[$empresayLiquidacion->fechaInicio, $empresayLiquidacion->fechaFin])->first();
                $fechaFinalVaca = $empresayLiquidacion->fechaFin;
                $periodoPagoVac = $this->days_360($empleado->fechaIngreso,$empresayLiquidacion->fechaFin) + 1 ;
                if(isset($novedadesRetiro)){
                    if(strtotime($empresayLiquidacion->fechaFin) > strtotime($novedadesRetiro->fecha)){
                        $periodoPagoVac = $this->days_360($empleado->fechaIngreso,$novedadesRetiro->fecha) + 1 ;
                        $fechaFinalVaca = $novedadesRetiro->fecha;
                    }
                }

                $diasVac = $periodoPagoVac * 15 / 360;

                $novedadesVacacion = DB::table("novedad","n")
                ->select("v.*", "c.nombre","c.idconcepto", "ibpn.valor")
                ->join("concepto as c","c.idconcepto","=","n.fkConcepto")
                ->join("vacaciones as v","v.idVacaciones","=","n.fkVacaciones")
                ->join("item_boucher_pago_novedad as ibpn","ibpn.fkNovedad","=","n.idNovedad")
                ->join("item_boucher_pago as ibp","ibp.idItemBoucherPago","=","ibpn.fkItemBoucher")
                ->where("n.fkEmpleado","=",$empleado->idempleado)
                ->whereRaw("n.fkPeriodoActivo in(
                    SELECT p.idPeriodo from periodo as p where p.fkEmpleado = '".$empleado->idempleado."' and p.fkEstado = '1'
                )")
                ->where("ibp.fkBoucherPago","<>",$idBoucherPago)
                ->whereIn("n.fkEstado",["7"]) // Pagada o sin pagar-> no que este eliminada
                ->whereNotNull("n.fkVacaciones")
                ->get();
                //$diasVac = $totalPeriodoPagoAnioActual * 15 / 360;
                foreach($novedadesVacacion as $novedadVacacion){
                    $diasVac = $diasVac - $novedadVacacion->diasCompensar;
                }
                if(isset($diasVac) && $diasVac < 0){
                    $diasVac = 0;
                }

            
                
                $html.='<div class="page_break"></div>
                    <div class="page">
                        <div style="border: 2px solid #000; padding: 10px 20px;">
                            <table class="tituloTable">
                                <tr>
                                    <td rowspan="2">
                                    '.(isset($empresayLiquidacion->logoEmpresa) ? '<img style="max-width: 50px; max-height: 50px; margin-right: 5px;" src="data:image/png;base64,'.$base64.'" class="logoEmpresa" />' : '').'
                                    </td>
                                    <td valign="bottom"><b>'.$empresayLiquidacion->razonSocial.'</b></td>
                                </tr>
                                <tr>
                                    <td  valign="top"><b>'.number_format($empresayLiquidacion->documento,0, ",", ".").'-'.$empresayLiquidacion->digitoVerificacion.'</b></td>
                                </tr>
                            </table>
                            <center>
                                <h2 style="margin:0; margin-bottom: 10px;">Comprobante de Pago de Vacaciones</h2>
                            </center>
                            <table style="width: 100%; text-align: left;">
                                <tr>
                                    <th>Empleado</th>
                                    <td>'.$empleado->primerApellido." ".$empleado->segundoApellido." ".$empleado->primerNombre." ".$empleado->segundoNombre.'</td>
                                    <th>Fecha ingreso</th>
                                    <td>'.date("Y",strtotime($empleado->fechaIngreso))."/".$arrMeses[date("m",strtotime($empleado->fechaIngreso)) - 1].'/'.date("d",strtotime($empleado->fechaIngreso)).'</td>
                                </tr>
                                <tr>
                                    <th>Identificación</th>
                                    <td>'.$empleado->tipoidentificacion.' '.$empleado->numeroIdentificacion.'</td>
                                    <th>Cargo</th>
                                    <td>'.$empleado->nombreCargo.'</td>
                                </tr>
                                <tr>
                                    <th>Días Pendientes Consolidado</th>
                                    <td>'.round($diasVac,2).'</td>
                                    <th>Fecha Corte Consolidado:</th>
                                    <td>'.date("Y",strtotime($fechaFinalVaca))."/".$arrMeses[date("m",strtotime($fechaFinalVaca)) - 1].'/'.date("d",strtotime($fechaFinalVaca)).'</td>
                                </tr>
                                
                            </table>
                    </div>
                    <br>
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                    <table style="width: 100%; text-align: left; font-size: 10px;">
                        <tr>
                            <th style="background: #d89290; text-align: center; font-size: 10px;" colspan="11">Liquidación de Vacaciones</th>                         
                        </tr>
                        <tr>
                            <th style="background: #CCC; text-align: center; font-size: 10px;" rowspan="2">Tipo Movimiento</th>
                            <th style="background: #CCC; text-align: center; font-size: 10px;" colspan="2">Periodo Causación</th>
                            <th style="background: #CCC; text-align: center; font-size: 10px;" colspan="4">Periodo Vacaciones</th>
                            <th style="background: #CCC; text-align: center; font-size: 10px;" colspan="2">Días Pagados</th>
                            <th style="background: #CCC; text-align: center; font-size: 10px;" rowspan="2">Promedio<br>Diario</th>
                            <th style="background: #CCC; text-align: center; font-size: 10px;" rowspan="2">Valor<br>Liquidado</th>
                        </tr>
                        <tr>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Fecha Inicio</th>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Fecha Fin</th>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Fecha Inicio</th>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Fecha Fin</th>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Días</th>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Regreso</th>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Tiempo</th>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Dinero</th>
                        </tr>
                        
                ';
                $totalVac = 0;
                foreach($novedadesVacacionActual as $novedadVacacion){
                    $tipoMov = str_replace("VACACIONES", "", $novedadVacacion->nombre);
                    $html.='
                        <tr>
                            <td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">'.$tipoMov.'</td>
                            <td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.$empleado->fechaIngreso.'</td>
                            <td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.$novedadVacacion->fechaInicio.'</td>';
                        if($novedadVacacion->idconcepto == 29){
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.$novedadVacacion->fechaInicio.'</td>';
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.$novedadVacacion->fechaFin.'</td>';
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.$novedadVacacion->diasCompensar.'</td>';
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.date("Y-m-d",strtotime($novedadVacacion->fechaFin."+1 day")).'</td>';
                        }
                        else{
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;"></td>';
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;"></td>';
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">'.$novedadVacacion->diasCompensar.'</td>';
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;"></td>';
                        }
                        
                        $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">'.$novedadVacacion->diasCompensar.'</td>';
                        $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">$'.number_format($novedadVacacion->valor,0, ",", ".").'</td>';
                        $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">$'.number_format($novedadVacacion->valor/$novedadVacacion->diasCompensar,0, ",", ".").'</td>';
                        $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">$'.number_format($novedadVacacion->valor,0, ",", ".").'</td>';

                        $html.='</tr>
                    ';
                    $totalVac = $totalVac + $novedadVacacion->valor;
                    if($totalVac<0){
                        $totalVac=0;
                    }
                }    
                $html.='
                    <tr>
                        <th style="text-align: right; font-size: 10px;" colspan="9">TOTAL LIQUIDADO VACACIONES</th>
                        <td style="text-align: right; border: 1px solid #B0B0B0; font-size: 10px;" colspan="2">$'.number_format($totalVac,0, ",", ".").'</td>
                    </tr>            
                    </table>
                </div>
                <br>
                    <center><h4>Observaciones</h4></center>
                <br>
                <div style="border: 2px solid #000; padding: 10px 20px; min-height: 50px;">
                <br><br><br>
                </div>
                <div style="position: absolute; bottom: 40px; width:100%;">
                    <table style="width: 100%; text-align: left;">
                        <tr>
                            <td>COLABORADOR</td>
                            <td></td>
                            <td>LA EMPRESA</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>Cédula o NIT</td>
                            <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                            <td>NIT</td>
                            <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                        </tr>
                        <tr>
                            <td colspan="3"></td>
                            <td>Fecha de elaboración:&nbsp;&nbsp;&nbsp; '.date("d/m/Y").'</td>
                        </tr>
                    </table>
                    <table style="width: 100%;">
                        <tr>
                            <th style="border: 1px solid #000; width:33%;">ELABORÓ: '.$userSolicita.'</th>
                            <th style="border: 1px solid #000; width:33%;">REVISÓ: '.$userAprueba.'</th>
                            <th style="border: 1px solid #000; width:33%;">APROBÓ:</th>
                        </tr>
                    </table>
                </div>
                ';
            }            

            
            $html.='
            </body>
        </html>
        ';
        
        $dompdf->loadHtml($html ,'UTF-8');

        // (Optional) Setup the paper size and orientation
        $dompdf->setPaper('Letter', 'portrait');
        // Render the HTML as PDF
        $dompdf->render();
        // @php-ignore  
        $dompdf->getCanvas()->get_cpdf()->setEncryption($empleado->numeroIdentificacion, $empleado->numeroIdentificacion);


        $pdf = $dompdf->output();
        return $pdf;       
    }

    public function boucherPagoPdf($idBoucherPago){

        $empresayLiquidacion = DB::table("empresa", "e")
        ->select("e.*", "ln.*", "n.nombre as nom_nombre", "bp.*")
        ->join("nomina as n","n.fkEmpresa", "e.idempresa")
        ->join("liquidacionnomina as ln","ln.fkNomina", "n.idNomina")
        ->join("boucherpago as bp","bp.fkLiquidacion", "ln.idLiquidacionNomina")
        ->where("bp.idBoucherPago","=",$idBoucherPago)
        ->first();
    
        $userSolicita = "";
        if(isset($empresayLiquidacion->fkUserSolicita)){
            $usuario = DB::table('users')->select(
                'users.username',
                'users.email',
                'datospersonales.primerNombre',
                'datospersonales.primerApellido',
                'datospersonales.foto',
                'empleado.idempleado'
            )->leftJoin('empleado', 'users.fkEmpleado', 'empleado.idempleado',"left")
            ->leftJoin('empresa', 'empresa.idempresa', 'empleado.fkEmpresa',"left")
            ->leftJoin('datospersonales', 'datospersonales.idDatosPersonales', 'empleado.fkDatosPersonales',"left")
            ->where('users.id', $empresayLiquidacion->fkUserSolicita)
            ->first();
            
            if(isset($usuario->primerNombre)){
                $userSolicita = $usuario->primerNombre." ".$usuario->primerApellido;
            }
            else{
                $userSolicita = $usuario->username;
            }
        }

        $userAprueba = "";
        if(isset($empresayLiquidacion->fkUserAprueba)){
            $usuario = DB::table('users')->select(
                'users.username',
                'users.email',
                'datospersonales.primerNombre',
                'datospersonales.primerApellido',
                'datospersonales.foto',
                'empleado.idempleado'
            )
            ->leftJoin('empleado', 'users.fkEmpleado', 'empleado.idempleado')
            ->leftJoin('empresa', 'empresa.idempresa', 'empleado.fkEmpresa')
            ->leftJoin('datospersonales', 'datospersonales.idDatosPersonales', 'empleado.fkDatosPersonales')
            ->where('users.id',"=", $empresayLiquidacion->fkUserAprueba)
            ->first();
            
            if(isset($usuario->primerNombre)){
                $userAprueba = $usuario->primerNombre." ".$usuario->primerApellido;
            }
            else{
                $userAprueba = $usuario->username;
            }
        }


        $empleado = DB::table("empleado","e")
        ->select("e.idempleado", "p.fechaInicio as fechaIngreso",
        "e.tipoRegimen","p.tipoRegimen as tipoRegimenPeriodo", "p.fkNomina",
        "dp.primerNombre","dp.segundoNombre", 
        "dp.primerApellido","dp.segundoApellido","ti.nombre as tipoidentificacion", 
        "dp.numeroIdentificacion", "cargo.nombreCargo", "cargo2.nombreCargo as nombreCargoPeriodo",
        "p.idPeriodo")
        ->join("boucherpago as bp","bp.fkEmpleado", "e.idempleado")
        ->join("periodo as p","p.idPeriodo", "bp.fkPeriodoActivo")
        ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion")
        ->join("cargo","cargo.idCargo","=","e.fkCargo", "left")
        ->join("cargo as cargo2","cargo2.idCargo","=","p.fkCargo", "left")
        ->where("bp.idBoucherPago","=",$idBoucherPago)
        ->first();
        
        $empleado->tipoRegimen = ($empleado->tipoRegimenPeriodo ?? $empleado->tipoRegimen);
        $empleado->nombreCargo = ($empleado->nombreCargoPeriodo ?? $empleado->nombreCargo);

        
        $nomina = DB::table("nomina","n")
        ->where("n.idNomina","=",$empleado->fkNomina)->first();


        $pension = DB::table("tercero", "t")->
        select(["t.razonSocial", "t.numeroIdentificacion", "t.idTercero", "ti.nombre as tipoidentificacion", "t.digitoVer"])
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","t.fkTipoIdentificacion")
        ->join("afiliacion as a","t.idTercero", "=","a.fkTercero")
        ->where("a.fkEmpleado","=",$empleado->idempleado)
        ->where("a.fkTipoAfilicacion","=","4") //4-Pensión Obligatoria 
        ->where("a.fkPeriodoActivo","=",$empresayLiquidacion->fkPeriodoActivo)
        ->first();

        $salud = DB::table("tercero", "t")->select(["t.razonSocial", "t.numeroIdentificacion", "t.idTercero",
        "ti.nombre as tipoidentificacion", "t.digitoVer"])
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","t.fkTipoIdentificacion")
        ->join("afiliacion as a","t.idTercero", "=","a.fkTercero")
        ->where("a.fkEmpleado","=",$empleado->idempleado)
        ->where("a.fkTipoAfilicacion","=","3") //3-Salud
        ->where("a.fkPeriodoActivo","=",$empresayLiquidacion->fkPeriodoActivo)
        ->first();

        $cesantiasEmp = DB::table("tercero", "t")->select(["t.razonSocial", "t.numeroIdentificacion", "t.idTercero",
            "ti.nombre as tipoidentificacion", "t.digitoVer"])
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","t.fkTipoIdentificacion")
        ->join("afiliacion as a","t.idTercero", "=","a.fkTercero")
        ->where("a.fkEmpleado","=",$empleado->idempleado)
        ->where("a.fkTipoAfilicacion","=","1") //2-CCF
        ->where("a.fkPeriodoActivo","=",$empresayLiquidacion->fkPeriodoActivo)
        ->first();

        $entidadBancaria = DB::table("tercero", "t")->select(["t.razonSocial", "e.numeroCuenta"])
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","t.fkTipoIdentificacion","left")
        ->join("empleado as e", "e.fkEntidad", "=","t.idTercero","left")
        ->where("e.idempleado","=",$empleado->idempleado)
        ->first();

        $entidadBancariaPeriodo = DB::table("tercero", "t")->select(["t.razonSocial", "p.numeroCuenta"])
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","t.fkTipoIdentificacion","left")
        ->join("periodo as p", "p.fkEntidad", "=","t.idTercero","left")
        ->where("p.idPeriodo","=",$empresayLiquidacion->fkPeriodoActivo)
        ->first();

        if(!isset($entidadBancaria)){
            $entidadBancaria = $entidadBancariaPeriodo;
        }
        if(isset($entidadBancaria)){
            $entidadBancaria->razonSocial = ($entidadBancariaPeriodo->razonSocial ?? $entidadBancaria->razonSocial);
            $entidadBancaria->numeroCuenta = ($entidadBancariaPeriodo->numeroCuenta ?? $entidadBancaria->numeroCuenta);
        }
        

        $idItemBoucherPago = DB::table("item_boucher_pago","ibp")
        ->join("concepto AS c","c.idconcepto","=", "ibp.fkConcepto")
        ->where("ibp.fkBoucherPago","=",$idBoucherPago)
        ->get();

        $itemsBoucherPagoFueraNomina = DB::table("item_boucher_pago_fuera_nomina","ibpfn")
        ->join("concepto AS c","c.idconcepto","=", "ibpfn.fkConcepto")
        ->where("ibpfn.fkBoucherPago","=",$idBoucherPago)
        ->get();

        $itemsBoucherPagoFueraNominaCesTras = DB::table("item_boucher_pago_fuera_nomina","ibpfn")
        ->select("ibpfn.*","c.*")
        ->join("concepto AS c","c.idconcepto","=", "ibpfn.fkConcepto")
        ->join("boucherpago as bp","bp.idBoucherPago","=","ibpfn.fkBoucherPago")
        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
        ->whereRaw("MONTH(ln.fechaLiquida)= MONTH('".$empresayLiquidacion->fechaLiquida."')")
        ->whereRaw("YEAR(ln.fechaLiquida)= YEAR('".$empresayLiquidacion->fechaLiquida."')")
        ->where("bp.fkEmpleado","=", $empleado->idempleado)
        ->where("bp.fkPeriodoActivo","=", $empresayLiquidacion->fkPeriodoActivo)
        ->where("ln.idLiquidacionNomina","<>",$empresayLiquidacion->idLiquidacionNomina)
        ->where("ln.fkTipoLiquidacion","=","11")
        ->where("ln.fkEstado","=","5")
        ->get();


        foreach($itemsBoucherPagoFueraNominaCesTras as $itemBoucherPagoFueraNominaCesTras){
            $itemsBoucherPagoFueraNomina->push($itemBoucherPagoFueraNominaCesTras);
        }




        $periodoPasadoReintegro = DB::table("periodo")
        ->where("fkEstado","=","2")
        ->where("fkEmpleado", "=", $empleado->idempleado)
        ->where("fechaInicio","<=",$empresayLiquidacion->fechaInicio)
        ->where("fechaFin",">=",$empresayLiquidacion->fechaInicio)
        ->where("fkNomina","=",$empresayLiquidacion->fkNomina)
        ->first();
        
        if(isset($periodoPasadoReintegro)){
            $conceptoSalario = new stdClass;
            $conceptoSalario->valor = $periodoPasadoReintegro->salario;
        }
        else{
            $conceptoSalario = DB::table("conceptofijo")
            ->where("fkEmpleado","=",$empleado->idempleado)
            ->where("fkPeriodoActivo","=", $empresayLiquidacion->fkPeriodoActivo)
            ->whereIn("fkConcepto",[1,2,53,54,154])->first();
        }
        



        //VACACIONES
        $novedadesVacacionActual = DB::table("novedad","n")
        ->select("v.*", "c.nombre","c.idconcepto", "ibpn.valor")
        ->join("concepto as c","c.idconcepto", "=","n.fkConcepto")
        ->join("vacaciones as v","v.idVacaciones","=","n.fkVacaciones")
        ->join("item_boucher_pago_novedad as ibpn","ibpn.fkNovedad","=","n.idNovedad")
        ->join("item_boucher_pago as ibp","ibp.idItemBoucherPago","=","ibpn.fkItemBoucher")
        ->where("ibp.fkBoucherPago","=",$idBoucherPago)
        ->whereIn("n.fkEstado",["8","7"]) // Pagada -> no que este eliminada
        ->whereNotNull("n.fkVacaciones")
        ->get();
        //$diasVac = $totalPeriodoPagoAnioActual * 15 / 360;
        





        $base64 = "";
        if(is_file($this->rutaBaseImagenes.'storage/logosEmpresas/'.$empresayLiquidacion->logoEmpresa)){
            $imagedata = file_get_contents($this->rutaBaseImagenes.'storage/logosEmpresas/'.$empresayLiquidacion->logoEmpresa);
                    // alternatively specify an URL, if PHP settings allow
            $base64 = base64_encode($imagedata);
        }
        else{
            unset($empresayLiquidacion->logoEmpresa);
        }
        $mensajeGen = array();
        $mensajeGen[8] = DB::table("mensaje")->where("idMensaje","=","8")->first();
        $mensajeGen[9] = DB::table("mensaje")->where("idMensaje","=","9")->first();
        
        $arrMeses = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];

        $dompdf = new Dompdf();
        $dompdf->getOptions()->setChroot($this->rutaBaseImagenes);
        $dompdf->getOptions()->setIsPhpEnabled(true);
        $html='
        <!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"</head><body>
        <body>
            <style>
            *{
                -webkit-hyphens: auto;
                -ms-hyphens: auto;
                hyphens: auto;
                font-family: sans-serif;                
            }
            td{
                text-align: left;
                font-size: 8px;
            }
            th{
                text-align: left;
                font-size: 8px;
            }
            .liquida td, .liquida th{
                font-size:9px;
            }

            @page { 
                margin: 0in;
            }
            .page {
                top: .3in;
                right: .3in;
                bottom: .3in;
                left: .3in;
                position: absolute;
                z-index: -1000;
                min-width: 7in;
                min-height: 11.7in;
                
            }
            .page_break { 
                page-break-before: always; 
            }
            .tituloTable td b{
                font-size: 14px;
            }
            </style>
            ';
            $novedadesRetiro = DB::table("novedad","n")
            ->select("r.fecha", "r.fechaReal","mr.nombre as motivoRet")
            ->join("retiro AS r", "r.idRetiro","=","n.fkRetiro")
            ->join("motivo_retiro as mr","mr.idMotivoRetiro","=","r.fkMotivoRetiro")
            ->where("n.fkEmpleado", "=", $empleado->idempleado)
            ->whereIn("n.fkEstado",["7", "8"])
            ->whereNotNull("n.fkRetiro")
            ->whereBetween("n.fechaRegistro",[$empresayLiquidacion->fechaInicio, $empresayLiquidacion->fechaFin])->first();


            if($empresayLiquidacion->fkTipoLiquidacion == "7"){
                $html.='<div class="page liquida">
                <div style="border: 2px solid #000; padding: 5px 10px; font-size: 15px; margin-bottom: 5px;">
                    <table class="tituloTable">
                        <tr>
                            <td rowspan="2">
                            '.(isset($empresayLiquidacion->logoEmpresa) ? '<img style="max-width: 50px; max-height: 50px; margin-right: 5px;" src="data:image/png;base64,'.$base64.'" class="logoEmpresa" />' : '').'
                            </td>
                            <td valign="bottom"><b>'.$empresayLiquidacion->razonSocial.'</b></td>
                        </tr>
                        <tr>
                            <td  valign="top"><b>'.number_format($empresayLiquidacion->documento,0, ",", ".").'-'.$empresayLiquidacion->digitoVerificacion.'</b></td>
                        </tr>
                    </table>
                    <center>
                        <h2 style="margin:0; margin-bottom: 0px; font-size: 20px;">COMPROBANTE DE PAGO DE NÓMINA</h2><br>
                    </center>
                    <table style="width: 100%;">
                        <tr>
                            <th>Nómina</th>
                            <td>'.$empresayLiquidacion->nom_nombre.'</td>
                            <th>Período liquidación</th>
                            <td>
                                '.date("Y",strtotime($empresayLiquidacion->fechaInicio))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaInicio)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaInicio)).' 
                                a
                                '.date("Y",strtotime($empresayLiquidacion->fechaFin))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaFin)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaFin)).' 
                            </td>
                        </tr>
                        <tr>
                            <th>Empleado</th>
                            <td>'.$empleado->primerApellido." ".$empleado->segundoApellido." ".$empleado->primerNombre." ".$empleado->segundoNombre.'</td>
                            <th>Salario</th>
                            <td>$ '.number_format($conceptoSalario->valor,0, ",", ".").'</td>
                        </tr>
                        <tr>
                            <th>Identificación</th>
                            <td>'.$empleado->tipoidentificacion.' '.$empleado->numeroIdentificacion.'</td>
                            <th>Cargo</th>
                            <td>'.$empleado->nombreCargo.'</td>
                        </tr>
                        <tr>
                            <th>Entidad Bancaria</th>
                            <td>'.(isset($entidadBancaria->razonSocial) ? $entidadBancaria->razonSocial : "").'</td>
                            <th>Cuenta</th>
                            <td>'.(isset($entidadBancaria->numeroCuenta) ? $entidadBancaria->numeroCuenta : "").'</td>
                        </tr>
                        <tr>
                            <th>EPS</th>
                            <td>'.($salud->razonSocial ?? "").'</td>
                            <th>Fondo Pensiones</th>
                            <td>'.(isset($pension->razonSocial) ? $pension->razonSocial : "").'</td>
                        </tr>
                        
                    </table>
                    <br>
                </div>
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                                <th style="background: #d89290; text-align: center;" colspan="2">Devengado</th>
                                <th style="background: #d89290; text-align: center;">Deducciones</th>                        
                            </tr>
                            <tr>
                                <th style="background: #CCC; text-align: center;">Conceptos Liquidados</th>
                                <th style="background: #CCC; text-align: center;">Cantidad</th>
                                <th style="background: #CCC; text-align: center;">Unidad</th>
                                <th style="background: #CCC; text-align: center;">Pagos</th>
                                <th style="background: #CCC; text-align: center;">Beneficios</th>
                                <th style="background: #CCC; text-align: center;">Descuentos</th>
                            </tr>';
                            $totalDesc = 0;
                            $totalPag = 0;
                
                            foreach($idItemBoucherPago as $itemBoucherPago){
                                $html.='<tr style="border-bottom: 1px solid #B0B0B0;">
                                    <td style="border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->nombre.'</td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.((15 / 180) * $itemBoucherPago->cantidad).'</td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->tipoUnidad.'</td>';
                                    
                                    if($itemBoucherPago->valor > 0){
                                        $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor,0, ",", ".").'</td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>';
                                        $totalPag = $totalPag + $itemBoucherPago->valor;
                                    }
                                    else{
                                        $html.='<td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor*-1,0, ",", ".").'</td>';
                                        $totalDesc = $totalDesc + $itemBoucherPago->valor;
                                    }

                                $html.='</tr>';
                            }
                            $html.='<tr>
                                        
                                        <th colspan="3" style="text-align: right;">Totales</th>
                                        <td style="text-align: right; border: 1px solid #B0B0B0;">$'.number_format($totalPag,0, ",", ".").'</td>
                                        <td style="text-align: right; border: 1px solid #B0B0B0;"></td>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" >$'.number_format($totalDesc*-1,0, ",", ".").'</td>
                                    </tr>
                            ';
                            $totalGen = $totalPag + $totalDesc;
                            if($totalGen<0){
                                $totalGen=0;
                            }
                            $html.='<tr>
                                        
                                        <th colspan="3" style="text-align: right;" >Neto a pagar en cuenta nómina</th>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" colspan="2">$'.number_format($totalGen,0, ",", ".").'</td>
                                        
                                    </tr>
                            ';
                            
                        $html.='</table>

                    </div>
                    <br>
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="2">Bases para cálculo de seguridad social</th>
                            </tr>
                            <tr>
                                <td>Ingreso Base Cotización Salud</td><td style="text-align: right;">$'.number_format($empresayLiquidacion->ibc_eps,0, ",", ".").'</td>
                            </tr>
                            <tr>
                                <td>Ingreso Base Cotización Pension</td><td style="text-align: right;">$'.number_format($empresayLiquidacion->ibc_afp,0, ",", ".").'</td>
                            </tr>
                        </table>
                    </div>
                    <br>
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="2">Mensaje Empresarial</th>
                            </tr>
                            <tr>
                                <td style="text-align: justify;">'.$mensajeGen[8]->html.'</td>
                            </tr>
                        </table>
                    </div>
                    <br>
                    <div style="position: absolute; bottom: 40px; width:100%;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td>COLABORADOR</td>
                                <td></td>
                                <td>LA EMPRESA</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td>Cédula o NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                                <td>NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                            </tr>
                            <tr>
                                <td colspan="3"></td>
                                <td>Fecha de elaboración:&nbsp;&nbsp;&nbsp; '.date("d/m/Y").'</td>
                            </tr>
                        </table>

                    </div>
                </div>';
            }
            else if(($empresayLiquidacion->fkTipoLiquidacion == "2" || $empresayLiquidacion->fkTipoLiquidacion == "3") && isset($novedadesRetiro->fecha)){
        
                $contrato = DB::table("contrato","c")
                ->join("tipocontrato as tc","tc.idtipoContrato", "=","c.fkTipoContrato")
                ->where("c.fkEmpleado","=",$empleado->idempleado)
                ->whereIn("c.fkEstado",["1","2","4"])->first();

                $cambioSalario = DB::table("cambiosalario","cs")
                ->where("cs.fkEmpleado","=",$empleado->idempleado)
                ->where("cs.fkEstado","=","5")
                ->first();
                $fechaUltimoCamSal = $empleado->fechaIngreso;
                if(isset($cambioSalario)){
                    $fechaUltimoCamSal = $cambioSalario->fechaCambio;
                }

                $fechaRet1 = $novedadesRetiro->fecha;
                if(substr($fechaRet1, 8, 2) == "31" || (substr($fechaRet1, 8, 2) == "28" && substr($fechaRet1, 5, 2) == "02") || (substr($fechaRet1, 8, 2) == "29" && substr($fechaRet1, 5, 2) == "02") ){
                    $fechaRet1 = $novedadesRetiro->fecha;
                }
                $diasLab = $this->days_360($empleado->fechaIngreso, $fechaRet1) + 1;
                $meses = intval($diasLab/30);
                $diasDemas = $diasLab - ($meses * 30);
                $tiempoTrabTxt = $meses." Meses ".$diasDemas." días";
                
                $fechaFinMesActual = date("Y-m-t", strtotime($novedadesRetiro->fechaReal));
                $fechaInicioMesActual = date("Y-m-01", strtotime($novedadesRetiro->fechaReal));
                $ultimoBoucher = DB::table("boucherpago", "bp")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9"])
                ->orderBy("bp.idBoucherPago","desc")
                ->first();
               
                
                if(!isset($ultimoBoucher)){
                    $ultimoBoucher = DB::table("boucherpago", "bp")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                    ->whereIn("ln.fkTipoLiquidacion",["3"])
                    ->orderBy("bp.idBoucherPago","desc")
                    ->first();
                }
                else{
                    $ultimoBoucherRetiro = DB::table("boucherpago", "bp")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                    ->whereIn("ln.fkTipoLiquidacion",["3"])
                    ->orderBy("bp.idBoucherPago","desc")
                    ->first();

                    $ultimoBoucher->ibc_afp = $ultimoBoucher->ibc_afp + ($ultimoBoucherRetiro->ibc_afp ?? 0);
                    $ultimoBoucher->ibc_eps = $ultimoBoucher->ibc_eps + ($ultimoBoucherRetiro->ibc_eps ?? 0);
                    $ultimoBoucher->ibc_arl = $ultimoBoucher->ibc_arl + ($ultimoBoucherRetiro->ibc_arl ?? 0);
                    $ultimoBoucher->ibc_ccf = $ultimoBoucher->ibc_ccf + ($ultimoBoucherRetiro->ibc_ccf ?? 0);
                    $ultimoBoucher->ibc_otros = $ultimoBoucher->ibc_otros + ($ultimoBoucherRetiro->ibc_otros ?? 0);
                }
                $IBL = $ultimoBoucher->ibc_eps;
               
                $html.='                    
                <div class="page liquida">
                    <div style="border: 2px solid #000; padding: 5px 10px; font-size: 15px; margin-bottom: 5px;">
                        <table class="tituloTable">
                            <tr>
                                <td rowspan="2">
                                '.(isset($empresayLiquidacion->logoEmpresa) ? '<img style="max-width: 50px; max-height: 50px; margin-right: 5px;" src="data:image/png;base64,'.$base64.'" class="logoEmpresa" />' : '').'
                                </td>
                                <td valign="bottom"><b>'.$empresayLiquidacion->razonSocial.'</b></td>
                            </tr>
                            <tr>
                                <td  valign="top"><b>'.number_format($empresayLiquidacion->documento,0, ",", ".").'-'.$empresayLiquidacion->digitoVerificacion.'</b></td>
                            </tr>
                        </table>
                        <center>
                            <h2 style="margin:0; margin-bottom: 0px; font-size: 20px;">LIQUIDACIÓN DE CONTRATO</h2>
                        </center>
                    </div>
                    <table style="width: 96%; text-align: left;">
                        <tr>
                            <th>
                                Nómina
                            </th>
                            <td>
                                '.$empresayLiquidacion->nom_nombre.'
                            </td>
                            <th>
                                Período liquidación
                            </th>
                            <td>
                                '.date("Y",strtotime($empresayLiquidacion->fechaInicio))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaInicio)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaInicio)).' 
                                a
                                '.date("Y",strtotime($empresayLiquidacion->fechaFin))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaFin)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaFin)).' 
                            </td>
                        </tr>
                        <tr>
                            <th>Empleado</th>
                            <td>'.$empleado->primerApellido." ".$empleado->segundoApellido." ".$empleado->primerNombre." ".$empleado->segundoNombre.'</td>
                            <th>Fecha ingreso</th>
                            <td>'.date("Y",strtotime($empleado->fechaIngreso))."/".$arrMeses[date("m",strtotime($empleado->fechaIngreso)) - 1].'/'.date("d",strtotime($empleado->fechaIngreso)).'</td>
                        </tr>
                        <tr>
                            <th>Identificación</th>
                            <td>'.$empleado->tipoidentificacion.' '.$empleado->numeroIdentificacion.'</td>
                            <th>Fecha Retiro</th>
                            <td>'.date("Y",strtotime($novedadesRetiro->fecha))."/".$arrMeses[date("m",strtotime($novedadesRetiro->fecha)) - 1].'/'.date("d",strtotime($novedadesRetiro->fecha)).'</td>
                        </tr>
                        <tr>
                            <th>Tipo Contrato</th>
                            <td>'.($contrato->nombre ?? "").'</td>
                            <th>Fecha Retiro Real</th>
                            <td>'.date("Y",strtotime($novedadesRetiro->fechaReal))."/".$arrMeses[date("m",strtotime($novedadesRetiro->fechaReal)) - 1].'/'.date("d",strtotime($novedadesRetiro->fechaReal)).'</td>
                        </tr>
                        <tr>
                            <th>Nómina</th>
                            <td>'.$empresayLiquidacion->nom_nombre.'</td>
                            <th>Fecha Último Aumento Salario</th>
                            <td>
                                '.date("Y",strtotime($fechaUltimoCamSal))."/".$arrMeses[date("m",strtotime($fechaUltimoCamSal)) - 1].'/'.date("d",strtotime($fechaUltimoCamSal)).' 
                            </td>
                        </tr>
                        <tr>
                            <th>Régimen</th>
                            <td>'.$empleado->tipoRegimen.'</td>
                            <th>Última Nómina Pagada</th>
                            <td>
                                '.date("Y",strtotime($empresayLiquidacion->fechaLiquida))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaLiquida)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaLiquida)).' 
                            </td>
                        </tr>
                        <tr>
                            <th>Tiempo Trabajado</th>
                            <td>'.$tiempoTrabTxt.'</td>
                            <th>Cargo</th>
                            <td>'.$empleado->nombreCargo.'</td>
                            </td>
                        </tr>
                        <tr>
                            <th>Salario</th>
                            <td>$ '.number_format($conceptoSalario->valor,0, ",", ".").'</td>
                            <th>EPS</th>
                            <td>'.($salud->razonSocial ?? "").'</td>
                        </tr>
                        <tr>
                            <th>Entidad Bancaria</th>
                            <td>'.(isset($entidadBancaria->razonSocial) ? $entidadBancaria->razonSocial : "").'</td>
                            <th>Cuenta</th>
                            <td>'.(isset($entidadBancaria->numeroCuenta) ? $entidadBancaria->numeroCuenta : "").'</td>
                        </tr>
                        <tr>
                            <th>Fondo Pensiones</th>
                            <td>'.(isset($pension->razonSocial) ? $pension->razonSocial : "").'</td>
                            <th>IBL Seguridad Social </th>
                            <td>$ '.number_format($this->roundSup($IBL,-2),0, ",", ".").'</td>
                        </tr>
                        <tr>
                            <th>Fondo Cesantías </th>
                            <td>'.(isset($cesantiasEmp->razonSocial) ? $cesantiasEmp->razonSocial : "").'</td>
                            <th>Motivo Retiro</th>
                            <td>'.$novedadesRetiro->motivoRet.'</td>
                        </tr>
                    </table>
                    <br>';
                    $basePrima = 0;
                    $baseCes = 0;
                    $baseVac = 0;

                    $fechaInicioCes = "";
                    $fechaInicioPrima = "";
                    $fechaInicioVac = $empleado->fechaIngreso;

                    $fechaFinCes = "";
                    $fechaFinPrima = "";
                    $fechaFinVac = $novedadesRetiro->fecha;

                    $diasCes = 0;
                    $diasPrima = 0;
                    $diasVac = 0;

                
                    foreach($idItemBoucherPago as $itemBoucherPago){
                        if($itemBoucherPago->fkConcepto == 30){
                            $baseVac = $itemBoucherPago->base;
                            $diasVac = $itemBoucherPago->cantidad;
                        }

                        if($itemBoucherPago->fkConcepto == 58){
                            $basePrima = $itemBoucherPago->base;
                            $fechaInicioPrima =  $itemBoucherPago->fechaInicio;
                            $fechaFinPrima =  $itemBoucherPago->fechaFin;                            
                            $diasPrima = (15 / 180) * $itemBoucherPago->cantidad;
                        }
                        
                        if($itemBoucherPago->fkConcepto == 66){
                            $baseCes = $itemBoucherPago->base;
                            $fechaInicioCes =  $itemBoucherPago->fechaInicio;
                            $fechaFinCes =  $itemBoucherPago->fechaFin;
                            $diasCes = (($itemBoucherPago->cantidad * $nomina->diasCesantias) / 360);
                        }
                        
                    }
                    $html.='<div style="border: 2px solid #000; padding: 0px 10px; margin-bottom: 5px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="6">Promedio Liquidación Prestaciones</th>
                            </tr>
                            <tr>
                                <th>Promedio Cesantías</th>
                                <td>$'.number_format($baseCes,0, ",", ".").'</td>
                                <th>Promedio Vacaciones</th>
                                <td>$'.number_format($baseVac,0, ",", ".").'</td>
                                <th>Promedio Prima</th>
                                <td>$'.number_format($basePrima,0, ",", ".").'</td>
                            </tr>
                        </table>
                    </div>';
                    $html.='<div style="border: 2px solid #000; padding: 0px 10px; margin-bottom: 5px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="5">Valores Consolidados</th>
                            </tr>
                            <tr>
                                <th  style="background: #CCC; text-align: center;">Tipo Consolidado </th>
                                <th  style="background: #CCC; text-align: center;">Fecha Inicio</th>
                                <th  style="background: #CCC; text-align: center;">Fecha Fin</th>
                                <th  style="background: #CCC; text-align: center;">Total Días</th>
                            </tr>
                            <tr>
                                <th>Cesantías consolidadas</th>
                                <td>'.$fechaInicioCes.'</td>
                                <td>'.$fechaFinCes.'</td>
                                <td>'.round($diasCes,2).'</td>
                            </tr>
                            <tr>
                                <th>Prima de servicios consolidadas</th>
                                <td>'.$fechaInicioPrima.'</td>
                                <td>'.$fechaFinPrima.'</td>
                                <td>'.round($diasPrima,2).'</td>
                            </tr>
                            <tr>
                                <th>Vacaciones consolidadas</th>
                                <td>'.$fechaInicioVac.'</td>
                                <td>'.$fechaFinVac.'</td>
                                <td>'.round($diasVac,2).'</td>
                            </tr>
                        </table>
                    </div>
                    <div style="border: 2px solid #000; padding: 0px 10px; margin-bottom: 5px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td colspan="4"></td>
                                <th style="background: #d89290; text-align: center;" colspan="2">Pagos y Descuentos</th>
                                <td></td>
                            </tr>
                            <tr>
                                <th style="background: #CCC; text-align: center;">Conceptos Liquidados</th>
                                <th style="background: #CCC; text-align: center;">Cantidad</th>
                                <th style="background: #CCC; text-align: center;">Unidad</th>
                                <th style="background: #CCC; text-align: center;">Base</th>
                                <th style="background: #CCC; text-align: center;">Pagos</th>
                                <th style="background: #CCC; text-align: center;">Descuentos</th>
                                <th style="background: #CCC; text-align: center;">Saldo Cuota</th>                                
                            </tr>';
                            $totalDesc = 0;
                            $totalPag = 0;
                            foreach($idItemBoucherPago as $itemBoucherPago){
                                $html.='<tr style="border-bottom: 1px solid #B0B0B0;">
                                    <td style="border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->nombre.'</td>';
                                    if($itemBoucherPago->fkConcepto == 58){
                                        $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.round($diasPrima,2).'</td>';
                                    }
                                    else if($itemBoucherPago->fkConcepto == 66 || $itemBoucherPago->fkConcepto == 69){
                                        
                                        $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.round($diasCes,2).'</td>';
                                    }
                                    else{
                                        $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->cantidad.'</td>';
                                    }

                                    $html.='
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->tipoUnidad.'</td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->base,0, ",", ".").'</td>';

                                    
                                    
                                    if($itemBoucherPago->valor > 0){
                                        $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor,0, ",", ".").'</td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>';
                                        $totalPag = $totalPag + $itemBoucherPago->valor;
                                    }
                                    else{
                                        $html.='<td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor*-1,0, ",", ".").'</td>';
                                        $totalDesc = $totalDesc + $itemBoucherPago->valor;
                                    }
                                    $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$0</td>';

                                $html.='</tr>';
                            }
                            $html.='<tr>
                                        
                                        <th colspan="4" style="text-align: right;">Totales</th>
                                        <th style="text-align: right; border: 1px solid #B0B0B0;">$'.number_format($totalPag,0, ",", ".").'</td>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" >$'.number_format($totalDesc*-1,0, ",", ".").'</td>
                                    </tr>
                            ';
                            $totalGen = $totalPag + $totalDesc;
                            if($totalGen<0){
                                $totalGen=0;
                            }
                            $html.='<tr>
                                        
                                        <th colspan="3" style="text-align: right;" >Neto a pagar</th>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" colspan="2">$'.number_format($totalGen,0, ",", ".").'</td>
                                        
                                    </tr>
                            ';
                            $valorText =$this->convertir(($totalPag + $totalDesc));
                            
                        $html.='</table>
                    </div>
                    <div style="border: 2px solid #000; padding: 10px 20px; font-size: 10px; font-weight: bold; margin-bottom: 5px;">
                        El valor neto a pagar es: '.strtoupper($valorText).' PESOS M/CTE
                    </div><br>';
                    if(sizeof($itemsBoucherPagoFueraNomina)>0){
                        $html.='<div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                        <tr>
                                <th style="background: #CCC; text-align: center;" colspan="5">Fuera de nómina</th>
                        </tr>
                        <tr>
                            <th style="background: #CCC; text-align: center;">Conceptos</th>
                            <th style="background: #CCC; text-align: center;">Cantidad</th>
                            <th style="background: #CCC; text-align: center;">Unidad</th>
                            <th style="background: #CCC; text-align: center;">Pagos</th>
                            <th style="background: #CCC; text-align: center;">Descuentos</th>
                        </tr>
                        ';
                        foreach($itemsBoucherPagoFueraNomina as $itemBoucherPagoFueraNomina){
                            $html.='<tr style="border-bottom: 1px solid #B0B0B0;">
                            <td style="border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->nombre.'</td>
                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->cantidad.'</td>
                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->tipoUnidad.'</td>';
                            
                            if($itemBoucherPagoFueraNomina->valor > 0){
                                $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPagoFueraNomina->valor,0, ",", ".").'</td>
                                    <td style="border-bottom: 1px solid #B0B0B0;"></td>';
                                
                            }
                            else{
                                $html.='<td style="border-bottom: 1px solid #B0B0B0;"></td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPagoFueraNomina->valor*-1,0, ",", ".").'</td>';
                                
                            }
                            $html.='</tr>';
                        }
                        $html.='</table></div><br> 
                        </div>
                        <div class="page_break"></div>
                        <div class="page">';
                    }
                    $html.='<div style="border: 2px solid #000; padding: 0px 10px; margin-bottom: 5px;">
                        <center><h4 style="margin:0px;" >Observaciones</h4></center>
                        <table>
                            <tr>
                                <th style="background: #CCC; text-align: center;">CONSTANCIAS - Se hace constar expresamente los siguiente:</th>
                            </tr>
                            <td style="font-size: 8px; text-align: justify;">'.$mensajeGen[9]->html.'</td>
                        </table>
                        <table style="width: 100%;">
                            <tr>
                                <th style="border: 1px solid #000; width:33%;">ELABORÓ: '.$userSolicita.'</th>
                                <th style="border: 1px solid #000; width:33%;">REVISÓ: '.$userAprueba.'</th>
                                <th style="border: 1px solid #000; width:33%;">APROBÓ:</th>
                            </tr>
                        </table>
                    </div>
                    <div style="position: absolute; bottom: 40px; width:100%;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td>COLABORADOR</td>
                                <td></td>
                                <td>LA EMPRESA</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td>Cédula o NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                                <td>NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                            </tr>
                            <tr>
                                <td colspan="3"></td>
                                <td>Fecha de elaboración:&nbsp;&nbsp;&nbsp; '.date("d/m/Y").'</td>
                            </tr>
                        </table>
                    </div>
                </div>';
            }
            else{
                $html.='<div class="page">
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                        ';
                        $html.='                        
                        <table class="tituloTable">
                            <tr>
                                <td rowspan="2">
                                '.(isset($empresayLiquidacion->logoEmpresa) ? '<img style="max-width: 50px; max-height: 50px; margin-right: 5px;" src="data:image/png;base64,'.$base64.'" class="logoEmpresa" />' : '').'
                                </td>
                                <td valign="bottom"><b>'.$empresayLiquidacion->razonSocial.'</b></td>
                            </tr>
                            <tr>
                                <td  valign="top"><b>'.number_format($empresayLiquidacion->documento,0, ",", ".").'-'.$empresayLiquidacion->digitoVerificacion.'</b></td>
                            </tr>
                        </table>
                        <center>
                            <h2 style="margin:0; margin-bottom: 10px;">Comprobante pago nómina</h2>
                        </center>
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th>
                                    Nómina
                                </th>
                                <td>
                                    '.$empresayLiquidacion->nom_nombre.'
                                </td>
                                <th>
                                    Periodo liquidación
                                </th>
                                <td>
                                    '.date("Y",strtotime($empresayLiquidacion->fechaInicio))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaInicio)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaInicio)).' 
                                    a
                                    '.date("Y",strtotime($empresayLiquidacion->fechaFin))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaFin)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaFin)).' 
                                </td>
                            </tr>
                            <tr>
                                <th>Empleado</th>
                                <td>'.$empleado->primerApellido." ".$empleado->segundoApellido." ".$empleado->primerNombre." ".$empleado->segundoNombre.'</td>
                                <th>Salario</th>
                                <td>$ '.number_format($conceptoSalario->valor,0, ",", ".").'</td>
                            </tr>
                            <tr>
                                <th>Identificación</th>
                                <td>'.$empleado->tipoidentificacion.' '.$empleado->numeroIdentificacion.'</td>
                                <th>Cargo</th>
                                <td>'.$empleado->nombreCargo.'</td>
                            </tr>
                            <tr>
                                <th>Entidad Bancaria</th>
                                <td>'.(isset($entidadBancaria->razonSocial) ? $entidadBancaria->razonSocial : "").'</td>
                                <th>Cuenta</th>
                                <td>'.(isset($entidadBancaria->numeroCuenta) ? $entidadBancaria->numeroCuenta : "").'</td>
                            </tr>
                            <tr>
                                <th>EPS</th>
                                <td>'.($salud->razonSocial ?? "").'</td>
                                <th>Fondo Pensiones</th>
                                <td>'.(isset($pension->razonSocial) ? $pension->razonSocial : "").'</td>
                            </tr>
                        </table>
                    </div><br>';
                    if(sizeof($idItemBoucherPago)>0){
                        $html.='<div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                                <th style="background: #d89290; text-align: center;" colspan="2">Devengado</th>
                                <th style="background: #d89290; text-align: center;">Deducciones</th>                        
                            </tr>
                            <tr>
                                <th style="background: #CCC; text-align: center;">Conceptos Liquidados</th>
                                <th style="background: #CCC; text-align: center;">Cantidad</th>
                                <th style="background: #CCC; text-align: center;">Unidad</th>
                                <th style="background: #CCC; text-align: center;">Pagos</th>
                                <th style="background: #CCC; text-align: center;">Beneficios</th>
                                <th style="background: #CCC; text-align: center;">Descuentos</th>
                            </tr>';
                            $totalDesc = 0;
                            $totalPag = 0;
                
                            foreach($idItemBoucherPago as $itemBoucherPago){
                                $html.='<tr style="border-bottom: 1px solid #B0B0B0;">
                                    <td style="border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->nombre.'</td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->cantidad.'</td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->tipoUnidad.'</td>';
                                    
                                    if($itemBoucherPago->valor > 0){
                                        $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor,0, ",", ".").'</td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>';
                                        $totalPag = $totalPag + $itemBoucherPago->valor;
                                    }
                                    else{
                                        $html.='<td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor*-1,0, ",", ".").'</td>';
                                        $totalDesc = $totalDesc + $itemBoucherPago->valor;
                                    }

                                $html.='</tr>';
                            }
                            


                            $html.='<tr>
                                        
                                        <th colspan="3" style="text-align: right;">Totales</th>
                                        <td style="text-align: right; border: 1px solid #B0B0B0;">$'.number_format($totalPag,0, ",", ".").'</td>
                                        <td style="text-align: right; border: 1px solid #B0B0B0;"></td>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" >$'.number_format($totalDesc*-1,0, ",", ".").'</td>
                                    </tr>
                            ';
                            $totalGen = $totalPag + $totalDesc;
                            if($totalGen<0){
                                $totalGen=0;
                            }
                            
                            $html.='<tr>
                                        
                                        <th colspan="3" style="text-align: right;" >Neto a pagar en cuenta nómina</th>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" colspan="2">$'.number_format($totalGen,0, ",", ".").'</td>
                                        
                                    </tr>
                            ';
                            
                        $html.='</table>

                    </div>
                    <br>';
                    }
                    

                    if(sizeof($itemsBoucherPagoFueraNomina)>0){
                        $html.='<div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                        <tr>
                                <th style="background: #CCC; text-align: center;" colspan="5">Fuera de nómina</th>
                        </tr>
                        <tr>
                            <th style="background: #CCC; text-align: center;">Conceptos</th>
                            <th style="background: #CCC; text-align: center;">Cantidad</th>
                            <th style="background: #CCC; text-align: center;">Unidad</th>
                            <th style="background: #CCC; text-align: center;">Pagos</th>
                            <th style="background: #CCC; text-align: center;">Descuentos</th>
                        </tr>
                        ';
                        foreach($itemsBoucherPagoFueraNomina as $itemBoucherPagoFueraNomina){
                            $html.='<tr style="border-bottom: 1px solid #B0B0B0;">
                            <td style="border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->nombre.'</td>
                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->cantidad.'</td>
                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->tipoUnidad.'</td>';
                            
                            if($itemBoucherPagoFueraNomina->valor > 0){
                                $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPagoFueraNomina->valor,0, ",", ".").'</td>
                                    <td style="border-bottom: 1px solid #B0B0B0;"></td>';
                                
                            }
                            else{
                                $html.='<td style="border-bottom: 1px solid #B0B0B0;"></td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPagoFueraNomina->valor*-1,0, ",", ".").'</td>';
                                
                            }
                            $html.='</tr>';
                        }
                        $html.='</table></div><br>';
                    }

                    $html.='<div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="2">Bases para cálculo de seguridad social</th>
                            </tr>
                            <tr>
                                <td>Ingreso Base Cotización Salud</td><td style="text-align: right;">$'.number_format($empresayLiquidacion->ibc_eps,0, ",", ".").'</td>
                            </tr>
                            <tr>
                                <td>Ingreso Base Cotización Pension</td><td style="text-align: right;">$'.number_format($empresayLiquidacion->ibc_afp,0, ",", ".").'</td>
                            </tr>
                        </table>
                    </div>
                    <br>
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="2">Mensaje Empresarial</th>
                            </tr>
                            <tr>
                                <td style="text-align: justify;">'.$mensajeGen[8]->html.'</td>
                            </tr>
                        </table>
                    </div>
                    <br>
                    <div style="position: absolute; bottom: 40px; width:100%;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td>COLABORADOR</td>
                                <td></td>
                                <td>LA EMPRESA</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td>Cédula o NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                                <td>NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                            </tr>
                            <tr>
                                <td colspan="3"></td>
                                <td>Fecha de elaboración:&nbsp;&nbsp;&nbsp; '.date("d/m/Y").'</td>
                            </tr>
                        </table>

                    </div>
                </div>
                ';
            }
            if(sizeof($novedadesVacacionActual) > 0){
                
                $novedadesRetiro = DB::table("novedad","n")
                ->select("r.fecha")
                ->join("retiro AS r", "r.idRetiro","=","n.fkRetiro")
                ->where("n.fkEmpleado", "=", $empleado->idempleado)
                ->whereRaw("n.fkPeriodoActivo in(
                    SELECT p.idPeriodo from periodo as p where p.fkEmpleado = '".$empleado->idempleado."' and p.fkEstado = '1'
                )")
                ->whereIn("n.fkEstado",["7", "8"])
                ->whereNotNull("n.fkRetiro")
                ->whereBetween("n.fechaRegistro",[$empresayLiquidacion->fechaInicio, $empresayLiquidacion->fechaFin])->first();
                $fechaFinalVaca = $empresayLiquidacion->fechaFin;
                $periodoPagoVac = $this->days_360($empleado->fechaIngreso,$empresayLiquidacion->fechaFin) + 1 ;
                if(isset($novedadesRetiro)){
                    if(strtotime($empresayLiquidacion->fechaFin) > strtotime($novedadesRetiro->fecha)){
                        $periodoPagoVac = $this->days_360($empleado->fechaIngreso,$novedadesRetiro->fecha) + 1 ;
                        $fechaFinalVaca = $novedadesRetiro->fecha;
                    }
                }

                $diasVac = $periodoPagoVac * 15 / 360;

                $novedadesVacacion = DB::table("novedad","n")
                ->select("v.*", "c.nombre","c.idconcepto", "ibpn.valor")
                ->join("concepto as c","c.idconcepto","=","n.fkConcepto")
                ->join("vacaciones as v","v.idVacaciones","=","n.fkVacaciones")
                ->join("item_boucher_pago_novedad as ibpn","ibpn.fkNovedad","=","n.idNovedad")
                ->join("item_boucher_pago as ibp","ibp.idItemBoucherPago","=","ibpn.fkItemBoucher")
                ->where("n.fkEmpleado","=",$empleado->idempleado)
                ->whereRaw("n.fkPeriodoActivo in(
                    SELECT p.idPeriodo from periodo as p where p.fkEmpleado = '".$empleado->idempleado."' and p.fkEstado = '1'
                )")
                ->where("ibp.fkBoucherPago","<>",$idBoucherPago)
                ->whereIn("n.fkEstado",["7"]) // Pagada o sin pagar-> no que este eliminada
                ->whereNotNull("n.fkVacaciones")
                ->get();
                //$diasVac = $totalPeriodoPagoAnioActual * 15 / 360;
                foreach($novedadesVacacion as $novedadVacacion){
                    $diasVac = $diasVac - $novedadVacacion->diasCompensar;
                }
                if(isset($diasVac) && $diasVac < 0){
                    $diasVac = 0;
                }

            
                
                $html.='<div class="page_break"></div>
                    <div class="page">
                        <div style="border: 2px solid #000; padding: 10px 20px;">
                            <table class="tituloTable">
                                <tr>
                                    <td rowspan="2">
                                    '.(isset($empresayLiquidacion->logoEmpresa) ? '<img style="max-width: 50px; max-height: 50px; margin-right: 5px;" src="data:image/png;base64,'.$base64.'" class="logoEmpresa" />' : '').'
                                    </td>
                                    <td valign="bottom"><b>'.$empresayLiquidacion->razonSocial.'</b></td>
                                </tr>
                                <tr>
                                    <td  valign="top"><b>'.number_format($empresayLiquidacion->documento,0, ",", ".").'-'.$empresayLiquidacion->digitoVerificacion.'</b></td>
                                </tr>
                            </table>
                            <center>
                                <h2 style="margin:0; margin-bottom: 10px;">Comprobante de Pago de Vacaciones</h2>
                            </center>
                            <table style="width: 100%; text-align: left;">
                                <tr>
                                    <th>Empleado</th>
                                    <td>'.$empleado->primerApellido." ".$empleado->segundoApellido." ".$empleado->primerNombre." ".$empleado->segundoNombre.'</td>
                                    <th>Fecha ingreso</th>
                                    <td>'.date("Y",strtotime($empleado->fechaIngreso))."/".$arrMeses[date("m",strtotime($empleado->fechaIngreso)) - 1].'/'.date("d",strtotime($empleado->fechaIngreso)).'</td>
                                </tr>
                                <tr>
                                    <th>Identificación</th>
                                    <td>'.$empleado->tipoidentificacion.' '.$empleado->numeroIdentificacion.'</td>
                                    <th>Cargo</th>
                                    <td>'.$empleado->nombreCargo.'</td>
                                </tr>
                                <tr>
                                    <th>Días Pendientes Consolidado</th>
                                    <td>'.round($diasVac,2).'</td>
                                    <th>Fecha Corte Consolidado:</th>
                                    <td>'.date("Y",strtotime($fechaFinalVaca))."/".$arrMeses[date("m",strtotime($fechaFinalVaca)) - 1].'/'.date("d",strtotime($fechaFinalVaca)).'</td>
                                </tr>
                                
                            </table>
                    </div>
                    <br>
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                    <table style="width: 100%; text-align: left; font-size: 10px;">
                        <tr>
                            <th style="background: #d89290; text-align: center; font-size: 10px;" colspan="11">Liquidación de Vacaciones</th>                         
                        </tr>
                        <tr>
                            <th style="background: #CCC; text-align: center; font-size: 10px;" rowspan="2">Tipo Movimiento</th>
                            <th style="background: #CCC; text-align: center; font-size: 10px;" colspan="2">Periodo Causación</th>
                            <th style="background: #CCC; text-align: center; font-size: 10px;" colspan="4">Periodo Vacaciones</th>
                            <th style="background: #CCC; text-align: center; font-size: 10px;" colspan="2">Días Pagados</th>
                            <th style="background: #CCC; text-align: center; font-size: 10px;" rowspan="2">Promedio<br>Diario</th>
                            <th style="background: #CCC; text-align: center; font-size: 10px;" rowspan="2">Valor<br>Liquidado</th>
                        </tr>
                        <tr>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Fecha Inicio</th>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Fecha Fin</th>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Fecha Inicio</th>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Fecha Fin</th>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Días</th>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Regreso</th>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Tiempo</th>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Dinero</th>
                        </tr>
                        
                ';


                $periodosVacaciones = array();
                $fechaInicioVacaciones = $empleado->fechaIngreso;
                $agregarPeriodos = true;
                /*
                
                do{
                    $date_time_inicio=new DateTime($fechaInicioVacaciones);
                    $date_time_inicio->add(new DateInterval('P1Y'));

                    $fechaFin = $date_time_inicio->format('Y-m-d');
                    if(strtotime($fechaFin) > strtotime($empresayLiquidacion->fechaFin)){
                        $fechaFin = $empresayLiquidacion->fechaFin;
                        $agregarPeriodos = false;
                    }
                    $periodosVacacionFila = array();
                    $periodosVacacionFila["fechaInicio"] = $fechaInicioVacaciones;
                    $periodosVacacionFila["fechaFin"] = $fechaFin;
                    $totalDiasMaximo = (sizeof($periodosVacaciones) + 1) * 15;                    
                    $totalDiasMinimo = sizeof($periodosVacaciones) * 15;
                   
                    $periodosVacacionFila["vacaciones"] = array();
                    foreach($novedadesVacacionActual as $novedadVacacion){
                        $diasOtroPeriodo = 0;
                        foreach($periodosVacaciones as $periodoVacaciones){
                            foreach($periodoVacaciones['vacaciones'] as $vacaciones){
                                if($vacaciones["idVacaciones"] == $novedadVacacion->idVacaciones){
                                    $diasOtroPeriodo = $vacaciones["dias"];
                                }
                            }
                        }
                        $diasVac = $novedadVacacion->diasCompensar;
                        if($diasOtroPeriodo != 0){
                            $diasVac = $novedadVacacion->diasCompensar - $diasOtroPeriodo;
                        }
                        if($diasVac != 0){
                            $diasPeriodoActual = 0;
                            foreach($periodosVacacionFila["vacaciones"] as $vac2){
                                $diasPeriodoActual = $diasPeriodoActual + $vac2["dias"];
                            }
                            if($diasPeriodoActual < 15){
                                if(($diasVac + $diasPeriodoActual) >= 15){
                                    $diasVac = (15 - $diasPeriodoActual);
                                    
                                    array_push($periodosVacacionFila["vacaciones"], [
                                        "dias" => $diasVac,
                                        "idVacaciones" => $novedadVacacion->idVacaciones
                                    ]);
                                }
                                else{
                                    array_push($periodosVacacionFila["vacaciones"], [
                                        "dias" => $diasVac,
                                        "idVacaciones" => $novedadVacacion->idVacaciones
                                    ]);
                                }
                            }
                        }
                    }



                    array_push($periodosVacaciones, $periodosVacacionFila);



                }while($agregarPeriodos);*/
         

                $totalVac = 0;
                foreach($novedadesVacacionActual as $novedadVacacion){
                    $tipoMov = str_replace("VACACIONES", "", $novedadVacacion->nombre);

                    
                    


                    $html.='
                        <tr>
                            <td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">'.$tipoMov.'</td>
                            <td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.$empleado->fechaIngreso.'</td>
                            <td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.$novedadVacacion->fechaInicio.'</td>';
                        if($novedadVacacion->idconcepto == 29){
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.$novedadVacacion->fechaInicio.'</td>';
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.$novedadVacacion->fechaFin.'</td>';
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.$novedadVacacion->diasCompensar.'</td>';
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.date("Y-m-d",strtotime($novedadVacacion->fechaFin."+1 day")).'</td>';
                        }
                        else{
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;"></td>';
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;"></td>';
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">'.$novedadVacacion->diasCompensar.'</td>';
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;"></td>';
                        }
                        
                        $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">'.$novedadVacacion->diasCompensar.'</td>';
                        $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">$'.number_format($novedadVacacion->valor,0, ",", ".").'</td>';
                        $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">$'.number_format($novedadVacacion->valor/$novedadVacacion->diasCompensar,0, ",", ".").'</td>';
                        $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">$'.number_format($novedadVacacion->valor,0, ",", ".").'</td>';

                        $html.='</tr>
                    ';
                    $totalVac = $totalVac + $novedadVacacion->valor;
                    if($totalVac<0){
                        $totalVac=0;
                    }
                }    
                $html.='
                    <tr>
                        <th style="text-align: right; font-size: 10px;" colspan="9">TOTAL LIQUIDADO VACACIONES</th>
                        <td style="text-align: right; border: 1px solid #B0B0B0; font-size: 10px;" colspan="2">$'.number_format($totalVac,0, ",", ".").'</td>
                    </tr>            
                    </table>
                </div>
                <br>
                    <center><h4>Observaciones</h4></center>
                <br>
                <div style="border: 2px solid #000; padding: 10px 20px; min-height: 50px;">
                <br><br><br>
                </div>
                <div style="position: absolute; bottom: 40px; width:100%;">
                    <table style="width: 100%; text-align: left;">
                        <tr>
                            <td>COLABORADOR</td>
                            <td></td>
                            <td>LA EMPRESA</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>Cédula o NIT</td>
                            <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                            <td>NIT</td>
                            <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                        </tr>
                        <tr>
                            <td colspan="3"></td>
                            <td>Fecha de elaboración:&nbsp;&nbsp;&nbsp; '.date("d/m/Y").'</td>
                        </tr>
                    </table>
                    <table style="width: 100%;">
                        <tr>
                            <th style="border: 1px solid #000; width:33%;">ELABORÓ: '.$userSolicita.'</th>
                            <th style="border: 1px solid #000; width:33%;">REVISÓ: '.$userAprueba.'</th>
                            <th style="border: 1px solid #000; width:33%;">APROBÓ:</th>
                        </tr>
                    </table>
                </div>
                ';
            }            

            
            $html.='
            </body>
        </html>
        ';
        
        $dompdf->loadHtml($html ,'UTF-8');

        // (Optional) Setup the paper size and orientation
        $dompdf->setPaper('Letter', 'portrait');
        // Render the HTML as PDF
        $dompdf->render();

        // Output the generated PDF to Browser
        $dompdf->stream("Comprobante de Pago ".$idBoucherPago.".pdf", array('compress' => 1, 'Attachment' => 0));
    }
    
    public function boucherPagoPdfPass($idBoucherPago){

        $empresayLiquidacion = DB::table("empresa", "e")
        ->select("e.*", "ln.*", "n.nombre as nom_nombre", "bp.*")
        ->join("nomina as n","n.fkEmpresa", "e.idempresa")
        ->join("liquidacionnomina as ln","ln.fkNomina", "n.idNomina")
        ->join("boucherpago as bp","bp.fkLiquidacion", "ln.idLiquidacionNomina")
        ->where("bp.idBoucherPago","=",$idBoucherPago)
        ->first();
    
        $userSolicita = "";
        if(isset($empresayLiquidacion->fkUserSolicita)){
            $usuario = DB::table('users')->select(
                'users.username',
                'users.email',
                'datospersonales.primerNombre',
                'datospersonales.primerApellido',
                'datospersonales.foto',
                'empleado.idempleado'
            )->leftJoin('empleado', 'users.fkEmpleado', 'empleado.idempleado',"left")
            ->leftJoin('empresa', 'empresa.idempresa', 'empleado.fkEmpresa',"left")
            ->leftJoin('datospersonales', 'datospersonales.idDatosPersonales', 'empleado.fkDatosPersonales',"left")
            ->where('users.id', $empresayLiquidacion->fkUserSolicita)
            ->first();
            
            if(isset($usuario->primerNombre)){
                $userSolicita = $usuario->primerNombre." ".$usuario->primerApellido;
            }
            else{
                $userSolicita = $usuario->username;
            }
        }

        $userAprueba = "";
        if(isset($empresayLiquidacion->fkUserAprueba)){
            $usuario = DB::table('users')->select(
                'users.username',
                'users.email',
                'datospersonales.primerNombre',
                'datospersonales.primerApellido',
                'datospersonales.foto',
                'empleado.idempleado'
            )
            ->leftJoin('empleado', 'users.fkEmpleado', 'empleado.idempleado')
            ->leftJoin('empresa', 'empresa.idempresa', 'empleado.fkEmpresa')
            ->leftJoin('datospersonales', 'datospersonales.idDatosPersonales', 'empleado.fkDatosPersonales')
            ->where('users.id',"=", $empresayLiquidacion->fkUserAprueba)
            ->first();
            
            if(isset($usuario->primerNombre)){
                $userAprueba = $usuario->primerNombre." ".$usuario->primerApellido;
            }
            else{
                $userAprueba = $usuario->username;
            }
        }


        
        $empleado = DB::table("empleado","e")
        ->select("e.idempleado", "p.fechaInicio as fechaIngreso",
        "e.tipoRegimen","p.tipoRegimen as tipoRegimenPeriodo", "p.fkNomina",
        "dp.primerNombre","dp.segundoNombre", 
        "dp.primerApellido","dp.segundoApellido","ti.nombre as tipoidentificacion", 
        "dp.numeroIdentificacion", "cargo.nombreCargo", "cargo2.nombreCargo as nombreCargoPeriodo",
        "p.idPeriodo")
        ->join("boucherpago as bp","bp.fkEmpleado", "e.idempleado")
        ->join("periodo as p","p.idPeriodo", "bp.fkPeriodoActivo")
        ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion")
        ->join("cargo","cargo.idCargo","=","e.fkCargo", "left")
        ->join("cargo as cargo2","cargo2.idCargo","=","p.fkCargo", "left")
        ->where("bp.idBoucherPago","=",$idBoucherPago)
        ->first();
        
        $empleado->tipoRegimen = ($empleado->tipoRegimenPeriodo ?? $empleado->tipoRegimen);
        $empleado->nombreCargo = ($empleado->nombreCargoPeriodo ?? $empleado->nombreCargo);
        
        $nomina = DB::table("nomina","n")
        ->where("n.idNomina","=",$empleado->fkNomina)->first();


        $pension = DB::table("tercero", "t")->
        select(["t.razonSocial", "t.numeroIdentificacion", "t.idTercero", "ti.nombre as tipoidentificacion", "t.digitoVer"])
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","t.fkTipoIdentificacion")
        ->join("afiliacion as a","t.idTercero", "=","a.fkTercero")
        ->where("a.fkEmpleado","=",$empleado->idempleado)
        ->where("a.fkTipoAfilicacion","=","4") //4-Pensión Obligatoria 
        ->where("a.fkPeriodoActivo","=",$empresayLiquidacion->fkPeriodoActivo)
        ->first();
        $salud = DB::table("tercero", "t")->
        select(["t.razonSocial", "t.numeroIdentificacion", "t.idTercero", "ti.nombre as tipoidentificacion", "t.digitoVer"])
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","t.fkTipoIdentificacion")
        ->join("afiliacion as a","t.idTercero", "=","a.fkTercero")
        ->where("a.fkEmpleado","=",$empleado->idempleado)
        ->where("a.fkTipoAfilicacion","=","3") //3-Salud
        ->where("a.fkPeriodoActivo","=",$empresayLiquidacion->fkPeriodoActivo)
        ->first();

        

        $cesantiasEmp = DB::table("tercero", "t")->select(["t.razonSocial", "t.numeroIdentificacion", "t.idTercero",
            "ti.nombre as tipoidentificacion", "t.digitoVer"])
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","t.fkTipoIdentificacion")
        ->join("afiliacion as a","t.idTercero", "=","a.fkTercero")
        ->where("a.fkEmpleado","=",$empleado->idempleado)
        ->where("a.fkTipoAfilicacion","=","1") //2-CCF
        ->where("a.fkPeriodoActivo","=",$empresayLiquidacion->fkPeriodoActivo)
        ->first();

        $entidadBancaria = DB::table("tercero", "t")->select(["t.razonSocial", "e.numeroCuenta"])
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","t.fkTipoIdentificacion")
        ->join("empleado as e", "e.fkEntidad", "=","t.idTercero")
        ->where("e.idempleado","=",$empleado->idempleado)
        ->first();

        $idItemBoucherPago = DB::table("item_boucher_pago","ibp")
        ->join("concepto AS c","c.idconcepto","=", "ibp.fkConcepto")
        ->where("ibp.fkBoucherPago","=",$idBoucherPago)
        ->get();

        $itemsBoucherPagoFueraNomina = DB::table("item_boucher_pago_fuera_nomina","ibpfn")
        ->join("concepto AS c","c.idconcepto","=", "ibpfn.fkConcepto")
        ->where("ibpfn.fkBoucherPago","=",$idBoucherPago)
        ->get();

        $itemsBoucherPagoFueraNominaCesTras = DB::table("item_boucher_pago_fuera_nomina","ibpfn")
        ->select("ibpfn.*","c.*")
        ->join("concepto AS c","c.idconcepto","=", "ibpfn.fkConcepto")
        ->join("boucherpago as bp","bp.idBoucherPago","=","ibpfn.fkBoucherPago")
        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
        ->whereRaw("MONTH(ln.fechaLiquida)= MONTH('".$empresayLiquidacion->fechaLiquida."')")
        ->whereRaw("YEAR(ln.fechaLiquida)= YEAR('".$empresayLiquidacion->fechaLiquida."')")
        ->where("bp.fkEmpleado","=", $empleado->idempleado)
        ->where("ln.idLiquidacionNomina","<>",$empresayLiquidacion->idLiquidacionNomina)
        ->where("ln.fkTipoLiquidacion","=","11")
        ->where("ln.fkEstado","=","5")
        ->get();


        foreach($itemsBoucherPagoFueraNominaCesTras as $itemBoucherPagoFueraNominaCesTras){
            $itemsBoucherPagoFueraNomina->push($itemBoucherPagoFueraNominaCesTras);
        }




        $periodoPasadoReintegro = DB::table("periodo")
        ->where("fkEstado","=","2")
        ->where("fkEmpleado", "=", $empleado->idempleado)
        ->where("fechaInicio","<=",$empresayLiquidacion->fechaInicio)
        ->where("fechaFin",">=",$empresayLiquidacion->fechaInicio)
        ->where("fkNomina","=",$empresayLiquidacion->fkNomina)
        ->first();
        
        if(isset($periodoPasadoReintegro)){
            $conceptoSalario = new stdClass;
            $conceptoSalario->valor = $periodoPasadoReintegro->salario;
        }
        else{
            $conceptoSalario = DB::table("conceptofijo")->where("fkEmpleado","=",$empleado->idempleado)
            ->where("fkPeriodoActivo","=",$empresayLiquidacion->fkPeriodoActivo)
            ->whereIn("fkConcepto",[1,2,53,54,154])
            ->orderBy("idConceptoFijo","desc")
            ->first();
        }
        



        //VACACIONES
        $novedadesVacacionActual = DB::table("novedad","n")
        ->select("v.*", "c.nombre","c.idconcepto", "ibpn.valor")
        ->join("concepto as c","c.idconcepto", "=","n.fkConcepto")
        ->join("vacaciones as v","v.idVacaciones","=","n.fkVacaciones")
        ->join("item_boucher_pago_novedad as ibpn","ibpn.fkNovedad","=","n.idNovedad")
        ->join("item_boucher_pago as ibp","ibp.idItemBoucherPago","=","ibpn.fkItemBoucher")
        ->where("ibp.fkBoucherPago","=",$idBoucherPago)
        ->whereIn("n.fkEstado",["8","7"]) // Pagada -> no que este eliminada
        ->whereNotNull("n.fkVacaciones")
        ->get();
        //$diasVac = $totalPeriodoPagoAnioActual * 15 / 360;
        





        $base64 = "";
        if(is_file($this->rutaBaseImagenes.'storage/logosEmpresas/'.$empresayLiquidacion->logoEmpresa)){
            $imagedata = file_get_contents($this->rutaBaseImagenes.'storage/logosEmpresas/'.$empresayLiquidacion->logoEmpresa);
                    // alternatively specify an URL, if PHP settings allow
            $base64 = base64_encode($imagedata);
        }
        else{
            unset($empresayLiquidacion->logoEmpresa);
        }
        $mensajeGen = array();
        $mensajeGen[8] = DB::table("mensaje")->where("idMensaje","=","8")->first();
        $mensajeGen[9] = DB::table("mensaje")->where("idMensaje","=","9")->first();
        
        $arrMeses = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];

        $dompdf = new Dompdf();
        $dompdf->getOptions()->setChroot($this->rutaBaseImagenes);
        $dompdf->getOptions()->setIsPhpEnabled(true);
        $html='
        <!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"</head><body>
        <body>
            <style>
            *{
                -webkit-hyphens: auto;
                -ms-hyphens: auto;
                hyphens: auto;
                font-family: sans-serif;                
            }
            td{
                text-align: left;
                font-size: 8px;
            }
            th{
                text-align: left;
                font-size: 8px;
            }
            .liquida td, .liquida th{
                font-size:9px;
            }
            
            @page { 
                margin: 0in;
            }
            .page {
                top: .3in;
                right: .3in;
                bottom: .3in;
                left: .3in;
                position: absolute;
                z-index: -1000;
                min-width: 7in;
                min-height: 11.7in;
                
            }
            .page_break { 
                page-break-before: always; 
            }
            .tituloTable td b{
                font-size: 14px;
            }
            </style>
            ';
            $novedadesRetiro = DB::table("novedad","n")
            ->select("r.fecha", "r.fechaReal","mr.nombre as motivoRet")
            ->join("retiro AS r", "r.idRetiro","=","n.fkRetiro")
            ->join("motivo_retiro as mr","mr.idMotivoRetiro","=","r.fkMotivoRetiro")
            ->where("n.fkEmpleado", "=", $empleado->idempleado)
            ->whereIn("n.fkEstado",["7", "8"])
            ->whereNotNull("n.fkRetiro")
            ->whereBetween("n.fechaRegistro",[$empresayLiquidacion->fechaInicio, $empresayLiquidacion->fechaFin])->first();


            if($empresayLiquidacion->fkTipoLiquidacion == "7"){
                $html.='<div class="page liquida">
                <div style="border: 2px solid #000; padding: 5px 10px; font-size: 15px; margin-bottom: 5px;">
                    <table class="tituloTable">
                        <tr>
                            <td rowspan="2">
                            '.(isset($empresayLiquidacion->logoEmpresa) ? '<img style="max-width: 50px; max-height: 50px; margin-right: 5px;" src="data:image/png;base64,'.$base64.'" class="logoEmpresa" />' : '').'
                            </td>
                            <td valign="bottom"><b>'.$empresayLiquidacion->razonSocial.'</b></td>
                        </tr>
                        <tr>
                            <td  valign="top"><b>'.number_format($empresayLiquidacion->documento,0, ",", ".").'-'.$empresayLiquidacion->digitoVerificacion.'</b></td>
                        </tr>
                    </table>
                    <center>
                        <h2 style="margin:0; margin-bottom: 0px; font-size: 20px;">COMPROBANTE DE PAGO DE NÓMINA</h2><br>
                    </center>
                    <table style="width: 100%;">
                        <tr>
                            <th>Nómina</th>
                            <td>'.$empresayLiquidacion->nom_nombre.'</td>
                            <th>Período liquidación</th>
                            <td>
                                '.date("Y",strtotime($empresayLiquidacion->fechaInicio))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaInicio)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaInicio)).' 
                                a
                                '.date("Y",strtotime($empresayLiquidacion->fechaFin))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaFin)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaFin)).' 
                            </td>
                        </tr>
                        <tr>
                            <th>Empleado</th>
                            <td>'.$empleado->primerApellido." ".$empleado->segundoApellido." ".$empleado->primerNombre." ".$empleado->segundoNombre.'</td>
                            <th>Salario</th>
                            <td>$ '.number_format($conceptoSalario->valor,0, ",", ".").'</td>
                        </tr>
                        <tr>
                            <th>Identificación</th>
                            <td>'.$empleado->tipoidentificacion.' '.$empleado->numeroIdentificacion.'</td>
                            <th>Cargo</th>
                            <td>'.$empleado->nombreCargo.'</td>
                        </tr>
                        <tr>
                            <th>Entidad Bancaria</th>
                            <td>'.(isset($entidadBancaria->razonSocial) ? $entidadBancaria->razonSocial : "").'</td>
                            <th>Cuenta</th>
                            <td>'.(isset($entidadBancaria->numeroCuenta) ? $entidadBancaria->numeroCuenta : "").'</td>
                        </tr>
                        <tr>
                            <th>EPS</th>
                            <td>'.($salud->razonSocial ?? "").'</td>
                            <th>Fondo Pensiones</th>
                            <td>'.(isset($pension->razonSocial) ? $pension->razonSocial : "").'</td>
                        </tr>
                        
                    </table>
                    <br>
                </div>
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                                <th style="background: #d89290; text-align: center;" colspan="2">Devengado</th>
                                <th style="background: #d89290; text-align: center;">Deducciones</th>                        
                            </tr>
                            <tr>
                                <th style="background: #CCC; text-align: center;">Conceptos Liquidados</th>
                                <th style="background: #CCC; text-align: center;">Cantidad</th>
                                <th style="background: #CCC; text-align: center;">Unidad</th>
                                <th style="background: #CCC; text-align: center;">Pagos</th>
                                <th style="background: #CCC; text-align: center;">Beneficios</th>
                                <th style="background: #CCC; text-align: center;">Descuentos</th>
                            </tr>';
                            $totalDesc = 0;
                            $totalPag = 0;
                
                            foreach($idItemBoucherPago as $itemBoucherPago){
                                
                                if($itemBoucherPago->valor != 0){
                                    
                                    $html.='<tr style="border-bottom: 1px solid #B0B0B0;">
                                        <td style="border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->nombre.'</td>
                                        <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->cantidad.'</td>
                                        <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->tipoUnidad.'</td>';
                                        
                                        if($itemBoucherPago->valor > 0){
                                            $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor,0, ",", ".").'</td>
                                                <td style="border-bottom: 1px solid #B0B0B0;"></td>
                                                <td style="border-bottom: 1px solid #B0B0B0;"></td>';
                                            $totalPag = $totalPag + $itemBoucherPago->valor;
                                        }
                                        else{
                                            $html.='<td style="border-bottom: 1px solid #B0B0B0;"></td>
                                                <td style="border-bottom: 1px solid #B0B0B0;"></td>
                                                <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor*-1,0, ",", ".").'</td>';
                                            $totalDesc = $totalDesc + $itemBoucherPago->valor;
                                        }

                                    $html.='</tr>';
                                }

                            }
                            $html.='<tr>
                                        
                                        <th colspan="3" style="text-align: right;">Totales</th>
                                        <td style="text-align: right; border: 1px solid #B0B0B0;">$'.number_format($totalPag,0, ",", ".").'</td>
                                        <td style="text-align: right; border: 1px solid #B0B0B0;"></td>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" >$'.number_format($totalDesc*-1,0, ",", ".").'</td>
                                    </tr>
                            ';
                            $totalGen = $totalPag + $totalDesc;
                            if($totalGen<0){
                                $totalGen=0;
                            }
                            $html.='<tr>
                                        
                                        <th colspan="3" style="text-align: right;" >Neto a pagar en cuenta nómina</th>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" colspan="2">$'.number_format($totalGen,0, ",", ".").'</td>
                                        
                                    </tr>
                            ';
                            
                        $html.='</table>

                    </div>
                    <br>
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="2">Bases para cálculo de seguridad social</th>
                            </tr>
                            <tr>
                                <td>Ingreso Base Cotización Salud</td><td style="text-align: right;">$'.number_format($empresayLiquidacion->ibc_eps,0, ",", ".").'</td>
                            </tr>
                            <tr>
                                <td>Ingreso Base Cotización Pension</td><td style="text-align: right;">$'.number_format($empresayLiquidacion->ibc_afp,0, ",", ".").'</td>
                            </tr>
                        </table>
                    </div>
                    <br>
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="2">Mensaje Empresarial</th>
                            </tr>
                            <tr>
                                <td style="text-align: justify;">'.$mensajeGen[8]->html.'</td>
                            </tr>
                        </table>
                    </div>
                    <br>
                    <div style="position: absolute; bottom: 40px; width:100%;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td>COLABORADOR</td>
                                <td></td>
                                <td>LA EMPRESA</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td>Cédula o NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                                <td>NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                            </tr>
                            <tr>
                                <td colspan="3"></td>
                                <td>Fecha de elaboración:&nbsp;&nbsp;&nbsp; '.date("d/m/Y").'</td>
                            </tr>
                        </table>

                    </div>
                </div>';
            }
            else if(($empresayLiquidacion->fkTipoLiquidacion == "2" || $empresayLiquidacion->fkTipoLiquidacion == "3") && isset($novedadesRetiro->fecha)){
        
                $contrato = DB::table("contrato","c")
                ->join("tipocontrato as tc","tc.idtipoContrato", "=","c.fkTipoContrato")
                ->where("c.fkEmpleado","=",$empleado->idempleado)
                ->whereIn("c.fkEstado",["1","2","4"])->first();

                $cambioSalario = DB::table("cambiosalario","cs")
                ->where("cs.fkEmpleado","=",$empleado->idempleado)
                ->where("cs.fkEstado","=","5")
                ->first();
                $fechaUltimoCamSal = $empleado->fechaIngreso;
                if(isset($cambioSalario)){
                    $fechaUltimoCamSal = $cambioSalario->fechaCambio;
                }

                $fechaRet1 = $novedadesRetiro->fecha;
                if(substr($fechaRet1, 8, 2) == "31" || (substr($fechaRet1, 8, 2) == "28" && substr($fechaRet1, 5, 2) == "02") || (substr($fechaRet1, 8, 2) == "29" && substr($fechaRet1, 5, 2) == "02") ){
                    $fechaRet1 = $novedadesRetiro->fecha;
                }
                $diasLab = $this->days_360($empleado->fechaIngreso, $fechaRet1) + 1;
                $meses = intval($diasLab/30);
                $diasDemas = $diasLab - ($meses * 30);
                $tiempoTrabTxt = $meses." Meses ".$diasDemas." días";

                $fechaFinMesActual = date("Y-m-t", strtotime($novedadesRetiro->fechaReal));
                $fechaInicioMesActual = date("Y-m-01", strtotime($novedadesRetiro->fechaReal));
                $ultimoBoucher = DB::table("boucherpago", "bp")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9"])
                ->orderBy("bp.idBoucherPago","desc")
                ->first();
               
                
                if(!isset($ultimoBoucher)){
                    $ultimoBoucher = DB::table("boucherpago", "bp")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                    ->whereIn("ln.fkTipoLiquidacion",["3"])
                    ->orderBy("bp.idBoucherPago","desc")
                    ->first();
                }
                else{
                    $ultimoBoucherRetiro = DB::table("boucherpago", "bp")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                    ->whereIn("ln.fkTipoLiquidacion",["3"])
                    ->orderBy("bp.idBoucherPago","desc")
                    ->first();

                    $ultimoBoucher->ibc_afp = $ultimoBoucher->ibc_afp + ($ultimoBoucherRetiro->ibc_afp ?? 0);
                    $ultimoBoucher->ibc_eps = $ultimoBoucher->ibc_eps + ($ultimoBoucherRetiro->ibc_eps ?? 0);
                    $ultimoBoucher->ibc_arl = $ultimoBoucher->ibc_arl + ($ultimoBoucherRetiro->ibc_arl ?? 0);
                    $ultimoBoucher->ibc_ccf = $ultimoBoucher->ibc_ccf + ($ultimoBoucherRetiro->ibc_ccf ?? 0);
                    $ultimoBoucher->ibc_otros = $ultimoBoucher->ibc_otros + ($ultimoBoucherRetiro->ibc_otros ?? 0);
                }
                $IBL = $ultimoBoucher->ibc_eps;
               
                $html.='                    
                <div class="page liquida">
                    <div style="border: 2px solid #000; padding: 5px 10px; font-size: 15px; margin-bottom: 5px;">
                        <table class="tituloTable">
                            <tr>
                                <td rowspan="2">
                                '.(isset($empresayLiquidacion->logoEmpresa) ? '<img style="max-width: 50px; max-height: 50px; margin-right: 5px;" src="data:image/png;base64,'.$base64.'" class="logoEmpresa" />' : '').'
                                </td>
                                <td valign="bottom"><b>'.$empresayLiquidacion->razonSocial.'</b></td>
                            </tr>
                            <tr>
                                <td  valign="top"><b>'.number_format($empresayLiquidacion->documento,0, ",", ".").'-'.$empresayLiquidacion->digitoVerificacion.'</b></td>
                            </tr>
                        </table>
                        <center>
                            <h2 style="margin:0; margin-bottom: 0px; font-size: 20px;">LIQUIDACIÓN DE CONTRATO</h2>
                        </center>
                    </div>
                    <table style="width: 96%; text-align: left;">
                        <tr>
                            <th>
                                Nómina
                            </th>
                            <td>
                                '.$empresayLiquidacion->nom_nombre.'
                            </td>
                            <th>
                                Período liquidación
                            </th>
                            <td>
                                '.date("Y",strtotime($empresayLiquidacion->fechaInicio))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaInicio)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaInicio)).' 
                                a
                                '.date("Y",strtotime($empresayLiquidacion->fechaFin))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaFin)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaFin)).' 
                            </td>
                        </tr>
                        <tr>
                            <th>Empleado</th>
                            <td>'.$empleado->primerApellido." ".$empleado->segundoApellido." ".$empleado->primerNombre." ".$empleado->segundoNombre.'</td>
                            <th>Fecha ingreso</th>
                            <td>'.date("Y",strtotime($empleado->fechaIngreso))."/".$arrMeses[date("m",strtotime($empleado->fechaIngreso)) - 1].'/'.date("d",strtotime($empleado->fechaIngreso)).'</td>
                        </tr>
                        <tr>
                            <th>Identificación</th>
                            <td>'.$empleado->tipoidentificacion.' '.$empleado->numeroIdentificacion.'</td>
                            <th>Fecha Retiro</th>
                            <td>'.date("Y",strtotime($novedadesRetiro->fecha))."/".$arrMeses[date("m",strtotime($novedadesRetiro->fecha)) - 1].'/'.date("d",strtotime($novedadesRetiro->fecha)).'</td>
                        </tr>
                        <tr>
                            <th>Tipo Contrato</th>
                            <td>'.($contrato->nombre ?? "").'</td>
                            <th>Fecha Retiro Real</th>
                            <td>'.date("Y",strtotime($novedadesRetiro->fechaReal))."/".$arrMeses[date("m",strtotime($novedadesRetiro->fechaReal)) - 1].'/'.date("d",strtotime($novedadesRetiro->fechaReal)).'</td>
                        </tr>
                        <tr>
                            <th>Nómina</th>
                            <td>'.$empresayLiquidacion->nom_nombre.'</td>
                            <th>Fecha Último Aumento Salario</th>
                            <td>
                                '.date("Y",strtotime($fechaUltimoCamSal))."/".$arrMeses[date("m",strtotime($fechaUltimoCamSal)) - 1].'/'.date("d",strtotime($fechaUltimoCamSal)).' 
                            </td>
                        </tr>
                        <tr>
                            <th>Régimen</th>
                            <td>'.$empleado->tipoRegimen.'</td>
                            <th>Última Nómina Pagada</th>
                            <td>
                                '.date("Y",strtotime($empresayLiquidacion->fechaLiquida))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaLiquida)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaLiquida)).' 
                            </td>
                        </tr>
                        <tr>
                            <th>Tiempo Trabajado</th>
                            <td>'.$tiempoTrabTxt.'</td>
                            <th>Cargo</th>
                            <td>'.$empleado->nombreCargo.'</td>
                            </td>
                        </tr>
                        <tr>
                            <th>Salario</th>
                            <td>$ '.number_format($conceptoSalario->valor,0, ",", ".").'</td>
                            <th>EPS</th>
                            <td>'.($salud->razonSocial ?? "").'</td>
                        </tr>
                        <tr>
                            <th>Entidad Bancaria</th>
                            <td>'.(isset($entidadBancaria->razonSocial) ? $entidadBancaria->razonSocial : "").'</td>
                            <th>Cuenta</th>
                            <td>'.(isset($entidadBancaria->numeroCuenta) ? $entidadBancaria->numeroCuenta : "").'</td>
                        </tr>
                        <tr>
                            <th>Fondo Pensiones</th>
                            <td>'.(isset($pension->razonSocial) ? $pension->razonSocial : "").'</td>
                            <th>IBL Seguridad Social </th>
                            <td>$ '.number_format($this->roundSup($IBL,-2),0, ",", ".").'</td>
                        </tr>
                        <tr>
                            <th>Fondo Cesantías </th>
                            <td>'.(isset($cesantiasEmp->razonSocial) ? $cesantiasEmp->razonSocial : "").'</td>
                            <th>Motivo Retiro</th>
                            <td>'.$novedadesRetiro->motivoRet.'</td>
                        </tr>
                    </table>
                    <br>';
                    $basePrima = 0;
                    $baseCes = 0;
                    $baseVac = 0;

                    $fechaInicioCes = "";
                    $fechaInicioPrima = "";
                    $fechaInicioVac = $empleado->fechaIngreso;

                    $fechaFinCes = "";
                    $fechaFinPrima = "";
                    $fechaFinVac = $novedadesRetiro->fecha;

                    $diasCes = 0;
                    $diasPrima = 0;
                    $diasVac = 0;

                
                    foreach($idItemBoucherPago as $itemBoucherPago){
                        if($itemBoucherPago->fkConcepto == 30){
                            $baseVac = $itemBoucherPago->base;
                            $diasVac = $itemBoucherPago->cantidad;
                        }

                        if($itemBoucherPago->fkConcepto == 58){
                            $basePrima = $itemBoucherPago->base;
                            $fechaInicioPrima =  $itemBoucherPago->fechaInicio;
                            $fechaFinPrima =  $itemBoucherPago->fechaFin;                            
                            $diasPrima = (15 / 180) * $itemBoucherPago->cantidad;
                        }
                        
                        if($itemBoucherPago->fkConcepto == 66){
                            $baseCes = $itemBoucherPago->base;
                            $fechaInicioCes =  $itemBoucherPago->fechaInicio;
                            $fechaFinCes =  $itemBoucherPago->fechaFin;
                            $diasCes = (($itemBoucherPago->cantidad * $nomina->diasCesantias) / 360);
                        }
                        
                    }
                    $html.='<div style="border: 2px solid #000; padding: 0px 10px; margin-bottom: 5px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="6">Promedio Liquidación Prestaciones</th>
                            </tr>
                            <tr>
                                <th>Promedio Cesantías</th>
                                <td>$'.number_format($baseCes,0, ",", ".").'</td>
                                <th>Promedio Vacaciones</th>
                                <td>$'.number_format($baseVac,0, ",", ".").'</td>
                                <th>Promedio Prima</th>
                                <td>$'.number_format($basePrima,0, ",", ".").'</td>
                            </tr>
                        </table>
                    </div>';
                    $html.='<div style="border: 2px solid #000; padding: 0px 10px; margin-bottom: 5px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="5">Valores Consolidados</th>
                            </tr>
                            <tr>
                                <th  style="background: #CCC; text-align: center;">Tipo Consolidado </th>
                                <th  style="background: #CCC; text-align: center;">Fecha Inicio</th>
                                <th  style="background: #CCC; text-align: center;">Fecha Fin</th>
                                <th  style="background: #CCC; text-align: center;">Total Días</th>
                            </tr>
                            <tr>
                                <th>Cesantías consolidadas</th>
                                <td>'.$fechaInicioCes.'</td>
                                <td>'.$fechaFinCes.'</td>
                                <td>'.round($diasCes,2).'</td>
                            </tr>
                            <tr>
                                <th>Prima de servicios consolidadas</th>
                                <td>'.$fechaInicioPrima.'</td>
                                <td>'.$fechaFinPrima.'</td>
                                <td>'.round($diasPrima,2).'</td>
                            </tr>
                            <tr>
                                <th>Vacaciones consolidadas</th>
                                <td>'.$fechaInicioVac.'</td>
                                <td>'.$fechaFinVac.'</td>
                                <td>'.round($diasVac,2).'</td>
                            </tr>
                        </table>
                    </div>
                    <div style="border: 2px solid #000; padding: 0px 10px; margin-bottom: 5px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td colspan="4"></td>
                                <th style="background: #d89290; text-align: center;" colspan="2">Pagos y Descuentos</th>
                                <td></td>
                            </tr>
                            <tr>
                                <th style="background: #CCC; text-align: center;">Conceptos Liquidados</th>
                                <th style="background: #CCC; text-align: center;">Cantidad</th>
                                <th style="background: #CCC; text-align: center;">Unidad</th>
                                <th style="background: #CCC; text-align: center;">Base</th>
                                <th style="background: #CCC; text-align: center;">Pagos</th>
                                <th style="background: #CCC; text-align: center;">Descuentos</th>
                                <th style="background: #CCC; text-align: center;">Saldo Cuota</th>                                
                            </tr>';
                            $totalDesc = 0;
                            $totalPag = 0;
                            foreach($idItemBoucherPago as $itemBoucherPago){
                                $html.='<tr style="border-bottom: 1px solid #B0B0B0;">
                                    <td style="border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->nombre.'</td>';
                                    if($itemBoucherPago->fkConcepto == 58){
                                        $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.round($diasPrima,2).'</td>';
                                    }
                                    else if($itemBoucherPago->fkConcepto == 66 || $itemBoucherPago->fkConcepto == 69){
                                        
                                        $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.round($diasCes,2).'</td>';
                                    }
                                    else{
                                        $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->cantidad.'</td>';
                                    }

                                    $html.='
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->tipoUnidad.'</td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->base,0, ",", ".").'</td>';

                                    
                                    
                                    if($itemBoucherPago->valor > 0){
                                        $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor,0, ",", ".").'</td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>';
                                        $totalPag = $totalPag + $itemBoucherPago->valor;
                                    }
                                    else{
                                        $html.='<td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor*-1,0, ",", ".").'</td>';
                                        $totalDesc = $totalDesc + $itemBoucherPago->valor;
                                    }
                                    $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$0</td>';

                                $html.='</tr>';
                            }
                            $html.='<tr>
                                        
                                        <th colspan="4" style="text-align: right;">Totales</th>
                                        <th style="text-align: right; border: 1px solid #B0B0B0;">$'.number_format($totalPag,0, ",", ".").'</td>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" >$'.number_format($totalDesc*-1,0, ",", ".").'</td>
                                    </tr>
                            ';
                            $totalGen = $totalPag + $totalDesc;
                            if($totalGen<0){
                                $totalGen=0;
                            }
                            $html.='<tr>
                                        
                                        <th colspan="3" style="text-align: right;" >Neto a pagar</th>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" colspan="2">$'.number_format($totalGen,0, ",", ".").'</td>
                                        
                                    </tr>
                            ';
                            $valorText =$this->convertir(($totalPag + $totalDesc));
                            
                        $html.='</table>
                    </div>
                    <div style="border: 2px solid #000; padding: 10px 20px; font-size: 10px; font-weight: bold; margin-bottom: 5px;">
                        El valor neto a pagar es: '.strtoupper($valorText).' PESOS M/CTE
                    </div><br>';
                    if(sizeof($itemsBoucherPagoFueraNomina)>0){
                        $html.='<div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                        <tr>
                                <th style="background: #CCC; text-align: center;" colspan="5">Fuera de nómina</th>
                        </tr>
                        <tr>
                            <th style="background: #CCC; text-align: center;">Conceptos</th>
                            <th style="background: #CCC; text-align: center;">Cantidad</th>
                            <th style="background: #CCC; text-align: center;">Unidad</th>
                            <th style="background: #CCC; text-align: center;">Pagos</th>
                            <th style="background: #CCC; text-align: center;">Descuentos</th>
                        </tr>
                        ';
                        foreach($itemsBoucherPagoFueraNomina as $itemBoucherPagoFueraNomina){
                            $html.='<tr style="border-bottom: 1px solid #B0B0B0;">
                            <td style="border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->nombre.'</td>
                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->cantidad.'</td>
                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->tipoUnidad.'</td>';
                            
                            if($itemBoucherPagoFueraNomina->valor > 0){
                                $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPagoFueraNomina->valor,0, ",", ".").'</td>
                                    <td style="border-bottom: 1px solid #B0B0B0;"></td>';
                                
                            }
                            else{
                                $html.='<td style="border-bottom: 1px solid #B0B0B0;"></td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPagoFueraNomina->valor*-1,0, ",", ".").'</td>';
                                
                            }
                            $html.='</tr>';
                        }
                        $html.='</table></div><br> 
                        </div>
                        <div class="page_break"></div>
                        <div class="page">';
                    }
                    $html.='<div style="border: 2px solid #000; padding: 0px 10px; margin-bottom: 5px;">
                        <center><h4 style="margin:0px;" >Observaciones</h4></center>
                        <table>
                            <tr>
                                <th style="background: #CCC; text-align: center;">CONSTANCIAS - Se hace constar expresamente los siguiente:</th>
                            </tr>
                            <td style="font-size: 8px; text-align: justify;">'.$mensajeGen[9]->html.'</td>
                        </table>
                        <table style="width: 100%;">
                            <tr>
                                <th style="border: 1px solid #000; width:33%;">ELABORÓ: '.$userSolicita.'</th>
                                <th style="border: 1px solid #000; width:33%;">REVISÓ: '.$userAprueba.'</th>
                                <th style="border: 1px solid #000; width:33%;">APROBÓ:</th>
                            </tr>
                        </table>
                    </div>
                    <div style="position: absolute; bottom: 40px; width:100%;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td>COLABORADOR</td>
                                <td></td>
                                <td>LA EMPRESA</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td>Cédula o NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                                <td>NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                            </tr>
                            <tr>
                                <td colspan="3"></td>
                                <td>Fecha de elaboración:&nbsp;&nbsp;&nbsp; '.date("d/m/Y").'</td>
                            </tr>
                        </table>
                    </div>
                </div>';
            }
            else{
                $html.='<div class="page">
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                        ';
                        $html.='                        
                        <table class="tituloTable">
                            <tr>
                                <td rowspan="2">
                                '.(isset($empresayLiquidacion->logoEmpresa) ? '<img style="max-width: 50px; max-height: 50px; margin-right: 5px;" src="data:image/png;base64,'.$base64.'" class="logoEmpresa" />' : '').'
                                </td>
                                <td valign="bottom"><b>'.$empresayLiquidacion->razonSocial.'</b></td>
                            </tr>
                            <tr>
                                <td  valign="top"><b>'.number_format($empresayLiquidacion->documento,0, ",", ".").'-'.$empresayLiquidacion->digitoVerificacion.'</b></td>
                            </tr>
                        </table>
                        <center>
                            <h2 style="margin:0; margin-bottom: 10px;">Comprobante pago nómina</h2>
                        </center>
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th>
                                    Nómina
                                </th>
                                <td>
                                    '.$empresayLiquidacion->nom_nombre.'
                                </td>
                                <th>
                                    Periodo liquidación
                                </th>
                                <td>
                                    '.date("Y",strtotime($empresayLiquidacion->fechaInicio))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaInicio)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaInicio)).' 
                                    a
                                    '.date("Y",strtotime($empresayLiquidacion->fechaFin))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaFin)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaFin)).' 
                                </td>
                            </tr>
                            <tr>
                                <th>Empleado</th>
                                <td>'.$empleado->primerApellido." ".$empleado->segundoApellido." ".$empleado->primerNombre." ".$empleado->segundoNombre.'</td>
                                <th>Salario</th>
                                <td>$ '.number_format($conceptoSalario->valor,0, ",", ".").'</td>
                            </tr>
                            <tr>
                                <th>Identificación</th>
                                <td>'.$empleado->tipoidentificacion.' '.$empleado->numeroIdentificacion.'</td>
                                <th>Cargo</th>
                                <td>'.$empleado->nombreCargo.'</td>
                            </tr>
                            <tr>
                                <th>Entidad Bancaria</th>
                                <td>'.(isset($entidadBancaria->razonSocial) ? $entidadBancaria->razonSocial : "").'</td>
                                <th>Cuenta</th>
                                <td>'.(isset($entidadBancaria->numeroCuenta) ? $entidadBancaria->numeroCuenta : "").'</td>
                            </tr>
                            <tr>
                                <th>EPS</th>
                                <td>'.(isset($salud->razonSocial) ? $salud->razonSocial : "").'</td>
                                <th>Fondo Pensiones</th>
                                <td>'.(isset($pension->razonSocial) ? $pension->razonSocial : "").'</td>
                            </tr>
                        </table>
                    </div><br>';
                    if(sizeof($idItemBoucherPago)>0){
                        $html.='<div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                                <th style="background: #d89290; text-align: center;" colspan="2">Devengado</th>
                                <th style="background: #d89290; text-align: center;">Deducciones</th>                        
                            </tr>
                            <tr>
                                <th style="background: #CCC; text-align: center;">Conceptos Liquidados</th>
                                <th style="background: #CCC; text-align: center;">Cantidad</th>
                                <th style="background: #CCC; text-align: center;">Unidad</th>
                                <th style="background: #CCC; text-align: center;">Pagos</th>
                                <th style="background: #CCC; text-align: center;">Beneficios</th>
                                <th style="background: #CCC; text-align: center;">Descuentos</th>
                            </tr>';
                            $totalDesc = 0;
                            $totalPag = 0;
                
                            foreach($idItemBoucherPago as $itemBoucherPago){
                                $html.='<tr style="border-bottom: 1px solid #B0B0B0;">
                                    <td style="border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->nombre.'</td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->cantidad.'</td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->tipoUnidad.'</td>';
                                    
                                    if($itemBoucherPago->valor > 0){
                                        $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor,0, ",", ".").'</td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>';
                                        $totalPag = $totalPag + $itemBoucherPago->valor;
                                    }
                                    else{
                                        $html.='<td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor*-1,0, ",", ".").'</td>';
                                        $totalDesc = $totalDesc + $itemBoucherPago->valor;
                                    }

                                $html.='</tr>';
                            }
                            


                            $html.='<tr>
                                        
                                        <th colspan="3" style="text-align: right;">Totales</th>
                                        <td style="text-align: right; border: 1px solid #B0B0B0;">$'.number_format($totalPag,0, ",", ".").'</td>
                                        <td style="text-align: right; border: 1px solid #B0B0B0;"></td>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" >$'.number_format($totalDesc*-1,0, ",", ".").'</td>
                                    </tr>
                            ';
                            $totalGen = $totalPag + $totalDesc;
                            if($totalGen<0){
                                $totalGen=0;
                            }
                            
                            $html.='<tr>
                                        
                                        <th colspan="3" style="text-align: right;" >Neto a pagar en cuenta nómina</th>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" colspan="2">$'.number_format($totalGen,0, ",", ".").'</td>
                                        
                                    </tr>
                            ';
                            
                        $html.='</table>

                    </div>
                    <br>';
                    }
                    

                    if(sizeof($itemsBoucherPagoFueraNomina)>0){
                        $html.='<div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                        <tr>
                                <th style="background: #CCC; text-align: center;" colspan="5">Fuera de nómina</th>
                        </tr>
                        <tr>
                            <th style="background: #CCC; text-align: center;">Conceptos</th>
                            <th style="background: #CCC; text-align: center;">Cantidad</th>
                            <th style="background: #CCC; text-align: center;">Unidad</th>
                            <th style="background: #CCC; text-align: center;">Pagos</th>
                            <th style="background: #CCC; text-align: center;">Descuentos</th>
                        </tr>
                        ';
                        foreach($itemsBoucherPagoFueraNomina as $itemBoucherPagoFueraNomina){
                            $html.='<tr style="border-bottom: 1px solid #B0B0B0;">
                            <td style="border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->nombre.'</td>
                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->cantidad.'</td>
                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->tipoUnidad.'</td>';
                            
                            if($itemBoucherPagoFueraNomina->valor > 0){
                                $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPagoFueraNomina->valor,0, ",", ".").'</td>
                                    <td style="border-bottom: 1px solid #B0B0B0;"></td>';
                                
                            }
                            else{
                                $html.='<td style="border-bottom: 1px solid #B0B0B0;"></td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPagoFueraNomina->valor*-1,0, ",", ".").'</td>';
                                
                            }
                            $html.='</tr>';
                        }
                        $html.='</table></div><br>';
                    }

                    $html.='<div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="2">Bases para cálculo de seguridad social</th>
                            </tr>
                            <tr>
                                <td>Ingreso Base Cotización Salud</td><td style="text-align: right;">$'.number_format($empresayLiquidacion->ibc_eps,0, ",", ".").'</td>
                            </tr>
                            <tr>
                                <td>Ingreso Base Cotización Pension</td><td style="text-align: right;">$'.number_format($empresayLiquidacion->ibc_afp,0, ",", ".").'</td>
                            </tr>
                        </table>
                    </div>
                    <br>
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="2">Mensaje Empresarial</th>
                            </tr>
                            <tr>
                                <td style="text-align: justify;">'.$mensajeGen[8]->html.'</td>
                            </tr>
                        </table>
                    </div>
                    <br>
                    <div style="position: absolute; bottom: 40px; width:100%;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td>COLABORADOR</td>
                                <td></td>
                                <td>LA EMPRESA</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td>Cédula o NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                                <td>NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                            </tr>
                            <tr>
                                <td colspan="3"></td>
                                <td>Fecha de elaboración:&nbsp;&nbsp;&nbsp; '.date("d/m/Y").'</td>
                            </tr>
                        </table>

                    </div>
                </div>
                ';
            }
            if(sizeof($novedadesVacacionActual) > 0){
                
                $novedadesRetiro = DB::table("novedad","n")
                ->select("r.fecha")
                ->join("retiro AS r", "r.idRetiro","=","n.fkRetiro")
                ->where("n.fkEmpleado", "=", $empleado->idempleado)
                ->whereRaw("n.fkPeriodoActivo in(
                    SELECT p.idPeriodo from periodo as p where p.fkEmpleado = '".$empleado->idempleado."' and p.fkEstado = '1'
                )")
                ->whereIn("n.fkEstado",["7", "8"])
                ->whereNotNull("n.fkRetiro")
                ->whereBetween("n.fechaRegistro",[$empresayLiquidacion->fechaInicio, $empresayLiquidacion->fechaFin])->first();
                $fechaFinalVaca = $empresayLiquidacion->fechaFin;
                $periodoPagoVac = $this->days_360($empleado->fechaIngreso,$empresayLiquidacion->fechaFin) + 1 ;
                if(isset($novedadesRetiro)){
                    if(strtotime($empresayLiquidacion->fechaFin) > strtotime($novedadesRetiro->fecha)){
                        $periodoPagoVac = $this->days_360($empleado->fechaIngreso,$novedadesRetiro->fecha) + 1 ;
                        $fechaFinalVaca = $novedadesRetiro->fecha;
                    }
                }

                $diasVac = $periodoPagoVac * 15 / 360;

                $novedadesVacacion = DB::table("novedad","n")
                ->select("v.*", "c.nombre","c.idconcepto", "ibpn.valor")
                ->join("concepto as c","c.idconcepto","=","n.fkConcepto")
                ->join("vacaciones as v","v.idVacaciones","=","n.fkVacaciones")
                ->join("item_boucher_pago_novedad as ibpn","ibpn.fkNovedad","=","n.idNovedad")
                ->join("item_boucher_pago as ibp","ibp.idItemBoucherPago","=","ibpn.fkItemBoucher")
                ->where("n.fkEmpleado","=",$empleado->idempleado)
                ->whereRaw("n.fkPeriodoActivo in(
                    SELECT p.idPeriodo from periodo as p where p.fkEmpleado = '".$empleado->idempleado."' and p.fkEstado = '1'
                )")
                ->where("ibp.fkBoucherPago","<>",$idBoucherPago)
                ->whereIn("n.fkEstado",["7"]) // Pagada o sin pagar-> no que este eliminada
                ->whereNotNull("n.fkVacaciones")
                ->get();
                //$diasVac = $totalPeriodoPagoAnioActual * 15 / 360;
                foreach($novedadesVacacion as $novedadVacacion){
                    $diasVac = $diasVac - $novedadVacacion->diasCompensar;
                }
                if(isset($diasVac) && $diasVac < 0){
                    $diasVac = 0;
                }

            
                
                $html.='<div class="page_break"></div>
                    <div class="page">
                        <div style="border: 2px solid #000; padding: 10px 20px;">
                            <table class="tituloTable">
                                <tr>
                                    <td rowspan="2">
                                    '.(isset($empresayLiquidacion->logoEmpresa) ? '<img style="max-width: 50px; max-height: 50px; margin-right: 5px;" src="data:image/png;base64,'.$base64.'" class="logoEmpresa" />' : '').'
                                    </td>
                                    <td valign="bottom"><b>'.$empresayLiquidacion->razonSocial.'</b></td>
                                </tr>
                                <tr>
                                    <td  valign="top"><b>'.number_format($empresayLiquidacion->documento,0, ",", ".").'-'.$empresayLiquidacion->digitoVerificacion.'</b></td>
                                </tr>
                            </table>
                            <center>
                                <h2 style="margin:0; margin-bottom: 10px;">Comprobante de Pago de Vacaciones</h2>
                            </center>
                            <table style="width: 100%; text-align: left;">
                                <tr>
                                    <th>Empleado</th>
                                    <td>'.$empleado->primerApellido." ".$empleado->segundoApellido." ".$empleado->primerNombre." ".$empleado->segundoNombre.'</td>
                                    <th>Fecha ingreso</th>
                                    <td>'.date("Y",strtotime($empleado->fechaIngreso))."/".$arrMeses[date("m",strtotime($empleado->fechaIngreso)) - 1].'/'.date("d",strtotime($empleado->fechaIngreso)).'</td>
                                </tr>
                                <tr>
                                    <th>Identificación</th>
                                    <td>'.$empleado->tipoidentificacion.' '.$empleado->numeroIdentificacion.'</td>
                                    <th>Cargo</th>
                                    <td>'.$empleado->nombreCargo.'</td>
                                </tr>
                                <tr>
                                    <th>Días Pendientes Consolidado</th>
                                    <td>'.round($diasVac,2).'</td>
                                    <th>Fecha Corte Consolidado:</th>
                                    <td>'.date("Y",strtotime($fechaFinalVaca))."/".$arrMeses[date("m",strtotime($fechaFinalVaca)) - 1].'/'.date("d",strtotime($fechaFinalVaca)).'</td>
                                </tr>
                                
                            </table>
                    </div>
                    <br>
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                    <table style="width: 100%; text-align: left; font-size: 10px;">
                        <tr>
                            <th style="background: #d89290; text-align: center; font-size: 10px;" colspan="11">Liquidación de Vacaciones</th>                         
                        </tr>
                        <tr>
                            <th style="background: #CCC; text-align: center; font-size: 10px;" rowspan="2">Tipo Movimiento</th>
                            <th style="background: #CCC; text-align: center; font-size: 10px;" colspan="2">Periodo Causación</th>
                            <th style="background: #CCC; text-align: center; font-size: 10px;" colspan="4">Periodo Vacaciones</th>
                            <th style="background: #CCC; text-align: center; font-size: 10px;" colspan="2">Días Pagados</th>
                            <th style="background: #CCC; text-align: center; font-size: 10px;" rowspan="2">Promedio<br>Diario</th>
                            <th style="background: #CCC; text-align: center; font-size: 10px;" rowspan="2">Valor<br>Liquidado</th>
                        </tr>
                        <tr>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Fecha Inicio</th>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Fecha Fin</th>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Fecha Inicio</th>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Fecha Fin</th>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Días</th>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Regreso</th>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Tiempo</th>
                            <th style="background: #CCC; text-align: center;font-size: 10px;">Dinero</th>
                        </tr>
                        
                ';


                $periodosVacaciones = array();
                $fechaInicioVacaciones = $empleado->fechaIngreso;
                $agregarPeriodos = true;
                /*
                
                do{
                    $date_time_inicio=new DateTime($fechaInicioVacaciones);
                    $date_time_inicio->add(new DateInterval('P1Y'));

                    $fechaFin = $date_time_inicio->format('Y-m-d');
                    if(strtotime($fechaFin) > strtotime($empresayLiquidacion->fechaFin)){
                        $fechaFin = $empresayLiquidacion->fechaFin;
                        $agregarPeriodos = false;
                    }
                    $periodosVacacionFila = array();
                    $periodosVacacionFila["fechaInicio"] = $fechaInicioVacaciones;
                    $periodosVacacionFila["fechaFin"] = $fechaFin;
                    $totalDiasMaximo = (sizeof($periodosVacaciones) + 1) * 15;                    
                    $totalDiasMinimo = sizeof($periodosVacaciones) * 15;
                   
                    $periodosVacacionFila["vacaciones"] = array();
                    foreach($novedadesVacacionActual as $novedadVacacion){
                        $diasOtroPeriodo = 0;
                        foreach($periodosVacaciones as $periodoVacaciones){
                            foreach($periodoVacaciones['vacaciones'] as $vacaciones){
                                if($vacaciones["idVacaciones"] == $novedadVacacion->idVacaciones){
                                    $diasOtroPeriodo = $vacaciones["dias"];
                                }
                            }
                        }
                        $diasVac = $novedadVacacion->diasCompensar;
                        if($diasOtroPeriodo != 0){
                            $diasVac = $novedadVacacion->diasCompensar - $diasOtroPeriodo;
                        }
                        if($diasVac != 0){
                            $diasPeriodoActual = 0;
                            foreach($periodosVacacionFila["vacaciones"] as $vac2){
                                $diasPeriodoActual = $diasPeriodoActual + $vac2["dias"];
                            }
                            if($diasPeriodoActual < 15){
                                if(($diasVac + $diasPeriodoActual) >= 15){
                                    $diasVac = (15 - $diasPeriodoActual);
                                    
                                    array_push($periodosVacacionFila["vacaciones"], [
                                        "dias" => $diasVac,
                                        "idVacaciones" => $novedadVacacion->idVacaciones
                                    ]);
                                }
                                else{
                                    array_push($periodosVacacionFila["vacaciones"], [
                                        "dias" => $diasVac,
                                        "idVacaciones" => $novedadVacacion->idVacaciones
                                    ]);
                                }
                            }
                        }
                    }



                    array_push($periodosVacaciones, $periodosVacacionFila);



                }while($agregarPeriodos);*/
         

                $totalVac = 0;
                foreach($novedadesVacacionActual as $novedadVacacion){
                    $tipoMov = str_replace("VACACIONES", "", $novedadVacacion->nombre);

                    
                    


                    $html.='
                        <tr>
                            <td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">'.$tipoMov.'</td>
                            <td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.$empleado->fechaIngreso.'</td>
                            <td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.$novedadVacacion->fechaInicio.'</td>';
                        if($novedadVacacion->idconcepto == 29){
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.$novedadVacacion->fechaInicio.'</td>';
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.$novedadVacacion->fechaFin.'</td>';
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.$novedadVacacion->diasCompensar.'</td>';
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.date("Y-m-d",strtotime($novedadVacacion->fechaFin."+1 day")).'</td>';
                        }
                        else{
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;"></td>';
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;"></td>';
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">'.$novedadVacacion->diasCompensar.'</td>';
                            $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;"></td>';
                        }
                        
                        $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">'.$novedadVacacion->diasCompensar.'</td>';
                        $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">$'.number_format($novedadVacacion->valor,0, ",", ".").'</td>';
                        $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">$'.number_format($novedadVacacion->valor/$novedadVacacion->diasCompensar,0, ",", ".").'</td>';
                        $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">$'.number_format($novedadVacacion->valor,0, ",", ".").'</td>';

                        $html.='</tr>
                    ';
                    $totalVac = $totalVac + $novedadVacacion->valor;
                    if($totalVac<0){
                        $totalVac=0;
                    }
                }    
                $html.='
                    <tr>
                        <th style="text-align: right; font-size: 10px;" colspan="9">TOTAL LIQUIDADO VACACIONES</th>
                        <td style="text-align: right; border: 1px solid #B0B0B0; font-size: 10px;" colspan="2">$'.number_format($totalVac,0, ",", ".").'</td>
                    </tr>            
                    </table>
                </div>
                <br>
                    <center><h4>Observaciones</h4></center>
                <br>
                <div style="border: 2px solid #000; padding: 10px 20px; min-height: 50px;">
                <br><br><br>
                </div>
                <div style="position: absolute; bottom: 40px; width:100%;">
                    <table style="width: 100%; text-align: left;">
                        <tr>
                            <td>COLABORADOR</td>
                            <td></td>
                            <td>LA EMPRESA</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>Cédula o NIT</td>
                            <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                            <td>NIT</td>
                            <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                        </tr>
                        <tr>
                            <td colspan="3"></td>
                            <td>Fecha de elaboración:&nbsp;&nbsp;&nbsp; '.date("d/m/Y").'</td>
                        </tr>
                    </table>
                    <table style="width: 100%;">
                        <tr>
                            <th style="border: 1px solid #000; width:33%;">ELABORÓ: '.$userSolicita.'</th>
                            <th style="border: 1px solid #000; width:33%;">REVISÓ: '.$userAprueba.'</th>
                            <th style="border: 1px solid #000; width:33%;">APROBÓ:</th>
                        </tr>
                    </table>
                </div>
                ';
            }            

            
            $html.='
            </body>
        </html>
        ';
        
        $dompdf->loadHtml($html ,'UTF-8');

        // (Optional) Setup the paper size and orientation
        $dompdf->setPaper('Letter', 'portrait');
        // Render the HTML as PDF
        $dompdf->render();
        //@ts-ignore
        $dompdf->getCanvas()->get_cpdf()->setEncryption($empleado->numeroIdentificacion, $empleado->numeroIdentificacion);
        // Output the generated PDF to Browser
        $dompdf->stream("Comprobante de Pago ".$idBoucherPago.".pdf", array('compress' => 1, 'Attachment' => 0));
    }
    
    public function diasVacacionesDisponibles($idEmpleado){

        
        $empleado = DB::table("empleado","e")->where("e.idempleado","=",$idEmpleado)->first();
        $fechaFin = date("Y-m-d");

        $novedadesRetiro = DB::table("novedad","n")
        ->select("r.fecha")
        ->join("retiro AS r", "r.idRetiro","=","n.fkRetiro")
        ->where("n.fkEmpleado", "=", $empleado->idempleado)
        ->whereRaw("n.fkPeriodoActivo in(
            SELECT p.idPeriodo from periodo as p where p.fkEmpleado = '".$empleado->idempleado."' and p.fkEstado = '1'
        )")
        ->whereIn("n.fkEstado",["7","8"])
        ->whereNotNull("n.fkRetiro")
        ->first();

        $fechaFin = date("Y-m-d");

        $periodoPagoVac = $this->days_360($empleado->fechaIngreso,$fechaFin) + 1 ;

        if(isset($novedadesRetiro)){
            if(strtotime($fechaFin) > strtotime($novedadesRetiro->fecha)){
                $periodoPagoVac = $this->days_360($empleado->fechaIngreso,$novedadesRetiro->fecha) + 1 ;
            }
        }
        $diasVac = $periodoPagoVac * 15 / 360;
        $novedadesVacacion = DB::table("novedad","n")
        ->join("vacaciones as v","v.idVacaciones","=","n.fkVacaciones")
        ->where("n.fkEmpleado","=",$empleado->idempleado)
        ->whereRaw("n.fkPeriodoActivo in(
            SELECT p.idPeriodo from periodo as p where p.fkEmpleado = '".$empleado->idempleado."' and p.fkEstado = '1'
        )")
        ->whereIn("n.fkEstado",["7", "8"]) // Pagada o sin pagar-> no que este eliminada
        ->whereNotNull("n.fkVacaciones")
        ->get();
        //$diasVac = $totalPeriodoPagoAnioActual * 15 / 360;
        foreach($novedadesVacacion as $novedadVacacion){
            $diasVac = $diasVac - $novedadVacacion->diasCompensar;
        }
        if(isset($diasVac) && $diasVac < 0){
            $diasVac = 0;
        }
        $diasVac = intval($diasVac*100);
        $diasVac = $diasVac/100;
        return response()->json([
            "success" => true,
            "diasVac" => floatval($diasVac),
            "fechaIngreso" => $empleado->fechaIngreso,
            "fechaCorteCalculo" => $fechaFin
        ]);


    }
    public function seleccionarDocumentoSeguridad(){
        $dataUsu = UsuarioController::dataAdminLogueado();
        $empresas = DB::table("empresa", "e");
        if(isset($dataUsu) && $dataUsu->fkRol == 2){            
            $empresas = $empresas->whereIn("idempresa", $dataUsu->empresaUsuario);
        }
        $empresas = $empresas->orderBy("razonSocial")->get();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu 'Documento seguridad social'");

        return view('/reportes.seleccionarDocumentoSeguridad',[
            'empresas' => $empresas,
            "dataUsu" => $dataUsu            
        ]);
    }
    public function documentoSSTxt(Request $req){
        //Anexo-tecnico-2-2016-pila.pdf Seccion 2.1.1.1 ---- Pag 20 pdf
        $idEmpresa = $req->empresa;
        $fechaDocumento = $req->fechaDocumento;
        $arrayMuestra = $this->documentoSSArray($idEmpresa, $fechaDocumento);
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", generó un 'Documento seguridad social' de la fecha:".$fechaDocumento.", para la empresa:".$idEmpresa);
        
        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename=Informe_ss_.txt');
        foreach($arrayMuestra[0] as $arr){
            echo $arr;
        }
        echo "\n"; 
        for($j=1; $j<sizeof($arrayMuestra); $j++){
            for($i=0; $i<=97; $i++){
                echo $arrayMuestra[$j][$i];                
            }
            echo "\n";
        }


    

    }


    public function documentoSSArray($idEmpresa, $fechaDocumento){
        //Anexo-tecnico-2-2016-pila.pdf Seccion 2.1.1.1 ---- Pag 20 pdf
        
        $fechaInicioMesActual = date("Y-m-01", strtotime($fechaDocumento));
        $fechaFinMesActual = date("Y-m-t", strtotime($fechaDocumento));

        $fechaInicioMes = date("Y-m-01", strtotime($fechaDocumento));

        $empresa = DB::table('empresa',"e")
        ->select("e.razonSocial","e.documento","ti.siglaPila", "te.codigoTercero as codigoArl", "e.digitoVerificacion", 
        "e.exento", "e.pagoParafiscales", "a.riesgo","a.ciiu","a.codigo")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion","=","e.fkTipoIdentificacion")
        ->join("tercero as te","te.idTercero","=","e.fkTercero_ARL")
        ->leftJoin("actividad_economica_decreto_768 as a", "a.id","=","e.fkActividadEconomica768")
        ->where("idEmpresa","=",$idEmpresa)
        ->first();
        
        

        $empleados = DB::table('empleado', 'e')
        ->selectRaw("count(*) as cuenta")
        ->join("boucherpago as bp","bp.fkEmpleado", "=", "e.idempleado")
        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
        ->join("nomina as n","n.idNomina", "=","ln.fkNomina")
        ->where("n.fkEmpresa","=",$idEmpresa)
        ->whereIn("ln.fkTipoLiquidacion",["1","2"]) //1 - Normal, 2- Retiro
        ->where("ln.fkEstado","=","5")
        ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")->first();
        


        $arrayMuestra = array();
        $arrayMuestra[0] = array();
        $arrayMuestra[0][0] = $this->plantillaTxt("01",2,"","right");
        $arrayMuestra[0][1] = $this->plantillaTxt("1",1,"","right");
        $arrayMuestra[0][2] = $this->plantillaTxt("1",4,"0","right");
        $arrayMuestra[0][3] = $this->plantillaTxt($empresa->razonSocial,200," ","left");
        $arrayMuestra[0][4] = $this->plantillaTxt($empresa->siglaPila,2,"","left");
        $arrayMuestra[0][5] = $this->plantillaTxt($empresa->documento,16," ","left");
        $arrayMuestra[0][6] = $this->plantillaTxt($empresa->digitoVerificacion,1," ","left");
        $arrayMuestra[0][7] = $this->plantillaTxt("E",1," ","left");//Planilla empleados
        $arrayMuestra[0][8] = $this->plantillaTxt("",10," ","left");//Número de la planilla asociada
        $arrayMuestra[0][9] = $this->plantillaTxt("",10," ","left");//Fecha de pago Planilla asociada
        $arrayMuestra[0][10] = $this->plantillaTxt("U",1,"","left");//Unico
        $arrayMuestra[0][11] = $this->plantillaTxt("",10," ","left");//Código de la sucursal del aportante
        $arrayMuestra[0][12] = $this->plantillaTxt("",40," ","left");//Nombre de la sucursal
        $arrayMuestra[0][13] = $this->plantillaTxt($empresa->codigoArl,6," ","left");
        $arrayMuestra[0][14] = $this->plantillaTxt(date("Y-m",strtotime($fechaDocumento)),7," ","left");
        $arrayMuestra[0][15] = $this->plantillaTxt(date("Y-m",strtotime($fechaInicioMes." +1 month")),7," ","left");
        $arrayMuestra[0][16] = $this->plantillaTxt("",10,"0","left");//Número de radicación
        $arrayMuestra[0][17] = $this->plantillaTxt("",10," ","left");//Fecha de pago
        $arrayMuestra[0][18] = $this->plantillaTxt($empleados->cuenta,5,"0","right");//Número total de cotizantes
        $arrayMuestra[0][19] = $this->plantillaTxt("",12,"0","right");//Valor total nomina
        $arrayMuestra[0][20] = $this->plantillaTxt("1",2,"0","right");//Tipo de aportante
        $arrayMuestra[0][21] = $this->plantillaTxt("0",2,"0","right");//Código del operador de información
        

        
        $empleadosGen = DB::table('empleado', 'e')
        ->select("e.*", "dp.*", "ti.siglaPila","p.fkEstado as estado", "p.fkNomina as nominaPeriodo", "p.idPeriodo"
        ,   "n.periodo as periodoNominaDias",
            "p.fkNomina as fkNominaPeriodo", 
            "p.fechaInicio as fechaInicioPeriodo", 
            "p.fechaFin as fechaFinPeriodo",
            "p.salario as salarioPeriodo", 
            "p.fkCargo as fkCargoPeriodo", 
            "p.fkTipoContrato as fkTipoContratoPeriodo", 
            "p.fkTipoCotizante as fkTipoCotizantePeriodo", 
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
            "p.aplicaSubsidio as aplicaSubsidioPeriodo"
        )
        ->join("datospersonales AS dp", "e.fkDatosPersonales", "=" , "dp.idDatosPersonales")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion","=","dp.fkTipoIdentificacion")
        ->join("periodo as p","p.fkEmpleado", "=","e.idempleado")
        ->join("nomina as n", "n.idNomina", "=","p.fkNomina")
        ->leftJoin('boucherpago as bp', function ($join) {
            $join->on('bp.fkEmpleado', '=', 'e.idempleado')
                ->on('bp.fkPeriodoActivo', '=', 'p.idPeriodo');                
        })
        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
        ->where("n.fkEmpresa","=",$idEmpresa)
        ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9"]) //1 - Normal, 2- Retiro
        ->where("ln.fkEstado","=","5")
        ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")    
        //->whereIn("dp.numeroIdentificacion",["1003741115"])
        ->distinct()
        ->orderby("e.idempleado")->get();
        
        $variablesParafiscales = DB::table("variable")
        ->where("idVariable",">=","49")
        ->where("idVariable","<=","56")
        ->get();
        $varParafiscales = array();
        foreach($variablesParafiscales as $variablesParafiscal){
            $varParafiscales[$variablesParafiscal->idVariable] = $variablesParafiscal->valor;
        }
        $variables = DB::table("variable")->where("idVariable","=","1")->first();
        $valorSalarioMinimo = $variables->valor;

        $contador=1;
        $totalNomina = 0;
        $numeroEmpleados = 0;
        
        
        foreach($empleadosGen as $empleado){
          
            $empleado->fkNomina = ($empleado->fkNominaPeriodo ?? $empleado->fkNomina);
            $empleado->fechaIngreso = ($empleado->fechaInicioPeriodo ?? $empleado->fechaInicio);
            
            $empleado->fkCargo = ($empleado->fkCargoPeriodo ?? $empleado->fkCargo);
            $empleado->fkTipoCotizante = ($empleado->fkTipoCotizantePeriodo ?? $empleado->fkTipoCotizante);
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


            $arrayFila = array();
            $arrayNuevoRegistro = array();
            $numeroEmpleados ++;
            $fechaFin = $fechaFinMesActual;
            $arrayFila[0] = $this->plantillaTxt("02",2,"","right");
            $arrayFila[1] = $this->plantillaTxt($contador,5,"0","right");
            $arrayFila[2] = $this->plantillaTxt($empleado->siglaPila,2," ","right");
            $arrayFila[3] = $this->plantillaTxt($empleado->numeroIdentificacion,16," ","left");
            $arrayFila[4] = $this->plantillaTxt($empleado->fkTipoCotizante,2,"0","right");//Tipo cotizante
            $arrayFila[5] = $this->plantillaTxt($empleado->esPensionado,2,"0","right");//Subtipo de cotizante

            //Extranjero no obligado a cotizar a pensiones
            if($empleado->fkTipoIdentificacion == "4"){
                $arrayFila[6] = $this->plantillaTxt("X",1,"","left");
            }
            else{
                $arrayFila[6] = $this->plantillaTxt(" ",1,"","left");
            }

            //Colombiano en el exterior
            if(substr($empleado->fkUbicacionResidencia,0,2) != "57" && ($empleado->fkTipoIdentificacion == "1" || $empleado->fkTipoIdentificacion == "6")){
                $arrayFila[7] = $this->plantillaTxt("X",1,"","left");
            }
            else{
                $arrayFila[7] = $this->plantillaTxt(" ",1," ","left");
            }

            //Código del departamento de la ubicación laboral
            if(substr($empleado->fkUbicacionLabora,0,2) == "57"){
                $arrayFila[8] = $this->plantillaTxt(substr("0".substr($empleado->fkUbicacionLabora,2,2),-2),2,"","right");
            }
            else{
                $arrayFila[8] = $this->plantillaTxt("",2," ","left");
            }


            //Código del municipio de ubicación laboral
            if(substr($empleado->fkUbicacionLabora,0,2) == "57"){
                $arrayFila[9] = $this->plantillaTxt(substr("00".substr($empleado->fkUbicacionLabora,4),-3),3,"","right");
            }
            else{
                $arrayFila[9] = $this->plantillaTxt("",3," ","left");
            }


            $arrayFila[10] = $this->plantillaTxt($empleado->primerApellido,20," ","left");
            $arrayFila[11] = $this->plantillaTxt($empleado->segundoApellido,30," ","left");
            $arrayFila[12] = $this->plantillaTxt($empleado->primerNombre,20," ","left");
            $arrayFila[13] = $this->plantillaTxt($empleado->segundoNombre,30," ","left");
        
            

            $periodoTrabajado = 30;
            //Salario
            $conceptoFijoSalario = DB::table("conceptofijo", "cf")
            ->whereIn("cf.fkConcepto",["1","2","53","54","154"])
            ->where("cf.fkEmpleado", "=", $empleado->idempleado)
            ->where("cf.fkPeriodoActivo", "=", $empleado->idPeriodo)
            ->first();
        


            $ultimoBoucher = DB::table("boucherpago", "bp")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')");
            if($empleado->periodoNominaDias == 15){
                $ultimoBoucher = $ultimoBoucher->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","12"]);
            }
            else{
                $ultimoBoucher = $ultimoBoucher->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"]);
            }
            $ultimoBoucher = $ultimoBoucher->whereRaw("(ibc_afp <> 0 or ibc_eps <> 0 or diasInjustificados > 0 or diasIncapacidad > 0)");
            $ultimoBoucher = $ultimoBoucher->orderBy("bp.idBoucherPago","desc")
            ->first();
            
            
            if(!isset($ultimoBoucher)){
                $ultimoBoucher = DB::table("boucherpago", "bp")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
                ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                ->whereIn("ln.fkTipoLiquidacion",["3"])
                ->orderBy("bp.idBoucherPago","desc")
                ->first();
                
            }
            else{
                
                if(strtotime($ultimoBoucher->fechaFin) == strtotime($fechaFinMesActual) && $empleado->periodoNominaDias == 30){
                    
                    
                    $ultimoBoucherRetiro = DB::table("boucherpago", "bp")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
                    ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                    ->whereIn("ln.fkTipoLiquidacion",["3"])
                    ->orderBy("bp.idBoucherPago","desc")
                    ->first();
                    if(isset($ultimoBoucherRetiro)){
                        if($ultimoBoucherRetiro->ibc_eps > 0){
                            $ultimoBoucher = $ultimoBoucherRetiro;
                        }
                        else{
                            $ultimoBoucher->periodoPago = ($ultimoBoucherRetiro->periodoPago ?? $ultimoBoucher->periodoPago);
                            $ultimoBoucher->ibc_afp = $ultimoBoucher->ibc_afp + ($ultimoBoucherRetiro->ibc_afp ?? 0);
                            $ultimoBoucher->ibc_eps = $ultimoBoucher->ibc_eps + ($ultimoBoucherRetiro->ibc_eps ?? 0);
                            $ultimoBoucher->ibc_arl = $ultimoBoucher->ibc_arl + ($ultimoBoucherRetiro->ibc_arl ?? 0);
                            $ultimoBoucher->ibc_ccf = $ultimoBoucher->ibc_ccf + ($ultimoBoucherRetiro->ibc_ccf ?? 0);
                            $ultimoBoucher->ibc_otros = $ultimoBoucher->ibc_otros + ($ultimoBoucherRetiro->ibc_otros ?? 0);
                        }
                    }
                    
                    // $ultimoBoucher->periodoPago = ($ultimoBoucherRetiro->periodoPago ?? $ultimoBoucher->periodoPago);
                    // $ultimoBoucher->ibc_afp = $ultimoBoucher->ibc_afp + ($ultimoBoucherRetiro->ibc_afp ?? 0);
                    // $ultimoBoucher->ibc_eps = $ultimoBoucher->ibc_eps + ($ultimoBoucherRetiro->ibc_eps ?? 0);
                    // $ultimoBoucher->ibc_arl = $ultimoBoucher->ibc_arl + ($ultimoBoucherRetiro->ibc_arl ?? 0);
                    // $ultimoBoucher->ibc_ccf = $ultimoBoucher->ibc_ccf + ($ultimoBoucherRetiro->ibc_ccf ?? 0);
                    // $ultimoBoucher->ibc_otros = $ultimoBoucher->ibc_otros + ($ultimoBoucherRetiro->ibc_otros ?? 0);
                    
                    /*if(isset($ultimoBoucherRetiro)){
                        $ultimoBoucher = $ultimoBoucherRetiro;
                    }
                    /*
                    $ultimoBoucher->ibc_afp = ($ultimoBoucherRetiro->ibc_afp ?? 0);
                    $ultimoBoucher->ibc_eps = ($ultimoBoucherRetiro->ibc_eps ?? 0);
                    $ultimoBoucher->ibc_arl = ($ultimoBoucherRetiro->ibc_arl ?? 0);
                    $ultimoBoucher->ibc_ccf = ($ultimoBoucherRetiro->ibc_ccf ?? 0);
                    $ultimoBoucher->ibc_otros = ($ultimoBoucherRetiro->ibc_otros ?? 0);*/
                }
                else if(date("d",strtotime($ultimoBoucher->fechaFin)) == "15"){
                    //si la lq es de retiro, verificar que si tiene lq normal
                    if($ultimoBoucher->fkTipoLiquidacion == '3'){

                        $ultimoBoucherNormal = DB::table("boucherpago", "bp")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
                        ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                        ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
                        ->whereRaw("(ibc_afp <> 0 or ibc_ccf <> 0 or diasInjustificados > 0)")
                        ->where("bp.idBoucherPago","<>",$ultimoBoucher->idBoucherPago)
                        ->orderBy("bp.idBoucherPago","desc")
                        ->first();
                        $ultimoBoucher->periodoPago = ($ultimoBoucherNormal->periodoPago ?? $ultimoBoucher->periodoPago);
                        $ultimoBoucher->ibc_afp = $ultimoBoucher->ibc_afp + ($ultimoBoucherNormal->ibc_afp ?? 0);
                        $ultimoBoucher->ibc_eps = $ultimoBoucher->ibc_eps + ($ultimoBoucherNormal->ibc_eps ?? 0);
                        $ultimoBoucher->ibc_arl = $ultimoBoucher->ibc_arl + ($ultimoBoucherNormal->ibc_arl ?? 0);
                        $ultimoBoucher->ibc_ccf = $ultimoBoucher->ibc_ccf + ($ultimoBoucherNormal->ibc_ccf ?? 0);
                        $ultimoBoucher->ibc_otros = $ultimoBoucher->ibc_otros + ($ultimoBoucherNormal->ibc_otros ?? 0);
                    }
                    else{
                        //Verificar si tiene una lq de retiro en la fecha 
                        $ultimoBoucherRetiro = DB::table("boucherpago", "bp")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
                        ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                        ->whereIn("ln.fkTipoLiquidacion",["3"])
                        ->whereRaw("(ibc_afp <> 0 or ibc_ccf <> 0 or diasInjustificados > 0)")
                        ->where("bp.idBoucherPago","<>",$ultimoBoucher->idBoucherPago)
                        ->orderBy("bp.idBoucherPago","desc")
                        ->first();
                        $ultimoBoucher->periodoPago = ($ultimoBoucherRetiro->periodoPago ?? $ultimoBoucher->periodoPago);
                        $ultimoBoucher->ibc_afp = $ultimoBoucher->ibc_afp + ($ultimoBoucherRetiro->ibc_afp ?? 0);
                        $ultimoBoucher->ibc_eps = $ultimoBoucher->ibc_eps + ($ultimoBoucherRetiro->ibc_eps ?? 0);
                        $ultimoBoucher->ibc_arl = $ultimoBoucher->ibc_arl + ($ultimoBoucherRetiro->ibc_arl ?? 0);
                        $ultimoBoucher->ibc_ccf = $ultimoBoucher->ibc_ccf + ($ultimoBoucherRetiro->ibc_ccf ?? 0);
                        $ultimoBoucher->ibc_otros = $ultimoBoucher->ibc_otros + ($ultimoBoucherRetiro->ibc_otros ?? 0);
                    }
                    

                }
                else if($ultimoBoucher->ibc_eps <= 0 && strtotime($ultimoBoucher->fechaFin) == strtotime($fechaFinMesActual) && $empleado->periodoNominaDias == 15){
                    //Verificar si fue un retiro con fecha menor 14 pero lq de 16 a 30 
                   
                    $ultimoBoucherNormal = DB::table("boucherpago", "bp")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
                    ->where("bp.idBoucherPago","<>",$ultimoBoucher->idBoucherPago)
                    ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                    ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
                    ->orderBy("bp.idBoucherPago","desc")
                    ->first();
                    
                    
                    //$ultimoBoucher = $ultimoBoucherRetiro;
                    
                    $ultimoBoucher->periodoPago = ($ultimoBoucherNormal->periodoPago ?? $ultimoBoucher->periodoPago);
                    $ultimoBoucher->ibc_afp = $ultimoBoucher->ibc_afp + ($ultimoBoucherNormal->ibc_afp ?? 0);
                    $ultimoBoucher->ibc_eps = $ultimoBoucher->ibc_eps + ($ultimoBoucherNormal->ibc_eps ?? 0);
                    $ultimoBoucher->ibc_arl = $ultimoBoucher->ibc_arl + ($ultimoBoucherNormal->ibc_arl ?? 0);
                    $ultimoBoucher->ibc_ccf = $ultimoBoucher->ibc_ccf + ($ultimoBoucherNormal->ibc_ccf ?? 0);
                    $ultimoBoucher->ibc_otros = $ultimoBoucher->ibc_otros + ($ultimoBoucherNormal->ibc_otros ?? 0);


                }
                else if(date("d",strtotime($ultimoBoucher->fechaInicio)) == "16" && $empleado->periodoNominaDias == 15 && $ultimoBoucher->fkTipoLiquidacion != 3){
                    //La que se encontro de primeras es una liquidacion de vacaciones, y si tiene un retiro
                    
                    $ultimoBoucherNormal = DB::table("boucherpago", "bp")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
                    ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                    ->whereIn("ln.fkTipoLiquidacion",["3"])
                    ->where("bp.idBoucherPago","<>",$ultimoBoucher->idBoucherPago)
                    ->whereRaw("(ibc_afp <> 0 or ibc_eps <> 0 or ibc_ccf <> 0 or diasInjustificados > 0)")
                    ->orderBy("bp.idBoucherPago","desc")
                    ->first();
                    
                    //$ultimoBoucher = $ultimoBoucherRetiro;
                    
                    $ultimoBoucher->periodoPago = ($ultimoBoucherNormal->periodoPago ?? $ultimoBoucher->periodoPago);
                    $ultimoBoucher->ibc_afp = $ultimoBoucher->ibc_afp + ($ultimoBoucherNormal->ibc_afp ?? 0);
                    $ultimoBoucher->ibc_eps = $ultimoBoucher->ibc_eps + ($ultimoBoucherNormal->ibc_eps ?? 0);
                    $ultimoBoucher->ibc_arl = $ultimoBoucher->ibc_arl + ($ultimoBoucherNormal->ibc_arl ?? 0);
                    $ultimoBoucher->ibc_ccf = $ultimoBoucher->ibc_ccf + ($ultimoBoucherNormal->ibc_ccf ?? 0);
                    $ultimoBoucher->ibc_otros = $ultimoBoucher->ibc_otros + ($ultimoBoucherNormal->ibc_otros ?? 0);
                }
                
            }
            
            
            if(!isset($ultimoBoucher->idBoucherPago)){
                continue;
            }
            $arrayFila[101] = $ultimoBoucher->idBoucherPago;
            $arraySinNada = $arrayFila;
            
            $esRetiroNegativo = 0;
            
            
            if($ultimoBoucher->ibc_afp < 0 || ($ultimoBoucher->diasIncapacidad <> 30 && $ultimoBoucher->ibc_ccf == 0 && $empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23" && $empleado->fkTipoCotizante != "51")){
                $esRetiroNegativo = 1;
                $minimosRedondeo = DB::table("tabla_smmlv_redondeo")->where("dias","=","30")->first();
                if($conceptoFijoSalario->valor < $minimosRedondeo->ibc){
                    $conceptoFijoSalario->valor =  $minimosRedondeo->ibc;
                }


                $ultimoBoucher->ibc_afp = ($conceptoFijoSalario->valor / 30);
                $ultimoBoucher->ibc_eps = ($conceptoFijoSalario->valor / 30);
                $ultimoBoucher->ibc_arl = ($conceptoFijoSalario->valor / 30);

                $itemVacacionesRetiro = DB::table("item_boucher_pago","ibp")
                ->selectRaw("Sum(ibp.valor) as vacaciones")
                ->whereIn("ibp.fkConcepto",["30","29"])
                ->where("ibp.fkBoucherPago","=",$ultimoBoucher->idBoucherPago)
                ->first();
                $ultimoBoucher->ibc_ccf = ($conceptoFijoSalario->valor / 30);
                if($ultimoBoucher->ibc_otros > 0){
                    $ultimoBoucher->ibc_otros = ($conceptoFijoSalario->valor / 30);
                }
                
                if(isset($itemVacacionesRetiro->vacaciones)){
                    $ultimoBoucher->ibc_ccf = $ultimoBoucher->ibc_ccf + $itemVacacionesRetiro->vacaciones;
                    if($ultimoBoucher->ibc_otros > 0){
                        $ultimoBoucher->ibc_otros = $ultimoBoucher->ibc_otros + $itemVacacionesRetiro->vacaciones;
                    }
                }
                
                $periodoTrabajado = 1;
                $arrayFila[100] = $esRetiroNegativo;
            }
            //dd($esRetiroNegativo, $ultimoBoucher);

            if($empleado->fkTipoCotizante == "51"){
                $sumaPeriodo = DB::table("boucherpago", "bp")
                ->selectRaw("sum(bp.periodoPago) as suma")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
                ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                ->orderBy("bp.idBoucherPago","desc")
                ->first();
                $periodoTrabajado = $sumaPeriodo->suma;
            }

            $ibcAFP = $ultimoBoucher->ibc_afp;
            $ibcEPS = $ultimoBoucher->ibc_eps;
            $ibcARL = $ultimoBoucher->ibc_arl;
            $ibcCCF = $ultimoBoucher->ibc_ccf;
            $ibcOtros = $ultimoBoucher->ibc_otros;  
            
            
            
            $itemBoucherVacacionesParaCCF = DB::table("item_boucher_pago", "ibp")
            ->selectRaw("sum(ibp.valor) as suma")
            ->join("boucherpago as bp","bp.idBoucherPago", "=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->whereBetween("ln.fechaLiquida",[$fechaInicioMesActual, $fechaFinMesActual])
            ->whereIn("ibp.fkConcepto",[28,30])
            ->where("bp.fkPeriodoActivo", "=", $empleado->idPeriodo)
            ->first();     
            
            $ibcCCF_sinVac = $ibcCCF - ($itemBoucherVacacionesParaCCF->suma ?? 0);
            
            $consulta_dias_periodos = DB::table("boucherpago", "bp")
            ->selectRaw("sum(bp.periodoPago) as per")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')");
            $consulta_dias_periodos = $consulta_dias_periodos->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","12"]);
            $consulta_dias_periodos = $consulta_dias_periodos->whereRaw("(ibc_afp <> 0 or ibc_eps <> 0 or diasInjustificados > 0)");
            $consulta_dias_periodos = $consulta_dias_periodos->orderBy("bp.idBoucherPago","desc")
            ->first();

            

            
            if( $empresa->exento == "0" || 
                ($ibcCCF_sinVac > ($varParafiscales[56] *($valorSalarioMinimo/30)*$consulta_dias_periodos->per) && intval($consulta_dias_periodos->per) > 0)|| 
                $empleado->tipoRegimen == "Salario Integral"
                //(($ibcCCF_sinVac*100/70) > ($varParafiscales[56] *($valorSalarioMinimo/30)*$consulta_dias_periodos->per) && $empleado->tipoRegimen == "Salario Integral")
            ){
                $ibcOtros = $ibcCCF;  
                $ultimoBoucher->ibc_otros = $ibcOtros; 
            }
            else{
                $ibcOtros = 0;
                $ultimoBoucher->ibc_otros = 0;
            }
            //dd($ultimoBoucher->ibc_otros);

            //dd( $consulta_dias_periodos, $ibcOtros, $ibcCCF, $ibcCCF_sinVac);
            $arrayFila[41] = $this->plantillaTxt(round($ibcAFP),9,"0","right");

            //dd("ibc",$ibcAFP);
            //ING
           
            //Verificar bien el if y que la empresa sea la misma que trae el periodo
            if(strtotime($fechaInicioMesActual) <= strtotime($empleado->fechaInicioPeriodo)){
                
                $arrayFila[14] = $this->plantillaTxt("X",1,"","left");
                
                $arrayFila[79] = $this->plantillaTxt($empleado->fechaInicioPeriodo,10,"","left");
                if($empleado->fkTipoCotizante != "51" ){
                    $periodoTrabajado = $periodoTrabajado - intval(substr($empleado->fechaInicioPeriodo,8,2)) + 1;
                    if($empleado->fkTipoCotizante == "12" || $empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23"){
                        //Regla de 3 para aprendices sena
                        $ultimoBoucher->ibc_eps = ($ultimoBoucher->ibc_eps / 30) * $periodoTrabajado;
                        $ultimoBoucher->ibc_arl = ($ultimoBoucher->ibc_arl / 30) * $periodoTrabajado;
                        $ibcEPS = $ultimoBoucher->ibc_eps;
                        $ibcARL = $ultimoBoucher->ibc_arl;
                    }
                }
                
                
            }
            else{
                $arrayFila[14] = $this->plantillaTxt(" ",1,"","left");
                $arrayFila[79] = $this->plantillaTxt("",10," ","left");
            }
            //dd($periodoTrabajado, $empleado);
            
            
            //RET
            $novedadesRetiro = DB::table("novedad","n")
                ->select("r.fecha")
                ->join("retiro as r", "r.idRetiro", "=","n.fkRetiro")
                ->where("n.fkEmpleado","=", $empleado->idempleado)
                ->where("n.fkPeriodoActivo","=",$empleado->idPeriodo)
                ->whereIn("n.fkEstado",["8"]) // Pagada-> no que este eliminada
                ->whereNotNull("n.fkRetiro")
                ->whereBetween("n.fechaRegistro",[$fechaInicioMesActual, $fechaFinMesActual])
                ->get();
            
            if(sizeof($novedadesRetiro)>0){
                $arrayFila[15] = $this->plantillaTxt("X",1," ","left");
                $arrayFila[80] = $this->plantillaTxt($novedadesRetiro[0]->fecha,10,"","left");

                
                $diasRetiro = 30 - intval(substr($novedadesRetiro[0]->fecha,8,2));
                if((substr($novedadesRetiro[0]->fecha, 8, 2) == "28" && substr($novedadesRetiro[0]->fecha, 5, 2) == "02") || (substr($novedadesRetiro[0]->fecha, 8, 2) == "29" && substr($novedadesRetiro[0]->fecha, 5, 2) == "02")){
                    $diasRetiro = 0;
                }

                
                if(strtotime($novedadesRetiro[0]->fecha) > strtotime($empleado->fechaInicioPeriodo)){
                    if($diasRetiro>0){
                        if($esRetiroNegativo == 0){
                            if($empleado->fkTipoCotizante != "51" ){
                                $periodoTrabajado = $periodoTrabajado - $diasRetiro;
                            }
                        }
                        
                    } 
                    if($esRetiroNegativo == 1){
                        $arrayFila[80] = $this->plantillaTxt($fechaInicioMesActual,10,"","left");//
                    }          
                }
                else if(strtotime($novedadesRetiro[0]->fecha) == strtotime($empleado->fechaInicioPeriodo)){
                    $periodoTrabajado = 1;
                }
                else{
                    
                    if($diasRetiro>0){
                        if($esRetiroNegativo == 0){
                            if($empleado->fkTipoCotizante != "51" ){
                                $periodoTrabajado = $periodoTrabajado + intval(substr($novedadesRetiro[0]->fecha,8,2));
                            }
                        }
                        
                    } 
                    if($esRetiroNegativo == 1){
                        $arrayFila[80] = $this->plantillaTxt($fechaInicioMesActual,10,"","left");//
                    } 
                }


                if($empleado->fkTipoCotizante == "12" || $empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23"){
                    //Regla de 3 para aprendices sena
                    $ultimoBoucher->ibc_eps = ($ultimoBoucher->ibc_eps / 30) * $periodoTrabajado;
                    $ultimoBoucher->ibc_arl = ($ultimoBoucher->ibc_arl / 30) * $periodoTrabajado;
                    $ibcEPS = $ultimoBoucher->ibc_eps;
                    $ibcARL = $ultimoBoucher->ibc_arl;
                }
                
                    
            }
            else{
                $arrayFila[15] = $this->plantillaTxt("",1," ","left");
                $arrayFila[80] = $this->plantillaTxt("",10," ","left");
            }
            
            
           
            
            //TDE
            $fechaInicioParaMesAntes = date("Y-m-01", strtotime($fechaInicioMesActual."  -1 month"));
            $fechaFinParaMesAntes = date("Y-m-t", strtotime($fechaInicioParaMesAntes));
            
            $cambioAfiliacionEps = DB::table("cambioafiliacion","ca")
                ->where("ca.fkEmpleado", "=", $empleado->idempleado)
                ->where("ca.fkPeriodoActivo", "=", $empleado->idPeriodo)
                ->where("ca.fkEstado", "=", "5")
                ->where("ca.fkTipoAfiliacionNueva", "=", "3") //3-Salud
                ->whereBetween("ca.fechaCambio", [$fechaInicioParaMesAntes, $fechaFinParaMesAntes])
                ->get();
            if(sizeof($cambioAfiliacionEps)>0){
                $arrayFila[16] = $this->plantillaTxt("X",1," ","left");
            }
            else{
                $arrayFila[16] = $this->plantillaTxt(" ",1," ","left");
            }
            
            

            //TAE
            $cambioAfiliacionEps2 = DB::table("cambioafiliacion","ca")
                ->join("tercero as t", "t.idTercero", "=","ca.fkTerceroNuevo")
                ->where("ca.fkEmpleado", "=", $empleado->idempleado)
                ->where("ca.fkPeriodoActivo", "=", $empleado->idPeriodo)
                ->where("ca.fkEstado", "=", "5")
                ->where("ca.fkTipoAfiliacionNueva", "=", "3") //3-Salud
                ->whereBetween("ca.fechaCambio", [$fechaInicioMesActual, $fechaFinMesActual])
                ->get();
            if(sizeof($cambioAfiliacionEps2)>0){
                $arrayPlace = $arraySinNada;
                $arrayFila[17] = $this->plantillaTxt("X",1," ","left");
                $arrayFila[33] = $this->plantillaTxt($cambioAfiliacionEps2[0]->codigoTercero,6," ","left");
                //array_push($arrayNuevoRegistro, $arrayPlace);
            }
            else{
                $arrayFila[17] = $this->plantillaTxt(" ",1," ","left");
                $arrayFila[33] = $this->plantillaTxt(" ",6," ","left");
            }
            
            //TDP
            $cambioAfiliacionPension = DB::table("cambioafiliacion","ca")
                ->join("tercero as t", "t.idTercero", "=", "ca.fkTerceroNuevo")
                ->where("ca.fkEmpleado", "=", $empleado->idempleado)
                ->where("ca.fkPeriodoActivo", "=", $empleado->idPeriodo)
                ->where("ca.fkEstado", "=", "5")
                ->where("ca.fkTipoAfiliacionNueva", "=", "4") //4-Pension
                ->whereBetween("ca.fechaCambio", [$fechaInicioParaMesAntes, $fechaFinParaMesAntes])
                ->get();
            if(sizeof($cambioAfiliacionPension)>0){
                //$arrayPlace = $arraySinNada;
                $arrayFila[18] = $this->plantillaTxt("X",1," ","left");
                
                //array_push($arrayNuevoRegistro, $arrayPlace);
            }
            else{
                $arrayFila[18] = $this->plantillaTxt(" ",1," ","left");
            }
            

            //TAP
            $cambioAfiliacionPension2 = DB::table("cambioafiliacion","ca")
                ->join("tercero as t", "t.idTercero", "=", "ca.fkTerceroNuevo")
                ->where("ca.fkEmpleado", "=", $empleado->idempleado)
                ->where("ca.fkTipoAfiliacionNueva", "=", "4") //4-Pension
                ->where("ca.fkPeriodoActivo", "=", $empleado->idPeriodo)
                ->where("ca.fkEstado", "=", "5")
                ->whereBetween("ca.fechaCambio", [$fechaInicioMesActual, $fechaFinMesActual])
                ->get();

            if(sizeof($cambioAfiliacionPension2)>0){
                //$arrayPlace = $arraySinNada;
                $arrayFila[19] = $this->plantillaTxt("X",1," ","left");


                $arrayFila[31] = $this->plantillaTxt($cambioAfiliacionPension2[0]->codigoTercero,6," ","left");
                //array_push($arrayNuevoRegistro, $arrayPlace);
            }
            else{
                $arrayFila[19] = $this->plantillaTxt(" ",1," ","left");
                $arrayFila[31] = $this->plantillaTxt(" ",6," ","left");
            }          
            //VSP
            $cambioSalario = DB::table("cambiosalario","cs")
            ->where("cs.fkEmpleado", "=", $empleado->idempleado)
            ->where("cs.fkPeriodoActivo", "=", $empleado->idPeriodo)                
            ->whereBetween("cs.fechaCambio", [$fechaInicioMesActual, $fechaFinMesActual])
            ->where("cs.fkEstado","=","5")
            ->get();

            if(sizeof($cambioSalario)>0){
                $arrayFila[20] = $this->plantillaTxt("X",1," ","left");
                $arrayFila[81] = $this->plantillaTxt($cambioSalario[0]->fechaCambio,10,"","left");
            }
            else{
                $arrayFila[20] = $this->plantillaTxt(" ",1," ","left");
                $arrayFila[81] = $this->plantillaTxt("",10," ","left");
            }
            
            //Correcciones
            $arrayFila[21] = $this->plantillaTxt(" ",1," ","left");

            //VST
            $itemsBoucherPago = DB::table("item_boucher_pago", "ibp")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
            ->where("gcc.fkGrupoConcepto","=","10") //10 - CONCEPTOS QUE GENERAN VST	
            ->get();
            
            $boucherPagoVST = DB::table("boucherpago", "bp")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
            ->where("bp.vstNoSalarial","=","1")
            ->get();
                        
            
            
            if(sizeof($itemsBoucherPago) > 0 || $ultimoBoucher->vstNoSalarial == 1 ){
                $arrayFila[22] = $this->plantillaTxt("X",1," ","left");
            }
            else{
                $arrayFila[22] = $this->plantillaTxt(" ",1," ","left");
            }
            
            //SLN
            $novedadesSancion = DB::table("novedad","n")
            ->join("ausencia AS a","a.idAusencia", "=", "n.fkAusencia")
            ->where("a.cantidadDias",">=", "1")
            ->where("n.fkEmpleado","=", $empleado->idempleado)
            ->where("n.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->whereIn("n.fkEstado",["7", "8"]) // Pagada o sin pagar-> no que este eliminada
            ->whereNotNull("n.fkAusencia")
            ->whereBetween("n.fechaRegistro",[$fechaInicioMesActual, $fechaFinMesActual])
            ->get();
            $periodoTrabajadoSinNov = $periodoTrabajado;
            
            foreach($novedadesSancion as $novedadSancion){
                $arrayPlace = $arraySinNada;
                $arrFechasN = array();
                if(isset($novedadSancion->fechasAdicionales) && !empty($novedadSancion->fechasAdicionales)){
                    $arrFechasN = explode(",",$novedadSancion->fechasAdicionales);
                }
                

                $novedadSancion->cantidadDias = intval($novedadSancion->cantidadDias) - sizeof($arrFechasN);
                $fechaInicioSLN = date("Y-m-d",strtotime($novedadSancion->fechaInicio));
                $fechaFinSLN =  date("Y-m-d",strtotime($novedadSancion->fechaFin));

                $arrFechasNF = array([
                    "fechaInicial" => $fechaInicioSLN,
                    "fechaFinal" => $fechaFinSLN,
                    "cantidadDias" => $novedadSancion->cantidadDias
                ]);

                foreach($arrFechasN as $arrFechaN){
                    array_push($arrFechasNF, [
                        "fechaInicial" => $arrFechaN,
                        "fechaFinal" => $arrFechaN,
                        "cantidadDias" => 1
                    ]);
                }
                
                foreach($arrFechasNF as $arrFechaNF){
                    $novedadSancion->cantidadDias =  $arrFechaNF["cantidadDias"];
                    $novedadSancion->fechaInicio =  $arrFechaNF["fechaInicial"];
                    $novedadSancion->fechaFin = $arrFechaNF["fechaFinal"];
                
                    $novedadSancion->cantidadDias = intval( $novedadSancion->cantidadDias);
                    if($empleado->fkTipoCotizante != "51" ){
                        $periodoTrabajado = $periodoTrabajado - $novedadSancion->cantidadDias;
                    }

                    
                    if($periodoTrabajado <= 0){
                        $arrayFila[23] = $this->plantillaTxt("X",1," ","left");
                        $arrayPlace = $arrayFila;
                        
                        if($empleado->esPensionado == 0  && ($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23")){
                            $arrayPlace[35] = $this->plantillaTxt($novedadSancion->cantidadDias,2,"0","right");
                        }
                        else{
                            $arrayPlace[35] = $this->plantillaTxt("",2,"0","right");
                        }
                        $arrayPlace[36] = $this->plantillaTxt($novedadSancion->cantidadDias,2,"0","right");
                        $arrayPlace[37] = $this->plantillaTxt($novedadSancion->cantidadDias,2,"0","right"); 
                        $arrayPlace[38] = $this->plantillaTxt($novedadSancion->cantidadDias,2,"0","right"); 
                        
        
                        $fechaInicioSLN = date("Y-m-d",strtotime($novedadSancion->fechaInicio));
                        $fechaFinSLN =  date("Y-m-d",strtotime($novedadSancion->fechaFin));
        
        
                        $arrayPlace[82] = $this->plantillaTxt($fechaInicioSLN,10," ","left");
                        $arrayPlace[83] = $this->plantillaTxt($fechaFinSLN,10," ","left");
        
                        //Tarifa en 0 para ausentismos
        
                        //Fondo de Solidaridad 
                        $arrayPlace[50] = $this->plantillaTxt("0",9,"0","right");
                        $arrayPlace[51] = $this->plantillaTxt("0",9,"0","right");
        
                        //ARL
                        $arrayPlace[60] =  $this->plantillaTxt("0.0",9,"0","left");
                        $arrayPlace[62] = $this->plantillaTxt("0",9,"0","right");
        
                        //SALUD
                        $arrayPlace[53] =  $this->plantillaTxt("0.0",7,"0","left");
                        $arrayPlace[54] =  $this->plantillaTxt("0",9,"0","left");
                        
                        //CCF
                        // if($ibcCCF <= 0){
                            $arrayPlace[63] = $this->plantillaTxt("0.0",7,"0","left");
                            $arrayPlace[64] =  $this->plantillaTxt("0",9,"0","left");
                        // }
                        
                        
                        
                        $valorNovedad = (intval($conceptoFijoSalario->valor)/30)*$novedadSancion->cantidadDias;
        
                        
                        $arrayPlace[41] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        $arrayPlace[42] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        $arrayPlace[43] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        $arrayPlace[44] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        
                        if($ibcOtros > 0){
                            $arrayPlace[94] = $this->plantillaTxt(round($ibcCCF),9,"0","right");
                        }
                        else{
                            $arrayPlace[94] = $this->plantillaTxt("0",9,"0","right");
                        }

                        $arrayPlace[45] = $this->plantillaTxt("0.12",7,"0","left");
                        //$arrayPlace[46] =  $this->plantillaTxt("0",9,"0","left");
        
        
                        if($empleado->esPensionado != "0"){
                            $arrayPlace[41] = $this->plantillaTxt(0,9,"0","right");
                            $arrayPlace[45] = $this->plantillaTxt("0.0",7,"0","left");
                        }
        
        
                        //dd($arrayPlace);
        
                        array_push($arrayNuevoRegistro, $arrayPlace);   
                        
                    }
                    else{
                        $arrayPlace[23] = $this->plantillaTxt("X",1," ","left");
                        if($empleado->esPensionado == 0  && ($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23")){
                            $arrayPlace[35] = $this->plantillaTxt($novedadSancion->cantidadDias,2,"0","right");
                        }
                        else{
                            $arrayPlace[35] = $this->plantillaTxt("",2,"0","right");
                        }
                        $arrayPlace[36] = $this->plantillaTxt($novedadSancion->cantidadDias,2,"0","right");
                        $arrayPlace[37] = $this->plantillaTxt($novedadSancion->cantidadDias,2,"0","right"); 
                        $arrayPlace[38] = $this->plantillaTxt($novedadSancion->cantidadDias,2,"0","right"); 
                        
        
                        $fechaInicioSLN = date("Y-m-d",strtotime($novedadSancion->fechaInicio));
                        $fechaFinSLN =  date("Y-m-d",strtotime($novedadSancion->fechaFin));
        
        
                        $arrayPlace[82] = $this->plantillaTxt($fechaInicioSLN,10," ","left");
                        $arrayPlace[83] = $this->plantillaTxt($fechaFinSLN,10," ","left");
        
                        //Tarifa en 0 para ausentismos
        
                        //Fondo de Solidaridad 
                        $arrayPlace[50] = $this->plantillaTxt("0",9,"0","right");
                        $arrayPlace[51] = $this->plantillaTxt("0",9,"0","right");
        
                        //ARL
                        $arrayPlace[60] =  $this->plantillaTxt("0.0",9,"0","left");
                        $arrayPlace[62] = $this->plantillaTxt("0",9,"0","right");
        
                        //SALUD
                        $arrayPlace[53] =  $this->plantillaTxt("0.0",7,"0","left");
                        $arrayPlace[54] =  $this->plantillaTxt("0",9,"0","left");
                        
                        //CCF
                        $arrayPlace[63] = $this->plantillaTxt("0.0",7,"0","left");
                        $arrayPlace[64] =  $this->plantillaTxt("0",9,"0","left");
                        
                        
                        $valorNovedad = (intval($conceptoFijoSalario->valor)/30)*$novedadSancion->cantidadDias;
        
                        $arrayPlace[41] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        $arrayPlace[42] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        $arrayPlace[43] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        $arrayPlace[44] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        
                        if($ibcOtros > 0){
                            $arrayPlace[94] = $this->plantillaTxt(0,9,"0","right");
                        }
                        else{
                            $arrayPlace[94] = $this->plantillaTxt("0",9,"0","right");
                        }

                        $arrayPlace[45] = $this->plantillaTxt("0.12",7,"0","left");
                        //$arrayPlace[46] =  $this->plantillaTxt("0",9,"0","left");
        
        
                        if($empleado->esPensionado != "0"){
                            $arrayPlace[41] = $this->plantillaTxt(0,9,"0","right");
                            $arrayPlace[45] = $this->plantillaTxt("0.0",7,"0","left");
                        }
        
        
                    
        
                        array_push($arrayNuevoRegistro, $arrayPlace);   
                    }
                }
                

                
            }
            $arrayFila[23] = $this->plantillaTxt(" ",1," ","left");
            $arrayFila[82] = $this->plantillaTxt("",10," ","left");
            $arrayFila[83] = $this->plantillaTxt("",10," ","left");
            
            
            //IGE
            $sqlWhere = "( 
                ('".$fechaInicioMesActual."' BETWEEN i.fechaInicial AND i.fechaFinal) OR
                ('".$fechaFinMesActual."' BETWEEN i.fechaInicial AND i.fechaFinal) OR
                (i.fechaInicial BETWEEN '".$fechaInicioMesActual."' AND '".$fechaFinMesActual."') OR
                (i.fechaFinal BETWEEN '".$fechaInicioMesActual."' AND '".$fechaFinMesActual."')
            )";

            $novedadesIncapacidadNoLab = DB::table("novedad","n")
            ->join("incapacidad as i","i.idIncapacidad","=", "n.fkIncapacidad")
            ->whereIn("i.fkTipoAfilicacion",["3","4"]) //3- Salud //4 Pension
            ->whereNotIn("i.tipoIncapacidad",["Maternidad", "Paternidad"])
            ->where("n.fkEmpleado","=", $empleado->idempleado)
            ->where("n.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->whereIn("n.fkEstado",["8","16"]) // Pagada -> no que este eliminada
            ->whereNotNull("n.fkIncapacidad")
            ->whereRaw($sqlWhere)
            ->get();

            

            
            foreach($novedadesIncapacidadNoLab as $novedadIncapacidadNoLab){
                $arrayPlace = $arraySinNada;
                $diasCompensar = 0;
                $diasPagoVac = 0;
                $fechaFin = $fechaFinMesActual;

                if(strtotime($novedadIncapacidadNoLab->fechaInicial)>=strtotime($fechaInicioMesActual)
                    &&  strtotime($novedadIncapacidadNoLab->fechaInicial)<=strtotime($fechaFin) 
                    &&  strtotime($novedadIncapacidadNoLab->fechaFinal)>=strtotime($fechaFin))
                {
                    $diaI = $novedadIncapacidadNoLab->fechaInicial;
                    $diaF = $fechaFin;
                    if(substr($diaI, 8, 2) == "31" || (substr($diaI, 8, 2) == "28" && substr($diaI, 5, 2) == "02") || (substr($diaI, 8, 2) == "29" && substr($diaI, 5, 2) == "02") ){
                        $diaI = substr($diaI,0,8)."30";
                    }
                    if(substr($diaF, 8, 2) == "31" || (substr($diaF, 8, 2) == "28" && substr($diaF, 5, 2) == "02") || (substr($diaF, 8, 2) == "29" && substr($diaF, 5, 2) == "02")){
                        $diaF = substr($diaF,0,8)."30";
                    }
                    $diasCompensar = $this->days_360($diaI, $diaF);
                    $diasCompensar ++;
                    $diasPagoVac = $diasCompensar;
                }
                else if(strtotime($novedadIncapacidadNoLab->fechaFinal)>=strtotime($fechaInicioMesActual)  
                &&  strtotime($novedadIncapacidadNoLab->fechaFinal)<=strtotime($fechaFin) 
                &&  strtotime($novedadIncapacidadNoLab->fechaInicial)<=strtotime($fechaInicioMesActual))
                {

                    $diaI = $fechaInicioMesActual;
                    $diaF = $novedadIncapacidadNoLab->fechaFinal;
                    if(substr($diaI, 8, 2) == "31" || (substr($diaI, 8, 2) == "28" && substr($diaI, 5, 2) == "02") || (substr($diaI, 8, 2) == "29" && substr($diaI, 5, 2) == "02") ){
                        $diaI = substr($diaI,0,8)."30";
                    }
                    if(substr($diaF, 8, 2) == "31" || (substr($diaF, 8, 2) == "28" && substr($diaF, 5, 2) == "02") || (substr($diaF, 8, 2) == "29" && substr($diaF, 5, 2) == "02")){
                        $diaF = substr($diaF,0,8)."30";
                    }
                    $diasCompensar = $this->days_360($diaI, $diaF);
                    $diasCompensar ++;
                    $diasPagoVac = $diasCompensar;
                }
                else if(strtotime($novedadIncapacidadNoLab->fechaInicial)<=strtotime($fechaInicioMesActual)  
                &&  strtotime($novedadIncapacidadNoLab->fechaFinal)>=strtotime($fechaFin)) 
                {
                    $diaI = $fechaInicioMesActual;
                    $diaF = $fechaFin;
                    if(substr($diaI, 8, 2) == "31" || (substr($diaI, 8, 2) == "28" && substr($diaI, 5, 2) == "02") || (substr($diaI, 8, 2) == "29" && substr($diaI, 5, 2) == "02") ){
                        $diaI = substr($diaI,0,8)."30";
                    }
                    if(substr($diaF, 8, 2) == "31" || (substr($diaF, 8, 2) == "28" && substr($diaF, 5, 2) == "02") || (substr($diaF, 8, 2) == "29" && substr($diaF, 5, 2) == "02")){
                        $diaF = substr($diaF,0,8)."30";
                    }
                    $diasCompensar = $this->days_360($diaI, $diaF);
                    $diasCompensar ++;
                    $diasPagoVac = $diasCompensar;
                }
                else if(strtotime($fechaInicioMesActual)<=strtotime($novedadIncapacidadNoLab->fechaInicial)  
                &&  strtotime($fechaFin)>=strtotime($novedadIncapacidadNoLab->fechaFinal)) 
                {

                    $diaI = $novedadIncapacidadNoLab->fechaInicial;
                    $diaF = $novedadIncapacidadNoLab->fechaFinal;
                    if(substr($diaI, 8, 2) == "31" || (substr($diaI, 8, 2) == "28" && substr($diaI, 5, 2) == "02") || (substr($diaI, 8, 2) == "29" && substr($diaI, 5, 2) == "02") ){
                        $diaI = substr($diaI,0,8)."30";
                    }
                    if(substr($diaF, 8, 2) == "31" || (substr($diaF, 8, 2) == "28" && substr($diaF, 5, 2) == "02") || (substr($diaF, 8, 2) == "29" && substr($diaF, 5, 2) == "02")){
                        $diaF = substr($diaF,0,8)."30";
                    }
                    $diasCompensar = $this->days_360($diaI, $diaF);
                    $diasCompensar ++;
                    $diasPagoVac = $diasCompensar;                    
                } 


                $diasTotales = $novedadIncapacidadNoLab->numDias;

                $novedadIncapacidadNoLab->numDias = intval($diasPagoVac);
                $arrayPlace[24] = $this->plantillaTxt("X",1," ","left");
                if($empleado->fkTipoCotizante != "51" ){
                    $periodoTrabajado = $periodoTrabajado - $novedadIncapacidadNoLab->numDias;
                }
                if($periodoTrabajado <= 0){
                    $arrayPlace = $arrayFila;
                    $arrayPlace[24] = $this->plantillaTxt("X",1," ","left");
                    if($empleado->esPensionado == 0  && ($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23")){
                        $arrayPlace[35] = $this->plantillaTxt($novedadIncapacidadNoLab->numDias,2,"0","right");
                    }
                    else{
                        $arrayPlace[35] = $this->plantillaTxt("",2,"0","right");
                    }
                    $arrayPlace[36] = $this->plantillaTxt($novedadIncapacidadNoLab->numDias,2,"0","right");
                    $arrayPlace[37] = $this->plantillaTxt($novedadIncapacidadNoLab->numDias,2,"0","right"); 
                    $arrayPlace[38] = $this->plantillaTxt($novedadIncapacidadNoLab->numDias,2,"0","right"); 
                    
    
                    $itemBoucherNovedad = DB::table("item_boucher_pago_novedad", "ibpn")
                    ->join("item_boucher_pago as ibp","ibp.idItemBoucherPago", "=","ibpn.fkItemBoucher")
                    ->join("boucherpago as bp","bp.idBoucherPago", "=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->selectRaw("sum(ibpn.valor) as valor, sum(ibpn.valor_ss) as valor_ss")
                    ->where("ibpn.fkNovedad", "=",$novedadIncapacidadNoLab->idNovedad)
                    ->whereBetween("ln.fechaLiquida",[$fechaInicioMesActual, $fechaFin])
                    ->first();     
    
                    if(isset($itemBoucherNovedad->valor_ss) && $itemBoucherNovedad->valor_ss != 0){
                        $valorNovedad = ($itemBoucherNovedad->valor_ss > 0 ? $itemBoucherNovedad->valor_ss : $itemBoucherNovedad->valor_ss*-1);
                    }
                    else{
                        $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                    }
                    
    
                    $restaIbc = 0;
                    if($novedadIncapacidadNoLab->pagoTotal == 1){
                        if(isset($itemBoucherNovedad) && $itemBoucherNovedad->valor > 0){
                            if(isset($itemBoucherNovedad->valor_ss) && $itemBoucherNovedad->valor_ss != 0){
                                $valorNovedad = ($itemBoucherNovedad->valor_ss > 0 ? $itemBoucherNovedad->valor_ss : $itemBoucherNovedad->valor_ss*-1);
                                $restaIbc = $valorNovedad;
                                $valorNovedad = $diasPagoVac*$valorNovedad/$diasTotales;
                                $valorNovedadGen = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                                $valorNovedadGen = $diasPagoVac*$valorNovedadGen/$diasTotales;
                            }
                            else{
                                $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                                $restaIbc = $valorNovedad;
                                $valorNovedad = $diasPagoVac*$valorNovedad/$diasTotales;    
                            }
                            
                            
                        }
                        else{
    
                            
                            $itemBoucherNovedad = DB::table("item_boucher_pago_novedad", "ibpn")
                            ->where("ibpn.fkNovedad", "=",$novedadIncapacidadNoLab->idNovedad)
                            ->first();
                            $restaIbc = 0;
                            $valorNovedad = 0;
                            if(isset($itemBoucherNovedad)){
                                if(isset($itemBoucherNovedad->valor_ss) && $itemBoucherNovedad->valor_ss != 0){
                                    $valorNovedad = ($itemBoucherNovedad->valor_ss > 0 ? $itemBoucherNovedad->valor_ss : $itemBoucherNovedad->valor_ss*-1);
                                    $valorNovedad = $diasPagoVac*$valorNovedad/$diasTotales;
                                    $valorNovedadGen = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                                }
                                else{
                                    $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                                    $valorNovedad = $diasPagoVac*$valorNovedad/$diasTotales;
                                }
                            }
                           
                            
                            
                        
                        }
                    
                        
                    }
                    else{
                        
                        if(isset($itemBoucherNovedad->valor_ss) && $itemBoucherNovedad->valor_ss != 0){
                            $valorNovedad = ($itemBoucherNovedad->valor_ss > 0 ? $itemBoucherNovedad->valor_ss : $itemBoucherNovedad->valor_ss*-1);    
                            $valorNovedadGen = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);    
                            $restaIbc = $valorNovedad;
                        }
                        else{
                            $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);    
                            $restaIbc = $valorNovedad;
                        }
                        
                    }
                    
    
                    if($empleado->esPensionado != "0" || $empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23"){
                        $arrayPlace[41] = $this->plantillaTxt(0,9,"0","right");
                        $arrayPlace[45] = $this->plantillaTxt("0.0",7,"0","left");
                    }
                    else{
                        $arrayPlace[41] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        $ibcAFP = $ibcAFP - $restaIbc;
                    }
                    
                    if($empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23"){
                        $arrayPlace[42] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        $arrayPlace[43] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        $arrayPlace[44] = $this->plantillaTxt(0,9,"0","right");
                        $arrayPlace[94] = $this->plantillaTxt(0,9,"0","right");
                    }
                    else{
                        $arrayPlace[42] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        $arrayPlace[43] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        
                        if(isset($valorNovedadGen) && $valorNovedadGen == 0){
                            $arrayPlace[44] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        }
                        else{
                            $arrayPlace[44] = $this->plantillaTxt(round($ibcCCF),9,"0","right");
                        }
                        //$arrayPlace[44] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        if($ibcOtros > 0){
                            $arrayPlace[94] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        }
                        else{
                            $arrayPlace[94] = $this->plantillaTxt("0",9,"0","right");
                        }
                        
                    }
                   
    
    
                    
                    $arrayFila[62] = $this->plantillaTxt(0,9,"0","right");
    
                    /*if($empleado->numeroIdentificacion == "79765038"){
                        dd($ultimoBoucher, $ibcEPS, $periodoTrabajado, $restaIbc);
                    }*/
                    
                    $ibcEPS = $ibcEPS - $restaIbc;
                    $ibcARL = $ibcARL - $restaIbc;
                    $ibcCCF = $ibcCCF - $restaIbc;
                    $ibcOtros = $ibcOtros - $restaIbc;
    
    
                    if(strtotime($novedadIncapacidadNoLab->fechaFinal) > strtotime($fechaFin)){
                        $novedadIncapacidadNoLab->fechaFinal=$fechaFin;
                    }
                    if(strtotime($novedadIncapacidadNoLab->fechaInicial) < strtotime($fechaInicioMesActual)){
                        $novedadIncapacidadNoLab->fechaInicial=$fechaInicioMesActual;
                    }
                    if(substr($novedadIncapacidadNoLab->fechaFinal, 8, 2) == "31"){
                        
                        //$arrayPlace[22] = $this->plantillaTxt("X",1," ","left");
                        //$arrayFila[22] = $this->plantillaTxt("X",1," ","left");
                        $novedadIncapacidadNoLab->fechaFinal = substr($novedadIncapacidadNoLab->fechaFinal, 0, 8)."30";
                    }
    
                    $fechaInicioIGE = date("Y-m-d",strtotime($novedadIncapacidadNoLab->fechaInicial));
                    $fechaFinIGE =  date("Y-m-d",strtotime($novedadIncapacidadNoLab->fechaFinal));
                    $arrayPlace[84] = $this->plantillaTxt($fechaInicioIGE,10," ","left");
                    $arrayPlace[85] = $this->plantillaTxt($fechaFinIGE,10," ","left");
    
                    //Tarifa en 0 para ausentismos
                    $arrayPlace[60] =  $this->plantillaTxt("0.0",9,"0","left");
                    $arrayPlace[62] = $this->plantillaTxt("0",9,"0","right");

                    array_push($arrayNuevoRegistro, $arrayPlace);   
               
                }
                else{
                    if($empleado->esPensionado == 0  && ($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23")){
                        $arrayPlace[35] = $this->plantillaTxt($novedadIncapacidadNoLab->numDias,2,"0","right");
                    }
                    else{
                        $arrayPlace[35] = $this->plantillaTxt("",2,"0","right");
                    }
                    $arrayPlace[36] = $this->plantillaTxt($novedadIncapacidadNoLab->numDias,2,"0","right");
                    $arrayPlace[37] = $this->plantillaTxt($novedadIncapacidadNoLab->numDias,2,"0","right"); 
                    $arrayPlace[38] = $this->plantillaTxt($novedadIncapacidadNoLab->numDias,2,"0","right"); 
                    
    
                    $itemBoucherNovedad = DB::table("item_boucher_pago_novedad", "ibpn")
                    ->join("item_boucher_pago as ibp","ibp.idItemBoucherPago", "=","ibpn.fkItemBoucher")
                    ->join("boucherpago as bp","bp.idBoucherPago", "=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->selectRaw("sum(ibpn.valor) as valor, sum(ibpn.valor_ss) as valor_ss")
                    ->where("ibpn.fkNovedad", "=",$novedadIncapacidadNoLab->idNovedad)
                    ->whereBetween("ln.fechaLiquida",[$fechaInicioMesActual, $fechaFin])
                    ->first();     
    
                    if(isset($itemBoucherNovedad->valor_ss) && $itemBoucherNovedad->valor_ss != 0){
                        $valorNovedad = ($itemBoucherNovedad->valor_ss > 0 ? $itemBoucherNovedad->valor_ss : $itemBoucherNovedad->valor_ss*-1);
                    }
                    else{
                        $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                    }
                    
    
                    $restaIbc = 0;
                    if($novedadIncapacidadNoLab->pagoTotal == 1){
                        if(isset($itemBoucherNovedad) && $itemBoucherNovedad->valor > 0){
                            if(isset($itemBoucherNovedad->valor_ss) && $itemBoucherNovedad->valor_ss != 0){
                                $valorNovedad = ($itemBoucherNovedad->valor_ss > 0 ? $itemBoucherNovedad->valor_ss : $itemBoucherNovedad->valor_ss*-1);
                                $restaIbc = $valorNovedad;
                                $valorNovedad = $diasPagoVac*$valorNovedad/$diasTotales;
                            }
                            else{
                                $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                                $restaIbc = $valorNovedad;
                                $valorNovedad = $diasPagoVac*$valorNovedad/$diasTotales;    
                            }
                            
                            
                        }
                        else{
    
                            
                            $itemBoucherNovedad = DB::table("item_boucher_pago_novedad", "ibpn")
                            ->where("ibpn.fkNovedad", "=",$novedadIncapacidadNoLab->idNovedad)
                            ->first();
                            $restaIbc = 0;
                            $valorNovedad = 0;
                            if(isset($itemBoucherNovedad)){
                                if(isset($itemBoucherNovedad->valor_ss) && $itemBoucherNovedad->valor_ss != 0){
                                    $valorNovedad = ($itemBoucherNovedad->valor_ss > 0 ? $itemBoucherNovedad->valor_ss : $itemBoucherNovedad->valor_ss*-1);
                                    $valorNovedad = $diasPagoVac*$valorNovedad/$diasTotales;
                                }
                                else{
                                    $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                                    $valorNovedad = $diasPagoVac*$valorNovedad/$diasTotales;
                                }
                            }
                           
                            
                            
                        
                        }
                    
                        
                    }
                    else{
                        if(isset($itemBoucherNovedad->valor_ss) && $itemBoucherNovedad->valor_ss != 0){
                            $valorNovedad = ($itemBoucherNovedad->valor_ss > 0 ? $itemBoucherNovedad->valor_ss : $itemBoucherNovedad->valor_ss*-1);    
                            $restaIbc = $valorNovedad;
                        }
                        else{
                            $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);    
                            $restaIbc = $valorNovedad;
                        }
                        
                    }
                    
    
                    if($empleado->esPensionado != "0" || $empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23"){
                        $arrayPlace[41] = $this->plantillaTxt(0,9,"0","right");
                        $arrayPlace[45] = $this->plantillaTxt("0.0",7,"0","left");
                    }
                    else{
                        $arrayPlace[41] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        $ibcAFP = $ibcAFP - $restaIbc;
                    }
                    
                    if($empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23"){
                        $arrayPlace[42] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        $arrayPlace[43] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        $arrayPlace[44] = $this->plantillaTxt(0,9,"0","right");
                        $arrayPlace[94] = $this->plantillaTxt(0,9,"0","right");
                    }
                    else{
                        $arrayPlace[42] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        $arrayPlace[43] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        $arrayPlace[44] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        if($ibcOtros > 0){
                            $arrayPlace[94] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        }
                        else{
                            $arrayPlace[94] = $this->plantillaTxt("0",9,"0","right");
                        }
                    }
                   
    
    
                    
                    $arrayFila[62] = $this->plantillaTxt(0,9,"0","right");
    
                    /*if($empleado->numeroIdentificacion == "79765038"){
                        dd($ultimoBoucher, $ibcEPS, $periodoTrabajado, $restaIbc);
                    }*/
                    
                    $ibcEPS = $ibcEPS - $restaIbc;
                    $ibcARL = $ibcARL - $restaIbc;
                    $ibcCCF = $ibcCCF - $restaIbc;
                    $ibcOtros = $ibcOtros - $restaIbc;
    
    
                    if(strtotime($novedadIncapacidadNoLab->fechaFinal) > strtotime($fechaFin)){
                        $novedadIncapacidadNoLab->fechaFinal=$fechaFin;
                    }
                    if(strtotime($novedadIncapacidadNoLab->fechaInicial) < strtotime($fechaInicioMesActual)){
                        $novedadIncapacidadNoLab->fechaInicial=$fechaInicioMesActual;
                    }
                    if(substr($novedadIncapacidadNoLab->fechaFinal, 8, 2) == "31"){
                        
                        //$arrayPlace[22] = $this->plantillaTxt("X",1," ","left");
                        //$arrayFila[22] = $this->plantillaTxt("X",1," ","left");
                        $novedadIncapacidadNoLab->fechaFinal = substr($novedadIncapacidadNoLab->fechaFinal, 0, 8)."30";
                    }
    
                    $fechaInicioIGE = date("Y-m-d",strtotime($novedadIncapacidadNoLab->fechaInicial));
                    $fechaFinIGE =  date("Y-m-d",strtotime($novedadIncapacidadNoLab->fechaFinal));
                    $arrayPlace[84] = $this->plantillaTxt($fechaInicioIGE,10," ","left");
                    $arrayPlace[85] = $this->plantillaTxt($fechaFinIGE,10," ","left");
    
                    //Tarifa en 0 para ausentismos
                    $arrayPlace[60] =  $this->plantillaTxt("0.0",9,"0","left");
                    $arrayPlace[62] = $this->plantillaTxt("0",9,"0","right");
    
                    array_push($arrayNuevoRegistro, $arrayPlace);   
                }
                
            }
            $arrayFila[24] = $this->plantillaTxt(" ",1," ","left");
            $arrayFila[84] = $this->plantillaTxt("",10," ","left");
            $arrayFila[85] = $this->plantillaTxt("",10," ","left");

            //LMA            
            $sqlWhere = "( 
                ('".$fechaInicioMesActual."' BETWEEN i.fechaInicial AND i.fechaFinal) OR
                ('".$fechaFinMesActual."' BETWEEN i.fechaInicial AND i.fechaFinal) OR
                (i.fechaInicial BETWEEN '".$fechaInicioMesActual."' AND '".$fechaFinMesActual."') OR
                (i.fechaFinal BETWEEN '".$fechaInicioMesActual."' AND '".$fechaFinMesActual."')
            )";
            $novedadesIncapacidadNoLaMat = DB::table("novedad","n")
            ->join("incapacidad as i","i.idIncapacidad","=", "n.fkIncapacidad")
            ->whereIn("i.tipoIncapacidad",["Maternidad", "Paternidad"])
            ->where("n.fkEmpleado","=", $empleado->idempleado)
            ->where("n.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->whereIn("n.fkEstado",["8","16"]) // Pagada-> no que este eliminada
            ->whereNotNull("n.fkIncapacidad")
            ->whereRaw($sqlWhere)
            ->get();
            
            
            foreach($novedadesIncapacidadNoLaMat as $novedadIncapacidadNoLaMat){
                $arrayPlace = $arraySinNada;
                $arrayPlace[25] = $this->plantillaTxt("X",1," ","left");

                $diasCompensar = 0;
                $diasPagoVac = 0;
                $fechaFin = $fechaFinMesActual;

                if(strtotime($novedadIncapacidadNoLaMat->fechaInicial)>=strtotime($fechaInicioMesActual)
                    &&  strtotime($novedadIncapacidadNoLaMat->fechaInicial)<=strtotime($fechaFin) 
                    &&  strtotime($novedadIncapacidadNoLaMat->fechaFinal)>=strtotime($fechaFin))
                {
                    $diaI = $novedadIncapacidadNoLaMat->fechaInicial;
                    $diaF = $fechaFin;
                    if(substr($diaI, 8, 2) == "31" || (substr($diaI, 8, 2) == "28" && substr($diaI, 5, 2) == "02") || (substr($diaI, 8, 2) == "29" && substr($diaI, 5, 2) == "02") ){
                        $diaI = substr($diaI,0,8)."30";
                    }
                    if(substr($diaF, 8, 2) == "31" || (substr($diaF, 8, 2) == "28" && substr($diaF, 5, 2) == "02") || (substr($diaF, 8, 2) == "29" && substr($diaF, 5, 2) == "02")){
                        $diaF = substr($diaF,0,8)."30";
                    }
                    $diasCompensar = $this->days_360($diaI, $diaF);
                    $diasCompensar ++;
                    $diasPagoVac = $diasCompensar;
                
                }
                else if(strtotime($novedadIncapacidadNoLaMat->fechaFinal)>=strtotime($fechaInicioMesActual)  
                &&  strtotime($novedadIncapacidadNoLaMat->fechaFinal)<=strtotime($fechaFin) 
                &&  strtotime($novedadIncapacidadNoLaMat->fechaInicial)<=strtotime($fechaInicioMesActual))
                {

                    $diaI = $fechaInicioMesActual;
                    $diaF = $novedadIncapacidadNoLaMat->fechaFinal;
                    if(substr($diaI, 8, 2) == "31" || (substr($diaI, 8, 2) == "28" && substr($diaI, 5, 2) == "02") || (substr($diaI, 8, 2) == "29" && substr($diaI, 5, 2) == "02") ){
                        $diaI = substr($diaI,0,8)."30";
                    }
                    if(substr($diaF, 8, 2) == "31" || (substr($diaF, 8, 2) == "28" && substr($diaF, 5, 2) == "02") || (substr($diaF, 8, 2) == "29" && substr($diaF, 5, 2) == "02")){
                        $diaF = substr($diaF,0,8)."30";
                    }
                    $diasCompensar = $this->days_360($diaI, $diaF);
                    $diasCompensar ++;
                    $diasPagoVac = $diasCompensar;

                }
                else if(strtotime($novedadIncapacidadNoLaMat->fechaInicial)<=strtotime($fechaInicioMesActual)  
                &&  strtotime($novedadIncapacidadNoLaMat->fechaFinal)>=strtotime($fechaFin)) 
                {
                    $diaI = $fechaInicioMesActual;
                    $diaF = $fechaFin;
                    if(substr($diaI, 8, 2) == "31" || (substr($diaI, 8, 2) == "28" && substr($diaI, 5, 2) == "02") || (substr($diaI, 8, 2) == "29" && substr($diaI, 5, 2) == "02") ){
                        $diaI = substr($diaI,0,8)."30";
                    }
                    if(substr($diaF, 8, 2) == "31" || (substr($diaF, 8, 2) == "28" && substr($diaF, 5, 2) == "02") || (substr($diaF, 8, 2) == "29" && substr($diaF, 5, 2) == "02")){
                        $diaF = substr($diaF,0,8)."30";
                    }
                    $diasCompensar = $this->days_360($diaI, $diaF);
                    $diasCompensar ++;
                    $diasPagoVac = $diasCompensar;

                }
                else if(strtotime($fechaInicioMesActual)<=strtotime($novedadIncapacidadNoLaMat->fechaInicial)  
                &&  strtotime($fechaFin)>=strtotime($novedadIncapacidadNoLaMat->fechaFinal)) 
                {

                    $diaI = $novedadIncapacidadNoLaMat->fechaInicial;
                    $diaF = $novedadIncapacidadNoLaMat->fechaFinal;
                    if(substr($diaI, 8, 2) == "31" || (substr($diaI, 8, 2) == "28" && substr($diaI, 5, 2) == "02") || (substr($diaI, 8, 2) == "29" && substr($diaI, 5, 2) == "02") ){
                        $diaI = substr($diaI,0,8)."30";
                    }
                    if(substr($diaF, 8, 2) == "31" || (substr($diaF, 8, 2) == "28" && substr($diaF, 5, 2) == "02") || (substr($diaF, 8, 2) == "29" && substr($diaF, 5, 2) == "02")){
                        $diaF = substr($diaF,0,8)."30";
                    }
                    $diasCompensar = $this->days_360($diaI, $diaF);
                    $diasCompensar ++;
                    $diasPagoVac = $diasCompensar;

                }


                $diasTotales = $novedadIncapacidadNoLaMat->numDias;



                $novedadIncapacidadNoLaMat->numDias = intval( $diasPagoVac);
                if($empleado->fkTipoCotizante != "51" ){
                    $periodoTrabajado = $periodoTrabajado - $novedadIncapacidadNoLaMat->numDias;
                }

                if($periodoTrabajado <= 0){
                    $arrayPlace = $arrayFila;
                    $arrayPlace[25] = $this->plantillaTxt("X",1," ","left");
                    if($empleado->esPensionado == 0  && ($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23")){
                        $arrayPlace[35] = $this->plantillaTxt($novedadIncapacidadNoLaMat->numDias,2,"0","right");
                    }
                    else{
                        $arrayPlace[35] = $this->plantillaTxt("",2,"0","right");
                    }
                   
                    $arrayPlace[36] = $this->plantillaTxt($novedadIncapacidadNoLaMat->numDias,2,"0","right");
                    $arrayPlace[37] = $this->plantillaTxt($novedadIncapacidadNoLaMat->numDias,2,"0","right"); 
                    $arrayPlace[38] = $this->plantillaTxt($novedadIncapacidadNoLaMat->numDias,2,"0","right"); 
                    
                    $itemBoucherNovedad = DB::table("item_boucher_pago_novedad", "ibpn")
                    ->where("ibpn.fkNovedad", "=",$novedadIncapacidadNoLaMat->idNovedad)
                    ->first();
                    
                    if(isset($itemBoucherNovedad->valor_ss) && $itemBoucherNovedad->valor_ss != 0){
                        $valorNovedad = ($itemBoucherNovedad->valor_ss > 0 ? $itemBoucherNovedad->valor_ss : $itemBoucherNovedad->valor_ss*-1);
                    }
                    else{
                        $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);    
                    }


                    $restaIbc = 0;
                    if($novedadIncapacidadNoLaMat->pagoTotal == 1){
                        if(isset($itemBoucherNovedad) && $itemBoucherNovedad->valor > 0){
                            if(isset($itemBoucherNovedad->valor_ss) && $itemBoucherNovedad->valor_ss != 0){
                                $valorNovedad = ($itemBoucherNovedad->valor_ss > 0 ? $itemBoucherNovedad->valor_ss : $itemBoucherNovedad->valor_ss*-1);
                                $restaIbc = $valorNovedad;
                                $valorNovedad = $diasPagoVac*$valorNovedad/$diasTotales;
                                $valorNovedadGen = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                                $valorNovedadGen = $diasPagoVac*$valorNovedadGen/$diasTotales;
                            }
                            else{
                                $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                                $restaIbc = $valorNovedad;
                                $valorNovedad = $diasPagoVac*$valorNovedad/$diasTotales;    
                            }
                            
                            
                        }
                        else{
    
                            
                            $itemBoucherNovedad = DB::table("item_boucher_pago_novedad", "ibpn")
                            ->where("ibpn.fkNovedad", "=",$novedadIncapacidadNoLaMat->idNovedad)
                            ->first();
                            $restaIbc = 0;
                            $valorNovedad = 0;
                            if(isset($itemBoucherNovedad)){
                                if(isset($itemBoucherNovedad->valor_ss) && $itemBoucherNovedad->valor_ss != 0){
                                    $valorNovedad = ($itemBoucherNovedad->valor_ss > 0 ? $itemBoucherNovedad->valor_ss : $itemBoucherNovedad->valor_ss*-1);
                                    $valorNovedad = $diasPagoVac*$valorNovedad/$diasTotales;
                                    $valorNovedadGen = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                                }
                                else{
                                    $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                                    $valorNovedad = $diasPagoVac*$valorNovedad/$diasTotales;
                                }
                            }
                           
                            
                            
                        
                        }
                    
                        
                    }
                    else{
                        
                        if(isset($itemBoucherNovedad->valor_ss) && $itemBoucherNovedad->valor_ss != 0){
                            $valorNovedad = ($itemBoucherNovedad->valor_ss > 0 ? $itemBoucherNovedad->valor_ss : $itemBoucherNovedad->valor_ss*-1);    
                            $valorNovedadGen = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);    
                            $restaIbc = $valorNovedad;
                        }
                        else{
                            $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);    
                            $restaIbc = $valorNovedad;
                        }
                        
                    }
                    
                    
                    if($empleado->esPensionado != "0"){
                        $arrayPlace[41] = $this->plantillaTxt(0,9,"0","right");
                        $arrayPlace[45] = $this->plantillaTxt("0.0",7,"0","left");
                    }
                    else{
                        $arrayPlace[41] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        $ibcAFP = $ibcAFP - $restaIbc;
                    }
                    
                    
                    $arrayPlace[42] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                    $arrayPlace[43] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                    $arrayPlace[44] = $this->plantillaTxt(round($ibcCCF),9,"0","right");
                    if($ibcOtros > 0){
                        $arrayPlace[94] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                    }
                    else{
                        $arrayPlace[94] = $this->plantillaTxt("0",9,"0","right");
                    }
    
                    $arrayFila[62] = $this->plantillaTxt(0,9,"0","right");
    
    
                    
                    $ibcEPS = $ibcEPS - $restaIbc;
                    $ibcARL = $ibcARL - $restaIbc;
                    $ibcCCF = $ibcCCF - $restaIbc;
                    $ibcOtros = $ibcOtros - $restaIbc;
    
    
    
    
    
    
    
                    $fechaInicioLMA = date("Y-m-d",strtotime($novedadIncapacidadNoLaMat->fechaInicial));
                    $fechaFinLMA =  date("Y-m-d",strtotime($novedadIncapacidadNoLaMat->fechaFinal));
                    $arrayPlace[86] = $this->plantillaTxt($fechaInicioLMA,10," ","left");
                    $arrayPlace[87] = $this->plantillaTxt($fechaFinLMA,10," ","left");
    
                    //Tarifa en 0 para ausentismos
                    $arrayPlace[60] =  $this->plantillaTxt("0.0",9,"0","left");
                    $arrayPlace[62] = $this->plantillaTxt("0",9,"0","right");
    
                    array_push($arrayNuevoRegistro, $arrayPlace);  
                }
                else{
                    if($empleado->esPensionado == 0  && ($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23")){
                        $arrayPlace[35] = $this->plantillaTxt($novedadIncapacidadNoLaMat->numDias,2,"0","right");
                    }
                    else{
                        $arrayPlace[35] = $this->plantillaTxt("",2,"0","right");
                    }
                    $arrayPlace[36] = $this->plantillaTxt($novedadIncapacidadNoLaMat->numDias,2,"0","right");
                    $arrayPlace[37] = $this->plantillaTxt($novedadIncapacidadNoLaMat->numDias,2,"0","right"); 
                    $arrayPlace[38] = $this->plantillaTxt($novedadIncapacidadNoLaMat->numDias,2,"0","right"); 
    
                    $itemBoucherNovedad = DB::table("item_boucher_pago_novedad", "ibpn")
                    ->where("ibpn.fkNovedad", "=",$novedadIncapacidadNoLaMat->idNovedad)
                    ->first();
                    
                    if(isset($itemBoucherNovedad->valor_ss) && $itemBoucherNovedad->valor_ss != 0){
                        $valorNovedad = ($itemBoucherNovedad->valor_ss > 0 ? $itemBoucherNovedad->valor_ss : $itemBoucherNovedad->valor_ss*-1);
                    }
                    else{
                        $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);    
                    }
                    $restaIbc = 0;
                    if($novedadIncapacidadNoLaMat->pagoTotal == 1){
                        if(isset($itemBoucherNovedad) && $itemBoucherNovedad->valor > 0){
                            if(isset($itemBoucherNovedad->valor_ss) && $itemBoucherNovedad->valor_ss != 0){
                                $valorNovedad = ($itemBoucherNovedad->valor_ss > 0 ? $itemBoucherNovedad->valor_ss : $itemBoucherNovedad->valor_ss*-1);
                                $restaIbc = $valorNovedad;
                                $valorNovedad = $diasPagoVac*$valorNovedad/$diasTotales;
                                $valorNovedadGen = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                                $valorNovedadGen = $diasPagoVac*$valorNovedadGen/$diasTotales;
                            }
                            else{
                                $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                                $restaIbc = $valorNovedad;
                                $valorNovedad = $diasPagoVac*$valorNovedad/$diasTotales;    
                            }
                            
                            
                        }
                        else{
    
                            
                            $itemBoucherNovedad = DB::table("item_boucher_pago_novedad", "ibpn")
                            ->where("ibpn.fkNovedad", "=",$novedadIncapacidadNoLaMat->idNovedad)
                            ->first();
                            $restaIbc = 0;
                            $valorNovedad = 0;
                            if(isset($itemBoucherNovedad)){
                                if(isset($itemBoucherNovedad->valor_ss) && $itemBoucherNovedad->valor_ss != 0){
                                    $valorNovedad = ($itemBoucherNovedad->valor_ss > 0 ? $itemBoucherNovedad->valor_ss : $itemBoucherNovedad->valor_ss*-1);
                                    $valorNovedad = $diasPagoVac*$valorNovedad/$diasTotales;
                                    $valorNovedadGen = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                                }
                                else{
                                    $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                                    $valorNovedad = $diasPagoVac*$valorNovedad/$diasTotales;
                                }
                            }
                           
                            
                            
                        
                        }
                    
                        
                    }
                    else{
                        
                        if(isset($itemBoucherNovedad->valor_ss) && $itemBoucherNovedad->valor_ss != 0){
                            $valorNovedad = ($itemBoucherNovedad->valor_ss > 0 ? $itemBoucherNovedad->valor_ss : $itemBoucherNovedad->valor_ss*-1);    
                            $valorNovedadGen = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);    
                            $restaIbc = $valorNovedad;
                        }
                        else{
                            $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);    
                            $restaIbc = $valorNovedad;
                        }
                        
                    }
    
                    if($empleado->esPensionado != "0"){
                        $arrayPlace[41] = $this->plantillaTxt(0,9,"0","right");
                        $arrayPlace[45] = $this->plantillaTxt("0.0",7,"0","left");
                    }
                    else{
                        $arrayPlace[41] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        $ibcAFP = $ibcAFP - $restaIbc;
                    }
                    
                    
                    $arrayPlace[42] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                    $arrayPlace[43] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                    $arrayPlace[44] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                    if($ibcOtros > 0){
                        $arrayPlace[94] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                    }
                    else{
                        $arrayPlace[94] = $this->plantillaTxt("0",9,"0","right");
                    }
    
                    $arrayFila[62] = $this->plantillaTxt(0,9,"0","right");
    
    
                    
                    $ibcEPS = $ibcEPS - $restaIbc;
                    $ibcARL = $ibcARL - $restaIbc;
                    $ibcCCF = $ibcCCF - $restaIbc;
                    $ibcOtros = $ibcOtros - $restaIbc;
    
    
    
    
    
    
    
                    $fechaInicioLMA = date("Y-m-d",strtotime($novedadIncapacidadNoLaMat->fechaInicial));
                    $fechaFinLMA =  date("Y-m-d",strtotime($novedadIncapacidadNoLaMat->fechaFinal));
                    $arrayPlace[86] = $this->plantillaTxt($fechaInicioLMA,10," ","left");
                    $arrayPlace[87] = $this->plantillaTxt($fechaFinLMA,10," ","left");
    
                    //Tarifa en 0 para ausentismos
                    $arrayPlace[60] =  $this->plantillaTxt("0.0",9,"0","left");
                    $arrayPlace[62] = $this->plantillaTxt("0",9,"0","right");
    
                    array_push($arrayNuevoRegistro, $arrayPlace);  
                }


                 
            }
            $arrayFila[25] = $this->plantillaTxt(" ",1," ","left");
            $arrayFila[86] = $this->plantillaTxt("",10," ","left");
            $arrayFila[87] = $this->plantillaTxt("",10," ","left");

            //VAC 
            $sqlWhere = "( 
                ('".$fechaInicioMesActual."' BETWEEN v.fechaInicio AND v.fechaFin) OR
                ('".$fechaFinMesActual."' BETWEEN v.fechaInicio AND v.fechaFin) OR
                (v.fechaInicio BETWEEN '".$fechaInicioMesActual."' AND '".$fechaFinMesActual."') OR
                (v.fechaFin BETWEEN '".$fechaInicioMesActual."' AND '".$fechaFinMesActual."')
            )";

            $novedadesVac = DB::table("novedad","n")
            ->join("vacaciones as v","v.idVacaciones","=", "n.fkVacaciones")
            ->where("n.fkEmpleado","=", $empleado->idempleado)
            ->where("n.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->whereIn("n.fkEstado",["8","16"]) // Pagada-> no que este eliminada o parcialmente paga (para las de pago parcial)
            ->whereNotNull("n.fkVacaciones")
            ->where("n.fkConcepto","=", "29")
            ->whereRaw($sqlWhere)
            ->get();

            //dd($novedadesVac);
            //dd($arrayFila,$arrayNuevoRegistro);
            foreach($novedadesVac as $novedadVac){
                $arrayPlace = $arraySinNada;
                $arrayPlace[26] = $this->plantillaTxt("X",1," ","left");
                
                $diasCompensar = 0;
                $diasPagoVac = 0;
                $fechaFin = $fechaFinMesActual;
                $diaI="";
                $diaF="";
                

                if(strtotime($novedadVac->fechaInicio)>=strtotime($fechaInicioMesActual)
                    &&  strtotime($novedadVac->fechaInicio)<=strtotime($fechaFin) 
                    &&  strtotime($novedadVac->fechaFin)>=strtotime($fechaFin))
                {
                    
                    $diaI = $novedadVac->fechaInicio;
                    $diaF = $fechaFin;
                    if(substr($diaF, 8, 2) == "31"){
                        $diaF = date("Y-m-30",strtotime($diaF));
                    }
                    if(substr($diaI, 8, 2) == "31"){
                        $diaI = date("Y-m-30",strtotime($diaI));
                    }
                    $diasCompensar = $this->days_360($diaI, $diaF) + 1;
                    $diasPagoVac = $diasCompensar;                    
                
                }
                else if(strtotime($novedadVac->fechaFin)>=strtotime($fechaInicioMesActual)  
                &&  strtotime($novedadVac->fechaFin)<=strtotime($fechaFin) 
                &&  strtotime($novedadVac->fechaInicio)<=strtotime($fechaInicioMesActual))
                {
                   
                    
                    $diaI = $fechaInicioMesActual;
                    $diaF = $novedadVac->fechaFin;
                    if(substr($diaF, 8, 2) == "31"){
                        $diaF = date("Y-m-30",strtotime($diaF));
                    }
                    
                    $diasCompensar = $this->days_360($fechaInicioMesActual, $novedadVac->fechaFin) + 1;
                    $diasPagoVac = $diasCompensar;
                }
                else if(strtotime($novedadVac->fechaInicio)<=strtotime($fechaInicioMesActual)  
                &&  strtotime($novedadVac->fechaFin)>=strtotime($fechaFin)) 
                {
                    
                    
                    $diaI = $fechaInicioMesActual;
                    $diaF = $fechaFin;
                    if(substr($diaF, 8, 2) == "31"){
                        $diaF = date("Y-m-30",strtotime($diaF));
                    }
                    $diasCompensar = $this->days_360($fechaInicioMesActual, $diaF) + 1;
                    $diasPagoVac = $diasCompensar;
                }
                else if(strtotime($fechaInicioMesActual)<=strtotime($novedadVac->fechaInicio)  
                &&  strtotime($fechaFin)>=strtotime($novedadVac->fechaFin)) 
                {
                   
                    $diaI = $novedadVac->fechaInicio;
                    $diaF = $novedadVac->fechaFin;
                    if(substr($diaF, 8, 2) == "31"){
                        $diaF = date("Y-m-30",strtotime($diaF));
                    }
                    if(substr($diaI, 8, 2) == "31"){
                        $diaI = date("Y-m-30",strtotime($diaI));
                    }


                    $diasCompensar = $this->days_360($diaI, $diaF) + 1;
                    $diasPagoVac = $diasCompensar;
                }
                
                $diasTotales = $novedadVac->diasCompensar;
                $novedadVac->diasCompensar = intval( $diasCompensar);
                
                

                if($empleado->fkTipoCotizante != "51" ){
                    $periodoTrabajado = $periodoTrabajado - $novedadVac->diasCompensar;
                }
                if($empleado->esPensionado == 0  && ($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23")){
                    $arrayPlace[35] = $this->plantillaTxt($novedadVac->diasCompensar,2,"0","right");
                }
                else{
                    $arrayPlace[35] = $this->plantillaTxt("",2,"0","right");
                }
                $arrayPlace[36] = $this->plantillaTxt($novedadVac->diasCompensar,2,"0","right");
                $arrayPlace[37] = $this->plantillaTxt($novedadVac->diasCompensar,2,"0","right"); 
                $arrayPlace[38] = $this->plantillaTxt($novedadVac->diasCompensar,2,"0","right"); 

                $itemBoucherNovedad = DB::table("item_boucher_pago_novedad", "ibpn")
                ->join("item_boucher_pago as ibp","ibp.idItemBoucherPago", "=","ibpn.fkItemBoucher")
                ->join("boucherpago as bp","bp.idBoucherPago", "=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->selectRaw("sum(ibpn.valor) as valor")
                ->where("ibpn.fkNovedad", "=",$novedadVac->idNovedad)
                ->whereBetween("ln.fechaLiquida",[$fechaInicioMesActual, $fechaFinMesActual])
                ->first();                

                $restaIbc = 0;
            
                if($novedadVac->pagoAnticipado == 1){
                    if(isset($itemBoucherNovedad) && $itemBoucherNovedad->valor > 0){
                        $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                        $restaIbc = $valorNovedad;
                        //dd($valorNovedad, $novedadVac->fechaFin);
                        if($diasPagoVac>0){

                            
                            if(strtotime($fechaFinMesActual) > strtotime($novedadVac->fechaInicio) && substr($novedadVac->fechaFin, 8, 2) == "31"){
                                //Comentado para hidroyunda
                                $diasPagoVac++;                                   
                            }
                            else if(strtotime($fechaFinMesActual) > strtotime($novedadVac->fechaInicio) && date("t", strtotime($novedadVac->fechaInicio)) == "31" && strtotime($novedadVac->fechaFin) > strtotime(date("Y-m-t", strtotime($novedadVac->fechaInicio)))){
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
                        ->where("ibpn.fkNovedad", "=",$novedadVac->idNovedad)
                        ->first();
                        
                        $restaIbc = 0;
                        $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                        
                        $valorNovedad = $diasPagoVac*$valorNovedad/$diasTotales;
                    }
                
                    
                }
                else{                    
                    $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);    
                    $restaIbc = $valorNovedad;
                }
                if($empleado->tipoRegimen == "Salario Integral"){
                    $valorNovedad = $valorNovedad*0.7;
                }
                //dd($valorNovedad);

                
                
                if($empleado->esPensionado != "0"){
                    $arrayPlace[41] = $this->plantillaTxt(0,9,"0","right");
                    $arrayPlace[45] = $this->plantillaTxt("0.0",7,"0","left");
                }
                else{
                    $arrayPlace[41] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                    if($diasCompensar > 0){
                        $ibcAFP = $ibcAFP - $restaIbc;
                    }
                    else{
                        $ibcAFP = $ibcAFP - $restaIbc + $valorNovedad;
                    }
                }
                
                
                $arrayPlace[42] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                $arrayPlace[43] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                if($periodoTrabajado <= 0){
                    $arrayPlace[44] = $this->plantillaTxt(round($ibcCCF),9,"0","right");
                }
                else{
                    $arrayPlace[44] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                }
                //dump($restaIbc, $novedadVac,$valorNovedad);
                if($ibcOtros > 0){
                    if($periodoTrabajado <= 0){
                        $arrayPlace[94] = $this->plantillaTxt(round($ibcOtros),9,"0","right");
                    }
                    else{
                        $arrayPlace[94] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                    }
                    $arrayPlace[94] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                }
                else{
                    $arrayPlace[94] = $this->plantillaTxt("0",9,"0","right");
                }

                $arrayFila[62] = $this->plantillaTxt(0,9,"0","right");

                if($diasCompensar > 0){
                    $ibcEPS = $ibcEPS - $restaIbc;
                    $ibcARL = $ibcARL - $restaIbc;
                    $ibcCCF = $ibcCCF - $restaIbc;
                    $ibcOtros = $ibcOtros - $restaIbc;
                }
                else{
                    $ibcEPS = $ibcEPS - $restaIbc + $valorNovedad;
                    $ibcARL = $ibcARL - $restaIbc + $valorNovedad;
                    $ibcCCF = $ibcCCF - $restaIbc + $valorNovedad;
                    $ibcOtros = $ibcOtros - $restaIbc + $valorNovedad;
                }

                //dd($ibcEPS);
                



                if(strtotime($novedadVac->fechaFin) > strtotime($fechaFinMesActual)){
                    $novedadVac->fechaFin=$fechaFinMesActual;
                }
                if(strtotime($novedadVac->fechaInicio) < strtotime($fechaInicioMesActual)){
                    $novedadVac->fechaInicio=$fechaInicioMesActual;
                }
                
                if(substr($novedadVac->fechaFin, 8, 2) == "31"){
                    
                    //$arrayPlace[22] = $this->plantillaTxt("X",1," ","left");
                    //$arrayFila[22] = $this->plantillaTxt("X",1," ","left");
                    $novedadVac->fechaFin = substr($novedadVac->fechaFin, 0, 8)."30";
                }


                $fechaInicioVAC = date("Y-m-d",strtotime($novedadVac->fechaInicio));
                $fechaFinVAC =  date("Y-m-d",strtotime($novedadVac->fechaFin));
                if(substr($fechaInicioVAC, 8, 2) == "31"){
                    $fechaInicioVAC = date("Y-m-30",strtotime($fechaInicioVAC));
                }
                if(substr($fechaFinVAC, 8, 2) == "31"){
                    $fechaFinVAC = date("Y-m-30",strtotime($fechaFinVAC));
                }


                $arrayPlace[88] = $this->plantillaTxt($fechaInicioVAC,10," ","left");
                $arrayPlace[89] = $this->plantillaTxt($fechaFinVAC,10," ","left");


                //Tarifa en 0 para ausentismos
                $arrayPlace[60] =  $this->plantillaTxt("0.0",9,"0","left");
                $arrayPlace[62] = $this->plantillaTxt("0",9,"0","right");
                //dd($arrayPlace);
                if($diasCompensar > 0){
                    array_push($arrayNuevoRegistro, $arrayPlace);   
                }
                

                
            }

            //dd("bye", $ibcCCF, $diasCompensar, $valorNovedad);

            
            //LICENCIAS REMUNERADAS
            $novedadesLIC = DB::table("novedad","n")
            ->join("licencia as l","l.idLicencia","=", "n.fkLicencia")
            ->where("n.fkEmpleado","=", $empleado->idempleado)
            ->where("n.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->whereIn("n.fkEstado",["8"]) // Pagada-> no que este eliminada
            ->whereNotNull("n.fkLicencia")
            ->whereBetween("n.fechaRegistro",[$fechaInicioMesActual, $fechaFinMesActual])
            ->get();

            
            foreach($novedadesLIC as $novedadLIC){
               
        
                $novedadLIC->numDias = intval( $novedadLIC->numDias);
                if($empleado->fkTipoCotizante != "51" ){
                    $periodoTrabajado = $periodoTrabajado - $novedadLIC->numDias;
                }

                if( $periodoTrabajado <= 0){
                    $arrayPlace = $arrayFila;
                    $arrayPlace[26] = $this->plantillaTxt("L",1," ","left");
                    if($empleado->esPensionado == 0  && ($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23")){
                        $arrayPlace[35] = $this->plantillaTxt($novedadLIC->numDias,2,"0","right");
                    }
                    else{
                        $arrayPlace[35] = $this->plantillaTxt("",2,"0","right");
                    }
                    $arrayPlace[36] = $this->plantillaTxt($novedadLIC->numDias,2,"0","right");
                    $arrayPlace[37] = $this->plantillaTxt($novedadLIC->numDias,2,"0","right"); 
                    $arrayPlace[38] = $this->plantillaTxt($novedadLIC->numDias,2,"0","right"); 
    
                    $itemBoucherNovedad = DB::table("item_boucher_pago_novedad", "ibpn")
                    ->where("ibpn.fkNovedad", "=",$novedadLIC->idNovedad)
                    ->first();
                    
                    if(isset($itemBoucherNovedad->valor_ss) && $itemBoucherNovedad->valor_ss != 0){
                        $valorNovedad = ($itemBoucherNovedad->valor_ss > 0 ? $itemBoucherNovedad->valor_ss : $itemBoucherNovedad->valor_ss*-1);
                    }
                    else{
                        $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                    }
                    
    
    
                    if($empleado->esPensionado != "0"){
                        $arrayPlace[41] = $this->plantillaTxt(0,9,"0","right");
                        $arrayPlace[45] = $this->plantillaTxt("0.0",7,"0","left");
                    }
                    else{
                        $arrayPlace[41] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        $ibcAFP = $ibcAFP - $valorNovedad;
                    }
                    
                    $arrayPlace[42] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                    $arrayPlace[43] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                    $arrayPlace[44] = $this->plantillaTxt(round($ibcCCF),9,"0","right");
                    if($ibcOtros > 0){
                        $arrayPlace[94] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                    }
                    else{
                        $arrayPlace[94] = $this->plantillaTxt("0",9,"0","right");
                    }
    
                    $arrayFila[62] = $this->plantillaTxt(0,9,"0","right");
    
    
                    
                    $ibcEPS = $ibcEPS - $valorNovedad;
                    $ibcARL = $ibcARL - $valorNovedad;
                    $ibcCCF = $ibcCCF - $valorNovedad;
                    if($ibcOtros > 0){
                        $ibcOtros = $ibcOtros - $valorNovedad;
                    }
                    
    
    
    
    
                    $fechaInicioLIC = date("Y-m-d",strtotime($novedadLIC->fechaInicial));
                    $fechaFinLIC =  date("Y-m-d",strtotime($novedadLIC->fechaFinal));
                    $arrayPlace[88] = $this->plantillaTxt($fechaInicioLIC,10," ","left");
                    $arrayPlace[89] = $this->plantillaTxt($fechaFinLIC,10," ","left");
    
                    //Tarifa en 0 para ausentismos
                    $arrayPlace[60] =  $this->plantillaTxt("0.0",9,"0","left");
                    $arrayPlace[62] = $this->plantillaTxt("0",9,"0","right");
    
    
                    array_push($arrayNuevoRegistro, $arrayPlace);
                }else{
                    $arrayPlace = $arraySinNada;
                    $arrayPlace[26] = $this->plantillaTxt("L",1," ","left");
    
    
    
                    if($empleado->esPensionado == 0  && ($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23")){
                        $arrayPlace[35] = $this->plantillaTxt($novedadLIC->numDias,2,"0","right");
                    }
                    else{
                        $arrayPlace[35] = $this->plantillaTxt("",2,"0","right");
                    }
                    $arrayPlace[36] = $this->plantillaTxt($novedadLIC->numDias,2,"0","right");
                    $arrayPlace[37] = $this->plantillaTxt($novedadLIC->numDias,2,"0","right"); 
                    $arrayPlace[38] = $this->plantillaTxt($novedadLIC->numDias,2,"0","right"); 
    
                    $itemBoucherNovedad = DB::table("item_boucher_pago_novedad", "ibpn")
                    ->where("ibpn.fkNovedad", "=",$novedadLIC->idNovedad)
                    ->first();
                    
                    if(isset($itemBoucherNovedad->valor_ss) && $itemBoucherNovedad->valor_ss != 0){
                        $valorNovedad = ($itemBoucherNovedad->valor_ss > 0 ? $itemBoucherNovedad->valor_ss : $itemBoucherNovedad->valor_ss*-1);
                    }
                    else{
                        $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                    }
                    
    
    
                    if($empleado->esPensionado != "0"){
                        $arrayPlace[41] = $this->plantillaTxt(0,9,"0","right");
                        $arrayPlace[45] = $this->plantillaTxt("0.0",7,"0","left");
                    }
                    else{
                        $arrayPlace[41] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                        $ibcAFP = $ibcAFP - $valorNovedad;
                    }
                    
                    $arrayPlace[42] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                    $arrayPlace[43] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                    $arrayPlace[44] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                    if($ibcOtros > 0){
                        $arrayPlace[94] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                    }
                    else{
                        $arrayPlace[94] = $this->plantillaTxt("0",9,"0","right");
                    }
    
                    $arrayFila[62] = $this->plantillaTxt(0,9,"0","right");
    
    
                    
                    $ibcEPS = $ibcEPS - $valorNovedad;
                    $ibcARL = $ibcARL - $valorNovedad;
                    $ibcCCF = $ibcCCF - $valorNovedad;
                    if($ibcOtros > 0){
                        $ibcOtros = $ibcOtros - $valorNovedad;
                    }
                    
    
    
    
    
                    $fechaInicioLIC = date("Y-m-d",strtotime($novedadLIC->fechaInicial));
                    $fechaFinLIC =  date("Y-m-d",strtotime($novedadLIC->fechaFinal));
                    $arrayPlace[88] = $this->plantillaTxt($fechaInicioLIC,10," ","left");
                    $arrayPlace[89] = $this->plantillaTxt($fechaFinLIC,10," ","left");
    
                    //Tarifa en 0 para ausentismos
                    $arrayPlace[60] =  $this->plantillaTxt("0.0",9,"0","left");
                    $arrayPlace[62] = $this->plantillaTxt("0",9,"0","right");
    
    
                    array_push($arrayNuevoRegistro, $arrayPlace);
                }

                   
            }

            
            $arrayFila[26] = $this->plantillaTxt(" ",1," ","left");
            $arrayFila[88] = $this->plantillaTxt("",10," ","left");
            $arrayFila[89] = $this->plantillaTxt("",10," ","left");

            
            //AVP
            $itemsBoucherAVP = DB::table("item_boucher_pago", "ibp")
            ->selectRaw("Sum(ibp.descuento) as suma")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
            ->where("gcc.fkGrupoConcepto","=","6") //6 - APORTE VOLUNTARIO PENSION	
            ->get();

            $aporteVoluntarioPension = 0;
            if(sizeof($itemsBoucherAVP)>0){
                if($itemsBoucherAVP[0]->suma > 0){                    
                    $aporteVoluntarioPension = $itemsBoucherAVP[0]->suma;
                    
                    $arrayFila[47] = $this->plantillaTxt(intval($itemsBoucherAVP[0]->suma),9,"0","right");
                    $arrayFila[27] = $this->plantillaTxt("X",1," ","left");
                    
                }
                else{
                    $arrayFila[27] = $this->plantillaTxt(" ",1," ","left");
                    $arrayFila[47] = $this->plantillaTxt("",9,"0","right");
                }
            } 
            else{
                $arrayFila[27] = $this->plantillaTxt(" ",1," ","left");
                $arrayFila[47] = $this->plantillaTxt("",9,"0","right");    
            }
            
            
            
            //VCT
            $cambioCentroTrab = DB::table("cambiocentrotrabajo","cct")
                ->where("cct.fkEmpleado", "=", $empleado->idempleado)
                ->whereBetween("cct.fechaCambio", [$fechaInicioMesActual, $fechaFinMesActual])
                ->get();
            
            if(sizeof($cambioCentroTrab)>0){
                $arrayPlace = $arraySinNada;
                $arrayPlace[28] = $this->plantillaTxt("X",1," ","left");
                $arrayPlace[90] = $this->plantillaTxt($cambioCentroTrab[0]->fechaCambio,10," ","left");
                $arrayPlace[91] = $this->plantillaTxt($fechaFinMesActual,10," ","left");
                array_push($arrayNuevoRegistro, $arrayPlace);  
            }
            else{
                $arrayFila[28] = $this->plantillaTxt(" ",1," ","left");
                $arrayFila[90] = $this->plantillaTxt("",10," ","left");
                $arrayFila[91] = $this->plantillaTxt("",10," ","left");
            }
            
            //IRL
            $novedadesIncapacidadLab = DB::table("novedad","n")
            ->join("incapacidad as i","i.idIncapacidad","=", "n.fkIncapacidad")
            ->whereRaw("(i.fkTipoAfilicacion is NULL or i.fkTipoAfilicacion = 1)") // NULL - Accidente laboral
            ->whereNotIn("i.tipoIncapacidad",["Maternidad", "Paternidad"])
            ->where("n.fkEmpleado","=", $empleado->idempleado)
            ->where("n.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->whereIn("n.fkEstado",["8"]) // Pagada o sin pagar-> no que este eliminada
            ->whereNotNull("n.fkIncapacidad")
            ->whereBetween("n.fechaRegistro",[$fechaInicioMesActual, $fechaFinMesActual])
            ->get();

            //dd($novedadesIncapacidadLab, $novedadesLIC, $novedadesIncapacidadNoLab, $novedadesIncapacidadNoLaMat, $sqlWhere);


            foreach($novedadesIncapacidadLab as $novedadIncapacidadLab){
                $arrayPlace = $arraySinNada;
                $arrayPlace[29] = $this->plantillaTxt($novedadIncapacidadLab->numDias,2,"0","right");

                $novedadIncapacidadLab->numDias = intval( $novedadIncapacidadLab->numDias);
                if($empleado->fkTipoCotizante != "51" ){
                    $periodoTrabajado = $periodoTrabajado - $novedadIncapacidadLab->numDias;
                }
                if($empleado->esPensionado == 0  && ($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23")){
                    $arrayPlace[35] = $this->plantillaTxt($novedadIncapacidadLab->numDias,2,"0","right");
                }
                else{
                    $arrayPlace[35] = $this->plantillaTxt("",2,"0","right");
                }
                $arrayPlace[36] = $this->plantillaTxt($novedadIncapacidadLab->numDias,2,"0","right");
                $arrayPlace[37] = $this->plantillaTxt($novedadIncapacidadLab->numDias,2,"0","right"); 
                $arrayPlace[38] = $this->plantillaTxt($novedadIncapacidadLab->numDias,2,"0","right"); 

                
                $itemBoucherNovedad = DB::table("item_boucher_pago_novedad", "ibpn")
                ->where("ibpn.fkNovedad", "=",$novedadIncapacidadLab->idNovedad)
                ->first();
                
                if(isset($itemBoucherNovedad->valor_ss) && $itemBoucherNovedad->valor_ss != 0){
                    $valorNovedad = ($itemBoucherNovedad->valor_ss > 0 ? $itemBoucherNovedad->valor_ss : $itemBoucherNovedad->valor_ss*-1);
                }
                else{

                    $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                }

                if($empleado->esPensionado != "0"){
                    $arrayPlace[41] = $this->plantillaTxt(0,9,"0","right");
                    $arrayPlace[45] = $this->plantillaTxt("0.0",7,"0","left");
                }
                else{
                    $arrayPlace[41] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                    $ibcAFP = $ibcAFP - $valorNovedad;
                }
                
                $arrayPlace[42] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                $arrayPlace[43] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                $arrayPlace[44] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                if($ibcOtros > 0){
                    $arrayPlace[94] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                }
                else{
                    $arrayPlace[94] = $this->plantillaTxt("0",9,"0","right");
                }


                
                $ibcEPS = $ibcEPS - $valorNovedad;
                $ibcARL = $ibcARL - $valorNovedad;
                $ibcCCF = $ibcCCF - $valorNovedad;                
                if($ibcOtros > 0){
                    $ibcOtros = $ibcOtros - $valorNovedad;
                }


                $fechaInicioIRL = date("Y-m-d",strtotime($novedadIncapacidadLab->fechaInicial));
                $fechaFinIRL =  date("Y-m-d",strtotime($novedadIncapacidadLab->fechaFinal));
                $arrayPlace[92] = $this->plantillaTxt($fechaInicioIRL,10," ","left");
                $arrayPlace[93] = $this->plantillaTxt($fechaFinIRL,10," ","left");

                //Tarifa en 0 para ausentismos
                $arrayPlace[60] =  $this->plantillaTxt("0.0",9,"0","left");
                $arrayPlace[62] = $this->plantillaTxt("0",9,"0","right");
                
                array_push($arrayNuevoRegistro, $arrayPlace);   
            }

            //LINEA EN CASO DE CAMBIO DE TIPO DE AFILIACION
            $cambioTipoCotizante = DB::table("cambiotipocotizante", "ctc")
            ->join("concepto as c","c.idconcepto", "=","ctc.fkConceptoAnt")
            ->whereBetween("ctc.fechaCambio",[$fechaInicioMesActual, $fechaFinMesActual])
            ->where("ctc.fkEmpleado","=",$empleado->idempleado)
            ->where("ctc.fkEstado","=","8")
            ->first();
            if(isset($cambioTipoCotizante)){
                $arrayPlace = $arraySinNada;
                $arrayPlace[36] = intval($cambioTipoCotizante->dias);
                $arrayPlace[4] = $this->plantillaTxt($cambioTipoCotizante->fkTipoCotizanteAnt,2,"0","right");//Tipo cotizante
                $arrayPlace[15] = $this->plantillaTxt("X",1," ","left");
                if(intval(date("d",strtotime($cambioTipoCotizante->fechaCambio))) != 1){
                    $cambioTipoCotizante->fechaCambio = date("Y-m-d", strtotime($cambioTipoCotizante->fechaCambio." -1 day"));
                }
                $arrayPlace[80] = $this->plantillaTxt($cambioTipoCotizante->fechaCambio,10,"","left");


                $valorNovedad = (intval($ibcEPS)/30)*$cambioTipoCotizante->dias;

                
                
                
                
                
                if($ibcAFP > 0){
                    $ibcAFP = $ibcAFP - round($valorNovedad);
                    $arrayPlace[41] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                }
                if($ibcEPS > 0){
                    $ibcEPS = $ibcEPS - round($valorNovedad);
                    $arrayPlace[42] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                }
                if($ibcARL > 0){
                    $ibcARL = $ibcARL - round($valorNovedad);
                    $arrayPlace[43] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                }
                if($ibcCCF > 0){
                    $ibcCCF = $ibcCCF - round($valorNovedad);
                    $arrayPlace[44] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                }

                if($ibcOtros > 0 || $empresa->exento == "0"){
                    $ibcOtros = $ibcOtros - round($valorNovedad);
                    $arrayPlace[94] = $this->plantillaTxt(round($valorNovedad),9,"0","right");
                }


                array_push($arrayNuevoRegistro, $arrayPlace);   
            }






            $arrayFila[29] = $this->plantillaTxt("",2,"0","right");
            $arrayFila[92] = $this->plantillaTxt("",10," ","left");
            $arrayFila[93] = $this->plantillaTxt("",10," ","left");


            //Codigo Pension
            if($empleado->esPensionado == 0  && ($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23")){
                $pension = DB::table("afiliacion","a")
                ->join("tercero as t", "t.idTercero", "=", "a.fkTercero")
                ->where("a.fkEmpleado", "=", $empleado->idempleado)
                ->where("a.fkPeriodoActivo", "=", $empleado->idPeriodo)
                ->where("a.fkTipoAfilicacion", "=", "4") // 4 - Tipo Afiliacion = Pension
                ->first();
                
                $arrayFila[30] = $this->plantillaTxt($pension->codigoTercero,6," ","left");
            }
            else{
                $arrayFila[30] = $this->plantillaTxt("",6," ","left");
            }
        


            $salud = DB::table("afiliacion","a")
            ->join("tercero as t", "t.idTercero", "=", "a.fkTercero")
            ->where("a.fkEmpleado", "=", $empleado->idempleado)
            ->where("a.fkPeriodoActivo", "=", $empleado->idPeriodo)
            ->where("a.fkTipoAfilicacion", "=", "3") // 3 - Tipo Afiliacion = Salud
            ->first();

            $arrayFila[32] = $this->plantillaTxt(($salud->codigoTercero ?? ""),6," ","left");
            
            
            //CCF
            if($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23"){
                $ccf = DB::table("afiliacion","a")
                ->join("tercero as t", "t.idTercero", "=", "a.fkTercero")
                ->where("a.fkEmpleado", "=", $empleado->idempleado)
                ->where("a.fkPeriodoActivo", "=", $empleado->idPeriodo)
                ->where("a.fkTipoAfilicacion", "=", "2") // 2 - Tipo Afiliacion = Caja de compensacion
                ->first();
                $arrayFila[34] = $this->plantillaTxt($ccf->codigoTercero,6," ","left");
            }
            else{
                $arrayFila[34] = $this->plantillaTxt("",6," ","left");
            }
            
            
            //AFP días
            if($empleado->esPensionado == 0  && ($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23")){
                $arrayFila[35] = $this->plantillaTxt($periodoTrabajado,2,"0","right");
            }
            else{
                $arrayFila[35] = $this->plantillaTxt("0",2,"0","right");
            }
            //EPS días
            $arrayFila[36] = $this->plantillaTxt($periodoTrabajado,2,"0","right");
            //ARL días
            if($empleado->fkTipoCotizante != "12"){
                $arrayFila[37] = $this->plantillaTxt($periodoTrabajado,2,"0","right");
            }
            else{
                $arrayFila[37] = $this->plantillaTxt("0",2,"0","right");
            }
            //CCF días
            if($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19"  && $empleado->fkTipoCotizante != "23"){
                $arrayFila[38] = $this->plantillaTxt($periodoTrabajado,2,"0","right");
            }else{
                $arrayFila[38] = $this->plantillaTxt("",2,"0","right");   
            }

            $minimosRedondeoFijoSal = DB::table("tabla_smmlv_redondeo")->where("dias","=","30")->first();
            $salarioMes = ($conceptoFijoSalario->valor < $minimosRedondeoFijoSal->ibc ? $minimosRedondeoFijoSal->ibc :$conceptoFijoSalario->valor);
            if($empleado->tipoRegimen == "Salario Integral"){
                $salarioMes = ($conceptoFijoSalario->valor < ($valorSalarioMinimo*13) ? ($valorSalarioMinimo*13) :$conceptoFijoSalario->valor);
            }
            $arrayFila[39] = $this->plantillaTxt(intval($salarioMes),9,"0","right");
            

            if($empleado->tipoRegimen=="Salario Integral"){
                $arrayFila[40] = $this->plantillaTxt("X",1," ","left");
            }
            else{
                if(date("Y",strtotime($fechaFin))<=2020 && date("m",strtotime($fechaFin))<=6){
                    $arrayFila[40] = $this->plantillaTxt(" ",1," ","left");
                }
                else{
                    if($empleado->fkTipoCotizante == "12" || $empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23"){
                        $arrayFila[40] = $this->plantillaTxt("",1," ","left");
                    }
                    else{
                        $arrayFila[40] = $this->plantillaTxt("F",1," ","left");
                    }
                    
                }
                
                
            }

            
            
        

            //$ibcAFP = ($ultimoBoucher->ibc_afp/30) * $periodoTrabajadoSinNov;
            
            $minimosRedondeo = DB::table("tabla_smmlv_redondeo")->where("dias","=",$periodoTrabajado)->first();
            if(!isset($minimosRedondeo)){
                $minimosRedondeo = DB::table("tabla_smmlv_redondeo")->where("dias","=","1")->first();
            }
            
            //$ibcAFP = $ultimoBoucher->ibc_afp;
            //$ibcAFP = ($ultimoBoucher->ibc_afp * $periodoTrabajado) / $periodoTrabajadoSinNov;

            
            if($ibcAFP < $minimosRedondeo->ibc && $ibcAFP > 0){
                $ibcAFP = $minimosRedondeo->ibc;
            }
            if($empleado->fkTipoCotizante == "12" || $empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23"){
                $ibcAFP = 0;
            }

            $arrayFila[41] = $this->plantillaTxt(round($ibcAFP),9,"0","right");

            //$ibcEPS = ($ultimoBoucher->ibc_eps/30) * $periodoTrabajadoSinNov;
            
            //$ibcEPS = $ultimoBoucher->ibc_eps;
            //$ibcEPS = ($ultimoBoucher->ibc_eps * $periodoTrabajado) / $periodoTrabajadoSinNov;
            if($ibcEPS < $minimosRedondeo->ibc && $ibcEPS > 0){
                $ibcEPS = $minimosRedondeo->ibc;
            }

            

            $arrayFila[42] = $this->plantillaTxt(round($ibcEPS),9,"0","right");

            //$ibcARL = ($ultimoBoucher->ibc_arl/30) * $periodoTrabajadoSinNov;

            //$ibcARL = $ultimoBoucher->ibc_arl;
            //$ibcARL = ($ultimoBoucher->ibc_arl * $periodoTrabajado) / $periodoTrabajadoSinNov;
            if($ibcARL < $minimosRedondeo->ibc && $ibcARL > 0){
                $ibcARL = $minimosRedondeo->ibc;
            }
            if($empleado->fkTipoCotizante == "12"){
                $ibcARL = 0;
            }

            $arrayFila[43] = $this->plantillaTxt(round($ibcARL),9,"0","right");

        


            //$ibcCCF = ($ultimoBoucher->ibc_ccf/30) * $periodoTrabajado;
            //$ibcCCF = $ultimoBoucher->ibc_ccf;
            //$ibcCCF = ($ultimoBoucher->ibc_ccf * $periodoTrabajado) / $periodoTrabajadoSinNov;
            if($ibcCCF < $minimosRedondeo->ibc && $ibcCCF > 0){
                $ibcCCF = $minimosRedondeo->ibc;
            }
            if($empleado->fkTipoCotizante == "12" || $empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23"){
                $ibcCCF = 0;
            }
            $arrayFila[44] = $this->plantillaTxt(round($ibcCCF),9,"0","right");
            $totalNomina = $totalNomina + $ibcCCF;



            $parafiscales = DB::table("parafiscales","p")
            ->selectRaw("Sum(p.afp) as suma_afp, Sum(p.eps) as suma_eps, Sum(p.arl) as suma_arl, Sum(p.ccf) as suma_ccf, Sum(p.icbf) as suma_icbf, Sum(p.sena) as suma_sena")
            ->join("boucherpago as bp","bp.idBoucherPago","=","p.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
            ->get();




            $itemsBoucherAFP = DB::table("item_boucher_pago", "ibp")
            ->selectRaw("Sum(ibp.descuento) as suma")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
            ->where("ibp.fkConcepto","=","19") //19 - PENSION
            ->get();

            $varsPension = DB::table("variable", "v")->whereIn("v.idVariable",["51","52"])->get();
            $totalPorcentajePension = 0;
            foreach($varsPension as $varPension){
                $totalPorcentajePension = $totalPorcentajePension + floatval($varPension->valor);
            }
            
            $cotizacionPension = 0;
            
            if($empleado->esPensionado==0){
                //TARIFA AFP
                $arrayFila[45] = $this->plantillaTxt($totalPorcentajePension,7,"0","left");

            
                foreach($itemsBoucherAFP as $itemBoucherAFP){
                    $cotizacionPension = $cotizacionPension + $itemBoucherAFP->suma;
                }
                foreach($parafiscales as $parafiscal){
                    $cotizacionPension = $cotizacionPension + $parafiscal->suma_afp;
                }
                //$cotizacionPension= round(($cotizacionPension/30) * $periodoTrabajadoSinNov, -2);
                //Cotizacion AFP
                
                $cotizacionPension = round($ibcAFP*$totalPorcentajePension);
               
                
                $cotizacionPension = $this->roundSup($cotizacionPension, -2);
                if($cotizacionPension < $minimosRedondeo->pension && $cotizacionPension > 0){
                    $cotizacionPension = $minimosRedondeo->pension;
                }
                

                $arrayFila[46] = $this->plantillaTxt($cotizacionPension,9,"0","right");

                

            }
            else{
                $arrayFila[45] = $this->plantillaTxt("0.0",7,"0","left");
                $arrayFila[46] = $this->plantillaTxt("",9,"0","right");
            }

            //Aporte voluntario del aportante
            $arrayFila[48] = $this->plantillaTxt("",9,"0","right");

            //total cotizacion AFP
            //$totalCotizacionAFP = $cotizacionPension + $aporteVoluntarioPension;
            $totalCotizacionAFP = $cotizacionPension;
            $arrayFila[49] = $this->plantillaTxt(intval($totalCotizacionAFP),9,"0","right");



            //FSP SOLIDARIDAD	            

            $itemsBoucherFPS = DB::table("item_boucher_pago", "ibp")
            ->selectRaw("Sum(ibp.descuento) as suma")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
            ->where("ibp.fkConcepto","=","33") //33 - FPS
            ->get();

            $totalFPS = 0;
            foreach($itemsBoucherFPS as $itemBoucherFPS){
                $totalFPS = $totalFPS + $itemBoucherFPS->suma;
            }
            

            

            if($totalFPS > 0){
                $valorSalario = $ultimoBoucher->ibc_afp;        

                $variablesAporteFondo = DB::table("variable")->whereIn("idVariable",[11,12,13,14,15])->get();
                $varAporteFondo = array();
                foreach($variablesAporteFondo as $variablesAporteFond){
                    $varAporteFondo[$variablesAporteFond->idVariable] = $variablesAporteFond->valor;
                }
                
                $variables = DB::table("variable")->where("idVariable","=","1")->first();
                $valorSalarioMinimo = $variables->valor;
                $porcentajeDescuento = $varAporteFondo[12];
                if(intval($valorSalario) >= intval($valorSalarioMinimo * $varAporteFondo[11])){
                    $porcentajeDescuento = $varAporteFondo[12];
                }
                if(intval($valorSalario) >= intval($valorSalarioMinimo * $varAporteFondo[13])){
                    $diffSalariosMas = $valorSalario - ($valorSalarioMinimo * ($varAporteFondo[13]));
                    $number = round($diffSalariosMas  / $valorSalarioMinimo, 2);
                    $numSalariosMas = ceil($number);
                    $porcentajeDescuento = $porcentajeDescuento + ($numSalariosMas * $varAporteFondo[14]);
                }

                if($porcentajeDescuento >= $varAporteFondo[15]){
                    $porcentajeDescuento = $varAporteFondo[15];
                }
                
                
                $totalFPS = round($ibcAFP*$porcentajeDescuento);
                
                $paraFPS = round(($totalFPS * 0.005)/$porcentajeDescuento);
                $paraFS = $totalFPS - $paraFPS;
                //dd($ibcAFP, $totalFPS, $paraFPS, $paraFS, $porcentajeDescuento, $valorSalario, $varAporteFondo[12], ($varAporteFondo[13] - 1));

                $paraFPS = $this->roundSup($paraFPS, -2);
                
                $paraFS = $this->roundSup($paraFS, -2);

                
                $arrayFila[50] = $this->plantillaTxt(intval($paraFPS),9,"0","right");
                $arrayFila[51] = $this->plantillaTxt(intval($paraFS),9,"0","right");
            }
            else{
                $arrayFila[50] = $this->plantillaTxt("",9,"0","right");
                $arrayFila[51] = $this->plantillaTxt("",9,"0","right");
            }
            $arrayFila[52] = $this->plantillaTxt("",9,"0","right");



            $varsEPS = DB::table("variable", "v")->whereIn("v.idVariable",["49","50"])->get();
            $totalPorcentajeEPS = 0;
            //dd($ultimoBoucher->ibc_otros);
            foreach($varsEPS as $varEPS){
                if($ultimoBoucher->ibc_otros==0 && $varEPS->idVariable == "50" && $empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23"){
                    
                }
                else{
                    $totalPorcentajeEPS = $totalPorcentajeEPS + floatval($varEPS->valor);
                }                
            }
            if($empleado->fkTipoCotizante == "51"){
                $totalPorcentajeEPS = "0.0";
            }
            
            
            $arrayFila[53] =$this->plantillaTxt($totalPorcentajeEPS,7,"0","left");   

            $itemsBoucherESP = DB::table("item_boucher_pago", "ibp")
            ->selectRaw("Sum(ibp.descuento) as suma")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
            ->where("ibp.fkConcepto","=","18") //18 - SALUD
            ->get();
            $cotizacionSalud = 0;
            foreach($itemsBoucherESP as $itemBoucherESP){
                $cotizacionSalud = $cotizacionSalud + $itemBoucherESP->suma;
            }
            foreach($parafiscales as $parafiscal){
                $cotizacionSalud = $cotizacionSalud + $parafiscal->suma_eps;
            }

            $cotizacionSalud = round($ibcEPS*$totalPorcentajeEPS);
            

            if($totalPorcentajeEPS == 0.125){
                if($cotizacionSalud < $minimosRedondeo->salud_12_5 && $cotizacionSalud > 0){
                    $cotizacionSalud = $minimosRedondeo->salud_12_5;
                }
            }
            if($totalPorcentajeEPS == 0.12){
                if($cotizacionSalud < $minimosRedondeo->salud_12 && $cotizacionSalud > 0){
                    $cotizacionSalud = $minimosRedondeo->salud_12;
                }
            }
            if($totalPorcentajeEPS == 0.1){
                if($cotizacionSalud < $minimosRedondeo->salud_10 && $cotizacionSalud > 0){
                    $cotizacionSalud = $minimosRedondeo->salud_10;
                }
            }
            if($totalPorcentajeEPS == 0.08){
                if($cotizacionSalud < $minimosRedondeo->salud_8 && $cotizacionSalud > 0){
                    $cotizacionSalud = $minimosRedondeo->salud_8;
                }
            }
            if($totalPorcentajeEPS == 0.04){
                if($cotizacionSalud < $minimosRedondeo->salud_4 && $cotizacionSalud > 0){
                    $cotizacionSalud = $minimosRedondeo->salud_4;
                }
            }

            
            //$cotizacionSalud= round(($cotizacionSalud/30) * $periodoTrabajadoSinNov, -2);
            $cotizacionSalud = $this->roundSup($cotizacionSalud, -2);
            $arrayFila[54] =$this->plantillaTxt(round($cotizacionSalud, -2),9,"0","right");

            //Valor de la UPC adicional.
            $arrayFila[55] =$this->plantillaTxt("",9,"0","right");
            
            $arrayFila[56] =$this->plantillaTxt("",15," ","left");
            $arrayFila[57] =$this->plantillaTxt("",9,"0","left");
            $arrayFila[58] =$this->plantillaTxt("",15," ","left");
            $arrayFila[59] =$this->plantillaTxt("",9,"0","left");


            //TARIFA RIESGOS
            if($empleado->fkTipoCotizante != "12"){
                $nivelesArl = DB::table("nivel_arl","na")
                ->where("na.idnivel_arl","=",$empleado->fkNivelArl)
                ->first();
                $arrayFila[60] = $this->plantillaTxt(($nivelesArl->porcentaje / 100),9,"0","left");
            }
            else{
                $arrayFila[60] = $this->plantillaTxt("0.0",9,"0","left");
            }
            if($empleado->fkTipoCotizante == "51"){
                //$arrayFila[60] = $this->plantillaTxt("0.0",9,"0","left");
            }
            
            //Centro de Trabajo
            if(isset($empleado->fkCentroTrabajo)){
                $centroTrabajo = DB::table("centrotrabajo","ct")
                ->where("ct.idCentroTrabajo","=",$empleado->fkCentroTrabajo)
                ->first();

                $arrayFila[61] = $this->plantillaTxt($centroTrabajo->codigo,9,"0","right");
            }
            else{
                $arrayFila[61] = $this->plantillaTxt("",9,"0","right");
            }


            $cotizacionArl = 0;
            foreach($parafiscales as $parafiscal){
                $cotizacionArl = $cotizacionArl + $parafiscal->suma_arl;
            }

            //$cotizacionArl = round(($cotizacionArl/30) * $periodoTrabajadoSinNov, -2);
            if(isset($nivelesArl)){
                $cotizacionArl = $ibcARL*($nivelesArl->porcentaje / 100);
            }else{
                if($empleado->fkTipoCotizante != "12"){
                    $cotizacionArl = 0;
                }
                
            }
            

            
            $cotizacionArl = $this->roundSup($cotizacionArl, -2);
            if($empleado->fkNivelArl == 1){
                if($cotizacionArl < $minimosRedondeo->riesgos_1 && $cotizacionArl > 0){
                    $cotizacionArl = $minimosRedondeo->riesgos_1;
                }
            }
            if($empleado->fkNivelArl == 2){
                if($cotizacionArl < $minimosRedondeo->riesgos_2 && $cotizacionArl > 0){
                    $cotizacionArl = $minimosRedondeo->riesgos_2;
                }
            }
            if($empleado->fkNivelArl == 3){
                if($cotizacionArl < $minimosRedondeo->riesgos_3 && $cotizacionArl > 0){
                    $cotizacionArl = $minimosRedondeo->riesgos_3;
                }
            }
            if($empleado->fkNivelArl == 4){
                if($cotizacionArl < $minimosRedondeo->riesgos_4 && $cotizacionArl > 0){
                    $cotizacionArl = $minimosRedondeo->riesgos_4;
                }
            }
            if($empleado->fkNivelArl == 5){
                if($cotizacionArl < $minimosRedondeo->riesgos_5 && $cotizacionArl > 0){
                    $cotizacionArl = $minimosRedondeo->riesgos_5;
                }
            }

            $arrayFila[62] = $this->plantillaTxt($cotizacionArl,9,"0","right");



            //TARIFA CCF
            if($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23"){
                $varsCCF = DB::table("variable", "v")->whereIn("v.idVariable",["53"])->get();
                $totalPorcentajeCCF = 0;
                foreach($varsCCF as $varCCF){
                    $totalPorcentajeCCF = $totalPorcentajeCCF + floatval($varCCF->valor);
                }
            }
            else{
                $totalPorcentajeCCF = "0.0";
            }
        
            $arrayFila[63] = $this->plantillaTxt($totalPorcentajeCCF,7,"0","left");    

            //VALOR CCF
            $ccfFinal = 0;
            foreach($parafiscales as $parafiscal){
                $ccfFinal = $ccfFinal + $parafiscal->suma_ccf;
            }
            //$ccfFinal = ($ccfFinal/30) * $periodoTrabajado;

            
            $ccfFinal = round($ibcCCF*$totalPorcentajeCCF);
            
            $ccfFinal = $this->roundSup($ccfFinal, -2);

            if($ccfFinal < $minimosRedondeo->ccf && $ccfFinal > 0){
                $ccfFinal = $minimosRedondeo->ccf;
            }
            
            
            $arrayFila[64] = $this->plantillaTxt($ccfFinal,9,"0","right");
            
            if($empresa->exento == "0"){
                $ibcOtros = $ibcCCF;
                $ultimoBoucher->ibc_otros = $ultimoBoucher->ibc_ccf;
            }
            if($ibcOtros<0){
                $ibcOtros = 0;
            }
            //TARIFA SENA
            $varsSENA = DB::table("variable", "v")->whereIn("v.idVariable",["55"])->get();
            $totalPorcentajeSENA = 0;
            foreach($varsSENA as $varSENA){
                $totalPorcentajeSENA = $totalPorcentajeSENA + floatval($varSENA->valor);
            }
            if($ibcOtros<=0){
                $totalPorcentajeSENA = "0.0";
            }
            

            if($empleado->fkTipoCotizante == "12" || $empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23"){
                $totalPorcentajeSENA = "0.0";
            }


            $arrayFila[65] = $this->plantillaTxt($totalPorcentajeSENA,7,"0","left");  

            //VALOR SENA
            $SENAFinal = 0;
            foreach($parafiscales as $parafiscal){
                $SENAFinal = $SENAFinal + $parafiscal->suma_sena;
            }
            //$SENAFinal = ($SENAFinal/30) * $periodoTrabajadoSinNov;
            $SENAFinal = $ibcOtros*$totalPorcentajeSENA;
            $SENAFinal = $this->roundSup($SENAFinal, -2);

            if($totalPorcentajeSENA == 0.005){
                if($SENAFinal < $minimosRedondeo->sena_0_5 && $SENAFinal > 0){
                    $SENAFinal = $minimosRedondeo->sena_0_5;
                }
            }
            if($totalPorcentajeSENA == 0.02){
                if($SENAFinal < $minimosRedondeo->sena_2 && $SENAFinal > 0){
                    $SENAFinal = $minimosRedondeo->sena_2;
                }
            }
            
            $arrayFila[66] = $this->plantillaTxt(intval($SENAFinal),9,"0","right");

            //TARIFA ICBF
            $varsICBF = DB::table("variable", "v")->whereIn("v.idVariable",["54"])->get();
            $totalPorcentajeICBF = 0;
            foreach($varsICBF as $varICBF){
                $totalPorcentajeICBF = $totalPorcentajeICBF + floatval($varICBF->valor);
            }
            if($ultimoBoucher->ibc_otros==0){
                $totalPorcentajeICBF = "0.0";
            }

            if($empleado->fkTipoCotizante == "12" || $empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23"){
                $totalPorcentajeICBF = "0.0";
            }
            $arrayFila[67] = $this->plantillaTxt($totalPorcentajeICBF,7,"0","left");  

            //VALOR ICBF
            $ICBFFinal = 0;
            foreach($parafiscales as $parafiscal){
                $ICBFFinal = $ICBFFinal + $parafiscal->suma_icbf;
            }
            //$ICBFFinal = ($ICBFFinal/30) * $periodoTrabajadoSinNov;     
            
            $ICBFFinal = $ibcOtros*$totalPorcentajeICBF;
            $ICBFFinal = $this->roundSup($ICBFFinal, -2);


            if($ICBFFinal < $minimosRedondeo->icbf && $ICBFFinal > 0 && $empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23"){
                $ICBFFinal = $minimosRedondeo->icbf;
            }

            
            $arrayFila[68] = $this->plantillaTxt(intval($ICBFFinal),9,"0","right");

            $arrayFila[69] = $this->plantillaTxt("0.0",7,"0","left");  
            $arrayFila[70] = $this->plantillaTxt("",9,"0","right");

            $arrayFila[71] = $this->plantillaTxt("0.0",7,"0","left");  
            $arrayFila[72] = $this->plantillaTxt("",9,"0","right");


            $arrayFila[73] = $this->plantillaTxt("",2," ","right");
            $arrayFila[74] = $this->plantillaTxt("",16," ","right");
            


            if($ibcEPS < ($minimosRedondeo->ibc * 10) && $empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23" && $empleado->tipoRegimen != "Salario Integral"){
                $arrayFila[75] = $this->plantillaTxt("S",1,"","left");
            }
            else{
                $arrayFila[75] = $this->plantillaTxt("N",1,"","left");
            }

            if($empleado->fkTipoCotizante != "12"){
                $arrayFila[76] = $this->plantillaTxt($empresa->codigoArl,6," ","left");
                $arrayFila[77] = $this->plantillaTxt($empleado->fkNivelArl,1,"","left");
            }
            else{
                $arrayFila[76] = $this->plantillaTxt("",6," ","left");
                $arrayFila[77] = $this->plantillaTxt(" ",1,"","left");
            }

            

            $arrayFila[78] = $this->plantillaTxt("",1," ","left");

            //$arrayFila[94] = $this->plantillaTxt($ultimoBoucher->ibc_otros,9,"0","right");
            if($ultimoBoucher->ibc_otros!=0 && $empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23"){
                //$ibcOtros = ($ultimoBoucher->ibc_otros/30) * $periodoTrabajado;
        
                //$ibcOtros = $this->roundSup($ibcOtros, -2);
                if($ibcOtros < $minimosRedondeo->ibc && $ibcOtros > 0){
                    $ibcOtros = $minimosRedondeo->ibc;
                }
            
                $arrayFila[94] = $this->plantillaTxt(round($ibcOtros),9,"0","right");
            }
            else{
                $arrayFila[94] = $this->plantillaTxt("0",9,"0","right");
            }
            
            $horasTrabajadas = $periodoTrabajado*8;
            $novedadesHorasExtras = DB::table("novedad","n")
            ->join("horas_extra as h","h.idHoraExtra","=", "n.fkHorasExtra")
            ->where("n.fkEmpleado","=", $empleado->idempleado)
            ->where("n.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->whereIn("n.fkEstado",["8"]) // Pagada
            ->whereNotNull("n.fkHorasExtra")
            ->whereBetween("n.fechaRegistro",[$fechaInicioMesActual, $fechaFinMesActual])
            ->get();

            foreach($novedadesHorasExtras as $novedadHorasExtras){
                $horasTrabajadas= $horasTrabajadas + ceil($novedadHorasExtras->cantidadHoras);
                
            }
            if($horasTrabajadas > 300){
                $horasTrabajadas = 300;
            }
            if($empleado->fkTipoCotizante == "12" || $empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23"){
                $horasTrabajadas = 0;
            }

            if($empleado->fkTipoCotizante=="51"){
                $horasTrabajadas = round($ultimoBoucher->horasPeriodo);
            }


            $arrayFila[95] = $this->plantillaTxt($horasTrabajadas,3,"0","right");
            $arrayFila[96] = $this->plantillaTxt("",10," ","right");

            //ActividadEconomica
            if(isset($empleado->fkCentroTrabajo)){
                $centroTrabajo = DB::table("centrotrabajo","ct")
                ->select(["a.riesgo","a.ciiu","a.codigo"])
                ->leftJoin("actividad_economica_decreto_768 as a", "a.id","=","ct.fkActividadEconomica768")
                ->where("ct.idCentroTrabajo","=",$empleado->fkCentroTrabajo)
                ->first();

                $arrayFila[97] = $this->plantillaTxt($centroTrabajo->riesgo.$centroTrabajo->ciiu.$centroTrabajo->codigo,7,"0","right");
            }
            else{                
                $arrayFila[97] = $this->plantillaTxt($empresa->riesgo.$empresa->ciiu.$empresa->codigo,7,"0","right");
            }


            $arrayFila = $this->upperCaseAllArray($arrayFila);
            
            if($periodoTrabajado > 0){
                
                array_push($arrayMuestra, $arrayFila);
                $contador++;
            }
            
            
        
            foreach($arrayNuevoRegistro as $arrayRegistroN){
            
                $arrayFila2 = $arrayRegistroN;

                $cambioTipoCotizante = DB::table("cambiotipocotizante", "ctc")
                ->join("concepto as c","c.idconcepto", "=","ctc.fkConceptoAnt")
                ->whereBetween("ctc.fechaCambio",[$fechaInicioMesActual, $fechaFinMesActual])
                ->where("ctc.fkEmpleado","=",$empleado->idempleado)
                ->where("ctc.fkEstado","=","8")
                ->first();
                if(isset($cambioTipoCotizante)){
                    $empleado->fkTipoCotizante = $cambioTipoCotizante->fkTipoCotizanteAnt;
                }
                $arrayFila2[1] = $this->plantillaTxt($contador,5,"0","right");
                if(!isset($arrayFila2[14])){
                    $arrayFila2[14] = $this->plantillaTxt(" ",1,"","left");
                }
                
                if(!isset($arrayFila2[79])){
                    $arrayFila2[79] = $this->plantillaTxt("",10," ","left");
                }
                
                if(!isset($arrayFila2[15])){
                    $arrayFila2[15] = $this->plantillaTxt("",1," ","left");
                }

                if(!isset($arrayFila2[80])){
                    $arrayFila2[80] = $this->plantillaTxt("",10," ","left");
                }
                
                
                if(!isset($arrayFila2[16])){
                    $arrayFila2[16] = $this->plantillaTxt(" ",1," ","left");
                }
                if(!isset($arrayFila2[17])){
                    $arrayFila2[17] = $this->plantillaTxt(" ",1," ","left");
                    $arrayFila2[33] = $this->plantillaTxt(" ",6," ","left");
                }
                if(!isset($arrayFila2[18])){
                    $arrayFila2[18] = $this->plantillaTxt(" ",1," ","left");
                }
                if(!isset($arrayFila2[19])){
                    $arrayFila2[19] = $this->plantillaTxt(" ",1," ","left");
                    $arrayFila2[31] = $this->plantillaTxt(" ",6," ","left");
                }
                $arrayFila2[20] = $this->plantillaTxt(" ",1," ","left");
                $arrayFila2[81] = $this->plantillaTxt("",10," ","left");
                $arrayFila2[21] = $this->plantillaTxt(" ",1," ","left");
                if(!isset($arrayFila2[22])){
                    $arrayFila2[22] = $this->plantillaTxt(" ",1," ","left");
                }
            
                if(!isset($arrayFila2[23])){
                    $arrayFila2[23] = $this->plantillaTxt(" ",1," ","left");
                    $arrayFila2[82] = $this->plantillaTxt("",10," ","left");
                    $arrayFila2[83] = $this->plantillaTxt("",10," ","left");
                
                }
                $periodoTrabajado = 0;
                
                if(isset($arrayFila2[36])){
                    $periodoTrabajado = intval($arrayFila2[36]);
                }
                else{
                    $arrayFila2[35] = $this->plantillaTxt("",2,"0","right");
                    $arrayFila2[36] = $this->plantillaTxt("",2,"0","right");
                    $arrayFila2[37] = $this->plantillaTxt("",2,"0","right"); 
                    $arrayFila2[38] = $this->plantillaTxt("",2,"0","right"); 
                }


                if(!isset($arrayFila2[24])){
                    $arrayFila2[24] = $this->plantillaTxt(" ",1," ","left");
                    $arrayFila2[84] = $this->plantillaTxt("",10," ","left");
                    $arrayFila2[85] = $this->plantillaTxt("",10," ","left");
                
                }
            
                if(!isset($arrayFila2[25])){
                    $arrayFila2[25] = $this->plantillaTxt(" ",1," ","left");
                    $arrayFila2[86] = $this->plantillaTxt("",10," ","left");
                    $arrayFila2[87] = $this->plantillaTxt("",10," ","left");
                
                }

                if(!isset($arrayFila2[26])){
                    $arrayFila2[26] = $this->plantillaTxt(" ",1," ","left");
                    $arrayFila2[88] = $this->plantillaTxt("",10," ","left");
                    $arrayFila2[89] = $this->plantillaTxt("",10," ","left");
                
                }

                if(!isset($arrayFila2[27])){
                    $arrayFila2[27] = $this->plantillaTxt(" ",1," ","left");
                    $arrayFila2[47] = $this->plantillaTxt("",9,"0","right");
                }

                if(!isset($arrayFila2[28])){
                    $arrayFila2[28] = $this->plantillaTxt(" ",1," ","left");
                    $arrayFila2[90] = $this->plantillaTxt("",10," ","left");
                    $arrayFila2[91] = $this->plantillaTxt("",10," ","left");
                }

                if(!isset($arrayFila2[29])){
                    $arrayFila2[29] = $this->plantillaTxt("",2,"0","right");
                    $arrayFila2[92] = $this->plantillaTxt("",10," ","left");
                    $arrayFila2[93] = $this->plantillaTxt("",10," ","left");
                }
                
                if($empleado->esPensionado == 0  && ($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23")){
                    $pension = DB::table("afiliacion","a")
                    ->join("tercero as t", "t.idTercero", "=", "a.fkTercero")
                    ->where("a.fkEmpleado", "=", $empleado->idempleado)
                    ->where("a.fkPeriodoActivo", "=", $empleado->idPeriodo)
                    ->where("a.fkTipoAfilicacion", "=", "4") // 4 - Tipo Afiliacion = Pension
                    ->first();
                    $arrayFila2[30] = $this->plantillaTxt($pension->codigoTercero,6," ","left");
                }
                else{
                    $arrayFila2[30] = $this->plantillaTxt("",6," ","left");
                }
            
                $salud = DB::table("afiliacion","a")
                ->join("tercero as t", "t.idTercero", "=", "a.fkTercero")
                ->where("a.fkEmpleado", "=", $empleado->idempleado)
                ->where("a.fkPeriodoActivo", "=", $empleado->idPeriodo)
                ->where("a.fkTipoAfilicacion", "=", "3") // 3 - Tipo Afiliacion = Salud
                ->first();
    
                $arrayFila2[32] = $this->plantillaTxt($salud->codigoTercero,6," ","left");
                
                
                //CCF
                if($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23"){
                    $ccf = DB::table("afiliacion","a")
                    ->join("tercero as t", "t.idTercero", "=", "a.fkTercero")
                    ->where("a.fkEmpleado", "=", $empleado->idempleado)
                    ->where("a.fkPeriodoActivo", "=", $empleado->idPeriodo)
                    ->where("a.fkTipoAfilicacion", "=", "2") // 2 - Tipo Afiliacion = Caja de compensacion
                    ->first();
                    $arrayFila2[34] = $this->plantillaTxt($ccf->codigoTercero,6," ","left");
                }else{
                    $arrayFila2[34] = $this->plantillaTxt("",6," ","left");
                }
                
                
                //AFP días
                
                if($empleado->esPensionado == 0  && ($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23")){
                    $arrayFila2[35] = $this->plantillaTxt($periodoTrabajado,2,"0","right");
                }
                else{
                    $arrayFila2[35] = $this->plantillaTxt("0",2,"0","right");
                }
                //EPS días
                $arrayFila2[36] = $this->plantillaTxt($periodoTrabajado,2,"0","right");
                //ARL días
                if($empleado->fkTipoCotizante != "12"){
                    $arrayFila2[37] = $this->plantillaTxt($periodoTrabajado,2,"0","right");
                }
                else{
                    $arrayFila2[37] = $this->plantillaTxt("0",2,"0","right");
                }
                //CCF días
                if($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23"){
                    $arrayFila2[38] = $this->plantillaTxt($periodoTrabajado,2,"0","right");
                }
                else{
                    $arrayFila2[38] = $this->plantillaTxt("0",2,"0","right");
                }
    
                //Salario
                $conceptoFijoSalario = DB::table("conceptofijo", "cf")
                ->whereIn("cf.fkConcepto",["1","2","53","54","154"])
                ->where("cf.fkEmpleado", "=", $empleado->idempleado)
                ->where("cf.fkPeriodoActivo","=",$empleado->idPeriodo)
                ->first();
                
                $minimosRedondeoFijoSal = DB::table("tabla_smmlv_redondeo")->where("dias","=","30")->first();
                $salarioMes = ($conceptoFijoSalario->valor < $minimosRedondeoFijoSal->ibc ? $minimosRedondeoFijoSal->ibc :$conceptoFijoSalario->valor);
                if($empleado->tipoRegimen == "Salario Integral"){
                    $salarioMes = ($conceptoFijoSalario->valor < ($valorSalarioMinimo*13) ? ($valorSalarioMinimo*13) :$conceptoFijoSalario->valor);
                }
                $arrayFila2[39] = $this->plantillaTxt(intval($salarioMes),9,"0","right");
    
                if($empleado->tipoRegimen=="Salario Integral"){
                    $arrayFila2[40] = $this->plantillaTxt("X",1," ","left");
                }
                else{
                    if(date("Y",strtotime($fechaFin))<=2020 && date("m",strtotime($fechaFin))<=6){
                        $arrayFila2[40] = $this->plantillaTxt(" ",1," ","left");
                    }
                    else{
                        if($empleado->fkTipoCotizante == "12" || $empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23"){
                            $arrayFila2[40] = $this->plantillaTxt(" ",1," ","left");
                        }
                        else{
                            $arrayFila2[40] = $this->plantillaTxt("F",1," ","left");
                        }

                        
                    }
                    //$arrayFila2[40] = $this->plantillaTxt("F",1," ","left");
                    //$arrayFila2[40] = $this->plantillaTxt(" ",1," ","left");
                }
    
                $ultimoBoucher = DB::table("boucherpago", "bp")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9"])
                ->orderBy("bp.idBoucherPago","desc")
                ->first();
                if(!isset($ultimoBoucher)){
                    $ultimoBoucher = DB::table("boucherpago", "bp")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                    ->whereIn("ln.fkTipoLiquidacion",["3"])
                    ->orderBy("bp.idBoucherPago","desc")
                    ->first();
                }
                else{
                    $ultimoBoucherRetiro = DB::table("boucherpago", "bp")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                    ->whereIn("ln.fkTipoLiquidacion",["3"])
                    ->orderBy("bp.idBoucherPago","desc")
                    ->first();
        
                    $ultimoBoucher->ibc_afp = $ultimoBoucher->ibc_afp + ($ultimoBoucherRetiro->ibc_afp ?? 0);
                    $ultimoBoucher->ibc_eps = $ultimoBoucher->ibc_eps + ($ultimoBoucherRetiro->ibc_eps ?? 0);
                    $ultimoBoucher->ibc_arl = $ultimoBoucher->ibc_arl + ($ultimoBoucherRetiro->ibc_arl ?? 0);
                    $ultimoBoucher->ibc_ccf = $ultimoBoucher->ibc_ccf + ($ultimoBoucherRetiro->ibc_ccf ?? 0);
                    $ultimoBoucher->ibc_otros = $ultimoBoucher->ibc_otros + ($ultimoBoucherRetiro->ibc_otros ?? 0);
                }
    
                //Parte 2
                if(!isset($arrayFila2[41])){
                    $ibcAFP = ($ultimoBoucher->ibc_afp * $periodoTrabajado) / $periodoTrabajadoSinNov;
                }
                else{
                    $ibcAFP = intval($arrayFila2[41]);
                }
                
                $minimosRedondeo = DB::table("tabla_smmlv_redondeo")->where("dias","=",$periodoTrabajado)->first();

                //$ibcAFP = $ultimoBoucher->ibc_afp;
                if(!isset($minimosRedondeo->ibc)){
                    dd($periodoTrabajado, $empleado, $arrayFila2,$arrayNuevoRegistro);
                }
                
                
                if($ibcAFP < $minimosRedondeo->ibc && $ibcAFP > 0){
                    $ibcAFP = $minimosRedondeo->ibc;
                }

                $arrayFila2[41] = $this->plantillaTxt(round($ibcAFP),9,"0","right");
    
                //$ibcEPS = ($ultimoBoucher->ibc_eps/30) * $periodoTrabajadoSinNov;

                //$ibcEPS = $ultimoBoucher->ibc_eps;
                if(!isset($arrayFila2[42])){
                    $ibcEPS = ($ultimoBoucher->ibc_eps * $periodoTrabajado) / $periodoTrabajadoSinNov;
                }
                else{
                    $ibcEPS = intval($arrayFila2[42]);
                    
                }
                
                
                if($ibcEPS < $minimosRedondeo->ibc && $ibcEPS > 0){
                    $ibcEPS = $minimosRedondeo->ibc;
                }
                
                $arrayFila2[42] = $this->plantillaTxt(round($ibcEPS),9,"0","right");
    
                //$ibcARL = ($ultimoBoucher->ibc_arl/30) * $periodoTrabajadoSinNov;

                //$ibcARL = $ultimoBoucher->ibc_arl;
                
                if(!isset($arrayFila2[43])){
                    $ibcARL = ($ultimoBoucher->ibc_arl * $periodoTrabajado) / $periodoTrabajadoSinNov;
                }
                else{
                    $ibcARL = intval($arrayFila2[43]);
                }

                if($ibcARL < $minimosRedondeo->ibc && $ibcARL > 0){
                    $ibcARL = $minimosRedondeo->ibc;
                }
                if($empleado->fkTipoCotizante == "12"){
                    $ibcARL = 0;
                }

            
                $arrayFila2[43] = $this->plantillaTxt(round($ibcARL),9,"0","right");
    
                //$ibcCCF = ($ultimoBoucher->ibc_ccf/30) * $periodoTrabajado;
                
                
                if(!isset($arrayFila2[44])){
                    $ibcCCF = ($ultimoBoucher->ibc_ccf * $periodoTrabajado) / $periodoTrabajadoSinNov;
                }
                else{
                    $ibcCCF = intval($arrayFila2[44]);
                }
                
                if($ibcCCF < $minimosRedondeo->ibc && $ibcCCF > 0){
                    $ibcCCF = $minimosRedondeo->ibc;
                }
                $totalNomina = $totalNomina + $ibcCCF;
                $arrayFila2[44] = $this->plantillaTxt(round($ibcCCF),9,"0","right");
                
                $parafiscales = DB::table("parafiscales","p")
                ->selectRaw("Sum(p.afp) as suma_afp, Sum(p.eps) as suma_eps, Sum(p.arl) as suma_arl, Sum(p.ccf) as suma_ccf, Sum(p.icbf) as suma_icbf, Sum(p.sena) as suma_sena")
                ->join("boucherpago as bp","bp.idBoucherPago","=","p.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                ->get();
    
                $itemsBoucherAFP = DB::table("item_boucher_pago", "ibp")
                ->selectRaw("Sum(ibp.descuento) as suma")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                ->where("ibp.fkConcepto","=","19") //19 - PENSION
                ->get();
    
                $varsPension = DB::table("variable", "v")->whereIn("v.idVariable",["51","52"])->get();
                $totalPorcentajePension = 0;
                foreach($varsPension as $varPension){
                    $totalPorcentajePension = $totalPorcentajePension + floatval($varPension->valor);
                }
                
                $cotizacionPension = 0;
                
                if($empleado->esPensionado==0){
                    //TARIFA AFP
                    if(!isset($arrayFila2[45])){
                        $arrayFila2[45] = $this->plantillaTxt($totalPorcentajePension,7,"0","left");
                    }
                    else{
                        $totalPorcentajePension = floatval($arrayFila2[45]);
                    }
                    
                    
                
                    foreach($itemsBoucherAFP as $itemBoucherAFP){
                        $cotizacionPension = $cotizacionPension + $itemBoucherAFP->suma;
                    }
                    foreach($parafiscales as $parafiscal){
                        $cotizacionPension = $cotizacionPension + $parafiscal->suma_afp;
                    }

                
                    //$cotizacionPension= round(($cotizacionPension/30) * $periodoTrabajadoSinNov, -2);
                    
                    //Cotizacion AFP
                    $cotizacionPension = $ibcAFP*$totalPorcentajePension;
                    
                    if($cotizacionPension < $minimosRedondeo->pension && $cotizacionPension > 0 && $totalPorcentajePension == 0.16){
                        $cotizacionPension = $minimosRedondeo->pension;
                    }
                    
                    
                
                    $cotizacionPension = $this->roundSup($cotizacionPension, -2);
                    $arrayFila2[46] = $this->plantillaTxt($cotizacionPension,9,"0","right");
    
                    
    
                }
                else{
                    $arrayFila2[45] = $this->plantillaTxt("0.0",7,"0","left");
                    $arrayFila2[46] = $this->plantillaTxt("",9,"0","right");
                }
    
                //Aporte voluntario del aportante
                $arrayFila2[48] = $this->plantillaTxt("",9,"0","right");
                $aporteVoluntarioPension = intval($arrayFila2[47]);
                //total cotizacion AFP
                $totalCotizacionAFP = $cotizacionPension + $aporteVoluntarioPension;
                $arrayFila2[49] = $this->plantillaTxt($totalCotizacionAFP,9,"0","right");
    
    
    
                //FSP SOLIDARIDAD	            
    
                $itemsBoucherFPS = DB::table("item_boucher_pago", "ibp")
                ->selectRaw("Sum(ibp.descuento) as suma")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$empleado->idPeriodo)
                ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                ->where("ibp.fkConcepto","=","33") //33 - FPS
                ->get();
    
                $totalFPS = 0;
                foreach($itemsBoucherFPS as $itemBoucherFPS){
                    $totalFPS = $totalFPS + $itemBoucherFPS->suma;
                }
    
                if(!isset($arrayFila2[50])){
                    if($totalFPS > 0){
                        $valorSalario = $ultimoBoucher->ibc_afp;            
                        $variablesAporteFondo = DB::table("variable")->whereIn("idVariable",[11,12,13,14,15])->get();
                        $varAporteFondo = array();
                        foreach($variablesAporteFondo as $variablesAporteFond){
                            $varAporteFondo[$variablesAporteFond->idVariable] = $variablesAporteFond->valor;
                        }
                        
                        $variables = DB::table("variable")->where("idVariable","=","1")->first();
                        $valorSalarioMinimo = $variables->valor;
                    
                        if(intval($valorSalario) >= intval($valorSalarioMinimo * $varAporteFondo[11])){
                            $porcentajeDescuento = $varAporteFondo[12];
                        }
                        if(intval($valorSalario) >= intval($valorSalarioMinimo * $varAporteFondo[13])){
                            $diffSalariosMas = $valorSalario - ($valorSalarioMinimo * ($varAporteFondo[13]));
                            $number = round($diffSalariosMas  / $valorSalarioMinimo, 2);
                            $numSalariosMas = ceil($number);
                            $porcentajeDescuento = $porcentajeDescuento + ($numSalariosMas * $varAporteFondo[14]);
                        }
                        if($porcentajeDescuento >= $varAporteFondo[15]){
                            $porcentajeDescuento = $varAporteFondo[15];
                        }
        
                        $totalFPS = $ibcAFP*$porcentajeDescuento;
    
                        $paraFPS = ($totalFPS * 0.005)/$porcentajeDescuento;
                    
                        $paraFS = $totalFPS - $paraFPS;
                            
                        $paraFPS = $this->roundSup($paraFPS, -2);
                        
                        $paraFS = $this->roundSup($paraFS, -2);
    
                        $arrayFila2[50] = $this->plantillaTxt(intval($paraFPS),9,"0","right");
                        $arrayFila2[51] = $this->plantillaTxt(intval($paraFS),9,"0","right");
                    }
                    else{
                        $arrayFila2[50] = $this->plantillaTxt("",9,"0","right");
                        $arrayFila2[51] = $this->plantillaTxt("",9,"0","right");
                    }
                }


               



                $arrayFila2[52] = $this->plantillaTxt("",9,"0","right");
    
    

                $varsEPS = DB::table("variable", "v")->whereIn("v.idVariable",["49","50"])->get();
                $totalPorcentajeEPS = 0;
                foreach($varsEPS as $varEPS){
                    if($ibcOtros==0 && $varEPS->idVariable == "50" && $empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23"){
                        
                    }
                    else{
                        $totalPorcentajeEPS = $totalPorcentajeEPS + floatval($varEPS->valor);
                    }
                    
                }
                if(!isset($arrayFila2[53])){
                    $arrayFila2[53] =$this->plantillaTxt($totalPorcentajeEPS,7,"0","left");                       
                }
                
    
                $itemsBoucherESP = DB::table("item_boucher_pago", "ibp")
                ->selectRaw("Sum(ibp.descuento) as suma")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                ->where("ibp.fkConcepto","=","18") //19 - PENSION
                ->get();
                $cotizacionSalud = 0;
                foreach($itemsBoucherESP as $itemBoucherESP){
                    $cotizacionSalud = $cotizacionSalud + $itemBoucherESP->suma;
                }
                foreach($parafiscales as $parafiscal){
                    $cotizacionSalud = $cotizacionSalud + $parafiscal->suma_eps;
                }

                $cotizacionSalud = $ibcEPS*$totalPorcentajeEPS;

                $cotizacionSalud = $this->roundSup($cotizacionSalud, -2);

                
                if($totalPorcentajeEPS == 0.125){
                    if($cotizacionSalud < $minimosRedondeo->salud_12_5 && $cotizacionSalud > 0){
                        $cotizacionSalud = $minimosRedondeo->salud_12_5;
                    }
                }
                if($totalPorcentajeEPS == 0.12){
                    if($cotizacionSalud < $minimosRedondeo->salud_12 && $cotizacionSalud > 0){
                        $cotizacionSalud = $minimosRedondeo->salud_12;
                    }
                }
                if($totalPorcentajeEPS == 0.1){
                    if($cotizacionSalud < $minimosRedondeo->salud_10 && $cotizacionSalud > 0){
                        $cotizacionSalud = $minimosRedondeo->salud_10;
                    }
                }
                if($totalPorcentajeEPS == 0.08){
                    if($cotizacionSalud < $minimosRedondeo->salud_8 && $cotizacionSalud > 0){
                        $cotizacionSalud = $minimosRedondeo->salud_8;
                    }
                }
                if($totalPorcentajeEPS == 0.04){
                    if($cotizacionSalud < $minimosRedondeo->salud_4 && $cotizacionSalud > 0){
                        $cotizacionSalud = $minimosRedondeo->salud_4;
                    }
                }
    
                //$cotizacionSalud= round(($cotizacionSalud/30) * $periodoTrabajadoSinNov, -2);
                if(!isset($arrayFila2[54])){
                    $arrayFila2[54] = $this->plantillaTxt(round($cotizacionSalud),9,"0","right");
                }
                
    
                //Valor de la UPC adicional.
                $arrayFila2[55] =$this->plantillaTxt("",9,"0","right");
                
                $arrayFila2[56] =$this->plantillaTxt("",15," ","left");
                $arrayFila2[57] =$this->plantillaTxt("",9,"0","left");
                $arrayFila2[58] =$this->plantillaTxt("",15," ","left");
                $arrayFila2[59] =$this->plantillaTxt("",9,"0","left");
    
    
                //TARIFA RIESGOS
                
                //Parte 2
                if(!isset($arrayFila2[60])){

                    if($empleado->fkTipoCotizante != "12"){
                        $nivelesArl = DB::table("nivel_arl","na")
                        ->where("na.idnivel_arl","=",$empleado->fkNivelArl)
                        ->first();
                        $arrayFila2[60] = $this->plantillaTxt(($nivelesArl->porcentaje / 100),9,"0","left");
                    }
                    else{
                        $arrayFila2[60] =  $this->plantillaTxt("0.0",9,"0","left");
                    }
                }
                
                
                //Centro de Trabajo
                if(isset($empleado->fkCentroTrabajo)){
                    $centroTrabajo = DB::table("centrotrabajo","ct")
                    ->where("ct.idCentroTrabajo","=",$empleado->fkCentroTrabajo)
                    ->first();
        
                    $arrayFila2[61] = $this->plantillaTxt($centroTrabajo->codigo,9,"0","right");
                }
                else{
                    $arrayFila2[61] = $this->plantillaTxt("",9,"0","right");
                }
                
    
    
                $cotizacionArl = 0;
                foreach($parafiscales as $parafiscal){
                    $cotizacionArl = $cotizacionArl + $parafiscal->suma_arl;
                }
    
                if(isset($nivelesArl)){
                    $cotizacionArl = $ibcARL*($nivelesArl->porcentaje / 100);
                }
                
                //$cotizacionArl = round(($cotizacionArl/30) * $periodoTrabajadoSinNov, -2);
                $cotizacionArl = $ibcARL*($nivelesArl->porcentaje / 100);

                $cotizacionArl = $this->roundSup($cotizacionArl, -2);
                if($empleado->fkNivelArl == 1){
                    if($cotizacionArl < $minimosRedondeo->riesgos_1 && $cotizacionArl > 0){
                        $cotizacionArl = $minimosRedondeo->riesgos_1;
                    }
                }
                if($empleado->fkNivelArl == 2){
                    if($cotizacionArl < $minimosRedondeo->riesgos_2 && $cotizacionArl > 0){
                        $cotizacionArl = $minimosRedondeo->riesgos_2;
                    }
                }
                if($empleado->fkNivelArl == 3){
                    if($cotizacionArl < $minimosRedondeo->riesgos_3 && $cotizacionArl > 0){
                        $cotizacionArl = $minimosRedondeo->riesgos_3;
                    }
                }
                if($empleado->fkNivelArl == 4){
                    if($cotizacionArl < $minimosRedondeo->riesgos_4 && $cotizacionArl > 0){
                        $cotizacionArl = $minimosRedondeo->riesgos_4;
                    }
                }
                if($empleado->fkNivelArl == 5){
                    if($cotizacionArl < $minimosRedondeo->riesgos_5 && $cotizacionArl > 0){
                        $cotizacionArl = $minimosRedondeo->riesgos_5;
                    }
                }

                if($empleado->fkTipoCotizante == "12"){
                    $cotizacionArl = 0;
                }

                //Parte 2
                if(!isset($arrayFila2[62])){
                    $arrayFila2[62] = $this->plantillaTxt(round($cotizacionArl),9,"0","right");
                }    
                
    
    
                //TARIFA CCF
                if($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19"  && $empleado->fkTipoCotizante != "23"){
                    $varsCCF = DB::table("variable", "v")->whereIn("v.idVariable",["53"])->get();
                    $totalPorcentajeCCF = 0;
                    foreach($varsCCF as $varCCF){
                        $totalPorcentajeCCF = $totalPorcentajeCCF + floatval($varCCF->valor);
                    }
                }
                else{
                    $totalPorcentajeCCF = "0.0";
                }
            
                
                if(!isset($arrayFila2[63])){
                    $arrayFila2[63] = $this->plantillaTxt($totalPorcentajeCCF,7,"0","left");    
                }
                
    
                //VALOR CCF
                $ccfFinal = 0;
                foreach($parafiscales as $parafiscal){
                    $ccfFinal = $ccfFinal + $parafiscal->suma_ccf;
                }
                $ccfFinal = $ibcCCF*($totalPorcentajeCCF);
                //$ccfFinal = ($ccfFinal/30) * $periodoTrabajado;
                $ccfFinal = $this->roundSup($ccfFinal, -2);
                if($ccfFinal < $minimosRedondeo->ccf && $ccfFinal > 0){
                    $ccfFinal = $minimosRedondeo->ccf;
                }
    
              
                if(!isset($arrayFila2[64])){
                    
                    $arrayFila2[64] = $this->plantillaTxt($ccfFinal,9,"0","right");
                }
                
    
                if(!isset($arrayFila2[94])){
                    $ibcOtros = $ibcCCF;
                }
                else{
                    $ibcOtros = intval($arrayFila2[94]);
                }
                //dd($arrayFila2,$ibcOtros,$ibcCCF);
                if($ibcOtros<0){
                    $ibcOtros = 0;
                }
    
                //TARIFA SENA
                $varsSENA = DB::table("variable", "v")->whereIn("v.idVariable",["55"])->get();
                $totalPorcentajeSENA = 0;
                foreach($varsSENA as $varSENA){
                    $totalPorcentajeSENA = $totalPorcentajeSENA + floatval($varSENA->valor);
                }
                if($ibcOtros==0){
                    $totalPorcentajeSENA = "0.0";
                }
    
                if($empleado->fkTipoCotizante == "12" || $empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23"){
                    $totalPorcentajeSENA = "0.0";
                }

                $arrayFila2[65] = $this->plantillaTxt($totalPorcentajeSENA,7,"0","left");  
                
                


                //VALOR SENA
                $SENAFinal = 0;
                foreach($parafiscales as $parafiscal){
                    $SENAFinal = $SENAFinal + $parafiscal->suma_sena;
                }
                //$SENAFinal = ($SENAFinal/30) * $periodoTrabajadoSinNov;  
                $SENAFinal = $ibcOtros*($totalPorcentajeSENA);
                $SENAFinal = $this->roundSup($SENAFinal, -2);

                if($totalPorcentajeSENA == 0.005){
                    if($SENAFinal < $minimosRedondeo->sena_0_5 && $SENAFinal > 0){
                        $SENAFinal = $minimosRedondeo->sena_0_5;
                    }
                }
                if($totalPorcentajeSENA == 0.02){
                    if($SENAFinal < $minimosRedondeo->sena_2 && $SENAFinal > 0){
                        $SENAFinal = $minimosRedondeo->sena_2;
                    }
                }     
                $arrayFila2[66] = $this->plantillaTxt(intval($SENAFinal),9,"0","right");
    
                //TARIFA ICBF
                $varsICBF = DB::table("variable", "v")->whereIn("v.idVariable",["54"])->get();
                $totalPorcentajeICBF = 0;
                foreach($varsICBF as $varICBF){
                    $totalPorcentajeICBF = $totalPorcentajeICBF + floatval($varICBF->valor);
                }
                if($ibcOtros==0){
                    $totalPorcentajeICBF = "0.0";
                }
                if($empleado->fkTipoCotizante == "12" || $empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23"){
                    $totalPorcentajeICBF = "0.0";
                }

                $arrayFila2[67] = $this->plantillaTxt($totalPorcentajeICBF,7,"0","left");  
    
                //VALOR ICBF
                $ICBFFinal = 0;
                foreach($parafiscales as $parafiscal){
                    $ICBFFinal = $ICBFFinal + $parafiscal->suma_icbf;
                }

                //$ICBFFinal = ($ICBFFinal/30) * $periodoTrabajadoSinNov;        
                $ICBFFinal = $ibcOtros*($totalPorcentajeICBF);
                $ICBFFinal = $this->roundSup($ICBFFinal, -2);
                
                if($ICBFFinal < $minimosRedondeo->icbf && $ICBFFinal > 0 && $empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23"){
                    $ICBFFinal = $minimosRedondeo->icbf;
                }
                
                $arrayFila2[68] = $this->plantillaTxt(intval($ICBFFinal),9,"0","right");


    
                $arrayFila2[69] = $this->plantillaTxt("0.0",7,"0","left");  
                $arrayFila2[70] = $this->plantillaTxt("",9,"0","right");
    
                $arrayFila2[71] = $this->plantillaTxt("0.0",7,"0","left");  
                $arrayFila2[72] = $this->plantillaTxt("",9,"0","right");
    
    
                $arrayFila2[73] = $this->plantillaTxt("",2," ","right");
                $arrayFila2[74] = $this->plantillaTxt("",16," ","right");
                
                
                if($ibcEPS < ($minimosRedondeo->ibc * 10) && $empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23"){
                    $arrayFila2[75] = $this->plantillaTxt("S",1,"","left");
                }
                else{
                    $arrayFila2[75] = $this->plantillaTxt("N",1,"","left");
                }
                if($empleado->fkTipoCotizante != "12"){
                    $arrayFila2[76] = $this->plantillaTxt($empresa->codigoArl,6," ","left");
    
                    $arrayFila2[77] = $this->plantillaTxt($empleado->fkNivelArl,1,"","left");
                }
                else{
                    $arrayFila2[76] = $this->plantillaTxt("",6," ","left");
    
                    $arrayFila2[77] = $this->plantillaTxt("",1," ","left");
                }
            
    
                $arrayFila2[78] = $this->plantillaTxt("",1," ","left");
    
                
                if($ibcOtros!=0 && $empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23"){
            
                    
                    if($ibcOtros < $minimosRedondeo->ibc && $ibcOtros > 0){
                        $ibcOtros = $minimosRedondeo->ibc;
                    }
                    $arrayFila2[94] = $this->plantillaTxt(round($ibcOtros),9,"0","right");
                }
                else{
                    $arrayFila2[94] = $this->plantillaTxt("0",9,"0","right");
                }


                $arrayFila2[95] = $this->plantillaTxt("0",3,"0","right");
                $arrayFila2[96] = $this->plantillaTxt("",10," ","right");
                //ActividadEconomica
                if(isset($empleado->fkCentroTrabajo)){
                    $centroTrabajo = DB::table("centrotrabajo","ct")
                    ->select(["a.riesgo","a.ciiu","a.codigo"])
                    ->leftJoin("actividad_economica_decreto_768 as a", "a.id","=","ct.fkActividadEconomica768")
                    ->where("ct.idCentroTrabajo","=",$empleado->fkCentroTrabajo)
                    ->first();

                    $arrayFila2[97] = $this->plantillaTxt($centroTrabajo->riesgo.$centroTrabajo->ciiu.$centroTrabajo->codigo,7,"0","right");
                }
                else{
                    
                    $arrayFila2[97] = $this->plantillaTxt($empresa->riesgo.$empresa->ciiu.$empresa->codigo,7,"0","right");
                }

                $arrayFila2 = $this->upperCaseAllArray($arrayFila2);

                
                array_push($arrayMuestra, $arrayFila2);
                $contador++;


            }
            
            $upcAdicionales = DB::table("upcadicional","u")
            ->select("u.*","ti.siglaPila","ub.zonaUPC")
            ->join("tipoidentificacion as ti","ti.idtipoIdentificacion","=","u.fkTipoIdentificacion")
            ->join("ubicacion as ub", "ub.idubicacion", "=","u.fkUbicacion")
            ->where("u.fkEmpleado","=",$empleado->idempleado)
            ->get();
            
            foreach($upcAdicionales as $upcAdicional){
                $arrayFila = array();
                
                $arrayFila[0] = $this->plantillaTxt("02",2,"","right");
                $arrayFila[1] = $this->plantillaTxt($contador,5,"0","right");
                $arrayFila[2] = $this->plantillaTxt($upcAdicional->siglaPila,2," ","right");
                $arrayFila[3] = $this->plantillaTxt($upcAdicional->numIdentificacion,16," ","left");
                $arrayFila[4] = $this->plantillaTxt("40",2,"0","right");//Tipo cotizante
                $arrayFila[5] = $this->plantillaTxt($empleado->esPensionado,2,"0","right");//Subtipo de cotizante

                //Extranjero no obligado a cotizar a pensiones
                $arrayFila[6] = $this->plantillaTxt(" ",1,"","left");
                $arrayFila[7] = $this->plantillaTxt(" ",1," ","left");
            
                //Código del departamento de la ubicación laboral
                $arrayFila[8] = $this->plantillaTxt("0",2,"0","right");

                //Código del municipio de ubicación laboral
                $arrayFila[9] = $this->plantillaTxt("0",3,"0","right");
        

                $arrayFila[10] = $this->plantillaTxt($upcAdicional->primerApellido,20," ","left");
                $arrayFila[11] = $this->plantillaTxt($upcAdicional->segundoApellido,30," ","left");
                $arrayFila[12] = $this->plantillaTxt($upcAdicional->primerNombre,20," ","left");
                $arrayFila[13] = $this->plantillaTxt($upcAdicional->segundoNombre,30," ","left");

                $salud = DB::table("afiliacion","a")
                ->join("tercero as t", "t.idTercero", "=", "a.fkTercero")
                ->where("a.fkEmpleado", "=", $empleado->idempleado)
                ->where("a.fkTipoAfilicacion", "=", "3") // 3 - Tipo Afiliacion = Salud
                ->first();
                $arrayFila[14] = $this->plantillaTxt(" ",1,"","left");
                $arrayFila[15] = $this->plantillaTxt("",1," ","left");
                $arrayFila[16] = $this->plantillaTxt(" ",1," ","left");
                $arrayFila[17] = $this->plantillaTxt(" ",1," ","left");
                $arrayFila[18] = $this->plantillaTxt(" ",1," ","left");
                $arrayFila[19] = $this->plantillaTxt(" ",1," ","left");
                $arrayFila[20] = $this->plantillaTxt(" ",1," ","left");
                $arrayFila[21] = $this->plantillaTxt(" ",1," ","left");	
                $arrayFila[22] = $this->plantillaTxt(" ",1," ","left");
                $arrayFila[23] = $this->plantillaTxt(" ",1," ","left");
                $arrayFila[24] = $this->plantillaTxt(" ",1," ","left");
                $arrayFila[25] = $this->plantillaTxt(" ",1," ","left");
                $arrayFila[26] = $this->plantillaTxt(" ",1," ","left");
                $arrayFila[27] = $this->plantillaTxt(" ",1," ","left");
                $arrayFila[28] = $this->plantillaTxt(" ",1," ","left");
                $arrayFila[29] = $this->plantillaTxt("",2,"0","right");
                $arrayFila[30] = $this->plantillaTxt("",6," ","left");
                $arrayFila[31] = $this->plantillaTxt(" ",6," ","left");
                $arrayFila[32] = $this->plantillaTxt($salud->codigoTercero,6," ","left");
                $arrayFila[33] = $this->plantillaTxt(" ",6," ","left");
                $arrayFila[34] = $this->plantillaTxt(" ",6," ","left");
                $arrayFila[35] = $this->plantillaTxt("0",2,"0","right");
                $arrayFila[36] = $this->plantillaTxt("30",2,"0","right");
                $arrayFila[37] = $this->plantillaTxt("0",2,"0","right");
                $arrayFila[38] = $this->plantillaTxt("0",2,"0","right");
                $arrayFila[39] = $this->plantillaTxt("0",9,"0","right");
                $arrayFila[40] = $this->plantillaTxt("",1," ","left");
                $arrayFila[41] = $this->plantillaTxt("0",9,"0","right");
                $arrayFila[42] = $this->plantillaTxt("0",9,"0","right");
                $arrayFila[43] = $this->plantillaTxt("0",9,"0","right");
                $arrayFila[44] = $this->plantillaTxt("0",9,"0","right");
                $arrayFila[45] = $this->plantillaTxt("0.0",7,"0","left");
                $arrayFila[46] = $this->plantillaTxt("",9,"0","right");
                $arrayFila[47] = $this->plantillaTxt("",9,"0","right");
                $arrayFila[48] = $this->plantillaTxt("",9,"0","right");
                $arrayFila[49] = $this->plantillaTxt("0",9,"0","right");
                $arrayFila[50] = $this->plantillaTxt("",9,"0","right");
                $arrayFila[51] = $this->plantillaTxt("",9,"0","right");
                $arrayFila[52] = $this->plantillaTxt("",9,"0","right");
                $arrayFila[53] =$this->plantillaTxt("0.0",7,"0","left");   
                $arrayFila[54] =$this->plantillaTxt("0",9,"0","right");


                $edad = strtotime("now") - strtotime($upcAdicional->fechaNacimiento);
                $edad = $edad / (60* 60 * 24 * 360);
                $edad = intval($edad);


                $tarifasUpc = DB::table("upcadicionaltarifas", "ut")
                ->join("upcadicionaledades as ue", "ue.idUpcAdicionalTabla", "=","ut.fkUpcEdad");
                if($edad == 0){
                    $tarifasUpc = $tarifasUpc->where("ut.fkUpcEdad", "=", "1");
                }
                else if($edad >= 75){
                    $tarifasUpc = $tarifasUpc->where("ut.fkUpcEdad", "=", "14");
                }
                else{
                    $tarifasUpc = $tarifasUpc->where("ue.edadMinima", "<=", $edad);
                    $tarifasUpc = $tarifasUpc->where("ue.edadMaxima", ">=", $edad);
                }
                $tarifasUpc = $tarifasUpc->where("ut.zona", "=", $upcAdicional->zonaUPC)
                ->get();

                
                $tarifa = 0;
                foreach($tarifasUpc as $tarifaUpc){
                    if(!isset($tarifaUpc->fkGenero) || $tarifaUpc->fkGenero == $upcAdicional->fkGenero){
                        $tarifa = $tarifaUpc->valor;
                    }
                }
                $arrayFila[55] = $this->plantillaTxt($tarifa,9,"0","right");

                $arrayFila[56] =$this->plantillaTxt("",15," ","left");
                $arrayFila[57] =$this->plantillaTxt("",9,"0","left");
                $arrayFila[58] =$this->plantillaTxt("",15," ","left");
                $arrayFila[59] =$this->plantillaTxt("",9,"0","left");

                $arrayFila[60] = $this->plantillaTxt("0.0",9,"0","left");
                $arrayFila[61] = $this->plantillaTxt("0",9,"0","right");
                $arrayFila[62] = $this->plantillaTxt("0",9,"0","right");
                $arrayFila[63] = $this->plantillaTxt("0.0",7,"0","left");    
                $arrayFila[64] = $this->plantillaTxt("0",9,"0","right");
                $arrayFila[65] = $this->plantillaTxt("0.0",7,"0","left");  
                $arrayFila[66] = $this->plantillaTxt("0",9,"0","right");
                $arrayFila[67] = $this->plantillaTxt("0.0",7,"0","left");  
                $arrayFila[68] = $this->plantillaTxt("0",9,"0","right");

                $arrayFila[69] = $this->plantillaTxt("0.0",7,"0","left");
                $arrayFila[70] = $this->plantillaTxt("",9,"0","right");
                $arrayFila[71] = $this->plantillaTxt("0.0",7,"0","left");  
                $arrayFila[72] = $this->plantillaTxt("",9,"0","right");

                $arrayFila[73] = $this->plantillaTxt($empleado->siglaPila,2," ","right");
                $arrayFila[74] = $this->plantillaTxt($empleado->numeroIdentificacion,16," ","left");
                $arrayFila[75] = $this->plantillaTxt("N",1,"","left");
                $arrayFila[76] = $this->plantillaTxt("",6," ","left");
                $arrayFila[77] = $this->plantillaTxt(" ",1,"","left");
                $arrayFila[78] = $this->plantillaTxt("",1," ","left");


                $arrayFila[79] = $this->plantillaTxt("",10," ","left");
                $arrayFila[80] = $this->plantillaTxt("",10," ","left");
                $arrayFila[81] = $this->plantillaTxt("",10," ","left");
                $arrayFila[82] = $this->plantillaTxt("",10," ","left");
                $arrayFila[83] = $this->plantillaTxt("",10," ","left");
                $arrayFila[84] = $this->plantillaTxt("",10," ","left");
                $arrayFila[85] = $this->plantillaTxt("",10," ","left");
                $arrayFila[86] = $this->plantillaTxt("",10," ","left");
                $arrayFila[87] = $this->plantillaTxt("",10," ","left");
                $arrayFila[88] = $this->plantillaTxt("",10," ","left");
                $arrayFila[89] = $this->plantillaTxt("",10," ","left");
                $arrayFila[90] = $this->plantillaTxt("",10," ","left");
                $arrayFila[91] = $this->plantillaTxt("",10," ","left");
                $arrayFila[92] = $this->plantillaTxt("",10," ","left");
                $arrayFila[93] = $this->plantillaTxt("",10," ","left");
                
                $arrayFila[94] = $this->plantillaTxt("0",9,"0","right");
                $arrayFila[95] = $this->plantillaTxt("0",3,"0","right");
                $arrayFila[96] = $this->plantillaTxt("",10," ","right");
                $arrayFila[97] = $this->plantillaTxt("0",7,"0","right");
                $arrayFila = $this->upperCaseAllArray($arrayFila);
                array_push($arrayMuestra, $arrayFila);
                $contador++;
            }
            

            



        }
        

        //$arrayMuestra[0][18] = $this->plantillaTxt($contador,5,"0","right");//Número total de cotizantes
        $arrayMuestra[0][19] = $this->plantillaTxt(round($totalNomina),12,"0","right");//Valor total nomina
        $arrayMuestra[0][18] = $this->plantillaTxt($numeroEmpleados,5,"0","right");//Número total de cotizantes
        return $arrayMuestra;
    }



    public function seleccionarDocumentoProvisiones(){
        $dataUsu = UsuarioController::dataAdminLogueado();
        $empresas = DB::table("empresa", "e");
        if(isset($dataUsu) && $dataUsu->fkRol == 2){            
            $empresas = $empresas->whereIn("idempresa", $dataUsu->empresaUsuario);
        }
        $empresas = $empresas->orderBy("razonSocial")->get();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu 'Reporte provisiones'");

        return view('/reportes.seleccionarDocumentoProvisiones',[
            'empresas' => $empresas,
            "dataUsu" => $dataUsu            
        ]);
    }

    public function documentoProv(Request $req){

        
        $nombreDoc = date("M",strtotime($req->fechaDocumento))."-". date("Y",strtotime($req->fechaDocumento));
        $mesFechaDocumento = date("m",strtotime($req->fechaDocumento));
        $anioFechaDocumento = date("Y",strtotime($req->fechaDocumento));

        $datosProv = DB::table('provision','p')
        ->select("dp.numeroIdentificacion","dp.primerNombre","dp.segundoNombre","dp.primerApellido","dp.segundoApellido","p.*")
        ->join("empleado as e", "e.idempleado", "=", "p.fkEmpleado","left")
        ->leftJoin('periodo', function ($join) {
            $join->on('periodo.fkEmpleado', '=', 'e.idempleado')
                ->on('periodo.idPeriodo', '=', 'p.fkPeriodoActivo');
        })
        ->join("nomina as n", "n.idNomina", "=", "periodo.fkNomina","left")
        ->join("datospersonales as dp", "dp.idDatosPersonales", "=", "e.fkDatosPersonales")
        ->where("p.anio","=",$anioFechaDocumento)
        ->where("p.mes","<=",$mesFechaDocumento);
       
        if($req->provision!="Consolidado"){
            $datosProv = $datosProv->where("p.fkConcepto","=",$req->provision);
        }
        $datosProv = $datosProv->where("n.fkEmpresa","=",$req->empresa)
        ->orderBy("p.fkEmpleado")
        ->orderBy("p.fkPeriodoActivo")
        ->get();
        
        $arrMeses = ["ENERO","FEBRERO","MARZO","ABRIL","MAYO","JUNIO","JULIO", "AGOSTO", "SEPTIEMBRE", "OCTUBRE", "NOVIEMBRE", "DICIEMBRE"];


    

        $arrDatos=array();
        $arrTitulo = array(
            "Identificacion",
            "Primer Apellido",
            "Segundo Apellido",
            "Primer Nombre",
            "Segundo Nombre"
        );
        if($req->provision!="Consolidado"){
            array_push($arrTitulo, "SALDO");
            for($i=0; $i<$mesFechaDocumento; $i++){
                array_push($arrTitulo, "PROVISION ".$arrMeses[$i]);
                array_push($arrTitulo, "PAGO ".$arrMeses[$i]);
            }
        }
        else{
            array_push($arrTitulo, "PRIMA SALDO");
            array_push($arrTitulo, "CESANTIAS SALDO");
            array_push($arrTitulo, "INT. CESANTIAS SALDO");
            array_push($arrTitulo, "VACACIONES SALDO");
            for($i=0; $i<$mesFechaDocumento; $i++){
                array_push($arrTitulo, "PRIMA PROVISION ".$arrMeses[$i]);
                array_push($arrTitulo, "PRIMA PAGO ".$arrMeses[$i]);
                array_push($arrTitulo, "CESANTIAS PROVISION ".$arrMeses[$i]);
                array_push($arrTitulo, "CESANTIAS PAGO ".$arrMeses[$i]);
                array_push($arrTitulo, "INT. CESANTIAS PROVISION ".$arrMeses[$i]);
                array_push($arrTitulo, "INT. CESANTIAS PAGO ".$arrMeses[$i]);
                array_push($arrTitulo, "VACACIONES PROVISION ".$arrMeses[$i]);
                array_push($arrTitulo, "VACACIONES PAGO ".$arrMeses[$i]);
            }
        }
        array_push($arrDatos, $arrTitulo);

        $arrInt = array();
        if($req->provision!="Consolidado"){
            for($j = 0; $j <= 28; $j++){
                if(!isset($arrInt[$j])){
                    $arrInt[$j] = " ";
                }
            }
        }else{
            for($j = 0; $j <= 103; $j++){
                if(!isset($arrInt[$j])){
                    $arrInt[$j] = " ";
                }
            }
        }
        $idPeriodo = 0;
        $row = 0;




        foreach($datosProv as $datoProv){
            if($idPeriodo != $datoProv->fkPeriodoActivo){
                
                if($idPeriodo != 0){                
                    array_push($arrDatos, $arrInt);
                    $arrInt = array();
                    if($req->provision!="Consolidado"){
                        for($j = 0; $j <= 28; $j++){
                            if(!isset($arrInt[$j])){
                                $arrInt[$j] = " ";
                            }
                        }
                    }else{
                        for($j = 0; $j <= 103; $j++){
                            if(!isset($arrInt[$j])){
                                $arrInt[$j] = " ";
                            }
                        }
                    }
                }

                $arrInt[0]= $datoProv->numeroIdentificacion;
                $arrInt[1]= $datoProv->primerApellido;
                $arrInt[2]= $datoProv->segundoApellido;
                $arrInt[3]= $datoProv->primerNombre;
                $arrInt[4]= $datoProv->segundoNombre;
            
                $idPeriodo = $datoProv->fkPeriodoActivo;


                
                if($req->provision!="Consolidado"){


                    $arrBusqueda = array();
                    if($datoProv->fkConcepto=="74"){
                        $arrBusqueda = [74];
                    }
                    if($datoProv->fkConcepto=="73"){
                        $arrBusqueda = [73];
                    }
                    if($datoProv->fkConcepto=="71"){
                        $arrBusqueda = [67,71];
                    }
                    if($datoProv->fkConcepto=="72"){
                        $arrBusqueda = [68,72];
                    }
                

                    $periodoActivoReintegro = DB::table("periodo")
                    ->where("idPeriodo", "=", $datoProv->fkPeriodoActivo)->first();
                                  
                    
                    if(isset($periodoActivoReintegro)){
                        $saldo = DB::table("saldo","s")
                        ->selectRaw("sum(s.valor) as suma")
                        ->where("s.fkEmpleado","=",$datoProv->fkEmpleado)
                        ->where("s.fkPeriodoActivo","=",$datoProv->fkPeriodoActivo)
                        ->where("s.anioAnterior","=",($anioFechaDocumento))
                        ->whereIn("s.fkConcepto",$arrBusqueda)
                        ->whereRaw("CAST(CONCAT(s.anioAnterior,'-',s.mesAnterior,'-01') as Date)>='".$periodoActivoReintegro->fechaInicio."'")
                        ->first();


                        if(isset($saldo)){
                            $arrInt[5]= $saldo->suma;
                        }
                    }

                }else{
                
                    
                    $arrBusquedaVac = [74];
                    $arrBusquedaPrima = [73];
                    $arrBusquedaCes = [67,71];            
                    $arrBusquedaIntCes = [68,72];
                                   
                    $periodoActivoReintegro = DB::table("periodo")
                    ->where("idPeriodo", "=", $datoProv->fkPeriodoActivo)->first();
                    

                    if(isset($periodoActivoReintegro)){
                        $saldoPrima = DB::table("saldo","s")
                        ->selectRaw("sum(s.valor) as suma")
                        ->where("s.fkEmpleado","=",$datoProv->fkEmpleado)
                        ->where("s.fkPeriodoActivo","=",$datoProv->fkPeriodoActivo)
                        ->where("s.anioAnterior","=",($anioFechaDocumento))
                        ->whereRaw("CAST(CONCAT(s.anioAnterior,'-',s.mesAnterior,'-01') as Date)>='".$periodoActivoReintegro->fechaInicio."'")
                        ->whereIn("s.fkConcepto",$arrBusquedaPrima)
                        ->first();
                        if(isset($saldoPrima)){
                            $arrInt[5]= $saldoPrima->suma;
                        }
    
                        $saldoCes = DB::table("saldo","s")
                        ->selectRaw("sum(s.valor) as suma")
                        ->where("s.fkEmpleado","=",$datoProv->fkEmpleado)
                        ->where("s.fkPeriodoActivo","=",$datoProv->fkPeriodoActivo)
                        ->where("s.anioAnterior","=",($anioFechaDocumento))
                        ->whereIn("s.fkConcepto",$arrBusquedaCes)
                        ->whereRaw("CAST(CONCAT(s.anioAnterior,'-',s.mesAnterior,'-01') as Date)>='".$periodoActivoReintegro->fechaInicio."'")
                        ->first();
                        if(isset($saldoCes)){
                            $arrInt[6]= $saldoCes->suma;
                        }
    
                        $saldoIntCes = DB::table("saldo","s")
                        ->selectRaw("sum(s.valor) as suma")
                        ->where("s.fkEmpleado","=",$datoProv->fkEmpleado)
                        ->where("s.fkPeriodoActivo","=",$datoProv->fkPeriodoActivo)
                        ->where("s.anioAnterior","=",($anioFechaDocumento))
                        ->whereIn("s.fkConcepto",$arrBusquedaIntCes)
                        ->whereRaw("CAST(CONCAT(s.anioAnterior,'-',s.mesAnterior,'-01') as Date)>='".$periodoActivoReintegro->fechaInicio."'")
                        ->first();
                        if(isset($saldoIntCes)){
                            $arrInt[7]= $saldoIntCes->suma;
                        }
    
                        $saldoVac = DB::table("saldo","s")
                        ->selectRaw("sum(s.valor) as suma")
                        ->where("s.fkEmpleado","=",$datoProv->fkEmpleado)
                        ->where("s.fkPeriodoActivo","=",$datoProv->fkPeriodoActivo)
                        ->where("s.anioAnterior","=",($anioFechaDocumento))
                        ->whereIn("s.fkConcepto",$arrBusquedaVac)
                        ->whereRaw("CAST(CONCAT(s.anioAnterior,'-',s.mesAnterior,'-01') as Date)>='".$periodoActivoReintegro->fechaInicio."'")
                        ->first();
                        if(isset($saldoVac)){
                            $arrInt[8]= $saldoVac->suma;
                        }
                    }
                    
                }
            
            }
          
            if($req->provision!="Consolidado"){
                if($datoProv->mes==1){
                    $row = 5;
                }
                if($datoProv->mes==2){
                    $row = 7;
                }
                if($datoProv->mes==3){
                    $row = 9;
                }
                if($datoProv->mes==4){
                    $row = 11;
                }
                if($datoProv->mes==5){
                    $row = 13;
                }
                if($datoProv->mes==6){
                    $row = 15;
                }
                if($datoProv->mes==7){
                    $row = 17;
                }
                if($datoProv->mes==8){
                    $row = 19;
                }
                if($datoProv->mes==9){
                    $row = 21;
                }
                if($datoProv->mes==10){
                    $row = 23;
                }
                if($datoProv->mes==11){
                    $row = 25;
                }
                if($datoProv->mes==12){
                    $row = 27;
                }
                $row++;
            }
            else{

                if($datoProv->mes==1 && $datoProv->fkConcepto=="73"){//Prima
                    $row = 5;
                }
                if($datoProv->mes==1 && $datoProv->fkConcepto=="71"){//CES
                    $row = 7;
                }
                if($datoProv->mes==1 && $datoProv->fkConcepto=="72"){//Int. CES
                    $row = 9;
                }
                if($datoProv->mes==1 && $datoProv->fkConcepto=="74"){//Vac
                    $row = 11;
                }


                if($datoProv->mes==2 && $datoProv->fkConcepto=="73"){//Prima
                    $row = 13;
                }
                if($datoProv->mes==2 && $datoProv->fkConcepto=="71"){//CES
                    $row = 15;
                }
                if($datoProv->mes==2 && $datoProv->fkConcepto=="72"){//Int. CES
                    $row = 17;
                }
                if($datoProv->mes==2 && $datoProv->fkConcepto=="74"){//Vac
                    $row = 19;
                }


                if($datoProv->mes==3 && $datoProv->fkConcepto=="73"){//Prima
                    $row = 21;
                }
                if($datoProv->mes==3 && $datoProv->fkConcepto=="71"){//CES
                    $row = 23;
                }
                if($datoProv->mes==3 && $datoProv->fkConcepto=="72"){//Int. CES
                    $row = 25;
                }
                if($datoProv->mes==3 && $datoProv->fkConcepto=="74"){//Vac
                    $row = 27;
                }


                if($datoProv->mes==4 && $datoProv->fkConcepto=="73"){//Prima
                    $row = 29;
                }
                if($datoProv->mes==4 && $datoProv->fkConcepto=="71"){//CES
                    $row = 31;
                }
                if($datoProv->mes==4 && $datoProv->fkConcepto=="72"){//Int. CES
                    $row = 33;
                }
                if($datoProv->mes==4 && $datoProv->fkConcepto=="74"){//Vac
                    $row = 35;
                }

                if($datoProv->mes==5 && $datoProv->fkConcepto=="73"){//Prima
                    $row = 37;
                }
                if($datoProv->mes==5 && $datoProv->fkConcepto=="71"){//CES
                    $row = 39;
                }
                if($datoProv->mes==5 && $datoProv->fkConcepto=="72"){//Int. CES
                    $row = 41;
                }
                if($datoProv->mes==5 && $datoProv->fkConcepto=="74"){//Vac
                    $row = 43;
                }
            

                if($datoProv->mes==6 && $datoProv->fkConcepto=="73"){//Prima
                    $row = 45;
                }
                if($datoProv->mes==6 && $datoProv->fkConcepto=="71"){//CES
                    $row = 47;
                }
                if($datoProv->mes==6 && $datoProv->fkConcepto=="72"){//Int. CES
                    $row = 49;
                }
                if($datoProv->mes==6 && $datoProv->fkConcepto=="74"){//Vac
                    $row = 51;
                }


                if($datoProv->mes==7 && $datoProv->fkConcepto=="73"){//Prima
                    $row = 53;
                }
                if($datoProv->mes==7 && $datoProv->fkConcepto=="71"){//CES
                    $row = 55;
                }
                if($datoProv->mes==7 && $datoProv->fkConcepto=="72"){//Int. CES
                    $row = 57;
                }
                if($datoProv->mes==7 && $datoProv->fkConcepto=="74"){//Vac
                    $row = 59;
                }

                if($datoProv->mes==8 && $datoProv->fkConcepto=="73"){//Prima
                    $row = 61;
                }
                if($datoProv->mes==8 && $datoProv->fkConcepto=="71"){//CES
                    $row = 63;
                }
                if($datoProv->mes==8 && $datoProv->fkConcepto=="72"){//Int. CES
                    $row = 65;
                }
                if($datoProv->mes==8 && $datoProv->fkConcepto=="74"){//Vac
                    $row = 67;
                }

                if($datoProv->mes==9 && $datoProv->fkConcepto=="73"){//Prima
                    $row = 69;
                }
                if($datoProv->mes==9 && $datoProv->fkConcepto=="71"){//CES
                    $row = 71;
                }
                if($datoProv->mes==9 && $datoProv->fkConcepto=="72"){//Int. CES
                    $row = 73;
                }
                if($datoProv->mes==9 && $datoProv->fkConcepto=="74"){//Vac
                    $row = 75;
                }

                if($datoProv->mes==10 && $datoProv->fkConcepto=="73"){//Prima
                    $row = 77;
                }
                if($datoProv->mes==10 && $datoProv->fkConcepto=="71"){//CES
                    $row = 79;
                }
                if($datoProv->mes==10 && $datoProv->fkConcepto=="72"){//Int. CES
                    $row = 81;
                }
                if($datoProv->mes==10 && $datoProv->fkConcepto=="74"){//Vac
                    $row = 83;
                }

                if($datoProv->mes==11 && $datoProv->fkConcepto=="73"){//Prima
                    $row = 85;
                }
                if($datoProv->mes==11 && $datoProv->fkConcepto=="71"){//CES
                    $row = 87;
                }
                if($datoProv->mes==11 && $datoProv->fkConcepto=="72"){//Int. CES
                    $row = 89;
                }
                if($datoProv->mes==11 && $datoProv->fkConcepto=="74"){//Vac
                    $row = 91;
                }

                if($datoProv->mes==12 && $datoProv->fkConcepto=="73"){//Prima
                    $row = 93;
                }
                if($datoProv->mes==12 && $datoProv->fkConcepto=="71"){//CES
                    $row = 95;
                }
                if($datoProv->mes==12 && $datoProv->fkConcepto=="72"){//Int. CES
                    $row = 97;
                }
                if($datoProv->mes==12 && $datoProv->fkConcepto=="74"){//Vac
                    $row = 99;
                }
                $row =  $row + 4;
            }
            
            /*$datosProvResta = DB::table('provision','p')
                ->selectRaw("sum(p.valor) as suma")
                ->where("p.anio","=",$datoProv->anio)
                ->where("p.mes","<",$datoProv->mes)
                ->where("p.fkEmpleado","=",$datoProv->fkEmpleado)
                ->where("p.fkConcepto","=",$datoProv->fkConcepto)
                ->first();
                
            if(isset($datosProvResta)){
                $arrInt[$row] = $datoProv->valor - $datosProvResta->suma;    
            }
            else{*/
                $arrInt[$row] = $datoProv->valor;    
            /*}*/

           
            
            
            $pago = 0;
            if($datoProv->fkConcepto=="73"){
                
                $itemsBoucherPrima = DB::table("item_boucher_pago", "ibp")
                ->selectRaw("Sum(ibp.pago) as suma")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$datoProv->fkEmpleado)
                ->where("bp.fkPeriodoActivo","=",$datoProv->fkPeriodoActivo)
                ->whereRaw("MONTH(ln.fechaFin) = '".$datoProv->mes."' and YEAR(ln.fechaLiquida) = '".$datoProv->anio."'")
                ->where("ibp.fkConcepto","=","58") //58 - PRIMA DE SERVICIOS	
                ->first();
                if(isset($itemsBoucherPrima)){
                    $pago = $itemsBoucherPrima->suma;
                }
            }

            if($datoProv->fkConcepto=="71"){
                $itemsBoucherCes = DB::table("item_boucher_pago", "ibp")
                ->selectRaw("Sum(ibp.pago) as suma")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$datoProv->fkEmpleado)
                ->where("bp.fkPeriodoActivo","=",$datoProv->fkPeriodoActivo)
                ->whereRaw("MONTH(ln.fechaLiquida) = '".$datoProv->mes."' and YEAR(ln.fechaLiquida) = '".$datoProv->anio."'")
                ->whereIn("ibp.fkConcepto",["66","67"]) //66 - CES . 67 - CES AÑO ANTERIOR
                ->first();
                if(isset($itemsBoucherCes)){
                    $pago = $itemsBoucherCes->suma;
                }

                $itemsBoucherFueraNomCes = DB::table("item_boucher_pago_fuera_nomina", "ibp")
                ->selectRaw("Sum(ibp.valor) as suma")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$datoProv->fkEmpleado)
                ->where("bp.fkPeriodoActivo","=",$datoProv->fkPeriodoActivo)
                ->whereRaw("MONTH(ln.fechaLiquida) = '".$datoProv->mes."' and YEAR(ln.fechaLiquida) = '".$datoProv->anio."'")
                ->whereIn("ibp.fkConcepto",["84"]) // 84 - CESANTIAS TRASLADO
                ->first();
                
                
                if(isset($itemsBoucherFueraNomCes)){
                    $pago = $pago + $itemsBoucherFueraNomCes->suma;
                }
                
                    
            }
            if($datoProv->fkConcepto=="72"){
                $itemsBoucherIntCes = DB::table("item_boucher_pago", "ibp")
                ->selectRaw("Sum(ibp.pago) as suma")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$datoProv->fkEmpleado)
                ->where("bp.fkPeriodoActivo","=",$datoProv->fkPeriodoActivo)
                ->whereRaw("MONTH(ln.fechaFin) = '".$datoProv->mes."' and YEAR(ln.fechaLiquida) = '".$datoProv->anio."'")
                ->whereIn("ibp.fkConcepto",["69","68"]) //69 - INT . 68 - INT AÑO ANTERIOR
                ->first();
                if(isset($itemsBoucherIntCes)){
                    $pago = $itemsBoucherIntCes->suma;
                }
            }
            if($datoProv->fkConcepto=="74"){

                $fechaInicioMesActual = date("Y-m-01",strtotime($datoProv->anio."-".$datoProv->mes."-01"));
                $fechaFinMesActual = date("Y-m-t",strtotime($datoProv->anio."-".$datoProv->mes."-01"));
                $pago = 0;
                $sqlWhere = "( 
                    ('".$fechaInicioMesActual."' BETWEEN v.fechaInicio AND v.fechaFin) OR
                    ('".$fechaFinMesActual."' BETWEEN v.fechaInicio AND v.fechaFin) OR
                    (v.fechaInicio BETWEEN '".$fechaInicioMesActual."' AND '".$fechaFinMesActual."') OR
                    (v.fechaFin BETWEEN '".$fechaInicioMesActual."' AND '".$fechaFinMesActual."')
                )";



                $itemsBoucherVac30 = DB::table("item_boucher_pago", "ibp")
                ->selectRaw("Sum(ibp.pago) as suma")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$datoProv->fkEmpleado)
                ->where("bp.fkPeriodoActivo","=",$datoProv->fkPeriodoActivo)
                ->whereRaw("MONTH(ln.fechaFin) = '".$datoProv->mes."' and YEAR(ln.fechaLiquida) = '".$datoProv->anio."' ")
                ->where("ibp.fkConcepto","=","30") //30 - RETIRO
                ->first();

                if(isset($itemsBoucherVac30)){
                    $pago = $pago + $itemsBoucherVac30->suma;                    
                    
                }
                
                

                $itemsBoucherVac28 = DB::table("item_boucher_pago", "ibp")
                ->selectRaw("Sum(ibp.pago) as suma")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$datoProv->fkEmpleado)
                ->where("bp.fkPeriodoActivo","=",$datoProv->fkPeriodoActivo)
                ->whereRaw("MONTH(ln.fechaFin) = '".$datoProv->mes."' and YEAR(ln.fechaLiquida) = '".$datoProv->anio."' ")
                ->where("ibp.fkConcepto","=","28") //28 - compensadas
                ->first();
                if(isset($itemsBoucherVac28)){
                    $pago = $pago + $itemsBoucherVac28->suma;
                }
                
                $itemsBoucherVac29 = DB::table("item_boucher_pago", "ibp")
                ->selectRaw("Sum(ibp.pago) as suma")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$datoProv->fkEmpleado)
                ->where("bp.fkPeriodoActivo","=",$datoProv->fkPeriodoActivo)
                ->whereRaw("MONTH(ln.fechaFin) = '".$datoProv->mes."' and YEAR(ln.fechaLiquida) = '".$datoProv->anio."' ")
                ->where("ibp.fkConcepto","=","29") //28 - compensadas
                ->first();
                if(isset($itemsBoucherVac29)){
                    $pago = $pago + $itemsBoucherVac29->suma;
                }

                /*$novedadesVac = DB::table("novedad","n")
                ->join("vacaciones as v","v.idVacaciones","=", "n.fkVacaciones")
                ->where("n.fkEmpleado","=", $datoProv->fkEmpleado)
                ->whereRaw("n.fkPeriodoActivo in(
                    SELECT p.idPeriodo from periodo as p where p.fkEmpleado = '".$datoProv->fkEmpleado."' and p.fkEstado = '1'
                )")
                ->whereIn("n.fkEstado",["8","16"]) // Pagada-> no que este eliminada o parcialmente paga (para las de pago parcial)
                ->whereNotNull("n.fkVacaciones")
                ->where("n.fkConcepto","=", "29")
                ->whereRaw($sqlWhere)
                ->get();
                
                $fechaFin = $fechaFinMesActual;
                foreach($novedadesVac as $novedadVac){
                    if(strtotime($novedadVac->fechaInicio)>=strtotime($fechaInicioMesActual)
                        &&  strtotime($novedadVac->fechaInicio)<=strtotime($fechaFin) 
                        &&  strtotime($novedadVac->fechaFin)>=strtotime($fechaFin))
                    {
                        $diaI = $novedadVac->fechaInicio;
                        $diaF = $fechaFin;
                        $diasCompensar = $this->days_360($novedadVac->fechaInicio, $fechaFin) + 1;
                        if(substr($novedadVac->fechaInicio, 8, 2) == "31" && substr($fechaFin, 8, 2) == "31"){
                            $diasCompensar--;
                        }
                        $diasPagoVac = $diasCompensar;
                        if(substr($fechaFin, 8, 2) == "31"){
                            $diasCompensar--;   
                        }
                        
                    
                    }
                    else if(strtotime($novedadVac->fechaFin)>=strtotime($fechaInicioMesActual)  
                    &&  strtotime($novedadVac->fechaFin)<=strtotime($fechaFin) 
                    &&  strtotime($novedadVac->fechaInicio)<=strtotime($fechaInicioMesActual))
                    {
                        $diaI = $fechaInicioMesActual;
                        $diaF = $novedadVac->fechaFin;

                        $diasCompensar = $this->days_360($fechaInicioMesActual, $novedadVac->fechaFin) + 1;
                        if(substr($fechaInicioMesActual, 8, 2) == "31" && substr($novedadVac->fechaFin, 8, 2) == "31"){
                            $diasCompensar--;   
                        }
                        $diasPagoVac = $diasCompensar;
                        if(substr($novedadVac->fechaFin, 8, 2) == "31"){
                            $diasCompensar--;   
                        }
                    }
                    else if(strtotime($novedadVac->fechaInicio)<=strtotime($fechaInicioMesActual)  
                    &&  strtotime($novedadVac->fechaFin)>=strtotime($fechaFin)) 
                    {
                        $diaI = $fechaInicioMesActual;
                        $diaF = $fechaFin;
                        $diasCompensar = $this->days_360($fechaInicioMesActual, $fechaFin) + 1;
                        if(substr($fechaInicioMesActual, 8, 2) == "31" && substr($fechaFin, 8, 2) == "31"){
                            $diasCompensar--;   
                        }
                        $diasPagoVac = $diasCompensar;
                        if(substr($fechaFin, 8, 2) == "31"){
                            $diasCompensar--;   
                        }
                    }
                    else if(strtotime($fechaInicioMesActual)<=strtotime($novedadVac->fechaInicio)  
                    &&  strtotime($fechaFin)>=strtotime($novedadVac->fechaFin)) 
                    {
                        $diaI = $novedadVac->fechaInicio;
                        $diaF = $novedadVac->fechaFin;
                        $diasCompensar = $this->days_360($novedadVac->fechaInicio, $novedadVac->fechaFin) + 1;

                        if(substr($novedadVac->fechaInicio, 8, 2) == "31" && substr($novedadVac->fechaFin, 8, 2) == "31"){
                            $diasCompensar--;   
                        }
                        $diasPagoVac = $diasCompensar;
                        if(substr($novedadVac->fechaFin, 8, 2) == "31"){
                            $diasCompensar--;   
                        }
                        
                    }
                    $diasTotales = $novedadVac->diasCompensar;
                    $novedadVac->diasCompensar = intval( $diasCompensar);

                    $itemBoucherNovedad = DB::table("item_boucher_pago_novedad", "ibpn")
                    ->join("item_boucher_pago as ibp","ibp.idItemBoucherPago", "=","ibpn.fkItemBoucher")
                    ->join("boucherpago as bp","bp.idBoucherPago", "=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->selectRaw("sum(ibpn.valor) as valor")
                    ->where("ibpn.fkNovedad", "=",$novedadVac->idNovedad)
                    ->whereBetween("ln.fechaLiquida",[$fechaInicioMesActual, $fechaFin])
                    ->first();       
                    if($novedadVac->pagoAnticipado == 1){
                        if(isset($itemBoucherNovedad) && $itemBoucherNovedad->valor > 0){
                            $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                            $valorNovedad = $diasPagoVac*$valorNovedad/$diasTotales;
                        
                        }
                        else{
                            $itemBoucherNovedad = DB::table("item_boucher_pago_novedad", "ibpn")
                            ->where("ibpn.fkNovedad", "=",$novedadVac->idNovedad)
                            ->first();
                
                            $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);
                            $valorNovedad = $diasPagoVac*$valorNovedad/$diasTotales;
                        
                        }
                    
                        
                    }
                    else{
                        $valorNovedad = ($itemBoucherNovedad->valor > 0 ? $itemBoucherNovedad->valor : $itemBoucherNovedad->valor*-1);    
                    }

                    $pago = $pago + $valorNovedad;
                }*/
                
                if($pago < 0){
                    $datosProvSuma = DB::table('provision','p')
                    ->selectRaw("sum(p.valor) as suma")
                    ->where("p.anio","=",$datoProv->anio)
                    ->where("p.mes","<=",$datoProv->mes)
                    ->where("p.fkPeriodoActivo","=",$datoProv->fkPeriodoActivo)
                    ->where("p.fkConcepto","=",$datoProv->fkConcepto)
                    ->first();
                    
                    if(isset($datosProvResta)){
                        $pago = $datosProvSuma->suma;    
                    }
                    
                }
            }
            $row = $row + 1;
            $arrInt[$row] = round($pago);
        
        
        }
        
        if($idPeriodo != 0){
            array_push($arrDatos, $arrInt);
        }
        
        //Colorcar totales por row
        $totales = array();
        foreach($arrDatos as $datos){
            foreach($datos as $row => $dato){
                if($row > 4){
                    if(is_numeric($dato)){
                        $totales[$row] = (isset($totales[$row]) ? $totales[$row] : 0) + $dato;
                    }
                }
            }            
        }
        $arrInt = array();
        if($req->provision!="Consolidado"){
            for($j = 0; $j <= 28; $j++){
                if(!isset($arrInt[$j])){
                    $arrInt[$j] = " ";
                }
            }
        }else{
            for($j = 0; $j <= 100; $j++){
                if(!isset($arrInt[$j])){
                    $arrInt[$j] = " ";
                }
            }
        }
        
        foreach($totales as $row => $total){
            $arrInt[$row] = $total; 
        }        
        array_push($arrDatos, $arrInt);

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", generó un reporte de provisiones");

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename=Informe_Provision_'.$nombreDoc.'.csv');

        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->setDelimiter(';');
        $csv->insertAll($arrDatos);
        $csv->setOutputBOM(Reader::BOM_UTF8);
        $csv->output('Informe_Provision_'.$nombreDoc.'.csv');
    }
    public function plantillaTxt($valor, $longitud, $relleno, $alinear){
        $string = "";
        $inicio = 0;
        if($alinear == "left"){
            $string.=$valor;
            $inicio=mb_strlen($valor);
        }
        
        for ($i=$inicio; $i < $longitud; $i++) { 
            $string.=$relleno;
        }
        
        if($alinear == "right"){
            $string.=$valor;
            $string = substr($string, $longitud*-1);
        }

        if(mb_strlen($string) > $longitud){
            $string = substr($string, 0 ,$longitud);
        }
        return $string;
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
    public function roundSup($numero, $presicion){
        $redondeo = $numero / pow(10,$presicion*-1);
        $redondeo = ceil($redondeo);
        $redondeo = $redondeo * pow(10,$presicion*-1);
        return $redondeo;
    }
    public function basico($numero) {
        $valor = array ('uno','dos','tres','cuatro','cinco','seis','siete','ocho',
        'nueve','diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve',
        'veinte','veintiuno','veintidos','veintitres','veinticuatro','veinticinco',
        'veintiséis','veintisiete','veintiocho','veintinueve');
        return $valor[$numero - 1];
    }        
    public function decenas($n) {
        $decenas = array (30=>'treinta',40=>'cuarenta',50=>'cincuenta',60=>'sesenta',
        70=>'setenta',80=>'ochenta',90=>'noventa');
        if( $n <= 29){
            return $this->basico($n);
        } 
        $x = $n % 10;
        if ( $x == 0 ) {
            return $decenas[$n];
        } else{
            return $decenas[$n - $x].' y '. $this->basico($x);  
        } 
    }        
    public function centenas($n) {
        $cientos = array (100 =>'cien',200 =>'doscientos',300=>'trecientos',
        400=>'cuatrocientos', 500=>'quinientos',600=>'seiscientos',
        700=>'setecientos',800=>'ochocientos', 900 =>'novecientos');
        if( $n >= 100) {
            if ( $n % 100 == 0 ) {
                return $cientos[$n];
            } 
            else {
                $u = (int) substr($n,0,1);
                $d = (int) substr($n,1,2);
                return (($u == 1)?'ciento':$cientos[$u*100]).' '.$this->decenas($d);
            }
        } else return $this->decenas($n);
    }        
    public function miles($n) {
        if($n > 999) {
            if( $n == 1000) {
                return 'mil';
            }
            else {
                $l = strlen($n);
                $c = (int)substr($n,0,$l-3);
                $x = (int)substr($n,-3);
                if($c == 1) {
                    $cadena = 'mil '.$this->centenas($x);
                }
                else if($x != 0) {
                    $cadena = $this->centenas($c).' mil '.$this->centenas($x);
                }
                else{
                    $cadena = $this->centenas($c). ' mil';
                }
                return $cadena;
            }
        } 
        else{
            return $this->centenas($n);
        }
    }        
    public function millones($n) {
        if($n == 1000000) {
            return 'un millón';
        }
        else {
            $l = strlen($n);
            $c = (int)substr($n,0,$l-6);
            $x = (int)substr($n,-6);
            if($c == 1) {
                $cadena = ' millón ';
            } else {
                $cadena = ' millones ';
            }
            return $this->miles($c).$cadena.(($x > 0)?$this->miles($x):'');
        }
    }
    public function convertir($n) {
        switch (true) {
            case ($n <= 0):
                return "CERO";
                break;
            case ( $n >= 1 && $n <= 29):
                return $this->basico($n);
                break;
            case ( $n >= 30 && $n < 100):
                return $this->decenas($n); 
                break;
            case ( $n >= 100 && $n < 1000):
                return $this->centenas($n); 
                break;
            case ($n >= 1000 && $n <= 999999): 
                return $this->miles($n); 
                break;
            case ($n >= 1000000): 
                return $this->millones($n);
                break;
        }
    }
    public function normalize ($string) {
        $table = array(
            'Š'=>'S', 'š'=>'s', 'Đ'=>'Dj', 'đ'=>'dj', 'Ž'=>'Z', 'ž'=>'z', 'Č'=>'C', 'č'=>'c', 'Ć'=>'C', 'ć'=>'c',
            'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
            'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss',
            'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e',
            'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o',
            'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b',
            'ÿ'=>'y', 'Ŕ'=>'R', 'ŕ'=>'r'
        );
    
        return strtr($string, $table);
    }
    public function upperCaseAllArray($array){
        foreach($array as $key => $value){
            $array[$key] = strtoupper($value);
            $array[$key] = $this->normalize($array[$key]);

            
            
        }
        return $array;
    }
    public function indexReporteVacaciones(){
        $dataUsu = UsuarioController::dataAdminLogueado();
        $empresas = DB::table("empresa", "e");
        if(isset($dataUsu) && $dataUsu->fkRol == 2){            
            $empresas = $empresas->whereIn("idempresa", $dataUsu->empresaUsuario);
        }
        $empresas = $empresas->orderBy("razonSocial")->get();
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu 'Reporte de vacaciones'");
        return view('/reportes.reporteVacaciones',[
            "empresas" => $empresas,
            "dataUsu" => $dataUsu
        ]);
    }
    public function reporteVacaciones(Request $req){

        if($req->tipoReporte == "PDF"){
            $dataUsu = UsuarioController::dataAdminLogueado();
            Log::channel('gesath')->info("El usuario ".$dataUsu->email.", generó un reporte de vacaciones PDF");
        
            $dataUsu = Auth::user();

            $empresa = DB::table("empresa", "e")->where("e.idempresa","=",$req->empresa)->first();

            $empleados = DB::table("empleado","e")
            ->select("e.*","dp.*","ccfijo.valor as valorSalario", "p.idPeriodo", "p.fechaInicio as fechaInicioPeriodo")
            ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
            ->join("conceptofijo as ccfijo","ccfijo.fkEmpleado", "=", "e.idempleado")
            ->join("periodo as p","p.fkEmpleado", "=","e.idempleado")
            ->join("nomina as n","n.idNomina", "=","p.fkNomina")            
            ->where("n.fkEmpresa","=",$req->empresa)
            ->where("p.fkEstado","=","1")
            ->whereIn("ccfijo.fkConcepto",["1","2","53","54","154"])
            //->take(1)
            //->skip(0)
            ->get();

            $base64 = "";
            if(is_file($this->rutaBaseImagenes.'storage/logosEmpresas/'.$empresa->logoEmpresa)){
                $imagedata = file_get_contents($this->rutaBaseImagenes.'storage/logosEmpresas/'.$empresa->logoEmpresa);
                        // alternatively specify an URL, if PHP settings allow
                $base64 = base64_encode($imagedata);
            }
            else{
                unset($empresa->logoEmpresa);
            }

            $dompdf = new Dompdf();
            $dompdf->getOptions()->setChroot($this->rutaBaseImagenes);
            //$dompdf->getOptions()->setIsPhpEnabled(true);
            $dompdf->set_option("isPhpEnabled", true);
            ob_start();?>
                <!DOCTYPE html>
                <html>
                    <head>
                        <meta charset='utf-8'>
                        <style>
                            /** 
                                Set the margins of the page to 0, so the footer and the header
                                can be of the full height and width !
                            **/
                            @page {
                                margin: 0cm 0cm;
                            }

                            /** Define now the real margins of every page in the PDF **/
                            body {
                                margin-top: 3.5cm;
                                margin-left: 1cm;
                                margin-right: 1cm;
                                margin-bottom: 1cm;
                                font-family: sans-serif;
                                font-size: 12px;
                            }

                            /** Define the header rules **/
                            header {
                                position: fixed;
                                top: 0.5cm;
                                left: 0cm;
                                right: 0cm;
                                height: 3cm;
                            }
                            .logoEmpresa{
                                max-width: 3cm;
                                max-height: 3cm;
                            }
                            .tablaHeader th, .tablaHeader td{
                                text-align: left;
                                
                                padding-left: 20px;
                                
                            }
                            .tablaDatos{
                                border-collapse: collapse;
                                width: 100%
                            }
                            .tablaDatos td{
                                text-align: right;
                                font-size: 9px;
                            }
                            .tablaDatos th{
                                font-size: 9px;
                            }
                            .tablaDatos td.left{
                                text-align: left;
                            }
                            .tablaDatos td.arriba *, .tablaDatos td.arriba{
                                vertical-align: top;
                                padding: 0 5px;
                            }
                            .azul1{
                                background: #afeeee;
                            }
                            .azul2{
                                background: #add8e6;
                            }
                            .separador_2{
                                page-break-before: always; 
                            }
                            .pagenum:before {
                                content: counter(page);
                            }
                            
                        </style>
                        <title>Reporte vacaciones</title>
                    </head>
                    <body>
                        <header>
                            <table class="tablaHeader">
                                <tr> 
                                    <td rowspan="4" width="5cm" height="2cm">
                                    <?php if(isset($empresa->logoEmpresa)){ echo '<img src="data:image/png;base64,'.$base64.'" class="logoEmpresa" style="max-width: 50px; max-height: 50px; "/>'; } ?>
                                    </td>
                                    <th width="8.1cm">LIBRO DE VACACIONES</th>
                                    <th width="2cm">Fecha:</th>
                                    <td width="4cm"><?php echo date("Y-m-d H:i:s"); ?></td>
                                </tr>
                                <tr>
                                    <th><?php echo $empresa->razonSocial; ?></th>
                                    <th>Usuario:</th>
                                    <td><?php echo $dataUsu->username; ?></td>
                                </tr>
                                <tr>
                                    <th><?php echo number_format($empresa->documento,0)." - ".$empresa->digitoVerificacion; ?></th>
                                    <th>Reporte:</th>
                                    <td>NOM_U_713</td>
                                </tr>
                                <tr>
                                    <th>FECHA DE CORTE: <?php echo date("t/m/Y",strtotime($req->fechaFin)); ?></th>
                                    <th>Página:</th>
                                    <td>
                                        <span class="pagenum"></span>
                                    </td>
                                </tr>
                            </table>
                        </header>
                        <main>
                            <table class="tablaDatos" border="1">
                                <tr>
                                    <th rowspan="2">ID</th>
                                    <th rowspan="2" width="180">NOMBRE</th>
                                    <th rowspan="2" width="45">F ING</th>
                                    <th rowspan="2">SUELDO</th>
                                    <th rowspan="2">D TRA</th>
                                    <th rowspan="2">D LNR</th>
                                    <th rowspan="2">D NET</th>
                                    <th rowspan="2">D VAC</th>
                                    <th rowspan="2">D TOM</th>
                                    <th rowspan="2" width="30">D PEN</th>
                                    <th class="azul1" colspan="6">CAUSACIÓN</th>
                                    <th class="azul2" colspan="3">DISFRUTE</th>
                                </tr>
                                <tr>
                                    <th class="azul1">PER</th>
                                    <th class="azul1" width="40">P INI</th>
                                    <th class="azul1" width="40">P FIN</th>
                                    <th class="azul1">D CAU</th>
                                    <th class="azul1">D TOM</th>
                                    <th class="azul1" width="25">D PEN</th>
                                    <th class="azul2" width="40">INI</th>
                                    <th class="azul2" width="40">FIN</th>
                                    <th class="azul2">DÍAS</th>
                                </tr>

                            <?php 
                                $cuenta = 0;
                                foreach($empleados as $empleado){
                                
                                    $periodoActivoReintegro = DB::table("periodo")
                                    ->where("idPeriodo", "=", $empleado->idPeriodo)
                                    ->first();

                                    $fechaInicio = $empleado->fechaInicioPeriodo;
                                    $empleado->fechaIngreso = $fechaInicio;


                                    $fechaFinGen = date("Y-m-t",strtotime($req->fechaFin));
                                    if(substr($fechaFinGen, 8, 2) == "31" || (substr($fechaFinGen, 8, 2) == "28" && substr($fechaFinGen, 5, 2) == "02") || (substr($fechaFinGen, 8, 2) == "29" && substr($fechaFinGen, 5, 2) == "02")  ){
                                        $fechaFinGen = substr($fechaFinGen,0,8)."30";
                                    }
                                    $entrar=true;
                                    $periodo = 1;
                        
                                    //Dias trabajados en este periodo
                        
                        
                                    //Obtener la primera liquidacion de nomina de la persona 
                                    $primeraLiquidacion = DB::table("liquidacionnomina", "ln")
                                    ->selectRaw("min(ln.fechaInicio) as primeraFecha")
                                    ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")   
                                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                    ->where("bp.fkEmpleado","=",$empleado->idempleado)->first();
                        
                                    $minimaFecha = date("Y-m-d");
                                    
                                    if(isset($primeraLiquidacion)){
                                        $minimaFecha = $primeraLiquidacion->primeraFecha;
                                    }
                                    $diasAgregar = 0;
                                    //Verificar si dicha nomina es menor a la fecha de ingreso
                                    if(strtotime($empleado->fechaIngreso) < strtotime($minimaFecha)){
                                        $diasAgregar = $this->days_360($empleado->fechaIngreso, $minimaFecha);
                                    }
                                    
                                    $liquidacionesMesesAnterioresCompleta = DB::table("liquidacionnomina", "ln")
                                    ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.salarioPeriodoPago) as salarioPago")
                                    ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                    ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6"])         
                                    ->first();

                                    
                                    $diasTrabajados = $this->days_360($fechaInicio, $fechaFinGen) + 1;
                                    

                                    $novedadesAus = DB::table("novedad","n")
                                    ->selectRaw("sum(a.cantidadDias) as suma")
                                    ->join("ausencia as a","a.idAusencia", "=", "n.fkAusencia")
                                    ->whereNotNull("n.fkAusencia")            
                                    ->where("n.fkEmpleado","=",$empleado->idempleado)
                                    ->where("n.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                    ->whereIn("n.fkEstado",["7", "8"]) // Pagada o sin pagar-> no que este eliminada
                                    ->first();

                                    $diasNeto = $diasTrabajados - $novedadesAus->suma;
                                   
                                    
                                    $diasVacGen = $diasNeto * 15 / 360;
                                
                                    $fechaFinGen = date("Y-m-t",strtotime($req->fechaFin));

                                    $novedadesVacacionGenDisf = DB::table("novedad","n")
                                    ->selectRaw("sum(v.diasCompletos) as suma")
                                    ->join("vacaciones as v","v.idVacaciones","=","n.fkVacaciones")
                                    ->where("n.fkEmpleado","=",$empleado->idempleado)
                                    ->where("n.fkPeriodoActivo","=",$empleado->idPeriodo)
                                    ->where("n.fkConcepto","=","29")
                                    ->whereIn("n.fkEstado",["7", "8","16"]) // Pagada -> no que este eliminada
                                    ->whereBetween("n.fechaRegistro",[$fechaInicio, $fechaFinGen])
                                    ->whereNotNull("n.fkVacaciones")
                                    ->first();

                                    $novedadesVacacionGenComp = DB::table("novedad","n")
                                    ->selectRaw("sum(v.diasCompensar) as suma")
                                    ->join("vacaciones as v","v.idVacaciones","=","n.fkVacaciones")
                                    ->where("n.fkEmpleado","=",$empleado->idempleado)
                                    ->where("n.fkPeriodoActivo","=",$empleado->idPeriodo)
                                    ->where("n.fkConcepto","=","28")
                                    ->whereIn("n.fkEstado",["7", "8","16"]) // Pagada -> no que este eliminada
                                    ->whereBetween("n.fechaRegistro",[$fechaInicio, $fechaFinGen])
                                    ->whereNotNull("n.fkVacaciones")
                                    ->first();
                                    
                                    $arrDatos = array();
                                    $rowspan = 1;
                                    while($entrar){
                                        $arrFila = array();
                                        $fechaFinInt = date("Y-m-d",strtotime($fechaInicio." +1 year -1 day"));
                                        if(strtotime($fechaFinGen) < strtotime($fechaFinInt)){
                                            $fechaFinInt = $fechaFinGen;
                                        }
                                        $periodoPagoVac = $this->days_360($fechaInicio, $fechaFinInt);
                                        
                                        //Proceso de vacaciones
                                        //Con esos dias calcular los que me pertenecen en vacaciones
                                        $diasVac = $periodoPagoVac * 15 / 360;
                                        //Cargar en este periodo las vacaciones tomadas
                                        $novedadesVacacion = DB::table("novedad","n")
                                        ->join("vacaciones as v","v.idVacaciones","=","n.fkVacaciones")
                                        ->where("n.fkEmpleado","=",$empleado->idempleado)
                                        ->where("n.fkPeriodoActivo","=",$empleado->idPeriodo)
                                        ->whereIn("n.fkEstado",["7", "8","16"]) // Pagada o sin pagar-> no que este eliminada
                                        ->whereBetween("n.fechaRegistro",[$fechaInicio, $fechaFinInt])
                                        ->whereNotNull("n.fkVacaciones")
                                        ->get();
                                        
                                        $diasTomadosPeriodo = 0;
                                        $arrFila['disfrute'] = array();
                                        foreach($novedadesVacacion as $novedadVacacion){
                                            $arrFilaInt = array();
                                            $arrFilaInt['diaIni'] = (isset($novedadVacacion->fechaInicio) ? date("d/m/Y",strtotime($novedadVacacion->fechaInicio)) : "");
                                            $arrFilaInt['diaFin'] = (isset($novedadVacacion->fechaFin) ? date("d/m/Y",strtotime($novedadVacacion->fechaFin)) : "");
                                            $arrFilaInt['diaTom'] = ($novedadVacacion->fkConcepto == "28" ? $novedadVacacion->diasCompensar : $novedadVacacion->diasCompletos);
                                            array_push($arrFila['disfrute'], $arrFilaInt);
                                            if($novedadVacacion->fkConcepto == "29"){
                                                $diasTomadosPeriodo = $diasTomadosPeriodo + $novedadVacacion->diasCompletos;        
                                            }
                                            else{
                                                $diasTomadosPeriodo = $diasTomadosPeriodo + $novedadVacacion->diasCompensar;        
                                            }
                                            
                                        }
                                        $rowspan = $rowspan + (sizeof($novedadesVacacion) > 0 ? (sizeof($novedadesVacacion) - 1) : 0);
                                        $diasPendientesPeriodo = $diasVac - $diasTomadosPeriodo;                
                                        $arrFila['periodo'] = $periodo;
                                        $arrFila['fechaInicio'] = $fechaInicio;
                                        $arrFila['fechaFinInt'] = $fechaFinInt;
                                        $arrFila['diaCau'] = $diasVac;
                                        $arrFila['diaTom'] = $diasTomadosPeriodo;
                                        $arrFila['diaPen'] = $diasPendientesPeriodo;
                                        array_push($arrDatos, $arrFila);
                                        
                                        
                                        //Restar dias que estuvo en vacacion en ese periodo y colocar los dias pendientes en el periodo
                        
                                        if(strtotime($fechaFinGen) == strtotime($fechaFinInt)){
                                            $entrar=false;
                                        }
                                        else{
                                            //$fechaInicio = $fechaFinInt;
                                            $fechaInicio = date("Y-m-d",strtotime($fechaInicio." +1 year"));
                                            $periodo++;
                                            $rowspan++;
                        
                                        }
                                    }
                                    $cuenta += $rowspan;
                                    if($cuenta > 42){
                                        
                                        echo '
                                        </table>';
                                       
                                            echo '<div class="separador_2"></div>';
                                        
                                    

                                        echo '<table class="tablaDatos" border="1">                                        
                                        <tr>
                                            <th rowspan="2">ID</th>
                                            <th rowspan="2" width="180">NOMBRE</th>
                                            <th rowspan="2" width="45">F ING</th>
                                            <th rowspan="2">SUELDO</th>
                                            <th rowspan="2">D TRA</th>
                                            <th rowspan="2">D LNR</th>
                                            <th rowspan="2">D NET</th>
                                            <th rowspan="2">D VAC</th>
                                            <th rowspan="2">D TOM</th>
                                            <th rowspan="2" width="30">D PEN</th>
                                            <th class="azul1" colspan="6">CAUSACIÓN</th>
                                            <th class="azul2" colspan="3">DISFRUTE</th>
                                        </tr>
                                        <tr>
                                            <th class="azul1">PER</th>
                                            <th class="azul1" width="40">P INI</th>
                                            <th class="azul1" width="40">P FIN</th>
                                            <th class="azul1">D CAU</th>
                                            <th class="azul1">D TOM</th>
                                            <th class="azul1" width="25">D PEN</th>
                                            <th class="azul2" width="40">INI</th>
                                            <th class="azul2" width="40">FIN</th>
                                            <th class="azul2">DÍAS</th>
                                    </tr>
                                    ';
                                        $cuenta = $rowspan;
                                    }
                                    //dump($rowspan);
                                    $diasTomados = ($novedadesVacacionGenDisf->suma ?? 0) + ($novedadesVacacionGenComp->suma ?? 0);
                                    echo '<tr>
                                    <td class="arriba" rowspan="'.$rowspan.'">'.$empleado->numeroIdentificacion.'</td>
                                    <td class="arriba left" width="180" rowspan="'.$rowspan.'">'.$empleado->primerApellido." ".$empleado->segundoApellido." ".$empleado->primerNombre." ".$empleado->segundoNombre.'</td>
                                    <td class="arriba" rowspan="'.$rowspan.'">'.$empleado->fechaIngreso.'</td>
                                    <td class="arriba" rowspan="'.$rowspan.'">'.number_format($empleado->valorSalario,0,",", ".").'</td>';                            
                                    echo '<td class="arriba" rowspan="'.$rowspan.'">'.$diasTrabajados.'</td>';
                                    echo '<td class="arriba" rowspan="'.$rowspan.'">'.(isset($novedadesAus->suma) ? $novedadesAus->suma : 0).'</td>';
                                    echo '<td class="arriba" rowspan="'.$rowspan.'">'.$diasNeto.'</td>';
                                    echo '<td class="arriba" rowspan="'.$rowspan.'">'.round($diasVacGen,2).'</td>';
                                    echo '<td class="arriba" rowspan="'.$rowspan.'">'.$diasTomados.'</td>';
                                    echo '<td class="arriba" rowspan="'.$rowspan.'">'.round($diasVacGen - $diasTomados, 2).'</td>';
                                    $aplico = 0;
                                    foreach($arrDatos as $datoCau){
                                        $rowspanInt = 1;
                                        
                                        if(sizeof($datoCau['disfrute'])>0){
                                            $rowspanInt =  $rowspanInt + sizeof($datoCau['disfrute']) - 1;
                                        }
                                        if($aplico == 1){
                                            echo '<tr>';
                                        }
                                        echo '<td rowspan="'.$rowspanInt.'" class="azul1">'.$datoCau['periodo'].'</td>';
                                        echo '<td rowspan="'.$rowspanInt.'" class="azul1">'.$datoCau['fechaInicio'].'</td>';
                                        echo '<td rowspan="'.$rowspanInt.'" class="azul1">'.$datoCau['fechaFinInt'].'</td>';
                                        echo '<td rowspan="'.$rowspanInt.'" class="azul1">'.round($datoCau['diaCau'],2).'</td>';
                                        echo '<td rowspan="'.$rowspanInt.'" class="azul1">'.$datoCau['diaTom'].'</td>';
                                        echo '<td rowspan="'.$rowspanInt.'" class="azul1">'.round($datoCau['diaPen'],2).'</td>';
                                        
                                        if(sizeof($datoCau['disfrute'])>0){
                                            $aplico2 = 0;
                                            foreach($datoCau['disfrute'] as $disf){
                                                if($aplico2 == 1){
                                                    echo '<tr>';
                                                }

                                                echo '<td class="azul2">'.$disf['diaIni'].'</td>';
                                                echo '<td class="azul2" >'.$disf['diaFin'].'</td>';
                                                echo '<td class="azul2">'.$disf['diaTom'].'</td>';
                                                echo "</tr>";
                                                $aplico2 = 1;
                                            }
                                        }
                                        else{
                                            echo '<td class="azul2"></td>';
                                            echo '<td class="azul2"></td>';
                                            echo '<td class="azul2">0</td>';
                                            echo "</tr>";
                                        }
                                        $aplico = 1;
                                    }
                                }
                            ?>
                            </table>
                        </main>
                    </body>
                </html>
            <?php
            $html = ob_get_clean();
            //dd($html);
            //exit;
            $dompdf->loadHtml($html ,'UTF-8');
        
            // (Optional) Setup the paper size and orientation
            $dompdf->setPaper('legal', 'landscape');
            // Render the HTML as PDF
            $dompdf->render();

            // Output the generated PDF to Browser
            $dompdf->stream("Reporte vacaciones.pdf", array('compress' => 1, 'Attachment' => 0));
            
        }
        else if($req->tipoReporte == "EXCEL"){
            $dataUsu = UsuarioController::dataAdminLogueado();
            Log::channel('gesath')->info("El usuario ".$dataUsu->email.", generó un reporte de vacaciones Excel");
            $novedades = DB::table("novedad","n")
            ->select("dp.numeroIdentificacion", 
                     "c.idconcepto", "c.nombre as nombreConcepto", "a.cantidadDias as ausDias", "a.fechaInicio as ausFechaInicio", "a.fechaFin as ausFechaFin",
                     "i.numDias as incDias","i.fechaInicial as incFechaInicio", "i.fechaFinal as incFechaFin", "l.numDias as licDias", "l.fechaInicial as licFechaInicio",
                     "l.fechaFinal as licFechaFin", "v.diasCompletos as vacDias", "v.diasCompensar as vacDiasComp", "v.fechaInicio as vacFechaInicio", "v.fechaFin as vacFechaFin",
                     "n.*","car.nombreCargo as nombreCargoPeriodo", "car2.nombreCargo as nombreCargoEmp","emp.razonSocial as nombreEmpresa",
                     "nom.nombre as nombreNomina","est.nombre as estado","est.idestado")
            ->selectRaw('(select cc2.nombre from centrocosto as cc2 where cc2.idcentroCosto 
                     in(Select ecc.fkCentroCosto from empleado_centrocosto as ecc where 
                     ecc.fkEmpleado = e.idempleado and ecc.fkPeriodoActivo = n.fkPeriodoActivo)
                     limit 0,1) as centroCosto, CONCAT(dp.primerApellido," ",dp.segundoApellido," ",dp.primerNombre," ",dp.segundoNombre) 
                     as nombreEmpleado')
            ->join("empleado as e","e.idempleado", "=","n.fkEmpleado")
            ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
            ->join("periodo as p","p.fkEmpleado", "=","e.idempleado")
            ->join("concepto as c","c.idconcepto", "=","n.fkConcepto")
            ->join("ausencia as a","a.idAusencia","=", "n.fkAusencia", "left")
            ->join("incapacidad as i","i.idIncapacidad","=", "n.fkIncapacidad", "left")
            ->join("licencia as l","l.idLicencia","=", "n.fkLicencia", "left")
            ->join("vacaciones as v","v.idVacaciones","=", "n.fkVacaciones", "left")
            ->join("nomina as nom","nom.idNomina", "=","p.fkNomina")
            ->join("empresa as emp","emp.idempresa", "=","nom.fkEmpresa")
            ->join("cargo as car","car.idCargo", "=","p.fkCargo","left")
            ->join("cargo as car2","car2.idCargo", "=","e.fkCargo","left")
            ->join("estado as est","est.idestado", "=","p.fkEstado","left")
            ->whereIn("n.fkEstado",["8"])
            ->where("n.fechaRegistro","<=", $req->fechaFin)
            ->where(function($query) use($req){
                $query->where("emp.idempresa","=",$req->empresa);                
            })
            ->where(function($query){
                $query->whereNotNull("n.fkAusencia")
                ->orWhereNotNull("n.fkIncapacidad")
                ->orWhereNotNull("n.fkLicencia")
                ->orWhereNotNull("n.fkVacaciones");
            })
            ->get();


            $arrDef = array([
                "Fecha Novedad",
                "Empresa",
                "Nomina",
                "Cargo",
                "Estado",
                "Documento",
                "Empleado",
                "Concepto",
                "Tipo",
                "Días",                
                "Fecha Inicio",
                "Fecha Fin"
            ]);

            foreach($novedades as $novedad){
                $tipo = "";
                $dias = "";
                $fechaInicio = "";
                $fechaFin = "";
                $cargo = $novedad->nombreCargoEmp;

                if($novedad->idestado == "2"){
                    $cargo = $novedad->nombreCargoPeriodo;
                }

                if($novedad->fkTipoNovedad == "1"){
                    $tipo = "AUS";
                    $dias = $novedad->ausDias;
                    $fechaInicio = $novedad->ausFechaInicio;
                    $fechaFin = $novedad->ausFechaFin;
                }
                if($novedad->fkTipoNovedad == "2"){
                    $tipo = "INC";
                    $dias = $novedad->incDias;
                    $fechaInicio = $novedad->incFechaInicio;
                    $fechaFin = $novedad->incFechaFin;
                }
                if($novedad->fkTipoNovedad == "3"){
                    $tipo = "LIC";
                    $dias = $novedad->licDias;
                    $fechaInicio = $novedad->licFechaInicio;
                    $fechaFin = $novedad->licFechaFin;
                }
                if($novedad->fkTipoNovedad == "6"){
                    $tipo = "VAC";
                    if($novedad->idconcepto == "29"){
                        $dias = $novedad->vacDias;
                    }
                    else if($novedad->idconcepto == "28"){
                        $dias = $novedad->vacDiasComp;
                    }
                    
                    $fechaInicio = $novedad->vacFechaInicio;
                    $fechaFin = $novedad->vacFechaFin;
                }


                array_push($arrDef, [
                    $novedad->fechaRegistro,
                    $novedad->nombreEmpresa,
                    $novedad->nombreNomina,
                    $cargo,
                    $novedad->estado,
                    $novedad->numeroIdentificacion,
                    $novedad->nombreEmpleado,
                    $novedad->nombreConcepto,
                    $tipo,
                    floatval($dias),                    
                    $fechaInicio,
                    $fechaFin
                ]);
            }
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Description: File Transfer');
            header('Content-Disposition: attachment; filename=Vacaciones.csv');
    
            $csv = Writer::createFromFileObject(new SplTempFileObject());
            $csv->setDelimiter(';');
            $csv->insertAll($arrDef);
            $csv->setOutputBOM(Reader::BOM_UTF8);
            $csv->output('Vacaciones.csv');
            

        }
    }
    public function indexFormulario220(){
        $dataUsu = UsuarioController::dataAdminLogueado();
        $empresas = DB::table("empresa", "e");
        if(isset($dataUsu) && $dataUsu->fkRol == 2){            
            $empresas = $empresas->whereIn("idempresa", $dataUsu->empresaUsuario);
        }
        $empresas = $empresas->orderBy("razonSocial")->get();
        $formularios = DB::table("formulario220")->orderBy("anio","desc")->get();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu 'Formulario 220'");

        return view('/reportes.formulario220',[
            "empresas" => $empresas,
            "formularios" => $formularios,
            "dataUsu" => $dataUsu
        ]);
    }

    public function formulario220Dian(Request $req){

        $formulario =  DB::table("formulario220")->where("idFormulario220","=",$req->anio)->first();
        $req->anio =  $formulario->anio;
        $req->fechaExp =  "30-03-".$req->anio;

        if($req->reporte == "PDF"){
            $dataUsu = UsuarioController::dataAdminLogueado();
            Log::channel('gesath')->info("El usuario ".$dataUsu->email.", generó un reporte pdf del formulario 220");

            $dompdf = new Dompdf();
            $dompdf->getOptions()->setChroot($this->rutaBaseImagenes);
            $dompdf->getOptions()->setIsPhpEnabled(true);
    
            $urlFoto = Storage::url($formulario->rutaImagen);
            $base64 = "";
            if(is_file($this->rutaBaseImagenes.$urlFoto)){
                $imagedata = file_get_contents($this->rutaBaseImagenes.$urlFoto);
                        // alternatively specify an URL, if PHP settings allow
                $base64 = base64_encode($imagedata);
            }
            $url = Storage::url('fonts/ARIALNB.TTF');
            ob_start();?>
                <!DOCTYPE html>
                <html>
                    <head>
                        <meta charset='utf-8'>
                        <style>
                            @font-face {
                                font-family: 'ArialNarrow';
                                font-style: normal;
                                font-weight: normal;
                                src: url(/fonts/ARIALNB.TTF) format('truetype');
                            }

                
                            /** 
                                Set the margins of the page to 0, so the footer and the header
                                can be of the full height and width !
                            **/
                            @page {
                                margin: 0cm 0cm;
                                position: absolute;
                            }

                            /** Define now the real margins of every page in the PDF **/
                            body {
                                margin: 0;
                                font-family: sans-serif;
                                font-size: 12px;
                            }
                            .page_break { 
                                page-break-before: always; 
                            }
                        

                            
                        </style>
                        <title>Formulario 220</title>
                    </head>
                    <body>
                    <?php 
                    $empleados = DB::table("empleado","e")
                    ->select("e.*","p.idPeriodo", "p.fechaInicio as fechaInicioPeriodo","n.fkEmpresa as fkEmpresaNomina")
                    ->join("periodo as p","p.fkEmpleado", "=","e.idempleado")
                    ->join("nomina as n","n.idNomina", "=","p.fkNomina")
                    ->where("n.fkEmpresa","=",$req->empresa)
                    ->where("p.fkEstado","=","1");
                    if(isset($req->infoNomina)){
                        $empleados = $empleados->where("p.fkNomina","=",$req->infoNomina);    
                    }
                    if(isset($req->idEmpleado)){
                        $empleados = $empleados->where("e.idempleado","=", $req->idEmpleado);
                    }
                    $empleados = $empleados->get();     

                    foreach($empleados as $row => $empleado){

                        
                        $periodoActivoReintegro = DB::table("periodo")
                        ->where("idPeriodo", "=", $empleado->idPeriodo)
                        ->first();

                        $fechaPeriodoCer = $formulario->anio."-01-01";
                        if(strtotime($fechaPeriodoCer) < strtotime($empleado->fechaInicioPeriodo) && date("Y", strtotime($fechaPeriodoCer)) == date("Y", strtotime($empleado->fechaInicioPeriodo))){
                            $fechaPeriodoCer = $empleado->fechaInicioPeriodo;
                        }
                        $datosPersonales = DB::table("datospersonales", "dp")
                        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion")
                        ->where("dp.idDatosPersonales","=", $empleado->fkDatosPersonales)->first();
                        $empresa = DB::table("empresa","e")
                        ->select("e.*","u.nombre as ciudadUb","u.idubicacion" )
                        ->join("ubicacion as u", "u.idubicacion", "=","e.fkUbicacion")
                        ->where("idempresa","=", $empleado->fkEmpresaNomina)->first();
                        



                        



                        $sumaItems37 = DB::table("item_boucher_pago","ibp")
                        ->selectRaw("sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                        ->join("liquidacionnomina as ln","bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto", "=","ibp.fkConcepto")
                        ->whereRaw("YEAR(ln.fechaLiquida) = '".$req->anio."'")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("gcc.fkGrupoConcepto","=","30")
                        ->first();
                        

                        $sumaItems40 = DB::table("item_boucher_pago","ibp")
                        ->selectRaw("sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                        ->join("liquidacionnomina as ln","bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto", "=","ibp.fkConcepto")
                        ->whereRaw("YEAR(ln.fechaLiquida) = '".$req->anio."'")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("gcc.fkGrupoConcepto","=","31")->first();

                        $sumaItems41 = DB::table("item_boucher_pago","ibp")
                        ->selectRaw("sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                        ->join("liquidacionnomina as ln","bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto", "=","ibp.fkConcepto")
                        ->whereRaw("YEAR(ln.fechaLiquida) = '".$req->anio."'")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("gcc.fkGrupoConcepto","=","32")->first();

                        $sumaItems42 = DB::table("item_boucher_pago","ibp")
                        ->selectRaw("sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                        ->join("liquidacionnomina as ln","bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto", "=","ibp.fkConcepto")
                        ->whereRaw("YEAR(ln.fechaLiquida) = '".$req->anio."'")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("gcc.fkGrupoConcepto","=","33")->first();

                        $sumaItems43 = DB::table("item_boucher_pago","ibp")
                        ->selectRaw("sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                        ->join("liquidacionnomina as ln","bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto", "=","ibp.fkConcepto")
                        ->whereRaw("YEAR(ln.fechaLiquida) = '".$req->anio."'")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("gcc.fkGrupoConcepto","=","44")->first();



                        $sumaItems45 = DB::table("item_boucher_pago","ibp")
                        ->selectRaw("sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                        ->join("liquidacionnomina as ln","bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto", "=","ibp.fkConcepto")
                        ->whereRaw("YEAR(ln.fechaLiquida) = '".$req->anio."'")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("gcc.fkGrupoConcepto","=","34")->first();
                        $sumaItems46 = DB::table("item_boucher_pago","ibp")
                        ->selectRaw("sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                        ->join("liquidacionnomina as ln","bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto", "=","ibp.fkConcepto")
                        ->whereRaw("YEAR(ln.fechaLiquida) = '".$req->anio."'")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("gcc.fkGrupoConcepto","=","35")->first();
                        $sumaItems49 = DB::table("item_boucher_pago","ibp")
                        ->selectRaw("sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                        ->join("liquidacionnomina as ln","bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto", "=","ibp.fkConcepto")
                        ->whereRaw("YEAR(ln.fechaLiquida) = '".$req->anio."'")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("gcc.fkGrupoConcepto","=","36")->first();
                        $sumaItems50 = DB::table("item_boucher_pago","ibp")
                        ->selectRaw("sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                        ->join("liquidacionnomina as ln","bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto", "=","ibp.fkConcepto")
                        ->whereRaw("YEAR(ln.fechaLiquida) = '".$req->anio."'")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("gcc.fkGrupoConcepto","=","37")->first();
                        $sumaItems51 = DB::table("item_boucher_pago","ibp")
                        ->selectRaw("sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                        ->join("liquidacionnomina as ln","bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto", "=","ibp.fkConcepto")
                        ->whereRaw("YEAR(ln.fechaLiquida) = '".$req->anio."'")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("gcc.fkGrupoConcepto","=","38")->first();
                        $sumaItems52 = DB::table("item_boucher_pago","ibp")
                        ->selectRaw("sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                        ->join("liquidacionnomina as ln","bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto", "=","ibp.fkConcepto")
                        ->whereRaw("YEAR(ln.fechaLiquida) = '".$req->anio."'")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("gcc.fkGrupoConcepto","=","39")->first();
                        $sumaItems53 = DB::table("item_boucher_pago","ibp")
                        ->selectRaw("sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                        ->join("liquidacionnomina as ln","bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto", "=","ibp.fkConcepto")
                        ->whereRaw("YEAR(ln.fechaLiquida) = '".$req->anio."'")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("gcc.fkGrupoConcepto","=","40")->first();
                        


                        $dependiente = DB::table("beneficiotributario","bt")
                        ->select("nf.numIdentificacion", "p.nombre as paren", "nf.nombre")
                        ->join("nucleofamiliar as nf", "nf.idNucleoFamiliar","=","bt.fkNucleoFamiliar")
                        ->join("parentesco as p","p.idParentesco","=","nf.fkParentesco")
                        ->where("bt.fkEmpleado", "=", $empleado->idempleado)
                        ->where("bt.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("bt.fechaVigencia", ">", date("Y-m-d", strtotime($fechaPeriodoCer)))
                        ->first();

                    ?>
                        <div class="page">
                            <img src="data:image/png;base64,<?php echo $base64 ?>" style="position: absolute;"/>
                            <div style="position: absolute; font-family: 'ArialNarrow', sans-serif;   font-size: 13.3px; left: 218px; top: 43px; width: 400px; text-align: center; line-height: 11px; color:#1d1d1b; letter-spacing: -0.04px;">Certificado de Ingresos y Retenciones por Rentas de Trabajo y de Pensiones<br>Año gravable <?php echo $formulario->anio ?></div>
                            
                            
                            <div style="position: absolute; font-size: 8px; color: #2b2d48; left: 95px; top: 93px; letter-spacing: 0.03px;">Antes de diligenciar este formulario lea cuidadosamente las instrucciones</div>

                            <div style="position: absolute; font-size: 9px; color: #000; left: 434px; top: 85px;letter-spacing: 0.07px;">4. Número de formulario</div>
                            <div style="position: absolute; font-size: 8px; color: #000; left: 51px; top: 115px;letter-spacing: 0.00px;">5. Número de Identificación Tributaria (NIT)</div>
                            <div style="position: absolute; font-size: 8px; color: #000; left: 247px; top: 115px;letter-spacing: 0.00px;">6. DV.</div>
                            <div style="position: absolute; font-size: 8px; color: #000; left: 274.5px; top: 115px;letter-spacing: -0.01px;">7. Primer apellido</div>
                            <div style="position: absolute; font-size: 8px; color: #000; left: 393px; top: 115px;letter-spacing: -0.01px;">8. Segundo apellido</div>
                            <div style="position: absolute; font-size: 8px; color: #000; left: 504px; top: 115px;letter-spacing: -0.01px;">9. Primer nombre</div>
                            <div style="position: absolute; font-size: 8px; color: #000; left: 639px; top: 115px;letter-spacing: -0.01px;">10. Otros nombres</div>
                            <div style="position: absolute; font-size: 8px; color: #000; left: 51px; top: 149px;letter-spacing: -0.01px;">11. Razón social</div>

                            <div style="position: absolute; font-size: 8px; color: #000; left: 53px; top: 182.5px;letter-spacing: -0.02px; line-height: 7.2px;">24. Tipo de<br>documento</div>
                            <div style="position: absolute; font-size: 8px; color: #000; left: 109px; top: 180px;letter-spacing: -0.00px;">25. Número de Identificación</div>
                            <div style="position: absolute; font-size: 8px; color: #000; left: 312.2px; top: 180px;letter-spacing: -0.00px;">Apellidos y nombres</div>

                            <div style="position: absolute; font-size: 8px; color: #000; left: 129px; top: 211px;letter-spacing: -0.00px;">Período de la Certificación</div>
                            <div style="position: absolute; font-size: 8px; color: #000; left: 346px; top: 211px;letter-spacing: 0.02px;">32. Fecha de expedición</div>
                            <div style="position: absolute; font-size: 8px; color: #000; left: 460px; top: 211px;letter-spacing: -0.2px;">33. Lugar donde se practicó la retención</div>
                            <div style="position: absolute; font-size: 8px; color: #000; left: 697px; top: 213px;letter-spacing: -0.02px; line-height: 7.2px;">34. Cód.<br>Dpto.</div>
                            <div style="position: absolute; font-size: 8px; color: #000; left: 734px; top: 213px;letter-spacing: -0.4px; line-height: 7.2px;">35. Cód. Ciudad/<br>Municipio</div>

                            <div style="position: absolute; font-family: sans-serif; font-weight:bold; font-size: 8px; color: #000; left: 34px; top: 229px;letter-spacing: 0.00px;">30. DE:</div>
                            <div style="position: absolute; font-family: sans-serif; font-weight:bold; font-size: 8px; color: #000; left: 186px; top: 229px;letter-spacing: 0.00px;">31. A:</div>
                            
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 34px; top: 245px;letter-spacing: 0.078px;">36. Número de agencias, sucursales, filiales o subsidiarias de la empresa retenedora cuyos montos de retención se consolidan</div>
                            <div style="position: absolute; font-family: sans-serif; font-weight:bold; font-size: 9px; color: #000; left: 282px; top: 260px;letter-spacing: 0.07px;">Concepto de los Ingresos</div>
                            <div style="position: absolute; font-family: sans-serif; font-weight:bold; font-size: 9px; color: #000; left: 703px; top: 260px;letter-spacing: -0.5px;">Valor</div>


                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 277px;letter-spacing: 0.1px;">Pagos por salarios o emolumentos eclesiásticos</div>
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 277px;letter-spacing: 0.1px;">37</div>

                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 293px;letter-spacing: 0.1px;">Pagos por honorarios</div>
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 293px;letter-spacing: 0.1px;">38</div>

                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 309px;letter-spacing: 0.1px;">Pagos por servicios</div>
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 309px;letter-spacing: 0.1px;">39</div>

                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 325px;letter-spacing: 0.1px;">Pagos por comisiones</div>
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 325px;letter-spacing: 0.1px;">40</div>
                            
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 341px;letter-spacing: 0.1px;">Pagos por prestaciones sociales</div>
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 341px;letter-spacing: 0.1px;">41</div>

                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 357px;letter-spacing: 0.1px;">Pagos por viáticos</div>
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 357px;letter-spacing: 0.1px;">42</div>

                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 373px;letter-spacing: 0.1px;">Pagos por gastos de representación</div>
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 373px;letter-spacing: 0.1px;">43</div>

                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 389px;letter-spacing: 0.1px;">Pagos por compensaciones por el trabajo asociado cooperativo</div>
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 389px;letter-spacing: 0.1px;">44</div>

                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 405px;letter-spacing: 0.1px;">Otros pagos</div>
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 405px;letter-spacing: 0.1px;">45</div>

                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 421px;letter-spacing: 0.1px;">Cesantías e intereses de cesantías efectivamente pagadas, consignadas o reconocidas en el periodo</div>
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 421px;letter-spacing: 0.1px;">46</div>

                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 437px;letter-spacing: 0.1px;">Pensiones de jubilación, vejez o invalidez</div>
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 437px;letter-spacing: 0.1px;">47</div>

                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 455px;letter-spacing: 0.1px;"><b>Total de ingresos brutos</b> (Sume 37 a 47)</div>
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 455px;letter-spacing: 0.1px;"><b>48</b></div>


                            <div style="position: absolute; font-family: sans-serif; font-weight:bold; font-size: 9px; color: #000; left: 284px; top: 470px;letter-spacing: 0.09px;">Concepto de los aportes</div>
                            <div style="position: absolute; font-family: sans-serif; font-weight:bold; font-size: 9px; color: #000; left: 704px; top: 470px;letter-spacing: -0.5px;">Valor</div>

                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 487px;letter-spacing: 0.1px;">Aportes obligatorios por salud</div>
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 486px;letter-spacing: 0.1px;">49</div>

                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 503px;letter-spacing: 0.1px;">Aportes obligatorios a fondos de pensiones y solidaridad pensional y Aportes voluntarios al - RAIS</div>
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 502px;letter-spacing: 0.1px;">50</div>

                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 519px;letter-spacing: 0.1px;">Aportes voluntarios a fondos de pensiones</div>
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 518px;letter-spacing: 0.1px;">51</div>

                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 535px;letter-spacing: 0.1px;">Aportes a cuentas AFC.</div>
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 534px;letter-spacing: 0.1px;">52</div>

                            <div style="position: absolute; font-family: sans-serif; font-weight:bold; font-size: 9px; color: #FFF; left: 35px; top: 550px;letter-spacing: 0.08px;">Valor de la retención en la fuente por rentas de trabajo y pensiones</div>
                            <div style="position: absolute; font-family: sans-serif; font-weight:bold; font-size: 9px; color: #000; left: 630px; top: 550px;letter-spacing: 0.0px;">53</div>

                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 566px;letter-spacing: 0.1px;">Nombre del pagador o agente retenedor</div>
                            


                            <div style="position: absolute; font-family: sans-serif; font-weight:bold; font-size: 9px; color: #000; left: 348px; top: 599px;letter-spacing: 0.06px;">Datos a cargo del trabajador o pensionado</div>

                            <div style="position: absolute; font-family: sans-serif; font-weight:bold; font-size: 9px; color: #000; left: 183px; top: 616px;letter-spacing: 0.1px;">Concepto de otros ingresos</div>
                            <div style="position: absolute; font-family: sans-serif; font-weight:bold; font-size: 9px; color: #000; left: 520px; top: 616px;letter-spacing: 0.0px;">Valor recibido</div>
                            <div style="position: absolute; font-family: sans-serif; font-weight:bold; font-size: 9px; color: #000; left: 683px; top: 616px;letter-spacing: 0.0px;">Valor retenido</div>


                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 633px;letter-spacing: 0.1px;">Arrendamientos</div>   
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 474px; top: 633px;letter-spacing: 0.1px;">54</div>  
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 633px;letter-spacing: 0.1px;">61</div>  

                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 649px;letter-spacing: 0.1px;">Honorarios, comisiones y servicios</div>   
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 474px; top: 649px;letter-spacing: 0.1px;">55</div>  
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 649px;letter-spacing: 0.1px;">62</div>  

                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 665px;letter-spacing: 0.1px;">Intereses y rendimientos financieros</div>   
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 474px; top: 665px;letter-spacing: 0.1px;">56</div>  
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 665px;letter-spacing: 0.1px;">63</div>  

                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 681px;letter-spacing: 0.1px;">Enajenación de activos fijos</div>   
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 474px; top: 681px;letter-spacing: 0.1px;">57</div>  
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 681px;letter-spacing: 0.1px;">64</div>  

                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 697px;letter-spacing: 0.1px;">Loterías, rifas, apuestas y similares</div>   
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 474px; top: 697px;letter-spacing: 0.1px;">58</div>  
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 697px;letter-spacing: 0.1px;">65</div>  

                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 713px;letter-spacing: 0.1px;">Otros</div>   
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 474px; top: 713px;letter-spacing: 0.1px;">59</div>  
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 713px;letter-spacing: 0.1px;">66</div>  

                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 729px;letter-spacing: 0.1px;"><b>Totales: (Valor recibido:</b> Sume 54 a 59), (<b>Valor retenido:</b> Sume 61 a 66)</div>   
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 474px; top: 729px;letter-spacing: 0.1px;"><b>60<b></div>  
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 729px;letter-spacing: 0.1px;"><b>67</b></div>  

                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 35px; top: 745px;letter-spacing: 0.1px;"><b>Total retenciones año gravable <?php echo $formulario->anio ?></b> (Sume 53 + 67)</div>   
                            
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 630px; top: 745px;letter-spacing: 0.1px;"><b>68</b></div>  

                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 30px; top: 760px;letter-spacing: 0.1px;"><b>Item</b></div>  
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 253px; top: 760px;letter-spacing: 0.1px;"><b>69. Identificación de los bienes y derechos poseídos</b></div>  
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 670px; top: 760px;letter-spacing: 0.1px;"><b>70. Valor Patrimonial</b></div>  
                            
                            <div style="position: absolute; font-family: sans-serif; font-size: 8px; color: #000; left: 38px; top: 776px;letter-spacing: 0.1px;"><b>1</b></div>  
                            <div style="position: absolute; font-family: sans-serif; font-size: 8px; color: #000; left: 38px; top: 792px;letter-spacing: 0.1px;"><b>2</b></div>  
                            <div style="position: absolute; font-family: sans-serif; font-size: 8px; color: #000; left: 38px; top: 808px;letter-spacing: 0.1px;"><b>3</b></div>  
                            <div style="position: absolute; font-family: sans-serif; font-size: 8px; color: #000; left: 38px; top: 824px;letter-spacing: 0.1px;"><b>4</b></div>  
                            <div style="position: absolute; font-family: sans-serif; font-size: 8px; color: #000; left: 38px; top: 840px;letter-spacing: 0.1px;"><b>5</b></div>  
                            <div style="position: absolute; font-family: sans-serif; font-size: 8px; color: #000; left: 38px; top: 856px;letter-spacing: 0.1px;"><b>6</b></div>  
                            <div style="position: absolute; font-family: sans-serif; font-size: 8px; color: #000; left: 38px; top: 872px;letter-spacing: 0.1px;"><b>7</b></div>  

                            <div style="position: absolute; font-family: sans-serif; font-weight:bold; font-size: 9px; color: #FFF; left: 37px; top: 887px;letter-spacing: 0.1px;">Deudas vigentes a 31 de Diciembre de <?php echo $formulario->anio ?></div>
                            <div style="position: absolute; font-family: sans-serif; font-size: 9px; color: #000; left: 626px; top: 888px;letter-spacing: 0.1px;">71</div>  

                            <div style="position: absolute; font-family: sans-serif; font-weight:bold; font-size: 9px; color: #000; left: 170px; top: 904px;letter-spacing: 0.1px;">Identificación de la persona dependiente de acuerdo al parágrafo 2 del artículo 387 del Estatuto Tributario</div>
                            
                            <div style="position: absolute; font-family: sans-serif; font-size: 10px; color: #000; left: 33px; top: 918px;letter-spacing: -0.03px;">72. C.C. o NIT</div>   
                            <div style="position: absolute; font-family: sans-serif; font-size: 10px; color: #000; left: 196px; top: 918px;letter-spacing: -0.3px;">73. Apellidos y Nombres</div>    
                            <div style="position: absolute; font-family: sans-serif; font-size: 10px; color: #000; left: 688px; top: 918px;letter-spacing: -0.3px;">74. Parentesco</div>    

                            <div style="position: absolute; font-family: sans-serif; font-size: 7.6px; color: #000; left: 37px; top: 953px;letter-spacing: -0.31px;">Certifico que durante el año gravable de <?php echo $formulario->anio ?>:</div>    
                            <div style="position: absolute; font-family: sans-serif; font-size: 8px; color: #000; left: 592px; top: 951px;letter-spacing: -0.01px;">Firma del Trabajador o Pensionado</div>    

                            <div style="position: absolute; font-family: sans-serif; font-size: 7px; color: #000; left: 38px; top: 965px;letter-spacing: -0.3px;"><?php echo $formulario->punto1 ?></div>    
                            <div style="position: absolute; font-family: sans-serif; font-size: 7px; color: #000; left: 38px; top: 973px;letter-spacing: -0.3px;"><?php echo $formulario->punto2 ?></div>    
                            <div style="position: absolute; font-family: sans-serif; font-size: 7px; color: #000; left: 38px; top: 981px;letter-spacing: -0.3px;"><?php echo $formulario->punto3 ?></div>    
                            <div style="position: absolute; font-family: sans-serif; font-size: 7px; color: #000; left: 38px; top: 989px;letter-spacing: -0.3px;"><?php echo $formulario->punto4 ?></div>    
                            <div style="position: absolute; font-family: sans-serif; font-size: 7px; color: #000; left: 38px; top: 997px;letter-spacing: -0.3px;"><?php echo $formulario->punto5 ?></div>    
                            <div style="position: absolute; font-family: sans-serif; font-size: 7px; color: #000; left: 38px; top: 1005px;letter-spacing: -0.3px;"><?php echo $formulario->punto6 ?></div>    
                            <div style="position: absolute; font-family: sans-serif; font-size: 7px; color: #000; left: 38px; top: 1015px;letter-spacing: -0.3px;">Por lo tanto, manifiesto que no estoy obligado a presentar declaración de renta y complementario por el año gravable <?php echo $formulario->anio ?>.</div>    

                            <div style="position: absolute; font-family: sans-serif; font-size: 8px; color: #000; left: 45px; top: 1031px;letter-spacing: 0.03px;"><b>NOTA:</b> este certificado sustituye para todos los efectos legales la declaración de Renta y Complementario para el trabajador o pensionado que lo firme. Para aquellos trabajadores independientes inscritos<br>en el Régimen Simple de Tributación la declaración de renta y complementario es reemplazada por la declaración anual consolidada del Régimen Simple de Tributación (SIMPLE).</div>    
                            
                            

                            

                            <div class="campo_texto" style="position: absolute; left: 60px; top: 127px;  width: 188px; height: 15px;"><?php echo $empresa->documento; ?></div>
                            <div class="campo_texto" style="position: absolute; left: 255px; top: 127px;  width: 10px; height: 15px;"><?php echo $empresa->digitoVerificacion; ?></div>
                            <div class="campo_texto" style="position: absolute; left: 60px; top: 160px;  width: 700px; height: 15px;"><?php echo $empresa->razonSocial; ?></div>
                            <div class="campo_texto" style="position: absolute; left: 55px; top: 198px;  width: 30px; height: 15px; font-size: 10px;"><?php echo $datosPersonales->sigla220; ?></div>
                            <div class="campo_texto" style="position: absolute; left: 123px; top: 195px;  width: 180px; height: 15px;"><?php echo $datosPersonales->numeroIdentificacion; ?></div>
                            <div class="campo_texto" style="position: absolute; left: 320px; top: 195px;  width: 100px; height: 15px;"><?php echo $datosPersonales->primerApellido; ?></div>
                            <div class="campo_texto" style="position: absolute; left: 435px; top: 195px;  width: 110px; height: 15px;"><?php echo $datosPersonales->segundoApellido; ?></div>
                            <div class="campo_texto" style="position: absolute; left: 555px; top: 195px;  width: 110px; height: 15px;"><?php echo $datosPersonales->primerNombre; ?></div>
                            <div class="campo_texto" style="position: absolute; left: 675px; top: 195px;  width: 110px; height: 15px;"><?php echo $datosPersonales->segundoNombre; ?></div>

                            <div class="campo_texto" style="position: absolute; left: 85px; top: 226px;  width: 30px; height: 15px;  font-size: 11px;"><?php echo date("Y", strtotime($fechaPeriodoCer)); ?></div>
                            <div class="campo_texto" style="position: absolute; left: 115px; top: 226px;  width: 20px; height: 15px;  font-size: 11px;"><?php echo date("m", strtotime($fechaPeriodoCer)); ?></div>
                            <div class="campo_texto" style="position: absolute; left: 135px; top: 226px;  width: 20px; height: 15px;  font-size: 11px;"><?php echo date("d", strtotime($fechaPeriodoCer)); ?></div>
                            
                            <div class="campo_texto" style="position: absolute; left: 225px; top: 226px;  width: 30px; height: 15px;  font-size: 11px;"><?php echo date("Y", strtotime($fechaPeriodoCer)); ?></div>
                            <div class="campo_texto" style="position: absolute; left: 255px; top: 226px;  width: 20px; height: 15px;  font-size: 11px;"><?php echo date("12", strtotime($fechaPeriodoCer)); ?></div>
                            <div class="campo_texto" style="position: absolute; left: 275px; top: 226px;  width: 20px; height: 15px;  font-size: 11px;"><?php echo date("30", strtotime($fechaPeriodoCer)); ?></div>

                            <div class="campo_texto" style="position: absolute; left: 355px; top: 226px;  width: 30px; height: 15px;  font-size: 11px;"><?php echo date("Y", strtotime($req->fechaExp)); ?></div>
                            <div class="campo_texto" style="position: absolute; left: 385px; top: 226px;  width: 20px; height: 15px;  font-size: 11px;"><?php echo date("m", strtotime($req->fechaExp)); ?></div>
                            <div class="campo_texto" style="position: absolute; left: 405px; top: 226px;  width: 20px; height: 15px;  font-size: 11px;"><?php echo date("d", strtotime($req->fechaExp)); ?></div>
                            
                            <div class="campo_texto" style="position: absolute; left: 460px; top: 228px;  width: 200px; height: 15px;  font-size: 11px;"><?php echo $empresa->ciudadUb;  ?></div>
                            <div class="campo_texto" style="position: absolute; left: 700px; top: 228px;  width: 20px; height: 15px;  font-size: 11px;"><?php echo substr($empresa->idubicacion, 2, 2);  ?></div>
                            <div class="campo_texto" style="position: absolute; left: 750px; top: 228px;  width: 20px; height: 15px;  font-size: 11px;"><?php echo substr($empresa->idubicacion, 4, 3);  ?></div>

                            <div class="campo_texto" style="text-align:right; position: absolute; left: 645px; top: 274px;  width: 140px; height: 15px;  font-size: 11px;"><?php echo number_format($sumaItems37->suma,0, ",", ".");  ?></div>
                            <div class="campo_texto" style="text-align:right; position: absolute; left: 645px; top: 290px;  width: 140px; height: 15px;  font-size: 11px;">0</div>
                            <div class="campo_texto" style="text-align:right; position: absolute; left: 645px; top: 306px;  width: 140px; height: 15px;  font-size: 11px;">0</div>
                            <div class="campo_texto" style="text-align:right; position: absolute; left: 645px; top: 323px;  width: 140px; height: 15px;  font-size: 11px;"><?php echo number_format($sumaItems40->suma,0, ",", ".");  ?></div>
                            <div class="campo_texto" style="text-align:right; position: absolute; left: 645px; top: 339px;  width: 140px; height: 15px;  font-size: 11px;"><?php echo number_format($sumaItems41->suma,0, ",", ".");  ?></div>
                            <div class="campo_texto" style="text-align:right; position: absolute; left: 645px; top: 355px;  width: 140px; height: 15px;  font-size: 11px;"><?php echo number_format($sumaItems42->suma,0, ",", ".");  ?></div>
                            <div class="campo_texto" style="text-align:right; position: absolute; left: 645px; top: 371px;  width: 140px; height: 15px;  font-size: 11px;"><?php echo number_format($sumaItems43->suma,0, ",", ".");  ?></div>
                            <div class="campo_texto" style="text-align:right; position: absolute; left: 645px; top: 387px;  width: 140px; height: 15px;  font-size: 11px;">0</div>
                            <div class="campo_texto" style="text-align:right; position: absolute; left: 645px; top: 403px;  width: 140px; height: 15px;  font-size: 11px;"><?php echo number_format($sumaItems45->suma,0, ",", ".");  ?></div>
                            <div class="campo_texto" style="text-align:right; position: absolute; left: 645px; top: 419px;  width: 140px; height: 15px;  font-size: 11px;"><?php echo number_format($sumaItems46->suma,0, ",", ".");  ?></div>
                            <div class="campo_texto" style="text-align:right; position: absolute; left: 645px; top: 435px;  width: 140px; height: 15px;  font-size: 11px;">0</div>
                            <div class="campo_texto" style="text-align:right; position: absolute; left: 645px; top: 451px;  width: 140px; height: 15px;  font-size: 11px;"><?php echo number_format($sumaItems37->suma + $sumaItems40->suma +  $sumaItems41->suma + $sumaItems42->suma + $sumaItems45->suma + $sumaItems46->suma,0, ",", "."); ?></div>

                            <div class="campo_texto" style="text-align:right; position: absolute; left: 645px; top: 483px;  width: 140px; height: 15px;  font-size: 11px;"><?php echo number_format($sumaItems49->suma*-1,0, ",", ".");  ?></div>
                            <div class="campo_texto" style="text-align:right; position: absolute; left: 645px; top: 501px;  width: 140px; height: 15px;  font-size: 11px;"><?php echo number_format($sumaItems50->suma*-1,0, ",", ".");  ?></div>
                            <div class="campo_texto" style="text-align:right; position: absolute; left: 645px; top: 517px;  width: 140px; height: 15px;  font-size: 11px;"><?php echo number_format($sumaItems51->suma*-1,0, ",", ".");  ?></div>
                            <div class="campo_texto" style="text-align:right; position: absolute; left: 645px; top: 533px;  width: 140px; height: 15px;  font-size: 11px;"><?php echo number_format($sumaItems52->suma*-1,0, ",", ".");  ?></div>
                            <div class="campo_texto" style="text-align:right; position: absolute; left: 645px; top: 550px;  width: 140px; height: 15px;  font-size: 11px;"><?php echo number_format($sumaItems53->suma*-1,0, ",", ".");  ?></div>

                            <div class="campo_texto" style="position: absolute; left: 35px; top: 580px;  width: 740px; height: 15px;  font-size: 11px;"><?php echo $req->agenteRetenedor;  ?></div>

                            <?php
                                if(isset($dependiente)){
                                    ?>
                                <div class="campo_texto" style="position: absolute; left: 40px; top: 933px;  width: 150px; height: 15px;  font-size: 11px;"><?php echo $dependiente->numIdentificacion;  ?></div>
                                <div class="campo_texto" style="position: absolute; left: 200px; top: 933px;  width: 470px; height: 15px;  font-size: 11px;"><?php echo $dependiente->nombre;  ?></div>
                                <div class="campo_texto" style="position: absolute; left: 690px; top: 933px;  width: 100px; height: 15px;  font-size: 11px;"><?php echo $dependiente->paren;  ?></div>
                                    <?php
                                }
                            ?>
                        </div>
                        <?php 
                            if($row < (sizeof($empleados) - 1)){
                        ?>
                            <div class="page_break"></div>
                        <?php
                        }
                        ?>
                        

                        <?php 
                        
                        }
                        ?>
                    </body> 
                </html>

            <?php
            $html = ob_get_clean();
            //echo $html;
        
        
            $dompdf->loadHtml($html ,'UTF-8');
        
            // (Optional) Setup the paper size and orientation
            $dompdf->setPaper('letter', 'portrait');
            // Render the HTML as PDF
            $dompdf->render();

            // Output the generated PDF to Browser
            $dompdf->stream("Formulario 220.pdf", array('compress' => 1, 'Attachment' => 0));

        }
        else{
            $dataUsu = UsuarioController::dataAdminLogueado();
            Log::channel('gesath')->info("El usuario ".$dataUsu->email.", generó un reporte excel del formulario 220");
            $arrDatos = array();
            $empleados = DB::table("empleado","e")
            ->select("e.*","p.idPeriodo", "p.fechaInicio as fechaInicioPeriodo","n.fkEmpresa as fkEmpresaNomina")
            ->join("periodo as p","p.fkEmpleado", "=","e.idempleado")
            ->join("nomina as n","n.idNomina", "=","p.fkNomina")
            ->where("n.fkEmpresa","=",$req->empresa)
            ->where("p.fkEstado","=","1");
            if(isset($req->infoNomina)){
                $empleados = $empleados->where("p.fkNomina","=",$req->infoNomina);    
            }
            if(isset($req->idEmpleado)){
                $empleados = $empleados->where("e.idempleado","=", $req->idEmpleado);
            }
            $empleados = $empleados->get();     


            $arrFila = array(
                "Campo 4",
                "Campo 5",
                "Campo 6",
                "Campo 7",
                "Campo 8",
                "Campo 9",
                "Campo 10",
                "Campo 11",
                "Campo 24",
                "Campo 25",
                "Campo 26",
                "Campo 27",
                "Campo 28",
                "Campo 29",
                "Campo 30",
                "Campo 31",
                "Campo 32",
                "Campo 33",
                "Campo 34",
                "Campo 35",
                "Campo 36",
                "Campo 37",
                "Campo 38",
                "Campo 39",
                "Campo 40",
                "Campo 41",
                "Campo 42",
                "Campo 43",
                "Campo 44",
                "Campo 45",
                "Campo 46",
                "Campo 47",
                "Campo 48",
                "Campo 49",
                "Campo 50",
                "Campo 51",
                "Campo 52",
                "Campo 53",
                "Agente Retenedor",
                "Campo 54",
                "Campo 55",
                "Campo 56",
                "Campo 57",
                "Campo 58",
                "Campo 59",
                "Campo 60",
                "Campo 61",
                "Campo 62",
                "Campo 63",
                "Campo 64",
                "Campo 65",
                "Campo 66",
                "Campo 67",
                "Campo 68",
                "Campo 69",
                "Campo 70",
                "Campo 71",
                "Campo 72",
                "Campo 73",
                "Campo 74"
            );

            array_push($arrDatos, $arrFila);
            
            foreach($empleados as $row => $empleado){

                $periodoActivoReintegro = DB::table("periodo")
                ->where("idPeriodo", "=", $empleado->idPeriodo)
                ->first();


                $fechaPeriodoCer = $req->anio."-01-01";
                if(strtotime($fechaPeriodoCer) < strtotime($empleado->fechaInicioPeriodo)){
                    $fechaPeriodoCer = $empleado->fechaInicioPeriodo;
                }
                $datosPersonales = DB::table("datospersonales", "dp")
                ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion")
                ->where("dp.idDatosPersonales","=", $empleado->fkDatosPersonales)->first();

                $empresa = DB::table("empresa","e")
                ->select("e.*","u.nombre as ciudadUb","u.idubicacion" )
                ->join("ubicacion as u", "u.idubicacion", "=","e.fkUbicacion")
                ->where("idempresa","=", $empleado->fkEmpresaNomina)->first();
                



                



                $sumaItems37 = DB::table("item_boucher_pago","ibp")
                ->selectRaw("sum(ibp.valor) as suma")
                ->join("boucherpago as bp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                ->join("liquidacionnomina as ln","bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto", "=","ibp.fkConcepto")
                ->whereRaw("YEAR(ln.fechaLiquida) = '".$req->anio."'")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("gcc.fkGrupoConcepto","=","30")->first();


                $sumaItems40 = DB::table("item_boucher_pago","ibp")
                ->selectRaw("sum(ibp.valor) as suma")
                ->join("boucherpago as bp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                ->join("liquidacionnomina as ln","bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto", "=","ibp.fkConcepto")
                ->whereRaw("YEAR(ln.fechaLiquida) = '".$req->anio."'")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("gcc.fkGrupoConcepto","=","31")
                ->first();
                
                $sumaItems41 = DB::table("item_boucher_pago","ibp")
                ->selectRaw("sum(ibp.valor) as suma")
                ->join("boucherpago as bp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                ->join("liquidacionnomina as ln","bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto", "=","ibp.fkConcepto")
                ->whereRaw("YEAR(ln.fechaLiquida) = '".$req->anio."'")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("gcc.fkGrupoConcepto","=","32")->first();

                $sumaItems42 = DB::table("item_boucher_pago","ibp")
                ->selectRaw("sum(ibp.valor) as suma")
                ->join("boucherpago as bp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                ->join("liquidacionnomina as ln","bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto", "=","ibp.fkConcepto")
                ->whereRaw("YEAR(ln.fechaLiquida) = '".$req->anio."'")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("gcc.fkGrupoConcepto","=","33")->first();

                $sumaItems45 = DB::table("item_boucher_pago","ibp")
                ->selectRaw("sum(ibp.valor) as suma")
                ->join("boucherpago as bp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                ->join("liquidacionnomina as ln","bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto", "=","ibp.fkConcepto")
                ->whereRaw("YEAR(ln.fechaLiquida) = '".$req->anio."'")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("gcc.fkGrupoConcepto","=","34")->first();
                
                $sumaItems46 = DB::table("item_boucher_pago","ibp")
                ->selectRaw("sum(ibp.valor) as suma")
                ->join("boucherpago as bp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                ->join("liquidacionnomina as ln","bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto", "=","ibp.fkConcepto")
                ->whereRaw("YEAR(ln.fechaLiquida) = '".$req->anio."'")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("gcc.fkGrupoConcepto","=","35")->first();
                
                $sumaItems49 = DB::table("item_boucher_pago","ibp")
                ->selectRaw("sum(ibp.valor) as suma")
                ->join("boucherpago as bp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                ->join("liquidacionnomina as ln","bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto", "=","ibp.fkConcepto")
                ->whereRaw("YEAR(ln.fechaLiquida) = '".$req->anio."'")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("gcc.fkGrupoConcepto","=","36")->first();
                
                $sumaItems50 = DB::table("item_boucher_pago","ibp")
                ->selectRaw("sum(ibp.valor) as suma")
                ->join("boucherpago as bp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                ->join("liquidacionnomina as ln","bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto", "=","ibp.fkConcepto")
                ->whereRaw("YEAR(ln.fechaLiquida) = '".$req->anio."'")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("gcc.fkGrupoConcepto","=","37")->first();
                
                $sumaItems51 = DB::table("item_boucher_pago","ibp")
                ->selectRaw("sum(ibp.valor) as suma")
                ->join("boucherpago as bp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                ->join("liquidacionnomina as ln","bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto", "=","ibp.fkConcepto")
                ->whereRaw("YEAR(ln.fechaLiquida) = '".$req->anio."'")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("gcc.fkGrupoConcepto","=","38")->first();

                $sumaItems52 = DB::table("item_boucher_pago","ibp")
                ->selectRaw("sum(ibp.valor) as suma")
                ->join("boucherpago as bp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                ->join("liquidacionnomina as ln","bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto", "=","ibp.fkConcepto")
                ->whereRaw("YEAR(ln.fechaLiquida) = '".$req->anio."'")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("gcc.fkGrupoConcepto","=","39")->first();

                $sumaItems53 = DB::table("item_boucher_pago","ibp")
                ->selectRaw("sum(ibp.valor) as suma")
                ->join("boucherpago as bp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                ->join("liquidacionnomina as ln","bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto", "=","ibp.fkConcepto")
                ->whereRaw("YEAR(ln.fechaLiquida) = '".$req->anio."'")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("gcc.fkGrupoConcepto","=","40")->first();
                
                $dependiente = DB::table("beneficiotributario","bt")
                ->select("nf.numIdentificacion", "p.nombre as paren", "nf.nombre")
                ->join("nucleofamiliar as nf", "nf.idNucleoFamiliar","=","bt.fkNucleoFamiliar")
                ->join("parentesco as p","p.idParentesco","=","nf.fkParentesco")
                ->where("bt.fkEmpleado", "=", $empleado->idempleado)
                ->where("bt.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("bt.fechaVigencia", ">", date("Y-m-d", strtotime($fechaPeriodoCer)))
                ->first();


                $arrFila = array(
                    "",
                    $empresa->documento,
                    $empresa->digitoVerificacion,
                    "",
                    "",
                    "",
                    "",//10
                    $empresa->razonSocial,
                    $datosPersonales->sigla220,
                    $datosPersonales->numeroIdentificacion,
                    $datosPersonales->primerApellido,
                    $datosPersonales->segundoApellido,
                    $datosPersonales->primerNombre,
                    $datosPersonales->segundoNombre,
                    date("Y-m-d", strtotime($fechaPeriodoCer)),
                    date("Y-12-30", strtotime($fechaPeriodoCer)),
                    date("Y-m-d", strtotime($req->fechaExp)),
                    $empresa->ciudadUb,
                    substr($empresa->idubicacion, 2, 2),
                    substr($empresa->idubicacion, 4, 3),
                    "",
                    number_format($sumaItems37->suma,0, ",", "."),
                    0,
                    0,
                    number_format($sumaItems40->suma,0, ",", "."),
                    number_format($sumaItems41->suma,0, ",", "."),
                    number_format($sumaItems42->suma,0, ",", "."),
                    0,
                    0,
                    number_format($sumaItems45->suma,0, ",", "."),
                    number_format($sumaItems46->suma,0, ",", "."),
                    0,
                    number_format($sumaItems37->suma + $sumaItems40->suma +  $sumaItems41->suma + $sumaItems42->suma + $sumaItems45->suma + $sumaItems46->suma,0, ",", "."),
                    number_format($sumaItems49->suma*-1,0, ",", "."),
                    number_format($sumaItems50->suma*-1,0, ",", "."),
                    number_format($sumaItems51->suma*-1,0, ",", "."),
                    number_format($sumaItems52->suma*-1,0, ",", "."),
                    number_format($sumaItems53->suma*-1,0, ",", "."),
                    $req->agenteRetenedor,
                    0,
                    0,
                    0,
                    0,
                    0,
                    0,
                    0,
                    0,
                    0,
                    0,
                    0,
                    0,
                    0,
                    0,
                    0,
                    0,
                    0,
                    0,
                    (isset($dependiente) ? $dependiente->numIdentificacion : ""),
                    (isset($dependiente) ? $dependiente->nombre : ""),
                    (isset($dependiente) ? $dependiente->paren : "")
    
                );

                array_push($arrDatos, $arrFila);
            }
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Description: File Transfer');
            header('Content-Disposition: attachment; filename=Formulario220.csv');
    
            $csv = Writer::createFromFileObject(new SplTempFileObject());
            $csv->setDelimiter(';');
            $csv->insertAll($arrDatos);
            $csv->setOutputBOM(Reader::BOM_UTF8);
            $csv->output('Formulario220.csv');
        }




    }


    public function indexNovedades(){
        $dataUsu = UsuarioController::dataAdminLogueado();
        $empresas = DB::table("empresa", "e");
        if(isset($dataUsu) && $dataUsu->fkRol == 2){            
            $empresas = $empresas->whereIn("idempresa", $dataUsu->empresaUsuario);
        }
        $empresas = $empresas->orderBy("razonSocial")->get();
        
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu 'Reporte de novedades'");

        return view('/reportes.novedades',[
            "empresas" => $empresas,
            "dataUsu" => $dataUsu
        ]);
    }
    public function generarNovedades(Request $req){
        $novedades = DB::table("novedad","n")
        ->select("*", "cargo2.nombreCargo as nombreCargoPeriodo",
                "a.cantidadDias","c.nombre as nombreConcepto","nom.nombre as nombreNomina", 
                "ti.nombre as tipoidentificacion", "ta.nombre as tipoAfiliacion", 
                "t.razonSocial as nombreTercero", "l.fechaInicial as licenciaFechaInicial"
                , "l.fechaFinal as licenciaFechaFinal", "l.numDias as licenciaNumDias", 
                "h.cantidadHoras as horasCantidadHoras", "mr.nombre as motivoRetiro", 
                "v.fechaInicio as vacacionesFechaInicio", "v.fechaFin as vacacionesFechaFin", "emp.razonSocial as razonSocialEmp")
        ->selectRaw("(select cc2.nombre from centrocosto as cc2 where cc2.idcentroCosto 
        in(Select ecc.fkCentroCosto from empleado_centrocosto as ecc where 
        ecc.fkEmpleado = e.idempleado and ecc.fkPeriodoActivo = n.fkPeriodoActivo)
        limit 0,1) as centroCosto")
        ->join("ausencia as a","a.idAusencia","=", "n.fkAusencia","left")
        ->join("incapacidad as i","i.idIncapacidad","=", "n.fkIncapacidad","left")
        ->join("tipoafilicacion as ta","ta.idTipoAfiliacion","=", "i.fkTipoAfilicacion","left")
        ->join("tercero as t","t.idTercero","=", "i.fkTercero","left")
        ->join("licencia as l","l.idLicencia","=", "n.fkLicencia","left")
        ->join("horas_extra as h","h.idHoraExtra","=", "n.fkHorasExtra","left")
        ->join("retiro as r","r.idRetiro","=", "n.fkRetiro","left")
        ->join("motivo_retiro as mr","mr.idMotivoRetiro","=", "r.fkMotivoRetiro","left")        
        ->join("vacaciones as v","v.idVacaciones","=", "n.fkVacaciones","left")
        ->join("otra_novedad as o","o.idOtraNovedad","=", "n.fkOtros","left")
        ->join("concepto as c","c.idconcepto","=", "n.fkConcepto","left")
        ->join("nomina as nom","nom.idNomina","=", "n.fkNomina","left")
        ->join("empleado as e","e.idempleado","=", "n.fkEmpleado","left")
        ->join("periodo as p","p.idPeriodo","=", "n.fkPeriodoActivo","left")
        ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales", "left")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion","left")
        ->join("empresa as emp","emp.idEmpresa","=", "nom.fkEmpresa","left")
        ->join("cargo","cargo.idCargo","=", "e.fkCargo","left")
        ->join("cargo as cargo2","cargo2.idCargo","=", "p.fkCargo","left")        
        ->whereBetween("n.fechaRegistro",[$req->fechaIni, $req->fechaFin])
        ->where("n.fkEstado","=","8");
        if(isset($req->nomina)){
            $novedades = $novedades->where("nom.idNomina", "=",$req->nomina);
        }
        if(isset($req->empresa)){
            $novedades = $novedades->where("nom.fkEmpresa", "=",$req->empresa);
        }
        $novedades = $novedades->get();

        
        $arrFila1=  [
            "Fecha Registro",
            "Empresa",
            "Nomina",
            "Centro Costo",
            "Cargo",
            "Tipo Documento",
            "Documento",
            "Empleado",
            "Tipo Novedad",
            "Tipo Reporte",            
            "concepto",
            "fechaAusenciaInicial",
            "fechaAusenciaFinal",
            "diasAusencia",
            "diasIncapacidad",
            "fechaIncapacidadInicial",
            "fechaIncapacidadFinal",
            "fechaRealIncapacidadInicial",
            "fechaRealIncapacidadFinal",
            "pagoTotalIncapacidad",
            "idCodigoDiagnostico",
            "numeroIncapacidad",
            "tipoAfiliacion",
            "naturaleza",
            "tipoIncapacidad",
            "diasLicencia",
            "fechaInicialLicencia",
            "fechaFinalLicencia",
            "HoraExtraInicial",
            "HoraExtraFinal",
            "cantidaHoras",
            "fechaRetiro",
            "fechaRetiroReal",
            "motivoRetiro",
            "indemnizacion",
            "fechaInicialVacaciones",
            "fechaFinalVacaciones",
            "diasVacacionesCompletos",
            "diasVacacionesHabiles",
            "pagoAnticipadoVacaciones",
            "valorOtros",
            "SumaResta"
        ];

        $arrDatos = array();
        array_push($arrDatos, $arrFila1);
        foreach($novedades as $novedad){

            $arrFila=  [
                $novedad->fechaRegistro,
                $novedad->razonSocialEmp,
                $novedad->nombreNomina,
                $novedad->centroCosto,
                ($novedad->nombreCargoPeriodo ?? $novedad->nombreCargo),
                $novedad->tipoidentificacion,
                $novedad->numeroIdentificacion,
                $novedad->primerApellido." ".$novedad->segundoApellido." ".$novedad->primerNombre." ".$novedad->segundoNombre,
                $novedad->fkTipoNovedad,
                $novedad->fkTipoReporte,                
                $novedad->nombreConcepto,                
                $novedad->fechaInicio,
                $novedad->fechaFin,
                $novedad->cantidadDias,                
                $novedad->numDias,
                $novedad->fechaInicial,
                $novedad->fechaFinal,
                $novedad->fechaRealI,
                $novedad->fechaRealF,
                ($novedad->pagoTotal == "1" ? "SI" : "NO"),
                $novedad->fkCodDiagnostico,
                $novedad->numIncapacidad,
                $novedad->tipoAfiliacion,
                $novedad->naturaleza,
                $novedad->tipoIncapacidad,
                $novedad->licenciaNumDias,
                $novedad->licenciaFechaInicial,
                $novedad->licenciaFechaFinal,
                $novedad->fechaHoraInicial,
                $novedad->fechaHoraFinal,
                $novedad->horasCantidadHoras,
                $novedad->fecha,
                $novedad->fechaReal,
                $novedad->motivoRetiro,
                ($novedad->indemnizacion == "1" ? "SI" : "NO"), 
                $novedad->vacacionesFechaInicio,
                $novedad->vacacionesFechaFin,
                $novedad->diasCompensar,
                $novedad->diasCompletos,
                ($novedad->pagoAnticipado == "1" ? "SI" : "NO"),
                $novedad->valor,
                ($novedad->sumaResta == "1" ? "Suma" : "Resta")
            ];
            array_push($arrDatos, $arrFila);
        }
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", generó un reporte de novedades");

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename=ReporteNovedades.csv');

        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->setDelimiter(';');
        $csv->insertAll($arrDatos);
        $csv->setOutputBOM(Reader::BOM_UTF8);
        $csv->output('ReporteNovedades.csv');





    }

    public function reporteador(){


        $reportes = DB::table("reporte", "r")
        ->orderBy("r.nombre")->get();

        $dataUsu = UsuarioController::dataAdminLogueado();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu 'Reporteador'");

        return view("/reportes.reporteador", ["reportes" => $reportes, "dataUsu" => $dataUsu]);


        
        //Tercero Compañia

    }
    public function reporteadorGetFormAdd(){
        $tipo_reportes = DB::table("tipo_reporte", "tr")
        ->orderBy("tr.nombre")->get();

        return view("/reportes.reporteadorAdd", ["tipo_reportes" => $tipo_reportes]);
    }
    public function reporteadorGetItemsxReporte($idTipoReporte){

        $items_tipo_reporte = DB::table("item_tipo_reporte", "itr")
        ->where("itr.fkTipoReporte","=",$idTipoReporte)
        ->get();

        return view("/reportes.reporteadorSelect", ["items_tipo_reporte" => $items_tipo_reporte]);
        
    }
    public function crearReporte(Request $req){
        $idReporte = DB::table("reporte")->insertGetId([
            "nombre" => $req->nombre,
            "fkTipoReporte" => $req->tipoReporte,
        ], "idReporte");


        $pos = 0;
        if(isset($req->opcionesSeleccionadas)){
            foreach($req->opcionesSeleccionadas as $opcionesS){
                DB::table("reporte_item")->insert([
                    "fkReporte" => $idReporte,
                    "fkItemTipoReporte" => $opcionesS,
                    "posicion" => $pos
                ]);
                $pos++;
            }
        }
        
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", creó un nuevo reporte en el 'Reporteador'");

        return response()->json([
            "success" => true
        ]);

    }
    public function reporteadorGenerar($idReporte){

        $reporte = DB::table("reporte", "r")
        ->select("r.*","tr.nombre as tipo_reporte")
        ->join("tipo_reporte as tr", "tr.idTipoReporte", "=","r.fkTipoReporte")
        ->where("r.idReporte","=",$idReporte)->first();
        
        $itemsReporte = DB::table("reporte_item", "ir")
        ->join("item_tipo_reporte as itr","itr.IdItemTipoReporte", "=", "ir.fkItemTipoReporte")
        ->where("ir.fkReporte","=",$idReporte)->orderBy("ir.posicion")->get();
        
        $dataUsu = UsuarioController::dataAdminLogueado();
      
        return view('/reportes.generarReporte', [
            "reporte" => $reporte,
            "itemsReporte" => $itemsReporte,
            "dataUsu" => $dataUsu
        ]);

    }
    public function reporteadorGetFormFiltro($idReporteItem){
        $itemReporte = DB::table("item_tipo_reporte","itr")
        ->join("reporte_item as ri", "ri.fkItemTipoReporte", "=","itr.IdItemTipoReporte")
        ->where("ri.idReporteItem","=",$idReporteItem)->first();

        $estados = array();
        if($itemReporte->fkItemTipoReporte == "41" || $itemReporte->fkItemTipoReporte == "117"){
            $estados = DB::table("estado")->whereIn("idestado",["1","2","3"])->get();
        }
    


        $OperadorComparacion = array();
        if($itemReporte->tipo == "texto"){
            $OperadorComparacion = [
                "LIKE",
                "=",
                ">",
                ">=",
                "<",
                "<=",
                "<>"
            ];
        }
        else if($itemReporte->tipo == "fecha"){
            $OperadorComparacion = [
                "=",
                ">",
                ">=",
                "<",
                "<=",
                "<>"
            ];
        }
        else if($itemReporte->tipo == "bool"){
            $OperadorComparacion = [
                "=",
                "<>"
            ];
        }
        return view("/reportes.reporteadorFiltro",[
            "OperadorComparacion" => $OperadorComparacion,
            "itemReporte" => $itemReporte,
            "estados" => $estados,
            "idReporteItem" => $itemReporte->fkItemTipoReporte
        ]);


    }
    public function reporteadorGetFormEdit($idReporte){
        $tipo_reportes = DB::table("tipo_reporte", "tr")
        ->orderBy("tr.nombre")->get();

        $reporte = DB::table("reporte", "r")
        ->select("r.*","tr.nombre as tipo_reporte")
        ->join("tipo_reporte as tr", "tr.idTipoReporte", "=","r.fkTipoReporte")
        ->where("r.idReporte","=",$idReporte)->first();

        $items_tipo_reporte = DB::table("item_tipo_reporte", "itr")
        ->where("itr.fkTipoReporte","=",$reporte->fkTipoReporte)
        ->whereRaw("itr.IdItemTipoReporte not in(select ri.fkItemTipoReporte from reporte_item as ri WHERE ri.fkReporte = ".$idReporte.")")
        ->get();

        $itemsReporte = DB::table("reporte_item", "ir")
        ->join("item_tipo_reporte as itr","itr.IdItemTipoReporte", "=", "ir.fkItemTipoReporte")
        ->where("ir.fkReporte","=",$idReporte)
        ->orderBy("ir.posicion")->get();


        return view("/reportes.reporteadorEdit", [
            "tipo_reportes" => $tipo_reportes, 
            "reporte" => $reporte,
            "items_tipo_reporte" => $items_tipo_reporte,
            "items_tipo_reporte_select" => $itemsReporte
        ]);
    }
    public function modificarReporte(Request $req){

        $idReporte = $req->idReporte;

        DB::table("reporte")->where("idReporte","=",$idReporte)->update([
            "nombre" => $req->nombre,
            "fkTipoReporte" => $req->tipoReporte,
        ]);


        $pos = 0;
        if(isset($req->opcionesSeleccionadas)){
            DB::table("reporte_item")->where("fkReporte", "=", $idReporte)->delete();

            foreach($req->opcionesSeleccionadas as $opcionesS){
                DB::table("reporte_item")->insert([
                    "fkReporte" => $idReporte,
                    "fkItemTipoReporte" => $opcionesS,
                    "posicion" => $pos
                ]);
                $pos++;
            }
        }
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", modificó un reporte en el 'Reporteador'");
        return response()->json([
            "success" => true
        ]);
    }
    public function generarFinalReporteador(Request $req){


        $reporte = DB::table("reporte", "r")->where("r.idReporte","=",$req->idReporte)->first();
        if($reporte->fkTipoReporte == "1"){
            $consulta = DB::table("empleado", "e");
            
            $itemsReporte = DB::table("reporte_item", "ir")
            ->join("item_tipo_reporte as itr","itr.IdItemTipoReporte", "=", "ir.fkItemTipoReporte")
            ->where("ir.fkReporte","=",$reporte->idReporte)
            ->orderBy("ir.posicion")->get();
            $arrSelect = [];
            foreach($itemsReporte as $itemReporte){
                if($itemReporte->tipo == "bool"){
                    array_push($arrSelect, "IF(".$itemReporte->campo."='1','SI','NO') as '".$itemReporte->nombre."'");
                    //IF(periodo.fkEstado='2',cargoPeriodo.nombreCargo, cargo.nombreCargo)
                }
                else{
                    array_push($arrSelect, $itemReporte->campo." as '".$itemReporte->nombre."'");
                }
                
            }
            $sql = implode(",",$arrSelect);
            $consulta = $consulta->selectRaw($sql);
            $consulta = $consulta
            ->join("periodo","periodo.fkEmpleado", "=","e.idempleado","left")
            ->joinSub("(
                SELECT    MAX(idCambioSalario) max_id, fechaCambio, valorNuevo,valorAnterior, fkEmpleado, fkPeriodoActivo 
                FROM      cambiosalario 
                GROUP BY  fkEmpleado
            )","cambio_salario_nuevo","cambio_salario_nuevo.fkPeriodoActivo", "=","periodo.idPeriodo","left")            
            ->join("cargo","cargo.idCargo", "=","e.fkCargo","left")
            ->join("cargo as cargoPeriodo","cargoPeriodo.idCargo", "=","periodo.fkCargo","left")
            ->join("tipoidentificacion as tio","tio.idtipoIdentificacion", "=", "e.fkTipoOtroDocumento","left")
            ->join("tipoidentificacion as tioPeriodo","tioPeriodo.idtipoIdentificacion", "=", "periodo.fkTipoOtroDocumento","left")
            ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales","left")
            ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion","left")
            ->join("genero as g","g.idGenero", "=", "dp.fkGenero","left")
            ->join("estadocivil as estCiv","estCiv.idEstadoCivil", "=", "dp.fkEstadoCivil","left")
            ->join("tipo_vivienda","tipo_vivienda.idTipoVivienda", "=", "dp.fkTipoVivienda","left")       
            ->join("nomina as n","n.idNomina", "=","periodo.fkNomina","left")
            ->join("empresa as emp","emp.idempresa", "=","n.fkEmpresa","left")
            //->join('centrocosto AS cc','cc.fkEmpresa', '=', 'n.fkEmpresa',"left")
            ->join('estado AS est','est.idestado', '=', 'periodo.fkEstado', "left")
            ->join('ubicacion AS uLabora','uLabora.idubicacion', '=', 'e.fkUbicacionLabora',"left")
            ->join('ubicacion AS uLaboraLoc','uLaboraLoc.idubicacion', '=', 'e.fkLocalidad',"left")
            ->join('ubicacion AS uLaboraPeriodo','uLaboraPeriodo.idubicacion', '=', 'periodo.fkUbicacionLabora',"left")
            ->join('ubicacion AS uLaboraLocPeriodo','uLaboraLocPeriodo.idubicacion', '=', 'periodo.fkLocalidad',"left")
            ->join('ubicacion AS uExpDoc','uExpDoc.idubicacion', '=', 'dp.fkUbicacionExpedicion',"left")
            ->join('ubicacion AS uNacimiento','uNacimiento.idubicacion', '=', 'dp.fkUbicacionNacimiento',"left")
            ->join('ubicacion AS uResidencia','uResidencia.idubicacion', '=', 'dp.fkUbicacionResidencia',"left")        
            ->join('tercero AS terceroEntidad','terceroEntidad.idTercero', '=', 'e.fkEntidad',"left")
            ->join('tercero AS terceroEntidadPeriodo','terceroEntidadPeriodo.idTercero', '=', 'periodo.fkEntidad',"left")
            ->join('centrotrabajo AS ct','ct.idCentroTrabajo', '=', 'e.fkCentroTrabajo',"left")
            ->join('centrotrabajo AS ctPeriodo','ctPeriodo.idCentroTrabajo', '=', 'periodo.fkCentroTrabajo',"left")
            ->join('gruposanguineo AS gs','gs.idGrupoSanguineo', '=', 'dp.fkGrupoSanguineo',"left")
            ->join('rh','rh.idRh', '=', 'dp.fkRh',"left")
            ->join('nivel_estudio as ne','ne.idNivelEstudio', '=', 'dp.fkNivelEstudio',"left")
            ->join('etnia','etnia.idEtnia', '=', 'dp.fkEtnia',"left")
            ->leftJoin('afiliacion as afPension', function ($join) {
                $join->on('afPension.fkEmpleado', '=', 'e.idempleado')
                    ->on('afPension.fkPeriodoActivo', '=', 'periodo.idPeriodo')
                    ->where('afPension.fkTipoAfilicacion', '=', 4);
            })
            ->join('tercero AS terceroPension','terceroPension.idTercero', '=', 'afPension.fkTercero',"left")
            ->leftJoin('afiliacion as afSalud', function ($join) {
                $join->on('afSalud.fkEmpleado', '=', 'e.idempleado')
                    ->on('afSalud.fkPeriodoActivo', '=', 'periodo.idPeriodo')
                    ->where('afSalud.fkTipoAfilicacion', '=', 3);
            })
            ->join('tercero AS terceroSalud','terceroSalud.idTercero', '=', 'afSalud.fkTercero',"left")
            ->leftJoin('afiliacion as afCCF', function ($join) {
                $join->on('afCCF.fkEmpleado', '=', 'e.idempleado')
                    ->on('afCCF.fkPeriodoActivo', '=', 'periodo.idPeriodo')
                    ->where('afCCF.fkTipoAfilicacion', '=', 2);
            })
            ->join('tercero AS terceroCCF','terceroCCF.idTercero', '=', 'afCCF.fkTercero',"left")
            ->leftJoin('afiliacion as afCes', function ($join) {
                $join->on('afCes.fkEmpleado', '=', 'e.idempleado')
                    ->on('afCes.fkPeriodoActivo', '=', 'periodo.idPeriodo')
                    ->where('afCes.fkTipoAfilicacion', '=', 1);
            })
            ->join('tercero AS terceroCes','terceroCes.idTercero', '=', 'afCes.fkTercero',"left")
            ->leftJoin('conceptofijo as cf', function ($join) {
                $join->on('cf.fkEmpleado', '=', 'e.idempleado')
                    ->on('cf.fkPeriodoActivo', '=', 'periodo.idPeriodo')
                    ->whereIn('cf.fkConcepto', ["1","2","53","54","154"]);
            })
            ->leftJoin('novedad AS nRet', function ($join) {
                $join->on('nRet.fkPeriodoActivo', '=', 'periodo.idPeriodo')
                    ->on('nRet.fkEmpleado', '=', 'e.idempleado')
                    ->whereNotNull('nRet.fkRetiro')
                    ->where('nRet.fkEstado',"=","8");
            })
            ->joinSub("(
                SELECT    Min(idcontrato) min_id, fechaInicio, fechaFin,tipoDuracionContrato, numeroMeses, numeroDias, fkEstado, fkTipoContrato,fkPeriodoActivo,fkEmpleado 
                FROM      contrato 
                GROUP BY  fkPeriodoActivo
            )","contrato",function ($join) {
                $join->on('contrato.fkEmpleado', '=', 'e.idempleado')
                     ->on('contrato.fkPeriodoActivo', '=', 'periodo.idPeriodo');
            })
            /*->leftJoin('contrato', function ($join) {
                $join->on('contrato.fkEmpleado', '=', 'e.idempleado')
                     ->on('contrato.fkPeriodoActivo', '=', 'periodo.idPeriodo');
            })*/
            ->join("tipocontrato", "tipocontrato.idtipoContrato","=","contrato.fkTipoContrato","left")
            ->join('tercero AS terceroArl','terceroArl.idTercero', '=', 'emp.fkTercero_ARL',"left")
            ->join('retiro AS r','r.idRetiro', '=', 'nRet.fkRetiro',"left")
            ->join("motivo_retiro as mr","mr.idMotivoRetiro","=","r.fkMotivoRetiro","left");
            $dataUsu = UsuarioController::dataAdminLogueado();
 
            if(isset($dataUsu) && $dataUsu->fkRol == 2){
                $consulta = $consulta->whereIn("n.fkEmpresa", $dataUsu->empresaUsuario);
            }

            $existeFiltroEstado = false;
            if(isset($req->filtro)){
                //Filtros con and
                $cuentaFiltros = 0;
                foreach($req->filtro as $row => $filtro){
                    if($req->campoId[$row] == "41"){
                        $existeFiltroEstado = true; 
                    }

                    if($req->operador[$row] == "LIKE"){
                        $filtro = "%".$filtro."%";
                    }
                    $item_tipo_reportes = DB::table("item_tipo_reporte")->where("IdItemTipoReporte","=",$req->campoId[$row])->first();
                    if(!isset( $req->concector[$row - 1]))
                    {
                        $consulta = $consulta->whereRaw($item_tipo_reportes->campo." ".$req->operador[$row]." '".$filtro."'");
                        $cuentaFiltros++;
                    }
                    else if(isset($req->concector[$row - 1]) && $req->concector[$row-1]=="AND" && $req->concector[$row]!="OR")
                    {
                        $consulta = $consulta->whereRaw($item_tipo_reportes->campo." ".$req->operador[$row]." '".$filtro."'");
                        $cuentaFiltros++;
                    }
                    
                }
                //Filtros or
                if($cuentaFiltros!=sizeof($req->filtro)){
                    $consulta = $consulta->where(function($query) use($req) {
                        foreach($req->filtro as $row => $filtro){
                            if($req->operador[$row] == "LIKE"){
                                $filtro = "%".$filtro."%";
                            }                    
                            $item_tipo_reportes = DB::table("item_tipo_reporte")->where("IdItemTipoReporte","=",$req->campoId[$row])->first();

                            if(isset( $req->concector[$row - 1]) && $req->concector[$row-1]=="OR")
                            {
                                $query->orWhereRaw($item_tipo_reportes->campo." ".$req->operador[$row]." '".$filtro."'");
                            }
                            else if($req->concector[$row]=="OR"){
                                $query->orWhereRaw($item_tipo_reportes->campo." ".$req->operador[$row]." '".$filtro."'");
                            }
                        }
                    });
                }               
                foreach($req->filtro as $row => $filtro){
                    if($req->campoId[$row] == "41"){
                        $existeFiltroEstado = true; 
                    }
                }
            }

            if(!$existeFiltroEstado){
                $consulta = $consulta->where("periodo.fkEstado","=","1");
            }
           // $consulta = $consulta->where("e.idempleado","=",2712);
            $consulta = $consulta->orderBy("e.idempleado", "desc");
            $consulta = $consulta->get();
            //dd($consulta);

            $arrDef = array();
            $arrTitulos = array();
            if(sizeof($consulta) > 0){
                foreach($consulta[0] as $row => $con){
                    array_push($arrTitulos, $row);
                }
                array_push($arrDef, $arrTitulos);

                foreach($consulta as $con){
                    $arrayFila = array();
                    foreach($con as $row => $dat){
                        if(strpos($row,"sin acentos")!==false){
                            array_push($arrayFila, $this->normalize($dat." "));
                        }
                        else{
                            array_push($arrayFila, $dat." ");
                        }
                    }
                    array_push($arrDef, $arrayFila);
                }
    
            }
            
            $filename = "Reporteador.xls";
            header("Content-Type: application/vnd.ms-excel");
            header("Content-Disposition: attachment; filename=".$filename);
            echo "<table>";
            foreach($arrDef as $row){
                echo "<tr>";
                foreach($row as $data){
                    echo "<td>".utf8_decode($data)."</td>";
                }
                echo "</tr>";
            }
            echo "</table>";

        }
        else if($reporte->fkTipoReporte == "2"){
            $consulta = DB::table("empleado", "e");
            
            $itemsReporte = DB::table("reporte_item", "ir")
            ->join("item_tipo_reporte as itr","itr.IdItemTipoReporte", "=", "ir.fkItemTipoReporte")
            ->where("ir.fkReporte","=",$reporte->idReporte)
            ->orderBy("ir.posicion")->get();
            $arrSelect = [];
            foreach($itemsReporte as $itemReporte){
                if($itemReporte->tipo == "bool"){
                    array_push($arrSelect, "IF(".$itemReporte->campo."='1','SI','NO') as '".$itemReporte->nombre."'");
                }
                else{
                    array_push($arrSelect, $itemReporte->campo." as '".$itemReporte->nombre."'");
                }
                
            }
            $sql = implode(",",$arrSelect);
            $consulta = $consulta->selectRaw($sql);
            $consulta = $consulta
            ->join("periodo","periodo.fkEmpleado", "=","e.idempleado","left")
            ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales","left")
            ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion","left")
            ->join('conceptofijo as cf', function ($join) {
                $join->on('cf.fkEmpleado', '=', 'e.idempleado')
                    ->on('cf.fkPeriodoActivo', '=', 'periodo.idPeriodo');
            })
            ->join("concepto as c","c.idconcepto", "=", "cf.fkConcepto","left")
            ->join("naturalezaconcepto","naturalezaconcepto.idnaturalezaConcepto", "=", "c.fkNaturaleza","left")
            ->join("nomina as n","n.idNomina", "=","periodo.fkNomina","left")
            ->join("empresa as emp","emp.idempresa", "=","n.fkEmpresa","left")
            ->join('estado AS est','est.idestado', '=', 'periodo.fkEstado', "left")
            ;





            $existeFiltroEstado = false;
            if(isset($req->filtro)){
                //Filtros con and
                $cuentaFiltros = 0;
                foreach($req->filtro as $row => $filtro){
                    if($req->campoId[$row] == "117"){
                        $existeFiltroEstado = true; 
                    }

                    if($req->operador[$row] == "LIKE"){
                        $filtro = "%".$filtro."%";
                    }
                    $item_tipo_reportes = DB::table("item_tipo_reporte")->where("IdItemTipoReporte","=",$req->campoId[$row])->first();
                    if(!isset( $req->concector[$row - 1]))
                    {
                        $consulta = $consulta->whereRaw($item_tipo_reportes->campo." ".$req->operador[$row]." '".$filtro."'");
                        $cuentaFiltros++;
                    }
                    else if(isset($req->concector[$row - 1]) && $req->concector[$row-1]=="AND" && $req->concector[$row]!="OR")
                    {
                        $consulta = $consulta->whereRaw($item_tipo_reportes->campo." ".$req->operador[$row]." '".$filtro."'");
                        $cuentaFiltros++;
                    }
                    
                }
                //Filtros or
                if($cuentaFiltros!=sizeof($req->filtro)){
                    $consulta = $consulta->where(function($query) use($req) {
                        foreach($req->filtro as $row => $filtro){
                            if($req->operador[$row] == "LIKE"){
                                $filtro = "%".$filtro."%";
                            }                    
                            $item_tipo_reportes = DB::table("item_tipo_reporte")->where("IdItemTipoReporte","=",$req->campoId[$row])->first();

                            if(isset( $req->concector[$row - 1]) && $req->concector[$row-1]=="OR")
                            {
                                $query->orWhereRaw($item_tipo_reportes->campo." ".$req->operador[$row]." '".$filtro."'");
                            }
                            else if($req->concector[$row]=="OR"){
                                $query->orWhereRaw($item_tipo_reportes->campo." ".$req->operador[$row]." '".$filtro."'");
                            }
                        }
                    });
                }               
                foreach($req->filtro as $row => $filtro){
                    if($req->campoId[$row] == "41"){
                        $existeFiltroEstado = true; 
                    }
                }
            }

            if(!$existeFiltroEstado){
                $consulta = $consulta->where("periodo.fkEstado","=","1");
            }
            $consulta = $consulta->get();
            

            
            $arrDef = array();
            $arrTitulos = array();
            if(sizeof($consulta) > 0){
                foreach($consulta[0] as $row => $con){
                    array_push($arrTitulos, $row);
                }
                array_push($arrDef, $arrTitulos);
                foreach($consulta as $con){
                    $arrayFila = array();
                    foreach($con as $row => $dat){
                        array_push($arrayFila, $dat." ");
                    }
                    array_push($arrDef, $arrayFila);
                }
    
            }
            
            $filename = "Reporteador.xls";
            header("Content-Type: application/vnd.ms-excel");
            header("Content-Disposition: attachment; filename=".$filename);
            echo "<table>";
            foreach($arrDef as $row){
                echo "<tr>";
                foreach($row as $data){
                    echo "<td>".utf8_decode($data)."</td>";
                }
                echo "</tr>";
            }
            echo "</table>";

        }
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", generó un reporte en el 'Reporteador'");
    }
    public function boucherPdfConsolidado($idLiquidacionNomina){


        
        $empresayLiquidacion = DB::table("empresa", "e")
        ->select("e.*", "ln.*", "n.nombre as nom_nombre")
        ->join("nomina as n","n.fkEmpresa", "e.idempresa")
        ->join("liquidacionnomina as ln","ln.fkNomina", "n.idNomina")
        ->where("ln.idLiquidacionNomina","=",$idLiquidacionNomina)
        ->first();
    


        $userSolicita = "";
        if(isset($empresayLiquidacion->fkUserSolicita)){
            $usuario = DB::table('users')->select(
                'users.username',
                'users.email',
                'datospersonales.primerNombre',
                'datospersonales.primerApellido',
                'datospersonales.foto',
                'empleado.idempleado'
            )->leftJoin('empleado', 'users.fkEmpleado', 'empleado.idempleado',"left")
            ->leftJoin('empresa', 'empresa.idempresa', 'empleado.fkEmpresa',"left")
            ->leftJoin('datospersonales', 'datospersonales.idDatosPersonales', 'empleado.fkDatosPersonales',"left")
            ->where('users.id', $empresayLiquidacion->fkUserSolicita)
            ->first();
            
            if(isset($usuario->primerNombre)){
                $userSolicita = $usuario->primerNombre." ".$usuario->primerApellido;
            }
            else{
                $userSolicita = $usuario->username;
            }
        }

        $userAprueba = "";
        if(isset($empresayLiquidacion->fkUserAprueba)){
            $usuario = DB::table('users')->select(
                'users.username',
                'users.email',
                'datospersonales.primerNombre',
                'datospersonales.primerApellido',
                'datospersonales.foto',
                'empleado.idempleado'
            )
            ->leftJoin('empleado', 'users.fkEmpleado', 'empleado.idempleado')
            ->leftJoin('empresa', 'empresa.idempresa', 'empleado.fkEmpresa')
            ->leftJoin('datospersonales', 'datospersonales.idDatosPersonales', 'empleado.fkDatosPersonales')
            ->where('users.id',"=", $empresayLiquidacion->fkUserAprueba)
            ->first();
            
            if(isset($usuario->primerNombre)){
                $userAprueba = $usuario->primerNombre." ".$usuario->primerApellido;
            }
            else{
                $userAprueba = $usuario->username;
            }
        }


        $bouchersPago = DB::table("boucherpago","bp")->where("fkLiquidacion","=",$idLiquidacionNomina)->get();
        $base64 = "";
        if(is_file($this->rutaBaseImagenes.'storage/logosEmpresas/'.$empresayLiquidacion->logoEmpresa)){
            $imagedata = file_get_contents($this->rutaBaseImagenes.'storage/logosEmpresas/'.$empresayLiquidacion->logoEmpresa);
                    // alternatively specify an URL, if PHP settings allow
            $base64 = base64_encode($imagedata);
        }
        else{
            unset($empresayLiquidacion->logoEmpresa);
        }
        

        $arrMeses = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];
        $mensajeGen = array();
        $mensajeGen[8] = DB::table("mensaje")->where("idMensaje","=","8")->first();
        $mensajeGen[9] = DB::table("mensaje")->where("idMensaje","=","9")->first();

        $dompdf = new Dompdf();
        $dompdf->getOptions()->setChroot($this->rutaBaseImagenes);
        $dompdf->getOptions()->setIsPhpEnabled(true);
        $html='
        <html>
        <body>
            <style>
            *{
                -webkit-hyphens: auto;
                -ms-hyphens: auto;
                hyphens: auto;
                font-family: sans-serif;                
            }
            td{
                text-align: left;
                font-size: 8px;
            }
            th{
                text-align: left;
                font-size: 8px;
            }
            .liquida td, .liquida th{
                font-size:9px;
            }
            
            @page { 
                margin: 0in;
                position: absolute;
            }
            .page {
                top: .3in;
                right: .3in;
                bottom: .3in;
                left: .3in;
                position: absolute;
                z-index: -1000;
                min-width: 7in;
                min-height: 11.7in;
                
            }
            .page_break { 
                page-break-before: always; 
            }
        
            </style>
            ';

        foreach($bouchersPago as $boucherPago){
            $idBoucherPago = $boucherPago->idBoucherPago;
            $empleado = DB::table("empleado","e")
            ->select("e.idempleado", "p.fechaInicio as fechaIngreso",
            "e.tipoRegimen","p.tipoRegimen as tipoRegimenPeriodo", "p.fkNomina",
            "dp.primerNombre","dp.segundoNombre", 
            "dp.primerApellido","dp.segundoApellido","ti.nombre as tipoidentificacion", 
            "dp.numeroIdentificacion", "cargo.nombreCargo", "cargo2.nombreCargo as nombreCargoPeriodo",
            "p.idPeriodo")
            ->join("boucherpago as bp","bp.fkEmpleado", "e.idempleado")
            ->join("periodo as p","p.idPeriodo", "bp.fkPeriodoActivo")
            ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
            ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion")
            ->join("cargo","cargo.idCargo","=","e.fkCargo", "left")
            ->join("cargo as cargo2","cargo2.idCargo","=","p.fkCargo", "left")
            ->where("bp.idBoucherPago","=",$idBoucherPago)
            ->first();

            $empleado->tipoRegimen = ($empleado->tipoRegimenPeriodo ?? $empleado->tipoRegimen);
            $empleado->nombreCargo = ($empleado->nombreCargoPeriodo ?? $empleado->nombreCargo);
            

            $nomina = DB::table("nomina","n")
            ->where("n.idNomina","=",$empleado->fkNomina)->first();
            $pension = DB::table("tercero", "t")->
            select(["t.razonSocial", "t.numeroIdentificacion", "t.idTercero", "ti.nombre as tipoidentificacion", "t.digitoVer"])
            ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","t.fkTipoIdentificacion")
            ->join("afiliacion as a","t.idTercero", "=","a.fkTercero")
            ->where("a.fkEmpleado","=",$empleado->idempleado)
            ->where("a.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("a.fkTipoAfilicacion","=","4") //4-Pensión Obligatoria 
            ->first();

            $salud = DB::table("tercero", "t")->select(["t.razonSocial", "t.numeroIdentificacion", "t.idTercero",
            "ti.nombre as tipoidentificacion", "t.digitoVer"])
            ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","t.fkTipoIdentificacion")
            ->join("afiliacion as a","t.idTercero", "=","a.fkTercero")
            ->where("a.fkEmpleado","=",$empleado->idempleado)
            ->where("a.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("a.fkTipoAfilicacion","=","3") //3-Salud
            ->first();
            
      



            $cesantiasEmp = DB::table("tercero", "t")->select(["t.razonSocial", "t.numeroIdentificacion", "t.idTercero",
                "ti.nombre as tipoidentificacion", "t.digitoVer"])
            ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","t.fkTipoIdentificacion")
            ->join("afiliacion as a","t.idTercero", "=","a.fkTercero")
            ->where("a.fkEmpleado","=",$empleado->idempleado)
            ->where("a.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->where("a.fkTipoAfilicacion","=","1") //2-CCF
            ->first();

            $entidadBancaria = DB::table("tercero", "t")->select(["t.razonSocial", "e.numeroCuenta"])
            ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","t.fkTipoIdentificacion")
            ->join("empleado as e", "e.fkEntidad", "=","t.idTercero")
            ->where("e.idempleado","=",$empleado->idempleado)
            ->first();

            $entidadBancariaPeriodo = DB::table("tercero", "t")->select(["t.razonSocial", "p.numeroCuenta"])
            ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","t.fkTipoIdentificacion")
            ->join("periodo as p", "p.fkEntidad", "=","t.idTercero")
            ->where("p.idPeriodo","=",$empleado->idPeriodo)
            ->first();

            if(!isset($entidadBancaria)){
                $entidadBancaria = $entidadBancariaPeriodo;
            }
            if(isset($entidadBancaria)){
                $entidadBancaria->razonSocial = ($entidadBancariaPeriodo->razonSocial ?? $entidadBancaria->razonSocial);
                $entidadBancaria->numeroCuenta = ($entidadBancariaPeriodo->numeroCuenta ?? $entidadBancaria->numeroCuenta);
            }

            $idItemBoucherPago = DB::table("item_boucher_pago","ibp")
            ->join("concepto AS c","c.idconcepto","=", "ibp.fkConcepto")
            ->where("ibp.fkBoucherPago","=",$idBoucherPago)
            ->get();

            $itemsBoucherPagoFueraNomina = DB::table("item_boucher_pago_fuera_nomina","ibpfn")
            ->join("concepto AS c","c.idconcepto","=", "ibpfn.fkConcepto")
            ->where("ibpfn.fkBoucherPago","=",$idBoucherPago)
            ->get();

            $itemsBoucherPagoFueraNominaCesTras = DB::table("item_boucher_pago_fuera_nomina","ibpfn")
            ->select("ibpfn.*","c.*")
            ->join("concepto AS c","c.idconcepto","=", "ibpfn.fkConcepto")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibpfn.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
            ->whereRaw("MONTH(ln.fechaLiquida)= MONTH('".$empresayLiquidacion->fechaLiquida."')")
            ->whereRaw("YEAR(ln.fechaLiquida)= YEAR('".$empresayLiquidacion->fechaLiquida."')")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=", $empleado->idPeriodo)
            ->where("ln.idLiquidacionNomina","<>",$empresayLiquidacion->idLiquidacionNomina)
            ->where("ln.fkTipoLiquidacion","=","11")
            ->get();
            foreach($itemsBoucherPagoFueraNominaCesTras as $itemBoucherPagoFueraNominaCesTras){
                $itemsBoucherPagoFueraNomina->push($itemBoucherPagoFueraNominaCesTras);
            }


            
            $periodoPasadoReintegro = DB::table("periodo")
            ->where("fkEstado","=","2")
            ->where("fkEmpleado", "=", $empleado->idempleado)
            ->where("fechaFin",">=",$empresayLiquidacion->fechaInicio)
            ->where("fkNomina","=",$empresayLiquidacion->fkNomina)
            ->first();

            if(isset($periodoPasadoReintegro)){
                $conceptoSalario = new stdClass;
                $conceptoSalario->valor = $periodoPasadoReintegro->salario;
            }
            else{
                $conceptoSalario = DB::table("conceptofijo")
                ->where("fkEmpleado","=",$empleado->idempleado)
                ->where("fkPeriodoActivo","=",$empleado->idPeriodo)
                ->whereIn("fkConcepto",[1,2,53,54,154])->first();
            }
            
            
                
            //VACACIONES
            $novedadesVacacionActual = DB::table("novedad","n")
            ->select("v.*", "c.nombre","c.idconcepto", "ibpn.valor")
            ->join("concepto as c","c.idconcepto", "=","n.fkConcepto")
            ->join("vacaciones as v","v.idVacaciones","=","n.fkVacaciones")
            ->join("item_boucher_pago_novedad as ibpn","ibpn.fkNovedad","=","n.idNovedad")
            ->join("item_boucher_pago as ibp","ibp.idItemBoucherPago","=","ibpn.fkItemBoucher")
            ->where("ibp.fkBoucherPago","=",$idBoucherPago)
            ->whereIn("n.fkEstado",["8","7"]) // Pagada -> no que este eliminada
            ->whereNotNull("n.fkVacaciones")
            ->get();
            //$diasVac = $totalPeriodoPagoAnioActual * 15 / 360;
            


            $novedadesRetiro = DB::table("novedad","n")
            ->select("r.fecha", "r.fechaReal","mr.nombre as motivoRet")
            ->join("retiro AS r", "r.idRetiro","=","n.fkRetiro")
            ->join("motivo_retiro as mr","mr.idMotivoRetiro","=","r.fkMotivoRetiro")
            ->where("n.fkEmpleado", "=", $empleado->idempleado)
            ->where("n.fkPeriodoActivo","=",$empleado->idPeriodo)
            ->whereIn("n.fkEstado",["7", "8"])
            ->whereNotNull("n.fkRetiro")
            ->whereBetween("n.fechaRegistro",[$empresayLiquidacion->fechaInicio, $empresayLiquidacion->fechaFin])->first();
            



            
        
            if($empresayLiquidacion->fkTipoLiquidacion == "7"){
                $html.='<div class="page liquida">
                <div style="border: 2px solid #000; padding: 5px 10px; font-size: 13px; margin-bottom: 5px;">
                    <table class="tituloTable">
                        <tr>
                            <td rowspan="2">
                            '.(isset($empresayLiquidacion->logoEmpresa) ? '<img style="max-width: 50px; max-height: 50px; margin-right: 5px;" src="data:image/png;base64,'.$base64.'" class="logoEmpresa" />' : '').'
                            </td>
                            <td valign="bottom"><b>'.$empresayLiquidacion->razonSocial.'</b></td>
                        </tr>
                        <tr>
                            <td  valign="top"><b>'.number_format($empresayLiquidacion->documento,0, ",", ".").'-'.$empresayLiquidacion->digitoVerificacion.'</b></td>
                        </tr>
                    </table>
                    <center>
                        <h2 style="margin:0; margin-bottom: 0px; font-size: 18px;">COMPROBANTE DE PAGO DE NÓMINA</h2><br>
                    </center>
                    <table style="width: 100%;">
                        <tr>
                            <th>Nómina</th>
                            <td>'.$empresayLiquidacion->nom_nombre.'</td>
                            <th>Período liquidación</th>
                            <td>
                                '.date("Y",strtotime($empresayLiquidacion->fechaInicio))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaInicio)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaInicio)).' 
                                a
                                '.date("Y",strtotime($empresayLiquidacion->fechaFin))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaFin)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaFin)).' 
                            </td>
                        </tr>
                        <tr>
                            <th>Empleado</th>
                            <td>'.$empleado->primerApellido." ".$empleado->segundoApellido." ".$empleado->primerNombre." ".$empleado->segundoNombre.'</td>
                            <th>Salario</th>
                            <td>$ '.number_format($conceptoSalario->valor,0, ",", ".").'</td>
                        </tr>
                        <tr>
                            <th>Identificación</th>
                            <td>'.$empleado->tipoidentificacion.' '.$empleado->numeroIdentificacion.'</td>
                            <th>Cargo</th>
                            <td>'.$empleado->nombreCargo.'</td>
                        </tr>
                        <tr>
                            <th>Entidad Bancaria</th>
                            <td>'.(isset($entidadBancaria->razonSocial) ? $entidadBancaria->razonSocial : "").'</td>
                            <th>Cuenta</th>
                            <td>'.(isset($entidadBancaria->numeroCuenta) ? $entidadBancaria->numeroCuenta : "").'</td>
                        </tr>
                        <tr>
                            <th>EPS</th>
                            <td>'.($salud->razonSocial ?? "").'</td>
                            <th>Fondo Pensiones</th>
                            <td>'.(isset($pension->razonSocial) ? $pension->razonSocial : "").'</td>
                        </tr>
                        
                    </table>
                    <br>
                </div>
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                                <th style="background: #d89290; text-align: center;" colspan="2">Devengado</th>
                                <th style="background: #d89290; text-align: center;">Deducciones</th>                        
                            </tr>
                            <tr>
                                <th style="background: #CCC; text-align: center;">Conceptos Liquidados</th>
                                <th style="background: #CCC; text-align: center;">Cantidad</th>
                                <th style="background: #CCC; text-align: center;">Unidad</th>
                                <th style="background: #CCC; text-align: center;">Pagos</th>
                                <th style="background: #CCC; text-align: center;">Beneficios</th>
                                <th style="background: #CCC; text-align: center;">Descuentos</th>
                            </tr>';
                            $totalDesc = 0;
                            $totalPag = 0;
                
                            foreach($idItemBoucherPago as $itemBoucherPago){
                                $html.='<tr style="border-bottom: 1px solid #B0B0B0;">
                                    <td style="border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->nombre.'</td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.((15 / 180) * $itemBoucherPago->cantidad).'</td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->tipoUnidad.'</td>';
                                    
                                    if($itemBoucherPago->valor > 0){
                                        $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor,0, ",", ".").'</td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>';
                                        $totalPag = $totalPag + $itemBoucherPago->valor;
                                    }
                                    else{
                                        $html.='<td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor*-1,0, ",", ".").'</td>';
                                        $totalDesc = $totalDesc + $itemBoucherPago->valor;
                                    }

                                $html.='</tr>';
                            }
                            $html.='<tr>
                                        
                                        <th colspan="3" style="text-align: right;">Totales</th>
                                        <td style="text-align: right; border: 1px solid #B0B0B0;">$'.number_format($totalPag,0, ",", ".").'</td>
                                        <td style="text-align: right; border: 1px solid #B0B0B0;"></td>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" >$'.number_format($totalDesc*-1,0, ",", ".").'</td>
                                    </tr>
                            ';
                            $totalGen = $totalPag + $totalDesc;
                            if($totalGen<0){
                                $totalGen=0;
                            }
                            $html.='<tr>
                                        
                                        <th colspan="3" style="text-align: right;" >Neto a pagar en cuenta nómina</th>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" colspan="2">$'.number_format($totalGen,0, ",", ".").'</td>
                                        
                                    </tr>
                            ';
                            
                        $html.='</table>

                    </div>
                    <br>';
                    if(sizeof($itemsBoucherPagoFueraNomina)>0){
                        $html.='<div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                        <tr>
                                <th style="background: #CCC; text-align: center;" colspan="5">Fuera de nómina</th>
                        </tr>
                        <tr>
                            <th style="background: #CCC; text-align: center;">Conceptos</th>
                            <th style="background: #CCC; text-align: center;">Cantidad</th>
                            <th style="background: #CCC; text-align: center;">Unidad</th>
                            <th style="background: #CCC; text-align: center;">Pagos</th>
                            <th style="background: #CCC; text-align: center;">Descuentos</th>
                        </tr>
                        ';
                        foreach($itemsBoucherPagoFueraNomina as $itemBoucherPagoFueraNomina){
                            $html.='<tr style="border-bottom: 1px solid #B0B0B0;">
                            <td style="border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->nombre.'</td>
                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->cantidad.'</td>
                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->tipoUnidad.'</td>';
                            
                            if($itemBoucherPagoFueraNomina->valor > 0){
                                $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPagoFueraNomina->valor,0, ",", ".").'</td>
                                    <td style="border-bottom: 1px solid #B0B0B0;"></td>';
                                
                            }
                            else{
                                $html.='<td style="border-bottom: 1px solid #B0B0B0;"></td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPagoFueraNomina->valor*-1,0, ",", ".").'</td>';
                                
                            }
                            $html.='</tr>';
                        }
                        $html.='</table></div><br>';
                    }
                    $html.='<div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="2">Bases para cálculo de seguridad social</th>
                            </tr>
                            <tr>
                                <td>Ingreso Base Cotización Salud</td><td style="text-align: right;">$'.number_format($boucherPago->ibc_eps,0, ",", ".").'</td>
                            </tr>
                            <tr>
                                <td>Ingreso Base Cotización Pension</td><td style="text-align: right;">$'.number_format($boucherPago->ibc_afp,0, ",", ".").'</td>
                            </tr>
                        </table>
                    </div>
                    <br>
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="2">Mensaje Empresarial</th>
                            </tr>
                            <tr>
                                <td style="text-align: justify;">'.$mensajeGen[8]->html.'</td>
                            </tr>
                        </table>
                    </div>
                    <br>
                    <div style="position: absolute; bottom: 40px; width:100%;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td>COLABORADOR</td>
                                <td></td>
                                <td>LA EMPRESA</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td>Cédula o NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                                <td>NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                            </tr>
                            <tr>
                                <td colspan="3"></td>
                                <td>Fecha de elaboración:&nbsp;&nbsp;&nbsp; '.date("d/m/Y").'</td>
                            </tr>
                        </table>

                    </div>
                </div>
                <div class="page_break"></div>';
            }
            else if(($empresayLiquidacion->fkTipoLiquidacion == "2" || $empresayLiquidacion->fkTipoLiquidacion == "3") && isset($novedadesRetiro)){
                
                $contrato = DB::table("contrato","c")
                ->join("tipocontrato as tc","tc.idtipoContrato", "=","c.fkTipoContrato")
                ->where("c.fkEmpleado","=",$empleado->idempleado)
                ->where("c.fkEstado","=",["1","2"])->first();
                
                $cambioSalario = DB::table("cambiosalario","cs")
                ->where("cs.fkEmpleado","=",$empleado->idempleado)
                ->where("cs.fkEstado","=","5")
                ->first();
                $fechaUltimoCamSal = $empleado->fechaIngreso;
                if(isset($cambioSalario)){
                    $fechaUltimoCamSal = $cambioSalario->fechaCambio;
                }
            
                $fechaRet1 = $novedadesRetiro->fecha;
                if(substr($fechaRet1, 8, 2) == "31" || (substr($fechaRet1, 8, 2) == "28" && substr($fechaRet1, 5, 2) == "02") || (substr($fechaRet1, 8, 2) == "29" && substr($fechaRet1, 5, 2) == "02") ){
                    $fechaRet1 = $novedadesRetiro->fecha;
                }
                $diasLab = $this->days_360($empleado->fechaIngreso, $fechaRet1) + 1;
                $meses = intval($diasLab/30);
                $diasDemas = $diasLab - ($meses * 30);
                $tiempoTrabTxt = $meses." Meses ".$diasDemas." días";

                $fechaFinMesActual = date("Y-m-t", strtotime($empresayLiquidacion->fechaLiquida));
                $fechaInicioMesActual = date("Y-m-01", strtotime($empresayLiquidacion->fechaLiquida));
                $ultimoBoucher = DB::table("boucherpago", "bp")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9"])
                ->orderBy("bp.idBoucherPago","desc")
                ->first();
                if(!isset($ultimoBoucher)){
                    $ultimoBoucher = DB::table("boucherpago", "bp")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                    ->whereIn("ln.fkTipoLiquidacion",["3"])
                    ->orderBy("bp.idBoucherPago","desc")
                    ->first();
                }
                else{
                    $ultimoBoucherRetiro = DB::table("boucherpago", "bp")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->whereRaw("(ln.fechaFin <= '".$fechaFinMesActual."' and ln.fechaInicio >= '".$fechaInicioMesActual."')")
                    ->whereIn("ln.fkTipoLiquidacion",["3"])
                    ->orderBy("bp.idBoucherPago","desc")
                    ->first();

                    $ultimoBoucher->ibc_afp = $ultimoBoucher->ibc_afp + ($ultimoBoucherRetiro->ibc_afp ?? 0);
                    $ultimoBoucher->ibc_eps = $ultimoBoucher->ibc_eps + ($ultimoBoucherRetiro->ibc_eps ?? 0);
                    $ultimoBoucher->ibc_arl = $ultimoBoucher->ibc_arl + ($ultimoBoucherRetiro->ibc_arl ?? 0);
                    $ultimoBoucher->ibc_ccf = $ultimoBoucher->ibc_ccf + ($ultimoBoucherRetiro->ibc_ccf ?? 0);
                    $ultimoBoucher->ibc_otros = $ultimoBoucher->ibc_otros + ($ultimoBoucherRetiro->ibc_otros ?? 0);
                }
                $IBL = $ultimoBoucher->ibc_eps;
                $html.='                    
                <div class="page liquida">
                    <div style="border: 2px solid #000; padding: 5px 10px; font-size: 15px; margin-bottom: 5px;">
                        <table class="tituloTable">
                            <tr>
                                <td rowspan="2">
                                '.(isset($empresayLiquidacion->logoEmpresa) ? '<img style="max-width: 50px; max-height: 50px; margin-right: 5px;" src="data:image/png;base64,'.$base64.'" class="logoEmpresa" />' : '').'
                                </td>
                                <td valign="bottom"><b>'.$empresayLiquidacion->razonSocial.'</b></td>
                            </tr>
                            <tr>
                                <td  valign="top"><b>'.number_format($empresayLiquidacion->documento,0, ",", ".").'-'.$empresayLiquidacion->digitoVerificacion.'</b></td>
                            </tr>
                        </table>
                        <center>
                            <h2 style="margin:0; margin-bottom: 0px; font-size: 20px;">LIQUIDACIÓN DE CONTRATO</h2>
                        </center>
                    </div>
                    <table style="width: 96%; text-align: left;">
                        <tr>
                            <th>
                                Nómina
                            </th>
                            <td>
                                '.$empresayLiquidacion->nom_nombre.'
                            </td>
                            <th>
                                Período liquidación
                            </th>
                            <td>
                                '.date("Y",strtotime($empresayLiquidacion->fechaInicio))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaInicio)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaInicio)).' 
                                a
                                '.date("Y",strtotime($empresayLiquidacion->fechaFin))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaFin)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaFin)).' 
                            </td>
                        </tr>
                        <tr>
                            <th>Empleado</th>
                            <td>'.$empleado->primerApellido." ".$empleado->segundoApellido." ".$empleado->primerNombre." ".$empleado->segundoNombre.'</td>
                            <th>Fecha ingreso</th>
                            <td>'.date("Y",strtotime($empleado->fechaIngreso))."/".$arrMeses[date("m",strtotime($empleado->fechaIngreso)) - 1].'/'.date("d",strtotime($empleado->fechaIngreso)).'</td>
                        </tr>
                        <tr>
                            <th>Identificación</th>
                            <td>'.$empleado->tipoidentificacion.' '.$empleado->numeroIdentificacion.'</td>
                            <th>Fecha Retiro</th>
                            <td>'.date("Y",strtotime($novedadesRetiro->fecha))."/".$arrMeses[date("m",strtotime($novedadesRetiro->fecha)) - 1].'/'.date("d",strtotime($novedadesRetiro->fecha)).'</td>
                        </tr>
                        <tr>
                            <th>Tipo Contrato</th>
                            <td>'.($contrato->nombre ?? "").'</td>
                            <th>Fecha Retiro Real</th>
                            <td>'.date("Y",strtotime($novedadesRetiro->fechaReal))."/".$arrMeses[date("m",strtotime($novedadesRetiro->fechaReal)) - 1].'/'.date("d",strtotime($novedadesRetiro->fechaReal)).'</td>
                        </tr>
                        <tr>
                            <th>Nómina</th>
                            <td>'.$empresayLiquidacion->nom_nombre.'</td>
                            <th>Fecha Último Aumento Salario</th>
                            <td>
                                '.date("Y",strtotime($fechaUltimoCamSal))."/".$arrMeses[date("m",strtotime($fechaUltimoCamSal)) - 1].'/'.date("d",strtotime($fechaUltimoCamSal)).' 
                            </td>
                        </tr>
                        <tr>
                            <th>Régimen</th>
                            <td>'.$empleado->tipoRegimen.'</td>
                            <th>Última Nómina Pagada</th>
                            <td>
                                '.date("Y",strtotime($empresayLiquidacion->fechaLiquida))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaLiquida)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaLiquida)).' 
                            </td>
                        </tr>
                        <tr>
                            <th>Tiempo Trabajado</th>
                            <td>'.$tiempoTrabTxt.'</td>
                            <th>Cargo</th>
                            <td>'.$empleado->nombreCargo.'</td>
                            </td>
                        </tr>
                        <tr>
                            <th>Salario</th>
                            <td>$ '.number_format($conceptoSalario->valor,0, ",", ".").'</td>
                            <th>EPS</th>
                            <td>'.($salud->razonSocial ?? "").'</td>
                        </tr>
                        <tr>
                            <th>Entidad Bancaria</th>
                            <td>'.(isset($entidadBancaria->razonSocial) ? $entidadBancaria->razonSocial : "").'</td>
                            <th>Cuenta</th>
                            <td>'.(isset($entidadBancaria->numeroCuenta) ? $entidadBancaria->numeroCuenta : "").'</td>
                        </tr>
                        <tr>
                            <th>Fondo Pensiones</th>
                            <td>'.(isset($pension->razonSocial) ? $pension->razonSocial : "").'</td>
                            <th>IBL Seguridad Social </th>
                            <td>$ '.number_format($IBL,0, ",", ".").'</td>
                        </tr>
                        <tr>
                            <th>Fondo Cesantías </th>
                            <td>'.(isset($cesantiasEmp->razonSocial) ? $cesantiasEmp->razonSocial : "").'</td>
                            <th>Motivo Retiro</th>
                            <td>'.$novedadesRetiro->motivoRet.'</td>
                        </tr>
                    </table>
                    <br>';
                    $basePrima = 0;
                    $baseCes = 0;
                    $baseVac = 0;

                    $fechaInicioCes = "";
                    $fechaInicioPrima = "";
                    $fechaInicioVac = $empleado->fechaIngreso;

                    $fechaFinCes = "";
                    $fechaFinPrima = "";
                    $fechaFinVac = $novedadesRetiro->fecha;

                    $diasCes = 0;
                    $diasPrima = 0;
                    $diasVac = 0;

                
                    foreach($idItemBoucherPago as $itemBoucherPago){
                        if($itemBoucherPago->fkConcepto == 30){
                            $baseVac = $itemBoucherPago->base;
                            $diasVac = $itemBoucherPago->cantidad;
                        }

                        if($itemBoucherPago->fkConcepto == 58){
                            $basePrima = $itemBoucherPago->base;
                            $fechaInicioPrima =  $itemBoucherPago->fechaInicio;
                            $fechaFinPrima =  $itemBoucherPago->fechaFin;                            
                            $diasPrima = (15 / 180) * $itemBoucherPago->cantidad;
                        }
                        
                        if($itemBoucherPago->fkConcepto == 66){
                            $baseCes = $itemBoucherPago->base;
                            $fechaInicioCes =  $itemBoucherPago->fechaInicio;
                            $fechaFinCes =  $itemBoucherPago->fechaFin;
                            $diasCes = (($itemBoucherPago->cantidad * $nomina->diasCesantias) / 360);
                        }
                        
                    }
                    if(!is_int($diasCes) && !is_float($diasCes)){
                        $diasCes = 0;
                    }
                    if(!is_int($diasPrima) && !is_float($diasPrima)){
                        $diasPrima = 0;
                    }
                    if(!is_int($diasVac) && !is_float($diasVac)){
                        $diasVac = 0;
                    }
                    
                    $html.='<div style="border: 2px solid #000; padding: 0px 10px; margin-bottom: 5px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="6">Promedio Liquidación Prestaciones</th>
                            </tr>
                            <tr>
                                <th>Promedio Cesantías</th>
                                <td>$'.number_format($baseCes,0, ",", ".").'</td>
                                <th>Promedio Vacaciones</th>
                                <td>$'.number_format($baseVac,0, ",", ".").'</td>
                                <th>Promedio Prima</th>
                                <td>$'.number_format($basePrima,0, ",", ".").'</td>
                            </tr>
                        </table>
                    </div>';
                    
                    
                    $html.='<div style="border: 2px solid #000; padding: 0px 10px; margin-bottom: 5px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="5">Valores Consolidados</th>
                            </tr>
                            <tr>
                                <th  style="background: #CCC; text-align: center;">Tipo Consolidado </th>
                                <th  style="background: #CCC; text-align: center;">Fecha Inicio</th>
                                <th  style="background: #CCC; text-align: center;">Fecha Fin</th>
                                <th  style="background: #CCC; text-align: center;">Total Días</th>
                            </tr>
                            <tr>
                                <th>Cesantías consolidadas</th>
                                <td>'.$fechaInicioCes.'</td>
                                <td>'.$fechaFinCes.'</td>
                                <td>'.round($diasCes,2).'</td>
                            </tr>
                            <tr>
                                <th>Prima de servicios consolidadas</th>
                                <td>'.$fechaInicioPrima.'</td>
                                <td>'.$fechaFinPrima.'</td>
                                <td>'.round($diasPrima,2).'</td>
                            </tr>
                            <tr>
                                <th>Vacaciones consolidadas</th>
                                <td>'.$fechaInicioVac.'</td>
                                <td>'.$fechaFinVac.'</td>
                                <td>'.round($diasVac,2).'</td>
                            </tr>
                        </table>
                    </div>
                    <div style="border: 2px solid #000; padding: 0px 10px; margin-bottom: 5px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td colspan="4"></td>
                                <th style="background: #d89290; text-align: center;" colspan="2">Pagos y Descuentos</th>
                                <td></td>
                            </tr>
                            <tr>
                                <th style="background: #CCC; text-align: center;">Conceptos Liquidados</th>
                                <th style="background: #CCC; text-align: center;">Cantidad</th>
                                <th style="background: #CCC; text-align: center;">Unidad</th>
                                <th style="background: #CCC; text-align: center;">Base</th>
                                <th style="background: #CCC; text-align: center;">Pagos</th>
                                <th style="background: #CCC; text-align: center;">Descuentos</th>
                                <th style="background: #CCC; text-align: center;">Saldo Cuota</th>                                
                            </tr>';
                            $totalDesc = 0;
                            $totalPag = 0;
                            foreach($idItemBoucherPago as $itemBoucherPago){
                                $html.='<tr style="border-bottom: 1px solid #B0B0B0;">
                                    <td style="border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->nombre.'</td>';
                                    if($itemBoucherPago->fkConcepto == 58){
                                        $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.round($diasPrima,2).'</td>';
                                    }
                                    else if($itemBoucherPago->fkConcepto == 66 || $itemBoucherPago->fkConcepto == 69){
                                        
                                        $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.round($diasCes,2).'</td>';
                                    }
                                    else{
                                        $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->cantidad.'</td>';
                                    }

                                    $html.='
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->tipoUnidad.'</td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->base,0, ",", ".").'</td>';

                                    
                                    
                                    if($itemBoucherPago->valor > 0){
                                        $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor,0, ",", ".").'</td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>';
                                        $totalPag = $totalPag + $itemBoucherPago->valor;
                                    }
                                    else{
                                        $html.='<td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor*-1,0, ",", ".").'</td>';
                                        $totalDesc = $totalDesc + $itemBoucherPago->valor;
                                    }
                                    $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$0</td>';

                                $html.='</tr>';
                            }
                            $html.='<tr>
                                        
                                        <th colspan="4" style="text-align: right;">Totales</th>
                                        <th style="text-align: right; border: 1px solid #B0B0B0;">$'.number_format($totalPag,0, ",", ".").'</td>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" >$'.number_format($totalDesc*-1,0, ",", ".").'</td>
                                    </tr>
                            ';
                            $totalGen = $totalPag + $totalDesc;
                            if($totalGen<0){
                                $totalGen=0;
                            }
                            $html.='<tr>
                                        
                                        <th colspan="3" style="text-align: right;" >Neto a pagar</th>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" colspan="2">$'.number_format($totalGen,0, ",", ".").'</td>
                                        
                                    </tr>
                            ';
                            $valorText =$this->convertir(($totalPag + $totalDesc));
                            
                        $html.='</table>
                    </div>
                    <div style="border: 2px solid #000; padding: 10px 20px; font-size: 10px; font-weight: bold; margin-bottom: 5px;">
                        El valor neto a pagar es: '.strtoupper($valorText).' PESOS M/CTE
                    </div><br>';
                    if(sizeof($itemsBoucherPagoFueraNomina)>0){
                        $html.='<div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                        <tr>
                                <th style="background: #CCC; text-align: center;" colspan="5">Fuera de nómina</th>
                        </tr>
                        <tr>
                            <th style="background: #CCC; text-align: center;">Conceptos</th>
                            <th style="background: #CCC; text-align: center;">Cantidad</th>
                            <th style="background: #CCC; text-align: center;">Unidad</th>
                            <th style="background: #CCC; text-align: center;">Pagos</th>
                            <th style="background: #CCC; text-align: center;">Descuentos</th>
                        </tr>
                        ';
                        foreach($itemsBoucherPagoFueraNomina as $itemBoucherPagoFueraNomina){
                            $html.='<tr style="border-bottom: 1px solid #B0B0B0;">
                            <td style="border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->nombre.'</td>
                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->cantidad.'</td>
                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->tipoUnidad.'</td>';
                            
                            if($itemBoucherPagoFueraNomina->valor > 0){
                                $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPagoFueraNomina->valor,0, ",", ".").'</td>
                                    <td style="border-bottom: 1px solid #B0B0B0;"></td>';
                                
                            }
                            else{
                                $html.='<td style="border-bottom: 1px solid #B0B0B0;"></td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPagoFueraNomina->valor*-1,0, ",", ".").'</td>';
                                
                            }
                            $html.='</tr>';
                        }
                        $html.='</table></div><br> 
                        </div>
                        <div class="page_break"></div>
                        <div class="page">';
                    }
                    $html.='
                    <div style="border: 2px solid #000; padding: 0px 10px; margin-bottom: 5px;">
                        <center><h4 style="margin:0px;" >Observaciones</h4></center>
                        <table>
                            <tr>
                                <th style="background: #CCC; text-align: center;">CONSTANCIAS - Se hace constar expresamente los siguiente:</th>
                            </tr>
                            <td style="font-size: 8px; text-align: justify;">'.$mensajeGen[9]->html.'</td>
                        </table>
                        <table style="width: 100%;">
                            <tr>
                                <th style="border: 1px solid #000; width:33%;">ELABORÓ: '.$userSolicita.'</th>
                                <th style="border: 1px solid #000; width:33%;">REVISÓ: '.$userAprueba.'</th>
                                <th style="border: 1px solid #000; width:33%;">APROBÓ:</th>
                            </tr>
                        </table>
                    </div>
                    <div style="position: absolute; bottom: 40px; width:100%;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td>COLABORADOR</td>
                                <td></td>
                                <td>LA EMPRESA</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td>No. Documento</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                                <td>NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                            </tr>
                            <tr>
                                <td colspan="3"></td>
                                <td>Fecha de elaboración:&nbsp;&nbsp;&nbsp; '.date("d/m/Y").'</td>
                            </tr>
                        </table>
                    </div>

                </div>
                <div class="page_break"></div>
                ';
            }
            else{
                $html.='<div class="page">
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                        ';
                        
                        
                        
                        $html.='
                        
                        <table class="tituloTable">
                            <tr>
                                <td rowspan="2">
                                '.(isset($empresayLiquidacion->logoEmpresa) ? '<img style="max-width: 50px; max-height: 50px; margin-right: 5px;" src="data:image/png;base64,'.$base64.'" class="logoEmpresa" />' : '').'
                                </td>
                                <td valign="bottom"><b>'.$empresayLiquidacion->razonSocial.'</b></td>
                            </tr>
                            <tr>
                                <td  valign="top"><b>'.number_format($empresayLiquidacion->documento,0, ",", ".").'-'.$empresayLiquidacion->digitoVerificacion.'</b></td>
                            </tr>
                        </table>
                        <center>
                            <h2 style="margin:0; margin-bottom: 10px;">Comprobante pago nómina</h2>
                        </center>
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th>
                                    Nómina
                                </th>
                                <td>
                                    '.$empresayLiquidacion->nom_nombre.'
                                </td>
                                <th>
                                    Periodo liquidación
                                </th>
                                <td>
                                    '.date("Y",strtotime($empresayLiquidacion->fechaInicio))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaInicio)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaInicio)).' 
                                    a
                                    '.date("Y",strtotime($empresayLiquidacion->fechaFin))."/".$arrMeses[date("m",strtotime($empresayLiquidacion->fechaFin)) - 1].'/'.date("d",strtotime($empresayLiquidacion->fechaFin)).' 
                                </td>
                            </tr>
                            <tr>
                                <th>Empleado</th>
                                <td>'.$empleado->primerApellido." ".$empleado->segundoApellido." ".$empleado->primerNombre." ".$empleado->segundoNombre.'</td>
                                <th>Salario</th>
                                <td>$ '.number_format($conceptoSalario->valor,0, ",", ".").'</td>
                            </tr>
                            <tr>
                                <th>Identificación</th>
                                <td>'.$empleado->tipoidentificacion.' '.$empleado->numeroIdentificacion.'</td>
                                <th>Cargo</th>
                                <td>'.$empleado->nombreCargo.'</td>
                            </tr>
                            <tr>
                                <th>Entidad Bancaria</th>
                                <td>'.(isset($entidadBancaria->razonSocial) ? $entidadBancaria->razonSocial : "").'</td>
                                <th>Cuenta</th>
                                <td>'.(isset($entidadBancaria->numeroCuenta) ? $entidadBancaria->numeroCuenta : "").'</td>
                            </tr>
                            <tr>
                                <th>EPS</th>
                                <td>'.($salud->razonSocial ?? "").'</td>
                                <th>Fondo Pensiones</th>
                                <td>'.(isset($pension->razonSocial) ? $pension->razonSocial : "").'</td>
                            </tr>
                        </table>
                    </div><br>
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                                <th style="background: #d89290; text-align: center;" colspan="2">Devengado</th>
                                <th style="background: #d89290; text-align: center;">Deducciones</th>                        
                            </tr>
                            <tr>
                                <th style="background: #CCC; text-align: center;">Conceptos Liquidados</th>
                                <th style="background: #CCC; text-align: center;">Cantidad</th>
                                <th style="background: #CCC; text-align: center;">Unidad</th>
                                <th style="background: #CCC; text-align: center;">Pagos</th>
                                <th style="background: #CCC; text-align: center;">Beneficios</th>
                                <th style="background: #CCC; text-align: center;">Descuentos</th>
                            </tr>';
                            $totalDesc = 0;
                            $totalPag = 0;
                
                            foreach($idItemBoucherPago as $itemBoucherPago){
                                $html.='<tr style="border-bottom: 1px solid #B0B0B0;">
                                    <td style="border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->nombre.'</td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->cantidad.'</td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPago->tipoUnidad.'</td>';
                                    
                                    if($itemBoucherPago->valor > 0){
                                        $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor,0, ",", ".").'</td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>';
                                        $totalPag = $totalPag + $itemBoucherPago->valor;
                                    }
                                    else{
                                        $html.='<td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="border-bottom: 1px solid #B0B0B0;"></td>
                                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPago->valor*-1,0, ",", ".").'</td>';
                                        $totalDesc = $totalDesc + $itemBoucherPago->valor;
                                    }

                                $html.='</tr>';
                            }
                            $html.='<tr>
                                        
                                        <th colspan="3" style="text-align: right;">Totales</th>
                                        <td style="text-align: right; border: 1px solid #B0B0B0;">$'.number_format($totalPag,0, ",", ".").'</td>
                                        <td style="text-align: right; border: 1px solid #B0B0B0;"></td>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" >$'.number_format($totalDesc*-1,0, ",", ".").'</td>
                                    </tr>
                            ';
                            $totalGen = $totalPag + $totalDesc;
                            if($totalGen<0){
                                $totalGen=0;
                            }
                            
                            $html.='<tr>
                                        
                                        <th colspan="3" style="text-align: right;" >Neto a pagar en cuenta nómina</th>
                                        <td style="text-align: right;border: 1px solid #B0B0B0;" colspan="2">$'.number_format($totalGen,0, ",", ".").'</td>
                                        
                                    </tr>
                            ';
                            
                        $html.='</table>
                    </div>
                    <br>';
                    if(sizeof($itemsBoucherPagoFueraNomina)>0){
                        $html.='<div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                        <tr>
                                <th style="background: #CCC; text-align: center;" colspan="5">Fuera de nómina</th>
                        </tr>
                        <tr>
                            <th style="background: #CCC; text-align: center;">Conceptos</th>
                            <th style="background: #CCC; text-align: center;">Cantidad</th>
                            <th style="background: #CCC; text-align: center;">Unidad</th>
                            <th style="background: #CCC; text-align: center;">Pagos</th>
                            <th style="background: #CCC; text-align: center;">Descuentos</th>
                        </tr>
                        ';
                        foreach($itemsBoucherPagoFueraNomina as $itemBoucherPagoFueraNomina){
                            $html.='<tr style="border-bottom: 1px solid #B0B0B0;">
                            <td style="border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->nombre.'</td>
                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->cantidad.'</td>
                            <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">'.$itemBoucherPagoFueraNomina->tipoUnidad.'</td>';
                            
                            if($itemBoucherPagoFueraNomina->valor > 0){
                                $html.='<td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPagoFueraNomina->valor,0, ",", ".").'</td>
                                    <td style="border-bottom: 1px solid #B0B0B0;"></td>';
                                
                            }
                            else{
                                $html.='<td style="border-bottom: 1px solid #B0B0B0;"></td>
                                    <td style="text-align: right;border-bottom: 1px solid #B0B0B0;">$'.number_format($itemBoucherPagoFueraNomina->valor*-1,0, ",", ".").'</td>';
                                
                            }
                            $html.='</tr>';
                        }
                        $html.='</table></div><br>';
                    }
                    $html.='<div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="2">Bases para cálculo de seguridad social</th>
                            </tr>
                            <tr>
                                <td>Ingreso Base Cotización Salud</td><td style="text-align: right;">$'.number_format($boucherPago->ibc_eps,0, ",", ".").'</td>
                            </tr>
                            <tr>
                                <td>Ingreso Base Cotización Pension</td><td style="text-align: right;">$'.number_format($boucherPago->ibc_afp,0, ",", ".").'</td>
                            </tr>
                        </table>
                    </div>
                    <br>
                    <div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <th style="background: #CCC; text-align: center;" colspan="2">Mensaje Empresarial</th>
                            </tr>
                            <tr>
                                <td style="text-align: justify;">'.$mensajeGen[8]->html.'</td>
                            </tr>
                        </table>
                    </div>
                    <br>
                    <div style="position: absolute; bottom: 40px; width:100%;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td>COLABORADOR</td>
                                <td></td>
                                <td>LA EMPRESA</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td>Cédula o NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                                <td>NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                            </tr>
                            <tr>
                                <td colspan="3"></td>
                                <td>Fecha de elaboración:&nbsp;&nbsp;&nbsp; '.date("d/m/Y").'</td>
                            </tr>
                        </table>

                    </div>
                </div>
                <div class="page_break"></div>
                ';
            }
            if(sizeof($novedadesVacacionActual) > 0){
                
                $novedadesRetiro = DB::table("novedad","n")
                ->select("r.fecha")
                ->join("retiro AS r", "r.idRetiro","=","n.fkRetiro")
                ->where("n.fkEmpleado", "=", $empleado->idempleado)
                ->whereRaw("n.fkPeriodoActivo in(
                    SELECT p.idPeriodo from periodo as p where p.fkEmpleado = '".$empleado->idempleado."' and p.fkEstado = '1'
                )")
                ->whereIn("n.fkEstado",["7", "8"])
                ->whereNotNull("n.fkRetiro")
                ->whereBetween("n.fechaRegistro",[$empresayLiquidacion->fechaInicio, $empresayLiquidacion->fechaFin])->first();
                $fechaFinalVaca = $empresayLiquidacion->fechaFin;
                $periodoPagoVac = $this->days_360($empleado->fechaIngreso,$empresayLiquidacion->fechaFin) + 1 ;
                if(isset($novedadesRetiro)){
                    if(strtotime($empresayLiquidacion->fechaFin) > strtotime($novedadesRetiro->fecha)){
                        $periodoPagoVac = $this->days_360($empleado->fechaIngreso,$novedadesRetiro->fecha) + 1 ;
                        $fechaFinalVaca = $novedadesRetiro->fecha;
                    }
                }

                $diasVac = $periodoPagoVac * 15 / 360;

                $novedadesVacacion = DB::table("novedad","n")
                ->select("v.*", "c.nombre","c.idconcepto", "ibpn.valor")
                ->join("concepto as c","c.idconcepto","=","n.fkConcepto")
                ->join("vacaciones as v","v.idVacaciones","=","n.fkVacaciones")
                ->join("item_boucher_pago_novedad as ibpn","ibpn.fkNovedad","=","n.idNovedad")
                ->join("item_boucher_pago as ibp","ibp.idItemBoucherPago","=","ibpn.fkItemBoucher")
                ->where("n.fkEmpleado","=",$empleado->idempleado)
                ->whereRaw("n.fkPeriodoActivo in(
                    SELECT p.idPeriodo from periodo as p where p.fkEmpleado = '".$empleado->idempleado."' and p.fkEstado = '1'
                )")
                ->where("ibp.fkBoucherPago","<>",$idBoucherPago)
                ->whereIn("n.fkEstado",["7"]) // Pagada o sin pagar-> no que este eliminada
                ->whereNotNull("n.fkVacaciones")
                ->get();
                //$diasVac = $totalPeriodoPagoAnioActual * 15 / 360;
                foreach($novedadesVacacion as $novedadVacacion){
                    $diasVac = $diasVac - $novedadVacacion->diasCompensar;
                }
                if(isset($diasVac) && $diasVac < 0){
                    $diasVac = 0;
                }

            
                
                $html.='
                    <div class="page">
                        <div style="border: 2px solid #000; padding: 10px 20px;">
                            <table class="tituloTable">
                                <tr>
                                    <td rowspan="2">
                                    '.(isset($empresayLiquidacion->logoEmpresa) ? '<img style="max-width: 50px; max-height: 50px; margin-right: 5px;" src="data:image/png;base64,'.$base64.'" class="logoEmpresa" />' : '').'
                                    </td>
                                    <td valign="bottom"><b>'.$empresayLiquidacion->razonSocial.'</b></td>
                                </tr>
                                <tr>
                                    <td  valign="top"><b>'.number_format($empresayLiquidacion->documento,0, ",", ".").'-'.$empresayLiquidacion->digitoVerificacion.'</b></td>
                                </tr>
                            </table>
                            <center>
                                <h2 style="margin:0; margin-bottom: 10px;">Comprobante de Pago de Vacaciones</h2>
                            </center>
                            <table style="width: 100%; text-align: left;">
                                <tr>
                                    <th>Empleado</th>
                                    <td>'.$empleado->primerApellido." ".$empleado->segundoApellido." ".$empleado->primerNombre." ".$empleado->segundoNombre.'</td>
                                    <th>Fecha ingreso</th>
                                    <td>'.date("Y",strtotime($empleado->fechaIngreso))."/".$arrMeses[date("m",strtotime($empleado->fechaIngreso)) - 1].'/'.date("d",strtotime($empleado->fechaIngreso)).'</td>
                                </tr>
                                <tr>
                                    <th>Identificación</th>
                                    <td>'.$empleado->tipoidentificacion.' '.$empleado->numeroIdentificacion.'</td>
                                    <th>Cargo</th>
                                    <td>'.$empleado->nombreCargo.'</td>
                                </tr>
                                <tr>
                                    <th>Días Pendientes Consolidado</th>
                                    <td>'.round($diasVac,2).'</td>
                                    <th>Fecha Corte Consolidado:</th>
                                    <td>'.date("Y",strtotime($fechaFinalVaca))."/".$arrMeses[date("m",strtotime($fechaFinalVaca)) - 1].'/'.date("d",strtotime($fechaFinalVaca)).'</td>
                                </tr>
                                
                            </table>
                        </div>
                        <br>
                        <div style="border: 2px solid #000; padding: 10px 20px;">
                        <table style="width: 100%; text-align: left; font-size: 10px;">
                            <tr>
                                <th style="background: #d89290; text-align: center;font-size: 10px;" colspan="11">Liquidación de Vacaciones</th>                         
                            </tr>
                            <tr>
                                <th style="background: #CCC; text-align: center;font-size: 10px;" rowspan="2">Tipo Movimiento</th>
                                <th style="background: #CCC; text-align: center;font-size: 10px;" colspan="2">Periodo Causación</th>
                                <th style="background: #CCC; text-align: center;font-size: 10px;" colspan="4">Periodo Vacaciones</th>
                                <th style="background: #CCC; text-align: center;font-size: 10px;" colspan="2">Días Pagados</th>
                                <th style="background: #CCC; text-align: center;font-size: 10px;" rowspan="2">Promedio<br>Diario</th>
                                <th style="background: #CCC; text-align: center;font-size: 10px;" rowspan="2">Valor<br>Liquidado</th>
                            </tr>
                            <tr>
                                <th style="background: #CCC; text-align: center;font-size: 10px;">Fecha Inicio</th>
                                <th style="background: #CCC; text-align: center;font-size: 10px;">Fecha Fin</th>
                                <th style="background: #CCC; text-align: center;font-size: 10px;">Fecha Inicio</th>
                                <th style="background: #CCC; text-align: center;font-size: 10px;">Fecha Fin</th>
                                <th style="background: #CCC; text-align: center;font-size: 10px;">Días</th>
                                <th style="background: #CCC; text-align: center;font-size: 10px;">Regreso</th>
                                <th style="background: #CCC; text-align: center;font-size: 10px;">Tiempo</th>
                                <th style="background: #CCC; text-align: center;font-size: 10px;">Dinero</th>
                            </tr>';
                        $totalVac = 0;
                        foreach($novedadesVacacionActual as $novedadVacacion){
                            $tipoMov = str_replace("VACACIONES", "", $novedadVacacion->nombre);
                            $html.='
                                <tr>
                                    <td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">'.$tipoMov.'</td>
                                    <td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.$empleado->fechaIngreso.'</td>
                                    <td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.$novedadVacacion->fechaInicio.'</td>';
                                if($novedadVacacion->idconcepto == 29){
                                    $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.$novedadVacacion->fechaInicio.'</td>';
                                    $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.$novedadVacacion->fechaFin.'</td>';
                                    $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.$novedadVacacion->diasCompensar.'</td>';
                                    $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;" width="50">'.date("Y-m-d",strtotime($novedadVacacion->fechaFin."+1 day")).'</td>';
                                }
                                else{
                                    $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;"></td>';
                                    $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;"></td>';
                                    $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">'.$novedadVacacion->diasCompensar.'</td>';
                                    $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;"></td>';
                                }
                                
                                $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">'.$novedadVacacion->diasCompensar.'</td>';
                                $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">$'.number_format($novedadVacacion->valor,0, ",", ".").'</td>';
                                $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">$'.number_format($novedadVacacion->valor/$novedadVacacion->diasCompensar,0, ",", ".").'</td>';
                                $html.='<td style="border-bottom: 1px solid #B0B0B0;font-size: 10px;">$'.number_format($novedadVacacion->valor,0, ",", ".").'</td>';

                                $html.='</tr>
                            ';
                            $totalVac = $totalVac + $novedadVacacion->valor;
                            if($totalVac<0){
                                $totalVac=0;
                            }
                        }    
                        $html.='
                            <tr>
                                <th style="text-align: right;font-size: 10px;" colspan="9">TOTAL LIQUIDADO VACACIONES</th>
                                <td style="text-align: right; border: 1px solid #B0B0B0;font-size: 10px;" colspan="2">$'.number_format($totalVac,0, ",", ".").'</td>
                            </tr>            
                        </table>
                        </div>
                    <br>
                        <center><h4>Observaciones</h4></center>
                    <br>
                    <div style="border: 2px solid #000; padding: 10px 20px; min-height: 50px;">
                        <br><br><br>
                    </div>
                    <div style="position: absolute; bottom: 40px; width:100%;">
                        <table style="width: 100%; text-align: left;">
                            <tr>
                                <td>COLABORADOR</td>
                                <td></td>
                                <td>LA EMPRESA</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td>Cédula o NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                                <td>NIT</td>
                                <td style="width: 2in; border-bottom: 1px solid #000;"> </td>
                            </tr>
                            <tr>
                                <td colspan="3"></td>
                                <td>Fecha de elaboración:&nbsp;&nbsp;&nbsp; '.date("d/m/Y").'</td>
                            </tr>
                        </table>
                        <table style="width: 100%;">
                            <tr>
                                <th style="border: 1px solid #000; width:33%;">ELABORÓ: '.$userSolicita.'</th>
                                <th style="border: 1px solid #000; width:33%;">REVISÓ: '.$userAprueba.'</th>
                                <th style="border: 1px solid #000; width:33%;">APROBÓ:</th>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="page_break"></div>
                ';
            }            

        }
        $html = substr($html, 0, -30);
            
            $html.='
            </body>
        </html>
        ';
        
        $dompdf->loadHtml($html ,'UTF-8');

        // (Optional) Setup the paper size and orientation
        $dompdf->setPaper('Letter', 'portrait');
        // Render the HTML as PDF
        $dompdf->render();

        // Output the generated PDF to Browser
        $dompdf->stream("Comprobante de Pago ".$idLiquidacionNomina.".pdf", array('compress' => 1, 'Attachment' => 1));
    }

    public function verificarSiPendientes($idEmpresa, $fecha){
        

        $nominasxEmpresa = DB::table("nomina")->where("fkEmpresa","=", $idEmpresa)->get();
        $nominasLiquidadas = DB::table("liquidacionnomina","ln")
        ->select("ln.fkNomina")
        ->join("nomina as n", "n.idNomina", "=","ln.fkNomina")
        ->where("n.fkEmpresa","=",$idEmpresa)
        ->whereRaw("MONTH(ln.fechaLiquida) = MONTH('".$fecha."')")
        ->where("ln.fkEstado","=","5")//5 - TERMINADA
        ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9"])    
        ->groupBy("ln.fkNomina")
        ->get();
        
        if(sizeof($nominasxEmpresa) != sizeof($nominasLiquidadas)){
            $cualesSi = array();
            foreach($nominasxEmpresa as $nominaxEmpresa){
                foreach($nominasLiquidadas as $nominaLiquidadas){
                    if($nominaxEmpresa->idNomina == $nominaLiquidadas->fkNomina){
                        $cualesSi[$nominaxEmpresa->idNomina] = $nominaxEmpresa->nombre;
                    }
                }
            }

            $cualesNo = array();
            foreach($nominasxEmpresa as $nominaxEmpresa){
                if(!isset($cualesSi[$nominaxEmpresa->idNomina])){
                    array_push($cualesNo, $nominaxEmpresa->nombre);
                }
            }

            
            return response()->json([
                "success" => false,
                "mensaje" => "Aun cuenta con nominas sin crear (".implode(",",$cualesNo)."), desea continuar? "
            ]);
        }
        
        
        $liquidacionesPendientes = DB::table("liquidacionnomina","ln")
        ->join("nomina as n", "n.idNomina", "=","ln.fkNomina")
        ->where("n.fkEmpresa","=",$idEmpresa)
        ->whereRaw("MONTH(ln.fechaLiquida) = MONTH('".$fecha."')")
        ->where("ln.fkEstado","=","6")//6- SOLICITADA
        ->first();

        if(isset($liquidacionesPendientes)){
            return response()->json([
                "success" => false,
                "mensaje" => "Aun cuenta con liquidaciones sin terminar, desea continuar?"
            ]);
        }
        else{
            return response()->json([
                "success" => true
            ]);
        }
    }  

    public function reporteBoucherPdfNuevoDiseno($idLiquidacionNomina){

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", generó un reporte pdf de la liquidacion:".$idLiquidacionNomina);

        $empresayLiquidacion = DB::table("empresa", "e")
        ->select("e.*", "ln.*", "n.nombre as nom_nombre")
        ->join("nomina as n","n.fkEmpresa", "e.idempresa")
        ->join("liquidacionnomina as ln","ln.fkNomina", "n.idNomina")
        ->where("ln.idLiquidacionNomina","=",$idLiquidacionNomina)
        ->first();


        $base64 = "";
        if(is_file($this->rutaBaseImagenes.'storage/logosEmpresas/'.$empresayLiquidacion->logoEmpresa)){
            $imagedata = file_get_contents($this->rutaBaseImagenes.'storage/logosEmpresas/'.$empresayLiquidacion->logoEmpresa);
                    // alternatively specify an URL, if PHP settings allow
            $base64 = base64_encode($imagedata);
        }
        else{
            unset($empresayLiquidacion->logoEmpresa);
        }
        $arrMeses = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];

        $dataUsu = Auth::user();

        $dompdf = new Dompdf();
        $dompdf->getOptions()->setChroot($this->rutaBaseImagenes);
        $dompdf->getOptions()->setIsPhpEnabled(true);
        $bouchersPago = DB::table("boucherpago","bp")->where("fkLiquidacion","=",$idLiquidacionNomina)->get();
        
        ob_start();?>
        <!DOCTYPE html>
            <html>
                <head>
                    <meta charset='utf-8'>
                    <style>
                        /** 
                            Set the margins of the page to 0, so the footer and the header
                            can be of the full height and width !
                        **/
                        @page {
                            margin: 0.5cm 0.5cm;
                        }

                        /** Define now the real margins of every page in the PDF **/
                        body {
                            margin-top: 3.5cm;
                            margin-left: 0cm;
                            margin-right: 0cm;
                            margin-bottom: 0cm;
                            font-family: sans-serif;
                            font-size: 12px;
                            padding: 0 20px;
                        }

                        /** Define the header rules **/
                        header {
                            position: fixed;
                            top: 0.5cm;
                            left: 0.5cm;
                            right: 0.5cm;
                            height: 3cm;
                        }
                        .logoEmpresa{
                            max-width: 3cm;
                            max-height: 3cm;
                        }
                        .tablaHeader{
                            border: 1px solid;
                            width: 100%;
                        }
                        .tablaHeader th, .tablaHeader td{
                            text-align: left;
                        }
                        .tablaDatos{
                            border-collapse: collapse;
                            width: 100%
                        }
                        .tablaDatos td{
                            
                            font-size: 9px;
                        }
                        .tablaDatos th{
                            font-size: 9px;
                        }
                        .tablaDatos{
                            width: 100% !important;
                        }
                        .tablaDatos td.left{
                            text-align: left;
                        }
                        .tablaDatos td.arriba *, .tablaDatos td.arriba{
                            vertical-align: top;
                            padding: 0 5px;
                        }
                        .azul1{
                            background: #afeeee;
                        }
                        .azul2{
                            background: #add8e6;
                        }
                        .datosEmpleado b,.datosEmpleado span{
                            padding: 5px 10px;
                        }
                        .totalFinal{
                            width: 100%;
                        }
                        .totalFinal th{
                            font-size: 20px;
                        }
                        .boucherTab{
                            border: 1px solid;
                            width: 100%;
                            margin-bottom: 5px;
                        }
                        .boucherTab th{
                            background: #CCC;
                            font-size: 16px;
                        }
                        .pagenum:before {
                            content: counter(page);
                        }
                    </style>
                    <title>Reporte Nomina <?php echo $idLiquidacionNomina; ?></title>
                </head>
                <body>
                    <header>
                        <table class="tablaHeader">
                            <tr>
                                <td rowspan="4">
                                <?php echo (isset($empresayLiquidacion->logoEmpresa) ? '<img style="max-width: 50px; max-height: 50px; margin-right: 5px;" src="data:image/png;base64,'.$base64.'" class="logoEmpresa" />' : ''); ?>
                                </td>
                                <th>LISTADO DE NOMINA</th>
                                <th>Fecha</th>
                                <td><?php echo date("Y-m-d H:i:s"); ?></td>
                            </tr>
                            <tr>
                                <th><?php echo $empresayLiquidacion->razonSocial; ?></th>
                                <th>Usuario:</th>
                                <td><?php echo $dataUsu->username; ?></td>
                            </tr>
                            <tr>
                                <th><?php echo number_format($empresayLiquidacion->documento,0)." - ".$empresayLiquidacion->digitoVerificacion; ?></th>
                                <th>Reporte:</th>
                                <td>NOM_U_<?php echo $idLiquidacionNomina; ?></td>
                            </tr>
                            <tr>
                                <th>PRENÓMINA <?php echo $arrMeses[intval(date("m",strtotime($empresayLiquidacion->fechaLiquida))) - 1]." - ".date("Y",strtotime($empresayLiquidacion->fechaLiquida)); ?></th>
                                <th>Página:</th>
                                <td>
                                    <span class="pagenum"></span>
                                </td>
                            </tr>
                        </table>
                    </header>
                    
                        <table class="tablaDatos">
                            <?php
                                $totalGeneralPag = 0;
                                $totalGeneralDesc = 0;
                                $totalGeneral = 0;
                                foreach($bouchersPago as $boucherPago){
                                    $idBoucherPago = $boucherPago->idBoucherPago;
                                    $empleado = DB::table("empleado","e")
                                        ->select("e.idempleado", "e.fechaIngreso","e.tipoRegimen", "e.fkNomina",
                                        "dp.primerNombre","dp.segundoNombre", 
                                        "dp.primerApellido","dp.segundoApellido","ti.nombre as tipoidentificacion", 
                                        "dp.numeroIdentificacion", "cargo.nombreCargo" , DB::raw("cargo2.nombreCargo as nombreCargoPeriodo"), "bp.fkPeriodoActivo")
                                        ->join("boucherpago as bp","bp.fkEmpleado", "e.idempleado")
                                        ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
                                        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion")
                                        ->join("periodo as p","p.idPeriodo","=","bp.fkPeriodoActivo")
                                        ->leftJoin("cargo","cargo.idCargo","=","e.fkCargo")
                                        ->leftJoin("cargo as cargo2","cargo2.idCargo","=","p.fkCargo")
                                        ->where("bp.idBoucherPago","=",$idBoucherPago)
                                        ->first();

                                    if(!isset($empleado)){
                                        dd($idBoucherPago);
                                    }
                                    $empleado->nombreCargo = ($empleado->nombreCargoPeriodo ?? $empleado->nombreCargo);

                                    $centroCosto = DB::table("centrocosto","cc")
                                    ->join("empleado_centrocosto as ecc","ecc.fkCentroCosto","=","cc.idcentroCosto")
                                    ->where("ecc.fkEmpleado","=",$empleado->idempleado)
                                    ->where("ecc.fkEmpleado","=",$empleado->fkPeriodoActivo)
                                    ->first();


                                    $nomina = DB::table("nomina","n")
                                    ->where("n.idNomina","=",$empleado->fkNomina)->first();
                                    $pension = DB::table("tercero", "t")->
                                    select(["t.razonSocial", "t.numeroIdentificacion", "t.idTercero", "ti.nombre as tipoidentificacion", "t.digitoVer"])
                                    ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","t.fkTipoIdentificacion")
                                    ->join("afiliacion as a","t.idTercero", "=","a.fkTercero")
                                    ->where("a.fkEmpleado","=",$empleado->idempleado)
                                    ->where("a.fkTipoAfilicacion","=","4") //4-Pensión Obligatoria 
                                    ->first();
                        
                                    $salud = DB::table("tercero", "t")->select(["t.razonSocial", "t.numeroIdentificacion", "t.idTercero",
                                    "ti.nombre as tipoidentificacion", "t.digitoVer"])
                                    ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","t.fkTipoIdentificacion")
                                    ->join("afiliacion as a","t.idTercero", "=","a.fkTercero")
                                    ->where("a.fkEmpleado","=",$empleado->idempleado)
                                    ->where("a.fkTipoAfilicacion","=","3") //3-Salud
                                    ->first();
                        
                                    $cesantiasEmp = DB::table("tercero", "t")->select(["t.razonSocial", "t.numeroIdentificacion", "t.idTercero",
                                        "ti.nombre as tipoidentificacion", "t.digitoVer"])
                                    ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","t.fkTipoIdentificacion")
                                    ->join("afiliacion as a","t.idTercero", "=","a.fkTercero")
                                    ->where("a.fkEmpleado","=",$empleado->idempleado)
                                    ->where("a.fkTipoAfilicacion","=","1") //2-FONDO CESANTIAS
                                    ->first();
                        
                                    $entidadBancaria = DB::table("tercero", "t")->select(["t.razonSocial", "e.numeroCuenta"])
                                    ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","t.fkTipoIdentificacion")
                                    ->join("empleado as e", "e.fkEntidad", "=","t.idTercero")
                                    ->where("e.idempleado","=",$empleado->idempleado)
                                    ->first();
                        
                                    $idItemBoucherPago = DB::table("item_boucher_pago","ibp")
                                    ->join("concepto AS c","c.idconcepto","=", "ibp.fkConcepto")
                                    ->where("ibp.fkBoucherPago","=",$idBoucherPago)
                                    ->get();
                        
                                    $itemsBoucherPagoFueraNomina = DB::table("item_boucher_pago_fuera_nomina","ibpfn")
                                    ->join("concepto AS c","c.idconcepto","=", "ibpfn.fkConcepto")
                                    ->where("ibpfn.fkBoucherPago","=",$idBoucherPago)
                                    ->get();
                        
                                    $periodoPasadoReintegro = DB::table("periodo")
                                    ->where("fkEstado","=","2")
                                    ->where("fkEmpleado", "=", $empleado->idempleado)
                                    ->where("fechaFin",">=",$empresayLiquidacion->fechaInicio)
                                    ->where("fkNomina","=",$empresayLiquidacion->fkNomina)
                                    ->first();
                        
                                    if(isset($periodoPasadoReintegro)){
                                        $conceptoSalario = new stdClass;
                                        $conceptoSalario->valor = $periodoPasadoReintegro->salario;
                                    }
                                    else{
                                        $conceptoSalario = DB::table("conceptofijo")->where("fkEmpleado","=",$empleado->idempleado)
                                        ->whereIn("fkConcepto",[1,2,53,54,154])->first();
                                    }


                                    $novedadesRetiro = DB::table("novedad","n")
                                    ->select("r.fecha", "r.fechaReal","mr.nombre as motivoRet")
                                    ->join("retiro AS r", "r.idRetiro","=","n.fkRetiro")
                                    ->join("motivo_retiro as mr","mr.idMotivoRetiro","=","r.fkMotivoRetiro")
                                    ->where("n.fkEmpleado", "=", $empleado->idempleado)
                                    ->whereIn("n.fkEstado",["7", "8"])
                                    ->whereNotNull("n.fkRetiro")
                                    ->whereBetween("n.fechaRegistro",[$empresayLiquidacion->fechaInicio, $empresayLiquidacion->fechaFin])->first();

                                    $liquidacion = "Liquidación de Nómina";
                                    if(($empresayLiquidacion->fkTipoLiquidacion == "2"||$empresayLiquidacion->fkTipoLiquidacion == "3")
                                        && isset($novedadesRetiro->fecha)){
                                            $liquidacion = "Liquidación de contrato";
                                    }

                                    echo "<tr><td style='padding: 0px;'>
                                    <table class='boucherTab'>";
                                    echo '<tr>
                                        <td colspan="7">
                                            <div class="datosEmpleado">
                                                <div class="row">
                                                    <b>Empleado:</b>
                                                    <span>'.$empleado->tipoidentificacion.' - '.$empleado->numeroIdentificacion.'</span>
                                                    <span>'.$empleado->primerApellido.' '.$empleado->segundoApellido.' '.$empleado->primerNombre.' '.$empleado->segundoNombre.'</span>
                                                    <b>Fecha de ingreso:</b>
                                                    <span>'.$empleado->fechaIngreso.'</span>
                                                </div>
                                                <div class="row">
                                                    <b>Sueldo básico:</b>
                                                    <span>'.number_format($conceptoSalario->valor,0, ",", ".").'</span>
                                                    <b>C. Costo:</b>
                                                    <span>'.($centroCosto->nombre ?? "").'</span>
                                                    <b>Cargo:</b>
                                                    <span>'.$empleado->nombreCargo.'</span>
                                                </div>
                                                <div>
                                                    <b>IBC Salud:</b>
                                                    <span>'.number_format($boucherPago->ibc_eps,0, ",", ".").'</span>
                                                    <b>IBC Pensión:</b>
                                                    <span>'.number_format($boucherPago->ibc_eps,0, ",", ".").'</span>
                                                    <b>Salud:</b>
                                                    <span>'.(isset($salud->razonSocial) ? $salud->razonSocial : '').'</span>
                                                    <b>Pensión:</b>
                                                    <span>'.(isset($pension->razonSocial) ? $pension->razonSocial : '').'</span>
                                                    <b>Cesantias:</b>
                                                    <span>'.(isset($cesantiasEmp->razonSocial) ? $cesantiasEmp->razonSocial : '').'</span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Concepto</th>
                                        <th>Liquidación</th>
                                        <th>Cant</th>
                                        <th>Unidad</th>
                                        <th>Devengos</th>
                                        <th>Deducidos</th>   
                                        <th>Neto a Pagar</th>   
                                    </tr>
                                    ';
                                    $totalPag = 0;
                                    $totalDesc = 0;
                                    foreach($idItemBoucherPago as $itemBoucherPago){
                                        echo '<tr>
                                            <td>'.$itemBoucherPago->nombre.'</td>
                                            <td>'.$liquidacion.'</td>
                                            <td style="text-align: right;">'.$itemBoucherPago->cantidad.'</td>
                                            <td style="text-align: right;">'.$itemBoucherPago->tipoUnidad.'</td>';
                                            
                                            if($itemBoucherPago->valor > 0){
                                                echo '<td style="text-align: right;">$'.number_format($itemBoucherPago->valor,0, ",", ".").'</td>
                                                    <td></td>';
                                                $totalPag = $totalPag + $itemBoucherPago->valor;
                                            }
                                            else{
                                                echo '<td></td>
                                                    <td style="text-align: right;">$'.number_format($itemBoucherPago->valor*-1,0, ",", ".").'</td>';
                                                $totalDesc = $totalDesc + $itemBoucherPago->valor;
                                            }
                                            echo '<td></td>';
                                        echo '</tr>';
                                    }
                                    $totalBoucher = $totalPag + $totalDesc;
                                    echo '<tr>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td style="border-top: 1px solid #B0B0B0;">Total</td>
                                        <td style="border-top: 1px solid #B0B0B0;text-align: right;">$'.number_format($totalPag, 0, ",", ".").'</td>
                                        <td style="border-top: 1px solid #B0B0B0;text-align: right;">$'.number_format($totalDesc*-1,0, ",", ".").'</td>
                                        <td style="border-top: 1px solid #B0B0B0;text-align: right;">$'.number_format($totalBoucher,0, ",", ".").'</td>
                                    </tr>';
                                    $totalGeneralPag = $totalGeneralPag + $totalPag;
                                    $totalGeneralDesc = $totalGeneralDesc + $totalDesc;
                                    $totalGeneral = $totalGeneral + $totalBoucher;
                                    echo "</table></td></tr>";
                                }
                            ?>
                            <tr>
                                <table class="totalFinal">
                                    <tr>
                                        <th>Empleados: <span><?php echo sizeof($bouchersPago); ?></span></th>
                                        <th>Total General: </th>
                                        <th><?php echo number_format($totalGeneralPag,0, ",", "."); ?></th>
                                        <th><?php echo number_format($totalGeneralDesc*-1,0, ",", "."); ?></th>
                                        <th><?php echo number_format($totalGeneral,0, ",", "."); ?></th>
                                    </tr>
                                </table>
                            </tr>
                        </table>
                </body>
            </html>
        <?php
        $html = ob_get_clean();
        
        $dompdf->loadHtml($html ,'UTF-8');
    
        // (Optional) Setup the paper size and orientation
        $dompdf->setPaper('letter', 'landscape');
        // Render the HTML as PDF
        $dompdf->render();

        // Output the generated PDF to Browser
        $dompdf->stream("Reporte Nomina ".$idLiquidacionNomina.".pdf", array('compress' => 1, 'Attachment' => 1));

    }

    public function reportePorEmpleado(){
        $dataUsu = UsuarioController::dataAdminLogueado();
        $empresas = DB::table("empresa", "e");
        if(isset($dataUsu) && $dataUsu->fkRol == 2){            
            $empresas = $empresas->whereIn("idempresa", $dataUsu->empresaUsuario);
        }
        $empresas = $empresas->orderBy("razonSocial")->get();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu 'Reporte por empleado'");

        return view('/reportes.porEmpleado',["empresas" => $empresas, "dataUsu" => $dataUsu ]);
    }   
    public function liquidacionesxEmpleado($idEmpleado, $idPeriodo){
        $liquidaciones = DB::table("liquidacionnomina","ln")
        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")
        ->where("bp.fkEmpleado","=",$idEmpleado)
        ->where("bp.fkPeriodoActivo","=",$idPeriodo)
        ->get();
        $html = '<option value=""></option>';

        foreach($liquidaciones as $liquidacion){
            $html .='<option value="'.$liquidacion->idBoucherPago.'">'.$liquidacion->fechaLiquida.'</option>';
        }
        return response()->json([
            "success" => true,
            "mensaje" => $html
        ]);
    }

    public function indexReportePrestamos(){
        $dataUsu = UsuarioController::dataAdminLogueado();
        $empresas = DB::table("empresa", "e");
        if(isset($dataUsu) && $dataUsu->fkRol == 2){            
            $empresas = $empresas->whereIn("idempresa", $dataUsu->empresaUsuario);
        }
        $empresas = $empresas->orderBy("razonSocial")->get();

        $conceptos = DB::table("concepto","c")
        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","c.idconcepto")
        ->whereIn("gcc.fkGrupoConcepto",["41","42"])
        ->orderBy("nombre")->get();
        
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu 'Reporte de prestamos'");

        return view('/reportes.prestamos',["empresas" => $empresas, "conceptos" => $conceptos, "dataUsu" => $dataUsu]);
    }
    public function conceptosPorTipo($tipoReporte){

        $conceptos = array();
        if($tipoReporte == "Ambos"){
            $conceptos = DB::table("concepto","c")
            ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","c.idconcepto")
            ->whereIn("gcc.fkGrupoConcepto",["41","42"])
            ->orderBy("nombre")->get();
        }
        else if($tipoReporte == "Solo Prestamos"){
            $conceptos = DB::table("concepto","c")
            ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","c.idconcepto")
            ->where("gcc.fkGrupoConcepto","=","41")
            ->orderBy("nombre")->get();
        }
        else if($tipoReporte == "Solo Embargos"){
            $conceptos = DB::table("concepto","c")
            ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","c.idconcepto")
            ->where("gcc.fkGrupoConcepto","=","42")
            ->orderBy("nombre")->get();
        }
        $html = "<option value=''></option>";
        foreach($conceptos as $concepto){
            $html .= '<option value="'.$concepto->idconcepto.'">'.$concepto->nombre.'</option>';
        }
        
        return response()->json([
            "html" => $html
        ]);
        
    }
    public function generarReportePrestamo(Request $req){
        
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", generó un 'Reporte de prestamos'");

        $columnas = ["p.*", 
        "ti.nombre as tipoidentificacion", 
        "dp.primerApellido", "dp.segundoApellido", 
        "dp.primerNombre", 
        "dp.segundoNombre",
        "dp.numeroIdentificacion", 
        "concepto.nombre as nm_concepto", 
        "periocidad.per_nombre as nm_periocidad", 
        "estado.nombre as nm_estado"];
        if($req->tipoReporte=="Ambos" || $req->tipoReporte=="Solo Embargos"){
            array_push($columnas, "embargo.*", "terceroJuzgado.razonSocial as nombreJuzgado", 
            "terceroDemandante.primerApellido as primerApellidoDem", "terceroDemandante.segundoApellido as segundoApellidoDem", 
            "terceroDemandante.primerNombre as primerNombreDem", "terceroDemandante.segundoNombre as segundoNombreDem");
        }

        $prestamo = DB::table("prestamo","p")
        ->select($columnas)
        ->join("empleado as e","e.idempleado", "=","p.fkEmpleado")
        ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion")
        ->join("periocidad","periocidad.per_id", "=", "p.fkPeriocidad")
        ->join("concepto","concepto.idconcepto","=","p.fkConcepto")
        ->join("estado","estado.idestado","=","p.fkEstado");

        if($req->tipoReporte=="Ambos"){
            $prestamo = $prestamo->leftJoin("embargo","embargo.fkPrestamo", "=", "p.idPrestamo");
        }
        else if($req->tipoReporte=="Solo Embargos"){
            $prestamo = $prestamo->join("embargo","embargo.fkPrestamo", "=", "p.idPrestamo");
        } 

        if($req->tipoReporte=="Ambos" || $req->tipoReporte=="Solo Embargos"){
            $prestamo = $prestamo->leftJoin("tercero as terceroJuzgado","terceroJuzgado.idtercero", "=", "embargo.fkTerceroJuzgado");
            $prestamo = $prestamo->leftJoin("tercero as terceroDemandante","terceroDemandante.idtercero", "=", "embargo.fkTerceroDemandante");
        }
         
        if(isset($req->infoEmpresa)){
            $prestamo = $prestamo->where("e.fkEmpresa","=",$req->infoEmpresa);
        }
        if(isset($req->infoNomina)){
            $prestamo = $prestamo->where("e.fkNomina","=",$req->infoNomina);
        }
        if(isset($req->idEmpleado)){
            $prestamo = $prestamo->where("e.idempleado","=",$req->idEmpleado);
        }
        if(isset($req->concepto)){
            $prestamo = $prestamo->where("concepto.idconcepto","=",$req->concepto);
        }        
        $prestamo = $prestamo->get();
        




        $arrDef = array([
            "Tipo Documento",
            "Documento",
            "Primer Nombre",
            "Segundo Nombre",
            "Primer Apellido",
            "Segundo Apellido",
            "Concepto",
            "Codigo Prestamo",
            "Motivo Prestamo",
            "Monto Inicial",
            "Saldo Actual",
            "Periocidad",
            "Tipo Descuento",
            "Número de Cuotas",
            "Valor Cuota",
            "Porcentaje Cuota",
            "Fecha Inicio",
            "Fecha Desembolso",
            "Pignoracion",
            "Desde Salario Minimo",
            "Estado"
        ]);
        if($req->tipoReporte=="Ambos" || $req->tipoReporte=="Solo Embargos"){
            array_push($arrDef[0], 
            "Número de Embargo", 
            "Número de Oficio",
            "Número de Proceso", 
            "Juzgado",
            "Demandante",
            "Número de Cuenta Judicial",
            "Número de Cuenta Demandante",
            "Valor Total Embargo"
            );
        }

        foreach($prestamo as $pres){
            $arrFila = [
                $pres->tipoidentificacion,
                $pres->numeroIdentificacion,
                $pres->primerNombre,
                $pres->segundoNombre,
                $pres->primerApellido,
                $pres->segundoApellido,
                $pres->nm_concepto,
                $pres->codPrestamo,
                $pres->motivoPrestamo,
                $pres->montoInicial,
                $pres->saldoActual,
                $pres->nm_periocidad,
                ($pres->tipoDescuento == "1" ? "Cuotas" : ($pres->tipoDescuento == "2" ? "Valor Fijo" : ($pres->tipoDescuento == "3" ? "Porcentaje" : ""))),
                $pres->numCuotas,
                $pres->valorCuota,
                $pres->porcentajeCuota,
                $pres->fechaInicio,
                $pres->fechaDesembolso,
                ($pres->pignoracion == "1" ? "SI" : "NO"),
                ($pres->hastaSalarioMinimo == "1" ? "SI" : "NO"),
                $pres->nm_estado
            ];
            if($req->tipoReporte=="Ambos" || $req->tipoReporte=="Solo Embargos"){
                array_push(
                    $arrFila, $pres->numeroEmbargo, $pres->numeroOficio, $pres->numeroProceso, $pres->nombreJuzgado,
                    ($pres->primerApellidoDem." ".$pres->segundoApellidoDem." ".$pres->primerNombreDem." ".$pres->segundoNombreDem),
                    $pres->numeroCuentaJudicial, $pres->numeroCuentaDemandante, $pres->valorTotalEmbargo                    
                );
            }
            array_push($arrDef, $arrFila);
        }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename=ReportePrestamos.csv');

        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->setDelimiter(';');
        $csv->insertAll($arrDef);
        $csv->setOutputBOM(Reader::BOM_UTF8);
        $csv->output('ReportePrestamos.csv');

    }
    
    public function envioCorreosReporte(){
        $dataUsu = UsuarioController::dataAdminLogueado();
        $empresas = DB::table("empresa", "e");
        if(isset($dataUsu) && $dataUsu->fkRol == 2){            
            $empresas = $empresas->whereIn("idempresa", $dataUsu->empresaUsuario);
        }
        $empresas = $empresas->orderBy("razonSocial")->get();

        $mensajes = DB::table("mensaje")
        ->whereIn("tipo",[1,4,7,8])
        ->whereNull("fkEmpresa")->get();

        $tipos_liquidacion = DB::table("tipoliquidacion")->get();        
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu 'Enviar correos'");

        return view('/reportes.envioCorreos',[
            "empresas" => $empresas,
            "mensajes" => $mensajes,
            "dataUsu" => $dataUsu,
            "tipos_liquidacion" => $tipos_liquidacion
        ]);
    }

    public function generarColaCorreo(Request $req){

        $id_envio_correo_reporte = DB::table("envio_correo_reporte")->insertGetId([
            "fkMensaje" => $req->mensaje,
            "fechaInicio" => $req->fechaInicio,
            "fechaFin" => $req->fechaFin,
            "fkEmpresa" => $req->empresa,
            "fkNomina" => $req->infoNomina
        ]);
        $numRegistros = 0;

        if($req->mensaje == "4"){

            if(!isset($req->nProceso) && (!isset($req->fechaInicio) || !isset($req->fechaFin))){
                $usu = UsuarioController::dataAdminLogueado();
                return view('layouts/respuestaGen', [
                    "dataUsu" => $usu,
                    "titulo" => "Seleccione fechas o número de proceso",
                    "mensaje" => "Debes seleccionar las fechas o el número de proceso, cuando el mensaje sea de comprobante de pago"
                ]);
            }
            
            $liquidacion = DB::table("boucherpago","bp")
            ->select("bp.*")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=", "bp.fkLiquidacion")
            ->join("nomina as n","n.idNomina", "=","ln.fkNomina");
            if(isset($req->centroCosto)){
                $liquidacion = $liquidacion->join("empleado_centrocosto as ecc","ecc.fkEmpleado", "=","bp.fkEmpleado");
            }


            $liquidacion = $liquidacion->where("n.fkEmpresa","=",$req->empresa)
            ->where("ln.fkTipoLiquidacion","<>","11");
            
            if(isset($req->infoNomina)){
                $liquidacion = $liquidacion->where("n.idNomina","=",$req->infoNomina);
            }
            
            if(isset($req->idEmpleado)){
                $liquidacion = $liquidacion->where("bp.fkEmpleado","=",$req->idEmpleado);
            }

            if(isset($req->centroCosto)){
                $liquidacion = $liquidacion->where("ecc.fkCentroCosto","=",$req->centroCosto);
            }

            if(isset($req->tipoliquidacion)){
                $liquidacion = $liquidacion->where("ln.fkTipoLiquidacion","=",$req->tipoliquidacion);
            }

            if(isset($req->nProceso)){
                $nProceso = explode(",", $req->nProceso);
                $nProceso = array_map('trim', $nProceso);                
                $liquidacion = $liquidacion->whereIn("ln.idLiquidacionNomina",$nProceso);
            }
            if(isset($req->fechaInicio) && isset($req->fechaFin)){
                $sqlWhere = "( 
                    ('".$req->fechaInicio."' BETWEEN ln.fechaInicio AND ln.fechaFin) OR
                    ('".$req->fechaFin."' BETWEEN ln.fechaInicio AND ln.fechaFin) OR
                    (ln.fechaInicio BETWEEN '".$req->fechaInicio."' AND '".$req->fechaFin."') OR
                    (ln.fechaFin BETWEEN '".$req->fechaInicio."' AND '".$req->fechaFin."')
                )";
                $liquidacion = $liquidacion->whereRaw($sqlWhere);
            }
            
            $liquidacion = $liquidacion->get();
            foreach($liquidacion as $liquida){
                DB::table("item_envio_correo_reporte")->insert([
                    "fkEnvioCoreoReporte" => $id_envio_correo_reporte,
                    "fkEstado" => "3",
                    "fkEmpleado" => $liquida->fkEmpleado,
                    "fkBoucherPago" => $liquida->idBoucherPago
                ]);
                $numRegistros++;
            }            
        }
        else if($req->mensaje == "7"){
            if(!isset($req->fechaInicio) || !isset($req->fechaFin)){
                $usu = UsuarioController::dataAdminLogueado();
                return view('layouts/respuestaGen', [
                    "dataUsu" => $usu,
                    "titulo" => "Seleccione fechas",
                    "mensaje" => "Debes seleccionar las fechas obligatoriamente cuando el mensaje no sea de comprobante de pago"
                ]);
            }

            $anioInicio = date("Y",strtotime($req->fechaInicio));
            $anioFin = date("Y",strtotime($req->fechaFin));

            $empleados = DB::table("empleado","e")
            ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
            ->join("periodo as p","p.fkEmpleado","=","e.idempleado")
            ->join("nomina as n","n.idNomina","=","p.fkNomina");
            if(isset($req->centroCosto)){
                $empleados = $empleados->join("empleado_centrocosto as ecc","ecc.fkEmpleado", "=","e.idempleado");
            }

            $empleados = $empleados->where("n.fkEmpresa","=", $req->empresa)            
            ->whereRaw("CAST(CONCAT('".$anioInicio."-',MONTH(dp.fechaNacimiento),'-',DAY(dp.fechaNacimiento)) as DATE) >= '".$req->fechaInicio."'")
            ->whereRaw("CAST(CONCAT('".$anioFin."-',MONTH(dp.fechaNacimiento),'-',DAY(dp.fechaNacimiento)) as DATE) <= '".$req->fechaFin."'");            
            
            if(isset($req->infoNomina)){
                $empleados = $empleados->where("p.fkNomina","=", $req->infoNomina);
            }
            
            if(isset($req->centroCosto)){
                $empleados = $empleados->where("ecc.fkCentroCosto","=",$req->centroCosto);
            }


            if(isset($req->idEmpleado)){
                $empleados = $empleados->where("e.idempleado","=",$req->idEmpleado);
            }
            $empleados = $empleados->get();
            foreach($empleados as $empleado){
                DB::table("item_envio_correo_reporte")->insert([
                    "fkEnvioCoreoReporte" => $id_envio_correo_reporte,
                    "fkEstado" => "3",
                    "fkEmpleado" => $empleado->idempleado
                ]);
                $numRegistros++;
            } 
        }
        else{
            if(!isset($req->fechaInicio) || !isset($req->fechaFin)){
                $usu = UsuarioController::dataAdminLogueado();
                return view('layouts/respuestaGen', [
                    "dataUsu" => $usu,
                    "titulo" => "Seleccione fechas",
                    "mensaje" => "Debes seleccionar las fechas obligatoriamente cuando el mensaje no sea de comprobante de pago"
                ]);
            }
            
            $empleados = DB::table("empleado","e")
            ->join("periodo as p","p.fkEmpleado","=","e.idempleado")
            ->join("nomina as n","n.idNomina","=","p.fkNomina");
            if(isset($req->centroCosto)){
                $empleados = $empleados->join("empleado_centrocosto as ecc","ecc.fkEmpleado", "=","e.idempleado");
            }
            $empleados = $empleados->where("n.fkEmpresa","=", $req->empresa)
            ->whereBetween("p.fechaInicio",[ $req->fechaInicio,  $req->fechaFin]);

            if(isset($req->idEmpleado)){
                $empleados = $empleados->where("e.idempleado","=",$req->idEmpleado);
            }
            if(isset($req->infoNomina)){
                $empleados = $empleados->where("p.fkNomina","=", $req->infoNomina);
            }
            if(isset($req->centroCosto)){
                $empleados = $empleados->where("ecc.fkCentroCosto","=",$req->centroCosto);
            }
                        
            $empleados = $empleados->get();
            foreach($empleados as $empleado){
                DB::table("item_envio_correo_reporte")->insert([
                    "fkEnvioCoreoReporte" => $id_envio_correo_reporte,
                    "fkEstado" => "3",
                    "fkEmpleado" => $empleado->idempleado
                ]);
                $numRegistros++;
            } 
        }
        DB::table("envio_correo_reporte")
        ->where("id_envio_correo_reporte", "=", $id_envio_correo_reporte)
        ->update([
            "numActual" => "0",
            "numRegistros" => $numRegistros
        ]);


        return redirect(action('ReportesNominaController@verColaCorreo',[$id_envio_correo_reporte]));

    }
    public function verColaCorreo($idEnvioCorreo){
        $empleados = DB::table('item_envio_correo_reporte',"iec")
        ->select("dp.*","ti.nombre as tipoidentificacion", "est.nombre as estado", "iec.mensaje")
        ->join("empleado as e","e.idempleado", "=","iec.fkEmpleado")
        ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","dp.fkTipoIdentificacion")
        ->join("estado as est", "est.idEstado", "=","iec.fkEstado")
        ->where("iec.fkEnvioCoreoReporte", "=",$idEnvioCorreo)
        ->get();
        
        $usu = UsuarioController::dataAdminLogueado();

        $envioxReporte = DB::table("envio_correo_reporte")->where("id_envio_correo_reporte","=",$idEnvioCorreo)->first();
        return view('reportes/verCorreos', [
            "empleados" => $empleados,
            "envioxReporte" => $envioxReporte,
            "dataUsu" => $usu
        ]);
    }    

}