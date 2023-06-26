<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use League\Csv\Writer;
use SplTempFileObject;
use DateTime;
use DateInterval;
use Exception;
use League\Csv\Reader;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;


class NominaController extends Controller
{
    

    public function solicitudLiquidacion(Request $req){
        $usu = UsuarioController::dataAdminLogueado();
        $liquidaciones = DB::table("liquidacionnomina", "ln")
        ->select(["ln.idLiquidacionNomina", "ln.fechaLiquida", "e.razonSocial", "tl.nombre as tipoLiquidacion", "est.nombre as estado","n.nombre as nomNomina"])
        ->join("nomina AS n","ln.fkNomina", "=", "n.idnomina")
        ->join("empresa AS e","n.fkEmpresa","=", "e.idempresa")
        ->join("tipoliquidacion AS tl","ln.fkTipoLiquidacion","=", "tl.idTipoLiquidacion")        
        ->join("estado AS est","ln.fkEstado","=", "est.idestado")
        ->where("ln.fkEstado", "<>", "5")
        ->where("ln.fkEstado", "<>", "8");
        if(isset($req->fechaInicio)){
            $liquidaciones = $liquidaciones->where("ln.fechaLiquida",">=",$req->fechaInicio);
        }
        
        if(isset($usu) && $usu->fkRol == 2){
            $liquidaciones = $liquidaciones->whereIn("n.fkEmpresa", $usu->empresaUsuario);
        }

        if(isset($req->fechaFin)){
            $fechaBusquedaFin = $req->fechaFin;
            if(substr($fechaBusquedaFin,8,2) == "30" && substr(date("Y-m-t",strtotime($fechaBusquedaFin)),8,2)=="31"){
                $fechaBusquedaFin = date("Y-m-t",strtotime($fechaBusquedaFin));
            }
            $liquidaciones = $liquidaciones->where("ln.fechaLiquida","<=",$fechaBusquedaFin);
        }

        if(isset($req->nomina)){
            $liquidaciones = $liquidaciones->where("ln.fkNomina","=",$req->nomina);
        }
        if(isset($req->tipoLiquidacion)){
            $liquidaciones = $liquidaciones->where("ln.fkTipoLiquidacion","=",$req->tipoLiquidacion);
        }

        $liquidaciones = $liquidaciones->get();
        $nominas = DB::table("nomina");
        if(isset($usu) && $usu->fkRol == 2){            
            $nominas = $nominas->whereIn("fkEmpresa", $usu->empresaUsuario);
        }
        $nominas = $nominas->orderBy("nombre")->get();


        $tipoLiquidaciones = DB::table("tipoliquidacion")->orderBy("nombre")->get();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu 'Solicitudes de liquidacion'");

        return view('/nomina.solicitudes.listaSolicitudes',[
            'liquidaciones' => $liquidaciones,
            "nominas" => $nominas,
            "tipoLiquidaciones" => $tipoLiquidaciones,
            "req" => $req,
            "dataUsu" => $usu
        ]);
    }
    
    public function centroCostoPeriodo(){

        $dataUsu = UsuarioController::dataAdminLogueado();
        $distri_centro_costo = DB::table("distri_centro_costo", "d")
        ->join("nomina as n", "n.idNomina", "=","d.fkNomina")
        ->where("fkEstado", "=","1");
        if(isset($dataUsu) && $dataUsu->fkRol == 2){
            $distri_centro_costo->whereIn("n.fkEmpresa", $dataUsu->empresaUsuario);
        }
        $distri_centro_costo = $distri_centro_costo->paginate(15);

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu 'Centros de costo por periodo'");

        return view('/nomina.distri.distribucionCentroCosto',[
            'distris_centro_costo' => $distri_centro_costo,
            "dataUsu" => $dataUsu
        ]);
    }
    public function centroCostoPeriodoFormAdd(){
        $dataUsu = UsuarioController::dataAdminLogueado();
        $nominas = DB::table("nomina");
        if(isset($dataUsu) && $dataUsu->fkRol == 2){            
            $nominas = $nominas->whereIn("fkEmpresa", $dataUsu->empresaUsuario);
        }
        $nominas = $nominas->orderBy("nombre")->get();



        return view('/nomina.distri.addDistri',[
            'nominas' => $nominas,
            "dataUsu" => $dataUsu
        ]);
    }
    public function insertDistri(Request $req){
        $messages = [
            'required' => 'El campo :attribute es requerido.',
            'after_or_equal' => 'La :attribute debe ser mayor a la inicial.',
        ];
        $validator = Validator::make($req->all(), [
            'fkNomina' => 'required',
            'fechaInicio' => 'required|date',
            'fechaFin' => 'required|date',
        ],$messages);

        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()->all()]);
        }

        $arrDistri = [
            "fkNomina" => $req->fkNomina,
            "fechaInicio" => $req->fechaInicio,
            "fechaFin" => $req->fechaFin,
        ];
        $idDistri = DB::table("distri_centro_costo")->insertGetId($arrDistri,  "id_distri_centro_costo");
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", agregó una nueva distribución por centro de costo");
        return response()->json([
            "success" => true,
            "idDistri" => $idDistri
        ]);
    }
    
    public function modificarDistriIndex($idDistri){
        $dataUsu = UsuarioController::dataAdminLogueado();
        $distri = DB::table("distri_centro_costo", "d")
        ->join("nomina as n", "n.idNomina", "=","d.fkNomina")
        ->where("d.id_distri_centro_costo", "=",$idDistri)
        ->first();
        $empleados = DB::table("empleado", "e")
        ->select("e.idempleado", "p.idPeriodo", "dp.primerNombre", "dp.segundoNombre", "dp.primerApellido", "dp.segundoApellido", 
                "dp.numeroIdentificacion", "ti.nombre as tipoDocumento")
        ->join("periodo as p","p.fkEmpleado", "=","e.idempleado")
        ->join("distri_centro_costo as d", "d.fkNomina", "=","p.fkNomina")
        ->join("datospersonales as dp", "dp.idDatosPersonales", "=", "e.fkDatosPersonales")
        ->join("tipoidentificacion as ti", "ti.idtipoIdentificacion", "=","dp.fkTipoIdentificacion")
        ->where("d.id_distri_centro_costo", "=",$idDistri)
        ->orderByRaw("dp.primerApellido, dp.segundoApellido, dp.primerNombre, dp.segundoNombre")
        ->get();

        $centrosCostoGen = DB::table("centrocosto","cc")
        ->select("cc.*")
        ->join("nomina as n", "n.fkEmpresa", "=","cc.fkEmpresa")
        ->join("distri_centro_costo as d", "d.fkNomina", "=","n.idNomina")
        ->where("d.id_distri_centro_costo", "=",$idDistri)
        ->orderBy("cc.id_uni_centro")
        ->get();

        $arrEmpleadoCC = array();
        foreach($empleados as $empleado){

            $centrosCostoEmpleado = DB::table("distri_centro_costo_centrocosto", "ddc")
            ->where("ddc.fkEmpleado","=",$empleado->idempleado)
            ->where("ddc.fkDistribucion","=",$idDistri)
            ->get();
            $arrCentrosCosto = array();

            if(sizeof($centrosCostoEmpleado) > 0){
                foreach($centrosCostoEmpleado as $centroCostoEmpleado ){
                    array_push($arrCentrosCosto, [
                        "centroCosto" => $centroCostoEmpleado->fkCentroCosto,
                        "porcentaje" => $centroCostoEmpleado->porcentaje
                    ]);
                }
            }
            else{
                $centrosCosto = DB::table("empleado_centrocosto", "ecc")
                ->where("ecc.fkEmpleado", "=",$empleado->idempleado)
                ->where("ecc.fkPeriodoActivo", "=",$empleado->idPeriodo)
                ->get();
                foreach($centrosCosto as $centroCosto){
                    array_push($arrCentrosCosto, [
                        "centroCosto" => $centroCosto->fkCentroCosto,
                        "porcentaje" => $centroCosto->porcentajeTiempoTrabajado
                    ]);
                }
            }

            $arrEmpleadoCC[$empleado->idempleado] = $arrCentrosCosto;
        }

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó a la acción 'Modificar centro de costo'");
        return view('/nomina.distri.modDistri',[
            'distri' => $distri,
            'arrEmpleadoCC' => $arrEmpleadoCC,
            'centrosCostoGen' => $centrosCostoGen,
            "empleados" => $empleados,
            "dataUsu" => $dataUsu     
        ]);
    }
    public function editarDistriEm($idEmpleado, $idDistri){
        $centrosCostoGen = DB::table("centrocosto","cc")
        ->select("cc.*")
        ->join("nomina as n", "n.fkEmpresa", "=","cc.fkEmpresa")
        ->join("distri_centro_costo as d", "d.fkNomina", "=","n.idNomina")
        ->where("d.id_distri_centro_costo", "=",$idDistri)
        ->orderBy("cc.id_uni_centro")
        ->get();

        $centrosCostoEmpleado = DB::table("distri_centro_costo_centrocosto", "ddc")
        ->where("ddc.fkEmpleado","=",$idEmpleado)
        ->where("ddc.fkDistribucion","=",$idDistri)
        ->get();
        $arrCentrosCosto = array();

        if(sizeof($centrosCostoEmpleado) > 0){
            foreach($centrosCostoEmpleado as $centroCostoEmpleado ){
                array_push($arrCentrosCosto, [
                    "centroCosto" => $centroCostoEmpleado->fkCentroCosto,
                    "porcentaje" => $centroCostoEmpleado->porcentaje
                ]);
            }
        }
        else{
            $centrosCosto = DB::table("empleado_centrocosto", "ecc")
            ->where("ecc.fkEmpleado", "=",$idEmpleado)            
            ->get();
            foreach($centrosCosto as $centroCosto){
                array_push($arrCentrosCosto, [
                    "centroCosto" => $centroCosto->fkCentroCosto,
                    "porcentaje" => $centroCosto->porcentajeTiempoTrabajado
                ]);
            }
        }

        $arrEmpleadoCC = $arrCentrosCosto;
        
        $dataUsu = UsuarioController::dataAdminLogueado();
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó a la acción 'Modificar centro de costo para empleado'");

        return view('/nomina.distri.editarDistriEm',[
            'centrosCostoGen' => $centrosCostoGen,   
            'arrEmpleadoCC' => $arrEmpleadoCC,
            "idDistri" => $idDistri,
            "idEmpleado" => $idEmpleado,
            "dataUsu" => $dataUsu
        ]);
    }
    
    public function modDistriEmp(Request $req){
        $porcentajeTotal = 0;
        foreach($req->idCentroCosto as $row => $idCentroCosto){
            $porcentajeTotal = $porcentajeTotal + $req->porcentajeCentro[$row];
        }
        if($porcentajeTotal != 100){
            return response()->json([
                "success" => false, 
                "mensaje" => "La suma de porcentajes no es un 100%"
            ]);
        }
        foreach($req->idCentroCosto as $row => $idCentroCosto){
            $dcCentroCosto = DB::table("distri_centro_costo_centrocosto", "dcc")
            ->where("dcc.fkEmpleado", "=",$req->idEmpleado)
            ->where("dcc.fkDistribucion", "=",$req->idDistri)
            ->where("dcc.fkCentroCosto", "=",$idCentroCosto)
            ->first();
            $arrDCC= [
                "fkEmpleado" => $req->idEmpleado,
                "fkDistribucion" => $req->idDistri,
                "fkCentroCosto" => $idCentroCosto,
                "porcentaje" => $req->porcentajeCentro[$row],
            ];
            if(isset($dcCentroCosto)){
                DB::table("distri_centro_costo_centrocosto")
                ->where("idDistriCentroCentro", "=", $dcCentroCosto->idDistriCentroCentro)
                ->update($arrDCC);
            }
            else{
                DB::table("distri_centro_costo_centrocosto")
                ->insert($arrDCC);
            }
        }
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", modificó un centro de costo para el empleado:".$req->idEmpleado);
        return response()->json([
            "success" => true
        ]);
    }

    public function modificarDistribucion(Request $req){
        $messages = [
            'required' => 'El campo :attribute es requerido.',
            'after_or_equal' => 'La :attribute debe ser mayor a la inicial.',
        ];
        $validator = Validator::make($req->all(), [
            'fkNomina' => 'required',
            'fechaInicio' => 'required|date',
            'fechaFin' => 'required|date',
        ],$messages);

        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()->all()]);
        }

        $arrDistri = [
            "fechaInicio" => $req->fechaInicio,
            "fechaFin" => $req->fechaFin,
        ];
        DB::table("distri_centro_costo")->where("id_distri_centro_costo", "=",$req->id_distri_centro_costo)->update($arrDistri);
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", modificó las fechas para una distribucion de centros de costo");
        return response()->json([
            "success" => true,
        ]);
    }
    
    public function copiarDistri($idDistri){
        $distri = DB::table("distri_centro_costo", "d")
        ->join("nomina as n", "n.idNomina", "=","d.fkNomina")
        ->where("d.id_distri_centro_costo", "=",$idDistri)
        ->first();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu de copiar distribución de centros de costo");
        return view('/nomina.distri.copyDistri',[
            'distri' => $distri        
        ]);
    }

    public function copyDistriBd(Request $req){
        $messages = [
            'required' => 'El campo :attribute es requerido.',
            'after_or_equal' => 'La :attribute debe ser mayor a la inicial.',
        ];
        $validator = Validator::make($req->all(), [
            'fkNomina' => 'required',
            'fechaInicio' => 'required|date',
            'fechaFin' => 'required|date',
        ],$messages);

        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()->all()]);
        }

        $sqlWhere = "( 
            ('".$req->fechaInicio."' BETWEEN dcc.fechaInicio AND dcc.fechaFin) OR
            ('".$req->fechaFin."' BETWEEN dcc.fechaInicio AND dcc.fechaFin) OR
            (dcc.fechaInicio BETWEEN '".$req->fechaInicio."' AND '".$req->fechaFin."') OR
            (dcc.fechaFin BETWEEN '".$req->fechaInicio."' AND '".$req->fechaFin."')
        )";

        $comparteFecha =  DB::table("distri_centro_costo","dcc")
        ->where("dcc.fkNomina", "=",$req->fkNomina)
        ->whereRaw($sqlWhere)
        ->first();
        if(isset($comparteFecha)){
            return response()->json(['succes'=>false, "mensaje" => "La nueva distribucion comparte fechas con otra"]);
        }



        $arrDistri = [
            "fkNomina" => $req->fkNomina,
            "fechaInicio" => $req->fechaInicio,
            "fechaFin" => $req->fechaFin,
        ];
        $idDistri = DB::table("distri_centro_costo")->insertGetId($arrDistri,  "id_distri_centro_costo");

        $distri_centro_costo_centrocosto = DB::table("distri_centro_costo_centrocosto", "dcc")
        ->where("dcc.fkDistribucion","=",$req->id_distri_centro_costo)
        ->get();

        foreach($distri_centro_costo_centrocosto as $distri_centro_costo_centro){
            $arrDCC= [
                "fkEmpleado" => $distri_centro_costo_centro->fkEmpleado,
                "fkDistribucion" => $idDistri,
                "fkCentroCosto" => $distri_centro_costo_centro->fkCentroCosto,
                "porcentaje" => $distri_centro_costo_centro->porcentaje,
            ];
            DB::table("distri_centro_costo_centrocosto")->insert($arrDCC);
        }

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", copió una distribución de centros de costo");

        return response()->json([
            "success" => true            
        ]);
    }
    
    public function subirPlano(Request $req){

        $idDistri = $req->id_distri_centro_costo;
        $distri = DB::table("distri_centro_costo")->where("id_distri_centro_costo","=",$idDistri)->first();
        
        $errors = array();

        
        $file = $req->file('archivoCSV')->get();
        $file = str_replace("\r","\n",$file);
        $reader = Reader::createFromString($file);
        $reader->setDelimiter(';');
        $arrCentroCosto = array();
        foreach($reader as $row => $read){
            if($row == 0){
                foreach($read as $idCol =>$cols){
                    if($idCol > 1){
                        $centroCostoEmpresa = DB::table("centrocosto","cc")
                        ->join("empresa as e", "e.idempresa", "=","cc.fkEmpresa")
                        ->join("nomina as n", "n.fkEmpresa", "=","e.idempresa")                        
                        ->where("n.idNomina", "=",$distri->fkNomina)
                        ->where("cc.idcentroCosto", "=",$cols)
                        ->get();
                        if(sizeof($centroCostoEmpresa)>0){
                            $arrCentroCosto[$idCol] = $cols;
                        }
                    }
                }
            }
            else{
 
                $existeEmpleado = DB::table("empleado","e")
                ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
                ->where("dp.numeroIdentificacion","=", $read[1])
                ->where("dp.fkTipoIdentificacion","=", $read[0])
                ->first();
                if(isset($existeEmpleado)){
                    $porcentaje = 0;

                    foreach($read as $idCol =>$cols){
                       
                    
                        if($idCol > 1 && isset($arrCentroCosto[$idCol])){
                            $porcentaje = $porcentaje + floatval($cols);
                        }
                    }
                    
                    
                     
                    if($porcentaje == 100){
                        foreach($read as $idCol =>$cols){
                            
                            if($idCol > 1  && isset($arrCentroCosto[$idCol])){
                                $dcCentroCosto = DB::table("distri_centro_costo_centrocosto", "dcc")
                                ->where("dcc.fkEmpleado", "=",$existeEmpleado->idempleado)
                                ->where("dcc.fkDistribucion", "=",$idDistri)
                                ->where("dcc.fkCentroCosto", "=",$arrCentroCosto[$idCol])
                                ->first();
                                $arrDCC= [
                                    "fkEmpleado" => $existeEmpleado->idempleado,
                                    "fkDistribucion" => $idDistri,
                                    "fkCentroCosto" => $arrCentroCosto[$idCol],
                                    "porcentaje" => floatval($cols),
                                ];
                                if(isset($dcCentroCosto)){
                                    DB::table("distri_centro_costo_centrocosto")
                                    ->where("idDistriCentroCentro", "=", $dcCentroCosto->idDistriCentroCentro)
                                    ->update($arrDCC);
                                }
                                else{
                                    DB::table("distri_centro_costo_centrocosto")
                                    ->insert($arrDCC);
                                }  
                            }
                            
                         }
                        
                    }
                    else if($porcentaje != 100){
                        foreach($read as $idCol =>$cols){
                            if(!isset($arrCentroCosto[$idCol]) && $idCol > 1){
                                array_push($errors, ["idEmpleado" => $read[1], "msj" => "Centro de costo de la columna (".($idCol + 1).") no existe o pertenece a otra empresa"]);
                                break;
                            }
                        }
                        array_push($errors, ["idEmpleado" => $read[1], "msj" => "Porcentaje es diferente a 100 (No se efectuaron cambios)"]);
                    }
                    
                }
                else{
                    array_push($errors, ["idEmpleado" => $read[1], "msj" => "Empleado no existe"]);
                }
                
                
            }
            
        }
        $distri = DB::table("distri_centro_costo", "d")
        ->join("nomina as n", "n.idNomina", "=","d.fkNomina")
        ->where("d.id_distri_centro_costo", "=",$idDistri)
        ->first();
        $empleados = DB::table("empleado", "e")
        ->select("e.idempleado", "dp.primerNombre", "dp.segundoNombre", "dp.primerApellido", "dp.segundoApellido", "dp.numeroIdentificacion", "ti.nombre as tipoDocumento")
        ->join("distri_centro_costo as d", "d.fkNomina", "=","e.fkNomina")
        ->join("datospersonales as dp", "dp.idDatosPersonales", "=", "e.fkDatosPersonales")
        ->join("tipoidentificacion as ti", "ti.idtipoIdentificacion", "=","dp.fkTipoIdentificacion")
        ->where("d.id_distri_centro_costo", "=",$idDistri)
        ->orderByRaw("dp.primerApellido, dp.segundoApellido, dp.primerNombre, dp.segundoNombre")
        ->get();

        $centrosCostoGen = DB::table("centrocosto","cc")
        ->select("cc.*")
        ->join("nomina as n", "n.fkEmpresa", "=","cc.fkEmpresa")
        ->join("distri_centro_costo as d", "d.fkNomina", "=","n.idNomina")
        ->where("d.id_distri_centro_costo", "=",$idDistri)
        ->orderBy("cc.id_uni_centro")
        ->get();

        $arrEmpleadoCC = array();
        foreach($empleados as $empleado){

            $centrosCostoEmpleado = DB::table("distri_centro_costo_centrocosto", "ddc")
            ->where("ddc.fkEmpleado","=",$empleado->idempleado)
            ->where("ddc.fkDistribucion","=",$idDistri)
            ->get();
            $arrCentrosCosto = array();

            if(sizeof($centrosCostoEmpleado) > 0){
                foreach($centrosCostoEmpleado as $centroCostoEmpleado ){
                    array_push($arrCentrosCosto, [
                        "centroCosto" => $centroCostoEmpleado->fkCentroCosto,
                        "porcentaje" => $centroCostoEmpleado->porcentaje
                    ]);
                }
            }
            else{
                $centrosCosto = DB::table("empleado_centrocosto", "ecc")
                ->where("ecc.fkEmpleado", "=",$empleado->idempleado)
                ->get();
                foreach($centrosCosto as $centroCosto){
                    array_push($arrCentrosCosto, [
                        "centroCosto" => $centroCosto->fkCentroCosto,
                        "porcentaje" => $centroCosto->porcentajeTiempoTrabajado
                    ]);
                }
            }

            $arrEmpleadoCC[$empleado->idempleado] = $arrCentrosCosto;
        }
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", subió una distribución de centros de costo por archivo plano");

        return view('/nomina.distri.modDistri',[
            'distri' => $distri,
            'arrEmpleadoCC' => $arrEmpleadoCC,
            'centrosCostoGen' => $centrosCostoGen,
            "empleados" => $empleados,
            "errors" => $errors
        ]);
        
        
    }

    public function agregarSolicitudLiquidacion(){
        $dataUsu = UsuarioController::dataAdminLogueado();

        $empresas = DB::table("empresa");
        if(isset($dataUsu) && $dataUsu->fkRol == 2){            
            $empresas = $empresas->whereIn("idempresa", $dataUsu->empresaUsuario);
        }
        $empresas = $empresas->orderBy("razonSocial")->get();

        $nominas = DB::table("nomina");
        if(isset($dataUsu) && $dataUsu->fkRol == 2){            
            $nominas = $nominas->whereIn("fkEmpresa", $dataUsu->empresaUsuario);
        }
        $nominas = $nominas->orderBy("nombre")->get();
        
        $tiposliquidaciones = DB::table("tipoliquidacion")->get();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó a la acción 'Agregar Solicitud'");

        return view('/nomina.solicitudes.agregarSolicitud',[
            'nominas' => $nominas,
            'empresas' => $empresas,
            'tiposliquidaciones' => $tiposliquidaciones,
            "dataUsu" => $dataUsu
        ]);
    }
    public function cargarFechaPagoxNomina($idNomina, $idTipoLiquidacion){
   
        $nomina = DB::table("nomina")->where("idNomina", "=", $idNomina)->first();
        
        $periodoNomina = $nomina->periodo." ".$nomina->tipoPeriodo;
        
        $liquidacionNomina = DB::table("liquidacionnomina")
            ->where("fkNomina", "=", $idNomina)
            ->where("fkTipoLiquidacion", "=", "1")//Normal
            ->where("fkEstado", "=", "5")//Terminada            
            ->orderBy("idLiquidacionNomina","desc")->first();

        $fechaPagoDeseada="";
        $fechaMinima=date('Y-m-01');
        $fechaProximoInicio = "";
        $fechaProximoFin = "";

        if(isset($liquidacionNomina->fechaLiquida)){            
            $fechaMinima = date('Y-m-d', strtotime($liquidacionNomina->fechaProximaInicio)); 
            if(intval(substr($liquidacionNomina->fechaProximaFin,8,10)) >= 28){
                $fechaPagoDeseada = date('Y-m-t', strtotime($liquidacionNomina->fechaProximaFin));
            }
            else{
                $fechaPagoDeseada = date('Y-m-d', strtotime($liquidacionNomina->fechaProximaFin));
            }

            $fechaProximoInicio = date('Y-m-d', strtotime($fechaPagoDeseada." +1 day")); 
            
            if($nomina->periodo == 30){
                $fechaProximoFin = date('Y-m-t', strtotime($fechaProximoInicio)); 
            }
            else if($nomina->periodo == 15){
                $fechaProximoFin = date('Y-m-d', strtotime($fechaProximoInicio." +".($nomina->periodo - 1)." day"));     
            }
            else{
                $fechaProximoFin = date('Y-m-d', strtotime($fechaProximoInicio." +".($nomina->periodo - 1)." day")); 

            }

        }
        else{
            if($nomina->periodo == 15){
                if(intval(date("d"))>15){
                    $fechaMinima = date('Y-m-16'); 
                    $fechaPagoDeseada = date("Y-m-t");                        
                    $fechaProximoInicio = date('Y-m-d', strtotime($fechaPagoDeseada." +1 day")); 
                    $fechaProximoFin = date('Y-m-d', strtotime($fechaProximoInicio." +".$nomina->periodo." day")); 
                }
                else{
                    
                    $fechaMinima = date('Y-m-01'); 
                    $fechaPagoDeseada = date("Y-m-15");

                    $fechaProximoInicio = date('Y-m-16'); 
                    $fechaProximoFin = date("Y-m-t");    
                }
            }
            else if($nomina->periodo == 30){
                
                $fechaMinima = date('Y-m-01'); 
                $fechaPagoDeseada = date("Y-m-t");
                $fechaProximoInicio = date('Y-m-d', strtotime($fechaPagoDeseada." +1 day")); 
                $fechaProximoFin = date("Y-m-t", strtotime($fechaProximoInicio));
            }
        }
        return response()->json([
            "success" => true,
            "periodo" => $nomina->periodo,
            "periodoNomina" => $periodoNomina,
            "fechaPagoDeseada" => $fechaPagoDeseada,
            "fechaMinima" => $fechaMinima,
            "fechaProximoInicio" => $fechaProximoInicio,
            "fechaProximoFin" => $fechaProximoFin
        ]);
    }

    public function insertarSolicitud(Request $req){

        $arrSinEsos = array();
        if(isset($req->excluirEmpleados) && !empty($req->excluirEmpleados)){
            $excluidos = explode(",",$req->excluirEmpleados);
            array_pop($excluidos);
            $arrSinEsos = $excluidos;
        }

        $messages = [
            'required' => 'El campo :attribute es requerido.',
            'after_or_equal' => 'La :attribute debe ser mayor a la inicial.',
        ];
        $validator = Validator::make($req->all(), [
            'nomina' => 'required',
            'tipoliquidacion' => 'required',
            'fecha' => 'required|date',
            'fechaInicio' => 'required|date',
            'fechaFin' => 'required|date',
            'fechaInicioProx' => 'required|date',
            'fechaFinProx' => 'required|date'
        ],$messages);

        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()->all()]);
        }

        $cierreAnte = DB::table("cierre","c")
        ->join("nomina as n","n.fkEmpresa", "=", "c.fkEmpresa")
        ->where("n.idNomina","=",$req->nomina)
        ->where("c.mes","=", date("m",strtotime($req->fecha)))
        ->where("c.anio","=", date("Y",strtotime($req->fecha)))
        ->where("c.fkEstado","=","1")
        ->first();
        if(isset($cierreAnte)){
            return response()->json(['error'=>["Ya se cerró ese periodo"]]);
        }
        


        $empleados = DB::table('empleado', 'e')
        ->join("periodo as p","p.fkEmpleado","=","e.idempleado")
        ->whereNotIn("e.idempleado", $arrSinEsos)
        ->where("p.fkNomina","=", $req->nomina)
        ->where("p.fkEstado","=", "1")//Estado Activo 
        ->where("p.fechaInicio", "<=", $req->fechaFin);
        if($req->tipoliquidacion == "3"){
            $empleados =  $empleados->whereRaw("e.idempleado 
            in(select n.fkEmpleado from novedad as n where 
                    n.fkEstado in ('7')  and
                    n.fkRetiro is not null and 
                    n.fkEmpleado = e.idempleado and
                    n.fkPeriodoActivo = p.idPeriodo
            )");
        }
        if($req->tipoliquidacion == "12"){
            $empleados =  $empleados->whereRaw("e.idempleado 
            in(select n.fkEmpleado from novedad as n where 
                    n.fkEstado in ('7')  and
                    n.fkVacaciones is not null and 
                    n.fkEmpleado = e.idempleado and
                    n.fkPeriodoActivo = p.idPeriodo
            )");
        }
        if($req->tipoliquidacion == "7"){
            $empleados =  $empleados->whereRaw("(e.tipoRegimen = 'Ley 50' or p.tipoRegimen = 'Ley 50')");
        }

        if($req->tipoliquidacion != "7" && $req->tipoliquidacion != "3"  && $req->tipoliquidacion != "11"  && $req->tipoliquidacion != "12" && $req->tipoliquidacion != "10" ){
            $empleados =  $empleados->whereRaw("e.idempleado not in(
                SELECT fkEmpleado from boucherpago as bp WHERE bp.fkPeriodoActivo = p.idPeriodo and bp.fkEmpleado = e.idempleado
                    and bp.fkLiquidacion in 
                    (SELECT ln.idLiquidacionNomina FROM liquidacionnomina as ln where ln.fechaInicio = '".$req->fechaInicio."' 
                                                                                and fkTipoLiquidacion not in ('7','3', '10','11','12')
                    )
            )");
        }
        else{
            $empleados =  $empleados->whereRaw("e.idempleado not in(
                SELECT fkEmpleado from boucherpago as bp WHERE  bp.fkPeriodoActivo = p.idPeriodo and bp.fkEmpleado = e.idempleado and
                    bp.fkLiquidacion in 
                    (SELECT ln.idLiquidacionNomina FROM liquidacionnomina as ln where ln.fechaInicio = '".$req->fechaInicio."' 
                                                                                and fkTipoLiquidacion in ('".$req->tipoliquidacion."')
                    )
            )");
        }
        
        
        
        $empleados = $empleados->get();
        
        //Consultar si existe alguna liquidacion previa no terminada 
        $liquidacionesNomina = DB::table("liquidacionnomina")
        ->where("fkNomina", "=", $req->nomina)
        ->where("fkTipoLiquidacion", "=", $req->tipoliquidacion)
        ->where("fkTipoLiquidacion", "<>", "3")
        ->where("fkEstado", "<>", "5")//Terminada            
        ->orderBy("idLiquidacionNomina","desc")->get();
        if(sizeof($liquidacionesNomina)>0){
        return response()->json(['error'=>["Esta nomina ya se encuentra en liquidacion, termine primero la liquidacion actual"]]);
        }

        if(sizeof($empleados)==0){
            return response()->json(['error'=>["No hay empleados para este periodo"]]);
        }

        

        $usuario = Auth::user();
        $arrLiquidacionNomina = [
            "fechaInicio" => $req->fechaInicio,
            "fechaFin" => $req->fechaFin,
            "fechaLiquida" => $req->fecha, 
            "fechaProximaInicio" => $req->fechaInicioProx, 
            "fechaProximaFin" => $req->fechaFinProx, 
            "fkNomina" => $req->nomina, 
            "fkTipoLiquidacion" => $req->tipoliquidacion,
            "fkUserSolicita" => $usuario->id
        ];

        if($req->tipoliquidacion == "6"){
            $arrLiquidacionNomina["tipoliquidacionPrima"] = $req->tipoliquidacionPrima;
            $arrLiquidacionNomina["fechaPrima"] = $req->fechaPrima;
            $arrLiquidacionNomina["porcentajePrima"] = $req->porcentajePrima;
            $arrLiquidacionNomina["valorFijoPrima"] = $req->valorFijoPrima;

        }
        if($req->tipoliquidacion == "7"){
            $arrLiquidacionNomina["fechaPrima"] = $req->fechaPrima;
        }

        $idLiquidacionNomina = DB::table('liquidacionnomina')->insertGetId($arrLiquidacionNomina, "idLiquidacionNomina");

        $variables = DB::table("variable")->where("idVariable","=","1")->first();
        $salarioMinimoDia = $variables->valor / 30;

        $salarioMaximoDia = ($variables->valor * 25) / 30;

        foreach($empleados as $empleado){

            if($req->has("numHoras".$empleado->idempleado) && $req->has("numDias".$empleado->idempleado)){
                $respuesta=$this->calcularLiquidacionEmpleado($empleado->idempleado, $idLiquidacionNomina, null, $req->input("numHoras".$empleado->idempleado), $req->input("numDias".$empleado->idempleado));
            }
            else{
                $respuesta=$this->calcularLiquidacionEmpleado($empleado->idempleado, $idLiquidacionNomina);
            }
            if($respuesta !== true){
                return response()->json([
                    "success" => false,
                    "error" => [$respuesta]
                ]);
            }
        }
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", agregó una solicitud de liquidacion con id:".$idLiquidacionNomina);

        return response()->json([
            "success" => true
        ]);
        


    }
    public function recalcularNomina($idLiquidacionNomina){
        Artisan::call('view:clear');
        $boucherpagos = DB::table("boucherpago")->where("fkLiquidacion","=",$idLiquidacionNomina)->get();

    
        foreach($boucherpagos as $boucherpago){
            if(isset($boucherpago->horasPeriodo)){
                $horas= $boucherpago->horasPeriodo/$boucherpago->periodoPago;
                $this->calcularLiquidacionEmpleado($boucherpago->fkEmpleado, $boucherpago->fkLiquidacion, $boucherpago->idBoucherPago, $horas, $boucherpago->periodoPago);
            }
            else{
                $this->calcularLiquidacionEmpleado($boucherpago->fkEmpleado, $boucherpago->fkLiquidacion, $boucherpago->idBoucherPago);
            }
            
        }
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", recalculó una solicitud de liquidacion con id:".$idLiquidacionNomina);
        return response()->json([
            "success" => true
        ]);
        
    }

    public function calcularFormulaxArray($idConcepto, $arrValorxConcepto, $tipoSalario, $periodo){
        $formulasConceptos = DB::table("formulaconcepto")
        ->where("fkConcepto","=",$idConcepto)
        ->orderBy("idformulaConcepto")
        ->get();
        $valor1 = 0;
        $valor2 = 0;
        $valorf = 0;
        $variables = DB::table("variable")->where("idVariable","=","1")->first();
        $salarioMaximo = ($variables->valor * 25) / 30;
        $salarioMaximo = $salarioMaximo * $periodo;

        foreach($formulasConceptos as $formulaConcepto){        
            //VALOR 1
            if(isset($formulaConcepto->fkFormulaConcepto)){
                $valor1=$valorf;
            }
            else if(isset($formulaConcepto->fkConceptoInicial)){
                if(isset($arrValorxConcepto[$formulaConcepto->fkConceptoInicial])){
                    $valor1=floatval($arrValorxConcepto[$formulaConcepto->fkConceptoInicial]['valor']);
                }
            }
            else if(isset($formulaConcepto->fkGrupoConceptoInicial)){
                $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")
                    ->where("gcc.fkGrupoConcepto", "=", $formulaConcepto->fkGrupoConceptoInicial)                       
                    ->get();
                foreach($grupoConceptoCalculo as $grupoConcepto){
                    if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto != 36){
                        $valor1= $valor1 + floatval($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor']);
                    }
                }
                if($tipoSalario=="Salario Integral" && $valor1 > $salarioMaximo){
                    $valor1 = $salarioMaximo;
                }
            }
            else if(isset($formulaConcepto->fkVariableInicial)){
                $variableCalculo = DB::table('variable')->where("idVariable","=",$formulaConcepto->fkVariableFinal)->first();
                $valor1 = floatval($variableCalculo->valor);
            }
            else if(isset($formulaConcepto->valorInicial)){
                $valor1 = floatval($formulaConcepto->valorInicial);
            }
            //VALOR 2
            if(isset($formulaConcepto->fkConceptoFinal)){
                if(isset($arrValorxConcepto[$formulaConcepto->fkConceptoFinal])){
                    $valor2=floatval($arrValorxConcepto[$formulaConcepto->fkConceptoFinal]['valor']);
                }
            }
            else if(isset($formulaConcepto->fkGrupoConceptoFinal)){
                $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")
                    ->where("gcc.fkGrupoConcepto", "=", $formulaConcepto->fkGrupoConceptoFinal)                       
                    ->get();

                foreach($grupoConceptoCalculo as $grupoConcepto){
                    if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto != 36){
                        $valor1= $valor1 + floatval($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor']);
                    }
                }
                
            }
            else if(isset($formulaConcepto->fkVariableFinal)){
                $variableFinal = DB::table('variable')->where("idVariable","=",$formulaConcepto->fkVariableFinal)->first();
                $valor2 = floatval($variableFinal->valor);
            }
            else if(isset($formulaConcepto->valorFinal)){
                $valor2 = floatval($formulaConcepto->valorFinal);
            }

            //VALOR F 
            if($formulaConcepto->fkTipoOperacion=="1"){//Suma
                $valorf = $valor1 + $valor2;
            }
            else if($formulaConcepto->fkTipoOperacion=="2"){//Resta
                $valorf = $valor1 - $valor2;
            }
            else if($formulaConcepto->fkTipoOperacion=="3"){//Multiplicacion
                $valorf = $valor1 * $valor2;
            }
            else if($formulaConcepto->fkTipoOperacion=="4"){//Division
                if($valor2 != 0){
                    $valorf = $valor1 / $valor2;
                }                                
            }    
        }
        return $valorf;
    }

    public function calcularValoresxConceptoSSxEmpleado($idConcepto, $idEmpleado, $idPeriodo){
        $formulasConceptos = DB::table("formulaconcepto_ss")
        ->where("fkConcepto","=",$idConcepto)
        ->orderBy("idformulaConcepto")
        ->get();
        $valor1 = 0;
        $valor2 = 0;
        $valorf = 0;
        foreach($formulasConceptos as $formulaConcepto){
        
            //VALOR 1
            if(isset($formulaConcepto->fkFormulaConcepto)){
                $valor1=$valorf;
            }
            else if(isset($formulaConcepto->fkConceptoInicial)){
                $conceptoCalculo = DB::table("conceptofijo")->select(DB::raw('SUM(conceptofijo.valor) as totalValor'))
                    ->where("conceptofijo.fkEmpleado","=", $idEmpleado)
                    ->where("conceptofijo.fkPeriodoActivo","=", $idPeriodo)
                    ->where("conceptofijo.fkConcepto","=", $formulaConcepto->fkConceptoInicial)
                    ->where("conceptofijo.fkEstado","=","1")
                    ->first();
                $valor1=floatval($conceptoCalculo->totalValor);
            }
            else if(isset($formulaConcepto->fkGrupoConceptoInicial)){

                $grupoConceptoCalculo = DB::table("conceptofijo")->select(DB::raw('SUM(conceptofijo.valor) as totalValor'))
                    ->join("grupoconcepto_concepto as gcc", "gcc.fkConcepto","=","conceptofijo.fkConcepto")
                    ->where("conceptofijo.fkEmpleado","=", $idEmpleado)
                    ->where("conceptofijo.fkPeriodoActivo","=", $idPeriodo)
                    ->where("conceptofijo.fkEstado","=","1")
                    ->where("gcc.fkGrupoConcepto", "=", $formulaConcepto->fkGrupoConceptoInicial)                       
                    ->first();

                $valor1=floatval($grupoConceptoCalculo->totalValor);
            }
            else if(isset($formulaConcepto->fkVariableInicial)){
                $variableCalculo = DB::table('variable')->where("idVariable","=",$formulaConcepto->fkVariableFinal)->first();
                $valor1 = floatval($variableCalculo->valor);
            }
            else if(isset($formulaConcepto->valorInicial)){
                $valor1 = floatval($formulaConcepto->valorInicial);
            }
            //VALOR 2
            if(isset($formulaConcepto->fkConceptoFinal)){
                $conceptoCalculo = DB::table("conceptofijo")->select(DB::raw('SUM(conceptofijo.valor) as totalValor'))
                    ->where("conceptofijo.fkEmpleado","=", $idEmpleado)
                    ->where("conceptofijo.fkPeriodoActivo","=", $idPeriodo)
                    ->where("conceptofijo.fkConcepto","=", $formulaConcepto->fkConceptoFinal)
                    ->where("conceptofijo.fkEstado","=","1")
                    ->first();
                $valor2=floatval($conceptoCalculo->totalValor);
            }
            else if(isset($formulaConcepto->fkGrupoConceptoFinal)){
                $grupoConceptoCalculo = DB::table("conceptofijo")->select(DB::raw('SUM(conceptofijo.valor) as totalValor'))
                    ->join("grupoconcepto_concepto as gcc", "gcc.fkConcepto","=","conceptofijo.fkConcepto")
                    ->where("conceptofijo.fkEmpleado","=", $idEmpleado)
                    ->where("conceptofijo.fkPeriodoActivo","=", $idPeriodo)
                    ->where("conceptofijo.fkEstado","=","1")
                    ->where("gcc.fkGrupoConcepto", "=", $formulaConcepto->fkGrupoConceptoFinal)                       
                    ->first();

                $valor2=floatval($grupoConceptoCalculo->totalValor);
            }
            else if(isset($formulaConcepto->fkVariableFinal)){
                $variableFinal = DB::table('variable')->where("idVariable","=",$formulaConcepto->fkVariableFinal)->first();
                $valor2 = floatval($variableFinal->valor);
            }
            else if(isset($formulaConcepto->valorFinal)){
                $valor2 = floatval($formulaConcepto->valorFinal);
            }

            //VALOR F 
            if($formulaConcepto->fkTipoOperacion=="1"){//Suma
                $valorf = $valor1 + $valor2;
            }
            else if($formulaConcepto->fkTipoOperacion=="2"){//Resta
                $valorf = $valor1 - $valor2;
            }
            else if($formulaConcepto->fkTipoOperacion=="3"){//Multiplicacion
                $valorf = $valor1 * $valor2;
            }
            else if($formulaConcepto->fkTipoOperacion=="4"){//Division
                if($valor2 != 0){
                    $valorf = $valor1 / $valor2;
                }                                
            }    
        }
        return $valorf;
    }

    public function calcularValoresxConceptoxEmpleado($idConcepto, $idEmpleado, $idPeriodo){
        $formulasConceptos = DB::table("formulaconcepto")
        ->where("fkConcepto","=",$idConcepto)
        ->orderBy("idformulaConcepto")
        ->get();
        $valor1 = 0;
        $valor2 = 0;
        $valorf = 0;
        foreach($formulasConceptos as $formulaConcepto){
        
            //VALOR 1
            if(isset($formulaConcepto->fkFormulaConcepto)){
                $valor1=$valorf;
            }
            else if(isset($formulaConcepto->fkConceptoInicial)){
                $conceptoCalculo = DB::table("conceptofijo")->select(DB::raw('SUM(conceptofijo.valor) as totalValor'))
                    ->where("conceptofijo.fkEmpleado","=", $idEmpleado)
                    ->where("conceptofijo.fkPeriodoActivo","=", $idPeriodo)
                    ->where("conceptofijo.fkConcepto","=", $formulaConcepto->fkConceptoInicial)
                    ->where("conceptofijo.fkEstado","=","1")
                    ->first();
                $valor1=floatval($conceptoCalculo->totalValor);
            }
            else if(isset($formulaConcepto->fkGrupoConceptoInicial)){

                $grupoConceptoCalculo = DB::table("conceptofijo")->select(DB::raw('SUM(conceptofijo.valor) as totalValor'))
                    ->join("grupoconcepto_concepto as gcc", "gcc.fkConcepto","=","conceptofijo.fkConcepto")
                    ->where("conceptofijo.fkEmpleado","=", $idEmpleado)
                    ->where("conceptofijo.fkPeriodoActivo","=", $idPeriodo)
                    ->where("conceptofijo.fkEstado","=","1")
                    ->where("gcc.fkGrupoConcepto", "=", $formulaConcepto->fkGrupoConceptoInicial)                       
                    ->first();

                $valor1=floatval($grupoConceptoCalculo->totalValor);
            }
            else if(isset($formulaConcepto->fkVariableInicial)){
                $variableCalculo = DB::table('variable')->where("idVariable","=",$formulaConcepto->fkVariableFinal)->first();
                $valor1 = floatval($variableCalculo->valor);
            }
            else if(isset($formulaConcepto->valorInicial)){
                $valor1 = floatval($formulaConcepto->valorInicial);
            }
            //VALOR 2
            if(isset($formulaConcepto->fkConceptoFinal)){
                $conceptoCalculo = DB::table("conceptofijo")->select(DB::raw('SUM(conceptofijo.valor) as totalValor'))
                    ->where("conceptofijo.fkEmpleado","=", $idEmpleado)
                    ->where("conceptofijo.fkPeriodoActivo","=", $idPeriodo)
                    ->where("conceptofijo.fkConcepto","=", $formulaConcepto->fkConceptoFinal)
                    ->where("conceptofijo.fkEstado","=","1")
                    ->first();
                $valor2=floatval($conceptoCalculo->totalValor);
            }
            else if(isset($formulaConcepto->fkGrupoConceptoFinal)){
                $grupoConceptoCalculo = DB::table("conceptofijo")->select(DB::raw('SUM(conceptofijo.valor) as totalValor'))
                    ->join("grupoconcepto_concepto as gcc", "gcc.fkConcepto","=","conceptofijo.fkConcepto")
                    ->where("conceptofijo.fkEmpleado","=", $idEmpleado)
                    ->where("conceptofijo.fkPeriodoActivo","=", $idPeriodo)
                    ->where("conceptofijo.fkEstado","=","1")
                    ->where("gcc.fkGrupoConcepto", "=", $formulaConcepto->fkGrupoConceptoFinal)                       
                    ->first();

                $valor2=floatval($grupoConceptoCalculo->totalValor);
            }
            else if(isset($formulaConcepto->fkVariableFinal)){
                $variableFinal = DB::table('variable')->where("idVariable","=",$formulaConcepto->fkVariableFinal)->first();
                $valor2 = floatval($variableFinal->valor);
            }
            else if(isset($formulaConcepto->valorFinal)){
                $valor2 = floatval($formulaConcepto->valorFinal);
            }

            //VALOR F 
            if($formulaConcepto->fkTipoOperacion=="1"){//Suma
                $valorf = $valor1 + $valor2;
            }
            else if($formulaConcepto->fkTipoOperacion=="2"){//Resta
                $valorf = $valor1 - $valor2;
            }
            else if($formulaConcepto->fkTipoOperacion=="3"){//Multiplicacion
                $valorf = $valor1 * $valor2;
            }
            else if($formulaConcepto->fkTipoOperacion=="4"){//Division
                if($valor2 != 0){
                    $valorf = $valor1 / $valor2;
                }                                
            }    
        }
        return $valorf;
    }

    public function comoCalculaValoresxConceptoxEmpleado($idConcepto, $idEmpleado, $idPeriodo , $arrComoCalcula){
        $formulasConceptos = DB::table("formulaconcepto")
        ->where("fkConcepto","=",$idConcepto)
        ->orderBy("idformulaConcepto")
        ->get();
        $valor1 = 0;
        $valor2 = 0;
        $valorf = 0;
        foreach($formulasConceptos as $formulaConcepto){
        
            //VALOR 1
            if(isset($formulaConcepto->fkFormulaConcepto)){
                $valor1=$valorf;
                array_push($arrComoCalcula, "Se toma el valor: ".number_format($valor1,0,",", "."));
            }
            else if(isset($formulaConcepto->fkConceptoInicial)){
                $conceptoCalculo = DB::table("conceptofijo")->select(DB::raw('SUM(conceptofijo.valor) as totalValor', "c.nombre as nombreConcepto"))
                ->join("concepto as c","c.idconcepto", "=","conceptofijo.fkConcepto")
                ->where("conceptofijo.fkEmpleado","=", $idEmpleado)
                ->where("conceptofijo.fkPeriodoActivo","=", $idPeriodo)
                ->where("conceptofijo.fkConcepto","=", $formulaConcepto->fkConceptoInicial)
                ->where("conceptofijo.fkEstado","=","1")
                ->first();
                $valor1=floatval($conceptoCalculo->totalValor);                
                array_push($arrComoCalcula, "Se toma el concepto ".$conceptoCalculo->nombreConcepto." con valor: ".number_format($valor1,0,",", "."));

            }
            else if(isset($formulaConcepto->fkGrupoConceptoInicial)){

                $grupoConceptoCalculo = DB::table("conceptofijo")->select(DB::raw('SUM(conceptofijo.valor) as totalValor'), 'grupoconcepto.nombre')
                    ->join("grupoconcepto_concepto as gcc", "gcc.fkConcepto","=","conceptofijo.fkConcepto")
                    ->join("grupoconcepto","grupoconcepto.idgrupoConcepto","=","gcc.fkGrupoConcepto")
                    ->where("conceptofijo.fkEmpleado","=", $idEmpleado)
                    ->where("conceptofijo.fkPeriodoActivo","=", $idPeriodo)
                    ->where("conceptofijo.fkEstado","=","1")
                    ->where("gcc.fkGrupoConcepto", "=", $formulaConcepto->fkGrupoConceptoInicial)                       
                    ->first();
                $valor1=floatval($grupoConceptoCalculo->totalValor);
                array_push($arrComoCalcula, "Se toma el grupo de concepto ".$grupoConceptoCalculo->nombre." con valor: ".number_format($valor1,0,",", "."));
            }
            else if(isset($formulaConcepto->fkVariableInicial)){
                $variableCalculo = DB::table('variable')->where("idVariable","=",$formulaConcepto->fkVariableFinal)->first();
                $valor1 = floatval($variableCalculo->valor);
                array_push($arrComoCalcula, "Se toma la variable ".$variableCalculo->nombre." con valor: ".number_format($valor1,0,",", "."));
            }
            else if(isset($formulaConcepto->valorInicial)){
                $valor1 = floatval($formulaConcepto->valorInicial);
                array_push($arrComoCalcula, "Se toma el valor: ".number_format($valor1,0,",", "."));
            }
            //VALOR 2
            if(isset($formulaConcepto->fkConceptoFinal)){
                $conceptoCalculo = DB::table("conceptofijo")->select(DB::raw('SUM(conceptofijo.valor) as totalValor'), "c.nombre as nombreConcepto")
                ->join("concepto as c","c.idconcepto", "=","conceptofijo.fkConcepto")
                ->where("conceptofijo.fkEmpleado","=", $idEmpleado)
                ->where("conceptofijo.fkPeriodoActivo","=", $idPeriodo)
                ->where("conceptofijo.fkConcepto","=", $formulaConcepto->fkConceptoFinal)
                ->where("conceptofijo.fkEstado","=","1")
                ->first();
                
                $valor2=floatval($conceptoCalculo->totalValor);
                array_push($arrComoCalcula, "Se toma el concepto ".$conceptoCalculo->nombreConcepto." con valor: ".number_format($valor2,0,",", "."));
            }
            else if(isset($formulaConcepto->fkGrupoConceptoFinal)){
                $grupoConceptoCalculo = DB::table("conceptofijo")->select(DB::raw('SUM(conceptofijo.valor) as totalValor'), 'grupoconcepto.nombre')
                    ->join("grupoconcepto_concepto as gcc", "gcc.fkConcepto","=","conceptofijo.fkConcepto")
                    ->join("grupoconcepto","grupoconcepto.idgrupoConcepto","=","gcc.fkGrupoConcepto")
                    ->where("conceptofijo.fkEmpleado","=", $idEmpleado)
                    ->where("conceptofijo.fkPeriodoActivo","=", $idPeriodo)
                    ->where("conceptofijo.fkEstado","=","1")
                    ->where("gcc.fkGrupoConcepto", "=", $formulaConcepto->fkGrupoConceptoFinal)                       
                    ->first();

                $valor2=floatval($grupoConceptoCalculo->totalValor);
                array_push($arrComoCalcula, "Se toma el grupo de concepto ".$grupoConceptoCalculo->nombre." con valor: ".number_format($valor2,0,",", "."));
            }
            else if(isset($formulaConcepto->fkVariableFinal)){
                $variableFinal = DB::table('variable')->where("idVariable","=",$formulaConcepto->fkVariableFinal)->first();
                $valor2 = floatval($variableFinal->valor);
                array_push($arrComoCalcula, "Se toma la variable ".$variableFinal->nombre." con valor: ".number_format($valor2,0,",", "."));
            }
            else if(isset($formulaConcepto->valorFinal)){
                $valor2 = floatval($formulaConcepto->valorFinal);
                array_push($arrComoCalcula, "Se toma el valor: ".number_format($valor2,0,",", "."));
            }

            //VALOR F 
            if($formulaConcepto->fkTipoOperacion=="1"){//Suma
                $valorf = $valor1 + $valor2;
                array_push($arrComoCalcula, "Se suman los valores: $".number_format($valor1,0,",", ".")." y $".number_format($valor2,0,",", "."));
            }
            else if($formulaConcepto->fkTipoOperacion=="2"){//Resta
                $valorf = $valor1 - $valor2;
                array_push($arrComoCalcula, "Se restan los valores: $".number_format($valor1,0,",", ".")." y $".number_format($valor2,0,",", "."));
            }
            else if($formulaConcepto->fkTipoOperacion=="3"){//Multiplicacion
                $valorf = $valor1 * $valor2;
                array_push($arrComoCalcula, "Se multiplican los valores: $".number_format($valor1,0,",", ".")." y $".number_format($valor2,0,",", "."));
            }
            else if($formulaConcepto->fkTipoOperacion=="4"){//Division
                if($valor2 != 0){
                    $valorf = $valor1 / $valor2;
                    array_push($arrComoCalcula, "Se dividen los valores: $".number_format($valor1,0,",", ".")." entre $".number_format($valor2,0,",", "."));
                }                                
            }    
        }
        return $arrComoCalcula;
    }

    
    public function cargarEmpleadosxNomina($idNomina, $tipoNomina){
        $empleados = DB::table('empleado', 'e')
        ->select(["e.*", "dp.primerNombre", "dp.segundoNombre", "dp.primerApellido", "dp.segundoApellido", "dp.numeroIdentificacion","t.nombre"])
        ->join("datospersonales AS dp", "e.fkDatosPersonales", "=" , "dp.idDatosPersonales")
        ->join("tipoidentificacion AS t", "dp.fkTipoIdentificacion", "=" , "t.idtipoIdentificacion")
        ->join("periodo as p", "p.fkEmpleado", "=","e.idempleado")
        ->where("p.fkNomina","=", $idNomina)
        ->where("p.fkEstado","=", "1");//Estado Activo
        if($tipoNomina == "3"){
            $empleados =  $empleados->whereRaw("e.idempleado in
            (
                select n.fkEmpleado from novedad as n where n.fkEstado in ('7') and n.fkRetiro is not null and 
                n.fkEmpleado = e.idempleado and
                n.fkPeriodoActivo = p.idPeriodo
            )");
        }
        if($tipoNomina == "12"){
            $empleados =  $empleados->whereRaw("e.idempleado in
            (
                select n.fkEmpleado from novedad as n where n.fkEstado in ('7') and n.fkVacaciones is not null and 
                n.fkEmpleado = e.idempleado and
                n.fkPeriodoActivo = p.idPeriodo
            )");
        }
        if($tipoNomina == "7" || $tipoNomina == "6"){
            $empleados = $empleados->whereRaw("(e.tipoRegimen = 'Ley 50' or p.tipoRegimen = 'Ley 50')");
        }
        $empleados = $empleados->get();
        //dd($empleados);

        return view('/nomina.ajax.empleadosxNomina',[
            'empleados' => $empleados,
            'tipoNomina' => $tipoNomina
        ]);
        
    }

    public function cargarFechaxNomina($idNomina){

        $nomina = DB::table("nomina")->where("idNomina", "=", $idNomina)->first();
        $periodoNomina = $nomina->periodo." ".$nomina->tipoPeriodo;
        $liquidacionNomina = DB::table("liquidacionnomina")
            ->where("fkNomina", "=", $idNomina)
            ->whereIn("fkTipoLiquidacion", ["1","2","4","5","6","9","12"])//Normal
            ->where("fkEstado", "=", "5")//Terminada            
            ->orderBy("fechaLiquida","desc")->first();
        
        if(isset($liquidacionNomina->fechaLiquida)){            
            $fechaInicioDeseada = date('Y-m-d', strtotime($liquidacionNomina->fechaProximaInicio)); 
        }
        else{
            $fechaInicioDeseada = date('Y-m-01');
        }
        return response()->json([
            "success" => true,
            "periodoNomina" => $periodoNomina,
            "fechaInicioDeseada" => $fechaInicioDeseada
        ]);
    }

    public function condicionesxConceptoEnArray($concepto, $idEmpleado, $idPeriodo, $arrConcepto, $periodo){
        $condiciones = DB::table("condicion")
            ->where("fkConcepto", "=", $concepto)
            ->where("fkTipoResultado", "=", "3")            
            ->get();


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
                        
                        
                    }
                    else{
                        if(isset($arrConcepto[$itemCondicion->fkConceptoInicial]['valor'])){                                
                            $arrCondicionActual["inicio"]= intval($arrConcepto[$itemCondicion->fkConceptoInicial]['valor'])*$multiplicadorInicial;
                        }
                        else{
                            $arrCondicionActual["inicio"]=0;
                        }
                    }
                }
                else if(isset($itemCondicion->fkGrupoConceptoInicial)){
                    $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")                    
                    ->where("gcc.fkGrupoConcepto", "=", $itemCondicion->fkGrupoConceptoInicial)   
                    ->distinct()                    
                    ->get();
                    

                    $totalValor = 0;
                    foreach($grupoConceptoCalculo as $grupoConceptoC){
                        
                        if(isset($arrConcepto[$grupoConceptoC->fkConcepto]['valor'])){           
                            if($grupoConceptoC->fkConcepto == 1){
                                if($periodo!=0){
                                    $totalValor = $totalValor + (intval($arrConcepto[$grupoConceptoC->fkConcepto]['valor'] * 30)/$periodo);
                                    
                                }
                                
                            }
                            else{
                                $totalValor = $totalValor + intval($arrConcepto[$grupoConceptoC->fkConcepto]['valor']);
                                
                            }
                            
                        }
                    }
                    

                    $totalValor = $totalValor*$multiplicadorInicial;
                    $arrCondicionActual["inicio"]= $totalValor;
                    
                }
                $arrCondicionActual["fkOperadorComparacion"] = $itemCondicion->fkOperadorComparacion;
                $multiplicador1 = 1;
                if(isset($itemCondicion->multiplicador1)){
                    $multiplicador1 = $itemCondicion->multiplicador1;
                }

                if(isset($itemCondicion->fkConceptoFinal1)){
                    if($itemCondicion->fkConceptoFinal1 == $concepto){
                        
                        
                    }
                    else{
                        if(isset($arrConcepto[$itemCondicion->fkConceptoFinal1]['valor'])){                                
                            $arrCondicionActual["final1"]= intval($arrConcepto[$itemCondicion->fkConceptoFinal1]['valor'])*$multiplicador1;
                        }
                        else{
                            $arrCondicionActual["final1"]=0;
                        }
                    }
                }
                else if(isset($itemCondicion->fkGrupoConceptoFinal1)){

                    $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")                    
                    ->where("gcc.fkGrupoConcepto", "=", $itemCondicion->fkGrupoConceptoFinal1)                       
                    ->get();
                    $totalValor = 0;
                    foreach($grupoConceptoCalculo as $grupoConceptoC){
                        if(isset($arrConcepto[$grupoConceptoC->fkConcepto]['valor'])){                                
                            if($grupoConceptoC->fkConcepto == 1){
                                if($periodo!=0){
                                    $totalValor = $totalValor + (intval($arrConcepto[$grupoConceptoC->fkConcepto]['valor'] * 30)/$periodo);
                                }
                            }
                            else{
                                $totalValor = $totalValor + intval($arrConcepto[$grupoConceptoC->fkConcepto]['valor']);
                            }
                            
                        }
                    }
                    $totalValor = $totalValor*$multiplicador1;
                    $arrCondicionActual["final1"]= $totalValor;
                    
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
                        
                    }
                    else{
                        if(isset($arrConcepto[$itemCondicion->fkConceptoFinal2]['valor'])){                                
                            $arrCondicionActual["final2"]= intval($arrConcepto[$itemCondicion->fkConceptoFinal2]['valor'])*$multiplicador2;
                        }
                        else{
                            $arrCondicionActual["final2"]=0;
                        }                      
                    }
                }
                else if(isset($itemCondicion->fkGrupoConceptoFinal2)){
                    $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")                    
                    ->where("gcc.fkGrupoConcepto", "=", $itemCondicion->fkGrupoConceptoFinal2)                       
                    ->get();
                    $totalValor = 0;
                    foreach($grupoConceptoCalculo as $grupoConceptoC){
                        if(isset($arrConcepto[$grupoConceptoC->fkConcepto]['valor'])){                                
                            $totalValor = $totalValor + intval($arrConcepto[$grupoConceptoC->fkConcepto]['valor']);
                        }
                    }
                    $totalValor = $totalValor*$multiplicador2;
                    $arrCondicionActual["final2"]= $totalValor;
                    
                    
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
                    
                    return false;
                }


            }
            $cuentaValidos = 0;
            
            foreach($arrCondicion as $arrItemCond){
                
                if(isset($arrItemCond["valido"]) &&  $arrItemCond["valido"] == true){
                    $cuentaValidos++;
                    
                }
            }
            
            if(sizeof($arrCondicion) == $cuentaValidos && sizeof($arrCondicion)!=0){

                return false;
            }
        }
        
        return true;

    }

    public function condicionesxConcepto($concepto, $idEmpleado){
    
        $condiciones = DB::table("condicion")
            ->where("fkConcepto", "=", $concepto)
            ->where("fkTipoResultado", "=", "3")            
            ->get();


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
                        
                        
                    }
                    else{
                        $conceptoCalculo = DB::table("conceptofijo")->where("fkEmpleado","=",$idEmpleado)
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
                    ->where("conceptofijo.fkEmpleado","=", $idEmpleado)
                    ->where("conceptofijo.fkEstado","=","1")
                    ->where("gcc.fkGrupoConcepto", "=", $itemCondicion->fkGrupoConceptoInicial)                       
                    ->first();

                    $arrCondicionActual["inicio"]= intval($grupoConceptoCalculo->totalValor)*$multiplicadorInicial;

                }
                $arrCondicionActual["fkOperadorComparacion"] = $itemCondicion->fkOperadorComparacion;
                $multiplicador1 = 1;
                if(isset($itemCondicion->multiplicador1)){
                    $multiplicador1 = $itemCondicion->multiplicador1;
                }

                if(isset($itemCondicion->fkConceptoFinal1)){
                    if($itemCondicion->fkConceptoFinal1 == $concepto){
                        
                        
                    }
                    else{
                        $conceptoCalculo = DB::table("conceptofijo")->where("fkEmpleado","=",$idEmpleado)
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
                    ->where("conceptofijo.fkEmpleado","=", $idEmpleado)
                    ->where("conceptofijo.fkEstado","=","1")
                    ->where("gcc.fkGrupoConcepto", "=", $itemCondicion->fkGrupoConceptoFinal1)                       
                    ->first();

                    $arrCondicionActual["final1"]= intval($grupoConceptoCalculo->totalValor)*$multiplicador1;
                    
                    
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
                        
                        
                    }
                    else{
                        $conceptoCalculo = DB::table("conceptofijo")->where("fkEmpleado","=",$idEmpleado)
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
                    ->where("conceptofijo.fkEmpleado","=", $idEmpleado)
                    ->where("conceptofijo.fkEstado","=","1")
                    ->where("gcc.fkGrupoConcepto", "=", $itemCondicion->fkGrupoConceptoFinal2)                       
                    ->first();

                    $arrCondicionActual["final2"]= intval($grupoConceptoCalculo->totalValor)*$multiplicador2;
                    
                    
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
                    
                    return false;
                }


            }
            $cuentaValidos = 0;
            
            foreach($arrCondicion as $arrItemCond){
                
                if(isset($arrItemCond["valido"]) &&  $arrItemCond["valido"] == true){
                    $cuentaValidos++;
                    
                }
            }
            
            if(sizeof($arrCondicion) == $cuentaValidos && sizeof($arrCondicion)!=0){
                return false;
            }                
        }
            return true;


    
    }

    public function verSolicitudLiquidacion($idLiquidacion, Request $req){
        $dataUsu = UsuarioController::dataAdminLogueado();
        $liquidaciones = DB::table("liquidacionnomina", "ln")
        ->select(["ln.idLiquidacionNomina", "ln.fechaLiquida", "e.razonSocial", "n.nombre as nombreNomina", "tl.nombre as tipoLiquidacion", "est.nombre as estado"])
        ->join("nomina AS n","ln.fkNomina", "=", "n.idnomina")
        ->join("empresa AS e","n.fkEmpresa","=", "e.idempresa")
        ->join("tipoliquidacion AS tl","ln.fkTipoLiquidacion","=", "tl.idTipoLiquidacion")        
        ->join("estado AS est","ln.fkEstado","=", "est.idestado")
        ->where("ln.idLiquidacionNomina", "=", $idLiquidacion)
        ->first();

        $bouchers = DB::table("boucherpago", "b")
        ->select(["b.idBoucherPago","e.idempleado", "dp.primerNombre", "dp.segundoNombre", "dp.primerApellido", "dp.segundoApellido", "dp.numeroIdentificacion","t.nombre", "b.netoPagar"])
        ->join("empleado AS e", "b.fkEmpleado","=", "e.idempleado")
        ->join("datospersonales AS dp", "e.fkDatosPersonales","=", "dp.idDatosPersonales")
        ->join("tipoidentificacion AS t", "dp.fkTipoIdentificacion", "=" , "t.idtipoIdentificacion")
        ->where("b.fkLiquidacion","=",$idLiquidacion);
        if(isset($req->numDoc)){
            $bouchers = $bouchers->where("dp.numeroIdentificacion","LIKE","%".$req->numDoc."%");
        }
        if(isset($req->nombre)){
            $bouchers = $bouchers->where(function($query) use($req){
                $query->where("dp.primerNombre","LIKE","%".$req->nombre."%")
                ->orWhere("dp.segundoNombre","LIKE","%".$req->nombre."%")
                ->orWhere("dp.primerApellido","LIKE","%".$req->nombre."%")
                ->orWhere("dp.segundoApellido","LIKE","%".$req->nombre."%")
                ->orWhereRaw("CONCAT(dp.primerApellido,' ',dp.segundoApellido,' ',dp.primerNombre,' ',dp.segundoNombre) LIKE '%".$req->nombre."%'");
            });
        }
        
        $bouchers = $bouchers->orderBy("dp.primerApellido")
        ->orderBy("dp.segundoApellido")
        ->orderBy("dp.primerNombre")
        ->orderBy("dp.segundoNombre")
        ->get();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó a la acción 'Ver solicitud de liquidacion' con id:".$idLiquidacion);

        return view('nomina.solicitudes.verSolicitud', [
            'bouchers' => $bouchers, 
            "liquidaciones" => $liquidaciones, 
            "dataUsu" => $dataUsu,
            "req" => $req
        ]);

    }
    public function verSolicitudLiquidacionSinEdit($idLiquidacion, Request $req){
        $dataUsu = UsuarioController::dataAdminLogueado();




        $liquidaciones = DB::table("liquidacionnomina", "ln")
        ->select(["ln.idLiquidacionNomina", "ln.fechaLiquida", "e.razonSocial","n.nombre as nombreNomina", 
                    "tl.nombre as tipoLiquidacion", "est.nombre as estado", "ln.fkTipoLiquidacion", "e.idempresa"])
        ->join("nomina AS n","ln.fkNomina", "=", "n.idnomina")
        ->join("empresa AS e","n.fkEmpresa","=", "e.idempresa")
        ->join("tipoliquidacion AS tl","ln.fkTipoLiquidacion","=", "tl.idTipoLiquidacion")        
        ->join("estado AS est","ln.fkEstado","=", "est.idestado")
        ->where("ln.idLiquidacionNomina", "=", $idLiquidacion)
        ->first();

        
        if(!in_array($liquidaciones->idempresa, $dataUsu->empresaUsuario) && $dataUsu->fkRol == 2){
            return view('/layouts.respuestaGen',[
                "dataUsu" => $dataUsu,
                "titulo" => "Error no tiene permisos para ver esta liquidación",
                "mensaje" =>"Error no tiene permisos para ver esta liquidación"
            ]);
        }
        


        $bouchers = DB::table("boucherpago", "b")
        ->select(["b.idBoucherPago","e.idempleado", "dp.primerNombre", "dp.segundoNombre", "dp.primerApellido", "dp.segundoApellido", "dp.numeroIdentificacion","t.nombre", "b.netoPagar"])
        ->join("empleado AS e", "b.fkEmpleado","=", "e.idempleado")
        ->join("datospersonales AS dp", "e.fkDatosPersonales","=", "dp.idDatosPersonales")
        ->join("tipoidentificacion AS t", "dp.fkTipoIdentificacion", "=" , "t.idtipoIdentificacion")
        ->where("b.fkLiquidacion","=",$idLiquidacion);
        if(isset($req->numDoc)){
            $bouchers = $bouchers->where("dp.numeroIdentificacion","LIKE","%".$req->numDoc."%");
        }
        if(isset($req->nombre)){
            $bouchers = $bouchers->where(function($query) use($req){
                $query->where("dp.primerNombre","LIKE","%".$req->nombre."%")
                ->orWhere("dp.segundoNombre","LIKE","%".$req->nombre."%")
                ->orWhere("dp.primerApellido","LIKE","%".$req->nombre."%")
                ->orWhere("dp.segundoApellido","LIKE","%".$req->nombre."%")
                ->orWhereRaw("CONCAT(dp.primerApellido,' ',dp.segundoApellido,' ',dp.primerNombre,' ',dp.segundoNombre) LIKE '%".$req->nombre."%'");
            });
        }
        $bouchers = $bouchers->orderBy("dp.primerApellido")
        ->orderBy("dp.segundoApellido")
        ->orderBy("dp.primerNombre")
        ->orderBy("dp.segundoNombre")
        ->get();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó a la acción 'Ver solicitud de liquidacion sin editar' con id:".$idLiquidacion);

        return view('nomina.solicitudes.verSolicitudSinEdit', ["dataUsu" => $dataUsu,
            'bouchers' => $bouchers, "liquidaciones" => $liquidaciones, "req" => $req]);

    }

    public function cargarInfoxBoucher($idBoucherPago){

        $idItemBoucherPago = DB::table("item_boucher_pago","ibp")
        ->join("concepto AS c","c.idconcepto","=", "ibp.fkConcepto")
        ->where("ibp.fkBoucherPago","=",$idBoucherPago)
        ->get();


        $itemsBoucherPagoFueraNomina = DB::table("item_boucher_pago_fuera_nomina","ibp")
        ->join("concepto AS c","c.idconcepto","=", "ibp.fkConcepto")
        ->where("ibp.fkBoucherPago","=",$idBoucherPago)
        ->get();



        $empleado = DB::table("boucherpago","bp")
        ->select(["e.*", "emp.razonSocial", "e.idempleado"])
        ->join("empleado as e", "e.idempleado", "=", "bp.fkEmpleado")
        ->join("periodo as p", "p.idPeriodo", "=", "bp.fkPeriodoActivo")
        ->join("nomina as n", "n.idNomina", "=", "p.fkNomina")
        ->join("empresa as emp", "emp.idempresa", "=", "n.fkEmpresa")
        ->where("bp.idBoucherPago","=", $idBoucherPago)
        ->first();
           
        $boucher = DB::table("boucherpago","bp")
        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
        ->where("bp.idBoucherPago","=",$idBoucherPago)->first();

        $centrosCosto = DB::table("centrocosto","cc")
        ->join("empleado_centrocosto as ecc", "ecc.fkCentroCosto", "=", "cc.idCentroCosto")
        ->where("ecc.fkPeriodoActivo", "=", $boucher->fkPeriodoActivo)
        ->where("ecc.fkEmpleado", "=", $empleado->idempleado)->get();
        

        $conceptoSalario = DB::table("conceptofijo")->where("fkPeriodoActivo","=",$boucher->fkPeriodoActivo)
        ->whereIn("fkConcepto",[1,2,53,54,154])->first();
        
        
        


        $novedadesRetiro = DB::table("novedad","n")
        ->select("r.fecha")
        ->join("retiro AS r", "r.idRetiro","=","n.fkRetiro")
        ->where("n.fkEmpleado", "=", $empleado->idempleado)
        ->whereIn("n.fkEstado",["7", "8"])
        ->where("n.fkPeriodoActivo","=",$boucher->fkPeriodoActivo)
        ->whereNotNull("n.fkRetiro")
        ->first();

        
        $periodo = DB::table("periodo")->where("idPeriodo","=",$boucher->fkPeriodoActivo)->first();

        $liquidacion = DB::table("liquidacionnomina","ln")
        ->select("ln.fechaLiquida","bp.fkPeriodoActivo")
        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")
        ->where("bp.idBoucherPago","=",$idBoucherPago)
        ->first();
        

        $provisiones = DB::table("provision","p")
        ->where("p.mes","=",date("m",strtotime($liquidacion->fechaLiquida)))
        ->where("p.anio","=",date("Y",strtotime($liquidacion->fechaLiquida)))
        ->where("p.fkPeriodoActivo","=",$liquidacion->fkPeriodoActivo)
        ->get();
        
        $empleado->fkTipoCotizante = ($periodo->fkTipoCotizante ?? $empleado->fkTipoCotizante);

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó a la acción 'Ver detalles de liquidacion' para el comprobante de pago:".$idBoucherPago);

        return view('nomina.solicitudes.verItemBoucher', [
            'novedadesRetiro' => $novedadesRetiro, 
            'infoBoucher' => $idItemBoucherPago, 
            'itemsBoucherPagoFueraNomina' => $itemsBoucherPagoFueraNomina,
            "centrosCosto" => $centrosCosto, 
            "empleado" => $empleado, 
            'conceptoSalario' => $conceptoSalario,
            "boucher" => $boucher,
            "provisiones" => $provisiones,
            "periodo_activo" => $periodo
        ]);
    }
    
    public function reversar($idLiquidacion){

        $liquida = DB::table("liquidacionnomina")
        ->where("idLiquidacionNomina", "=", $idLiquidacion)
        ->first();

        $cierreAnte = DB::table("cierre","c")
        ->join("nomina as n","n.fkEmpresa", "=", "c.fkEmpresa")
        ->where("n.idNomina","=",$liquida->fkNomina)
        ->where("c.mes","=", date("m",strtotime($liquida->fechaLiquida)))
        ->where("c.anio","=", date("Y",strtotime($liquida->fechaLiquida)))
        ->where("c.fkEstado","=", "1")
        ->first();
        $usu = UsuarioController::dataAdminLogueado();
        if(isset($cierreAnte)){
            
            return view('/layouts.respuestaGen',[
                "dataUsu" => $usu,
                "titulo" => "Error ya se cerró ese periodo",
                "mensaje" =>"Error ya se cerró ese periodo"
            ]);

        }


        $novedadesRetiro = DB::table('novedad',"n")
        ->select("n.*", "dp.*")
        ->join("empleado as e","e.idempleado","=","n.fkEmpleado")
        ->join("datospersonales as dp","dp.idDatosPersonales","=","e.fkDatosPersonales")
        ->whereRaw("n.idNovedad in(
            Select itbn.fkNovedad from item_boucher_pago_novedad as itbn where itbn.fkItemBoucher in(
                SELECT ibp.idItemBoucherPago from item_boucher_pago as ibp WHERE ibp.fkBoucherPago IN(
                    Select bp.idBoucherPago from boucherpago as bp WHERE bp.fkLiquidacion = '".$idLiquidacion."'
                    )
                )
            )")
        ->whereNotNull("n.fkRetiro")
        ->get();
        $error = false;
        $empleadosConPeriodosNuevos = array();
        foreach($novedadesRetiro as $novedadRetiro){
            $periodoActivoPersona =  DB::table("periodo")->where("fkEmpleado","=",$novedadRetiro->fkEmpleado)->where("fkEstado","=","1")->first();
            if(isset($periodoActivoPersona)){
                $error = true;
                array_push($empleadosConPeriodosNuevos, $novedadRetiro->numeroIdentificacion);
            }
            
        }
        
        if($error){
            return view('/layouts.respuestaGen',[
                "dataUsu" => $usu,
                "titulo" => "El o los empleados ya tienen un nuevo periodo",
                "mensaje" =>"El o los empleados ya tienen un nuevo periodo: ".implode(",", $empleadosConPeriodosNuevos)
            ]);
        }

        foreach($novedadesRetiro as $novedadRetiro){
            DB::table("periodo")->where("idPeriodo","=",$novedadRetiro->fkPeriodoActivo)->update(["fkEstado" => "1"]);

            DB::table("contrato","con")
            ->where("con.fkPeriodoActivo","=",$novedadRetiro->fkPeriodoActivo)
            ->where("con.fkEstado","=","2")
            ->update([
                "fkEstado" => "1"
            ]);
        }
        

        $affected = DB::table("liquidacionnomina")
        ->where("idLiquidacionNomina", "=", $idLiquidacion)
        ->update(["fkEstado" => "6"]);

        $arrNovedad = ["fkEstado" => "7"];
        $affected = DB::table('novedad',"n")->
        whereRaw("n.idNovedad in(
            Select itbn.fkNovedad from item_boucher_pago_novedad as itbn where itbn.fkItemBoucher in (
                SELECT ibp.idItemBoucherPago from item_boucher_pago as ibp WHERE ibp.fkBoucherPago IN(
                    Select bp.idBoucherPago from boucherpago as bp WHERE bp.fkLiquidacion = '".$idLiquidacion."'
                )
            )
        )")
        ->update($arrNovedad);


        $arrSaldo = ["fkEstado" => "7"];
        $affected = DB::table('saldo',"s")->
        whereRaw("s.idSaldo in(Select itbs.fkSaldo from item_boucher_pago_saldo as itbs where itbs.fkItemBoucher in(SELECT ibp.idItemBoucherPago from item_boucher_pago as ibp WHERE ibp.fkBoucherPago IN(Select bp.idBoucherPago from boucherpago as bp WHERE bp.fkLiquidacion = '".$idLiquidacion."')))")
        ->update($arrSaldo);



        $arrSaldo = ["fkEstado" => "7"];
        $affected = DB::table('cambiotipocotizante',"ctc")->
        whereRaw("ctc.idCambioTipoCotizante in(Select itbs.fkCambioTipoCotizante from item_boucher_pago_cambio_tipo_cot as itbs where itbs.	fkItemBoucherPago in(SELECT ibp.idItemBoucherPago from item_boucher_pago as ibp WHERE ibp.fkBoucherPago IN(Select bp.idBoucherPago from boucherpago as bp WHERE bp.fkLiquidacion = '".$idLiquidacion."')))")
        ->update($arrSaldo);


        $arrSaldo = ["fkEstado" => "7"];
        $affected = DB::table('saldo',"s")->
        whereRaw("s.idSaldo in(Select itbs.fkSaldo from item_boucher_pago_fuera_nomina_saldo as itbs where itbs.fkItemBoucherFueraNomina in(SELECT ibp.idItemBoucherPagoFueraNomina from item_boucher_pago_fuera_nomina as ibp WHERE ibp.fkBoucherPago IN(Select bp.idBoucherPago from boucherpago as bp WHERE bp.fkLiquidacion = '".$idLiquidacion."')))")
        ->update($arrSaldo);

        $prestamos = DB::table("prestamo","p")
        ->select("p.*","ibpp.valor")
        ->join("item_boucher_pago_prestamo as ibpp","ibpp.fkPrestamo","=","p.idPrestamo")
        ->join("item_boucher_pago as ibp","ibp.idItemBoucherPago","=","ibpp.fkItemBoucher")
        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
        ->where("bp.fkLiquidacion","=",$idLiquidacion)->get();


        foreach($prestamos as $prestamo){
            
            $nuevoSaldo = $prestamo->saldoActual + $prestamo->valor;
            $arrUpdatePrestamo = array("saldoActual" => $nuevoSaldo);            
            $arrUpdatePrestamo["fkEstado"] = "1";
            

            DB::table("prestamo")
            ->where("idPrestamo","=",$prestamo->idPrestamo)
            ->update($arrUpdatePrestamo);
        }
        
        


        $affected = DB::table('empleado',"e")->
        whereRaw("
            e.idempleado in (
                Select n.fkEmpleado from novedad as n where n.fkRetiro is not null and n.idNovedad in(
                    Select itbn.fkNovedad from item_boucher_pago_novedad as itbn where itbn.fkItemBoucher in(
                        SELECT ibp.idItemBoucherPago from item_boucher_pago as ibp WHERE ibp.fkBoucherPago IN(
                            Select bp.idBoucherPago from boucherpago as bp WHERE bp.fkLiquidacion = '".$idLiquidacion."'
                        )
                    )
                )
            )")
        ->update([
            "fkEstado" => "1"
        ]);

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", reversó la liquidación con id:".$idLiquidacion);

        return redirect('/nomina/solicitudLiquidacion/');
    }
    public function aprobarSolicitud(Request $req){

        $usuario = Auth::user();

        $affected = DB::table("liquidacionnomina")
        ->where("idLiquidacionNomina", "=", $req->idLiquidacion)
        ->update(["fkEstado" => "5","fkUserAprueba" => $usuario->id]);

        $arrNovedad = ["fkEstado" => "8"];
        $affected = DB::table('novedad',"n")->
        whereRaw("n.idNovedad 
        in(Select itbn.fkNovedad from item_boucher_pago_novedad as itbn where itbn.parcial = 0 and itbn.fkItemBoucher 
            in(SELECT ibp.idItemBoucherPago from item_boucher_pago as ibp WHERE ibp.fkBoucherPago 
                IN(Select bp.idBoucherPago from boucherpago as bp WHERE bp.fkLiquidacion = '".$req->idLiquidacion."')))")
        ->update($arrNovedad);


        $arrNovedad = ["fkEstado" => "16"];
        $affected = DB::table('novedad',"n")->
        whereRaw("n.idNovedad 
        in(Select itbn.fkNovedad from item_boucher_pago_novedad as itbn where itbn.parcial = 1 and itbn.fkItemBoucher 
            in(SELECT ibp.idItemBoucherPago from item_boucher_pago as ibp WHERE ibp.fkBoucherPago 
                IN(Select bp.idBoucherPago from boucherpago as bp WHERE bp.fkLiquidacion = '".$req->idLiquidacion."')))")
        ->update($arrNovedad);

        
        $pagosParciales = DB::table('novedad',"n")->
        whereRaw("n.idNovedad 
        in(Select itbn.fkNovedad from item_boucher_pago_novedad as itbn where itbn.parcial = 1 and itbn.fkItemBoucher 
            in(SELECT ibp.idItemBoucherPago from item_boucher_pago as ibp WHERE ibp.fkBoucherPago 
                IN(Select bp.idBoucherPago from boucherpago as bp WHERE bp.fkLiquidacion = '".$req->idLiquidacion."')))")
        ->get();
        foreach($pagosParciales as $pagoParcial){
            if($pagoParcial->fkVacaciones){
                $vacaciones = DB::table("vacaciones","v")
                ->where("v.idVacaciones","=",$pagoParcial->fkVacaciones)->first();

                $cantidadITBN = DB::table("item_boucher_pago_novedad","itbn")
                ->selectRaw("sum(itbn.cantidad) as suma")
                ->where("itbn.fkNovedad","=",$pagoParcial->idNovedad)
                ->first();


                if($cantidadITBN->suma >= $vacaciones->diasCompensar){
                    DB::table('novedad',"n")->where("n.idNovedad","=",$pagoParcial->idNovedad)->update(["fkEstado" => "8"]);
                }
            }
        }

        $arrSaldo = ["fkEstado" => "8"];
        $affected = DB::table('saldo',"s")->
        whereRaw("s.idSaldo in(Select itbs.fkSaldo from item_boucher_pago_saldo as itbs where itbs.fkItemBoucher in(SELECT ibp.idItemBoucherPago from item_boucher_pago as ibp WHERE ibp.fkBoucherPago IN(Select bp.idBoucherPago from boucherpago as bp WHERE bp.fkLiquidacion = '".$req->idLiquidacion."')))")
        ->update($arrSaldo);

        $arrCambioCtc = ["fkEstado" => "8"];
        $affected = DB::table('cambiotipocotizante',"ctc")->
        whereRaw("ctc.idCambioTipoCotizante in(Select itbs.fkCambioTipoCotizante from item_boucher_pago_cambio_tipo_cot as itbs where itbs.	fkItemBoucherPago in(SELECT ibp.idItemBoucherPago from item_boucher_pago as ibp WHERE ibp.fkBoucherPago IN(Select bp.idBoucherPago from boucherpago as bp WHERE bp.fkLiquidacion = '".$req->idLiquidacion."')))")
        ->update($arrCambioCtc);


        $arrSaldo = ["fkEstado" => "8"];
        $affected = DB::table('saldo',"s")->
        whereRaw("s.idSaldo in(
            Select itbs.fkSaldo from item_boucher_pago_fuera_nomina_saldo as itbs where itbs.fkItemBoucherFueraNomina in(
                SELECT ibp.idItemBoucherPagoFueraNomina from item_boucher_pago_fuera_nomina as ibp WHERE ibp.fkBoucherPago IN(
                    Select bp.idBoucherPago from boucherpago as bp WHERE bp.fkLiquidacion = '".$req->idLiquidacion."')
                )
            )")
        ->update($arrSaldo);

        

        $novedadesRetiro = DB::table('novedad',"n")
        ->join("retiro as r","r.idRetiro", "=","n.fkRetiro")
        ->whereRaw("n.idNovedad in(
            Select itbn.fkNovedad from item_boucher_pago_novedad as itbn where itbn.fkItemBoucher in(
                SELECT ibp.idItemBoucherPago from item_boucher_pago as ibp WHERE ibp.fkBoucherPago IN(
                    Select bp.idBoucherPago from boucherpago as bp WHERE bp.fkLiquidacion = '".$req->idLiquidacion."')
                    )
                )")
        ->whereNotNull("n.fkRetiro")
        ->get();
        
        foreach($novedadesRetiro as $novedadRetiro){

            $salarioCf = DB::table("conceptofijo", "cf")
            ->where("cf.fkEmpleado","=",$novedadRetiro->fkEmpleado)
            ->whereIn("cf.fkConcepto",["1","2","53","54","154"])->first();

            $empleado = DB::table("empleado", "e")->where("e.idempleado","=",$novedadRetiro->fkEmpleado)->first();
            $contrato = DB::table("contrato","con")->where("con.fkPeriodoActivo","=",$novedadRetiro->fkPeriodoActivo)
            ->orderBy("idcontrato","desc")
            ->first();
            

            
            DB::table("periodo")->where("idPeriodo","=",$novedadRetiro->fkPeriodoActivo)
            ->update([
                "fkEstado" => "2", 
                "fechaFin" => $novedadRetiro->fecha, 
                "salario" => $salarioCf->valor,
                "fkTipoContrato" => $contrato->fkTipoContrato,
            ]);

            DB::table("contrato","con")->where("con.fkPeriodoActivo","=",$novedadRetiro->fkPeriodoActivo)->update([
                "fkEstado" => "2"
            ]);
            $periodosActivos = DB::table('periodo', "p")
            ->where("p.fkEmpleado","=",$novedadRetiro->fkEmpleado)
            ->where("p.fkEstado","=","1")
            ->first();
            if(!isset($periodosActivos)){
                DB::table("empleado","e")->where("e.idempleado","=",$novedadRetiro->fkEmpleado)->update(["fkEstado" => "2"]);        
            }           

            //Eliminar provisiones de fechas futuras
            DB::table("provision")
            ->where("fkPeriodoActivo","=",$novedadRetiro->fkPeriodoActivo)
            ->where("anio",">=", date("Y",strtotime($novedadRetiro->fecha)))
            ->where("mes",">", date("m",strtotime($novedadRetiro->fecha)))
            ->delete();
        }
        
        $prestamos = DB::table("prestamo","p")
        ->select("p.*","ibpp.valor")
        ->join("item_boucher_pago_prestamo as ibpp","ibpp.fkPrestamo","=","p.idPrestamo")
        ->join("item_boucher_pago as ibp","ibp.idItemBoucherPago","=","ibpp.fkItemBoucher")
        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
        ->where("bp.fkLiquidacion","=",$req->idLiquidacion)
        ->get();


        foreach($prestamos as $prestamo){
            
            $nuevoSaldo = $prestamo->saldoActual - $prestamo->valor;
            $arrUpdatePrestamo = array("saldoActual" => $nuevoSaldo);
            if($nuevoSaldo == 0){
                $arrUpdatePrestamo["fkEstado"] = "8";
            }


            DB::table("prestamo")
            ->where("idPrestamo","=",$prestamo->idPrestamo)
            ->update($arrUpdatePrestamo);
        }


        //INICIO CALCULO SS EMPLEADOR CON DOC DE SS
        $reportes = new ReportesNominaController();
        $liquidacion = DB::table("liquidacionnomina","ln")
        ->join("nomina as n", "n.idNomina", "=","ln.fkNomina")
        ->where("ln.idLiquidacionNomina", "=", $req->idLiquidacion)
        ->first();

        $arrSeguridadSocial = $reportes->documentoSSArray($liquidacion->fkEmpresa, $liquidacion->fechaInicio);
        $prevBoucher = 0;
        $prevAfp = 0;
        $prevAporteVoluntario = 0;
        $prevEps = 0;
        $prevArl = 0;
        $prevCcf = 0;
        $prevIcbf = 0;
        $prevSena = 0;

        foreach($arrSeguridadSocial as $itemSeguridadSocial){

            if(isset($itemSeguridadSocial[101])){
                if($prevBoucher != 0 && $prevBoucher != $itemSeguridadSocial[101]){
                   
                    //Modifico registro de parafiscales de la suma de SS
                    $boucherPago = DB::table('boucherpago',"bp")
                    ->select("ln.fechaInicio","bp.fkEmpleado","bp.fkPeriodoActivo")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
                    ->where("bp.idBoucherPago","=",$prevBoucher)->first();
                    $fechaInicio = date("Y-m-01",strtotime($boucherPago->fechaInicio));
                    $fechaFin = date("Y-m-t",strtotime($boucherPago->fechaInicio));

                    //Cargar la suma de aportes en el mes
                    $itemsBoucherPension = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.descuento) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$boucherPago->fkEmpleado)
                    ->where("bp.fkPeriodoActivo", "=",$boucherPago->fkPeriodoActivo)
                    ->whereBetween("ln.fechaLiquida",[$fechaInicio, $fechaFin])
                    ->where("ibp.fkConcepto","=","19") //19 - APORTE PENSIÓN
                    ->first();

                    $prevAfp = $prevAfp - round($itemsBoucherPension->suma ?? 0);

                    //Cargar la suma de aportes en el mes
                    $itemsBoucherAporteVoluntario = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.descuento) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$boucherPago->fkEmpleado)
                    ->where("bp.fkPeriodoActivo", "=",$boucherPago->fkPeriodoActivo)
                    ->whereBetween("ln.fechaLiquida",[$fechaInicio, $fechaFin])
                    ->where("ibp.fkConcepto","=","33") //19 - APORTE PENSIÓN
                    ->first();

                    $prevAporteVoluntario = $prevAporteVoluntario - round($itemsBoucherAporteVoluntario->suma ?? 0);                    
                    $prevAfp = $prevAfp; //NOTA: Hay que cambiarlo a "parafiscal" aparte




                    $itemsBoucherSalud = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.descuento) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$boucherPago->fkEmpleado)
                    ->where("bp.fkPeriodoActivo", "=",$boucherPago->fkPeriodoActivo)
                    ->whereBetween("ln.fechaLiquida",[$fechaInicio, $fechaFin])
                    ->where("ibp.fkConcepto","=","18") //19 - APORTE SALUD
                    ->first();

                    $prevEps = $prevEps - round($itemsBoucherSalud->suma ?? 0);
                    


                    $parafiscales = DB::table('parafiscales',"p")
                    ->where("p.fkBoucherPago","=",$prevBoucher)->first();
                    
                    $arrModParaFiscales = [
                        "afp" => intval($prevAfp),
                        "eps" => intval($prevEps),
                        "arl" => intval($prevArl),
                        "ccf" => intval($prevCcf),
                        "icbf" => intval($prevIcbf),
                        "sena" => intval($prevSena),
                        "fondoSolidaridad" => intval($prevAporteVoluntario)
                    ];

                    if(isset($parafiscales)){
                        DB::table('parafiscales')
                        ->where("idParafiscales","=",$parafiscales->idParafiscales)
                        ->update($arrModParaFiscales);
                    }
                    
                }
                if($prevBoucher != $itemSeguridadSocial[101]){
                    //Reseteo variables 
                    $prevAfp = 0;
                    $prevEps = 0;
                    $prevArl = 0;
                    $prevCcf = 0;
                    $prevIcbf = 0;
                    $prevSena = 0;
                    $prevAporteVoluntario = 0;
                    //Asigno nuevo prevBoucher 
                    $prevBoucher =  $itemSeguridadSocial[101];
                }
               

                $prevAfp += intval($itemSeguridadSocial[46]);
                $prevAporteVoluntario += intval($itemSeguridadSocial[50]);
                $prevAporteVoluntario += intval($itemSeguridadSocial[51]);
                $prevEps += intval($itemSeguridadSocial[54]);
                $prevArl += intval($itemSeguridadSocial[62]);
                $prevCcf += intval($itemSeguridadSocial[64]);
                $prevSena += intval($itemSeguridadSocial[66]);
                $prevIcbf += intval($itemSeguridadSocial[68]);

                
            }
        }
        if($prevBoucher != 0 ){

           
            //Modifico registro de parafiscales de la suma de SS
            $boucherPago = DB::table('boucherpago',"bp")
            ->select("ln.fechaInicio","bp.fkEmpleado", "bp.fkPeriodoActivo")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
            ->where("bp.idBoucherPago","=",$prevBoucher)->first();
            $fechaInicio = date("Y-m-01",strtotime($boucherPago->fechaInicio));
            $fechaFin = date("Y-m-t",strtotime($boucherPago->fechaInicio));

            //Cargar la suma de aportes en el mes
            $itemsBoucherPension = DB::table("item_boucher_pago", "ibp")
            ->selectRaw("Sum(ibp.descuento) as suma")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->where("bp.fkEmpleado","=",$boucherPago->fkEmpleado)
            ->where("bp.fkPeriodoActivo", "=",$boucherPago->fkPeriodoActivo)
            ->whereBetween("ln.fechaLiquida",[$fechaInicio, $fechaFin])
            ->where("ibp.fkConcepto","=","19") //19 - APORTE PENSIÓN
            ->first();

            $prevAfp = $prevAfp - ($itemsBoucherPension->suma ?? 0);

            //Cargar la suma de aportes en el mes
            $itemsBoucherAporteVoluntario = DB::table("item_boucher_pago", "ibp")
            ->selectRaw("Sum(ibp.descuento) as suma")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->where("bp.fkEmpleado","=",$boucherPago->fkEmpleado)
            ->where("bp.fkPeriodoActivo", "=",$boucherPago->fkPeriodoActivo)
            ->whereBetween("ln.fechaLiquida",[$fechaInicio, $fechaFin])
            ->where("ibp.fkConcepto","=","33") //19 - APORTE PENSIÓN
            ->first();

            $prevAporteVoluntario = $prevAporteVoluntario - round($itemsBoucherAporteVoluntario->suma ?? 0);                    
            $prevAfp = $prevAfp; //NOTA: Hay que cambiarlo a "parafiscal" aparte

            $itemsBoucherSalud = DB::table("item_boucher_pago", "ibp")
            ->selectRaw("Sum(ibp.descuento) as suma")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->where("bp.fkEmpleado","=",$boucherPago->fkEmpleado)
            ->where("bp.fkPeriodoActivo", "=",$boucherPago->fkPeriodoActivo)
            ->whereBetween("ln.fechaLiquida",[$fechaInicio, $fechaFin])
            ->where("ibp.fkConcepto","=","18") //19 - APORTE SALUD
            ->first();

            $prevEps = $prevEps - ($itemsBoucherSalud->suma ?? 0);
            




            $parafiscales = DB::table('parafiscales',"p")
            ->where("p.fkBoucherPago","=",$prevBoucher)->first();
            $arrModParaFiscales = [
                "afp" => intval($prevAfp),
                "eps" => intval($prevEps),
                "arl" => intval($prevArl),
                "ccf" => intval($prevCcf),
                "icbf" => intval($prevIcbf),
                "sena" => intval($prevSena),
            ];


            if(isset($parafiscales)){
                DB::table('parafiscales')
                ->where("idParafiscales","=",$parafiscales->idParafiscales)
                ->update($arrModParaFiscales);
            }
            
        }
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", aprobó la liquidación con id:".$req->idLiquidacion);
        return response()->json([
            "success" => true
        ]);
    }

    public function unirSSyContabilidad($idLiquidacion, $idEmpleado = NULL){
        //INICIO CALCULO SS EMPLEADOR CON DOC DE SS
        $reportes = new ReportesNominaController();
        $liquidacion = DB::table("liquidacionnomina","ln")
        ->join("nomina as n", "n.idNomina", "=","ln.fkNomina")
        ->where("ln.idLiquidacionNomina", "=", $idLiquidacion)
        ->first();

        $arrSeguridadSocial = $reportes->documentoSSArray($liquidacion->fkEmpresa, $liquidacion->fechaInicio);
        $prevBoucher = 0;
        $prevAfp = 0;
        $prevAporteVoluntario = 0;
        $prevEps = 0;
        $prevArl = 0;
        $prevCcf = 0;
        $prevIcbf = 0;
        $prevSena = 0;

        foreach($arrSeguridadSocial as $itemSeguridadSocial){

            if(isset($itemSeguridadSocial[101])){
                if($prevBoucher != 0 && $prevBoucher != $itemSeguridadSocial[101]){
                   
                    //Modifico registro de parafiscales de la suma de SS
                    $boucherPago = DB::table('boucherpago',"bp")
                    ->select("ln.fechaInicio","bp.fkEmpleado","bp.fkPeriodoActivo")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
                    ->where("bp.idBoucherPago","=",$prevBoucher)->first();
                    $fechaInicio = date("Y-m-01",strtotime($boucherPago->fechaInicio));
                    $fechaFin = date("Y-m-t",strtotime($boucherPago->fechaInicio));

                    //Cargar la suma de aportes en el mes
                    $itemsBoucherPension = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.descuento) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$boucherPago->fkEmpleado)
                    ->where("bp.fkPeriodoActivo", "=",$boucherPago->fkPeriodoActivo)
                    ->whereBetween("ln.fechaLiquida",[$fechaInicio, $fechaFin])
                    ->where("ibp.fkConcepto","=","19") //19 - APORTE PENSIÓN
                    ->first();

                    $prevAfp = $prevAfp - round($itemsBoucherPension->suma ?? 0);

                    //Cargar la suma de aportes en el mes
                    $itemsBoucherAporteVoluntario = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.descuento) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$boucherPago->fkEmpleado)
                    ->where("bp.fkPeriodoActivo", "=",$boucherPago->fkPeriodoActivo)
                    ->whereBetween("ln.fechaLiquida",[$fechaInicio, $fechaFin])
                    ->where("ibp.fkConcepto","=","33") //19 - APORTE PENSIÓN
                    ->first();

                    $prevAporteVoluntario = $prevAporteVoluntario - round($itemsBoucherAporteVoluntario->suma ?? 0);                    
                    $prevAfp = $prevAfp; //NOTA: Hay que cambiarlo a "parafiscal" aparte




                    $itemsBoucherSalud = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.descuento) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$boucherPago->fkEmpleado)
                    ->where("bp.fkPeriodoActivo", "=",$boucherPago->fkPeriodoActivo)
                    ->whereBetween("ln.fechaLiquida",[$fechaInicio, $fechaFin])
                    ->where("ibp.fkConcepto","=","18") //19 - APORTE SALUD
                    ->first();

                    $prevEps = $prevEps - round($itemsBoucherSalud->suma ?? 0);
                    


                    $parafiscales = DB::table('parafiscales',"p")
                    ->where("p.fkBoucherPago","=",$prevBoucher)->first();
                    
                    $arrModParaFiscales = [
                        "afp" => intval($prevAfp),
                        "eps" => intval($prevEps),
                        "arl" => intval($prevArl),
                        "ccf" => intval($prevCcf),
                        "icbf" => intval($prevIcbf),
                        "sena" => intval($prevSena),
                        "fondoSolidaridad" => intval($prevAporteVoluntario)
                    ];

                    if(isset($parafiscales)){
                        DB::table('parafiscales')
                        ->where("idParafiscales","=",$parafiscales->idParafiscales)
                        ->update($arrModParaFiscales);
                    }
                    else{
                        $arrModParaFiscales["fkBoucherPago"] = $prevBoucher;
                        DB::table('parafiscales')->insert($arrModParaFiscales);
                    }
                    
                }
                if($prevBoucher != $itemSeguridadSocial[101]){
                    //Reseteo variables 
                    $prevAfp = 0;
                    $prevEps = 0;
                    $prevArl = 0;
                    $prevCcf = 0;
                    $prevIcbf = 0;
                    $prevSena = 0;
                    $prevAporteVoluntario = 0;
                    //Asigno nuevo prevBoucher 
                    $prevBoucher =  $itemSeguridadSocial[101];
                }
               

                $prevAfp += intval($itemSeguridadSocial[46]);
                $prevAporteVoluntario += intval($itemSeguridadSocial[50]);
                $prevAporteVoluntario += intval($itemSeguridadSocial[51]);
                $prevEps += intval($itemSeguridadSocial[54]);
                $prevArl += intval($itemSeguridadSocial[62]);
                $prevCcf += intval($itemSeguridadSocial[64]);
                $prevSena += intval($itemSeguridadSocial[66]);
                $prevIcbf += intval($itemSeguridadSocial[68]);

                
            }
        }
        if($prevBoucher != 0 ){

           
            //Modifico registro de parafiscales de la suma de SS
            $boucherPago = DB::table('boucherpago',"bp")
            ->select("ln.fechaInicio","bp.fkEmpleado", "bp.fkPeriodoActivo")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
            ->where("bp.idBoucherPago","=",$prevBoucher)->first();
            $fechaInicio = date("Y-m-01",strtotime($boucherPago->fechaInicio));
            $fechaFin = date("Y-m-t",strtotime($boucherPago->fechaInicio));

            //Cargar la suma de aportes en el mes
            $itemsBoucherPension = DB::table("item_boucher_pago", "ibp")
            ->selectRaw("Sum(ibp.descuento) as suma")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->where("bp.fkEmpleado","=",$boucherPago->fkEmpleado)
            ->where("bp.fkPeriodoActivo", "=",$boucherPago->fkPeriodoActivo)
            ->whereBetween("ln.fechaLiquida",[$fechaInicio, $fechaFin])
            ->where("ibp.fkConcepto","=","19") //19 - APORTE PENSIÓN
            ->first();

            $prevAfp = $prevAfp - ($itemsBoucherPension->suma ?? 0);

            //Cargar la suma de aportes en el mes
            $itemsBoucherAporteVoluntario = DB::table("item_boucher_pago", "ibp")
            ->selectRaw("Sum(ibp.descuento) as suma")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->where("bp.fkEmpleado","=",$boucherPago->fkEmpleado)
            ->where("bp.fkPeriodoActivo", "=",$boucherPago->fkPeriodoActivo)
            ->whereBetween("ln.fechaLiquida",[$fechaInicio, $fechaFin])
            ->where("ibp.fkConcepto","=","33") //19 - APORTE PENSIÓN
            ->first();

            $prevAporteVoluntario = $prevAporteVoluntario - round($itemsBoucherAporteVoluntario->suma ?? 0);                    
            $prevAfp = $prevAfp; //NOTA: Hay que cambiarlo a "parafiscal" aparte

            $itemsBoucherSalud = DB::table("item_boucher_pago", "ibp")
            ->selectRaw("Sum(ibp.descuento) as suma")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->where("bp.fkEmpleado","=",$boucherPago->fkEmpleado)
            ->where("bp.fkPeriodoActivo", "=",$boucherPago->fkPeriodoActivo)
            ->whereBetween("ln.fechaLiquida",[$fechaInicio, $fechaFin])
            ->where("ibp.fkConcepto","=","18") //19 - APORTE SALUD
            ->first();

            $prevEps = $prevEps - ($itemsBoucherSalud->suma ?? 0);
            




            $parafiscales = DB::table('parafiscales',"p")
            ->where("p.fkBoucherPago","=",$prevBoucher)->first();
            $arrModParaFiscales = [
                "afp" => intval($prevAfp),
                "eps" => intval($prevEps),
                "arl" => intval($prevArl),
                "ccf" => intval($prevCcf),
                "icbf" => intval($prevIcbf),
                "sena" => intval($prevSena),
            ];


            if(isset($parafiscales)){
                DB::table('parafiscales')
                ->where("idParafiscales","=",$parafiscales->idParafiscales)
                ->update($arrModParaFiscales);
            }
            else{
                $arrModParaFiscales["fkBoucherPago"] = $prevBoucher;
                DB::table('parafiscales')->insert($arrModParaFiscales);
            }
            
        }

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", unió la SS y la contabilidad de la liquidacion:".$idLiquidacion);
        return response()->json([
            "success" => true
        ]);
    }

    public function unirSSForm(){
        $dataUsu = UsuarioController::dataAdminLogueado();
        $empresas = DB::table("empresa", "e");
        if(isset($dataUsu) && $dataUsu->fkRol == 2){            
            $empresas = $empresas->whereIn("idempresa", $dataUsu->empresaUsuario);
        }
        $empresas = $empresas->orderBy("razonSocial")->get();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu 'Unir SS y contabilidad general'");

        return view('/nomina.seleccionarUnir',[
            'empresas' => $empresas,
            "dataUsu" => $dataUsu            
        ]);
    }

    public function unirSSyContabilidadGeneral(Request $req){
        $reportes = new ReportesNominaController();

        $arrSeguridadSocial = $reportes->documentoSSArray($req->empresa, $req->fechaDocumento);
        $prevBoucher = 0;
        $prevAfp = 0;
        $prevAporteVoluntario = 0;
        $prevEps = 0;
        $prevArl = 0;
        $prevCcf = 0;
        $prevIcbf = 0;
        $prevSena = 0;

        foreach($arrSeguridadSocial as $itemSeguridadSocial){

            if(isset($itemSeguridadSocial[101])){
                if($prevBoucher != 0 && $prevBoucher != $itemSeguridadSocial[101]){
                   
                    //Modifico registro de parafiscales de la suma de SS
                    $boucherPago = DB::table('boucherpago',"bp")
                    ->select("ln.fechaInicio","bp.fkEmpleado","bp.fkPeriodoActivo")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
                    ->where("bp.idBoucherPago","=",$prevBoucher)->first();
                    $fechaInicio = date("Y-m-01",strtotime($boucherPago->fechaInicio));
                    $fechaFin = date("Y-m-t",strtotime($boucherPago->fechaInicio));

                    //Cargar la suma de aportes en el mes
                    $itemsBoucherPension = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.descuento) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$boucherPago->fkEmpleado)
                    ->where("bp.fkPeriodoActivo", "=",$boucherPago->fkPeriodoActivo)
                    ->whereBetween("ln.fechaLiquida",[$fechaInicio, $fechaFin])
                    ->where("ibp.fkConcepto","=","19") //19 - APORTE PENSIÓN
                    ->first();

                    $prevAfp = $prevAfp - round($itemsBoucherPension->suma ?? 0);

                    //Cargar la suma de aportes en el mes
                    $itemsBoucherAporteVoluntario = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.descuento) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$boucherPago->fkEmpleado)
                    ->where("bp.fkPeriodoActivo", "=",$boucherPago->fkPeriodoActivo)
                    ->whereBetween("ln.fechaLiquida",[$fechaInicio, $fechaFin])
                    ->where("ibp.fkConcepto","=","33") //19 - APORTE PENSIÓN
                    ->first();

                    $prevAporteVoluntario = $prevAporteVoluntario - round($itemsBoucherAporteVoluntario->suma ?? 0);                    
                    $prevAfp = $prevAfp; //NOTA: Hay que cambiarlo a "parafiscal" aparte




                    $itemsBoucherSalud = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.descuento) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$boucherPago->fkEmpleado)
                    ->where("bp.fkPeriodoActivo", "=",$boucherPago->fkPeriodoActivo)
                    ->whereBetween("ln.fechaLiquida",[$fechaInicio, $fechaFin])
                    ->where("ibp.fkConcepto","=","18") //19 - APORTE SALUD
                    ->first();

                    $prevEps = $prevEps - round($itemsBoucherSalud->suma ?? 0);
                    


                    $parafiscales = DB::table('parafiscales',"p")
                    ->where("p.fkBoucherPago","=",$prevBoucher)->first();
                    
                    $arrModParaFiscales = [
                        "afp" => intval($prevAfp),
                        "eps" => intval($prevEps),
                        "arl" => intval($prevArl),
                        "ccf" => intval($prevCcf),
                        "icbf" => intval($prevIcbf),
                        "sena" => intval($prevSena),
                        "fondoSolidaridad" => intval($prevAporteVoluntario)
                    ];

                    if(isset($parafiscales)){
                        DB::table('parafiscales')
                        ->where("idParafiscales","=",$parafiscales->idParafiscales)
                        ->update($arrModParaFiscales);
                    }
                    else{
                        $arrModParaFiscales["fkBoucherPago"] = $prevBoucher;
                        DB::table('parafiscales')->insert($arrModParaFiscales);
                    }
                    
                }
                if($prevBoucher != $itemSeguridadSocial[101]){
                    //Reseteo variables 
                    $prevAfp = 0;
                    $prevEps = 0;
                    $prevArl = 0;
                    $prevCcf = 0;
                    $prevIcbf = 0;
                    $prevSena = 0;
                    $prevAporteVoluntario = 0;
                    //Asigno nuevo prevBoucher 
                    $prevBoucher =  $itemSeguridadSocial[101];
                }
               

                $prevAfp += intval($itemSeguridadSocial[46]);
                $prevAporteVoluntario += intval($itemSeguridadSocial[50]);
                $prevAporteVoluntario += intval($itemSeguridadSocial[51]);
                $prevEps += intval($itemSeguridadSocial[54]);
                $prevArl += intval($itemSeguridadSocial[62]);
                $prevCcf += intval($itemSeguridadSocial[64]);
                $prevSena += intval($itemSeguridadSocial[66]);
                $prevIcbf += intval($itemSeguridadSocial[68]);

                
            }
        }
        if($prevBoucher != 0 ){

           
            //Modifico registro de parafiscales de la suma de SS
            $boucherPago = DB::table('boucherpago',"bp")
            ->select("ln.fechaInicio","bp.fkEmpleado", "bp.fkPeriodoActivo")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
            ->where("bp.idBoucherPago","=",$prevBoucher)->first();
            $fechaInicio = date("Y-m-01",strtotime($boucherPago->fechaInicio));
            $fechaFin = date("Y-m-t",strtotime($boucherPago->fechaInicio));

            //Cargar la suma de aportes en el mes
            $itemsBoucherPension = DB::table("item_boucher_pago", "ibp")
            ->selectRaw("Sum(ibp.descuento) as suma")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->where("bp.fkEmpleado","=",$boucherPago->fkEmpleado)
            ->where("bp.fkPeriodoActivo", "=",$boucherPago->fkPeriodoActivo)
            ->whereBetween("ln.fechaLiquida",[$fechaInicio, $fechaFin])
            ->where("ibp.fkConcepto","=","19") //19 - APORTE PENSIÓN
            ->first();

            $prevAfp = $prevAfp - ($itemsBoucherPension->suma ?? 0);

            //Cargar la suma de aportes en el mes
            $itemsBoucherAporteVoluntario = DB::table("item_boucher_pago", "ibp")
            ->selectRaw("Sum(ibp.descuento) as suma")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->where("bp.fkEmpleado","=",$boucherPago->fkEmpleado)
            ->where("bp.fkPeriodoActivo", "=",$boucherPago->fkPeriodoActivo)
            ->whereBetween("ln.fechaLiquida",[$fechaInicio, $fechaFin])
            ->where("ibp.fkConcepto","=","33") //19 - APORTE PENSIÓN
            ->first();

            $prevAporteVoluntario = $prevAporteVoluntario - round($itemsBoucherAporteVoluntario->suma ?? 0);                    
            $prevAfp = $prevAfp; //NOTA: Hay que cambiarlo a "parafiscal" aparte

            $itemsBoucherSalud = DB::table("item_boucher_pago", "ibp")
            ->selectRaw("Sum(ibp.descuento) as suma")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->where("bp.fkEmpleado","=",$boucherPago->fkEmpleado)
            ->where("bp.fkPeriodoActivo", "=",$boucherPago->fkPeriodoActivo)
            ->whereBetween("ln.fechaLiquida",[$fechaInicio, $fechaFin])
            ->where("ibp.fkConcepto","=","18") //19 - APORTE SALUD
            ->first();

            $prevEps = $prevEps - ($itemsBoucherSalud->suma ?? 0);
            




            $parafiscales = DB::table('parafiscales',"p")
            ->where("p.fkBoucherPago","=",$prevBoucher)->first();
            $arrModParaFiscales = [
                "afp" => intval($prevAfp),
                "eps" => intval($prevEps),
                "arl" => intval($prevArl),
                "ccf" => intval($prevCcf),
                "icbf" => intval($prevIcbf),
                "sena" => intval($prevSena),
            ];


            if(isset($parafiscales)){
                DB::table('parafiscales')
                ->where("idParafiscales","=",$parafiscales->idParafiscales)
                ->update($arrModParaFiscales);
            }
            else{
                $arrModParaFiscales["fkBoucherPago"] = $prevBoucher;
                DB::table('parafiscales')->insert($arrModParaFiscales);
            }
            
        }
        
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", unió la SS y la contabilidad general de la empresa: ".$req->empresa." y en la fecha:".$req->fechaDocumento);
        return view('/layouts.respuestaGen',[
            "dataUsu" => $dataUsu,
            "titulo" => "Unir SS y contabilidad general",
            "mensaje" =>"Se ha realizado el proceso correctamente"
        ]);
    }


    public function cancelarSolicitud(Request $req){
        $affected = DB::table('liquidacionnomina')->where("idLiquidacionNomina", "=", $req->idLiquidacion)->delete();
        
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", canceló la liquidacion:".$req->idLiquidacion);

        return response()->json([
            "success" => true
        ]);
    }

    public function generarCierre(Request $req){
        $messages = [
            'required' => 'El campo :attribute es requerido.',
            'after_or_equal' => 'La :attribute debe ser mayor a la inicial.',
        ];
        $validator = Validator::make($req->all(), [
            'empresa' => 'required',
            'fechaCierre' => 'required|date'            
        ],$messages);

        if ($validator->fails()) {
            return response()->json([
                'error'=>$validator->errors()->all(),
                "success" => false
            ]);
        }

        $cierreAnt = DB::table("cierre")
        ->where("fkEmpresa","=", $req->empresa)
        ->where("mes","=", date("m",strtotime($req->fechaCierre)))
        ->where("anio","=", date("Y",strtotime($req->fechaCierre)))
        ->first();

        if(isset($cierreAnt)){
            // $usu = UsuarioController::dataAdminLogueado();
            // return view('/layouts.respuestaGen',[
            //     "dataUsu" => $usu,
            //     "titulo" => "Error ya existe un cierre de este periodo",
            //     "mensaje" => "Error ya existe un cierre de este periodo"
            // ]);
            $idCierre = $cierreAnt->idCierre;
        }
        else{
            $arrCierre = [
                "fkEmpresa" => $req->empresa,
                "mes" => date("m",strtotime($req->fechaCierre)),
                "anio" => date("Y",strtotime($req->fechaCierre))
            ];
    
            $idCierre = DB::table("cierre")->insertGetId($arrCierre, "idCierre");
            
        }

        

        
        //Lista empleados activos a fecha Cierre
        $empleados = DB::table("empleado", "e")
        ->select(["e.*", "p.fkNomina as fkNominaPeriodo","p.idPeriodo"])
        ->join("periodo as p","p.fkEmpleado", "=","e.idempleado")
        ->join("nomina as n","n.idNomina", "=","p.fkNomina")
        ->where("n.fkEmpresa","=",$req->empresa)
        //->where("p.fkEstado", "=","1")
        // ->where("p.idPeriodo", "=", "1101")
        ->get();
        
        $anioFechaDocumento = date("Y",strtotime($req->fechaCierre));
        $mesFechaDocumento = date("m",strtotime($req->fechaCierre));

        foreach($empleados as $empleado){
            
            $periodoActivoReintegro = DB::table("periodo")
            ->where("idPeriodo", "=", $empleado->idPeriodo)
            ->first();

            if($mesFechaDocumento == 6){



                $datosProvPrima = DB::table('provision','p')
                ->selectRaw("sum(valor) as suma")
                ->where("p.anio","=",$anioFechaDocumento)
                ->where("p.mes","<=",$mesFechaDocumento)
                ->where("p.fkEmpleado","=",$empleado->idempleado)
                ->where("p.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("p.fkConcepto","=","73")
                ->first();

                $itemsBoucherPrima = DB::table("item_boucher_pago", "ibp")
                ->selectRaw("Sum(ibp.pago) as suma")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->whereRaw("MONTH(ln.fechaFin) <= 6 and YEAR(ln.fechaInicio) = '".$anioFechaDocumento."'")
                ->where("ibp.fkConcepto","=","58") //58 - PRIMA SERVICIOS
                ->first();

                $pagoPrima = 0;
                if(isset($itemsBoucherPrima)){
                    if($itemsBoucherPrima->suma == 0){
                        $itemsBoucherAnticipoPrima = DB::table("item_boucher_pago", "ibp")
                        ->selectRaw("Sum(ibp.pago) as suma")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->whereRaw("MONTH(ln.fechaFin) <= 6 and YEAR(ln.fechaFin) = '".$anioFechaDocumento."'")
                        ->where("ibp.fkConcepto","=","78") //78 - ANTICIPO PRIMA
                        ->first();
                        if(isset($itemsBoucherAnticipoPrima)){
                            $pagoPrima = $itemsBoucherAnticipoPrima->suma;
                        }
                    }
                    else{
                        $pagoPrima = $itemsBoucherPrima->suma;
                    }                    
                }
                if(isset($datosProvPrima)){

                    $saldoPrimaAnt = DB::table("saldo")
                    ->where("fkEmpleado","=",$empleado->idempleado)
                    ->where("fkPeriodoActivo","=", $periodoActivoReintegro->idPeriodo)
                    ->where("mesAnterior","<=","6")
                    ->where("anioAnterior","=",$anioFechaDocumento)
                    ->where("fkConcepto","=", "73")
                    ->where("fkEstado","=","8")
                    ->first();

                    
                    
                    $saldoPrima = (isset($saldoPrimaAnt) ? $saldoPrimaAnt->valor : 0) + $datosProvPrima->suma - $pagoPrima;
                    if(abs($saldoPrima) == 1){
                        $saldoPrima = 0;
                    }
                    
                    $arrSaldo = [
                        "fkConcepto"  => "73",
                        "fkEmpleado"  => $empleado->idempleado,
                        "fkPeriodoActivo"  => $periodoActivoReintegro->idPeriodo,
                        "valor" => $saldoPrima,
                        "mesAnterior" => ($mesFechaDocumento + 1),
                        "anioAnterior" => $anioFechaDocumento,
                        "fkCierre" => $idCierre
                    ];
                    $saldoEnPeriodo = DB::table("saldo")
                    ->where("fkEmpleado","=",$empleado->idempleado)
                    ->where("fkPeriodoActivo","=", $periodoActivoReintegro->idPeriodo)
                    ->where("fkConcepto","=","73")
                    ->where("mesAnterior","=",($mesFechaDocumento + 1))
                    ->where("anioAnterior","=",$anioFechaDocumento)
                    ->first();

                    if(isset($saldoEnPeriodo)){
                        DB::table("saldo")->where("idSaldo","=",$saldoEnPeriodo->idSaldo)->update($arrSaldo);
                    }
                    else{
                        DB::table("saldo")->insert($arrSaldo);
                    }
                }
                


            }            
            if($mesFechaDocumento == 12){
                // PRIMA
                $datosProvPrima = DB::table('provision','p')
                ->selectRaw("sum(valor) as suma")
                ->where("p.anio","=",$anioFechaDocumento)
                ->where("p.mes","<=",$mesFechaDocumento)
                ->where("p.mes",">","6")
                ->where("p.fkEmpleado","=",$empleado->idempleado)
                ->where("p.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("p.fkConcepto","=","73")
                ->first();

                

                $itemsBoucherPrima = DB::table("item_boucher_pago", "ibp")
                ->selectRaw("Sum(ibp.pago) as suma")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->whereRaw("MONTH(ln.fechaFin) > 6 and YEAR(ln.fechaInicio) = '".$anioFechaDocumento."'")
                ->where("ibp.fkConcepto","=","58") //58 - PRIMA SERVICIOS
                ->first();

                $pagoPrima = 0;
                if(isset($itemsBoucherPrima)){
                    if($itemsBoucherPrima->suma == 0){
                        $itemsBoucherAnticipoPrima = DB::table("item_boucher_pago", "ibp")
                        ->selectRaw("Sum(ibp.pago) as suma")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->whereRaw("MONTH(ln.fechaFin) > 6 and YEAR(ln.fechaFin) = '".$anioFechaDocumento."'")
                        ->where("ibp.fkConcepto","=","78") //78 - ANTICIPO PRIMA
                        ->first();
                        if(isset($itemsBoucherAnticipoPrima)){
                            $pagoPrima = $itemsBoucherAnticipoPrima->suma;
                        }
                    }
                    else{
                        $pagoPrima = $itemsBoucherPrima->suma;
                    }                    
                }
                if(isset($datosProvPrima)){
                    $saldoPrimaAnt = DB::table("saldo")
                    ->where("fkEmpleado","=",$empleado->idempleado)
                    ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("mesAnterior",">","6")
                    ->where("anioAnterior","=",$anioFechaDocumento)
                    ->where("fkConcepto","=", "73")
                    ->first();

                    
                    
                    
                    $saldoPrima = (isset($saldoPrimaAnt) ? $saldoPrimaAnt->valor : 0) + $datosProvPrima->suma - $pagoPrima;
                    $saldoPrima = intval($saldoPrima);

                    if(abs($saldoPrima) == 1){
                        $saldoPrima = 0;
                    }


                    $arrSaldo = [
                        "fkConcepto"  => "73",
                        "fkEmpleado"  => $empleado->idempleado,
                        "fkPeriodoActivo"  => $periodoActivoReintegro->idPeriodo,
                        "valor" => $saldoPrima,
                        "mesAnterior" => "1",
                        "anioAnterior" => ($anioFechaDocumento+1),
                        "fkCierre" => $idCierre
                    ];
                    $saldoEnPeriodo = DB::table("saldo")
                    ->where("fkEmpleado","=",$empleado->idempleado)
                    ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("fkConcepto","=","73")
                    ->where("mesAnterior","=","1")
                    ->where("anioAnterior","=",($anioFechaDocumento+1))->first();
                    if(isset($saldoEnPeriodo)){
                        DB::table("saldo")->where("idSaldo","=",$saldoEnPeriodo->idSaldo)->update($arrSaldo);
                    }
                    else{
                        DB::table("saldo")->insert($arrSaldo);
                    }
                }
                else{
                    $saldoPrima = $pagoPrima*-1;
                    $arrSaldo = [
                        "fkConcepto"  => "73",
                        "fkEmpleado"  => $empleado->idempleado,
                        "fkPeriodoActivo"  => $periodoActivoReintegro->idPeriodo,
                        "valor" => $saldoPrima,
                        "mesAnterior" => $mesFechaDocumento,
                        "anioAnterior" => $anioFechaDocumento,
                        "fkCierre" => $idCierre
                    ];
                    $saldoEnPeriodo = DB::table("saldo")
                    ->where("fkEmpleado","=",$empleado->idempleado)
                    ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("fkConcepto","=","73")
                    ->where("mesAnterior","=",$mesFechaDocumento)
                    ->where("anioAnterior","=",$anioFechaDocumento)->first();
                    if(isset($saldoEnPeriodo)){
                        DB::table("saldo")->where("idSaldo","=",$saldoEnPeriodo->idSaldo)->update($arrSaldo);
                    }
                    else{
                        DB::table("saldo")->insert($arrSaldo);
                    }
                }
                //FIN PRIMA

                //CES
                $datosProvCes = DB::table('provision','p')
                ->selectRaw("sum(valor) as suma")
                ->where("p.anio","=",$anioFechaDocumento)
                ->where("p.fkEmpleado","=",$empleado->idempleado)
                ->where("p.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("p.fkConcepto","=","71")
                ->first();


                $itemsBoucherCes = DB::table("item_boucher_pago", "ibp")
                ->selectRaw("Sum(ibp.pago) as suma")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->whereRaw("YEAR(ln.fechaInicio) = '".$anioFechaDocumento."'")
                ->where("ibp.fkConcepto","=","66") //66 - PRIMA SERVICIOS
                ->first();

                $pagoCes = 0;
                if(isset($itemsBoucherCes)){
                    $pagoCes = $itemsBoucherCes->suma;
                }
                if(isset($datosProvCes)){
                    $saldoCesAnt = DB::table("saldo")
                    ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("fkEmpleado","=",$empleado->idempleado)
                    ->where("anioAnterior","=",$anioFechaDocumento)
                    ->where("fkConcepto","=", "71")
                    ->first();

                    
                    $saldoCes = (isset($saldoCesAnt) ? $saldoCesAnt->valor : 0) + $datosProvCes->suma - $pagoCes;
                  
                    $arrSaldo = [
                        "fkConcepto"  => "67",
                        "fkEmpleado"  => $empleado->idempleado,
                        "fkPeriodoActivo"  => $periodoActivoReintegro->idPeriodo,
                        "valor" => $saldoCes,
                        "mesAnterior" => "1",
                        "anioAnterior" => ($anioFechaDocumento + 1),
                        "fkCierre" => $idCierre
                    ];
                    $saldoEnPeriodo = DB::table("saldo")
                    ->where("fkEmpleado","=",$empleado->idempleado)
                    ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("fkConcepto","=","67")
                    ->where("mesAnterior","=","1")
                    ->where("anioAnterior","=",($anioFechaDocumento + 1))->first();
                    if(isset($saldoEnPeriodo)){
                        DB::table("saldo")->where("idSaldo","=",$saldoEnPeriodo->idSaldo)->update($arrSaldo);
                    }
                    else{
                        DB::table("saldo")->insert($arrSaldo);
                    }
                }
                else{
                    $saldoCes = $pagoCes*-1;

                    $arrSaldo = [
                        "fkConcepto"  => "67",
                        "fkEmpleado"  => $empleado->idempleado,
                        "fkPeriodoActivo"  => $periodoActivoReintegro->idPeriodo,
                        "valor" => $saldoCes,
                        "mesAnterior" => "1",
                        "anioAnterior" => ($anioFechaDocumento + 1),
                        "fkCierre" => $idCierre
                    ];
                    $saldoEnPeriodo = DB::table("saldo")
                    ->where("fkEmpleado","=",$empleado->idempleado)
                    ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("fkConcepto","=","67")
                    ->where("mesAnterior","=","1")
                    ->where("anioAnterior","=",($anioFechaDocumento + 1))->first();
                    if(isset($saldoEnPeriodo)){
                        DB::table("saldo")->where("idSaldo","=",$saldoEnPeriodo->idSaldo)->update($arrSaldo);
                    }
                    else{
                        DB::table("saldo")->insert($arrSaldo);
                    }
                }
                //FIN CES

                //INT CES
                $datosProvIntCes = DB::table('provision','p')
                ->selectRaw("sum(valor) as suma")
                ->where("p.anio","=",$anioFechaDocumento)
                ->where("p.fkEmpleado","=",$empleado->idempleado)
                ->where("p.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("p.fkConcepto","=","72")
                ->first();


                $itemsBoucherIntCes = DB::table("item_boucher_pago", "ibp")
                ->selectRaw("Sum(ibp.pago) as suma")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->whereRaw("YEAR(ln.fechaInicio) = '".$anioFechaDocumento."'")
                ->where("ibp.fkConcepto","=","69") //66 - PRIMA SERVICIOS
                ->first();

                $pagoIntCes = 0;
                if(isset($itemsBoucherIntCes)){
                    $pagoIntCes = $itemsBoucherIntCes->suma;
                }
                if(isset($datosProvIntCes)){
                    $saldoIntCesAnt = DB::table("saldo")
                    ->where("fkEmpleado","=",$empleado->idempleado)
                    ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("anioAnterior","=",$anioFechaDocumento)
                    ->where("fkConcepto","=", "72")
                    ->first();

                    $saldoIntCes =  (isset($saldoIntCesAnt) ? $saldoIntCesAnt->valor : 0) + $datosProvIntCes->suma - $pagoIntCes;

                    $arrSaldo = [
                        "fkConcepto"  => "68",
                        "fkEmpleado"  => $empleado->idempleado,
                        "fkPeriodoActivo"  => $periodoActivoReintegro->idPeriodo,
                        "valor" => $saldoIntCes,
                        "mesAnterior" => "1",
                        "anioAnterior" => ($anioFechaDocumento + 1),
                        "fkCierre" => $idCierre
                    ];
                    $saldoEnPeriodo = DB::table("saldo")
                    ->where("fkEmpleado","=",$empleado->idempleado)
                    ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("fkConcepto","=","68")
                    ->where("mesAnterior","=","1")
                    ->where("anioAnterior","=",($anioFechaDocumento + 1))->first();
                    if(isset($saldoEnPeriodo)){
                        DB::table("saldo")->where("idSaldo","=",$saldoEnPeriodo->idSaldo)->update($arrSaldo);
                    }
                    else{
                        DB::table("saldo")->insert($arrSaldo);
                    }
                }
                else{
                    $saldoIntCes = $pagoIntCes*-1;

                    $arrSaldo = [
                        "fkConcepto"  => "68",
                        "fkEmpleado"  => $empleado->idempleado,
                        "fkPeriodoActivo"  => $periodoActivoReintegro->idPeriodo,
                        "valor" => $saldoIntCes,
                        "mesAnterior" => "1",
                        "anioAnterior" => ($anioFechaDocumento + 1),
                        "fkCierre" => $idCierre
                    ];
                    $saldoEnPeriodo = DB::table("saldo")
                    ->where("fkEmpleado","=",$empleado->idempleado)
                    ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("fkConcepto","=","68")
                    ->where("mesAnterior","=","1")
                    ->where("anioAnterior","=",($anioFechaDocumento + 1))->first();
                    if(isset($saldoEnPeriodo)){
                        DB::table("saldo")->where("idSaldo","=",$saldoEnPeriodo->idSaldo)->update($arrSaldo);
                    }
                    else{
                        DB::table("saldo")->insert($arrSaldo);
                    }
                }
                //FIN INT CES
                
                //VAC
                $datosProvVac = DB::table('provision','p')
                ->selectRaw("sum(valor) as suma")
                ->where("p.anio","=",$anioFechaDocumento)
                ->where("p.fkEmpleado","=",$empleado->idempleado)
                ->where("p.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("p.fkConcepto","=","74")
                ->first();

                $saldoVacaciones = DB::table("saldo")
                ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("anioAnterior","=",$anioFechaDocumento)
                ->where("fkConcepto","=", "74")
                ->where("fkEstado","=","7")
                ->first();


                $datosProvVac = DB::table('provision','p')
                ->selectRaw("sum(valor) as suma")
                ->where("p.anio","=",$anioFechaDocumento)
                ->where("p.fkEmpleado","=",$empleado->idempleado)
                ->where("p.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("p.fkConcepto","=","74")
                ->first();


                $itemsBoucherVac = DB::table("item_boucher_pago", "ibp")
                ->selectRaw("Sum(ibp.pago) as suma")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->whereRaw("YEAR(ln.fechaInicio) = '".$anioFechaDocumento."'")
                ->whereIn("ibp.fkConcepto",[28,29,30]) //vac
                ->whereNotIn("ln.fkTipoliquidacion",[8])
                ->first();
                

                //dd($empleado,$periodoActivoReintegro->idPeriodo, $itemsBoucherVac, $anioFechaDocumento);

                $pagoVac = 0;
                if(isset($itemsBoucherVac)){
                    $pagoVac = $itemsBoucherVac->suma;
                }

                
               
                if(isset($datosProvVac)){
                    $saldoVac = (isset($saldoVacaciones) ? $saldoVacaciones->valor : 0) + $datosProvVac->suma - $pagoVac;

                    $arrSaldo = [
                        "fkConcepto"  => "74",
                        "fkEmpleado"  => $empleado->idempleado,
                        "fkPeriodoActivo"  => $periodoActivoReintegro->idPeriodo,
                        "valor" => $saldoVac,
                        "mesAnterior" => "1",
                        "anioAnterior" => ($anioFechaDocumento + 1),
                        "fkCierre" => $idCierre
                    ];
                    $saldoEnPeriodo = DB::table("saldo")
                    ->where("fkEmpleado","=",$empleado->idempleado)
                    ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("fkConcepto","=","74")
                    ->where("mesAnterior","=","1")
                    ->where("anioAnterior","=",($anioFechaDocumento + 1))->first();
                    if(isset($saldoEnPeriodo)){
                        DB::table("saldo")->where("idSaldo","=",$saldoEnPeriodo->idSaldo)->update($arrSaldo);
                    }
                    else{
                        DB::table("saldo")->insert($arrSaldo);
                    }
                }
                else{
                    $saldoVac = (isset($saldoVacaciones) ? $saldoVacaciones->valor : 0) - $pagoVac;

                    $arrSaldo = [
                        "fkConcepto"  => "74",
                        "fkEmpleado"  => $empleado->idempleado,
                        "fkPeriodoActivo"  => $periodoActivoReintegro->idPeriodo,
                        "valor" => $saldoVac,
                        "mesAnterior" => "1",
                        "anioAnterior" => ($anioFechaDocumento + 1),
                        "fkCierre" => $idCierre
                    ];
                    $saldoEnPeriodo = DB::table("saldo")
                    ->where("fkEmpleado","=",$empleado->idempleado)
                    ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("fkConcepto","=","74")
                    ->where("mesAnterior","=","1")
                    ->where("anioAnterior","=",($anioFechaDocumento + 1))->first();
                    if(isset($saldoEnPeriodo)){
                        DB::table("saldo")->where("idSaldo","=",$saldoEnPeriodo->idSaldo)->update($arrSaldo);
                    }
                    else{
                        DB::table("saldo")->insert($arrSaldo);
                    }
                }
                //FIN VAC

            }
        }

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", generó el cierre para la empresa:".$req->empresa);

        return redirect('/nomina/cierre/');

        
    }
    public function indexCierre(){
        $dataUsu = UsuarioController::dataAdminLogueado();
        $empresas = DB::table("empresa", "e");
        if(isset($dataUsu) && $dataUsu->fkRol == 2){            
            $empresas = $empresas->whereIn("idempresa", $dataUsu->empresaUsuario);
        }
        $empresas = $empresas->orderBy("razonSocial")->get();
        
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu 'Generar cierre'");

        return view('/nomina.cierre',[
            "empresas" => $empresas,
            "dataUsu" => $dataUsu
        ]);
    }

    public function comoCalculo($idItemBoucherPago){

        $itemBoucherPago = DB::table("item_boucher_pago","ibp")
            ->select(["ibp.*", "bp.fkEmpleado"])
            ->join("boucherpago as bp", "bp.idBoucherPago", "=", "ibp.fkBoucherPago")
            ->where("ibp.idItemBoucherPago","=",$idItemBoucherPago)->first();

        $concepto = DB::table("concepto","c")
            ->join("item_boucher_pago AS ibp","ibp.fkConcepto", "=", "c.idconcepto")
            ->where("ibp.idItemBoucherPago","=",$idItemBoucherPago)->first();


        return view('nomina.solicitudes.verComoCalculoConceptosGen', [
            'itemBoucherPago' => $itemBoucherPago,  
            'concepto' => $concepto,  
        ]);
            
        if($concepto->subTipo == "Formula"){
            $formulasConceptos = DB::table("formulaconcepto")
            ->where("fkConcepto","=",$concepto->idconcepto)
            ->orderBy("idformulaConcepto")
            ->get();

            $arrFormulas = array();
            $valorf = 0;
            $idEmpleado = $itemBoucherPago->fkEmpleado;
            $contador = 0;
            foreach($formulasConceptos as $formulaConcepto){
                //VALOR 1
                
                if(isset($formulaConcepto->fkFormulaConcepto)){
                    $valor1=$valorf;
                    $arrFormulas[$contador]["valor1"] = $valor1;
                    $arrFormulas[$contador]["valor1_tipo"] = "formulaConcepto";
                }
                else if(isset($formulaConcepto->fkConceptoInicial)){
                    $conceptoCalculo = DB::table("conceptofijo")->select(DB::raw('SUM(conceptofijo.valor) as totalValor'))
                        ->where("conceptofijo.fkEmpleado","=", $idEmpleado)
                        ->where("conceptofijo.fkConcepto","=", $formulaConcepto->fkConceptoInicial)
                        ->where("conceptofijo.fkEstado","=","1")
                        ->first();
                    $valor1=floatval($conceptoCalculo->totalValor);

                    
                    $conceptoValor1 = DB::table("concepto","c")
                    ->where("c.idconcepto","=",$formulaConcepto->fkConceptoInicial)->first();


                    $arrFormulas[$contador]["valor1"] = $valor1;
                    $arrFormulas[$contador]["valor1_tipo"] = "Concepto";
                    $arrFormulas[$contador]["valor1_nombre"] = $conceptoValor1->nombre;
                }
                else if(isset($formulaConcepto->fkGrupoConceptoInicial)){
    
                    $grupoConceptoCalculo = DB::table("conceptofijo")->select(DB::raw('SUM(conceptofijo.valor) as totalValor'))
                        ->join("grupoconcepto_concepto as gcc", "gcc.fkConcepto","=","conceptofijo.fkConcepto")
                        ->where("conceptofijo.fkEmpleado","=", $idEmpleado)
                        ->where("conceptofijo.fkEstado","=","1")
                        ->where("gcc.fkGrupoConcepto", "=", $formulaConcepto->fkGrupoConceptoInicial)                       
                        ->first();
    
                    $valor1=floatval($grupoConceptoCalculo->totalValor);

                    $grupoValor1 = DB::table("grupoconcepto","gc")
                    ->where("gc.idgrupoConcepto","=",$formulaConcepto->fkGrupoConceptoInicial)->first();

                    $arrFormulas[$contador]["valor1"] = $valor1;
                    $arrFormulas[$contador]["valor1_tipo"] = "Grupo Concepto";
                    $arrFormulas[$contador]["valor1_nombre"] = $grupoValor1->nombre;
                    $arrFormulas[$contador]["valor1_idgrupoConcepto"] = $formulaConcepto->fkGrupoConceptoInicial;
                }
                else if(isset($formulaConcepto->fkVariableInicial)){
                    $variableCalculo = DB::table('variable')->where("idVariable","=",$formulaConcepto->fkVariableFinal)->first();
                    $valor1 = floatval($variableCalculo->valor);

                    $arrFormulas[$contador]["valor1"] = $valor1;
                    $arrFormulas[$contador]["valor1_tipo"] = "Variable";
                    $arrFormulas[$contador]["valor1_nombre"] = $variableCalculo->nombre;

                }
                else if(isset($formulaConcepto->valorInicial)){
                    $valor1 = floatval($formulaConcepto->valorInicial);
                    $arrFormulas[$contador]["valor1"] = $valor1;
                    $arrFormulas[$contador]["valor1_tipo"] = "Valor";
                    $arrFormulas[$contador]["valor1_nombre"] = "Valor Fijo";
                }
                //VALOR 2
                if(isset($formulaConcepto->fkConceptoFinal)){
                    $conceptoCalculo = DB::table("conceptofijo")->select(DB::raw('SUM(conceptofijo.valor) as totalValor'))
                        ->where("conceptofijo.fkEmpleado","=", $idEmpleado)
                        ->where("conceptofijo.fkConcepto","=", $formulaConcepto->fkConceptoInicial)
                        ->where("conceptofijo.fkEstado","=","1")
                        ->first();
                    $valor2=floatval($conceptoCalculo->totalValor);

                    $conceptoValor2 = DB::table("concepto","c")
                    ->where("c.idconcepto","=",$formulaConcepto->fkConceptoInicial)->first();

                    $arrFormulas[$contador]["valor2"] = $valor2;
                    $arrFormulas[$contador]["valor2_tipo"] = "Concepto";
                    $arrFormulas[$contador]["valor2_nombre"] = $conceptoValor2->nombre;
                    

                }
                else if(isset($formulaConcepto->fkGrupoConceptoFinal)){
                    $grupoConceptoCalculo = DB::table("conceptofijo")->select(DB::raw('SUM(conceptofijo.valor) as totalValor'))
                        ->join("grupoconcepto_concepto as gcc", "gcc.fkConcepto","=","conceptofijo.fkConcepto")
                        ->where("conceptofijo.fkEmpleado","=", $idEmpleado)
                        ->where("conceptofijo.fkEstado","=","1")
                        ->where("gcc.fkGrupoConcepto", "=", $formulaConcepto->fkGrupoConceptoFinal)                       
                        ->first();


                    $grupoValor2 = DB::table("grupoconcepto","gc")
                        ->where("gc.idgrupoConcepto","=",$formulaConcepto->fkGrupoConceptoFinal)->first();

                    $valor2=floatval($grupoConceptoCalculo->totalValor);

                    $arrFormulas[$contador]["valor2"] = $valor2;
                    $arrFormulas[$contador]["valor2_tipo"] = "Grupo Concepto";
                    $arrFormulas[$contador]["valor2_nombre"] = $grupoValor2->nombre;
                    $arrFormulas[$contador]["valor2_idgrupoConcepto"] = $formulaConcepto->fkGrupoConceptoFinal;


                }
                else if(isset($formulaConcepto->fkVariableFinal)){
                    $variableFinal = DB::table('variable')->where("idVariable","=",$formulaConcepto->fkVariableFinal)->first();
                    $valor2 = floatval($variableFinal->valor);

                    $arrFormulas[$contador]["valor2"] = $valor2;
                    $arrFormulas[$contador]["valor2_tipo"] = "Variable";
                    $arrFormulas[$contador]["valor2_nombre"] = $variableFinal->nombre;

                }
                else if(isset($formulaConcepto->valorFinal)){
                    $valor2 = floatval($formulaConcepto->valorFinal);

                    $arrFormulas[$contador]["valor2"] = $valor2;
                    $arrFormulas[$contador]["valor2_tipo"] = "Valor";
                    $arrFormulas[$contador]["valor2_nombre"] = "Valor Fijo";
                }
    
                //VALOR F 
                
                if($formulaConcepto->fkTipoOperacion=="1"){//Suma
                    $valorf = $valor1 + $valor2;
                    $arrFormulas[$contador]["operacion"]="Mas:";
                }
                else if($formulaConcepto->fkTipoOperacion=="2"){//Resta
                    $valorf = $valor1 - $valor2;
                    $arrFormulas[$contador]["operacion"]="Menos:";
                }
                else if($formulaConcepto->fkTipoOperacion=="3"){//Multiplicacion
                    $valorf = $valor1 * $valor2;
                    $arrFormulas[$contador]["operacion"]="Multiplicado por:";
                }
                else if($formulaConcepto->fkTipoOperacion=="4"){//Division
                    if($valor2 != 0){
                        $valorf = $valor1 / $valor2;
                        $arrFormulas[$contador]["operacion"]="Dividido por:";
                    }                                
                }   
                $contador++; 
            }




            return view('nomina.solicitudes.verComoCalculoFormula', [
                'itemBoucherPago' => $itemBoucherPago,  
                'concepto' => $concepto,  
                'arrFormulas' => $arrFormulas
                
                ]);

        }else if($concepto->subTipo == "Tabla"){

            $variable = DB::table("variable")
            ->where("idVariable","=",$concepto->fkVariable)
            ->first();

            return view('nomina.solicitudes.verComoCalculoVariable', [
                'itemBoucherPago' => $itemBoucherPago,  
                'variable' => $variable,
                'concepto' => $concepto,  
                ]);
        }
        else if($concepto->subTipo == "Valor"){
            $conceptosFijos = DB::table("conceptofijo")
            ->where("fkConcepto","=",$concepto->idconcepto)
            ->where("fkEstado","=","1")
            ->where("fkEmpleado","=",$itemBoucherPago->fkEmpleado)
            ->first();


            return view('nomina.solicitudes.verComoCalculoConceptos', [
                'itemBoucherPago' => $itemBoucherPago,  
                'conceptosFijos' => $conceptosFijos,
                'concepto' => $concepto,  
                ]);

        }
        


    }
    public function verDetalleRetencion($idBoucherPago, $tipoRetencion){
        
        $retencion = DB::table('retencionfuente')
        ->where("fkBoucherPago","=",$idBoucherPago)
        ->where("tipoRetencion","=",$tipoRetencion)
        ->first();

        if($tipoRetencion == "NORMAL"){
            $itemBoucherPago = DB::table('item_boucher_pago')
            ->where("fkBoucherPago","=",$idBoucherPago)
            ->where("fkConcepto","=","36")->first();
        }
        else if($tipoRetencion == "INDEMNIZACION"){
            $itemBoucherPago = DB::table('item_boucher_pago')
            ->where("fkBoucherPago","=",$idBoucherPago)
            ->where("fkConcepto","=","76")->first();
        }
        else if($tipoRetencion == "PRIMA"){
            $itemBoucherPago = DB::table('item_boucher_pago')
            ->where("fkBoucherPago","=",$idBoucherPago)
            ->where("fkConcepto","=","77")->first();
        }
        //dd($idBoucherPago, $tipoRetencion);

        return view('nomina.solicitudes.verDetalleRetencion', [
            'retencion' => $retencion,
            'itemBoucherPago' => $itemBoucherPago
        ]);

    }

    public function recalcularBoucher($idBoucherPago, $numDias = null, $numHoras = null){
        Artisan::call('view:clear');
        $boucherPago = DB::table('boucherpago')
            ->where("idBoucherPago","=",$idBoucherPago)
            ->first();
        if(isset($numDias) && isset($numHoras)){
            $respuesta = $this->calcularLiquidacionEmpleado($boucherPago->fkEmpleado, $boucherPago->fkLiquidacion, $boucherPago->idBoucherPago, $numHoras, $numDias);
        }
        else{
            if(isset($boucherPago->horasPeriodo)){
                $horas= $boucherPago->horasPeriodo/$boucherPago->periodoPago;
                $respuesta = $this->calcularLiquidacionEmpleado($boucherPago->fkEmpleado, $boucherPago->fkLiquidacion, $boucherPago->idBoucherPago, $horas, $boucherPago->periodoPago);
            }
            else{
                $respuesta = $this->calcularLiquidacionEmpleado($boucherPago->fkEmpleado, $boucherPago->fkLiquidacion, $boucherPago->idBoucherPago);
            }
        }
        if($respuesta===true){
            $boucherPago = DB::table('boucherpago')
            ->where("idBoucherPago","=",$idBoucherPago)
            ->first();

            $totalNomina = DB::table('boucherpago')
            ->selectRaw("sum(netoPagar) as suma")
            ->where("fkLiquidacion","=",$boucherPago->fkLiquidacion)
            ->first();

            $dataUsu = UsuarioController::dataAdminLogueado();
            Log::channel('gesath')->info("El usuario ".$dataUsu->email.", recalculó el comprobante de pago con id:".$idBoucherPago);

            return response()->json([
                "success" => true,
                "netoPagar" => number_format($boucherPago->netoPagar,0, ",", "."),
                "totalNomina" => number_format($totalNomina->suma,0, ",", ".")
            ]);
        }
        else{
            return response()->json([
                "success" => false,
                "error" => $respuesta
            ]);
        }
    }

    public function calcularLiquidacionEmpleado($idEmpleado, $idLiquidacionNomina, $idBoucherPago = null, $numeroHoras = null, $numeroDias = null){

        try{        

            $variables = DB::table("variable")->where("idVariable","=","1")->first();
            $salarioMinimoDia = $variables->valor / 30;

            $empleado = DB::table('empleado')->where("idempleado","=", $idEmpleado)->first();
            
            $liquidacionNomina = DB::table('liquidacionnomina')->where("idLiquidacionNomina", "=", $idLiquidacionNomina)->first();
            
            $periodoActivoReintegro = DB::table("periodo")
            ->join("nomina as n","n.idNomina", "=","periodo.fkNomina")
            ->where("fkEstado","=","1")
            ->where("fkEmpleado", "=", $idEmpleado)
            ->where("fkNomina", "=", $liquidacionNomina->fkNomina)
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
            if($empleado->tipoRegimen == "Salario Integral"){
                $salarioMinimoDia = (($variables->valor*13) / 30)*0.7;
            }
            


            $nomina = DB::table("nomina")->where("idNomina", "=", $liquidacionNomina->fkNomina)->first();
            $periodo = $nomina->periodo;
            
            $empresa = DB::table('empresa', "em")
            ->join("nomina as n","n.fkEmpresa", "=", "em.idempresa")        
            ->join("liquidacionnomina as ln","ln.fkNomina", "=", "n.idNomina")        
            ->where("ln.idLiquidacionNomina","=", $idLiquidacionNomina)->first();

            $tipoliquidacion = $liquidacionNomina->fkTipoLiquidacion;
            $fechaInicio = $liquidacionNomina->fechaInicio;
            $fechaFin = $liquidacionNomina->fechaFin;

            if(strtotime($fechaInicio) > strtotime($empleado->fechaIngreso)){
                //Verificar si hay liquidaciones anteriores

                if(!isset($periodoActivoReintegro->idPeriodo)){
                    return;
                }
                $liquidacionAnteriores = DB::table("liquidacionnomina", "ln")
                ->selectRaw("min(ln.fechaInicio) as primeraFecha")
                ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")     
                ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])              
                ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->first();
                if(!isset($liquidacionAnteriores->primeraFecha)){
                    $fechaInicio = $empleado->fechaIngreso;
                }
            }

            

            $diasNoTrabajados = 0;
            $diasNoTrabajadosInjustificados = 0;
            $novedades = DB::table("novedad","n")
                ->join("conceptosxtipoliquidacion AS ctl", "ctl.fkConcepto","=","n.fkConcepto")
                ->join("concepto AS c", "ctl.fkConcepto","=","c.idconcepto")
                ->where("ctl.fkTipoLiquidacion","=",$tipoliquidacion)
                ->where("n.fkEmpleado", "=", $empleado->idempleado)
                ->where("n.fkPeriodoActivo","=", $periodoActivoReintegro->idPeriodo)
                ->where("n.fkEstado","=","7")
                ->whereBetween("n.fechaRegistro",[$fechaInicio, $fechaFin])
                ->distinct()
                ->get();

            $arrValorxConcepto = array();
            $arrValorxConceptoFueraNomina = array();
            $arrValorxConceptoOtros = array();
            $arrComoCalcula = array();
            //Agregar valor de las novedades a la liquidacion actual 

            foreach($novedades as $novedadyconcepto){
                $arrComoCalcula[$novedadyconcepto->idconcepto] = ($arrComoCalcula[$novedadyconcepto->idconcepto] ?? array());
                if(isset($novedadyconcepto->fkAusencia)){
                    $ausencia = DB::table("ausencia")
                        ->where("idAusencia", "=", $novedadyconcepto->fkAusencia)
                        ->first();  
                   
                    $valorFormula = $this->calcularValoresxConceptoxEmpleado($novedadyconcepto->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo);
                    $valorFormulaSS = $this->calcularValoresxConceptoSSxEmpleado($novedadyconcepto->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo);
                                        
                    array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Nueva novedad de ausencia");
                    $arrComoCalcula[$novedadyconcepto->idconcepto] = $this->comoCalculaValoresxConceptoxEmpleado($novedadyconcepto->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo, $arrComoCalcula[$novedadyconcepto->idconcepto]);

                    if(isset($arrValorxConcepto[$novedadyconcepto->idconcepto])){
                    
                        $cantidadHorasEnDias = ($ausencia->cantidadHoras/24);
                        $cantidadInt = $arrValorxConcepto[$novedadyconcepto->idconcepto]["cantidad"] + floatval($cantidadHorasEnDias) + floatval($ausencia->cantidadDias);
                        
                        $valorUnit = ($valorFormula*(floatval($cantidadHorasEnDias) + floatval($ausencia->cantidadDias)));

                        $valorUnitSS = ($valorFormulaSS*(floatval($cantidadHorasEnDias) + floatval($ausencia->cantidadDias)));

                        array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Se toma valor ".number_format($valorFormula,0,",", ".")." y se multiplica por ".(floatval($cantidadHorasEnDias) + floatval($ausencia->cantidadDias))." dias");

                        $valorInt = $arrValorxConcepto[$novedadyconcepto->idconcepto]["valor"] + $valorUnit;
                        
                        array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Se suma con anterior(es) ausencias ".$arrValorxConcepto[$novedadyconcepto->idconcepto]["valor"]." + ". $valorUnit);
                        
                        
                        $arrNovedades = $arrValorxConcepto[$novedadyconcepto->idconcepto]["arrNovedades"];
                        array_push($arrNovedades, 
                            [
                                "idNovedad" => $novedadyconcepto->idNovedad,
                                "valor" => $valorUnit,
                                "valor_ss" => $valorUnitSS
                            ]
                        );
                        $arrValorxConcepto[$novedadyconcepto->idconcepto] = array(
                            "naturaleza" => $novedadyconcepto->fkNaturaleza,
                            "unidad"=>"DÍA",
                            "cantidad"=> $cantidadInt,
                            "arrNovedades"=> $arrNovedades,
                            "valor" => 0,
                            "valorAus" => $arrValorxConcepto[$novedadyconcepto->idconcepto]["valorAus"] + $valorInt,
                            "tipoGen" => "novedadAus"
                        );
                        
                        
                        $diasNoTrabajadosInjustificados = $diasNoTrabajadosInjustificados + $ausencia->cantidadDias;
                    }
                    else{
                       
                        $cantidadHorasEnDias = ($ausencia->cantidadHoras/24);
                        $valorInt = $valorFormula*(floatval($cantidadHorasEnDias) + floatval($ausencia->cantidadDias));
                        $valorIntSS = $valorFormulaSS*(floatval($cantidadHorasEnDias) + floatval($ausencia->cantidadDias));
                        
                        array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Se toma valor ".number_format($valorFormula,0,",", ".")." y se multiplica por ".(floatval($cantidadHorasEnDias) + floatval($ausencia->cantidadDias))." dias");

                        $arrNovedades = array(
                            [
                                "idNovedad" => $novedadyconcepto->idNovedad,
                                "valor" => $valorInt,
                                "valor_ss" => $valorIntSS
                            ]
                        );
                        $cantidadInt = floatval($cantidadHorasEnDias) + floatval($ausencia->cantidadDias);
                        $arrValorxConcepto[$novedadyconcepto->idconcepto] = array(
                            "naturaleza" => $novedadyconcepto->fkNaturaleza,
                            "unidad"=>"DÍA",
                            "cantidad"=> $cantidadInt,
                            "arrNovedades"=> $arrNovedades,
                            "valor" => 0,
                            "valorAus" => $valorInt,
                            "tipoGen" => "novedadAus"
                        );
                        $diasNoTrabajadosInjustificados = $diasNoTrabajadosInjustificados + $cantidadInt;
                        
                    }                    
                }
                else if(isset($novedadyconcepto->fkHorasExtra)){
                    $horas_extra = DB::table("horas_extra")
                        ->where("idHoraExtra", "=", $novedadyconcepto->fkHorasExtra)
                        ->first();
                        
                    array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Nueva novedad de horas extras");
                    if($novedadyconcepto->subTipo == "Formula" || $novedadyconcepto->subTipo == "Porcentaje"){
                        $valorFormula = $this->calcularValoresxConceptoxEmpleado($novedadyconcepto->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo);
                        $arrComoCalcula[$novedadyconcepto->idconcepto] = $this->comoCalculaValoresxConceptoxEmpleado($novedadyconcepto->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo, $arrComoCalcula[$novedadyconcepto->idconcepto]);
                        if(isset($arrValorxConcepto[$novedadyconcepto->idconcepto])){

                            $valorUnit = ($valorFormula * floatval($horas_extra->cantidadHoras));
                            
                            array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Se multiplica ".number_format($valorFormula,0,",", ".")." por ".$horas_extra->cantidadHoras);
                            array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Se suma el valor(es) con anterior(es) ".$arrValorxConcepto[$novedadyconcepto->idconcepto]["valor"]." + ". $valorUnit);
                            array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Se suma la cantidad(es) con anterior(es) ".$arrValorxConcepto[$novedadyconcepto->idconcepto]["cantidad"]." + ". $horas_extra->cantidadHoras);

                            $valorInt = $arrValorxConcepto[$novedadyconcepto->idconcepto]["valor"] + $valorUnit;
                            $cantidadInt = $arrValorxConcepto[$novedadyconcepto->idconcepto]["cantidad"] + floatval($horas_extra->cantidadHoras);
                            $arrNovedades=$arrValorxConcepto[$novedadyconcepto->idconcepto]["arrNovedades"];
                            

                            array_push($arrNovedades, [
                                "idNovedad" => $novedadyconcepto->idNovedad,
                                "valor" => $valorUnit
                            ]);
                            $arrValorxConcepto[$novedadyconcepto->idconcepto] = array(
                                "naturaleza" => $novedadyconcepto->fkNaturaleza,
                                "unidad"=>"HORAS",
                                "cantidad"=> $cantidadInt,
                                "arrNovedades"=> $arrNovedades,
                                "valor" => $valorInt,
                                "tipoGen" => "novedad"
                            );
                        }
                        else{
                            $valorInt = $valorFormula * floatval($horas_extra->cantidadHoras);
                            array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Se multiplica ".number_format($valorFormula,0,",", ".")." por ".$horas_extra->cantidadHoras);
                            $arrNovedades = array([
                                "idNovedad" => $novedadyconcepto->idNovedad,
                                "valor" => $valorInt
                            ]);
                            $cantidadInt = $horas_extra->cantidadHoras;
                            $arrValorxConcepto[$novedadyconcepto->idconcepto] = array(
                                "naturaleza" => $novedadyconcepto->fkNaturaleza,
                                "unidad"=>"HORAS",
                                "cantidad"=> $cantidadInt,
                                "arrNovedades"=> $arrNovedades,
                                "valor" => $valorInt,
                                "tipoGen" => "novedad"
                            );
                        }
                    }
                }
                else if(isset($novedadyconcepto->fkIncapacidad)){
                    $incapacidadPTotal = DB::table("incapacidad")
                        ->where("idIncapacidad", "=", $novedadyconcepto->fkIncapacidad)
                        ->where("pagoTotal", "=", "1")
                        ->first();
                    if(isset($incapacidadPTotal->numDias)){
                        if($novedadyconcepto->subTipo == "Formula"){      

                            $valorFormula = $this->calcularValoresxConceptoxEmpleado($novedadyconcepto->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo);
                            $valorFormulaSS = $this->calcularValoresxConceptoSSxEmpleado($novedadyconcepto->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo);
                            
                            array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Nueva novedad de incapacidad");
                            
    
                            $arrComoCalcula[$novedadyconcepto->idconcepto] = $this->comoCalculaValoresxConceptoxEmpleado($novedadyconcepto->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo, $arrComoCalcula[$novedadyconcepto->idconcepto]);
                            
                            if($valorFormula < $salarioMinimoDia && $novedadyconcepto->idconcepto != 135){
                                array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Valor calculado ($".number_format($valorFormula,0,",", ".").") es menor a el salario mínimo por día (".number_format($salarioMinimoDia,0,",", ".").") se aplica valor salario mínimo por día");
                                $valorFormula = $salarioMinimoDia;
                            }
                            if($valorFormulaSS != 0 &&  $valorFormulaSS < $salarioMinimoDia){
                                $valorFormulaSS = $salarioMinimoDia;
                            }
                            
                            if(isset($arrValorxConcepto[$novedadyconcepto->idconcepto])){
                                $valorUnit = ($valorFormula * floatval($incapacidadPTotal->numDias));
                                $valorUnitSS = ($valorFormulaSS * floatval($incapacidadPTotal->numDias));

                                array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Se multiplica ".number_format($valorFormula,0,",", ".")." por ".$incapacidadPTotal->numDias." días");
                                array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Se suma el valor(es) con anterior(es) ".number_format($arrValorxConcepto[$novedadyconcepto->idconcepto]["valor"],0,",", ".")." + ".number_format($valorUnit,0,",", "."));
                                array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Se suma la cantidad(es) con anterior(es) ".$arrValorxConcepto[$novedadyconcepto->idconcepto]["cantidad"]." + ". $incapacidadPTotal->numDias);                           
                                
                                $valorInt = $arrValorxConcepto[$novedadyconcepto->idconcepto]["valor"] + $valorUnit;
                                $cantidadInt = $arrValorxConcepto[$novedadyconcepto->idconcepto]["cantidad"] + floatval($incapacidadPTotal->numDias);
                                $arrNovedades=$arrValorxConcepto[$novedadyconcepto->idconcepto]["arrNovedades"];
                                array_push($arrNovedades, [
                                    "idNovedad" => $novedadyconcepto->idNovedad,
                                    "valor" => $valorUnit,
                                    "valor_ss" => $valorUnitSS
                                ]);
                                $arrValorxConcepto[$novedadyconcepto->idconcepto] = array(
                                    "naturaleza" => $novedadyconcepto->fkNaturaleza,
                                    "unidad"=>"DÍA",
                                    "cantidad"=> $cantidadInt,
                                    "arrNovedades"=> $arrNovedades,
                                    "valor" => $valorInt,
                                    "tipoGen" => "novedad"
                                );
                            }
                            else{
                                $valorInt = $valorFormula * floatval($incapacidadPTotal->numDias);
                                $valorIntSS = $valorFormulaSS * floatval($incapacidadPTotal->numDias);
                                array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Se multiplica ".number_format($valorFormula,0,",", ".")." por ".$incapacidadPTotal->numDias." días");
                                $arrNovedades = array([
                                    "idNovedad" => $novedadyconcepto->idNovedad,
                                    "valor" => $valorInt,
                                    "valor_ss" => $valorIntSS
                                ]);
                                $cantidadInt = $incapacidadPTotal->numDias;
                
                                $arrValorxConcepto[$novedadyconcepto->idconcepto] = array(
                                    "naturaleza" => $novedadyconcepto->fkNaturaleza,
                                    "unidad"=>"DÍA",
                                    "cantidad"=> $cantidadInt,
                                    "arrNovedades"=> $arrNovedades,
                                    "valor" => $valorInt,
                                    "tipoGen" => "novedad"
                                );
                            }
                        }
                    }
                }            
                else if(isset($novedadyconcepto->fkOtros)){
                
                    $otrasNovedades = DB::table("otra_novedad")
                        ->where("idOtraNovedad", "=", $novedadyconcepto->fkOtros)
                        ->first();
                        array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Nueva novedad de valor(Otros)");

                        if(isset($arrValorxConceptoOtros[$novedadyconcepto->idconcepto])){
                            $valorUnit =  (floatval($otrasNovedades->valor) * intval($otrasNovedades->sumaResta));
                            try{
                                array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Se multiplica ".number_format($otrasNovedades->valor,0,",", ".")." por ".$otrasNovedades->sumaResta."");
                                array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Se suma el valor(es) con anterior(es) ".number_format($arrComoCalcula[$novedadyconcepto->idconcepto]["valor"],0,",", ".")." + ".number_format($valorUnit,0,",", "."));
                            }
                            catch(Exception $e){
                                
                            }
                            
                            $valorInt = $arrValorxConceptoOtros[$novedadyconcepto->idconcepto]["valor"] + $valorUnit;
                            
                            $cantidadInt = 0;
                            $arrNovedades=$arrValorxConceptoOtros[$novedadyconcepto->idconcepto]["arrNovedades"];
                            array_push($arrNovedades, [
                                "idNovedad" => $novedadyconcepto->idNovedad,
                                "valor" => $valorUnit
                            ]);
                            $arrValorxConceptoOtros[$novedadyconcepto->idconcepto] = array(
                                "naturaleza" => $novedadyconcepto->fkNaturaleza,
                                "unidad"=>"VALOR",
                                "cantidad"=> $cantidadInt,
                                "arrNovedades"=> $arrNovedades,
                                "valor" => $valorInt,
                                "tipoGen" => "novedad"
                            );




                        }
                        else{
                            $valorInt = (floatval($otrasNovedades->valor) * intval($otrasNovedades->sumaResta));
                            array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Se multiplica ".number_format($otrasNovedades->valor,0,",", ".")." por ".$otrasNovedades->sumaResta."");
                            
                            $arrNovedades = array([
                                "idNovedad" => $novedadyconcepto->idNovedad,
                                "valor" => $valorInt
                            ]);
                            $cantidadInt = 0;

                            $arrValorxConceptoOtros[$novedadyconcepto->idconcepto] = array(
                                "naturaleza" => $novedadyconcepto->fkNaturaleza,
                                "unidad"=>"VALOR",
                                "cantidad"=> $cantidadInt,
                                "arrNovedades"=> $arrNovedades,
                                "valor" => $valorInt,
                                "tipoGen" => "novedad"
                            );

                            
                        }
                    
                    
                }
                
            }
            
            //INICIO LICENCIAS PARCIAL
            $sqlWhere = "( 
                ('".$fechaInicio."' BETWEEN l.fechaInicial AND l.fechaFinal) OR
                ('".$fechaFin."' BETWEEN l.fechaInicial AND l.fechaFinal) OR
                (l.fechaInicial BETWEEN '".$fechaInicio."' AND '".$fechaFin."') OR
                (l.fechaFinal BETWEEN '".$fechaInicio."' AND '".$fechaFin."')
            )";

            $licenciasPParcial = DB::table("licencia", "l")
            ->select(["l.*","c.*","n.*"])
            ->join("novedad as n", "n.fkLicencia","=","l.idLicencia")
            ->join("conceptosxtipoliquidacion AS ctl", "ctl.fkConcepto","=","n.fkConcepto")
            ->join("concepto AS c", "ctl.fkConcepto","=","c.idconcepto")
            ->where("n.fkEmpleado", "=", $empleado->idempleado)
            ->where("n.fkPeriodoActivo","=", $periodoActivoReintegro->idPeriodo)
            ->where("ctl.fkTipoLiquidacion","=",$tipoliquidacion)
            ->where("n.fkEstado","=","7")
            ->whereRaw($sqlWhere)
            ->distinct()
            ->get();
                
            //Agregar valor de las novedades de licencia ya que todas ellas tienen el pago parcial (lo de cada cosa en cada mes)
            foreach($licenciasPParcial as $licenciaPParcial){
                
                if(strtotime($licenciaPParcial->fechaInicial)>=strtotime($fechaInicio)
                    &&  strtotime($licenciaPParcial->fechaInicial)<=strtotime($fechaFin) 
                    &&  strtotime($licenciaPParcial->fechaFinal)>=strtotime($fechaFin))
                {
                    $diaI = strtotime($licenciaPParcial->fechaInicial);
                    $diaF = strtotime($fechaFin);
                    $diff = $diaF - $diaI;
                    $dias = $diff / ( 60 * 60 * 24);
                    $dias++; //Como se toma desde dia.00:00:00 es para que tome el dia completo
                    $diasNoTrabajados = $diasNoTrabajados + $dias;

                    if($licenciaPParcial->subTipo == "Formula"){             
                        $arrComoCalcula[$licenciaPParcial->idconcepto] = ($arrComoCalcula[$licenciaPParcial->idconcepto] ?? array());
                        array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Nueva novedad de licencia");
                        $valorFormula = $this->calcularValoresxConceptoxEmpleado($licenciaPParcial->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo);
                        $valorFormulaSS = $this->calcularValoresxConceptoSSxEmpleado($licenciaPParcial->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo);
                        
                        $arrComoCalcula[$licenciaPParcial->idconcepto] = $this->comoCalculaValoresxConceptoxEmpleado($licenciaPParcial->idconcepto, $empleado->idempleado,$periodoActivoReintegro->idPeriodo, $arrComoCalcula[$licenciaPParcial->idconcepto]);

                        if($valorFormula < $salarioMinimoDia){
                            array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Valor calculado ($".number_format($valorFormula,0,",", ".").") es menor a el salario mínimo por día (".number_format($salarioMinimoDia,0,",", ".").") se aplica valor salario mínimo por día");
                            $valorFormula = $salarioMinimoDia;
                        }

                        if($valorFormulaSS != 0 &&  $valorFormulaSS < $salarioMinimoDia){
                            $valorFormulaSS = $salarioMinimoDia;
                        }

                        
                        if(isset($arrValorxConcepto[$licenciaPParcial->idconcepto])){
                            $valorUnit = ($valorFormula * floatval($dias));
                            $valorUnitSS = ($valorFormulaSS * floatval($dias));
                            array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Se multiplica $".number_format($valorFormula,0,",", ".")." por ".number_format($dias,0,",", ".")." días");
                            array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Se suma el valor(es) con anterior(es) ".number_format($arrValorxConcepto[$licenciaPParcial->idconcepto]["valor"],0,",", ".")." + ".number_format($valorUnit,0,",", "."));
                            array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Se suma el cantidad(es) con anterior(es) ".$arrValorxConcepto[$licenciaPParcial->idconcepto]["cantidad"]." + ".$dias." días");
                            $valorInt = $arrValorxConcepto[$licenciaPParcial->idconcepto]["valor"] + $valorUnit;
                            $cantidadInt = $arrValorxConcepto[$licenciaPParcial->idconcepto]["cantidad"] + floatval($dias);
                            $arrNovedades=$arrValorxConcepto[$licenciaPParcial->idconcepto]["arrNovedades"];
                            array_push($arrNovedades, [
                                "idNovedad" => $licenciaPParcial->idNovedad,
                                "valor" => $valorUnit,
                                "valor_ss" => $valorUnitSS
                                ]
                            );                            
                            $arrValorxConcepto[$licenciaPParcial->idconcepto] = array(
                                "naturaleza" => $licenciaPParcial->fkNaturaleza,
                                "unidad"=>"DÍA",
                                "cantidad"=> $cantidadInt,
                                "arrNovedades"=> $arrNovedades,
                                "valor" => $valorInt,
                                "tipoGen" => "novedad"
                            );
                        }
                        else{
                            $valorInt = $valorFormula * floatval($dias);
                            $valorIntSS = $valorFormulaSS * floatval($dias);
                            array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Se multiplica $".number_format($valorFormula,0,",", ".")." por ".number_format($dias,0,",", ".")." días");
                            $cantidadInt = floatval($dias);
                            $arrNovedades = array([
                                "idNovedad" => $licenciaPParcial->idNovedad,
                                "valor" => $valorInt,
                                "valor_ss" => $valorIntSS
                            ]);
                            $arrValorxConcepto[$licenciaPParcial->idconcepto] = array(
                                "naturaleza" => $licenciaPParcial->fkNaturaleza,
                                "unidad"=>"DÍA",
                                "cantidad"=> $cantidadInt,
                                "arrNovedades"=> $arrNovedades,
                                "valor" => $valorInt,
                                "tipoGen" => "novedad"
                            );
                        }
                    }                        
                }
                else if(strtotime($licenciaPParcial->fechaFinal)>=strtotime($fechaInicio)  
                &&  strtotime($licenciaPParcial->fechaFinal)<=strtotime($fechaFin) 
                &&  strtotime($licenciaPParcial->fechaInicial)<=strtotime($fechaInicio))
                {
                    $diaI = strtotime( $fechaInicio );
                    $diaF = strtotime( $licenciaPParcial->fechaFinal );
                    $diff = $diaF - $diaI;
                    $dias = $diff / ( 60 * 60 * 24); 
                    $dias++; //Como se toma desde dia.00:00:00 es para que tome el dia completo
                    $diasNoTrabajados = $diasNoTrabajados + $dias;
                    
                    if($licenciaPParcial->subTipo == "Formula"){                
                        $arrComoCalcula[$licenciaPParcial->idconcepto] = ($arrComoCalcula[$licenciaPParcial->idconcepto] ?? array());
                        array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Nueva novedad de licencia");
                        $valorFormula = $this->calcularValoresxConceptoxEmpleado($licenciaPParcial->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo);
                        $valorFormulaSS = $this->calcularValoresxConceptoSSxEmpleado($licenciaPParcial->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo);
                        
                        $arrComoCalcula[$licenciaPParcial->idconcepto] = $this->comoCalculaValoresxConceptoxEmpleado($licenciaPParcial->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo, $arrComoCalcula[$licenciaPParcial->idconcepto]);
                        if($valorFormula < $salarioMinimoDia){
                            array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Valor calculado ($".number_format($valorFormula,0,",", ".").") es menor a el salario mínimo por día (".number_format($salarioMinimoDia,0,",", ".").") se aplica valor salario mínimo por día");
                            $valorFormula = $salarioMinimoDia;
                        }

                        if($valorFormulaSS != 0 &&  $valorFormulaSS < $salarioMinimoDia){
                            $valorFormulaSS = $salarioMinimoDia;
                        }

                        if(isset($arrValorxConcepto[$licenciaPParcial->idconcepto])){

                            $valorUnit = ($valorFormula * floatval($dias));
                            $valorUnitSS = ($valorFormulaSS * floatval($dias));
                            array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Se multiplica $".number_format($valorFormula,0,",", ".")." por ".number_format($dias,0,",", ".")." días");
                            array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Se suma el valor(es) con anterior(es) ".number_format($arrValorxConcepto[$licenciaPParcial->idconcepto]["valor"],0,",", ".")." + ".number_format($valorUnit,0,",", "."));
                            array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Se suma el cantidad(es) con anterior(es) ".$arrValorxConcepto[$licenciaPParcial->idconcepto]["cantidad"]." + ".$dias." días");

                            $valorInt = $arrValorxConcepto[$licenciaPParcial->idconcepto]["valor"] + $valorUnit;
                            $cantidadInt = $arrValorxConcepto[$licenciaPParcial->idconcepto]["cantidad"] + floatval($dias);
                            $arrNovedades=$arrValorxConcepto[$licenciaPParcial->idconcepto]["arrNovedades"];
                            array_push($arrNovedades, [
                                "idNovedad" => $licenciaPParcial->idNovedad,
                                "valor" => $valorUnit,
                                "valor_ss" => $valorUnitSS
                            ]);
                            
                            $arrValorxConcepto[$licenciaPParcial->idconcepto] = array(
                                "naturaleza" => $licenciaPParcial->fkNaturaleza,
                                "unidad"=>"DÍA",
                                "cantidad"=> $cantidadInt,
                                "arrNovedades"=> $arrNovedades,
                                "valor" => $valorInt,
                                "tipoGen" => "novedad"
                            );
                        }
                        else{
                            $valorInt = $valorFormula * floatval($dias);
                            $valorIntSS = $valorFormulaSS * floatval($dias);
                            array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Se multiplica $".number_format($valorFormula,0,",", ".")." por ".number_format($dias,0,",", ".")." días");
                            $cantidadInt = floatval($dias);
                            $arrNovedades=array([
                                "idNovedad" => $licenciaPParcial->idNovedad,
                                "valor" => $valorInt,
                                "valor_ss" => $valorIntSS
                            ]);
                            $arrValorxConcepto[$licenciaPParcial->idconcepto] = array(
                                "naturaleza" => $licenciaPParcial->fkNaturaleza,
                                "unidad"=>"DÍA",
                                "cantidad"=> $cantidadInt,
                                "arrNovedades"=> $arrNovedades,
                                "valor" => $valorInt,
                                "tipoGen" => "novedad"
                            );
                        }
                    }
                }
                else if(strtotime($licenciaPParcial->fechaInicial)<=strtotime($fechaInicio)  
                &&  strtotime($licenciaPParcial->fechaFinal)>=strtotime($fechaFin)) 
                {
                    $diaI = strtotime( $fechaInicio );
                    $diaF = strtotime( $fechaFin );
                    $diff = $diaF - $diaI;
                    $dias = $diff / ( 60 * 60 * 24); 
                    $dias++; //Como se toma desde dia.00:00:00 es para que tome el dia completo
                    $diasNoTrabajados = $diasNoTrabajados + $dias;
                    
                    if($licenciaPParcial->subTipo == "Formula"){       
                        $arrComoCalcula[$licenciaPParcial->idconcepto] = ($arrComoCalcula[$licenciaPParcial->idconcepto] ?? array());
                        array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Nueva novedad de licencia");
                        $valorFormula = $this->calcularValoresxConceptoxEmpleado($licenciaPParcial->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo);
                        $valorFormulaSS = $this->calcularValoresxConceptoSSxEmpleado($licenciaPParcial->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo);

                        $arrComoCalcula[$licenciaPParcial->idconcepto] = $this->comoCalculaValoresxConceptoxEmpleado($licenciaPParcial->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo, $arrComoCalcula[$licenciaPParcial->idconcepto]);
                        if($valorFormula < $salarioMinimoDia){
                            array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Valor calculado ($".number_format($valorFormula,0,",", ".").") es menor a el salario mínimo por día (".number_format($salarioMinimoDia,0,",", ".").") se aplica valor salario mínimo por día");
                            $valorFormula = $salarioMinimoDia;
                        }

                        if($valorFormulaSS != 0 &&  $valorFormulaSS < $salarioMinimoDia){
                            $valorFormulaSS = $salarioMinimoDia;
                        }

                        if(isset($arrValorxConcepto[$licenciaPParcial->idconcepto])){

                            $valorUnit = ($valorFormula * floatval($dias));
                            $valorUnitSS = ($valorFormulaSS * floatval($dias));
                            array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Se multiplica $".number_format($valorFormula,0,",", ".")." por ".number_format($dias,0,",", ".")." días");
                            array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Se suma el valor(es) con anterior(es) ".number_format($arrValorxConcepto[$licenciaPParcial->idconcepto]["valor"],0,",", ".")." + ".number_format($valorUnit,0,",", "."));
                            array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Se suma el cantidad(es) con anterior(es) ".$arrValorxConcepto[$licenciaPParcial->idconcepto]["cantidad"]." + ".$dias." días");
                            $valorInt = $arrValorxConcepto[$licenciaPParcial->idconcepto]["valor"] + $valorUnit;
                            $cantidadInt = $arrValorxConcepto[$licenciaPParcial->idconcepto]["cantidad"] + floatval($dias);
                            $arrNovedades=$arrValorxConcepto[$licenciaPParcial->idconcepto]["arrNovedades"];
                            array_push($arrNovedades,[
                                "idNovedad" => $licenciaPParcial->idNovedad,
                                "valor" => $valorUnit,
                                "valor_ss" => $valorUnitSS
                            ]);
                            
                            $arrValorxConcepto[$licenciaPParcial->idconcepto] = array(
                                "naturaleza" => $licenciaPParcial->fkNaturaleza,
                                "unidad"=>"DÍA",
                                "cantidad"=> $cantidadInt,
                                "arrNovedades"=> $arrNovedades,
                                "valor" => $valorInt,
                                "tipoGen" => "novedad"
                            );
                        }
                        else{
                            $valorInt = $valorFormula * floatval($dias);
                            $valorIntSS = $valorFormulaSS * floatval($dias);
                            array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Se multiplica $".number_format($valorFormula,0,",", ".")." por ".number_format($dias,0,",", ".")." días");
                            $cantidadInt = floatval($dias);
                            $arrNovedades=array([
                                "idNovedad" => $licenciaPParcial->idNovedad,
                                "valor" => $valorInt,
                                "valor_ss" => $valorIntSS
                            ]);
                            $arrValorxConcepto[$licenciaPParcial->idconcepto] = array(
                                "naturaleza" => $licenciaPParcial->fkNaturaleza,
                                "unidad"=>"DÍA",
                                "cantidad"=> $cantidadInt,
                                "arrNovedades"=> $arrNovedades,
                                "valor" => $valorInt,
                                "tipoGen" => "novedad"
                            );
                        }
                    }

                }
                else if(strtotime($fechaInicio)<=strtotime($licenciaPParcial->fechaInicial)  
                &&  strtotime($fechaFin)>=strtotime($licenciaPParcial->fechaFinal)) 
                {
                    
                    $diaI = strtotime($licenciaPParcial->fechaInicial);
                    $diaF = strtotime($licenciaPParcial->fechaFinal);
                    $diff = $diaF - $diaI;
                    $dias = $diff / ( 60 * 60 * 24); 
                    $dias++; //Como se toma desde dia.00:00:00 es para que tome el dia completo
                    $diasNoTrabajados = $diasNoTrabajados + $dias;
                    if($licenciaPParcial->subTipo == "Formula"){         
                        $arrComoCalcula[$licenciaPParcial->idconcepto] = ($arrComoCalcula[$licenciaPParcial->idconcepto] ?? array());
                        array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Nueva novedad de licencia");                                  
                        $valorFormula = $this->calcularValoresxConceptoxEmpleado($licenciaPParcial->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo);
                        $valorFormulaSS = $this->calcularValoresxConceptoSSxEmpleado($licenciaPParcial->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo);

                        $arrComoCalcula[$licenciaPParcial->idconcepto] = $this->comoCalculaValoresxConceptoxEmpleado($licenciaPParcial->idconcepto, $empleado->idempleado,$periodoActivoReintegro->idPeriodo, $arrComoCalcula[$licenciaPParcial->idconcepto]);
                        if($valorFormula < $salarioMinimoDia){
                            array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Valor calculado ($".number_format($valorFormula,0,",", ".").") es menor a el salario mínimo por día (".number_format($salarioMinimoDia,0,",", ".").") se aplica valor salario mínimo por día");
                            $valorFormula = $salarioMinimoDia;
                        }

                        if($valorFormulaSS != 0 &&  $valorFormulaSS < $salarioMinimoDia){
                            $valorFormulaSS = $salarioMinimoDia;
                        }

                        if(isset($arrValorxConcepto[$licenciaPParcial->idconcepto])){
                            $valorUnit = ($valorFormula * floatval($dias));
                            $valorUnitSS = ($valorFormulaSS * floatval($dias));
                            array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Se multiplica $".number_format($valorFormula,0,",", ".")." por ".number_format($dias,0,",", ".")." días");
                            array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Se suma el valor(es) con anterior(es) ".number_format($arrValorxConcepto[$licenciaPParcial->idconcepto]["valor"],0,",", ".")." + ".number_format($valorUnit,0,",", "."));
                            array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Se suma el cantidad(es) con anterior(es) ".$arrValorxConcepto[$licenciaPParcial->idconcepto]["cantidad"]." + ".$dias." días");
                            $valorInt = $arrValorxConcepto[$licenciaPParcial->idconcepto]["valor"] + $valorUnit;
                            $cantidadInt = $arrValorxConcepto[$licenciaPParcial->idconcepto]["cantidad"] + floatval($dias);
                            $arrNovedades=$arrValorxConcepto[$licenciaPParcial->idconcepto]["arrNovedades"];
                            array_push($arrNovedades, [
                                "idNovedad" => $licenciaPParcial->idNovedad,
                                "valor" => $valorUnit,
                                "valor_ss" => $valorUnitSS
                            ]);
                            
                            $arrValorxConcepto[$licenciaPParcial->idconcepto] = array(
                                "naturaleza" => $licenciaPParcial->fkNaturaleza,
                                "unidad"=>"DÍA",
                                "cantidad"=> $cantidadInt,
                                "arrNovedades"=> $arrNovedades,
                                "valor" => $valorInt,
                                "tipoGen" => "novedad"
                            );
                        }
                        else{
                            $valorInt = $valorFormula * floatval($dias);
                            $valorIntSS = $valorFormulaSS * floatval($dias);
                            array_push($arrComoCalcula[$licenciaPParcial->idconcepto], "Se multiplica $".number_format($valorFormula,0,",", ".")." por ".number_format($dias,0,",", ".")." días");
                            $cantidadInt = floatval($dias);
                            $arrNovedades=array([
                                "idNovedad" => $licenciaPParcial->idNovedad,
                                "valor" => $valorInt,
                                "valor_ss" => $valorIntSS
                            ]);
                            $arrValorxConcepto[$licenciaPParcial->idconcepto] = array(
                                "naturaleza" => $licenciaPParcial->fkNaturaleza,
                                "unidad"=>"DÍA",
                                "cantidad"=> $cantidadInt,
                                "arrNovedades"=> $arrNovedades,
                                "valor" => $valorInt,
                                "tipoGen" => "novedad"
                            );
                        }
                    }
                }
            }
            //FIN LICENCIAS PARCIAL


            //INICIO INCAPACIDADES PARCIAL        
            $sqlWhere = "( 
                ('".$fechaInicio."' BETWEEN i.fechaInicial AND i.fechaFinal) OR
                ('".$fechaFin."' BETWEEN i.fechaInicial AND i.fechaFinal) OR
                (i.fechaInicial BETWEEN '".$fechaInicio."' AND '".$fechaFin."') OR
                (i.fechaFinal BETWEEN '".$fechaInicio."' AND '".$fechaFin."')
            )";
            $incapacidadesPParcial = DB::table("incapacidad", "i")
            ->select(["i.*","c.*","n.*"])
            ->join("novedad as n", "n.fkIncapacidad","=","i.idIncapacidad")
            ->join("conceptosxtipoliquidacion AS ctl", "ctl.fkConcepto","=","n.fkConcepto")
            ->join("concepto AS c", "ctl.fkConcepto","=","c.idconcepto")
            ->where("n.fkEmpleado", "=", $empleado->idempleado)
            ->where("n.fkPeriodoActivo","=", $periodoActivoReintegro->idPeriodo)
            ->where("ctl.fkTipoLiquidacion","=",$tipoliquidacion)
            ->where("i.pagoTotal", "=", "0")           
            ->where("n.fkEstado","=","7") 
            ->whereRaw($sqlWhere)
            ->distinct()
            ->get();
            
            //Agregar valor de las novedades de incapacidades con pago parcial
            foreach($incapacidadesPParcial as $incapacidadPParcial){

                if(strtotime($incapacidadPParcial->fechaInicial)>=strtotime($fechaInicio)
                    &&  strtotime($incapacidadPParcial->fechaInicial)<=strtotime($fechaFin) 
                    &&  strtotime($incapacidadPParcial->fechaFinal)>=strtotime($fechaFin))
                {

                    $diaI = $incapacidadPParcial->fechaInicial;
                    $diaF = $fechaFin;
                    if(substr($diaI, 8, 2) == "31" || (substr($diaI, 8, 2) == "28" && substr($diaI, 5, 2) == "02") || (substr($diaI, 8, 2) == "29" && substr($diaI, 5, 2) == "02") ){
                        $diaI = substr($diaI,0,8)."30";
                    }

                    if(substr($diaF, 8, 2) == "31" || (substr($diaF, 8, 2) == "28" && substr($diaF, 5, 2) == "02") || (substr($diaF, 8, 2) == "29" && substr($diaF, 5, 2) == "02")){
                        $diaF = substr($diaF,0,8)."30";
                    }
                    $dias = $this->days_360($diaI, $diaF);
                    $dias ++;
                    
                    if($incapacidadPParcial->subTipo == "Formula"){             

                        $arrComoCalcula[$incapacidadPParcial->idconcepto] = ($arrComoCalcula[$incapacidadPParcial->idconcepto] ?? array());
                        array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Nueva novedad de incapacidad");       

                        $valorFormula = $this->calcularValoresxConceptoxEmpleado($incapacidadPParcial->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo);
                        $valorFormulaSS = $this->calcularValoresxConceptoSSxEmpleado($incapacidadPParcial->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo);

                        $arrComoCalcula[$incapacidadPParcial->idconcepto] = $this->comoCalculaValoresxConceptoxEmpleado($incapacidadPParcial->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo, $arrComoCalcula[$incapacidadPParcial->idconcepto]);

                        if($valorFormula < $salarioMinimoDia && $incapacidadPParcial->idconcepto != 135){
                            array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Valor calculado ($".number_format($valorFormula,0,",", ".").") es menor a el salario mínimo por día (".number_format($salarioMinimoDia,0,",", ".").") se aplica valor salario mínimo por día");
                            $valorFormula = $salarioMinimoDia;
                        }

                        if($valorFormulaSS != 0 &&  $valorFormulaSS < $salarioMinimoDia){
                            $valorFormulaSS = $salarioMinimoDia;
                        }

                        if(isset($arrValorxConcepto[$incapacidadPParcial->idconcepto])){

                            $valorUnit = ($valorFormula * floatval($dias));
                            $valorUnitSS = ($valorFormulaSS * floatval($dias));
                            array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Se multiplica $".number_format($valorFormula,0,",", ".")." por ".number_format($dias,0,",", ".")." días");
                            array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Se suma el valor(es) con anterior(es) ".number_format($arrValorxConcepto[$incapacidadPParcial->idconcepto]["valor"],0,",", ".")." + ".number_format($valorUnit,0,",", "."));
                            array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Se suma el cantidad(es) con anterior(es) ".$arrValorxConcepto[$incapacidadPParcial->idconcepto]["cantidad"]." + ".$dias." días");

                            $valorInt = $arrValorxConcepto[$incapacidadPParcial->idconcepto]["valor"] + $valorUnit;
                            $cantidadInt = $arrValorxConcepto[$incapacidadPParcial->idconcepto]["cantidad"] + floatval($dias);
                            $arrNovedades=$arrValorxConcepto[$incapacidadPParcial->idconcepto]["arrNovedades"];
                            array_push($arrNovedades, [
                                "idNovedad" => $incapacidadPParcial->idNovedad,
                                "valor" => $valorUnit,
                                "valor_ss" => $valorUnitSS
                            ]
                            );                            
                            $arrValorxConcepto[$incapacidadPParcial->idconcepto] = array(
                                "naturaleza" => $incapacidadPParcial->fkNaturaleza,
                                "unidad"=>"DÍA",
                                "cantidad"=> $cantidadInt,
                                "arrNovedades"=> $arrNovedades,
                                "valor" => $valorInt,
                                "tipoGen" => "novedad"
                            );
                        }
                        else{
                            $valorInt = $valorFormula * floatval($dias);
                            $valorIntSS = $valorFormulaSS * floatval($dias);
                            array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Se multiplica $".number_format($valorFormula,0,",", ".")." por ".number_format($dias,0,",", ".")." días");
                            $cantidadInt = floatval($dias);
                            $arrNovedades = array([
                                "idNovedad" => $incapacidadPParcial->idNovedad,
                                "valor" => $valorInt,
                                "valor_ss" => $valorIntSS
                            ]);
                            $arrValorxConcepto[$incapacidadPParcial->idconcepto] = array(
                                "naturaleza" => $incapacidadPParcial->fkNaturaleza,
                                "unidad"=>"DÍA",
                                "cantidad"=> $cantidadInt,
                                "arrNovedades"=> $arrNovedades,
                                "valor" => $valorInt,
                                "tipoGen" => "novedad"
                            );
                        }
                    }                        
                }
                else if(strtotime($incapacidadPParcial->fechaFinal)>=strtotime($fechaInicio)  
                &&  strtotime($incapacidadPParcial->fechaFinal)<=strtotime($fechaFin) 
                &&  strtotime($incapacidadPParcial->fechaInicial)<=strtotime($fechaInicio))
                {
                    $diaI = $fechaInicio;
                    $diaF = $incapacidadPParcial->fechaFinal;
                    if(substr($diaI, 8, 2) == "31" || (substr($diaI, 8, 2) == "28" && substr($diaI, 5, 2) == "02") || (substr($diaI, 8, 2) == "29" && substr($diaI, 5, 2) == "02") ){
                        $diaI = substr($diaI,0,8)."30";
                    }

                    if(substr($diaF, 8, 2) == "31" || (substr($diaF, 8, 2) == "28" && substr($diaF, 5, 2) == "02") || (substr($diaF, 8, 2) == "29" && substr($diaF, 5, 2) == "02")){
                        $diaF = substr($diaF,0,8)."30";
                    }
                    $dias = $this->days_360($diaI, $diaF);
                    $dias ++;


                    if($incapacidadPParcial->subTipo == "Formula"){                
                        $arrComoCalcula[$incapacidadPParcial->idconcepto] = ($arrComoCalcula[$incapacidadPParcial->idconcepto] ?? array());
                        array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Nueva novedad de incapacidad");  

                        $valorFormula = $this->calcularValoresxConceptoxEmpleado($incapacidadPParcial->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo);
                        $valorFormulaSS = $this->calcularValoresxConceptoSSxEmpleado($incapacidadPParcial->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo);


                        $arrComoCalcula[$incapacidadPParcial->idconcepto] = $this->comoCalculaValoresxConceptoxEmpleado($incapacidadPParcial->idconcepto, $empleado->idempleado,$periodoActivoReintegro->idPeriodo, $arrComoCalcula[$incapacidadPParcial->idconcepto]);
                        if($valorFormula < $salarioMinimoDia  && $incapacidadPParcial->idconcepto != 135){
                            array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Valor calculado ($".number_format($valorFormula,0,",", ".").") es menor a el salario mínimo por día (".number_format($salarioMinimoDia,0,",", ".").") se aplica valor salario mínimo por día");
                            $valorFormula = $salarioMinimoDia;
                        }

                        if($valorFormulaSS != 0 &&  $valorFormulaSS < $salarioMinimoDia){
                            $valorFormulaSS = $salarioMinimoDia;
                        }

                        if(isset($arrValorxConcepto[$incapacidadPParcial->idconcepto])){

                            $valorUnit = ($valorFormula * floatval($dias));
                            $valorUnitSS = ($valorFormulaSS * floatval($dias));

                            array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Se multiplica $".number_format($valorFormula,0,",", ".")." por ".number_format($dias,0,",", ".")." días");
                            array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Se suma el valor(es) con anterior(es) ".number_format($arrValorxConcepto[$incapacidadPParcial->idconcepto]["valor"],0,",", ".")." + ".number_format($valorUnit,0,",", "."));
                            array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Se suma el cantidad(es) con anterior(es) ".$arrValorxConcepto[$incapacidadPParcial->idconcepto]["cantidad"]." + ".$dias." días");

                            $valorInt = $arrValorxConcepto[$incapacidadPParcial->idconcepto]["valor"] + $valorUnit;
                            $cantidadInt = $arrValorxConcepto[$incapacidadPParcial->idconcepto]["cantidad"] + floatval($dias);
                            $arrNovedades=$arrValorxConcepto[$incapacidadPParcial->idconcepto]["arrNovedades"];
                            array_push($arrNovedades, [
                                "idNovedad" => $incapacidadPParcial->idNovedad,
                                "valor" => $valorUnit,
                                "valor_ss" => $valorUnitSS
                            ]);
                            
                            $arrValorxConcepto[$incapacidadPParcial->idconcepto] = array(
                                "naturaleza" => $incapacidadPParcial->fkNaturaleza,
                                "unidad"=>"DÍA",
                                "cantidad"=> $cantidadInt,
                                "arrNovedades"=> $arrNovedades,
                                "valor" => $valorInt,
                                "tipoGen" => "novedad"
                            );
                        }
                        else{
                            $valorInt = $valorFormula * floatval($dias);
                            $valorIntSS = $valorFormulaSS * floatval($dias);
                            array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Se multiplica $".number_format($valorFormula,0,",", ".")." por ".number_format($dias,0,",", ".")." días");
                            $cantidadInt = floatval($dias);
                            $arrNovedades=array([
                                "idNovedad" => $incapacidadPParcial->idNovedad,
                                "valor" => $valorInt,
                                "valor_ss" => $valorIntSS
                            ]);
                            $arrValorxConcepto[$incapacidadPParcial->idconcepto] = array(
                                "naturaleza" => $incapacidadPParcial->fkNaturaleza,
                                "unidad"=>"DÍA",
                                "cantidad"=> $cantidadInt,
                                "arrNovedades"=> $arrNovedades,
                                "valor" => $valorInt,
                                "tipoGen" => "novedad"
                            );
                        }
                    }
                }
                else if(strtotime($incapacidadPParcial->fechaInicial)<=strtotime($fechaInicio)  
                &&  strtotime($incapacidadPParcial->fechaFinal)>=strtotime($fechaFin)) 
                {
                    $diaI = $fechaInicio;
                    $diaF = $fechaFin;
                    if(substr($diaI, 8, 2) == "31" || (substr($diaI, 8, 2) == "28" && substr($diaI, 5, 2) == "02") || (substr($diaI, 8, 2) == "29" && substr($diaI, 5, 2) == "02") ){
                        $diaI = substr($diaI,0,8)."30";
                    }

                    if(substr($diaF, 8, 2) == "31" || (substr($diaF, 8, 2) == "28" && substr($diaF, 5, 2) == "02") || (substr($diaF, 8, 2) == "29" && substr($diaF, 5, 2) == "02")){
                        $diaF = substr($diaF,0,8)."30";
                    }
                    $dias = $this->days_360($diaI, $diaF);
                    $dias ++;

                    if($incapacidadPParcial->subTipo == "Formula"){       
                        $arrComoCalcula[$incapacidadPParcial->idconcepto] = ($arrComoCalcula[$incapacidadPParcial->idconcepto] ?? array());
                        array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Nueva novedad de incapacidad");  
                        $valorFormula = $this->calcularValoresxConceptoxEmpleado($incapacidadPParcial->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo);
                        $valorFormulaSS = $this->calcularValoresxConceptoSSxEmpleado($incapacidadPParcial->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo);

                        $arrComoCalcula[$incapacidadPParcial->idconcepto] = $this->comoCalculaValoresxConceptoxEmpleado($incapacidadPParcial->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo, $arrComoCalcula[$incapacidadPParcial->idconcepto]);
                        if($valorFormula < $salarioMinimoDia  && $incapacidadPParcial->idconcepto != 135){
                            array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Valor calculado ($".number_format($valorFormula,0,",", ".").") es menor a el salario mínimo por día (".number_format($salarioMinimoDia,0,",", ".").") se aplica valor salario mínimo por día");
                            $valorFormula = $salarioMinimoDia;
                        }

                        if($valorFormulaSS != 0 &&  $valorFormulaSS < $salarioMinimoDia){
                            $valorFormulaSS = $salarioMinimoDia;
                        }

                        if(isset($arrValorxConcepto[$incapacidadPParcial->idconcepto])){

                            $valorUnit = ($valorFormula * floatval($dias));
                            $valorUnitSS = ($valorFormulaSS * floatval($dias));
                            array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Se multiplica $".number_format($valorFormula,0,",", ".")." por ".number_format($dias,0,",", ".")." días");
                            array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Se suma el valor(es) con anterior(es) ".number_format($arrValorxConcepto[$incapacidadPParcial->idconcepto]["valor"],0,",", ".")." + ".number_format($valorUnit,0,",", "."));
                            array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Se suma el cantidad(es) con anterior(es) ".$arrValorxConcepto[$incapacidadPParcial->idconcepto]["cantidad"]." + ".$dias." días");

                            $valorInt = $arrValorxConcepto[$incapacidadPParcial->idconcepto]["valor"] + ($valorFormula * floatval($dias));
                            $cantidadInt = $arrValorxConcepto[$incapacidadPParcial->idconcepto]["cantidad"] + floatval($dias);
                            $arrNovedades=$arrValorxConcepto[$incapacidadPParcial->idconcepto]["arrNovedades"];
                            array_push($arrNovedades, [
                                "idNovedad" => $incapacidadPParcial->idNovedad,
                                "valor" => $valorUnit,
                                "valor_ss" => $valorUnitSS
                            ]);
                            
                            $arrValorxConcepto[$incapacidadPParcial->idconcepto] = array(
                                "naturaleza" => $incapacidadPParcial->fkNaturaleza,
                                "unidad"=>"DÍA",
                                "cantidad"=> $cantidadInt,
                                "arrNovedades"=> $arrNovedades,
                                "valor" => $valorInt,
                                "tipoGen" => "novedad"
                            );
                        }
                        else{
                            $valorInt = $valorFormula * floatval($dias);
                            $valorIntSS = $valorFormulaSS * floatval($dias);
                            array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Se multiplica $".number_format($valorFormula,0,",", ".")." por ".number_format($dias,0,",", ".")." días");
                            $cantidadInt = floatval($dias);
                            $arrNovedades=array([
                                "idNovedad" => $incapacidadPParcial->idNovedad,
                                "valor" => $valorInt,
                                "valor_ss" => $valorIntSS
                            ]);
                            $arrValorxConcepto[$incapacidadPParcial->idconcepto] = array(
                                "naturaleza" => $incapacidadPParcial->fkNaturaleza,
                                "unidad"=>"DÍA",
                                "cantidad"=> $cantidadInt,
                                "arrNovedades"=> $arrNovedades,
                                "valor" => $valorInt,
                                "tipoGen" => "novedad"
                            );
                        }
                    }

                }
                else if(strtotime($fechaInicio)<=strtotime($incapacidadPParcial->fechaInicial)  
                &&  strtotime($fechaFin)>=strtotime($incapacidadPParcial->fechaFinal)) 
                {
                    $diaI = $incapacidadPParcial->fechaInicial;
                    $diaF = $incapacidadPParcial->fechaFinal;
                    if(substr($diaI, 8, 2) == "31" || (substr($diaI, 8, 2) == "28" && substr($diaI, 5, 2) == "02") || (substr($diaI, 8, 2) == "29" && substr($diaI, 5, 2) == "02") ){
                        $diaI = substr($diaI,0,8)."30";
                    }

                    if(substr($diaF, 8, 2) == "31" || (substr($diaF, 8, 2) == "28" && substr($diaF, 5, 2) == "02") || (substr($diaF, 8, 2) == "29" && substr($diaF, 5, 2) == "02")){
                        $diaF = substr($diaF,0,8)."30";
                    }
                    $dias = $this->days_360($diaI, $diaF);
                    $dias ++;

                    
                    if($incapacidadPParcial->subTipo == "Formula"){                                           
                        $arrComoCalcula[$incapacidadPParcial->idconcepto] = ($arrComoCalcula[$incapacidadPParcial->idconcepto] ?? array());
                        array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Nueva novedad de incapacidad");  

                        $valorFormula = $this->calcularValoresxConceptoxEmpleado($incapacidadPParcial->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo);
                        $valorFormulaSS = $this->calcularValoresxConceptoSSxEmpleado($incapacidadPParcial->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo);
                        
                        $arrComoCalcula[$incapacidadPParcial->idconcepto] = $this->comoCalculaValoresxConceptoxEmpleado($incapacidadPParcial->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo, $arrComoCalcula[$incapacidadPParcial->idconcepto]);
                        if($valorFormula < $salarioMinimoDia  && $incapacidadPParcial->idconcepto != 135){
                            array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Valor calculado ($".number_format($valorFormula,0,",", ".").") es menor a el salario mínimo por día (".number_format($salarioMinimoDia,0,",", ".").") se aplica valor salario mínimo por día");
                            $valorFormula = $salarioMinimoDia;
                        }

                        if($valorFormulaSS != 0 &&  $valorFormulaSS < $salarioMinimoDia){
                            $valorFormulaSS = $salarioMinimoDia;
                        }

                        if(isset($arrValorxConcepto[$incapacidadPParcial->idconcepto])){
                            $valorUnit = ($valorFormula * floatval($dias));
                            $valorUnitSS = ($valorFormulaSS * floatval($dias));
                            array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Se multiplica $".number_format($valorFormula,0,",", ".")." por ".number_format($dias,0,",", ".")." días");
                            array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Se suma el valor(es) con anterior(es) ".number_format($arrValorxConcepto[$incapacidadPParcial->idconcepto]["valor"],0,",", ".")." + ".number_format($valorUnit,0,",", "."));
                            array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Se suma el cantidad(es) con anterior(es) ".$arrValorxConcepto[$incapacidadPParcial->idconcepto]["cantidad"]." + ".$dias." días");
                            $valorInt = $arrValorxConcepto[$incapacidadPParcial->idconcepto]["valor"] + ($valorFormula * floatval($dias));
                            $cantidadInt = $arrValorxConcepto[$incapacidadPParcial->idconcepto]["cantidad"] + floatval($dias);
                            $arrNovedades=$arrValorxConcepto[$incapacidadPParcial->idconcepto]["arrNovedades"];
                            array_push($arrNovedades, [
                                "idNovedad" => $incapacidadPParcial->idNovedad,
                                "valor" => $valorUnit,
                                "valor_ss" => $valorUnitSS
                            ]);
                            
                            $arrValorxConcepto[$incapacidadPParcial->idconcepto] = array(
                                "naturaleza" => $incapacidadPParcial->fkNaturaleza,
                                "unidad"=>"DÍA",
                                "cantidad"=> $cantidadInt,
                                "arrNovedades"=> $arrNovedades,
                                "valor" => $valorInt,
                                "tipoGen" => "novedad"
                            );
                        }
                        else{
                            $valorInt = $valorFormula * floatval($dias);
                            $valorIntSS = $valorFormulaSS * floatval($dias);
                            array_push($arrComoCalcula[$incapacidadPParcial->idconcepto], "Se multiplica $".number_format($valorFormula,0,",", ".")." por ".number_format($dias,0,",", ".")." días");
                            $cantidadInt = floatval($dias);
                            $arrNovedades=array([
                                "idNovedad" => $incapacidadPParcial->idNovedad,
                                "valor" => $valorInt,
                                "valor_ss" => $valorIntSS
                            ]);
                            $arrValorxConcepto[$incapacidadPParcial->idconcepto] = array(
                                "naturaleza" => $incapacidadPParcial->fkNaturaleza,
                                "unidad"=>"DÍA",
                                "cantidad"=> $cantidadInt,
                                "arrNovedades"=> $arrNovedades,
                                "valor" => $valorInt,
                                "tipoGen" => "novedad"
                            );
                        }
                    }
                }
            }
            //FIN INCAPACIDADES PARCIAL        



            //Calcular los dias que estuvo en incapacidad independiente si es pago parcial o completo
            //INICIO INCAPACIDADES DIAS
            $incapacidadesParaCalculoDias = DB::table("incapacidad", "i")
            ->select(["i.*","c.*"])
            ->join("novedad as n", "n.fkIncapacidad","=","i.idIncapacidad")
            ->join("conceptosxtipoliquidacion AS ctl", "ctl.fkConcepto","=","n.fkConcepto")
            ->join("concepto AS c", "ctl.fkConcepto","=","c.idconcepto")
            ->where("ctl.fkTipoLiquidacion","=",$tipoliquidacion)     
            ->where("n.fkEmpleado", "=", $empleado->idempleado)  
            ->where("n.fkPeriodoActivo","=", $periodoActivoReintegro->idPeriodo)
            ->where("n.fkEstado","=","7")
            ->whereRaw($sqlWhere)
            ->distinct()
            ->get();


            

            foreach($incapacidadesParaCalculoDias as $incapacidadCalculos){
                if(strtotime($incapacidadCalculos->fechaInicial)>=strtotime($fechaInicio)  
                    &&  strtotime($incapacidadCalculos->fechaInicial)<=strtotime($fechaFin) 
                    &&  strtotime($incapacidadCalculos->fechaFinal)>=strtotime($fechaFin))
                {
                    $diaI = $incapacidadCalculos->fechaInicial;
                    $diaF = $fechaFin;
                    if(substr($diaI, 8, 2) == "31" || (substr($diaI, 8, 2) == "28" && substr($diaI, 5, 2) == "02") || (substr($diaI, 8, 2) == "29" && substr($diaI, 5, 2) == "02") ){
                        $diaI = substr($diaI,0,8)."30";
                    }

                    if(substr($diaF, 8, 2) == "31" || (substr($diaF, 8, 2) == "28" && substr($diaF, 5, 2) == "02") || (substr($diaF, 8, 2) == "29" && substr($diaF, 5, 2) == "02")){
                        $diaF = substr($diaF,0,8)."30";
                    }
                    $dias = $this->days_360($diaI, $diaF);
                    $dias ++;
                    $diasNoTrabajados = $diasNoTrabajados + $dias;
                    
                }
                else if(strtotime($incapacidadCalculos->fechaFinal)>=strtotime($fechaInicio)  
                &&  strtotime($incapacidadCalculos->fechaFinal)<=strtotime($fechaFin) 
                &&  strtotime($incapacidadCalculos->fechaInicial)<=strtotime($fechaInicio))
                {
                    $diaI = $fechaInicio;
                    $diaF = $incapacidadCalculos->fechaFinal;
                    if(substr($diaI, 8, 2) == "31" || (substr($diaI, 8, 2) == "28" && substr($diaI, 5, 2) == "02") || (substr($diaI, 8, 2) == "29" && substr($diaI, 5, 2) == "02") ){
                        $diaI = substr($diaI,0,8)."30";
                    }

                    if(substr($diaF, 8, 2) == "31" || (substr($diaF, 8, 2) == "28" && substr($diaF, 5, 2) == "02") || (substr($diaF, 8, 2) == "29" && substr($diaF, 5, 2) == "02")){
                        $diaF = substr($diaF,0,8)."30";
                    }
                    $dias = $this->days_360($diaI, $diaF);
                    $dias ++;
                    $diasNoTrabajados = $diasNoTrabajados + $dias;
               
                } 
                else if(strtotime($incapacidadCalculos->fechaInicial)<=strtotime($fechaInicio)  
                &&  strtotime($incapacidadCalculos->fechaFinal)>=strtotime($fechaFin)) 
                {
                    $diaI = $fechaInicio;
                    $diaF = $fechaFin;

                    if(substr($diaI, 8, 2) == "31" || (substr($diaI, 8, 2) == "28" && substr($diaI, 5, 2) == "02") || (substr($diaI, 8, 2) == "29" && substr($diaI, 5, 2) == "02") ){
                        $diaI = substr($diaI,0,8)."30";
                    }

                    if(substr($diaF, 8, 2) == "31" || (substr($diaF, 8, 2) == "28" && substr($diaF, 5, 2) == "02") || (substr($diaF, 8, 2) == "29" && substr($diaF, 5, 2) == "02")){
                        $diaF = substr($diaF,0,8)."30";
                    }
                    
                    

                    $dias = $this->days_360($diaI, $diaF);
                    $dias ++;

                    $diasNoTrabajados = $diasNoTrabajados + $dias;
         
                }
                else if(strtotime($fechaInicio)<=strtotime($incapacidadCalculos->fechaInicial)  
                &&  strtotime($fechaFin)>=strtotime($incapacidadCalculos->fechaFinal)) 
                {
                    $diaI = $incapacidadCalculos->fechaInicial;
                    $diaF = $incapacidadCalculos->fechaFinal;
                    
                    if(substr($diaI, 8, 2) == "31" || (substr($diaI, 8, 2) == "28" && substr($diaI, 5, 2) == "02") || (substr($diaI, 8, 2) == "29" && substr($diaI, 5, 2) == "02") ){
                        $diaI = substr($diaI,0,8)."30";
                    }

                    if(substr($diaF, 8, 2) == "31" || (substr($diaF, 8, 2) == "28" && substr($diaF, 5, 2) == "02") || (substr($diaF, 8, 2) == "29" && substr($diaF, 5, 2) == "02")){
                        $diaF = substr($diaF,0,8)."30";
                    }
                    

                    $dias = $this->days_360($diaI, $diaF);
                    $dias ++;
                    $diasNoTrabajados = $diasNoTrabajados + $dias;
                }
            }
            //FIN INCAPACIDADES DIAS
            

            //INICIO CONCEPTOS FIJOS EMPLEADO
            $conceptosFijosNoSalarialEmpl = DB::table("conceptofijo", "cf")
            ->select(["cf.valor","cf.fechaInicio","cf.fechaFin", "cf.fkConcepto","cf.unidad", "c.*"])
            ->join("conceptosxtipoliquidacion AS ctl", "ctl.fkConcepto","=","cf.fkConcepto")
            ->join("concepto AS c", "ctl.fkConcepto","=","c.idconcepto")
            ->where("ctl.fkTipoLiquidacion","=",$tipoliquidacion)     
            ->where("cf.fkEmpleado", "=", $empleado->idempleado)  
            ->where("cf.fkPeriodoActivo","=", $periodoActivoReintegro->idPeriodo)
            ->where("cf.fkEstado", "=", "1")
            ->whereIn("c.fkNaturaleza",["5","6"])//Naturaleza: PAGO O DESCUENTO
            ->distinct()
            ->get();
            foreach($conceptosFijosNoSalarialEmpl as $conceptoFijoNoSalarialEmpl){
                if(isset($conceptoFijoNoSalarialEmpl->fechaFin)){
                    if(strtotime($conceptoFijoNoSalarialEmpl->fechaInicio)>=strtotime($fechaInicio)  
                        &&  strtotime($conceptoFijoNoSalarialEmpl->fechaInicio)<=strtotime($fechaFin) 
                        &&  strtotime($conceptoFijoNoSalarialEmpl->fechaFin)>=strtotime($fechaFin))
                    {
                        if(isset($arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto])){
                            $valorInt = floatval($conceptoFijoNoSalarialEmpl->valor);

                            
                            $arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Nuevo concepto fijo no salarial: $".number_format($valorInt,0,",", ".")."");  

                            if($conceptoFijoNoSalarialEmpl->fkNaturaleza=="6" && $valorInt > 0){
                                $valorInt=$valorInt*-1;                            
                                array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Naturaleza descuento no salarial se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }
                            array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Se suma con anterior(es): $".number_format( $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto]["valor"],0,",", ".")." + $".number_format($valorInt,0,",", "."));  

                            

                            $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoNoSalarialEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoNoSalarialEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto]["arrNovedades"],
                                "valor" => $valorInt + $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto]["valor"],
                                "tipoGen" => "conceptoFijo"
                            );
                        }
                        else{
                            $arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Nuevo concepto fijo no salarial: $".number_format($valorInt,0,",", ".")."");  
                            if($conceptoFijoNoSalarialEmpl->fkNaturaleza=="6" && $valorInt > 0){                            
                                $valorInt=$valorInt*-1;
                                array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Naturaleza descuento no salarial se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }
                            $valorInt = floatval($conceptoFijoNoSalarialEmpl->valor);                            
                            $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoNoSalarialEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoNoSalarialEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> array(),
                                "valor" => $valorInt ,
                                "tipoGen" => "conceptoFijo"
                            );
                        }                  
                    }
                    else if(strtotime($conceptoFijoNoSalarialEmpl->fechaFin)>=strtotime($fechaInicio)  
                    &&  strtotime($conceptoFijoNoSalarialEmpl->fechaFin)<=strtotime($fechaFin) 
                    &&  strtotime($conceptoFijoNoSalarialEmpl->fechaInicio)<=strtotime($fechaInicio))
                    {
                        
                        if(isset($arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto])){                            
                            $valorInt = floatval($conceptoFijoNoSalarialEmpl->valor);
                            $arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Nuevo concepto fijo no salarial: $".number_format($valorInt,0,",", ".")."");  

                            
                            if($conceptoFijoNoSalarialEmpl->fkNaturaleza=="6" && $valorInt > 0){
                                $valorInt=$valorInt*-1;
                                array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Naturaleza descuento no salarial se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }
                            array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Se suma con anterior(es): $".number_format( $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto]["valor"],0,",", ".")." + $".number_format($valorInt,0,",", "."));  
                            $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoNoSalarialEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoNoSalarialEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto]["arrNovedades"],
                                "valor" => $valorInt + $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto]["valor"],
                                "tipoGen" => "conceptoFijo"
                            );

                        }
                        else{
                            $valorInt = floatval($conceptoFijoNoSalarialEmpl->valor);     
                            $arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Nuevo concepto fijo no salarial: $".number_format($valorInt,0,",", "."));
                            if($conceptoFijoNoSalarialEmpl->fkNaturaleza=="6" && $valorInt > 0){
                                $valorInt=$valorInt*-1;
                                array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Naturaleza descuento no salarial se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }                       
                            $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoNoSalarialEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoNoSalarialEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto]["arrNovedades"],
                                "valor" => $valorInt,
                                "tipoGen" => "conceptoFijo"
                            );
                        }
                        
                    } 
                    else if(strtotime($conceptoFijoNoSalarialEmpl->fechaInicio)<=strtotime($fechaInicio)  
                    &&  strtotime($conceptoFijoNoSalarialEmpl->fechaFin)>=strtotime($fechaFin)) 
                    {
                        if(isset($arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto])){                            
                            $valorInt =  floatval($conceptoFijoNoSalarialEmpl->valor);
                            $arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Nuevo concepto fijo no salarial: $".number_format($valorInt,0,",", "."));
                            if($conceptoFijoNoSalarialEmpl->fkNaturaleza=="6" && $valorInt > 0){
                                $valorInt=$valorInt*-1;
                                array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Naturaleza descuento no salarial se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }
                            array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Se suma con anterior(es): $".number_format( $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto]["valor"],0,",", ".")." + $".number_format($valorInt,0,",", "."));  
                            $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoNoSalarialEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoNoSalarialEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto]["arrNovedades"],
                                "valor" => $valorInt + $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto]["valor"],
                                "tipoGen" => "conceptoFijo"
                            );

                        }
                        else{
                            $valorInt = floatval($conceptoFijoNoSalarialEmpl->valor);            
                            $arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Nuevo concepto fijo no salarial: $".number_format($valorInt,0,",", "."));
                            if($conceptoFijoNoSalarialEmpl->fkNaturaleza=="6" && $valorInt > 0){
                                $valorInt=$valorInt*-1;
                                array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Naturaleza descuento no salarial se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }                
                            $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoNoSalarialEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoNoSalarialEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> array(),
                                "valor" => $valorInt ,
                                "tipoGen" => "conceptoFijo"
                            );
                        }
                        
                    }
                    else if(strtotime($fechaInicio)<=strtotime($conceptoFijoNoSalarialEmpl->fechaInicio)  
                    &&  strtotime($fechaFin)>=strtotime($conceptoFijoNoSalarialEmpl->fechaFin)) 
                    {
                        if(isset($arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto])){                            
                            $valorInt = floatval($conceptoFijoNoSalarialEmpl->valor);
                            $arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Nuevo concepto fijo no salarial: $".number_format($valorInt,0,",", "."));
                            if($conceptoFijoNoSalarialEmpl->fkNaturaleza=="6" && $valorInt > 0){
                                $valorInt=$valorInt*-1;
                                array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Naturaleza descuento no salarial se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }
                            array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Se suma con anterior(es): $".number_format( $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto]["valor"],0,",", ".")." + $".number_format($valorInt,0,",", "."));  
                            $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoNoSalarialEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoNoSalarialEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto]["arrNovedades"],
                                "valor" => $valorInt  + $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto]["valor"],
                                "tipoGen" => "conceptoFijo"
                            );

                        }
                        else{
                            $valorInt = floatval($conceptoFijoNoSalarialEmpl->valor);           
                            $arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Nuevo concepto fijo no salarial: $".number_format($valorInt,0,",", "."));
                            if($conceptoFijoNoSalarialEmpl->fkNaturaleza=="6" && $valorInt > 0){
                                $valorInt=$valorInt*-1;
                                array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Naturaleza descuento no salarial se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }                 
                            $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoNoSalarialEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoNoSalarialEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> array(),
                                "valor" => $valorInt,
                                "tipoGen" => "conceptoFijo"
                            );
                        }
                    }
                }
                else{
                    if(strtotime($fechaInicio)<=strtotime($conceptoFijoNoSalarialEmpl->fechaInicio) &&
                    strtotime($fechaFin)>=strtotime($conceptoFijoNoSalarialEmpl->fechaInicio))
                    {
                        
                        if(isset($arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto])){           
                                            
                            $valorInt = floatval($conceptoFijoNoSalarialEmpl->valor);
                            $arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Nuevo concepto fijo no salarial: $".number_format($valorInt,0,",", "."));
                            if($conceptoFijoNoSalarialEmpl->fkNaturaleza=="6" && $valorInt > 0){
                                $valorInt=$valorInt*-1;
                                array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Naturaleza descuento no salarial se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }
                            array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Se suma con anterior(es): $".number_format( $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto]["valor"],0,",", ".")." + $".number_format($valorInt,0,",", "."));  
                            $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoNoSalarialEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoNoSalarialEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto]["arrNovedades"],
                                "valor" => $valorInt + $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto]["valor"],
                                "tipoGen" => "conceptoFijo"
                            );
                    

                        }
                        else{
                            $valorInt = floatval($conceptoFijoNoSalarialEmpl->valor);       
                            $arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Nuevo concepto fijo no salarial: $".number_format($valorInt,0,",", "."));
                            if($conceptoFijoNoSalarialEmpl->fkNaturaleza=="6" && $valorInt > 0){
                                $valorInt=$valorInt*-1;
                                array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Naturaleza descuento no salarial se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }  
                            
                                            
                            $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoNoSalarialEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoNoSalarialEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> array(),
                                "valor" => $valorInt,
                                "tipoGen" => "conceptoFijo"
                            );
                        

                        }
                    }
                    else if(strtotime($conceptoFijoNoSalarialEmpl->fechaInicio)<=strtotime($fechaInicio))
                    {
                        if(isset($arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto])){                            
                            $valorInt = floatval($conceptoFijoNoSalarialEmpl->valor);
                            $arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Nuevo concepto fijo no salarial: $".number_format($valorInt,0,",", "."));
                            if($conceptoFijoNoSalarialEmpl->fkNaturaleza=="6" && $valorInt > 0){
                                $valorInt=$valorInt*-1;
                                array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Naturaleza descuento no salarial se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }
                            array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Se suma con anterior(es): $".number_format( $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto]["valor"],0,",", ".")." + $".number_format($valorInt,0,",", "."));  
                            $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoNoSalarialEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoNoSalarialEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto]["arrNovedades"],
                                "valor" => $valorInt + $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto]["valor"],
                                "tipoGen" => "conceptoFijo"
                            );
                        
                        }
                        else{
                            $valorInt = floatval($conceptoFijoNoSalarialEmpl->valor);       
                            $arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Nuevo concepto fijo no salarial: $".number_format($valorInt,0,",", "."));
                            if($conceptoFijoNoSalarialEmpl->fkNaturaleza=="6" && $valorInt > 0){
                                $valorInt=$valorInt*-1;
                                array_push($arrComoCalcula[$conceptoFijoNoSalarialEmpl->fkConcepto], "Naturaleza descuento no salarial se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }               

                            $arrValorxConceptoFueraNomina[$conceptoFijoNoSalarialEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoNoSalarialEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoNoSalarialEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> array(),
                                "valor" => $valorInt,
                                "tipoGen" => "conceptoFijo"
                            );
                        }     
                    }
                }
            }

            $conceptosFijosEmpl = DB::table("conceptofijo", "cf")
            ->select(["cf.valor","cf.fechaInicio","cf.fechaFin", "cf.fkConcepto","cf.unidad", "c.*"])
            ->join("conceptosxtipoliquidacion AS ctl", "ctl.fkConcepto","=","cf.fkConcepto")
            ->join("concepto AS c", "ctl.fkConcepto","=","c.idconcepto")
            ->where("ctl.fkTipoLiquidacion","=",$tipoliquidacion)     
            ->where("cf.fkEmpleado", "=", $empleado->idempleado)  
            ->where("cf.fkPeriodoActivo","=", $periodoActivoReintegro->idPeriodo)
            ->where("cf.fkEstado", "=", "1")
            ->whereIn("c.fkNaturaleza",["1","3"])//Naturaleza: PAGO O DESCUENTO
            ->distinct()
            ->get();
            //Agregar conceptos fijos a la liquidacion actual
            foreach($conceptosFijosEmpl as $conceptoFijoEmpl){
                if(isset($conceptoFijoEmpl->fechaFin)){
                    if(strtotime($conceptoFijoEmpl->fechaInicio)>=strtotime($fechaInicio)  
                        &&  strtotime($conceptoFijoEmpl->fechaInicio)<=strtotime($fechaFin) 
                        &&  strtotime($conceptoFijoEmpl->fechaFin)>=strtotime($fechaFin))
                    {
                        
                        if(isset($arrValorxConcepto[$conceptoFijoEmpl->fkConcepto])){
                            $valorInt = floatval($conceptoFijoEmpl->valor);
                            
                            $arrComoCalcula[$conceptoFijoEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Nuevo concepto fijo: $".number_format($valorInt,0,",", ".")."");  

                            if($conceptoFijoEmpl->fkNaturaleza=="3" && $valorInt > 0){
                                $valorInt=$valorInt*-1;
                                array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Naturaleza descuento se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }
                            array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Se suma con anterior(es): $".number_format( $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto]["valor"],0,",", ".")." + $".number_format($valorInt,0,",", "."));  

                            $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> $arrValorxConcepto[$conceptoFijoNoSalarialEmpl->fkConcepto]["arrNovedades"],
                                "valor" => $valorInt + $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto]["valor"],
                                "tipoGen" => "conceptoFijo"
                            );
                        }
                        else{
                            
                            $valorInt = floatval($conceptoFijoEmpl->valor);     
                            $arrComoCalcula[$conceptoFijoEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Nuevo concepto fijo: $".number_format($valorInt,0,",", ".")."");  

                            if($conceptoFijoEmpl->fkNaturaleza=="3" && $valorInt > 0){
                                $valorInt=$valorInt*-1;
                                array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Naturaleza descuento se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }
                            
                            $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> array(),
                                "valor" => $valorInt ,
                                "tipoGen" => "conceptoFijo"
                            );
                        }                  
                    }
                    else if(strtotime($conceptoFijoEmpl->fechaFin)>=strtotime($fechaInicio)  
                    &&  strtotime($conceptoFijoEmpl->fechaFin)<=strtotime($fechaFin) 
                    &&  strtotime($conceptoFijoEmpl->fechaInicio)<=strtotime($fechaInicio))
                    {
                        
                        if(isset($arrValorxConcepto[$conceptoFijoEmpl->fkConcepto])){                            
                            $valorInt = floatval($conceptoFijoEmpl->valor);
                            $arrComoCalcula[$conceptoFijoEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Nuevo concepto fijo: $".number_format($valorInt,0,",", ".")."");  

                            if($conceptoFijoEmpl->fkNaturaleza=="3" && $valorInt > 0){
                                $valorInt=$valorInt*-1;
                                array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Naturaleza descuento se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }
                            array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Se suma con anterior(es): $".number_format( $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto]["valor"],0,",", ".")." + $".number_format($valorInt,0,",", "."));  

                            
                            $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> $arrValorxConcepto[$conceptoFijoNoSalarialEmpl->fkConcepto]["arrNovedades"],
                                "valor" => $valorInt + $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto]["valor"],
                                "tipoGen" => "conceptoFijo"
                            );

                        }
                        else{
                            $valorInt = floatval($conceptoFijoEmpl->valor);     
                            $arrComoCalcula[$conceptoFijoEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Nuevo concepto fijo: $".number_format($valorInt,0,",", ".")."");  

                            if($conceptoFijoEmpl->fkNaturaleza=="3" && $valorInt > 0){
                                $valorInt=$valorInt*-1;
                                array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Naturaleza descuento se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }                    
                            $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> array(),
                                "valor" => $valorInt,
                                "tipoGen" => "conceptoFijo"
                            );
                        }
                        
                    } 
                    else if(strtotime($conceptoFijoEmpl->fechaInicio)<=strtotime($fechaInicio)  
                    &&  strtotime($conceptoFijoEmpl->fechaFin)>=strtotime($fechaFin)) 
                    {
                        
                        if(isset($arrValorxConcepto[$conceptoFijoEmpl->fkConcepto])){                            
                            $valorInt =  floatval($conceptoFijoEmpl->valor);
                            $arrComoCalcula[$conceptoFijoEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Nuevo concepto fijo: $".number_format($valorInt,0,",", ".")."");  

                            if($conceptoFijoEmpl->fkNaturaleza=="3" && $valorInt > 0){
                                $valorInt=$valorInt*-1;
                                array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Naturaleza descuento se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }
                            array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Se suma con anterior(es): $".number_format( $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto]["valor"],0,",", ".")." + $".number_format($valorInt,0,",", "."));  

                            $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> $arrValorxConcepto[$conceptoFijoNoSalarialEmpl->fkConcepto]["arrNovedades"],
                                "valor" => $valorInt + $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto]["valor"],
                                "tipoGen" => "conceptoFijo"
                            );

                        }
                        else{
                            $valorInt = floatval($conceptoFijoEmpl->valor);            
                            $arrComoCalcula[$conceptoFijoEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Nuevo concepto fijo: $".number_format($valorInt,0,",", ".")."");  

                            if($conceptoFijoEmpl->fkNaturaleza=="3" && $valorInt > 0){
                                $valorInt=$valorInt*-1;
                                array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Naturaleza descuento se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }                    
                            $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> array(),
                                "valor" => $valorInt ,
                                "tipoGen" => "conceptoFijo"
                            );
                        }
                        
                    }
                    else if(strtotime($fechaInicio)<=strtotime($conceptoFijoEmpl->fechaInicio)  
                    &&  strtotime($fechaFin)>=strtotime($conceptoFijoEmpl->fechaFin)) 
                    {
                        
                        if(isset($arrValorxConcepto[$conceptoFijoEmpl->fkConcepto])){                            
                            $valorInt = floatval($conceptoFijoEmpl->valor);
                            $arrComoCalcula[$conceptoFijoEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Nuevo concepto fijo: $".number_format($valorInt,0,",", ".")."");  

                            if($conceptoFijoEmpl->fkNaturaleza=="3" && $valorInt > 0){
                                $valorInt=$valorInt*-1;
                                array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Naturaleza descuento se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }
                            array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Se suma con anterior(es): $".number_format( $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto]["valor"],0,",", ".")." + $".number_format($valorInt,0,",", "."));  

                            $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> $arrValorxConcepto[$conceptoFijoNoSalarialEmpl->fkConcepto]["arrNovedades"],
                                "valor" => $valorInt  + $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto]["valor"],
                                "tipoGen" => "conceptoFijo"
                            );

                        }
                        else{
                            $valorInt = floatval($conceptoFijoEmpl->valor);            
                            $arrComoCalcula[$conceptoFijoEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Nuevo concepto fijo: $".number_format($valorInt,0,",", ".")."");  

                            if($conceptoFijoEmpl->fkNaturaleza=="3" && $valorInt > 0){
                                $valorInt=$valorInt*-1;
                                array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Naturaleza descuento se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }                 
                            $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> array(),
                                "valor" => $valorInt,
                                "tipoGen" => "conceptoFijo"
                            );
                        }
                    }
                }
                else{
                    
                    
                    if(strtotime($fechaInicio)<=strtotime($conceptoFijoEmpl->fechaInicio) &&
                    strtotime($fechaFin)>=strtotime($conceptoFijoEmpl->fechaInicio))
                    {
                        
                        if(isset($arrValorxConcepto[$conceptoFijoEmpl->fkConcepto])){           
                                            
                            $valorInt = floatval($conceptoFijoEmpl->valor);
                            $arrComoCalcula[$conceptoFijoEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Nuevo concepto fijo: $".number_format($valorInt,0,",", ".")."");  

                            if($conceptoFijoEmpl->fkNaturaleza=="3" && $valorInt > 0){
                                $valorInt=$valorInt*-1;
                                array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Naturaleza descuento se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }
                            array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Se suma con anterior(es): $".number_format( $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto]["valor"],0,",", ".")." + $".number_format($valorInt,0,",", "."));  


                            $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> $arrValorxConcepto[$conceptoFijoNoSalarialEmpl->fkConcepto]["arrNovedades"],
                                "valor" => $valorInt + $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto]["valor"],
                                "tipoGen" => "conceptoFijo",
                                "fechaInicioConcepto" => $conceptoFijoEmpl->fechaInicio                            
                            );
                    

                        }
                        else{
                            $valorInt = floatval($conceptoFijoEmpl->valor);            
                            $arrComoCalcula[$conceptoFijoEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Nuevo concepto fijo: $".number_format($valorInt,0,",", ".")."");  

                            if($conceptoFijoEmpl->fkNaturaleza=="3" && $valorInt > 0){
                                $valorInt=$valorInt*-1;
                                array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Naturaleza descuento se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }  
                            
                            $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> array(),
                                "valor" => $valorInt,
                                "tipoGen" => "conceptoFijo",
                                "fechaInicioConcepto" => $conceptoFijoEmpl->fechaInicio
                            );
                        

                        }
                    }
                    else if(strtotime($conceptoFijoEmpl->fechaInicio)<=strtotime($fechaInicio))
                    {
                        if(isset($arrValorxConcepto[$conceptoFijoEmpl->fkConcepto])){                            
                            $valorInt = floatval($conceptoFijoEmpl->valor);
                            $arrComoCalcula[$conceptoFijoEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Nuevo concepto fijo: $".number_format($valorInt,0,",", ".")."");  

                            if($conceptoFijoEmpl->fkNaturaleza=="3" && $valorInt > 0){
                                $valorInt=$valorInt*-1;
                                array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Naturaleza descuento se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }
                            array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Se suma con anterior(es): $".number_format( $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto]["valor"],0,",", ".")." + $".number_format($valorInt,0,",", "."));  


                            $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> $arrValorxConcepto[$conceptoFijoNoSalarialEmpl->fkConcepto]["arrNovedades"],
                                "valor" => $valorInt + $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto]["valor"],
                                "tipoGen" => "conceptoFijo",
                                "fechaInicioConcepto" => $conceptoFijoEmpl->fechaInicio
                            );
                        
                        }
                        else{
                            $valorInt = floatval($conceptoFijoEmpl->valor);            
                            $arrComoCalcula[$conceptoFijoEmpl->fkConcepto] = ($arrComoCalcula[$conceptoFijoEmpl->fkConcepto] ?? array());
                            array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Nuevo concepto fijo: $".number_format($valorInt,0,",", ".")."");  

                            if($conceptoFijoEmpl->fkNaturaleza=="3" && $valorInt > 0){
                                $valorInt=$valorInt*-1;
                                array_push($arrComoCalcula[$conceptoFijoEmpl->fkConcepto], "Naturaleza descuento se multiplica por -1: $".number_format($valorInt,0,",", "."));  
                            }                

                            $arrValorxConcepto[$conceptoFijoEmpl->fkConcepto] = array(
                                "naturaleza" => $conceptoFijoEmpl->fkNaturaleza,
                                "unidad" => $conceptoFijoEmpl->unidad,
                                "cantidad"=> 0,
                                "arrNovedades"=> array(),
                                "valor" => $valorInt,
                                "tipoGen" => "conceptoFijo",
                                "fechaInicioConcepto" => $conceptoFijoEmpl->fechaInicio
                            );
                        }     
                    }
                }
            }
            
            //FIN CONCEPTOS FIJOS EMPLEADO

            
            $sqlWhere = "( 
                ('".$fechaInicio."' BETWEEN v.fechaInicio AND v.fechaFin) OR
                ('".$fechaFin."' BETWEEN v.fechaInicio AND v.fechaFin) OR
                (v.fechaInicio BETWEEN '".$fechaInicio."' AND '".$fechaFin."') OR
                (v.fechaFin BETWEEN '".$fechaInicio."' AND '".$fechaFin."')
            )";
            
            $cambioTipoCotizante = DB::table("cambiotipocotizante", "ctc")
            ->join("concepto as c","c.idconcepto", "=","ctc.fkConceptoAnt")
            ->whereBetween("ctc.fechaCambio",[$fechaInicio,$fechaFin])
            ->where("ctc.fkEmpleado","=",$empleado->idempleado)
            ->where("ctc.fkPeriodoActivo","=", $periodoActivoReintegro->idPeriodo)
            ->where("ctc.fkEstado","=","7")
            ->first();
            if(isset($cambioTipoCotizante)){
                $valorInt = $cambioTipoCotizante->valorNovedad;

                $arrComoCalcula[$cambioTipoCotizante->fkConceptoAnt] = ($arrComoCalcula[$cambioTipoCotizante->fkConceptoAnt] ?? array());
                array_push($arrComoCalcula[$cambioTipoCotizante->fkConceptoAnt], "Nuevo concepto por cambio de tipo cotizante: $".number_format($valorInt,0,",", ".")."");  

                $cantidadInt = floatval($cambioTipoCotizante->dias);
                $arrCambio=array([
                    "idCambioTipoCotizante" => $cambioTipoCotizante->idCambioTipoCotizante,
                    "valor" => $valorInt
                ]);
                $arrValorxConcepto[$cambioTipoCotizante->fkConceptoAnt] = array(
                    "naturaleza" => $cambioTipoCotizante->fkNaturaleza,
                    "unidad"=>"DÍA",
                    "cantidad"=> $cantidadInt,
                    "arrNovedades"=> array(),
                    "arrCambio" =>  $arrCambio,
                    "valor" => $valorInt,
                    "tipoGen" => "novedad"
                );
            }
            
            //INICIO CALCULAR PERIODO
            //Calcular el # de dias trabajados desde la fecha de ingreso
            $fechaInicio = $liquidacionNomina->fechaInicio;
            $dias = 0;
            $tieneFechaIngresoPosterior = false;
            $fechaFinCalc = $fechaFin;
            if(strtotime($fechaInicio) < strtotime($empleado->fechaIngreso)){
                if(substr($fechaFin, 8, 2) == "31" || (substr($fechaFin, 8, 2) == "28" && substr($fechaFin, 5, 2) == "02") || (substr($fechaFin, 8, 2) == "29" && substr($fechaFin, 5, 2) == "02")  ){
                    $fechaFinCalc = substr($fechaFin,0,8)."30";
                }
                $dias = $this->days_360($empleado->fechaIngreso, $fechaFinCalc) + 1;
                $tieneFechaIngresoPosterior = true;
            }

            $tieneFechaIngresoAnterior = false;
            $fechaInicioMesParaAnterior = date("Y-m-01", strtotime($fechaInicio));


            if(strtotime($fechaInicio) > strtotime($empleado->fechaIngreso) && strtotime($fechaInicioMesParaAnterior) < strtotime($empleado->fechaIngreso)){
                //Verificar si hay liquidaciones anteriores
                $liquidacionAnteriores = DB::table("liquidacionnomina", "ln")
                ->selectRaw("min(ln.fechaInicio) as primeraFecha")
                ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")     
                ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])              
                ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->first();
                
                if(!isset($liquidacionAnteriores->primeraFecha)){
                    $fechaInicio = $empleado->fechaIngreso;
                    if(substr($fechaFin, 8, 2) == "31" || (substr($fechaFin, 8, 2) == "28" && substr($fechaFin, 5, 2) == "02") || (substr($fechaFin, 8, 2) == "29" && substr($fechaFin, 5, 2) == "02")  ){
                        $fechaFinCalc = substr($fechaFin,0,8)."30";
                    }
                    $dias = $this->days_360($empleado->fechaIngreso, $fechaFinCalc) + 1;
                    $tieneFechaIngresoAnterior = true;
                }
                
            }
            


            $periodoPago = $periodo;
          
            $diasEnMismoPeriodo = 0;
            if($dias>0){
                $periodoPago = $dias;                
            }       

            if(isset($numeroDias)){
                $periodoPago = $numeroDias;
            }
            
            if($tipoliquidacion == "12"){
                //dd($periodo, $periodoPago, $dias);
                $novedadesVacacionParaTipo12 = DB::table("novedad","n")
                ->join("vacaciones as v","v.idVacaciones","=","n.fkVacaciones")
                ->where("n.fkEmpleado","=",$empleado->idempleado)
                ->where("n.fkPeriodoActivo","=", $periodoActivoReintegro->idPeriodo)
                ->whereRaw($sqlWhere)
                ->whereIn("n.fkEstado",["7", "8","16"]) // Pagada o sin pagar-> no que este eliminada
                ->whereNotNull("n.fkVacaciones")
                ->where("fkConcepto","=","29")
                ->orderBy("v.fechaFin","desc")
                ->first();

                if(isset($novedadesVacacionParaTipo12->fechaFin)){
                    $fechaFinCalc = $fechaFin;
                    if(substr($fechaFin, 8, 2) == "31" || (substr($fechaFin, 8, 2) == "28" && substr($fechaFin, 5, 2) == "02") || (substr($fechaFin, 8, 2) == "29" && substr($fechaFin, 5, 2) == "02")  ){
                        $fechaFinCalc = substr($fechaFin,0,8)."30";
                    }

                    if(strtotime( $novedadesVacacionParaTipo12->fechaFin) > strtotime($fechaFinCalc)){
                        $novedadesVacacionParaTipo12->fechaFin = $fechaFinCalc;
                    }
                    if($tieneFechaIngresoPosterior){
                        $dias = $this->days_360($empleado->fechaIngreso, $novedadesVacacionParaTipo12->fechaFin) + 1;
                    }
                    else{
                        $dias = $this->days_360($fechaInicio, $novedadesVacacionParaTipo12->fechaFin) + 1;
                    }
                    $periodoPago = $dias;
                }

                
            }
           

            $novedadesRetiro = DB::table("novedad","n")
            ->select("r.fecha","r.fechaReal")
            ->join("retiro AS r", "r.idRetiro","=","n.fkRetiro")
            ->where("n.fkEmpleado", "=", $empleado->idempleado)
            ->where("n.fkPeriodoActivo","=", $periodoActivoReintegro->idPeriodo)
            ->where("n.fkEstado","=","7")
            ->whereNotNull("n.fkRetiro")
            ->whereBetween("n.fechaRegistro",[$fechaInicio, $fechaFin])->first();

            if(isset($novedadesRetiro) && $empleado->fkTipoCotizante!=51){
                $fechaFinCalc = $fechaFin;
                if(substr($fechaFin, 8, 2) == "31" || (substr($fechaFin, 8, 2) == "28" && substr($fechaFin, 5, 2) == "02") || (substr($fechaFin, 8, 2) == "29" && substr($fechaFin, 5, 2) == "02")  ){
                    $fechaFinCalc = substr($fechaFin,0,8)."30";
                }

                if(substr($novedadesRetiro->fecha, 8, 2) == "31" || (substr($novedadesRetiro->fecha, 8, 2) == "28" && substr($novedadesRetiro->fecha, 5, 2) == "02") || (substr($novedadesRetiro->fecha, 8, 2) == "29"  && substr($novedadesRetiro->fecha, 5, 2) == "02") ){
                    $novedadesRetiro->fecha = substr($novedadesRetiro->fecha,0,8)."30";
                }
                
                if(substr($novedadesRetiro->fechaReal, 8, 2) == "31" || (substr($novedadesRetiro->fechaReal, 8, 2) == "28" && substr($novedadesRetiro->fechaReal, 5, 2) == "02") || (substr($novedadesRetiro->fechaReal, 8, 2) == "29"  && substr($novedadesRetiro->fechaReal, 5, 2) == "02") ){
                    $novedadesRetiro->fechaReal = substr($novedadesRetiro->fechaReal,0,8)."30";
                }
                
                $dias = $this->days_360($novedadesRetiro->fecha, $fechaFinCalc);
                
                if($tieneFechaIngresoPosterior || $tieneFechaIngresoAnterior){
                    $dias++;
                }
                
                if($dias < 30){                
                    if($empleado->fechaIngreso != $novedadesRetiro->fecha){
                        
                        if($tieneFechaIngresoPosterior || $tieneFechaIngresoAnterior){
                            $dias = $this->days_360($empleado->fechaIngreso, $novedadesRetiro->fecha);
                            $dias++;
                            $periodoPago = $dias;
                        }
                        else{                            
                            $periodoPago = $periodoPago - (floor($dias));
                        }
                    }
                    else{
                        
                        if($tieneFechaIngresoPosterior || $tieneFechaIngresoAnterior){
                            $dias--;
                        }
                        $periodoPago = $periodoPago - (floor($dias));
                    }
                }
                
               

                if($novedadesRetiro->fecha != $novedadesRetiro->fechaReal){
                    if(substr($novedadesRetiro->fechaReal, 5, 2) <= substr($novedadesRetiro->fecha, 5, 2) ){
                        $fechaFinMesAnteriorParaPeriodo = date("Y-m-t",strtotime($fechaFinCalc." -30 days"));
                        if(substr($fechaFinMesAnteriorParaPeriodo, 8, 2) == "31" || (substr($fechaFinMesAnteriorParaPeriodo, 8, 2) == "28" || substr($fechaFinMesAnteriorParaPeriodo, 8, 2) == "29") && substr($fechaFinMesAnteriorParaPeriodo, 5, 2) == "02")
                        {
                            $fechaFinMesAnteriorParaPeriodo = substr($fechaFinMesAnteriorParaPeriodo,0,8)."30";
                        }
                        if(date("Y-m-d",strtotime($novedadesRetiro->fechaReal)) ==  $fechaFinMesAnteriorParaPeriodo){
                            $periodoPago = -1*($this->days_360($novedadesRetiro->fechaReal, $fechaFinMesAnteriorParaPeriodo) + 1);
                        }
                        else{
                            $periodoPago = -1*($this->days_360($novedadesRetiro->fechaReal, $fechaFinMesAnteriorParaPeriodo));
                        }
                        
                        
                    }
                    else if(substr($novedadesRetiro->fechaReal, 5, 2) == 12 && substr($novedadesRetiro->fecha, 5, 2) == 1){
                        //$periodoPago = 0;
                        $fechaFinMesAnteriorParaPeriodo = date("Y-m-t",strtotime($fechaFinCalc." -30 days"));
                        $periodoPago = -1*($this->days_360($novedadesRetiro->fechaReal, $fechaFinMesAnteriorParaPeriodo) + 1);
                    }
                }
                //dd($periodoPago);
                        
            }
            //VERIFICAR SI EXISTE OTRA NOMINA NORMAL QUE SE LE PAGO EN EL PERIODO ACTUAL
            $itemsBoucherMismoPeriodoNomin = DB::table("item_boucher_pago", "ibp")
            ->selectRaw("ibp.*")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("grupoconcepto_concepto as gcc", "gcc.fkConcepto","=","ibp.fkConcepto")
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=", $periodoActivoReintegro->idPeriodo)
            ->where("ln.fechaInicio","=",$fechaInicio)
            ->where("ln.fechaFin","=",$fechaFin)
            ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
            ->where("ln.fkEstado","=","5")
            ->whereNotIn("ln.fkTipoLiquidacion",["7","10","11"]) //Puede tenerlas en el mismo periodo
            ->where("gcc.fkGrupoConcepto","=","48")
            ->get();
            
            foreach($itemsBoucherMismoPeriodoNomin as $itemBoucherMismoPeriodoNomin){
                $diasEnMismoPeriodo =  $diasEnMismoPeriodo + $itemBoucherMismoPeriodoNomin->cantidad;
            }   
            //dd($diasEnMismoPeriodo);
            $periodoPagoSinVac = $periodoPago;
            
            $sqlWhere = "( 
                ('".$fechaInicio."' BETWEEN v.fechaInicio AND v.fechaFin) OR
                ('".$fechaFin."' BETWEEN v.fechaInicio AND v.fechaFin) OR
                (v.fechaInicio BETWEEN '".$fechaInicio."' AND '".$fechaFin."') OR
                (v.fechaFin BETWEEN '".$fechaInicio."' AND '".$fechaFin."')
            )";
            
            $novedadesVacacion = DB::table("novedad","n")
            ->join("vacaciones as v","v.idVacaciones","=","n.fkVacaciones")
            ->where("n.fkEmpleado","=",$empleado->idempleado)
            ->where("n.fkPeriodoActivo","=", $periodoActivoReintegro->idPeriodo)
            ->whereRaw($sqlWhere)
            ->whereIn("n.fkEstado",["7", "8","16"]) // Pagada o sin pagar-> no que este eliminada
            ->whereNotNull("n.fkVacaciones")
            ->where("fkConcepto","=","29")
            ->get();
            if($empleado->fkTipoCotizante!=51){
                //$diasVac = $totalPeriodoPagoAnioActual * 15 / 360;
                foreach($novedadesVacacion as $novedadVacacion){

                    
                    if(strtotime($novedadVacacion->fechaInicio)>=strtotime($fechaInicio)
                        &&  strtotime($novedadVacacion->fechaInicio)<=strtotime($fechaFin) 
                        &&  strtotime($novedadVacacion->fechaFin)>=strtotime($fechaFin))
                    {
                        
                        $diaI = strtotime($novedadVacacion->fechaInicio);
                        $diaF = strtotime($fechaFin);
                        $diasCompensar = $this->days_360($novedadVacacion->fechaInicio, $fechaFin) + 1;
                        if(substr($novedadVacacion->fechaInicio, 8, 2) == "31" && substr($fechaFin, 8, 2) == "31"){
                            $diasCompensar--;   
                        }
                        if( substr($fechaFin, 8, 2) == "31"){
                            $diasCompensar--;  
                        }
                    }
                    else if(strtotime($novedadVacacion->fechaFin)>=strtotime($fechaInicio)  
                    &&  strtotime($novedadVacacion->fechaFin)<=strtotime($fechaFin) 
                    &&  strtotime($novedadVacacion->fechaInicio)<=strtotime($fechaInicio))
                    {
                        
                        $diaI = strtotime( $fechaInicio );
                        $diaF = strtotime( $novedadVacacion->fechaFin );

                        $diasCompensar = $this->days_360($fechaInicio, $novedadVacacion->fechaFin) + 1;
                        
                        if(substr($fechaInicio, 8, 2) == "31" && substr($novedadVacacion->fechaFin, 8, 2) == "31"){
                            $diasCompensar--;   
                        }
                        if( substr($novedadVacacion->fechaFin, 8, 2) == "31"){
                            $diasCompensar--;  
                        }
                        
                    }
                    else if(strtotime($novedadVacacion->fechaInicio)<=strtotime($fechaInicio)  
                    &&  strtotime($novedadVacacion->fechaFin)>=strtotime($fechaFin)) 
                    {
                        
                        $diaI = strtotime( $fechaInicio );
                        $diaF = strtotime( $fechaFin );
                        $diasCompensar = $this->days_360($fechaInicio, $fechaFin) + 1;
                        if(substr($fechaInicio, 8, 2) == "31" && substr($fechaFin, 8, 2) == "31"){
                            $diasCompensar--;   
                        }
                        if( substr($fechaFin, 8, 2) == "31"){
                            $diasCompensar--;  
                        }
                    }
                    else if(strtotime($fechaInicio)<=strtotime($novedadVacacion->fechaInicio)  
                    &&  strtotime($fechaFin)>=strtotime($novedadVacacion->fechaFin)) 
                    {
                        
                        $diaI = strtotime($novedadVacacion->fechaInicio);
                        $diaF = strtotime($novedadVacacion->fechaFin);
                        $diasCompensar = $this->days_360($novedadVacacion->fechaInicio, $novedadVacacion->fechaFin) + 1;
                        

                        if(substr($novedadVacacion->fechaInicio, 8, 2) == "31" && substr($novedadVacacion->fechaFin, 8, 2) == "31"){
                            $diasCompensar--;   
                        }
                        if( substr($novedadVacacion->fechaFin, 8, 2) == "31"){
                            $diasCompensar--;  
                        }
                    }
                    

                    $periodoPago =  $periodoPago - $diasCompensar;
                    
                }        
            }
            //dd($periodoPago, $diasNoTrabajados, $diasNoTrabajadosInjustificados, $diasEnMismoPeriodo);
            $periodoGen = $periodoPago - $diasNoTrabajados - $diasNoTrabajadosInjustificados - $diasEnMismoPeriodo;
            
            
            //FIN CALCULAR PERIODO
            
            $valorNeto = 0;
            
            //INICIO VACACIONES PAGO TOTAL
            $novedades = DB::table("novedad","n")
            ->join("conceptosxtipoliquidacion AS ctl", "ctl.fkConcepto","=","n.fkConcepto")
            ->join("concepto AS c", "ctl.fkConcepto","=","c.idconcepto")
            ->where("ctl.fkTipoLiquidacion","=",$tipoliquidacion)
            ->where("n.fkEmpleado", "=", $empleado->idempleado)
            ->where("n.fkPeriodoActivo","=", $periodoActivoReintegro->idPeriodo)
            ->where("n.fkEstado","=","7")
            ->whereBetween("n.fechaRegistro",[$fechaInicio, $fechaFin])
            ->distinct()
            ->get();
    
            foreach($novedades as $novedadyconcepto){
                if(isset($novedadyconcepto->fkVacaciones)){
                    $vacacionesPTotal = DB::table("vacaciones")
                        ->where("idVacaciones", "=", $novedadyconcepto->fkVacaciones)
                        ->where("pagoAnticipado", "=", "1")
                        ->first();
                    
                    if(isset($vacacionesPTotal->diasCompensar)){
                            $arrComoCalcula[$novedadyconcepto->idconcepto] = ($arrComoCalcula[$novedadyconcepto->idconcepto] ?? array());
                            

                            $valorFormula = $this->calcularValoresxConceptoxEmpleado($novedadyconcepto->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo);
                            
                            if($valorFormula < $salarioMinimoDia){
                                $valorFormula = $salarioMinimoDia;
                            }
                            
                            if(isset($arrValorxConcepto[$novedadyconcepto->idconcepto])){                        
                                $salarialVac = 0;
                                $fechaInicioMes = date("Y-m-01", strtotime($fechaInicio));
                                $grupoConceptoCalculoVac = DB::table("grupoconcepto_concepto","gcc")
                                    ->where("gcc.fkGrupoConcepto", "=", "13")//Salarial para provisiones
                                    ->get();
                                
                                foreach($grupoConceptoCalculoVac as $grupoConcepto){
                                    if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto != 36){
                                        $salarialVac = $salarialVac + floatval($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor']);
                                        array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Se suma el valor de para base salarial $".number_format($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor'],0,",", "."));
                                    }
                                    if(isset($arrValorxConceptoOtros[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto != 36){
                                        $salarialVac = $salarialVac + floatval($arrValorxConceptoOtros[$grupoConcepto->fkConcepto]['valor']);
                                        array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Se suma el valor de para base salarial $".number_format($arrValorxConceptoOtros[$grupoConcepto->fkConcepto]['valor'],0,",", "."));
                                    }
                                    
                                    
                                }
                                
                                array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Calculó valor suma liquidacion mes actual $".number_format($salarialVac,0,",", "."));


                                $itemsBoucherSalarialMesAnteriorVac = DB::table("item_boucher_pago", "ibp")
                                ->selectRaw("Sum(ibp.valor) as suma")
                                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                ->where("ln.fechaInicio","=",$fechaInicioMes)
                                ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                                ->where("gcc.fkGrupoConcepto","=","13") //13 - Salarial vacaciones
                                ->first();
                                if(isset($itemsBoucherSalarialMesAnteriorVac)){
                                    
                                    array_push($arrComoCalcula[$novedadyconcepto->idconcepto], 
                                    "Se suma al valor salarial la liquidacion mismo mes $".number_format($itemsBoucherSalarialMesAnteriorVac->suma,0,",", "."));

                                    $salarialVac = $salarialVac + $itemsBoucherSalarialMesAnteriorVac->suma;

                                }
                                
                                

                                if(isset($novedadesRetiro)){
                                    if(strtotime($fechaFin) > strtotime($novedadesRetiro->fechaReal)){
                                        $periodoPagoVac = $this->days_360($empleado->fechaIngreso,$novedadesRetiro->fechaReal) + 1 ;
                                    }
                                }
                                else{
                                    $periodoPagoVac = $this->days_360($empleado->fechaIngreso,$fechaFin) + 1 ;
                                }
    
                                $anioActual = intval(date("Y",strtotime($fechaInicio)));
                                $mesActual = intval(date("m",strtotime($fechaInicio)));


                                $liquidacionesMesesAnterioresCompleta = DB::table("liquidacionnomina", "ln")
                                ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.salarioPeriodoPago) as salarioPago")
                                ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                ->where("ln.fechaInicio","<=",$fechaInicioMes)
                                ->where("ln.fechaInicio",">=",date("Y-m-d",strtotime($fechaInicioMes." -1 year")))
                                ->where("ln.idLiquidacionNomina", "<>", $idLiquidacionNomina)
                                ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])         
                                ->first();

                                //Obtener la primera liquidacion de nomina de la persona 
                                $primeraLiquidacion = DB::table("liquidacionnomina", "ln")
                                ->selectRaw("min(ln.fechaInicio) as primeraFecha")
                                ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")     
                                ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])              
                                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                ->first();

                                $minimaFecha = date("Y-m-d");
                                
                                if(isset($primeraLiquidacion)){
                                    $minimaFecha = $primeraLiquidacion->primeraFecha;
                                }
                                $diasAgregar = 0;
                                //Verificar si dicha nomina es menor a la fecha de ingreso
                                if(strtotime($empleado->fechaIngreso) < strtotime($minimaFecha)){
                                    $diasAgregar = $this->days_360($empleado->fechaIngreso, $minimaFecha);
                                }                            
                                if(isset($vacacionesPTotal->fechaInicio)){
                                    $periodoNuevo = $this->days_360($fechaInicio,$vacacionesPTotal->fechaInicio);
                                }
                                else{
                                    $periodoNuevo = $this->days_360($fechaInicio,$novedadyconcepto->fechaRegistro);
                                }
                                

                                $periodoPagoMesActual = $periodoNuevo + $diasAgregar;

                                
                                $totalPeriodoPagoAnioActual = $periodoPagoMesActual + $liquidacionesMesesAnterioresCompleta->periodPago;
                                if($totalPeriodoPagoAnioActual>360){
                                    $totalPeriodoPagoAnioActual = 360;
                                }
                                $salarioMes = 0;
                                foreach($conceptosFijosEmpl as $conceptoFijoEmpl){
                                    if($conceptoFijoEmpl->fkConcepto=="1"){
                                        $salarioMes = $conceptoFijoEmpl->valor; 
                                    }
                                }
                                
                                
                                $itemsBoucherSalarialMesesAnterioresVac = DB::table("item_boucher_pago", "ibp")
                                ->selectRaw("Sum(ibp.valor) as suma")
                                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                ->where("ln.fechaInicio","<",$fechaInicioMes)
                                ->where("ln.fechaInicio",">=",date("Y-m-d",strtotime($fechaInicioMes." -1 year")))
                                //->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")         
                                ->where("gcc.fkGrupoConcepto","=","13") //13 - Salarial vacaciones
                                ->first();

                                if($totalPeriodoPagoAnioActual != 0){
                                    $salarialVac = $salarialVac + $itemsBoucherSalarialMesesAnterioresVac->suma;
                                    array_push($arrComoCalcula[$novedadyconcepto->idconcepto], 
                                    "Se suma al valor salarial la liquidaciones de maximo un año atras: $".number_format($itemsBoucherSalarialMesesAnterioresVac->suma,0,",", "."));
                                    array_push($arrComoCalcula[$novedadyconcepto->idconcepto], 
                                    "Se promedia el valor salarial: ($".number_format($salarialVac,0,",", ".")." / ".$totalPeriodoPagoAnioActual.")*30");
                                    $salarialVac = ($salarialVac / $totalPeriodoPagoAnioActual)*30;
                                }
                                
                                
                                $salarioVac = 0;

                                foreach($conceptosFijosEmpl as $conceptoFijoEmpl){
                                    if($conceptoFijoEmpl->fkConcepto=="1" || $conceptoFijoEmpl->fkConcepto=="2"){
                                        $salarioVac = $conceptoFijoEmpl->valor; 
                                    }
                                }
                            
                                
                                if($empleado->fkTipoCotizante == 51){
                                    //Todas mis liquidaciones 12 meses atras
                                    $fechaFinVac51 = date("Y-m-d", strtotime($fechaInicioMes." - 1 YEAR"));
                                    $liquidacionesMesesAnterioresVac51 = DB::table("liquidacionnomina", "ln")
                                    ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.diasTrabajados) as diasTrabajadosPer, sum(bp.diasIncapacidad) as diasIncapacidadPer, sum(bp.salarioPeriodoPago) as salarioPago, min(ln.fechaInicio) as minimaFecha")
                                    ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                    ->where("ln.fechaInicio","<",$fechaInicioMes)
                                    ->where("ln.fechaInicio",">=",$fechaFinVac51)
                                    ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
                                    ->first();
                    
                                    //INICIO CALCULAR EL PROMEDIO SALARIO EN PERIODO ACTUAL    
                                    
                    
                                    //$totalPeriodoPago51 = $periodoPagoMesActual + (isset($liquidacionesMesesAnterioresCes51->periodPago) ? $liquidacionesMesesAnterioresCes51->periodPago : 0);
                                    $totalPeriodoPagoParaSalario51 = $periodoPagoMesActual + (isset($liquidacionesMesesAnterioresVac51->periodPago) ? $liquidacionesMesesAnterioresVac51->periodPago : 0);
                                    
                                    $retroActivoMesesAnterioresVac51 = DB::table("liquidacionnomina", "ln")
                                    ->selectRaw("sum(ibp.valor) as suma")
                                    ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                                    ->join("item_boucher_pago as ibp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                    ->where("ln.fechaInicio","<",$fechaInicio)
                                    ->where("ln.fechaInicio",">=",$fechaFinVac51)
                                    ->where("ibp.fkConcepto","=","49") // 49 - RETROACTIVO SALARIO
                                    ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
                                    ->first();
                                    
                                    /*dd([
                                        "salarioMes" => $salarioMes,
                                        "liquidacionesMesesAnterioresPrima" => $liquidacionesMesesAnterioresPrima,
                                        "retroActivoMesesAnterioresPrima" => $retroActivoMesesAnterioresPrima
                                    ]);*/
                    
                                    
                                    $salarioVac = $salarioMes + ($liquidacionesMesesAnterioresVac51->salarioPago ?? 0) + ($retroActivoMesesAnterioresVac51->suma ?? 0);
                                    $salarioVac = ($salarioVac / $totalPeriodoPagoParaSalario51)*30;

                    
                                }
                                array_push($arrComoCalcula[$novedadyconcepto->idconcepto], 
                                "Valor del salario para la liquidacion actual $".number_format($salarioVac,0,",", "."));
                                
                                $baseVac = $salarioVac + $salarialVac;
                            
                                array_push($arrComoCalcula[$novedadyconcepto->idconcepto], 
                                "Valor de la base $".number_format($baseVac,0,",", "."));


                                $valorInt = ($baseVac/30)*$vacacionesPTotal->diasCompensar;

                                array_push($arrComoCalcula[$novedadyconcepto->idconcepto], 
                                "(Base / 30) * ".$vacacionesPTotal->diasCompensar." días = ".$valorInt);


                                $valorUnit = $valorInt;

                                array_push($arrComoCalcula[$novedadyconcepto->idconcepto], 
                                "Se suma con valor(es) anterior(es) ".$valorInt." + ".$arrValorxConcepto[$novedadyconcepto->idconcepto]["valor"]);

                                array_push($arrComoCalcula[$novedadyconcepto->idconcepto], 
                                "Se suma con cantidad(es) anterior(es) ".$valorInt." + ".$arrValorxConcepto[$novedadyconcepto->idconcepto]["valor"]);

                                $valorInt = $valorInt + $arrValorxConcepto[$novedadyconcepto->idconcepto]["valor"];
                                $cantidadInt = $arrValorxConcepto[$novedadyconcepto->idconcepto]["cantidad"] + floatval($vacacionesPTotal->diasCompensar);
                                


                                $arrNovedades=$arrValorxConcepto[$novedadyconcepto->idconcepto]["arrNovedades"];
                                array_push($arrNovedades, [
                                    "idNovedad" => $novedadyconcepto->idNovedad,
                                    "valor" => $valorUnit
                                ]);
                                $arrValorxConcepto[$novedadyconcepto->idconcepto] = array(
                                    "naturaleza" => $novedadyconcepto->fkNaturaleza,
                                    "unidad"=>"DÍA",
                                    "cantidad"=> $cantidadInt,
                                    "arrNovedades"=> $arrNovedades,
                                    "valor" => $valorInt,
                                    "tipoGen" => "novedad"
                                );
                            }
                            else{                           
                                $salarialVac = 0;                            
                                $fechaInicioMes = date("Y-m-01", strtotime($fechaInicio));
                                $grupoConceptoCalculoVac = DB::table("grupoconcepto_concepto","gcc")
                                    ->where("gcc.fkGrupoConcepto", "=", "13")//Salarial para provisiones
                                    ->get();
                                foreach($grupoConceptoCalculoVac as $grupoConcepto){

                                    $arrComoCalcula[$novedadyconcepto->idconcepto] = ($arrComoCalcula[$novedadyconcepto->idconcepto] ?? array());
                                    if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto != 36){
                                        $salarialVac = $salarialVac + floatval($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor']);
                                    
                                        array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Se suma el valor de para base salarial $".number_format($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor'],0,",", "."));
                                    }
                                    if(isset($arrValorxConceptoOtros[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto != 36){
                                        $salarialVac = $salarialVac + floatval($arrValorxConceptoOtros[$grupoConcepto->fkConcepto]['valor']);
                                        array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Se suma el valor de para base salarial $".number_format($arrValorxConceptoOtros[$grupoConcepto->fkConcepto]['valor'],0,",", "."));
                                    }                                
                                }                            
                                array_push($arrComoCalcula[$novedadyconcepto->idconcepto], "Calculó valor suma liquidacion mes actual $".number_format($salarialVac,0,",", "."));

                                $itemsBoucherSalarialMesAnteriorVac = DB::table("item_boucher_pago", "ibp")
                                ->selectRaw("Sum(ibp.valor) as suma")
                                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                ->where("ln.fechaInicio","=",$fechaInicioMes)
                                ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                                ->where("gcc.fkGrupoConcepto","=","13") //13 - Salarial vacaciones
                                ->first();

                                if(isset($itemsBoucherSalarialMesAnteriorVac)){
                                    $salarialVac = $salarialVac + $itemsBoucherSalarialMesAnteriorVac->suma;
                                    array_push($arrComoCalcula[$novedadyconcepto->idconcepto], 
                                    "Se suma al valor salarial la liquidacion mismo mes $".number_format($itemsBoucherSalarialMesAnteriorVac->suma,0,",", "."));
                                }
                                

                                $periodoPagoVac = $this->days_360($empleado->fechaIngreso,$fechaFin) + 1 ;

                                if(isset($novedadesRetiro)){
                                    if(strtotime($fechaFin) > strtotime($novedadesRetiro->fechaReal)){
                                        $periodoPagoVac = $this->days_360($empleado->fechaIngreso,$novedadesRetiro->fechaReal) + 1 ;
                                    }
                                }
                                
                                
                                // $diasVac = $totalPeriodoPagoAnioActual * 15 / 360;
                                
                                $anioActual = intval(date("Y",strtotime($fechaInicio)));
                                $mesActual = intval(date("m",strtotime($fechaInicio)));


                                $liquidacionesMesesAnterioresCompleta = DB::table("liquidacionnomina", "ln")
                                ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.salarioPeriodoPago) as salarioPago")
                                ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                ->where("ln.fechaInicio","<=",$fechaInicioMes)
                                ->where("ln.fechaInicio",">=",date("Y-m-d",strtotime($fechaInicioMes." -1 year")))
                                ->where("ln.idLiquidacionNomina", "<>", $idLiquidacionNomina)
                                ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])           
                                ->first();


                                //Obtener la primera liquidacion de nomina de la persona 
                                $primeraLiquidacion = DB::table("liquidacionnomina", "ln")
                                ->selectRaw("min(ln.fechaInicio) as primeraFecha")
                                ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                                ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])   
                                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                ->first();

                                $minimaFecha = date("Y-m-d");
                                
                                if(isset($primeraLiquidacion)){
                                    $minimaFecha = $primeraLiquidacion->primeraFecha;
                                }
                                $diasAgregar = 0;
                                //Verificar si dicha nomina es menor a la fecha de ingreso
                                if(strtotime($empleado->fechaIngreso) < strtotime($minimaFecha)){
                                    $diasAgregar = $this->days_360($empleado->fechaIngreso, $minimaFecha);
                                }
                                
                                
                                

                                
                                
                                if(isset($vacacionesPTotal->fechaInicio)){
                                    $periodoNuevo = $this->days_360($fechaInicio,$vacacionesPTotal->fechaInicio);
                                }
                                else{
                                    $periodoNuevo = $this->days_360($fechaInicio,$novedadyconcepto->fechaRegistro);
                                }
                                

                                $periodoPagoMesActual = $periodoNuevo + $diasAgregar;

                                
                                $totalPeriodoPagoAnioActual = $periodoPagoMesActual + $liquidacionesMesesAnterioresCompleta->periodPago;
                                if($totalPeriodoPagoAnioActual>360){
                                    $totalPeriodoPagoAnioActual = 360;
                                }
                                $salarioMes = 0;
                                foreach($conceptosFijosEmpl as $conceptoFijoEmpl){
                                    if($conceptoFijoEmpl->fkConcepto=="1"){
                                        $salarioMes = $conceptoFijoEmpl->valor; 
                                    }
                                }

                                //$salarioMes = ($salarioMes / 30) * $periodoPagoMesActual;
                                
                                $itemsBoucherSalarialMesesAnterioresVac = DB::table("item_boucher_pago", "ibp")
                                ->selectRaw("Sum(ibp.valor) as suma, sum(bp.periodoPago) as sumaPeriodoPago ")
                                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                ->where("ln.fechaInicio","<",$fechaInicioMes)
                                ->where("ln.fechaInicio",">=",date("Y-m-d",strtotime($fechaInicioMes." -1 year")))
                                //->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")      
                                ->where("gcc.fkGrupoConcepto","=","13") //13 - Salarial vacaciones
                                ->first();

                                if($totalPeriodoPagoAnioActual != 0){
                                    $salarialVac = $salarialVac + $itemsBoucherSalarialMesesAnterioresVac->suma;
                                    
                                    array_push($arrComoCalcula[$novedadyconcepto->idconcepto], 
                                    "Se suma al valor salarial la liquidaciones de maximo un año atras: $".number_format($itemsBoucherSalarialMesesAnterioresVac->suma,0,",", "."));
                                    array_push($arrComoCalcula[$novedadyconcepto->idconcepto], 
                                    "Se promedia el valor salarial: ($".number_format($salarialVac,0,",", ".")." / ".$totalPeriodoPagoAnioActual.")*30");

                                    $salarialVac = ($salarialVac / $totalPeriodoPagoAnioActual)*30;
                                    
                                }
                                
                                $salarioVac = 0;

                                foreach($conceptosFijosEmpl as $conceptoFijoEmpl){
                                    if($conceptoFijoEmpl->fkConcepto=="1" || $conceptoFijoEmpl->fkConcepto=="2"){
                                        $salarioVac = $conceptoFijoEmpl->valor; 
                                    }
                                }
                                if($empleado->fkTipoCotizante == 51){
                                    //Todas mis liquidaciones 12 meses atras
                                    $fechaFinVac51 = date("Y-m-d", strtotime($fechaInicioMes." - 1 YEAR"));
                                    $liquidacionesMesesAnterioresVac51 = DB::table("liquidacionnomina", "ln")
                                    ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.diasTrabajados) as diasTrabajadosPer, sum(bp.diasIncapacidad) as diasIncapacidadPer, sum(bp.salarioPeriodoPago) as salarioPago, min(ln.fechaInicio) as minimaFecha")
                                    ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                    ->where("ln.fechaInicio","<",$fechaInicioMes)
                                    ->where("ln.fechaInicio",">=",$fechaFinVac51)
                                    ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
                                    ->first();
                    
                                    //INICIO CALCULAR EL PROMEDIO SALARIO EN PERIODO ACTUAL    
                                    
                    
                                    //$totalPeriodoPago51 = $periodoPagoMesActual + (isset($liquidacionesMesesAnterioresCes51->periodPago) ? $liquidacionesMesesAnterioresCes51->periodPago : 0);
                                    $totalPeriodoPagoParaSalario51 = $periodoPagoMesActual + (isset($liquidacionesMesesAnterioresVac51->periodPago) ? $liquidacionesMesesAnterioresVac51->periodPago : 0);
                                    
                                    $retroActivoMesesAnterioresVac51 = DB::table("liquidacionnomina", "ln")
                                    ->selectRaw("sum(ibp.valor) as suma")
                                    ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                                    ->join("item_boucher_pago as ibp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                    ->where("ln.fechaInicio","<",$fechaInicio)
                                    ->where("ln.fechaInicio",">=",$fechaFinVac51)
                                    ->where("ibp.fkConcepto","=","49") // 49 - RETROACTIVO SALARIO
                                    ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
                                    ->first();
                                    
                                    
                                    $salarioVac = $salarioMes + ($liquidacionesMesesAnterioresVac51->salarioPago ?? 0) + ($retroActivoMesesAnterioresVac51->suma ?? 0);
                                    $salarioVac = ($salarioVac / $totalPeriodoPagoParaSalario51)*30;
                    
                                }
                                array_push($arrComoCalcula[$novedadyconcepto->idconcepto], 
                                "Valor del salario para la liquidacion actual $".number_format($salarioVac,0,",", "."));

                                $baseVac = $salarioVac + $salarialVac;
                            
                                array_push($arrComoCalcula[$novedadyconcepto->idconcepto], 
                                "Valor de la base $".number_format($baseVac,0,",", "."));
                                
                                
                                $valorInt = ($baseVac/30)*$vacacionesPTotal->diasCompensar;

                                array_push($arrComoCalcula[$novedadyconcepto->idconcepto], 
                                "(Base / 30) * ".$vacacionesPTotal->diasCompensar." días = ".$valorInt);

                                $arrNovedades = array([
                                    "idNovedad" => $novedadyconcepto->idNovedad,
                                    "valor" => $valorInt
                                ]);
                                
                                $cantidadInt = $vacacionesPTotal->diasCompensar;

                                $arrValorxConcepto[$novedadyconcepto->idconcepto] = array(
                                    "naturaleza" => $novedadyconcepto->fkNaturaleza,
                                    "unidad"=>"DÍA",
                                    "cantidad"=> $cantidadInt,
                                    "arrNovedades"=> $arrNovedades,
                                    "valor" => $valorInt,
                                    "tipoGen" => "novedad"
                                );
                            }
                            
                        
                    }
                }
            }
            //FIN VACACIONES PAGO TOTAL
        
            //INICIO VACACIONES PAGO PARCIAL
            $sqlWhere = "( 
                ('".$fechaInicio."' BETWEEN v.fechaInicio AND v.fechaFin) OR
                ('".$fechaFin."' BETWEEN v.fechaInicio AND v.fechaFin) OR
                (v.fechaInicio BETWEEN '".$fechaInicio."' AND '".$fechaFin."') OR
                (v.fechaFin BETWEEN '".$fechaInicio."' AND '".$fechaFin."')
            )";
            $vacacionesPParcial = DB::table("vacaciones", "v")
            ->select(["v.*","c.*","n.*"])
            ->join("novedad as n", "n.fkVacaciones","=","v.idVacaciones")
            ->join("conceptosxtipoliquidacion AS ctl", "ctl.fkConcepto","=","n.fkConcepto")
            ->join("concepto AS c", "ctl.fkConcepto","=","c.idconcepto")
            ->where("n.fkEmpleado", "=", $empleado->idempleado)
            ->where("n.fkPeriodoActivo","=", $periodoActivoReintegro->idPeriodo)
            ->where("ctl.fkTipoLiquidacion","=",$tipoliquidacion)
            ->where("v.pagoAnticipado", "=", "0")            
            ->whereIn("n.fkEstado",["7","16"])
            ->whereRaw($sqlWhere)
            ->distinct()
            ->get();
            //Agregar valor de las novedades de vacaciones con pago parcial
            foreach($vacacionesPParcial as $vacacionPParcial){
                if($vacacionPParcial->subTipo == "Formula"){
                    $arrComoCalcula[$vacacionPParcial->idconcepto] = ($arrComoCalcula[$vacacionPParcial->idconcepto] ?? array());

                    $valorFormula = $this->calcularValoresxConceptoxEmpleado($vacacionPParcial->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo);

                    if(isset($arrValorxConcepto[$vacacionPParcial->idconcepto])){
                        
                        $salarialVac = 0;
                        $fechaInicioMes = date("Y-m-01", strtotime($fechaInicio));
                        $grupoConceptoCalculoVac = DB::table("grupoconcepto_concepto","gcc")
                            ->where("gcc.fkGrupoConcepto", "=", "13")//Salarial para provisiones
                            ->get();
                        foreach($grupoConceptoCalculoVac as $grupoConcepto){
                            if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto != 36){
                                $salarialVac = $salarialVac + floatval($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor']);
                            }
                        }
                        array_push($arrComoCalcula[$vacacionPParcial->idconcepto], "Calculó valor suma liquidacion mes actual $".number_format($salarialVac,0,",", "."));

                        $itemsBoucherSalarialMesAnteriorVac = DB::table("item_boucher_pago", "ibp")
                        ->selectRaw("Sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.fechaInicio","=",$fechaInicioMes)
                        ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                        ->where("gcc.fkGrupoConcepto","=","13") //13 - Salarial vacaciones
                        ->first();
                        if(isset($itemsBoucherSalarialMesAnteriorVac)){
                            
                            array_push($arrComoCalcula[$vacacionPParcial->idconcepto], 
                            "Se suma al valor salarial la liquidacion mismo mes $".number_format($itemsBoucherSalarialMesAnteriorVac->suma,0,",", "."));

                            $salarialVac = $salarialVac + $itemsBoucherSalarialMesAnteriorVac->suma;
                        }

                        $periodoPagoVac = $this->days_360($empleado->fechaIngreso,$fechaFin) + 1;
                        if(isset($novedadesRetiro)){
                            if(strtotime($fechaFin) > strtotime($novedadesRetiro->fechaReal)){
                                $periodoPagoVac = $this->days_360($empleado->fechaIngreso,$novedadesRetiro->fechaReal) + 1 ;
                            }
                        }
                        $anioActual = intval(date("Y",strtotime($fechaInicio)));
                        $mesActual = intval(date("m",strtotime($fechaInicio)));


                        $liquidacionesMesesAnterioresCompleta = DB::table("liquidacionnomina", "ln")
                        ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.salarioPeriodoPago) as salarioPago")
                        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.fechaInicio","<=",$fechaInicioMes)
                        ->where("ln.fechaInicio",">=",date("Y-m-d",strtotime($fechaInicioMes." -1 year")))
                        ->where("ln.idLiquidacionNomina", "<>", $idLiquidacionNomina)   
                        ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])          
                        ->first();

                        //Obtener la primera liquidacion de nomina de la persona 
                        $primeraLiquidacion = DB::table("liquidacionnomina", "ln")
                        ->selectRaw("min(ln.fechaInicio) as primeraFecha")
                        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")          
                        ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])        
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->first();

                        $minimaFecha = date("Y-m-d");
                        
                        if(isset($primeraLiquidacion)){
                            $minimaFecha = $primeraLiquidacion->primeraFecha;
                        }
                        $diasAgregar = 0;
                        //Verificar si dicha nomina es menor a la fecha de ingreso
                        if(strtotime($empleado->fechaIngreso) < strtotime($minimaFecha)){
                            $diasAgregar = $this->days_360($empleado->fechaIngreso, $minimaFecha);
                        }
                        
                        $periodoNuevo = $this->days_360($fechaInicio,$vacacionPParcial->fechaInicio);

                        $periodoPagoMesActual = $periodoNuevo + $diasAgregar;
                        
                        

                        $totalPeriodoPagoAnioActual = $periodoPagoMesActual + $liquidacionesMesesAnterioresCompleta->periodPago;
                        if($totalPeriodoPagoAnioActual>360){
                            $totalPeriodoPagoAnioActual = 360;
                        }
                        $salarioMes = 0;
                        $conceptosFijosEmpl = DB::table("conceptofijo", "cf")
                        ->select(["cf.valor","cf.fechaInicio","cf.fechaFin", "cf.fkConcepto","cf.unidad", "c.*"])
                        ->join("conceptosxtipoliquidacion AS ctl", "ctl.fkConcepto","=","cf.fkConcepto")
                        ->join("concepto AS c", "ctl.fkConcepto","=","c.idconcepto")
                        ->where("ctl.fkTipoLiquidacion","=",$tipoliquidacion)     
                        ->where("cf.fkEmpleado", "=", $empleado->idempleado)  
                        ->where("cf.fkPeriodoActivo","=", $periodoActivoReintegro->idPeriodo)
                        ->where("cf.fkEstado", "=", "1")
                        ->distinct()
                        ->get();
                        foreach($conceptosFijosEmpl as $conceptoFijoEmpl){
                            if($conceptoFijoEmpl->fkConcepto=="1"){
                                $salarioMes = $conceptoFijoEmpl->valor; 
                            }
                        }

                        $itemsBoucherSalarialMesesAnterioresVac = DB::table("item_boucher_pago", "ibp")
                        ->selectRaw("Sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.fechaInicio","<",$fechaInicioMes)
                        ->where("ln.fechaInicio",">=",date("Y-m-d",strtotime($fechaInicioMes." -1 year")))
                        //->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")                
                        ->where("gcc.fkGrupoConcepto","=","13") //13 - Salarial vacaciones
                        ->first();

                        $salarialVac = $salarialVac + $itemsBoucherSalarialMesesAnterioresVac->suma;
                        array_push($arrComoCalcula[$vacacionPParcial->idconcepto], 
                        "Se suma al valor salarial la liquidaciones de maximo un año atras: $".number_format($itemsBoucherSalarialMesesAnterioresVac->suma,0,",", "."));
                        $salarialVac = ($salarialVac / $totalPeriodoPagoAnioActual)*30;
                        array_push($arrComoCalcula[$vacacionPParcial->idconcepto], 
                        "Se promedia el valor salarial: ($".number_format($salarialVac,0,",", ".")." / ".$totalPeriodoPagoAnioActual.")*30");

                        $salarioVac = 0;

                        foreach($conceptosFijosEmpl as $conceptoFijoEmpl){
                            if($conceptoFijoEmpl->fkConcepto=="1" || $conceptoFijoEmpl->fkConcepto=="2"){
                                $salarioVac = $conceptoFijoEmpl->valor; 
                            }
                        }
                        if($empleado->fkTipoCotizante == 51){
                            //Todas mis liquidaciones 12 meses atras
                            $fechaFinVac51 = date("Y-m-d", strtotime($fechaInicioMes." - 1 YEAR"));
                            $liquidacionesMesesAnterioresVac51 = DB::table("liquidacionnomina", "ln")
                            ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.diasTrabajados) as diasTrabajadosPer, sum(bp.diasIncapacidad) as diasIncapacidadPer, sum(bp.salarioPeriodoPago) as salarioPago, min(ln.fechaInicio) as minimaFecha")
                            ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                            ->where("bp.fkEmpleado","=",$empleado->idempleado)
                            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                            ->where("ln.fechaInicio","<",$fechaInicioMes)
                            ->where("ln.fechaInicio",">=",$fechaFinVac51)
                            ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
                            ->first();
            
                            //INICIO CALCULAR EL PROMEDIO SALARIO EN PERIODO ACTUAL    
                            
            
                            //$totalPeriodoPago51 = $periodoPagoMesActual + (isset($liquidacionesMesesAnterioresCes51->periodPago) ? $liquidacionesMesesAnterioresCes51->periodPago : 0);
                            $totalPeriodoPagoParaSalario51 = $periodoPagoMesActual + (isset($liquidacionesMesesAnterioresVac51->periodPago) ? $liquidacionesMesesAnterioresVac51->periodPago : 0);
                            
                            $retroActivoMesesAnterioresVac51 = DB::table("liquidacionnomina", "ln")
                            ->selectRaw("sum(ibp.valor) as suma")
                            ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                            ->join("item_boucher_pago as ibp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                            ->where("bp.fkEmpleado","=",$empleado->idempleado)
                            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                            ->where("ln.fechaInicio","<",$fechaInicio)
                            ->where("ln.fechaInicio",">=",$fechaFinVac51)
                            ->where("ibp.fkConcepto","=","49") // 49 - RETROACTIVO SALARIO
                            ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
                            ->first();
                            
                            $salarioVac = $salarioMes + ($liquidacionesMesesAnterioresVac51->salarioPago ?? 0) + ($retroActivoMesesAnterioresVac51->suma ?? 0);
                            $salarioVac = ($salarioVac / $totalPeriodoPagoParaSalario51)*30;
            
                        }
                        array_push($arrComoCalcula[$vacacionPParcial->idconcepto], 
                                "Valor del salario para la liquidacion actual $".number_format($salarioVac,0,",", "."));
                        
                        $baseVac = $salarioVac + $salarialVac;
                        
                        array_push($arrComoCalcula[$vacacionPParcial->idconcepto], 
                                "Valor de la base $".number_format($baseVac,0,",", "."));
                        
                        $diasCompensar = 0;

                        if(strtotime($vacacionPParcial->fechaInicio)>=strtotime($fechaInicio)
                            &&  strtotime($vacacionPParcial->fechaInicio)<=strtotime($fechaFin) 
                            &&  strtotime($vacacionPParcial->fechaFin)>=strtotime($fechaFin))
                        {
                            $diaI = strtotime($vacacionPParcial->fechaInicio);
                            $diaF = strtotime($fechaFin);
                            $diasCompensar = $this->days_360($vacacionPParcial->fechaInicio, $fechaFin) + 1;
                            if(substr($vacacionPParcial->fechaInicio, 8, 2) == "31" && substr($fechaFin, 8, 2) == "31"){
                                $diasCompensar--;   
                            }
                        }
                        else if(strtotime($vacacionPParcial->fechaFin)>=strtotime($fechaInicio)  
                        &&  strtotime($vacacionPParcial->fechaFin)<=strtotime($fechaFin) 
                        &&  strtotime($vacacionPParcial->fechaInicio)<=strtotime($fechaInicio))
                        {
                            $diaI = strtotime( $fechaInicio );
                            $diaF = strtotime( $vacacionPParcial->fechaFin );

                            $diasCompensar = $this->days_360($fechaInicio, $vacacionPParcial->fechaFin) + 1;
                            if(substr($fechaInicio, 8, 2) == "31" && substr($vacacionPParcial->fechaFin, 8, 2) == "31"){
                                $diasCompensar--;   
                            }
                        }
                        else if(strtotime($vacacionPParcial->fechaInicio)<=strtotime($fechaInicio)  
                        &&  strtotime($vacacionPParcial->fechaFin)>=strtotime($fechaFin)) 
                        {
                            $diaI = strtotime( $fechaInicio );
                            $diaF = strtotime( $fechaFin );
                            $diasCompensar = $this->days_360($fechaInicio, $fechaFin) + 1;
                            if(substr($fechaInicio, 8, 2) == "31" && substr($fechaFin, 8, 2) == "31"){
                                $diasCompensar--;   
                            }
                        }
                        else if(strtotime($fechaInicio)<=strtotime($vacacionPParcial->fechaInicio)  
                        &&  strtotime($fechaFin)>=strtotime($vacacionPParcial->fechaFin)) 
                        {
                            $diaI = strtotime($vacacionPParcial->fechaInicio);
                            $diaF = strtotime($vacacionPParcial->fechaFin);
                            $diasCompensar = $this->days_360($vacacionPParcial->fechaInicio, $vacacionPParcial->fechaFin) + 1;

                            if(substr($vacacionPParcial->fechaInicio, 8, 2) == "31" && substr($vacacionPParcial->fechaFin, 8, 2) == "31"){
                                $diasCompensar--;   
                            }
                            
                        }

                        $valorInt = ($baseVac/30)*$diasCompensar;

                        array_push($arrComoCalcula[$vacacionPParcial->idconcepto], 
                        "(Base / 30) * ".$diasCompensar." días = ".$valorInt);


                        $valorUnit = $valorInt;
                        array_push($arrComoCalcula[$vacacionPParcial->idconcepto], 
                        "Se suma con valor(es) anterior(es) ".$valorInt." + ".$arrValorxConcepto[$vacacionPParcial->idconcepto]["valor"]);

                        array_push($arrComoCalcula[$vacacionPParcial->idconcepto], 
                        "Se suma con cantidad(es) anterior(es) ".$valorInt." + ".$arrValorxConcepto[$vacacionPParcial->idconcepto]["valor"]);

                        $valorInt = $valorInt + $arrValorxConcepto[$vacacionPParcial->idconcepto]["valor"];
                        $cantidadInt = $arrValorxConcepto[$vacacionPParcial->idconcepto]["cantidad"] + floatval($diasCompensar);
                        $arrNovedades=$arrValorxConcepto[$vacacionPParcial->idconcepto]["arrNovedades"];
                        array_push($arrNovedades, [
                            "idNovedad" => $vacacionPParcial->idNovedad,
                            "parcial" => 1,
                            "valor" => $valorUnit,
                            "cantidad"=> $cantidadInt
                        ]);
                        $arrValorxConcepto[$vacacionPParcial->idconcepto] = array(
                            "naturaleza" => $vacacionPParcial->fkNaturaleza,
                            "unidad"=>"DÍA",
                            "cantidad"=> $cantidadInt,
                            "arrNovedades"=> $arrNovedades,
                            "valor" => $valorInt,
                            "tipoGen" => "novedad",
                            "base" => $baseVac
                        );
                    }
                    else{

                        $salarialVac = 0;
                        $fechaInicioMes = date("Y-m-01", strtotime($fechaInicio));
                        $grupoConceptoCalculoVac = DB::table("grupoconcepto_concepto","gcc")
                            ->where("gcc.fkGrupoConcepto", "=", "13")//Salarial para provisiones
                            ->get();
                        foreach($grupoConceptoCalculoVac as $grupoConcepto){
                            if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto != 36){
                                $salarialVac = $salarialVac + floatval($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor']);
                            }
                        }
                        array_push($arrComoCalcula[$vacacionPParcial->idconcepto], "Calculó valor suma liquidacion mes actual $".number_format($salarialVac,0,",", "."));

                        $itemsBoucherSalarialMesAnteriorVac = DB::table("item_boucher_pago", "ibp")
                        ->selectRaw("Sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.fechaInicio","=",$fechaInicioMes)
                        ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                        ->where("gcc.fkGrupoConcepto","=","13") //13 - Salarial vacaciones
                        ->first();
                        if(isset($itemsBoucherSalarialMesAnteriorVac)){
                            $salarialVac = $salarialVac + $itemsBoucherSalarialMesAnteriorVac->suma;
                            array_push($arrComoCalcula[$vacacionPParcial->idconcepto], 
                            "Se suma al valor salarial la liquidacion mismo mes $".number_format($itemsBoucherSalarialMesAnteriorVac->suma,0,",", "."));
                        }
                        

                        $periodoPagoVac = $this->days_360($empleado->fechaIngreso,$fechaFin) + 1 ;

                        if(isset($novedadesRetiro)){
                            if(strtotime($fechaFin) > strtotime($novedadesRetiro->fechaReal)){
                                $periodoPagoVac = $this->days_360($empleado->fechaIngreso,$novedadesRetiro->fechaReal) + 1 ;
                            }
                        }
                        
                        
                        // $diasVac = $totalPeriodoPagoAnioActual * 15 / 360;
                        
                        $anioActual = intval(date("Y",strtotime($fechaInicio)));
                        $mesActual = intval(date("m",strtotime($fechaInicio)));


                        $liquidacionesMesesAnterioresCompleta = DB::table("liquidacionnomina", "ln")
                        ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.salarioPeriodoPago) as salarioPago")
                        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.fechaInicio","<=",$fechaInicioMes)
                        ->where("ln.idLiquidacionNomina", "<>", $idLiquidacionNomina)
                        ->where("ln.fechaInicio",">=",date("Y-m-d",strtotime($fechaInicioMes." -1 year")))
                        ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
                        ->first();


                        //Obtener la primera liquidacion de nomina de la persona 
                        $primeraLiquidacion = DB::table("liquidacionnomina", "ln")
                        ->selectRaw("min(ln.fechaInicio) as primeraFecha")
                        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")    
                        ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])               
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->first();

                        $minimaFecha = date("Y-m-d");
                        
                        if(isset($primeraLiquidacion)){
                            $minimaFecha = $primeraLiquidacion->primeraFecha;
                        }
                        $diasAgregar = 0;
                        //Verificar si dicha nomina es menor a la fecha de ingreso
                        if(strtotime($empleado->fechaIngreso) < strtotime($minimaFecha)){
                            $diasAgregar = $this->days_360($empleado->fechaIngreso, $minimaFecha);
                        }
                        
                        
                        

                        
                        
                        $periodoNuevo = $this->days_360($fechaInicio,$vacacionPParcial->fechaInicio);

                        $periodoPagoMesActual = $periodoNuevo + $diasAgregar;

                        //dd($vacacionPParcial->fechaInicio, $fechaInicio, $periodoNuevo, $diasAgregar, $liquidacionesMesesAnterioresCompleta->periodPago);

                        $totalPeriodoPagoAnioActual = $periodoPagoMesActual + $liquidacionesMesesAnterioresCompleta->periodPago;
                        if($totalPeriodoPagoAnioActual>360){
                            $totalPeriodoPagoAnioActual = 360;
                        }
                        $salarioMes = 0;
                        $conceptosFijosEmpl = DB::table("conceptofijo", "cf")
                        ->select(["cf.valor","cf.fechaInicio","cf.fechaFin", "cf.fkConcepto","cf.unidad", "c.*"])
                        ->join("conceptosxtipoliquidacion AS ctl", "ctl.fkConcepto","=","cf.fkConcepto")
                        ->join("concepto AS c", "ctl.fkConcepto","=","c.idconcepto")
                        ->where("ctl.fkTipoLiquidacion","=",$tipoliquidacion)     
                        ->where("cf.fkEmpleado", "=", $empleado->idempleado)  
                        ->where("cf.fkPeriodoActivo","=", $periodoActivoReintegro->idPeriodo)
                        ->where("cf.fkEstado", "=", "1")
                        ->distinct()
                        ->get();
                        foreach($conceptosFijosEmpl as $conceptoFijoEmpl){
                            if($conceptoFijoEmpl->fkConcepto=="1"){
                                $salarioMes = $conceptoFijoEmpl->valor; 
                            }
                        }

                        $itemsBoucherSalarialMesesAnterioresVac = DB::table("item_boucher_pago", "ibp")
                        ->selectRaw("Sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.fechaInicio","<",$fechaInicioMes)
                        ->where("ln.fechaInicio",">=",date("Y-m-d",strtotime($fechaInicioMes." -1 year")))
                        //->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")      
                        ->where("gcc.fkGrupoConcepto","=","13") //13 - Salarial vacaciones
                        ->first();


                        $salarialVac = $salarialVac + $itemsBoucherSalarialMesesAnterioresVac->suma;
                        array_push($arrComoCalcula[$vacacionPParcial->idconcepto], 
                        "Se suma al valor salarial la liquidaciones de maximo un año atras: $".number_format($itemsBoucherSalarialMesesAnterioresVac->suma,0,",", "."));
                        $salarialVac = ($salarialVac / $totalPeriodoPagoAnioActual)*30;
                        array_push($arrComoCalcula[$vacacionPParcial->idconcepto], 
                        "Se promedia el valor salarial: ($".number_format($salarialVac,0,",", ".")." / ".$totalPeriodoPagoAnioActual.")*30");
                        $salarioVac = 0;

                        foreach($conceptosFijosEmpl as $conceptoFijoEmpl){
                            if($conceptoFijoEmpl->fkConcepto=="1" || $conceptoFijoEmpl->fkConcepto=="2"){
                                $salarioVac = $conceptoFijoEmpl->valor; 
                            }
                        }

                        if($empleado->fkTipoCotizante == 51){
                            //Todas mis liquidaciones 12 meses atras
                            $fechaFinVac51 = date("Y-m-d", strtotime($fechaInicioMes." - 1 YEAR"));
                            $liquidacionesMesesAnterioresVac51 = DB::table("liquidacionnomina", "ln")
                            ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.diasTrabajados) as diasTrabajadosPer, sum(bp.diasIncapacidad) as diasIncapacidadPer, sum(bp.salarioPeriodoPago) as salarioPago, min(ln.fechaInicio) as minimaFecha")
                            ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                            ->where("bp.fkEmpleado","=",$empleado->idempleado)
                            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                            ->where("ln.fechaInicio","<",$fechaInicioMes)
                            ->where("ln.fechaInicio",">=",$fechaFinVac51)
                            ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
                            ->first();
            
                            //INICIO CALCULAR EL PROMEDIO SALARIO EN PERIODO ACTUAL    
                            
            
                            //$totalPeriodoPago51 = $periodoPagoMesActual + (isset($liquidacionesMesesAnterioresCes51->periodPago) ? $liquidacionesMesesAnterioresCes51->periodPago : 0);
                            $totalPeriodoPagoParaSalario51 = $periodoPagoMesActual + (isset($liquidacionesMesesAnterioresVac51->periodPago) ? $liquidacionesMesesAnterioresVac51->periodPago : 0);
                            
                            $retroActivoMesesAnterioresVac51 = DB::table("liquidacionnomina", "ln")
                            ->selectRaw("sum(ibp.valor) as suma")
                            ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                            ->join("item_boucher_pago as ibp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                            ->where("bp.fkEmpleado","=",$empleado->idempleado)
                            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                            ->where("ln.fechaInicio","<",$fechaInicio)
                            ->where("ln.fechaInicio",">=",$fechaFinVac51)
                            ->where("ibp.fkConcepto","=","49") // 49 - RETROACTIVO SALARIO
                            ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
                            ->first();
                            
                            /*dd([
                                "salarioMes" => $salarioMes,
                                "liquidacionesMesesAnterioresPrima" => $liquidacionesMesesAnterioresPrima,
                                "retroActivoMesesAnterioresPrima" => $retroActivoMesesAnterioresPrima
                            ]);*/
            
                            
                            $salarioVac = $salarioMes + ($liquidacionesMesesAnterioresVac51->salarioPago ?? 0) + ($retroActivoMesesAnterioresVac51->suma ?? 0);
                            $salarioVac = ($salarioVac / $totalPeriodoPagoParaSalario51)*30;
            
                        }
                        array_push($arrComoCalcula[$vacacionPParcial->idconcepto], 
                                "Valor del salario para la liquidacion actual $".number_format($salarioVac,0,",", "."));
                        $baseVac = $salarioVac + $salarialVac;

                        array_push($arrComoCalcula[$vacacionPParcial->idconcepto], 
                                "Valor de la base $".number_format($baseVac,0,",", "."));
                        
                        $diasCompensar = 0;

                        if(strtotime($vacacionPParcial->fechaInicio)>=strtotime($fechaInicio)
                            &&  strtotime($vacacionPParcial->fechaInicio)<=strtotime($fechaFin) 
                            &&  strtotime($vacacionPParcial->fechaFin)>=strtotime($fechaFin))
                        {
                            $diaI = strtotime($vacacionPParcial->fechaInicio);
                            $diaF = strtotime($fechaFin);
                            $diasCompensar = $this->days_360($vacacionPParcial->fechaInicio, $fechaFin) + 1;
                            if(substr($vacacionPParcial->fechaInicio, 8, 2) == "31" && substr($fechaFin, 8, 2) == "31"){
                                $diasCompensar--;   
                            }
                        }
                        else if(strtotime($vacacionPParcial->fechaFin)>=strtotime($fechaInicio)  
                        &&  strtotime($vacacionPParcial->fechaFin)<=strtotime($fechaFin) 
                        &&  strtotime($vacacionPParcial->fechaInicio)<=strtotime($fechaInicio))
                        {
                            $diaI = strtotime( $fechaInicio );
                            $diaF = strtotime( $vacacionPParcial->fechaFin );

                            $diasCompensar = $this->days_360($fechaInicio, $vacacionPParcial->fechaFin) + 1;
                            if(substr($fechaInicio, 8, 2) == "31" && substr($vacacionPParcial->fechaFin, 8, 2) == "31"){
                                $diasCompensar--;   
                            }
                        }
                        else if(strtotime($vacacionPParcial->fechaInicio)<=strtotime($fechaInicio)  
                        &&  strtotime($vacacionPParcial->fechaFin)>=strtotime($fechaFin)) 
                        {
                            $diaI = strtotime( $fechaInicio );
                            $diaF = strtotime( $fechaFin );
                            $diasCompensar = $this->days_360($fechaInicio, $fechaFin) + 1;
                            if(substr($fechaInicio, 8, 2) == "31" && substr($fechaFin, 8, 2) == "31"){
                                $diasCompensar--;   
                            }
                        }
                        else if(strtotime($fechaInicio)<=strtotime($vacacionPParcial->fechaInicio)  
                        &&  strtotime($fechaFin)>=strtotime($vacacionPParcial->fechaFin)) 
                        {
                            $diaI = strtotime($vacacionPParcial->fechaInicio);
                            $diaF = strtotime($vacacionPParcial->fechaFin);
                            $diasCompensar = $this->days_360($vacacionPParcial->fechaInicio, $vacacionPParcial->fechaFin) + 1;

                            if(substr($vacacionPParcial->fechaInicio, 8, 2) == "31" && substr($vacacionPParcial->fechaFin, 8, 2) == "31"){
                                $diasCompensar--;   
                            }
                            
                        }

                        $diferenciaDiasPeriodo = $this->days_360($vacacionPParcial->fechaInicio, $vacacionPParcial->fechaFin);
                        
                        
                        
                        
                        
                        $valorInt = ($baseVac/30)*$diasCompensar;

                        array_push($arrComoCalcula[$vacacionPParcial->idconcepto], 
                        "(Base / 30) * ".$diasCompensar." días = ".$valorInt);

                        /*echo "valorInt => ".intval($valorInt)."<br>";
                        exit;*/
                        
                        $cantidadInt = $diasCompensar;
                        $arrNovedades = array([
                            "idNovedad" => $vacacionPParcial->idNovedad,
                            "valor" => $valorInt,
                            "parcial" => 1,
                            "cantidad"=> $cantidadInt
                        ]);
                        $arrValorxConcepto[$vacacionPParcial->idconcepto] = array(
                            "naturaleza" => $vacacionPParcial->fkNaturaleza,
                            "unidad"=>"DÍA",
                            "cantidad"=> $cantidadInt,
                            "arrNovedades"=> $arrNovedades,
                            "valor" => $valorInt,
                            "tipoGen" => "novedad",
                            "base" => $baseVac
                        );
                    }
                }                        
                
            }
            //FIN VACACIONES PAGO PARCIAL
            
        
            //INICIO CALCULAR VALOR DIA CONCEPTO
            $novedadesRetiro = DB::table("novedad","n")
            ->select("r.fecha","r.fechaReal")
            ->join("retiro AS r", "r.idRetiro","=","n.fkRetiro")
            ->where("n.fkEmpleado", "=", $empleado->idempleado)
            ->where("n.fkPeriodoActivo","=", $periodoActivoReintegro->idPeriodo)
            ->where("n.fkEstado","=","7")
            ->whereNotNull("n.fkRetiro")
            ->whereBetween("n.fechaRegistro",[$fechaInicio, $fechaFin])->first();

            $arrConceptosSalariales = [1,2,53,54,154];
            foreach($arrValorxConcepto as $idConcepto => $arrConcepto){                
                if($arrConcepto["tipoGen"] != "novedad"){//Si no es una novedad
                    if($arrConcepto["unidad"]=="MES"){             
                        $fechaFinCalcConc = $fechaFin;                        
                        if(isset($novedadesRetiro)){
                            $fechaFinCalcConc = $novedadesRetiro->fechaReal;                            
                        }
                        if(substr($fechaFinCalcConc, 8, 2) == "31" || (substr($fechaFinCalcConc, 8, 2) == "28" && substr($fechaFinCalcConc, 5, 2) == "02") || (substr($fechaFinCalcConc, 8, 2) == "29" && substr($fechaFinCalcConc, 5, 2) == "02")  ){
                            $fechaFinCalcConc = substr($fechaFinCalcConc,0,8)."30";
                        }
                        if(isset($arrConcepto["fechaInicioConcepto"])){
                            $arrComoCalcula[$idConcepto] = ($arrComoCalcula[$idConcepto] ?? array());

                            if(( strtotime($arrConcepto["fechaInicioConcepto"]) > strtotime($fechaInicio) &&
                                strtotime($arrConcepto["fechaInicioConcepto"]) < strtotime($fechaFin)
                            )&& (!in_array($idConcepto, $arrConceptosSalariales)))
                            {
                                $diasEntreLiqyCon = $this->days_360($arrConcepto["fechaInicioConcepto"], $fechaFinCalcConc) + 1;
                                
                                $diasEntreLiqyCon = $periodoGen;
              
                                array_push($arrComoCalcula[$idConcepto], 
                                "Se toman los días desde ".$arrConcepto["fechaInicioConcepto"]." y ".$fechaFinCalcConc." = ".$diasEntreLiqyCon." días");

                                array_push($arrComoCalcula[$idConcepto], 
                                "(".$arrConcepto["valor"]." / 30) * ".$diasEntreLiqyCon." días = ".($diasEntreLiqyCon * ($arrConcepto["valor"]/30)));


                                
                                $arrConcepto["valor"] = $diasEntreLiqyCon * ($arrConcepto["valor"]/30);
                                $arrConcepto["cantidad"]=$diasEntreLiqyCon;
                                $arrConcepto["unidad"] = "DÍA";     
                            }
                            else if(( strtotime($arrConcepto["fechaInicioConcepto"]) > strtotime($fechaInicio) &&
                                strtotime($arrConcepto["fechaInicioConcepto"]) < strtotime($fechaFin)
                            )&& (in_array($idConcepto, $arrConceptosSalariales)))
                            {
                                //Buscar cambio de salario anterior
                                //Si la fecha de ingreso es menor a la fecha de inicio de la nomina
                                if(strtotime($empleado->fechaIngreso) < strtotime($fechaInicio)){
                                    $cambioSalarioFin = DB::table("cambiosalario","cs")
                                    ->where('cs.fkEmpleado', "=", $empleado->idempleado)
                                    ->where("cs.fkPeriodoActivo","=", $periodoActivoReintegro->idPeriodo)
                                    ->where('cs.fkEstado', "=", "5")
                                    ->orderBy("idCambioSalario","desc")->first();
    
    
                                    $diasEntreLiqyCon = $this->days_360($arrConcepto["fechaInicioConcepto"], $fechaFinCalcConc) + 1;

                                    $diasFaltantesConOtroSalario = $periodoGen - $diasEntreLiqyCon;

                                    array_push($arrComoCalcula[$idConcepto], 
                                    "Se toman los días desde ".$arrConcepto["fechaInicioConcepto"]." y ".$fechaFinCalcConc." = ".$diasEntreLiqyCon." días");
    
                                    array_push($arrComoCalcula[$idConcepto], 
                                    "(".$arrConcepto["valor"]." / 30) * ".$diasEntreLiqyCon." días = ".($diasEntreLiqyCon * ($arrConcepto["valor"]/30)));
                                    
                                    array_push($arrComoCalcula[$idConcepto], 
                                    "Se toman los días restastes = ".$diasFaltantesConOtroSalario." días");
                                    $valorAdicional = 0;
                                    if(isset($cambioSalarioFin)){
                                        $valorAdicional = $diasFaltantesConOtroSalario * ($cambioSalarioFin->valorAnterior / 30);
                                        array_push($arrComoCalcula[$idConcepto], 
                                        "(".$cambioSalarioFin->valorAnterior." / 30) * ".$diasFaltantesConOtroSalario." días = ".$valorAdicional );
                                    }
                                    else{
                                        array_push($arrComoCalcula[$idConcepto], 
                                        "No se encontró salario anterior, no se suma nada");
                                    }


                                    $arrConcepto["valor"] = ($diasEntreLiqyCon * ($arrConcepto["valor"]/30)) + $valorAdicional;
                                    $arrConcepto["cantidad"]=$diasEntreLiqyCon + $diasFaltantesConOtroSalario;
                                    $arrConcepto["unidad"] = "DÍA";    
                                }
                                

                                

                            }
                            else{

                                array_push($arrComoCalcula[$idConcepto], 
                                "(".$arrConcepto["valor"]." / 30) * ".$periodoGen." días = ".($periodoGen * ($arrConcepto["valor"]/30)));
                                
                                $arrConcepto["valor"] = $periodoGen * ($arrConcepto["valor"]/30);
                                $arrConcepto["cantidad"]=$periodoGen;
                                $arrConcepto["unidad"] = "DÍA";                            
                            }
                        }   
                        else{
                            array_push($arrComoCalcula[$idConcepto], 
                                "(".$arrConcepto["valor"]." / 30) * ".$periodoGen." días = ".($periodoGen * ($arrConcepto["valor"]/30)));

                            $arrConcepto["valor"] = $periodoGen * ($arrConcepto["valor"]/30);
                            $arrConcepto["cantidad"]=$periodoGen;
                            $arrConcepto["unidad"] = "DÍA";                        
                        }

                        
                    }
                }

                if($arrConcepto["naturaleza"]=="3"){
                    array_push($arrComoCalcula[$idConcepto], 
                    "Naturaleza de descuento ". $arrConcepto["valor"]." * -1");
                    $arrConcepto["valor"] = $arrConcepto["valor"] * -1;
                    


                }            
                $arrValorxConcepto[$idConcepto] = $arrConcepto;
                //Faltan otros tipos de unidades OJO
            }
            
            //FIN CALCULAR VALOR DIA CONCEPTO

            //INICIO CALCULO DE SUBSIDIO DE TRANSPORTE

            //INICIO CALCULAR CONCEPTOS PARA SUBSIDIO DE TRANSPORTE
            
            $arrValorxConceptoParaSu = $arrValorxConcepto;
            foreach($arrValorxConceptoParaSu as $idConcepto => $arrConcepto){                
                if($arrConcepto["tipoGen"] != "novedad"){//Si no es una novedad
                    if($arrConcepto["unidad"]=="MES"){
                        $arrConcepto["valor"] = $periodoGen * ($arrConcepto["valor"]/30);
                        $arrConcepto["cantidad"]=$periodoGen;
                        $arrConcepto["unidad"] = "DÍA";                        
                    }
                }
                if($arrConcepto["naturaleza"]=="3"){
                    $arrConcepto["valor"] = $arrConcepto["valor"] * -1;
                }            
                $arrValorxConceptoParaSu[$idConcepto] = $arrConcepto;
                //Faltan otros tipos de unidades OJO
            }


            //INICIO AGREGAR CONCEPTOS NOVEDAD OTROS
            foreach($arrValorxConceptoOtros as $idConceptoOtros => $arrConceptoOtros){                
                if(isset($arrValorxConcepto[$idConceptoOtros]))
                {
                    $arrValorxConceptoParaSu[$idConceptoOtros]["valor"] = $arrValorxConceptoParaSu[$idConceptoOtros]["valor"] + $arrConceptoOtros['valor'];
                }
                else{
                    $arrValorxConceptoParaSu[$idConceptoOtros] = $arrConceptoOtros;
                }
            }
            //FIN AGREGAR CONCEPTOS NOVEDAD OTROS
            //FIN CALCULAR CONCEPTOS PARA SUBSIDIO DE TRANSPORTE
            $periodoParaSub = $periodoGen;
            
            if($periodo == 15 && $periodoParaSub > 0){
                if(substr($liquidacionNomina->fechaInicio,8,2) == "16"){
                    $fechaPrimeraQuincena = substr($liquidacionNomina->fechaInicio,0,8)."01";

                    $itemsAntBoucherPago = DB::table("item_boucher_pago","ibp")
                    ->select("ibp.*")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=", $empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fkEstado","=","5")//Terminada
                    ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                    ->get();
                    foreach($itemsAntBoucherPago as $itemAntBoucherPago){
                        if(isset($arrValorxConceptoParaSu[$itemAntBoucherPago->fkConcepto])){
                            $arrValorxConceptoParaSu[$itemAntBoucherPago->fkConcepto]["valor"] = $arrValorxConceptoParaSu[$itemAntBoucherPago->fkConcepto]["valor"] + $itemAntBoucherPago->valor;
                        }
                        else{
                            $arrValorxConceptoParaSu[$itemAntBoucherPago->fkConcepto]["valor"] = $itemAntBoucherPago->valor;
                        }
                    } 
                    
                    $BoucherPagoAnt = DB::table("boucherpago","bp")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=", $empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fkEstado","=","5")//Terminada
                    ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                    ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
                    ->first();
                    
                    if(isset($BoucherPagoAnt)){
                        $periodoParaSub += $BoucherPagoAnt->diasTrabajados;
                    }
                    
                }
            }
            
            $subsidio = DB::table("concepto", "c")
            ->join("conceptosxtipoliquidacion AS ctl", "ctl.fkConcepto","=","c.idconcepto")
            ->where("ctl.fkTipoLiquidacion","=",$tipoliquidacion)     
            ->where("c.idconcepto", "=", "5")
            ->distinct()
            ->get();
            //Agregar el valor del subisidio de transporte en caso de que exista dentro de la liquidacion actual
            if( $empleado->fkTipoCotizante == "12" || 
                $empleado->fkTipoCotizante == "19" || 
                $empleado->fkTipoCotizante == "23" ||
                $empleado->aplicaSubsidio == "0" || 
                $empleado->tipoRegimen == 'Salario Integral'){
            }
            else{
                foreach($subsidio as $automatico){                
                    
                    if($this->condicionesxConceptoEnArray($automatico->idconcepto, $empleado->idempleado, $periodoActivoReintegro->idPeriodo, $arrValorxConceptoParaSu, $periodoParaSub)){
                        if($automatico->subTipo == "Tabla"){                        
                            $variableFinal = DB::table('variable')->where("idVariable","=",$automatico->fkVariable)->first();
                            $valorFormula = floatval($variableFinal->valor);
                            $arrComoCalcula[$automatico->idconcepto] = ($arrComoCalcula[$automatico->idconcepto] ?? array());

                            if(isset($arrValorxConcepto[$automatico->idconcepto])){
                                
                                $valorInt = $arrValorxConcepto[$automatico->idconcepto]["valor"] + $valorFormula;
                                array_push($arrComoCalcula[$automatico->idconcepto], 
                                "Nuevo subsidio de transporte por: ".$valorFormula."");

                                
                                $arrValorxConcepto[$automatico->idconcepto] = array(
                                    "naturaleza" => $automatico->fkNaturaleza,
                                    "unidad" => "MES",
                                    "cantidad"=> 0,
                                    "arrNovedades"=> array(),
                                    "valor" => $valorInt,
                                    "tipoGen" => "automaticos"
                                );                            
                            }
                            else{
                                $valorInt = $valorFormula;
                                array_push($arrComoCalcula[$automatico->idconcepto], 
                                "Nuevo subsidio de transporte por: ".$valorFormula."");

                                $arrValorxConcepto[$automatico->idconcepto] = array(
                                    "naturaleza" => $automatico->fkNaturaleza,
                                    "unidad" => "MES",
                                    "cantidad"=> 0,
                                    "arrNovedades"=> array(),
                                    "valor" => $valorInt,
                                    "tipoGen" => "automaticos"
                                );
                            }                
                        }
                    }
                    else{
                        if($periodo == 15){
                            if(substr($liquidacionNomina->fechaInicio,8,2) == "16"){
                                $fechaPrimeraQuincena = substr($liquidacionNomina->fechaInicio,0,8)."01";
            
                                $itemsAntBoucherPago = DB::table("item_boucher_pago","ibp")
                                ->select("ibp.*")
                                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                                ->where("bp.fkEmpleado","=", $empleado->idempleado)
                                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                ->where("ln.fkEstado","=","5")//Terminada
                                ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                                ->where("ibp.fkConcepto","=",$automatico->idconcepto)
                                ->first();

                                if(isset($itemsAntBoucherPago->valor)){
                                    $arrComoCalcula[$automatico->idconcepto] = ($arrComoCalcula[$automatico->idconcepto] ?? array());
                                    if(isset($arrValorxConcepto[$automatico->idconcepto])){
                                
                                        $valorInt = $itemsAntBoucherPago->valor * -1;
                                        array_push($arrComoCalcula[$automatico->idconcepto], 
                                        "Nuevo subsidio de transporte por: ".$valorInt."");
        
                                        
                                        $arrValorxConcepto[$automatico->idconcepto] = array(
                                            "naturaleza" => $automatico->fkNaturaleza,
                                            "unidad" => "DIA",
                                            "cantidad"=> $itemsAntBoucherPago->cantidad,
                                            "arrNovedades"=> array(),
                                            "valor" => $valorInt,
                                            "tipoGen" => "automaticos"
                                        );                            
                                    }
                                    else{
                                        $valorInt = $itemsAntBoucherPago->valor * -1;
                                        array_push($arrComoCalcula[$automatico->idconcepto], 
                                        "Nuevo subsidio de transporte por: ".$valorInt."");
        
                                        $arrValorxConcepto[$automatico->idconcepto] = array(
                                            "naturaleza" => $automatico->fkNaturaleza,
                                            "unidad" => "DIA",
                                            "cantidad"=> $itemsAntBoucherPago->cantidad,
                                            "arrNovedades"=> array(),
                                            "valor" => $valorInt,
                                            "tipoGen" => "automaticos"
                                        );
                                    }     
                                }
                                //Verificar si le pago el subsidio para descontarlo
                            }
                        }
                    }
                }
            }
            //FIN CALCULO DE SUBSIDIO DE TRANSPORTE
           
            //AÑADIR NOVEDADES DE TIPO "OTROS" AL ARRAY DE CONCEPTOS
            foreach($arrValorxConcepto as $idConcepto => $arrConcepto){        
                $arrComoCalcula[$idConcepto] = ($arrComoCalcula[$idConcepto] ?? array());
                        
                if($arrConcepto["tipoGen"] != "novedad"){//Si no es una novedad
                    if($arrConcepto["unidad"]=="MES"){
                        array_push($arrComoCalcula[$idConcepto], 
                                "(".$arrConcepto["valor"]." / 30) * ".$periodoGen." días = ".($periodoGen * ($arrConcepto["valor"]/30)));
                                
                        $arrConcepto["valor"] = $periodoGen * ($arrConcepto["valor"]/30);
                        $arrConcepto["cantidad"]=$periodoGen;
                        $arrConcepto["unidad"] = "DÍA";
                    }
                }
                if($arrConcepto["naturaleza"]=="3"){

                    array_push($arrComoCalcula[$idConcepto], 
                    "Naturaleza de descuento ". $arrConcepto["valor"]." * -1");

                    $arrConcepto["valor"] = $arrConcepto["valor"] * -1;
                }            
                $arrValorxConcepto[$idConcepto] = $arrConcepto;
            }    

            foreach($arrValorxConceptoOtros as $idConceptoOtros => $arrConceptoOtros){                
                if($arrConceptoOtros["naturaleza"]=="3"){
                    array_push($arrComoCalcula[$idConceptoOtros], 
                    "Naturaleza de descuento ". $arrConceptoOtros["valor"]." * -1");
                    $arrConceptoOtros["valor"] = $arrConceptoOtros["valor"] * -1;
                }       

                if(isset($arrValorxConcepto[$idConceptoOtros]))
                {
                    $arrValorxConcepto[$idConceptoOtros]["valor"] = $arrValorxConcepto[$idConceptoOtros]["valor"] + $arrConceptoOtros['valor'];

                    $arrValorxConcepto[$idConceptoOtros]["arrNovedades"] = array_merge($arrValorxConcepto[$idConceptoOtros]["arrNovedades"],$arrConceptoOtros["arrNovedades"]);
                }
                else{
                    $arrValorxConcepto[$idConceptoOtros] = $arrConceptoOtros;
                }
            }

            foreach($arrValorxConceptoFueraNomina as $idConcepto => $arrConcepto){        
                $arrComoCalcula[$idConcepto] = ($arrComoCalcula[$idConcepto] ?? array());
                
                if($arrConcepto["tipoGen"] != "novedad"){//Si no es una novedad
                    if($arrConcepto["unidad"]=="MES"){
                        array_push($arrComoCalcula[$idConcepto], 
                                "(".$arrConcepto["valor"]." / 30) * ".$periodoGen." días = ".($periodoGen * ($arrConcepto["valor"]/30)));
                                
                        $arrConcepto["valor"] = $periodoGen * ($arrConcepto["valor"]/30);
                        $arrConcepto["cantidad"]=$periodoGen;
                        $arrConcepto["unidad"] = "DÍA";
                    }
                }
                if($arrConcepto["naturaleza"]=="6"){

                    array_push($arrComoCalcula[$idConcepto], 
                    "Naturaleza de descuento ". $arrConcepto["valor"]." * -1");

                    $arrConcepto["valor"] = $arrConcepto["valor"] * -1;
                }            
                $arrValorxConceptoFueraNomina[$idConcepto] = $arrConcepto;
            }    
            
          

            //FIN AÑADIR NOVEDADES DE TIPO "OTROS" AL ARRAY DE CONCEPTOS

            //TRATAMIENTO CONCEPTOS FIJOS CON TIPO COTIZANTE 51
            if($empleado->fkTipoCotizante == 51 && isset( $arrValorxConcepto[1]) && $arrValorxConcepto[1]["cantidad"]!=0){
                $valorDia = $arrValorxConcepto[1]["valor"]/$arrValorxConcepto[1]["cantidad"];
                $valorHora = $valorDia/8;
                $arrComoCalcula[1] = array();
                array_push($arrComoCalcula[1], 
                "Valor hora => ".$valorHora.", multiplicado por el numeroHoras => ".$numeroHoras." multiplicado por numeroDias => ".$numeroDias."");

                $arrValorxConcepto[1]["valor"] = $valorHora * $numeroHoras * $numeroDias;
                
            }
            //FIN TRATAMIENTO TIPO COTIZANTE 51
            

            

            //En caso de que el empleado tenga un salario integral, todo lo que se comprende dentro de salarios (4) se toma solo el 70%
            $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")
            ->where("gcc.fkGrupoConcepto", "=", "4")->get();//4- Salarial
            foreach($grupoConceptoCalculo as $grupoConcepto){
                if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto != 36){
                    if($empleado->tipoRegimen == "Salario Integral" && $arrValorxConcepto[$grupoConcepto->fkConcepto]["naturaleza"]=="1"){
                        $arrValorxConcepto[$grupoConcepto->fkConcepto]["valor"] =  $arrValorxConcepto[$grupoConcepto->fkConcepto]["valor"] * 0.7;
                    }
                }   
            }




            //INICIO CALCULO MAXIMO 40% PARA NO SALARIAL
            $totalRemuneracion = 0;
            $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")
            ->where("gcc.fkGrupoConcepto", "=", "49")->get();//49- Total Rem
            /*foreach($arrValorxConcepto as $idConcepto => $arrConcepto){
                if($arrConcepto['valor'] > 0 && $idConcepto != 5 && $idConcepto!=36 && $idConcepto!=28){
                    $totalRemuneracion = $totalRemuneracion + $arrConcepto['valor'];
                }                
            }*/

            
            foreach($grupoConceptoCalculo as $grupoConcepto){
                if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto])){
                    $totalRemuneracion = $totalRemuneracion +  $arrValorxConcepto[$grupoConcepto->fkConcepto]["valor"];
            
                }   
            }

            

            if($periodo == 15){
                if(date("d", strtotime($liquidacionNomina->fechaInicio)) == "16"){
                    //Buscar total remuneracion en periodo anterior
                    $fechaPrimeraQuincena = date("Y-m-01", strtotime($liquidacionNomina->fechaInicio));
                    $itemsBoucherMesAnterior = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("ibp.valor,ibp.fkConcepto")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                    ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                    ->where("gcc.fkGrupoConcepto","=","49") //49 - Remuneración Total Para Tope 40%	
                    ->get();
                    
                    foreach($itemsBoucherMesAnterior as $itemBoucherMesAnterior){
                        if($itemBoucherMesAnterior->fkConcepto == 2){//SALARIO INTEGRAL
                            $totalRemuneracion += ($itemBoucherMesAnterior->valor ?? 0)*0.7;
                        }
                        else{
                            $totalRemuneracion += ($itemBoucherMesAnterior->valor ?? 0);                            
                        }                        
                    }

                    //Buscar total remuneracion en la misma quiencena
                    
                    $itemsBoucherMesAnterior = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("ibp.valor,ibp.fkConcepto")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fechaInicio","=",date("Y-m-16", strtotime($liquidacionNomina->fechaInicio)))
                    ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                    ->where("gcc.fkGrupoConcepto","=","49") //49 - Remuneración Total Para Tope 40%	
                    ->get();
                    
                    foreach($itemsBoucherMesAnterior as $itemBoucherMesAnterior){
                        if($itemBoucherMesAnterior->fkConcepto == 2){//SALARIO INTEGRAL
                            $totalRemuneracion += ($itemBoucherMesAnterior->valor ?? 0)*0.7;
                        }
                        else{
                            $totalRemuneracion += ($itemBoucherMesAnterior->valor ?? 0);                            
                        }                        
                    }

                }

                
            }
            else{
                //Buscar total remuneracion en periodo anterior
                $fechaPrimeraQuincena = date("Y-m-01", strtotime($liquidacionNomina->fechaInicio));
                $itemsBoucherMesAnterior = DB::table("item_boucher_pago", "ibp")
                ->selectRaw("ibp.valor,ibp.fkConcepto")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                ->where("gcc.fkGrupoConcepto","=","49") //49 - Remuneración Total Para Tope 40%	
                ->get();
                
                foreach($itemsBoucherMesAnterior as $itemBoucherMesAnterior){
                    if($itemBoucherMesAnterior->fkConcepto == 2){//SALARIO INTEGRAL
                        $totalRemuneracion += ($itemBoucherMesAnterior->valor ?? 0)*0.7;
                    }
                    else{
                        $totalRemuneracion += ($itemBoucherMesAnterior->valor ?? 0);                            
                    }                        
                }
            }
            
            $totalRemuneracion = round($totalRemuneracion);
            
            $totalNoSalarialRemuneracion = 0;
            $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")
                ->where("gcc.fkGrupoConcepto", "=", "5")                       
                ->get();
            foreach($grupoConceptoCalculo as $grupoConcepto){
                if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto!=36){
                    $totalNoSalarialRemuneracion = $totalNoSalarialRemuneracion + floatval($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor']);
                }
            }
            
            if($periodo == 15){
                if(date("d", strtotime($liquidacionNomina->fechaInicio)) == "16"){
                    //Buscar total remuneracion en periodo anterior
                    $fechaPrimeraQuincena = date("Y-m-01", strtotime($liquidacionNomina->fechaInicio));
                    $itemsBoucherMesAnterior = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.valor) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                    ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                    ->where("gcc.fkGrupoConcepto","=","5") //5 - No Salarial nomina
                    ->first();
                    $totalNoSalarialRemuneracion += ($itemsBoucherMesAnterior->suma ?? 0);

                    //Buscar total remuneracion en la misma quiencena                    
                    $itemsBoucherMesAnterior = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("ibp.valor,ibp.fkConcepto")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fechaInicio","=",date("Y-m-16", strtotime($liquidacionNomina->fechaInicio)))
                    ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                    ->where("gcc.fkGrupoConcepto","=","5") //5 - No Salarial nomina
                    ->get();
                    
                    foreach($itemsBoucherMesAnterior as $itemBoucherMesAnterior){
                        if($itemBoucherMesAnterior->fkConcepto == 2){//SALARIO INTEGRAL
                            $totalNoSalarialRemuneracion += ($itemBoucherMesAnterior->valor ?? 0)*0.7;
                        }
                        else{
                            $totalNoSalarialRemuneracion += ($itemBoucherMesAnterior->valor ?? 0);                            
                        }                        
                    }
                }
            }
            else{
                $fechaPrimeraQuincena = date("Y-m-01", strtotime($liquidacionNomina->fechaInicio));
                $itemsBoucherMesAnterior = DB::table("item_boucher_pago", "ibp")
                ->selectRaw("Sum(ibp.valor) as suma")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                ->where("gcc.fkGrupoConcepto","=","5") //5 - No Salarial nomina
                ->first();
                $totalNoSalarialRemuneracion += ($itemsBoucherMesAnterior->suma ?? 0);
            }
            
            
            $limite40Por = $totalRemuneracion * 0.4;
            //dd($limite40Por, $totalRemuneracion, $totalNoSalarialRemuneracion);
            if($totalNoSalarialRemuneracion > $limite40Por && $limite40Por > 0){
                $valorInt = round($totalNoSalarialRemuneracion - $limite40Por);
                
                $arrValorxConcepto[32] = array(
                    "naturaleza" => '1',
                    "unidad" => "DÍA",
                    "cantidad"=> 0,
                    "arrNovedades"=> array(),
                    "valor" => $valorInt,
                    "tipoGen" => "automaticos"
                );   
            }
            
            //FIN CALCULO MAXIMO 40% PARA NO SALARIAL
            



            //INICIO PARAFISCALES

            //Cambio dias iguales a los del salario basico 
            

            $arrBoucherPago = array();
            $arrParafiscales = array();
            $ibcGeneral = 0;
            $ibcArl = 0;
            $salarioMaximo = 0;
            $arrTiposLiquidacionNormal = ["1","2","3","4","5","6","9","12"];
            if(in_array($tipoliquidacion, $arrTiposLiquidacionNormal)){

                $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")
                ->where("gcc.fkGrupoConcepto", "=", '47')//47 - IBC Gen
                ->get();
                
                //dd($arrValorxConcepto);
                foreach($grupoConceptoCalculo as $grupoConcepto){
                    if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto!=36){
                        
                        $arrComoCalcula[18] = ($arrComoCalcula[18] ?? array());
                        $arrComoCalcula[19] = ($arrComoCalcula[19] ?? array());
                        array_push($arrComoCalcula[18], "Se toma para la base el concepto #".$grupoConcepto->fkConcepto." por valor de : ".round($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor'])."");
                        array_push($arrComoCalcula[19], "Se toma para la base el concepto #".$grupoConcepto->fkConcepto." por valor de : ".round($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor'])."");
                        $ibcGeneral = $ibcGeneral + floatval($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor']);                  
                    }
                }              
                

                
                

                $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")
                ->where("gcc.fkGrupoConcepto", "=", '45')//45->ibcArl
                ->get();
                foreach($grupoConceptoCalculo as $grupoConcepto){
                    if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto!=36){
                        $ibcArl = $ibcArl + floatval($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor']);
                        
                    }
                }
                
                $conceptosFijosParaIBC = DB::table("conceptofijo", "cf")
                ->where("cf.fkEmpleado", "=", $empleado->idempleado)  
                ->where("cf.fkPeriodoActivo","=", $periodoActivoReintegro->idPeriodo)
                ->whereIn("cf.fkConcepto",[1,2,53,54,154])
                ->where("cf.fkEstado", "=", "1")
                ->first();

                $periodoAportes = $periodoGen;
                if(isset($arrValorxConcepto[29])){
                    $periodoAportes += $arrValorxConcepto[29]["cantidad"];
                } 

                $variables = DB::table("variable")->where("idVariable","=","1")->first();
                $salarioMinimoDiaOtromas = $variables->valor / 30;

                $salarioMaximo = ($salarioMinimoDiaOtromas * 30) * 25;

                
                if(($tipoliquidacion == "1" 
                || $tipoliquidacion == "2" 
                || $tipoliquidacion == "3" 
                || $tipoliquidacion == "4" 
                || $tipoliquidacion == "5" 
                || $tipoliquidacion == "6" 
                || $tipoliquidacion == "9") && $ibcGeneral < ($salarioMinimoDia * $periodoAportes) &&
                ($empleado->fkTipoCotizante != 51) && $periodoAportes >= 1){
                    $ibcGeneral = ($salarioMinimoDia * $periodoAportes);
                }
                

                if($ibcArl > $salarioMaximo){
                    $ibcArl = $salarioMaximo;
                }
                //dd($ibcGeneral, $salarioMaximo, $salarioMinimoDia);

                if($ibcGeneral > $salarioMaximo){
                    $ibcGeneral = $salarioMaximo;
                }
                


                if($periodo == 15){
                    if(substr($liquidacionNomina->fechaInicio,8,2) == "16"){
                        $fechaPrimeraQuincena = substr($liquidacionNomina->fechaInicio,0,8)."01";
                    } 
                }
            }

            
            
            
            //INICIO CALCULAR IBC MES COMPLETO
            $ibcGeneral2 = $ibcGeneral;
            
            $ibcArl2  = $ibcArl;
            
            
            
            if(in_array($tipoliquidacion, $arrTiposLiquidacionNormal) && isset($fechaPrimeraQuincena) && ($periodoAportes > 0 || $diasNoTrabajados > 0 || $ibcGeneral > 0)){ 
                
                if($empleado->tipoRegimen != "Salario Integral"){

                    
                    $itemsBoucherIbcOtrosMesAnterior = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.valor) as suma, bp.periodoPago as sumaPerPago, bp.diasTrabajados as sumaDiaTrab")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                    ->where("bp.ibc_eps",">","0")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                    ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                    ->whereIn("ln.fkTipoLiquidacion", $arrTiposLiquidacionNormal)
                    ->where("gcc.fkGrupoConcepto","=","47")  //47 - IBC Gen
                    ->where("ln.fkEstado","=","5")
                    ->first();
                    
                    
                    if(isset($itemsBoucherIbcOtrosMesAnterior->suma)){
                        $periodoInterno = $itemsBoucherIbcOtrosMesAnterior->sumaDiaTrab;                        
                        $ibcInterno = $itemsBoucherIbcOtrosMesAnterior->suma;
                        
                        if(isset($conceptosFijosParaIBC) && intval($conceptosFijosParaIBC->valor) < intval($salarioMinimoDia * 30)){
                            $ibcInterno = ($salarioMinimoDia)*$periodoInterno;
                            
                        }
                     
                        $ibcGeneral2 += $ibcInterno;
                        
                    }
                    
                    
                    $itemsBoucherIbcOtrosMesAnterior = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.valor) as suma, bp.periodoPago as sumaPerPago, bp.diasTrabajados as sumaDiaTrab")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                    ->where("bp.ibc_eps",">","0")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                    ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                    ->whereIn("ln.fkTipoLiquidacion", $arrTiposLiquidacionNormal)
                    ->where("gcc.fkGrupoConcepto","=","45") //45 - ibc Arl
                    ->where("ln.fkEstado","=","5")
                    ->first();    
                    if(isset($itemsBoucherIbcOtrosMesAnterior->suma)){

                        $periodoInterno = $itemsBoucherIbcOtrosMesAnterior->sumaDiaTrab;                        
                        $ibcInterno = $itemsBoucherIbcOtrosMesAnterior->suma;
                        if(isset($conceptosFijosParaIBC) && intval($conceptosFijosParaIBC->valor) < intval($salarioMinimoDia * 30)){
                            $ibcInterno = ($salarioMinimoDia)*$periodoInterno;
                        }
                      

                        $ibcArl2 += $ibcInterno;
                    }
                
                }           
                else{
                    $itemsBoucherIbcOtrosMesAnterior = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.valor) as suma, bp.periodoPago as sumaPerPago, bp.diasTrabajados as sumaDiaTrab")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")      
                    ->where("bp.ibc_eps",">","0")          
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                    ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                    ->where("gcc.fkGrupoConcepto","=","47") //47 - IBC Gen
                    ->where("ln.fkEstado","=","5")
                    ->first();
                   
                
                    if(isset($itemsBoucherIbcOtrosMesAnterior->suma)){
                        $periodoInterno = $itemsBoucherIbcOtrosMesAnterior->sumaDiaTrab;                        
                        $ibcInterno = $itemsBoucherIbcOtrosMesAnterior->suma*0.7;
                        //$ibcInterno = ($ibcInterno / $itemsBoucherIbcOtrosMesAnterior->sumaDiaTrab)* $periodoInterno;
                        $ibcGeneral2 += $ibcInterno;

                        if(($tipoliquidacion == "1" 
                        || $tipoliquidacion == "2" 
                        || $tipoliquidacion == "3" 
                        || $tipoliquidacion == "4" 
                        || $tipoliquidacion == "5" 
                        || $tipoliquidacion == "6" 
                        || $tipoliquidacion == "9") && $ibcGeneral2 < ($salarioMinimoDia * $periodoInterno) &&
                        ($empleado->fkTipoCotizante != 51) && $periodoAportes == 0){
                            $ibcGeneral2 = ($salarioMinimoDia * $periodoInterno);
                        }
                        //dd($ibcGeneral, $periodoAportes, $itemsBoucherIbcOtrosMesAnterior);
                    }


                    $itemsBoucherIbcOtrosMesAnterior = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.valor) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")  
                    ->where("bp.ibc_eps",">","0")              
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                    ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                    ->where("gcc.fkGrupoConcepto","=","45") //45 - ibc Arl
                    ->where("ln.fkEstado","=","5")
                    ->first();
        
                    if(isset($itemsBoucherIbcOtrosMesAnterior->suma)){
                        $ibcArl2 = $ibcArl2 + $itemsBoucherIbcOtrosMesAnterior->suma;
                    }
                }
            }
            

            
            if(in_array($tipoliquidacion, $arrTiposLiquidacionNormal) && ((isset($fechaPrimeraQuincena) && $fechaPrimeraQuincena != $fechaInicio) || !isset($fechaPrimeraQuincena)) && ($periodoAportes > 0 || $diasNoTrabajados > 0 || $ibcGeneral > 0)){
                //VERIFICAR QUINCENA ACTUAL
                if($empleado->tipoRegimen != "Salario Integral"){
                    $itemsBoucherIbcOtrosMesAnterior = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.valor) as suma, bp.periodoPago as sumaPerPago, bp.diasTrabajados as sumaDiaTrab")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")         
                    ->where("bp.ibc_eps",">","0")       
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fechaInicio","=",$fechaInicio)
                    ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                    ->whereIn("ln.fkTipoLiquidacion", $arrTiposLiquidacionNormal)
                    ->where("gcc.fkGrupoConcepto","=","47")  //47 - IBC Gen
                    ->where("ln.fkEstado","=","5")
                    ->first();

                    //dd($itemsBoucherIbcOtrosMesAnterior, $arrTiposLiquidacionNormal);
                    
                    if(isset($itemsBoucherIbcOtrosMesAnterior->suma)){
                        $periodoInterno = $itemsBoucherIbcOtrosMesAnterior->sumaDiaTrab;                        
                        $ibcInterno = $itemsBoucherIbcOtrosMesAnterior->suma;
                        //$ibcInterno = ($ibcInterno / $itemsBoucherIbcOtrosMesAnterior->sumaDiaTrab)* $periodoInterno;
                        if(isset($conceptosFijosParaIBC) && intval($conceptosFijosParaIBC->valor) < intval($salarioMinimoDia * 30)){
                            $ibcInterno = ($salarioMinimoDia)*$periodoInterno;
                        }
                        
                        $ibcGeneral2 += $ibcInterno;
                        
                    }
                    //dd($ibcGeneral2, $itemsBoucherIbcOtrosMesAnterior);
                    $itemsBoucherIbcOtrosMesAnterior = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.valor) as suma, bp.periodoPago as sumaPerPago, bp.diasTrabajados as sumaDiaTrab")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")        
                    ->where("bp.ibc_eps",">","0")        
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fechaInicio","=",$fechaInicio)
                    ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                    ->whereIn("ln.fkTipoLiquidacion", $arrTiposLiquidacionNormal)
                    //->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9", "12"])
                    ->where("ln.fkEstado","=","5")
                    ->where("gcc.fkGrupoConcepto","=","45") //45 - ibc Arl
                    ->first();    
                    if(isset($itemsBoucherIbcOtrosMesAnterior->suma)){
                        
                        $periodoInternoArl = $itemsBoucherIbcOtrosMesAnterior->sumaDiaTrab;                        
                        $ibcInternoArl = $itemsBoucherIbcOtrosMesAnterior->suma;

                        if(isset($conceptosFijosParaIBC) && intval($conceptosFijosParaIBC->valor) < intval($salarioMinimoDia * 30)){
                            $ibcInternoArl = ($salarioMinimoDia)*$periodoInternoArl;
                        }
                        $ibcArl2 += $ibcInternoArl;
                    }
                
                }           
                else{
                    $itemsBoucherIbcOtrosMesAnterior = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.valor) as suma, bp.periodoPago as sumaPerPago, bp.diasTrabajados as sumaDiaTrab")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fechaInicio","=",$fechaInicio)
                    ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                    ->where("ln.fkEstado","=","5")
                    ->whereIn("ln.fkTipoLiquidacion", $arrTiposLiquidacionNormal)
                    //->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9", "12"])
                    ->where("gcc.fkGrupoConcepto","=","47") //47 - IBC Gen
                    ->first();
        
                    if(isset($itemsBoucherIbcOtrosMesAnterior->suma)){
                        $periodoInterno = $itemsBoucherIbcOtrosMesAnterior->sumaDiaTrab;                        
                        $ibcInterno = $itemsBoucherIbcOtrosMesAnterior->suma*0.7;
                        //$ibcInterno = ($ibcInterno / $itemsBoucherIbcOtrosMesAnterior->sumaDiaTrab)* $periodoInterno;
                        $ibcGeneral2 += $ibcInterno;

                    }

                    $itemsBoucherIbcOtrosMesAnterior = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.valor) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fechaInicio","=",$fechaInicio)
                    ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                    ->whereIn("ln.fkTipoLiquidacion", $arrTiposLiquidacionNormal)
                    //->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9", "12"])
                    ->where("gcc.fkGrupoConcepto","=","45") //45 - ibc Arl
                    ->where("ln.fkEstado","=","5")
                    ->first();
        
                    if(isset($itemsBoucherIbcOtrosMesAnterior->suma)){
                        $ibcArl2 = $ibcArl2 + $itemsBoucherIbcOtrosMesAnterior->suma;
                    }
                }
            }
            
            
            $ibcGeneralAFP_SALUD = $ibcGeneral2;
            if($ibcGeneralAFP_SALUD > $salarioMaximo){
                $ibcGeneralAFP_SALUD = $salarioMaximo;
            }
            $arrBoucherPago["ibc_afp"] = round($ibcGeneralAFP_SALUD);
            $arrBoucherPago["ibc_eps"] = round($ibcGeneralAFP_SALUD);
            $arrBoucherPago["ibc_arl"] = round($ibcArl2);
            $arrBoucherPago["ibc_ccf"] = round($ibcGeneral2);
            $arrBoucherPago["ibc_otros"] = round($ibcGeneral2);
           
            
            //INICIO CALCULAR IBC COMO SALARIO MINIMO PARA EMPLEADOS SENA
            if( $empleado->fkTipoCotizante == "12" ||
                $empleado->fkTipoCotizante == "19" ||
                $empleado->fkTipoCotizante == "23"){
                $arrBoucherPago["ibc_eps"] = ($salarioMinimoDia * 30);
                $ibcGeneral2 = ($salarioMinimoDia * 30);
            }

            if($empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23"){
                $arrBoucherPago["ibc_arl"] = ($salarioMinimoDia * 30);
            }

            
            //FIN CALCULAR IBC COMO SALARIO MINIMO PARA EMPLEADOS SENA
            $valorSalarioMinimo = 30 * $salarioMinimoDia;
            
            //INICIO CALCULAR IBC TIPO COTIZANTE 51
            if($empleado->fkTipoCotizante == 51){
                
                $arrBoucherPago["ibc_eps"] = 0;
                $arrBoucherPago["ibc_arl"] = 0;
                $arrBoucherPago["ibc_ccf"] = 0;
                $arrBoucherPago["ibc_otros"] = 0;
            }
            //FIN CALCULAR IBC TIPO COTIZANTE 51

            $variablesParafiscales = DB::table("variable")
            ->where("idVariable",">=","49")
            ->where("idVariable","<=","56")
            ->get();
            $varParafiscales = array();
            foreach($variablesParafiscales as $variablesParafiscal){
                $varParafiscales[$variablesParafiscal->idVariable] = $variablesParafiscal->valor;
            }

            //INICIO CALCULO EPS EMPLEADO
            $arrComoCalcula[18] = ($arrComoCalcula[18] ?? array());
            if($empleado->fkTipoCotizante == "12" || $empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23"){
                $valorEpsEmpleado = 0;
            }
            else{
                $valorEpsEmpleado = $arrBoucherPago["ibc_eps"] * $varParafiscales[49];                
                array_push($arrComoCalcula[18], "Base: ".number_format($arrBoucherPago["ibc_eps"], 0,",",".")." por ".($varParafiscales[49]*100)."%");
            }            
            $valorEpsEmpleado = round($valorEpsEmpleado);     
            //VERIFICAR VALOR PAGADO EN LA QUINCENA ANTERIOR Y RESTARLO AL ACTUAL
            
            if(in_array($tipoliquidacion, $arrTiposLiquidacionNormal) && $periodo == 15 && ($periodoAportes > 0 || $diasNoTrabajados > 0 || $ibcGeneral > 0)){
                if(substr($liquidacionNomina->fechaInicio,8,2) == "16"){
                    $itemsBoucherPagoSaludMesAntVarias = DB::table("item_boucher_pago","ibp")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=", $empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fkEstado","=","5")//Terminada
                    ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                    ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","12"])
                    ->where("ibp.fkConcepto","=","18")//SS
                    ->get();
                    foreach($itemsBoucherPagoSaludMesAntVarias as $itemsBoucherPagoSaludMesAnt){
                        $valorEpsEmpleado -= ($itemsBoucherPagoSaludMesAnt->descuento ?? 0);
                        array_push($arrComoCalcula[18], "Se resta lo pagado en la anterior quincena: ".number_format(($itemsBoucherPagoSaludMesAnt->descuento ?? 0), 0,",","."));
                    }
                }
            }

            $itemsBoucherPagoSaludMismaFe = DB::table("item_boucher_pago","ibp")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->where("bp.fkEmpleado","=", $empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
            ->where("ln.fkEstado","=","5")//Terminada
            ->where("ln.fechaInicio","=",$fechaInicio)
            ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
            ->where("ibp.fkConcepto","=","18")//SS
            ->first();
            $periodoAportes = ($periodoAportes ?? 0);


            if(($itemsBoucherPagoSaludMismaFe->descuento ?? 0)>0 && ($periodoAportes > 0 || $diasNoTrabajados > 0 || $ibcGeneral > 0)){
                $valorEpsEmpleado -= ($itemsBoucherPagoSaludMismaFe->descuento ?? 0);
                array_push($arrComoCalcula[18], "Se resta lo pagado en esta quincena pero otra liquidación: ".number_format(($itemsBoucherPagoSaludMismaFe->descuento ?? 0), 0,",","."));
            }
            if(in_array($tipoliquidacion, $arrTiposLiquidacionNormal) && $empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23"){
                if(isset($arrValorxConcepto[18])){
                    array_push($arrComoCalcula[18], "Se suma con el acumulado de : ".($arrValorxConcepto[18]["valor"]*-1)."");
                    $arrValorxConcepto[18] = array(
                        "naturaleza" => "3",
                        "unidad" => "DÍA",
                        "cantidad"=> $periodoAportes,
                        "arrNovedades"=> array(),
                        "valor" => ($arrValorxConcepto[18]["valor"] - $valorEpsEmpleado),
                        "tipoGen" => "automaticos"
                    );
                }
                else{
                    
                    $arrValorxConcepto[18] = array(
                        "naturaleza" => "3",
                        "unidad" => "DÍA",
                        "cantidad"=> $periodoAportes,
                        "arrNovedades"=> array(),
                        "valor" => $valorEpsEmpleado*-1 ,
                        "tipoGen" => "automaticos"
                    );
            
                }
            }
            //FIN Calculo EPS
            
            
            //INICIO CALCULAR IBC ARL PARA SENA TIPO 19
            if($ibcGeneral2 == 0 && ($empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23")){
                $arrBoucherPago["ibc_arl"] = ($salarioMinimoDia * 30);
            }
            //FIN CALCULAR IBC MES COMPLETO
            $arrBoucherPago["ibc_ccf"] = round($ibcGeneral2);
            $arrBoucherPago["ibc_otros"] = round($ibcGeneral2);
            
            if($empleado->fkTipoCotizante == 51){
                $arrBoucherPago["ibc_eps"] = 0;
                $arrBoucherPago["ibc_arl"] = 0;
                $arrBoucherPago["ibc_ccf"] = 0;
                $arrBoucherPago["ibc_otros"] = 0;
            }

            //INICIO CALCULO EPS EMPLEADOR
            if($empleado->fkTipoCotizante == "12" || $empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23"){
                $valorEpsEmpleador = $arrBoucherPago["ibc_eps"] * ($varParafiscales[50] + $varParafiscales[49]);
            }
            else{
                $valorEpsEmpleador = $ibcGeneral2 * $varParafiscales[50];
            }        
            $valorEpsEmpleador = round($valorEpsEmpleador);        
            $arrParafiscales["eps"] = $valorEpsEmpleador;
            //FIN CALCULO EPS EMPLEADOR

            $existeConceptoUPCLiq = DB::table("conceptosxtipoliquidacion","ctl")
            ->where("ctl.fkConcepto","=","79")
            ->where("ctl.fkTipoLiquidacion","=",$tipoliquidacion) 
            ->distinct()
            ->get();
            //INICIO CALCULO UPC
            if(sizeof($existeConceptoUPCLiq)>0){

                $upcAdicionales = DB::table("upcadicional","u")
                ->select("u.*","ti.siglaPila","ub.zonaUPC")
                ->join("tipoidentificacion as ti","ti.idtipoIdentificacion","=","u.fkTipoIdentificacion")
                ->join("ubicacion as ub", "ub.idubicacion", "=","u.fkUbicacion")
                ->where("u.fkEmpleado","=",$empleado->idempleado)
                ->get();

                //dd($upcAdicionales);

                $valorUpcAd = 0;
                foreach($upcAdicionales as $upcAdicional){
                    $arrComoCalcula[79] = ($arrComoCalcula[79] ?? array());

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

                    foreach($tarifasUpc as $tarifaUpc){
                        if(!isset($tarifaUpc->fkGenero) || $tarifaUpc->fkGenero == $upcAdicional->fkGenero){
                            $valorUpcAd = $valorUpcAd + $tarifaUpc->valor;
                            array_push($arrComoCalcula[79], "Edad: ".$edad." y Genero ".$upcAdicional->fkGenero." tarifa de: $".number_format($tarifaUpc->valor, 0, ",", "."));
                        }
                    }
                    

                    if($periodo == 15 && substr($liquidacionNomina->fechaInicio,8,2) == "01" && $upcAdicional->fkPeriocidad == "2"){

                        if($valorUpcAd > 0){
                            if(isset($arrValorxConcepto[79])){
                                
                                array_push($arrComoCalcula[79], "Se suma con acumulado de: ".($arrValorxConcepto[79]["valor"] *-1));
                                $arrValorxConcepto[79] = array(
                                    "naturaleza" => "3",
                                    "unidad" => "UNIDAD",
                                    "cantidad"=> "0",
                                    "arrNovedades"=> array(),
                                    "valor" => ($arrValorxConcepto[79]["valor"] - $valorUpcAd),
                                    "tipoGen" => "automaticos"
                                );
                            }
                            else{
                                $arrValorxConcepto[79] = array(
                                    "naturaleza" => "3",
                                    "unidad" => "UNIDAD",
                                    "cantidad"=> "0",
                                    "arrNovedades"=> array(),
                                    "valor" => $valorUpcAd*-1 ,
                                    "tipoGen" => "automaticos"
                                );
                            }
                        
                        }
                    }
                    else if($periodo == 15 && substr($liquidacionNomina->fechaInicio,8,2) == "16" && $upcAdicional->fkPeriocidad == "3"){
                        if($valorUpcAd > 0){
                            if(isset($arrValorxConcepto[79])){
                                array_push($arrComoCalcula[79], "Se suma con acumulado de: ".($arrValorxConcepto[79]["valor"] *-1));
                                $arrValorxConcepto[79] = array(
                                    "naturaleza" => "3",
                                    "unidad" => "UNIDAD",
                                    "cantidad"=> "0",
                                    "arrNovedades"=> array(),
                                    "valor" => ($arrValorxConcepto[79]["valor"] - $valorUpcAd),
                                    "tipoGen" => "automaticos"
                                );
                            }
                            else{
                                $arrValorxConcepto[79] = array(
                                    "naturaleza" => "3",
                                    "unidad" => "UNIDAD",
                                    "cantidad"=> "0",
                                    "arrNovedades"=> array(),
                                    "valor" => $valorUpcAd*-1 ,
                                    "tipoGen" => "automaticos"
                                );
                            }
                        
                        }
                    }
                    else if($periodo == 15 && $upcAdicional->fkPeriocidad == "4"){
                        if($valorUpcAd > 0){
                            if(isset($arrValorxConcepto[79])){
                                array_push($arrComoCalcula[79], "Se suma con acumulado de: ".($arrValorxConcepto[79]["valor"] *-1));
                                $arrValorxConcepto[79] = array(
                                    "naturaleza" => "3",
                                    "unidad" => "UNIDAD",
                                    "cantidad"=> "0",
                                    "arrNovedades"=> array(),
                                    "valor" => ($arrValorxConcepto[79]["valor"] - ($valorUpcAd / 2)),
                                    "tipoGen" => "automaticos"
                                );
                            }
                            else{
                                $arrValorxConcepto[79] = array(
                                    "naturaleza" => "3",
                                    "unidad" => "UNIDAD",
                                    "cantidad"=> "0",
                                    "arrNovedades"=> array(),
                                    "valor" => ($valorUpcAd / 2)*-1,
                                    "tipoGen" => "automaticos"
                                );
                            }
                        
                        }
                    }
                    else if($periodo == 30){
                        if($valorUpcAd > 0){
                            if(isset($arrValorxConcepto[79])){
                                array_push($arrComoCalcula[79], "Se suma con acumulado de: ".($arrValorxConcepto[79]["valor"] *-1));
                                $arrValorxConcepto[79] = array(
                                    "naturaleza" => "3",
                                    "unidad" => "UNIDAD",
                                    "cantidad"=> "0",
                                    "arrNovedades"=> array(),
                                    "valor" => ($arrValorxConcepto[79]["valor"] - $valorUpcAd),
                                    "tipoGen" => "automaticos"
                                );
                            }
                            else{
                                $arrValorxConcepto[79] = array(
                                    "naturaleza" => "3",
                                    "unidad" => "UNIDAD",
                                    "cantidad"=> "0",
                                    "arrNovedades"=> array(),
                                    "valor" => $valorUpcAd*-1 ,
                                    "tipoGen" => "automaticos"
                                );
                            }
                        
                        }
                    }
                }
                
                
            }
            //FIN CALCULO UPC
           

            //Calculo AFP
            $valorAfpEmpleador = 0;
            $valorAfpEmpleado = 0;
            
            if($empleado->esPensionado == 0 && ($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23")){

                $valorAfpEmpleado = $arrBoucherPago["ibc_afp"] * $varParafiscales[51];            
                $valorAfpEmpleado = round($valorAfpEmpleado);        
                
                $arrComoCalcula[19] = ($arrComoCalcula[19] ?? array());
                array_push($arrComoCalcula[19], "Base: $".number_format($arrBoucherPago["ibc_afp"],0,",",".")." por ".($varParafiscales[51] * 100)."%");

                if(in_array($tipoliquidacion, $arrTiposLiquidacionNormal) && $periodo == 15 && ($periodoAportes > 0 || $diasNoTrabajados > 0 || $ibcGeneral > 0)){
                    if(substr($liquidacionNomina->fechaInicio,8,2) == "16"){
                        $itemsBoucherPagoPensionMesAntVarias = DB::table("item_boucher_pago","ibp")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->where("bp.fkEmpleado","=", $empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.fkEstado","=","5")//Terminada
                        ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                        ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","12"])
                        ->where("ibp.fkConcepto","=","19")//SS
                        ->get();
                        foreach($itemsBoucherPagoPensionMesAntVarias as $itemsBoucherPagoPensionMesAnt){
                            $valorAfpEmpleado -= ($itemsBoucherPagoPensionMesAnt->descuento ?? 0);
                            array_push($arrComoCalcula[19], "Se resta lo pagado en la anterior quincena: ".number_format(($itemsBoucherPagoPensionMesAnt->descuento ?? 0), 0,",","."));        
                        }                        
                    }
                }
                $itemsBoucherPagoPensionMismaFe = DB::table("item_boucher_pago","ibp")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=", $empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("ln.fkEstado","=","5")//Terminada
                ->where("ln.fechaInicio","=",$fechaInicio)
                ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                ->where("ibp.fkConcepto","=","19")//SS
                ->first();

                if(($itemsBoucherPagoPensionMismaFe->descuento ?? 0)>0 && ($periodoAportes > 0 || $diasNoTrabajados > 0 || $ibcGeneral > 0)){
                    $valorAfpEmpleado -= ($itemsBoucherPagoPensionMismaFe->descuento ?? 0);
                    array_push($arrComoCalcula[19], "Se resta lo pagado en esta quincena pero otra liquidación: ".number_format(($itemsBoucherPagoPensionMismaFe->descuento ?? 0), 0,",","."));
                }
                if(in_array($tipoliquidacion, $arrTiposLiquidacionNormal)){
                    if(isset($arrValorxConcepto[19])){

                        array_push($arrComoCalcula[19], "Se suma con el acumulado de : ".($arrValorxConcepto[19]["valor"]*-1)."");
                        $arrValorxConcepto[19] = array(
                            "naturaleza" => "3",
                            "unidad" => "DÍA",
                            "cantidad"=> $periodoAportes,
                            "arrNovedades"=> array(),
                            "valor" => ($arrValorxConcepto[19]["valor"] - $valorAfpEmpleado) ,
                            "tipoGen" => "automaticos"
                        );
                    }
                    else{
                        $arrValorxConcepto[19] = array(
                            "naturaleza" => "3",
                            "unidad" => "DÍA",
                            "cantidad"=> $periodoAportes,
                            "arrNovedades"=> array(),
                            "valor" => $valorAfpEmpleado*-1 ,
                            "tipoGen" => "automaticos"
                        );
                    }
                }
                
                $nuevoIbc = $ibcGeneral2;
                if(isset($arrValorxConcepto[24])){
                    if(isset($arrValorxConcepto[24]['valorAus'])){
                        $nuevoIbc = $nuevoIbc + floatval($arrValorxConcepto[24]['valorAus']);
                    }
                    else{
                        $nuevoIbc = $nuevoIbc - floatval($arrValorxConcepto[24]['valor']);
                    }
                            
                }
                if($empleado->fkTipoCotizante == 51){
                    if($numeroDias>=1 && $numeroDias<=7){
                        $nuevoIbc = $valorSalarioMinimo*(1/4);
                    }
                    else if($numeroDias>=8 && $numeroDias<=14){
                        $nuevoIbc = $valorSalarioMinimo*(2/4);
                    }
                    else if($numeroDias>=15 && $numeroDias<=21){
                        $nuevoIbc = $valorSalarioMinimo*(3/4);
                    }
                    else if($numeroDias>=22 && $numeroDias<=30){
                        $nuevoIbc = $valorSalarioMinimo*(4/4);
                    }
                    $arrBoucherPago["ibc_afp"] = round($nuevoIbc); 
                    $valorAfpEmpleador = $nuevoIbc * ($varParafiscales[52] + $varParafiscales[51]);
                    $valorAfpEmpleador =  $valorAfpEmpleador -  $valorAfpEmpleado;
                    $valorAfpEmpleador = round($valorAfpEmpleador);
                }
                else{
                    $valorAfpEmpleador = $nuevoIbc * $varParafiscales[52];
                    $valorAfpEmpleador = round($valorAfpEmpleador);
                }           
            }
            else{
                $arrBoucherPago["ibc_afp"] = 0;
            }
            

            if($empleado->fkTipoCotizante == "12" || $empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23"){
                $arrBoucherPago["ibc_afp"] = 0;
            }
            
            $arrParafiscales["afp"] = $valorAfpEmpleador;
            
            //Calculo ARL
            $arl = DB::table('nivel_arl', 'na')
            ->where("na.idnivel_arl", "=", $empleado->fkNivelArl)
            ->first();
            
            if(isset($arl->porcentaje)){
                $valorArlEmpleador = $arrBoucherPago["ibc_arl"] * (floatval($arl->porcentaje) / 100);
                $valorArlEmpleador = round($valorArlEmpleador);
                $arrParafiscales["arl"] = $valorArlEmpleador;
                $arrBoucherPago["ibc_arl"] = $ibcGeneral2;
                if(($empleado->fkTipoCotizante == "51")){
                    $arrBoucherPago["ibc_arl"] = $arrBoucherPago["ibc_afp"] ;
                }
                if($ibcGeneral2 == 0 && ($empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23")){
                    $arrBoucherPago["ibc_arl"] = ($salarioMinimoDia * 30);
                }
            }
            else{
                $arrParafiscales["arl"] = 0;
            }
            
            if(($empleado->fkTipoCotizante == "51")){
                $arrBoucherPago["ibc_ccf"] = $arrBoucherPago["ibc_afp"] ;
            }

            //Calculo CCF
            $valorCCFEmpleador = $arrBoucherPago["ibc_ccf"] * $varParafiscales[53];
            $valorCCFEmpleador = round($valorCCFEmpleador);
            $arrParafiscales["ccf"] = $valorCCFEmpleador;
                    
            if($empresa->exento == "0" || $arrBoucherPago["ibc_otros"] > ($varParafiscales[56] * $valorSalarioMinimo) ||
                ($empresa->pagoParafiscales == "1" && ($arrBoucherPago["ibc_otros"]*100/70) > ($varParafiscales[56] * $valorSalarioMinimo) && $empleado->tipoRegimen == "Salario Integral")
            ){
                //Calculo ICBF
                $valorICBFEmpleador = $arrBoucherPago["ibc_otros"] * $varParafiscales[54];
                $valorICBFEmpleador = round($valorICBFEmpleador);
                $arrParafiscales["icbf"] = $valorICBFEmpleador;

                //Calculo SENA
                $valorSenaEmpleador = $arrBoucherPago["ibc_otros"] * $varParafiscales[55];
                $valorSenaEmpleador = round($valorSenaEmpleador);
                $arrParafiscales["sena"] = $valorSenaEmpleador;           
            }
            else{
                $arrParafiscales["icbf"] = 0;
                $arrParafiscales["sena"] = 0;
                if($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23"){
                    $arrParafiscales["eps"] = 0;
                }            
                $arrBoucherPago["ibc_otros"] = 0;
            }
            
            

            //Nota: si ya se encuentra pensionado no se aplica APORTE FONDO DE SOLIDARIDAD
            if($empleado->esPensionado == 0 && ($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23")){

                
                $aporteFondoSoliradidad = DB::table("concepto", "c")
                ->join("conceptosxtipoliquidacion AS ctl", "ctl.fkConcepto","=","c.idconcepto")
                ->where("ctl.fkTipoLiquidacion","=",$tipoliquidacion)     
                ->whereIn("c.idconcepto",["33"])
                ->distinct()
                ->get();

                
                $porcentajeDescuento = 0;            
                if($empleado->fkTipoCotizante == 51){
                    $valorSalario = $arrBoucherPago["ibc_afp"];
                }
                else{
                    $valorSalario = $arrBoucherPago["ibc_eps"];
                }
                
                
                $variablesAporteFondo = DB::table("variable")->whereIn("idVariable",[11,12,13,14,15])->get();
                $varAporteFondo = array();
                foreach($variablesAporteFondo as $variablesAporteFond){
                    $varAporteFondo[$variablesAporteFond->idVariable] = $variablesAporteFond->valor;
                }
                $variables = DB::table("variable")->where("idVariable","=","1")->first();
                $valorSalarioMinimo = $variables->valor;
                
                
                foreach($aporteFondoSoliradidad as $automatico){
                    $arrComoCalcula[$automatico->idconcepto] = ($arrComoCalcula[$automatico->idconcepto] ?? array());


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

                    array_push($arrComoCalcula[$automatico->idconcepto],
                        "Se aplica un porcentaje de descuento de ".($porcentajeDescuento * 100)."%");
                    array_push($arrComoCalcula[$automatico->idconcepto],
                        "Ibc usado $".number_format($valorSalario, 0,".",",")."");
                    
                    $valorFormula = $valorSalario * $porcentajeDescuento;
                    $valorInt = $valorFormula;

                    $itemsBoucherPagoFondoSoliMismaFe = DB::table("item_boucher_pago","ibp")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=", $empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fkEstado","=","5")//Terminada
                    ->where("ln.fechaInicio","=",$fechaInicio)
                    ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                    ->where("ibp.fkConcepto","=","33")//FONDO SOL
                    ->first();

                    if(($itemsBoucherPagoFondoSoliMismaFe->descuento ?? 0)>0 && ($periodoAportes > 0 || $diasNoTrabajados > 0 || $ibcGeneral > 0)){
                        $valorInt -= ($itemsBoucherPagoFondoSoliMismaFe->descuento ?? 0);
                        array_push($arrComoCalcula[33], "Se resta lo pagado en esta quincena pero otra liquidación: ".number_format(($itemsBoucherPagoFondoSoliMismaFe->descuento ?? 0), 0,",","."));
                    }

                    if($valorInt > 0){


                        if($periodo == 30){

                            if(isset($arrValorxConcepto[$automatico->idconcepto])){
                                $arrValorxConcepto[$automatico->idconcepto] = array(
                                    "naturaleza" => "3",
                                    "unidad" => "UNIDAD",
                                    "cantidad"=> 0,
                                    "arrNovedades"=> array(),
                                    "valor" => ($arrValorxConcepto[$automatico->idconcepto]["valor"] - $valorInt),
                                    "tipoGen" => "automaticos",
                                    "porcentaje" => $porcentajeDescuento
                                );
                            }
                            else{
                                $arrValorxConcepto[$automatico->idconcepto] = array(
                                    "naturaleza" => "3",
                                    "unidad" => "UNIDAD",
                                    "cantidad"=> 0,
                                    "arrNovedades"=> array(),
                                    "valor" => $valorInt*-1 ,
                                    "tipoGen" => "automaticos",
                                    "porcentaje" => $porcentajeDescuento
                                );
                            }

                            
                        }
                        else{
                            if(substr($liquidacionNomina->fechaInicio,8,2) == "01"){

                                if(isset($arrValorxConcepto[$automatico->idconcepto])){
                                    $arrValorxConcepto[$automatico->idconcepto] = array(
                                        "naturaleza" => "3",
                                        "unidad" => "UNIDAD",
                                        "cantidad"=> 0,
                                        "arrNovedades"=> array(),
                                        "valor" => ($arrValorxConcepto[$automatico->idconcepto]["valor"] - $valorInt),
                                        "tipoGen" => "automaticos",
                                        "porcentaje" => $porcentajeDescuento
                                    );
                                }
                                else{
                                    $arrValorxConcepto[$automatico->idconcepto] = array(
                                        "naturaleza" => "3",
                                        "unidad" => "UNIDAD",
                                        "cantidad"=> 0,
                                        "arrNovedades"=> array(),
                                        "valor" => $valorInt*-1 ,
                                        "tipoGen" => "automaticos",
                                        "porcentaje" => $porcentajeDescuento
                                    );
                                }

                                
                            }
                            else{
                                $fechaPrimeraQuincena = substr($liquidacionNomina->fechaInicio,0,8)."01";
                                
                                $itemFPS = DB::table("item_boucher_pago","ibp")
                                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                                ->where("bp.fkEmpleado","=", $empleado->idempleado)
                                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                ->where("ln.fkEstado","=","5")//Terminada
                                ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                                ->where("ibp.fkConcepto","=","33")//FONDO DE SOLIDARIDAD                
                                ->get();

                                if(sizeof($itemFPS)> 0){
                                    $valorInt = $valorInt - $itemFPS[0]->descuento;
                                }
                            
                                if(isset($arrValorxConcepto[$automatico->idconcepto])){
                                    $arrValorxConcepto[$automatico->idconcepto] = array(
                                        "naturaleza" => "3",
                                        "unidad" => "UNIDAD",
                                        "cantidad"=> 0,
                                        "arrNovedades"=> array(),
                                        "valor" => ($arrValorxConcepto[$automatico->idconcepto]["valor"] - $valorInt),
                                        "tipoGen" => "automaticos",
                                        "porcentaje" => $porcentajeDescuento
                                    );
                                }
                                else{
                                    $arrValorxConcepto[$automatico->idconcepto] = array(
                                        "naturaleza" => "3",
                                        "unidad" => "UNIDAD",
                                        "cantidad"=> 0,
                                        "arrNovedades"=> array(),
                                        "valor" => $valorInt*-1 ,
                                        "tipoGen" => "automaticos",
                                        "porcentaje" => $porcentajeDescuento
                                    );
                                }
                            }
                        }
                            
                    }
                                    
                }
            }

            
            //INICIO CALCULO RETENCION EN LA FUENTE
            $variableUVT = DB::table("variable")->where("idVariable","=","10")->first();
            $uvtActual = intval($variableUVT->valor);
            $arrayRetencion = array();
            $arrayRetencionInd = array();
            $arrayRetencionPri = array();

            $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")
            ->where("gcc.fkGrupoConcepto", "=", "4")->get();
            $arrComoCalcula[36] = ($arrComoCalcula[36]?? array());
            array_push($arrComoCalcula[36],"Se comienza a buscar los conceptos del grupo de concepto 4 - Salarial");
            foreach($grupoConceptoCalculo as $grupoConcepto){
                if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto != 36){
                    if($empleado->tipoRegimen == "Salario Integral" && $arrValorxConcepto[$grupoConcepto->fkConcepto]["naturaleza"]=="1"){
                        $arrValorxConcepto[$grupoConcepto->fkConcepto]["valor"] =  ($arrValorxConcepto[$grupoConcepto->fkConcepto]["valor"] * 100)/70;
                        array_push($arrComoCalcula[36],"Concepto #".$grupoConcepto->fkConcepto." encontrado, es un salario integral tomo el 70% = $".number_format($arrValorxConcepto[$grupoConcepto->fkConcepto]["valor"], 0,",","."));
                    }
                }
            }
            


            $ingreso = 0;
            $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")
                ->where("gcc.fkGrupoConcepto", "=", "9")
                ->get();        
            array_push($arrComoCalcula[36],"Se comienza a buscar los conceptos del grupo de concepto 9 - INGRESO PARA RETENCION");
            foreach($grupoConceptoCalculo as $grupoConcepto){
                if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto!=36){
                    $ingreso = $ingreso + floatval($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor']);
                    array_push($arrComoCalcula[36],"Concepto #".$grupoConcepto->fkConcepto." encontrado se suman = $".number_format($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor'], 0,",","."));
                }
            }
            array_push($arrComoCalcula[36],"Ingreso para retención = $".number_format($ingreso, 0,",","."));

            $validoPeriocidad=false;
            if(!$empresa->fkPeriocidadRetencion){
                $validoPeriocidad =true;
                array_push($arrComoCalcula[36],"Periocidad no configurada se toma para todas las liquidaciones");
            }
            else{
                if($periodo == 15){
                    if(substr($liquidacionNomina->fechaInicio,8,2)=="01" && $empresa->fkPeriocidadRetencion==3){//SOLO EN SEG QUIN
                        $validoPeriocidad =false;
                    }
                    else{
                        $validoPeriocidad =true;
                        array_push($arrComoCalcula[36],"Periocidad configurada para la segunda quincena");
                    }
                }
                else{
                    $validoPeriocidad =true;
                    array_push($arrComoCalcula[36],"Periocidad configurada se toma para todas las liquidaciones");
                }
            }


            if(($tipoliquidacion == "1" 
            || $tipoliquidacion == "2" 
            || $tipoliquidacion == "4" 
            || $tipoliquidacion == "5" 
            || $tipoliquidacion == "6" 
            || $tipoliquidacion == "12" 
            || $tipoliquidacion == "9") && $validoPeriocidad){
                
                $valorSalario = 0;
                $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")
                    ->where("gcc.fkGrupoConcepto", "=", "3")
                    ->get();
                foreach($grupoConceptoCalculo as $grupoConcepto){
                    if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto != 36){
                        $valorSalario = $valorSalario + floatval($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor']);
                    }
                }
                
                if($periodo == 15){
                    if(substr($liquidacionNomina->fechaInicio,8,2) == "16"){
                        $fechaPrimeraQuincena = substr($liquidacionNomina->fechaInicio,0,8)."01";
                        $itemsBoucherPago = DB::table("item_boucher_pago","ibp")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->where("bp.fkEmpleado","=", $empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.fkEstado","=","5")//Terminada
                        ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                        ->where("gcc.fkGrupoConcepto", "=", "3")
                        ->get();
            
                        foreach($itemsBoucherPago as $itemBoucherPago){
                            $valorSalario = $valorSalario + floatval($itemBoucherPago->pago);
                        }
                    }
                }
                array_push($arrComoCalcula[36],"Se toma el valor del salario para el periodo actual segun el grupo de concepto 3 -> Salario = $".number_format($valorSalario, 0,",","."));

                $variablesRetencion = DB::table("variable")
                ->where("idVariable",">=","16")
                ->where("idVariable","<=","48")
                ->orWhereIn("idVariable",["66","67"])
                ->get();
                $varRetencion = array();
                foreach($variablesRetencion as $variablesRetencio){
                    $varRetencion[$variablesRetencio->idVariable] = $variablesRetencio->valor;
                }

                $valorSalarioParaFuera = $valorSalario;
                /*if($empleado->tipoRegimen == "Salario Integral"){
                    $valorSalarioParaFuera = $valorSalario*0.7;
                    
                }*/
                
                
                if($valorSalarioParaFuera > ($uvtActual * $varRetencion[66])){//TOPE_MAXIMO_SALARIO_UVTS_FUERA_DE_NOMINA (Nota: lo del salario integral se cambia mas arriba)
                    $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")
                        ->where("gcc.fkGrupoConcepto", "=", "43")
                        ->get();
                    
                    array_push($arrComoCalcula[36],"Salario ($ ".number_format($valorSalarioParaFuera, 0,",",".").") supera las ".$varRetencion[66]." UVTS configuradas en TOPE_MAXIMO_SALARIO_UVTS_FUERA_DE_NOMINA");
                    array_push($arrComoCalcula[36],"Se comienza a buscar los conceptos del grupo de concepto 43 - INGRESO FUERA DE NOMINA PARA RETENCION");
                    foreach($grupoConceptoCalculo as $grupoConcepto){
                        if(isset($arrValorxConceptoFueraNomina[$grupoConcepto->fkConcepto])){
                            $ingreso = $ingreso + floatval($arrValorxConceptoFueraNomina[$grupoConcepto->fkConcepto]['valor']);
                            array_push($arrComoCalcula[36],"Concepto #".$grupoConcepto->fkConcepto." encontrado se suman al ingreso $".number_format($arrValorxConceptoFueraNomina[$grupoConcepto->fkConcepto]['valor'], 0,",","."));
                        }
                    }
                    array_push($arrComoCalcula[36],"Ingreso actual en $".number_format($ingreso, 0,",","."));
                    

                }
                else{
                    $fueraSalario = 0;
                    $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")
                        ->where("gcc.fkGrupoConcepto", "=", "43")
                        ->get();
                    array_push($arrComoCalcula[36],"Se comienza a buscar los conceptos del grupo de concepto 43 - INGRESO FUERA DE NOMINA PARA RETENCION");
                    foreach($grupoConceptoCalculo as $grupoConcepto){
                        if(isset($arrValorxConceptoFueraNomina[$grupoConcepto->fkConcepto])){
                            $fueraSalario = $fueraSalario + floatval($arrValorxConceptoFueraNomina[$grupoConcepto->fkConcepto]['valor']);
                            array_push($arrComoCalcula[36],"Concepto #".$grupoConcepto->fkConcepto." encontrado se suman en fuera de salario $".number_format($arrValorxConceptoFueraNomina[$grupoConcepto->fkConcepto]['valor'], 0,",","."));
                        }
                    }
                    
                    if($periodo == 15){
                        if(substr($liquidacionNomina->fechaInicio,8,2) == "16"){
                            $fechaPrimeraQuincena = substr($liquidacionNomina->fechaInicio,0,8)."01";
                            $itemsBoucherPago = DB::table("item_boucher_pago","ibp")
                            ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")
                            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                            ->where("bp.fkEmpleado","=", $empleado->idempleado)
                            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                            ->where("ln.fkEstado","=","5")//Terminada
                            ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                            ->where("gcc.fkGrupoConcepto", "=", "43")
                            ->get();
                            
                            array_push($arrComoCalcula[36],"Se comienza a buscar los conceptos del grupo de concepto 43 - INGRESO FUERA DE NOMINA PARA RETENCION en liquidacion de la quincena pasada");
                            foreach($itemsBoucherPago as $itemBoucherPago){
                                $fueraSalario = $fueraSalario + floatval($itemBoucherPago->pago);
                            }
                        }
                    }

                    

                    if($fueraSalario > ($uvtActual * $varRetencion[67])){//TOPE_MAXIMO_UVTS_FUERA_DE_NOMINA
                        array_push($arrComoCalcula[36],"La suma de fuera de salario($".number_format($fueraSalario, 0,",",".").") supera el TOPE_MAXIMO_UVTS_FUERA_DE_NOMINA (".$varRetencion[67].")");
                        $excendete = $fueraSalario - ($uvtActual * $varRetencion[67]);
                        array_push($arrComoCalcula[36],"Se agrega el excente ($".number_format($excendete, 0,",",".").") al ingreso");
                        $ingreso = $ingreso + $excendete;
                        array_push($arrComoCalcula[36],"Ingreso actual en ($".number_format($ingreso, 0,",",".").")");
                    }

                }

                
                $FPS = (isset($arrValorxConcepto[33]) ? $arrValorxConcepto[33]['valor'] : 0);
                $EPS = (isset($arrValorxConcepto[18]) ? $arrValorxConcepto[18]['valor'] : 0);
                $AFP = (isset($arrValorxConcepto[19]) ? $arrValorxConcepto[19]['valor'] : 0);
                $SS = ($FPS + $EPS + $AFP) * -1;
                
                
                if($periodo == 15){
                    if(substr($liquidacionNomina->fechaInicio,8,2) == "16"){
                        $itemsBoucherPago = DB::table("item_boucher_pago","ibp")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->where("bp.fkEmpleado","=", $empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.fkEstado","=","5")//Terminada
                        ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                        ->where("gcc.fkGrupoConcepto", "=", "9") //9-INGRESO PARA RETENCION	
                        ->get();
        
                        foreach($itemsBoucherPago as $itemBoucherPago){
                            $ingreso = $ingreso + floatval($itemBoucherPago->pago);
                        }
                        
                        $itemsBoucherPagoSS = DB::table("item_boucher_pago","ibp")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->where("bp.fkEmpleado","=", $empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.fkEstado","=","5")//Terminada
                        ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                        ->whereIn("ibp.fkConcepto",["33","18","19"])//SS
                        ->get();
                        foreach($itemsBoucherPagoSS as $itemBoucherPagoSS){
                            $SS = $SS + floatval($itemBoucherPagoSS->descuento);
                            if($itemBoucherPagoSS->fkConcepto == "33"){
                                $FPS = $FPS + $itemBoucherPagoSS->valor;
                            }
                            else if($itemBoucherPagoSS->fkConcepto == "18"){
                                $EPS = $EPS + $itemBoucherPagoSS->valor;
                            }
                            else if($itemBoucherPagoSS->fkConcepto == "19"){
                                $AFP = $AFP + $itemBoucherPagoSS->valor;
                            }

                        }
                    }
                }
                
                $rentaLiquida = $ingreso - $SS;
                array_push($arrComoCalcula[36],"Se calcula la renta liquida como Ingreso($".number_format($ingreso, 0,",",".").") menos Seguridad Social ($".number_format($SS, 0,",",".").")");
                array_push($arrComoCalcula[36],"Renta liquida = ($".number_format($rentaLiquida, 0,",",".").")");
                $interesesVivienda = 0;
                $beneficiosTributarioIntVivienda = DB::table("beneficiotributario", "bt")
                    ->selectRaw("sum(bt.valorMensual) as suma")
                    ->where("bt.fkEmpleado","=",$empleado->idempleado)
                    ->where("bt.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("bt.fkTipoBeneficio", "=", "2")
                    ->whereDate("bt.fechaVigencia",">=", $fechaFin)
                    ->get();
                
                $interesesVivienda = intval($beneficiosTributarioIntVivienda[0]->suma);
                array_push($arrComoCalcula[36],"Intereses de vivienda = ($".number_format($interesesVivienda, 0,",",".").")");
                if($interesesVivienda > round($uvtActual * $varRetencion[16], -3)){
                    
                    $interesesVivienda = round($uvtActual * $varRetencion[16], -3);
                    array_push($arrComoCalcula[36],"Intereses de vivienda superan el TOPE_MAXIMO_INTERESES_VIVIENDA_UVTS_RETENCION(".$varRetencion[16].")");
                    array_push($arrComoCalcula[36],"Nuevos intereses de vivienda = ($".number_format($interesesVivienda, 0,",",".").")");
                }            
                $medicinaPrepagada = 0;
                $beneficiosTributarioMedicinaPrepagada = DB::table("beneficiotributario", "bt")
                    ->selectRaw("sum(bt.valorMensual) as suma")
                    ->where("bt.fkEmpleado","=",$empleado->idempleado)
                    ->where("bt.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("bt.fkTipoBeneficio", "=", "3")
                    ->whereDate("bt.fechaVigencia",">=", $fechaFin)
                    ->get();
                
                $medicinaPrepagada = intval($beneficiosTributarioMedicinaPrepagada[0]->suma);
                array_push($arrComoCalcula[36],"Medicina Prepagada = ($".number_format($medicinaPrepagada, 0,",",".").")");
                if($medicinaPrepagada > round($uvtActual * $varRetencion[17], -3)){
                    $medicinaPrepagada = round($uvtActual * $varRetencion[17], -3);
                    array_push($arrComoCalcula[36],"Medicina Prepagada superan el TOPE_MAXIMO_MEDICINA_PREPAGADA_UVTS_RETENCION(".$varRetencion[17].")");
                    array_push($arrComoCalcula[36],"Nuevo valor medicina prepagada = ($".number_format($medicinaPrepagada, 0,",",".").")");
                }

                //Calcular cuanto cuesta este dependiente
                $dependiente = 0;
                $beneficiosTributarioDependiente = DB::table("beneficiotributario", "bt")
                    ->select("bt.idBeneficioTributario")
                    ->where("bt.fkEmpleado","=",$empleado->idempleado)
                    ->where("bt.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("bt.fkTipoBeneficio", "=", "4")
                    ->whereDate("bt.fechaVigencia",">=", $fechaFin)
                    ->get();
                
                if(sizeof($beneficiosTributarioDependiente)> 0){
                    $dependiente = ($ingreso * $varRetencion[18]);
                    array_push($arrComoCalcula[36],"Dependiente = ($".number_format($dependiente, 0,",",".").")");
                }
                            
                //Tope maximo dependencia
                if($dependiente > round($uvtActual * $varRetencion[19], -3)){
                    $dependiente = round($uvtActual * $varRetencion[19], -3);
                    array_push($arrComoCalcula[36],"Dependiente supera el TOPE_MAXIMO_DEPENDIENTE_UVTS_RETENCION(".$varRetencion[19].")");
                    array_push($arrComoCalcula[36],"Nuevo valor dependiente = ($".number_format($dependiente, 0,",",".").")");
                }

                $aporteVoluntario = 0;
                $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")
                    ->where("gcc.fkGrupoConcepto", "=", "6")
                    ->get();
                array_push($arrComoCalcula[36],"Comienza a buscar aporte voluntario segun el grupo de concepto 6");
                foreach($grupoConceptoCalculo as $grupoConcepto){
                    if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto != 36){
                        array_push($arrComoCalcula[36],"Concepto #".$grupoConcepto->fkConcepto." encontrado se suman en aporte voluntario $".number_format($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor'], 0,",","."));
                        $aporteVoluntario = $aporteVoluntario + floatval($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor']);
                    }
                }
                $aporteVoluntario = $aporteVoluntario * -1;
                array_push($arrComoCalcula[36],"Aporte voluntario = ($".number_format($aporteVoluntario, 0,",",".").")");
                if($aporteVoluntario > round($rentaLiquida * $varRetencion[20], -3)){
                    $aporteVoluntario = round($rentaLiquida * $varRetencion[20], -3);
                    array_push($arrComoCalcula[36],"Aporte voluntario supera el TOPE_MAXIMO_PORCENTAJE_APORTE_VOLUNTARIO_RETENCION(".$varRetencion[20].")");
                    array_push($arrComoCalcula[36],"Nuevo valor aporte voluntario = ($".number_format($aporteVoluntario, 0,",",".").")");
                }
                
                $AFC = 0;
                $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")
                    ->where("gcc.fkGrupoConcepto", "=", "8")
                    ->get();
                array_push($arrComoCalcula[36],"Comienza a buscar AFC segun el grupo de concepto 8");
                foreach($grupoConceptoCalculo as $grupoConcepto){
                    if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto != 36){
                        $AFC = $AFC + floatval($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor']);
                        array_push($arrComoCalcula[36],"Concepto #".$grupoConcepto->fkConcepto." encontrado se suman en AFC $".number_format($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor'], 0,",","."));
                    }
                }
                
                $AFC = $AFC * -1;
                array_push($arrComoCalcula[36],"AFC = ($".number_format($AFC, 0,",",".").")");
                if($AFC > round($rentaLiquida * $varRetencion[21], -3)){
                    $AFC = round($rentaLiquida * $varRetencion[21], -3);
                    array_push($arrComoCalcula[36],"AFC supera el TOPE_MAXIMO_PORCENTAJE_AFC_RETENCION(".$varRetencion[21].")");
                    array_push($arrComoCalcula[36],"Nuevo valor AFC = ($".number_format($AFC, 0,",",".").")");
                }
                
                $deducciones = $interesesVivienda + $medicinaPrepagada + $dependiente + $aporteVoluntario + $AFC;

                array_push($arrComoCalcula[36],"Deducciones = ($".number_format($deducciones, 0,",",".").") la suma de intereses vivienda, medicina prepagada, dependiente, aporte voluntario y AFC");
                $deduccionesSinAportes = $interesesVivienda + $medicinaPrepagada + $dependiente;

                $baseNeta = $rentaLiquida - $deducciones;
                array_push($arrComoCalcula[36],"Base neta = ($".number_format($baseNeta, 0,",",".").") => renta liquida menos deducciones ");
                $baseNetaSinAportes = $rentaLiquida - $deduccionesSinAportes;

                $exenta = $baseNeta * $varRetencion[22];
                array_push($arrComoCalcula[36],"Parte exenta = ($".number_format($exenta, 0,",",".").") => base Neta * (".$varRetencion[22].") PORCENTAJE_PARTE_EXENTA_RETENCION");
                $exentaSinAportes = $baseNetaSinAportes * $varRetencion[22];

        
                if($exenta > round($uvtActual * $varRetencion[23],-3)){
                    $exenta = round($uvtActual * $varRetencion[23], -3);
                    array_push($arrComoCalcula[36],"Parte excenta supera el TOPE_MAXIMO_PARTE_EXENTA_UVTS_RETENCION(".$varRetencion[23].")");
                    array_push($arrComoCalcula[36],"Nuevo valor Parte excenta = ($".number_format($exenta, 0,",",".").")");
                }
                if($exentaSinAportes > round($uvtActual * $varRetencion[23],-3)){
                    $exentaSinAportes = round($uvtActual * $varRetencion[23], -3);
                }

                $totalBeneficiosTributarios = $exenta + $deducciones;
                array_push($arrComoCalcula[36],"Total beneficicos tributarios = ($".number_format($totalBeneficiosTributarios, 0,",",".").") => exenta + deducciones");
                $totalBeneficiosTributariosSinAportes = $exentaSinAportes + $deduccionesSinAportes;
                
                $topeBeneficios= $rentaLiquida*$varRetencion[24];

                if($totalBeneficiosTributarios > ($rentaLiquida*$varRetencion[24])){
                    $totalBeneficiosTributarios = $rentaLiquida*$varRetencion[24];
                    array_push($arrComoCalcula[36],"Total Beneficios Tributarios supera el TOPE_MAXIMO_BENEFICIOS_RENTA_LIQUIDA_RETENCION(".$varRetencion[24].")");
                    array_push($arrComoCalcula[36],"Nuevo Total Beneficios Tributarios = ($".number_format($totalBeneficiosTributarios, 0,",",".").")");
                }

                if($totalBeneficiosTributarios > round($uvtActual*$varRetencion[25],-3)){
                    
                    $totalBeneficiosTributarios = round($uvtActual*$varRetencion[25], -3);
                    $topeBeneficios= $rentaLiquida*round($uvtActual*$varRetencion[25], -3);
                    array_push($arrComoCalcula[36],"Total Beneficios Tributarios supera el TOPE_MAXIMO_BENEFICIOS_UVTS_RETENCION(".$varRetencion[25].")");
                    array_push($arrComoCalcula[36],"Nuevo Total Beneficios Tributarios = ($".number_format($totalBeneficiosTributarios, 0,",",".").")");
                }

                if($totalBeneficiosTributariosSinAportes > ($rentaLiquida*$varRetencion[24])){
                    $totalBeneficiosTributariosSinAportes = $rentaLiquida*$varRetencion[24];
                }
                if($totalBeneficiosTributariosSinAportes > round($uvtActual*$varRetencion[25],-3)){
                    $totalBeneficiosTributariosSinAportes = round($uvtActual*$varRetencion[25], -3);
                }

                $baseGravable  = $rentaLiquida - $totalBeneficiosTributarios;
                array_push($arrComoCalcula[36],"Base gravable = ($".number_format($baseGravable, 0,",",".").") => renta liquida - total beneficios tributarios");

                $baseGravableSinAportes  = $rentaLiquida - $totalBeneficiosTributariosSinAportes;
                
                $baseGravableUVTS = round($baseGravable / $uvtActual, 2);
                array_push($arrComoCalcula[36],"Base gravable en uvts = ".$baseGravableUVTS);
                $baseGravableSinAportesUVTS = round($baseGravableSinAportes / $uvtActual, 2);
                
                $impuestoUVT = 0;
                $tablaRete = DB::table("tabla_retencion")->orderBy("minimo")->orderBy("maximo")->get();
                foreach($tablaRete as $tablaRet){
                    if(!isset($tablaRet->minimo)){
                        $tablaRet->minimo = 0;
                    }
                    
                    if(!isset($tablaRet->maximo)){
                        $tablaRet->minimo = 99999999;
                    }

                    if($baseGravableUVTS > $tablaRet->minimo && $baseGravableUVTS <= $tablaRet->maximo){
                        array_push($arrComoCalcula[36],$tablaRet->minimo."<".$baseGravableUVTS."<=".$tablaRet->maximo." En uvts");
                        $impuestoUVT = ($baseGravableUVTS - $tablaRet->minimo)*$tablaRet->porcentaje;
                        
                        $impuestoUVT = $impuestoUVT + $tablaRet->adicion;
                        array_push($arrComoCalcula[36],"Impuesto en uvt = ".$impuestoUVT);
                        break;
                    }
                }

                $impuestoSinAportesUVT = 0;
                foreach($tablaRete as $tablaRet){
                    if(!isset($tablaRet->minimo)){
                        $tablaRet->minimo = 0;
                    }
                    
                    if(!isset($tablaRet->maximo)){
                        $tablaRet->minimo = 99999999;
                    }

                    if($baseGravableSinAportesUVTS > $tablaRet->minimo && $baseGravableSinAportesUVTS <= $tablaRet->maximo){
                        $impuestoSinAportesUVT = ($baseGravableSinAportesUVTS - $tablaRet->minimo)*$tablaRet->porcentaje;
                        $impuestoSinAportesUVT = $impuestoSinAportesUVT + $tablaRet->adicion;
                        break;
                    }
                }

                $impuestoValor = round($impuestoUVT * $uvtActual, -3);
                array_push($arrComoCalcula[36],"Se multiplica el impuesto en uvts por valor de uvt actual = ".$impuestoValor);
                $impuestoValorSinAportes = round($impuestoSinAportesUVT * $uvtActual, -3);
                $valorInt = $impuestoValor;
                //dd($arrValorxConcepto, $arrValorxConceptoOtros, $impuestoValor );
                if($impuestoValor>0){
                    if($periodo == 30){
                        
                        if(isset($arrValorxConcepto[36])){
                           
                            $arrValorxConcepto[36] = array(
                                "naturaleza" => "3",
                                "unidad" => "UNIDAD",
                                "cantidad"=> "0",
                                "arrNovedades"=> array(),
                                "valor" => ($arrValorxConcepto[36]['valor'] - ($valorInt)),
                                "tipoGen" => "automaticos"
                            );
                        }
                        else{
                            
                            $arrValorxConcepto[36] = array(
                                "naturaleza" => "3",
                                "unidad" => "UNIDAD",
                                "cantidad"=> "0",
                                "arrNovedades"=> array(),
                                "valor" => $valorInt*-1 ,
                                "tipoGen" => "automaticos"
                            );
                        }
                    }
                    else{
                        if(substr($liquidacionNomina->fechaInicio,8,2) == "01"){
                           
                            if(isset($arrValorxConcepto[36])){
                                $arrValorxConcepto[36] = array(
                                    "naturaleza" => "3",
                                    "unidad" => "UNIDAD",
                                    "cantidad"=> "0",
                                    "arrNovedades"=> array(),
                                    "valor" => ($arrValorxConcepto[36]['valor'] - ($valorInt)),
                                    "tipoGen" => "automaticos"
                                );
                            }
                            else{
                                $arrValorxConcepto[36] = array(
                                    "naturaleza" => "3",
                                    "unidad" => "UNIDAD",
                                    "cantidad"=> "0",
                                    "arrNovedades"=> array(),
                                    "valor" => $valorInt*-1 ,
                                    "tipoGen" => "automaticos"
                                );
                            }
                        }
                        else{
                            $fechaPrimeraQuincena = substr($liquidacionNomina->fechaInicio,0,8)."01";                        
                            $itemReteFuente = DB::table("item_boucher_pago","ibp")
                            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                            ->where("bp.fkEmpleado","=", $empleado->idempleado)
                            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                            ->where("ln.fkEstado","=","5")//Terminada
                            ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                            ->where("ibp.fkConcepto","=","36")//RETENCION                
                            ->get();
                            if(sizeof($itemReteFuente)> 0){
                                $valorInt = $valorInt - $itemReteFuente[0]->descuento;
                            }                    
                            if(isset($arrValorxConcepto[36])){
                                $arrValorxConcepto[36] = array(
                                    "naturaleza" => "3",
                                    "unidad" => "UNIDAD",
                                    "cantidad"=> "0",
                                    "arrNovedades"=> array(),
                                    "valor" => ($arrValorxConcepto[36]['valor'] - ($valorInt)),
                                    "tipoGen" => "automaticos"
                                );
                            }
                            else{
                                $arrValorxConcepto[36] = array(
                                    "naturaleza" => "3",
                                    "unidad" => "UNIDAD",
                                    "cantidad"=> "0",
                                    "arrNovedades"=> array(),
                                    "valor" => $valorInt*-1 ,
                                    "tipoGen" => "automaticos"
                                );
                            }
                        }
                    }
                }
                else{
                   
                }
                $retencionContingente = $impuestoValorSinAportes - $impuestoValor;
                
                $arrayRetencion["salario"] = $valorSalario;
                $arrayRetencion["ingreso"] = $ingreso;
                $arrayRetencion["EPS"] = $EPS*-1;
                $arrayRetencion["AFP"] = $AFP*-1;
                $arrayRetencion["FPS"] = $FPS*-1;
                $arrayRetencion["seguridadSocial"] = $SS;
                $arrayRetencion["interesesVivienda"] = $interesesVivienda;
                $arrayRetencion["medicinaPrepagada"] = $medicinaPrepagada;
                $arrayRetencion["dependiente"] = $dependiente;
                $arrayRetencion["aporteVoluntario"] = $aporteVoluntario;
                $arrayRetencion["AFC"] = $AFC;
                $arrayRetencion["exenta"] = $exenta;
                $arrayRetencion["exentaSinAportes"] = $exentaSinAportes;
                $arrayRetencion["totalBeneficiosTributarios"] = $totalBeneficiosTributarios;
                $arrayRetencion["totalBeneficiosTributariosSinAportes"] = $totalBeneficiosTributariosSinAportes;
                $arrayRetencion["topeBeneficios"] = $topeBeneficios;
                $arrayRetencion["baseGravableUVTS"] = $baseGravableUVTS;
                $arrayRetencion["impuestoUVT"] = $impuestoUVT;
                $arrayRetencion["impuestoSinAportesUVT"] = $impuestoSinAportesUVT;
                $arrayRetencion["impuestoValor"] = $impuestoValor;
                $arrayRetencion["impuestoValorSinAportes"] = $impuestoValorSinAportes;
                $arrayRetencion["retencionContingente"] = $retencionContingente;

                
            }
            //FIN CALCULO RETENCION EN LA FUENTE

            
            
            
            //INICIO CALCULO PROVISIONES
            $liquidacionPrima = 0;
            $liquidacionCesantias = 0;
            $liquidacionIntCesantias = 0;
            $liquidacionVac = 0;
        
            if($empleado->tipoRegimen=="Ley 50" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "23"){


                $novedadesRetiro = DB::table("novedad","n")
                ->join("retiro AS r", "r.idRetiro","=","n.fkRetiro")
                ->where("n.fkEmpleado", "=", $empleado->idempleado)
                ->where("n.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("n.fkEstado","=","7")
                ->whereNotNull("n.fkRetiro")
                ->whereBetween("n.fechaRegistro",[$fechaInicio, $fechaFin])->first();
                //$fechaFinParaPrima = date("Y-m-t",strtotime($fechaFin));
                $fechaFinParaPrima = $fechaFin;
                if($tipoliquidacion == "5"){
                    $fechaFinParaPrima = date("Y-m-30",strtotime($fechaFin));
                }



                $fechaFinParaPrimaReal = $fechaFin;
                if(isset($novedadesRetiro)){
                    $fechaFinParaPrima = $novedadesRetiro->fechaReal;
                    $fechaFinParaPrimaReal = $novedadesRetiro->fechaReal;
                }            
                $arrComoCalcula[58] = ($arrComoCalcula[58] ?? array());

                if(substr($fechaFinParaPrima, 8, 2) == "31" || (substr($fechaFinParaPrima, 8, 2) == "28" && substr($fechaFinParaPrima, 5, 2) == "02") || (substr($fechaFinParaPrima, 8, 2) == "29" && substr($fechaFinParaPrima, 5, 2) == "02")  ){
                    $fechaFinParaPrima = substr($fechaFinParaPrima,0,8)."30";
                }

                $arrResPrima = $this->calcularPrima($fechaInicio, $fechaFinParaPrima, $empleado, $periodo, $periodoPagoSinVac, $arrValorxConcepto, $idLiquidacionNomina, $arrComoCalcula[58]);


                $liquidacionPrima = $arrResPrima["liquidacionPrima"];
                $fechaInicialPrima = $arrResPrima["fechaInicialPrima"];
                $fechaFinalPrima = $arrResPrima["fechaFinalPrima"];
                $totalPeriodoPago = $arrResPrima["totalPeriodoPago"];
                $basePrima = $arrResPrima["basePrima"];
                $salarioPrima = $arrResPrima["salarioPrima"];
                $salarialPrima = $arrResPrima["salarialPrima"];

                $arrComoCalcula[58] = $arrResPrima["arrComoCalcula"];
                array_push($arrComoCalcula[58], "Valor Salario: $".number_format($arrResPrima["salarioPrima"],0,",","."));
                array_push($arrComoCalcula[58], "Valor promedio Salarial: $".number_format($arrResPrima["salarialPrima"],0,",","."));
                array_push($arrComoCalcula[58], "Valor Base: $".number_format($arrResPrima["basePrima"],0,",","."));
                array_push($arrComoCalcula[58], "Fecha inicial: ".$arrResPrima["fechaInicialPrima"]);
                array_push($arrComoCalcula[58], "Fecha final: ".$fechaFinParaPrima);
                array_push($arrComoCalcula[58], "Días: ".$arrResPrima["totalPeriodoPago"]);
                array_push($arrComoCalcula[58], "Valor liquidación prima: $".number_format($arrResPrima["liquidacionPrima"],0,",","."));
                $mesActual = date("m",strtotime($fechaInicio));
                $anioActual = date("Y",strtotime($fechaInicio));    
                //dd($arrResPrima);
                if($tipoliquidacion != "7" && $tipoliquidacion != "10" && $tipoliquidacion != "11" && $tipoliquidacion != "8"){
                    if($mesActual >= 1 && $mesActual <= 6){            
                        $historicoProvisionPrima = DB::table("provision","p")
                        ->selectRaw("sum(p.valor) as sumaValor")
                        ->where("p.fkEmpleado","=",$empleado->idempleado)
                        ->where("p.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("p.mes","<",date("m",strtotime($fechaInicio)))
                        ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                        ->where("p.fkConcepto","=","73")
                        ->first();   

                        $pagoPrimaItems = DB::table("item_boucher_pago", "ibp")
                        ->selectRaw("Sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                        ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")              
                        ->whereRaw("MONTH(ln.fechaInicio) < '".date("m",strtotime($fechaInicio))."'")              
                        ->whereIn("ibp.fkConcepto",["58"]) //58 - PRIMA
                        ->first();

                        $saldoPrima = DB::table("saldo")
                        ->where("fkEmpleado","=",$empleado->idempleado)
                        ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("anioAnterior","=",date("Y",strtotime($fechaInicio)))
                        ->where("fkConcepto","=", "73")
                        ->where("fkEstado","=","7")
                        ->first();
                        
                        $arrComoCalculaProv[58] = ($arrComoCalcula[58] ?? array());

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
                        ->where("p.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
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
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                        ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")              
                        ->whereRaw("MONTH(ln.fechaInicio) < '".date("m",strtotime($fechaInicio))."'")
                        ->whereRaw("MONTH(ln.fechaInicio) > '6'")
                        ->whereIn("ibp.fkConcepto",["58"]) //58 - PRIMA
                        ->first();
                        $saldoPrima = DB::table("saldo")
                        ->where("fkEmpleado","=",$empleado->idempleado)
                        ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("anioAnterior","=",date("Y",strtotime($fechaInicio)))
                        ->where("fkConcepto","=", "73")
                        ->where("fkEstado","=","7")
                        ->first();
                        $arrComoCalculaProv[58] = ($arrComoCalcula[58] ?? array());
                        array_push($arrComoCalculaProv[58], "Se suma a la liquidación anterior junto con la suma de pagos anteriores : $".number_format(( $pagoPrimaItems->suma ?? 0),0,",","."));
                        array_push($arrComoCalculaProv[58], "Se resta el historico de provisiones por un valor de : $".number_format(( $historicoProvisionPrima->sumaValor ?? 0),0,",","."));
                        array_push($arrComoCalculaProv[58], "Se resta el saldo por un valor de : $".number_format(( $saldoPrima->valor ?? 0),0,",","."));
                        $provisionPrimaValor = $liquidacionPrima + ($pagoPrimaItems->suma ?? 0) - (isset($historicoProvisionPrima->sumaValor) ? $historicoProvisionPrima->sumaValor : 0) - ( $saldoPrima->valor ?? 0);
                        array_push($arrComoCalculaProv[58], "Finalmente se tiene una provision de : $".number_format(( $provisionPrimaValor ?? 0),0,",","."));


                    }
                    $provisionPrima = DB::table("provision","p")
                    ->where("p.fkEmpleado","=",$empleado->idempleado)
                    ->where("p.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("p.mes","=",date("m",strtotime($fechaInicio)))
                    ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                    ->where("p.fkConcepto","=","73")
                    ->get();

                    $arrProvisionPrima = array(
                        "fkPeriodoActivo" => $periodoActivoReintegro->idPeriodo,
                        "fkConcepto" => "73",                
                        "fkEmpleado"=> $empleado->idempleado,
                        "mes" => date("m",strtotime($fechaInicio)),
                        "anio"  => date("Y",strtotime($fechaInicio)),
                        "valor" => round($provisionPrimaValor),
                        "comoCalculo" => implode("<br>",$arrComoCalculaProv[58])
                    );

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
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9"])
                    ->first();

                    $ultimaLiquidacionDatosPasados = DB::table("liquidacionnomina", "ln")
                    ->join("boucherpago as bp", "bp.fkLiquidacion", "=","ln.idLiquidacionNomina")
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->whereIn("ln.fkTipoLiquidacion",["8"])
                    ->orderBy("bp.idBoucherPago","desc")
                    ->first();

                    
                    $primerLiq = ($primeraLiquidacionNoDatosPasados->idLiquidacionNomina ?? 0);
                    $primerLiqFecha = ($ultimaLiquidacionDatosPasados->fechaLiquida ?? "1990-01-01");
                    

                    $fechaInicioMes = date("Y-m-01", strtotime($fechaInicio));


                    $nomina = DB::table("nomina")->where("idNomina", "=", $liquidacionNomina->fkNomina)->first();
                    $salarioMes = 0;
                    foreach($conceptosFijosEmpl as $conceptoFijoEmpl){
                        if($conceptoFijoEmpl->fkConcepto=="1"){
                            $salarioMes = $conceptoFijoEmpl->valor; 
                        }
                    }
                   
                    if($empleado->fkTipoCotizante == 51){
                        $salarioMes = ($arrValorxConcepto[1]["valor"] ?? 0);
                    }
                    /*if($periodo == 15){
                        if(substr($fechaInicio,8,2) == "16"){
                            $bouchersPagoPrimeraQ = DB::table("boucherpago","bp")
                            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                            ->where("bp.fkEmpleado","=", $empleado->idempleado)
                            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                            ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
                            ->where("ln.fechaInicio","=",$fechaInicioMes)
                            ->get();
                            $salarioMes = 0;
                            
                            foreach($conceptosFijosEmpl as $conceptoFijoEmpl){
                                if($conceptoFijoEmpl->fkConcepto=="1"){
                                    if(sizeof($bouchersPagoPrimeraQ) == 0){
                                        $salarioMes = ($conceptoFijoEmpl->valor/30)*30;
                                    }
                                    else{
                                        $salarioMes = ($conceptoFijoEmpl->valor/30)*$periodo;
                                    }
                                   
                                }
                            }
                            

                            foreach($bouchersPagoPrimeraQ as $boucherPagoPrimeraQ){
                                $salarioCalc = ($boucherPagoPrimeraQ->salarioPeriodoPago*$periodo)/($boucherPagoPrimeraQ->periodoPago != 0 ? $boucherPagoPrimeraQ->periodoPago : 1);
                                $salarioMes += $salarioCalc; 
                            }
                        }
                    }*/
                    //INICIO VERIFICAR SI TIENE RETROACTIVOS EN ESTE MES Y AGREGARLOS AL VALOR DEL SALARIO DEL MES
                    
                    //FIN VERIFICAR SI TIENE RETROACTIVOS EN ESTE MES Y AGREGARLOS AL VALOR DEL SALARIO DEL MES

                    
                    $periodoPagoMesActual = $periodoPagoSinVac;    
                    //INICIO SALARIAL GANADO ESTE MES
                    $salarial = 0;
                    $grupoConceptoCalculoPrimaCes = DB::table("grupoconcepto_concepto","gcc")
                        ->where("gcc.fkGrupoConcepto", "=", "11")//Salarial para provisiones
                        ->get();
                    foreach($grupoConceptoCalculoPrimaCes as $grupoConcepto){
                        if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto != 36){
                            $salarial = $salarial + floatval($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor']);
                        }
                    }

                    $liquidacionMesActual = DB::table("liquidacionnomina", "ln")
                    ->select("bp.periodoPago","ln.idLiquidacionNomina")
                    ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fechaInicio","=",$fechaInicioMes)
                    ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                    ->where("ln.idLiquidacionNomina",">=",$primerLiq)
                    ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
                    ->first();

                    
                    if(isset($liquidacionMesActual)){

                        $itemsBoucherSalarialMesAct = DB::table("item_boucher_pago", "ibp")
                        ->selectRaw("Sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("bp.fkLiquidacion","=",$liquidacionMesActual->idLiquidacionNomina)     
                        ->where("bp.fkLiquidacion","<>",$idLiquidacionNomina)
                        ->where("gcc.fkGrupoConcepto","=","11") //11 - Salarial Prima
                        ->first();
                        
                        $salarial = $salarial + $itemsBoucherSalarialMesAct->suma;
                        
                        $itemsBoucherSalarialMesAct16 = DB::table("item_boucher_pago", "ibp")
                        ->selectRaw("Sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.fechaInicio","=",date("Y-m-16",strtotime($fechaInicioMes)))
                        ->where("bp.fkLiquidacion","<>",$idLiquidacionNomina)
                        ->where("bp.fkLiquidacion","<>",$idLiquidacionNomina)
                        ->where("gcc.fkGrupoConcepto","=","11") //11 - Salarial Prima
                        ->first();
                        
                        //dd($salarial, $itemsBoucherSalarialMesAct16);
                        $salarial = $salarial + ($itemsBoucherSalarialMesAct16->suma ?? 0);
                    }  
                    
                    if($periodo == 15 && !isset($liquidacionMesActual)){
                        if(substr($fechaInicio,8,2) == "16"){
                            $itemsBoucherSalarialMesAct16 = DB::table("item_boucher_pago", "ibp")
                            ->selectRaw("Sum(ibp.valor) as suma")
                            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                            ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")
                            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                            ->where("bp.fkEmpleado","=",$empleado->idempleado)
                            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                            ->where("ln.fechaInicio","=",date("Y-m-16",strtotime($fechaInicioMes)))
                            ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                            ->where("gcc.fkGrupoConcepto","=","11") //11 - Salarial Prima
                            ->first();
                            $salarial = $salarial + ($itemsBoucherSalarialMesAct16->suma ?? 0);
                        }
                    }

                    if($periodo == 15){
                        if(substr($fechaInicio,8,2) == "16"){
                            $liquidacionAnt = DB::table("liquidacionnomina", "ln")
                            ->select("bp.periodoPago","ln.idLiquidacionNomina")
                            ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                            ->where("bp.fkEmpleado","=",$empleado->idempleado)
                            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                            ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                            ->where("ln.fechaInicio","=",$fechaInicioMes);
                            if(isset($liquidacionMesActual)){
                                $liquidacionAnt = $liquidacionAnt->where("ln.idLiquidacionNomina","<>",$liquidacionMesActual->idLiquidacionNomina);
                            }
                            $liquidacionAnt = $liquidacionAnt->first();
                            
                            if(isset($liquidacionAnt)){
                                $periodoPagoMesActual = $periodoPagoMesActual + $liquidacionAnt->periodoPago;
                                $itemsBoucherSalarialMesAnt = DB::table("item_boucher_pago", "ibp")
                                ->selectRaw("Sum(ibp.valor) as suma")
                                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                ->where("bp.fkLiquidacion","=",$liquidacionAnt->idLiquidacionNomina) 
                                ->where("bp.fkLiquidacion","<>",$idLiquidacionNomina)
                                ->where("gcc.fkGrupoConcepto","=","11") //11 - Salarial Prima
                                ->first();
                                $salarial = $salarial + $itemsBoucherSalarialMesAnt->suma;
                                
                            }                   
                        }
                    }
                    //FIN SALARIAL GANADO ESTE MES
                    //$maximoRetroActivoDesde = (date("m",strtotime($fechaInicio)) - 3);
                    $maximoRetroActivoDesde = 1;


                    if(isset($novedadesRetiro)){                        
                        $cambioSalario = DB::table("cambiosalario")
                        ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("fechaCambio",">",date("Y-m-d", strtotime($fechaFin." -3 months + 1 day")))
                        ->first();
                    }
                    else{
                        $cambioSalario = DB::table("cambiosalario")
                        ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("fechaCambio",">",date("Y-m-d", strtotime($fechaFin." -3 months + 1 day")))
                        ->first();
                    }
                    
                    
                    
                    
                    if(isset($cambioSalario)){
                        $liquidacionesMesesAnterioresCesantias = DB::table("liquidacionnomina", "ln")
                        ->selectRaw("bp.periodoPago as periodPago, bp.salarioPeriodoPago as salarioPago, bp.diasTrabajados as diasTrabajados, bp.diasIncapacidad as diasIncapacidad")
                        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.fechaInicio","<",$fechaInicioMes)
                        ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
                        ->where("ln.idLiquidacionNomina",">=",$primerLiq)
                        ->where("bp.salarioPeriodoPago",">","0")
                        ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."' and MONTH(ln.fechaInicio) >= '".$maximoRetroActivoDesde."'")
                        ->orderBy("bp.idBoucherPago","desc");
                        $liquidacionesMesesAnterioresCesantias = $liquidacionesMesesAnterioresCesantias->get();
                        

                        $periodPagoCesantiasMesesAnt = 0;
                        $salarioPagoCesantiasMesesAnt = 0;  
                        
                       
                        foreach($liquidacionesMesesAnterioresCesantias as $liquidacionMesAnteriorCesantias){
                            $periodPagoCesantiasMesesAnt = $periodPagoCesantiasMesesAnt + $liquidacionMesAnteriorCesantias->periodPago;
                            $salarioPagoCesantiasMesesAnt = $salarioPagoCesantiasMesesAnt + $liquidacionMesAnteriorCesantias->salarioPago;
                        }
                        
                        
                        
                        $salarioMesNoMod = $salarioMes;
                        $totalPeriodoPagoCes = $periodoPagoMesActual + $periodPagoCesantiasMesesAnt;
                        if($empleado->fkTipoCotizante == 51){
                            $totalPeriodoPagoCesParaSalario = $periodoPagoMesActual + $periodPagoCesantiasMesesAnt;
                        }
                        else{
                            $diasMes = 30;
                            if($liquidacionNomina->fkTipoLiquidacion == "2" || $liquidacionNomina->fkTipoLiquidacion == "3"){
                                $diasMes = $this->days_360($fechaInicioMes, $fechaFinalPrima) + 1;                                
                                $salarioMes = ($salarioMes / 30) * $diasMes;                                
                            }                       
                            $totalPeriodoPagoCesParaSalario = $diasMes + $periodPagoCesantiasMesesAnt;
                        }
                        
                        
                        $itemRetroActivos = DB::table("item_boucher_pago", "ibp")
                        ->select("ibp.*", "ln.fechaInicio")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
                        ->where("ibp.fkConcepto","=","49")
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                        ->where("ln.idLiquidacionNomina",">=",$primerLiq)
                        ->whereBetween("ln.fechaLiquida",[$fechaInicioMes,$fechaFin])
                        ->get();
                        
                        foreach($itemRetroActivos as $itemRetroActivo){
                            if($periodo == 15){
                                if(substr($fechaInicio,8,2) == "16"){
                                    //Verificar si el retroactivo es del mes actual
                                    $debiPagar = ($cambioSalario->valorNuevo / 30) * 15;
                                    $pague = ($cambioSalario->valorAnterior / 30) * 15;
                                    $diferencia = $debiPagar - $pague;
                                    if( $cambioSalario->fechaCambio == $fechaInicioMes){           
                                        $itemRetroActivo->valor -= $diferencia;
                                    }
                                    $salarioMes = $salarioMes + $itemRetroActivo->valor;
                                    
                                }
                            }
                            else{
                                $salarioMes = $salarioMes + $itemRetroActivo->valor;
                            }
                        }
                        if(isset($arrValorxConcepto[49])){
                            $salarioMes = $salarioMes + floatval($arrValorxConcepto[49]['valor']);
                        }
                        
                        
                        //$maximoRetroActivoDesde = (date("m",strtotime($fechaInicio)) - 3);
                        $maximoRetroActivoDesde = 1;
                        
                        $retroActivoMesesAnterioresCes = DB::table("liquidacionnomina", "ln")
                        ->selectRaw("sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                        ->join("item_boucher_pago as ibp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.fechaInicio","<",$fechaInicioMes)
                        ->where("ln.idLiquidacionNomina",">=",$primerLiq)
                        ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."' and MONTH(ln.fechaInicio) > '".$maximoRetroActivoDesde."'")
                        ->where("ibp.fkConcepto","=","49") // 49 - RETROACTIVO SALARIO
                        ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
                        ->first();
                        
    
                        
                        
                        
    
                        $salarioCes = $salarioMes + $salarioPagoCesantiasMesesAnt + ($retroActivoMesesAnterioresCes->suma ?? 0);     
                        //dd($salarioPagoCesantiasMesesAnt, $salarioMes, $retroActivoMesesAnterioresCes, $totalPeriodoPagoCesParaSalario, $itemRetroActivos);
                        //dd($maximoRetroActivoDesde, $retroActivoMesesAnterioresCesDetalle);
                        $salarioCes = ($salarioCes / $totalPeriodoPagoCesParaSalario)*30;

                     


                        //dd($salarioCes);
                    }
                    else{
                        $salarioCes = $salarioMes;
                        $salarioMesNoMod = $salarioMes;
                    }
                    
                    
                    

                    if($empleado->fkTipoCotizante == 51){
                        $liquidacionesMesesAnterioresCes51 = DB::table("liquidacionnomina", "ln")
                        ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.diasTrabajados) as diasTrabajadosPer, sum(bp.diasIncapacidad) as diasIncapacidadPer, sum(bp.salarioPeriodoPago) as salarioPago, min(ln.fechaInicio) as minimaFecha")
                        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.fechaInicio","<",$fechaInicioMes)
                        ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")
                        ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
                        ->where("ln.idLiquidacionNomina",">=",$primerLiq)
                        ->first();

                        //INICIO CALCULAR EL PROMEDIO SALARIO EN PERIODO ACTUAL    
                        

                        //$totalPeriodoPago51 = $periodoPagoMesActual + (isset($liquidacionesMesesAnterioresCes51->periodPago) ? $liquidacionesMesesAnterioresCes51->periodPago : 0);
                        $totalPeriodoPagoParaSalario51 = $periodoPagoMesActual + (isset($liquidacionesMesesAnterioresCes51->periodPago) ? $liquidacionesMesesAnterioresCes51->periodPago : 0);
                        
                        $retroActivoMesesAnterioresCes51 = DB::table("liquidacionnomina", "ln")
                        ->selectRaw("sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                        ->join("item_boucher_pago as ibp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.fechaInicio","<",$fechaInicio)
                        ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")
                        ->where("ln.idLiquidacionNomina",">=",$primerLiq)
                        ->where("ibp.fkConcepto","=","49") // 49 - RETROACTIVO SALARIO
                        ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
                        ->first();
                       
                        $salarioCes = $salarioMes + ($liquidacionesMesesAnterioresCes51->salarioPago ?? 0) + ($retroActivoMesesAnterioresCes51->suma ?? 0);
                        $salarioCes = ($salarioCes / $totalPeriodoPagoParaSalario51)*30;

                    }



                    
                    if($empleado->aplicaSubsidio == "1" && $empleado->tipoRegimen == 'Ley 50'){
                        $variablesSalarioMinimo = DB::table("variable")->where("idVariable","=","1")->first();
                        $salarioCesParaSubsidio = $salarioMesNoMod;    
                        if($salarioCesParaSubsidio <= (2 * $variablesSalarioMinimo->valor)){
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
                            $salarioCesParaSubsidio = $salarioMesNoMod;    
                            if($salarioCesParaSubsidio <= (2 * $variablesSalarioMinimo->valor)){
                                $variablesSubTrans = DB::table("variable")->where("idVariable","=","2")->first();
                                $salarioCes = $salarioCes + $variablesSubTrans->valor;
                            }
                        }                
                    }

                    

                    $liquidacionesMesesAnterioresCompleta = DB::table("liquidacionnomina", "ln")
                    ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.salarioPeriodoPago) as salarioPago, min(ln.fechaInicio) as minimaFecha")
                    ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fechaInicio","<",$fechaInicioMes)
                    ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")        
                    //->where("ln.idLiquidacionNomina",">=",$primerLiq)
                    ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","8"])
                    ->first();
                    if($periodo == 15){

                        if(substr($liquidacionNomina->fechaInicio,8,2)=="16"){
                            $liquidacionesMesesAnterioresCompleta = DB::table("liquidacionnomina", "ln")
                            ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.salarioPeriodoPago) as salarioPago, min(ln.fechaInicio) as minimaFecha")
                            ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                            ->where("bp.fkEmpleado","=",$empleado->idempleado)
                            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                            ->where("ln.fechaInicio","<",$fechaInicioMes)
                            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")        
                            //->where("ln.idLiquidacionNomina",">=",$primerLiq)
                            ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","8"])
                            ->first();
                            
                        }
                    }
                    //dd($liquidacionesMesesAnterioresCompleta, $primerLiq, $anioActual, $fechaInicioMes, $periodoActivoReintegro->idPeriodo, $empleado->idempleado);
                
                    if(isset($liquidacionesMesesAnterioresCompleta->minimaFecha)){
                        if(strtotime($empleado->fechaIngreso) > strtotime($liquidacionesMesesAnterioresCompleta->minimaFecha)){
                            $fechaInicialCes = $empleado->fechaIngreso;
                        }
                        else if(strtotime($empleado->fechaIngreso) < strtotime($liquidacionesMesesAnterioresCompleta->minimaFecha) && strtotime($empleado->fechaIngreso) >= strtotime(date($anioActual."-01-01"))){
                            $fechaInicialCes = $empleado->fechaIngreso;
                        }
                        else{
                            $fechaInicialCes = $liquidacionesMesesAnterioresCompleta->minimaFecha;
                        }                    
                    }
                    else{
                        $fechaInicialCes = $empleado->fechaIngreso;
                    }       
                    //dd($liquidacionesMesesAnterioresCompleta);
                    if(strtotime($fechaInicialCes)< strtotime(date($anioActual."-01-01"))){
                        $fechaInicialCes = date($anioActual."-01-01");
                    }

                    //dd($fechaInicialCes);
                    
                    $novedadesRetiro = DB::table("novedad","n")
                    ->join("retiro AS r", "r.idRetiro","=","n.fkRetiro")
                    ->where("n.fkEmpleado", "=", $empleado->idempleado)
                    ->where("n.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("n.fkEstado","=","7")
                    ->whereNotNull("n.fkRetiro")
                    ->whereBetween("n.fechaRegistro",[$fechaInicio, $fechaFin])->first();
                    //$fechaFinParaPrima = date("Y-m-t",strtotime($fechaFin));
                    $fechaFinalCes = $fechaFin;
                    if(isset($novedadesRetiro)){
                        $fechaFinalCes = $novedadesRetiro->fechaReal;
                    }            

                    $totalPeriodoPagoAnioActual = $periodoPagoMesActual + $liquidacionesMesesAnterioresCompleta->periodPago;
                    
                    //FIN QUITAR LRN PARA CESANTIAS

                    $itemsBoucherSalarialMesesAnterioresCes = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.valor) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fechaInicio","<",$fechaInicioMes)
                    //->where("ln.idLiquidacionNomina",">=",$primerLiq)
                    ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")                
                    ->where("gcc.fkGrupoConcepto","=","11") //11 - Salarial
                    ->first();

                    $itemsBoucherSalarialMesesAnterioresCesDetalle = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("ibp.valor, ibp.fkConcepto")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fechaInicio","<",$fechaInicioMes)
                    //->where("ln.idLiquidacionNomina",">=",$primerLiq)
                    ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")                
                    ->where("gcc.fkGrupoConcepto","=","11") //11 - Salarial
                    ->get();
                    //dd($salarial, $itemsBoucherSalarialMesesAnterioresCes->suma);
                    //dd($salarial, $itemsBoucherSalarialMesesAnterioresCesDetalle);

                

                    $salarialCes = $salarial + $itemsBoucherSalarialMesesAnterioresCes->suma;

                    
                    
                    if(substr($fechaFinalCes, 8, 2) == "31" || (substr($fechaFinalCes, 8, 2) == "28" && substr($fechaFinalCes, 5, 2) == "02") || (substr($fechaFinalCes, 8, 2) == "29" && substr($fechaFinalCes, 5, 2) == "02")  ){
                        $fechaFinalCes = substr($fechaFinalCes,0,8)."30";
                    }
                    $totalPeriodoPagoAnioActualReal = $this->days_360($fechaInicialCes, $fechaFinalCes);
                    $totalPeriodoPagoAnioActualReal++;
                    
                    $diasLRN_Quita_Ces = 0;
                    //INICIO QUITAR LRN PARA CESANTIAS
                    if($empresa->LRN_cesantias == "1"){

                        $novedadesAus = DB::table("novedad","n")
                        ->selectRaw("sum(a.cantidadDias) as suma")
                        ->join("ausencia as a","a.idAusencia", "=", "n.fkAusencia")
                        ->whereNotNull("n.fkAusencia")            
                        ->where("n.fkEmpleado","=",$empleado->idempleado)
                        ->where("n.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->whereRaw("YEAR(n.fechaRegistro) = '".$anioActual."'")
                        ->whereIn("n.fkEstado",["7", "8"]) // Pagada o sin pagar-> no que este eliminada
                        ->first();
                        $diasLRN_Quita_Ces = $novedadesAus->suma;
                        $totalPeriodoPagoAnioActualReal = $totalPeriodoPagoAnioActualReal - $novedadesAus->suma;
                    }
                    
                    if($totalPeriodoPagoAnioActualReal != 0){
                        $salarialCes = ($salarialCes / $totalPeriodoPagoAnioActualReal)*30;
                    }

                    $centroCostoEmpleadoParaCes = DB::table("centrocosto","c")
                    ->join("empleado_centrocosto as ecc","ecc.fkCentroCosto","=","c.idcentroCosto")
                    ->where("ecc.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
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
                    //dd($baseCes, $baseUnDiaCes, $base3036Dias,$nomina->diasCesantias, $totalPeriodoPagoAnioActualReal,$liquidacionCesantias);
                    $arrComoCalcula[66] = ($arrComoCalcula[66] ?? array());

                    array_push($arrComoCalcula[66], "Valor Salario: $".number_format($salarioCes,0,",","."));
                    array_push($arrComoCalcula[66], "Valor promedio Salarial: $".number_format($salarialCes,0,",","."));
                    array_push($arrComoCalcula[66], "Valor Base: $".number_format($baseCes,0,",","."));
                    array_push($arrComoCalcula[66], "Fecha inicial: ".$fechaInicialCes);
                    array_push($arrComoCalcula[66], "Fecha final: ".$fechaFinalCes);
                    if($diasLRN_Quita_Ces != 0){
                        array_push($arrComoCalcula[66], "Días LRN: ".$diasLRN_Quita_Ces);   
                    }                    
                    array_push($arrComoCalcula[66], "Días: ".$totalPeriodoPagoAnioActualReal);
                    array_push($arrComoCalcula[66], "Nómina configurada a: ".$nomina->diasCesantias." días de cesantias por año");
                    array_push($arrComoCalcula[66], "Valor liquidación : $".number_format($liquidacionCesantias,0,",","."));


                    
                    $historicoProvisionCesantias = DB::table("provision","p")
                    ->selectRaw("sum(p.valor) as sumaValor")
                    ->where("p.fkEmpleado","=",$empleado->idempleado)
                    ->where("p.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("p.mes","<",date("m",strtotime($fechaInicio)))
                    ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                    ->where("p.fkConcepto","=","71")
                    ->first();  

                        

                    $pagoCesantiasItems = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.valor) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                    ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")      
                    ->where("ln.idLiquidacionNomina",">=",$primerLiq)        
                    ->whereIn("ibp.fkConcepto",["66"]) //66 - CESANTIAS
                    ->first();

                    $saldoCesantias = DB::table("saldo")
                    ->where("fkEmpleado","=",$empleado->idempleado)
                    ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("anioAnterior","=",date("Y",strtotime($fechaInicio)))
                    ->where("fkConcepto","=", "71")
                    ->where("fkEstado","=","7")
                    ->first();

                    $arrComoCalculaProv[66] = ($arrComoCalcula[66] ?? array());

                    array_push($arrComoCalculaProv[66], "Se suma a la liquidación anterior junto con la suma de pagos anteriores : $".number_format(( $pagoCesantiasItems->suma ?? 0),0,",","."));
                    array_push($arrComoCalculaProv[66], "Se resta el historico de provisiones por un valor de : $".number_format(( $historicoProvisionCesantias->sumaValor ?? 0),0,",","."));
                    array_push($arrComoCalculaProv[66], "Se resta el saldo por un valor de : $".number_format(( $saldoCesantias->valor ?? 0),0,",","."));

                    $provisionCesantiasValor = $liquidacionCesantias + ( $pagoCesantiasItems->suma ?? 0) - $historicoProvisionCesantias->sumaValor - ($saldoCesantias->valor ?? 0);            
                    array_push($arrComoCalculaProv[66], "Finalmente se tiene una provision de : $".number_format(( $provisionCesantiasValor ?? 0),0,",","."));


                    $provisionCesantias = DB::table("provision","p")
                        ->where("p.fkEmpleado","=",$empleado->idempleado)
                        ->where("p.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("p.mes","=",date("m",strtotime($fechaInicio)))
                        ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                        ->where("p.fkConcepto","=","71")
                        ->get();

                    $arrProvisionCesantias = array(
                        "fkPeriodoActivo" => $periodoActivoReintegro->idPeriodo,
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
                    $primeraLiquidacion = DB::table("liquidacionnomina", "ln")
                    ->selectRaw("min(ln.fechaInicio) as primeraFecha")
                    ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")             
                    ->whereIn("ln.fkTipoLiquidacion",["1","2", "3", "4","5","6","9"])   
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->first();

                    $minimaFecha = date("Y-m-d");
                    $inicioAnio = date("Y-01-01");
                    if(isset($primeraLiquidacion->primeraFecha)){
                        $minimaFecha = $primeraLiquidacion->primeraFecha;
                    }
                    $diasAgregar = 0;
                    //Verificar si dicha nomina es menor a la fecha de ingreso en este mismo año
                    if(strtotime($empleado->fechaIngreso) < strtotime($minimaFecha) && date("Y",strtotime($minimaFecha))==date("Y",strtotime($fechaInicio)) && date("Y",strtotime($minimaFecha))==date("Y",strtotime($empleado->fechaIngreso)) ){
                        $diasAgregar = $this->days_360($empleado->fechaIngreso, $minimaFecha);
                    }
                    else if(strtotime($inicioAnio) < strtotime($minimaFecha)){
                        $diasAgregar = $this->days_360($inicioAnio, $primerLiqFecha);
                    }         
                    
                    
                    
                    $totalPeriodoPagoAnioActual =  $totalPeriodoPagoAnioActualReal;
                    $fechaFinIntCes = $fechaFin; 
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
                    
                    $arrComoCalcula[69] = ($arrComoCalcula[69] ?? array());

                    array_push($arrComoCalcula[69], "Valor liquidación cesantias: $".number_format($liquidacionCesantias,0,",","."));
                    /*if(isset($saldoCesantias->valor)){
                        array_push($arrComoCalcula[69], "Se suma valor de saldo de cesantias por: $".number_format($saldoCesantias->valor,0,",","."));
                    }*/
                    array_push($arrComoCalcula[69], "Se calcula el porcentaje para el periodo actual: (".$totalPeriodoPagoAnioActual." días * 12%) / 360");
                    array_push($arrComoCalcula[69], "Multiplicado por un porcentaje de ".($interesesPorcen*100)."%");
                    array_push($arrComoCalcula[69], "Fecha inicial: ".$fechaInicialCes);
                    array_push($arrComoCalcula[69], "Fecha final: ".$fechaFinIntCes);
                    array_push($arrComoCalcula[69], "Días: ".$totalPeriodoPagoAnioActual);
                    array_push($arrComoCalcula[69], "Valor liquidación : $".number_format($liquidacionIntCesantias,0,",","."));

                    


                    $historicoProvisionIntCesantias = DB::table("provision","p")
                    ->selectRaw("sum(p.valor) as sumaValor")
                    ->where("p.fkEmpleado","=",$empleado->idempleado)
                    ->where("p.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("p.mes","<",date("m",strtotime($fechaInicio)))
                    ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                    ->where("p.fkConcepto","=","72")
                    ->first();

                    $saldoIntCesantias = DB::table("saldo")
                    ->where("fkEmpleado","=",$empleado->idempleado)
                    ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("anioAnterior","=",date("Y",strtotime($fechaInicio)))
                    ->where("fkConcepto","=", "72")
                    ->where("fkEstado","=","7")
                    ->first();

                    $pagoInteresesItems = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.valor) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                    ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")              
                    ->whereIn("ibp.fkConcepto",["69"]) //69 - INTERESES CESANTIAS	
                    ->first();


                        
                    $arrComoCalculaProv[69] = ($arrComoCalcula[69] ?? array());

                    array_push($arrComoCalculaProv[69], "Se suma a la liquidación anterior junto con el pago de cesantias anteriores : $".number_format(( $pagoInteresesItems->suma ?? 0),0,",","."));
                    array_push($arrComoCalculaProv[69], "Se resta el historico de provisiones por un valor de : $".number_format(( $historicoProvisionIntCesantias->sumaValor ?? 0),0,",","."));
                    array_push($arrComoCalculaProv[69], "Se resta el saldo por un valor de : $".number_format((isset($saldoIntCesantias->valor) ? $saldoIntCesantias->valor : 0),0,",","."));
                    $provisionIntCesantiasValor = $liquidacionIntCesantias + ($pagoInteresesItems->suma ?? 0)  - $historicoProvisionIntCesantias->sumaValor - (isset($saldoIntCesantias->valor) ? $saldoIntCesantias->valor : 0);
                    array_push($arrComoCalculaProv[69], "Finalmente se tiene una provision de : $".number_format(( $provisionIntCesantiasValor ?? 0),0,",","."));


                    
                    
                    $provisionIntCesantias = DB::table("provision","p")
                        ->where("p.fkEmpleado","=",$empleado->idempleado)
                        ->where("p.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("p.mes","=",date("m",strtotime($fechaInicio)))
                        ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                        ->where("p.fkConcepto","=","72")
                        ->get();

                    $arrProvisionIntCesantias = array(
                        "fkPeriodoActivo" => $periodoActivoReintegro->idPeriodo,
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
                    
                    $periodoPagoVac = $this->days_360($empleado->fechaIngreso,$fechaFinPeriodoVac) + 1 ;
                    
                    if(isset($novedadesRetiro)){
                        if(strtotime($fechaFin) > strtotime($novedadesRetiro->fechaReal)){
                            $fechaFinPeriodoVac = $novedadesRetiro->fechaReal;
                            if(substr($novedadesRetiro->fechaReal, 8, 2) == "31" || (substr($novedadesRetiro->fechaReal, 8, 2) == "28" && substr($novedadesRetiro->fechaReal, 5, 2) == "02") || (substr($novedadesRetiro->fechaReal, 8, 2) == "29" && substr($novedadesRetiro->fechaReal, 5, 2) == "02")){
                                $fechaFinPeriodoVac = substr($novedadesRetiro->fechaReal, 0, 8)."30";
                            }
                        
                            $periodoPagoVac = $this->days_360($empleado->fechaIngreso,$fechaFinPeriodoVac) + 1 ;
                        }
                    }
                    $arrComoCalcula[30] = ($arrComoCalcula[30] ?? array());
                    array_push($arrComoCalcula[30],"Calculo el total de diás con fecha inicio: ".$empleado->fechaIngreso." y fecha fin: ".$fechaFinPeriodoVac." para un total de ".$periodoPagoVac." días");


                    //INICIO QUITAR LRN VAC
                    $novedadesAus = DB::table("novedad","n")
                    ->selectRaw("sum(a.cantidadDias) as suma")
                    ->join("ausencia as a","a.idAusencia", "=", "n.fkAusencia")
                    ->whereNotNull("n.fkAusencia")            
                    ->where("n.fkEmpleado","=",$empleado->idempleado)
                    ->where("n.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->whereIn("n.fkEstado",["7", "8"]) // Pagada o sin pagar-> no que este eliminada
                    ->first();
                    $periodoPagoVac = $periodoPagoVac - $novedadesAus->suma;
                    if($novedadesAus->suma != 0){
                        array_push($arrComoCalcula[30],"Al periodo anterior se le restan ".$novedadesAus->suma." días de ausencia");
                    }
                    
                    //FIN QUITAR LRN PARA VAC
                    
                    
                    
                    

                    $salarialVac = 0;
                    $grupoConceptoCalculoVac = DB::table("grupoconcepto_concepto","gcc")
                        ->where("gcc.fkGrupoConcepto", "=", "13")//Salarial vacaciones
                        ->get();
                    foreach($grupoConceptoCalculoVac as $grupoConcepto){
                        if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto != 36){
                            $salarialVac = $salarialVac + floatval($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor']);
                        }
                    }
                
                    $liquidacionMesActual = DB::table("liquidacionnomina", "ln")
                    ->select("bp.periodoPago","ln.idLiquidacionNomina")
                    ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fechaInicio","=",$fechaInicioMes)
                    ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                    ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
                    ->first();

                    if(isset($liquidacionMesActual)){
                        $itemsBoucherSalarialMesAct = DB::table("item_boucher_pago", "ibp")
                        ->selectRaw("Sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("bp.fkLiquidacion","=",$liquidacionMesActual->idLiquidacionNomina)                        
                        ->where("gcc.fkGrupoConcepto","=","13") //13 - Salarial vacaciones
                        ->first();
                        $salarialVac = $salarialVac + $itemsBoucherSalarialMesAct->suma;

                        $itemsBoucherSalarialMesAct16 = DB::table("item_boucher_pago", "ibp")
                        ->selectRaw("Sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.fechaInicio","=",date("Y-m-16",strtotime($fechaInicioMes)))
                        ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                        ->where("gcc.fkGrupoConcepto","=","13") //13 - Salarial vacaciones
                        ->first();
                        $salarialVac = $salarialVac + ($itemsBoucherSalarialMesAct16->suma ?? 0);
                    }
                    
                    
                    $itemsBoucherSalarialMesAnteriorVac = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.valor) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fechaInicio","=",$fechaInicioMes)
                    ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina);

                    if(isset($liquidacionMesActual)){
                        $itemsBoucherSalarialMesAnteriorVac = $itemsBoucherSalarialMesAnteriorVac->where("ln.idLiquidacionNomina","<>",$liquidacionMesActual->idLiquidacionNomina);
                    }
                    $itemsBoucherSalarialMesAnteriorVac = $itemsBoucherSalarialMesAnteriorVac->where("gcc.fkGrupoConcepto","=","13") //13 - Salarial vacaciones
                    ->first();

                    if(isset($itemsBoucherSalarialMesAnteriorVac)){
                        $salarialVac = $salarialVac + $itemsBoucherSalarialMesAnteriorVac->suma;
                    }
                   

                    $diasVac = $periodoPagoVac * 15 / 360;
                    array_push($arrComoCalcula[30],"Se calcula la cantidad de dias disponibles de vacaciones = ".$diasVac." días disponibles");

                    
                    //INICIO QUITAR DIAS VAC
                    $novedadesVacacion = DB::table("novedad","n")
                    ->join("vacaciones as v","v.idVacaciones","=","n.fkVacaciones")
                    ->where("n.fkEmpleado","=",$empleado->idempleado)
                    ->where("n.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->whereIn("n.fkEstado",["7", "8","16"]) // Pagada o sin pagar-> no que este eliminada
                    ->where("n.fechaRegistro","<=",$fechaFin)
                    ->where("n.fechaRegistro",">=",$empleado->fechaIngreso)
                    ->whereNotNull("n.fkVacaciones")
                    ->get();
            

                    foreach($novedadesVacacion as $novedadVacacion){
                        array_push($arrComoCalcula[30],"Se restan = ". ($novedadVacacion->fkConcepto == "28" ? $novedadVacacion->diasCompensar : $novedadVacacion->diasCompletos)." días de los disponibles, id_novedad = ".$novedadVacacion->idNovedad);
                        $diasVac = $diasVac - ($novedadVacacion->fkConcepto == "28" ? $novedadVacacion->diasCompensar : $novedadVacacion->diasCompletos);
                        
                    }
                    
                    if(isset($diasVac) && $diasVac < 0 && $empresa->vacacionesNegativas == 0){
                        $diasVac = 0;
                    }
                    $diasVac = round($diasVac, 2);
                    array_push($arrComoCalcula[30],"Finalmente se tienen dias disponibles de vacaciones = ".$diasVac." días disponibles");

                    //FIN QUITAR DIAS VAC


                    // $diasVac = $totalPeriodoPagoAnioActual * 15 / 360;
                    if(isset($novedadesRetiro)){
                        $fechaFinVac = $novedadesRetiro->fecha;
                    }    
                    else{
                        $fechaFinVac = $fechaFin;
                    }

                    $fechaInicioParaVacaciones = date("Y-m-d", strtotime($fechaFinVac." - 1 YEAR"));
                    //$fechaInicioAnio= date("Y-01-01", strtotime($fechaFin));

                    if(strtotime($fechaInicioParaVacaciones) < strtotime($empleado->fechaIngreso) ){
                        $fechaInicioParaVacaciones = $empleado->fechaIngreso;
                    }


                   
                    
                    $itemsBoucherSalarialMesesAnterioresVac = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.valor) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fechaInicio","<",$fechaInicioMes)
                    ->where("ln.fechaLiquida",">=",$fechaInicioParaVacaciones)
                        //->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")                    
                    ->where("gcc.fkGrupoConcepto","=","13") //13 - Salarial vacaciones
                    ->first();
                    
                    $salarialVac = $salarialVac + $itemsBoucherSalarialMesesAnterioresVac->suma;
                    
                    /*if($totalPeriodoPagoAnioActual!=0){
                        $salarialVac = ($salarialVac / $totalPeriodoPagoAnioActual)*30;
                    }
                    else{
                        
                    }
                    ;*/
                    $diff = $this->days_360($empleado->fechaIngreso, $fechaFinPeriodoVac) + 1;
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
                    //dd($salarialVac,$empleado->fechaIngreso, $fechaFin);
                    
                    
                    
                    $salarioVac = 0;

                    foreach($conceptosFijosEmpl as $conceptoFijoEmpl){
                        if($conceptoFijoEmpl->fkConcepto=="1" || $conceptoFijoEmpl->fkConcepto=="2"){
                            $salarioVac = $conceptoFijoEmpl->valor; 
                        }
                    }
                    if($empleado->fkTipoCotizante == 51){
                        //Todas mis liquidaciones 12 meses atras
                        $fechaFinVac51 = date("Y-m-d", strtotime($fechaInicioMes." - 1 YEAR"));
                        $liquidacionesMesesAnterioresVac51 = DB::table("liquidacionnomina", "ln")
                        ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.diasTrabajados) as diasTrabajadosPer, sum(bp.diasIncapacidad) as diasIncapacidadPer, sum(bp.salarioPeriodoPago) as salarioPago, min(ln.fechaInicio) as minimaFecha")
                        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.fechaInicio","<",$fechaInicioMes)
                        ->where("ln.fechaInicio",">=",$fechaFinVac51)
                        ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
                        ->first();

                        //INICIO CALCULAR EL PROMEDIO SALARIO EN PERIODO ACTUAL    
                        

                        //$totalPeriodoPago51 = $periodoPagoMesActual + (isset($liquidacionesMesesAnterioresCes51->periodPago) ? $liquidacionesMesesAnterioresCes51->periodPago : 0);
                        $totalPeriodoPagoParaSalario51 = $periodoPagoMesActual + (isset($liquidacionesMesesAnterioresVac51->periodPago) ? $liquidacionesMesesAnterioresVac51->periodPago : 0);
                        
                        $retroActivoMesesAnterioresVac51 = DB::table("liquidacionnomina", "ln")
                        ->selectRaw("sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                        ->join("item_boucher_pago as ibp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.fechaInicio","<",$fechaInicio)
                        ->where("ln.fechaInicio",">=",$fechaFinVac51)
                        ->where("ibp.fkConcepto","=","49") // 49 - RETROACTIVO SALARIO
                        ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
                        ->first();
                        
                        /*dd([
                            "salarioMes" => $salarioMes,
                            "liquidacionesMesesAnterioresPrima" => $liquidacionesMesesAnterioresPrima,
                            "retroActivoMesesAnterioresPrima" => $retroActivoMesesAnterioresPrima
                        ]);*/

                        
                        $salarioVac = $salarioMes + ($liquidacionesMesesAnterioresVac51->salarioPago ?? 0) + ($retroActivoMesesAnterioresVac51->suma ?? 0);
                        $salarioVac = ($salarioVac / $totalPeriodoPagoParaSalario51)*30;

                    }


                    $baseVac = $salarioVac + $salarialVac;
                    
                    $liquidacionVac = ($baseVac/30)*$diasVac;
                    
                    
                    array_push($arrComoCalcula[30],"Valor salario $".number_format($salarioVac,0,",","."));
                    array_push($arrComoCalcula[30],"Valor salarial $".number_format($salarialVac,0,",","."));
                    array_push($arrComoCalcula[30],"Valor base $".number_format($baseVac,0,",","."));
                    array_push($arrComoCalcula[30],"Dias: ".$diasVac." días");
                    array_push($arrComoCalcula[30],"Valor liquidación $".number_format($liquidacionVac,0,",","."));
                   

                    $historicoProvisionVac = DB::table("provision","p")
                    ->selectRaw("sum(p.valor) as sumaValor")
                    ->where("p.fkEmpleado","=",$empleado->idempleado)
                    ->where("p.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("p.mes","<",date("m",strtotime($fechaInicio)))
                    ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                    ->where("p.fkConcepto","=","74")
                    ->first();

                    $saldoVacaciones = DB::table("saldo")
                    ->where("fkEmpleado","=",$empleado->idempleado)
                    ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("anioAnterior","=",date("Y",strtotime($fechaInicio)))
                    ->where("fkConcepto","=", "74")
                    ->where("fkEstado","=","7")
                    ->first();

                    $pagoVacacionesItems = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.valor) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                    ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")              
                    ->whereIn("ibp.fkConcepto",["28","29","30"]) //VACACIONES
                    ->first();
                

                    $arrComoCalculaProv[30] = ($arrComoCalcula[30] ?? array());
                    array_push($arrComoCalculaProv[30], "Se suma a la liquidación anterior junto con el pago de vacaciones anteriores : $".number_format(( $pagoVacacionesItems->suma ?? 0),0,",","."));
                    array_push($arrComoCalculaProv[30], "Se resta el historico de provisiones por un valor de : $".number_format(( $historicoProvisionVac->sumaValor ?? 0),0,",","."));
                    array_push($arrComoCalculaProv[30], "Se resta el saldo por un valor de : $".number_format((isset($saldoVacaciones->valor) ? $saldoVacaciones->valor : 0),0,",","."));

                    $provAnteriores = $historicoProvisionVac->sumaValor + (isset($saldoVacaciones) ? $saldoVacaciones->valor : 0) - ($pagoVacacionesItems->suma ?? 0);
                    
                    /*if(isset($arrValorxConcepto[28])){
                        $provAnteriores = $provAnteriores - $arrValorxConcepto[28]["valor"];
                        array_push($arrComoCalculaProv[30], "Se suma pago de vacaciones en el mes actual por un valor de : $".number_format($arrValorxConcepto[28]["valor"],0,",","."));
                    }

                    if(isset($arrValorxConcepto[29])){
                        $provAnteriores = $provAnteriores - $arrValorxConcepto[29]["valor"];
                        array_push($arrComoCalculaProv[30], "Se suma pago de vacaciones en el mes actual por un valor de : $".number_format($arrValorxConcepto[29]["valor"],0,",","."));
                    }*/

                    
                    $provisionVacValor = $liquidacionVac - $provAnteriores;
                    array_push($arrComoCalculaProv[30], "Finalmente se tiene una provision de : $".number_format(( $provisionVacValor ?? 0),0,",","."));

                    $provisionVacaciones = DB::table("provision","p")
                        ->where("p.fkEmpleado","=",$empleado->idempleado)
                        ->where("p.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("p.mes","=",date("m",strtotime($fechaInicio)))
                        ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                        ->where("p.fkConcepto","=","74")
                        ->get();
                            
               
                    $arrProvisionVacaciones = array(
                        "fkPeriodoActivo" => $periodoActivoReintegro->idPeriodo,
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

                if($tipoliquidacion == "7"){//TipoLiquidacion solo PRIMA

                    $arrComoCalcula[58] = array();

                    $arrResPrima = $this->calcularPrima($fechaInicio, $liquidacionNomina->fechaPrima, $empleado, $periodo, $periodoPagoSinVac, $arrValorxConcepto, $idLiquidacionNomina, $arrComoCalcula[58]);
                    $liquidacionAnticipoPrima = $arrResPrima["liquidacionPrima"];
                    $fechaInicialPrima = $arrResPrima["fechaInicialPrima"];
                    $fechaFinalPrima = $arrResPrima["fechaFinalPrima"];
                    $totalPeriodoPago = $arrResPrima["totalPeriodoPago"];
                    $basePrima = $arrResPrima["basePrima"];
                    $salarioPrima = $arrResPrima["salarioPrima"];
                    $salarialPrima = $arrResPrima["salarialPrima"];
                    $arrComoCalcula[58] = $arrResPrima["arrComoCalcula"];
                    array_push($arrComoCalcula[58], "Valor Salario: $".number_format($arrResPrima["salarioPrima"],0,",","."));
                    array_push($arrComoCalcula[58], "Valor promedio Salarial: $".number_format($arrResPrima["salarialPrima"],0,",","."));
                    array_push($arrComoCalcula[58], "Valor Base: $".number_format($arrResPrima["basePrima"],0,",","."));
                    array_push($arrComoCalcula[58], "Fecha inicial: ".$arrResPrima["fechaInicialPrima"]);
                    array_push($arrComoCalcula[58], "Fecha final: ".$arrResPrima["fechaFinalPrima"]);
                    array_push($arrComoCalcula[58], "Días: ".$arrResPrima["totalPeriodoPago"]);
                    array_push($arrComoCalcula[58], "Valor liquidación prima: $".number_format($arrResPrima["liquidacionPrima"],0,",","."));



                    $mesProyeccion = intval(date("m",strtotime($liquidacionNomina->fechaPrima)));
                
                    if(isset($arrResPrima["saldoPrima"])){
                        $liquidacionAnticipoPrima = $liquidacionAnticipoPrima;// + $arrResPrima["saldoPrima"]->suma;
                    }


                    if($mesProyeccion >= 1 && $mesProyeccion <= 6){
                        
                        $itemsBoucherAnticipoNominaMesesAnteriores = DB::table("item_boucher_pago", "ibp")
                        ->selectRaw("Sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.fechaInicio","<",$fechaInicioMes)
                        ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")              
                        ->where("ibp.fkConcepto","=","78") //78 - Anticipo prima
                        ->first();
                        if(isset($itemsBoucherAnticipoNominaMesesAnteriores)){
                            $liquidacionAnticipoPrima = $liquidacionAnticipoPrima - $itemsBoucherAnticipoNominaMesesAnteriores->suma;
                            array_push($arrComoCalcula[58],"Se resta el valor de anticipos de meses anteriores: $".number_format($itemsBoucherAnticipoNominaMesesAnteriores->suma, 0, ",","."));
                            if($liquidacionAnticipoPrima < 0){
                                $liquidacionAnticipoPrima = 0;
                            }
                        }
                    }
                    else if($mesProyeccion >= 7 && $mesProyeccion <= 12){
                    
                        $itemsBoucherAnticipoNominaMesesAnteriores = DB::table("item_boucher_pago", "ibp")
                        ->selectRaw("Sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.fechaInicio","<",$fechaInicioMes)
                        ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."' and MONTH(ln.fechaInicio) > 6")                
                        ->where("ibp.fkConcepto","=","78") //78 - Anticipo prima
                        ->first();

                        if(isset($itemsBoucherAnticipoNominaMesesAnteriores)){
                            $liquidacionAnticipoPrima = $liquidacionAnticipoPrima - $itemsBoucherAnticipoNominaMesesAnteriores->suma;
                            array_push($arrComoCalcula[58],"Se resta el valor de anticipos de meses anteriores: $".number_format($itemsBoucherAnticipoNominaMesesAnteriores->suma, 0, ",","."));
                            if($liquidacionAnticipoPrima < 0){
                                $liquidacionAnticipoPrima = 0;
                            }
                        }
                        
                    }
                    
                    if( $liquidacionAnticipoPrima > 0){
                                        
                        //Obtener la primera liquidacion de nomina de la persona 
                        $primeraLiquidacion = DB::table("liquidacionnomina", "ln")
                        ->selectRaw("min(ln.fechaInicio) as primeraFecha")
                        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")             
                        ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])   
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->first();

                        $minimaFecha = date("Y-m-d");
                        
                        if(isset($primeraLiquidacion)){
                            $minimaFecha = $primeraLiquidacion->primeraFecha;
                        }
                        $diasAgregar = 0;
                        //Verificar si dicha nomina es menor a la fecha de ingreso
                        if(strtotime($empleado->fechaIngreso) < strtotime($minimaFecha) && date("Y",strtotime($minimaFecha))==date("Y",strtotime($fechaInicio)) && date("Y",strtotime($minimaFecha))==date("Y",strtotime($empleado->fechaIngreso)) ){
                            $diasAgregar = $this->days_360($empleado->fechaIngreso, $minimaFecha);
                        }   
                        
                        
                        $arrSaldo = array();
                        if(isset($arrResPrima["saldoPrima"])){
                            $arrSaldo = array([
                                "idSaldo" => $arrResPrima["saldoPrima"]->idSaldo,
                                "valor" => $arrResPrima["saldoPrima"]->valor
                            ]);
                        }

                        if(isset($arrValorxConcepto[58])){
                            $arrValorxConcepto[58]["valor"] = $arrValorxConcepto[58]["valor"] + $liquidacionAnticipoPrima;
                            $arrValorxConcepto[58]["naturaleza"] =  "1";
                            $arrValorxConcepto[58]["unidad"] =  "DÍA";
                            $arrValorxConcepto[58]["cantidad"] = $totalPeriodoPago + $diasAgregar;
                            $arrValorxConcepto[58]["arrNovedades"] = array();
                            $arrValorxConcepto[58]["arrSaldo"] = $arrSaldo;
                            $arrValorxConcepto[58]["tipoGen"] = "automaticos";
                            $arrValorxConcepto[58]["base"] = $basePrima;
                            $arrValorxConcepto[58]["fechaInicio"] = $fechaInicialPrima;
                            $arrValorxConcepto[58]["fechaFin"] = $fechaFinalPrima;
                            
                        }
                        else{
                            $arrValorxConcepto[58] = array(
                                "naturaleza" => "1",
                                "unidad" => "DÍA",
                                "cantidad"=> ($totalPeriodoPago + $diasAgregar),
                                "arrNovedades"=> array(),
                                "arrSaldo" => $arrSaldo,
                                "valor" => $liquidacionAnticipoPrima,
                                "tipoGen" => "automaticos",
                                "base" => $basePrima,
                                "fechaInicio" => $fechaInicialPrima,
                                "fechaFin" => $liquidacionNomina->fechaPrima
                            );
                        }
                        
                                        
                    }
                }
                
                if($tipoliquidacion == "10" || $tipoliquidacion == "9"){
                    $saldoIntCesantiasAnioAnterior = DB::table("saldo")
                    ->where("fkEmpleado","=",$empleado->idempleado)
                    ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("anioAnterior","=",date("Y",strtotime($fechaInicio)))
                    ->where("fkConcepto","=", "68")//INTERESES CESANTIAS AÑO ANTERIOR
                    ->where("fkEstado","=","7")
                    ->where("valor", ">", "0")
                    ->first();
                    if(isset($saldoIntCesantiasAnioAnterior)){
                        $arrComoCalcula[68] = ($arrComoCalcula[68] ?? array());

                        array_push($arrComoCalcula[68],"Valor intereses de año anterior en saldos $".number_format($saldoIntCesantiasAnioAnterior->valor,0,",","."));

                        $arrValorxConcepto[68] = array(
                            "naturaleza" => "1",
                            "unidad" => "VALOR",
                            "cantidad"=> "0",
                            "arrNovedades"=> array(),
                            "arrSaldo"=> array([
                                "idSaldo" => $saldoIntCesantiasAnioAnterior->idSaldo,
                                "valor" => $saldoIntCesantiasAnioAnterior->valor
                            ]),
                            "valor" => $saldoIntCesantiasAnioAnterior->valor,
                            "tipoGen" => "automaticos"
                        );
                    } 
                }

                if($tipoliquidacion == "5"){ //Normal + Prima
                    //Obtener la primera liquidacion de nomina de la persona 
                    $primeraLiquidacion = DB::table("liquidacionnomina", "ln")
                    ->selectRaw("min(ln.fechaInicio) as primeraFecha")
                    ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")             
                    ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])   
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->first();
                    $minimaFecha = date("Y-m-d");                
                    if(isset($primeraLiquidacion)){
                        $minimaFecha = $primeraLiquidacion->primeraFecha;
                    }
                    $diasAgregar = 0;
                    //Verificar si dicha nomina es menor a la fecha de ingreso
                    if(strtotime($empleado->fechaIngreso) < strtotime($minimaFecha) && date("Y",strtotime($minimaFecha))==date("Y",strtotime($fechaInicio)) && date("Y",strtotime($minimaFecha))==date("Y",strtotime($empleado->fechaIngreso)) ){
                        $diasAgregar = $this->days_360($empleado->fechaIngreso, $minimaFecha);
                    }   
                    

                    $arrSaldo = array();
                    $saldoPrima = 0;
                    if(isset($arrResPrima["saldoPrima"])){
                        $arrSaldo = array([
                            "idSaldo" => $arrResPrima["saldoPrima"]->idSaldo,
                            "valor" => $arrResPrima["saldoPrima"]->valor
                        ]);
                        //$saldoPrima = $arrResPrima["saldoPrima"]->valor;
                        
                        array_push($arrComoCalcula[58],"Se agrega valor de saldo en prima: $".number_format($saldoPrima, 0, ",","."));
                    }
                    
                    if(isset($arrValorxConcepto[58])){
                        $arrValorxConcepto[58]["valor"] = $arrValorxConcepto[58]["valor"] + $liquidacionPrima + $saldoPrima;
                        $arrValorxConcepto[58]["naturaleza"] =  "1";
                        $arrValorxConcepto[58]["unidad"] =  "DÍA";
                        $arrValorxConcepto[58]["cantidad"] = $totalPeriodoPago + $diasAgregar;
                        $arrValorxConcepto[58]["arrNovedades"] = array();
                        $arrValorxConcepto[58]["arrSaldo"] = $arrSaldo;
                        $arrValorxConcepto[58]["tipoGen"] = "automaticos";
                        $arrValorxConcepto[58]["base"] = $basePrima;
                        $arrValorxConcepto[58]["fechaInicio"] = $fechaInicialPrima;
                        $arrValorxConcepto[58]["fechaFin"] = $fechaFinalPrima;
                        
                    }
                    else{
                        $arrValorxConcepto[58] = array(
                            "naturaleza" => "1",
                            "unidad" => "DÍA",
                            "cantidad"=> $totalPeriodoPago + $diasAgregar,
                            "arrNovedades"=> array(),
                            "arrSaldo" => $arrSaldo,
                            "valor" => $liquidacionPrima + $saldoPrima,
                            "tipoGen" => "automaticos",
                            "base" => $basePrima,
                            "fechaInicio" => $fechaInicialPrima,
                            "fechaFin" => $fechaFinalPrima
                            
                        );
                    }

                    
                    $mesActual = intval(date("m",strtotime($fechaInicio))); 

                    if($mesActual >= 1 && $mesActual <= 6){
                    

                        $itemsBoucherAnticipoNominaMesesAnteriores = DB::table("item_boucher_pago", "ibp")
                        ->selectRaw("Sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo);
                        
                        if($periodo == 15){
                            $itemsBoucherAnticipoNominaMesesAnteriores = $itemsBoucherAnticipoNominaMesesAnteriores->where("ln.fechaInicio","<=",date("Y-m-16", strtotime($fechaInicioMes)));
                        }
                        else{
                            $itemsBoucherAnticipoNominaMesesAnteriores = $itemsBoucherAnticipoNominaMesesAnteriores->where("ln.fechaInicio","<=",$fechaInicioMes);    
                        }
                        $itemsBoucherAnticipoNominaMesesAnteriores = $itemsBoucherAnticipoNominaMesesAnteriores->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")              
                        ->where("ibp.fkConcepto","=","78") //78 - Anticipo prima
                        ->first();
                        
                        if(isset($itemsBoucherAnticipoNominaMesesAnteriores)){
                            
                            array_push($arrComoCalcula[58],"Se resta el valor de anticipos de meses anteriores: $".number_format($itemsBoucherAnticipoNominaMesesAnteriores->suma, 0, ",","."));
                            $arrValorxConcepto[58]['valor'] = $arrValorxConcepto[58]['valor']  - $itemsBoucherAnticipoNominaMesesAnteriores->suma;
                        }
                        
                        $itemsBoucherPrimaMesesAnteriores = DB::table("item_boucher_pago", "ibp")
                        ->selectRaw("Sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo);
                        
                        if($periodo == 15){
                            
                            $itemsBoucherPrimaMesesAnteriores = $itemsBoucherPrimaMesesAnteriores->where("ln.fechaInicio","<=",date("Y-m-16", strtotime($fechaInicioMes)));
                        }
                        else{
                            $itemsBoucherPrimaMesesAnteriores = $itemsBoucherPrimaMesesAnteriores->where("ln.fechaInicio","<=",$fechaInicioMes);    
                        }
                        $itemsBoucherPrimaMesesAnteriores = $itemsBoucherPrimaMesesAnteriores->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                        ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")              
                        ->where("ibp.fkConcepto","=","58") //58 - Prima de servicios
                        ->first();

                        
                        if($itemsBoucherPrimaMesesAnteriores->suma > 0){
                            array_push($arrComoCalcula[58],"Se resta el valor de primas anteriores: $".number_format($itemsBoucherPrimaMesesAnteriores->suma, 0, ",","."));
                            $arrValorxConcepto[58]['valor'] = $arrValorxConcepto[58]['valor']  - $itemsBoucherPrimaMesesAnteriores->suma;
                        }

                    }
                    else if($mesActual >= 7 && $mesActual <= 12){
                        $itemsBoucherAnticipoNominaMesesAnteriores = DB::table("item_boucher_pago", "ibp")
                        ->selectRaw("Sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo);
                        if($periodo == 15){
                            $itemsBoucherAnticipoNominaMesesAnteriores = $itemsBoucherAnticipoNominaMesesAnteriores->where("ln.fechaInicio","<=",date("Y-m-16", strtotime($fechaInicioMes)));
                        }
                        else{
                            $itemsBoucherAnticipoNominaMesesAnteriores = $itemsBoucherAnticipoNominaMesesAnteriores->where("ln.fechaInicio","<=",$fechaInicioMes);    
                        }
                        $itemsBoucherAnticipoNominaMesesAnteriores = $itemsBoucherAnticipoNominaMesesAnteriores->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."' and MONTH(ln.fechaInicio) > 6")                
                        ->where("ibp.fkConcepto","=","78") //78 - Anticipo prima
                        ->first();

                        if(isset($itemsBoucherAnticipoNominaMesesAnteriores)){
                            
                           //array_push($arrComoCalcula[58],"Se resta el valor de anticipos de meses anteriores: $".number_format($itemsBoucherAnticipoNominaMesesAnteriores->suma, 0, ",","."));
                            //dd("aca", $itemsBoucherAnticipoNominaMesesAnteriores->suma);
                            array_push($arrComoCalcula[58],"Se resta el valor de anticipos de meses anteriores: $".number_format($itemsBoucherAnticipoNominaMesesAnteriores->suma, 0, ",","."));
                            $arrValorxConcepto[58]['valor'] = $arrValorxConcepto[58]['valor']  - $itemsBoucherAnticipoNominaMesesAnteriores->suma;


                            /*$arrValorxConcepto[78] = array(
                                "naturaleza" => "3",
                                "unidad" => "UNIDAD",
                                "cantidad"=> "0",
                                "arrNovedades"=> array(),
                                "valor" => ($itemsBoucherAnticipoNominaMesesAnteriores->suma * -1),
                                "tipoGen" => "automaticos"
                            );*/
                        }              
                        
                        
                        $itemsBoucherPrimaMesesAnteriores = DB::table("item_boucher_pago", "ibp")
                        ->selectRaw("Sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo);
                        if($periodo == 15){
                            $itemsBoucherPrimaMesesAnteriores = $itemsBoucherPrimaMesesAnteriores->where("ln.fechaInicio","<=",date("Y-m-16", strtotime($fechaInicioMes)));
                        }
                        else{
                            $itemsBoucherPrimaMesesAnteriores = $itemsBoucherPrimaMesesAnteriores->where("ln.fechaInicio","<=",$fechaInicioMes);    
                        }
                        $itemsBoucherPrimaMesesAnteriores = $itemsBoucherPrimaMesesAnteriores->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                        ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."' and MONTH(ln.fechaInicio) > 6")   
                        ->where("ibp.fkConcepto","=","58") //58 - Prima de servicios
                        ->first();

                        
                        if($itemsBoucherPrimaMesesAnteriores->suma > 0){
                            array_push($arrComoCalcula[58],"Se resta el valor de primas anteriores: $".number_format($itemsBoucherPrimaMesesAnteriores->suma, 0, ",","."));
                            $arrValorxConcepto[58]['valor'] = $arrValorxConcepto[58]['valor']  - $itemsBoucherPrimaMesesAnteriores->suma;
                        }
                    }      



                    $itemsBoucherMismoPeriodoNomin = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("ibp.*")
                    ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fechaInicio","=",$fechaInicio)
                    ->where("ln.fechaFin","=",$fechaFin)
                    ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                    ->where("gcc.fkGrupoConcepto", "=","46")
                    ->whereNotIn("ln.fkTipoLiquidacion",["7","10","11"]) //Puede tenerlas en el mismo periodo
                    ->get();
                    
                    foreach($itemsBoucherMismoPeriodoNomin as $itemBoucherMismoPeriodoNomin){                        
                        /*if(isset($arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto])){

                            array_push($arrComoCalcula[$itemBoucherMismoPeriodoNomin->fkConcepto],
                            "Se resta el valor de una liquidación en el mismo periodo: $".number_format($itemBoucherMismoPeriodoNomin->valor, 0, ",","."));

                            array_push($arrComoCalcula[$itemBoucherMismoPeriodoNomin->fkConcepto],
                            "Se resta la cantidad de una liquidación en el mismo periodo: ".$itemBoucherMismoPeriodoNomin->cantidad);

                            $arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["valor"] = round($arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["valor"]) - $itemBoucherMismoPeriodoNomin->valor;
                            $arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["cantidad"] = round($arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["cantidad"]) - $itemBoucherMismoPeriodoNomin->cantidad;
                        }*/
                    }


                    if($periodo == 15){                
                        if(substr($liquidacionNomina->fechaInicio,8,2) == "01"){
                            $itemsBoucherPeriodo16 = DB::table("item_boucher_pago", "ibp")
                            ->selectRaw("ibp.*")
                            ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")
                            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                            ->where("bp.fkEmpleado","=",$empleado->idempleado)
                            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                            ->where("ln.fechaInicio","=",date("Y-m-16",strtotime($fechaInicio)))
                            ->where("ln.fechaFin","=",date("Y-m-t",strtotime($fechaInicio)))
                            ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                            ->where("gcc.fkGrupoConcepto", "=","46")
                            ->whereNotIn("ln.fkTipoLiquidacion",["7","10","11"]) //Puede tenerlas en el mismo periodo
                            ->get();
                            
                            foreach($itemsBoucherPeriodo16 as $itemBoucherMismoPeriodoNomin){                        
                                if(isset($arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto])){
                                    array_push($arrComoCalcula[$itemBoucherMismoPeriodoNomin->fkConcepto],
                                    "Se resta el valor de una liquidación del ".date("Y-m-16",strtotime($fechaInicio))." al ".date("Y-m-t",strtotime($fechaInicio)).": $".number_format($itemBoucherMismoPeriodoNomin->valor, 0, ",","."));

                                    array_push($arrComoCalcula[$itemBoucherMismoPeriodoNomin->fkConcepto],
                                    "Se resta la cantidad de una liquidación del ".date("Y-m-16",strtotime($fechaInicio))." al ".date("Y-m-t",strtotime($fechaInicio)).": ".$itemBoucherMismoPeriodoNomin->cantidad);

                                    $arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["valor"] = round($arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["valor"]) - $itemBoucherMismoPeriodoNomin->valor;
                                    $arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["cantidad"] = round($arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["cantidad"]) - $itemBoucherMismoPeriodoNomin->cantidad;
                                }
                            }
                        }
                    }
                    else{
                        //Verificar mes anterior
                        $itemsBoucherPeriodoMesAnt = DB::table("item_boucher_pago", "ibp")
                        ->selectRaw("ibp.*")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.fechaInicio","=",date("Y-m-01",strtotime($fechaInicio."- 1 month")))
                        ->where("ln.fechaFin","=",date("Y-m-t",strtotime($fechaInicio."- 1 month")))
                        ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                        ->where("gcc.fkGrupoConcepto", "=","46")
                        ->whereIn("ln.fkTipoLiquidacion",["3","7"])
                        ->get();

                        foreach($itemsBoucherPeriodoMesAnt as $itemBoucherMismoPeriodoNomin){                        
                            if(isset($arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto])){

                                array_push($arrComoCalcula[$itemBoucherMismoPeriodoNomin->fkConcepto],
                                "Se resta el valor de una liquidación del ".date("Y-m-01",strtotime($fechaInicio." - 1 month"))." al ".date("Y-m-t",strtotime($fechaInicio." - 1 month")).": $".number_format($itemBoucherMismoPeriodoNomin->valor, 0, ",","."));

                                array_push($arrComoCalcula[$itemBoucherMismoPeriodoNomin->fkConcepto],
                                "Se resta la cantidad de una liquidación del ".date("Y-m-01",strtotime($fechaInicio." - 1 month"))." al ".date("Y-m-t",strtotime($fechaInicio." - 1 month")).": ".$itemBoucherMismoPeriodoNomin->cantidad);

                                $arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["valor"] = round($arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["valor"]) - $itemBoucherMismoPeriodoNomin->valor;
                                $arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["cantidad"] = round($arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["cantidad"]) - $itemBoucherMismoPeriodoNomin->cantidad;
                            }
                        }
                    }                
                }

                if($tipoliquidacion == "6"){ //NORMAL + ANTICIPO DE PRIMA
                    if(isset($liquidacionNomina->fechaPrima) && $liquidacionNomina->tipoliquidacionPrima=="1"){

                        $arrResPrima = $this->calcularPrima($fechaInicio, $liquidacionNomina->fechaPrima, $empleado, $periodo, $periodoPagoSinVac, $arrValorxConcepto, $idLiquidacionNomina);
                        $liquidacionAnticipoPrima = $arrResPrima["liquidacionPrima"];
                        $fechaInicialPrima = $arrResPrima["fechaInicialPrima"];
                        $fechaFinalPrima = $arrResPrima["fechaFinalPrima"];
                        $totalPeriodoPago = $arrResPrima["totalPeriodoPago"];
                        $basePrima = $arrResPrima["basePrima"];
                        $salarioPrima = $arrResPrima["salarioPrima"];
                        $salarialPrima = $arrResPrima["salarialPrima"];
                        
                        $arrComoCalcula[78] = $arrResPrima["arrComoCalcula"];
                        array_push($arrComoCalcula[78], "Valor Salario: $".number_format($arrResPrima["salarioPrima"],0,",","."));
                        array_push($arrComoCalcula[78], "Valor promedio Salarial: $".number_format($arrResPrima["salarialPrima"],0,",","."));
                        array_push($arrComoCalcula[78], "Valor Base: $".number_format($arrResPrima["basePrima"],0,",","."));
                        array_push($arrComoCalcula[78], "Fecha inicial: ".$arrResPrima["fechaInicialPrima"]);
                        array_push($arrComoCalcula[78], "Fecha final: ".$arrResPrima["fechaFinalPrima"]);
                        array_push($arrComoCalcula[78], "Días: ".$arrResPrima["totalPeriodoPago"]);
                        array_push($arrComoCalcula[78], "Valor liquidación prima: $".number_format($arrResPrima["liquidacionPrima"],0,",","."));

                        $mesProyeccion = intval(date("m",strtotime($liquidacionNomina->fechaPrima)));
                    
                        if(isset($arrResPrima["saldoPrima"])){
                            $liquidacionAnticipoPrima = $liquidacionAnticipoPrima + $arrResPrima["saldoPrima"]->suma;
                            array_push($arrComoCalcula[78], "Se agrega saldo de prima por: $".number_format($arrResPrima["saldoPrima"]->suma,0,",","."));
                        }

                        if($mesProyeccion >= 1 && $mesProyeccion <= 6){                        
                        $itemsBoucherAnticipoNominaMesesAnteriores = DB::table("item_boucher_pago", "ibp")
                            ->selectRaw("Sum(ibp.valor) as suma")
                            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                            ->where("bp.fkEmpleado","=",$empleado->idempleado)
                            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                            ->where("ln.fechaInicio","<",$fechaInicioMes)
                            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")              
                            ->where("ibp.fkConcepto","=","78") //78 - Anticipo prima
                            ->first();

                            if(isset($itemsBoucherAnticipoNominaMesesAnteriores)){
                                $liquidacionAnticipoPrima = $liquidacionAnticipoPrima - $itemsBoucherAnticipoNominaMesesAnteriores->suma;
                                if($liquidacionAnticipoPrima < 0){
                                    $liquidacionAnticipoPrima = 0;
                                }
                            }

                        }
                        else if($mesProyeccion >= 7 && $mesProyeccion <= 12){
                            $itemsBoucherAnticipoNominaMesesAnteriores = DB::table("item_boucher_pago", "ibp")
                            ->selectRaw("Sum(ibp.valor) as suma")
                            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                            ->where("bp.fkEmpleado","=",$empleado->idempleado)
                            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                            ->where("ln.fechaInicio","<",$fechaInicioMes)
                            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."' and MONTH(ln.fechaInicio) > 6")                
                            ->where("ibp.fkConcepto","=","78") //78 - Anticipo prima
                            ->first();

                            if(isset($itemsBoucherAnticipoNominaMesesAnteriores)){
                                $liquidacionAnticipoPrima = $liquidacionAnticipoPrima - $itemsBoucherAnticipoNominaMesesAnteriores->suma;
                                if($liquidacionAnticipoPrima < 0){
                                    $liquidacionAnticipoPrima = 0;
                                }
                            }
                        }
                        
                        if( $liquidacionAnticipoPrima > 0){
                            if(!isset($arrValorxConcepto[78])){

                                //Obtener la primera liquidacion de nomina de la persona 
                                $primeraLiquidacion = DB::table("liquidacionnomina", "ln")
                                ->selectRaw("min(ln.fechaInicio) as primeraFecha")
                                ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")             
                                ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])   
                                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                ->first();
                                $minimaFecha = date("Y-m-d");                
                                if(isset($primeraLiquidacion)){
                                    $minimaFecha = $primeraLiquidacion->primeraFecha;
                                }
                                $diasAgregar = 0;
                                //Verificar si dicha nomina es menor a la fecha de ingreso
                                if(strtotime($empleado->fechaIngreso) < strtotime($minimaFecha)){
                                    $diasAgregar = $this->days_360($empleado->fechaIngreso, $minimaFecha);
                                }   


                                $arrSaldo = array();
                                if(isset($arrResPrima["saldoPrima"])){
                                    $arrSaldo = array([
                                        "idSaldo" => $arrResPrima["saldoPrima"]->idSaldo,
                                        "valor" => $arrResPrima["saldoPrima"]->valor
                                    ]);
                
                                }
                                
                                $arrValorxConcepto[78] = array(
                                    "naturaleza" => "1",
                                    "unidad" => "DÍA",
                                    "cantidad"=> $totalPeriodoPago,
                                    "arrNovedades"=> array(),
                                    "arrSaldo"=> $arrSaldo,
                                    "valor" => $liquidacionAnticipoPrima,
                                    "tipoGen" => "automaticos"
                                );
                            }                        
                        }
                        

                    }
                    else if(isset($liquidacionNomina->porcentajePrima) && $liquidacionNomina->tipoliquidacionPrima == "2"){ //Anticipo de prima tipo 2
                        
                        $mesActual = intval(date("m",strtotime($fechaInicio)));
                        if($mesActual >= 1 && $mesActual <= 6){
                            $mesProyeccion = 6;
                        }
                        else if($mesActual >= 7 && $mesActual <= 12){
                            $mesProyeccion = 12;

                        }
                        
                        $arrResPrima = $this->calcularPrima($fechaInicio, date("Y-".$mesProyeccion."-30", strtotime($fechaInicio)), $empleado, $periodo, $periodoPagoSinVac, $arrValorxConcepto, $idLiquidacionNomina);
                        $liquidacionAnticipoPrima = $arrResPrima["liquidacionPrima"];
                        $fechaInicialPrima = $arrResPrima["fechaInicialPrima"];
                        $fechaFinalPrima = $arrResPrima["fechaFinalPrima"];
                        $totalPeriodoPago = $arrResPrima["totalPeriodoPago"];
                        $basePrima = $arrResPrima["basePrima"];
                        $salarioPrima = $arrResPrima["salarioPrima"];
                        $salarialPrima = $arrResPrima["salarialPrima"];

                        $arrComoCalcula[78] = $arrResPrima["arrComoCalcula"];
                        array_push($arrComoCalcula[78], "Valor Salario: $".number_format($arrResPrima["salarioPrima"],0,",","."));
                        array_push($arrComoCalcula[78], "Valor promedio Salarial: $".number_format($arrResPrima["salarialPrima"],0,",","."));
                        array_push($arrComoCalcula[78], "Valor Base: $".number_format($arrResPrima["basePrima"],0,",","."));
                        array_push($arrComoCalcula[78], "Fecha inicial: ".$arrResPrima["fechaInicialPrima"]);
                        array_push($arrComoCalcula[78], "Fecha final: ".$arrResPrima["fechaFinalPrima"]);
                        array_push($arrComoCalcula[78], "Días: ".$arrResPrima["totalPeriodoPago"]);
                        array_push($arrComoCalcula[78], "Valor liquidación prima: $".number_format($arrResPrima["liquidacionPrima"],0,",","."));


                        if(isset($arrResPrima["saldoPrima"])){
                            $liquidacionAnticipoPrima = $liquidacionAnticipoPrima + $arrResPrima["saldoPrima"]->suma;
                            array_push($arrComoCalcula[78], "Se agrega saldo de prima por: $".number_format($arrResPrima["saldoPrima"]->suma,0,",","."));
                        }




                        if($mesActual >= 1 && $mesActual <= 6){
                            $mesProyeccion = 6;                        
                            $liquidacionAnticipoPrima = $liquidacionAnticipoPrima * ($liquidacionNomina->porcentajePrima / 100);

                            $itemsBoucherAnticipoNominaMesesAnteriores = DB::table("item_boucher_pago", "ibp")
                            ->selectRaw("Sum(ibp.valor) as suma")
                            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                            ->where("bp.fkEmpleado","=",$empleado->idempleado)
                            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                            ->where("ln.fechaInicio","<",$fechaInicioMes)
                            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")              
                            ->where("ibp.fkConcepto","=","78") //78 - Anticipo prima
                            ->first();

                            if(isset($itemsBoucherAnticipoNominaMesesAnteriores)){
                                $liquidacionAnticipoPrima = $liquidacionAnticipoPrima - $itemsBoucherAnticipoNominaMesesAnteriores->suma;
                                if($liquidacionAnticipoPrima < 0){
                                    $liquidacionAnticipoPrima = 0;
                                }
                            }

                        }
                        else if($mesActual >= 7 && $mesActual <= 12){
                            $mesProyeccion = 12;
                            $liquidacionAnticipoPrima = $liquidacionAnticipoPrima * ($liquidacionNomina->porcentajePrima / 100);
                            $itemsBoucherAnticipoNominaMesesAnteriores = DB::table("item_boucher_pago", "ibp")
                            ->selectRaw("Sum(ibp.valor) as suma")
                            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                            ->where("bp.fkEmpleado","=",$empleado->idempleado)
                            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                            ->where("ln.fechaInicio","<",$fechaInicioMes)
                            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."' and MONTH(ln.fechaInicio) > 6")                
                            ->where("ibp.fkConcepto","=","78") //78 - Anticipo prima
                            ->first();

                            if(isset($itemsBoucherAnticipoNominaMesesAnteriores)){
                                $liquidacionAnticipoPrima = $liquidacionAnticipoPrima - $itemsBoucherAnticipoNominaMesesAnteriores->suma;
                                if($liquidacionAnticipoPrima < 0){
                                    $liquidacionAnticipoPrima = 0;
                                }
                            }
                        }
                        if( $liquidacionAnticipoPrima > 0){
                            if(!isset($arrValorxConcepto[78])){                            
                                $arrSaldo = array();
                                if(isset($arrResPrima["saldoPrima"])){
                                    $arrSaldo = array([
                                        "idSaldo" => $arrResPrima["saldoPrima"]->idSaldo,
                                        "valor" => $arrResPrima["saldoPrima"]->valor
                                    ]);              
                                }
                                $arrValorxConcepto[78] = array(
                                    "naturaleza" => "1",
                                    "unidad" => "PORCENTAJE",
                                    "cantidad"=> $liquidacionNomina->porcentajePrima,
                                    "arrNovedades"=> array(),
                                    "arrSaldo"=> $arrSaldo,
                                    "valor" => $liquidacionAnticipoPrima,
                                    "tipoGen" => "automaticos"
                                );
                            }
                        }
                        


                        


                    }
                    else if(isset($liquidacionNomina->valorFijoPrima) && $liquidacionNomina->tipoliquidacionPrima=="3"){//Anticipo de prima tipo 3
                        $mesActual = intval(date("m",strtotime($fechaInicio)));
                        if($mesActual >= 1 && $mesActual <= 6){
                            $mesProyeccion = 6;
                        }
                        else if($mesActual >= 7 && $mesActual <= 12){
                            $mesProyeccion = 12;
                        }                    
                        $arrResPrima = $this->calcularPrima($fechaInicio, date("Y-".$mesProyeccion."-30", strtotime($fechaInicio)), $empleado, $periodo, $periodoPagoSinVac, $arrValorxConcepto, $idLiquidacionNomina);
                        $liquidacionAnticipoPrima = $arrResPrima["liquidacionPrima"];
                        $fechaInicialPrima = $arrResPrima["fechaInicialPrima"];
                        $fechaFinalPrima = $arrResPrima["fechaFinalPrima"];
                        $totalPeriodoPago = $arrResPrima["totalPeriodoPago"];
                        $basePrima = $arrResPrima["basePrima"];
                        $salarioPrima = $arrResPrima["salarioPrima"];
                        $salarialPrima = $arrResPrima["salarialPrima"];
                        if(isset($arrResPrima["saldoPrima"])){
                            $liquidacionAnticipoPrima = $liquidacionAnticipoPrima + $arrResPrima["saldoPrima"]->suma;
                            array_push($arrComoCalcula[78], "Se agrega saldo de prima por: $".number_format($arrResPrima["saldoPrima"]->suma,0,",","."));
                        }

                        $arrComoCalcula[78] = $arrResPrima["arrComoCalcula"];
                        array_push($arrComoCalcula[78], "Valor Salario: $".number_format($arrResPrima["salarioPrima"],0,",","."));
                        array_push($arrComoCalcula[78], "Valor promedio Salarial: $".number_format($arrResPrima["salarialPrima"],0,",","."));
                        array_push($arrComoCalcula[78], "Valor Base: $".number_format($arrResPrima["basePrima"],0,",","."));
                        array_push($arrComoCalcula[78], "Fecha inicial: ".$arrResPrima["fechaInicialPrima"]);
                        array_push($arrComoCalcula[78], "Fecha final: ".$arrResPrima["fechaFinalPrima"]);
                        array_push($arrComoCalcula[78], "Días: ".$arrResPrima["totalPeriodoPago"]);
                        array_push($arrComoCalcula[78], "Valor liquidación prima: $".number_format($arrResPrima["liquidacionPrima"],0,",","."));
                        
                        if($mesActual >= 1 && $mesActual <= 6){
                            $mesProyeccion = 6;
                            
                            $itemsBoucherAnticipoNominaMesesAnteriores = DB::table("item_boucher_pago", "ibp")
                            ->selectRaw("Sum(ibp.valor) as suma")
                            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                            ->where("bp.fkEmpleado","=",$empleado->idempleado)
                            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                            ->where("ln.fechaInicio","<",$fechaInicioMes)
                            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")              
                            ->where("ibp.fkConcepto","=","78") //78 - Anticipo prima
                            ->first();

                            if(isset($itemsBoucherAnticipoNominaMesesAnteriores)){
                                $liquidacionAnticipoPrima = $liquidacionAnticipoPrima - $itemsBoucherAnticipoNominaMesesAnteriores->suma;
                                if($liquidacionAnticipoPrima < 0){
                                    $liquidacionAnticipoPrima = 0;
                                }
                            }
                            if($liquidacionNomina->valorFijoPrima < $liquidacionAnticipoPrima){
                                $liquidacionAnticipoPrima = $liquidacionNomina->valorFijoPrima;
                            }


                        }
                        else if($mesActual >= 7 && $mesActual <= 12){
                            $mesProyeccion = 12;
                            $itemsBoucherAnticipoNominaMesesAnteriores = DB::table("item_boucher_pago", "ibp")
                            ->selectRaw("Sum(ibp.valor) as suma")
                            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                            ->where("bp.fkEmpleado","=",$empleado->idempleado)
                            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                            ->where("ln.fechaInicio","<",$fechaInicioMes)
                            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."' and MONTH(ln.fechaInicio) > 6")                
                            ->where("ibp.fkConcepto","=","78") //78 - Anticipo prima
                            ->first();

                            if(isset($itemsBoucherAnticipoNominaMesesAnteriores)){
                                $liquidacionAnticipoPrima = $liquidacionAnticipoPrima - $itemsBoucherAnticipoNominaMesesAnteriores->suma;
                                if($liquidacionAnticipoPrima < 0){
                                    $liquidacionAnticipoPrima = 0;
                                }
                            }
                            if($liquidacionNomina->valorFijoPrima < $liquidacionAnticipoPrima){
                                $liquidacionAnticipoPrima = $liquidacionNomina->valorFijoPrima;
                            }
                        }
                        if( $liquidacionAnticipoPrima > 0){
                            if(!isset($arrValorxConcepto[78])){
                                $arrSaldo = array();
                                if(isset($arrResPrima["saldoPrima"])){
                                    $arrSaldo = array([
                                        "idSaldo" => $arrResPrima["saldoPrima"]->idSaldo,
                                        "valor" => $arrResPrima["saldoPrima"]->valor
                                    ]);              
                                }

                                $arrValorxConcepto[78] = array(
                                    "naturaleza" => "1",
                                    "unidad" => "VALOR",
                                    "cantidad"=> "0",
                                    "arrNovedades"=> array(),
                                    "arrSaldo"=> $arrSaldo,
                                    "valor" => $liquidacionAnticipoPrima,
                                    "tipoGen" => "automaticos"
                                );
                            }
                        }
                        
                    }
                }
        
            }
            else if($empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "23"){ //CALCULO PARA SALARIO INTEGRAL DE PROVISION DE VACACIONES

                //Vacaciones
                $fechaInicioMes = date("Y-m-01", strtotime($fechaInicio));
                $anioActual = intval(date("Y",strtotime($fechaInicio)));
                $mesActual = intval(date("m",strtotime($fechaInicio)));
                
                
                $fechaFinPeriodoVac=$fechaFin;
                if(substr($fechaFinPeriodoVac, 8, 2) == "31" || (substr($fechaFinPeriodoVac, 8, 2) == "28" && substr($fechaFinPeriodoVac, 5, 2) == "02") || (substr($fechaFinPeriodoVac, 8, 2) == "29" && substr($fechaFinPeriodoVac, 5, 2) == "02")){
                    $fechaFinPeriodoVac = substr($fechaFinPeriodoVac, 0, 8)."30";
                }
                $periodoPagoVac = $this->days_360($empleado->fechaIngreso,$fechaFinPeriodoVac) + 1 ;
                if(isset($novedadesRetiro)){
                    if(strtotime($fechaFin) > strtotime($novedadesRetiro->fechaReal)){
                        $fechaFinPeriodoVac = $novedadesRetiro->fechaReal;
                        if(substr($novedadesRetiro->fechaReal, 8, 2) == "31" || (substr($novedadesRetiro->fechaReal, 8, 2) == "28" && substr($novedadesRetiro->fechaReal, 5, 2) == "02") || (substr($novedadesRetiro->fechaReal, 8, 2) == "29" && substr($novedadesRetiro->fechaReal, 5, 2) == "02")){
                            $fechaFinPeriodoVac = substr($novedadesRetiro->fechaReal, 0, 8)."30";
                        }
                    
                        $periodoPagoVac = $this->days_360($empleado->fechaIngreso,$fechaFinPeriodoVac) + 1 ;
                    }
                }

                $arrComoCalcula[30] = ($arrComoCalcula[30] ?? array());
                array_push($arrComoCalcula[30],"Calculo el total de diás con fecha inicio: ".$empleado->fechaIngreso." y fecha fin: ".$fechaFinPeriodoVac." para un total de ".$periodoPagoVac." días");
                //INICIO QUITAR LRN VAC
                $novedadesAus = DB::table("novedad","n")
                ->selectRaw("sum(a.cantidadDias) as suma")
                ->join("ausencia as a","a.idAusencia", "=", "n.fkAusencia")
                ->whereNotNull("n.fkAusencia")            
                ->where("n.fkEmpleado","=",$empleado->idempleado)
                ->where("n.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->whereIn("n.fkEstado",["7", "8"]) // Pagada o sin pagar-> no que este eliminada
                ->first();
                $periodoPagoVac = $periodoPagoVac - $novedadesAus->suma;
                if($novedadesAus->suma != 0){
                    array_push($arrComoCalcula[30],"Al periodo anterior se le restan ".$novedadesAus->suma." días de ausencia");
                }
                //FIN QUITAR LRN PARA VAC
                


                
                $salarialVac = 0;
                $grupoConceptoCalculoVac = DB::table("grupoconcepto_concepto","gcc")
                    ->where("gcc.fkGrupoConcepto", "=", "13")//Salarial para provisiones
                    ->get();
                foreach($grupoConceptoCalculoVac as $grupoConcepto){
                    if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto != 36){
                        $salarialVac = $salarialVac + floatval($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor']);
                    }
                }
                
                $itemsBoucherSalarialVac = DB::table("item_boucher_pago", "ibp")
                ->selectRaw("Sum(ibp.valor) as suma")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("ln.fechaInicio","=",$fechaInicioMes)
                ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                ->where("gcc.fkGrupoConcepto","=","13") //13 - Salarial vacaciones
                ->first();
                if(isset($itemsBoucherSalarialVac)){
                    $salarialVac = $salarialVac + $itemsBoucherSalarialVac->suma;
                }
                
                

                $diasVac = $periodoPagoVac * 15 / 360;
                array_push($arrComoCalcula[30],"Se calcula la cantidad de dias disponibles de vacaciones = ".$diasVac." días disponibles");
                //INICIO QUITAR DIAS VAC
                $novedadesVacacion = DB::table("novedad","n")
                ->join("vacaciones as v","v.idVacaciones","=","n.fkVacaciones")
                ->where("n.fkEmpleado","=",$empleado->idempleado)
                ->where("n.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->whereIn("n.fkEstado",["7", "8","16"]) // Pagada o sin pagar-> no que este eliminada
                ->where("n.fechaRegistro",">=",$empleado->fechaIngreso)
                ->whereNotNull("n.fkVacaciones")
                ->get();
                    
                
                foreach($novedadesVacacion as $novedadVacacion){
                    if($novedadVacacion->fkConcepto == 29){
                        array_push($arrComoCalcula[30],"Se restan = ". $novedadVacacion->diasCompletos." días de los disponibles");
                        $diasVac = $diasVac - $novedadVacacion->diasCompletos;
                    }
                    else if($novedadVacacion->fkConcepto == 28){
                        array_push($arrComoCalcula[30],"Se restan = ". $novedadVacacion->diasCompensar." días de los disponibles");
                        $diasVac = $diasVac - $novedadVacacion->diasCompensar;
                    }
                    
                }
                
                //NO APLICAN PARA RET - (Creo)
                if(isset($diasVac) && $diasVac < 0 && $empresa->vacacionesNegativas == 0){
                    $diasVac = 0;
                }
                $diasVac = round($diasVac, 2);
                array_push($arrComoCalcula[30],"Finalmente se tienen dias disponibles de vacaciones = ".$diasVac." días disponibles");
                //FIN QUITAR DIAS VAC
                


            



                // $diasVac = $totalPeriodoPagoAnioActual * 15 / 360;

                $liquidacionesMesesAnterioresCompleta = DB::table("liquidacionnomina", "ln")
                ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.salarioPeriodoPago) as salarioPago")
                ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("ln.fechaInicio","<=",$fechaInicioMes)
                ->where("ln.fechaInicio",">=",date("Y-m-d",strtotime($fechaInicioMes." -1 year")))
                ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])         
                ->first();



                $primeraLiquidacion = DB::table("liquidacionnomina", "ln")
                ->selectRaw("min(ln.fechaInicio) as primeraFecha")
                ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])   
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->first();

                $minimaFecha = date("Y-m-d");
                
                if(isset($primeraLiquidacion)){
                    $minimaFecha = $primeraLiquidacion->primeraFecha;
                }
                $diasAgregar = 0;
                //Verificar si dicha nomina es menor a la fecha de ingreso
                if(strtotime($empleado->fechaIngreso) < strtotime($minimaFecha)){
                    $diasAgregar = $this->days_360($empleado->fechaIngreso, $minimaFecha);
                }
                if(isset($vacacionesPTotal->fechaInicio)){
                    $periodoNuevo = $this->days_360($fechaInicio,$vacacionesPTotal->fechaInicio);
                }
                else{
                    $periodoNuevo = $this->days_360($fechaInicio,$fechaFinPeriodoVac);
                }
                    

                $periodoPagoMesActual = $periodoNuevo + $diasAgregar;
                //dd($periodoNuevo, $liquidacionesMesesAnterioresCompleta->periodPago);
                $totalPeriodoPagoAnioActual = $periodoPagoMesActual + $liquidacionesMesesAnterioresCompleta->periodPago;
                if($totalPeriodoPagoAnioActual>360){
                    $totalPeriodoPagoAnioActual = 360;
                }
                if(isset($novedadesRetiro)){
                    $fechaFinVac = $novedadesRetiro->fecha;
                }    
                else{
                    $fechaFinVac = $fechaFin;
                }

                $fechaInicioParaVacaciones = date("Y-m-d", strtotime($fechaFinVac." - 1 YEAR"));
                //$fechaInicioAnio= date("Y-01-01", strtotime($fechaFin));

                if(strtotime($fechaInicioParaVacaciones) < strtotime($empleado->fechaIngreso) ){
                    $fechaInicioParaVacaciones = $empleado->fechaIngreso;
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
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("ln.fechaInicio","<",$fechaInicioMes)
                ->where("ln.fechaLiquida",">=",$fechaInicioParaVacaciones)      
                ->where("gcc.fkGrupoConcepto","=","13") //13 - Salarial vacaciones
                ->first();  



                $itemsBoucherSalarialMesesAnterioresVac = DB::table("item_boucher_pago", "ibp")
                ->selectRaw("Sum(ibp.valor) as suma")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("ln.fechaInicio","<",$fechaInicioMes)
                ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")                
                ->where("gcc.fkGrupoConcepto","=","13") //13 - Salarial vacaciones
                ->first();
                //dd($totalPeriodoPagoAnioActual, $itemsBoucherSalarialMesesAnterioresVac->suma);
                $salarialVac = $salarialVac + $itemsBoucherSalarialMesesAnterioresVac->suma;
                //$salarialVac = ($salarialVac / $totalPeriodoPagoAnioActual)*30;



                
                $diff = $this->days_360($empleado->fechaIngreso, $fechaFinPeriodoVac) + 1;
                $diasTomar = $diff;
                if($diff> 360){
                    $diasTomar = 360;
                }
                
                $salarialVac = ($salarialVac / $diasTomar)*30;
                
                
                $salarioVac = 0;

                foreach($conceptosFijosEmpl as $conceptoFijoEmpl){
                    if($conceptoFijoEmpl->fkConcepto=="1" || $conceptoFijoEmpl->fkConcepto=="2"){
                        $salarioVac = $conceptoFijoEmpl->valor; 
                    }
                }
                


                
                $baseVac = $salarioVac + $salarialVac;
                
                $liquidacionVac = ($baseVac/30)*$diasVac;                
                array_push($arrComoCalcula[30],"Valor salario $".number_format($salarioVac,0,",","."));
                array_push($arrComoCalcula[30],"Valor salarial $".number_format($salarialVac,0,",","."));
                array_push($arrComoCalcula[30],"Valor base $".number_format($baseVac,0,",","."));
                array_push($arrComoCalcula[30],"Dias: ".$diasVac." días");
                array_push($arrComoCalcula[30],"Valor liquidación $".number_format($liquidacionVac,0,",","."));
                
                $historicoProvisionVac = DB::table("provision","p")
                ->selectRaw("sum(p.valor) as sumaValor")
                ->where("p.fkEmpleado","=",$empleado->idempleado)
                ->where("p.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("p.mes","<",date("m",strtotime($fechaInicio)))
                ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                ->where("p.fkConcepto","=","74")
                ->first();


                $pagoVacacionesItems = DB::table("item_boucher_pago", "ibp")
                ->selectRaw("Sum(ibp.valor) as suma")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")
                ->whereRaw("ln.fechaInicio < '".$fechaInicio."'")
                ->whereIn("ibp.fkConcepto",["28","29","30"]) //VACACIONES
                ->first();

                
                $saldoVacaciones = DB::table("saldo")
                ->where("fkEmpleado","=",$empleado->idempleado)
                ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("anioAnterior","=",date("Y",strtotime($fechaInicio)))
                ->where("fkConcepto","=", "74")
                ->where("fkEstado","=","7")
                ->first();
                $arrComoCalculaProv[30] = ($arrComoCalcula[30] ?? array());
                array_push($arrComoCalculaProv[30], "Se suma a la liquidación anterior junto con el pago de vacaciones anteriores : $".number_format(( $pagoVacacionesItems->suma ?? 0),0,",","."));
                array_push($arrComoCalculaProv[30], "Se resta el historico de provisiones por un valor de : $".number_format(( $historicoProvisionVac->sumaValor ?? 0),0,",","."));
                array_push($arrComoCalculaProv[30], "Se resta el saldo por un valor de : $".number_format((isset($saldoVacaciones) ? $saldoVacaciones->valor : 0),0,",","."));

                $provAnteriores = $historicoProvisionVac->sumaValor + (isset($saldoVacaciones) ? $saldoVacaciones->valor : 0) - ($pagoVacacionesItems->suma ?? 0);
                
                /*if(isset($arrValorxConcepto[28])){
                    $provAnteriores = $provAnteriores - $arrValorxConcepto[28]["valor"];
                    array_push($arrComoCalculaProv[30], "Se suma pago de vacaciones en el mes actual por un valor de : $".number_format($arrValorxConcepto[28]["valor"],0,",","."));
                }

                if(isset($arrValorxConcepto[29])){
                    $provAnteriores = $provAnteriores - $arrValorxConcepto[29]["valor"];
                    array_push($arrComoCalculaProv[30], "Se suma pago de vacaciones en el mes actual por un valor de : $".number_format($arrValorxConcepto[29]["valor"],0,",","."));
                }*/

                
                
                $provisionVacValor = $liquidacionVac - $provAnteriores;
                array_push($arrComoCalculaProv[30], "Finalmente se tiene una provision de : $".number_format(( $provisionVacValor ?? 0),0,",","."));
            
                //Anterior
                //$provisionVacValor = $liquidacionVac + ($pagoVacacionesItems->suma ?? 0) - $historicoProvisionVac->sumaValor;

            
                $provisionVacaciones = DB::table("provision","p")
                    ->where("p.fkEmpleado","=",$empleado->idempleado)
                    ->where("p.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("p.mes","=",date("m",strtotime($fechaInicio)))
                    ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                    ->where("p.fkConcepto","=","74")
                    ->get();
        
                $arrProvisionVacaciones = array(
                    "fkPeriodoActivo" => $periodoActivoReintegro->idPeriodo,
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


                //Provisiones en 0
                $provisionPrima = DB::table("provision","p")
                    ->where("p.fkEmpleado","=",$empleado->idempleado)
                    ->where("p.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("p.mes","=",date("m",strtotime($fechaInicio)))
                    ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                    ->where("p.fkConcepto","=","73")
                    ->get();

                $arrProvisionPrima = array(
                    "fkPeriodoActivo" => $periodoActivoReintegro->idPeriodo,
                    "fkConcepto" => "73",
                    "fkEmpleado"=> $empleado->idempleado,
                    "mes" => date("m",strtotime($fechaInicio)),
                    "anio"  => date("Y",strtotime($fechaInicio)),
                    "valor" => intval(0),
                    "comoCalculo" => "0 para salario integral"
                );
                if(sizeof($provisionPrima)>0){
                    

                    DB::table("provision")
                    ->where("idProvision","=", $provisionPrima[0]->idProvision)
                    ->update($arrProvisionPrima);

                }
                else{
                    DB::table("provision")
                    ->insert($arrProvisionPrima);
                }



                
                $provisionCesantias = DB::table("provision","p")
                    ->where("p.fkEmpleado","=",$empleado->idempleado)
                    ->where("p.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("p.mes","=",date("m",strtotime($fechaInicio)))
                    ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                    ->where("p.fkConcepto","=","71")
                    ->get();
            
                $arrProvisionCesantias = array(
                    "fkPeriodoActivo" => $periodoActivoReintegro->idPeriodo,
                    "fkConcepto" => "71",
                    "fkEmpleado"=> $empleado->idempleado,
                    "mes" => date("m",strtotime($fechaInicio)),
                    "anio"  => date("Y",strtotime($fechaInicio)),
                    "valor" => intval(0),
                    "comoCalculo" => "0 para salario integral"
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



                $provisionIntCesantias = DB::table("provision","p")
                    ->where("p.fkEmpleado","=",$empleado->idempleado)
                    ->where("p.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("p.mes","=",date("m",strtotime($fechaInicio)))
                    ->where("p.anio","=",date("Y",strtotime($fechaInicio)))
                    ->where("p.fkConcepto","=","72")
                    ->get();
            
                $arrProvisionIntCesantias = array(
                    "fkPeriodoActivo" => $periodoActivoReintegro->idPeriodo,
                    "fkConcepto" => "72",
                    "fkEmpleado"=> $empleado->idempleado,
                    "mes" => date("m",strtotime($fechaInicio)),
                    "anio"  => date("Y",strtotime($fechaInicio)),
                    "valor" => intval(0),
                    "comoCalculo" => "0 para salario integral"
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


            }
            
            //INICIO PRESTAMOS Y EMBARGOS
            //SOLO SI ES NORMAL 
            if($tipoliquidacion == "1" || $tipoliquidacion == "2" || $tipoliquidacion == "4" || $tipoliquidacion == "5" || $tipoliquidacion == "6" || $tipoliquidacion == "9" || $tipoliquidacion == "12" ){
                
                $prestamos = DB::table("prestamo","p")
                ->join("periocidad","periocidad.per_id","=","p.fkPeriocidad")
                ->where("p.fkEstado","=","1")
                ->where("p.fechaInicio","<=",$liquidacionNomina->fechaLiquida)
                ->where("p.fkEmpleado","=", $empleado->idempleado)
                ->where("p.fkPeriodoActivo","=", $periodoActivoReintegro->idPeriodo)
                ->get();    
                
                foreach($prestamos as $prestamo){
                    $prestamoMismoPeriodoNomin = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("ibp.*")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fechaInicio","=",$fechaInicio)
                    ->where("ln.fechaFin","=",$fechaFin)
                    ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                    ->where("ln.fkEstado","=","5")
                    ->whereNotIn("ln.fkTipoLiquidacion",["7","10","11"]) //Puede tenerlas en el mismo periodo
                    ->where("ibp.fkConcepto", "=",$prestamo->fkConcepto)
                    ->first();
                    if(!isset($prestamoMismoPeriodoNomin)){
                        $arrComoCalcula[$prestamo->fkConcepto] = ($arrComoCalcula[$prestamo->fkConcepto] ?? array());
                        $valorPrestamo = 0;
                        if($prestamo->tipoDescuento == "1"){
                            $valorPrestamo = $prestamo->montoInicial / $prestamo->numCuotas;
                            array_push($arrComoCalcula[$prestamo->fkConcepto], 
                            "Se calcula el valor de la cuota con: monto inicial $".number_format($prestamo->montoInicial, 0,",",".")." 
                            dividido en el número de cuotas: ".$prestamo->numCuotas.", igual a: ".number_format($valorPrestamo, 0,",",".")."
                            ");
    
                        }
                        else if($prestamo->tipoDescuento == "2"){
                            $valorPrestamo = $prestamo->valorCuota;
                            array_push($arrComoCalcula[$prestamo->fkConcepto], 
                            "Valor de la cuota fija: ".number_format($valorPrestamo, 0,",",".")."
                            ");
                        }
                        else if($prestamo->tipoDescuento == "3"){
                        
                            $grupoConceptoCalculoPrestamo = DB::table("grupoconcepto_concepto","gcc")
                                ->where("gcc.fkGrupoConcepto", "=", "5")                       
                                ->get();
                            $totalBasePrestamo = 0;
                            foreach($grupoConceptoCalculoPrestamo as $grupoConcepto){
                                if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto])){
                                    $totalBasePrestamo = $totalBasePrestamo + floatval($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor']);
                                }
                            }
                            
    
                            $valorPrestamo = ($totalBasePrestamo * $prestamo->porcentajeCuota)/100;
                            array_push($arrComoCalcula[$prestamo->fkConcepto], 
                            "Se calcula el valor de la cuota: base =".number_format($totalBasePrestamo, 0,",",".")." x ".$prestamo->porcentajeCuota."%
                            ");
    
                            if($prestamo->hastaSalarioMinimo == "1"){
                                $valorQuincena = 0;
                                foreach($arrValorxConcepto as $idConcepto => $arrConcepto){
                                    $valorQuincena = $valorQuincena + $arrConcepto['valor'];
                                }
    
                                $salarioMinimoPeriodo = ($salarioMinimoDia * $periodo);
                                $placeResta = $valorQuincena - $valorPrestamo;
    
                                if($placeResta < $salarioMinimoPeriodo){
                                    
    
                                    $valorPrestamo = $valorQuincena - $salarioMinimoPeriodo;
                                    array_push($arrComoCalcula[$prestamo->fkConcepto], 
                                    "El concepto esta configurado hasta un maximo del salario minimo: 
                                    Valor del prestamo = ".number_format($valorPrestamo, 0,",",".")."
                                    ");
                                }
                            }
                        }
                        if($valorPrestamo > $prestamo->saldoActual){
                            $valorPrestamo = $prestamo->saldoActual;
                            array_push($arrComoCalcula[$prestamo->fkConcepto], 
                            "El valor del prestamo supera el saldo actual, se toma el saldo actual: 
                            Valor del prestamo = ".number_format($valorPrestamo, 0,",",".")."
                            ");
                        }
                        $arrPrestamo = array("idPrestamo" => $prestamo->idPrestamo, "valor" => $valorPrestamo);
    
    
                        if($periodo == 15){
                            
                            if(substr($liquidacionNomina->fechaInicio,8,2) == "01" && $prestamo->per_id == "2"){
    
                                if(isset($arrValorxConcepto[$prestamo->fkConcepto])){
    
                                    $arrPrestamoNuevo = $arrValorxConcepto[$prestamo->fkConcepto]["arrPrestamo"];   
                                    array_push($arrPrestamoNuevo, $arrPrestamo);
                                    array_push($arrComoCalcula[$prestamo->fkConcepto], 
                                    "Se suma con anteriores valores: ".number_format($valorPrestamo, 0,",",".")." + ".number_format($arrValorxConcepto[$prestamo->fkConcepto]["valor"], 0,",",".")."                            
                                    ");
                                    
                                    $arrValorxConcepto[$prestamo->fkConcepto] = array(
                                        "naturaleza" => "3",
                                        "unidad"=>"VALOR",
                                        "cantidad"=> "0",
                                        "arrNovedades"=> array(),
                                        "arrPrestamo"=> $arrPrestamoNuevo,
                                        "valor" => $arrValorxConcepto[$prestamo->fkConcepto]["valor"] + ($valorPrestamo*-1),
                                        "tipoGen" => "prestamo"
                                    );
                                }
                                else{
                                    $arrValorxConcepto[$prestamo->fkConcepto] = array(
                                        "naturaleza" => "3",
                                        "unidad"=>"VALOR",
                                        "cantidad"=> "0",
                                        "arrNovedades"=> array(),
                                        "arrPrestamo"=> array($arrPrestamo),
                                        "valor" => $valorPrestamo*-1,
                                        "tipoGen" => "prestamo"
                                    );
                                }
    
                                
                            }
                            else if(substr($liquidacionNomina->fechaInicio,8,2) == "16" && $prestamo->per_id == "3"){
                                if(isset($arrValorxConcepto[$prestamo->fkConcepto])){
    
                                    $arrPrestamoNuevo = $arrValorxConcepto[$prestamo->fkConcepto]["arrPrestamo"];   
                                    array_push($arrPrestamoNuevo, $arrPrestamo);
                                    array_push($arrComoCalcula[$prestamo->fkConcepto], 
                                    "Se suma con anteriores valores: ".number_format($valorPrestamo, 0,",",".")." + ".number_format($arrValorxConcepto[$prestamo->fkConcepto]["valor"], 0,",",".")."                            
                                    ");
                                    $arrValorxConcepto[$prestamo->fkConcepto] = array(
                                        "naturaleza" => "3",
                                        "unidad"=>"VALOR",
                                        "cantidad"=> "0",
                                        "arrNovedades"=> array(),
                                        "arrPrestamo"=> $arrPrestamoNuevo,
                                        "valor" => $arrValorxConcepto[$prestamo->fkConcepto]["valor"] + ($valorPrestamo*-1),
                                        "tipoGen" => "prestamo"
                                    );
                                }
                                else{
                                    $arrValorxConcepto[$prestamo->fkConcepto] = array(
                                        "naturaleza" => "3",
                                        "unidad"=>"VALOR",
                                        "cantidad"=> "0",
                                        "arrNovedades"=> array(),
                                        "arrPrestamo"=> array($arrPrestamo),
                                        "valor" => $valorPrestamo*-1,
                                        "tipoGen" => "prestamo"
                                    );
                                }
                            }
                            else if($prestamo->per_id == "4"){
                                if(isset($arrValorxConcepto[$prestamo->fkConcepto])){
    
                                    $arrPrestamoNuevo = $arrValorxConcepto[$prestamo->fkConcepto]["arrPrestamo"] ?? array();   
                                    array_push($arrPrestamoNuevo, $arrPrestamo);
                                    array_push($arrComoCalcula[$prestamo->fkConcepto], 
                                    "Se suma con anteriores valores: ".number_format($valorPrestamo, 0,",",".")." + ".number_format($arrValorxConcepto[$prestamo->fkConcepto]["valor"], 0,",",".")."                            
                                    ");
                                    $arrValorxConcepto[$prestamo->fkConcepto] = array(
                                        "naturaleza" => "3",
                                        "unidad"=>"VALOR",
                                        "cantidad"=> "0",
                                        "arrNovedades"=> array(),
                                        "arrPrestamo"=> $arrPrestamoNuevo,
                                        "valor" => $arrValorxConcepto[$prestamo->fkConcepto]["valor"] + ($valorPrestamo*-1),
                                        "tipoGen" => "prestamo"
                                    );
                                }
                                else{
                                    $arrValorxConcepto[$prestamo->fkConcepto] = array(
                                        "naturaleza" => "3",
                                        "unidad"=>"VALOR",
                                        "cantidad"=> "0",
                                        "arrNovedades"=> array(),
                                        "arrPrestamo"=> array($arrPrestamo),
                                        "valor" => $valorPrestamo*-1,
                                        "tipoGen" => "prestamo"
                                    );
                                }
                            }
                        }
                        else{
                            if($prestamo->per_id == "1"){
                                //Verificar que no se le haya pagado en 
    
                                
                                
    
                                if(isset($arrValorxConcepto[$prestamo->fkConcepto])){
                                    
                                    $arrPrestamoNuevo = $arrValorxConcepto[$prestamo->fkConcepto]["arrPrestamo"] ?? array();   
                                    array_push($arrPrestamoNuevo, $arrPrestamo);
                                    array_push($arrComoCalcula[$prestamo->fkConcepto], 
                                    "Se suma con anteriores valores: ".number_format($valorPrestamo, 0,",",".")." + ".number_format($arrValorxConcepto[$prestamo->fkConcepto]["valor"], 0,",",".")."                            
                                    ");
                                    $arrValorxConcepto[$prestamo->fkConcepto] = array(
                                        "naturaleza" => "3",
                                        "unidad"=>"VALOR",
                                        "cantidad"=> "0",
                                        "arrNovedades"=> array(),
                                        "arrPrestamo"=> $arrPrestamoNuevo,
                                        "valor" => $arrValorxConcepto[$prestamo->fkConcepto]["valor"] + ($valorPrestamo*-1),
                                        "tipoGen" => "prestamo"
                                    );
                                }
                                else{
                                    $arrValorxConcepto[$prestamo->fkConcepto] = array(
                                        "naturaleza" => "3",
                                        "unidad"=>"VALOR",
                                        "cantidad"=> "0",
                                        "arrNovedades"=> array(),
                                        "arrPrestamo"=> array($arrPrestamo),
                                        "valor" => $valorPrestamo*-1,
                                        "tipoGen" => "prestamo"
                                    );
                                }
                            }
                        }
                    }
                    
                }
            }
            //FIN PRESTAMOS Y EMBARGOS

            //RETIROS
            if($tipoliquidacion == "2" || $tipoliquidacion == "3"){
                
                //Calcular retiro dias
                $novedadesRetiro = DB::table("novedad","n")
                ->join("retiro AS r", "r.idRetiro","=","n.fkRetiro")
                ->where("n.fkEmpleado", "=", $empleado->idempleado)
                ->where("n.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("n.fkEstado","=","7")
                ->whereNotNull("n.fkRetiro")
                ->whereBetween("n.fechaRegistro",[$fechaInicio, $fechaFin])->first();
                
                if(isset($novedadesRetiro)){
                    
                    
                    foreach($arrValorxConcepto as $idConcepto => $arrConcepto){
                        array_push($arrValorxConcepto[$idConcepto]["arrNovedades"], [
                            "idNovedad" => $novedadesRetiro->idNovedad,
                            "valor" => "0"
                        ]);
                        break;
                    }
                    



                    //Verificar si tien un pago para el mismo periodoPago
                    if($liquidacionPrima!=0){
                        $arrSaldo = array();
                        $saldoPrima = 0;
                        if(isset($arrResPrima["saldoPrima"])){
                            $arrSaldo = array([
                                "idSaldo" => $arrResPrima["saldoPrima"]->idSaldo,
                                "valor" => $arrResPrima["saldoPrima"]->valor
                            ]);         
                            //$saldoPrima = $arrResPrima["saldoPrima"]->valor;     
                        }


                        //Obtener la primera liquidacion de nomina de la persona 
                        $primeraLiquidacion = DB::table("liquidacionnomina", "ln")
                        ->selectRaw("min(ln.fechaInicio) as primeraFecha")
                        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")             
                        ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])   
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->first();
                        $minimaFecha = date("Y-m-d");                
                        if(isset($primeraLiquidacion)){
                            $minimaFecha = $primeraLiquidacion->primeraFecha;
                        }
                        $diasAgregar = 0;
                        //Verificar si dicha nomina es menor a la fecha de ingreso
                        if(date("m",strtotime($fechaInicio)) > 6){
                            $fechaMitadAnio = date("Y-07-01",strtotime($fechaInicio));

                            if(strtotime($empleado->fechaIngreso) < strtotime($minimaFecha) && strtotime($empleado->fechaIngreso) > strtotime($fechaMitadAnio) && date("Y",strtotime($minimaFecha))==date("Y",strtotime($fechaInicio))){                        
                                $diasAgregar = $this->days_360($empleado->fechaIngreso, $minimaFecha);
                            }   
                            else if(strtotime($empleado->fechaIngreso) < strtotime($fechaMitadAnio)){
                                //$diasAgregar = $this->days_360($fechaMitadAnio, $minimaFecha);                                
                            }
                            
                        }
                        else{
                            if(strtotime($empleado->fechaIngreso) < strtotime($minimaFecha) && date("Y",strtotime($minimaFecha))==date("Y",strtotime($fechaInicio)) && date("Y",strtotime($minimaFecha))==date("Y",strtotime($empleado->fechaIngreso)) ){                        
                                $diasAgregar = $this->days_360($empleado->fechaIngreso, $minimaFecha);
                            }   
                        }
                        
                        $fechaFinalPrima = $novedadesRetiro->fecha;
                        if(isset($arrValorxConcepto[58])){

                            $arrValorxConcepto[58]["valor"] = $arrValorxConcepto[58]["valor"] + ($liquidacionPrima  + $saldoPrima);
                            array_push($arrValorxConcepto[58]["arrNovedades"], [
                                "idNovedad" => $novedadesRetiro->idNovedad,
                                "valor" => $liquidacionPrima + $saldoPrima
                            ]);                       

                            $arrValorxConcepto[58]["naturaleza"] = "1";
                            $arrValorxConcepto[58]["unidad"] = "DÍA";
                            $arrValorxConcepto[58]["cantidad"] = $totalPeriodoPago + $diasAgregar;                        
                            $arrValorxConcepto[58]["arrSaldo"]= $arrSaldo;
                            $arrValorxConcepto[58]["tipoGen"] = "automaticos";
                            $arrValorxConcepto[58]["base"] = $basePrima;
                            $arrValorxConcepto[58]["fechaInicio"] = $fechaInicialPrima;
                            $arrValorxConcepto[58]["fechaFin"] = $fechaFinalPrima;
                            
                        }
                        else{
                            $arrValorxConcepto[58] = array(
                                "naturaleza" => "1",
                                "unidad" => "DÍA",
                                "cantidad"=> $totalPeriodoPago + $diasAgregar,
                                "arrNovedades"=> array([
                                    "idNovedad" => $novedadesRetiro->idNovedad,
                                    "valor" => $liquidacionPrima + $saldoPrima
                                ]),
                                "arrSaldo"=> $arrSaldo,
                                "valor" => $liquidacionPrima  + $saldoPrima,
                                "tipoGen" => "automaticos",
                                "base" => $basePrima,
                                "fechaInicio" => $fechaInicialPrima,
                                "fechaFin" => $fechaFinalPrima
                            );
                        }
                        
                        $mesActual = intval(date("m",strtotime($novedadesRetiro->fechaReal)));
                        //dd($mesActual, $fechaFinalPrima, $fechaInicio, $novedadesRetiro);
                        if($mesActual >= 1 && $mesActual <= 6 && $empleado->tipoRegimen!="Salario Integral"){


                            $itemsBoucherAnticipoNominaMesesAnteriores = DB::table("item_boucher_pago", "ibp")
                            ->selectRaw("Sum(ibp.valor) as suma")
                            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                            ->where("bp.fkEmpleado","=",$empleado->idempleado)
                            ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo);
                            if($periodo == 15){
                                $itemsBoucherAnticipoNominaMesesAnteriores = $itemsBoucherAnticipoNominaMesesAnteriores->where("ln.fechaInicio","<",date("Y-m-16", strtotime($fechaInicioMes)));
                            }
                            else{
                                $itemsBoucherAnticipoNominaMesesAnteriores = $itemsBoucherAnticipoNominaMesesAnteriores->where("ln.fechaInicio","<",$fechaInicioMes);    
                            }
                            $itemsBoucherAnticipoNominaMesesAnteriores = $itemsBoucherAnticipoNominaMesesAnteriores->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")              
                            ->where("ibp.fkConcepto","=","78") //78 - Anticipo prima
                            ->first();

                            if(isset($itemsBoucherAnticipoNominaMesesAnteriores) && $itemsBoucherAnticipoNominaMesesAnteriores->suma > 0){
                                $arrComoCalcula[129] = ($arrComoCalcula[129] ?? array());
                                array_push($arrComoCalcula[129], 
                                "Suma anticipos de prima en liquidaciones anteriores: ".number_format($itemsBoucherAnticipoNominaMesesAnteriores->sum, 0,",",".")."                     
                                ");
                                $arrValorxConcepto[129] = array(
                                    "naturaleza" => "3",
                                    "unidad" => "UNIDAD",
                                    "cantidad"=> "0",
                                    "arrNovedades"=> array(),
                                    "valor" => ($itemsBoucherAnticipoNominaMesesAnteriores->suma * -1),
                                    "tipoGen" => "automaticos"
                                );
                            }
                            

                            $itemsBoucherPrimaMesesAnteriores = DB::table("item_boucher_pago", "ibp")
                            ->selectRaw("Sum(ibp.valor) as suma")
                            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                            ->where("bp.fkEmpleado","=",$empleado->idempleado)
                            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo);
                            if($periodo == 15){
                                $itemsBoucherPrimaMesesAnteriores = $itemsBoucherPrimaMesesAnteriores->where("ln.fechaInicio","<=",date("Y-m-16", strtotime($fechaInicioMes)));
                            }
                            else{
                                $itemsBoucherPrimaMesesAnteriores = $itemsBoucherPrimaMesesAnteriores->where("ln.fechaInicio","<=",$fechaInicioMes);    
                            }
                            $itemsBoucherPrimaMesesAnteriores = $itemsBoucherPrimaMesesAnteriores->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")              
                            ->where("ibp.fkConcepto","=","58") //58 - Prima de servicios
                            ->first();
                            if($itemsBoucherPrimaMesesAnteriores->suma > 0){
                                array_push($arrComoCalcula[58],"Se resta el valor de primas anteriores: $".number_format($itemsBoucherPrimaMesesAnteriores->suma, 0, ",","."));
                                $arrValorxConcepto[58]['valor'] = $arrValorxConcepto[58]['valor']  - $itemsBoucherPrimaMesesAnteriores->suma;
                            }

                        }
                        else if($mesActual >= 7 && $mesActual <= 12 && $empleado->tipoRegimen!="Salario Integral"){

                            $itemsBoucherAnticipoNominaMesesAnteriores = DB::table("item_boucher_pago", "ibp")
                            ->selectRaw("Sum(ibp.valor) as suma")
                            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                            ->where("bp.fkEmpleado","=",$empleado->idempleado)
                            ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo);                            
                            if($periodo == 15){
                                $itemsBoucherAnticipoNominaMesesAnteriores = $itemsBoucherAnticipoNominaMesesAnteriores->where("ln.fechaInicio","<",date("Y-m-16", strtotime($fechaInicioMes)));
                            }
                            else{
                                $itemsBoucherAnticipoNominaMesesAnteriores = $itemsBoucherAnticipoNominaMesesAnteriores->where("ln.fechaInicio","<",$fechaInicioMes);    
                            }
                            $itemsBoucherAnticipoNominaMesesAnteriores = $itemsBoucherAnticipoNominaMesesAnteriores->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."' and MONTH(ln.fechaInicio) > 6")                
                            ->where("ibp.fkConcepto","=","78") //78 - Anticipo prima
                            ->first();

                            if(isset($itemsBoucherAnticipoNominaMesesAnteriores) && $itemsBoucherAnticipoNominaMesesAnteriores->suma > 0){
                                $arrComoCalcula[129] = ($arrComoCalcula[129] ?? array());
                                array_push($arrComoCalcula[129], 
                                "Suma anticipos de prima en liquidaciones anteriores: ".number_format($itemsBoucherAnticipoNominaMesesAnteriores->suma, 0,",",".")."                     
                                ");
                                $arrValorxConcepto[129] = array(
                                    "naturaleza" => "3",
                                    "unidad" => "UNIDAD",
                                    "cantidad"=> "0",
                                    "arrNovedades"=> array(),
                                    "valor" => ($itemsBoucherAnticipoNominaMesesAnteriores->suma * -1),
                                    "tipoGen" => "automaticos"
                                );
                                
                            }


                            
                            $itemsBoucherPrimaMesesAnteriores = DB::table("item_boucher_pago", "ibp")
                            ->selectRaw("Sum(ibp.valor) as suma")
                            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                            ->where("bp.fkEmpleado","=",$empleado->idempleado)
                            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo);
                            if($periodo == 15){
                                $itemsBoucherPrimaMesesAnteriores = $itemsBoucherPrimaMesesAnteriores->where("ln.fechaInicio","<=",date("Y-m-16", strtotime($fechaInicioMes)));
                            }
                            else{
                                $itemsBoucherPrimaMesesAnteriores = $itemsBoucherPrimaMesesAnteriores->where("ln.fechaInicio","<=",$fechaInicioMes);    
                            }
                            $itemsBoucherPrimaMesesAnteriores = $itemsBoucherPrimaMesesAnteriores->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."' and MONTH(ln.fechaInicio) > 6")
                            ->where("ibp.fkConcepto","=","58") //58 - Prima de servicios
                            ->first();



                            
                            if($itemsBoucherPrimaMesesAnteriores->suma > 0){
                                array_push($arrComoCalcula[58],"Se resta el valor de primas anteriores: $".number_format($itemsBoucherPrimaMesesAnteriores->suma, 0, ",","."));
                                $arrValorxConcepto[58]['valor'] = $arrValorxConcepto[58]['valor']  - $itemsBoucherPrimaMesesAnteriores->suma;
                            }
                        }
                    }
                    if($liquidacionCesantias>0){

                        $saldoCesantias = DB::table("saldo")
                        ->where("fkEmpleado","=",$empleado->idempleado)
                        ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("anioAnterior","=",date("Y",strtotime($fechaInicio)))
                        ->where("fkConcepto","=", "71")
                        ->where("fkEstado","=","7")
                        ->first();

                        $arrSaldo = array();
                        $saldoValor = 0;
                        if(isset($saldoCesantias)){
                            $arrSaldo = array([
                                "idSaldo" => $saldoCesantias->idSaldo,
                                "valor" => $saldoCesantias->valor
                            ]);
                            //$saldoValor = $saldoCesantias->valor;
                        }

                        $fechaFinalCes = $novedadesRetiro->fecha;
                        
                        if(isset($arrValorxConcepto[66])){
                            $arrValorxConcepto[66]["valor"] = $arrValorxConcepto[66]["valor"] + ($liquidacionCesantias + $saldoValor);
                            array_push($arrValorxConcepto[66]["arrNovedades"],[
                                "idNovedad" => $novedadesRetiro->idNovedad,
                                "valor" => $liquidacionCesantias
                                ]);
                            $arrValorxConcepto[66]["arrSaldo"] = $arrSaldo;
                            $arrValorxConcepto[66]["cantidad"] = $totalPeriodoPagoAnioActualReal;
                            $arrValorxConcepto[66]["unidad"] = "DÍA";
                            $arrValorxConcepto[66]["naturaleza"] = "1";
                            $arrValorxConcepto[66]["tipoGen"] = "automaticos";
                            $arrValorxConcepto[66]["base"] = $baseCes;
                            $arrValorxConcepto[66]["fechaInicio"] = $fechaInicialCes;
                            $arrValorxConcepto[66]["fechaFin"] = $fechaFinalCes;
                            
                        }
                        else{
                            $arrValorxConcepto[66] = array( 
                                "naturaleza" => "1",
                                "unidad" => "DÍA",
                                "cantidad"=> $totalPeriodoPagoAnioActualReal,
                                "arrNovedades"=> array([
                                    "idNovedad" => $novedadesRetiro->idNovedad,
                                    "valor" => $liquidacionCesantias
                                    ]),
                                "arrSaldo"=> $arrSaldo,
                                "valor" => ($liquidacionCesantias + $saldoValor),
                                "tipoGen" => "automaticos",
                                "base" => $baseCes,
                                "fechaInicio" => $fechaInicialCes,
                                "fechaFin" => $fechaFinalCes
                            );
                        }
                    
                    }
                    $saldoCesantiasAnioAnterior = DB::table("saldo")
                    ->where("fkEmpleado","=",$empleado->idempleado)
                    ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("anioAnterior","=",date("Y",strtotime($fechaInicio)))
                    ->where("fkConcepto","=", "67")//CESANTIAS AÑO ANTERIOR
                    ->where("fkEstado","=","7")
                    ->first();

                    if(isset($saldoCesantiasAnioAnterior)){
                        $arrComoCalcula[67] = ($arrComoCalcula[67] ?? array());
                        array_push($arrComoCalcula[67], 
                        "Existe un saldo anterior: ".number_format($saldoCesantiasAnioAnterior->valor, 0,",",".")."                     
                        ");

                        $arrValorxConcepto[67] = array(
                            "naturaleza" => "1",
                            "unidad" => "VALOR",
                            "cantidad"=> "0",
                            "arrNovedades"=> array([
                                "idNovedad" => $novedadesRetiro->idNovedad,
                                "valor" => $saldoCesantiasAnioAnterior->valor
                            ]),
                            "arrSaldo"=> array([
                                "idSaldo" => $saldoCesantiasAnioAnterior->idSaldo,
                                "valor" => $saldoCesantiasAnioAnterior->valor
                            ]),
                            "valor" => $saldoCesantiasAnioAnterior->valor,
                            "tipoGen" => "automaticos"
                        );
                    }
                    if($liquidacionIntCesantias>0){


                        $saldoIntCesantias = DB::table("saldo")
                        ->where("fkEmpleado","=",$empleado->idempleado)
                        ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("anioAnterior","=",date("Y",strtotime($fechaInicio)))
                        ->where("fkConcepto","=", "72")
                        ->where("fkEstado","=","7")
                        ->first();

                        $arrSaldo = array();
                        if(isset($saldoIntCesantias)){
                            $arrSaldo = array([
                                "idSaldo" => $saldoIntCesantias->idSaldo,
                                "valor" => $saldoIntCesantias->valor
                            ]);
                        
                        }
                        
                        $fechaFinalCes = $novedadesRetiro->fecha;
                        if(isset($arrValorxConcepto[69])){

                            $arrValorxConcepto[69]["valor"] = $arrValorxConcepto[69]["valor"] + $liquidacionIntCesantias;
                            array_push($arrValorxConcepto[69]["arrNovedades"],[
                                "idNovedad" => $novedadesRetiro->idNovedad,
                                "valor" => $liquidacionIntCesantias
                            ]);
                            $arrValorxConcepto[69]["naturaleza"] = "1";
                            $arrValorxConcepto[69]["unidad"] = "DÍA";
                            $arrValorxConcepto[69]["cantidad"] = $totalPeriodoPagoAnioActualReal;
                            $arrValorxConcepto[69]["arrSaldo"] = $arrSaldo;
                            $arrValorxConcepto[69]["tipoGen"] = "automaticos";
                            $arrValorxConcepto[69]["base"] = $baseCes;
                            $arrValorxConcepto[69]["fechaInicio"] = $fechaInicialCes;
                            $arrValorxConcepto[69]["fechaFin"] = $fechaFinalCes;
                        }
                        else{
                            $arrValorxConcepto[69] = array(
                                "naturaleza" => "1",
                                "unidad" => "DÍA",
                                "cantidad"=> $totalPeriodoPagoAnioActualReal,
                                "arrNovedades"=> array([
                                    "idNovedad" => $novedadesRetiro->idNovedad,
                                    "valor" => $liquidacionIntCesantias
                                ]),
                                "arrSaldo"=> $arrSaldo,
                                "valor" => ($liquidacionIntCesantias),
                                "tipoGen" => "automaticos",
                                "base" => $baseCes,
                                "fechaInicio" => $fechaInicialCes,
                                "fechaFin" => $fechaFinalCes
                            );
                        }

                        
                    }
                    $saldoIntCesantiasAnioAnterior = DB::table("saldo")
                    ->where("fkEmpleado","=",$empleado->idempleado)
                    ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("anioAnterior","=",date("Y",strtotime($fechaInicio)))
                    ->where("fkConcepto","=", "68")//INTERESES CESANTIAS AÑO ANTERIOR
                    ->where("fkEstado","=","7")
                    ->where("valor", ">", "0")
                    ->first();
                    if(isset($saldoIntCesantiasAnioAnterior)){
                        $arrValorxConcepto[68] = array(
                            "naturaleza" => "1",
                            "unidad" => "VALOR",
                            "cantidad"=> "0",
                            "arrNovedades"=> array([
                                "idNovedad" => $novedadesRetiro->idNovedad,
                                "valor" => $saldoIntCesantiasAnioAnterior->valor
                            ]),
                            "arrSaldo"=> array([
                                "idSaldo" => $saldoIntCesantiasAnioAnterior->idSaldo,
                                "valor" => $saldoIntCesantiasAnioAnterior->valor
                            ]),
                            "valor" => $saldoIntCesantiasAnioAnterior->valor,
                            "tipoGen" => "automaticos"
                        );
                    }
                    if($empleado->tipoRegimen!="Salario Integral"){
                        $salarialVac = 0;
                        $grupoConceptoCalculoVac = DB::table("grupoconcepto_concepto","gcc")
                            ->where("gcc.fkGrupoConcepto", "=", "13")//Salarial para provisiones
                            ->get();
                        foreach($grupoConceptoCalculoVac as $grupoConcepto){
                            if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto != 36){
                                $salarialVac = $salarialVac + floatval($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor']);
                            }
                        }
                        if(!isset($fechaInicioMes)){
                            $fechaInicioMes = date("Y-m-01", strtotime($fechaInicio));
                        }

                        $itemsBoucherSalarialMesAnteriorVac = DB::table("item_boucher_pago", "ibp")
                        ->selectRaw("Sum(ibp.valor) as suma")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.fechaInicio","=",$fechaInicioMes)
                        ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                        ->where("gcc.fkGrupoConcepto","=","13") //13 - Salarial vacaciones
                        ->first();

               
                       
                        if(isset($itemsBoucherSalarialMesAnteriorVac)){
                            $salarialVac = $salarialVac + $itemsBoucherSalarialMesAnteriorVac->suma;
                        }

            
                        if(!isset($anioActual)){
                            $anioActual = intval(date("Y",strtotime($fechaInicio)));
                        }

                        $anioLiquida = $anioActual;
                        $fechaInicioLiquida = $fechaInicioMes;
                        if($novedadesRetiro->fecha != $novedadesRetiro->fechaReal){
                            if(substr($novedadesRetiro->fechaReal, 5, 2) <= substr($novedadesRetiro->fecha, 5, 2) ){
                                
                            }
                            else if(substr($novedadesRetiro->fechaReal, 5, 2) == 12 && substr($novedadesRetiro->fecha, 5, 2) == 1){
                                $anioLiquida = $anioActual - 1;
                                $fechaInicioLiquida = $fechaInicioMes;
                            }
                        }
                        if(isset($novedadesRetiro)){
                            $fechaFinVac = $novedadesRetiro->fecha;
                        }    
                        else{
                            $fechaFinVac = $fechaFin;
                        }
    
                        $fechaInicioParaVacaciones = date("Y-m-d", strtotime($fechaFinVac." - 1 YEAR"));
                        //$fechaInicioAnio= date("Y-01-01", strtotime($fechaFin));
    
                        if(strtotime($fechaInicioParaVacaciones) < strtotime($empleado->fechaIngreso) ){
                            $fechaInicioParaVacaciones = $empleado->fechaIngreso;
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
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("ln.fechaInicio","<",$fechaInicioLiquida)
                        ->where("ln.fechaLiquida",">=",$fechaInicioParaVacaciones)
                        ->where("gcc.fkGrupoConcepto","=","13") //13 - Salarial vacaciones
                        ->first();  
        
                        
                        $salarialVac = $salarialVac + $itemsBoucherSalarialMesesAnterioresVac->suma;
                        
                      
                        $diff = $this->days_360($empleado->fechaIngreso, $novedadesRetiro->fechaReal) + 1;
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
    
                        
                        $salarioVac = 0;

                        foreach($conceptosFijosEmpl as $conceptoFijoEmpl){
                            if($conceptoFijoEmpl->fkConcepto=="1" || $conceptoFijoEmpl->fkConcepto=="2"){
                                $salarioVac = $conceptoFijoEmpl->valor; 
                            }
                        }                        
                        
                        
                        if(($liquidacionVac > 0 || $empresa->vacacionesNegativas == 1) && $liquidacionVac != 0){                           
                            
                            $saldoVacGen = DB::table("saldo")
                            ->where("fkEmpleado","=",$empleado->idempleado)
                            ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                            ->where("anioAnterior","=",date("Y",strtotime($fechaInicio)))
                            ->where("fkConcepto","=", "74")//INTERESES CESANTIAS AÑO ANTERIOR
                            ->where("fkEstado","=","7") 
                            ->where("valor", ">", "0")
                            ->first();
                            $arrSaldoVac = array();
                            if(isset($saldoVacGen)){
                                $arrSaldoVac = array([
                                    "idSaldo" => $saldoVacGen->idSaldo,
                                    "valor" => $saldoVacGen->valor
                                ]);
                            }

                            if(isset($arrValorxConcepto[30])){
                                
                                $arrNovedades = $arrValorxConcepto[30]["arrNovedades"];
                                array_push($arrNovedades, [
                                    "idNovedad" => $novedadesRetiro->idNovedad,
                                    "valor" => $liquidacionVac
                                ]);
                                
                                array_push($arrComoCalcula[30], "Se suma el acumulado anterior $".number_format( $arrValorxConcepto[30]['valor'], 0,",","."));
                                $arrValorxConcepto[30] = array(
                                    "naturaleza" => "1",
                                    "unidad" => "DÍA",
                                    "cantidad"=> $diasVac,
                                    "arrNovedades"=> $arrNovedades,
                                    "valor" => $liquidacionVac + $arrValorxConcepto[30]['valor'],
                                    "tipoGen" => "automaticos",
                                    "base" => $baseVac,
                                    "arrSaldo"=> $arrSaldoVac,
                                );     
                            }
                            else{
                                
                                    $arrValorxConcepto[30] = array(
                                        "naturaleza" => "1",
                                        "unidad" => "DÍA",
                                        "cantidad"=> $diasVac,
                                        "arrNovedades"=> array([
                                            "idNovedad" => $novedadesRetiro->idNovedad,
                                            "valor" => $liquidacionVac
                                        ]),
                                        "valor" => $liquidacionVac,
                                        "tipoGen" => "automaticos",
                                        "base" => $baseVac,
                                        "arrSaldo"=> $arrSaldoVac,
                                    );     
                                
                                
                            }
                            //dd($arrValorxConcepto);
                        }

                    }
                    else{
                        if($liquidacionVac > 0 || $empresa->vacacionesNegativas == 1){
                           

                            $saldoVacGen = DB::table("saldo")
                            ->where("fkEmpleado","=",$empleado->idempleado)
                            ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                            ->where("anioAnterior","=",date("Y",strtotime($fechaInicio)))
                            ->where("fkConcepto","=", "74")//INTERESES CESANTIAS AÑO ANTERIOR
                            ->where("fkEstado","=","7") 
                            ->where("valor", ">", "0")
                            ->first();
                            $arrSaldoVac = array();
                            if(isset($saldoVacGen)){
                                $arrSaldoVac = array([
                                    "idSaldo" => $saldoVacGen->idSaldo,
                                    "valor" => $saldoVacGen->valor
                                ]);
                            }

                            if(isset($arrValorxConcepto[30])){
                                
                                $arrNovedades = $arrValorxConcepto[30]["arrNovedades"];
                                array_push($arrNovedades, [
                                    "idNovedad" => $novedadesRetiro->idNovedad,
                                    "valor" => $liquidacionVac
                                ]);
                                
                                array_push($arrComoCalcula[30], "Se suma el acumulado anterior $".number_format( $arrValorxConcepto[30]['valor'], 0,",","."));
                                $arrValorxConcepto[30] = array(
                                    "naturaleza" => "1",
                                    "unidad" => "DÍA",
                                    "cantidad"=> $diasVac,
                                    "arrNovedades"=> $arrNovedades,
                                    "valor" => $liquidacionVac + $arrValorxConcepto[30]['valor'],
                                    "tipoGen" => "automaticos",
                                    "base" => $baseVac,
                                    "arrSaldo"=> $arrSaldoVac,
                                );     
                            }
                            else{
                                $arrValorxConcepto[30] = array(
                                    "naturaleza" => "1",
                                    "unidad" => "DÍA",
                                    "cantidad"=> $diasVac,
                                    "arrNovedades"=> array([
                                        "idNovedad" => $novedadesRetiro->idNovedad,
                                        "valor" => $liquidacionVac
                                    ]),
                                    "valor" => $liquidacionVac,
                                    "tipoGen" => "automaticos",
                                    "base" => $baseVac,
                                    "arrSaldo"=> $arrSaldoVac,
                                );     
                            }
                        }
                    }
                    
                    if($novedadesRetiro->indemnizacion == 1){
                        $indemnizacion = 0;

                        $contrato = DB::table("contrato","con")->where("con.fkPeriodoActivo","=",$novedadesRetiro->fkPeriodoActivo)
                        ->where("con.fkEstado","=","1")
                        ->orderBy("con.idcontrato","desc")
                        ->first();
                        if(!isset($contrato)){
                            $contrato = DB::table("contrato","con")->where("con.fkPeriodoActivo","=",$novedadesRetiro->fkPeriodoActivo)
                            ->where("con.fkEstado","=","4")
                            ->orderBy("con.idcontrato","desc")
                            ->first();
                        }

                        if(!isset($contrato)){
                            $contrato = DB::table("contrato","con")->where("con.fkPeriodoActivo","=",$novedadesRetiro->fkPeriodoActivo)
                            ->where("con.fkEstado","=","2")
                            ->where("con.fechaInicio","<=",$novedadesRetiro->fechaReal)
                            ->where("con.fechaFin",">=",$novedadesRetiro->fechaReal)
                            ->orderBy("con.idcontrato","desc")
                            ->first();
                        }
                        if($contrato->fkTipoContrato==1){//Contrato termino fijo
                            $arrComoCalcula[27] = ($arrComoCalcula[27] ?? array());
                            $arrComoCalcula[26] = ($arrComoCalcula[26] ?? array());
                            $salarioMes = 0;
                            foreach($conceptosFijosEmpl as $conceptoFijoEmpl){
                                if($conceptoFijoEmpl->fkConcepto=="1" || $conceptoFijoEmpl->fkConcepto=="2"){
                                    $salarioMes = $conceptoFijoEmpl->valor; 
                                }
                            }
                            $variables = DB::table("variable")->where("idVariable","=","1")->first();
                            $valorSalarioMinimo = $variables->valor;
                            $diasIndemnizacion = 0;
                            $valorDia = $salarioMes / 30;
                            $fechaRetiroIndem = $novedadesRetiro->fechaReal;
                            $fechaFinContrato = $contrato->fechaFin;
                            if(substr($fechaRetiroIndem, 8, 2) == "31" || (substr($fechaRetiroIndem, 8, 2) == "28" && substr($fechaRetiroIndem, 5, 2) == "02") || (substr($fechaRetiroIndem, 8, 2) == "29" && substr($fechaRetiroIndem, 5, 2) == "02") ){
                                $fechaRetiroIndem = substr($fechaRetiroIndem,0,8)."30";
                            }
        
                            if(substr($fechaFinContrato, 8, 2) == "31" || (substr($fechaFinContrato, 8, 2) == "28" && substr($fechaFinContrato, 5, 2) == "02") || (substr($fechaFinContrato, 8, 2) == "29" && substr($fechaFinContrato, 5, 2) == "02")){
                                $fechaFinContrato = substr($fechaFinContrato,0,8)."30";
                            }

                            if($salarioMes < (10 * $valorSalarioMinimo)){
                                array_push($arrComoCalcula[27], "Se calcula el salario de un dia ".number_format($salarioMes,0,",",".")." / 30 = ".number_format($valorDia,0,",","."));
                                array_push($arrComoCalcula[27], "Salario menor a 10 SLMV");
                                $diasIndemnizacion = $this->days_360($fechaRetiroIndem,$fechaFinContrato);
                                array_push($arrComoCalcula[27], "Se toman ".$diasIndemnizacion." dias de indemnizacion, fecha retiro: ".$novedadesRetiro->fechaReal." y fecha fin: ".$contrato->fechaFin);
                                $codigoIndem = 27;
                                array_push($arrComoCalcula[27], "Se multiplica el Valor día (".number_format($valorDia,0,",",".").") por ".round($diasIndemnizacion,2)." dias");

                            }
                            else{
                                array_push($arrComoCalcula[26], "Se calcula el salario de un dia ".number_format($salarioMes,0,",",".")." / 30 = ".number_format($valorDia,0,",","."));
                                array_push($arrComoCalcula[26], "Salario mayor a 10 SLMV");
                                $diasIndemnizacion = $this->days_360($fechaRetiroIndem,$fechaFinContrato);
                                array_push($arrComoCalcula[26], "Se toman ".$diasIndemnizacion." dias de indemnizacion, fecha retiro: ".$novedadesRetiro->fechaReal." y fecha fin: ".$contrato->fechaFin);
                                $codigoIndem = 26;
                                array_push($arrComoCalcula[26], "Se multiplica el Valor día (".number_format($valorDia,0,",",".").") por ".round($diasIndemnizacion,2)." dias");
                            }
                            
                            $indemnizacion = $valorDia * $diasIndemnizacion;
                            array_push($arrComoCalcula[$codigoIndem], "Finalmente se tiene que la indemnizacion es = $".number_format($indemnizacion,0,",",".")."");
                        }
                        else{
                            $periodoTrab = $this->days_360($empleado->fechaIngreso,$novedadesRetiro->fechaReal) + 1;
                            $periodoAnios = ($periodoTrab / 360);
                            $salarioMes = 0;
                            foreach($conceptosFijosEmpl as $conceptoFijoEmpl){
                                if($conceptoFijoEmpl->fkConcepto=="1" || $conceptoFijoEmpl->fkConcepto=="2"){
                                    $salarioMes = $conceptoFijoEmpl->valor; 
                                }
                            }
                            $arrComoCalcula[27] = ($arrComoCalcula[27] ?? array());
                            $arrComoCalcula[26] = ($arrComoCalcula[26] ?? array());
                            
                            $variables = DB::table("variable")->where("idVariable","=","1")->first();
                            $valorSalarioMinimo = $variables->valor;
                            $diasIndemnizacion = 0;
    
                            $codigoIndem = 0;
                            $valorDia = $salarioMes / 30;
                            if($salarioMes < (10 * $valorSalarioMinimo)){
                                $diasIndemnizacion = 30;                            
                                array_push($arrComoCalcula[27], "Se calcula el salario de un dia ".number_format($salarioMes,0,",",".")." / 30 = ".number_format($valorDia,0,",","."));
                                array_push($arrComoCalcula[27], "Salario menor a 10 SLMV");
                                array_push($arrComoCalcula[27], "Se toman ".$diasIndemnizacion." dias de indemnizacion");
                                $periodoAnios = $periodoAnios - 1;
                                if($periodoAnios > 0){
                                    array_push($arrComoCalcula[27], "Se suman a los dias de indemnizacion (20 * ".round($periodoAnios,2).") dias");
                                    $diasIndemnizacion = $diasIndemnizacion + (20 * $periodoAnios);
                                    array_push($arrComoCalcula[27], "Un total de ".round($diasIndemnizacion,2)." dias de indemnizacion");
                                }                        
                                $codigoIndem = 27;
                                array_push($arrComoCalcula[27], "Se multiplica el Valor día (".number_format($valorDia,0,",",".").") por ".round($diasIndemnizacion,2)." dias");
                            }
                            else{
                                $diasIndemnizacion = 20;
                                $periodoAnios = $periodoAnios - 1;
    
                                array_push($arrComoCalcula[26], "Se calcula el salario de un dia ".number_format($salarioMes,0,",",".")." / 30 = ".number_format($valorDia,0,",","."));
                                array_push($arrComoCalcula[26], "Salario mayor a 10 SLMV");
                                array_push($arrComoCalcula[26], "Se toman ".$diasIndemnizacion." dias de indemnizacion");
    
                                if($periodoAnios > 0){
                                    array_push($arrComoCalcula[26], "Se suman a los dias de indemnizacion (15 * ".$periodoAnios.") dias");
                                    $diasIndemnizacion = $diasIndemnizacion + (15 * $periodoAnios);
                                    array_push($arrComoCalcula[26], "Un total de ".$diasIndemnizacion." dias de indemnizacion");
                                }
                                $codigoIndem = 26;
                                array_push($arrComoCalcula[26], "Se multiplica el Valor día (".number_format($valorDia,0,",",".").") por ".$diasIndemnizacion." dias");
                            }
    
    
                        
                            $indemnizacion = $valorDia * $diasIndemnizacion;
                            array_push($arrComoCalcula[$codigoIndem], "Finalmente se tiene que la indemnizacion es = $".number_format($indemnizacion,0,",",".")."");
                            
    
                            
                        }
                        if($indemnizacion > 0){
                                
                            if(isset($arrValorxConcepto[$codigoIndem] )){

                                $arrNovedades = $arrValorxConcepto[$codigoIndem]['arrNovedades'];
                                array_push($arrNovedades, [
                                    "idNovedad" => $novedadesRetiro->idNovedad,
                                    "valor" => $indemnizacion
                                ]);
                                array_push($arrComoCalcula[$codigoIndem], "Se suma con valores anteriores resultando en $".number_format(($arrValorxConcepto[$codigoIndem]['valor'] + $indemnizacion),0,",",".")."");
                                $indemnizacion = ($arrValorxConcepto[$codigoIndem]['valor'] + $indemnizacion);
                                $arrValorxConcepto[$codigoIndem] = array(
                                    "naturaleza" => "1",
                                    "unidad" => "DÍA",
                                    "cantidad"=> $diasIndemnizacion,
                                    "arrNovedades"=> $arrNovedades,
                                    "valor" => $indemnizacion,
                                    "tipoGen" => "automaticos"
                                );     
                            }
                            else{
                                $arrValorxConcepto[$codigoIndem] = array(
                                    "naturaleza" => "1",
                                    "unidad" => "DÍA",
                                    "cantidad"=> $diasIndemnizacion,
                                    "arrNovedades"=> array([
                                        "idNovedad" => $novedadesRetiro->idNovedad,
                                        "valor" => $indemnizacion
                                    ]),
                                    "valor" => $indemnizacion,
                                    "tipoGen" => "automaticos"
                                );     
                            }
                            


                
                            $variablesRetInd = DB::table("variable")->whereIn("idVariable",["10","62","63","64","65"])->get();
                            $variablesRetInde = array();
                            foreach($variablesRetInd as $variableRetInd){
                                $variablesRetInde[$variableRetInd->idVariable] = $variableRetInd->valor;
                            }
                            
                            $uvtActual = $variablesRetInde[10];
                            $salarioUvts = $salarioMes/ $uvtActual;
                            if($salarioUvts > $variablesRetInde[62]){
                                $arrComoCalcula[76] = ($arrComoCalcula[76] ?? array());
                                array_push($arrComoCalcula[76], "Salario en uvts ".$salarioUvts." es mayor al configurado (".$variablesRetInde[62].") por lo tanto aplica retención");

                                $variablesRetencion = DB::table("variable")
                                    ->where("idVariable",">=","16")
                                    ->where("idVariable","<=","48")
                                    ->get();
                                $varRetencion = array();
                                foreach($variablesRetencion as $variablesRetencio){
                                    $varRetencion[$variablesRetencio->idVariable] = $variablesRetencio->valor;
                                }
                                $ingreso = $indemnizacion; 
                                array_push($arrComoCalcula[76], "Se toma como ingreso $".number_format(($indemnizacion),0,",",".")."");
                                $FPS = 0;
                                $EPS = 0;
                                $AFP = 0;
                                $SS = 0;
                                $rentaLiquida = $ingreso - $SS;
                        
                                $interesesVivienda = 0;
                                $medicinaPrepagada = 0;
                                $dependiente = 0;
                                $aporteVoluntario = 0;
                                $AFC = 0;
                                                    
                    
                    
                                $deducciones = $interesesVivienda + $medicinaPrepagada + $dependiente + $aporteVoluntario + $AFC;
                                $deduccionesSinAportes = $interesesVivienda + $medicinaPrepagada + $dependiente;
                    
                                $baseNeta = $rentaLiquida - $deducciones;
                                $baseNetaSinAportes = $rentaLiquida - $deduccionesSinAportes;
                    
                                $exenta = $baseNeta * $variablesRetInde[63];
                                array_push($arrComoCalcula[76], "Se toma como base $".number_format(($baseNeta),0,",",".")."");
                                array_push($arrComoCalcula[76], "Se calcula la parte exenta como base * ".$variablesRetInde[63]." (variable)");

                                $exentaSinAportes = $baseNetaSinAportes * $variablesRetInde[63];
                    
                        
                                $totalBeneficiosTributarios = $exenta + $deducciones;
                                $totalBeneficiosTributariosSinAportes = $exentaSinAportes + $deduccionesSinAportes;
                                
                                
                    
                                $topeBeneficios= $rentaLiquida*$varRetencion[24];
                                
                                if($totalBeneficiosTributarios > ($rentaLiquida*$varRetencion[24])){
                                    $totalBeneficiosTributarios = $rentaLiquida*$varRetencion[24];
                                    
                                }
                    
                    
                                if($totalBeneficiosTributarios > round($uvtActual*$variablesRetInde[64],-3)){

                                    $totalBeneficiosTributarios = round($uvtActual*$variablesRetInde[64], -3);
                                    $topeBeneficios= $rentaLiquida*round($uvtActual*$variablesRetInde[64], -3);
                                }
                    
                    
                                if($totalBeneficiosTributariosSinAportes > ($rentaLiquida*$varRetencion[24])){
                                    $totalBeneficiosTributariosSinAportes = $rentaLiquida*$varRetencion[24];
                                }
                                if($totalBeneficiosTributariosSinAportes > round($uvtActual*$variablesRetInde[64],-3)){
                                    $totalBeneficiosTributariosSinAportes = round($uvtActual*$variablesRetInde[64], -3);
                                }
                    
                    
                                $baseGravable  = $rentaLiquida - $totalBeneficiosTributarios;
                                $baseGravableSinAportes  = $rentaLiquida - $totalBeneficiosTributariosSinAportes;
                                
                                $baseGravableUVTS = round($baseGravable / $uvtActual, 2);
                                $baseGravableSinAportesUVTS = round($baseGravableSinAportes / $uvtActual, 2);
                                
                                $impuestoValor = round($baseGravable * $variablesRetInde[65], -3);
                                array_push($arrComoCalcula[76], "Se calcula el valor del impuesto como Base(".$baseGravable.") * ".$variablesRetInde[65]." (variable - 65)");
                                array_push($arrComoCalcula[76], "Se obtiene $ ".number_format($impuestoValor, 0,",","."));
                                $impuestoValorSinAportes = round($baseGravableSinAportes * $variablesRetInde[65], -3);
                    
                    
                                $impuestoUVT = round($impuestoValor / $uvtActual,2);
                                $impuestoSinAportesUVT = round($impuestoValorSinAportes / $uvtActual, 2);

                                $valorInt = $impuestoValor;
                                if($impuestoValor>0){
                                    $arrValorxConcepto[76] = array(
                                        "naturaleza" => "3",
                                        "unidad" => "UNIDAD",
                                        "cantidad"=> "0",
                                        "arrNovedades"=> array(),
                                        "valor" => $valorInt*-1 ,
                                        "tipoGen" => "automaticos"
                                    );
                                    $arrayRetencionInd["tipoRetencion"] = "INDEMNIZACION";
                                    $arrayRetencionInd["salario"] = $salarioMes;
                                    $arrayRetencionInd["ingreso"] = $ingreso;
                                    $arrayRetencionInd["EPS"] = $EPS*-1;
                                    $arrayRetencionInd["AFP"] = $AFP*-1;
                                    $arrayRetencionInd["FPS"] = $FPS*-1;
                                    $arrayRetencionInd["seguridadSocial"] = $SS;
                                    $arrayRetencionInd["interesesVivienda"] = $interesesVivienda;
                                    $arrayRetencionInd["medicinaPrepagada"] = $medicinaPrepagada;
                                    $arrayRetencionInd["dependiente"] = $dependiente;
                                    $arrayRetencionInd["aporteVoluntario"] = $aporteVoluntario;
                                    $arrayRetencionInd["AFC"] = $AFC;
                                    $arrayRetencionInd["exenta"] = $exenta;
                                    $arrayRetencionInd["exentaSinAportes"] = $exentaSinAportes;
                                    $arrayRetencionInd["totalBeneficiosTributarios"] = $totalBeneficiosTributarios;
                                    $arrayRetencionInd["totalBeneficiosTributariosSinAportes"] = $totalBeneficiosTributariosSinAportes;
                                    $arrayRetencionInd["topeBeneficios"] = $topeBeneficios;
                                    $arrayRetencionInd["baseGravableUVTS"] = $baseGravableUVTS;
                                    $arrayRetencionInd["impuestoUVT"] = $impuestoUVT;
                                    $arrayRetencionInd["impuestoSinAportesUVT"] = $impuestoSinAportesUVT;
                                    $arrayRetencionInd["impuestoValor"] = $impuestoValor;
                                    $arrayRetencionInd["impuestoValorSinAportes"] = $impuestoValorSinAportes;
                                    $arrayRetencionInd["retencionContingente"] = 0;
                                }
                                
                            }


                        }
                        
                    }
                    
                    //Calculo retencion retiro
                    if($tipoliquidacion == "3"){     
                        $variableUVT = DB::table("variable")->where("idVariable","=","10")->first();
                        $uvtActual = intval($variableUVT->valor);
                        $arrayRetencion = array();
                        $arrayRetencionInd = array();
                        $arrayRetencionPri = array();
            
                        $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")
                        ->where("gcc.fkGrupoConcepto", "=", "4")->get();
                        $arrComoCalcula[36] = array();
                        array_push($arrComoCalcula[36],"Se comienza a buscar los conceptos del grupo de concepto 4 - Salarial");
                        foreach($grupoConceptoCalculo as $grupoConcepto){
                            if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto != 36){

                                if($empleado->tipoRegimen == "Salario Integral" && $arrValorxConcepto[$grupoConcepto->fkConcepto]["naturaleza"]=="1"){
                                    $temp =  $arrValorxConcepto[$grupoConcepto->fkConcepto]["valor"];
                                    array_push($arrComoCalcula[36],"Concepto #".$grupoConcepto->fkConcepto." encontrado, es un salario integral = $".number_format($temp, 0,",","."));
                                }
                            }
                        }
                        $ingreso = 0;
                        $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")
                            ->where("gcc.fkGrupoConcepto", "=", "9")
                            ->get();        
                        array_push($arrComoCalcula[36],"Se comienza a buscar los conceptos del grupo de concepto 9 - INGRESO PARA RETENCION");
                        foreach($grupoConceptoCalculo as $grupoConcepto){
                            if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto!=36){
                                if($empleado->tipoRegimen == "Salario Integral" && $arrValorxConcepto[$grupoConcepto->fkConcepto]["naturaleza"]=="1"){
                                    $temp = $arrValorxConcepto[$grupoConcepto->fkConcepto]["valor"];
                                    $ingreso = $ingreso + floatval($temp);
                                    array_push($arrComoCalcula[36],"Concepto #".$grupoConcepto->fkConcepto." encontrado se suman = $".number_format($temp, 0,",","."));
                                }
                                else{
                                    $ingreso = $ingreso + floatval($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor']);
                                    array_push($arrComoCalcula[36],"Concepto #".$grupoConcepto->fkConcepto." encontrado se suman = $".number_format($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor'], 0,",","."));
                                }
                                
                                
                            }
                        }
                        
                        // if($periodo == 15){
                        //     if(substr($liquidacionNomina->fechaInicio,8,2) == "16"){
                        //         $fechaPrimeraQuincena = substr($liquidacionNomina->fechaInicio,0,8)."01";
                        //         $itemsBoucherPago = DB::table("item_boucher_pago","ibp")
                        //         ->select("ibp.*","c.nombre", "c.fkNaturaleza")
                        //         ->join("concepto as c","c.idconcepto","=","ibp.fkConcepto")
                        //         ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")
                        //         ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        //         ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        //         ->where("bp.fkEmpleado","=", $empleado->idempleado)
                        //         ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        //         ->where("ln.fkEstado","=","5")//Terminada
                        //         ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                        //         ->where("gcc.fkGrupoConcepto", "=", "9")
                        //         ->get();
                                
                        //         foreach($itemsBoucherPago as $itemBoucherPago){
                                    
                                    
                        //             if($empleado->tipoRegimen == "Salario Integral" && $itemBoucherPago->fkNaturaleza=="1"){
                        //                 $temp =  (floatval($itemBoucherPago->pago) * 70)/100;
                        //                 $ingreso = $ingreso + floatval($temp);
                        //                 array_push($arrComoCalcula[36],"Concepto ".$itemBoucherPago->nombre." encontrado en liquidaciones anteriores se suman = $".number_format($temp, 0,",","."));
                        //             }
                        //             else{
                        //                 $ingreso = $ingreso + floatval($itemBoucherPago->pago);
                        //                 array_push($arrComoCalcula[36],"Concepto ".$itemBoucherPago->nombre." encontrado  en liquidaciones anteriores se suman = $".number_format($itemBoucherPago->pago, 0,",","."));
                        //             }
                        //         }


                        //         $itemsBoucherPago = DB::table("item_boucher_pago","ibp")
                        //         ->select("ibp.*","c.nombre", "c.fkNaturaleza")
                        //         ->join("concepto as c","c.idconcepto","=","ibp.fkConcepto")
                        //         ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")
                        //         ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        //         ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        //         ->where("bp.fkEmpleado","=", $empleado->idempleado)
                        //         ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        //         ->where("ln.fkEstado","=","5")//Terminada
                        //         ->where("ln.fechaInicio","=",$liquidacionNomina->fechaInicio)
                        //         ->where("gcc.fkGrupoConcepto", "=", "9")
                        //         ->get();
                                
                        //         foreach($itemsBoucherPago as $itemBoucherPago){
                        //             if($empleado->tipoRegimen == "Salario Integral" && $itemBoucherPago->fkNaturaleza=="1"){
                        //                 $temp =  (floatval($itemBoucherPago->pago) * 70)/100;
                        //                 $ingreso = $ingreso + floatval($temp);
                        //                 array_push($arrComoCalcula[36],"Concepto ".$itemBoucherPago->nombre." encontrado en liquidaciones anteriores se suman = $".number_format($temp, 0,",","."));
                        //             }
                        //             else{
                        //                 $ingreso = $ingreso + floatval($itemBoucherPago->pago);
                        //                 array_push($arrComoCalcula[36],"Concepto ".$itemBoucherPago->nombre." encontrado  en liquidaciones anteriores se suman = $".number_format($itemBoucherPago->pago, 0,",","."));
                        //             }
                        //         }
                        //     }
                        // }


                        array_push($arrComoCalcula[36],"Ingreso para retención = $".number_format($ingreso, 0,",","."));
                        $valorSalario = 0;
                        $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")
                            ->where("gcc.fkGrupoConcepto", "=", "67")
                            ->get();
                        foreach($grupoConceptoCalculo as $grupoConcepto){
                            if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto != 36){
                                if($empleado->tipoRegimen == "Salario Integral" && $arrValorxConcepto[$grupoConcepto->fkConcepto]["naturaleza"]=="1"){
                                    $temp =  ($arrValorxConcepto[$grupoConcepto->fkConcepto]["valor"] * 70)/100;
                                    $valorSalario = $valorSalario + floatval($temp);
                                }
                                else{
                                    $valorSalario = $valorSalario + floatval($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor']);
                                }
                                
                            }
                        }
                        
                        if($periodo == 15){
                            if(substr($liquidacionNomina->fechaInicio,8,2) == "16"){
                                $fechaPrimeraQuincena = substr($liquidacionNomina->fechaInicio,0,8)."01";
                                $itemsBoucherPago = DB::table("item_boucher_pago","ibp")
                                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")
                                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                                ->where("bp.fkEmpleado","=", $empleado->idempleado)
                                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                ->where("ln.fkEstado","=","5")//Terminada
                                ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                                ->where("gcc.fkGrupoConcepto", "=", "67")
                                ->get();
                                
                                foreach($itemsBoucherPago as $itemBoucherPago){
                                    $valorSalario = $valorSalario + floatval($itemBoucherPago->pago);
                                }


                                $itemsBoucherPago = DB::table("item_boucher_pago","ibp")
                                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")
                                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                                ->where("bp.fkEmpleado","=", $empleado->idempleado)
                                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                ->where("ln.fkEstado","=","5")//Terminada
                                ->where("ln.fechaInicio","=",$liquidacionNomina->fechaInicio)
                                ->where("gcc.fkGrupoConcepto", "=", "67")
                                ->get();
                                
                                foreach($itemsBoucherPago as $itemBoucherPago){
                                    $valorSalario = $valorSalario + floatval($itemBoucherPago->pago);
                                }
                            }
                        }
                        array_push($arrComoCalcula[36],"Se toma el valor del salario para el periodo actual segun el grupo de concepto 3 -> Salario = $".number_format($valorSalario, 0,",","."));

                        $variablesRetencion = DB::table("variable")
                        ->where("idVariable",">=","16")
                        ->where("idVariable","<=","48")
                        ->orWhereIn("idVariable",["66","67"])
                        ->get();
                        $varRetencion = array();
                        foreach($variablesRetencion as $variablesRetencio){
                            $varRetencion[$variablesRetencio->idVariable] = $variablesRetencio->valor;
                        }

                        $valorSalarioParaFuera = $valorSalario;
                        /*if($empleado->tipoRegimen == "Salario Integral"){
                            $valorSalarioParaFuera = $valorSalario*0.7;
                            
                        }*/
                        
                        
                        if($valorSalarioParaFuera > ($uvtActual * $varRetencion[66])){//TOPE_MAXIMO_SALARIO_UVTS_FUERA_DE_NOMINA (Nota: lo del salario integral se cambia mas arriba)
                            $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")
                                ->where("gcc.fkGrupoConcepto", "=", "43")
                                ->get();
                            
                            array_push($arrComoCalcula[36],"Salario ($ ".number_format($valorSalarioParaFuera, 0,",",".").") supera las ".$varRetencion[66]." UVTS configuradas en TOPE_MAXIMO_SALARIO_UVTS_FUERA_DE_NOMINA");
                            array_push($arrComoCalcula[36],"Se comienza a buscar los conceptos del grupo de concepto 43 - INGRESO FUERA DE NOMINA PARA RETENCION");
                            foreach($grupoConceptoCalculo as $grupoConcepto){
                                if(isset($arrValorxConceptoFueraNomina[$grupoConcepto->fkConcepto])){
                                    $ingreso = $ingreso + floatval($arrValorxConceptoFueraNomina[$grupoConcepto->fkConcepto]['valor']);
                                    array_push($arrComoCalcula[36],"Concepto #".$grupoConcepto->fkConcepto." encontrado se suman al ingreso $".number_format($arrValorxConceptoFueraNomina[$grupoConcepto->fkConcepto]['valor'], 0,",","."));
                                }
                            }
                            array_push($arrComoCalcula[36],"Ingreso actual en $".number_format($ingreso, 0,",","."));
                            

                        }
                        else{
                            $fueraSalario = 0;
                            $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")
                                ->where("gcc.fkGrupoConcepto", "=", "43")
                                ->get();
                            array_push($arrComoCalcula[36],"Se comienza a buscar los conceptos del grupo de concepto 43 - INGRESO FUERA DE NOMINA PARA RETENCION");
                            foreach($grupoConceptoCalculo as $grupoConcepto){
                                if(isset($arrValorxConceptoFueraNomina[$grupoConcepto->fkConcepto])){
                                    $fueraSalario = $fueraSalario + floatval($arrValorxConceptoFueraNomina[$grupoConcepto->fkConcepto]['valor']);
                                    array_push($arrComoCalcula[36],"Concepto #".$grupoConcepto->fkConcepto." encontrado se suman en fuera de salario $".number_format($arrValorxConceptoFueraNomina[$grupoConcepto->fkConcepto]['valor'], 0,",","."));
                                }
                            }
                            
                            if($periodo == 15){
                                if(substr($liquidacionNomina->fechaInicio,8,2) == "16"){
                                    $fechaPrimeraQuincena = substr($liquidacionNomina->fechaInicio,0,8)."01";
                                    $itemsBoucherPago = DB::table("item_boucher_pago","ibp")
                                    ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")
                                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                                    ->where("bp.fkEmpleado","=", $empleado->idempleado)
                                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                    ->where("ln.fkEstado","=","5")//Terminada
                                    ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                                    ->where("gcc.fkGrupoConcepto", "=", "43")
                                    ->get();
                                    
                                    array_push($arrComoCalcula[36],"Se comienza a buscar los conceptos del grupo de concepto 43 - INGRESO FUERA DE NOMINA PARA RETENCION en liquidacion de la quincena pasada");
                                    foreach($itemsBoucherPago as $itemBoucherPago){
                                        $fueraSalario = $fueraSalario + floatval($itemBoucherPago->pago);
                                    }
                                }
                            }

                            

                            if($fueraSalario > ($uvtActual * $varRetencion[67])){//TOPE_MAXIMO_UVTS_FUERA_DE_NOMINA
                                array_push($arrComoCalcula[36],"La suma de fuera de salario($".number_format($fueraSalario, 0,",",".").") supera el TOPE_MAXIMO_UVTS_FUERA_DE_NOMINA (".$varRetencion[67].")");
                                $excendete = $fueraSalario - ($uvtActual * $varRetencion[67]);
                                array_push($arrComoCalcula[36],"Se agrega el excente ($".number_format($excendete, 0,",",".").") al ingreso");
                                $ingreso = $ingreso + $excendete;
                                array_push($arrComoCalcula[36],"Ingreso actual en ($".number_format($ingreso, 0,",",".").")");
                            }

                        }

                        
                        $FPS = (isset($arrValorxConcepto[33]) ? $arrValorxConcepto[33]['valor'] : 0);
                        $EPS = (isset($arrValorxConcepto[18]) ? $arrValorxConcepto[18]['valor'] : 0);
                        $AFP = (isset($arrValorxConcepto[19]) ? $arrValorxConcepto[19]['valor'] : 0);
                        $SS = ($FPS + $EPS + $AFP) * -1;
                        
                        
                        if($periodo == 15){
                            if(substr($liquidacionNomina->fechaInicio,8,2) == "16"){
                                $itemsBoucherPago = DB::table("item_boucher_pago","ibp")
                                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")
                                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                                ->where("bp.fkEmpleado","=", $empleado->idempleado)
                                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                ->where("ln.fkEstado","=","5")//Terminada
                                ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                                ->where("gcc.fkGrupoConcepto", "=", "9") //9-INGRESO PARA RETENCION	
                                ->get();
                
                                foreach($itemsBoucherPago as $itemBoucherPago){
                                    $ingreso = $ingreso + floatval($itemBoucherPago->pago);
                                }
                                
                                $itemsBoucherPago = DB::table("item_boucher_pago","ibp")
                                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")
                                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                                ->where("bp.fkEmpleado","=", $empleado->idempleado)
                                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                ->where("ln.fkEstado","=","5")//Terminada
                                ->where("ln.fechaInicio","=",$liquidacionNomina->fechaInicio)
                                ->where("gcc.fkGrupoConcepto", "=", "9") //9-INGRESO PARA RETENCION	
                                ->get();
                
                                foreach($itemsBoucherPago as $itemBoucherPago){
                                    $ingreso = $ingreso + floatval($itemBoucherPago->pago);
                                }

                                $itemsBoucherPagoSS = DB::table("item_boucher_pago","ibp")
                                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                                ->where("bp.fkEmpleado","=", $empleado->idempleado)
                                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                ->where("ln.fkEstado","=","5")//Terminada
                                ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                                ->whereIn("ibp.fkConcepto",["33","18","19"])//SS
                                ->get();
                                foreach($itemsBoucherPagoSS as $itemBoucherPagoSS){
                                    $SS = $SS + floatval($itemBoucherPagoSS->descuento);
                                    if($itemBoucherPagoSS->fkConcepto == "33"){
                                        $FPS = $FPS + $itemBoucherPagoSS->valor;
                                    }
                                    else if($itemBoucherPagoSS->fkConcepto == "18"){
                                        $EPS = $EPS + $itemBoucherPagoSS->valor;
                                    }
                                    else if($itemBoucherPagoSS->fkConcepto == "19"){
                                        $AFP = $AFP + $itemBoucherPagoSS->valor;
                                    }

                                }
                            }
                            //dd($ingreso);
                        }
                        
                        $rentaLiquida = $ingreso - $SS;
                        array_push($arrComoCalcula[36],"Se calcula la renta liquida como Ingreso($".number_format($ingreso, 0,",",".").") menos Seguridad Social ($".number_format($SS, 0,",",".").")");
                        array_push($arrComoCalcula[36],"Renta liquida = ($".number_format($rentaLiquida, 0,",",".").")");
                        $interesesVivienda = 0;
                        $beneficiosTributarioIntVivienda = DB::table("beneficiotributario", "bt")
                            ->selectRaw("sum(bt.valorMensual) as suma")
                            ->where("bt.fkEmpleado","=",$empleado->idempleado)
                            ->where("bt.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                            ->where("bt.fkTipoBeneficio", "=", "2")
                            ->whereDate("bt.fechaVigencia",">=", $fechaFin)
                            ->get();
                        
                        $interesesVivienda = intval($beneficiosTributarioIntVivienda[0]->suma);
                        array_push($arrComoCalcula[36],"Intereses de vivienda = ($".number_format($interesesVivienda, 0,",",".").")");
                        if($interesesVivienda > round($uvtActual * $varRetencion[16], -3)){
                            
                            $interesesVivienda = round($uvtActual * $varRetencion[16], -3);
                            array_push($arrComoCalcula[36],"Intereses de vivienda superan el TOPE_MAXIMO_INTERESES_VIVIENDA_UVTS_RETENCION(".$varRetencion[16].")");
                            array_push($arrComoCalcula[36],"Nuevos intereses de vivienda = ($".number_format($interesesVivienda, 0,",",".").")");
                        }            
                        $medicinaPrepagada = 0;
                        $beneficiosTributarioMedicinaPrepagada = DB::table("beneficiotributario", "bt")
                            ->selectRaw("sum(bt.valorMensual) as suma")
                            ->where("bt.fkEmpleado","=",$empleado->idempleado)
                            ->where("bt.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                            ->where("bt.fkTipoBeneficio", "=", "3")
                            ->whereDate("bt.fechaVigencia",">=", $fechaFin)
                            ->get();
                        
                        $medicinaPrepagada = intval($beneficiosTributarioMedicinaPrepagada[0]->suma);
                        array_push($arrComoCalcula[36],"Medicina Prepagada = ($".number_format($medicinaPrepagada, 0,",",".").")");
                        if($medicinaPrepagada > round($uvtActual * $varRetencion[17], -3)){
                            $medicinaPrepagada = round($uvtActual * $varRetencion[17], -3);
                            array_push($arrComoCalcula[36],"Medicina Prepagada superan el TOPE_MAXIMO_MEDICINA_PREPAGADA_UVTS_RETENCION(".$varRetencion[17].")");
                            array_push($arrComoCalcula[36],"Nuevo valor medicina prepagada = ($".number_format($medicinaPrepagada, 0,",",".").")");
                        }

                        //Calcular cuanto cuesta este dependiente
                        $dependiente = 0;
                        $beneficiosTributarioDependiente = DB::table("beneficiotributario", "bt")
                            ->select("bt.idBeneficioTributario")
                            ->where("bt.fkEmpleado","=",$empleado->idempleado)
                            ->where("bt.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                            ->where("bt.fkTipoBeneficio", "=", "4")
                            ->whereDate("bt.fechaVigencia",">=", $fechaFin)
                            ->get();
                        
                        if(sizeof($beneficiosTributarioDependiente)> 0){
                            $dependiente = ($ingreso * $varRetencion[18]);
                            array_push($arrComoCalcula[36],"Dependiente = ($".number_format($dependiente, 0,",",".").")");
                        }
                                    
                        //Tope maximo dependencia
                        if($dependiente > round($uvtActual * $varRetencion[19], -3)){
                            $dependiente = round($uvtActual * $varRetencion[19], -3);
                            array_push($arrComoCalcula[36],"Dependiente supera el TOPE_MAXIMO_DEPENDIENTE_UVTS_RETENCION(".$varRetencion[19].")");
                            array_push($arrComoCalcula[36],"Nuevo valor dependiente = ($".number_format($dependiente, 0,",",".").")");
                        }

                        $aporteVoluntario = 0;
                        $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")
                            ->where("gcc.fkGrupoConcepto", "=", "6")
                            ->get();
                        array_push($arrComoCalcula[36],"Comienza a buscar aporte voluntario segun el grupo de concepto 6");
                        foreach($grupoConceptoCalculo as $grupoConcepto){
                            if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto != 36){
                                array_push($arrComoCalcula[36],"Concepto #".$grupoConcepto->fkConcepto." encontrado se suman en aporte voluntario $".number_format($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor'], 0,",","."));
                                $aporteVoluntario = $aporteVoluntario + floatval($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor']);
                            }
                        }
                        $aporteVoluntario = $aporteVoluntario * -1;
                        array_push($arrComoCalcula[36],"Aporte voluntario = ($".number_format($aporteVoluntario, 0,",",".").")");
                        if($aporteVoluntario > round($rentaLiquida * $varRetencion[20], -3)){
                            $aporteVoluntario = round($rentaLiquida * $varRetencion[20], -3);
                            array_push($arrComoCalcula[36],"Aporte voluntario supera el TOPE_MAXIMO_PORCENTAJE_APORTE_VOLUNTARIO_RETENCION(".$varRetencion[20].")");
                            array_push($arrComoCalcula[36],"Nuevo valor aporte voluntario = ($".number_format($aporteVoluntario, 0,",",".").")");
                        }
                        
                        $AFC = 0;
                        $grupoConceptoCalculo = DB::table("grupoconcepto_concepto","gcc")
                            ->where("gcc.fkGrupoConcepto", "=", "8")
                            ->get();
                        array_push($arrComoCalcula[36],"Comienza a buscar AFC segun el grupo de concepto 8");
                        foreach($grupoConceptoCalculo as $grupoConcepto){
                            if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto != 36){
                                $AFC = $AFC + floatval($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor']);
                                array_push($arrComoCalcula[36],"Concepto #".$grupoConcepto->fkConcepto." encontrado se suman en AFC $".number_format($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor'], 0,",","."));
                            }
                        }
                        
                        $AFC = $AFC * -1;
                        array_push($arrComoCalcula[36],"AFC = ($".number_format($AFC, 0,",",".").")");
                        if($AFC > round($rentaLiquida * $varRetencion[21], -3)){
                            $AFC = round($rentaLiquida * $varRetencion[21], -3);
                            array_push($arrComoCalcula[36],"AFC supera el TOPE_MAXIMO_PORCENTAJE_AFC_RETENCION(".$varRetencion[21].")");
                            array_push($arrComoCalcula[36],"Nuevo valor AFC = ($".number_format($AFC, 0,",",".").")");
                        }
                        
                        $deducciones = $interesesVivienda + $medicinaPrepagada + $dependiente + $aporteVoluntario + $AFC;

                        array_push($arrComoCalcula[36],"Deducciones = ($".number_format($deducciones, 0,",",".").") la suma de intereses vivienda, medicina prepagada, dependiente, aporte voluntario y AFC");
                        $deduccionesSinAportes = $interesesVivienda + $medicinaPrepagada + $dependiente;

                        $baseNeta = $rentaLiquida - $deducciones;
                        array_push($arrComoCalcula[36],"Base neta = ($".number_format($baseNeta, 0,",",".").") => renta liquida menos deducciones ");
                        $baseNetaSinAportes = $rentaLiquida - $deduccionesSinAportes;

                        $exenta = $baseNeta * $varRetencion[22];
                        array_push($arrComoCalcula[36],"Parte exenta = ($".number_format($exenta, 0,",",".").") => base Neta * (".$varRetencion[22].") PORCENTAJE_PARTE_EXENTA_RETENCION");
                        $exentaSinAportes = $baseNetaSinAportes * $varRetencion[22];

                
                        if($exenta > round($uvtActual * $varRetencion[23],-3)){
                            $exenta = round($uvtActual * $varRetencion[23], -3);
                            array_push($arrComoCalcula[36],"Parte excenta supera el TOPE_MAXIMO_PARTE_EXENTA_UVTS_RETENCION(".$varRetencion[23].")");
                            array_push($arrComoCalcula[36],"Nuevo valor Parte excenta = ($".number_format($exenta, 0,",",".").")");
                        }
                        if($exentaSinAportes > round($uvtActual * $varRetencion[23],-3)){
                            $exentaSinAportes = round($uvtActual * $varRetencion[23], -3);
                        }

                        $totalBeneficiosTributarios = $exenta + $deducciones;
                        array_push($arrComoCalcula[36],"Total beneficicos tributarios = ($".number_format($totalBeneficiosTributarios, 0,",",".").") => exenta + deducciones");
                        $totalBeneficiosTributariosSinAportes = $exentaSinAportes + $deduccionesSinAportes;
                        
                        $topeBeneficios= $rentaLiquida*$varRetencion[24];

                        if($totalBeneficiosTributarios > ($rentaLiquida*$varRetencion[24])){
                            $totalBeneficiosTributarios = $rentaLiquida*$varRetencion[24];
                            array_push($arrComoCalcula[36],"Total Beneficios Tributarios supera el TOPE_MAXIMO_BENEFICIOS_RENTA_LIQUIDA_RETENCION(".$varRetencion[24].")");
                            array_push($arrComoCalcula[36],"Nuevo Total Beneficios Tributarios = ($".number_format($totalBeneficiosTributarios, 0,",",".").")");
                        }

                        if($totalBeneficiosTributarios > round($uvtActual*$varRetencion[25],-3)){
                            
                            $totalBeneficiosTributarios = round($uvtActual*$varRetencion[25], -3);
                            $topeBeneficios= $rentaLiquida*round($uvtActual*$varRetencion[25], -3);
                            array_push($arrComoCalcula[36],"Total Beneficios Tributarios supera el TOPE_MAXIMO_BENEFICIOS_UVTS_RETENCION(".$varRetencion[25].")");
                            array_push($arrComoCalcula[36],"Nuevo Total Beneficios Tributarios = ($".number_format($totalBeneficiosTributarios, 0,",",".").")");
                        }

                        if($totalBeneficiosTributariosSinAportes > ($rentaLiquida*$varRetencion[24])){
                            $totalBeneficiosTributariosSinAportes = $rentaLiquida*$varRetencion[24];
                        }
                        if($totalBeneficiosTributariosSinAportes > round($uvtActual*$varRetencion[25],-3)){
                            $totalBeneficiosTributariosSinAportes = round($uvtActual*$varRetencion[25], -3);
                        }

                        $baseGravable  = $rentaLiquida - $totalBeneficiosTributarios;
                        array_push($arrComoCalcula[36],"Base gravable = ($".number_format($baseGravable, 0,",",".").") => renta liquida - total beneficios tributarios");

                        $baseGravableSinAportes  = $rentaLiquida - $totalBeneficiosTributariosSinAportes;
                        
                        $baseGravableUVTS = round($baseGravable / $uvtActual, 2);
                        array_push($arrComoCalcula[36],"Base gravable en uvts = ".$baseGravableUVTS);
                        $baseGravableSinAportesUVTS = round($baseGravableSinAportes / $uvtActual, 2);
                        
                        $impuestoUVT = 0;
                        $tablaRete = DB::table("tabla_retencion")->orderBy("minimo")->orderBy("maximo")->get();
                        foreach($tablaRete as $tablaRet){
                            if(!isset($tablaRet->minimo)){
                                $tablaRet->minimo = 0;
                            }
                            
                            if(!isset($tablaRet->maximo)){
                                $tablaRet->minimo = 99999999;
                            }

                            if($baseGravableUVTS > $tablaRet->minimo && $baseGravableUVTS <= $tablaRet->maximo){
                                array_push($arrComoCalcula[36],$tablaRet->minimo."<".$baseGravableUVTS."<=".$tablaRet->maximo." En uvts");
                                $impuestoUVT = ($baseGravableUVTS - $tablaRet->minimo)*$tablaRet->porcentaje;
                                
                                $impuestoUVT = $impuestoUVT + $tablaRet->adicion;
                                array_push($arrComoCalcula[36],"Impuesto en uvt = ".$impuestoUVT);
                                break;
                            }
                        }

                        $impuestoSinAportesUVT = 0;
                        foreach($tablaRete as $tablaRet){
                            if(!isset($tablaRet->minimo)){
                                $tablaRet->minimo = 0;
                            }
                            
                            if(!isset($tablaRet->maximo)){
                                $tablaRet->minimo = 99999999;
                            }

                            if($baseGravableSinAportesUVTS > $tablaRet->minimo && $baseGravableSinAportesUVTS <= $tablaRet->maximo){
                                $impuestoSinAportesUVT = ($baseGravableSinAportesUVTS - $tablaRet->minimo)*$tablaRet->porcentaje;
                                $impuestoSinAportesUVT = $impuestoSinAportesUVT + $tablaRet->adicion;
                                break;
                            }
                        }

                        $impuestoValor = round($impuestoUVT * $uvtActual, -3);
                        array_push($arrComoCalcula[36],"Se multiplica el impuesto en uvts por valor de uvt actual = ".$impuestoValor);
                        $impuestoValorSinAportes = round($impuestoSinAportesUVT * $uvtActual, -3);
                        $valorInt = $impuestoValor;
                        //dd($arrValorxConcepto, $arrValorxConceptoOtros, $impuestoValor );
                        if($impuestoValor>0){
                            if($periodo == 30){
                                
                                if(isset($arrValorxConcepto[36])){
                                
                                    $arrValorxConcepto[36] = array(
                                        "naturaleza" => "3",
                                        "unidad" => "UNIDAD",
                                        "cantidad"=> "0",
                                        "arrNovedades"=> array(),
                                        "valor" => ($arrValorxConcepto[36]['valor'] - ($valorInt)),
                                        "tipoGen" => "automaticos"
                                    );
                                }
                                else{
                                    
                                    $arrValorxConcepto[36] = array(
                                        "naturaleza" => "3",
                                        "unidad" => "UNIDAD",
                                        "cantidad"=> "0",
                                        "arrNovedades"=> array(),
                                        "valor" => $valorInt*-1 ,
                                        "tipoGen" => "automaticos"
                                    );
                                }
                            }
                            else{
                                if(substr($liquidacionNomina->fechaInicio,8,2) == "01"){
                                
                                    if(isset($arrValorxConcepto[36])){
                                        $arrValorxConcepto[36] = array(
                                            "naturaleza" => "3",
                                            "unidad" => "UNIDAD",
                                            "cantidad"=> "0",
                                            "arrNovedades"=> array(),
                                            "valor" => ($arrValorxConcepto[36]['valor'] - ($valorInt)),
                                            "tipoGen" => "automaticos"
                                        );
                                    }
                                    else{
                                        $arrValorxConcepto[36] = array(
                                            "naturaleza" => "3",
                                            "unidad" => "UNIDAD",
                                            "cantidad"=> "0",
                                            "arrNovedades"=> array(),
                                            "valor" => $valorInt*-1 ,
                                            "tipoGen" => "automaticos"
                                        );
                                    }
                                }
                                else{
                                    $fechaPrimeraQuincena = substr($liquidacionNomina->fechaInicio,0,8)."01";                        
                                    $itemReteFuente = DB::table("item_boucher_pago","ibp")
                                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                                    ->where("bp.fkEmpleado","=", $empleado->idempleado)
                                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                                    ->where("ln.fkEstado","=","5")//Terminada
                                    ->where("ln.fechaInicio","=",$fechaPrimeraQuincena)
                                    ->where("ibp.fkConcepto","=","36")//RETENCION                
                                    ->get();
                                    if(sizeof($itemReteFuente)> 0){
                                        $valorInt = $valorInt - $itemReteFuente[0]->descuento;
                                    }                    
                                    if(isset($arrValorxConcepto[36])){
                                        $arrValorxConcepto[36] = array(
                                            "naturaleza" => "3",
                                            "unidad" => "UNIDAD",
                                            "cantidad"=> "0",
                                            "arrNovedades"=> array(),
                                            "valor" => ($arrValorxConcepto[36]['valor'] - ($valorInt)),
                                            "tipoGen" => "automaticos"
                                        );
                                    }
                                    else{
                                        $arrValorxConcepto[36] = array(
                                            "naturaleza" => "3",
                                            "unidad" => "UNIDAD",
                                            "cantidad"=> "0",
                                            "arrNovedades"=> array(),
                                            "valor" => $valorInt*-1 ,
                                            "tipoGen" => "automaticos"
                                        );
                                    }
                                }
                            }
                        }
                        else{
                        
                        }
                        
            
            
                        
                        $retencionContingente = $impuestoValorSinAportes - $impuestoValor;
            
                        $arrayRetencion["salario"] = $valorSalario;
                        $arrayRetencion["ingreso"] = $ingreso;
                        $arrayRetencion["EPS"] = $EPS*-1;
                        $arrayRetencion["AFP"] = $AFP*-1;
                        $arrayRetencion["FPS"] = $FPS*-1;
                        $arrayRetencion["seguridadSocial"] = $SS;
                        $arrayRetencion["interesesVivienda"] = $interesesVivienda;
                        $arrayRetencion["medicinaPrepagada"] = $medicinaPrepagada;
                        $arrayRetencion["dependiente"] = $dependiente;
                        $arrayRetencion["aporteVoluntario"] = $aporteVoluntario;
                        $arrayRetencion["AFC"] = $AFC;
                        $arrayRetencion["exenta"] = $exenta;
                        $arrayRetencion["exentaSinAportes"] = $exentaSinAportes;
                        $arrayRetencion["totalBeneficiosTributarios"] = $totalBeneficiosTributarios;
                        $arrayRetencion["totalBeneficiosTributariosSinAportes"] = $totalBeneficiosTributariosSinAportes;
                        $arrayRetencion["topeBeneficios"] = $topeBeneficios;
                        $arrayRetencion["baseGravableUVTS"] = $baseGravableUVTS;
                        $arrayRetencion["impuestoUVT"] = $impuestoUVT;
                        $arrayRetencion["impuestoSinAportesUVT"] = $impuestoSinAportesUVT;
                        $arrayRetencion["impuestoValor"] = $impuestoValor;
                        $arrayRetencion["impuestoValorSinAportes"] = $impuestoValorSinAportes;
                        $arrayRetencion["retencionContingente"] = $retencionContingente;
                    }
                    
                    

                    $itemsBoucherMismoPeriodoNomin = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("ibp.*")
                    ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("ln.fechaInicio","=",$fechaInicio)
                    ->where("ln.fechaFin","=",$fechaFin)
                    ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                    ->whereNotIn("ln.fkTipoLiquidacion",["7","10","11"]) //Puede tenerlas en el mismo periodo
                    ->where("gcc.fkGrupoConcepto", "=","46")
                    ->get();
                    
         
                    foreach($itemsBoucherMismoPeriodoNomin as $itemBoucherMismoPeriodoNomin){                        
                        /*if(isset($arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto])){
                            $arrComoCalcula[$itemBoucherMismoPeriodoNomin->fkConcepto] = ($arrComoCalcula[$itemBoucherMismoPeriodoNomin->fkConcepto] ?? array());

                            array_push($arrComoCalcula[$itemBoucherMismoPeriodoNomin->fkConcepto],
                            "* Se resta $".number_format($arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["valor"], 0,",",".")."
                            ya que es el mismo concepto en otra liquidacion pero con el mismo periodo ".$fechaInicio." a ".$fechaFin);

                            $arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["valor"] = round($arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["valor"]) - $itemBoucherMismoPeriodoNomin->valor;
                            $arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["cantidad"] = round($arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["cantidad"]) - $itemBoucherMismoPeriodoNomin->cantidad;
                        }*/
                    }

                


                    if($periodo == 15){                
                        if(substr($liquidacionNomina->fechaInicio,8,2) == "01"){
                            $itemsBoucherPeriodo16 = DB::table("item_boucher_pago", "ibp")
                            ->selectRaw("ibp.*")
                            ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")
                            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                            ->where("bp.fkEmpleado","=",$empleado->idempleado)
                            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                            ->where("ln.fechaInicio","=",date("Y-m-16",strtotime($fechaInicio)))
                            ->where("ln.fechaFin","=",date("Y-m-t",strtotime($fechaInicio)))
                            ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                            ->whereNotIn("ln.fkTipoLiquidacion",["7","10","11"]) //Puede tenerlas en el mismo periodo
                            ->where("gcc.fkGrupoConcepto", "=","46")
                            ->get();
                            
                            foreach($itemsBoucherPeriodo16 as $itemBoucherMismoPeriodoNomin){                        
                                if(isset($arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto])){
                                    
                                    $arrComoCalcula[$itemBoucherMismoPeriodoNomin->fkConcepto] = ($arrComoCalcula[$itemBoucherMismoPeriodoNomin->fkConcepto] ?? array());

                                    array_push($arrComoCalcula[$itemBoucherMismoPeriodoNomin->fkConcepto],
                                    "- Se resta $".number_format($arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["valor"], 0,",",".")."
                                    ya que es el mismo concepto en otra liquidacion con periodo ".date("Y-m-16",strtotime($fechaInicio))." a ".date("Y-m-t",strtotime($fechaInicio)));
                                    
                                    $arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["valor"] = round($arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["valor"]) - $itemBoucherMismoPeriodoNomin->valor;
                                    $arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["cantidad"] = round($arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["cantidad"]) - $itemBoucherMismoPeriodoNomin->cantidad;
                                }
                            }
            
                            
                        }
                        
                    }
                    else{
                        //Verificar mes anterior
                        $itemsBoucherPeriodoMesAnt = DB::table("item_boucher_pago", "ibp")
                        ->selectRaw("ibp.*")
                        ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")
                        ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                        ->where("bp.fkEmpleado","=",$empleado->idempleado)
                        ->where("ln.fechaInicio","=",date("Y-m-01",strtotime($fechaInicio."- 1 month")))
                        ->where("ln.fechaFin","=",date("Y-m-t",strtotime($fechaInicio."- 1 month")))
                        ->whereRaw("YEAR(ln.fechaLiquida) = '".date("Y",strtotime($fechaInicio))."'")
                        ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                        ->whereIn("ln.fkTipoLiquidacion",["3","7"])
                        ->where("gcc.fkGrupoConcepto", "=","46")
                        ->get();
                        
                        foreach($itemsBoucherPeriodoMesAnt as $itemBoucherMismoPeriodoNomin){                        
                            if(isset($arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto])){


                                $arrComoCalcula[$itemBoucherMismoPeriodoNomin->fkConcepto] = ($arrComoCalcula[$itemBoucherMismoPeriodoNomin->fkConcepto] ?? array());
                                

                                array_push($arrComoCalcula[$itemBoucherMismoPeriodoNomin->fkConcepto],
                                "+ Se resta $".number_format($arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["valor"], 0,",",".")."
                                ya que es el mismo concepto en otra liquidacion con periodo ".date("Y-m-01",strtotime($fechaInicio."- 1 month"))." a ".date("Y-m-t",strtotime($fechaInicio."- 1 month")));
                                

                                $arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["valor"] = round($arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["valor"]) - $itemBoucherMismoPeriodoNomin->valor;
                                $arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["cantidad"] = round($arrValorxConcepto[$itemBoucherMismoPeriodoNomin->fkConcepto]["cantidad"]) - $itemBoucherMismoPeriodoNomin->cantidad;
                            }
                        }                   
                        
                    }
            
                    if($novedadesRetiro->fechaReal == $fechaFin){
                        /*$grupoConceptoIBCOtros = DB::table("grupoconcepto_concepto","gcc")
                        ->where("gcc.fkGrupoConcepto", "=", '46')//19->IBC Otros
                        ->get();
                        foreach($grupoConceptoIBCOtros as $grupoConcepto){
                            if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto])){
                                $arrValorxConcepto[$grupoConcepto->fkConcepto]["valor"] = 0;
                            }
                        }*/
                    }
                    else{
                        //Retiro de mes anterior
                        $inicioMesActual = date("Y-m-01",strtotime($fechaFin));
                        if(strtotime($novedadesRetiro->fechaReal) < strtotime($inicioMesActual)){
                            if((isset($arrValorxConcepto[1]["valor"]) && $arrValorxConcepto[1]["valor"]<=0) || (isset($arrValorxConcepto[2]["valor"]) && $arrValorxConcepto[2]["valor"]<=0)){
                                $arrValorxConcepto[18]["valor"]=0;
                                $arrValorxConcepto[19]["valor"]=0;
                                
                            }
                        }
                        
                    }
                
                    



                    

                    //INICIO PRESTAMOS Y EMBARGOS
                    $prestamos = DB::table("prestamo","p")
                    ->join("periocidad","periocidad.per_id","=","p.fkPeriocidad")
                    ->where("p.fkEstado","=","1")
                    ->where("p.fechaInicio","<=",$liquidacionNomina->fechaLiquida)
                    ->where("p.fkEmpleado","=", $empleado->idempleado)
                    ->where("p.fkPeriodoActivo","=", $periodoActivoReintegro->idPeriodo)
                    ->get();    
                    foreach($prestamos as $prestamo){
                        unset($arrValorxConcepto[$prestamo->fkConcepto]);
                    }                
                    foreach($prestamos as $prestamo){
                        $valorPrestamo = $prestamo->saldoActual; 
                        $arrPrestamo = array("idPrestamo" => $prestamo->idPrestamo, "valor" => $valorPrestamo);
                        
                        $arrComoCalcula[$prestamo->fkConcepto] = ($arrComoCalcula[$prestamo->fkConcepto] ?? array());
                        array_push($arrComoCalcula[$prestamo->fkConcepto], "Se suma el saldo del prestamo $".number_format($valorPrestamo, 0,",","."));

                        if(isset($arrValorxConcepto[$prestamo->fkConcepto])){
                            
                            $arrPrestamoNuevo = $arrValorxConcepto[$prestamo->fkConcepto]["arrPrestamo"];   
                            array_push($arrPrestamoNuevo, $arrPrestamo);
                            array_push($arrComoCalcula[$prestamo->fkConcepto], "Se suma junto con el acumulado anterior $".number_format($arrValorxConcepto[$prestamo->fkConcepto]["valor"], 0,",","."));
                            $arrValorxConcepto[$prestamo->fkConcepto] = array(
                                "naturaleza" => "3",
                                "unidad"=>"VALOR",
                                "cantidad"=> "0",
                                "arrNovedades"=> array(),
                                "arrPrestamo"=> $arrPrestamoNuevo,
                                "valor" => $arrValorxConcepto[$prestamo->fkConcepto]["valor"] + ($valorPrestamo*-1),
                                "tipoGen" => "prestamo"
                            );
                        }
                        else{
                            $arrValorxConcepto[$prestamo->fkConcepto] = array(
                                "naturaleza" => "3",
                                "unidad"=>"VALOR",
                                "cantidad"=> "0",
                                "arrNovedades"=> array(),
                                "arrPrestamo"=> array($arrPrestamo),
                                "valor" => $valorPrestamo*-1,
                                "tipoGen" => "prestamo"
                            );
                        }
                    
                    }
                    //FIN PRESTAMOS Y EMBARGOS





                                
                }
            }
            //FIN RETIROS


            //RETENCION EN LA FUENTE PRIMA
            if(isset($arrValorxConcepto[58])){
                $variablesRetencion = DB::table("variable")
                    ->where("idVariable",">=","16")
                    ->where("idVariable","<=","48")
                    ->get();
                $varRetencion = array();
                foreach($variablesRetencion as $variablesRetencio){
                    $varRetencion[$variablesRetencio->idVariable] = $variablesRetencio->valor;
                }
                $FPS = 0;
                $EPS = 0;
                $AFP = 0;
                $SS = ($FPS + $EPS + $AFP) * -1;

                $ingreso = $arrValorxConcepto[58]['valor'];

                $rentaLiquida = $ingreso - $SS;

                $interesesVivienda = 0;
                
                $medicinaPrepagada = 0;
            

                //Calcular cuanto cuesta este dependiente
                $dependiente = 0;
            
                            
                //Tope maximo dependencia
                if($dependiente > round($uvtActual * $varRetencion[19], -3)){
                    $dependiente = round($uvtActual * $varRetencion[19], -3);
                }

                $aporteVoluntario = 0;
            
                
                $AFC = 0;
                



                $deducciones = $interesesVivienda + $medicinaPrepagada + $dependiente + $aporteVoluntario + $AFC;
                $deduccionesSinAportes = $interesesVivienda + $medicinaPrepagada + $dependiente;

                $baseNeta = $rentaLiquida - $deducciones;
                $baseNetaSinAportes = $rentaLiquida - $deduccionesSinAportes;

                $exenta = $baseNeta * $varRetencion[22];
                $exentaSinAportes = $baseNetaSinAportes * $varRetencion[22];

        
                if($exenta > round($uvtActual * $varRetencion[23],-3)){
                    $exenta = round($uvtActual * $varRetencion[23], -3);
                }
                if($exentaSinAportes > round($uvtActual * $varRetencion[23],-3)){
                    $exentaSinAportes = round($uvtActual * $varRetencion[23], -3);
                }

                $totalBeneficiosTributarios = $exenta + $deducciones;
                $totalBeneficiosTributariosSinAportes = $exentaSinAportes + $deduccionesSinAportes;
                
                

                $topeBeneficios= $rentaLiquida*$varRetencion[24];

                if($totalBeneficiosTributarios > ($rentaLiquida*$varRetencion[24])){
                    $totalBeneficiosTributarios = $rentaLiquida*$varRetencion[24];
                }


                if($totalBeneficiosTributarios > round($uvtActual*$varRetencion[25],-3)){
                    $totalBeneficiosTributarios = round($uvtActual*$varRetencion[25], -3);
                    $topeBeneficios= $rentaLiquida*round($uvtActual*$varRetencion[25], -3);
                }


                if($totalBeneficiosTributariosSinAportes > ($rentaLiquida*$varRetencion[24])){
                    $totalBeneficiosTributariosSinAportes = $rentaLiquida*$varRetencion[24];
                }
                if($totalBeneficiosTributariosSinAportes > round($uvtActual*$varRetencion[25],-3)){
                    $totalBeneficiosTributariosSinAportes = round($uvtActual*$varRetencion[25], -3);
                }



                

                $baseGravable  = $rentaLiquida - $totalBeneficiosTributarios;
                $baseGravableSinAportes  = $rentaLiquida - $totalBeneficiosTributariosSinAportes;
                
                $baseGravableUVTS = round($baseGravable / $uvtActual, 2);
                $baseGravableSinAportesUVTS = round($baseGravableSinAportes / $uvtActual, 2);
                
                


                $impuestoUVT = 0;
                $tablaRete = DB::table("tabla_retencion")->orderBy("minimo")->orderBy("maximo")->get();
                foreach($tablaRete as $tablaRet){
                    if(!isset($tablaRet->minimo)){
                        $tablaRet->minimo = 0;
                    }
                    
                    if(!isset($tablaRet->maximo)){
                        $tablaRet->minimo = 99999999;
                    }

                    if($baseGravableUVTS > $tablaRet->minimo && $baseGravableUVTS <= $tablaRet->maximo){
                        $impuestoUVT = ($baseGravableUVTS - $tablaRet->minimo)*$tablaRet->porcentaje;
                        $impuestoUVT = $impuestoUVT + $tablaRet->adicion;
                        break;
                    }
                }
                
              
                
                $impuestoSinAportesUVT = 0;
                foreach($tablaRete as $tablaRet){
                    if(!isset($tablaRet->minimo)){
                        $tablaRet->minimo = 0;
                    }
                    
                    if(!isset($tablaRet->maximo)){
                        $tablaRet->minimo = 99999999;
                    }

                    if($baseGravableSinAportesUVTS > $tablaRet->minimo && $baseGravableSinAportesUVTS <= $tablaRet->maximo){
                        $impuestoSinAportesUVT = ($baseGravableSinAportesUVTS - $tablaRet->minimo)*$tablaRet->porcentaje;
                        $impuestoSinAportesUVT = $impuestoSinAportesUVT + $tablaRet->adicion;
                        break;
                    }
                }
               

                $impuestoValor = round($impuestoUVT * $uvtActual, -3);
                $impuestoValorSinAportes = round($impuestoSinAportesUVT * $uvtActual, -3);
                $valorInt = $impuestoValor;
                if($impuestoValor>0){
                    if(isset($arrValorxConcepto[77])){
                        $arrValorxConcepto[77] = array(
                            "naturaleza" => "3",
                            "unidad" => "UNIDAD",
                            "cantidad"=> "0",
                            "arrNovedades"=> array(),
                            "valor" => ($arrValorxConcepto[77]['valor'] - ($valorInt)),
                            "tipoGen" => "automaticos"
                        );
                    }
                    else{
                        $arrValorxConcepto[77] = array(
                            "naturaleza" => "3",
                            "unidad" => "UNIDAD",
                            "cantidad"=> "0",
                            "arrNovedades"=> array(),
                            "valor" => $valorInt*-1 ,
                            "tipoGen" => "automaticos"
                        );
                    }
                }
                    
                
                $valorSalario = 0;
                $retencionContingente = $impuestoValorSinAportes - $impuestoValor;
                $arrayRetencionPri["tipoRetencion"] = "PRIMA";
                $arrayRetencionPri["salario"] = $valorSalario;
                $arrayRetencionPri["ingreso"] = $ingreso;
                $arrayRetencionPri["EPS"] = 0;
                $arrayRetencionPri["AFP"] = 0;
                $arrayRetencionPri["FPS"] = 0;
                $arrayRetencionPri["seguridadSocial"] = 0;
                $arrayRetencionPri["interesesVivienda"] = 0;
                $arrayRetencionPri["medicinaPrepagada"] = 0;
                $arrayRetencionPri["dependiente"] = 0;
                $arrayRetencionPri["aporteVoluntario"] = 0;
                $arrayRetencionPri["AFC"] = 0;
                $arrayRetencionPri["exenta"] = $exenta;
                $arrayRetencionPri["exentaSinAportes"] = $exentaSinAportes;
                $arrayRetencionPri["totalBeneficiosTributarios"] = $totalBeneficiosTributarios;
                $arrayRetencionPri["totalBeneficiosTributariosSinAportes"] = $totalBeneficiosTributariosSinAportes;
                $arrayRetencionPri["topeBeneficios"] = $topeBeneficios;
                $arrayRetencionPri["baseGravableUVTS"] = $baseGravableUVTS;
                $arrayRetencionPri["impuestoUVT"] = $impuestoUVT;
                $arrayRetencionPri["impuestoSinAportesUVT"] = $impuestoSinAportesUVT;
                $arrayRetencionPri["impuestoValor"] = $impuestoValor;
                $arrayRetencionPri["impuestoValorSinAportes"] = $impuestoValorSinAportes;
                $arrayRetencionPri["retencionContingente"] = $retencionContingente;
            }
            //FIN RETENCION EN LA FUENTE PRIMA

            //CESANTIAS DE TRASLADO
            if($tipoliquidacion == "11"){
                $saldoCesantiasAnioAnterior = DB::table("saldo")
                ->where("fkEmpleado","=",$empleado->idempleado)
                ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("anioAnterior","=",date("Y",strtotime($fechaInicio)))
                ->where("fkConcepto","=", "67")//CESANTIAS AÑO ANTERIOR
                ->where("fkEstado","=","7")
                ->first();
                
                if(isset($saldoCesantiasAnioAnterior)){

                    $arrComoCalcula[84] = ($arrComoCalcula[84] ?? array());
                    array_push($arrComoCalcula[84], "Valor en saldos: $".number_format($saldoCesantiasAnioAnterior->valor, 0,",","."));

                    if(isset($arrValorxConcepto[84])){
                        $arrValorxConceptoFueraNomina[84] = array(
                            "naturaleza" => "5",
                            "unidad" => "UNIDAD",
                            "cantidad"=> 0,
                            "arrNovedades"=> array(),
                            "arrSaldo"=> array([
                                "idSaldo" => $saldoCesantiasAnioAnterior->idSaldo,
                                "valor" => $saldoCesantiasAnioAnterior->valor
                            ]),
                            "valor" => $saldoCesantiasAnioAnterior->valor + $arrValorxConcepto[84]['valor'],
                            "tipoGen" => "conceptoFijo"
                        );
                        unset($arrValorxConcepto[84]);
                    }
                    elseif(isset($arrValorxConceptoFueraNomina[84])){
                        $arrValorxConceptoFueraNomina[84] = array(
                            "naturaleza" => "5",
                            "unidad" => "UNIDAD",
                            "cantidad"=> 0,
                            "arrNovedades"=> array(),
                            "arrSaldo"=> array([
                                "idSaldo" => $saldoCesantiasAnioAnterior->idSaldo,
                                "valor" => $saldoCesantiasAnioAnterior->valor
                            ]),
                            "valor" => $saldoCesantiasAnioAnterior->valor + $arrValorxConceptoFueraNomina[84]["valor"],
                            "tipoGen" => "conceptoFijo"
                        );
                    }
                    else{
                        $arrValorxConceptoFueraNomina[84] = array(
                            "naturaleza" => "5",
                            "unidad" => "UNIDAD",
                            "cantidad"=> 0,
                            "arrNovedades"=> array(),
                            "arrSaldo"=> array([
                                "idSaldo" => $saldoCesantiasAnioAnterior->idSaldo,
                                "valor" => $saldoCesantiasAnioAnterior->valor
                            ]),
                            "valor" => $saldoCesantiasAnioAnterior->valor,
                            "tipoGen" => "conceptoFijo"
                        );
                    }
                    
                }
            }

            //dd($saldoCesantiasAnioAnterior, $arrValorxConceptoFueraNomina);

        
            $salarioMaximo = ($salarioMinimoDia * 30) * 25;
            $ibcOtros = $arrBoucherPago["ibc_arl"];
            $ibcCCF = $arrBoucherPago["ibc_arl"];
            
            if($empleado->tipoRegimen != "Salario Integral" && ($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23")){
                
                //Verificar si tiene vacaciones en otra liquidacion del mismo periodo
                $itemsBoucherMismoPeriodo = DB::table("item_boucher_pago", "ibp")
                ->selectRaw("ibp.*")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("ln.fechaInicio",">=",date("Y-m-1",strtotime($fechaInicio)))
                ->where("ln.fechaFin","<=",date("Y-m-t",strtotime($fechaFin)))
                ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                ->where("ln.fkEstado","=","5")//Terminada
                ->whereIn("ibp.fkConcepto",["30","28"])
                ->get();



                foreach($itemsBoucherMismoPeriodo as $itemBoucherMismoPeriodo){
                    $ibcOtros = $ibcOtros + $itemBoucherMismoPeriodo->valor;
                    $ibcCCF =  $ibcCCF + $itemBoucherMismoPeriodo->valor;
                }
                //dd($itemsBoucherMismoPeriodo);







                if(isset($arrValorxConcepto[30]) && isset($arrValorxConcepto[28])){
                    $ibcOtros = $arrBoucherPago["ibc_arl"];
                    $ibcCCF = $arrBoucherPago["ibc_arl"];
                    
                    if($arrValorxConcepto[28]['valor']>0){
                        $ibcOtros = $ibcOtros + $arrValorxConcepto[28]['valor'];
                        $ibcCCF =  $ibcCCF + $arrValorxConcepto[28]['valor'];
                    }
                    if( $arrValorxConcepto[30]['valor']>0){
                        $ibcOtros = $ibcOtros +  $arrValorxConcepto[30]['valor'];
                        $ibcCCF =  $ibcCCF +  $arrValorxConcepto[30]['valor'];
                    }
                }
                else if(isset($arrValorxConcepto[30])){
                    $ibcOtros = $arrBoucherPago["ibc_arl"];
                    $ibcCCF = $arrBoucherPago["ibc_arl"];                
                    if( $arrValorxConcepto[30]['valor']>0){
                        $ibcOtros = $ibcOtros +  $arrValorxConcepto[30]['valor'];
                        $ibcCCF =  $ibcCCF +  $arrValorxConcepto[30]['valor'];
                    }
                }
                else if(isset($arrValorxConcepto[28])){
                    $ibcOtros = $arrBoucherPago["ibc_arl"];
                    $ibcCCF = $arrBoucherPago["ibc_arl"];                
                    if( $arrValorxConcepto[28]['valor']>0){
                        $ibcOtros = $ibcOtros +  $arrValorxConcepto[28]['valor'];
                        $ibcCCF =  $ibcCCF +  $arrValorxConcepto[28]['valor'];
                    }
                }            
                
               /* if($ibcOtros > $salarioMaximo){
                    $ibcOtros = $salarioMaximo;
                }
                if($ibcCCF > $salarioMaximo){
                    $ibcCCF = $salarioMaximo;
                }
                */
                $arrBoucherPago["ibc_otros"] = round($ibcOtros);
                
                $arrBoucherPago["ibc_ccf"] = round($ibcCCF);
                if($empleado->fkTipoCotizante == 51){
                    $arrBoucherPago["ibc_ccf"] = $arrBoucherPago["ibc_afp"];    
                }
                //INICIO CALCULAR IBC TIPO COTIZANTE 51
                if($empleado->fkTipoCotizante == 51){
                    $arrBoucherPago["ibc_eps"] = 0;
                }
                //FIN CALCULAR IBC TIPO COTIZANTE 51

                //Calculo CCF
                $valorCCFEmpleador = $arrBoucherPago["ibc_ccf"] * $varParafiscales[53];
                $valorCCFEmpleador = round($valorCCFEmpleador);
                $arrParafiscales["ccf"] = $valorCCFEmpleador;
            }
            else{
                $ibcOtros = $arrBoucherPago["ibc_arl"] + ((isset($arrValorxConcepto[30]) && $arrValorxConcepto[30]['valor']>0) ? $arrValorxConcepto[30]['valor'] : 0);            
                if($empleado->fkTipoCotizante == 51){
                    $arrBoucherPago["ibc_eps"] = 0;
                }
            }
            

            $calculoOtrosExcento = $arrBoucherPago["ibc_otros"];
            if($arrBoucherPago["ibc_eps"]<0){
                $salarioMes = 0;
                $conceptosFijosEmpl = DB::table("conceptofijo", "cf")
                ->select(["cf.valor","cf.fechaInicio","cf.fechaFin", "cf.fkConcepto","cf.unidad", "c.*"])
                ->join("concepto AS c", "cf.fkConcepto","=","c.idconcepto")
                ->where("cf.fkEmpleado", "=", $empleado->idempleado)  
                ->where("cf.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("cf.fkEstado", "=", "1")
                ->get();
                
                foreach($conceptosFijosEmpl as $conceptoFijoEmpl){
                    if($conceptoFijoEmpl->fkConcepto=="1"){
                        $salarioMes = $conceptoFijoEmpl->valor; 
                    }
                }
                $calculoOtrosExcento = intval($salarioMes / 30);
            }
                    
            if(isset($arrValorxConcepto[30]) && $arrValorxConcepto[30]['valor']>0){
                $calculoOtrosExcento = $calculoOtrosExcento - $arrValorxConcepto[30]['valor'];
            }
            if($empleado->fkTipoCotizante == 51){
                $calculoOtrosExcento = 0;           
            }

            if( $empresa->exento == "0" || ($calculoOtrosExcento > ($varParafiscales[56] *($valorSalarioMinimo/30)*$periodoPago)) ||
            ($empresa->pagoParafiscales == "1" && ($calculoOtrosExcento*100/70) > ($varParafiscales[56] *($valorSalarioMinimo/30)*$periodoPago) && $empleado->tipoRegimen == "Salario Integral")
            
            ){            
                //Calculo ICBF
                $valorICBFEmpleador = $arrBoucherPago["ibc_otros"] * $varParafiscales[54];
                $valorICBFEmpleador = round($valorICBFEmpleador);
                $arrParafiscales["icbf"] = $valorICBFEmpleador;
        
                //Calculo SENA
                $valorSenaEmpleador = $arrBoucherPago["ibc_otros"] * $varParafiscales[55];            
                $valorSenaEmpleador = round($valorSenaEmpleador);
                $arrParafiscales["sena"] = $valorSenaEmpleador;
                if($empleado->fkTipoCotizante == "12" || $empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23"){
                    $valorEpsEmpleador = $arrBoucherPago["ibc_eps"] * ($varParafiscales[50] + $varParafiscales[49]);
                }
                else{
                    $valorEpsEmpleador = $arrBoucherPago["ibc_eps"] * $varParafiscales[50];
                }
                
                $valorEpsEmpleador = round($valorEpsEmpleador);
        
                
                $arrParafiscales["eps"] = $valorEpsEmpleador;
                

            }
            else{
                $arrParafiscales["icbf"] = 0;
                $arrParafiscales["sena"] = 0;
                if($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23"){
                    $arrParafiscales["eps"] = 0;
                }
                
                $arrBoucherPago["ibc_otros"] = 0;
            }   
            
        
            $banderaVst = false;
            if(isset($arrValorxConcepto[32])){
                $banderaVst = true;
                unset($arrValorxConcepto[32]);            
            }
            

            //dd($arrBoucherPago);
            
            
            foreach($arrValorxConcepto as $idConcepto => $arrConcepto){
                if(round($arrConcepto["valor"]) == 0 && $arrConcepto["cantidad"] == 0){
                    if(!isset($arrConcepto["valorAus"]) || $arrConcepto["valorAus"] == 0){
                        if(!isset($arrConcepto['arrNovedades']) || empty($arrConcepto['arrNovedades'])){
                            unset($arrValorxConcepto[$idConcepto]);
                        }                        
                    }
                }
                
                $valorNeto = $valorNeto + round($arrConcepto["valor"]);
            }
            
            



            
            
            $salarioMes = 0;
            foreach($conceptosFijosEmpl as $conceptoFijoEmpl){
                
                if($conceptoFijoEmpl->fkConcepto=="1" && isset($arrValorxConcepto[1])){
                    if(strtotime($fechaInicio) < strtotime($conceptoFijoEmpl->fechaInicio)){
                        $salarioMes = (($arrValorxConcepto[1]["valor"] ?? 0) / ($arrValorxConcepto[1]["cantidad"] > 0 ? $arrValorxConcepto[1]["cantidad"] : 1)) * 30;           
                    }else{
                        $salarioMes = $conceptoFijoEmpl->valor; 
                    }
                    break;
                }
                if($conceptoFijoEmpl->fkConcepto=="2" && isset($arrValorxConcepto[2])){
                    if(strtotime($fechaInicio) < strtotime($conceptoFijoEmpl->fechaInicio)){
                        $salarioMes = (($arrValorxConcepto[2]["valor"] ?? 0) / ($arrValorxConcepto[2]["cantidad"] != 0 ? ($arrValorxConcepto[2]["cantidad"] ?? 1) : 1)) * 30;           
                    }else{
                        $salarioMes = $conceptoFijoEmpl->valor; 
                    }
                    break;
                }
            }
            if($salarioMes > 0){
                $salarioMes = round(($salarioMes / 30)*$periodoPagoSinVac);
            }
            else{
                $salarioMes = 0;
            }
            
            if($empleado->fkTipoCotizante == 51){
                $salarioMes = ($arrValorxConcepto[1]["valor"] ?? 0);           
            }
            if($diasNoTrabajados == $periodo){
                foreach($conceptosFijosEmpl as $conceptoFijoEmpl){                
                    if($conceptoFijoEmpl->fkConcepto=="1"){
                        $salarioMes = ($conceptoFijoEmpl->valor / 30) * $periodo; 
                        break;
                    }
                    if($conceptoFijoEmpl->fkConcepto=="2" && isset($arrValorxConcepto[2])){
                        $salarioMes = ($conceptoFijoEmpl->valor / 30) * $periodo; 
                        break;
                    }
                }
            }
            
            
            $periodoPago = $periodoPagoSinVac;
            if($liquidacionNomina->fkTipoLiquidacion == "3"){
                $periodoPago -= $diasEnMismoPeriodo;
            }
            if($periodoPago <= 0){
                $salarioMes = 0;
            }
            
            $arrBoucherPago["salarioPeriodoPago"] = $salarioMes;
            if($banderaVst){
                $arrBoucherPago["vstNoSalarial"] = "1";
            }
            else{
                $arrBoucherPago["vstNoSalarial"] = "0";
            }
        
            $arrBoucherPago["fkEmpleado"] = $empleado->idempleado;
            $arrBoucherPago["fkLiquidacion"] = $idLiquidacionNomina;
            $arrBoucherPago["periodoPago"] = $periodoPago;
            if(isset($numeroHoras) && isset($numeroDias)){
                $arrBoucherPago["horasPeriodo"] = $numeroHoras*$numeroDias;
                $arrBoucherPago["periodoPago"] = $numeroDias;
            }

            $arrBoucherPago["diasTrabajados"] = $periodoGen;
            $arrBoucherPago["diasIncapacidad"] = $diasNoTrabajados;
            $arrBoucherPago["diasInjustificados"] = $diasNoTrabajadosInjustificados;
            $arrBoucherPago["netoPagar"] = $valorNeto;
            
            $arrBoucherPago["fkPeriodoActivo"] = $periodoActivoReintegro->idPeriodo;
            
            
            if($idBoucherPago!=null){        
                DB::table('boucherpago')->where("idBoucherPago","=",$idBoucherPago)->update($arrBoucherPago);
            }
            else{
                $idBoucherPago = DB::table('boucherpago')->insertGetId($arrBoucherPago, "idBoucherPago");
            }
            
                       

            if(sizeof($arrayRetencion)>0){
                
                $retencionfuente = DB::table('retencionfuente')
                ->where("fkBoucherPago","=",$idBoucherPago)
                ->where("tipoRetencion","=","NORMAL")            
                ->get();
                
                if(sizeof($retencionfuente)>0){
                    DB::table('retencionfuente')->where("idRetencionFuente", "=",$retencionfuente[0]->idRetencionFuente)->update($arrayRetencion);
                }
                else{
                    $arrayRetencion["fkBoucherPago"] = $idBoucherPago;
                    DB::table('retencionfuente')->insert($arrayRetencion);
                }
                
            }
            else{
                $retencionfuente = DB::table('retencionfuente')
                ->where("fkBoucherPago","=",$idBoucherPago)
                ->where("tipoRetencion","=","NORMAL")            
                ->get();
                if(sizeof($retencionfuente)>0){
                    DB::table('retencionfuente')
                    ->where("fkBoucherPago","=",$idBoucherPago)
                    ->where("tipoRetencion","=","NORMAL")        
                    ->delete();
                }
                
            }
            if(sizeof($arrayRetencionInd)>0){
                
                $retencionfuente = DB::table('retencionfuente')
                ->where("fkBoucherPago","=",$idBoucherPago)
                ->where("tipoRetencion","=","INDEMNIZACION")            
                ->get();
                
                if(sizeof($retencionfuente)>0){
                    DB::table('retencionfuente')->where("idRetencionFuente", "=",$retencionfuente[0]->idRetencionFuente)->update($arrayRetencionInd);
                }
                else{
                    $arrayRetencionInd["fkBoucherPago"] = $idBoucherPago;
                    DB::table('retencionfuente')->insert($arrayRetencionInd);
                }
                
            }
            else{
                $retencionfuente = DB::table('retencionfuente')
                ->where("fkBoucherPago","=",$idBoucherPago)
                ->where("tipoRetencion","=","INDEMNIZACION")            
                ->get();
                if(sizeof($retencionfuente)>0){
                    DB::table('retencionfuente')
                    ->where("fkBoucherPago","=",$idBoucherPago)
                    ->where("tipoRetencion","=","INDEMNIZACION")        
                    ->delete();
                }
                
            }
            
            if(sizeof($arrayRetencionPri)>0){
                
                $retencionfuente = DB::table('retencionfuente')
                ->where("fkBoucherPago","=",$idBoucherPago)
                ->where("tipoRetencion","=","PRIMA")            
                ->get();
                //dd($arrayRetencionPri, $idBoucherPago, $retencionfuente);
                if(sizeof($retencionfuente)>0){
                    DB::table('retencionfuente')->where("idRetencionFuente", "=",$retencionfuente[0]->idRetencionFuente)->update($arrayRetencionPri);
                }
                else{
                    $arrayRetencionPri["fkBoucherPago"] = $idBoucherPago;
                    DB::table('retencionfuente')->insert($arrayRetencionPri);
                }
                
            }
            else{
                $retencionfuente = DB::table('retencionfuente')
                ->where("fkBoucherPago","=",$idBoucherPago)
                ->where("tipoRetencion","=","PRIMA")            
                ->get();
                if(sizeof($retencionfuente)>0){
                    DB::table('retencionfuente')
                    ->where("fkBoucherPago","=",$idBoucherPago)
                    ->where("tipoRetencion","=","PRIMA")        
                    ->delete();
                }
                
            }
            
            $parafiscales = DB::table('parafiscales')->where("fkBoucherPago","=",$idBoucherPago)->get();
            if(sizeof($parafiscales)>0){
                DB::table('parafiscales')->where("idParafiscales", "=",$parafiscales[0]->idParafiscales)->update($arrParafiscales);
            }
            else{
                $arrParafiscales["fkBoucherPago"] = $idBoucherPago;
                DB::table('parafiscales')->insert($arrParafiscales);
            }
           
            DB::table('item_boucher_pago')->where("fkBoucherPago","=",$idBoucherPago)->delete();        
           
            foreach($arrValorxConcepto as $idConcepto => $arrConcepto){
                $mensaje = "";
                if(isset($arrComoCalcula[$idConcepto])){
                    foreach($arrComoCalcula[$idConcepto] as $comoCalcula){
                        $mensaje.= $comoCalcula."<br>";
                    }
                }
                
                
                $arrInsertItemBoucher = array(
                    "fkBoucherPago" => $idBoucherPago, 
                    "fkConcepto" => $idConcepto, 
                    "cantidad" => $arrConcepto["cantidad"],
                    "tipoUnidad" => $arrConcepto["unidad"],
                    "valor" => $arrConcepto["valor"],
                    "tipo" => $arrConcepto["tipoGen"],
                    "comoCalcula" => $mensaje
                );


                if($arrConcepto["naturaleza"]=="1"){
                    $arrInsertItemBoucher["pago"] = $arrConcepto["valor"];
                }
                else{
                    $arrInsertItemBoucher["descuento"] = $arrConcepto["valor"]*-1;
                }

                if(isset($arrConcepto["base"])){
                    $arrInsertItemBoucher["base"] = $arrConcepto["base"];
                }

                if(isset($arrConcepto["fechaInicio"])){
                    $arrInsertItemBoucher["fechaInicio"] = $arrConcepto["fechaInicio"];
                }
                if(isset($arrConcepto["fechaFin"])){
                    $arrInsertItemBoucher["fechaFin"] = $arrConcepto["fechaFin"];
                }

                
                if(isset($arrConcepto["porcentaje"])){
                    $arrInsertItemBoucher["porcentaje"] = ($porcentajeDescuento*100);
                }
                

                

                if($arrConcepto["tipoGen"]=="novedadAus"){
                    $arrInsertItemBoucher["descuento"] = 0;
                }

                $idItemBoucherPago  = DB::table('item_boucher_pago')->insertGetId($arrInsertItemBoucher, "idItemBoucherPago");


                foreach($arrConcepto["arrNovedades"] as $datosNovedad){                
                    DB::table('item_boucher_pago_novedad')->insert(
                        [
                            "fkItemBoucher" => $idItemBoucherPago,
                            "fkNovedad"=> $datosNovedad['idNovedad'],
                            "valor"=> $datosNovedad['valor'],
                            "cantidad"=> ($datosNovedad['cantidad'] ?? 0),
                            "valor_ss"=> ($datosNovedad['valor_ss'] ?? 0),
                            "parcial" => (isset($datosNovedad['parcial']) ? $datosNovedad['parcial'] : 0)
                        ]
                    );
                }  
                if(isset($arrConcepto["arrCambio"])){
                    foreach($arrConcepto["arrCambio"] as $datosNovedad){    
                        DB::table('item_boucher_pago_cambio_tipo_cot')->insert(
                            [
                                "fkItemBoucherPago" => $idItemBoucherPago,
                                "fkCambioTipoCotizante"=> $datosNovedad['idCambioTipoCotizante'],
                                "valor"=> $datosNovedad['valor']                            
                            ]
                        );
                    }               
                }

                if(isset($arrConcepto["arrSaldo"])){
                    foreach($arrConcepto["arrSaldo"] as $datosSaldo){
                        DB::table('item_boucher_pago_saldo')->insert(
                            [
                                "fkItemBoucher" => $idItemBoucherPago,
                                "fkSaldo"=> $datosSaldo['idSaldo'],
                                "valor"=> $datosSaldo['valor']
                            ]
                        );
                    }
                }
                if(isset($arrConcepto["arrPrestamo"])){
                    
                    foreach($arrConcepto["arrPrestamo"] as $datosPrestamo){
                        DB::table('item_boucher_pago_prestamo')->insert(
                            [
                                "fkItemBoucher" => $idItemBoucherPago,
                                "fkPrestamo"=> $datosPrestamo['idPrestamo'],
                                "valor"=> $datosPrestamo['valor']
                            ]
                        );
                    }
                }


                
            }

            DB::table('item_boucher_pago_fuera_nomina')->where("fkBoucherPago","=",$idBoucherPago)->delete();        
            
            foreach($arrValorxConceptoFueraNomina as $idConcepto => $arrConceptoFueraNomina){
                
                $arrInsertItemBoucherFueraNomina = array(
                    "fkBoucherPago" => $idBoucherPago, 
                    "fkConcepto" => $idConcepto, 
                    "cantidad" => $arrConceptoFueraNomina["cantidad"],
                    "tipoUnidad" => $arrConceptoFueraNomina["unidad"],
                    "valor" => $arrConceptoFueraNomina["valor"],
                    "tipo" => $arrConceptoFueraNomina["tipoGen"]
                );
                if($arrConceptoFueraNomina["naturaleza"]=="5"){
                    $arrInsertItemBoucherNoSalarial["pago"] = $arrConceptoFueraNomina["valor"];
                }
                else{
                    $arrInsertItemBoucherNoSalarial["descuento"] = $arrConceptoFueraNomina["valor"]*-1;
                }

                
                $idItemBoucherPagoFueraNomina = DB::table('item_boucher_pago_fuera_nomina')->insertGetId($arrInsertItemBoucherFueraNomina, "idItemBoucherPagoFueraNomina");          
                
                if(isset($arrConceptoFueraNomina["arrSaldo"])){
                    foreach($arrConceptoFueraNomina["arrSaldo"] as $datosSaldo){
                        DB::table('item_boucher_pago_fuera_nomina_saldo')->insert(
                            [
                                "fkItemBoucherFueraNomina" => $idItemBoucherPagoFueraNomina,
                                "fkSaldo"=> $datosSaldo['idSaldo'],
                                "valor"=> $datosSaldo['valor']
                            ]
                        );
                    }
                }
            }

            $provisiones = new ProvisionesController();
            $provisiones->calcularProvisionesEmpleado($empleado->idempleado, $periodoActivoReintegro->idPeriodo, $idLiquidacionNomina, $idBoucherPago,  $numeroHoras, $numeroDias);

        }catch (Exception $e) {
            return $e->getMessage()." en la linea ".$e->getLine();
        }

        return true;
    }
    public function nominasLiquidadas(Request $req){
        $dataUsu = UsuarioController::dataAdminLogueado();
        $liquidaciones = DB::table("liquidacionnomina", "ln")
        ->select(["ln.fkTipoLiquidacion","ln.idLiquidacionNomina", "ln.fechaLiquida", "e.razonSocial", "tl.nombre as tipoLiquidacion", "est.nombre as estado", "n.nombre as nomNombre"])
        ->join("nomina AS n","ln.fkNomina", "=", "n.idnomina")
        ->join("empresa AS e","n.fkEmpresa","=", "e.idempresa")
        ->join("tipoliquidacion AS tl","ln.fkTipoLiquidacion","=", "tl.idTipoLiquidacion")        
        ->join("estado AS est","ln.fkEstado","=", "est.idestado")
        ->where("ln.fkEstado", "=", "5")
        ->orderBy("ln.fkNomina","desc")
        ->orderBy("ln.fechaLiquida","desc");

        if(isset($req->fechaInicio)){
            $liquidaciones = $liquidaciones->where("ln.fechaLiquida",">=",$req->fechaInicio);
        }
        
        if(isset($req->fechaFin)){
            $fechaBusquedaFin = $req->fechaFin;
            if(substr($fechaBusquedaFin,8,2) == "30" && substr(date("Y-m-t",strtotime($fechaBusquedaFin)),8,2)=="31"){
                $fechaBusquedaFin = date("Y-m-t",strtotime($fechaBusquedaFin));
            }
            $liquidaciones = $liquidaciones->where("ln.fechaLiquida","<=",$fechaBusquedaFin);
        }

        if(isset($dataUsu) && $dataUsu->fkRol == 2){
            $liquidaciones = $liquidaciones->whereIn("n.fkEmpresa", $dataUsu->empresaUsuario);
        }

        if(isset($req->nomina)){
            $liquidaciones = $liquidaciones->where("ln.fkNomina","=",$req->nomina);
        }
        if(isset($req->tipoLiquidacion)){
            $liquidaciones = $liquidaciones->where("ln.fkTipoLiquidacion","=",$req->tipoLiquidacion);
        }

        $liquidaciones = $liquidaciones->paginate(500);

        
        $nominas = DB::table("nomina");
        if(isset($dataUsu) && $dataUsu->fkRol == 2){            
            $nominas = $nominas->whereIn("fkEmpresa", $dataUsu->empresaUsuario);
        }
        $nominas = $nominas->orderBy("nombre")->get();

        $tipoLiquidaciones = DB::table("tipoliquidacion")->orderBy("nombre")->get();

        
        $arrConsulta = array("fechaInicio"=> $req->fechaInicio, "fechaFin"=> $req->fechaFin, "nomina"=> $req->nomina, "tipoLiquidacion"=> $req->tipoLiquidacion);

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu 'Liquidaciones terminadas'");

        return view('/nomina.liquidada.listaNominas',[
            "liquidaciones" => $liquidaciones,
            "arrConsulta" => $arrConsulta,
            "req" => $req,
            "nominas" => $nominas,
            "tipoLiquidaciones" => $tipoLiquidaciones,
            "dataUsu" => $dataUsu
        ]);
    }

    public function documentoRetencion($idLiquidacion){


        $retenciones = DB::table("retencionfuente", "rf")
        ->select(["rf.*","dp.numeroIdentificacion","dp.primerNombre","dp.segundoNombre","dp.primerApellido","dp.segundoApellido"])
        ->join("boucherpago as bp","bp.idBoucherPago","=","rf.fkBoucherPago")
        ->join("empleado as e","e.idempleado","=","bp.fkEmpleado")
        ->join("datospersonales as dp","dp.idDatosPersonales","=","e.fkDatosPersonales")
        ->where("bp.fkLiquidacion","=",$idLiquidacion)
        //->where("rf.impuestoValor",">",0)
        ->get();
        $arrDatos = array();
        $arrDatos[0] = array(
            "ID",
            "NOMBRE",
            "SALARIO",
            "OTROS_INGRESOS",
            "TOTAL_INGRESOS",
            "",
            "EPS",
            "AFP",
            "FPS",
            "TOTAL",
            "",
            "RENTA LIQUIDA",
            "",
            "INT",
            "MP",
            "DEP",
            "VOLUN",
            "TOTAL",
            "",
            "BASE NETA",
            "",
            "EXCENTO",
            "",
            "BENEFICIOS_TOTAL",
            "BENEFICIOS_TOPE",
            "",
            "BASE_GRAVABLE",
            "UVT",
            "",
            "RETENCION",
            "RETENCION SIN APORTES",
            "RETENCION CONTINGENTE"
        );//Diferencial entre retenciones

        foreach($retenciones as $retencion){
            $deducciones = $retencion->interesesVivienda + $retencion->medicinaPrepagada + $retencion->dependiente + $retencion->aporteVoluntario + $retencion->AFC;
            $rentaLiquida = $retencion->ingreso - $retencion->seguridadSocial;


            $baseGravable  = $rentaLiquida - $retencion->totalBeneficiosTributarios;

            $arrDatosGen = array(
                $retencion->numeroIdentificacion,
                $retencion->primerApellido." ".$retencion->segundoApellido." ".$retencion->primerNombre." ".$retencion->segundoNombre,
                $retencion->salario,
                $retencion->ingreso - $retencion->salario,
                $retencion->ingreso,
                "",
                $retencion->EPS,
                $retencion->AFP,
                $retencion->FPS,
                $retencion->seguridadSocial,
                "",
                $rentaLiquida,
                "",
                $retencion->interesesVivienda,
                $retencion->medicinaPrepagada,
                $retencion->dependiente,
                $retencion->aporteVoluntario + $retencion->AFC,
                $deducciones,
                "",
                $rentaLiquida - $deducciones,
                "",
                $retencion->exenta,
                "",
                $retencion->totalBeneficiosTributarios,
                $retencion->topeBeneficios,
                "",
                $baseGravable,
                $retencion->baseGravableUVTS,
                "",
                $retencion->impuestoValor,
                $retencion->impuestoValorSinAportes,
                $retencion->retencionContingente,
            );
            array_push($arrDatos, $arrDatosGen);
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename=Informe_Retencion_Fuente_'.$idLiquidacion.'.csv');

        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->setDelimiter(';');
        $csv->setOutputBOM(Reader::BOM_UTF8);
        $csv->insertAll($arrDatos);
        $csv->output('Informe_Retencion_Fuente_'.$idLiquidacion.'.csv');


    }
    
    public function documentoSS($idLiquidacion){
        $arrDatos = array();
        $arrDatos[0] = array(
            "TIPO REGISTRO",
            "SECUENCIA",
            "TIPO DOCUMENTO",
            "IDENTIFICACION",
            "TIPO COTIZANTE",
            "SUBTIPO COTIZANTE",
            "EXTRANJERO NO COTIZA PENSION",
            "RESIDENTE EXTERIOR",
            "CODIGO DEPARTAMENTO",
            "CODIGO MUNICIPIO",
            "PRIMER APELLIDO",
            "SEGUNDO APELLIDO",
            "PRIMER NOMBRE",
            "SEGUNDO NOMBRE",
            "ING",
            "RET",
            "TDE",
            "TAE",
            "TDP",
            "TAP",
            "VSP",
            "VTE",
            "VST",
            "SLN",
            "IGE",
            "LMA",
            "VAC",
            "AVP",
            "VCT",
            "IRP",
            "CODIGO PENSION",
            "NOMBRE ADMINISTRADORA PENSION",
            "CODIGO PENSION TRASLADO",
            "NOMBRE PENSION TRASLADO",
            "CODIGO SALUD",
            "NOMBRE ADMINISTRADORA SALUD",
            "CODIGO SALUD TRASLADO",
            "NOMBRE SALUD TRASLADO",
            "CODIGO CCF",
            "NOMBRE ADMINISTRADORA CCF",
            "DIAS PENSION",
            "DIAS SALUD",
            "DIAS RIESGOS",
            "DIAS CCF",
            "SALARIO BASICO",
            "TIPO SALARIO",
            "IBC PENSION",
            "IBC SALUD",
            "IBC RIESGOS",
            "IBC CCF",
            "IBC SENA e ICBF",
            "TARIFA AFP",
            "COTIZACION OBLIGATORIA PENSION",
            "APORTE VOLUNTARIO AFILIADO",
            "COTIZACION VOLUNTARIA APORTANTE",
            "TOTAL COTIZACION",
            "FSP SOLIDARIDAD",
            "FSP SUBSISTENCIA",
            "VALOR NO RETENIDO",
            "TARIFA EPS",
            "COTIZACION SALUD",
            "VALOR UPC",
            "NUMERO IGE",
            "VALOR IGE",
            "NUMERO LMA",
            "VALOR LMA",
            "TARIFA RIESGOS",
            "CENTRO TRABAJO",
            "COTIZACION RIESGOS",
            "TARIFA CCF",
            "VALOR CCF",
            "TARIFA SENA",
            "VALOR SENA",
            "TARIFA ICBF",
            "VALOR ICBF",
            "TARIFA ESAP",
            "VALOR ESAP",
            "TARIFA MINISTERIO",
            "VALOR MINISTERIO",
            "FECHA INGRESO",
            "FECHA RETIRO",
            "FECHA TRASLADO EPS",
            "FECHA TRASLADO AFP",
            "FECHA CAMBIO SALARIO",
            "FECHA CAMBIO CENTRO TRABAJO",
            "FECHA SANCION INICIO",
            "FECHA SANCION FIN",
            "FECHA INCAPACIDAD INICIO",
            "FECHA INCAPACIDAD FIN",
            "FECHA LICENCIA INICIO",
            "FECHA LICENCIA FIN",
            "FECHA VACACIONES INICIO",
            "FECHA VACACIONES FIN",
            "FECHA INCAPACIDAD RIESGOS LAB. INICIO",
            "FECHA  INCAPACIDAD RIESGOS LAB. FIN",
        );
        $empleados = DB::table("empleado", "e")
        ->select(["e.*","dp.*", "ti.nombre as tipoDocumento", "bp.*"])
        ->join("boucherpago as bp","bp.fkEmpleado","=","e.idempleado")
        ->join("datospersonales as dp","dp.idDatosPersonales","=","e.fkDatosPersonales")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","dp.fkTipoIdentificacion")
        ->where("bp.fkLiquidacion","=",$idLiquidacion)->get();

        $liquidacionNomina = DB::table('liquidacionnomina')->where("idLiquidacionNomina", "=", $idLiquidacion)->first();


        $arlEmpresa = DB::table("tercero", "t")
        ->select(["t.*"])
        ->join("empresa as e", "e.fkTercero_ARL", "=", "t.idTercero")
        ->join("nomina as n", "n.fkEmpresa", "=", "e.idempresa")
        ->join("liquidacionnomina as ln", "ln.fkNomina", "=", "n.idNomina")
        ->where("ln.idLiquidacionNomina", "=",$idLiquidacion)
        ->first();

        

        $incremental = 1;




        foreach($empleados as $empleado){
            $arrayFila = array();

            for ($i=0; $i <= 94; $i++) { 
                $arrayFila[$i] = "";
            }

            $arrayFila[0] = "02";
            $arrayFila[1] = $incremental;
            $arrayFila[2] = $empleado->tipoDocumento;
            $arrayFila[3] = $empleado->numeroIdentificacion;
            $arrayFila[4] = "01"; // Cuando deja de ser 01?
            $arrayFila[5] = "00"; // Cuando deja de ser 00?
            if($empleado->fkTipoIdentificacion == "4" || $empleado->fkTipoIdentificacion == "7"){
                $arrayFila[6] = "X";
            }
            if(substr($empleado->fkUbicacionResidencia,0,2) != "57"){
                $arrayFila[7] = "X";
            }
            $arrayFila[8] = substr("0".substr($empleado->fkUbicacionResidencia,2,2),-2);
            $arrayFila[9] = substr("00".substr($empleado->fkUbicacionResidencia,4),-3);
            $arrayFila[10] = $empleado->primerApellido;
            $arrayFila[11] = $empleado->segundoApellido;
            $arrayFila[12] = $empleado->primerNombre;
            $arrayFila[13] = $empleado->segundoNombre;
            

            if(strtotime($liquidacionNomina->fechaInicio) < strtotime($empleado->fechaIngreso)){
                $arrayFila[14] = "X";
            }
            
            $novedadesRetiro = DB::table("novedad","n")
                ->where("n.fkEmpleado","=", $empleado->idempleado)
                ->whereRaw("n.fkPeriodoActivo in(
                    SELECT p.idPeriodo from periodo as p where p.fkEmpleado = '".$empleado->idempleado."' and p.fkEstado = '1'
                )")
                ->whereIn("n.fkEstado",["7", "8"]) // Pagada o sin pagar-> no que este eliminada
                ->whereNotNull("n.fkRetiro")
                ->whereBetween("n.fechaRegistro",[$liquidacionNomina->fechaInicio, $liquidacionNomina->fechaFin])
                ->get();

            $fechaRetiro = "";
            foreach($novedadesRetiro as $retiro){
                $arrayFila[15] = "X";
                $retiroTabla = DB::table("retiro", "r")
                    ->where("idRetiro","=",$retiro->fkRetiro)
                    ->first();

                $fechaRetiro = $retiroTabla->fechaReal;
            }
            



            //TDE
            $fechaInicioParaMesAntes = date("Y-m-01", strtotime($liquidacionNomina->fechaInicio."  -1 month"));
            $fechaFinParaMesAntes = date("Y-m-t", strtotime($fechaInicioParaMesAntes));
            
            $cambioAfiliacionEps = DB::table("cambioafiliacion","ca")
                ->where("ca.fkEmpleado", "=", $empleado->idempleado)
                ->where("ca.fkTipoAfiliacionNueva", "=", "3") //3-Salud
                ->whereBetween("ca.fechaCambio", [$fechaInicioParaMesAntes, $fechaFinParaMesAntes])->get();
            $fechaCambioAfiliacionEPS = "";
            foreach($cambioAfiliacionEps as $cambioAfi){
                $arrayFila[16] = "X";
                $fechaCambioAfiliacionEPS = $cambioAfi->fechaCambio;
            }
            //TAE
            $cambioAfiliacionEps2 = DB::table("cambioafiliacion","ca")
                ->where("ca.fkEmpleado", "=", $empleado->idempleado)
                ->where("ca.fkTipoAfiliacionNueva", "=", "3") //3-Salud
                ->whereBetween("ca.fechaCambio", [$liquidacionNomina->fechaInicio, $liquidacionNomina->fechaFin])
                ->get();

            $fechaCambioAfiliacionEPS2 = "";
            foreach($cambioAfiliacionEps2 as $cambioAfi){
                $arrayFila[17] = "X";
                $fechaCambioAfiliacionEPS2 = $cambioAfi->fechaCambio;
            }

            //TDP
            $cambioAfiliacionPension = DB::table("cambioafiliacion","ca")
                ->join("tercero as t", "t.idTercero", "=", "ca.fkTerceroNuevo")
                ->where("ca.fkEmpleado", "=", $empleado->idempleado)
                ->where("ca.fkTipoAfiliacionNueva", "=", "4") //4-Pension
                ->whereBetween("ca.fechaCambio", [$fechaInicioParaMesAntes, $fechaFinParaMesAntes])->get();
            $fechaCambioAfiliacionAFP = "";
            foreach($cambioAfiliacionPension as $cambioAfi){
                $arrayFila[18] = "X";
                $fechaCambioAfiliacionAFP = $cambioAfi->fechaCambio;
            }

            //TAP
            $cambioAfiliacionPension2 = DB::table("cambioafiliacion","ca")
                ->where("ca.fkEmpleado", "=", $empleado->idempleado)
                ->where("ca.fkTipoAfiliacionNueva", "=", "4") //4-Pension
                ->whereBetween("ca.fechaCambio", [$liquidacionNomina->fechaInicio, $liquidacionNomina->fechaFin])
                ->get();

            $fechaCambioAfiliacionAFP2 = "";
            foreach($cambioAfiliacionPension2 as $cambioAfi){
                $arrayFila[19] = "X";
                $fechaCambioAfiliacionAFP2 = $cambioAfi->fechaCambio;
            }

            //VSP
            $cambioSalario = DB::table("cambiosalario","cs")
                ->where("cs.fkEmpleado", "=", $empleado->idempleado)
                ->whereBetween("cs.fechaCambio", [$liquidacionNomina->fechaInicio, $liquidacionNomina->fechaFin])
                ->get();

            if(sizeof($cambioSalario)>0){
                $arrayFila[20] = "X";                
            }
            
            //VTE
            $cambioCentroTrab = DB::table("cambiocentrotrabajo","cct")
                ->where("cct.fkEmpleado", "=", $empleado->idempleado)
                ->whereBetween("cct.fechaCambio", [$liquidacionNomina->fechaInicio, $liquidacionNomina->fechaFin])
                ->get();
            
            if(sizeof($cambioCentroTrab)>0){
                $arrayFila[21] = "X";
            }
            

            //VST
            $itemsBoucherPago = DB::table("item_boucher_pago", "ibp")
                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","<>","ibp.fkConcepto")
                ->where("ibp.fkBoucherPago","=",$empleado->idBoucherPago)
                ->where("ibp.pago",">","0")
                ->where("gcc.fkConcepto","=","10") //10 - CONCEPTOS QUE GENERAN VST	
                ->get();
            if(sizeof($itemsBoucherPago)>0){
                $arrayFila[22] = "X";
            }


            //SLN
            $novedadesSancion = DB::table("novedad","n")
                ->join("ausencia AS a","a.idAusencia", "=", "n.fkAusencia")
                ->where("a.cantidadDias",">=", "1")
                ->where("n.fkEmpleado","=", $empleado->idempleado)
                ->whereRaw("n.fkPeriodoActivo in(
                    SELECT p.idPeriodo from periodo as p where p.fkEmpleado = '".$empleado->idempleado."' and p.fkEstado = '1'
                )")
                ->whereIn("n.fkEstado",["7", "8"]) // Pagada o sin pagar-> no que este eliminada
                ->whereNotNull("n.fkAusencia")
                ->whereBetween("n.fechaRegistro",[$liquidacionNomina->fechaInicio, $liquidacionNomina->fechaFin])
                ->get();

            if(sizeof($novedadesSancion)>0){
                $arrayFila[23] = "X";
            }
            
            //IGE
            $novedadesIncapacidadNoLab = DB::table("novedad","n")
            ->join("incapacidad as i","i.idIncapacidad","=", "n.fkIncapacidad")
            ->where("i.fkTipoAfilicacion","=","3") //3- Salud
            ->whereNotIn("i.tipoIncapacidad",["Maternidad", "Paternidad"])
            ->where("n.fkEmpleado","=", $empleado->idempleado)
            ->whereRaw("n.fkPeriodoActivo in(
                SELECT p.idPeriodo from periodo as p where p.fkEmpleado = '".$empleado->idempleado."' and p.fkEstado = '1'
            )")
            ->whereIn("n.fkEstado",["7", "8"]) // Pagada o sin pagar-> no que este eliminada
            ->whereNotNull("n.fkIncapacidad")
            ->whereBetween("n.fechaRegistro",[$liquidacionNomina->fechaInicio, $liquidacionNomina->fechaFin])
            ->get();

            if(sizeof($novedadesIncapacidadNoLab)>0){
                $arrayFila[24] = "X";
            }

            //LMA
            $novedadesIncapacidadNoLaMat = DB::table("novedad","n")
            ->join("incapacidad as i","i.idIncapacidad","=", "n.fkIncapacidad")
            ->whereIn("i.tipoIncapacidad",["Maternidad", "Paternidad"])
            ->where("n.fkEmpleado","=", $empleado->idempleado)
            ->whereRaw("n.fkPeriodoActivo in(
                SELECT p.idPeriodo from periodo as p where p.fkEmpleado = '".$empleado->idempleado."' and p.fkEstado = '1'
            )")
            ->whereIn("n.fkEstado",["7", "8"]) // Pagada o sin pagar-> no que este eliminada
            ->whereNotNull("n.fkIncapacidad")
            ->whereBetween("n.fechaRegistro",[$liquidacionNomina->fechaInicio, $liquidacionNomina->fechaFin])
            ->get();

            if(sizeof($novedadesIncapacidadNoLaMat)>0){
                $arrayFila[25] = "X";
            }


            //VAC
            $novedadesVac = DB::table("novedad","n")
            ->join("vacaciones as v","v.idVacaciones","=", "n.fkVacaciones")
            ->where("n.fkEmpleado","=", $empleado->idempleado)
            ->whereRaw("n.fkPeriodoActivo in(
                SELECT p.idPeriodo from periodo as p where p.fkEmpleado = '".$empleado->idempleado."' and p.fkEstado = '1'
            )")
            ->whereIn("n.fkEstado",["7", "8"]) // Pagada o sin pagar-> no que este eliminada
            ->whereNotNull("n.fkVacaciones")
            ->whereBetween("n.fechaRegistro",[$liquidacionNomina->fechaInicio, $liquidacionNomina->fechaFin])
            ->get();

            if(sizeof($novedadesVac)>0){
                $arrayFila[26] = "X";
            }

            //AVP
            $itemsBoucherAVP = DB::table("item_boucher_pago", "ibp")
                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")
                ->where("ibp.fkBoucherPago","=",$empleado->idBoucherPago)
                ->where("gcc.fkGrupoConcepto","=","6") //6 - APORTE VOLUNTARIO PENSION	
                ->get();
            if(sizeof($itemsBoucherAVP)>0){
                $arrayFila[27] = "X";
            }

            //IRL
            $arrayFila[28] = "";

            $novedadesIncapacidadLab = DB::table("novedad","n")
            ->join("incapacidad as i","i.idIncapacidad","=", "n.fkIncapacidad")
            ->whereNull("i.fkTipoAfilicacion") // NULL - Accidente laboral
            ->whereNotIn("i.tipoIncapacidad",["Maternidad", "Paternidad"])
            ->where("n.fkEmpleado","=", $empleado->idempleado)
            ->whereRaw("n.fkPeriodoActivo in(
                SELECT p.idPeriodo from periodo as p where p.fkEmpleado = '".$empleado->idempleado."' and p.fkEstado = '1'
            )")
            ->whereIn("n.fkEstado",["7", "8"]) // Pagada o sin pagar-> no que este eliminada
            ->whereNotNull("n.fkIncapacidad")
            ->whereBetween("n.fechaRegistro",[$liquidacionNomina->fechaInicio, $liquidacionNomina->fechaFin])
            ->get();

            if(sizeof($novedadesIncapacidadLab)>0){
                $arrayFila[29] = "X";
            }

            $pension = DB::table("afiliacion","a")
            ->join("tercero as t", "t.idTercero", "=", "a.fkTercero")
            ->where("a.fkEmpleado", "=", $empleado->idempleado)
            ->where("a.fkTipoAfilicacion", "=", "4") // 4 - Tipo Afiliacion = Pension
            ->first();

            
            $arrayFila[30] = $pension->codigoTercero;
            $arrayFila[31] = $pension->razonSocial;
            foreach($cambioAfiliacionPension as $cambioAfi){
                $arrayFila[32] = $cambioAfi->codigoTercero;
                $arrayFila[33] = $cambioAfi->razonSocial;
            }
            
            $salud = DB::table("afiliacion","a")
            ->join("tercero as t", "t.idTercero", "=", "a.fkTercero")
            ->where("a.fkEmpleado", "=", $empleado->idempleado)
            ->where("a.fkTipoAfilicacion", "=", "3") // 3 - Tipo Afiliacion = Pension
            ->first();
            
            $arrayFila[34] = $salud->codigoTercero;
            $arrayFila[35] = $salud->razonSocial;
            foreach($cambioAfiliacionEps as $cambioAfi){
                $arrayFila[36] = $cambioAfi->codigoTercero;
                $arrayFila[37] = $cambioAfi->razonSocial;
            }

            $arrayFila[38] = $arlEmpresa->codigoTercero;
            $arrayFila[39] = $arlEmpresa->razonSocial;            

            $periodo_completo = $empleado->diasTrabajados + $empleado->diasIncapacidad + $empleado->diasInjustificados;
            
            $arrayFila[40] = $periodo_completo;
            $arrayFila[41] = $periodo_completo;
            $arrayFila[42] = $periodo_completo;
            $arrayFila[43] = $periodo_completo;

            //Salario
            $itemsBoucherSalario = DB::table("conceptofijo", "cf")
                ->whereIn("cf.fkConcepto",["1","2","53","54","154"])
                ->where("cf.fkEmpleado", "=", $empleado->idempleado)
                ->first();
            $arrayFila[44] = intval($itemsBoucherSalario->valor);



            //TIPO SALARIO
            $arrayFila[45] = "F";
            if($empleado->tipoRegimen=="Salario Integral"){
                $arrayFila[45] = "X";
            }

            $arrayFila[46] = $empleado->ibc_afp;
            $arrayFila[47] = $empleado->ibc_eps;
            $arrayFila[48] = $empleado->ibc_arl;
            $arrayFila[49] = $empleado->ibc_ccf;
            $arrayFila[50] = $empleado->ibc_otros;

            //TARIFA AFP
            if($empleado->esPensionado==0 && ($empleado->fkTipoCotizante != "12" && $empleado->fkTipoCotizante != "19" && $empleado->fkTipoCotizante != "23")){
                $varsPension = DB::table("variable", "v")->whereIn("v.idVariable",["51","52"])->get();
                $totalPorcentajePension = 0;
                foreach($varsPension as $varPension){
                    $totalPorcentajePension = $totalPorcentajePension + floatval($varPension->valor);
                }

                $arrayFila[51] = $totalPorcentajePension;
                
            }
            else{
                $arrayFila[51] = "0";   
            }
            if($empleado->fkTipoCotizante == "12" || $empleado->fkTipoCotizante == "19" || $empleado->fkTipoCotizante == "23"){
                $arrayFila[51] = "0";   
            }

            //COTIZACION OBLIGATORIA PENSION

            $itemsBoucherPension = DB::table("item_boucher_pago", "ibp")
            ->where("ibp.fkBoucherPago","=",$empleado->idBoucherPago)
            ->whereIn("ibp.fkConcepto",["19"])
            ->get();
            
            $parafiscales = DB::table("parafiscales","p")
            ->where("p.fkBoucherPago","=",$empleado->idBoucherPago)
            ->get();
            $cotizacionPension = 0;
            foreach($itemsBoucherPension as $itemBoucherPension){
                if( $itemBoucherPension->valor < 0){
                    $cotizacionPension = $cotizacionPension + ($itemBoucherPension->valor * -1);
                }
                else{
                    $cotizacionPension = $cotizacionPension + $itemBoucherPension->valor;
                }
                //$cotizacionPension = $cotizacionPension + $itemBoucherPension->valor;
            }

            foreach($parafiscales as $parafiscal){
                $cotizacionPension = $cotizacionPension + $parafiscal->afp;
            }

            $arrayFila[52] = $cotizacionPension;

            //APORTE VOLUNTARIO AFILIADO
            $aporteVoluntarioPension = 0;
            foreach($itemsBoucherAVP as $itemBoucherAVP){
                if( $itemBoucherAVP->valor < 0){
                    $aporteVoluntarioPension = $aporteVoluntarioPension + ($itemBoucherAVP->valor * -1);
                }
                else{
                    $aporteVoluntarioPension = $aporteVoluntarioPension + $itemBoucherAVP->valor;
                }
                //$aporteVoluntarioPension = $aporteVoluntarioPension + $itemBoucherAVP->valor;
            }

            $arrayFila[53] = $aporteVoluntarioPension;
            $arrayFila[54] = 0;
            $arrayFila[55] = $cotizacionPension + $aporteVoluntarioPension;

            //FSP SOLIDARIDAD	            
            $itemsBoucherFPS = DB::table("item_boucher_pago", "ibp")
            ->where("ibp.fkBoucherPago","=",$empleado->idBoucherPago)
            ->whereIn("ibp.fkConcepto",["33"])
            ->get();
            $totalFPS = 0;
            foreach($itemsBoucherFPS as $itemBoucherFPS){
                if( $itemBoucherFPS->valor < 0){
                    $totalFPS = $totalFPS + ($itemBoucherFPS->valor * -1);
                }
                else{
                    $totalFPS = $totalFPS + $itemBoucherFPS->valor;
                }
                
            }
            $arrayFila[56] = $totalFPS;

            //FSP SUBSISTENCIA	
            $arrayFila[57] = "0";
            //VALOR NO RETENIDO
            $arrayFila[58] = "0";
            //TARIFA EPS
            $varsEPS = DB::table("variable", "v")->whereIn("v.idVariable",["49"])->get();
            $totalPorcentajeEPS = 0;
            foreach($varsEPS as $varEPS){
                
                $totalPorcentajeEPS = $totalPorcentajeEPS + floatval($varEPS->valor);
            }
            $arrayFila[59] = $totalPorcentajeEPS;    
            //COTIZACION SALUD
            $itemsBoucherEPS = DB::table("item_boucher_pago", "ibp")
            ->where("ibp.fkBoucherPago","=",$empleado->idBoucherPago)
            ->whereIn("ibp.fkConcepto",["18"])
            ->get();
            $totalEPS = 0;
            foreach($itemsBoucherEPS as $itemBoucherEPS){

                if( $itemBoucherEPS->valor < 0){
                    $totalEPS = $totalEPS + ($itemBoucherEPS->valor * -1);
                }
                else{
                    $totalEPS = $totalEPS + $itemBoucherEPS->valor;
                }
                
            }
            $arrayFila[60] = $totalEPS;   
            //VALOR UPC	
            $arrayFila[61] = "0";   
            //NUMERO IGE	
            $arrayFila[62] = "";   
            //VALOR IGE
            $arrayFila[63] = "0";   
            //NUMERO LMA	
            $arrayFila[64] = "";   
            //VALOR LMA
            $arrayFila[65] = "0";   
            //TARIFA RIESGOS
            $nivelesArl = DB::table("nivel_arl","na")
            ->where("na.idnivel_arl","=",$empleado->fkNivelArl)
            ->first();
            $arrayFila[66] = $nivelesArl->porcentaje / 100;   

            $centroTrabajo = DB::table("centrotrabajo","ct")
            ->where("ct.idCentroTrabajo","=",$empleado->fkCentroTrabajo)
            ->first();
            $arrayFila[67] = $centroTrabajo->codigo;
            //COTIZACION RIESGOS
            $arrayFila[68] = "";
            //TARIFA CCF
            $varsCCF = DB::table("variable", "v")->whereIn("v.idVariable",["53"])->get();
            $totalPorcentajeCCF = 0;
            foreach($varsCCF as $varCCF){
                $totalPorcentajeCCF = $totalPorcentajeCCF + floatval($varCCF->valor);
            }
            $arrayFila[69] = $totalPorcentajeCCF;    
            //VALOR CCF
            $ccfFinal = 0;
            foreach($parafiscales as $parafiscal){
                $ccfFinal = $ccfFinal + $parafiscal->ccf;
            }

            $arrayFila[70] = $ccfFinal;

            //TARIFA SENA
            $varsSENA = DB::table("variable", "v")->whereIn("v.idVariable",["55"])->get();
            $totalPorcentajeSENA = 0;
            foreach($varsSENA as $varSENA){
                $totalPorcentajeSENA = $totalPorcentajeSENA + floatval($varSENA->valor);
            }
            if($empleado->ibc_otros==0){
                $totalPorcentajeSENA = 0;
            }
            $arrayFila[71] = $totalPorcentajeSENA;


            //VALOR SENA
            $SENAFinal = 0;
            foreach($parafiscales as $parafiscal){
                $SENAFinal = $SENAFinal + $parafiscal->sena;
            }

            $arrayFila[72] = $SENAFinal;

            //TARIFA ICBF
            $varsICBF = DB::table("variable", "v")->whereIn("v.idVariable",["54"])->get();
            $totalPorcentajeICBF = 0;
            foreach($varsICBF as $varICBF){
                $totalPorcentajeICBF = $totalPorcentajeICBF + floatval($varICBF->valor);
            }
            if($empleado->ibc_otros==0){
                $totalPorcentajeICBF = 0;
            }
            $arrayFila[73] = $totalPorcentajeICBF;


            //VALOR ICBF
            $ICBFFinal = 0;
            foreach($parafiscales as $parafiscal){
                $ICBFFinal = $ICBFFinal + $parafiscal->icbf;
            }

            $arrayFila[74] = $ICBFFinal;
            //TARIFA ESAP	
            $arrayFila[75] = "0";
            //VALOR ESAP
            $arrayFila[76] = "0";
            //TARIFA MINISTERIO
            $arrayFila[77] = "0";
            //VALOR MINISTERIO
            $arrayFila[78] = "0";
            
        

            //FECHA INGRESO
            if(strtotime($liquidacionNomina->fechaInicio) < strtotime($empleado->fechaIngreso)){
                $arrayFila[79] = $empleado->fechaIngreso;
            }
            //FECHA RETIRO
            $arrayFila[80] = $fechaRetiro;
            //FECHA TRASLADO EPS
            $arrayFila[81] = ($fechaCambioAfiliacionEPS == "" ? ($fechaCambioAfiliacionEPS2 == "" ? "" : $fechaCambioAfiliacionEPS2) : $fechaCambioAfiliacionEPS);
            //FECHA TRASLADO AFP
            $arrayFila[82] = ($fechaCambioAfiliacionAFP == "" ? ($fechaCambioAfiliacionAFP2 == "" ? "" : $fechaCambioAfiliacionAFP2) : $fechaCambioAfiliacionAFP);
            //FECHA CAMBIO SALARIO
            foreach($cambioSalario as $cambioSal){
                $arrayFila[83] = $cambioSal->fechaCambio;
            }
            //FECHA CAMBIO CENTRO TRABAJO
            foreach($cambioCentroTrab as $cambioCen){
                $arrayFila[84] = $cambioCen->fechaCambio;
            }
            $entrar = true;
            $contadorInterno = 0;


            while($entrar){
                $entrar = false;
                if(isset($novedadesSancion[$contadorInterno])){
                    $entrar = true;
                    $arrayFila[23] = "X";
                    $arrayFila[85] = date("Y-m-d",strtotime($novedadesSancion[$contadorInterno]->fechaInicio));
                    $arrayFila[86] = date("Y-m-d",strtotime($novedadesSancion[$contadorInterno]->fechaFin));

                }
                
                if(isset($novedadesIncapacidadNoLab[$contadorInterno])){
                    $entrar = true;
                    $arrayFila[24] = "X";
                    $arrayFila[87] = date("Y-m-d",strtotime($novedadesIncapacidadNoLab[$contadorInterno]->fechaRealI));
                    $arrayFila[88] = date("Y-m-d",strtotime($novedadesIncapacidadNoLab[$contadorInterno]->fechaRealF));

                }
                if(isset($novedadesIncapacidadNoLaMat[$contadorInterno])){
                    $entrar = true;
                    $arrayFila[25] = "X";
                    $arrayFila[89] = date("Y-m-d",strtotime($novedadesIncapacidadNoLaMat[$contadorInterno]->fechaRealI));
                    $arrayFila[90] = date("Y-m-d",strtotime($novedadesIncapacidadNoLaMat[$contadorInterno]->fechaRealF));
                }
                if(isset($novedadesVac[$contadorInterno])){
                    $entrar = true;
                    $arrayFila[26] = "X";
                    $arrayFila[91] = date("Y-m-d",strtotime($novedadesVac[$contadorInterno]->fechaInicio));
                    $arrayFila[92] = date("Y-m-d",strtotime($novedadesVac[$contadorInterno]->fechaFin));
                }
                if(isset($novedadesIncapacidadLab[$contadorInterno])){
                    $entrar = true;
                    $arrayFila[29] = "X";
                    $arrayFila[93] = date("Y-m-d",strtotime($novedadesIncapacidadLab[$contadorInterno]->fechaRealI));
                    $arrayFila[94] = date("Y-m-d",strtotime($novedadesIncapacidadLab[$contadorInterno]->fechaRealF));
                }
            
                
                if($entrar){
                    $arrayFila = $this->upperCaseAllArray($arrayFila);
                    array_push($arrDatos, $arrayFila);    
                    $incremental++;
                    $contadorInterno ++;    
                    $arrayFila[1] = $incremental;
                    $arrayFila[23] = "";
                    $arrayFila[24] = "";
                    $arrayFila[25] = "";
                    $arrayFila[26] = "";
                    $arrayFila[29] = "";
                    $arrayFila[14] = "";
                    $arrayFila[15] = "";
                    $arrayFila[16] = "";
                    $arrayFila[17] = "";
                    $arrayFila[18] = "";
                    $arrayFila[19] = "";
                    $arrayFila[20] = "";   
                    $arrayFila[21] = "";
                    $arrayFila[22] = "";
                    $arrayFila[79] = "";
                    $arrayFila[80] = "";
                    $arrayFila[81] = "";
                    $arrayFila[82] = "";
                    $arrayFila[83] = "";
                    $arrayFila[84] = "";
                    $arrayFila[85] = "";
                    $arrayFila[86] = "";
                    $arrayFila[87] = "";
                    $arrayFila[88] = "";
                    $arrayFila[89] = "";
                    $arrayFila[90] = "";
                    $arrayFila[91] = "";
                    $arrayFila[92] = "";
                    $arrayFila[93] = "";
                    $arrayFila[94] = "";
                }
            }

            if($contadorInterno==0){
                $incremental++;
                $arrayFila = $this->upperCaseAllArray($arrayFila);
                array_push($arrDatos, $arrayFila);    
            }
            
            
        }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename=Informe_ss_'.$idLiquidacion.'.csv');

        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->setDelimiter(';');
        $csv->insertAll($arrDatos);
        $csv->output('Informe_ss_'.$idLiquidacion.'.csv');

    }
    public function verDetalleProvision($idBoucherPago, $fkConcepto){
        $liquidacion = DB::table("liquidacionnomina","ln")
        ->select("ln.fechaLiquida","bp.fkPeriodoActivo")
        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")
        ->where("bp.idBoucherPago","=",$idBoucherPago)
        ->first();
        

        $provisiones = DB::table("provision","p")
        ->where("p.mes","=",date("m",strtotime($liquidacion->fechaLiquida)))
        ->where("p.anio","=",date("Y",strtotime($liquidacion->fechaLiquida)))
        ->where("p.fkPeriodoActivo","=",$liquidacion->fkPeriodoActivo)
        ->where("p.fkConcepto","=",$fkConcepto)->first();

        return view('/nomina.solicitudes.verDetalleProvision', [
            "provision" => $provisiones           
        ]);  
        

    }
    public function verDetalleVacacion($idItemBoucherPago){

        $itemBoucherPago = DB::table("item_boucher_pago","ibp")
        ->select("ln.*", "ibp.fkBoucherPago", "ibp.comoCalcula", "bp.fkPeriodoActivo")
        ->join("boucherpago as bp", "bp.idBoucherPago","=","ibp.fkBoucherPago")
        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina", "=","bp.fkLiquidacion")
        ->where("ibp.idItemBoucherPago","=",$idItemBoucherPago)->first();
        


        /*$vacaciones = DB::table("item_boucher_pago_novedad","ibpn")
        ->select("v.*")
        ->join("novedad as n", "n.idNovedad","=","ibpn.fkNovedad")
        ->join("vacaciones as v", "v.idVacaciones","=","n.fkVacaciones")
        ->where("ibpn.fkItemBoucher","=",$idItemBoucherPago)->first();*/

        $empleado = DB::table("empleado", "e")
        ->select("e.*","ccfijo.valor as valorSalario", "p.fechaInicio")
        ->join("boucherpago as bp", "bp.fkEmpleado","=","e.idempleado")
        ->leftJoin('conceptofijo as ccfijo', function ($join) {
            $join->on('ccfijo.fkEmpleado', '=', 'e.idempleado')
                ->on('ccfijo.fkPeriodoActivo', '=', 'bp.fkPeriodoActivo')
                ->whereIn('ccfijo.fkConcepto', ["1","2","53","54","154"]);
        })        
        ->join("periodo as p", "p.idPeriodo","=","bp.fkPeriodoActivo")
        ->where("idBoucherPago","=",$itemBoucherPago->fkBoucherPago)->first();


        $empleado->fechaIngreso = ($empleado->fechaInicio ?? $empleado->fechaIngreso);


        $fechaInicio = $empleado->fechaIngreso;
        $fechaFinGen = $itemBoucherPago->fechaFin;
        


        
        $entrar=true;
        $periodo = 1;

        //Dias trabajados en este periodo

        $periodoActivoReintegro = DB::table("periodo")
        ->where("idPeriodo", "=", $itemBoucherPago->fkPeriodoActivo)
        ->orderBy("idPeriodo","desc")->first();

        //Obtener la primera liquidacion de nomina de la persona 
        $primeraLiquidacion = DB::table("liquidacionnomina", "ln")
        ->selectRaw("min(ln.fechaInicio) as primeraFecha")
        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")       
        ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])            
        ->where("bp.fkEmpleado","=",$empleado->idempleado)
        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
        ->first();

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
        ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])         
        ->first();

        
        $diasTrabajados = $this->days_360($fechaInicio, $fechaFinGen);
        //$diasTrabajados = $diasAgregar + (isset($liquidacionesMesesAnterioresCompleta->periodPago) ? $liquidacionesMesesAnterioresCompleta->periodPago : 0);
        

        $novedadesLIC = DB::table("novedad","n")
        ->selectRaw("sum(a.cantidadDias) as suma")
        ->join("ausencia as a","a.idAusencia","=","n.fkAusencia")
        ->where("n.fkEmpleado","=",$empleado->idempleado)
        ->whereRaw("n.fkPeriodoActivo in(
            SELECT p.idPeriodo from periodo as p where p.fkEmpleado = '".$empleado->idempleado."' and p.fkEstado = '1'
        )")
        ->whereIn("n.fkEstado",["8"]) // Pagada -> no que este eliminada
        ->whereBetween("n.fechaRegistro",[$fechaInicio, $fechaFinGen])
        ->where("a.cantidadDias",">","0")
        ->whereNotNull("n.fkAusencia")
        ->first();

        $diasNeto = ($diasTrabajados - (isset($novedadesLIC->suma) ? $novedadesLIC->suma : 0));
        
        
        $diasVacGen = $diasNeto * 15 / 360;
        


        $novedadesVacacionGen = DB::table("novedad","n")
        ->selectRaw("sum(v.diasCompletos) as suma")
        ->join("vacaciones as v","v.idVacaciones","=","n.fkVacaciones")
        ->where("n.fkEmpleado","=",$empleado->idempleado)
        ->whereRaw("n.fkPeriodoActivo in(
            SELECT p.idPeriodo from periodo as p where p.fkEmpleado = '".$empleado->idempleado."' and p.fkEstado = '1'
        )")
        ->whereIn("n.fkEstado",["8","16"]) // Pagada -> no que este eliminada
        ->whereBetween("n.fechaRegistro",[$fechaInicio, $fechaFinGen])
        ->whereNotNull("n.fkVacaciones")
        ->first();
        
        
        $arrDatos = array();
        $rowspan = 1;
        while($entrar){
            $arrFila = array();
            $fechaFinInt = date("Y-m-d",strtotime($fechaInicio." +1 year"));
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
            ->whereRaw("n.fkPeriodoActivo in(
                SELECT p.idPeriodo from periodo as p where p.fkEmpleado = '".$empleado->idempleado."' and p.fkEstado = '1'
            )")
            ->whereIn("n.fkEstado",["8","16"]) // Pagada -> no que este eliminada
            ->whereBetween("n.fechaRegistro",[$fechaInicio, $fechaFinInt])
            ->whereNotNull("n.fkVacaciones")
            ->get();
            $diasTomadosPeriodo = 0;
            $arrFila['disfrute'] = array();
            foreach($novedadesVacacion as $novedadVacacion){
                $arrFilaInt = array();
                $arrFilaInt['diaIni'] = (isset($novedadVacacion->fechaInicio) ? date("d/m/Y",strtotime($novedadVacacion->fechaInicio)) : "");
                $arrFilaInt['diaFin'] = (isset($novedadVacacion->fechaFin) ? date("d/m/Y",strtotime($novedadVacacion->fechaFin)) : "");
                $arrFilaInt['diaTom'] = $novedadVacacion->diasCompletos;
                array_push($arrFila['disfrute'], $arrFilaInt);
                $diasTomadosPeriodo = $diasTomadosPeriodo + $novedadVacacion->diasCompletos;    
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
                $fechaInicio = $fechaFinInt;
                $periodo++;
                $rowspan++;

            }
        }



        return view('/nomina.solicitudes.verDetalleVacacion', [
            "arrDatos" => $arrDatos,
            "fechaFinGen" => $fechaFinGen,
            "empleado" => $empleado,
            "diasTrabajados" => $diasTrabajados,
            "diasLic" => (isset($novedadesLIC->suma) ? $novedadesLIC->suma : 0),
            "diasNeto" => $diasNeto,
            "diasVacGen" => round($diasVacGen,2),
            "novedadesVacacionGen" => $novedadesVacacionGen,
            "itemBoucherPago" => $itemBoucherPago
        ]);        
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
    public function cambiarConceptosFijosIndex(){
        $usu = UsuarioController::dataAdminLogueado();
        $empresas = DB::table("empresa");
        if(isset($usu) && $usu->fkRol == 2){
            $empresas->whereIn("idempresa", $usu->empresaUsuario);
        }        
        $empresas = $empresas->orderBy("razonSocial")->get();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu 'Incrementos de salarios y conceptos fijos'");

        return view('/nomina.cambiarConceptoFijo', ["empresas" => $empresas, "dataUsu" => $usu ]);
    }
    public function subirCambioConceptoFijo(Request $req){        
        
        $csv = $req->file("archivoCSV");
        $subidos = 0;
        $errores = array();
        $file = $req->file('archivoCSV')->get();
        $file = str_replace("\r","\n",$file);
        $reader = Reader::createFromString($file);
        $reader->setDelimiter(';');
        foreach($reader as $id => $row){
            
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
            
            //Buscar empleado
            $dt = DateTime::createFromFormat("Y-m-d", $row[3]);
            
            if($dt === false){
                //ERROR
                array_push($errores, "Error en la linea ".$id." el campo no es una fecha");
                continue;
            }

            if(strpos($row[2],".")!==false){
                //ERROR
                array_push($errores, "Error en la linea ".$id." el campo contiene un punto");
                continue;
            }

            $conceptos_existe = DB::table("concepto")->where("idconcepto","=",$row[1])->first();
            if(!isset($conceptos_existe)){
                //ERROR
                array_push($errores, "Error en la linea ".$id." el concepto no existe");
                continue;
            }

            $empleado = DB::table("empleado","e")
            ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
            ->where("dp.numeroIdentificacion","=",$row[0])->first();
            
            $periodo = DB::table("periodo","p")
            ->join("empleado as e","e.idempleado", "=", "p.fkEmpleado")
            ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
            ->join("nomina as n","n.idNomina", "=","p.fkNomina")
            ->where("dp.numeroIdentificacion","=",$row[0])
            ->where("n.fkEmpresa","=", $req->empresa)
            ->where("p.fkEstado", "=", "1")
            ->first();

            


            if(isset($empleado) && isset($periodo)){
                $conceptoFijo = DB::table("conceptofijo","cf")
                ->where("cf.fkEmpleado","=",$empleado->idempleado)
                ->where("cf.fkPeriodoActivo","=",$periodo->idPeriodo)
                ->where("cf.fkConcepto","=",$row[1])
                ->first();

                if(isset($conceptoFijo)){
                    
                    $idCambioSalario = 0;

                    if($row[1] == "1" || $row[1] == "2" || $row[1] == "53" || $row[1] == "54" || $row[1] == "154"){       

                        $idCambioSalario = DB::table("cambiosalario")->insertGetId([
                            "fechaCambio" => $row[3],
                            "fkEstado" => "5",
                            "valorNuevo" => $row[2],
                            "valorAnterior" => $conceptoFijo->valor,
                            "fkEmpleado" => $empleado->idempleado,
                            "fkPeriodoActivo" => $periodo->idPeriodo
                        ], "idCambioSalario");

                        DB::table("conceptofijo")
                        ->where("idConceptoFijo","=",$conceptoFijo->idConceptoFijo)
                        ->update([
                            "fechaInicio" => $row[3],
                            "fechaFin" => (isset($row[4]) ? $row[4] : NULL),
                            "valor" => $row[2],
                            "fkEstado" => "1",
                            "unidad" => (isset($row[5]) ? $row[5] : "MES")
                        ]);

                        if(isset($row[4]) && strtotime($row[4]) < strtotime("today")){
                            DB::table("conceptofijo")
                            ->where("idConceptoFijo","=",$conceptoFijo->idConceptoFijo)
                            ->update([
                                "fkEstado" => "2",
                            ]);
                        }
                        $subidos++;



                    }
                    else{
                        DB::table("conceptofijo")
                        ->where("idConceptoFijo","=",$conceptoFijo->idConceptoFijo)
                        ->update([
                            "fechaInicio" => $row[3],
                            "fechaFin" => (isset($row[4]) ? $row[4] : NULL),
                            "valor" => $row[2],
                            "fkEstado" => "1",
                            "unidad" => (isset($row[5]) ? $row[5] : "MES")
                        ]);

                        
                        DB::table("conceptofijo")
                        ->where("idConceptoFijo","=",$conceptoFijo->idConceptoFijo)
                        ->update(["fkEstado" => "1"]);

                        if(isset($row[4]) && strtotime($row[4]) < strtotime("today")){
                            DB::table("conceptofijo")
                            ->where("idConceptoFijo","=",$conceptoFijo->idConceptoFijo)
                            ->update([
                                "fkEstado" => "2",
                            ]);
                        }
                        
                        $subidos++;
                    }

                    
                }
                else{
                    if($row[1] == "1" || $row[1] == "2" || $row[1] == "53" || $row[1] == "54" || $row[1] == "154"){ 
                        $conceptoFijo = DB::table("conceptofijo","cf")
                        ->where("cf.fkEmpleado","=",$empleado->idempleado)
                        ->whereIn("cf.fkConcepto",[1,2,53,54,154])
                        ->where("cf.fkPeriodoActivo","=",$periodo->idPeriodo)
                        ->first();
                        if(isset($conceptoFijo)){

                            $idCambioSalario = DB::table("cambiosalario")->insertGetId([
                                "fechaCambio" => $row[3],
                                "fkEstado" => "5",
                                "valorNuevo" => $row[2],
                                "valorAnterior" => $conceptoFijo->valor,
                                "fkEmpleado" => $empleado->idempleado,
                                "fkPeriodoActivo" => $periodo->idPeriodo
                            ], "idCambioSalario");
    
                            
    
                            
                            DB::table("conceptofijo")
                            ->where("idConceptoFijo","=",$conceptoFijo->idConceptoFijo)
                            ->update([
                                "fkConcepto" => $row[1],
                                "fechaInicio" => $row[3],
                                "fechaFin" => (isset($row[4]) ? $row[4] : NULL),
                                "valor" => $row[2],
                                "fkEstado" => "1",
                                "unidad" => (isset($row[5]) ? $row[5] : "MES")
                            ]);

                            if(isset($row[4]) && strtotime($row[4]) < strtotime("today")){
                                DB::table("conceptofijo")
                                ->where("idConceptoFijo","=",$conceptoFijo->idConceptoFijo)
                                ->update([
                                    "fkEstado" => "2",
                                ]);
                            }
                            else{
                                if($row[1] == "2"){
                                    DB::table("empleado")
                                    ->where("idempleado","=",$empleado->idempleado)
                                    ->update([
                                        "tipoRegimen" => "Salario Integral"
                                    ]);

                                    DB::table("periodo")
                                    ->where("idPeriodo","=",$periodo->idPeriodo)
                                    ->update([
                                        "tipoRegimen" => "Salario Integral"
                                    ]);

                                }
                                if($row[1] == "1"){
                                    DB::table("empleado")
                                    ->where("idempleado","=",$empleado->idempleado)
                                    ->update([
                                        "tipoRegimen" => "Ley 50"
                                    ]);
                                    DB::table("periodo")
                                    ->where("idPeriodo","=",$periodo->idPeriodo)
                                    ->update([
                                        "tipoRegimen" => "Ley 50"
                                    ]);
                                }                                    
                                
                            }
    
                            
                        }
                        else{
                            $idConceptoFijo = DB::table("conceptofijo")
                            ->insertGetId([
                                "unidad" => (isset($row[5]) ? $row[5] : "MES"),
                                "fkEmpleado" => $empleado->idempleado,
                                "fkPeriodoActivo" => $periodo->idPeriodo,
                                "fkConcepto" => $row[1],
                                "fechaInicio" => $row[3],
                                "fechaFin" => (isset($row[4]) ? $row[4] : NULL),
                                "valor" => $row[2],
                                "fkEstado" => "1"
                            ],"idConceptoFijo");
        
                            
                            // DB::table("conceptofijo")
                            // ->where("idConceptoFijo","=",$idConceptoFijo)
                            // ->update(["fkEstado" => "1"]);
                            
                        }

                    }
                    else{
                        $idConceptoFijo = DB::table("conceptofijo")
                        ->insertGetId([
                            "unidad" => (isset($row[5]) ? $row[5] : "MES"),
                            "fkEmpleado" => $empleado->idempleado,
                            "fkPeriodoActivo" => $periodo->idPeriodo,
                            "fkConcepto" => $row[1],
                            "fechaInicio" => $row[3],
                            "fechaFin" => (isset($row[4]) ? $row[4] : NULL),
                            "valor" => $row[2],
                            "fkEstado" => "1"
                        ],"idConceptoFijo");
    
                        
                        // DB::table("conceptofijo")
                        // ->where("idConceptoFijo","=",$idConceptoFijo)
                        // ->update(["fkEstado" => "1"]);
                        
                    }


                    
                    $subidos++;
                }


            }

            
        }

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", subió un archivo plano de 'Incrementos de salarios y conceptos fijos'");

        $usu = UsuarioController::dataAdminLogueado();
        return view('/nomina.subirConceptoFijoResumen', [
            "subidos" => $subidos,
            "errores" => $errores,
            "dataUsu" => $usu
        ]);

    }
    public function calcularPrima($fechaInicio, $fechaFin, $empleado, $periodo, $periodoPagoSinVac, $arrValorxConcepto, $idLiquidacionNomina, $arrComoCalcula = array()){
        
        $esRetiroNegativo = false;
        if(strtotime($fechaInicio) > strtotime($fechaFin)){
            $esRetiroNegativo = true;
        }
        $liquidacionNomina = DB::table('liquidacionnomina')->where("idLiquidacionNomina", "=", $idLiquidacionNomina)->first();
        $periodoActivoReintegro = DB::table("periodo")
        ->join("nomina as n","n.idNomina", "=","periodo.fkNomina")
        ->where("fkEstado","=","1")
        ->where("fkEmpleado", "=", $empleado->idempleado)
        ->where("fkNomina", "=", $liquidacionNomina->fkNomina)
        ->first();


        $fechaInicioMes = date("Y-m-01", strtotime($fechaInicio));
        $periodoPagoMesActual = $periodoPagoSinVac;
        //INICIO SALARIAL GANADO ESTE MES
        $salarial = 0;
        
        $grupoConceptoCalculoPrimaCes = DB::table("grupoconcepto_concepto","gcc")
            ->where("gcc.fkGrupoConcepto", "=", "11")//Salarial para provisiones
            ->get();
        foreach($grupoConceptoCalculoPrimaCes as $grupoConcepto){
            if(isset($arrValorxConcepto[$grupoConcepto->fkConcepto]) && $grupoConcepto->fkConcepto != 36){
                $salarial = $salarial + floatval($arrValorxConcepto[$grupoConcepto->fkConcepto]['valor']);
            }
        }
       
        $liquidacionMesActual = DB::table("liquidacionnomina", "ln")
        ->select("bp.periodoPago","ln.idLiquidacionNomina")
        ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
        ->where("bp.fkEmpleado","=",$empleado->idempleado)
        ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
        ->where("ln.fechaInicio","=",$fechaInicioMes)
        ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
        ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
        ->first();
        
        if(isset($liquidacionMesActual)){

            $itemsBoucherSalarialMesAct = DB::table("item_boucher_pago", "ibp")
            ->selectRaw("Sum(ibp.valor) as suma")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
            ->where("bp.fkLiquidacion","=",$liquidacionMesActual->idLiquidacionNomina)                        
            ->where("gcc.fkGrupoConcepto","=","11") //11 - Salarial Prima
            ->first();
            $salarial = $salarial + $itemsBoucherSalarialMesAct->suma;

            $itemsBoucherSalarialMesAct16 = DB::table("item_boucher_pago", "ibp")
            ->selectRaw("Sum(ibp.valor) as suma")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
            ->where("ln.fechaInicio","=",date("Y-m-16",strtotime($fechaInicioMes)))
            ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
            ->where("gcc.fkGrupoConcepto","=","11") //11 - Salarial Prima
            ->first();
            $salarial = $salarial + ($itemsBoucherSalarialMesAct16->suma ?? 0);
        }  

        if($periodo == 15 && !isset($liquidacionMesActual)){
            if(substr($fechaInicio,8,2) == "16"){
                $itemsBoucherSalarialMesAct16 = DB::table("item_boucher_pago", "ibp")
                ->selectRaw("Sum(ibp.valor) as suma")
                ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("ln.fechaInicio","=",date("Y-m-16",strtotime($fechaInicioMes)))
                ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
                ->where("gcc.fkGrupoConcepto","=","11") //11 - Salarial Prima
                ->first();
                $salarial = $salarial + ($itemsBoucherSalarialMesAct16->suma ?? 0);
            }
        }

        
        

        if($periodo == 15){
            if(substr($fechaInicio,8,2) == "16"){
               

                $liquidacionAnt = DB::table("liquidacionnomina", "ln")
                ->select("bp.periodoPago","ln.idLiquidacionNomina")
                ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("ln.fechaInicio","=",$fechaInicioMes)
                ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"]);
                if(isset($liquidacionMesActual)){
                    $liquidacionAnt = $liquidacionAnt->where("ln.idLiquidacionNomina","<>",$liquidacionMesActual->idLiquidacionNomina);
                }
                $liquidacionAnt = $liquidacionAnt->first();
                

                if(isset($liquidacionAnt)){
                    $periodoPagoMesActual = $periodoPagoMesActual + $liquidacionAnt->periodoPago;
                    $itemsBoucherSalarialMesAnt = DB::table("item_boucher_pago", "ibp")
                    ->selectRaw("Sum(ibp.valor) as suma")
                    ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
                    ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
                    ->where("bp.fkEmpleado","=",$empleado->idempleado)
                    ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                    ->where("bp.fkLiquidacion","=",$liquidacionAnt->idLiquidacionNomina)                        
                    ->where("gcc.fkGrupoConcepto","=","11") //11 - Salarial Prima
                    ->first();
                    $salarial = $salarial + $itemsBoucherSalarialMesAnt->suma;
                }
            }
        }
        
        //FIN SALARIAL GANADO ESTE MES

        //INICIO SALARIO MES
        $salarioMes = 0;
        $conceptosFijosEmpl = DB::table("conceptofijo", "cf")
        ->select(["cf.valor","cf.fechaInicio","cf.fechaFin", "cf.fkConcepto","cf.unidad", "c.*"])
        ->join("concepto AS c", "cf.fkConcepto","=","c.idconcepto")
        ->where("cf.fkEmpleado", "=", $empleado->idempleado)  
        ->where("cf.fkPeriodoActivo", "=", $periodoActivoReintegro->idPeriodo)  
        ->where("cf.fkEstado", "=", "1")
        ->where("cf.fkConcepto", "=", "1")
        ->first();
     
        $salarioMes = $conceptosFijosEmpl->valor; 
        if($empleado->fkTipoCotizante == 51){
            $salarioMes = ($arrValorxConcepto[1]["valor"] ?? 0);
        }


        /*if($periodo == 15){
            if(substr($fechaInicio,8,2) == "16"){
                $bouchersPagoPrimeraQ = DB::table("boucherpago","bp")
                ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
                ->where("bp.fkEmpleado","=", $empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
                ->where("ln.fechaInicio","=",$fechaInicioMes)
                ->get();
                $salarioMes = ($conceptosFijosEmpl->valor/30)*$periodo;
                
                if(sizeof($bouchersPagoPrimeraQ) > 0){
                    foreach($bouchersPagoPrimeraQ as $boucherPagoPrimeraQ){
                        $salarioCalc = ($boucherPagoPrimeraQ->salarioPeriodoPago*$periodo)/($boucherPagoPrimeraQ->periodoPago != 0 ? $boucherPagoPrimeraQ->periodoPago : 1);
                        $salarioMes += $salarioCalc; 
                    }
                }
                else{
                    $salarioMes = ($conceptosFijosEmpl->valor/30)*30;
                }
                
                
            }
        }*/

        

        
        
        //FIN VERIFICAR SI TIENE RETROACTIVOS EN ESTE MES Y AGREGARLOS AL VALOR DEL SALARIO DEL MES
        //FIN SALARIO MES

        //INICIALIZAR VARIABLES
        $anioActual = intval(date("Y",strtotime($fechaInicio)));
        $mesActual = intval(date("m",strtotime($fechaInicio)));
        $salarioPrima = 0;
        $basePrima = 0;
        $totalPeriodoPago = 0;
        $provisionPrimaValor = 0;
        $fechaInicialPrima = "";
        $fechaFinalPrima = "";
        
        $mesProyeccion = intval(substr($fechaFin,5,2));
        
        
        //FIN INICIALIZAR VARIABLES
        $salarioMesNoMod = $salarioMes;
        if($esRetiroNegativo){
            $salarioMes = 0;
            $periodoPagoMesActual = 0;
        }
        
        //dump($esRetiroNegativo, $fechaInicio, $fechaFin);
        if($mesProyeccion >= 1 && $mesProyeccion <= 6){
            if($esRetiroNegativo){
                $liquidacionesMesesAnterioresPrima = DB::table("liquidacionnomina", "ln")
                ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.diasTrabajados) as diasTrabajadosPer, sum(bp.diasIncapacidad) as diasIncapacidadPer, sum(bp.salarioPeriodoPago) as salarioPago, min(ln.fechaInicio) as minimaFecha")
                ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("ln.fechaFin","<=",$fechaFin)
                ->where("bp.salarioPeriodoPago",">",0)
                ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")
                ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","8","12"])
                ->orderBy("ln.fechaLiquida")
                ->first();
                
                $liquidacionesMesesAnterioresPrimaDetalle = DB::table("liquidacionnomina", "ln")
                ->selectRaw("bp.periodoPago, bp.diasTrabajados, bp.diasIncapacidad, bp.salarioPeriodoPago, ln.fechaInicio, ln.idLiquidacionNomina")
                ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("ln.fechaInicio","<=",$fechaFin)
                ->where("ln.fechaFin","<=",$fechaFin)
                ->where("bp.salarioPeriodoPago","<>",0)     
                ->where("bp.ibc_eps","<>",0)     
                ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")
                ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","8","12"])
                ->orderBy("ln.fechaLiquida")
                ->get();

                $fechaIniMesAct = date("Y-m-01",strtotime($fechaFin));

                $liquidacionesMesesAnterioresPrimaMesAct = DB::table("liquidacionnomina", "ln")
                ->selectRaw("bp.periodoPago, bp.diasTrabajados, bp.diasIncapacidad, bp.salarioPeriodoPago, ln.fechaInicio, ln.idLiquidacionNomina")
                ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("ln.fechaFin",">",$fechaFin)
                ->where("ln.fechaInicio","=",$fechaIniMesAct)
                ->where("bp.salarioPeriodoPago",">",0)     
                ->where("bp.ibc_eps",">",0)     
                ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")
                ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","8","12"])
                ->orderBy("ln.fechaLiquida")
                ->first();

                if(isset($liquidacionesMesesAnterioresPrimaMesAct)){
                    $dias = $this->days_360($fechaIniMesAct, $fechaFin) + 1;
                    $calcularSalMesAct = $liquidacionesMesesAnterioresPrimaMesAct->salarioPeriodoPago*$dias/$liquidacionesMesesAnterioresPrimaMesAct->periodoPago;
                    $liquidacionesMesesAnterioresPrima->periodPago += $dias;
                    $liquidacionesMesesAnterioresPrima->salarioPago += $calcularSalMesAct;
                }
            }
            else{
                $liquidacionesMesesAnterioresPrima = DB::table("liquidacionnomina", "ln")
                ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.diasTrabajados) as diasTrabajadosPer, sum(bp.diasIncapacidad) as diasIncapacidadPer, sum(bp.salarioPeriodoPago) as salarioPago, min(ln.fechaInicio) as minimaFecha")
                ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("ln.fechaInicio","<",$fechaInicioMes)
                ->where("bp.salarioPeriodoPago","<>",0)
                ->where("bp.ibc_eps","<>",0)            
                ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")
                ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","8","12"])
                ->orderBy("ln.fechaLiquida")
                ->first();
                //dd($liquidacionesMesesAnterioresPrima);
    
                $liquidacionesMesesAnterioresPrimaDetalle = DB::table("liquidacionnomina", "ln")
                ->selectRaw("bp.periodoPago, bp.diasTrabajados, bp.diasIncapacidad, bp.salarioPeriodoPago, ln.fechaInicio, ln.idLiquidacionNomina")
                ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("ln.fechaInicio","<",$fechaInicioMes)
                ->where("bp.salarioPeriodoPago","<>",0)     
                ->where("bp.ibc_eps","<>",0)     
                ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")
                ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","8","12"])
                ->orderBy("ln.fechaLiquida")
                ->get();
            }
            
            
            if(isset($liquidacionesMesesAnterioresPrima->minimaFecha)){
                
                if(strtotime($empleado->fechaIngreso) > strtotime($liquidacionesMesesAnterioresPrima->minimaFecha)){
                    $fechaInicialPrima = $empleado->fechaIngreso;
                }
                else if(strtotime($empleado->fechaIngreso) < strtotime($liquidacionesMesesAnterioresPrima->minimaFecha) && strtotime($empleado->fechaIngreso) >= strtotime(date($anioActual."-01-01"))){
                    $fechaInicialPrima = $empleado->fechaIngreso;
                }
                else{
                    $fechaInicialPrima = $empleado->fechaIngreso;
                }                    
            }
            else{
                $fechaInicialPrima = $empleado->fechaIngreso;
            }

            //dd($fechaInicialPrima);
            if(strtotime($fechaInicialPrima) < strtotime(date($anioActual."-01-01"))){
                $fechaInicialPrima = date($anioActual."-01-01");
            }

            $fechaFinalPrima = $fechaFin;
            
            //INICIO CALCULAR EL PROMEDIO SALARIO EN PERIODO ACTUAL    
            

            $totalPeriodoPago = $periodoPagoMesActual + (isset($liquidacionesMesesAnterioresPrima->periodPago) ? $liquidacionesMesesAnterioresPrima->periodPago : 0);
            if($empleado->fkTipoCotizante == 51){
                $totalPeriodoPagoParaSalario = $periodoPagoMesActual + (isset($liquidacionesMesesAnterioresPrima->periodPago) ? $liquidacionesMesesAnterioresPrima->periodPago : 0);
            }
            else{
                if($esRetiroNegativo){
                    $totalPeriodoPagoParaSalario = $periodoPagoMesActual + (isset($liquidacionesMesesAnterioresPrima->periodPago) ? $liquidacionesMesesAnterioresPrima->periodPago : 0);
                }
                else{
                    $diasMes = 30;
                    if($liquidacionNomina->fkTipoLiquidacion == "2" || $liquidacionNomina->fkTipoLiquidacion == "3"){
                        $diasMes = $this->days_360($fechaInicioMes, $fechaFinalPrima) + 1;    
         
                        $salarioMes = ($salarioMes / 30) * $diasMes;
                    }
                    $totalPeriodoPagoParaSalario = $diasMes + (isset($liquidacionesMesesAnterioresPrima->periodPago) ? $liquidacionesMesesAnterioresPrima->periodPago : 0);
                }                
                
            }
            
            //INICIO VERIFICAR SI TIENE RETROACTIVOS EN ESTE MES Y AGREGARLOS AL VALOR DEL SALARIO DEL MES
            $itemRetroActivos = DB::table("item_boucher_pago", "ibp")
            ->select("ibp.*")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
            ->where("ibp.fkConcepto","=","49")
            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
            ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
            ->whereBetween("ln.fechaLiquida",[$fechaInicioMes,$fechaFin])
            ->get();
            foreach($itemRetroActivos as $itemRetroActivo){
                $salarioMes = $salarioMes + $itemRetroActivo->valor;
            }
            if(isset($arrValorxConcepto[49])){
                $salarioMes = $salarioMes + floatval($arrValorxConcepto[49]['valor']);
            }

            //INICIO PROYECTAR PERIODO PAGO A FECHA FINAL 
            $diasFaltantes = 0;
            if($periodo == 15 && intval(date("d",strtotime($fechaFin)))>15){                
                if(substr($fechaInicio,8,2) == "01"){
                    $diasFaltantes = $diasFaltantes + 15;
                }
            }
            $mesesFaltanes = $mesProyeccion - $mesActual;
            
            $diasFaltantes = $diasFaltantes + ($mesesFaltanes * 30);
            
            $totalPeriodoPago = $totalPeriodoPago + $diasFaltantes;
           
            //FIN PROYECTAR PERIODO PAGO A FECHA FINAL 

            $retroActivoMesesAnterioresPrima = DB::table("liquidacionnomina", "ln")
            ->selectRaw("sum(ibp.valor) as suma")
            ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
            ->join("item_boucher_pago as ibp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
            ->where("ln.fechaInicio","<",$fechaInicio)
            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")
            ->where("ibp.fkConcepto","=","49") // 49 - RETROACTIVO SALARIO
            ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","12"])
            ->first();
        
            $salarioAdicional = 0;
            
            if($mesesFaltanes>0){
                $totalPeriodoPagoParaSalario = $totalPeriodoPagoParaSalario + ($mesesFaltanes * 30);
                $salarioAdicional = ($mesesFaltanes * $salarioMes);
            }
            //dd($liquidacionesMesesAnterioresPrima, $salarioMes, $totalPeriodoPagoParaSalario);

            
            $salarioPrima = $salarioAdicional + $salarioMes + ($liquidacionesMesesAnterioresPrima->salarioPago ?? 0) + ($retroActivoMesesAnterioresPrima->suma ?? 0);
            //dd($salarioAdicional, $salarioMes, $liquidacionesMesesAnterioresPrima->salarioPago, $retroActivoMesesAnterioresPrima->suma, $totalPeriodoPagoParaSalario);
            //if($fechaFinalPrima=="2022-06-30") dd($liquidacionesMesesAnterioresPrimaDetalle, $retroActivoMesesAnterioresPrima, $salarioMes, $totalPeriodoPagoParaSalario); 
            $salarioPrima = ($salarioPrima / $totalPeriodoPagoParaSalario)*30;

            //dd($salarioPrima);
            

            //AGREGAR SUBSIDIO DE TRANSPORTE AL SALARIO DE SER REQUERIDO
            if($empleado->aplicaSubsidio == "1" && $empleado->tipoRegimen == 'Ley 50'){
                $variablesSalarioMinimo = DB::table("variable")->where("idVariable","=","1")->first();
                $salarioPrimaParaSubsidio = $salarioMesNoMod;                
                if($salarioPrimaParaSubsidio <= (2 * $variablesSalarioMinimo->valor)){
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
                    $salarioPrimaParaSubsidio = $salarioMesNoMod;                
                    if($salarioPrimaParaSubsidio <= (2 * $variablesSalarioMinimo->valor)){
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
            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
            ->where("ln.fechaInicio","<",$fechaInicioMes)
            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")                
            ->where("gcc.fkGrupoConcepto","=","11") //11 - Salarial
            ->first();
            
           
            $itemsBoucherSalarialMesesAnterioresSQL = DB::table("item_boucher_pago", "ibp")
            ->selectRaw("ibp.valor, ln.fechaLiquida")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("grupoconcepto_concepto as gcc","gcc.fkConcepto","=","ibp.fkConcepto")                
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
            ->where("ln.fechaInicio","<",$fechaInicioMes)
            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."'")                
            ->where("gcc.fkGrupoConcepto","=","11") //11 - Salarial
            ->get();
            
            
            $salarialPrima = $salarial + $itemsBoucherSalarialMesesAnteriores->suma;
            
            
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
            
            if($esRetiroNegativo){
                $liquidacionesMesesAnterioresPrima = DB::table("liquidacionnomina", "ln")
                ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.diasTrabajados) as diasTrabajadosPer, sum(bp.diasIncapacidad) as diasIncapacidadPer, sum(bp.salarioPeriodoPago) as salarioPago, min(ln.fechaInicio) as minimaFecha")
                ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("ln.fechaFin","<=",$fechaFin)
                ->where("bp.salarioPeriodoPago",">",0)
                ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."' and MONTH(ln.fechaInicio) > 6")
                ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","8","12"])
                ->orderBy("ln.fechaLiquida")
                ->first();
                
                $liquidacionesMesesAnterioresPrimaDetalle = DB::table("liquidacionnomina", "ln")
                ->selectRaw("bp.periodoPago, bp.diasTrabajados, bp.diasIncapacidad, bp.salarioPeriodoPago, ln.fechaInicio, ln.idLiquidacionNomina")
                ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("ln.fechaInicio","<=",$fechaFin)
                ->where("ln.fechaFin","<=",$fechaFin)
                ->where("bp.salarioPeriodoPago","<>",0)     
                ->where("bp.ibc_eps","<>",0)     
                ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."' and MONTH(ln.fechaInicio) > 6")
                ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","8","12"])
                ->orderBy("ln.fechaLiquida")
                ->get();
                
                $fechaIniMesAct = date("Y-m-01",strtotime($fechaFin));

                $liquidacionesMesesAnterioresPrimaMesAct = DB::table("liquidacionnomina", "ln")
                ->selectRaw("bp.periodoPago, bp.diasTrabajados, bp.diasIncapacidad, bp.salarioPeriodoPago, ln.fechaInicio, ln.idLiquidacionNomina")
                ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("ln.fechaFin",">",$fechaFin)
                ->where("ln.fechaInicio","=",$fechaIniMesAct)
                ->where("bp.salarioPeriodoPago",">",0)     
                ->where("bp.ibc_eps",">",0)     
                ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."' and MONTH(ln.fechaInicio) > 6")
                ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","8","12"])
                ->orderBy("ln.fechaLiquida")
                ->first();
                
                if(isset($liquidacionesMesesAnterioresPrimaMesAct)){
                   
                    $dias = $this->days_360($fechaIniMesAct, $fechaFin) + 1;
                    $calcularSalMesAct = $liquidacionesMesesAnterioresPrimaMesAct->salarioPeriodoPago*$dias/$liquidacionesMesesAnterioresPrimaMesAct->periodoPago;
                    $liquidacionesMesesAnterioresPrima->periodPago += $dias;
                    $liquidacionesMesesAnterioresPrima->salarioPago += $calcularSalMesAct;
                }
            }
            else{
                $liquidacionesMesesAnterioresPrima = DB::table("liquidacionnomina", "ln")
                ->selectRaw("sum(bp.periodoPago) as periodPago, sum(bp.diasTrabajados) as diasTrabajadosPer, sum(bp.diasIncapacidad) as diasIncapacidadPer, sum(bp.salarioPeriodoPago) as salarioPago, min(ln.fechaInicio) as minimaFecha")
                ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
                ->where("bp.fkEmpleado","=",$empleado->idempleado)
                ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
                ->where("ln.fechaInicio","<",$fechaInicioMes)
                ->where("bp.salarioPeriodoPago","<>",0)            
                ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."' and MONTH(ln.fechaInicio) > 6")
                ->whereIn("ln.fkTipoLiquidacion",["1","2","3","4","5","6","9","8","12"])
                ->orderBy("ln.fechaLiquida")
                ->first();
            }

            if(isset($liquidacionesMesesAnterioresPrima->minimaFecha)){
                
                if(strtotime($empleado->fechaIngreso) > strtotime($liquidacionesMesesAnterioresPrima->minimaFecha)){
                    $fechaInicialPrima = $empleado->fechaIngreso;
                }
                else if(strtotime($empleado->fechaIngreso) < strtotime($liquidacionesMesesAnterioresPrima->minimaFecha) && strtotime($empleado->fechaIngreso) >= strtotime(date($anioActual."-01-01"))){
                    $fechaInicialPrima = $empleado->fechaIngreso;
                }
                else{
                    $fechaInicialPrima = $empleado->fechaIngreso;
                }                    
            }
            else{
                $fechaInicialPrima = $empleado->fechaIngreso;
            }

            //dd($fechaInicialPrima);
            if(strtotime($fechaInicialPrima) < strtotime(date($anioActual."-07-01"))){
                $fechaInicialPrima = date($anioActual."-07-01");
            }


            $fechaFinalPrima = $fechaFin;
            //INICIO CALCULAR EL PROMEDIO SALARIO EN PERIODO ACTUAL 
            $totalPeriodoPago = $periodoPagoMesActual + (isset($liquidacionesMesesAnterioresPrima->periodPago) ? $liquidacionesMesesAnterioresPrima->periodPago : 0);
            if($empleado->fkTipoCotizante == 51){
                $totalPeriodoPagoParaSalario = $periodoPagoMesActual + (isset($liquidacionesMesesAnterioresPrima->periodPago) ? $liquidacionesMesesAnterioresPrima->periodPago : 0);
            }
            else{
                if($esRetiroNegativo){
                    $totalPeriodoPagoParaSalario = $periodoPagoMesActual + (isset($liquidacionesMesesAnterioresPrima->periodPago) ? $liquidacionesMesesAnterioresPrima->periodPago : 0);
                }
                else{
                    $diasMes = 30;
                    if($liquidacionNomina->fkTipoLiquidacion == "2" || $liquidacionNomina->fkTipoLiquidacion == "3"){
                        $diasMes = $this->days_360($fechaInicioMes, $fechaFinalPrima) + 1;               
                        $salarioMes = ($salarioMes / 30) * $diasMes;
                    }
                    $totalPeriodoPagoParaSalario = $diasMes + (isset($liquidacionesMesesAnterioresPrima->periodPago) ? $liquidacionesMesesAnterioresPrima->periodPago : 0);
                }                
            }

            //INICIO VERIFICAR SI TIENE RETROACTIVOS EN ESTE MES Y AGREGARLOS AL VALOR DEL SALARIO DEL MES
            $itemRetroActivos = DB::table("item_boucher_pago", "ibp")
            ->select("ibp.*")
            ->join("boucherpago as bp","bp.idBoucherPago","=","ibp.fkBoucherPago")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
            ->where("ibp.fkConcepto","=","49")
            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
            ->where("ln.idLiquidacionNomina","<>",$idLiquidacionNomina)
            ->whereBetween("ln.fechaLiquida",[$fechaInicioMes,$fechaFin])
            ->get();
            foreach($itemRetroActivos as $itemRetroActivo){
                $salarioMes = $salarioMes + $itemRetroActivo->valor;
            }
            if(isset($arrValorxConcepto[49])){
                $salarioMes = $salarioMes + floatval($arrValorxConcepto[49]['valor']);
            }
            //INICIO PROYECTAR PERIODO PAGO A FECHA FINAL 
            $diasFaltantes = 0;
            if($periodo == 15){                
                if(substr($fechaInicio,8,2) == "16"){
                    $diasFaltantes = $diasFaltantes + 15;
                }
            }
            $mesesFaltanes = $mesProyeccion - $mesActual;            
            $diasFaltantes = $diasFaltantes + ($mesesFaltanes * 30);
            $totalPeriodoPago = $totalPeriodoPago + $diasFaltantes;
            $fechaFinalPrima = $fechaFin;          
            
            $retroActivoMesesAnterioresPrima = DB::table("liquidacionnomina", "ln")
            ->selectRaw("sum(ibp.valor) as suma")
            ->join("boucherpago as bp","bp.fkLiquidacion","=","ln.idLiquidacionNomina")                
            ->join("item_boucher_pago as ibp","ibp.fkBoucherPago", "=","bp.idBoucherPago")
            ->where("bp.fkEmpleado","=",$empleado->idempleado)
            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
            ->where("ln.fechaInicio","<",$fechaInicioMes)
            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."' and MONTH(ln.fechaInicio) > 6")
            ->where("ibp.fkConcepto","=","49") // 49 - RETROACTIVO SALARIO
            ->whereIn("ln.fkTipoLiquidacion",["1","2","4","5","6","9","12"])
            ->first();

            $salarioAdicional = 0;
            if($mesesFaltanes>0){
                $totalPeriodoPagoParaSalario = $totalPeriodoPagoParaSalario + ($mesesFaltanes * 30);
                $salarioAdicional = ($mesesFaltanes * $salarioMes);
            }
            $salarioPrima = $salarioAdicional + $salarioMes + ($liquidacionesMesesAnterioresPrima->salarioPago ?? 0) + ($retroActivoMesesAnterioresPrima->suma ?? 0);

            $salarioPrima = ($salarioPrima / $totalPeriodoPagoParaSalario)*30;

            //AGREGAR SUBSIDIO DE TRANSPORTE AL SALARIO DE SER REQUERIDO
            if($empleado->aplicaSubsidio == "1" && $empleado->tipoRegimen == 'Ley 50'){
                $variablesSalarioMinimo = DB::table("variable")->where("idVariable","=","1")->first();
                //$salarioPrimaParaSubsidio = ($salarioPrima/$periodoPagoMesActual)*30;
                $salarioPrimaParaSubsidio = $salarioMesNoMod;    
                if($salarioPrimaParaSubsidio <= (2 * $variablesSalarioMinimo->valor)){
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
                    $salarioPrimaParaSubsidio = $salarioMesNoMod;                
                    if($salarioPrimaParaSubsidio <= (2 * $variablesSalarioMinimo->valor)){
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
            ->where("bp.fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo)
            ->where("ln.fechaInicio","<",$fechaInicioMes)
            ->whereRaw("YEAR(ln.fechaInicio) = '".$anioActual."' and MONTH(ln.fechaInicio) > 6")                
            ->where("gcc.fkGrupoConcepto","=","11") //11 - Salarial
            ->first();
            
            $salarialPrima = $salarial + $itemsBoucherSalarialMesesAnteriores->suma;
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
        
        $saldoPrima = DB::table("saldo")
        ->where("fkEmpleado","=",$empleado->idempleado)
        ->where("fkPeriodoActivo","=",$periodoActivoReintegro->idPeriodo);
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
        

        
        
        
        return [
            "liquidacionPrima" => $liquidacionPrima,
            "fechaInicialPrima" => $fechaInicialPrima,
            "fechaFinalPrima" => $fechaFinalPrima,
            "totalPeriodoPago" => $totalPeriodoPago,
            "basePrima" => $basePrima, 
            "salarioPrima" => $salarioPrima,
            "salarialPrima" => $salarialPrima,
            "saldoPrima" => $saldoPrima,
            "arrComoCalcula" => $arrComoCalcula
        ];

    }
}