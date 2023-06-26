<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\CentroCostoEmpresaRequest;
use App\CentroCostoEmpresaModel;
use Illuminate\Database\Eloquent\ModelNotFoundHttpException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CentroCostoEmpresaController extends Controller
{
    public function index($id) {
        $centroCosto = CentroCostoEmpresaModel::where('fkEmpresa', $id)->get();
        $usu = UsuarioController::dataAdminLogueado();
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu de centro de costo de la empresa: ".$id);
        return view('/empresas/CentroCostoEmpresa.centroCostoEmpresa', [
            "centrosCosto" => $centroCosto,
            'dataUsu' => $usu
        ]);
    }


    public function getFormAdd($id) {
        return view('/empresas/CentroCostoEmpresa/addCentroCostoEmpresa', [
            'idEmpresa' => $id
        ]);
    }

    public function create(CentroCostoEmpresaRequest $request) {
        $centroCosto = new CentroCostoEmpresaModel();
        $centroCosto->nombre = $request->nombre;
        $centroCosto->fkEmpresa = $request->fkEmpresa;
        $centroCosto->id_uni_centro = $request->id_uni_centro;
        $centroCosto->diasCesantias = (empty($request->dias_cesantias) ? null : $request->dias_cesantias);
        $save = $centroCosto->save();
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", creó un nuevo centro de costo en la empresa: ".$request->fkEmpresa);
        if ($save) {
            $success = true;
            $mensaje = "Centro de costo agregado correctamente";
        } else {
            $success = true;
            $mensaje = "Error al agregar centro de costo";
        }
        return response()->json(["success" => $success, "mensaje" => $mensaje]);
    }

    public function edit($id) {
        try {
            $centroCosto = CentroCostoEmpresaModel::findOrFail($id);
            return view('/empresas/CentroCostoEmpresa.editCentroCostoEmpresa', [
                'centroCosto' => $centroCosto
            ]);
		}
		catch (ModelNotFoundHttpException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe un centro de costo con este ID"]);
		}
    }

    public function detail($id) {
        try {
            $centroCosto = CentroCostoEmpresaModel::findOrFail($id);
            return view('/empresas/CentroCostoEmpresa.detalleCentroCostoEmpresa', [
                'centroCosto' => $centroCosto
            ]);
		}
		catch (ModelNotFoundHttpException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe un centro de costo con este ID"]);
		}
    }

    public function update(Request $request, $id) {
        try {
            
            
            $centroCosto = CentroCostoEmpresaModel::findOrFail($request->idCentro);
            $centroCosto->nombre = $request->nombre;
            $centroCosto->fkEmpresa = $request->fkEmpresa;
            $centroCosto->id_uni_centro = $request->id_uni_centro;
            $centroCosto->diasCesantias = (empty($request->dias_cesantias) ? null : $request->dias_cesantias);
            $save = $centroCosto->save();

            $dataUsu = UsuarioController::dataAdminLogueado();
            Log::channel('gesath')->info("El usuario ".$dataUsu->email.", modificó el centro de costo: ".$request->idCentro);

            if ($save) {
                $success = true;
                $mensaje = "Centro de costo actualizado correctamente";
            } else {
                $success = true;
                $mensaje = "Error al actualizar centro de costo";
            }
            return response()->json(["success" => $success, "mensaje" => $mensaje]);
            }
		catch (ModelNotFoundHttpException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe un centro de costo con este ID"]);
		}
    }

    public function delete($id) {
        try{
			$centroCosto = CentroCostoEmpresaModel::findOrFail($id);
			if($centroCosto->delete()){
                $dataUsu = UsuarioController::dataAdminLogueado();
                Log::channel('gesath')->info("El usuario ".$dataUsu->email.", eliminó el centro de costo con id: ".$id);
				$success = true;
				$mensaje = "Centro de costo eliminado con exito";
			} else {
				$success = false;
				$mensaje = "Error al eliminar centro de costo";
			}
			return response()->json(["success" => $success, "mensaje" => $mensaje]);
		} catch (ModelNotFoundHttpException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe un centro de costo con este ID"]);
		}
    }
}
