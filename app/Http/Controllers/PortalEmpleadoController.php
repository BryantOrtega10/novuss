<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SoloPassRequest;
use App\EmpleadoModel;
use App\Ubicacion;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Dompdf\Dompdf;
use DateTime;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class PortalEmpleadoController extends Controller
{
    public function index() {
        $dataUsu = Auth::user();
        
        $idPeriodo = session('idPeriodo');
        if(!isset($idPeriodo)){
            $ultimoPeriodo = DB::table("periodo")
            ->where("fkEmpleado","=",$dataUsu->fkEmpleado)
            ->orderBy("idPeriodo","desc")
            ->first();
    
            session(['idPeriodo' => $ultimoPeriodo->idPeriodo]);
            $idPeriodo = $ultimoPeriodo->idPeriodo;
        }

        

        $dataEmple = DB::table('empleado')
        ->join('datospersonales', 'empleado.fkDatosPersonales', 'datospersonales.idDatosPersonales')
        ->select(
            'datospersonales.primerNombre',
            'datospersonales.segundoNombre',
            'datospersonales.primerApellido',
            'datospersonales.segundoApellido',
            'datospersonales.foto'            
        )
        ->where('idempleado', $dataUsu->fkEmpleado)->first();

         
        $fotoEmple = Storage::url($dataEmple->foto);
        
        
        $periodoActivo = DB::table("periodo")
        ->where("idPeriodo","=",$idPeriodo)
        ->first();

        $dataEmpr = DB::table('empresa')->select(
            'empresa.idempresa',
            'empresa.logoEmpresa',
            'empresa.razonSocial',
            'empresa.permisosGenerales',
            'periodo.fkNomina'
        )
        ->join('nomina', 'nomina.fkEmpresa', 'empresa.idempresa')
        ->join('periodo', 'periodo.fkNomina', 'nomina.idNomina')
        ->where('periodo.idPeriodo', $periodoActivo->idPeriodo)
        ->first();
        
        $dataEmpr->fkNomina = ($periodoActivo->fkNomina ?? $dataEmpr->fkNomina);
            
        if (is_null($dataEmple->foto) || $dataEmple->foto === '') {           
            if (is_null($dataEmpr->logoEmpresa)) {
                $fotoEmple = '/img/noimage.png';
            } else {
                $fotoEmple = '/storage/logosEmpresas/'.$dataEmpr->logoEmpresa;
            }
        }
        
        
        $periodos = DB::table("periodo")
        ->select("periodo.*","empresa.razonSocial")
        ->join('nomina', 'nomina.idNomina', 'periodo.fkNomina')
        ->join('empresa', 'empresa.idempresa', 'nomina.fkEmpresa')
        ->where("fkEmpleado","=",$dataUsu->fkEmpleado)
        ->where("fkEstado","=","1")
        ->orderBy("empresa.razonSocial")
        ->get();
        //dd($periodos);
        
        return view('/portalEmpleado.inicio', [
            'dataUsu' => $dataUsu,
            'dataEmple' => $dataEmple,
            'dataEmpr' => $dataEmpr,
            'fotoEmple' => $fotoEmple,
            'idPeriodo' => $idPeriodo,
            'periodos' => $periodos
        ]);
    }

    public function infoLaboralEmpleado($idEmple) {


        $infoEmpleado = DB::table('empleado')
        ->join('periodo', 'periodo.fkEmpleado', 'empleado.idempleado')
        ->join('nomina', 'nomina.idnomina', 'periodo.fkNomina')
        ->join('empresa', 'nomina.fkEmpresa', 'empresa.idempresa')
        ->join('datospersonales', 'datospersonales.idDatosPersonales', 'empleado.fkDatosPersonales')        
        ->leftJoin('conceptofijo', function ($join) {
            $join->on('conceptofijo.fkEmpleado', '=', 'empleado.idempleado')
                ->on('conceptofijo.fkPeriodoActivo', '=', 'periodo.idPeriodo')
                ->whereIn('conceptofijo.fkConcepto', ["1","2","53","54"]);
        })
        ->join('cargo', 'empleado.fkCargo', "=", 'cargo.idCargo', "left")
        ->join('cargo as cargo2', 'periodo.fkCargo',"=", 'cargo2.idCargo',"left")
        ->select(
            'datospersonales.primerNombre',
            'datospersonales.segundoNombre',
            'datospersonales.primerApellido',
            'datospersonales.segundoApellido',
            'conceptofijo.valor',
            'conceptofijo.unidad',
            'cargo.nombreCargo',
            'cargo2.nombreCargo as nombreCargoPeriodo',
            'empresa.razonSocial',
            'periodo.fechaInicio as fechaIngreso',
            'datospersonales.numeroIdentificacion'
        )
        ->where('empleado.idempleado', $idEmple)
        ->where('periodo.idPeriodo',"=",session('idPeriodo'))
        ->groupBy('empresa.idempresa')
        ->get();

        //dd($infoEmpleado, session('idPeriodo'), $idEmple);



        
        $centroCosto = DB::table("centrocosto")
        ->join("empleado_centrocosto", "empleado_centrocosto.fkCentroCosto","=","centrocosto.idcentroCosto")
        ->where("empleado_centrocosto.fkEmpleado","=",$idEmple)
        ->where("empleado_centrocosto.fkPeriodoActivo","=",session('idPeriodo'))
        ->first();
            
        $infoEmpleado[0]->nombre = $centroCosto->nombre;
        $infoEmpleado[0]->nombreCargo = ($infoEmpleado[0]->nombreCargoPeriodo ?? $infoEmpleado[0]->nombreCargo);
        
        $otrosIngresos = DB::table('conceptofijo')
        ->join('concepto', 'conceptofijo.fkConcepto', 'concepto.idconcepto')
        ->select(
            'conceptofijo.valor',
            'concepto.nombre'
        )
        ->where('conceptofijo.fkEmpleado', $idEmple)
        ->where("conceptofijo.fkPeriodoActivo","=",session('idPeriodo'))
        ->whereNotIn('conceptofijo.fkConcepto', ["1","2","53","54"])
        ->get();

        $afiliaciones = DB::table('afiliacion')
        ->join('tercero', 'afiliacion.fkTercero', 'tercero.idTercero')
        ->join('tipoafilicacion', 'afiliacion.fkTipoAfilicacion', 'tipoafilicacion.idTipoAfiliacion')
        ->join('empleado', 'empleado.idempleado', 'afiliacion.fkEmpleado')
        ->select(
            'tercero.razonSocial',
            'afiliacion.fechaAfiliacion',
            'tipoafilicacion.nombre'
        )
        ->where('empleado.idempleado', $idEmple)
        ->where("afiliacion.fkPeriodoActivo","=",session('idPeriodo'))
        ->get();
    
        return view('portalEmpleado.infoLaboral', [
            'dataEmple' => $infoEmpleado,
            'otrosIngresos' => $otrosIngresos,
            'afiliaciones' => $afiliaciones
        ]);
    }

    public function diasVacacionesDisponibles($idEmpleado){       
        
        
        $empleado = DB::table("empleado","e")
        ->where("e.idempleado","=",$idEmpleado
        )->first();
        $fechaFin = date("Y-m-d");

        $periodoActivoReintegro = DB::table("periodo")
        ->where("idPeriodo", "=", session('idPeriodo'))
        ->first();
        $empleado->fechaIngreso = $periodoActivoReintegro->fechaInicio;
        //$empleado->fechaIngreso = $periodoActivoReintegro->fechaInicio;

        $novedadesRetiro = DB::table("novedad","n")
        ->select("r.fecha")
        ->join("retiro AS r", "r.idRetiro","=","n.fkRetiro")
        ->where("n.fkPeriodoActivo", "=", $periodoActivoReintegro->idPeriodo)
        ->whereIn("n.fkEstado",["7","8"])
        ->whereNotNull("n.fkRetiro")
        ->orderBy("n.idNovedad", "desc")
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
        ->where("n.fkPeriodoActivo", "=", $periodoActivoReintegro->idPeriodo)
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

        $fechaInicioVacas = new DateTime($fechaFin);
        $stringResta = (string) 'P'.round($diasVac).'D';
        $fechaInicioVacas->sub(new \DateInterval($stringResta));

        return view ('portalEmpleado.vacacionesEmple', [
            "diasVac" => floatval(round(number_format($diasVac), 2)),
            "fechaIngreso" => $empleado->fechaIngreso,
            "fechaInicioVacas" => $fechaInicioVacas->format("Y-m-d"),
            "fechaCorteCalculo" => $fechaFin
        ]);
    }

    public function datosEmpleadoPerfil($idEmpleado) {
        $datosEmple = DB::table('datospersonales')
        ->select(
            'datospersonales.correo',
            'datospersonales.correo2',
            'datospersonales.telefonoFijo',
            'datospersonales.celular',
            'datospersonales.direccion',
            'datospersonales.barrio',
            'datospersonales.fkUbicacionResidencia as ubi',
            DB::raw('(select fkUbicacion from ubicacion where idubicacion = ubi) as ubi_dos'),
            DB::raw('(select fkUbicacion from ubicacion where idubicacion = ubi_dos) as ubi_tres')
        )
        ->join('empleado', 'datospersonales.idDatosPersonales', 'empleado.fkDatosPersonales')
        ->where('empleado.idempleado', $idEmpleado)
        ->first();

        $paises = Ubicacion::where('fkTipoUbicacion', '=', 1)->get();
        $deptos = Ubicacion::where('fkTipoUbicacion', '=', 2)->get();
        $ciudades = Ubicacion::where('fkTipoUbicacion', '=', 3)->get();

        $ubicaciones = Ubicacion::all();

        return view("portalEmpleado.datosEmple", [
            "idEmpleado" => $idEmpleado,
            "datosEmple" => $datosEmple,
            'ubicaciones' => $ubicaciones,
            'paises' => $paises,
            'deptos' => $deptos,
            'ciudades' => $ciudades
        ]);
    }

    public function editarDataEmple(Request $request, $idEmpleado) {
        $emple = DB::table('empleado')->select(
            'fkDatosPersonales'
        )->where('idempleado', $idEmpleado)->first();
        if ($emple) {
            $actDatosEmple = DB::table('datospersonales')
            ->where('idDatosPersonales', $emple->fkDatosPersonales)
            ->update([
                'correo' => $request->correo,
                'correo2' => $request->correo2,
                'telefonoFijo' => $request->telefonoFijo,
                'celular' => $request->celular,
                'direccion' => $request->direccion,
                'barrio' => $request->barrio,
                'fkUbicacionResidencia' => $request->fkUbicacion
            ]);
            if ($actDatosEmple) {
                return response()->json(['success' => true, 'mensaje' => 'Datos actualizados exitosamente']);
            } else {
                return response()->json(['success' => false, 'mensaje' => 'Error al actualizar datos de empleado']);
            }
        } else {
            return response()->json(['success' => false, 'mensaje' => 'Error, empleado con este ID no existe']);
        }
    }

    public function getVistaBoucherPago($id) {
        return view('/portalEmpleado.comprobantesPago', [
            'idEmple' => $id
        ]);
    }

    public function getBouchersPagoEmpleado($idEmple) {
        $bouchersPago = DB::table('liquidacionnomina')
            ->join('boucherpago', 'boucherpago.fkLiquidacion', 'liquidacionnomina.idLiquidacionNomina')
            ->select([
                'boucherpago.idBoucherPago',
                'liquidacionnomina.fechaInicio',
                'liquidacionnomina.fechaFin',
            ])
            ->where('boucherpago.fkEmpleado', '=', $idEmple)
            ->where('liquidacionnomina.fkEstado', '=', '5')
            ->where('boucherpago.fkPeriodoActivo', '=', session("idPeriodo"))
            ->orderBy("boucherpago.idBoucherPago","desc")
            ->get();
        return $bouchersPago;
    }

    public function buscarBoucherPorFecha(Request $request, $idEmple) {
        $bouchersPago = DB::table('liquidacionnomina')
            ->join('boucherpago', 'boucherpago.fkLiquidacion', 'liquidacionnomina.idLiquidacionNomina')
            ->select([
                'boucherpago.idBoucherPago',
                'liquidacionnomina.fechaInicio',
                'liquidacionnomina.fechaFin',
            ])
            ->where('boucherpago.fkEmpleado', '=', $idEmple)
            ->where('liquidacionnomina.fkEstado', '=', '5')
            ->where('boucherpago.fkPeriodoActivo', '=', session("idPeriodo"))
            ->whereBetween('liquidacionnomina.fechaInicio', [$request->fechaInicio, $request->fechaFin])
            ->get();
        return $bouchersPago;
    }

    public function generarCertificadoLaboral($idEmple) {
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
        ->where("e.idempleado","=",$idEmple)
        ->where("p.idPeriodo","=",session('idPeriodo'))
        ->first();
       //dd($empleado);
        
       //dd($empleado);
        $empleado->nombreCargo = ($empleado->nombreCargoPeriodo 
        ?? ($empleado->nombreCargo ?? ""));
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


        $contratoGen = DB::table("contrato","con")
        ->select("tc.nombre as tipoContrato")        
        ->join("tipocontrato as tc","tc.idtipoContrato","=","con.fkTipoContrato")
        ->where("con.fkEmpleado","=",$idEmple)     
        ->where("con.fkPeriodoActivo","=",session('idPeriodo')) 
        ->orderBy("idcontrato","desc")
        ->first();



        $arrDatos =  (array) $empleado;
        
        $periodos = DB::table("periodo")
        ->select(
               "periodo.*",
                "cargo.nombreCargo","tipocontrato.nombre as nombreTipoContrato",
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
        ->where("periodo.fkEmpleado","=",$idEmple)
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
                $arrDatos["tipoContrato"] = $contratoGen->tipoContrato;
            }

            $arrDatos["fechaIngreso"] = $periodo->fechaInicio;

            if(isset($periodo->fechaFin)){
                $arrDatos["fechaRetiro"] = $periodo->fechaFin;
            }
            else{
                $arrDatos["fechaRetiro"] = "Actual";
            }
            if(isset($periodo->salario)){
                $arrDatos["salario"] = "$".number_format($periodo->salario, 2, ",", ".");
                $arrDatos["salarioLetras"] = $this->convertir($periodo->salario);
            }
            else{
                $conceptoSalario = DB::table("conceptofijo")
                ->where("fkEmpleado","=",$empleado->idempleado)
                ->where("fkPeriodoActivo","=",$periodo->idPeriodo)
                ->whereIn("fkConcepto",[1,2,53,54])
                ->first();
                $arrDatos["salario"] = "$".number_format($conceptoSalario->valor, 2, ",", ".");
                $arrDatos["salarioLetras"] = $this->convertir(intval($conceptoSalario->valor));
                //dd(intval($conceptoSalario->valor));
            }
            $arrDatos["fechaActual"] = $fechaCarta;


            $conceptosFijos = DB::table("conceptofijo","cf")
            ->select("cf.*","c.nombre as nombreConcepto")
            ->join("concepto as c", "c.idconcepto","=","cf.fkConcepto")
            ->where("cf.fkEmpleado","=",$empleado->idempleado)
            ->where("cf.fkPeriodoActivo","=",$periodo->idPeriodo)
            ->whereNotIn("cf.fkConcepto",[1,2,53,54])
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
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);

        //$dompdf = new Dompdf();
        

        $dompdf->loadHtml($html ,'UTF-8');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        $dompdf->get_canvas()->get_cpdf()->setEncryption($empleado->numeroIdentificacion, $empleado->numeroIdentificacion);
        $dompdf->stream("Certificación Laboral", array('compress' => 1, 'Attachment' => 0));
    }

    
    public function vistaActPass($id) {
        try {
            $usuario = DB::table('users')->where('fkEmpleado', $id)->first();            
            return view('/usuarios/cambiarPass', [
                'usuario' => $usuario
            ]);
		}
		catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe una usuario con este ID"]);
		}
    }

    public function actPass(Request $request, $id) {
        try {
            $usuario = User::findOrFail($id);
            $usuario->password = $request->password;
            $save = $usuario->save();
            if ($save) {
                $success = true;
                $mensaje = "Contraseña modificada correctamente";
            } else {
                $success = false;
                $mensaje = "Error al modificar contraseña";
            }
            return response()->json(["success" => $success, "mensaje" => $mensaje]);
            }
		catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe una usuario con este ID"]);
		}
    }

    public function traerFormularios220() {
        $formularios = DB::table('formulario220')->get();
        return view('portalEmpleado.selectFormulario2020', [
            'formularios' => $formularios
        ]);
    }

    public function days_360($fecha1,$fecha2,$europeo=true) {
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
    
    public function actOnlyPass(SoloPassRequest $request, $id) {
        try {
            $usuario = User::findOrFail($id);
            $usuario->password = $request->password;
            $save = $usuario->save();
            if ($save) {
                $success = true;
                $mensaje = "Contraseña modificada correctamente";
            } else {
                $success = false;
                $mensaje = "Error al modificar contraseña";
            }
            return response()->json(["success" => $success, "mensaje" => $mensaje]);
            }
		catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe una usuario con este ID"]);
		}
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

    public function cambiarPeriodoActivo($idPeriodoNuevo){
        session(['idPeriodo' => $idPeriodoNuevo]);
        return redirect("/portal");
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
}