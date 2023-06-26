<?php

namespace App\Http\Controllers;

use App\Concepto;
use App\ConceptoWO;
use App\Http\Requests\ConceptoWORequest;
use Facade\FlareClient\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConceptoWOController extends Controller
{
    public function index() {
        $conceptos = ConceptoWO::all();
        $usu = UsuarioController::dataAdminLogueado();
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu de conceptos World Office");

        return view('/conceptos_wo/index', [
            'conceptos' => $conceptos,
            'dataUsu' => $usu
        ]);
    }

    public function create(ConceptoWORequest $request){
        $conceptoWO = new ConceptoWO();
        $conceptoWO->nombre = $request->nombre;
        $conceptoWO->unidad_medida = $request->unidad_medida;
        $conceptoWO->save();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", agregó el concepto World Office ".$conceptoWO->nombre);

        return redirect()->route('conceptos_wo.index')->with('message', 'Concepto creado correctamente!');
    }
    
    public function update($id,ConceptoWORequest $request){
        $conceptoWO = ConceptoWO::find($id);
        $conceptoWO->nombre = $request->nombre;
        $conceptoWO->unidad_medida = $request->unidad_medida;
        $conceptoWO->save();
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", modificó el concepto World Office ".$conceptoWO->nombre);

        return redirect()->route('conceptos_wo.index')->with('message', 'Concepto modificado correctamente!');
    }

    public function delete($id){
        //Verificar si existen conceptos relacionados a este concepto
        $conceptos = Concepto::where("fk_concepto_wo","=",$id)->get();
        if(sizeof($conceptos) > 0){
            return redirect()->route('conceptos_wo.index')->withErrors(
                ['error' => 'No se puede eliminar el concepto de World Office esta relacionado con algun concepto del sistema']
            );
        }
        $conceptoWO = ConceptoWO::find($id);
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", eliminó el concepto World Office ".$conceptoWO->nombre);
        $conceptoWO->delete();
        return redirect()->route('conceptos_wo.index')->with('message', 'Concepto eliminado correctamente!');
    }

    public function formCreate(){
        $dataUsu = UsuarioController::dataAdminLogueado();
        if(!in_array("156",$dataUsu->permisosUsuario)){
            return view('/layouts.respuestaGen',[
                "dataUsu" => $dataUsu,
                "titulo" => "Error no tiene permisos para ver esta sección",
                "mensaje" =>"Error no tiene permisos para ver esta sección"
            ]);
        }

        return view('/conceptos_wo/create', [
            'dataUsu' => $dataUsu
        ]);
    }

    public function formUpdate($id){
        $dataUsu = UsuarioController::dataAdminLogueado();
        $conceptoWO = ConceptoWO::find($id);
        if(!in_array("156",$dataUsu->permisosUsuario)){
            return view('/layouts.respuestaGen',[
                "dataUsu" => $dataUsu,
                "titulo" => "Error no tiene permisos para ver esta sección",
                "mensaje" =>"Error no tiene permisos para ver esta sección"
            ]);
        }

        return view('/conceptos_wo/update', [
            'dataUsu' => $dataUsu,
            'conceptoWO' => $conceptoWO
        ]);
    }


}
