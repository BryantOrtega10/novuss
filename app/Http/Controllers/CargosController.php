<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\CargosModel;
use App\Http\Requests\CargosRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use League\Csv\Writer;
use SplTempFileObject;

class CargosController extends Controller
{
    public function index() {
        $cargos = CargosModel::all();
        $usu = UsuarioController::dataAdminLogueado();
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu de cargos");
        return view('/cargos/cargos', [
            'cargos' => $cargos,
            'dataUsu' => $usu
        ]);
    }

    public function exportar(){

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", exportó el archivo de cargos");

        $cargos = CargosModel::all();
        $arrDef = array([
            "idCargo",
            "Nombre"
        ]);
        foreach ($cargos as $cargo){
            array_push($arrDef, [
                $cargo->idCargo,
                $cargo->nombreCargo
            ]);
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename=conceptos.csv');

        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->setDelimiter(';');
        $csv->insertAll($arrDef);
        $csv->setOutputBOM(Reader::BOM_UTF8);
        $csv->output('cargos.csv');
    }


    public function subirPlanoIndex(){
        $usu = UsuarioController::dataAdminLogueado();
        return view('/cargos/subirPlanocargos', ['dataUsu' => $usu]);
    }

    public function subirArchivo(Request $req){
        $csvDatosPasados = $req->file("archivoCSV");
        $file = $req->file('archivoCSV')->get();
        $file = str_replace("\r","\n",$file);
        $reader = Reader::createFromString($file);
        $reader->setOutputBOM(Reader::BOM_UTF8);
        $reader->setDelimiter(';');
        $csvDatosPasados = $csvDatosPasados->store("public/csvFiles");
        foreach ($reader as $row){
            foreach($row as $key =>$col){
                $row[$key] = mb_convert_encoding($col,"UTF-8");
            }
            if($row[0] != ""){
                $cargo = new CargosModel();
                $cargo->nombreCargo = $row[0];
                $save = $cargo->save();
            }
            
        }
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", agregó cargos por archivo plano");

        return redirect('/cargos');
    }
    

    public function getFormAdd() {
        return view('/cargos/addCargo');
    }

    public function create(CargosRequest $request) {
        $cargo = new CargosModel();
        $cargo->nombreCargo = $request->nombreCargo;
        $save = $cargo->save();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", agregó un nuevo cargo");

        if ($save) {
            $success = true;
            $mensaje = "Nomina de empresa agregada correctamente";
        } else {
            $success = true;
            $mensaje = "Error al agregar nomina de empresa";
        }
        return response()->json(["success" => $success, "mensaje" => $mensaje]);
    }

    public function edit($id) {
        try {
            $cargo = CargosModel::findOrFail($id);
            return view('/cargos/editCargo', [
                'cargos' => $cargo
            ]);
		}
		catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe un nomina de empresa con este ID"]);
		}
    }

    public function detail($id) {
        try {
            $cargo = CargosModel::findOrFail($id);
            return view('/cargos/detailCargo', [
                'cargos' => $cargo
            ]);
		}
		catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe una nomina de empresa con este ID"]);
		}
    }

    public function update(Request $request, $id) {
        try {
            $cargo = CargosModel::findOrFail($id);
            $cargo->nombreCargo = $request->nombreCargo;
            $save = $cargo->save();
            $dataUsu = UsuarioController::dataAdminLogueado();
            Log::channel('gesath')->info("El usuario ".$dataUsu->email.", modificó un nuevo cargo");
            if ($save) {
                $success = true;
                $mensaje = "Nomina de empresa actualizada correctamente";
            } else {
                $success = true;
                $mensaje = "Error al actualizar nomina de empresa";
            }
            return response()->json(["success" => $success, "mensaje" => $mensaje]);
            }
		catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe una nomina de empresa con este ID"]);
		}
    }

    public function delete($id) {
        try{
            $cargo = CargosModel::findOrFail($id);
            if($cargo->delete()){
                $dataUsu = UsuarioController::dataAdminLogueado();
                Log::channel('gesath')->info("El usuario ".$dataUsu->email.", eliminó un nuevo cargo");
                $success = true;
                $mensaje = "Nomina de empresa eliminada con exito";
            } else {
                $success = false;
                $mensaje = "Error al eliminar nomina de empresa";
            }
			return response()->json(["success" => $success, "mensaje" => $mensaje]);
		} catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe una nomina de empresa con este ID"]);
        }
    }
}
