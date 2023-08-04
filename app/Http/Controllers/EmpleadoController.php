<?php

namespace App\Http\Controllers;

use App\Ubicacion;
use App\User;
use DateInterval;
use DateTime;
use ZipArchive;
use ErrorException;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use PhpParser\Node\Expr\Cast\Bool_;
use League\Csv\Reader;
use Illuminate\Support\Facades\Response;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class EmpleadoController extends Controller
{
    public function index(Request $req){
        $usu = UsuarioController::dataAdminLogueado();
        
        $select = "";
        if(isset($usu) && $usu->fkRol == 2){
            $select = " and p2.fkNomina in(Select idNomina from nomina where fkEmpresa in (".implode(",",$usu->empresaUsuario)."))";
        }

        
        $empleados = DB::table('empleado')->select( 'empleado.idempleado',
                                                    'empleado.tEmpleado',
                                                    'est.nombre AS estado',
                                                    'est.clase AS claseEstado',
                                                    'empleado.fkEstado',
                                                    'n.nombre as nombreNomina',
                                                    'u.nombre as ciudad',
                                                    'emp.razonSocial as nombreEmpresa',
                                                    'p.idPeriodo',
                                                    'p.fkNomina as fkNominaPeriodo',
                                                    'p.fkEstado as fkEstadoPeriodo',
                                                    'empleado.fkNomina as fkNominaEmpleado',
                                                    'dp.*')
                                        ->selectRaw('(select cc2.nombre from centrocosto as cc2 where cc2.idcentroCosto 
                                                        in(Select ecc.fkCentroCosto from empleado_centrocosto as ecc where 
                                                        ecc.fkEmpleado = empleado.idempleado and
                                                        ecc.fkPeriodoActivo = p.idPeriodo
                                                        )
                                                        limit 0,1) as centroCosto
                                                        
                                                        ,
                                                        (SELECT count(idperiodo) FROM periodo p2 WHERE p2.fkEmpleado = empleado.idempleado and p2.fkEstado = 2 '.$select.')
                                                        as reintegros
                                                        ')
                                        ->join('datospersonales AS dp','empleado.fkDatosPersonales', '=', 'dp.idDatosPersonales')
                                        ->join('periodo AS p','empleado.idempleado', '=', 'p.fkEmpleado')
                                        ->join('nomina AS n','p.fkNomina', '=', 'n.idNomina',"left")
                                        ->join('empresa AS emp','n.fkEmpresa', '=', 'emp.idEmpresa',"left")
                                        ->join('centrocosto AS cc','cc.fkEmpresa', '=', 'n.fkEmpresa',"left")
                                        ->join('ubicacion AS u','u.idubicacion', '=', 'empleado.fkUbicacionLabora',"left")
                                        ->join('estado AS est','p.fkEstado', '=', 'est.idestado');
        $arrConsulta = array();
        $apFiltro = false;
        if(isset($req->numDocNombre)){
            $empleados->where(function($query) use($req){
                $query->where("dp.primerNombre","LIKE","%".$req->numDocNombre."%")
                ->orWhere("dp.segundoNombre","LIKE","%".$req->numDocNombre."%")
                ->orWhere("dp.primerApellido","LIKE","%".$req->numDocNombre."%")
                ->orWhere("dp.segundoApellido","LIKE","%".$req->numDocNombre."%")
                ->orWhere("dp.numeroIdentificacion", "LIKE", $req->numDocNombre."%")
                ->orWhereRaw("CONCAT(dp.primerApellido,' ',dp.segundoApellido,' ',dp.primerNombre,' ',dp.segundoNombre) LIKE '%".$req->numDocNombre."%'");
            });
            $arrConsulta["numDocNombre"] = $req->numDocNombre;
            $apFiltro = true;
        }
        
        if(isset($req->nomina)){
            $empleados->where("n.idNomina", "=", $req->nomina);
            $arrConsulta["nomina"] = $req->nomina;
            $apFiltro = true;
        }
        
        if(isset($req->empresa)){
            $empleados->where("n.fkEmpresa", "=", $req->empresa);
            $arrConsulta["empresa"] = $req->empresa;
            $apFiltro = true;
        }
        else{
            $req->centroCosto = NULL;
        }

        if(isset($usu) && $usu->fkRol == 2){
            $empleados->whereIn("n.fkEmpresa", $usu->empresaUsuario);
        }

        if(isset($req->centroCosto)){
            $empleados->where("cc.idcentroCosto", "=", $req->centroCosto);
            $arrConsulta["centroCosto"] = $req->centroCosto;
            $apFiltro = true;
        }


        if(isset($req->tipoPersona)){
            $empleados->where("empleado.tipoPersona", "=", $req->tipoPersona);
            $arrConsulta["tipoPersona"] = $req->tipoPersona;
            $apFiltro = true;
        }
        if(isset($req->ciudad)){
            $empleados->where("empleado.fkUbicacionLabora", "=", $req->ciudad);
            $arrConsulta["ciudad"] = $req->ciudad;
            $apFiltro = true;
        }
        
        
        if(isset($req->centroCosto)){
            $empleados->join("empleado_centrocosto AS ec", "ec.fkEmpleado","=","empleado.idempleado");
            $empleados->where("ec.fkCentroCosto","=",$req->centroCosto);
            $arrConsulta["centroCosto"] = $req->centroCosto;
            $apFiltro = true;
        }

        if(isset($req->estado)){
            $empleados->where("empleado.fkEstado", "=", $req->estado);
            $arrConsulta["estado"] = $req->estado;
            $apFiltro = true;
            
        }
        
        if(!$apFiltro){
            $empleados->whereIn("empleado.fkEstado", ["1","3"]);
        }
        
        $empleados = $empleados->distinct()
        //->orderBy("empleado.fkEmpresa")
        //->orderBy("empleado.fkNomina")
        ->orderBy("dp.primerApellido")
        ->orderBy("dp.segundoApellido")
        ->orderBy("dp.primerNombre")
        ->orderBy("dp.segundoNombre")
        ->get();

        $numResultados = sizeof($empleados);

        $empleados = $this->paginate($empleados, 15);
        $empleados->withPath("");

        
        
        $empresas = DB::table("empresa","e")->orderBy("razonSocial")->get();
        $nominas = array();        
        $centrosDeCosto = array();
        
        if(isset($req->empresa)){
            $centrosDeCosto = DB::table("centrocosto")->where("fkEmpresa","=",$req->empresa)->orderBy("nombre")->get();
            $nominas = DB::table("nomina")->where("fkEmpresa","=",$req->empresa)->orderBy("nombre")->get();
        }
        
        $ciudades = DB::table("ubicacion")->where("fkTipoUbicacion","=","3")->orderBy("nombre")->get();
        $estados = DB::table("estado","e")->whereIn('e.idestado',[1,2,3])->get();
        
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu de empleados");

        return view('/empleado.verEmpleados',['empleados'=> $empleados, 
        'ciudades' => $ciudades,
         "req" => $req, 
         "arrConsulta" => $arrConsulta,
         "estados" => $estados,
         "numResultados" => $numResultados,
         "empresas" => $empresas,
         "centrosDeCosto" => $centrosDeCosto,
         'dataUsu' => $usu,
         "nominas" => $nominas
        ]);
    }


   

    public function formCrear($tipoEmpleado){       

        $paises = Ubicacion::where("fkTipoUbicacion", "=" ,'1')->get();
        $generos = DB::table("genero")->get();
        $estadosCivil = DB::table("estadocivil")->get();
        $tipo_vivienda = DB::table("tipo_vivienda")->get();
        $grupoSanguineo = DB::table("gruposanguineo")->get();
        $rhs = DB::table("rh")->get();
        $tipoidentificacion = DB::table("tipoidentificacion")->where("tipo", "=", "0")->get();
       
        $tipoEmpleadoEnv="empleado";
        
        if($tipoEmpleado == "2"){
            $tipoEmpleadoEnv = "contratista";
        }
        else if($tipoEmpleado == "3"){
            $tipoEmpleadoEnv = "aspirante";
        }

        $nivelesEstudios = DB::table("nivel_estudio")->get();
        $etnias = DB::table("etnia")->get();

        $usu = UsuarioController::dataAdminLogueado();
        
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu de crear empleado");

        return view('/empleado.addEmpleado', ['paises'=>$paises, 
            'generos' => $generos, 
            'estadosCivil' => $estadosCivil, 
            'tipo_vivienda' => $tipo_vivienda,
            'grupoSanguineo' => $grupoSanguineo,
            'rhs' => $rhs,
            'tipoidentificacion' => $tipoidentificacion,   
            'tipoEmpleado' => $tipoEmpleadoEnv,
            'nivelesEstudios' => $nivelesEstudios,
            'etnias' => $etnias,
            'dataUsu' => $usu
        ]);
    }
    public function cargarPersonasVive($num){
        $parentescos = DB::table("parentesco")->get();
        $escolaridades = DB::table("escolaridad")->get();
        

        return view('/empleado.ajax.personasVive', [
                    'parentescos'=>$parentescos, 
                    'escolaridades' => $escolaridades, 
                    'num' => $num    
        ]);
    }

    public function cargarUpcAdicional($num, $idEmpleado){
        

        $periocidad = array();
        $periodo = NULL;
        $nomina = DB::table("nomina","n")
        ->join("empleado as e","e.fkNomina", "=","n.idNomina")
        ->where("e.idempleado","=",$idEmpleado)->first();
        if(isset($nomina)){
            $periocidad = DB::table("periocidad")->where("per_periodo", "=",$nomina->periodo)->get();
            $periodo = $nomina->periodo;
        }
        

        $paises = Ubicacion::where("fkTipoUbicacion", "=" ,'1')->get();
        $generosBen = DB::table("genero")->whereIn("idGenero",["1","2"])->get();
        $tipoidentificacion = DB::table("tipoidentificacion")->where("tipo", "=", "0")->get();


        return view('/empleado.ajax.upcAdicional', [
            'generosBen' => $generosBen, 
            'tipoidentificacion' => $tipoidentificacion,
            'paises'=>$paises,  
            "periocidad" => $periocidad,
            "periodo" => $periodo,
            'idRow' => $num    
        ]);
    }

    
    public function cargarContactoEmergencia($num){
        $paises = Ubicacion::where("fkTipoUbicacion", "=" ,'1')->get();
       

        return view('/empleado.ajax.contactoEmergencia', [
                'paises'=>$paises,
                'num' => $num    
        ]);
    }
    
    
    public function insert(Request $req){

        if($req->numIdentificacion=="" || $req->tIdentificacion=="" ){
            return response()->json([
                "success" => false,
                "respuesta" => "Se requiere como minimo la identificación de la persona"
            ]);
        }

        if(isset($req->fechaNacimiento) && strtotime($req->fechaNacimiento) >= strtotime(date("Y-m-d"))){
            return response()->json([
                "success" => false,
                "respuesta" => "Error fecha de nacimiento incorrecta"
            ]);
        }
                
        $messages = [
            'email' => 'El campo :attribute no es un email valido.'
        ];
        $validator = Validator::make($req->all(), [
            'correo1' => 'nullable|email:rfc,dns',
            'correo2' => 'nullable|email:rfc,dns'
        ],$messages);
        
        if($validator->fails()) {
            
            $msjEnvio = "";
            foreach($validator->errors()->all() as $errores){
                $msjEnvio .= $errores." ,";
            }
            $msjEnvio = substr($msjEnvio, 0, strlen($msjEnvio) - 1);

            return response()->json([
                "success" => false,
                "respuesta" => $msjEnvio
            ]);
        }


        
        $empleado = DB::table('datospersonales')
            ->where("numeroIdentificacion", "=", $req->numIdentificacion)
            ->where("fkTipoIdentificacion", "=" , $req->tIdentificacion)
            ->select("e.idempleado")
            ->join("empleado AS e", "e.fkDatosPersonales", "=", "datospersonales.idDatosPersonales")->first();
        if(isset($empleado->idempleado)){
            return response()->json([
                "success" => false,
                "respuesta" => "Este documento ya se encuentra en base de datos, debé cambiar el numero de documento"
            ]);
        }








        $foto = "";
        if ($req->hasFile('foto')) {
            $foto = $req->file("foto")->store("public/imgEmpleados");
        }
        $insertDatosEmpleado = array("foto" => $foto,
                                     "numeroIdentificacion" => $req->numIdentificacion,
                                     "fkTipoIdentificacion" => $req->tIdentificacion,
                                     "primerNombre" => $req->pNombre,
                                     "segundoNombre" => $req->sNombre,
                                     "primerApellido" => $req->pApellido,
                                     "segundoApellido" => $req->sApellido,
                                     "fkUbicacionExpedicion" => $req->lugarExpedicion,
                                     "fechaExpedicion" => $req->fechaExpedicion,
                                     "fkGenero" => $req->genero,
                                     "fkEstadoCivil" => $req->estadoCivil,
                                     "libretaMilitar" => $req->libretaMilitar,
                                     "distritoMilitar" => $req->distritoMilitar,
                                     "fkUbicacionNacimiento" => $req->lugarNacimiento,
                                     "fechaNacimiento" => $req->fechaNacimiento,
                                     "fkUbicacionResidencia" => $req->lugarResidencia,
                                     "direccion" => $req->direccion,
                                     "barrio" => $req->barrio,
                                     "estrato" => $req->estrato,
                                     "fkTipoVivienda" => $req->tipoVivienda,
                                     "telefonoFijo" => $req->telFijo,
                                     "celular" => $req->celular,
                                     "correo" => $req->correo1,
                                     "correo2" => $req->correo2,
                                     "fkGrupoSanguineo" => $req->grupoSanguineo,
                                     "fkRh" => $req->rh,
                                     "tallaCamisa" => $req->tallaCamisa,
                                     "tallaPantalon" => $req->tallaPantalon,
                                     "tallaZapatos" => $req->tallaZapatos,
                                     "otros" => $req->otros,
                                     "tallaOtros" => $req->tallaOtros,
                                     "fkNivelEstudio" => $req->nivelEstudio,
                                     "fkEtnia" => $req->etnia
        );
        $idDatosPersonales = DB::table('datospersonales')->insertGetId($insertDatosEmpleado, "idDatosPersonales");


        foreach ($req->nombreEmergencia as $key => $nombEmer) {
            $insertContactoEmergencia = array(  "nombre" => $nombEmer, 
                                                "telefono" => $req->telefonoEmergencia[$key],
                                                "direccion" => $req->direccionEmergencia[$key],
                                                "fkUbicacion" => $req->lugarEmergencia[$key],
                                                "fkDatosEmpleado" => $idDatosPersonales,
            );
            DB::table('contactoemergencia')->insert($insertContactoEmergencia);
        }
        if(isset($req->nombrePersonaV)){
            foreach ($req->nombrePersonaV as $key => $nombNucleoFam) {
                $insertNucleoFam = array(   "nombre" => $nombNucleoFam, 
                                            "fechaNacimiento" => $req->fechaNacimientoPersonaV[$key],
                                            "fkEscolaridad" => $req->escolaridadPersonaV[$key],
                                            "fkParentesco" => $req->parentescoPersonaV[$key],
                                            "fkDatosEmpleado" => $idDatosPersonales,
                );
                DB::table('nucleofamiliar')->insert($insertNucleoFam);
            }
        }
        
        $estadoEnCreacion = 3;
        $insertEmpleado = array("tEmpleado" => $req->tEmpleado, "fkDatosPersonales" => $idDatosPersonales, "fkEstado" => $estadoEnCreacion);
        $idempleado = DB::table('empleado')->insertGetId($insertEmpleado, "idempleado");
        $idPeriodo = DB::table("periodo")->insertGetId([
            "fkEmpleado" => $idempleado,
            "fkEstado" => "3"
        ], "idPeriodo");

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", creó un empleado");

        return response()->json([
            "success" => true,
            "idempleado" => $idempleado,
            "idPeriodo" => $idPeriodo
        ]);

    }
    public function formModificar($idEmpleado, $idPeriodo = null ,Request $req){    
        
        $periodoActivo = DB::table("periodo", "p")
        ->select('ubi_dep_tra.idubicacion AS ubi_depto_tra', 'ubi_pa_tra.idubicacion AS ubi_pais_tra',
         "p.*", "n.fkEmpresa", "e.razonSocial")
        ->join("ubicacion AS ubi_ciud_tra", 'p.fkUbicacionLabora', '=', 'ubi_ciud_tra.idubicacion',"left")
        ->join("ubicacion AS ubi_dep_tra", 'ubi_ciud_tra.fkUbicacion', '=', 'ubi_dep_tra.idubicacion',"left")
        ->join("ubicacion AS ubi_pa_tra", 'ubi_dep_tra.fkUbicacion', '=', 'ubi_pa_tra.idubicacion',"left")
        ->join("nomina AS n","n.idNomina","=","p.fkNomina","left")
        ->join("empresa AS e","e.idempresa","=","n.fkEmpresa","left")
        ->where("p.fkEmpleado","=",$idEmpleado);
        if(isset($idPeriodo)){
            $periodoActivo = $periodoActivo->where("p.idPeriodo","=",$idPeriodo);
        }
        $periodoActivo = $periodoActivo->orderBy("p.fkEstado","asc")
        ->orderBy("p.idPeriodo","desc")
        ->first();
       

        $empleado = DB::table("empleado")->select( 
            'empleado.*', 'dp.*', 'u.username as usuarioTxt','ti.nombre as nombreTipoDoc',
            'ubi_dep_exp.idubicacion AS ubi_depto_exp', 'ubi_pa_exp.idubicacion AS ubi_pais_exp',
            'ubi_dep_nac.idubicacion AS ubi_depto_nac', 'ubi_pa_nac.idubicacion AS ubi_pais_nac',
            'ubi_dep_res.idubicacion AS ubi_depto_res', 'ubi_pa_res.idubicacion AS ubi_pais_res',
            'ubi_dep_tra.idubicacion AS ubi_depto_tra', 'ubi_pa_tra.idubicacion AS ubi_pais_tra')
        ->join('datospersonales AS dp','empleado.fkDatosPersonales', '=', 'dp.idDatosPersonales',"left")                                        
        ->join("ubicacion AS ubi_ciud_exp", 'dp.fkUbicacionExpedicion', '=', 'ubi_ciud_exp.idubicacion',"left")
        ->join("ubicacion AS ubi_dep_exp", 'ubi_ciud_exp.fkUbicacion', '=', 'ubi_dep_exp.idubicacion',"left")
        ->join("ubicacion AS ubi_pa_exp", 'ubi_dep_exp.fkUbicacion', '=', 'ubi_pa_exp.idubicacion',"left")

        ->join("ubicacion AS ubi_ciud_nac", 'dp.fkUbicacionNacimiento', '=', 'ubi_ciud_nac.idubicacion',"left")
        ->join("ubicacion AS ubi_dep_nac", 'ubi_ciud_nac.fkUbicacion', '=', 'ubi_dep_nac.idubicacion',"left")
        ->join("ubicacion AS ubi_pa_nac", 'ubi_dep_nac.fkUbicacion', '=', 'ubi_pa_nac.idubicacion',"left")

        ->join("ubicacion AS ubi_ciud_res", 'dp.fkUbicacionResidencia', '=', 'ubi_ciud_res.idubicacion',"left")
        ->join("ubicacion AS ubi_dep_res", 'ubi_ciud_res.fkUbicacion', '=', 'ubi_dep_res.idubicacion',"left")
        ->join("ubicacion AS ubi_pa_res", 'ubi_dep_res.fkUbicacion', '=', 'ubi_pa_res.idubicacion',"left")
        
        ->join("ubicacion AS ubi_ciud_tra", 'empleado.fkUbicacionLabora', '=', 'ubi_ciud_tra.idubicacion',"left")
        ->join("ubicacion AS ubi_dep_tra", 'ubi_ciud_tra.fkUbicacion', '=', 'ubi_dep_tra.idubicacion',"left")
        ->join("ubicacion AS ubi_pa_tra", 'ubi_dep_tra.fkUbicacion', '=', 'ubi_pa_tra.idubicacion',"left")

        ->join("users AS u", 'u.fkEmpleado','=','empleado.idempleado',"left")
        ->join("tipoidentificacion AS ti", 'ti.idtipoIdentificacion','=','dp.fkTipoIdentificacion',"left")
        ->where('idempleado', $idEmpleado)
        ->first();
        
        $empleado->fkEmpresa = ($periodoActivo->fkEmpresa ?? $empleado->fkEmpresa);
        $empleado->fkNomina =($periodoActivo->fkNomina ?? $empleado->fkNomina);
        $empleado->fechaIngreso =($periodoActivo->fechaInicio ?? $empleado->fechaIngreso);        
        $empleado->fkCargo =($periodoActivo->fkCargo ?? $empleado->fkCargo);
        $empleado->fkTipoCotizante =($periodoActivo->fkTipoCotizante ?? $empleado->fkTipoCotizante);
        $empleado->esPensionado =($periodoActivo->esPensionado ?? $empleado->esPensionado);
        $empleado->tipoRegimen =($periodoActivo->tipoRegimen ?? $empleado->tipoRegimen);
        $empleado->tipoRegimenPensional =($periodoActivo->tipoRegimenPensional ?? $empleado->tipoRegimenPensional);
        $empleado->fkUbicacionLabora =($periodoActivo->fkUbicacionLabora ?? $empleado->fkUbicacionLabora);
        $empleado->fkLocalidad =($periodoActivo->fkLocalidad ?? $empleado->fkLocalidad);
        $empleado->sabadoLaborable =($periodoActivo->sabadoLaborable ?? $empleado->sabadoLaborable);
        $empleado->formaPago =($periodoActivo->formaPago ?? $empleado->formaPago);
        $empleado->fkEntidad =($periodoActivo->fkEntidad ?? $empleado->fkEntidad);
        $empleado->numeroCuenta =($periodoActivo->numeroCuenta ?? $empleado->numeroCuenta);
        $empleado->tipoCuenta =($periodoActivo->tipoCuenta ?? $empleado->tipoCuenta);
        $empleado->otraFormaPago =($periodoActivo->otraFormaPago ?? $empleado->otraFormaPago);
        $empleado->fkTipoOtroDocumento =($periodoActivo->fkTipoOtroDocumento ?? $empleado->fkTipoOtroDocumento);
        $empleado->otroDocumento =($periodoActivo->otroDocumento ?? $empleado->otroDocumento);
        $empleado->procedimientoRetencion =($periodoActivo->procedimientoRetencion ?? $empleado->procedimientoRetencion);
        $empleado->porcentajeRetencion =($periodoActivo->porcentajeRetencion ?? $empleado->porcentajeRetencion);
        $empleado->fkNivelArl =($periodoActivo->fkNivelArl ?? $empleado->fkNivelArl);
        $empleado->fkCentroTrabajo =($periodoActivo->fkCentroTrabajo ?? $empleado->fkCentroTrabajo);
        $empleado->aplicaSubsidio =($periodoActivo->aplicaSubsidio ?? $empleado->aplicaSubsidio);

        if(isset($periodoActivo->fkEmpresa)){
            $empleado->fkEmpresa = $periodoActivo->fkEmpresa;
            $empleado->fkNomina = $periodoActivo->fkNomina;
        }

        if(isset($periodoActivo->fkUbicacionLabora)){
            $empleado->ubi_pais_tra = $periodoActivo->ubi_pais_tra;
            $empleado->ubi_depto_tra = $periodoActivo->ubi_depto_tra;
            $empleado->fkUbicacionLabora = $periodoActivo->fkUbicacionLabora;
        }

        $paises = Ubicacion::where("fkTipoUbicacion", "=" ,'1')->get();
        $deptosExp = array();
        $ciudadesExp= array();

        if(isset($empleado->ubi_pais_exp)){
            $deptosExp = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_pais_exp)->get();
            $ciudadesExp = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_depto_exp)->get();    
        }
        
        $deptosNac = array();
        $ciudadesNac= array();
        if(isset($empleado->ubi_pais_nac)){
            $deptosNac = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_pais_nac)->get();
            $ciudadesNac = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_depto_nac)->get();
        }

        $deptosRes = array();
        $ciudadesRes= array();
        if(isset($empleado->ubi_pais_res)){
            $deptosRes = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_pais_res)->get();
            $ciudadesRes = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_depto_res)->get();
        }

        $deptosTra = array();
        $ciudadesTra= array();
        $localidadesTra = array();
        if(isset($empleado->ubi_pais_tra)){
            $deptosTra = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_pais_tra)->get();
            $ciudadesTra = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_depto_tra)->get();    
            $localidadesTra = Ubicacion::where("fkUbicacion", "=", $empleado->fkUbicacionLabora)->get();    
        }




        $generos = DB::table("genero")->get();
        $estadosCivil = DB::table("estadocivil")->get();
        $tipo_vivienda = DB::table("tipo_vivienda")->get();
        $grupoSanguineo = DB::table("gruposanguineo")->get();
        $rhs = DB::table("rh")->get();
        $tipoidentificacion = DB::table("tipoidentificacion")->where("tipo", "=", "0")->get();

        $contactosEmergencia = DB::table("contactoemergencia")->select(
            "contactoemergencia.*",
            'ubi_dep_emer.idubicacion AS ubi_depto_emer', 
            'ubi_pa_emer.idubicacion AS ubi_pais_emer'
        )
        ->join("ubicacion AS ubi_ciud_emer", 'contactoemergencia.fkUbicacion', '=', 'ubi_ciud_emer.idubicacion')
        ->join("ubicacion AS ubi_dep_emer", 'ubi_ciud_emer.fkUbicacion', '=', 'ubi_dep_emer.idubicacion')
        ->join("ubicacion AS ubi_pa_emer", 'ubi_dep_emer.fkUbicacion', '=', 'ubi_pa_emer.idubicacion')
        ->where("fkDatosEmpleado", "=", $empleado->idDatosPersonales)->get();
        $deptosContactosEmergencia = array();
        $ciudadesContactosEmergencia = array();
        foreach($contactosEmergencia as $contactoEmergencia){
            
            $deptosEmer = Ubicacion::where("fkUbicacion", "=", $contactoEmergencia->ubi_pais_emer)->get();
            $ciudadesEmer = Ubicacion::where("fkUbicacion", "=", $contactoEmergencia->ubi_depto_emer)->get();

            array_push($deptosContactosEmergencia, $deptosEmer);
            array_push($ciudadesContactosEmergencia, $ciudadesEmer);

        }

        $nucleofamiliar = DB::table("nucleofamiliar")->where("fkDatosEmpleado", "=", $empleado->idDatosPersonales)->get();
        

        
        $parentescos = DB::table("parentesco")->get();
        $escolaridades = DB::table("escolaridad")->get();



        $empresas = DB::table("empresa")->orderBy("razonSocial")->get();

        $centrosCosto = array();
        if(isset($empleado->fkEmpresa)){
            $centrosCosto = DB::table("centrocosto")->where("fkEmpresa","=",$empleado->fkEmpresa)->get();
        }


        $tipoContratos = DB::table('tipocontrato')->get();
        $cargos = DB::table("cargo")->orderBy("nombrecargo","asc")->get();
        $entidadesFinancieras = DB::table("tercero")->where("fk_actividad_economica", "=", "4")->get();
        

        $afiliaciones = DB::table("afiliacion")
        ->where('fkEmpleado', "=", $idEmpleado)
        ->where("fkPeriodoActivo","=",$periodoActivo->idPeriodo)
        ->get();

        $tipoafilicaciones = DB::table('tipoafilicacion')->get();
        $entidadesAfiliacion = array();
        foreach($afiliaciones as $afiliacion){
            $afiliacionesEnt = DB::table("tercero")
                    ->join('tipoafilicacion AS ta','ta.fkActividadEconomica', '=', 'tercero.fk_actividad_economica')
                    ->where("ta.idTipoAfiliacion", "=", $afiliacion->fkTipoAfilicacion);
            if($afiliacion->fkTipoAfilicacion=="1"){
                $afiliacionesEnt = $afiliacionesEnt->whereNotIn("tercero.idTercero",["10","109","111","112","113"]);
            }
            if($afiliacion->fkTipoAfilicacion=="4"){
                $afiliacionesEnt = $afiliacionesEnt->whereNotIn("tercero.idTercero",["120"]);
            }
            $afiliacionesEnt = $afiliacionesEnt
            ->orderBy("razonSocial")
            ->get();

            $entidadesAfiliacion[$afiliacion->idAfiliacion] = $afiliacionesEnt;
        }


        $nivelesArl = DB::table('nivel_arl')->get();

        $afiliacionesEnt1 = DB::table("tercero")
                    ->join('tipoafilicacion AS ta','ta.fkActividadEconomica', '=', 'tercero.fk_actividad_economica')
                    ->where("ta.idTipoAfiliacion", "=", '1')
                    ->whereNotIn("tercero.idTercero",["10","109","111","112","113"])
                    ->orderBy("razonSocial")
                    ->get();

     
        $afiliacionesEnt2 = DB::table("tercero")
                    ->join('tipoafilicacion AS ta','ta.fkActividadEconomica', '=', 'tercero.fk_actividad_economica')
                    ->where("ta.idTipoAfiliacion", "=", '2')
                    ->orderBy("razonSocial")
                    ->get();
        $afiliacionesEnt3 = DB::table("tercero")
                    ->join('tipoafilicacion AS ta','ta.fkActividadEconomica', '=', 'tercero.fk_actividad_economica')
                    ->where("ta.idTipoAfiliacion", "=", '3')
                    ->orderBy("razonSocial")
                    ->get();
        $afiliacionesEnt4 = DB::table("tercero")
                    ->join('tipoafilicacion AS ta','ta.fkActividadEconomica', '=', 'tercero.fk_actividad_economica')
                    ->where("ta.idTipoAfiliacion", "=", '4')
                    ->orderBy("razonSocial")
                    ->get();
                    
        $conceptosFijos = DB::table('conceptofijo', 'cf')->select(["cf.*", "c.nombre AS nombreConcepto"])
        ->join("concepto AS c", "c.idConcepto", "=", "cf.fkConcepto")
        ->where("cf.fkEmpleado", "=", $idEmpleado)
        ->where("cf.fkPeriodoActivo", "=", $periodoActivo->idPeriodo)
        ->get();
       

        $contratoActivo = DB::table('contrato')
        ->where("fkEmpleado","=",$idEmpleado)
        ->where("fkPeriodoActivo", "=", $periodoActivo->idPeriodo)
        ->whereIn("fkEstado",array("1","4"))
        ->first();

        $conceptos = DB::table("concepto")->whereNotIn("idconcepto", [1,2])->orderBy("nombre")->get();

        $centrosCostoxEmpleado = DB::table("empleado_centrocosto")
        ->where("fkEmpleado","=", $idEmpleado)
        ->where("fkPeriodoActivo","=", $periodoActivo->idPeriodo)
        ->get();
        $centrosCostos = array();
        $nominas = array();

        if(isset($empleado->fkEmpresa)){
            $centrosCostos = DB::table("centrocosto")->where("fkEmpresa","=", $empleado->fkEmpresa)->get();
            $nominas = DB::table("nomina")->where("fkEmpresa","=", $empleado->fkEmpresa)->orderBy("nombre")->get();

        }
        $beneficiosTributarios = DB::table("beneficiotributario", "bt")
        ->select('bt.*', 'nf.*', 'ubi_dep_benef.idubicacion AS ubi_depto_benef', 'ubi_pa_benef.idubicacion AS ubi_pais_benef')
            ->join("nucleofamiliar AS nf", 'nf.idNucleoFamiliar', '=', 'bt.fkNucleoFamiliar', 'left')
            ->join("ubicacion AS ubi_ciud_benef", 'nf.fkUbicacion', '=', 'ubi_ciud_benef.idubicacion', 'left')
            ->join("ubicacion AS ubi_dep_benef", 'ubi_ciud_benef.fkUbicacion', '=', 'ubi_dep_benef.idubicacion', 'left')
            ->join("ubicacion AS ubi_pa_benef", 'ubi_dep_benef.fkUbicacion', '=', 'ubi_pa_benef.idubicacion', 'left')
        ->where("fkEmpleado", "=", $idEmpleado)
        ->where("fkPeriodoActivo","=", $periodoActivo->idPeriodo)
        ->get();
        
        
        $deptosBeneficiosTributarios = array();
        $ciudadesBeneficiosTributarios = array();
        foreach($beneficiosTributarios as $beneficioTributario){            
            $deptosBen = Ubicacion::where("fkUbicacion", "=", $beneficioTributario->ubi_pais_benef)->get();
            $ciudadesBen = Ubicacion::where("fkUbicacion", "=", $beneficioTributario->ubi_depto_benef)->get();
            array_push($deptosBeneficiosTributarios, $deptosBen);
            array_push($ciudadesBeneficiosTributarios, $ciudadesBen);
        }


        $destino = "";
        if(isset($req->destino)){
            $destino = $req->destino;
        }

        $generosBen = DB::table("genero")->whereIn("idGenero",["1","2"])->get();
        $tipobeneficio = DB::table("tipobeneficio")->orderBy("nombre")->get();

        $cambiosAfiliacion = DB::table("cambioafiliacion","ca")
        ->where("ca.fkEmpleado", "=", $empleado->idempleado)
        ->where("ca.fkPeriodoActivo","=", $periodoActivo->idPeriodo)
        ->where("ca.fkEstado", "=", "1")
        ->get();

        $afiliacionesNuevas = array();
        foreach($cambiosAfiliacion as $cambioAfiliacion){
            $afiliacionesNuevas[$cambioAfiliacion->fkAfiliacion] = $cambioAfiliacion;
        }

        $cambioSalario = DB::table("cambiosalario","cs")
        ->where('cs.fkEmpleado', "=", $empleado->idempleado)
        ->where('cs.fkPeriodoActivo', "=", $periodoActivo->idPeriodo)
        ->where('cs.fkEstado', "=", "4")
        ->orderBy("idCambioSalario","desc")->first();

        $subtiposcotizante = DB::table("subtipocotizante")->get();
        
        $upcadicional = DB::table("upcadicional", 'nf')
        ->select('nf.*', 'ubi_dep_upc.idubicacion AS ubi_depto_upc', 'ubi_pa_upc.idubicacion AS ubi_pais_upc')
        ->join("ubicacion AS ubi_ciud_upc", 'nf.fkUbicacion', '=', 'ubi_ciud_upc.idubicacion', 'left')
        ->join("ubicacion AS ubi_dep_upc", 'ubi_ciud_upc.fkUbicacion', '=', 'ubi_dep_upc.idubicacion', 'left')
        ->join("ubicacion AS ubi_pa_upc", 'ubi_dep_upc.fkUbicacion', '=', 'ubi_pa_upc.idubicacion', 'left')
        ->where("nf.fkEmpleado", "=", $empleado->idempleado)->get();
        $deptosUpc = array();
        $ciudadesUpc = array();
        foreach($upcadicional as $row => $upc){
            $deptosBen = Ubicacion::where("fkUbicacion", "=", $upc->ubi_pais_upc)->get();
            $ciudadesBen = Ubicacion::where("fkUbicacion", "=", $upc->ubi_depto_upc)->get();
            $deptosUpc[$row] = $deptosBen;
            $ciudadesUpc[$row] = $ciudadesBen;
        }
        $existe = false;
        $existeUsuario = User::where('fkEmpleado', $idEmpleado)->first();
        if ($existeUsuario) {
            $existe = true;
        }
        $nivelesEstudios = DB::table("nivel_estudio")->get();
        $etnias = DB::table("etnia")->get();
        $centrosTrabajo = array();
        if(isset($empleado->fkEmpresa)){
            $centrosTrabajo = DB::table("centrotrabajo")->where("fkEmpresa","=",$empleado->fkEmpresa)->get();
        }

        $usu = UsuarioController::dataAdminLogueado();
        $periocidad = array();
        $periodo = NULL;
        $nomina = DB::table("nomina","n")
        ->where("n.idNomina","=",$empleado->fkNomina)
        ->first();
        if(isset($nomina)){
            $periocidad = DB::table("periocidad")->where("per_periodo", "=",$nomina->periodo)->get();
            $periodo = $nomina->periodo;
        }

        
        
        $tiposcotizante = DB::table("tipo_cotizante")->get();

        $cambiosTipoCotizante = DB::table("cambiotipocotizante", "ctc")
        ->where("ctc.fkEmpleado","=",$idEmpleado)
        ->where("ctc.fkPeriodoActivo","=",$periodoActivo->idPeriodo)
        ->where("ctc.fkEstado","=","7")
        ->get();

        $empresasEnPeriodoActivas = DB::table('periodo','p')
        ->join("nomina as n", "n.idNomina","=", "p.fkNomina")
        ->join("empresa as e", "e.idempresa","=", "n.fkEmpresa")
        ->where("p.fkEmpleado","=",$idEmpleado)
        ->whereIn("p.fkEstado",[1,3]);
        if(isset($usu) && $usu->fkRol == 2){
            $empresasEnPeriodoActivas = $empresasEnPeriodoActivas->whereIn("n.fkEmpresa",$usu->empresaUsuario);
        }
        $empresasEnPeriodoActivas = $empresasEnPeriodoActivas->get();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu de modificar empleado");

        return view('/empleado.editEmpleado', [
            'empresasEnPeriodoActivas' => $empresasEnPeriodoActivas,
            'cambiosTipoCotizante' => $cambiosTipoCotizante,
            'paises'=>$paises,
            'dataUsu' => $usu,
            'usuExiste' => $existe,
            'generos' => $generos, 
            'estadosCivil' => $estadosCivil, 
            'tipo_vivienda' => $tipo_vivienda,
            'grupoSanguineo' => $grupoSanguineo,
            'rhs' => $rhs,
            'tipoidentificacion' => $tipoidentificacion,
            'empleado' => $empleado,
            'deptosExp' => $deptosExp,
            'ciudadesExp' => $ciudadesExp,
            'deptosNac' => $deptosNac,
            'ciudadesNac' => $ciudadesNac,
            'deptosRes' => $deptosRes,
            'ciudadesRes' => $ciudadesRes,
            'contactosEmergencia' => $contactosEmergencia,
            'deptosContactosEmergencia' => $deptosContactosEmergencia,
            'ciudadesContactosEmergencia' => $ciudadesContactosEmergencia,
            'nucleofamiliar' => $nucleofamiliar,
            'parentescos' => $parentescos,
            'escolaridades' => $escolaridades,
            'empresas' => $empresas,
            'centrosCosto' => $centrosCosto,
            'tipoContratos' => $tipoContratos,
            'idEmpleado' => $idEmpleado, 
            'cargos' => $cargos, 
            'entidadesFinancieras' => $entidadesFinancieras,
            'afiliaciones' => $afiliaciones,
            'tipoafilicaciones' => $tipoafilicaciones,
            'nivelesArl' => $nivelesArl,
            'afiliacionesEnt1' => $afiliacionesEnt1,
            'afiliacionesEnt2' => $afiliacionesEnt2,
            'afiliacionesEnt3' => $afiliacionesEnt3,
            'afiliacionesEnt4' => $afiliacionesEnt4,
            'entidadesAfiliacion' => $entidadesAfiliacion,
            'conceptosFijos' => $conceptosFijos,
            'contratoActivo' => $contratoActivo,
            'conceptos' => $conceptos,
            'destino' => $destino,
            'centrosCostoxEmpleado' => $centrosCostoxEmpleado,
            'centrosCostos' => $centrosCostos,
            'nominas' => $nominas,
            'deptosTra' => $deptosTra,
            'ciudadesTra' =>$ciudadesTra,
            'localidadesTra' => $localidadesTra,
            'beneficiosTributarios' => $beneficiosTributarios,
            'generosBen' => $generosBen,
            'tipobeneficio' => $tipobeneficio,
            'deptosBeneficiosTributarios' => $deptosBeneficiosTributarios,
            'ciudadesBeneficiosTributarios' => $ciudadesBeneficiosTributarios,
            'afiliacionesNuevas' => $afiliacionesNuevas,
            'cambioSalario' => $cambioSalario,
            'subtiposcotizante' => $subtiposcotizante,
            'upcAdicional' => $upcadicional,
            "periocidad" => $periocidad,
            "periodo" => $periodo,
            'deptosUpc' => $deptosUpc,
            'ciudadesUpc' => $ciudadesUpc,
            'nivelesEstudios' => $nivelesEstudios,
            'etnias' => $etnias,
            "centrosTrabajo" => $centrosTrabajo,
            'tiposcotizante' => $tiposcotizante,
            'periodoActivo' => $periodoActivo
        ]);


    }

    public function agregarNuevaEmpresa(Request $req){
        //No debe tener la misma empresa
        $empresasEnPeriodoActivas = DB::table('periodo','p')
        ->join("nomina as n", "n.idNomina","=", "p.fkNomina")
        ->where("n.fkEmpresa","=", $req->empresaNueva)
        ->where("p.fkEmpleado","=",$req->idEmpleado)
        ->where("p.fkEstado","=","1")
        ->first();
        if(isset($empresasEnPeriodoActivas)){
            //dd($empresasEnPeriodoActivas);
            return response()->json([
                "success" => false,
                "mensaje" => "El empleado ya se encuentra activo en esta empresa"
            ]);
        }


        $idPeriodoNuevo = DB::table("periodo")->insertGetId([
            "fkEmpleado" => $req->idEmpleado,
            "fkNomina" => $req->nominaNueva,
            "fkEstado" => "3"
        ], "idPeriodo");

        return response()->json([
            "success" => true,
            "idEmpleado" => $req->idEmpleado,
            "idPeriodo" => $idPeriodoNuevo
        ]);
    }

    

    public function formVer($idEmpleado, $idPeriodo, Request $req){
        
        $periodoActivo = DB::table("periodo", "p")
        ->select('ubi_dep_tra.idubicacion AS ubi_depto_tra', 'ubi_pa_tra.idubicacion AS ubi_pais_tra',
         "p.*", "n.fkEmpresa", "e.razonSocial")
        ->join("ubicacion AS ubi_ciud_tra", 'p.fkUbicacionLabora', '=', 'ubi_ciud_tra.idubicacion',"left")
        ->join("ubicacion AS ubi_dep_tra", 'ubi_ciud_tra.fkUbicacion', '=', 'ubi_dep_tra.idubicacion',"left")
        ->join("ubicacion AS ubi_pa_tra", 'ubi_dep_tra.fkUbicacion', '=', 'ubi_pa_tra.idubicacion',"left")
        ->join("nomina AS n","n.idNomina","=","p.fkNomina","left")
        ->join("empresa AS e","e.idempresa","=","n.fkEmpresa","left")
        ->where("p.fkEmpleado","=",$idEmpleado);
        if(isset($idPeriodo)){
            $periodoActivo = $periodoActivo->where("p.idPeriodo","=",$idPeriodo);
        }
        $periodoActivo = $periodoActivo->orderBy("p.fkEstado","asc")
        ->orderBy("p.idPeriodo","desc")
        ->first();

        $empleado = DB::table("empleado")->select(  'empleado.*', 'dp.*', 'u.username as usuarioTxt', 'ti.nombre as nombreTipoDoc',
                                                    'ubi_dep_exp.idubicacion AS ubi_depto_exp', 'ubi_pa_exp.idubicacion AS ubi_pais_exp',
                                                    'ubi_dep_nac.idubicacion AS ubi_depto_nac', 'ubi_pa_nac.idubicacion AS ubi_pais_nac',
                                                    'ubi_dep_res.idubicacion AS ubi_depto_res', 'ubi_pa_res.idubicacion AS ubi_pais_res',
                                                    'ubi_dep_tra.idubicacion AS ubi_depto_tra', 'ubi_pa_tra.idubicacion AS ubi_pais_tra')
                                        ->join('datospersonales AS dp','empleado.fkDatosPersonales', '=', 'dp.idDatosPersonales',"left")                                        
                                        ->join("ubicacion AS ubi_ciud_exp", 'dp.fkUbicacionExpedicion', '=', 'ubi_ciud_exp.idubicacion',"left")
                                        ->join("ubicacion AS ubi_dep_exp", 'ubi_ciud_exp.fkUbicacion', '=', 'ubi_dep_exp.idubicacion',"left")
                                        ->join("ubicacion AS ubi_pa_exp", 'ubi_dep_exp.fkUbicacion', '=', 'ubi_pa_exp.idubicacion',"left")

                                        ->join("ubicacion AS ubi_ciud_nac", 'dp.fkUbicacionNacimiento', '=', 'ubi_ciud_nac.idubicacion',"left")
                                        ->join("ubicacion AS ubi_dep_nac", 'ubi_ciud_nac.fkUbicacion', '=', 'ubi_dep_nac.idubicacion',"left")
                                        ->join("ubicacion AS ubi_pa_nac", 'ubi_dep_nac.fkUbicacion', '=', 'ubi_pa_nac.idubicacion',"left")

                                        ->join("ubicacion AS ubi_ciud_res", 'dp.fkUbicacionResidencia', '=', 'ubi_ciud_res.idubicacion',"left")
                                        ->join("ubicacion AS ubi_dep_res", 'ubi_ciud_res.fkUbicacion', '=', 'ubi_dep_res.idubicacion',"left")
                                        ->join("ubicacion AS ubi_pa_res", 'ubi_dep_res.fkUbicacion', '=', 'ubi_pa_res.idubicacion',"left")
                                        
                                        ->join("ubicacion AS ubi_ciud_tra", 'empleado.fkUbicacionLabora', '=', 'ubi_ciud_tra.idubicacion',"left")
                                        ->join("ubicacion AS ubi_dep_tra", 'ubi_ciud_tra.fkUbicacion', '=', 'ubi_dep_tra.idubicacion',"left")
                                        ->join("ubicacion AS ubi_pa_tra", 'ubi_dep_tra.fkUbicacion', '=', 'ubi_pa_tra.idubicacion',"left")

                                        ->join("users AS u", 'u.fkEmpleado','=','empleado.idempleado',"left")
                                        ->join("tipoidentificacion AS ti", 'ti.idtipoIdentificacion','=','dp.fkTipoIdentificacion',"left")
                                        ->where('idempleado', $idEmpleado)
                                        ->first();
        
        $empleado->fkEmpresa = ($periodoActivo->fkEmpresa ?? $empleado->fkEmpresa);
        $empleado->fkNomina =($periodoActivo->fkNomina ?? $empleado->fkNomina);
        $empleado->fechaIngreso =($periodoActivo->fechaInicio ?? $empleado->fechaIngreso);
        
        $empleado->fkCargo =($periodoActivo->fkCargo ?? $empleado->fkCargo);
        $empleado->fkTipoCotizante =($periodoActivo->fkTipoCotizante ?? $empleado->fkTipoCotizante);
        $empleado->esPensionado =($periodoActivo->esPensionado ?? $empleado->esPensionado);
        $empleado->tipoRegimen =($periodoActivo->tipoRegimen ?? $empleado->tipoRegimen);
        $empleado->tipoRegimenPensional =($periodoActivo->tipoRegimenPensional ?? $empleado->tipoRegimenPensional);
        $empleado->fkUbicacionLabora =($periodoActivo->fkUbicacionLabora ?? $empleado->fkUbicacionLabora);
        $empleado->fkLocalidad =($periodoActivo->fkLocalidad ?? $empleado->fkLocalidad);
        $empleado->sabadoLaborable =($periodoActivo->sabadoLaborable ?? $empleado->sabadoLaborable);
        $empleado->formaPago =($periodoActivo->formaPago ?? $empleado->formaPago);
        $empleado->fkEntidad =($periodoActivo->fkEntidad ?? $empleado->fkEntidad);
        $empleado->numeroCuenta =($periodoActivo->numeroCuenta ?? $empleado->numeroCuenta);
        $empleado->tipoCuenta =($periodoActivo->tipoCuenta ?? $empleado->tipoCuenta);
        $empleado->otraFormaPago =($periodoActivo->otraFormaPago ?? $empleado->otraFormaPago);
        $empleado->fkTipoOtroDocumento =($periodoActivo->fkTipoOtroDocumento ?? $empleado->fkTipoOtroDocumento);
        $empleado->otroDocumento =($periodoActivo->otroDocumento ?? $empleado->otroDocumento);
        $empleado->procedimientoRetencion =($periodoActivo->procedimientoRetencion ?? $empleado->procedimientoRetencion);
        $empleado->porcentajeRetencion =($periodoActivo->porcentajeRetencion ?? $empleado->porcentajeRetencion);
        $empleado->fkNivelArl =($periodoActivo->fkNivelArl ?? $empleado->fkNivelArl);
        $empleado->fkCentroTrabajo =($periodoActivo->fkCentroTrabajo ?? $empleado->fkCentroTrabajo);
        $empleado->aplicaSubsidio =($periodoActivo->aplicaSubsidio ?? $empleado->aplicaSubsidio);

        if(isset($periodoActivo->fkEmpresa)){
            $empleado->fkEmpresa = $periodoActivo->fkEmpresa;
            $empleado->fkNomina = $periodoActivo->fkNomina;
        }

        if(isset($periodoActivo->fkUbicacionLabora)){
            $empleado->ubi_pais_tra = $periodoActivo->ubi_pais_tra;
            $empleado->ubi_depto_tra = $periodoActivo->ubi_depto_tra;
            $empleado->fkUbicacionLabora = $periodoActivo->fkUbicacionLabora;
        }


        $paises = Ubicacion::where("fkTipoUbicacion", "=" ,'1')->get();
        $deptosExp = array();
        $ciudadesExp= array();

        if(isset($empleado->ubi_pais_exp)){
            $deptosExp = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_pais_exp)->get();
            $ciudadesExp = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_depto_exp)->get();    
        }
        
        $deptosNac = array();
        $ciudadesNac= array();
        if(isset($empleado->ubi_pais_nac)){
            $deptosNac = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_pais_nac)->get();
            $ciudadesNac = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_depto_nac)->get();
        }

        $deptosRes = array();
        $ciudadesRes= array();
        if(isset($empleado->ubi_pais_res)){
            $deptosRes = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_pais_res)->get();
            $ciudadesRes = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_depto_res)->get();
        }

        $deptosTra = array();
        $ciudadesTra= array();

        if(isset($empleado->ubi_pais_tra)){
            $deptosTra = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_pais_tra)->get();
            $ciudadesTra = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_depto_tra)->get();    
        }




        $generos = DB::table("genero")->get();
        $estadosCivil = DB::table("estadocivil")->get();
        $tipo_vivienda = DB::table("tipo_vivienda")->get();
        $grupoSanguineo = DB::table("gruposanguineo")->get();
        $rhs = DB::table("rh")->get();
        $tipoidentificacion = DB::table("tipoidentificacion")->where("tipo", "=", "0")->get();

        $contactosEmergencia = DB::table("contactoemergencia")->select(
            "contactoemergencia.*",
            'ubi_dep_emer.idubicacion AS ubi_depto_emer', 
            'ubi_pa_emer.idubicacion AS ubi_pais_emer'
        )
        ->join("ubicacion AS ubi_ciud_emer", 'contactoemergencia.fkUbicacion', '=', 'ubi_ciud_emer.idubicacion')
        ->join("ubicacion AS ubi_dep_emer", 'ubi_ciud_emer.fkUbicacion', '=', 'ubi_dep_emer.idubicacion')
        ->join("ubicacion AS ubi_pa_emer", 'ubi_dep_emer.fkUbicacion', '=', 'ubi_pa_emer.idubicacion')
        ->where("fkDatosEmpleado", "=", $empleado->idDatosPersonales)->get();
        $deptosContactosEmergencia = array();
        $ciudadesContactosEmergencia = array();
        foreach($contactosEmergencia as $contactoEmergencia){
            
            $deptosEmer = Ubicacion::where("fkUbicacion", "=", $contactoEmergencia->ubi_pais_emer)->get();
            $ciudadesEmer = Ubicacion::where("fkUbicacion", "=", $contactoEmergencia->ubi_depto_emer)->get();

            array_push($deptosContactosEmergencia, $deptosEmer);
            array_push($ciudadesContactosEmergencia, $ciudadesEmer);

        }

        $nucleofamiliar = DB::table("nucleofamiliar")->where("fkDatosEmpleado", "=", $empleado->idDatosPersonales)->get();
        

        
        $parentescos = DB::table("parentesco")->get();
        $escolaridades = DB::table("escolaridad")->get();



        $empresas = DB::table("empresa")->orderBy("razonSocial")->get();
        $centrosCosto = array();
        if(isset($empleado->fkEmpresa)){
            $centrosCosto = DB::table("centrocosto")->where("fkEmpresa","=",$empleado->fkEmpresa)->get();
        }


        $tipoContratos = DB::table('tipocontrato')->get();
        $cargos = DB::table("cargo")->orderBy("nombreCargo")->get();
        $entidadesFinancieras = DB::table("tercero")->where("fk_actividad_economica", "=", "4")->get();
 
        $afiliaciones = DB::table("afiliacion")
        ->where('fkEmpleado', "=", $idEmpleado)
        ->where('fkPeriodoActivo', "=", $periodoActivo->idPeriodo)
        ->get();

        $tipoafilicaciones = DB::table('tipoafilicacion')->get();
        $entidadesAfiliacion = array();
        foreach($afiliaciones as $afiliacion){
            $afiliacionesEnt = DB::table("tercero")
                    ->join('tipoafilicacion AS ta','ta.fkActividadEconomica', '=', 'tercero.fk_actividad_economica')
                    ->where("ta.idTipoAfiliacion", "=", $afiliacion->fkTipoAfilicacion)->orderBy("razonSocial")->get();

            $entidadesAfiliacion[$afiliacion->idAfiliacion] = $afiliacionesEnt;
        }


        $nivelesArl = DB::table('nivel_arl')->get();

        $afiliacionesEnt1 = DB::table("tercero")
                    ->join('tipoafilicacion AS ta','ta.fkActividadEconomica', '=', 'tercero.fk_actividad_economica')
                    ->where("ta.idTipoAfiliacion", "=", '1')
                    ->whereNotIn("tercero.idTercero",["10","109","111","112","113"])
                    ->orderBy("razonSocial")
                    ->get();

        $afiliacionesEnt2 = DB::table("tercero")
                    ->join('tipoafilicacion AS ta','ta.fkActividadEconomica', '=', 'tercero.fk_actividad_economica')
                    ->where("ta.idTipoAfiliacion", "=", '2')
                    ->orderBy("razonSocial")
                    ->get();
        $afiliacionesEnt3 = DB::table("tercero")
                    ->join('tipoafilicacion AS ta','ta.fkActividadEconomica', '=', 'tercero.fk_actividad_economica')
                    ->where("ta.idTipoAfiliacion", "=", '3')->orderBy("razonSocial")->get();
        $afiliacionesEnt4 = DB::table("tercero")
                    ->join('tipoafilicacion AS ta','ta.fkActividadEconomica', '=', 'tercero.fk_actividad_economica')
                    ->where("ta.idTipoAfiliacion", "=", '4')->whereNotIn("tercero.idTercero",["120"])->orderBy("razonSocial")->get();

               
        $conceptosFijos = DB::table('conceptofijo', 'cf')->select(["cf.*", "c.nombre AS nombreConcepto"])
        ->join("concepto AS c", "c.idConcepto", "=", "cf.fkConcepto")
        ->where("cf.fkPeriodoActivo","=",$periodoActivo->idPeriodo)
        ->where("cf.fkEmpleado", "=", $idEmpleado)->get();

        $contratoActivo = DB::table('contrato')
        ->where("fkEmpleado","=",$idEmpleado)
        ->where("fkPeriodoActivo","=",$periodoActivo->idPeriodo)
        ->orderBy("idcontrato", "desc")
        ->first();
        
        $conceptos = DB::table("concepto")->whereNotIn("idconcepto", [1,2])->orderBy("nombre")->get();

        $centrosCostoxEmpleado = DB::table("empleado_centrocosto")
        ->where("fkEmpleado","=", $idEmpleado)
        ->where('fkPeriodoActivo', "=", $periodoActivo->idPeriodo)
        ->get();
        $centrosCostos = array();
        $nominas = array();

        if(isset($empleado->fkEmpresa)){
            $centrosCostos = DB::table("centrocosto")->where("fkEmpresa","=", $empleado->fkEmpresa)->get();
            $nominas = DB::table("nomina")->where("fkEmpresa","=", $empleado->fkEmpresa)->orderBy("nombre")->get();
            
            
        }
        $beneficiosTributarios = DB::table("beneficiotributario", "bt")
        ->select('bt.*', 'nf.*', 'ubi_dep_benef.idubicacion AS ubi_depto_benef', 'ubi_pa_benef.idubicacion AS ubi_pais_benef')
            ->join("nucleofamiliar AS nf", 'nf.idNucleoFamiliar', '=', 'bt.fkNucleoFamiliar', 'left')
            ->join("ubicacion AS ubi_ciud_benef", 'nf.fkUbicacion', '=', 'ubi_ciud_benef.idubicacion', 'left')
            ->join("ubicacion AS ubi_dep_benef", 'ubi_ciud_benef.fkUbicacion', '=', 'ubi_dep_benef.idubicacion', 'left')
            ->join("ubicacion AS ubi_pa_benef", 'ubi_dep_benef.fkUbicacion', '=', 'ubi_pa_benef.idubicacion', 'left')
        ->where("fkEmpleado", "=", $idEmpleado)
        ->where("fkPeriodoActivo","=",$periodoActivo->idPeriodo)
        ->get();
        
        
        $deptosBeneficiosTributarios = array();
        $ciudadesBeneficiosTributarios = array();
        foreach($beneficiosTributarios as $beneficioTributario){            
            $deptosBen = Ubicacion::where("fkUbicacion", "=", $beneficioTributario->ubi_pais_benef)->get();
            $ciudadesBen = Ubicacion::where("fkUbicacion", "=", $beneficioTributario->ubi_depto_benef)->get();
            array_push($deptosBeneficiosTributarios, $deptosBen);
            array_push($ciudadesBeneficiosTributarios, $ciudadesBen);
        }


        $destino = "";
        if(isset($req->destino)){
            $destino = $req->destino;
        }

        $generosBen = DB::table("genero")->whereIn("idGenero",["1","2"])->get();
        $tipobeneficio = DB::table("tipobeneficio")->orderBy("nombre")->get();

        $cambiosAfiliacion = DB::table("cambioafiliacion","ca")
        ->where("ca.fkEmpleado", "=", $empleado->idempleado)
        ->where("ca.fkPeriodoActivo","=",$periodoActivo->idPeriodo)
        ->get();

        $afiliacionesNuevas = array();
        foreach($cambiosAfiliacion as $cambioAfiliacion){
            $afiliacionesNuevas[$cambioAfiliacion->fkAfiliacion] = $cambioAfiliacion;
        }

        $cambioSalario = DB::table("cambiosalario","cs")
        ->where('cs.fkEmpleado', "=", $empleado->idempleado)
        ->where("cs.fkPeriodoActivo","=",$periodoActivo->idPeriodo)
        ->where('cs.fkEstado', "=", "4")
        ->orderBy("idCambioSalario","desc")->first();

        $subtiposcotizante = DB::table("subtipocotizante")->get();
        
        $upcadicional = DB::table("upcadicional", 'nf')
        ->select('nf.*', 'ubi_dep_upc.idubicacion AS ubi_depto_upc', 'ubi_pa_upc.idubicacion AS ubi_pais_upc')
        ->join("ubicacion AS ubi_ciud_upc", 'nf.fkUbicacion', '=', 'ubi_ciud_upc.idubicacion', 'left')
        ->join("ubicacion AS ubi_dep_upc", 'ubi_ciud_upc.fkUbicacion', '=', 'ubi_dep_upc.idubicacion', 'left')
        ->join("ubicacion AS ubi_pa_upc", 'ubi_dep_upc.fkUbicacion', '=', 'ubi_pa_upc.idubicacion', 'left')
        ->where("nf.fkEmpleado", "=", $empleado->idempleado)->get();
        $deptosUpc = array();
        $ciudadesUpc = array();
        foreach($upcadicional as $row => $upc){
            $deptosBen = Ubicacion::where("fkUbicacion", "=", $upc->ubi_pais_upc)->get();
            $ciudadesBen = Ubicacion::where("fkUbicacion", "=", $upc->ubi_depto_upc)->get();
            $deptosUpc[$row] = $deptosBen;
            $ciudadesUpc[$row] = $ciudadesBen;
        }
        $existe = false;
        $existeUsuario = User::where('fkEmpleado', $idEmpleado)->first();
        if ($existeUsuario) {
            $existe = true;
        }
        $nivelesEstudios = DB::table("nivel_estudio")->get();
        $etnias = DB::table("etnia")->get();

        $centrosTrabajo = DB::table("centrotrabajo")->get();

        $usu = UsuarioController::dataAdminLogueado();
        $periocidad = array();
        $periodo = NULL;
        $nomina = DB::table("nomina","n")
        ->join("empleado as e","e.fkNomina", "=","n.idNomina")
        ->where("e.idempleado","=",$idEmpleado)->first();
        if(isset($nomina)){
            $periocidad = DB::table("periocidad")->where("per_periodo", "=",$nomina->periodo)->get();
            $periodo = $nomina->periodo;
        }
        
        $tiposcotizante = DB::table("tipo_cotizante")->get();

        $usu = UsuarioController::dataAdminLogueado();
        
        $cambioSalarioFin = DB::table("cambiosalario","cs")
        ->where('cs.fkEmpleado', "=", $empleado->idempleado)
        ->where("cs.fkPeriodoActivo","=",$periodoActivo->idPeriodo)
        ->where('cs.fkEstado', "=", "5")
        ->orderBy("idCambioSalario","desc")->get();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu de ver empleado");

        $contratosAnteriores =  DB::table('contrato')
        ->where("fkEmpleado","=",$idEmpleado)
        ->where("fkPeriodoActivo","=",$periodoActivo->idPeriodo)
        ->where("idcontrato","<>",$contratoActivo->idcontrato ?? 0)
        ->orderBy("idcontrato", "asc")
        ->get();


        return view('/empleado.verEmpleado', [
            'cambioSalarioFin' => $cambioSalarioFin,
            'paises'=>$paises,
            'dataUsu' => $usu,
            'usuExiste' => $existe,
            'generos' => $generos, 
            'estadosCivil' => $estadosCivil, 
            'tipo_vivienda' => $tipo_vivienda,
            'grupoSanguineo' => $grupoSanguineo,
            'rhs' => $rhs,
            'tipoidentificacion' => $tipoidentificacion,
            'empleado' => $empleado,
            'deptosExp' => $deptosExp,
            'ciudadesExp' => $ciudadesExp,
            'deptosNac' => $deptosNac,
            'ciudadesNac' => $ciudadesNac,
            'deptosRes' => $deptosRes,
            'ciudadesRes' => $ciudadesRes,
            'contactosEmergencia' => $contactosEmergencia,
            'deptosContactosEmergencia' => $deptosContactosEmergencia,
            'ciudadesContactosEmergencia' => $ciudadesContactosEmergencia,
            'nucleofamiliar' => $nucleofamiliar,
            'parentescos' => $parentescos,
            'escolaridades' => $escolaridades,
            'empresas' => $empresas,
            'centrosCosto' => $centrosCosto,
            'tipoContratos' => $tipoContratos,
            'idEmpleado' => $idEmpleado, 
            'cargos' => $cargos, 
            'entidadesFinancieras' => $entidadesFinancieras,
            'afiliaciones' => $afiliaciones,
            'tipoafilicaciones' => $tipoafilicaciones,
            'nivelesArl' => $nivelesArl,
            'afiliacionesEnt1' => $afiliacionesEnt1,
            'afiliacionesEnt2' => $afiliacionesEnt2,
            'afiliacionesEnt3' => $afiliacionesEnt3,
            'afiliacionesEnt4' => $afiliacionesEnt4,
            'entidadesAfiliacion' => $entidadesAfiliacion,
            'conceptosFijos' => $conceptosFijos,
            'contratoActivo' => $contratoActivo,
            'conceptos' => $conceptos,
            'destino' => $destino,
            'centrosCostoxEmpleado' => $centrosCostoxEmpleado,
            'centrosCostos' => $centrosCostos,
            'nominas' => $nominas,
            'deptosTra' => $deptosTra,
            'ciudadesTra' =>$ciudadesTra,
            'beneficiosTributarios' => $beneficiosTributarios,
            'generosBen' => $generosBen,
            'tipobeneficio' => $tipobeneficio,
            'deptosBeneficiosTributarios' => $deptosBeneficiosTributarios,
            'ciudadesBeneficiosTributarios' => $ciudadesBeneficiosTributarios,
            'afiliacionesNuevas' => $afiliacionesNuevas,
            'cambioSalario' => $cambioSalario,
            'subtiposcotizante' => $subtiposcotizante,
            'upcAdicional' => $upcadicional,
            "periocidad" => $periocidad,
            "periodo" => $periodo,
            'deptosUpc' => $deptosUpc,
            'ciudadesUpc' => $ciudadesUpc,
            'nivelesEstudios' => $nivelesEstudios,
            'etnias' => $etnias,
            "centrosTrabajo" => $centrosTrabajo,
            'tiposcotizante' => $tiposcotizante,
            'periodoActivo' => $periodoActivo,
            'contratosAnteriores' => $contratosAnteriores
        ]);


    }

    public function verificarDocumento(Request $req){
        $empleado = DB::table('datospersonales')
            ->where("numeroIdentificacion", "=", $req->numIdentificacion)
            ->where("fkTipoIdentificacion", "=" , $req->tIdentificacion)
            ->select("e.idempleado")
            ->join("empleado AS e", "e.fkDatosPersonales", "=", "datospersonales.idDatosPersonales")->first();
        
        if(isset($empleado->idempleado)){
            return response()->json([
                "success" => false,
                "link" => "/empleado/formModificar/".$empleado->idempleado,
                "respuesta" => "Este documento ya se encuentra en base de datos, quiere ir a modificar este empleado? <br> 
                                De lo contrario deberá cambiar el numero de documento"
            ]);
        }
        else{
            return response()->json(["success" => true]);
        }

    }
    public function cargarCentroCosto(Request $request){
        $centrosCosto = DB::table("centrocosto")->where("fkEmpresa", "=" ,$request->empresa)->get();
        
        return view('/empleado.ajax.centroCosto', [
                'centrosCosto'=>$centrosCosto,
                'num' => $request->num
        ]);
    }
    public function cargarBeneficiosTributarios($num, $idEmpleado){

        $tipobeneficio = DB::table("tipobeneficio")->orderBy("nombre")->get();
        $nucleofamiliar = DB::table("nucleofamiliar", "nf")
            ->select('nf.*')
            ->join('datospersonales as dp', "dp.idDatosPersonales", "=", "nf.fkDatosEmpleado")
            ->join('empleado as e', "e.fkDatosPersonales", "=", "dp.idDatosPersonales")            
            ->where("e.idempleado", "=",$idEmpleado)
            ->orderBy("nf.nombre")->get();
        $tipoidentificacion = DB::table('tipoidentificacion')->orderBy("nombre")->get();
        $generos = DB::table("genero")->whereIn('idGenero',["1","2"])->get();
        $paises = Ubicacion::where("fkTipoUbicacion", "=" ,'1')->get();

        return view('/empleado.ajax.beneficioTributario', [
            'num' => $num,
            'tipobeneficio' => $tipobeneficio,
            'nucleofamiliar' => $nucleofamiliar,
            'tipoidentificacion' => $tipoidentificacion,
            'generos' => $generos,
            'paises' => $paises
        ]);
    }
    public function cargarDatosPorEmpresa($idEmpresa){
        $empresa = DB::table('empresa')->where("idempresa", "=", $idEmpresa)->first();

        $nominas = DB::table("nomina")->where("fkEmpresa", "=", $idEmpresa)->orderBy("nombre")->get();
		$opcionesNomina = "<option value=''></option>";
		foreach($nominas as $nomina){
			$opcionesNomina.= '<option value="'.$nomina->idNomina.'">'.$nomina->nombre.'</option>';
        }

        $centrosCosto = DB::table("centrocosto")->where("fkEmpresa", "=" ,$idEmpresa)->orderBy("nombre")->get();
		$opcionesCentroCosto = "<option value=''></option>";
		foreach($centrosCosto as $centroCosto){
			$opcionesCentroCosto.= '<option value="'.$centroCosto->idcentroCosto.'">'.$centroCosto->nombre.'</option>';
        }
        if(strpos($empresa->dominio,"@")===false){
            $empresa->dominio = "@".$empresa->dominio;
        }

		return response()->json([
			"success" => true,
            "opcionesNomina" => $opcionesNomina,
            "opcionesCentroCosto" => $opcionesCentroCosto,
            "dominio" => ""
        ]);
    }
    public function addDatosInfoPersonalSinEmpresa(Request $req){
        $existeUsuario = User::where('fkEmpleado', $req->idEmpleado)->first();
        if (is_null($existeUsuario)) {
            $usuarioNuevo = new User;
            $usuarioNuevo->email = $req->infoUsuario;
            $usuarioNuevo->username = $req->infoUsuario;
            $usuarioNuevo->password = $req->password;
            $usuarioNuevo->fkRol = 1;
            $usuarioNuevo->estado = 1;
            $usuarioNuevo->fkEmpleado = $req->idEmpleado;
            $usuarioNuevo->save();
        }
        $datosPersonales = DB::table("datospersonales")->select("datospersonales.numeroIdentificacion")
                                        ->join('empleado AS e','e.fkDatosPersonales', '=', 'datospersonales.idDatosPersonales')
                                        ->where("e.idempleado", "=", $req->idEmpleado)->first();

        $insertUsuario = array("usuario" => $req->infoUsuario, "pass" => Hash::make($datosPersonales->numeroIdentificacion), "fkRol"=> "1");
        $idUsuario = DB::table('usuario')->insertGetId($insertUsuario, "idusuario");


        DB::table("periodo")
        ->where("idPeriodo", "=", $req->idPeriodo)
        ->update(
            [
                "fechaInicio" => $req->infoFechaIngreso,
                "fkNomina" => $req->infoNomina,
                "tipoRegimen" => $req->infoTipoRegimen,
                "fkUbicacionLabora" => $req->infoLugarLabora,
                "fkLocalidad" => $req->infoLocalidad,
                "sabadoLaborable" => $req->infoSabadoLabora,
                "formaPago" => $req->infoFormaPago,
                "fkEntidad" => $req->infoEntidadFinanciera,
                "numeroCuenta" => $req->infoNoCuenta,
                "tipoCuenta" => $req->infoTipoCuenta,
                "otraFormaPago" => $req->infoOtraFormaPago,
                "fkTipoOtroDocumento" => $req->infoOtroTIdentificacion, 
                "otroDocumento" => $req->infoOtroDocumento,
                "fkCargo" => $req->infoCargo,
                "procedimientoRetencion" => $req->infoProcedimientoRetencion, 
                "porcentajeRetencion" => $req->infoPorcentajeRetencion,
                "fkTipoCotizante" => $req->infoTipoCotizante,
                "esPensionado" => $req->infoSubTipoCotizante,
                "aplicaSubsidio" => $req->infoAplicaSubsidio
            ]
        );
            
        $periodoActivo = DB::table("periodo")
        ->where("idPeriodo", "=", $req->idPeriodo)
        ->first();

        $insertContrato = array(
            "fechaInicio" => $req->infoFechaIngreso,
            "fechaFin" => $req->infoFechaFin, 
            "fkEstado" => "4", 
            "fkTipoContrato" => $req->infoTipoContrato, 
            "tipoDuracionContrato" => $req->infoTipoDuracionContrato, 
            "fkEmpleado" => $req->idEmpleado,
            "fkPeriodoActivo" => $periodoActivo->idPeriodo
        );
        
        if($req->infoTipoDuracionContrato == "MES"){ 
            $meses = $req->infoDuracionContrato;
            $dias = $req->infoDuracionContrato*30;

            $insertContrato["numeroMeses"] = $meses;
            $insertContrato["numeroDias"] = $dias;
        }
        else{
            $meses = $req->infoDuracionContrato / 30;
            $dias = $req->infoDuracionContrato;
            $insertContrato["numeroMeses"] = $meses;
            $insertContrato["numeroDias"] = $dias;
        }

        DB::table('contrato')->insert($insertContrato);
                
        foreach ($req->infoCentroCosto as $key => $idCentroCosto) {
            if($idCentroCosto != ""){
                $insertContactoEmergencia = array( 
                     "fkEmpleado" => $req->idEmpleado, 
                    "fkCentroCosto" => $idCentroCosto,
                    "porcentajeTiempoTrabajado" => substr($req->infoPorcentaje[$key],0,-1),
                    "fkPeriodoActivo" => $periodoActivo->idPeriodo
                );
                DB::table('empleado_centrocosto')->insert($insertContactoEmergencia);
            }
           
        }


        if(isset($req->infoTipoBeneficio)){
            foreach ($req->infoTipoBeneficio as $key => $tipoBeneficio) {
           
                $insertBeneficioTributario = array(  "fkTipoBeneficio" => $tipoBeneficio, 
                                                    "valorMensual" => $req->infoValorMensual[$key],
                                                    "fechaVigencia" => $req->infoFechaVigencia[$key],
                                                    "numMeses" => $req->infoNumMeses[$key],
                                                    "valorTotal" => $req->infoValorTotal[$key],
                                                    "fkEmpleado" => $req->idEmpleado,
                                                    "fkPeriodoActivo" => $periodoActivo->idPeriodo
                                                );
                if($tipoBeneficio == "4"){
                    $insertBeneficioTributario["fkNucleoFamiliar"] = $req->infoPersonaVive[$key];
                    
                    $arrNucleoFamiliar = [
                        "fkTipoIdentificacion" => $req->infoTIdentificacion[$key],
                        "numIdentificacion" => $req->infoNumIdentificacion[$key],
                    ];

                    DB::table('nucleofamiliar')
                    ->where('idNucleoFamiliar', $req->infoPersonaVive[$key])
                    ->update($arrNucleoFamiliar);
                }
                DB::table('beneficiotributario')->insert($insertBeneficioTributario);
            }
        }
        
        $this->validarEstadoEmpleado($req->idEmpleado, $periodoActivo->idPeriodo);

        return response()->json([
            "success" => true,
            "idempleado" => $req->idEmpleado,
            "idPeriodo" => $periodoActivo->idPeriodo
        ]);
    }

    public function modificarDatosInfoPersonal(Request $req){
        $existeUsuario = User::where('fkEmpleado', $req->idEmpleado)->first();

        

        if (is_null($existeUsuario)) {
            $datosEmpleado = DB::table("datospersonales","dp")
            ->join("empleado as e", "e.fkDatosPersonales","=","dp.idDatosPersonales")
            ->where("e.idempleado","=",$req->idEmpleado)->first();
            
            
            $usuarioNuevo = new User;
            $usuarioNuevo->email = $datosEmpleado->numeroIdentificacion;
            $usuarioNuevo->username = $datosEmpleado->numeroIdentificacion;
            $usuarioNuevo->password = $datosEmpleado->numeroIdentificacion."#".substr($datosEmpleado->fechaNacimiento,0,4);
            $usuarioNuevo->fkRol = 1;
            $usuarioNuevo->estado = 1;
            $usuarioNuevo->fkEmpleado = $req->idEmpleado;
            $usuarioNuevo->save();
        }


        DB::table("periodo")
        ->where("idPeriodo", "=", $req->idPeriodo)
        ->update(
            [
                "fechaInicio" => $req->infoFechaIngreso,
                "fkNomina" => $req->infoNomina,
                "tipoRegimen" => $req->infoTipoRegimen,
                "fkUbicacionLabora" => $req->infoLugarLabora,
                "fkLocalidad" => $req->infoLocalidad,
                "sabadoLaborable" => $req->infoSabadoLabora,
                "formaPago" => $req->infoFormaPago,
                "fkEntidad" => $req->infoEntidadFinanciera,
                "numeroCuenta" => $req->infoNoCuenta,
                "tipoCuenta" => $req->infoTipoCuenta,
                "otraFormaPago" => $req->infoOtraFormaPago,
                "fkTipoOtroDocumento" => $req->infoOtroTIdentificacion, 
                "otroDocumento" => $req->infoOtroDocumento,
                "fkCargo" => $req->infoCargo,
                "procedimientoRetencion" => $req->infoProcedimientoRetencion, 
                "porcentajeRetencion" => $req->infoPorcentajeRetencion,
                "fkTipoCotizante" => $req->infoTipoCotizante,
                "esPensionado" => $req->infoSubTipoCotizante,
                "aplicaSubsidio" => $req->infoAplicaSubsidio
            ]
        );
        
        $conceptosFijos = DB::table('conceptofijo', 'cf')
        ->where("cf.fkPeriodoActivo", "=", $req->idPeriodo)
        ->whereIn("cf.fkConcepto",[1,2])
        ->first();
        
        if($req->infoTipoRegimen == "Salario Integral" && isset($conceptosFijos)){
            if($conceptosFijos->fkConcepto == 1){
                DB::table("conceptofijo")->where("idConceptoFijo", "=", $conceptosFijos->idConceptoFijo)
                ->update(["fkConcepto" => 2]);
            }            
        }
        else if(isset($conceptosFijos)){
            if($conceptosFijos->fkConcepto == 2){
                DB::table("conceptofijo")->where("idConceptoFijo", "=", $conceptosFijos->idConceptoFijo)
                ->update(["fkConcepto" => 1]);
            }  
        }
        


        $periodoActivo = DB::table("periodo")
        ->where("idPeriodo", "=", $req->idPeriodo)
        ->first();
        
        $modContrato = array(            
            "fechaFin" => $req->infoFechaFin, 
            "fkEstado" => "4", 
            "fkTipoContrato" => $req->infoTipoContrato, 
            "tipoDuracionContrato" => $req->infoTipoDuracionContrato, 
            "fkEmpleado" => $req->idEmpleado,
            "fkPeriodoActivo" => $req->idPeriodo
        );
        
        if(($req->fechaInicioActivoAnt == $req->fechaIngresoAnt) || !isset($req->fechaInicioActivoAnt) || empty($req->fechaInicioActivoAnt)){
            $modContrato["fechaInicio"] = $req->infoFechaIngreso;
        }

        if($req->infoTipoDuracionContrato == "MES"){ 
            $meses = $req->infoDuracionContrato;
            $dias = $req->infoDuracionContrato*30;

            $modContrato["numeroMeses"] = $meses;
            $modContrato["numeroDias"] = $dias;
        }
        else{
            $meses = $req->infoDuracionContrato / 30;
            $dias = $req->infoDuracionContrato;
            $modContrato["numeroMeses"] = $meses;
            $modContrato["numeroDias"] = $dias;
        }

        
         
        

        if(isset($req->infoTipoContratoN)){
            $fechaInicio = new DateTime($req->infoFechaFin);
            $fechaInicio->add(new DateInterval('P1D'));  

            $insertContrato = array(
                "fechaInicio" => $fechaInicio->format('Y-m-d'), 
                "fechaFin" => $req->infoFechaFinN, 
                "fkEstado" => "4", 
                "fkTipoContrato" => $req->infoTipoContratoN, 
                "tipoDuracionContrato" => $req->infoTipoDuracionContratoN, 
                "fkEmpleado" => $req->idEmpleado,
                "fkPeriodoActivo" => $req->idPeriodo
            );  

            if($req->infoTipoDuracionContratoN == "MES"){ 
                $meses = $req->infoDuracionContratoN;
                $dias = $req->infoDuracionContratoN*30;

                $insertContrato["numeroMeses"] = $meses;
                $insertContrato["numeroDias"] = $dias;
            }
            else{
                $meses = $req->infoDuracionContratoN / 30;
                $dias = $req->infoDuracionContratoN;
                $insertContrato["numeroMeses"] = $meses;
                $insertContrato["numeroDias"] = $dias;
            }
            DB::table('contrato')->insert($insertContrato);

            $modContrato["fkEstado"] = "2";
        }

        if(!isset($req->idContratoActivo)){
            $affected = DB::table('contrato')
            ->insert($modContrato);
        }
        else{
            $affected = DB::table('contrato')
              ->where('idcontrato', $req->idContratoActivo)
              ->update($modContrato);
        }
        

        if(isset($req->infoNuevoTipoCotizanteCamb) && isset($req->infoFechaAplicaCambioTCotCamb)){
            $cambioTipoCot = DB::table("cambiotipocotizante")
            ->where("idCambioTipoCotizante","=",$req->infoIdCambioTipoCotizante)
            ->first();

            $nomina = DB::table("nomina")->where("idNomina","=",$req->infoNomina)->first();

            $fechaInicio = date("Y-m-01", strtotime($req->infoFechaAplicaCambioTCotCamb));

            if($nomina->periodo == 15 && intval(date("d", strtotime($req->infoFechaAplicaCambioTCotCamb))) > 15 ){
                $fechaInicio = date("Y-m-16", strtotime($req->infoFechaAplicaCambioTCotCamb));
            }
            

            $diasAntValor = $this->days_360($fechaInicio, $req->infoFechaAplicaCambioTCotCamb);

            $valorNov = ($cambioTipoCot->valorCompletoAnt / 30) * $diasAntValor;

            $arrInsCambioTipoCotizante = [
                "fkNuevoTipoCotizante" => $req->infoNuevoTipoCotizanteCamb,
                "fechaCambio" => $req->infoFechaAplicaCambioTCotCamb,
                "dias" => $diasAntValor,
                "valorNovedad" => $valorNov,
                "fkEstado" => "7"                
            ];

            DB::table("cambiotipocotizante")
            ->where("idCambioTipoCotizante","=",$req->infoIdCambioTipoCotizante)
            ->update($arrInsCambioTipoCotizante);

            $updateEmpleado = array(
                "fkTipoCotizante" => $req->infoNuevoTipoCotizanteCamb, 
                "fechaIngreso" => $req->infoFechaAplicaCambioTCotCamb
            );
            DB::table('empleado')
            ->where('idempleado', $req->idEmpleado)
            ->update($updateEmpleado);

            $updatePeriodo = array(
                "fkTipoCotizante" => $req->infoNuevoTipoCotizanteCamb, 
                "fechaInicio" => $req->infoFechaAplicaCambioTCotCamb
            );
            DB::table('periodo')
            ->where('idPeriodo', $req->idPeriodo)
            ->update($updatePeriodo);
        }

        if(isset($req->infoNuevoTipoCotizante) && isset($req->infoFechaAplicaCambioTCot)){
            
            $conceptosFijos = DB::table('conceptofijo', 'cf')
            ->where("cf.fkEmpleado", "=", $req->idEmpleado)
            ->where("cf.fkPeriodoActivo", "=", $req->idPeriodo)
            ->whereIn("cf.fkConcepto",[1,2,53,54])
            ->first();

            

            $nomina = DB::table("nomina")->where("idNomina","=",$req->infoNomina)
            ->first();

            $fechaInicio = date("Y-m-01", strtotime($req->infoFechaAplicaCambioTCot));

            if($nomina->periodo == 15 && intval(date("d", strtotime($req->infoFechaAplicaCambioTCot))) > 15 ){
                $fechaInicio = date("Y-m-16", strtotime($req->infoFechaAplicaCambioTCot));
            }
            

            $diasAntValor = $this->days_360($fechaInicio, $req->infoFechaAplicaCambioTCot);

            $valorNov = ($conceptosFijos->valor / 30) * $diasAntValor;

            $arrInsCambioTipoCotizante = [
                "fkEmpleado" => $req->idEmpleado,
                "fkPeriodoActivo" => $req->idPeriodo,
                "fkNuevoTipoCotizante" => $req->infoNuevoTipoCotizante,
                "fechaCambio" => $req->infoFechaAplicaCambioTCot,
                "fkTipoCotizanteAnt" => $req->infoTipoCotizante,
                "fkConceptoAnt" => $conceptosFijos->fkConcepto,
                "valorCompletoAnt" => $conceptosFijos->valor,
                "dias" => $diasAntValor,
                "valorNovedad" => $valorNov,
                "fkEstado" => "7"
            ];
            DB::table("cambiotipocotizante")->insert($arrInsCambioTipoCotizante);

            DB::table("conceptofijo")
            ->where("idConceptoFijo","=",$conceptosFijos->idConceptoFijo)
            ->delete();

            $updatePeriodo = array(
                "fkTipoCotizante" => $req->infoNuevoTipoCotizante, 
                "fechaInicio" => $req->infoFechaAplicaCambioTCot
            );
            DB::table("periodo")
            ->where("idPeriodo", "=", $req->idPeriodo)
            ->update($updatePeriodo);
        }
       
        
        DB::table('empleado_centrocosto')
        ->where("fkEmpleado","=",$req->idEmpleado)
        ->where("fkPeriodoActivo","=",$periodoActivo->idPeriodo)
        ->delete();
        
        foreach ($req->infoCentroCosto as $key => $idCentroCosto) {
            if($idCentroCosto != ""){
                $insertEmpleadoCentroCosto = array("fkEmpleado" => $req->idEmpleado, 
                    "fkCentroCosto" => $idCentroCosto,
                    "porcentajeTiempoTrabajado" => substr($req->infoPorcentaje[$key],0,-1),
                    "fkPeriodoActivo" => $periodoActivo->idPeriodo
                );

                DB::table('empleado_centrocosto')->insert($insertEmpleadoCentroCosto);    
            }           
        }

        $arrBeneficiosNotIn = array();
        if(isset($req->infoTipoBeneficio)){
            foreach ($req->infoTipoBeneficio as $key => $tipoBeneficio) {
                $insertBeneficioTributario = array( "fkTipoBeneficio" => $tipoBeneficio, 
                                                    "valorMensual" => $req->infoValorMensual[$key],
                                                    "fechaVigencia" => $req->infoFechaVigencia[$key],
                                                    "numMeses" => $req->infoNumMeses[$key],
                                                    "valorTotal" => $req->infoValorTotal[$key],
                                                    "fkEmpleado" => $req->idEmpleado,
                                                    "fkPeriodoActivo" => $req->idPeriodo);
                if($tipoBeneficio == "4"){
                    $insertBeneficioTributario["fkNucleoFamiliar"] = $req->infoPersonaVive[$key];
                    
                    $arrNucleoFamiliar = [
                        "fkTipoIdentificacion" => $req->infoTIdentificacion[$key],
                        "numIdentificacion" => $req->infoNumIdentificacion[$key],
                    ];

                    DB::table('nucleofamiliar')
                    ->where('idNucleoFamiliar', $req->infoPersonaVive[$key])
                    ->update($arrNucleoFamiliar);
                }


                if($req->idBeneficioTributario[$key]=="-1"){
                    $idBeneficioTributario = DB::table('beneficiotributario')->insertGetId($insertBeneficioTributario, "idBeneficioTributario");
                    array_push($arrBeneficiosNotIn, $idBeneficioTributario);
                }
                else{
                    DB::table('beneficiotributario')
                    ->where('idBeneficioTributario', $req->idBeneficioTributario[$key])
                    ->update($insertBeneficioTributario);
                    array_push($arrBeneficiosNotIn, $req->idBeneficioTributario[$key]);
                }
                
            }
            
        }
 

        DB::table('beneficiotributario')
        ->whereNotIn("idBeneficioTributario", $arrBeneficiosNotIn)
        ->where("fkEmpleado","=", $req->idEmpleado)
        ->where("fkPeriodoActivo","=", $req->idPeriodo)
        ->delete();

        $this->validarEstadoEmpleado($req->idEmpleado, $req->idPeriodo);

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", modificó los datos personales del empleado:".$req->idEmpleado);

        return response()->json([
            "success" => true,
            "idempleado" => $req->idEmpleado,
            "idPeriodo" => $req->idPeriodo
        ]);
    }

    public function modificarDatosInfoPersonalReintegro(Request $req){
        
        $existeUsuario = User::where('fkEmpleado', $req->idEmpleado)->first();
        if (is_null($existeUsuario)) {
            $usuarioNuevo = new User;
            $usuarioNuevo->email = $req->infoUsuario;
            $usuarioNuevo->username = $req->infoUsuario;
            $usuarioNuevo->password = $req->password;
            $usuarioNuevo->fkRol = 1;
            $usuarioNuevo->estado = 1;
            $usuarioNuevo->fkEmpleado = $req->idEmpleado;
            $usuarioNuevo->save();
        }
        $updateEmpleado = array(
            "fkEmpresa" => $req->infoEmpresa
        );
        
        $idPeriodo = DB::table("periodo")
        ->where("idPeriodo", "=", $req->idPeriodo)
        ->insertGetId(
            [
                "fechaInicio" => $req->infoFechaIngreso,
                "fkNomina" => $req->infoNomina,
                "tipoRegimen" => $req->infoTipoRegimen,
                "fkUbicacionLabora" => $req->infoLugarLabora,
                "fkLocalidad" => $req->infoLocalidad,
                "sabadoLaborable" => $req->infoSabadoLabora,
                "formaPago" => $req->infoFormaPago,
                "fkEntidad" => $req->infoEntidadFinanciera,
                "numeroCuenta" => $req->infoNoCuenta,
                "tipoCuenta" => $req->infoTipoCuenta,
                "otraFormaPago" => $req->infoOtraFormaPago,
                "fkTipoOtroDocumento" => $req->infoOtroTIdentificacion, 
                "otroDocumento" => $req->infoOtroDocumento,
                "fkCargo" => $req->infoCargo,
                "procedimientoRetencion" => $req->infoProcedimientoRetencion, 
                "porcentajeRetencion" => $req->infoPorcentajeRetencion,
                "fkTipoCotizante" => $req->infoTipoCotizante,
                "esPensionado" => $req->infoSubTipoCotizante,
                "aplicaSubsidio" => $req->infoAplicaSubsidio,
                "fkEstado" => "3",
                "fkEmpleado" => $req->idEmpleado,
            ], "idPeriodo"
        );

        $affected = DB::table('empleado')
              ->where('idempleado', $req->idEmpleado)
              ->update($updateEmpleado);
              
        $modContrato = array(            
            "fechaFin" => $req->infoFechaFin, 
            "fkEstado" => "4", 
            "fkTipoContrato" => $req->infoTipoContrato, 
            "tipoDuracionContrato" => $req->infoTipoDuracionContrato, 
            "fkEmpleado" => $req->idEmpleado,
            "fkPeriodoActivo" => $idPeriodo
        );
        
        if(($req->fechaInicioActivoAnt == $req->fechaIngresoAnt) || !isset($req->fechaInicioActivoAnt) || empty($req->fechaInicioActivoAnt)){
            $modContrato["fechaInicio"] = $req->infoFechaIngreso;
        }

        if($req->infoTipoDuracionContrato == "MES"){ 
            $meses = $req->infoDuracionContrato;
            $dias = $req->infoDuracionContrato*30;

            $modContrato["numeroMeses"] = $meses;
            $modContrato["numeroDias"] = $dias;
        }
        else{
            $meses = $req->infoDuracionContrato / 30;
            $dias = $req->infoDuracionContrato;
            $modContrato["numeroMeses"] = $meses;
            $modContrato["numeroDias"] = $dias;
        }

        $affected = DB::table('contrato')
        ->insert($modContrato);
        
        
        DB::table('empleado_centrocosto')
        ->where("fkEmpleado","=",$req->idEmpleado)
        ->where("fkPeriodoActivo","=",$idPeriodo)
        ->delete();
        /*if($req->idEmpresaAnt != $req->infoEmpresa){
            
        }*/
        
        
        foreach ($req->infoCentroCosto as $key => $idCentroCosto) {

            $insertEmpleadoCentroCosto = array("fkEmpleado" => $req->idEmpleado, 
                    "fkCentroCosto" => $idCentroCosto,
                    "porcentajeTiempoTrabajado" => substr($req->infoPorcentaje[$key],0,-1),
                    "fkPeriodoActivo" => $idPeriodo
                
                );

            DB::table('empleado_centrocosto')->insert($insertEmpleadoCentroCosto);    
        }

        $arrBeneficiosNotIn = array();
        if(isset($req->infoTipoBeneficio)){
            foreach ($req->infoTipoBeneficio as $key => $tipoBeneficio) {
                $insertBeneficioTributario = array( "fkTipoBeneficio" => $tipoBeneficio, 
                                                    "valorMensual" => $req->infoValorMensual[$key],
                                                    "fechaVigencia" => $req->infoFechaVigencia[$key],
                                                    "numMeses" => $req->infoNumMeses[$key],
                                                    "valorTotal" => $req->infoValorTotal[$key],
                                                    "fkEmpleado" => $req->idEmpleado,
                                                    "fkPeriodoActivo" => $idPeriodo);
                if($tipoBeneficio == "4"){
                    $insertBeneficioTributario["fkNucleoFamiliar"] = $req->infoPersonaVive[$key];
                    
                    $arrNucleoFamiliar = [
                        "fkTipoIdentificacion" => $req->infoTIdentificacion[$key],
                        "numIdentificacion" => $req->infoNumIdentificacion[$key],
                    ];

                    DB::table('nucleofamiliar')
                    ->where('idNucleoFamiliar', $req->infoPersonaVive[$key])
                    ->update($arrNucleoFamiliar);
                }


                if($req->idBeneficioTributario[$key]=="-1"){
                    $idBeneficioTributario = DB::table('beneficiotributario')->insertGetId($insertBeneficioTributario, "idBeneficioTributario");
                    array_push($arrBeneficiosNotIn, $idBeneficioTributario);
                }
                else{
                    DB::table('beneficiotributario')
                    ->where('idBeneficioTributario', $req->idBeneficioTributario[$key])
                    ->update($insertBeneficioTributario);
                    array_push($arrBeneficiosNotIn, $req->idBeneficioTributario[$key]);
                }
                
            }
            
        }

        $this->validarEstadoEmpleado($req->idEmpleado, $idPeriodo);
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", modificó informacion laboral del empleado:".$req->idEmpleado);

        return response()->json([
            "success" => true,
            "idempleado" => $req->idEmpleado,
            "idPeriodo" => $idPeriodo
        ]);
    }

    public function cargarAfiliaciones($num){
        $tipoafilicaciones = DB::table("tipoafilicacion")->get();
        
        return view('/empleado.ajax.afiliacion', [
                'tipoafilicaciones'=>$tipoafilicaciones,
                'num' => $num
        ]);
    }
    public function cargarEntidadesAfiliacion($idTipoAfiliacion){
        $terceros = DB::table("tercero")
                    ->join('tipoafilicacion AS ta','ta.fkActividadEconomica', '=', 'tercero.fk_actividad_economica')
                    ->where("ta.idTipoAfiliacion", "=", $idTipoAfiliacion)->get();


		$opcionesAfiliacion = "<option value=''></option>";
		foreach($terceros as $tercero){
			$opcionesAfiliacion.= '<option value="'.$tercero->idTercero.'">'.$tercero->razonSocial.'</option>';
        }

		return response()->json([
			"success" => true,
            "opcionesAfiliacion" => $opcionesAfiliacion
        ]);
    }
    public function ingresarAfiliacionesEmpleado(Request $req){

        $updateEmpleado = array("fkNivelArl" => $req->afiliacionLvArl, "fkCentroTrabajo" => $req->afiliacionCentroTrabajo);
        DB::table('empleado')
        ->where('idempleado', $req->idEmpleado)
        ->update($updateEmpleado);

        
        DB::table('periodo')
        ->where('idPeriodo', $req->idPeriodo)
        ->update($updateEmpleado);


        if(isset($req->idsAfiliacionEliminar)){
            $idsElim = substr($req->idsAfiliacionEliminar, 0, strlen($req->idsAfiliacionEliminar) - 1);
            $arrIdsEl = explode(",",$idsElim);
            DB::table("afiliacion")->whereIn('idAfiliacion',$arrIdsEl)->delete();
        }
        
        $afiliaciones = DB::table("afiliacion")
        ->where('fkEmpleado', "=", $req->idEmpleado)
        ->where('fkPeriodoActivo', "=", $req->idPeriodo)
        ->get();


        $periodoActivo = DB::table("periodo")
        ->where('idPeriodo', "=", $req->idPeriodo)
        ->first();

        if(sizeof($afiliaciones)>0){
            foreach($req->afiliacionTipoAfilicacion as $key => $afiliacionTipoAfilicacion) {
           

                $insertAfiliacion = array(  "fkTipoAfilicacion" => $afiliacionTipoAfilicacion,
                                            "fkTercero" => $req->afiliacionEntidad[$key],
                                            "fechaAfiliacion" => $req->afiliacionFecha[$key],
                                            "fkEmpleado" => $req->idEmpleado,
                                            "fkPeriodoActivo" => $periodoActivo->idPeriodo);
                
                if($req->idAfiliacion[$key] == "-1"){
                    DB::table('afiliacion')->insert($insertAfiliacion);
                }
                else{
                    DB::table('afiliacion')
                    ->where('idAfiliacion', $req->idAfiliacion[$key])
                    ->update($insertAfiliacion);

                    if($req->afiliacionEntidadNueva[$key] != ""){
                        $arrCambioAfiliacion = array(
                            "fkTipoAfiliacionNueva" =>  $afiliacionTipoAfilicacion,
                            "fkTerceroNuevo" => $req->afiliacionEntidadNueva[$key],
                            "fkTerceroAnterior" => $req->afiliacionEntidad[$key],
                            "fechaCambio" => $req->afiliaFechaInicioCambio[$key],
                            "fkEmpleado" =>  $req->idEmpleado,
                            "fkAfiliacion" => $req->idAfiliacion[$key],
                            "fkPeriodoActivo" => $req->idPeriodo
                        );
                        
                        $afiliacionesCambio = DB::table("cambioafiliacion")
                        ->where("fkAfiliacion","=",$req->idAfiliacion[$key])
                        ->where("fkEstado","=","1")
                        ->get();
                        
                        if(sizeof($afiliacionesCambio)>0){
                            //Si la fecha de cambio es anterior al mes actual 
                            if(strtotime($req->afiliaFechaInicioCambio[$key]) < strtotime("today")){
                               
                                $affected = DB::table('afiliacion')
                                ->where('idAfiliacion', $req->idAfiliacion[$key])
                                ->update([
                                   "fkTercero" => $req->afiliacionEntidadNueva[$key],
                                   "fechaAfiliacion" => $req->afiliaFechaInicioCambio[$key]
                                ]);
                                
                            }
                            $arrCambioAfiliacion["fkEstado"] = "5";
                            DB::table("cambioafiliacion")
                            ->where("idCambioAfiliacion","=",$afiliacionesCambio[0]->idCambioAfiliacion)
                            ->update($arrCambioAfiliacion);
                        }
                        else{
                            if(strtotime($req->afiliaFechaInicioCambio[$key]) < strtotime("today")){
                                DB::table('afiliacion')
                                ->where('idAfiliacion', $req->idAfiliacion[$key])
                                ->update([
                                   "fkTercero" => $req->afiliacionEntidadNueva[$key],
                                   "fechaAfiliacion" => $req->afiliaFechaInicioCambio[$key]
                                ]);
                            }
                            $arrCambioAfiliacion["fkEstado"] = "5";
                            DB::table("cambioafiliacion")
                            ->insert($arrCambioAfiliacion);
                        }
                    }
                }                
            }
        }
        else{
            foreach($req->afiliacionTipoAfilicacion as $key => $afiliacionTipoAfilicacion) {     

                $insertAfiliacion = array("fkTipoAfilicacion" => $afiliacionTipoAfilicacion,
                                            "fkTercero" => $req->afiliacionEntidad[$key],
                                            "fechaAfiliacion" => $req->afiliacionFecha[$key],
                                            "fkEmpleado" => $req->idEmpleado,
                                            "fkPeriodoActivo" => $periodoActivo->idPeriodo);
    
                DB::table('afiliacion')->insert($insertAfiliacion);
            }
        }
        $this->validarEstadoEmpleado($req->idEmpleado, $req->idPeriodo);
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", modificó las afiliaciones del empleado:".$req->idEmpleado);
        return response()->json([
            "success" => true,
            "idempleado" => $req->idEmpleado,
            "idPeriodo" => $periodoActivo->idPeriodo
        ]);

    }

    public function cargarConceptosFijos($num){
        $conceptos = DB::table("concepto")->whereNotIn("idconcepto", [1,2])->orderBy("nombre")->get();
        return view('/empleado.ajax.conceptoFijo', [
            'conceptos' => $conceptos,
            'num' => $num
        ]);
    }

    public function validarConceptosFijos(Request $req){
        
        $diasxmes = DB::table('variable')->where("idVariable","=","4")->first();
        $horasxmes = DB::table('variable')->where("idVariable","=","6")->first();

        foreach($req->conFiConcepto as $key => $concepto) {

            if(($concepto == 1 || $concepto == 2) && $req->conFiPorcentaje[$key] != ""){
                $valorPor = ($req->conFiValor[$key]*100/$req->conFiPorcentaje[$key]);
            }
            else{
                $valorPor = $req->conFiValor[$key];
            }
                


            $condiciones = DB::table("condicion")->where("fkConcepto", "=", $concepto)->get();
            foreach($condiciones as $condicion){
                $itemsCondicion = DB::table("itemcondicion")->where("fkCondicion", "=", $condicion->idcondicion)->get();
                $arrCondicion = array();
                $posArr = 0;
               
                foreach($itemsCondicion as $itemCondicion){
                    if($itemCondicion->fkTipoCondicion == "1"){//Inicial
                        if(sizeof($arrCondicion)>0){
                            foreach($arrCondicion as $llave => $arrItemCond){                                
                                if(isset($arrItemCond['inicio']) && isset($arrItemCond['final1'])){
                                    if($arrItemCond["fkOperadorComparacion"]=="1"){
                                        if($arrItemCond['inicio'] > $arrItemCond['final1']){
                                            $arrCondicion[$llave]["valido"] = true;
                                        }
                                    }
                                    else if($arrItemCond["fkOperadorComparacion"]=="2"){
                                        if($arrItemCond['inicio'] < $arrItemCond['final1']){
                                            $arrCondicion[$llave]["valido"] = true;
                                        }
                                    }
                                    else if($arrItemCond["fkOperadorComparacion"]=="3"){
                                        if($arrItemCond['inicio'] == $arrItemCond['final1']){
                                            $arrCondicion[$llave]["valido"] = true;
                                        }
                                    }
                                    else if($arrItemCond["fkOperadorComparacion"]=="4"){
                                        if($arrItemCond['inicio'] >= $arrItemCond['final1']){
                                            $arrCondicion[$llave]["valido"] = true;
                                        }
                                    }
                                    else if($arrItemCond["fkOperadorComparacion"]=="5"){
                                        if($arrItemCond['inicio'] <= $arrItemCond['final1']){
                                            $arrCondicion[$llave]["valido"] = true;
                                        }
                                    }
                                    else if($arrItemCond["fkOperadorComparacion"]=="6"){
                                        if($arrItemCond['inicio'] != $arrItemCond['final1']){
                                            $arrCondicion[$llave]["valido"] = true;
                                        }
                                    }
                                }
                                if(isset($arrItemCond['inicio']) && isset($arrItemCond['final1']) && isset($arrItemCond['final2'])){
                                    if($arrItemCond["fkOperadorComparacion"]=="7"){
                                        if($arrItemCond['inicio'] >= $arrItemCond['final1'] && $arrItemCond['inicio'] <= $arrItemCond['final2']){
                                            $arrCondicion[$llave]["valido"] = true;
                                        }
                                    }
                                    else if($arrItemCond["fkOperadorComparacion"]=="8"){
                                        if($arrItemCond['inicio'] < $arrItemCond['final1'] && $arrItemCond['inicio'] > $arrItemCond['final2']){
                                            $arrCondicion[$llave]["valido"] = true;
                                        }
                                    }
                                }

                                if($arrItemCond["tipoCondicion"] == "or" && isset($arrCondicion[$llave]["valido"]) && $arrCondicion[$llave]["valido"] == true){
                                    
                                    return response()->json([
                                        "success" => false,
                                        "respuesta" => $condicion->mensajeMostrar
                                    ]);
                                }


                            }


                            $cuentaValidos = 0;
                            foreach($arrCondicion as $arrItemCond){
                                if(isset($arrCondicion["valido"]) &&  $arrItemCond["valido"] == true){
                                    $cuentaValidos++;
                                }
                            }
                            if(sizeof($arrCondicion) == $cuentaValidos && sizeof($arrCondicion)!=0){
                                return response()->json([
                                    "success" => false,
                                    "respuesta" => $condicion->mensajeMostrar
                                ]);
                            }
                        }
                        array_push($arrCondicion, array("tipoCondicion" => "Inicial"));
                        $posArr=0;
                    }
                    else if($itemCondicion->fkTipoCondicion == "2"){//and
                        array_push($arrCondicion, array("tipoCondicion" => "and"));
                        $posArr++;
                    }
                    else if($itemCondicion->fkTipoCondicion == "3"){//or
                        array_push($arrCondicion, array("tipoCondicion" => "or"));
                        $posArr++;
                    }










                    $multiplicadorInicial = 1;
                    if(isset($itemCondicion->multiplicadorInicial)){
                        $multiplicadorInicial = $itemCondicion->multiplicadorInicial;
                    }
                    $arrCondicionActual = $arrCondicion[$posArr];

                    if(isset($itemCondicion->fkConceptoInicial)){
                        if($itemCondicion->fkConceptoInicial == $concepto){
                            
                            $arrCondicionActual["inicio"]= intval($valorPor)*$multiplicadorInicial;
                        }
                        else{
                            $conceptoCalculo = DB::table("conceptofijo")->where("fkEmpleado","=",$req->idEmpleado)
                                                     ->where("fkEstado","=", "1")
                                                     ->where("fkConcepto","=", $itemCondicion->fkConceptoInicial)->first();
                            if(isset($conceptoCalculo->valor)){                                
                                $arrCondicionActual["inicio"]= intval($conceptoCalculo->valor)*$multiplicadorInicial;
                            }
                            else{
                                $arrCondicionActual["inicio"]=0;
                            }
                        }
                    }
                    else if(isset($itemCondicion->fkGrupoConceptoInicial)){
                        $grupoConceptoCalculo = DB::table("conceptofijo")->select(DB::raw('SUM(conceptofijo.valor) as totalValor'))
                        ->join("grupoconcepto_concepto as gcc", "gcc.fkConcepto","=","conceptofijo.fkConcepto")
                        ->where("conceptofijo.fkEmpleado","=", $req->idEmpleado)
                        ->where("conceptofijo.fkEstado","=","1")
                        ->where("gcc.fkGrupoConcepto", "=", $itemCondicion->fkGrupoConceptoInicial)                       
                        ->first();

                        $arrCondicionActual["inicio"]= intval($grupoConceptoCalculo->totalValor)*$multiplicadorInicial;
                        
                        $conceptosNoInculidos = DB::table("grupoconcepto_concepto","gcc")
                            ->join("conceptofijo as c","gcc.fkConcepto","=","c.fkConcepto","LEFT")
                            ->where("c.fkEmpleado","=", $req->idEmpleado)
                            ->where("c.fkEstado","=","1")
                            ->where("gcc.fkGrupoConcepto", "=", $itemCondicion->fkGrupoConceptoInicial)    
                            ->whereNull("c.fkConcepto")->get();
                        foreach($conceptosNoInculidos as $conceptoNoInculido){
                            if($conceptoNoInculido->fkConcepto == $concepto){
                                $arrCondicionActual["inicio"] = $arrCondicionActual["inicio"] + (intval($valorPor)*$multiplicadorInicial);
                            }
                        }
                    }
                    $arrCondicionActual["fkOperadorComparacion"] = $itemCondicion->fkOperadorComparacion;
                    $multiplicador1 = 1;
                    if(isset($itemCondicion->multiplicador1)){
                        $multiplicador1 = $itemCondicion->multiplicador1;
                    }

                    if(isset($itemCondicion->fkConceptoFinal1)){
                        if($itemCondicion->fkConceptoFinal1 == $concepto){
                            
                            $arrCondicionActual["final1"]= intval($valorPor)*$multiplicador1;
                        }
                        else{
                            $conceptoCalculo = DB::table("conceptofijo")->where("fkEmpleado","=",$req->idEmpleado)
                                                     ->where("fkEstado","=", "1")
                                                     ->where("fkConcepto","=", $itemCondicion->fkConceptoFinal1)->first();
                            if(isset($conceptoCalculo->valor)){                                
                                $arrCondicionActual["final1"]= intval($conceptoCalculo->valor)*$multiplicador1;
                            }
                            else{
                                $arrCondicionActual["final1"]=0;
                            }
                        }
                    }
                    else if(isset($itemCondicion->fkGrupoConceptoFinal1)){
                        $grupoConceptoCalculo = DB::table("conceptofijo")->select(DB::raw('SUM(conceptofijo.valor) as totalValor'))
                        ->join("grupoconcepto_concepto as gcc", "gcc.fkConcepto","=","conceptofijo.fkConcepto")
                        ->where("conceptofijo.fkEmpleado","=", $req->idEmpleado)
                        ->where("conceptofijo.fkEstado","=","1")
                        ->where("gcc.fkGrupoConcepto", "=", $itemCondicion->fkGrupoConceptoFinal1)                       
                        ->first();

                        $arrCondicionActual["final1"]= intval($grupoConceptoCalculo->totalValor)*$multiplicador1;
                        
                        $conceptosNoInculidos = DB::table("grupoconcepto_concepto","gcc")
                            ->join("conceptofijo as c","gcc.fkConcepto","=","c.fkConcepto","LEFT")
                            ->where("c.fkEmpleado","=", $req->idEmpleado)
                            ->where("c.fkEstado","=","1")
                            ->where("gcc.fkGrupoConcepto", "=", $itemCondicion->fkGrupoConceptoFinal1)           
                            ->whereNull("c.fkConcepto")->get();
                        foreach($conceptosNoInculidos as $conceptoNoInculido){
                            if($conceptoNoInculido->fkConcepto == $concepto){
                                $arrCondicionActual["final1"] = $arrCondicionActual["final1"] + (intval($valorPor)*$multiplicador1);
                            }
                        }
                    }
                    else if(isset($itemCondicion->fkVariableFinal1)){
                        $variableFinal1 = DB::table('variable')->where("idVariable","=",$itemCondicion->fkVariableFinal1)->first();
                        $arrCondicionActual["final1"] = intval($variableFinal1->valor)*$multiplicador1;
                    }
                    else if(isset($itemCondicion->valorCampo1)){
                        $arrCondicionActual["final1"] = intval($itemCondicion->valorCampo1)*$multiplicador1;
                    }
                    else{
                        $arrCondicionActual["final1"] = 0;
                    }
                    







                    $multiplicador2 = 1;
                    if(isset($itemCondicion->multiplicador2)){
                        $multiplicador2 = $itemCondicion->multiplicador2;
                    }

                    if(isset($itemCondicion->fkConceptoFinal2)){
                        if($itemCondicion->fkConceptoFinal2 == $concepto){
                            
                            $arrCondicionActual["final2"]= intval($valorPor)*$multiplicador2;
                        }
                        else{
                            $conceptoCalculo = DB::table("conceptofijo")->where("fkEmpleado","=",$req->idEmpleado)
                                                     ->where("fkEstado","=", "1")
                                                     ->where("fkConcepto","=", $itemCondicion->fkConceptoFinal2)->first();
                            if(isset($conceptoCalculo->valor)){                                
                                $arrCondicionActual["final2"]= intval($conceptoCalculo->valor)*$multiplicador2;
                            }
                            else{
                                $arrCondicionActual["final2"]=0;
                            }
                        }
                    }
                    else if(isset($itemCondicion->fkGrupoConceptoFinal2)){
                        $grupoConceptoCalculo = DB::table("conceptofijo")->select(DB::raw('SUM(conceptofijo.valor) as totalValor'))
                        ->join("grupoconcepto_concepto as gcc", "gcc.fkConcepto","=","conceptofijo.fkConcepto")
                        ->where("conceptofijo.fkEmpleado","=", $req->idEmpleado)
                        ->where("conceptofijo.fkEstado","=","1")
                        ->where("gcc.fkGrupoConcepto", "=", $itemCondicion->fkGrupoConceptoFinal2)                       
                        ->first();

                        $arrCondicionActual["final2"]= intval($grupoConceptoCalculo->totalValor)*$multiplicador2;
                        
                        $conceptosNoInculidos = DB::table("grupoconcepto_concepto","gcc")
                            ->join("conceptofijo as c","gcc.fkConcepto","=","c.fkConcepto","LEFT")
                            ->where("c.fkEmpleado","=", $req->idEmpleado)
                            ->where("c.fkEstado","=","1")
                            ->where("gcc.fkGrupoConcepto", "=", $itemCondicion->fkGrupoConceptoFinal2)           
                            ->whereNull("c.fkConcepto")->get();
                        foreach($conceptosNoInculidos as $conceptoNoInculido){
                            if($conceptoNoInculido->fkConcepto == $concepto){
                                $arrCondicionActual["final2"] = $arrCondicionActual["final2"] + (intval($valorPor)*$multiplicador2);
                            }
                        }
                    }
                    else if(isset($itemCondicion->fkVariableFinal2)){
                        $variableFinal2 = DB::table('variable')->where("idVariable","=",$itemCondicion->fkVariableFinal2)->first();
                        $arrCondicionActual["final2"] = intval($variableFinal2->valor)*$multiplicador2;
                    }
                    else if(isset($itemCondicion->valorCampo2)){
                        $arrCondicionActual["final2"] = intval($itemCondicion->valorCampo2)*$multiplicador2;
                    }
                    else{
                        $arrCondicionActual["final2"] = 0;
                    }


                    $arrCondicion[$posArr] = $arrCondicionActual;


                }
                
                foreach($arrCondicion as $llave => $arrItemCond){                                
                    if(isset($arrItemCond['inicio']) && isset($arrItemCond['final1'])){
                        if($arrItemCond["fkOperadorComparacion"]=="1"){
                            if($arrItemCond['inicio'] > $arrItemCond['final1']){
                                $arrCondicion[$llave]["valido"] = true;
                            }
                        }
                        else if($arrItemCond["fkOperadorComparacion"]=="2"){
                            if($arrItemCond['inicio'] < $arrItemCond['final1']){
                                $arrCondicion[$llave]["valido"] = true;
                            }
                        }
                        else if($arrItemCond["fkOperadorComparacion"]=="3"){
                            if($arrItemCond['inicio'] == $arrItemCond['final1']){
                                $arrCondicion[$llave]["valido"] = true;
                            }
                        }
                        else if($arrItemCond["fkOperadorComparacion"]=="4"){
                            
                            if($arrItemCond['inicio'] >= $arrItemCond['final1']){
                                $arrCondicion[$llave]["valido"] = true;
                               
                            }
                        }
                        else if($arrItemCond["fkOperadorComparacion"]=="5"){
                            if($arrItemCond['inicio'] <= $arrItemCond['final1']){
                                $arrCondicion[$llave]["valido"] = true;
                            }
                        }
                        else if($arrItemCond["fkOperadorComparacion"]=="6"){
                            if($arrItemCond['inicio'] != $arrItemCond['final1']){
                                $arrCondicion[$llave]["valido"] = true;
                            }
                        }
                    }
                    if(isset($arrItemCond['inicio']) && isset($arrItemCond['final1']) && isset($arrItemCond['final2'])){
                        if($arrItemCond["fkOperadorComparacion"]=="7"){
                            if($arrItemCond['inicio'] >= $arrItemCond['final1'] && $arrItemCond['inicio'] <= $arrItemCond['final2']){
                                $arrCondicion[$llave]["valido"] = true;
                            }
                        }
                        else if($arrItemCond["fkOperadorComparacion"]=="8"){
                            if($arrItemCond['inicio'] < $arrItemCond['final1'] && $arrItemCond['inicio'] > $arrItemCond['final2']){
                                $arrCondicion[$llave]["valido"] = true;
                            }
                        }
                    }

                    if($arrItemCond["tipoCondicion"] == "or" && isset($arrCondicion[$llave]["valido"]) && $arrCondicion[$llave]["valido"] == true){
                        
                        return response()->json([
                            "success" => false,
                            "respuesta" => $condicion->mensajeMostrar,
                            "idcondicion" => $condicion->idcondicion,
                            "tipoRestriccion" => $condicion->fkTipoResultado
                        ]);
                    }
                }
                $cuentaValidos = 0;
                
                foreach($arrCondicion as $arrItemCond){
                    
                    if(isset($arrItemCond["valido"]) &&  $arrItemCond["valido"] == true){
                        $cuentaValidos++;
                    }
                }
                
                if(sizeof($arrCondicion) == $cuentaValidos && sizeof($arrCondicion)!=0){
                    if(!isset($req->pasarAlerta)){
                        return response()->json([
                            "success" => false,                            
                            "respuesta" => $condicion->mensajeMostrar,
                            "idcondicion" => $condicion->idcondicion,
                            "tipoRestriccion" => $condicion->fkTipoResultado
                        ]);
                    }                    
                    else{
                        $pasarAlertas = explode(",",$req->pasarAlerta);
                        if(!in_array($condicion->idcondicion, $pasarAlertas)){
                            return response()->json([
                                "success" => false,
                                "idcondicion" => $condicion->idcondicion,
                                "respuesta" => $condicion->mensajeMostrar,
                                "tipoRestriccion" => $condicion->fkTipoResultado
                            ]);
                        }
                    }
                }                
            }
        }

        DB::table('conceptofijo')
        ->where("fkEmpleado","=", $req->idEmpleado)
        ->where("fkPeriodoActivo","=", $req->idPeriodo)
        ->delete();        
        
        foreach($req->conFiConcepto as $key => $concepto) {          
            $insertConceptoFijo = array(
                "unidad" => $req->conFiUnidad[$key],
                "valor" => $req->conFiValor[$key],
                "porcentaje" => $req->conFiPorcentaje[$key],
                "fechaInicio" => $req->conFiFechaInicio[$key],
                "fechaFin" => $req->conFiFechaFin[$key],
                "fkEmpleado" => $req->idEmpleado,
                "fkPeriodoActivo" => $req->idPeriodo,
                "fkEstado" => 1,
                "fkConcepto" => $concepto                
            );
            DB::table('conceptofijo')->insert($insertConceptoFijo);
        }

        if(isset($req->conFiFechaInicioCambio) and !empty($req->conFiFechaInicioCambio) and isset($req->conValorCambio) and !empty($req->conValorCambio)){
            $arrCambioSalario = array(
                "fechaCambio" => $req->conFiFechaInicioCambio,
                "fkEmpleado" => $req->idEmpleado,
                "fkPeriodoActivo" => $req->idPeriodo,
                "valorNuevo" => $req->conValorCambio,
                "valorAnterior" => $req->conFiValor[0]
            );
            $cambioSalario = DB::table("cambiosalario","cs")
            ->where("cs.fkEmpleado","=",$req->idEmpleado)
            ->where("cs.fkPeriodoActivo","=",$req->idPeriodo)
            ->where("cs.fkEstado","=","4")
            ->whereRaw("MONTH(cs.fechaCambio) = MONTH(".$req->conFiFechaInicioCambio.")")->get();

            $idCambioSalario = 0;
            if(sizeof($cambioSalario)>0){
                $idCambioSalario = $cambioSalario[0]->idCambioSalario;
                DB::table("cambiosalario")
                ->where("idCambioSalario", "=",$cambioSalario[0]->idCambioSalario)
                ->update($arrCambioSalario);
            }
            else{
                
                $idCambioSalario = DB::table("cambiosalario")
                ->insertGetId($arrCambioSalario,"idCambioSalario");
            }

            //OBTENER LA ULTIMA LQ DE TIPO NORMAL QUE ESTE APROBADA 
      
            
           
            $liquidacion = DB::table("liquidacionnomina","ln")
            ->join("boucherpago as bp","bp.fkLiquidacion", "=", "ln.idLiquidacionNomina")
            ->where("bp.fkEmpleado", "=",$req->idEmpleado)
            ->where("ln.fkEstado","=","5")
            ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9"])
            ->where("bp.fkPeriodoActivo","=",$req->idPeriodo)
            ->orderBy("ln.fechaLiquida","desc")
            ->first();

            
            if(isset($liquidacion)){
                
                if(strtotime($req->conFiFechaInicioCambio) <= strtotime($liquidacion->fechaLiquida)){
                    //Es anterior a la ultima liquidacion toca hacer retroactivo y activar el concepto
                    
                    $retroActivo = 0;
                    $liquidacionesMesesAnterioresPrima = DB::table("liquidacionnomina", "ln")
                    ->selectRaw("sum(bp.periodoPago) as periodPago")
                    ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                    ->where("bp.fkEmpleado","=",$req->idEmpleado)
                    ->where("bp.fkPeriodoActivo","=",$req->idPeriodo)
                    ->where("ln.fechaInicio","<=",$liquidacion->fechaInicio)
                    ->where("ln.fechaInicio",">=",$req->conFiFechaInicioCambio)
                    ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9"])
                    ->first();
                    if(isset($liquidacionesMesesAnterioresPrima)){
                        $valorDiaSalAnt = ($req->conFiValor[0] / 30);
                        $valorDiaSalAct = (intval($req->conValorCambio) / 30);

                        $valorAcumAnt = ($valorDiaSalAnt * floatval($liquidacionesMesesAnterioresPrima->periodPago));
                        $valorAcumAct = ($valorDiaSalAct * floatval($liquidacionesMesesAnterioresPrima->periodPago));

                        $retroActivo = $valorAcumAct - $valorAcumAnt;

                        $idOtraNovedad = DB::table('otra_novedad')->insertGetId([
                            "valor" => $retroActivo,
                            "sumaResta" => "1"
                        ], "idOtraNovedad");
            
            
                        $periodoActivoReintegro = DB::table("periodo")
                        ->where("fkEstado","=","1")
                        ->where("idPeriodo", "=", $req->idPeriodo)->first();
                        
               

                        $arrInsertNovedad = array(
                            "fkTipoNovedad" => "7", 
                            "fkPeriodoActivo" => $req->idPeriodo,
                            "fkNomina" => $periodoActivoReintegro->fkNomina,
                            "fechaRegistro" => $liquidacion->fechaProximaInicio,
                            "fkOtros" => $idOtraNovedad,
                            "fkEmpleado" => $req->idEmpleado,
                            "fkConcepto" => "49"
                        );
                        DB::table('novedad')->insert($arrInsertNovedad);
                    }


                    $conceptoSalario = DB::table("conceptofijo", "cf")
                    ->whereIn("cf.fkConcepto",["1","2"])
                    ->where("cf.fkEmpleado", "=", $req->idEmpleado)
                    ->where("cf.fkPeriodoActivo", "=", $req->idPeriodo)
                    ->first();
                    $updateConceptoFijo = array(
                        "valor"=> $req->conValorCambio,
                        "fechaInicio"=> $req->conFiFechaInicioCambio,
                        "fkEstado" => 1
                    );

                    DB::table('cambiosalario')
                    ->where("idCambioSalario","=",$idCambioSalario)
                    ->update(array("fkEstado" => "5"));

                    DB::table('conceptofijo')
                    ->where("idConceptoFijo","=",$conceptoSalario->idConceptoFijo)
                    ->update($updateConceptoFijo);


                }
                else{
                    
                    $conceptoSalario = DB::table("conceptofijo", "cf")
                    ->whereIn("cf.fkConcepto",["1","2"])
                    ->where("cf.fkEmpleado", "=", $req->idEmpleado)
                    ->where("cf.fkPeriodoActivo", "=", $req->idPeriodo)
                    ->first();
                    $updateConceptoFijo = array(
                        "valor"=> $req->conValorCambio,
                        "fechaInicio"=> $req->conFiFechaInicioCambio,
                        "fkEstado" => 1,                
                    );



                    DB::table('cambiosalario')
                    ->where("idCambioSalario","=",$idCambioSalario)
                    ->update(array("fkEstado" => "5"));

                    DB::table('conceptofijo')
                    ->where("idConceptoFijo","=",$conceptoSalario->idConceptoFijo)
                    ->update($updateConceptoFijo);
                
                }
                
            }
            else{
                
                $conceptoSalario = DB::table("conceptofijo", "cf")
                ->whereIn("cf.fkConcepto",["1","2"])
                ->where("cf.fkEmpleado", "=", $req->idEmpleado)
                ->where("cf.fkPeriodoActivo", "=", $req->idPeriodo)
                ->first();
                $updateConceptoFijo = array(
                    "valor"=> $req->conValorCambio,
                    "fechaInicio"=> $req->conFiFechaInicioCambio,
                    "fkEstado" => 1,                
                );



                DB::table('cambiosalario')
                ->where("idCambioSalario","=",$idCambioSalario)
                ->update(array("fkEstado" => "5"));

                DB::table('conceptofijo')
                ->where("idConceptoFijo","=",$conceptoSalario->idConceptoFijo)
                ->update($updateConceptoFijo);
            
            }
                
            
        }
        
        $this->validarEstadoEmpleado($req->idEmpleado, $req->idPeriodo);
        return response()->json([
            "success" => true
        ]);
    }

    public function validarConceptosFijosReintegro(Request $req){
        
        $diasxmes = DB::table('variable')->where("idVariable","=","4")->first();
        $horasxmes = DB::table('variable')->where("idVariable","=","6")->first();


        foreach($req->conFiConcepto as $key => $concepto) {
            $condiciones = DB::table("condicion")->where("fkConcepto", "=", $concepto)->get();
            foreach($condiciones as $condicion){
                $itemsCondicion = DB::table("itemcondicion")->where("fkCondicion", "=", $condicion->idcondicion)->get();
                $arrCondicion = array();
                $posArr = 0;
               
                foreach($itemsCondicion as $itemCondicion){
                    if($itemCondicion->fkTipoCondicion == "1"){//Inicial
                        if(sizeof($arrCondicion)>0){
                            foreach($arrCondicion as $llave => $arrItemCond){                                
                                if(isset($arrItemCond['inicio']) && isset($arrItemCond['final1'])){
                                    if($arrItemCond["fkOperadorComparacion"]=="1"){
                                        if($arrItemCond['inicio'] > $arrItemCond['final1']){
                                            $arrCondicion[$llave]["valido"] = true;
                                        }
                                    }
                                    else if($arrItemCond["fkOperadorComparacion"]=="2"){
                                        if($arrItemCond['inicio'] < $arrItemCond['final1']){
                                            $arrCondicion[$llave]["valido"] = true;
                                        }
                                    }
                                    else if($arrItemCond["fkOperadorComparacion"]=="3"){
                                        if($arrItemCond['inicio'] == $arrItemCond['final1']){
                                            $arrCondicion[$llave]["valido"] = true;
                                        }
                                    }
                                    else if($arrItemCond["fkOperadorComparacion"]=="4"){
                                        if($arrItemCond['inicio'] >= $arrItemCond['final1']){
                                            $arrCondicion[$llave]["valido"] = true;
                                        }
                                    }
                                    else if($arrItemCond["fkOperadorComparacion"]=="5"){
                                        if($arrItemCond['inicio'] <= $arrItemCond['final1']){
                                            $arrCondicion[$llave]["valido"] = true;
                                        }
                                    }
                                    else if($arrItemCond["fkOperadorComparacion"]=="6"){
                                        if($arrItemCond['inicio'] != $arrItemCond['final1']){
                                            $arrCondicion[$llave]["valido"] = true;
                                        }
                                    }
                                }
                                if(isset($arrItemCond['inicio']) && isset($arrItemCond['final1']) && isset($arrItemCond['final2'])){
                                    if($arrItemCond["fkOperadorComparacion"]=="7"){
                                        if($arrItemCond['inicio'] >= $arrItemCond['final1'] && $arrItemCond['inicio'] <= $arrItemCond['final2']){
                                            $arrCondicion[$llave]["valido"] = true;
                                        }
                                    }
                                    else if($arrItemCond["fkOperadorComparacion"]=="8"){
                                        if($arrItemCond['inicio'] < $arrItemCond['final1'] && $arrItemCond['inicio'] > $arrItemCond['final2']){
                                            $arrCondicion[$llave]["valido"] = true;
                                        }
                                    }
                                }

                                if($arrItemCond["tipoCondicion"] == "or" && isset($arrCondicion[$llave]["valido"]) && $arrCondicion[$llave]["valido"] == true){
                                    
                                    return response()->json([
                                        "success" => false,
                                        "respuesta" => $condicion->mensajeMostrar
                                    ]);
                                }


                            }


                            $cuentaValidos = 0;
                            foreach($arrCondicion as $arrItemCond){
                                if(isset($arrCondicion["valido"]) &&  $arrItemCond["valido"] == true){
                                    $cuentaValidos++;
                                }
                            }
                            if(sizeof($arrCondicion) == $cuentaValidos && sizeof($arrCondicion)!=0){
                                return response()->json([
                                    "success" => false,
                                    "respuesta" => $condicion->mensajeMostrar
                                ]);
                            }
                        }
                        array_push($arrCondicion, array("tipoCondicion" => "Inicial"));
                        $posArr=0;
                    }
                    else if($itemCondicion->fkTipoCondicion == "2"){//and
                        array_push($arrCondicion, array("tipoCondicion" => "and"));
                        $posArr++;
                    }
                    else if($itemCondicion->fkTipoCondicion == "3"){//or
                        array_push($arrCondicion, array("tipoCondicion" => "or"));
                        $posArr++;
                    }










                    $multiplicadorInicial = 1;
                    if(isset($itemCondicion->multiplicadorInicial)){
                        $multiplicadorInicial = $itemCondicion->multiplicadorInicial;
                    }
                    $arrCondicionActual = $arrCondicion[$posArr];

                    if(isset($itemCondicion->fkConceptoInicial)){
                        if($itemCondicion->fkConceptoInicial == $concepto){
                            
                            $arrCondicionActual["inicio"]= intval($req->conFiValor[$key])*$multiplicadorInicial;
                        }
                        else{
                            $conceptoCalculo = DB::table("conceptofijo")->where("fkEmpleado","=",$req->idEmpleado)
                                                     ->where("fkEstado","=", "1")
                                                     ->where("fkConcepto","=", $itemCondicion->fkConceptoInicial)->first();
                            if(isset($conceptoCalculo->valor)){                                
                                $arrCondicionActual["inicio"]= intval($conceptoCalculo->valor)*$multiplicadorInicial;
                            }
                            else{
                                $arrCondicionActual["inicio"]=0;
                            }
                        }
                    }
                    else if(isset($itemCondicion->fkGrupoConceptoInicial)){
                        $grupoConceptoCalculo = DB::table("conceptofijo")->select(DB::raw('SUM(conceptofijo.valor) as totalValor'))
                        ->join("grupoconcepto_concepto as gcc", "gcc.fkConcepto","=","conceptofijo.fkConcepto")
                        ->where("conceptofijo.fkEmpleado","=", $req->idEmpleado)
                        ->where("conceptofijo.fkEstado","=","1")
                        ->where("gcc.fkGrupoConcepto", "=", $itemCondicion->fkGrupoConceptoInicial)                       
                        ->first();

                        $arrCondicionActual["inicio"]= intval($grupoConceptoCalculo->totalValor)*$multiplicadorInicial;
                        
                        $conceptosNoInculidos = DB::table("grupoconcepto_concepto","gcc")
                            ->join("conceptofijo as c","gcc.fkConcepto","=","c.fkConcepto","LEFT")
                            ->where("c.fkEmpleado","=", $req->idEmpleado)
                            ->where("c.fkEstado","=","1")
                            ->where("gcc.fkGrupoConcepto", "=", $itemCondicion->fkGrupoConceptoInicial)    
                            ->whereNull("c.fkConcepto")->get();
                        foreach($conceptosNoInculidos as $conceptoNoInculido){
                            if($conceptoNoInculido->fkConcepto == $concepto){
                                $arrCondicionActual["inicio"] = $arrCondicionActual["inicio"] + (intval($req->conFiValor[$key])*$multiplicadorInicial);
                            }
                        }
                    }
                    $arrCondicionActual["fkOperadorComparacion"] = $itemCondicion->fkOperadorComparacion;
                    $multiplicador1 = 1;
                    if(isset($itemCondicion->multiplicador1)){
                        $multiplicador1 = $itemCondicion->multiplicador1;
                    }

                    if(isset($itemCondicion->fkConceptoFinal1)){
                        if($itemCondicion->fkConceptoFinal1 == $concepto){
                            
                            $arrCondicionActual["final1"]= intval($req->conFiValor[$key])*$multiplicador1;
                        }
                        else{
                            $conceptoCalculo = DB::table("conceptofijo")->where("fkEmpleado","=",$req->idEmpleado)
                                                     ->where("fkEstado","=", "1")
                                                     ->where("fkConcepto","=", $itemCondicion->fkConceptoFinal1)->first();
                            if(isset($conceptoCalculo->valor)){                                
                                $arrCondicionActual["final1"]= intval($conceptoCalculo->valor)*$multiplicador1;
                            }
                            else{
                                $arrCondicionActual["final1"]=0;
                            }
                        }
                    }
                    else if(isset($itemCondicion->fkGrupoConceptoFinal1)){
                        $grupoConceptoCalculo = DB::table("conceptofijo")->select(DB::raw('SUM(conceptofijo.valor) as totalValor'))
                        ->join("grupoconcepto_concepto as gcc", "gcc.fkConcepto","=","conceptofijo.fkConcepto")
                        ->where("conceptofijo.fkEmpleado","=", $req->idEmpleado)
                        ->where("conceptofijo.fkEstado","=","1")
                        ->where("gcc.fkGrupoConcepto", "=", $itemCondicion->fkGrupoConceptoFinal1)                       
                        ->first();

                        $arrCondicionActual["final1"]= intval($grupoConceptoCalculo->totalValor)*$multiplicador1;
                        
                        $conceptosNoInculidos = DB::table("grupoconcepto_concepto","gcc")
                            ->join("conceptofijo as c","gcc.fkConcepto","=","c.fkConcepto","LEFT")
                            ->where("c.fkEmpleado","=", $req->idEmpleado)
                            ->where("c.fkEstado","=","1")
                            ->where("gcc.fkGrupoConcepto", "=", $itemCondicion->fkGrupoConceptoFinal1)           
                            ->whereNull("c.fkConcepto")->get();
                        foreach($conceptosNoInculidos as $conceptoNoInculido){
                            if($conceptoNoInculido->fkConcepto == $concepto){
                                $arrCondicionActual["final1"] = $arrCondicionActual["final1"] + (intval($req->conFiValor[$key])*$multiplicador1);
                            }
                        }
                    }
                    else if(isset($itemCondicion->fkVariableFinal1)){
                        $variableFinal1 = DB::table('variable')->where("idVariable","=",$itemCondicion->fkVariableFinal1)->first();
                        $arrCondicionActual["final1"] = intval($variableFinal1->valor)*$multiplicador1;
                    }
                    else if(isset($itemCondicion->valorCampo1)){
                        $arrCondicionActual["final1"] = intval($itemCondicion->valorCampo1)*$multiplicador1;
                    }
                    else{
                        $arrCondicionActual["final1"] = 0;
                    }
                    







                    $multiplicador2 = 1;
                    if(isset($itemCondicion->multiplicador2)){
                        $multiplicador2 = $itemCondicion->multiplicador2;
                    }

                    if(isset($itemCondicion->fkConceptoFinal2)){
                        if($itemCondicion->fkConceptoFinal2 == $concepto){
                            
                            $arrCondicionActual["final2"]= intval($req->conFiValor[$key])*$multiplicador2;
                        }
                        else{
                            $conceptoCalculo = DB::table("conceptofijo")->where("fkEmpleado","=",$req->idEmpleado)
                                                     ->where("fkEstado","=", "1")
                                                     ->where("fkConcepto","=", $itemCondicion->fkConceptoFinal2)->first();
                            if(isset($conceptoCalculo->valor)){                                
                                $arrCondicionActual["final2"]= intval($conceptoCalculo->valor)*$multiplicador2;
                            }
                            else{
                                $arrCondicionActual["final2"]=0;
                            }
                        }
                    }
                    else if(isset($itemCondicion->fkGrupoConceptoFinal2)){
                        $grupoConceptoCalculo = DB::table("conceptofijo")->select(DB::raw('SUM(conceptofijo.valor) as totalValor'))
                        ->join("grupoconcepto_concepto as gcc", "gcc.fkConcepto","=","conceptofijo.fkConcepto")
                        ->where("conceptofijo.fkEmpleado","=", $req->idEmpleado)
                        ->where("conceptofijo.fkEstado","=","1")
                        ->where("gcc.fkGrupoConcepto", "=", $itemCondicion->fkGrupoConceptoFinal2)                       
                        ->first();

                        $arrCondicionActual["final2"]= intval($grupoConceptoCalculo->totalValor)*$multiplicador2;
                        
                        $conceptosNoInculidos = DB::table("grupoconcepto_concepto","gcc")
                            ->join("conceptofijo as c","gcc.fkConcepto","=","c.fkConcepto","LEFT")
                            ->where("c.fkEmpleado","=", $req->idEmpleado)
                            ->where("c.fkEstado","=","1")
                            ->where("gcc.fkGrupoConcepto", "=", $itemCondicion->fkGrupoConceptoFinal2)           
                            ->whereNull("c.fkConcepto")->get();
                        foreach($conceptosNoInculidos as $conceptoNoInculido){
                            if($conceptoNoInculido->fkConcepto == $concepto){
                                $arrCondicionActual["final2"] = $arrCondicionActual["final2"] + (intval($req->conFiValor[$key])*$multiplicador2);
                            }
                        }
                    }
                    else if(isset($itemCondicion->fkVariableFinal2)){
                        $variableFinal2 = DB::table('variable')->where("idVariable","=",$itemCondicion->fkVariableFinal2)->first();
                        $arrCondicionActual["final2"] = intval($variableFinal2->valor)*$multiplicador2;
                    }
                    else if(isset($itemCondicion->valorCampo2)){
                        $arrCondicionActual["final2"] = intval($itemCondicion->valorCampo2)*$multiplicador2;
                    }
                    else{
                        $arrCondicionActual["final2"] = 0;
                    }


                    $arrCondicion[$posArr] = $arrCondicionActual;


                }
                
                foreach($arrCondicion as $llave => $arrItemCond){                                
                    if(isset($arrItemCond['inicio']) && isset($arrItemCond['final1'])){
                        if($arrItemCond["fkOperadorComparacion"]=="1"){
                            if($arrItemCond['inicio'] > $arrItemCond['final1']){
                                $arrCondicion[$llave]["valido"] = true;
                            }
                        }
                        else if($arrItemCond["fkOperadorComparacion"]=="2"){
                            if($arrItemCond['inicio'] < $arrItemCond['final1']){
                                $arrCondicion[$llave]["valido"] = true;
                            }
                        }
                        else if($arrItemCond["fkOperadorComparacion"]=="3"){
                            if($arrItemCond['inicio'] == $arrItemCond['final1']){
                                $arrCondicion[$llave]["valido"] = true;
                            }
                        }
                        else if($arrItemCond["fkOperadorComparacion"]=="4"){
                            
                            if($arrItemCond['inicio'] >= $arrItemCond['final1']){
                                $arrCondicion[$llave]["valido"] = true;
                               
                            }
                        }
                        else if($arrItemCond["fkOperadorComparacion"]=="5"){
                            if($arrItemCond['inicio'] <= $arrItemCond['final1']){
                                $arrCondicion[$llave]["valido"] = true;
                            }
                        }
                        else if($arrItemCond["fkOperadorComparacion"]=="6"){
                            if($arrItemCond['inicio'] != $arrItemCond['final1']){
                                $arrCondicion[$llave]["valido"] = true;
                            }
                        }
                    }
                    if(isset($arrItemCond['inicio']) && isset($arrItemCond['final1']) && isset($arrItemCond['final2'])){
                        if($arrItemCond["fkOperadorComparacion"]=="7"){
                            if($arrItemCond['inicio'] >= $arrItemCond['final1'] && $arrItemCond['inicio'] <= $arrItemCond['final2']){
                                $arrCondicion[$llave]["valido"] = true;
                            }
                        }
                        else if($arrItemCond["fkOperadorComparacion"]=="8"){
                            if($arrItemCond['inicio'] < $arrItemCond['final1'] && $arrItemCond['inicio'] > $arrItemCond['final2']){
                                $arrCondicion[$llave]["valido"] = true;
                            }
                        }
                    }

                    if($arrItemCond["tipoCondicion"] == "or" && isset($arrCondicion[$llave]["valido"]) && $arrCondicion[$llave]["valido"] == true){
                        
                        return response()->json([
                            "success" => false,
                            "respuesta" => $condicion->mensajeMostrar,
                            "idcondicion" => $condicion->idcondicion,
                            "tipoRestriccion" => $condicion->fkTipoResultado
                        ]);
                    }
                }
                $cuentaValidos = 0;
                
                foreach($arrCondicion as $arrItemCond){
                    
                    if(isset($arrItemCond["valido"]) &&  $arrItemCond["valido"] == true){
                        $cuentaValidos++;
                    }
                }
                
                if(sizeof($arrCondicion) == $cuentaValidos && sizeof($arrCondicion)!=0){
                    if(!isset($req->pasarAlerta)){
                        return response()->json([
                            "success" => false,                            
                            "respuesta" => $condicion->mensajeMostrar,
                            "idcondicion" => $condicion->idcondicion,
                            "tipoRestriccion" => $condicion->fkTipoResultado
                        ]);
                    }                    
                    else{
                        $pasarAlertas = explode(",",$req->pasarAlerta);
                        if(!in_array($condicion->idcondicion, $pasarAlertas)){
                            return response()->json([
                                "success" => false,
                                "idcondicion" => $condicion->idcondicion,
                                "respuesta" => $condicion->mensajeMostrar,
                                "tipoRestriccion" => $condicion->fkTipoResultado
                            ]);
                        }
                    }
                }                
            }
        }

        DB::table('conceptofijo')
        ->where("fkEmpleado","=", $req->idEmpleado)
        ->where("fkPeriodoActivo","=", $req->idPeriodo)
        ->delete();

        
        
        foreach($req->conFiConcepto as $key => $concepto) {            
            $insertConceptoFijo = array(
                "unidad" => $req->conFiUnidad[$key],
                "valor" => $req->conFiValor[$key],
                "porcentaje" => $req->conFiPorcentaje[$key],
                "fechaInicio" => $req->conFiFechaInicio[$key],
                "fechaFin" => $req->conFiFechaFin[$key],
                "fkEmpleado" => $req->idEmpleado,
                "fkEstado" => 1,
                "fkConcepto" => $concepto                
            );
            DB::table('conceptofijo')->insert($insertConceptoFijo);
        }
        
        if(isset($req->conFiFechaInicioCambio) and !empty($req->conFiFechaInicioCambio) and isset($req->conValorCambio) and !empty($req->conValorCambio)){
            $arrCambioSalario = array(
                "fechaCambio" => $req->conFiFechaInicioCambio,
                "fkEmpleado" => $req->idEmpleado,
                "valorNuevo" => $req->conValorCambio,
                "valorAnterior" => $req->conFiValor[0]
            );
            $cambioSalario = DB::table("cambiosalario","cs")
            ->where("cs.fkEmpleado","=",$req->idEmpleado)
            ->where("cs.fkEstado","=","4")
            ->whereRaw("MONTH(cs.fechaCambio) = MONTH(".$req->conFiFechaInicioCambio.")")->get();

            $idCambioSalario = 0;
            if(sizeof($cambioSalario)>0){
                $idCambioSalario = $cambioSalario[0]->idCambioSalario;
                DB::table("cambiosalario")
                ->where("idCambioSalario", "=",$cambioSalario[0]->idCambioSalario)
                ->update($arrCambioSalario);
            }
            else{
                
                $idCambioSalario = DB::table("cambiosalario")
                ->insertGetId($arrCambioSalario,"idCambioSalario");
            }


            if(strtotime($req->conFiFechaInicioCambio) <= strtotime("now")){

                

                $conceptoSalario = DB::table("conceptofijo", "cf")
                ->whereIn("cf.fkConcepto",["1","2"])
                ->where("cf.fkEmpleado", "=", $req->idEmpleado)
                ->first();
                
                $updateConceptoFijo = array(
                    "valor"=> $req->conValorCambio,
                    "fechaInicio"=> $req->conFiFechaInicioCambio,
                    "fkEstado" => 1,                
                );



                DB::table('cambiosalario')
                ->where("idCambioSalario","=",$idCambioSalario)
                ->update(array("fkEstado" => "5"));

                DB::table('conceptofijo')
                ->where("idConceptoFijo","=",$conceptoSalario->idConceptoFijo)
                ->update($updateConceptoFijo);
            }
            

        }
        
        $periodoActivo = DB::table("periodo")
        ->where("fkEmpleado","=",$req->idEmpleado)
        ->where("idPeriodo","=", $req->idPeriodo)
        ->where("fkEstado","=","1")->first();
        
        if(isset($periodoActivo)){
            DB::table("empleado")->where("idempleado" ,"=", $req->idEmpleado)
            ->update(["fkEstado" => "3"]);
        }
        
       
        
        




        $this->validarEstadoEmpleado($req->idEmpleado, $req->idPeriodo);
        return response()->json([
            "success" => true
        ]);
        

    }
    public function validarEstadoEmpleado($idEmpleado, $idPeriodo){
        //Consultar que tenga todos los datos basicos

        /*DB::table("periodo")->where("idPeriodo","=",$idPeriodo)
        ->where("fkEstado","<>","2")
        ->update(["fkEstado" => "3"]);

        DB::table("empleado")->where("idempleado","=",$idEmpleado)
        ->where("fkEstado","<>","2")
        ->update(["fkEstado" => "3"]);*/

        $camposOpcionalesDatPer = array(
            "fijos" => ["foto","segundoApellido","segundoNombre", "tallaCamisa", "tallaPantalon", 
                        "tallaZapatos", "otros", "tallaOtros", "correo2", "telefonoFijo", "libretaMilitar", 
                        "distritoMilitar","fkNivelEstudio","fkEtnia","correo", "celular", "barrio"],
            "cambiantes" => []            
        );
        $datosPersonales = DB::table("datospersonales", "dp")->select('dp.*')
                                        ->join('empleado','empleado.fkDatosPersonales', '=', 'dp.idDatosPersonales')
                                        ->where('empleado.idempleado', $idEmpleado)
                                        ->first();
        foreach($datosPersonales as $key => $valor){
            if(!isset($valor)){
                if(!in_array($key, $camposOpcionalesDatPer["fijos"])){
                    $valid = false;
                    foreach($camposOpcionalesDatPer["cambiantes"] as $valorCambiante){
                        if(in_array($key, $valorCambiante["camposQueSonOb"])){                           
                            foreach($datosPersonales as $key2 => $valor2){
                                if($valorCambiante["campoCambia"]["campo"] == $key2 && $valorCambiante["campoCambia"]["valor"]==$valor2 && !$valid){
                                    $valid = true;
                                }
                            }                            
                        }
                    }
                    if(!$valid){
                        //echo $key."<br>";
                        return false;
                    }
                }    
            }
        }
        
        $infoLaboral = DB::table("periodo", "p")->where('p.idPeriodo', $idPeriodo)->first();
        $empleado = DB::table("empleado", "e")->where("e.idempleado", "=",$idEmpleado)->first();
        $empleado->fkEmpresa = ($infoLaboral->fkEmpresa ?? $empleado->fkEmpresa);
        $empleado->fkNomina =($infoLaboral->fkNomina ?? $empleado->fkNomina);
        $empleado->fechaIngreso =($infoLaboral->fechaInicio ?? $empleado->fechaIngreso);
        
        $empleado->fkCargo =($infoLaboral->fkCargo ?? $empleado->fkCargo);
        $empleado->fkTipoCotizante =($infoLaboral->fkTipoCotizante ?? $empleado->fkTipoCotizante);
        $empleado->esPensionado =($infoLaboral->esPensionado ?? $empleado->esPensionado);
        $empleado->tipoRegimen =($infoLaboral->tipoRegimen ?? $empleado->tipoRegimen);
        $empleado->tipoRegimenPensional =($infoLaboral->tipoRegimenPensional ?? $empleado->tipoRegimenPensional);
        $empleado->fkUbicacionLabora =($infoLaboral->fkUbicacionLabora ?? $empleado->fkUbicacionLabora);
        $empleado->fkLocalidad =($infoLaboral->fkLocalidad ?? $empleado->fkLocalidad);
        $empleado->sabadoLaborable =($infoLaboral->sabadoLaborable ?? $empleado->sabadoLaborable);
        $empleado->formaPago =($infoLaboral->formaPago ?? $empleado->formaPago);
        $empleado->fkEntidad =($infoLaboral->fkEntidad ?? $empleado->fkEntidad);
        $empleado->numeroCuenta =($infoLaboral->numeroCuenta ?? $empleado->numeroCuenta);
        $empleado->tipoCuenta =($infoLaboral->tipoCuenta ?? $empleado->tipoCuenta);
        $empleado->otraFormaPago =($infoLaboral->otraFormaPago ?? $empleado->otraFormaPago);
        $empleado->fkTipoOtroDocumento =($infoLaboral->fkTipoOtroDocumento ?? $empleado->fkTipoOtroDocumento);
        $empleado->otroDocumento =($infoLaboral->otroDocumento ?? $empleado->otroDocumento);
        $empleado->procedimientoRetencion =($infoLaboral->procedimientoRetencion ?? $empleado->procedimientoRetencion);
        $empleado->porcentajeRetencion =($infoLaboral->porcentajeRetencion ?? $empleado->porcentajeRetencion);
        $empleado->fkNivelArl =($infoLaboral->fkNivelArl ?? $empleado->fkNivelArl);
        $empleado->fkCentroTrabajo =($infoLaboral->fkCentroTrabajo ?? $empleado->fkCentroTrabajo);
        $empleado->aplicaSubsidio =($infoLaboral->aplicaSubsidio ?? $empleado->aplicaSubsidio);


        $camposOpcionalesInfoLab = array(
            "fijos" => ["tipoRegimenPensional", "porcentajeRetencion","esPensionado",
                        "otroDocumento","fkCentroTrabajo","fkTipoOtroDocumento", 
                        "fkUsuario", "fkLocalidad", "fechaFin", "salario", "fkTipoContrato"],
            "cambiantes" => [
            array(
                "campoCambia"=> array(
                    "campo" => "formaPago",
                    "valor" => "Efectivo"
                ),
                "camposQueSonOb" => array("fkEntidad", "numeroCuenta", "tipoCuenta", "otraFormaPago")
            ),
            array(
                "campoCambia"=> array(
                    "campo" => "formaPago",
                    "valor" => "Cheque"
                ),
                "camposQueSonOb" => array("fkEntidad", "numeroCuenta", "tipoCuenta", "otraFormaPago")
            ),
            array(
                "campoCambia"=> array(
                    "campo" => "formaPago",
                    "valor" => "Transferencia"
                ),
                "camposQueSonOb" => array("otraFormaPago")
            )
            ,
            array(
                "campoCambia"=> array(
                    "campo" => "formaPago",
                    "valor" => "Otra forma pago"
                ),
                "camposQueSonOb" => array("fkEntidad", "numeroCuenta", "tipoCuenta")
            )            
            ]            
        );
        if($empleado->fkTipoCotizante == 12){
            array_push($camposOpcionalesInfoLab["fijos"], "fkNivelArl");
        }
        
        foreach($infoLaboral as $key => $valor){
            if(!isset($valor)){                
                if(!in_array($key, $camposOpcionalesInfoLab["fijos"])){
                    $valid = false;
                    foreach($camposOpcionalesInfoLab["cambiantes"] as $valorCambiante){
                        if(in_array($key, $valorCambiante["camposQueSonOb"])){           
                            foreach($infoLaboral as $key2 => $valor2){
                                if($valorCambiante["campoCambia"]["campo"] == $key2 && $valorCambiante["campoCambia"]["valor"]==$valor2 && !$valid){
                                    $valid = true;
                                }
                            }                            
                        }
                    }
                    if(!$valid){
                        return false;
                    }
                }
            }
        }

        $contrato = DB::table("contrato", "c")
            ->whereIn('c.fkEstado', ['4','1'])
            ->where('c.fkEmpleado', $idEmpleado)
            ->where('c.fkPeriodoActivo', $idPeriodo)
            ->first();
        
        $camposOpcionalesContrato = array(
            "fijos" => [],
            "cambiantes" => [
                array(
                    "campoCambia"=> array(
                        "campo" => "fkTipoContrato",
                        "valor" => "2"
                    ),
                    "camposQueSonOb" => array("fechaFin", "tipoDuracionContrato", "numeroMeses", "numeroDias")
                ),
                array(
                    "campoCambia"=> array(
                        "campo" => "fkTipoContrato",
                        "valor" => "4"
                    ),
                    "camposQueSonOb" => array("fechaFin", "tipoDuracionContrato", "numeroMeses", "numeroDias")
                )
            ]            
        );
        if(!isset($contrato)){
            //echo "Todo el contrato <br>";
            return false;
        }
        else{
            foreach($contrato as $key => $valor){
                if(!isset($valor)){                
                    if(!in_array($key, $camposOpcionalesContrato["fijos"])){
                        $valid = false;
                        foreach($camposOpcionalesContrato["cambiantes"] as $valorCambiante){
                            if(in_array($key, $valorCambiante["camposQueSonOb"])){                           
                                foreach($contrato as $key2 => $valor2){
                                    if($valorCambiante["campoCambia"]["campo"] == $key2 && $valorCambiante["campoCambia"]["valor"]==$valor2 && !$valid){
                                        $valid = true;
                                    }
                                }                            
                            }
                        }
                        if(!$valid){
                            //echo $key."<br>";
                            return false;
                        }
                    }    
                }
            }
        }
        $periodoActivo = DB::table("periodo")
        ->where('idPeriodo', $idPeriodo)
        ->first();

        if(!isset($periodoActivo)){
            return false;
        }

        $afiliaciones = DB::table("afiliacion")
        ->where('fkEmpleado', $idEmpleado)
        ->where("fkPeriodoActivo","=",$periodoActivo->idPeriodo)
        ->get();
        $arrTipoAfiliacion = array("3");
        if($empleado->fkTipoCotizante != 23){
            $arrTipoAfiliacion = [];
        }        
        
       
        if($empleado->fkTipoCotizante == 1){
            
            array_push($arrTipoAfiliacion, "1");
            array_push($arrTipoAfiliacion, "2");
            if($periodoActivo->esPensionado == 0){
                array_push($arrTipoAfiliacion, "4");
            }
        }



        $camposOpcionalesAfiliaciones = array(
            "fijos" => ["documento"],
            "cambiantes" => []            
        );
        
        foreach($afiliaciones as $afiliacion){
            if(in_array($afiliacion->fkTipoAfilicacion, $arrTipoAfiliacion)){
                unset($arrTipoAfiliacion[array_search($afiliacion->fkTipoAfilicacion, $arrTipoAfiliacion)]);
            }
            foreach($afiliacion as $key => $valor){
                if(!isset($valor)){                
                    if(!in_array($key, $camposOpcionalesAfiliaciones["fijos"])){
                        $valid = false;
                        foreach($camposOpcionalesAfiliaciones["cambiantes"] as $valorCambiante){
                            if(in_array($key, $valorCambiante["camposQueSonOb"])){                           
                                foreach($afiliacion as $key2 => $valor2){
                                    if($valorCambiante["campoCambia"]["campo"] == $key2 && $valorCambiante["campoCambia"]["valor"]==$valor2 && !$valid){
                                        $valid = true;
                                    }
                                }                            
                            }
                        }
                        if(!$valid){
                            //echo $key."<br>";
                            return false;
                        }
                    }    
                }
            }
        }
        if(sizeof($arrTipoAfiliacion) > 0){
            //echo "Falta algun tipo de afiliacion ".sizeof($arrTipoAfiliacion)." <br>";
            return false;
        }


        $conceptofijo = DB::table("conceptofijo")
        ->where('fkEmpleado', $idEmpleado)
        ->where('fkPeriodoActivo', $idPeriodo)
        ->get();
        $camposOpcionalesAfiliaciones = array(
            "fijos" => ["fechaFin", "porcentaje"],
            "cambiantes" => []            
        );
        if(sizeof($conceptofijo) == 0){
            //echo "Faltan conceptos fijos <br>";
            return false;
        }
        foreach($conceptofijo as $conceptof){
            foreach($conceptof as $key => $valor){
                if(!isset($valor)){                
                    if(!in_array($key, $camposOpcionalesAfiliaciones["fijos"])){
                        $valid = false;
                        foreach($camposOpcionalesAfiliaciones["cambiantes"] as $valorCambiante){
                            if(in_array($key, $valorCambiante["camposQueSonOb"])){                           
                                foreach($conceptof as $key2 => $valor2){
                                    if($valorCambiante["campoCambia"]["campo"] == $key2 && $valorCambiante["campoCambia"]["valor"]==$valor2 && !$valid){
                                        $valid = true;
                                    }
                                }                            
                            }
                        }
                        if(!$valid){
                            //echo $key."<br>";
                            return false;
                        }
                    }    
                }
            }
        }
        
        $updateContrato = array(
            "fkEstado" => "1"
        );

        $contrato = DB::table("contrato")
            ->whereDate('fechaInicio', '<=', date('Y-m-d'))
            ->where('fkEstado',"=",'4')
            ->where('fkEmpleado', $idEmpleado)
            ->where('fkPeriodoActivo', $idPeriodo)
            ->update($updateContrato);

        $conceptofijo = DB::table("conceptofijo")
            ->whereDate('fechaInicio', '<=', date('Y-m-d'))
            ->where('fkEstado',"=",'4')
            ->where('fkEmpleado', $idEmpleado)
            ->where('fkPeriodoActivo', $idPeriodo)
            ->update($updateContrato);
            



      
        $updateEstado = array(
            "fkEstado" => "1"
        );

        DB::table('empleado')
        ->where('idempleado', $idEmpleado)
        ->where("fkEstado","=","3")
        ->update($updateEstado);

        DB::table('periodo')
        ->where('idPeriodo', $idPeriodo)
        ->where("fkEstado","=","3")
        ->update($updateEstado);       

        return true;
    }


    public function mostrarPorqueFalla($idEmpleado, $idPeriodo){
        //Consultar que tenga todos los datos basicos
        $camposOpcionalesDatPer = array(
            "fijos" => ["foto","segundoApellido","segundoNombre", "tallaCamisa", "tallaPantalon", "tallaZapatos", "otros", "tallaOtros", "correo2", "telefonoFijo", "libretaMilitar", "distritoMilitar","fkNivelEstudio","fkEtnia","correo", "celular", "barrio"],
            "cambiantes" => []            
        );
        $datosPersonales = DB::table("datospersonales", "dp")->select('dp.*')
                                        ->join('empleado','empleado.fkDatosPersonales', '=', 'dp.idDatosPersonales')
                                        ->where('empleado.idempleado', $idEmpleado)
                                        ->first();
        foreach($datosPersonales as $key => $valor){
            if(!isset($valor)){
                if(!in_array($key, $camposOpcionalesDatPer["fijos"])){
                    $valid = false;
                    foreach($camposOpcionalesDatPer["cambiantes"] as $valorCambiante){
                        if(in_array($key, $valorCambiante["camposQueSonOb"])){                           
                            foreach($datosPersonales as $key2 => $valor2){
                                if($valorCambiante["campoCambia"]["campo"] == $key2 && $valorCambiante["campoCambia"]["valor"]==$valor2 && !$valid){
                                    $valid = true;
                                }
                            }                            
                        }
                    }
                    if(!$valid){
                        echo $key."<br>";
                    }
                }    
            }
        }
        
        $infoLaboral = DB::table("periodo", "p")->where('p.idPeriodo', $idPeriodo)->first();

        $camposOpcionalesInfoLab = array(
            "fijos" => ["tipoRegimenPensional",
                        "porcentajeRetencion",
                        "esPensionado",
                        "otroDocumento",
                        "fkCentroTrabajo",
                        "fkTipoOtroDocumento",
                        "fkUsuario", 
                        "fkLocalidad",
                        "fechaFin",
                        "salario",
                        "fkTipoContrato"],
            "cambiantes" => [
            array(
                "campoCambia"=> array(
                    "campo" => "formaPago",
                    "valor" => "Efectivo"
                ),
                "camposQueSonOb" => array("fkEntidad", "numeroCuenta", "tipoCuenta", "otraFormaPago")
            ),
            array(
                "campoCambia"=> array(
                    "campo" => "formaPago",
                    "valor" => "Cheque"
                ),
                "camposQueSonOb" => array("fkEntidad", "numeroCuenta", "tipoCuenta", "otraFormaPago")
            ),
            array(
                "campoCambia"=> array(
                    "campo" => "formaPago",
                    "valor" => "Transferencia"
                ),
                "camposQueSonOb" => array("otraFormaPago")
            )
            ,
            array(
                "campoCambia"=> array(
                    "campo" => "formaPago",
                    "valor" => "Otra forma pago"
                ),
                "camposQueSonOb" => array("fkEntidad", "numeroCuenta", "tipoCuenta")
            )            
            ]            
        );
        $empleado = DB::table("empleado", "e")->where("idempleado", "=",$idEmpleado)->first();
        $empleado->fkEmpresa = ($infoLaboral->fkEmpresa ?? $empleado->fkEmpresa);
        $empleado->fkNomina =($infoLaboral->fkNomina ?? $empleado->fkNomina);
        $empleado->fechaIngreso =($infoLaboral->fechaInicio ?? $empleado->fechaIngreso);
        
        $empleado->fkCargo =($infoLaboral->fkCargo ?? $empleado->fkCargo);
        $empleado->fkTipoCotizante =($infoLaboral->fkTipoCotizante ?? $empleado->fkTipoCotizante);
        $empleado->esPensionado =($infoLaboral->esPensionado ?? $empleado->esPensionado);
        $empleado->tipoRegimen =($infoLaboral->tipoRegimen ?? $empleado->tipoRegimen);
        $empleado->tipoRegimenPensional =($infoLaboral->tipoRegimenPensional ?? $empleado->tipoRegimenPensional);
        $empleado->fkUbicacionLabora =($infoLaboral->fkUbicacionLabora ?? $empleado->fkUbicacionLabora);
        $empleado->fkLocalidad =($infoLaboral->fkLocalidad ?? $empleado->fkLocalidad);
        $empleado->sabadoLaborable =($infoLaboral->sabadoLaborable ?? $empleado->sabadoLaborable);
        $empleado->formaPago =($infoLaboral->formaPago ?? $empleado->formaPago);
        $empleado->fkEntidad =($infoLaboral->fkEntidad ?? $empleado->fkEntidad);
        $empleado->numeroCuenta =($infoLaboral->numeroCuenta ?? $empleado->numeroCuenta);
        $empleado->tipoCuenta =($infoLaboral->tipoCuenta ?? $empleado->tipoCuenta);
        $empleado->otraFormaPago =($infoLaboral->otraFormaPago ?? $empleado->otraFormaPago);
        $empleado->fkTipoOtroDocumento =($infoLaboral->fkTipoOtroDocumento ?? $empleado->fkTipoOtroDocumento);
        $empleado->otroDocumento =($infoLaboral->otroDocumento ?? $empleado->otroDocumento);
        $empleado->procedimientoRetencion =($infoLaboral->procedimientoRetencion ?? $empleado->procedimientoRetencion);
        $empleado->porcentajeRetencion =($infoLaboral->porcentajeRetencion ?? $empleado->porcentajeRetencion);
        $empleado->fkNivelArl =($infoLaboral->fkNivelArl ?? $empleado->fkNivelArl);
        $empleado->fkCentroTrabajo =($infoLaboral->fkCentroTrabajo ?? $empleado->fkCentroTrabajo);
        $empleado->aplicaSubsidio =($infoLaboral->aplicaSubsidio ?? $empleado->aplicaSubsidio);

        if($empleado->fkTipoCotizante == 12){
            array_push($camposOpcionalesInfoLab["fijos"], "fkNivelArl");
        }
        
        foreach($infoLaboral as $key => $valor){
            if(!isset($valor)){
                
                if(!in_array($key, $camposOpcionalesInfoLab["fijos"])){
                    $valid = false;
                    foreach($camposOpcionalesInfoLab["cambiantes"] as $valorCambiante){
                        if(in_array($key, $valorCambiante["camposQueSonOb"])){           
                            foreach($infoLaboral as $key2 => $valor2){
                                if($valorCambiante["campoCambia"]["campo"] == $key2 && $valorCambiante["campoCambia"]["valor"]==$valor2 && !$valid){
                                    $valid = true;
                                }
                            }                            
                        }
                    }
                    if(!$valid){
                        echo $key."<br>";
                    }
                }    
            }
        }

        $contrato = DB::table("contrato", "c")
            ->whereIn('c.fkEstado', ['4','1'])
            ->where('c.fkEmpleado', $idEmpleado)
            ->where('c.fkPeriodoActivo', $idPeriodo)
            ->first();
        
        $camposOpcionalesContrato = array(
            "fijos" => [],
            "cambiantes" => [
                array(
                    "campoCambia"=> array(
                        "campo" => "fkTipoContrato",
                        "valor" => "2"
                    ),
                    "camposQueSonOb" => array("fechaFin", "tipoDuracionContrato", "numeroMeses", "numeroDias")
                )               
                ,
                array(
                    "campoCambia"=> array(
                        "campo" => "fkTipoContrato",
                        "valor" => "4"
                    ),
                    "camposQueSonOb" => array("fechaFin", "tipoDuracionContrato", "numeroMeses", "numeroDias")
                )
            ]            
        );
        if(!isset($contrato)){
            echo "Todo el contrato <br>";
        
        }
        else{
            foreach($contrato as $key => $valor){
                if(!isset($valor)){                
                    if(!in_array($key, $camposOpcionalesContrato["fijos"])){
                        $valid = false;
                        foreach($camposOpcionalesContrato["cambiantes"] as $valorCambiante){
                            if(in_array($key, $valorCambiante["camposQueSonOb"])){                           
                                foreach($contrato as $key2 => $valor2){
                                    if($valorCambiante["campoCambia"]["campo"] == $key2 && $valorCambiante["campoCambia"]["valor"]==$valor2 && !$valid){
                                        $valid = true;
                                    }
                                }                            
                            }
                        }
                        if(!$valid){
                            echo $key."<br>";
                           
                        }
                    }    
                }
            }
        }
        

        

      
        $periodoActivo = DB::table("periodo")
        ->where('idPeriodo', $idPeriodo)
        ->first();
        
        $afiliaciones = DB::table("afiliacion")
        ->where('fkEmpleado', $idEmpleado)
        ->where("fkPeriodoActivo","=",$idPeriodo)
        ->get();

        $arrTipoAfiliacion = array("3");
        if($empleado->fkTipoCotizante != 23){
            $arrTipoAfiliacion = [];
        }       

        
        
        if($empleado->fkTipoCotizante == 1){
            
            array_push($arrTipoAfiliacion, "1");
            array_push($arrTipoAfiliacion, "2");
            if($periodoActivo->esPensionado == 0){
                array_push($arrTipoAfiliacion, "4");
            }
        }

        
        

        $camposOpcionalesAfiliaciones = array(
            "fijos" => ["documento"],
            "cambiantes" => []            
        );
        
        foreach($afiliaciones as $afiliacion){
            if(in_array($afiliacion->fkTipoAfilicacion, $arrTipoAfiliacion)){
                unset($arrTipoAfiliacion[array_search($afiliacion->fkTipoAfilicacion, $arrTipoAfiliacion)]);
            }
            foreach($afiliacion as $key => $valor){
                if(!isset($valor)){                
                    if(!in_array($key, $camposOpcionalesAfiliaciones["fijos"])){
                        $valid = false;
                        foreach($camposOpcionalesAfiliaciones["cambiantes"] as $valorCambiante){
                            if(in_array($key, $valorCambiante["camposQueSonOb"])){                           
                                foreach($afiliacion as $key2 => $valor2){
                                    if($valorCambiante["campoCambia"]["campo"] == $key2 && $valorCambiante["campoCambia"]["valor"]==$valor2 && !$valid){
                                        $valid = true;
                                    }
                                }                            
                            }
                        }
                        if(!$valid){
                            echo $key."<br>";
                           
                        }
                    }    
                }
            }
        }
        if(sizeof($arrTipoAfiliacion) > 0){
            echo "Falta algun tipo de afiliacion ".sizeof($arrTipoAfiliacion)." <br>";
        }

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingreso a la opcion de mostrar los campos faltantes del empleado:".$idEmpleado);


        $conceptofijo = DB::table("conceptofijo")
        ->where('fkEmpleado', $idEmpleado)
        ->where('fkPeriodoActivo', $idPeriodo)
        ->get();
        $camposOpcionalesAfiliaciones = array(
            "fijos" => ["fechaFin", "porcentaje"],
            "cambiantes" => []            
        );
        if(sizeof($conceptofijo) == 0){
            echo "Faltan conceptos fijos <br>";
        }
        foreach($conceptofijo as $conceptof){
            foreach($conceptof as $key => $valor){
                if(!isset($valor)){                
                    if(!in_array($key, $camposOpcionalesAfiliaciones["fijos"])){
                        $valid = false;
                        foreach($camposOpcionalesAfiliaciones["cambiantes"] as $valorCambiante){
                            if(in_array($key, $valorCambiante["camposQueSonOb"])){                           
                                foreach($conceptof as $key2 => $valor2){
                                    if($valorCambiante["campoCambia"]["campo"] == $key2 && $valorCambiante["campoCambia"]["valor"]==$valor2 && !$valid){
                                        $valid = true;
                                    }
                                }                            
                            }
                        }
                        if(!$valid){
                            echo $key."<br>";
                        }
                    }    
                }
            }
        }
        
    }
    
    public function cargarFormEmpleadosxNomina(Request $req){

        $empleados = DB::table('empleado', 'e')
        ->select(["est.nombre as estado", "e.idempleado", "dp.primerNombre", "dp.segundoNombre", "dp.primerApellido", 
                   "dp.segundoApellido", "dp.numeroIdentificacion","t.nombre", "p.idPeriodo"])
        ->join("datospersonales AS dp", "e.fkDatosPersonales", "=" , "dp.idDatosPersonales")
        ->join("tipoidentificacion AS t", "dp.fkTipoIdentificacion", "=" , "t.idtipoIdentificacion")
        ->join("periodo as p","p.fkEmpleado", "=","e.idempleado")
        ->join("nomina as n","n.idNomina", "=","p.fkNomina")
        ->join("estado as est", "est.idEstado", "=","p.fkEstado");
        if(isset($req->idNomina) && !empty($req->idNomina)){
            $empleados = $empleados->where("p.fkNomina","=", $req->idNomina);
        }
        if(isset($req->idEmpresa) && !empty($req->idEmpresa)){
            $empleados = $empleados->where("n.fkEmpresa","=", $req->idEmpresa);
        }        
        $empleados = $empleados->whereIn("p.fkEstado",["1","2"]);//Estado Activo 
        
        $arrConsulta = ['idNomina' => $req->idNomina];
        
        if(isset($req->nombre)){
            $empleados->where(function($query) use($req){
                $query->where("dp.primerNombre","LIKE","%".$req->nombre."%")
                ->orWhere("dp.segundoNombre","LIKE","%".$req->nombre."%")
                ->orWhere("dp.primerApellido","LIKE","%".$req->nombre."%")
                ->orWhere("dp.segundoApellido","LIKE","%".$req->nombre."%");
            });
            $arrConsulta["nombre"] = $req->nombre;
        }
        if(isset($req->numDoc)){
            $empleados->where("dp.numeroIdentificacion", "LIKE", $req->numDoc."%");
            $arrConsulta["numDoc"] = $req->numDoc;
        }
        if(isset($req->tipoPersona)){
            $empleados->where("e.tipoPersona", "=", $req->tipoPersona);
            $arrConsulta["tipoPersona"] = $req->tipoPersona;
        }
        if(isset($req->ciudad)){
            $empleados->where("e.fkUbicacionLabora", "=", $req->ciudad);
            $arrConsulta["ciudad"] = $req->ciudad;
        }

        if(isset($req->centroCosto)){
            $empleados->join("empleado_centrocosto AS ec", "ec.fkEmpleado","=","e.idempleado");
            $empleados->where("ec.fkCentroCosto","=",$req->centroCosto);
            $arrConsulta["centroCosto"] = $req->centroCosto;
        }
        
        $empleados = $empleados->paginate(15);
        $centrosDeCosto = DB::table("centrocosto")->orderBy("nombre")->get();
        $ciudades = DB::table("ubicacion")->where("fkTipoUbicacion","=","3")->get();
        

        

        return view('empleado.ajax.listaEmpleados', ['empleados' => $empleados, 'centrosDeCosto' => $centrosDeCosto,  'arrConsulta' => $arrConsulta, "req" => $req, "ciudades" => $ciudades]);

    }

    public function cargarEmpleadosMasivaIndex(){
        $cargaEmpleados = DB::table("carga_empleado","ce")
        ->join("estado as e", "e.idEstado", "=", "ce.fkEstado")
        ->orderBy("idCargaEmpleado", "desc")
        ->get();
        $usu = UsuarioController::dataAdminLogueado();
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingreso al menu de carga de empleados masivo");
        return view('empleado.cargarEmpleadosMasiva', ["cargaEmpleados" => $cargaEmpleados, "dataUsu" => $usu] );
    }
    public function cargaEmpleados($idCargaEmpleado){
        $cargaEmpleado = DB::table("carga_empleado","ce")
        ->join("estado as e", "e.idEstado", "=", "ce.fkEstado")
        ->where("ce.idCargaEmpleado","=",$idCargaEmpleado)
        ->first();
        
        $cargaEmpleado_Empleados = DB::table("carga_empleado_empleado", "cee")
        ->select('ti.nombre as tipoDocumento', "dp.*", "est.nombre as estado", "cee.fkEstado", "cee.linea","cee.adicional")
        ->join("empleado as e","e.idempleado", "=", "cee.fkEmpleado", "left")
        ->join("estado as est", "est.idEstado", "=", "cee.fkEstado", "left")
        ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales", "left")
        ->join("tipoidentificacion AS ti","ti.idtipoIdentificacion", "=","dp.fkTipoIdentificacion", "left")
        ->where("fkCargaEmpleado","=",$idCargaEmpleado)
        ->orderBy("est.idEstado","desc")
        ->get();

        $usu = UsuarioController::dataAdminLogueado();
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingreso al menu de ver los empleados segun una carga masiva con id:".$idCargaEmpleado);
        return view(
            'empleado.cargaEnProceso',
            [
                "cargaEmpleado" => $cargaEmpleado,
                "cargaEmpleado_Empleados" => $cargaEmpleado_Empleados,
                "dataUsu" => $usu
            ]
        );
    }

    public function subirEmpleadosCsv($idCargaEmpleado){
        $cargaEmpleado = DB::table("carga_empleado","ce")
        ->where("ce.idCargaEmpleado","=",$idCargaEmpleado)
        ->where("ce.fkEstado","=","3")
        ->first();

        if(isset($cargaEmpleado)){
            $contents = Storage::get($cargaEmpleado->rutaArchivo);            
            $contents = str_replace("\r","\n",$contents);

            $reader = Reader::createFromString($contents);            
            $reader->setOutputBOM(Reader::BOM_UTF8);
            $reader->setDelimiter(';');
            // Create a customer from each row in the CSV file
            $idDatosPersonales = 0;
            $idempleado = 0;
            $idPeriodo = 0;
            $fechaIngreso = "2020-09-02";            
            $empleadosSubidos = 0;            


           
            for($i = $cargaEmpleado->numActual; $i < $cargaEmpleado->numRegistros; $i++){
                
                $row = $reader->fetchOne($i);
               
                foreach($row as $key =>$valor){
                    if($valor==""){
                        $row[$key]=null;
                    }
                    else{
                        $row[$key] = mb_convert_encoding($row[$key],"UTF-8");
                        if(strpos($row[$key], "/")){
                            
                            $dt = DateTime::createFromFormat("d/m/Y", $row[$key]);
                            if($dt === false){
                                $dt = new DateTime();
                            }
                            $ts = $dt->getTimestamp();
                            $row[$key] = date("Y-m-d", $ts);
                        }
                    }
                }
                
                if($row[0]=="1"){

                    if($idempleado != 0 && $idPeriodo != 0){
                        $this->validarEstadoEmpleado($idempleado, $idPeriodo);
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "11",
                            "fkEmpleado" => $idempleado,
                            "fkPeriodoActivo" => $idPeriodo,
                            "fkCargaEmpleado" => $idCargaEmpleado
                        ]);

                        $empleadosSubidos++;
                        if($empleadosSubidos == 3){
                            DB::table('carga_empleado',"ce")
                            ->where("ce.idCargaEmpleado","=",$idCargaEmpleado)
                            ->update(["numActual" => ($i)]);
                            
                            $cargaEmpleado_Empleados = DB::table("carga_empleado_empleado", "cee")
                            ->select('ti.nombre as tipoDocumento', "dp.*", "est.nombre as estado")
                            ->join("empleado as e","e.idempleado", "=", "cee.fkEmpleado", "left")
                            ->join("estado as est", "est.idEstado", "=", "cee.fkEstado", "left")
                            ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales", "left")
                            ->join("tipoidentificacion AS ti","ti.idtipoIdentificacion", "=","dp.fkTipoIdentificacion", "left")
                            ->where("fkCargaEmpleado","=",$idCargaEmpleado)->get();
                            $mensaje = "";
                            foreach($cargaEmpleado_Empleados as $index => $cargaEmpleado_Empleado){
                                $mensaje .= '<tr>
                                    <td>'.($index + 1).'</td>
                                    <td>'.$cargaEmpleado_Empleado->tipoDocumento.' - '.$cargaEmpleado_Empleado->numeroIdentificacion.'</td>
                                    <td>'.$cargaEmpleado_Empleado->primerApellido.' '.$cargaEmpleado_Empleado->segundoApellido.' '.$cargaEmpleado_Empleado->primerNombre.' '.$cargaEmpleado_Empleado->segundoNombre.'</td>
                                    <td>'.$cargaEmpleado_Empleado->estado.'</td>
                                </tr>';
                            }

                            return response()->json([
                                "success" => true,
                                "seguirSubiendo" => true,
                                "numActual" =>  (($i-1)),
                                "mensaje" => $mensaje,
                                "porcentaje" => ceil((($i-1) / $cargaEmpleado->numRegistros)*100)."%"
                            ]);
                        }
                    }

                    $datosEmpleadoConsulta = DB::table("datospersonales", "dp")
                    ->where("dp.numeroIdentificacion", "=", $row[1])
                    ->where("dp.fkTipoIdentificacion", "=", $row[2])
                    ->get();
                    
                    if(sizeof($datosEmpleadoConsulta) > 0){

                        $i_adelantada = $i+1;
                        $row_adelantada = $reader->fetchOne($i_adelantada);
                        
                        $idEmpresa = 0;
                        if($row_adelantada[0] == "2"){
                            $idEmpresa = $row_adelantada[2];
                        }
                        

                        $empresa = DB::table("empresa")
                        ->where("idempresa", "=", $idEmpresa)
                        ->first();
                        
                        if(!isset($empresa)){
                            DB::table('carga_empleado_empleado')
                            ->insert([
                                "fkEstado" => "37",
                                "linea" => $i,
                                "fkCargaEmpleado" => $idCargaEmpleado,
                                "adicional" => ""
                                ]
                            );
                            $idempleado = 0;
                            $idDatosPersonales = 0;
                            continue;
                        }
                        
                        $empleado = DB::table("empleado", "e")
                        ->select("e.*","p.idPeriodo")
                        ->join("periodo as p", "p.fkEmpleado","=","e.idempleado")
                        ->join("nomina as n", "n.idNomina","=","p.fkNomina")
                        ->where("e.fkDatosPersonales", "=",$datosEmpleadoConsulta[0]->idDatosPersonales)
                        ->whereIn("p.fkEstado",["1","3"])
                        ->where("n.fkEmpresa","=",$idEmpresa)
                        ->first();

                        if(isset($empleado)){
                            //dd($empleado);
                            DB::table('carga_empleado_empleado')
                            ->insert([
                                "fkEstado" => "10",
                                "fkEmpleado" => $empleado->idempleado,
                                "fkPeriodoActivo" => $empleado->idPeriodo,
                                "fkCargaEmpleado" => $idCargaEmpleado
                                ]
                            );


                            $idempleado = 0;
                            $idDatosPersonales = 0;
                            continue;
                        }
                        else{
                            $idDatosPersonales = $datosEmpleadoConsulta[0]->idDatosPersonales;
                            continue;
                        }
                        
                    }

                    //Foraneas Datos Empleado

                    $tipoidentificacion = DB::table("tipoidentificacion")
                    ->where("idtipoIdentificacion", "=", $row[2])
                    ->first();
                    if(!isset($tipoidentificacion)){
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "29",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado
                            ]
                        );
                        $idempleado = 0;
                        $idDatosPersonales = 0;
                        continue;
                    }

                    $ubicacion = DB::table("ubicacion")
                    ->where("idubicacion", "=", $row[7])
                    ->first();
                    if(!isset($ubicacion)){
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "30",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado,
                            "adicional" => "UbicacionExpedicion"
                            ]
                        );
                        $idempleado = 0;
                        $idDatosPersonales = 0;
                        continue;
                    }

                    $genero = DB::table("genero")
                    ->where("idGenero", "=", $row[9])
                    ->first();
                    if(!isset($genero)){
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "31",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado,
                            "adicional" => ""
                            ]
                        );
                        $idempleado = 0;
                        $idDatosPersonales = 0;
                        continue;
                    }

                    $estadocivil = DB::table("estadocivil")
                    ->where("idEstadoCivil", "=", $row[10])
                    ->first();
                    if(!isset($estadocivil)){
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "32",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado,
                            "adicional" => ""
                            ]
                        );
                        $idempleado = 0;
                        $idDatosPersonales = 0;
                        continue;
                    }

                    $ubicacion = DB::table("ubicacion")
                    ->where("idubicacion", "=", $row[13])
                    ->first();
                    if(!isset($ubicacion)){
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "30",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado,
                            "adicional" => "UbicacionNacimiento"
                            ]
                        );
                        $idempleado = 0;
                        $idDatosPersonales = 0;
                        continue;
                    }

                    $ubicacion = DB::table("ubicacion")
                    ->where("idubicacion", "=", $row[15])
                    ->first();
                    if(!isset($ubicacion)){
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "30",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado,
                            "adicional" => "UbicacionResidencia"
                            ]
                        );
                        $idempleado = 0;
                        $idDatosPersonales = 0;
                        continue;
                    }

                    $tipo_vivienda = DB::table("tipo_vivienda")
                    ->where("idTipoVivienda", "=", $row[19])
                    ->first();
                    if(!isset($tipo_vivienda)){
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "33",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado,
                            "adicional" => ""
                            ]
                        );
                        $idempleado = 0;
                        $idDatosPersonales = 0;
                        continue;
                    }

                    $gruposanguineo = DB::table("gruposanguineo")
                    ->where("idGrupoSanguineo", "=", $row[24])
                    ->first();
                    if(!isset($gruposanguineo)){
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "34",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado,
                            "adicional" => ""
                            ]
                        );
                        $idempleado = 0;
                        $idDatosPersonales = 0;
                        continue;
                    }

                    $gruposanguineo = DB::table("gruposanguineo")
                    ->where("idGrupoSanguineo", "=", $row[24])
                    ->first();
                    if(!isset($gruposanguineo)){
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "34",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado,
                            "adicional" => ""
                            ]
                        );
                        $idempleado = 0;
                        $idDatosPersonales = 0;
                        continue;
                    }

                    $rh = DB::table("rh")
                    ->where("idRh", "=", $row[25])
                    ->first();
                    if(!isset($rh)){
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "35",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado,
                            "adicional" => ""
                            ]
                        );
                        $idempleado = 0;
                        $idDatosPersonales = 0;
                        continue;
                    }

                    $nivel_estudio = DB::table("nivel_estudio")
                    ->where("idNivelEstudio", "=", $row[31])
                    ->first();
                    if(!isset($nivel_estudio)){
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "46",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado,
                            "adicional" => ""
                            ]
                        );
                        $idempleado = 0;
                        $idDatosPersonales = 0;
                        continue;
                    }
                    $etnia = DB::table("etnia")
                    ->where("idEtnia", "=", $row[32])
                    ->first();
                    if(!isset($etnia)){

                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "47",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado,
                            "adicional" => ""
                            ]
                        );
                        $idempleado = 0;
                        $idDatosPersonales = 0;
                        continue;
                    }
                    
                    $insertDatosEmpleado = array(
                                    "numeroIdentificacion" => $row[1],
                                    "fkTipoIdentificacion" => $row[2],
                                    "primerNombre" => $row[3],
                                    "segundoNombre" => $row[4],
                                    "primerApellido" => $row[5],
                                    "segundoApellido" => $row[6],
                                    "fkUbicacionExpedicion" => $row[7],
                                    "fechaExpedicion" => $row[8],
                                    "fkGenero" => $row[9],
                                    "fkEstadoCivil" => $row[10],
                                    "libretaMilitar" => $row[11],
                                    "distritoMilitar" => $row[12],
                                    "fkUbicacionNacimiento" => $row[13],
                                    "fechaNacimiento" => $row[14],
                                    "fkUbicacionResidencia" => $row[15],
                                    "direccion" => $row[16],
                                    "barrio" => $row[17],
                                    "estrato" => $row[18],
                                    "fkTipoVivienda" => $row[19],
                                    "telefonoFijo" => $row[20],
                                    "celular" => $row[21],
                                    "correo" => $row[22],
                                    "correo2" => $row[23],
                                    "fkGrupoSanguineo" => $row[24],
                                    "fkRh" => $row[25],
                                    "tallaCamisa" => (isset($row[26]) ? $row[26] : null ),
                                    "tallaPantalon" => (isset($row[27]) ? $row[27] : null ),
                                    "tallaZapatos" => (isset($row[28]) ? $row[28] : null ),
                                    "otros" => (isset($row[29]) ? $row[29] : null ),
                                    "tallaOtros" => (isset($row[30]) ? $row[30] : null ),
                                    "fkNivelEstudio" => (isset($row[31]) ? $row[31] : null ),
                                    "fkEtnia" => (isset($row[32]) ? $row[32] : null ),
                    );
                    try{
                        $idDatosPersonales = DB::table('datospersonales')->insertGetId($insertDatosEmpleado, "idDatosPersonales");
                    }
                    catch(QueryException $e){                 
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "36",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado,
                            "adicional" => $e->getMessage()
                            ]
                        );
                        $idempleado = 0;
                        $idPeriodo = 0;
                        $idDatosPersonales = 0;
                        continue;                    
                    }
                    
                }
                else if($row[0]=="2" && $idDatosPersonales!=0){
                    
                    $idEmpresa = $row[2];
                    
                    $datosPersonales = DB::table('datospersonales')->where("idDatosPersonales","=",$idDatosPersonales)->first();

                    $regimen = "";
                    if( $row[5] == "1"){
                        $regimen = "Ley 50";
                    }
                    else if( $row[5] == "2"){
                        $regimen = "Salario Integral";
                    }

                    $empresa = DB::table("empresa")
                    ->where("idempresa", "=", $row[2])
                    ->first();
                    
                    if(!isset($empresa)){
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "37",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado,
                            "adicional" => ""
                            ]
                        );
                        $idempleado = 0;
                        $idDatosPersonales = 0;
                        continue;
                    }

                    $nomina = DB::table("nomina")
                    ->where("idNomina", "=", $row[3])
                    ->first();
                    if(!isset($nomina)){
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "38",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado,
                            "adicional" => ""
                            ]
                        );
                        $idempleado = 0;
                        $idDatosPersonales = 0;
                        continue;
                    }

                    $ubicacion = DB::table("ubicacion")
                    ->where("idubicacion", "=", $row[7])
                    ->first();
                    if(!isset($ubicacion)){
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "30",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado,
                            "adicional" => "UbicacionLabora"
                            ]
                        );
                        $idempleado = 0;
                        $idDatosPersonales = 0;
                        continue;
                    }
                    
                    $cargo = DB::table("cargo")
                    ->where("idCargo", "=", $row[8])
                    ->first();
                    if(!isset($cargo)){
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "39",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado,
                            "adicional" => ""
                            ]
                        );
                        $idempleado = 0;
                        $idDatosPersonales = 0;
                        continue;
                    }
                    
                    $tipo_cotizante = DB::table("tipo_cotizante")
                    ->where("idTipoCotizante", "=", $row[19])
                    ->first();
                    if(!isset($tipo_cotizante)){
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "40",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado,
                            "adicional" => ""
                            ]
                        );
                        $idempleado = 0;
                        $idDatosPersonales = 0;
                        continue;
                    }
                    
                    $subtipocotizante = DB::table("subtipocotizante")
                    ->where("idSubtipoCotizante", "=", $row[20])
                    ->first();
                    if(!isset($subtipocotizante)){
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "41",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado,
                            "adicional" => ""
                            ]
                        );
                        $idempleado = 0;
                        $idDatosPersonales = 0;
                        continue;
                    }
                    
    
                    $empleado = DB::table("empleado", "e")
                    ->where("e.fkDatosPersonales", "=",$idDatosPersonales)
                    ->first();              

                    if(isset($empleado)){
                        $fechaIngreso = $row[4];
                        $idempleado = $empleado->idempleado;
                        $insertPeriodo = [
                            "fkEmpleado" => $idempleado,
                            "fkNomina" => $row[3],
                            "fechaInicio" => $row[4],
                            "tipoRegimen" => $regimen,
                            "tipoRegimenPensional" => $row[6],
                            "fkUbicacionLabora" => $row[7],
                            "fkCargo" => $row[8],
                            "sabadoLaborable" => $row[9],
                            "formaPago" => $row[10],
                            "fkEntidad" => $row[11],
                            "numeroCuenta" => $row[12],
                            "tipoCuenta" => strtoupper($row[13]),
                            "otraFormaPago" => $row[14],
                            "fkTipoOtroDocumento" => $row[15],
                            "otroDocumento" => $row[16],
                            "fkNivelArl" => ((isset($row[17]) && !empty($row[17])) ? $row[17] : NULL),
                            "fkCentroTrabajo" => ((isset($row[18]) && !empty($row[18])) ? $row[18] : NULL),
                            "fkTipoCotizante" => $row[19],
                            "esPensionado" => $row[20],
                            "aplicaSubsidio" => (isset($row[21]) && !empty($row[21]) ? $row[21] : 0),
                            "procedimientoRetencion" => "TABLA",
                            "fkEstado" => 3
                        ];
                        $updateEmpleado = array(
                            "tEmpleado" => $row[1],
                            "fkDatosPersonales" => $idDatosPersonales,
                            "fkEstado" => 3
                        );
                        DB::table('empleado')->where("idempleado","=",$idempleado)->update($updateEmpleado);
                 
                        $idPeriodo = DB::table("periodo")->insertGetId($insertPeriodo, "idPeriodo");
                    }
                    else{
                        $insertEmpleado = array(
                            "tEmpleado" => $row[1],
                            "fkDatosPersonales" => $idDatosPersonales,
                            "fkEstado" => 3
                        );

                        $fechaIngreso = $row[4];
                        $idempleado = DB::table('empleado')->insertGetId($insertEmpleado, "idempleado");
                        $insertPeriodo = [
                            "fkEmpleado" => $idempleado,
                            "fkNomina" => $row[3],
                            "fechaInicio" => $row[4],
                            "tipoRegimen" => $regimen,
                            "tipoRegimenPensional" => $row[6],
                            "fkUbicacionLabora" => $row[7],
                            "fkCargo" => $row[8],
                            "sabadoLaborable" => $row[9],
                            "formaPago" => $row[10],
                            "fkEntidad" => $row[11],
                            "numeroCuenta" => $row[12],
                            "tipoCuenta" => strtoupper($row[13]),
                            "otraFormaPago" => $row[14],
                            "fkTipoOtroDocumento" => $row[15],
                            "otroDocumento" => $row[16],
                            "fkNivelArl" => ((isset($row[17]) && !empty($row[17])) ? $row[17] : NULL),
                            "fkCentroTrabajo" => ((isset($row[18]) && !empty($row[18])) ? $row[18] : NULL),
                            "fkTipoCotizante" => $row[19],
                            "esPensionado" => $row[20],
                            "aplicaSubsidio" => (isset($row[21]) && !empty($row[21]) ? $row[21] : 0),
                            "procedimientoRetencion" => "TABLA",
                            "fkEstado" => 3
                        ];
                        $idPeriodo = DB::table("periodo")->insertGetId($insertPeriodo, "idPeriodo");
                        
                        try{
                            $empresa = DB::table('empresa')->where("idempresa", "=", $idEmpresa)->first();
                            if(strpos($empresa->dominio,"@")===false){
                                $empresa->dominio = "@".$empresa->dominio;
                            }
    
                            $infoUsuario = $datosPersonales->numeroIdentificacion;
                            
    
                            $usuarioNuevo = new User;
                            $usuarioNuevo->email = $infoUsuario;
                            $usuarioNuevo->username = $infoUsuario;
                            $usuarioNuevo->password = ($datosPersonales->numeroIdentificacion."#".substr($datosPersonales->fechaNacimiento,0,4));
                            $usuarioNuevo->fkRol = 1;
                            $usuarioNuevo->estado = 1;
                            $usuarioNuevo->fkEmpleado = $idempleado;
                            $usuarioNuevo->save();
                        }
                        catch(QueryException $e){
                            DB::table('carga_empleado_empleado')
                            ->insert([
                                "fkEstado" => "36",
                                "linea" => $i,
                                "fkCargaEmpleado" => $idCargaEmpleado,
                                "adicional" => $e->getMessage()
                                ]
                            );
                            $idempleado = 0;
                            $idPeriodo = 0;
                            $idDatosPersonales = 0;
                            continue;
                        }
                    }
                    //Buscar si existe el campo de fecha de retiro, si existe inactivar el periodo activo y 
                    if(isset($row[22]) && !empty($row[22]) && $idempleado!=0 && $idPeriodo != 0){
                    
                        
                        $i_adelantada = $i+1;
                        $fkCargo = $row[8];
                        $fkTipoContrato = null;
                        $salario = 0;
                        for($i_adelantada = $i; $i_adelantada < $cargaEmpleado->numRegistros; $i_adelantada++){
                            $row_adelantada = $reader->fetchOne($i_adelantada);
                            if($row_adelantada[0] == "3"){
                                $fkTipoContrato = $row_adelantada[1];
                            }
                            if($row_adelantada[0] == "6" && ($row_adelantada[6] == "1" || $row_adelantada[6] == "2")){
                                $salario = $row_adelantada[2];
                            }
                            if($row_adelantada[0] == "1"){
                                break;
                            }
                        }


                        DB::table("periodo")->where("idPeriodo","=",$idPeriodo)
                        ->update([
                            "fkEstado" => "2",
                            "fechaFin" => $row[22],
                            "fkCargo" => $fkCargo,
                            "fkTipoContrato" => $fkTipoContrato,
                            "salario" => $salario,
                        ]);

                        DB::table('empleado')->where("idempleado","=",$idempleado)->update([
                            "fkEstado" => "2"
                        ]);
                        
                        if(!isset($row[23]) || empty($row[23])){
                            $row[23] = "17";
                        }

                        $idRetiro = DB::table("retiro")->insertGetId([
                            "fecha" => $row[22],
                            "fechaReal" => $row[22],
                            "fkMotivoRetiro" => $row[23],
                            "indemnizacion" => "0"
                        ], "idRetiro");

                        $insertNovedad = [
                            "fkTipoNovedad" => "5",
                            "fkNomina" => $row[3],
                            "fkEmpleado" => $idempleado,
                            "fkPeriodoActivo" => $idPeriodo,
                            "fkEstado" => 8,
                            "fechaRegistro" => $row[22],
                            "fkRetiro" => $idRetiro
                        ];

                        DB::table("novedad")->insert($insertNovedad);
                    }
                    
                    


                }
                else if($row[0]=="3" && $idempleado!=0){//AgregarContrato


                    $tipocontrato = DB::table("tipocontrato")
                    ->where("idtipoContrato", "=", $row[1])
                    ->first();
                    if(!isset($tipocontrato)){
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "42",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado,
                            "adicional" => ""
                            ]
                        );
                        continue;
                    }

                    $meses = 0;
                    $fechaFin = new DateTime($fechaIngreso);
                    
                    

                    $insertContrato = array(
                        "fechaInicio" => $fechaIngreso,
                        "fkEstado" => "4",
                        "fkTipoContrato" => $row[1],
                        "tipoDuracionContrato" => "MES",
                        "fkEmpleado" => $idempleado,
                        "fkPeriodoActivo" => $idPeriodo
                    );
                    if(isset($row[2])){
                        $meses = intval($row[2]);
                        $fechaFin->add(new DateInterval('P'.$meses.'M'));
                        $fechaFin->add(DateInterval::createFromDateString('-1 day'));
                        $insertContrato = array(
                            "fechaInicio" => $fechaIngreso,
                            "fechaFin" => $fechaFin->format('Y-m-d'), 
                            "fkEstado" => "4",
                            "fkTipoContrato" => $row[1],
                            "tipoDuracionContrato" => "MES",
                            "fkEmpleado" => $idempleado,
                            "fkPeriodoActivo" => $idPeriodo
                        );
                        $meses = $row[2];
                        $dias = $row[2]*30;
            
                        $insertContrato["numeroMeses"] = $meses;
                        $insertContrato["numeroDias"] = $dias;
                    }
                                
                    DB::table('contrato')->insert($insertContrato);

                }
                else if($row[0]=="4" && $idempleado!=0){//AgregarCentroCosto
                    $porcentaje = 100;
                    if(isset($row[2])){
                        $porcentaje = $row[2];
                    }

                    $centrocosto = DB::table("centrocosto")
                    ->where("idcentroCosto", "=", $row[1])
                    ->first();

                    if(!isset($centrocosto)){
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "43",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado,
                            "adicional" => ""
                            ]
                        );
                        continue;
                    }

                    DB::table('empleado_centrocosto')
                    ->where("fkEmpleado","=",$idempleado)
                    ->where("fkPeriodoActivo","=",$idPeriodo)
                    ->delete();
                    $insertCentroCosto = array(  "fkEmpleado" => $idempleado, 
                                                "fkCentroCosto" => $row[1],
                                                "porcentajeTiempoTrabajado" => $porcentaje,
                                                "fkPeriodoActivo" => $idPeriodo);

                    DB::table('empleado_centrocosto')->insert($insertCentroCosto);
                }
                
                else if($row[0]=="5" && $idempleado!=0){//AgregarAfiliacion
                    $fechaAfiliacion = $fechaIngreso;

                    $periodo = DB::table("periodo")->where("idPeriodo", "=",$idPeriodo)->first();
                    if($periodo->fkTipoCotizante == "12" || $periodo->fkTipoCotizante == "19" || $periodo->fkTipoCotizante == "23"){
                        $row[2] = "";
                        $row[3] = "";
                        $row[4] = "";
                    }

                    if(isset($row[5])){
                        $fechaAfiliacion = $row[5];
                    }
                    if(isset($row[1]) && !empty($row[1])){


                        $tercero = DB::table("tercero")
                        ->where("idTercero", "=", $row[1])
                        ->first();

                        if(!isset($tercero)){
                            DB::table('carga_empleado_empleado')
                            ->insert([
                                "fkEstado" => "44",
                                "linea" => $i,
                                "fkCargaEmpleado" => $idCargaEmpleado,
                                "adicional" => ""
                                ]
                            );
                            continue;
                        }
                     
                        $insertAfiliacion1 = array(
                            "fkTipoAfilicacion" => "1",
                            "fkTercero" => $row[1],
                            "fechaAfiliacion" => $fechaAfiliacion,
                            "fkEmpleado" => $idempleado,
                            "fkPeriodoActivo" => $idPeriodo
                        );
                        DB::table('afiliacion')->insert($insertAfiliacion1);
                    }

                    if(isset($row[2]) && !empty($row[2])){

                        $tercero = DB::table("tercero")
                        ->where("idTercero", "=", $row[2])
                        ->first();

                        if(!isset($tercero)){
                            DB::table('carga_empleado_empleado')
                            ->insert([
                                "fkEstado" => "44",
                                "linea" => $i,
                                "fkCargaEmpleado" => $idCargaEmpleado,
                                "adicional" => ""
                                ]
                            );
                            continue;
                        }
                       
                        $insertAfiliacion2 = array(
                            "fkTipoAfilicacion" => "2",
                            "fkTercero" => $row[2],
                            "fechaAfiliacion" => $fechaAfiliacion,
                            "fkEmpleado" => $idempleado,
                            "fkPeriodoActivo" => $idPeriodo
                        );

                        DB::table('afiliacion')->insert($insertAfiliacion2);
                    }


                    if(isset($row[3]) && !empty($row[3])){
                        $tercero = DB::table("tercero")
                        ->where("idTercero", "=", $row[3])
                        ->first();

                        if(!isset($tercero)){
                            DB::table('carga_empleado_empleado')
                            ->insert([
                                "fkEstado" => "44",
                                "linea" => $i,
                                "fkCargaEmpleado" => $idCargaEmpleado,
                                "adicional" => ""
                                ]
                            );
                            continue;
                        }

                       
                        $insertAfiliacion3 = array(
                            "fkTipoAfilicacion" => "3",
                            "fkTercero" => $row[3],
                            "fechaAfiliacion" => $fechaAfiliacion,
                            "fkEmpleado" => $idempleado,
                            "fkPeriodoActivo" => $idPeriodo
                        );

                        DB::table('afiliacion')->insert($insertAfiliacion3);
                    }

                    if(isset($row[4]) && !empty($row[4])){
                        $tercero = DB::table("tercero")
                        ->where("idTercero", "=", $row[4])
                        ->first();

                        if(!isset($tercero)){
                            DB::table('carga_empleado_empleado')
                            ->insert([
                                "fkEstado" => "44",
                                "linea" => $i,
                                "fkCargaEmpleado" => $idCargaEmpleado,
                                "adicional" => ""
                                ]
                            );
                            continue;
                        }
                        

                        $insertAfiliacion4 = array(
                            "fkTipoAfilicacion" => "4",
                            "fkTercero" => $row[4],
                            "fechaAfiliacion" => $fechaAfiliacion,
                            "fkEmpleado" => $idempleado,
                            "fkPeriodoActivo" => $idPeriodo
                        );
    
                        DB::table('afiliacion')->insert($insertAfiliacion4);
                    }

                    





                }
                else if($row[0]=="6" && $idempleado!=0){//AgregarConceptosFijos

                    $concepto = DB::table("concepto")
                    ->where("idconcepto", "=", $row[6])
                    ->first();

                    if(!isset($concepto)){
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "13",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado,
                            "adicional" => ""
                            ]
                        );
                        continue;
                    }
                    DB::table('conceptofijo')
                    ->where("fkEmpleado","=",$idempleado)
                    ->where("fkPeriodoActivo","=",$idPeriodo)
                    ->delete();
                    $insertConceptoFijo = array(
                        "unidad" => $row[1],
                        "valor" => $row[2],
                        "porcentaje" => $row[3],
                        "fechaInicio" => $row[4],
                        "fechaFin" => $row[5],
                        "fkEmpleado" => $idempleado,
                        "fkPeriodoActivo" => $idPeriodo,
                        "fkEstado" => 1,
                        "fkConcepto" => $row[6]
                    );
                    DB::table('conceptofijo')->insert($insertConceptoFijo);

                }                
                else if($row[0]=="7" && $idempleado!=0){//AgregarBeneficio

                    $valorMensual = 0; 
                    if($row[4]>0){
                        $valorMensual = intval($row[3]/$row[4]);
                    }
                    $tipobeneficio = DB::table("tipobeneficio")
                    ->where("idTipoBeneficio", "=", $row[1])
                    ->first();

                    if(!isset($tipobeneficio)){
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "13",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado,
                            "adicional" => ""
                            ]
                        );
                        continue;
                    }

                    $insertBeneficioTributario = array( 
                        "fkTipoBeneficio" => $row[1], 
                        "valorMensual" => $valorMensual,
                        "fechaVigencia" => $row[2],
                        "numMeses" => $row[4],
                        "valorTotal" => $row[3],
                        "fkEmpleado" => $idempleado,
                        "fkPeriodoActivo" => $idPeriodo);
           
                    if($row[1]=="4"){
                        $tipoidentificacion = DB::table("tipoidentificacion")
                        ->where("idtipoIdentificacion", "=", $row[8])
                        ->first();
                        if(!isset($tipoidentificacion)){
                            DB::table('carga_empleado_empleado')
                            ->insert([
                                "fkEstado" => "29",
                                "linea" => $i,
                                "fkCargaEmpleado" => $idCargaEmpleado,
                                "adicional" => "En nucleo familiar"
                                ]
                            );
                            $idempleado = 0;
                            $idDatosPersonales = 0;
                            continue;
                        }    

                        $nivel_estudio = DB::table("nivel_estudio")
                        ->where("idNivelEstudio", "=", $row[10])
                        ->first();
                        if(!isset($nivel_estudio)){
                            DB::table('carga_empleado_empleado')
                            ->insert([
                                "fkEstado" => "46",
                                "linea" => $i,
                                "fkCargaEmpleado" => $idCargaEmpleado,
                                "adicional" => ""
                                ]
                            );
                            $idempleado = 0;
                            $idDatosPersonales = 0;
                            continue;
                        }
                        

                        $arrNucleoFamiliar = [
                            "fkTipoIdentificacion" => $row[8],
                            "numIdentificacion" => $row[7],
                            "nombre" => $row[5],
                            "fechaNacimiento" => $row[6],
                            "fkEscolaridad" => $row[10],
                            "fkDatosEmpleado" => $idDatosPersonales
                        ];

                        DB::table('nucleofamiliar')->where("fkDatosEmpleado","=",$idDatosPersonales)->delete();

                        $idNucleo = DB::table('nucleofamiliar')
                        ->insertGetId($arrNucleoFamiliar,"idNucleoFamiliar");
                        $insertBeneficioTributario["fkNucleoFamiliar"] = $idNucleo;
                    }
                    DB::table('beneficiotributario')
                    ->where("fkEmpleado","=",$idempleado)
                    ->where("fkPeriodoActivo","=",$idPeriodo)
                    ->delete();
                    DB::table('beneficiotributario')->insert($insertBeneficioTributario);
                }
                else if($row[0]=="8" && $idempleado!=0){//AgregarContactoEmer
                   
                    $ubicacion = DB::table("ubicacion")
                    ->where("idubicacion", "=", $row[4])
                    ->first();
                    if(!isset($ubicacion)){
                        DB::table('carga_empleado_empleado')
                        ->insert([
                            "fkEstado" => "30",
                            "linea" => $i,
                            "fkCargaEmpleado" => $idCargaEmpleado,
                            "adicional" => "UbicacionContactoEmer"
                            ]
                        );
                        $idempleado = 0;
                        $idDatosPersonales = 0;
                        continue;
                    }

                    $insertContactoEm = array( 
                        "nombre" => $row[1], 
                        "telefono" => $row[2],
                        "direccion" => $row[3],
                        "fkUbicacion" => $row[4],
                        "fkDatosEmpleado" => $idDatosPersonales
                    );
                    DB::table('contactoemergencia')->where("fkDatosEmpleado","=",$idDatosPersonales)->delete();
                    DB::table('contactoemergencia')->insert($insertContactoEm);    
                }
                
            }

            if($idempleado!=0 && $idPeriodo != 0){
                
                $this->validarEstadoEmpleado($idempleado, $idPeriodo);
                DB::table('carga_empleado_empleado')
                ->insert([
                    "fkEstado" => "11",
                    "fkEmpleado" => $idempleado,
                    "fkCargaEmpleado" => $idCargaEmpleado
                    ]
                );
                
                DB::table('carga_empleado',"ce")
                ->where("ce.idCargaEmpleado","=",$idCargaEmpleado)
                ->update(["numActual" => $cargaEmpleado->numRegistros,"fkEstado" => "11"]);
            
                
            }  
            else{
                DB::table('carga_empleado',"ce")
                ->where("ce.idCargaEmpleado","=",$idCargaEmpleado)
                ->update(["numActual" => $cargaEmpleado->numRegistros,"fkEstado" => "11"]);
            }
              

            $cargaEmpleado_Empleados = DB::table("carga_empleado_empleado", "cee")
            ->select('ti.nombre as tipoDocumento', "dp.*", "est.nombre as estado")
            ->join("empleado as e","e.idempleado", "=", "cee.fkEmpleado", "left")
            ->join("estado as est", "est.idEstado", "=", "cee.fkEstado", "left")
            ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales", "left")
            ->join("tipoidentificacion AS ti","ti.idtipoIdentificacion", "=","dp.fkTipoIdentificacion", "left")
            ->where("fkCargaEmpleado","=",$idCargaEmpleado)->get();
            $mensaje = "";
            foreach($cargaEmpleado_Empleados as $index => $cargaEmpleado_Empleado){
                $mensaje .= '<tr>
                    <td>'.($index + 1).'</td>
                    <td>'.$cargaEmpleado_Empleado->tipoDocumento.' - '.$cargaEmpleado_Empleado->numeroIdentificacion.'</td>
                    <td>'.$cargaEmpleado_Empleado->primerApellido.' '.$cargaEmpleado_Empleado->segundoApellido.' '.$cargaEmpleado_Empleado->primerNombre.' '.$cargaEmpleado_Empleado->segundoNombre.'</td>
                    <td>'.$cargaEmpleado_Empleado->estado.'</td>
                </tr>';
            }


            return response()->json([
                "success" => true,
                "seguirSubiendo" => false,
                "numActual" => $cargaEmpleado->numRegistros,
                "mensaje" => $mensaje,
                "porcentaje" => "100%"
            ]);
        }
    }


    public function cargaMasivaEmpleados(Request $req){
        
        $csvEmpleados = $req->file("archivoCSV");
        $file = $req->file('archivoCSV')->get();
        $file = str_replace("\r","\n",$file);
        $reader = Reader::createFromString($file);
        $reader->setDelimiter(';');
        $csvEmpleados = $csvEmpleados->store("public/csvFiles");

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", creó carga masiva de empleados");

        $idCargaEmpleado  = DB::table("carga_empleado")->insertGetId([
            "rutaArchivo" => $csvEmpleados,
            "fkEstado" => "3",
            "numActual" => 0,
            "numRegistros" => sizeof($reader)
        ], "idCargaEmpleado");

        return redirect('empleado/cargaEmpleados/'.$idCargaEmpleado);
    }

    public function modificarDatosBasicos(Request $req){

        if($req->numIdentificacion=="" || $req->tIdentificacion=="" ){
            return response()->json([
                "success" => false,
                "respuesta" => "Se requiere como minimo la identificación de la persona"
            ]);
        }

        if(isset($req->fechaNacimiento) && strtotime($req->fechaNacimiento)>=strtotime(date("Y-m-d"))){
            return response()->json([
                "success" => false,
                "respuesta" => "Error fecha de nacimiento incorrecta"
            ]);
        }

        $messages = [
            'email' => 'El campo :attribute no es un email valido.'
        ];
        $validator = Validator::make($req->all(), [
            'correo1' => 'nullable|email:rfc,dns',
            'correo2' => 'nullable|email:rfc,dns'
        ],$messages);
        
        
        if ($validator->fails()) {
            
            $msjEnvio = "";
            foreach($validator->errors()->all() as $errores){
                $msjEnvio .= $errores." ,";
            }
            $msjEnvio = substr($msjEnvio, 0, strlen($msjEnvio) - 1);

            return response()->json([
                "success" => false,
                "respuesta" => $msjEnvio
            ]);
        }

        


        if( $req->numIdentificacion !=  $req->numIdentificacionAnt || $req->tIdentificacion != $req->tIdentificacionAnt){
            $empleado = DB::table('datospersonales')
            ->where("numeroIdentificacion", "=", $req->numIdentificacion)
            ->where("fkTipoIdentificacion", "=" , $req->tIdentificacion)
            ->select("e.idempleado")
            ->join("empleado AS e", "e.fkDatosPersonales", "=", "datospersonales.idDatosPersonales")->first();
            if(isset($empleado->idempleado)){
                return response()->json([
                    "success" => false,
                    "respuesta" => "Este documento ya se encuentra en base de datos, debé cambiar el numero de documento"
                ]);
            }


        }
        
        $foto = $req->fotoAnt;
        if ($req->hasFile('foto')) {
            $foto = $req->file("foto")->store("public/imgEmpleados");
        }
        $updateDatosEmpleado = array("foto" => $foto,
                                     "numeroIdentificacion" => $req->numIdentificacion,
                                     "fkTipoIdentificacion" => $req->tIdentificacion,
                                     "primerNombre" => $req->pNombre,
                                     "segundoNombre" => $req->sNombre,
                                     "primerApellido" => $req->pApellido,
                                     "segundoApellido" => $req->sApellido,
                                     "fkUbicacionExpedicion" => $req->lugarExpedicion,
                                     "fechaExpedicion" => $req->fechaExpedicion,
                                     "fkGenero" => $req->genero,
                                     "fkEstadoCivil" => $req->estadoCivil,
                                     "libretaMilitar" => $req->libretaMilitar,
                                     "distritoMilitar" => $req->distritoMilitar,
                                     "fkUbicacionNacimiento" => $req->lugarNacimiento,
                                     "fechaNacimiento" => $req->fechaNacimiento,
                                     "fkUbicacionResidencia" => $req->lugarResidencia,
                                     "direccion" => $req->direccion,
                                     "barrio" => $req->barrio,
                                     "estrato" => $req->estrato,
                                     "fkTipoVivienda" => $req->tipoVivienda,
                                     "telefonoFijo" => $req->telFijo,
                                     "celular" => $req->celular,
                                     "correo" => $req->correo1,
                                     "correo2" => $req->correo2,
                                     "fkGrupoSanguineo" => $req->grupoSanguineo,
                                     "fkRh" => $req->rh,
                                     "tallaCamisa" => $req->tallaCamisa,
                                     "tallaPantalon" => $req->tallaPantalon,
                                     "tallaZapatos" => $req->tallaZapatos,
                                     "otros" => $req->otros,
                                     "tallaOtros" => $req->tallaOtros,
                                     "fkNivelEstudio" => $req->nivelEstudio,
                                     "fkEtnia" => $req->etnia
        );
        $empleado = DB::table("empleado")->where('idempleado', $req->idEmpleado)->first();

        $affected = DB::table('datospersonales')
        ->where('idDatosPersonales', $empleado->fkDatosPersonales)
        ->update($updateDatosEmpleado);

        $idDatosPersonales = $empleado->fkDatosPersonales;

        if(isset($req->nombreEmergencia)){
            foreach ($req->nombreEmergencia as $key => $nombEmer) {
            
                $insertContactoEmergencia = array(  "nombre" => $nombEmer, 
                                                    "telefono" => $req->telefonoEmergencia[$key],
                                                    "direccion" => $req->direccionEmergencia[$key],
                                                    "fkUbicacion" => $req->lugarEmergencia[$key],
                                                    "fkDatosEmpleado" => $idDatosPersonales
                );
                if($req->idContactoEmergencia[$key]=="-1"){
                    DB::table('contactoemergencia')->insert($insertContactoEmergencia);
                }
                else{
                    $affected = DB::table('contactoemergencia')
                    ->where('idContactoEmergencia', $req->idContactoEmergencia[$key])
                    ->update($insertContactoEmergencia);                
                }
            }
        }
        
        $personasVive= DB::table("nucleofamiliar")->where("fkDatosEmpleado","=",$idDatosPersonales)->get();
        

        if(isset($req->nombrePersonaV)){
            foreach ($req->nombrePersonaV as $key => $nombNucleoFam) {
                

                $insertNucleoFam = array(   "nombre" => $nombNucleoFam, 
                                            "fechaNacimiento" => $req->fechaNacimientoPersonaV[$key],
                                            "fkEscolaridad" => $req->escolaridadPersonaV[$key],
                                            "fkParentesco" => $req->parentescoPersonaV[$key],
                                            "fkDatosEmpleado" => $idDatosPersonales
                );
                
                if($req->idNucleoFamiliar[$key]=="-1"){
                    DB::table('nucleofamiliar')->insert($insertNucleoFam);
                }
                else{
                    $affected = DB::table('nucleofamiliar')
                    ->where('idNucleoFamiliar', $req->idNucleoFamiliar[$key])
                    ->update($insertNucleoFam);                
                }
            }
        }
        foreach($personasVive as $personaVive){
            $val = 0;
            if(isset($req->nombrePersonaV)){
                foreach ($req->nombrePersonaV as $key => $nombNucleoFam) {
                    if($req->idNucleoFamiliar[$key] == $personaVive->idNucleoFamiliar){
                        $val = 1;
                    }   
                }
            }
            if($val == 0 ){
                DB::table('nucleofamiliar')
                ->where("idNucleoFamiliar","=",$personaVive->idNucleoFamiliar)
                ->delete();
            }
            
        }



        $upcadicional= DB::table("upcadicional")->where("fkEmpleado","=", $req->idEmpleado)->get();
        foreach($upcadicional as $upcad){
            $val = 0;
            if(isset($req->primerApellidoUpc)){
                foreach ($req->primerApellidoUpc as $key => $primerApellido) {
                    if($req->idUpcAdicional[$key] == $upcad->idUpcAdicional){
                        $val = 1;
                    }   
                }
            }
            if($val == 0 ){
                DB::table('upcadicional')
                ->where("idUpcAdicional","=",$upcad->idUpcAdicional)
                ->delete();
            }
            
        }

        if(isset($req->primerApellidoUpc)){
            foreach ($req->primerApellidoUpc as $key => $primerApellido) {
                
                $insertUpcAdicional = array(   "fkEmpleado" => $req->idEmpleado, 
                                            "primerApellido" => $primerApellido,
                                            "segundoApellido" => $req->segundoApellidoUpc[$key],
                                            "primerNombre" => $req->primerNombreUpc[$key],
                                            "segundoNombre" => $req->segundoNombreUpc[$key],
                                            "fkUbicacion" => $req->lugarUpc[$key],
                                            "fkGenero" => $req->generoUpc[$key],
                                            "fkTipoIdentificacion" => $req->tIdentificacionUpc[$key],
                                            "numIdentificacion" => $req->numIdentificacionUpc[$key],
                                            "fechaNacimiento" => $req->fechaNacimientoUpc[$key],
                                            "fkPeriocidad" => $req->periocidad[$key],

                );
                
                if($req->idUpcAdicional[$key]=="-1"){
                    DB::table('upcadicional')->insert($insertUpcAdicional);
                }
                else{
                    $affected = DB::table('upcadicional')
                    ->where('idUpcAdicional', $req->idUpcAdicional[$key])
                    ->update($insertUpcAdicional);                
                }
            }

        }




        

        
        $updateEmpleado = array("tEmpleado" => $req->tEmpleado);
        $affected = DB::table('empleado')
        ->where('idempleado', $req->idEmpleado)
        ->update($updateEmpleado); 

        $this->validarEstadoEmpleado($req->idEmpleado, $req->idPeriodo);

        return response()->json([
            "success" => true,
            "idempleado" => $req->idEmpleado,
            "idPeriodo" => $req->idPeriodo
        ]);

    }

    public function desactivarEmpleado($idEmpleado, $idPeriodo){


        DB::table("periodo")
        ->where("idPeriodo","=",$idPeriodo)
        ->update([
            "fkEstado" => "2"
        ]);

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", desactivó al empleado con periodo:".$idPeriodo);

        return response()->json([
            "success" => true
        ]);
    }

    public function reactivarEmpleado($idEmpleado, $idPeriodo){
        DB::table("periodo")
        ->where("idPeriodo","=",$idPeriodo)
        ->update([
            "fkEstado" => "3"
        ]);

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", reactivó al empleado con periodo:".$idPeriodo);

        $this->validarEstadoEmpleado($idEmpleado, $idPeriodo);

        return response()->json([
            "success" => true
        ]);
    }

    public function eliminarDefUsuario($idEmpleado,  $idPeriodo){

        try{
            DB::table("periodo")
            ->where("idPeriodo","=",$idPeriodo)
            ->delete();

            $dataUsu = UsuarioController::dataAdminLogueado();
            Log::channel('gesath')->info("El usuario ".$dataUsu->email.", eliminó definitivamente el periodo:".$idPeriodo);
    
            return response()->json([
                "success" => true
            ]);
        }
        catch(Exception $e){
            return response()->json([
                "success" => false,
                "message" => "Este periodo cuenta con datos, no se puede eliminar"
            ]);
        }
        
    }

    public function CSVEmpleados() {
        $arrayRetorno = array();
        $empleado = DB::table("empleado")->select(  
            'empleado.*', 'dp.*', 
            'ubi_dep_exp.idubicacion AS ubi_depto_exp', 'ubi_pa_exp.idubicacion AS ubi_pais_exp',
            'ubi_dep_nac.idubicacion AS ubi_depto_nac', 'ubi_pa_nac.idubicacion AS ubi_pais_nac',
            'ubi_dep_res.idubicacion AS ubi_depto_res', 'ubi_pa_res.idubicacion AS ubi_pais_res'
        )
        ->join('datospersonales AS dp','empleado.fkDatosPersonales', '=', 'dp.idDatosPersonales')
        
        ->join("ubicacion AS ubi_ciud_exp", 'dp.fkUbicacionExpedicion', '=', 'ubi_ciud_exp.idubicacion')
        ->join("ubicacion AS ubi_dep_exp", 'ubi_ciud_exp.fkUbicacion', '=', 'ubi_dep_exp.idubicacion')
        ->join("ubicacion AS ubi_pa_exp", 'ubi_dep_exp.fkUbicacion', '=', 'ubi_pa_exp.idubicacion')
    
        ->join("ubicacion AS ubi_ciud_nac", 'dp.fkUbicacionNacimiento', '=', 'ubi_ciud_nac.idubicacion')
        ->join("ubicacion AS ubi_dep_nac", 'ubi_ciud_nac.fkUbicacion', '=', 'ubi_dep_nac.idubicacion')
        ->join("ubicacion AS ubi_pa_nac", 'ubi_dep_nac.fkUbicacion', '=', 'ubi_pa_nac.idubicacion')
    
        ->join("ubicacion AS ubi_ciud_res", 'dp.fkUbicacionResidencia', '=', 'ubi_ciud_res.idubicacion')
        ->join("ubicacion AS ubi_dep_res", 'ubi_ciud_res.fkUbicacion', '=', 'ubi_dep_res.idubicacion')
        ->join("ubicacion AS ubi_pa_res", 'ubi_dep_res.fkUbicacion', '=', 'ubi_pa_res.idubicacion')
        ->get()->toArray();
        if ($empleado) {
            foreach($empleado as $emp) {
                $idEmpleado = $emp->idempleado;
                $paises = Ubicacion::where("fkTipoUbicacion", "=" ,'1')->get()->toArray();
                $deptosExp = array();
                $ciudadesExp= array();
                if(isset($emp->ubi_pais_exp)){
                    $deptosExp = Ubicacion::where("fkUbicacion", "=", $emp->ubi_pais_exp)->get()->toArray();
                    $ciudadesExp = Ubicacion::where("fkUbicacion", "=", $emp->ubi_depto_exp)->get()->toArray();    
                }
                
                $deptosNac = array();
                $ciudadesNac= array();
                if(isset($emp->ubi_pais_nac)){
                    $deptosNac = Ubicacion::where("fkUbicacion", "=", $emp->ubi_pais_nac)->get()->toArray();
                    $ciudadesNac = Ubicacion::where("fkUbicacion", "=", $emp->ubi_depto_nac)->get()->toArray();
                }
    
                $deptosRes = array();
                $ciudadesRes= array();
                if(isset($emp->ubi_pais_res)){
                    $deptosRes = Ubicacion::where("fkUbicacion", "=", $emp->ubi_pais_res)->get()->toArray();
                    $ciudadesRes = Ubicacion::where("fkUbicacion", "=", $emp->ubi_depto_res)->get()->toArray();
                }
    
    
                $generos = DB::table("genero")->get()->toArray();
                $estadosCivil = DB::table("estadocivil")->get()->toArray();
                $tipo_vivienda = DB::table("tipo_vivienda")->get()->toArray();
                $grupoSanguineo = DB::table("gruposanguineo")->get()->toArray();
                $rhs = DB::table("rh")->get()->toArray();
                $tipoidentificacion = DB::table("tipoidentificacion")->get()->toArray();
    
                $contactosEmergencia = DB::table("contactoemergencia")->select("contactoemergencia.*",
                'ubi_dep_emer.idubicacion AS ubi_depto_emer', 
                'ubi_pa_emer.idubicacion AS ubi_pais_emer'
                )
                ->join("ubicacion AS ubi_ciud_emer", 'contactoemergencia.fkUbicacion', '=', 'ubi_ciud_emer.idubicacion')
                ->join("ubicacion AS ubi_dep_emer", 'ubi_ciud_emer.fkUbicacion', '=', 'ubi_dep_emer.idubicacion')
                ->join("ubicacion AS ubi_pa_emer", 'ubi_dep_emer.fkUbicacion', '=', 'ubi_pa_emer.idubicacion')
                ->where("fkDatosEmpleado", "=", $emp->idDatosPersonales)->get()->toArray();
                $deptosContactosEmergencia = array();
                $ciudadesContactosEmergencia = array();
                foreach($contactosEmergencia as $contactoEmergencia){
                    
                    $deptosEmer = Ubicacion::where("fkUbicacion", "=", $contactoEmergencia->ubi_pais_emer)->get()->toArray();
                    $ciudadesEmer = Ubicacion::where("fkUbicacion", "=", $contactoEmergencia->ubi_depto_emer)->get()->toArray();
    
                    array_push($deptosContactosEmergencia, $deptosEmer);
                    array_push($ciudadesContactosEmergencia, $ciudadesEmer);
    
                }
    
                $nucleofamiliar = DB::table("nucleofamiliar")->where("fkDatosEmpleado", "=", $emp->idDatosPersonales)->get()->toArray();
                
    
                
                $parentescos = DB::table("parentesco")->get()->toArray();
                $escolaridades = DB::table("escolaridad")->get()->toArray();
    
    
    
                $empresas = DB::table("empresa")->orderBy("razonSocial")->get()->toArray();
                $centrosCosto = array();
                if(isset($emp->fkEmpresa)){
                    $centrosCosto = DB::table("centrocosto")->where("fkEmpresa","=",$emp->fkEmpresa)->get()->toArray();
                }
    
    
                $tipoContratos = DB::table('tipoContrato')->get()->toArray();
                $cargos = DB::table("cargo")->get()->toArray();
                $entidadesFinancieras = DB::table("tercero")->where("fk_actividad_economica", "=", "4")->get()->toArray();
                $periodoActivo = DB::table("periodo")
                ->where("fkEmpleado","=",$idEmpleado)
                ->where("fkEstado","=","1")->first();
                if(!isset($periodoActivo)){
                    $periodoActivo = DB::table("periodo")
                    ->where("fkEmpleado","=",$idEmpleado)
                    ->where("fkEstado","=","2")
                    ->orderBy("idPeriodo","desc")
                    ->first();
                }   
                           
                $afiliaciones = DB::table("afiliacion")
                ->where('fkEmpleado', "=", $idEmpleado)
                ->where("fkPeriodoActivo","=",$periodoActivo->idPeriodo)
                ->get()->toArray();
    
                $tipoafilicaciones = DB::table('tipoafilicacion')->get()->toArray();
                $entidadesAfiliacion = array();
                foreach($afiliaciones as $afiliacion){
                    $afiliacionesEnt = DB::table("tercero")
                            ->join('tipoafilicacion AS ta','ta.fkActividadEconomica', '=', 'tercero.fk_actividad_economica')
                            ->where("ta.idTipoAfiliacion", "=", $afiliacion->fkTipoAfilicacion)->orderBy("razonSocial")->get()->toArray();
    
                    $entidadesAfiliacion[$afiliacion->idAfiliacion] = $afiliacionesEnt;
                }
    
    
                $nivelesArl = DB::table('nivel_arl')->get()->toArray();
    
                $afiliacionesEnt1 = DB::table("tercero")
                            ->join('tipoafilicacion AS ta','ta.fkActividadEconomica', '=', 'tercero.fk_actividad_economica')
                            ->where("ta.idTipoAfiliacion", "=", '1')
                            ->whereNotIn("tercero.idTercero",["10","109","111","112","113"])
                            ->get()
                            ->orderBy("razonSocial")
                            ->toArray();
                $afiliacionesEnt2 = DB::table("tercero")
                            ->join('tipoafilicacion AS ta','ta.fkActividadEconomica', '=', 'tercero.fk_actividad_economica')
                            ->where("ta.idTipoAfiliacion", "=", '2')
                            ->orderBy("razonSocial")
                            ->get()->toArray();
                $afiliacionesEnt3 = DB::table("tercero")
                            ->join('tipoafilicacion AS ta','ta.fkActividadEconomica', '=', 'tercero.fk_actividad_economica')
                            ->where("ta.idTipoAfiliacion", "=", '3')
                            ->orderBy("razonSocial")
                            ->get()->toArray();
                $afiliacionesEnt4 = DB::table("tercero")
                            ->join('tipoafilicacion AS ta','ta.fkActividadEconomica', '=', 'tercero.fk_actividad_economica')
                            ->where("ta.idTipoAfiliacion", "=", '4')
                            ->orderBy("razonSocial")
                            ->get()->whereNotIn("tercero.idTercero",["120"])->toArray();


                $conceptosFijos = DB::table('conceptofijo', 'cf')->select(["cf.*", "c.nombre AS nombreConcepto"])
                ->join("concepto AS c", "c.idConcepto", "=", "cf.fkConcepto")
                ->where("cf.fkEmpleado", "=", $idEmpleado)->get()->toArray();
               
              
                $contratoActivo = DB::table('contrato')
                ->where("fkEmpleado","=",$idEmpleado)
                ->whereIn("fkEstado",array("1","4"))
                ->get()
                ->toArray();
    
                $conceptos = DB::table("conceptofijo")->whereNotIn("fkEmpleado", $idEmpleado)->get()->toArray();
                $benefTributario =  DB::table("beneficiotributario")->where("fkEmpleado", $idEmpleado)->get()->toArray();
               
                array_push($arrayRetorno, 
                    array(
                        'empleado' => (array) $emp,
                        'contratoActivo' => (array) $contratoActivo,
                        'centrosCosto' => (array) $centrosCosto,
                        /* 'paises'=> (array) $paises, 
                        'generos' => (array) $generos, 
                        'estadosCivil' => (array) $estadosCivil, 
                        'tipo_vivienda' => (array) $tipo_vivienda,
                        'grupoSanguineo' => (array) $grupoSanguineo,
                        'rhs' => (array) $rhs,
                        'tipoidentificacion' => (array) $tipoidentificacion,
                        'deptosExp' => (array) $deptosExp,
                        'ciudadesExp' => (array) $ciudadesExp,
                        'deptosNac' => (array) $deptosNac,
                        'ciudadesNac' => (array) $ciudadesNac,
                        'deptosRes' => (array) $deptosRes,
                        'ciudadesRes' => (array) $ciudadesRes,
                        'contactosEmergencia' => (array) $contactosEmergencia,
                        'deptosContactosEmergencia' => (array) $deptosContactosEmergencia,
                        'ciudadesContactosEmergencia' => (array) $ciudadesContactosEmergencia,
                        'nucleofamiliar' => (array) $nucleofamiliar,
                        'parentescos' => (array) $parentescos,
                        'escolaridades' => (array) $escolaridades,
                        'empresas' => (array) $empresas,                        
                        'tipoContratos' => (array) $tipoContratos,
                        'idEmpleado' => (array) $idEmpleado, 
                        'cargos' => (array) $cargos, 
                        'entidadesFinancieras' => (array) $entidadesFinancieras,
                        'afiliaciones' => (array) $afiliaciones,
                        'tipoafilicaciones' => (array) $tipoafilicaciones,
                        'nivelesArl' => (array) $nivelesArl, */
                        'afiliacionesEnt1' => (array) $afiliacionesEnt1,
                        'afiliacionesEnt2' => (array) $afiliacionesEnt2,
                        'afiliacionesEnt3' => (array) $afiliacionesEnt3,
                        'afiliacionesEnt4' => (array) $afiliacionesEnt4,
                        'entidadesAfiliacion' => (array) $entidadesAfiliacion,
                        "beneficioTributario" => (array) $benefTributario,
                        'conceptos' => (array) $conceptos,
                        'conceptosFijos' => (array) $conceptosFijos
                    )
                );
            }
        }
        
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=export_empleados_".date("Y_m_d_h:i:s").".csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );
        
      
        $callback = function() use ($arrayRetorno)
        {
            $file = fopen('php://output', 'w');
            
            $arrayRetorno = json_decode(json_encode($arrayRetorno), true);
            //var_dump($arrayRetorno);
            $arrResultCSV = array();
            $arrAux = array();
            $subArray = false;
            
            foreach($arrayRetorno as $k_uno => $ret) {
                $tabla = 1;
                //si tiene hijos con array
                $this->agregaraFile($ret, $file, $tabla);
               
            }





            /*foreach($arrayRetorno as $k_uno => $ret) {
                $tabla = 1;
                
                

                foreach($ret as $r) {



                    if (is_array($r)) {
                        foreach($r as $k_tres => $re) {
                            if (is_array($re)) {
                                $subArray = true;
                                foreach($re as $k_cuatro => $retor) {
                                    array_push($arrAux, $retor);
                                }
                         

                                try{
                                    array_unshift($arrAux, $tabla);
                                    array_push($arrResultCSV, $arrAux);
                                    fputcsv($file, $arrResultCSV[0]);
                                    $arrAux = array();
                                    $arrResultCSV = array();
                                }
                                catch(ErrorException $e){
                                    echo $e;
                                    var_dump( $arrResultCSV);
                                    exit;
                                }
                                
                            } else {
                                array_push($arrAux, $r[$k_tres]);
                            }
                        }
                    } else {
                        array_push($arrAux, $r);
                        $tabla++;
                    }
                    if (!$subArray) {
                        array_unshift($arrAux, $tabla);
                        array_push($arrResultCSV, $arrAux);
                        fputcsv($file, $arrResultCSV[0]);
                        $tabla++;
                    }                    
                    $arrAux = array();
                    $arrResultCSV = array();
                    $subArray = false;
                }
            }*/

            fclose($file);
        };
        return Response::stream($callback, 200, $headers);
    }

    public function verificarArrayInternos($array){
        foreach($array as $key => $value){
            if(is_array($value)){
                return true;
            }    
        }
        return false;

    }

    public function agregaraFile($ret, $file, $tabla){
        
        if($this->verificarArrayInternos($ret)){
            foreach($ret as $k_dos => $re) {
                if(is_array($re)){
                    
                    $this->agregaraFile($re, $file, $tabla);
                    if(!is_numeric($k_dos)){
                        $tabla++;
                    }
                }
                else{                    
                    array_unshift($ret, $tabla);
                    fputcsv($file, $ret[$k_dos]);
                    $tabla++;
                }
            }
        }
        else{
            array_unshift($ret, $tabla);
            fputcsv($file, $ret);
            $tabla++;            
        }
    }
    
    public function getDataPass($idEmple) {
        $dataUsu = DB::table('empleado')->select(
            'datospersonales.numeroIdentificacion',
            'datospersonales.fechaNacimiento'
        )
        ->join('datospersonales', 'empleado.fkDatosPersonales', 'datospersonales.idDatosPersonales')
        ->where('empleado.idempleado', $idEmple)
        ->first();
        if ($dataUsu) {
            $success = true;
            $info = $dataUsu;
        } else {
            $success = false;
            $info = 'Error, no existe un empleado con este ID';
        }
        return response()->json(['success' => $success, 'info' => $info]);
    }

    public function indexSubirFotos(){
        $usu = UsuarioController::dataAdminLogueado();
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu de subir fotos de los empleados");
        return view('/empleado.subirFotosMasivamente',[
            "dataUsu" => $usu
        ]);
    }

    public function cargaMasivaFotosEmpleados(Request $req){
        if ($req->hasFile('archivoZip')) {
            $rutaArchivoZip = $req->archivoZip->path();
            $zip = new ZipArchive();
            $zip->open($rutaArchivoZip);
            $registroExtraer = array();
            for ($i=0; $i<$zip->numFiles;$i++) {
                $registro = $zip->statIndex($i);
                $nombreR = explode(".",$registro["name"]);
                $extenciones = array("jpg", "jpeg", "png");

                if(sizeof($nombreR)>0 && in_array(last($nombreR), $extenciones)){
                    $empleado = DB::table("datospersonales", "dp")->where('dp.numeroIdentificacion', "=", $nombreR[0])->first();

                    if(isset($empleado)){
                        DB::table("datospersonales", "dp")->where('dp.numeroIdentificacion', "=", $nombreR[0])
                        ->update([
                            "foto" => "public/imgEmpleados/".$registro["name"]
                        ]);
                        array_push($registroExtraer, $registro["name"]);
                    }    

                }
            }
            
            

            $zip->extractTo(storage_path("app/public/imgEmpleados"), $registroExtraer);
            $zip->close();
            $usu = UsuarioController::dataAdminLogueado();
            $dataUsu = UsuarioController::dataAdminLogueado();
            Log::channel('gesath')->info("El usuario ".$dataUsu->email.", subió un comprimido zip con las imagenes de los empleados");
            return view("/layouts.respuestaGen",[
                "dataUsu" => $usu,
                "titulo" => "Imagenes modificadas",
                "mensaje" => "Se subieron ".sizeof($registroExtraer)." Fotos"
            ]);
        }
    }

    public function indexReintegro(Request $req){
        $usu = UsuarioController::dataAdminLogueado();

        $empleados = DB::table('empleado')->select( 'empleado.idempleado',
                                                    'empleado.tEmpleado',
                                                    'est.nombre AS estado',
                                                    'est.clase AS claseEstado',
                                                    'empleado.fkEstado',
                                                    'n.nombre as nombreNomina',
                                                    'u.nombre as ciudad',
                                                    'dp.*')
                                        ->selectRaw('(select cc2.nombre from centrocosto as cc2 where cc2.idcentroCosto 
                                                        in(Select ecc.fkCentroCosto from empleado_centrocosto as ecc where 
                                                        ecc.fkEmpleado = empleado.idempleado and
                                                        ecc.fkPeriodoActivo = (SELECT pp2.idPeriodo FROM periodo as pp2 where pp2.fkEmpleado = empleado.idempleado order by pp2.idPeriodo desc limit 0,1  )
                                                        )
                                                        limit 0,1) as centroCosto ')
                                        ->join('datospersonales AS dp','empleado.fkDatosPersonales', '=', 'dp.idDatosPersonales')
                                        ->join('nomina AS n','empleado.fkNomina', '=', 'n.idNomina',"left")
                                        ->join('centrocosto AS cc','cc.fkEmpresa', '=', 'n.fkEmpresa',"left")
                                        ->join('ubicacion AS u','u.idubicacion', '=', 'empleado.fkUbicacionLabora',"left")
                                        ->join('estado AS est','empleado.fkEstado', '=', 'est.idestado');
        $arrConsulta = array();

        if(isset($req->nombre)){
            $empleados->where(function($query) use($req){
                $query->where("dp.primerNombre","LIKE","%".$req->nombre."%")
                ->orWhere("dp.segundoNombre","LIKE","%".$req->nombre."%")
                ->orWhere("dp.primerApellido","LIKE","%".$req->nombre."%")
                ->orWhere("dp.segundoApellido","LIKE","%".$req->nombre."%");
            });
            $arrConsulta["nombre"] = $req->nombre;
        }
        if(isset($req->numDoc)){
            $empleados->where("dp.numeroIdentificacion", "LIKE", $req->numDoc."%");
            $arrConsulta["numDoc"] = $req->numDoc;
        }

        if(isset($req->empresa)){
            $empleados->where("empleado.fkEmpresa", "=", $req->empresa);
            $arrConsulta["empresa"] = $req->empresa;
        }
        else{
            $req->centroCosto = NULL;
        }

        if(isset($usu) && $usu->fkRol == 2){
            $empleados->whereIn("empleado.fkEmpresa", $usu->empresaUsuario);
        }
        
        if(isset($req->centroCosto)){
            $empleados->where("cc.idcentroCosto", "=", $req->centroCosto);
            $arrConsulta["centroCosto"] = $req->centroCosto;
        }


        if(isset($req->tipoPersona)){
            $empleados->where("empleado.tipoPersona", "=", $req->tipoPersona);
            $arrConsulta["tipoPersona"] = $req->tipoPersona;
        }
        if(isset($req->ciudad)){
            $empleados->where("empleado.fkUbicacionLabora", "=", $req->ciudad);
            $arrConsulta["ciudad"] = $req->ciudad;
        }
        if(isset($req->estado)){
            $empleados->where("empleado.fkEstado", "=", $req->estado);
        }
        else{
            $empleados->whereIn("empleado.fkEstado", ["2"]);
        }
        
        if(isset($req->centroCosto)){
            $empleados->join("empleado_centrocosto AS ec", "ec.fkEmpleado","=","empleado.idempleado");
            $empleados->where("ec.fkCentroCosto","=",$req->centroCosto);
            $arrConsulta["centroCosto"] = $req->centroCosto;
        }

        $empleados = $empleados->distinct()->get();
        $numResultados = sizeof($empleados);
        $empleados = $this->paginate($empleados, 15);
        $empleados->withPath("reintegro");

        
        
        $empresas = DB::table("empresa","e")->orderBy("razonSocial")->get();

        $centrosDeCosto = array();
        
        if(isset($req->empresa)){
            $centrosDeCosto = DB::table("centrocosto")->where("fkEmpresa","=",$req->empresa)->orderBy("nombre")->get();
        }
        
        $ciudades = DB::table("ubicacion")->where("fkTipoUbicacion","=","3")->orderBy("nombre")->get();
        $estados = DB::table("estado","e")->whereIn('e.idestado',[1,2,3])->get();
        
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu de reintegrar empleados");

        return view('/empleado.verEmpleadosReintegro',['empleados'=> $empleados, 
        'ciudades' => $ciudades,
         "req" => $req, 
         "arrConsulta" => $arrConsulta,
         "estados" => $estados,
         "numResultados" => $numResultados,
         "empresas" => $empresas,
         "centrosDeCosto" => $centrosDeCosto,
         'dataUsu' => $usu
        ]);
    }

    public function formReintegro($idEmpleado, Request $req){        
        $empleado = DB::table("empleado")->select(  'empleado.*', 'dp.*', 'u.usuario as usuarioTxt',
                                                    'ubi_dep_exp.idubicacion AS ubi_depto_exp', 'ubi_pa_exp.idubicacion AS ubi_pais_exp',
                                                    'ubi_dep_nac.idubicacion AS ubi_depto_nac', 'ubi_pa_nac.idubicacion AS ubi_pais_nac',
                                                    'ubi_dep_res.idubicacion AS ubi_depto_res', 'ubi_pa_res.idubicacion AS ubi_pais_res',
                                                    'ubi_dep_tra.idubicacion AS ubi_depto_tra', 'ubi_pa_tra.idubicacion AS ubi_pais_tra')
                                        ->join('datospersonales AS dp','empleado.fkDatosPersonales', '=', 'dp.idDatosPersonales',"left")                                        
                                        ->join("ubicacion AS ubi_ciud_exp", 'dp.fkUbicacionExpedicion', '=', 'ubi_ciud_exp.idubicacion',"left")
                                        ->join("ubicacion AS ubi_dep_exp", 'ubi_ciud_exp.fkUbicacion', '=', 'ubi_dep_exp.idubicacion',"left")
                                        ->join("ubicacion AS ubi_pa_exp", 'ubi_dep_exp.fkUbicacion', '=', 'ubi_pa_exp.idubicacion',"left")

                                        ->join("ubicacion AS ubi_ciud_nac", 'dp.fkUbicacionNacimiento', '=', 'ubi_ciud_nac.idubicacion',"left")
                                        ->join("ubicacion AS ubi_dep_nac", 'ubi_ciud_nac.fkUbicacion', '=', 'ubi_dep_nac.idubicacion',"left")
                                        ->join("ubicacion AS ubi_pa_nac", 'ubi_dep_nac.fkUbicacion', '=', 'ubi_pa_nac.idubicacion',"left")

                                        ->join("ubicacion AS ubi_ciud_res", 'dp.fkUbicacionResidencia', '=', 'ubi_ciud_res.idubicacion',"left")
                                        ->join("ubicacion AS ubi_dep_res", 'ubi_ciud_res.fkUbicacion', '=', 'ubi_dep_res.idubicacion',"left")
                                        ->join("ubicacion AS ubi_pa_res", 'ubi_dep_res.fkUbicacion', '=', 'ubi_pa_res.idubicacion',"left")
                                        
                                        ->join("ubicacion AS ubi_ciud_tra", 'empleado.fkUbicacionLabora', '=', 'ubi_ciud_tra.idubicacion',"left")
                                        ->join("ubicacion AS ubi_dep_tra", 'ubi_ciud_tra.fkUbicacion', '=', 'ubi_dep_tra.idubicacion',"left")
                                        ->join("ubicacion AS ubi_pa_tra", 'ubi_dep_tra.fkUbicacion', '=', 'ubi_pa_tra.idubicacion',"left")

                                        ->join("usuario AS u", 'u.idusuario','=','empleado.fkUsuario',"left")

                                        ->where('idempleado', $idEmpleado)
                                        ->first();
        


        $paises = Ubicacion::where("fkTipoUbicacion", "=" ,'1')->get();
        $deptosExp = array();
        $ciudadesExp= array();

        if(isset($empleado->ubi_pais_exp)){
            $deptosExp = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_pais_exp)->get();
            $ciudadesExp = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_depto_exp)->get();    
        }
        
        $deptosNac = array();
        $ciudadesNac= array();
        if(isset($empleado->ubi_pais_nac)){
            $deptosNac = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_pais_nac)->get();
            $ciudadesNac = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_depto_nac)->get();
        }

        $deptosRes = array();
        $ciudadesRes= array();
        if(isset($empleado->ubi_pais_res)){
            $deptosRes = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_pais_res)->get();
            $ciudadesRes = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_depto_res)->get();
        }

        $deptosTra = array();
        $ciudadesTra= array();

        if(isset($empleado->ubi_pais_tra)){
            $deptosTra = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_pais_tra)->get();
            $ciudadesTra = Ubicacion::where("fkUbicacion", "=", $empleado->ubi_depto_tra)->get();    
        }




        $generos = DB::table("genero")->get();
        $estadosCivil = DB::table("estadocivil")->get();
        $tipo_vivienda = DB::table("tipo_vivienda")->get();
        $grupoSanguineo = DB::table("gruposanguineo")->get();
        $rhs = DB::table("rh")->get();
        $tipoidentificacion = DB::table("tipoidentificacion")->where("tipo", "=", "0")->get();

        $contactosEmergencia = DB::table("contactoemergencia")->select(
            "contactoemergencia.*",
            'ubi_dep_emer.idubicacion AS ubi_depto_emer', 
            'ubi_pa_emer.idubicacion AS ubi_pais_emer'
        )
        ->join("ubicacion AS ubi_ciud_emer", 'contactoemergencia.fkUbicacion', '=', 'ubi_ciud_emer.idubicacion')
        ->join("ubicacion AS ubi_dep_emer", 'ubi_ciud_emer.fkUbicacion', '=', 'ubi_dep_emer.idubicacion')
        ->join("ubicacion AS ubi_pa_emer", 'ubi_dep_emer.fkUbicacion', '=', 'ubi_pa_emer.idubicacion')
        ->where("fkDatosEmpleado", "=", $empleado->idDatosPersonales)->get();
        $deptosContactosEmergencia = array();
        $ciudadesContactosEmergencia = array();
        foreach($contactosEmergencia as $contactoEmergencia){
            
            $deptosEmer = Ubicacion::where("fkUbicacion", "=", $contactoEmergencia->ubi_pais_emer)->get();
            $ciudadesEmer = Ubicacion::where("fkUbicacion", "=", $contactoEmergencia->ubi_depto_emer)->get();

            array_push($deptosContactosEmergencia, $deptosEmer);
            array_push($ciudadesContactosEmergencia, $ciudadesEmer);

        }

        $nucleofamiliar = DB::table("nucleofamiliar")->where("fkDatosEmpleado", "=", $empleado->idDatosPersonales)->get();
        

        
        $parentescos = DB::table("parentesco")->get();
        $escolaridades = DB::table("escolaridad")->get();



        $empresas = DB::table("empresa")->orderBy("razonSocial")->get();
        $centrosCosto = array();
        if(isset($empleado->fkEmpresa)){
            $centrosCosto = DB::table("centrocosto")->where("fkEmpresa","=",$empleado->fkEmpresa)->get();
        }

        $tipoContratos = DB::table('tipocontrato')->get();
        $cargos = DB::table("cargo")->get();
        $entidadesFinancieras = DB::table("tercero")->where("fk_actividad_economica", "=", "4")->orderBy("razonSocial")->get();
        
        $periodoActivo = DB::table("periodo")
        ->where("fkEmpleado","=",$idEmpleado)
        ->orderBy("idPeriodo", "desc")
        ->first();

        $afiliaciones = DB::table("afiliacion")->where('fkEmpleado', "=", $idEmpleado)
        ->where("fkPeriodoActivo","=",$periodoActivo->idPeriodo)
        ->get();

        $tipoafilicaciones = DB::table('tipoafilicacion')->get();
        $entidadesAfiliacion = array();
        foreach($afiliaciones as $afiliacion){
            $afiliacionesEnt = DB::table("tercero")
                    ->join('tipoafilicacion AS ta','ta.fkActividadEconomica', '=', 'tercero.fk_actividad_economica')
                    ->where("ta.idTipoAfiliacion", "=", $afiliacion->fkTipoAfilicacion)->orderBy("razonSocial")->get();

            $entidadesAfiliacion[$afiliacion->idAfiliacion] = $afiliacionesEnt;
        }
        

        $nivelesArl = DB::table('nivel_arl')->get();

        $afiliacionesEnt1 = DB::table("tercero")
                    ->join('tipoafilicacion AS ta','ta.fkActividadEconomica', '=', 'tercero.fk_actividad_economica')
                    ->where("ta.idTipoAfiliacion", "=", '1')
                    ->whereNotIn("tercero.idTercero",["10","109","111","112","113"])
                    ->orderBy("razonSocial")
                    ->get();
        $afiliacionesEnt2 = DB::table("tercero")
                    ->join('tipoafilicacion AS ta','ta.fkActividadEconomica', '=', 'tercero.fk_actividad_economica')
                    ->where("ta.idTipoAfiliacion", "=", '2')
                    ->orderBy("razonSocial")
                    ->get();
        $afiliacionesEnt3 = DB::table("tercero")
                    ->join('tipoafilicacion AS ta','ta.fkActividadEconomica', '=', 'tercero.fk_actividad_economica')
                    ->where("ta.idTipoAfiliacion", "=", '3')
                    ->orderBy("razonSocial")
                    ->get();
        $afiliacionesEnt4 = DB::table("tercero")
                    ->join('tipoafilicacion AS ta','ta.fkActividadEconomica', '=', 'tercero.fk_actividad_economica')
                    ->where("ta.idTipoAfiliacion", "=", '4')->whereNotIn("tercero.idTercero",["120"])
                    ->orderBy("razonSocial")
                    ->get();

        $conceptosFijos = DB::table('conceptofijo', 'cf')->select(["cf.*", "c.nombre AS nombreConcepto"])
        ->join("concepto AS c", "c.idConcepto", "=", "cf.fkConcepto")
        ->where("cf.fkEmpleado", "=", $idEmpleado)->get();

        $contratoActivo = DB::table('contrato')->where("fkEmpleado","=",$idEmpleado)->whereIn("fkEstado",array("1","4"))->first();

        $conceptos = DB::table("concepto")->whereNotIn("idconcepto", [1,2])->orderBy("nombre")->get();

        $centrosCostoxEmpleado = DB::table("empleado_centrocosto")
        ->where("fkEmpleado","=", $idEmpleado)
        ->where("fkPeriodoActivo","=", $periodoActivo->idPeriodo)
        ->get();
        $centrosCostos = array();
        $nominas = array();

        if(isset($empleado->fkEmpresa)){
            $centrosCostos = DB::table("centrocosto")->where("fkEmpresa","=", $empleado->fkEmpresa)->get();
            $nominas = DB::table("nomina")->where("fkEmpresa","=", $empleado->fkEmpresa)->orderBy("nombre")->get();
            
            
        }
        $beneficiosTributarios = DB::table("beneficiotributario", "bt")
        ->select('bt.*', 'nf.*', 'ubi_dep_benef.idubicacion AS ubi_depto_benef', 'ubi_pa_benef.idubicacion AS ubi_pais_benef')
            ->join("nucleofamiliar AS nf", 'nf.idNucleoFamiliar', '=', 'bt.fkNucleoFamiliar', 'left')
            ->join("ubicacion AS ubi_ciud_benef", 'nf.fkUbicacion', '=', 'ubi_ciud_benef.idubicacion', 'left')
            ->join("ubicacion AS ubi_dep_benef", 'ubi_ciud_benef.fkUbicacion', '=', 'ubi_dep_benef.idubicacion', 'left')
            ->join("ubicacion AS ubi_pa_benef", 'ubi_dep_benef.fkUbicacion', '=', 'ubi_pa_benef.idubicacion', 'left')
        ->where("fkEmpleado", "=", $idEmpleado)->get();
        
        
        $deptosBeneficiosTributarios = array();
        $ciudadesBeneficiosTributarios = array();
        foreach($beneficiosTributarios as $beneficioTributario){            
            $deptosBen = Ubicacion::where("fkUbicacion", "=", $beneficioTributario->ubi_pais_benef)->get();
            $ciudadesBen = Ubicacion::where("fkUbicacion", "=", $beneficioTributario->ubi_depto_benef)->get();
            array_push($deptosBeneficiosTributarios, $deptosBen);
            array_push($ciudadesBeneficiosTributarios, $ciudadesBen);
        }


        $destino = "infoLab";
        if(isset($req->destino)){
            $destino = $req->destino;
        }

        $generosBen = DB::table("genero")->whereIn("idGenero",["1","2"])->get();
        $tipobeneficio = DB::table("tipobeneficio")->orderBy("nombre")->get();

        $cambiosAfiliacion = DB::table("cambioafiliacion","ca")
        ->where("ca.fkEmpleado", "=", $empleado->idempleado)->get();

        $afiliacionesNuevas = array();
        foreach($cambiosAfiliacion as $cambioAfiliacion){
            $afiliacionesNuevas[$cambioAfiliacion->fkAfiliacion] = $cambioAfiliacion;
        }

        $cambioSalario = DB::table("cambiosalario","cs")
        ->where('cs.fkEmpleado', "=", $empleado->idempleado)
        ->where('cs.fkEstado', "=", "4")
        ->orderBy("idCambioSalario","desc")->first();

        $subtiposcotizante = DB::table("subtipocotizante")->get();
        
        $upcadicional = DB::table("upcadicional", 'nf')
        ->select('nf.*', 'ubi_dep_upc.idubicacion AS ubi_depto_upc', 'ubi_pa_upc.idubicacion AS ubi_pais_upc')
        ->join("ubicacion AS ubi_ciud_upc", 'nf.fkUbicacion', '=', 'ubi_ciud_upc.idubicacion', 'left')
        ->join("ubicacion AS ubi_dep_upc", 'ubi_ciud_upc.fkUbicacion', '=', 'ubi_dep_upc.idubicacion', 'left')
        ->join("ubicacion AS ubi_pa_upc", 'ubi_dep_upc.fkUbicacion', '=', 'ubi_pa_upc.idubicacion', 'left')
        ->where("nf.fkEmpleado", "=", $empleado->idempleado)->get();
        $deptosUpc = array();
        $ciudadesUpc = array();
        foreach($upcadicional as $row => $upc){
            $deptosBen = Ubicacion::where("fkUbicacion", "=", $upc->ubi_pais_upc)->get();
            $ciudadesBen = Ubicacion::where("fkUbicacion", "=", $upc->ubi_depto_upc)->get();
            $deptosUpc[$row] = $deptosBen;
            $ciudadesUpc[$row] = $ciudadesBen;
        }
        $existe = false;
        $existeUsuario = User::where('fkEmpleado', $idEmpleado)->first();
        if ($existeUsuario) {
            $existe = true;
        }
        $nivelesEstudios = DB::table("nivel_estudio")->get();
        $etnias = DB::table("etnia")->get();

        $centrosTrabajo = DB::table("centrotrabajo")->get();

        $usu = UsuarioController::dataAdminLogueado();

        $periodoActivo = DB::table("periodo")
        ->where("fkEmpleado","=",$req->idEmpleado)
        ->where("fkEstado","=","1")->first();

        $tiposcotizante = DB::table("tipo_cotizante")->get();


        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al formulario de reintegrar empleado");


        return view('/empleado.reintegroEmpleado', [
            'tiposcotizante' => $tiposcotizante,
            'paises'=>$paises,
            'dataUsu' => $usu,
            'usuExiste' => $existe,
            'generos' => $generos, 
            'estadosCivil' => $estadosCivil, 
            'tipo_vivienda' => $tipo_vivienda,
            'grupoSanguineo' => $grupoSanguineo,
            'rhs' => $rhs,
            'tipoidentificacion' => $tipoidentificacion,
            'empleado' => $empleado,
            'deptosExp' => $deptosExp,
            'ciudadesExp' => $ciudadesExp,
            'deptosNac' => $deptosNac,
            'ciudadesNac' => $ciudadesNac,
            'deptosRes' => $deptosRes,
            'ciudadesRes' => $ciudadesRes,
            'contactosEmergencia' => $contactosEmergencia,
            'deptosContactosEmergencia' => $deptosContactosEmergencia,
            'ciudadesContactosEmergencia' => $ciudadesContactosEmergencia,
            'nucleofamiliar' => $nucleofamiliar,
            'parentescos' => $parentescos,
            'escolaridades' => $escolaridades,
            'empresas' => $empresas,
            'centrosCosto' => $centrosCosto,
            'tipoContratos' => $tipoContratos,
            'idEmpleado' => $idEmpleado, 
            'cargos' => $cargos, 
            'entidadesFinancieras' => $entidadesFinancieras,
            'afiliaciones' => $afiliaciones,
            'tipoafilicaciones' => $tipoafilicaciones,
            'nivelesArl' => $nivelesArl,
            'afiliacionesEnt1' => $afiliacionesEnt1,
            'afiliacionesEnt2' => $afiliacionesEnt2,
            'afiliacionesEnt3' => $afiliacionesEnt3,
            'afiliacionesEnt4' => $afiliacionesEnt4,
            'entidadesAfiliacion' => $entidadesAfiliacion,
            'conceptosFijos' => $conceptosFijos,
            'contratoActivo' => $contratoActivo,
            'conceptos' => $conceptos,
            'destino' => $destino,
            'centrosCostoxEmpleado' => $centrosCostoxEmpleado,
            'centrosCostos' => $centrosCostos,
            'nominas' => $nominas,
            'deptosTra' => $deptosTra,
            'ciudadesTra' =>$ciudadesTra,
            'beneficiosTributarios' => $beneficiosTributarios,
            'generosBen' => $generosBen,
            'tipobeneficio' => $tipobeneficio,
            'deptosBeneficiosTributarios' => $deptosBeneficiosTributarios,
            'ciudadesBeneficiosTributarios' => $ciudadesBeneficiosTributarios,
            'afiliacionesNuevas' => $afiliacionesNuevas,
            'cambioSalario' => $cambioSalario,
            'subtiposcotizante' => $subtiposcotizante,
            'upcAdicional' => $upcadicional,
            'deptosUpc' => $deptosUpc,
            'ciudadesUpc' => $ciudadesUpc,
            'nivelesEstudios' => $nivelesEstudios,
            'etnias' => $etnias,
            "centrosTrabajo" => $centrosTrabajo,
            "periodoActivo" => $periodoActivo
        ]);


    }

    public function paginate($items, $perPage = 5, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    }

    public function agregarUsuariosaEmpleados(){
        $empleados = DB::table("empleado","e")
        ->select("dp.*","emp.dominio","e.idempleado")
        ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
        ->join("empresa as emp","emp.idempresa", "=","e.fkEmpresa")
        ->whereRaw("e.idempleado not in(Select u.fkEmpleado from users u WHERE u.fkEmpleado = e.idempleado)")->get();
        
        $i = 0;
        foreach($empleados as $empleado){
            if(strpos($empleado->dominio,"@")===false){
                $empleado->dominio = "@".$empleado->dominio;
            }
            $infoUsuario = $empleado->numeroIdentificacion;
            
            $usuarioNuevo = new User;
            $usuarioNuevo->email = $infoUsuario;
            $usuarioNuevo->username = $infoUsuario;
            $usuarioNuevo->password = ($empleado->numeroIdentificacion."#".substr($empleado->fechaNacimiento,0,4));
            $usuarioNuevo->fkRol = 1;
            $usuarioNuevo->estado = 1;
            $usuarioNuevo->fkEmpleado = $empleado->idempleado;
            $usuarioNuevo->save();
            $i++;
        }
        $usu = UsuarioController::dataAdminLogueado();
        $mensaje = "Se crearon y vincularion ".$i." empleados"; 
        $titulo = "Usuarios creados";
        return view("/layouts/respuestaGen",[
            "mensaje" => $mensaje,
            "dataUsu" => $usu,
            "titulo" => $titulo
        ]);
    }

    public function verPeriodos($idEmpleado){
        $periodos = DB::table("periodo")
        ->select("periodo.*",
        "cargo.nombreCargo","tipocontrato.nombre as nombreTipoContrato","n.nombre as nombreNomina", 
        "emp.razonSocial as nombreEmpresa", "cf.valor as conceptoFValor")
        ->leftJoin("cargo","cargo.idCargo","=","periodo.fkCargo")
        ->leftJoin("tipocontrato","tipocontrato.idtipoContrato","=","periodo.fkTipoContrato")
        ->leftJoin("nomina as n", "n.idNomina","=","periodo.fkNomina")
        ->leftJoin("empresa as emp", "emp.idempresa","=","n.fkEmpresa")
        ->leftJoin('conceptofijo as cf', function ($join) {
            $join->on('cf.fkPeriodoActivo', '=', 'idPeriodo')
            ->whereIn("cf.fkConcepto",[1,2]);
        })
        ->where("periodo.fkEmpleado", "=", $idEmpleado)
        ->orderBy("idPeriodo","desc")
        ->get();
        

        $empleado = DB::table("empleado", "e")
        ->select("e.*","cargo.nombreCargo","n.nombre as nombreNomina",  "emp.razonSocial as nombreEmpresa")
        ->leftJoin("cargo","cargo.idCargo","=","e.fkCargo")
        ->leftJoin("nomina as n", "n.idNomina","=","e.fkNomina")
        ->leftJoin("empresa as emp", "emp.idempresa","=","n.fkEmpresa")
        ->where("e.idempleado","=",$idEmpleado)        
        ->first();
        
        $tipoContrato = DB::table("contrato","con")
        ->select("con.*","tipocontrato.nombre as nombreTipoContrato")
        ->leftJoin("tipocontrato","tipocontrato.idtipoContrato","=","con.fkTipoContrato")
        ->where("con.fkEmpleado","=",$idEmpleado)
        ->whereIn("con.fkEstado",["1","4"])
        ->orderBy("con.idcontrato","desc")
        ->first();
        
        $conceptoFijo = DB::table("conceptofijo")->where("fkEmpleado", "=",$idEmpleado)->whereIn("fkConcepto",[1,2])->first();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", consultó los periodos del empleado con id:".$idEmpleado);

        return view("/empleado/ajax/verPeriodos",[
            "periodos" => $periodos,
            "empleado" => $empleado,
            "conceptoFijo" => $conceptoFijo,
            "tipoContrato" => $tipoContrato
        ]);

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
