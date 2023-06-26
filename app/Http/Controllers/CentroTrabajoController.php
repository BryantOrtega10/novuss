<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\CentroTrabajoModel;
use App\Http\Requests\CentroTrabajoRequest;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CentroTrabajoController extends Controller
{
    public function index($id) {
        $centroTrabajo = CentroTrabajoModel::where('fkEmpresa', $id)->get();
        $usu = UsuarioController::dataAdminLogueado();
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu de centro de trabajo para la empresa con id: ".$id);
        return view('/empresas/centroTrabajo.centroTrabajo', [
            "centroTrabajos" => $centroTrabajo,
            'dataUsu' => $usu,
            'idEmpre' => $id
        ]);
    }

    public function getFormAdd($id) {
        $nivelArl = DB::table('nivel_arl')->select('*')->get();
        return view('/empresas/centroTrabajo/addCentroTrabajo', [
            'nivelArl' => $nivelArl,
            'idEmpresa' => $id
        ]);
    }

    public function create(CentroTrabajoRequest $request, $fkEmpresa) {
        $centroTrabajo = new CentroTrabajoModel();
        $centroTrabajo->codigo = $request->codigo;
        $centroTrabajo->nombre = $request->nombre;
        $centroTrabajo->fkNivelArl = $request->riesgo768;
        $centroTrabajo->fkEmpresa = $fkEmpresa;
        $actividad = DB::table("actividad_economica_decreto_768")
        ->where("riesgo","=",$request->riesgo768)
        ->where("ciiu","=",$request->ciiu768)
        ->where("codigo","=",$request->codigo768)
        ->first();
        $centroTrabajo->fkActividadEconomica768 = ($actividad->id ?? null);
        
        $save = $centroTrabajo->save();
        if ($save) {
            $dataUsu = UsuarioController::dataAdminLogueado();
            Log::channel('gesath')->info("El usuario ".$dataUsu->email.", creó un centro de trabajo para la empresa con id: ".$fkEmpresa);

            $success = true;
            $mensaje = "Centro trabajo agregado correctamente";
        } else {
            $success = true;
            $mensaje = "Error al agregar centro trabajo";
        }
        return response()->json(["success" => $success, "mensaje" => $mensaje]);
    }

    public function edit($id) {
        try {
            $centroTrabajo = CentroTrabajoModel::findOrFail($id);
            $nivelArl = DB::table('nivel_arl')->select('*')->get();
            $riesgoSelect = "0";
            $ciiuSelect = "";
            $codigoSelect = "";
            $actividad_nombre = "";

            $datosRiesgo = DB::table("actividad_economica_decreto_768")->where("id","=",$centroTrabajo->fkActividadEconomica768)->first();
            if(isset($datosRiesgo)){
                $riesgoSelect = $datosRiesgo->riesgo;
                $ciiuSelect = $datosRiesgo->ciiu;
                $codigoSelect = $datosRiesgo->codigo;
                $actividad_nombre = $datosRiesgo->descripcion;
            }
            $riesgos = [];
            if($ciiuSelect != ""){
                $riesgos = DB::table("actividad_economica_decreto_768")->select("riesgo")
                ->where("ciiu","=",$ciiuSelect)
                ->distinct()
                ->orderBy("riesgo")
                ->get();        
            }
            
            $codigos = [];
            if($ciiuSelect != "" && $riesgoSelect != ""){
                $codigos = DB::table("actividad_economica_decreto_768")
                ->select("codigo")
                ->where("riesgo","=",$riesgoSelect)
                ->where("ciiu","=",$ciiuSelect) 
                ->orderBy("codigo")
                ->get();       
            }

            return view('/empresas/centroTrabajo.editCentroTrabajo', [
                'riesgos' => $riesgos,
                'codigos' => $codigos,
                'riesgoSelect' => $riesgoSelect,
                'ciiuSelect' => $ciiuSelect,
                'codigoSelect' => $codigoSelect,
                'actividad_nombre' => $actividad_nombre,
                'centro' => $centroTrabajo,
                'nivelArl' => $nivelArl
            ]);
		}
		catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe un centro trabajo con este ID"]);
		}
    }

    public function detail($id) {
        try {
            $centroTrabajo = CentroTrabajoModel::findOrFail($id);
            $nivelArl = DB::table('nivel_arl')->select('*')->get();
            $riesgoSelect = "0";
            $ciiuSelect = "";
            $codigoSelect = "";
            $actividad_nombre = "";

            $datosRiesgo = DB::table("actividad_economica_decreto_768")->where("id","=",$centroTrabajo->fkActividadEconomica768)->first();
            if(isset($datosRiesgo)){
                $riesgoSelect = $datosRiesgo->riesgo;
                $ciiuSelect = $datosRiesgo->ciiu;
                $codigoSelect = $datosRiesgo->codigo;
                $actividad_nombre = $datosRiesgo->descripcion;
            }
            $riesgos = [];
            if($ciiuSelect != ""){
                $riesgos = DB::table("actividad_economica_decreto_768")->select("riesgo")
                ->where("ciiu","=",$ciiuSelect)
                ->distinct()
                ->orderBy("riesgo")
                ->get();        
            }
            
            $codigos = [];
            if($ciiuSelect != "" && $riesgoSelect != ""){
                $codigos = DB::table("actividad_economica_decreto_768")
                ->select("codigo")
                ->where("riesgo","=",$riesgoSelect)
                ->where("ciiu","=",$ciiuSelect) 
                ->orderBy("codigo")
                ->get();       
            }

            return view('/empresas/centroTrabajo.detailCentroTrabajo', [
                'riesgos' => $riesgos,
                'codigos' => $codigos,
                'riesgoSelect' => $riesgoSelect,
                'ciiuSelect' => $ciiuSelect,
                'codigoSelect' => $codigoSelect,
                'actividad_nombre' => $actividad_nombre,
                'centro' => $centroTrabajo,
                'nivelArl' => $nivelArl
            ]);
		}
		catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe un centro trabajo con este ID"]);
		}
    }

    public function update(Request $request, $id) {
        try {
            $centroTrabajo = CentroTrabajoModel::findOrFail($id);
            $centroTrabajo->codigo = $request->codigo;
            $centroTrabajo->nombre = $request->nombre;
            $centroTrabajo->fkNivelArl = $request->riesgo768;
            $actividad = DB::table("actividad_economica_decreto_768")
            ->where("riesgo","=",$request->riesgo768)
            ->where("ciiu","=",$request->ciiu768)
            ->where("codigo","=",$request->codigo768)
            ->first();
            $centroTrabajo->fkActividadEconomica768 = ($actividad->id ?? null);
            
            $save = $centroTrabajo->save();
            
            if ($save) {
                $dataUsu = UsuarioController::dataAdminLogueado();
                Log::channel('gesath')->info("El usuario ".$dataUsu->email.", modificó el centro de trabajo con id: ".$id);
                $success = true;
                $mensaje = "Centro trabajo actualizado correctamente";
            } else {
                $success = true;
                $mensaje = "Error al actualizar centro trabajo";
            }
            return response()->json(["success" => $success, "mensaje" => $mensaje]);
            }
		catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe un centro trabajo con este ID"]);
		}
    }

    public function delete($id) {
        try{
			$centroTrabajo = CentroTrabajoModel::findOrFail($id);
			if($centroTrabajo->delete()){
                $dataUsu = UsuarioController::dataAdminLogueado();
                Log::channel('gesath')->info("El usuario ".$dataUsu->email.", eliminó el centro de trabajo con id: ".$id);
				$success = true;
				$mensaje = "Centro trabajo eliminado con exito";
			} else {
				$success = false;
				$mensaje = "Error al eliminar centro trabajo";
			}
			return response()->json(["success" => $success, "mensaje" => $mensaje]);
		} catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe un centro trabajo con este ID"]);
		}
    }
}
