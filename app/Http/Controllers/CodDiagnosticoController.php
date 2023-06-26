<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\CodDiagnosticoModel;
use App\Http\Requests\CodDiagnosticoRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class CodDiagnosticoController extends Controller
{
    public function index() {
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu de codigos de diagnostico");

        $usu = UsuarioController::dataAdminLogueado();
        return view('/codDiagnostico.codDiagnostico', [
            'dataUsu' => $usu
        ]);
    }

    public function getAll() {
        return CodDiagnosticoModel::all();
    }

    public function getFormAdd() {
        return view('/codDiagnostico.addCod');
    }

    public function create(CodDiagnosticoRequest $request) {
        $codigo = new CodDiagnosticoModel();
        $codigo->idCodDiagnostico = $request->idCodDiagnostico;
        $codigo->nombre = $request->nombre;
        $save = $codigo->save();
        if ($save) {
            $dataUsu = UsuarioController::dataAdminLogueado();
            Log::channel('gesath')->info("El usuario ".$dataUsu->email.", creó un nuevo codigo de diagnostico");
            $success = true;
            $mensaje = "Código de diagnóstico agregada correctamente";
        } else {
            $success = true;
            $mensaje = "Error al agregar código de diagnóstico";
        }
        return response()->json(["success" => $success, "mensaje" => $mensaje]);
    }

    public function edit($id) {
        try {
            $codigo = CodDiagnosticoModel::findOrFail($id);
            return view('/codDiagnostico.editCod', [
                'codigos' => $codigo
            ]);
		}
		catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe un código de diagnóstico con este ID"]);
		}
    }

    public function detail($id) {
        try {
            $codigo = CodDiagnosticoModel::findOrFail($id);
            return view('/codDiagnostico.detailCod', [
                'codigos' => $codigo
            ]);
		}
		catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe un código de diagnóstico con este ID"]);
		}
    }

    public function update(Request $request, $id) {
        try {
            $codigo = CodDiagnosticoModel::findOrFail($id);
            $codigo->idCodDiagnostico = $request->idCodDiagnostico;
            $codigo->nombre = $request->nombre;
            $save = $codigo->save();
            if ($save) {
                $dataUsu = UsuarioController::dataAdminLogueado();
                Log::channel('gesath')->info("El usuario ".$dataUsu->email.", modificó un codigo de diagnostico con id:".$id);
                $success = true;
                $mensaje = "Código de diagnóstico actualizada correctamente";
            } else {
                $success = true;
                $mensaje = "Error al actualizar código de diagnóstico";
            }
            return response()->json(["success" => $success, "mensaje" => $mensaje]);
            }
		catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe un código de diagnóstico con este ID"]);
		}
    }

    public function delete($id) {
        try{
            $codigo = CodDiagnosticoModel::findOrFail($id);
            if($codigo->delete()){
                $dataUsu = UsuarioController::dataAdminLogueado();
                Log::channel('gesath')->info("El usuario ".$dataUsu->email.", eliminó un codigo de diagnostico con id:".$id);
                $success = true;
                $mensaje = "Código de diagnóstico eliminada con exito";
            } else {
                $success = false;
                $mensaje = "Error al eliminar código de diagnóstico";
            }
			return response()->json(["success" => $success, "mensaje" => $mensaje]);
		} catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe un código de diagnóstico con este ID"]);
        }
    }
}
