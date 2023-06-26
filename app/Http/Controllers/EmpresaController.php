<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\EmpresaRequest;
use App\EmpresaModel;
use App\ActividadEconomicaModel;
use App\TipoAportanteModel;
use App\TipoCompaniaModel;
use App\TipoIdentificacionModel;
use App\Ubicacion;
use App\CentroCostoEmpresaModel;
use App\TercerosModel;
use App\NominaEmpresaModel;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use League\Csv\Writer;
use SplTempFileObject;

class EmpresaController extends Controller
{
    public function index() {
        $usu = UsuarioController::dataAdminLogueado();
        $empresas = DB::table("empresa", "e")->leftJoin("actividad_economica_decreto_768 as a", "a.id","=","e.fkActividadEconomica768");
        if(isset($usu) && $usu->fkRol == 2){            
            $empresas = $empresas->whereIn("idempresa", $usu->empresaUsuario);
        }
        $empresas = $empresas->orderBy("razonSocial")->get();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu de empresas");
        
    	return view('/empresas.empresas', [
            'empresas' => $empresas,
            'dataUsu' => $usu
        ]);
    }

    public function exportar(){

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", exportó un archivo plano con de las empresas");

        $empresas = DB::table("empresa","e")
        ->select("e.idempresa", "e.razonSocial", "e.dominio", "ti.nombre as tipoidentificacion",
         "e.documento","e.digitoVerificacion","ter_arl.razonSocial as arl","e.direccion","e.paginaWeb", "e.telefonoFijo", "e.celular", 
         "e.email1", "e.representanteLegal")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=","e.fkTipoIdentificacion")
        ->join("tercero as ter_arl","ter_arl.idTercero", "=","e.fkTercero_ARL");
        if(isset($usu) && $usu->fkRol == 2){            
            $empresas = $empresas->whereIn("e.idempresa", $usu->empresaUsuario);
        }
        $empresas = $empresas->get();

        $arrDef = array([
            "idEmpresa",
            "Razon Social",
            "Dominio",
            "Tipo Identificación",
            "Documento",
            "Digito verificación",
            "ARL",
            "Dirección",
            "Pagina Web",
            "Telefono Fijo",
            "Celular",
            "Correo",
            "Representante Legal"
        ]);
        foreach ($empresas as $empresa){
            array_push($arrDef, [
                $empresa->idempresa,
                $empresa->razonSocial,
                $empresa->dominio,
                $empresa->tipoidentificacion,
                $empresa->documento,
                $empresa->digitoVerificacion,
                $empresa->arl,
                $empresa->direccion,
                $empresa->paginaWeb,
                $empresa->telefonoFijo,
                $empresa->celular,
                $empresa->email1,
                $empresa->representanteLegal
            ]);
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename=conceptos.csv');

        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->setDelimiter(';');
        $csv->insertAll($arrDef);
        $csv->setOutputBOM(Reader::BOM_UTF8);
        $csv->output('empresas.csv');
    }


    public function indexPermisos($idEmpresa) {

        $empresas = DB::table("empresa")->where("idEmpresa","=",$idEmpresa)->first();
        
        $usu = UsuarioController::dataAdminLogueado();
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu de administrar permisos para la empresa ".$idEmpresa);
        $permisos = explode(",",$empresas->permisosGenerales);
    	return view('/empresas.permisos', [
            'empresas' => $empresas,
            'dataUsu' => $usu,
            "permisos" => $permisos,
            "idEmpresa" => $idEmpresa
        ]);
    } 
    public function updatePermisos(Request $req){

            
        
       
        $permisosGenerales = "";
        if(isset($req->permiso1)){
            $permisosGenerales.="1,";
        }
        else{
            $permisosGenerales.="0,";
        }

        if(isset($req->permiso2)){
            $permisosGenerales.="1,";
        }
        else{
            $permisosGenerales.="0,";
        }

        if(isset($req->permiso3)){
            $permisosGenerales.="1,";
        }
        else{
            $permisosGenerales.="0,";
        }

        if(isset($req->permiso4)){
            $permisosGenerales.="1,";
        }
        else{
            $permisosGenerales.="0,";
        }

        if(isset($req->permiso5)){
            $permisosGenerales.="1,";
        }
        else{
            $permisosGenerales.="0,";
        }

        if(isset($req->permiso6)){
            $permisosGenerales.="1,";
        }
        else{
            $permisosGenerales.="0,";
        }

        if(isset($req->permiso7)){
            $permisosGenerales.="1";
        }
        else{
            $permisosGenerales.="0";
        }

        $actualizar = DB::table("empresa")->where("idEmpresa","=",$req->idEmpresa)->update([
            "permisosGenerales" => $permisosGenerales
        ]);
        
        
        

        if ($actualizar) {
            $dataUsu = UsuarioController::dataAdminLogueado();
            Log::channel('gesath')->info("El usuario ".$dataUsu->email.", actualizó los permisos para la empresa ".$req->idEmpresa);
            $success = true;
            $mensaje = "Empresa actualizada correctamente";
        } else {
            $success = false;
            $mensaje = "Error al actualizar empresa";
        }
        return response()->json(['success' => $success, 'mensaje' => $mensaje]);
    }
    


    function getFormAdd() {
        $actEconomicas = ActividadEconomicaModel::all();
        $tipoIdent = TipoIdentificacionModel::all();
        $tipoApor = TipoAportanteModel::all();
        $tipoComp = TipoCompaniaModel::all();
        $ubicaciones = Ubicacion::all();
        $terceroArl = TercerosModel::where('fk_actividad_economica', '=', 1)->get();
        $paises = Ubicacion::where('fkTipoUbicacion', '=', 1)->get();
        $periocidad = DB::table("periocidad")->whereIn("per_id",[4,3])->orderBy("per_nombre")->get();
        $riesgos = DB::table("actividad_economica_decreto_768")->select("riesgo")
        ->distinct()
        ->orderBy("riesgo")
        ->get();          

    	return view('/empresas.addEmpresa', [
            'riesgos' => $riesgos,
            'actEconomicas' => $actEconomicas,
            'paises' => $paises,
            'terceroArl' => $terceroArl,
            'tipoIdent' => $tipoIdent,
            'tipoApor' => $tipoApor,
            'tipoComp' => $tipoComp,
            'ubicaciones' => $ubicaciones,
            "periocidad" => $periocidad
        ]);
    }

    public function create(EmpresaRequest $request) {
        $empresas = new EmpresaModel();
        
        // Validar subida de logo de empresa

        if ($request->hasFile("logoEmpresa")) {
            $image = $request->file('logoEmpresa');
            $name = "imagen_" . time() . '.' . $image->getClientOriginalExtension();
            $destinationPath = storage_path('app/public/logosEmpresas');
            $image->move($destinationPath, $name);
            $empresas->logoEmpresa = $name;
        } else {
            $empresas->logoEmpresa = '';
        }

        $empresas->fkTipoCompania = $request->fkTipoCompania;
        $empresas->fkTipoAportante = $request->fkTipoAportante;
        $empresas->razonSocial = $request->razonSocial;
        $empresas->sigla = $request->sigla;
        $empresas->dominio = $request->dominio;
        $empresas->fkTipoIdentificacion = $request->fkTipoIdentificacion;
        
        $empresas->representanteLegal = $request->representanteLegal;
        $empresas->docRepresentante = $request->docRepresentante;
        $empresas->numDocRepresentante = $request->numDocRepresentante;
        
        
        $empresas->fkUbicacion = $request->fkUbicacion;
        $empresas->direccion = $request->direccion;
        $empresas->paginaWeb = $request->paginaWeb;
        $empresas->telefonoFijo = $request->telefonoFijo;
        $empresas->celular = $request->celular;
        $empresas->email1 = $request->email1;
        $empresas->email2 = $request->email2;
        $empresas->documento = $request->documento;
        $empresas->digitoVerificacion = $request->digitoVerificacion;
        $empresas->fkTercero_ARL = $request->fkTercero_ARL;
        $empresas->exento = ($request->exento ?? 0);
        $empresas->vacacionesNegativas = ($request->vacacionesNegativas ?? 0);
        $empresas->LRN_cesantias = ($request->LRN_cesantias ?? 0);        
        $empresas->pagoParafiscales = ($request->pagoParafiscales ?? 0);
        $empresas->fkPeriocidadRetencion = $request->fkPeriocidadRetencion;
        $empresas->SoftwareDianId = $request->SoftwareDianId;
        $empresas->SoftwareTestSetId = $request->SoftwareTestSetId;
        $empresas->TipAmbNominaElectronica = $request->TipAmbNominaElectronica;
        $empresas->PrefijoNominaElectronica = $request->PrefijoNominaElectronica;
        $empresas->ConsecutivoNominaElectronica = ($request->ConsecutivoNominaElectronica ?? 1);

        $empresas->PrefijoNominaElectronicaReemplazo = $request->PrefijoNominaElectronicaReemplazo;
        $empresas->ConsecutivoNominaElectronicaReemplazo = ($request->ConsecutivoNominaElectronicaReemplazo ?? 1);

        $empresas->PrefijoNominaElectronicaEliminacion = $request->PrefijoNominaElectronicaEliminacion;
        $empresas->ConsecutivoNominaElectronicaEliminacion = ($request->ConsecutivoNominaElectronicaEliminacion ?? 1);

        $actividad = DB::table("actividad_economica_decreto_768")
        ->where("riesgo","=",$request->riesgo768)
        ->where("ciiu","=",$request->ciiu768)
        ->where("codigo","=",$request->codigo768)
        ->first();
        $empresas->fkActividadEconomica768 = ($actividad->id ?? null);
        

        $insert = $empresas->save();
        if ($insert) {
            $dataUsu = UsuarioController::dataAdminLogueado();
            Log::channel('gesath')->info("El usuario ".$dataUsu->email.", creó una nueva empresa con id:".$empresas->idempresa);
            // Creamos centro de costo
            if(isset($request->nom_cen_cost)){
                $cenCost = new CentroCostoEmpresaModel();
                $cenCost->nombre = $request->nom_cen_cost;
                $cenCost->fkEmpresa = $empresas->idempresa;
                $cenCost->id_uni_centro = $empresas->id_uni_centro;
                $cenCost->save();
            }
            

            //Creamos nomina
            $nomEmpresa = new NominaEmpresaModel();
            $nomEmpresa->nombre = $request->razonSocial;
            $nomEmpresa->tipoPeriodo = 'DIAS';
            $nomEmpresa->periodo = $request->periodo;
            $nomEmpresa->diasCesantias = $request->diasCesantias;
            $nomEmpresa->fkEmpresa = $empresas->idempresa;
            $nomEmpresa->id_uni_nomina = $empresas->id_uni_nomina;
            $nomEmpresa->save();
            
            $usu = UsuarioController::dataAdminLogueado();
            if(isset($usu) && $usu->fkRol == 2){            
                $user_emp = DB::table("user_empresa")
                ->where("fkUser","=",$usu->id)
                ->where("fkEmpresa","=",$empresas->idempresa)
                ->first();
                if(!isset($user_emp)){
                    DB::table("user_empresa")->insert([
                        "fkUser" => $usu->id,
                        "fkEmpresa" => $empresas->idempresa
                    ]);
                }   

            }


            $success = true;
            $mensaje = "Empresa creado exitosamente";
        } else {
            $success = false;
            $mensaje = "Error al crear tercero";
        }
        return response()->json(['success' => $success, 'mensaje' => $mensaje]);
    }

    public function edit($id) {
        try {
            $empresa = EmpresaModel::select(
                '*',
                'empresa.fkUbicacion as ubi',
                DB::raw('(select fkUbicacion from ubicacion where idubicacion = ubi) as ubi_dos'),
                DB::raw('(select fkUbicacion from ubicacion where idubicacion = ubi_dos) as ubi_tres')
            )
            ->where('empresa.idempresa', $id)
            ->first();

            $actEconomicas = ActividadEconomicaModel::all();
            $tipoIdent = TipoIdentificacionModel::all();
            $tipoApor = TipoAportanteModel::all();
            $tipoComp = TipoCompaniaModel::all();
            $terceroArl = TercerosModel::where('fk_actividad_economica', '=', 1)->get();
            $paises = Ubicacion::where('fkTipoUbicacion', '=', 1)->get();
            $deptos = Ubicacion::where('fkTipoUbicacion', '=', 2)->get();
            $ciudades = Ubicacion::where('fkTipoUbicacion', '=', 3)->get();
            $periocidad = DB::table("periocidad")->whereIn("per_id",[4,3])->orderBy("per_nombre")->get();
            $nominasQuincenlaes = DB::table("nomina")
            ->where("fkEmpresa","=",$id)
            ->where("periodo","=","15")->first();

            
            $riesgoSelect = "0";
            $ciiuSelect = "";
            $codigoSelect = "";
            $actividad_nombre = "";

            $datosRiesgo = DB::table("actividad_economica_decreto_768")->where("id","=",$empresa->fkActividadEconomica768)->first();
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
           
            return view('/empresas.editEmpresa', [
                'riesgos' => $riesgos,
                'codigos' => $codigos,
                'riesgoSelect' => $riesgoSelect,
                'ciiuSelect' => $ciiuSelect,
                'codigoSelect' => $codigoSelect,
                'actividad_nombre' => $actividad_nombre,
                'empresa' => $empresa,
                'actEconomicas' => $actEconomicas,
                'paises' => $paises,
                'deptos' => $deptos,
                'ciudades' => $ciudades,
                'terceroArl' => $terceroArl,
                'tipoIdent' => $tipoIdent,
                'tipoApor' => $tipoApor,
                'tipoComp' => $tipoComp,
                "nominasQuincenlaes" => $nominasQuincenlaes,
                'periocidad' => $periocidad
            ]);
		}
		catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe una empresa con este ID"]);
		}
    }

    public function actividades_economicas($ciiu){
        $riesgos = DB::table("actividad_economica_decreto_768")->select("riesgo")
        ->where("ciiu","=",$ciiu)
        ->distinct()
        ->orderBy("riesgo")
        ->get();
        $riesgosEnv = [];
        foreach($riesgos as $riesgo){
            array_push($riesgosEnv, $riesgo->riesgo);
        }
        

        return response()->json(["success" => true, "riesgos" => $riesgosEnv]);
    }

    public function actividad_economica_cod($riesgo,$ciiu){
        $codigos = DB::table("actividad_economica_decreto_768")
        ->select("codigo")
        ->where("riesgo","=",$riesgo)
        ->where("ciiu","=",$ciiu) 
        ->orderBy("codigo")
        ->get();
        $codigosEnv = [];
        foreach($codigos as $codigo){
            array_push($codigosEnv, $codigo->codigo);
        }
     
        return response()->json(["success" => true, "codigos" => $codigosEnv]);
    }
    
    public function actividad_economica($riesgo,$ciiu, $codigo){
        $actividad = DB::table("actividad_economica_decreto_768")
        ->where("riesgo","=",$riesgo)
        ->where("ciiu","=",$ciiu)
        ->where("codigo","=",$codigo)
        ->first();

        $nom_actividad = "No se encontró esta actividad económica";
        if(isset($actividad)){
            $nom_actividad = $actividad->descripcion;
        }
        return response()->json(["success" => true, "nom_actividad" => $nom_actividad]);
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

    public function detail($id) {
        try {
            $empresa = EmpresaModel::select(
                '*',
                'empresa.fkUbicacion as ubi',
                DB::raw('(select fkUbicacion from ubicacion where idubicacion = ubi) as ubi_dos'),
                DB::raw('(select fkUbicacion from ubicacion where idubicacion = ubi_dos) as ubi_tres')
            )
            ->where('empresa.idempresa', $id)
            ->first();
            $actEconomicas = ActividadEconomicaModel::all();
            $tipoIdent = TipoIdentificacionModel::all();
            $tipoApor = TipoAportanteModel::all();
            $tipoComp = TipoCompaniaModel::all();
            $paises = Ubicacion::where('fkTipoUbicacion', '=', 1)->get();
            $deptos = Ubicacion::where('fkTipoUbicacion', '=', 2)->get();
            $ciudades = Ubicacion::where('fkTipoUbicacion', '=', 3)->get();
            $terceroArl = TercerosModel::where('fk_actividad_economica', '=', 1)->get();
            $periocidad = DB::table("periocidad")->whereIn("per_id",[4,3])->orderBy("per_nombre")->get();
            $nominasQuincenlaes = DB::table("nomina")
            ->where("fkEmpresa","=",$id)
            ->where("periodo","=","15")->first();

            $riesgoSelect = "0";
            $ciiuSelect = "";
            $codigoSelect = "";
            $actividad_nombre = "";

            $datosRiesgo = DB::table("actividad_economica_decreto_768")->where("id","=",$empresa->fkActividadEconomica768)->first();
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

            return view('/empresas.detalleEmpresa', [
                'riesgos' => $riesgos,
                'codigos' => $codigos,
                'riesgoSelect' => $riesgoSelect,
                'ciiuSelect' => $ciiuSelect,
                'codigoSelect' => $codigoSelect,
                'actividad_nombre' => $actividad_nombre,
                'empresa' => $empresa,
                'actEconomicas' => $actEconomicas,
                'terceroArl' => $terceroArl,
                'tipoIdent' => $tipoIdent,
                'tipoApor' => $tipoApor,
                'tipoComp' => $tipoComp,
                'paises' => $paises,
                'deptos' => $deptos,
                'ciudades' => $ciudades,
                'periocidad' => $periocidad,
                'nominasQuincenlaes' => $nominasQuincenlaes
            ]);
		}
		catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe una empresa con este ID"]);
		}
    }

    public function update(Request  $request, $id) {
        try {
            $empresas = EmpresaModel::findOrFail($id);
            
            if ($request->hasFile("logoEmpresa")) {
                $image = $request->file('logoEmpresa');
                $name = "imagen_" . time() . '.' . $image->getClientOriginalExtension();
                $destinationPath = storage_path('app/public/logosEmpresas');
                $image->move($destinationPath, $name);
                $empresas->logoEmpresa = $name;
            } else {
                $empresas->logoEmpresa = $empresas->logoEmpresa;
            }

            $empresas->fkTipoCompania = $request->fkTipoCompania;
            $empresas->fkTipoAportante = $request->fkTipoAportante;
            $empresas->razonSocial = $request->razonSocial;
            $empresas->sigla = $request->sigla;
            $empresas->dominio = $request->dominio;
            $empresas->fkTipoIdentificacion = $request->fkTipoIdentificacion;

            $empresas->representanteLegal = $request->representanteLegal;
            $empresas->docRepresentante = $request->docRepresentante;
            $empresas->numDocRepresentante = $request->numDocRepresentante;
            
            $empresas->fkUbicacion = $request->fkUbicacion;
            $empresas->direccion = $request->direccion;
            $empresas->paginaWeb = $request->paginaWeb;
            $empresas->telefonoFijo = $request->telefonoFijo;
            $empresas->celular = $request->celular;
            $empresas->email1 = $request->email1;
            $empresas->email2 = $request->email2;
            $empresas->documento = $request->documento;
            $empresas->digitoVerificacion = $request->digitoVerificacion;
            $empresas->fkTercero_ARL = $request->fkTercero_ARL;
            $empresas->exento = ($request->exento ?? 0);
            $empresas->vacacionesNegativas = ($request->vacacionesNegativas ?? 0);
            $empresas->LRN_cesantias = ($request->LRN_cesantias ?? 0);
            $empresas->pagoParafiscales = ($request->pagoParafiscales ?? 0);
            $empresas->fkPeriocidadRetencion = $request->fkPeriocidadRetencion;
            $empresas->SoftwareDianId = $request->SoftwareDianId;
            $empresas->SoftwareTestSetId = $request->SoftwareTestSetId;
            $empresas->TipAmbNominaElectronica = $request->TipAmbNominaElectronica;
            $empresas->PrefijoNominaElectronica = $request->PrefijoNominaElectronica;
            $empresas->ConsecutivoNominaElectronica = $request->ConsecutivoNominaElectronica;
            
            $empresas->PrefijoNominaElectronicaReemplazo = $request->PrefijoNominaElectronicaReemplazo;
            $empresas->ConsecutivoNominaElectronicaReemplazo = ($request->ConsecutivoNominaElectronicaReemplazo ?? 1);
            
            $empresas->PrefijoNominaElectronicaEliminacion = $request->PrefijoNominaElectronicaEliminacion;
            $empresas->ConsecutivoNominaElectronicaEliminacion = ($request->ConsecutivoNominaElectronicaEliminacion ?? 1);
            $actividad = DB::table("actividad_economica_decreto_768")
            ->where("riesgo","=",$request->riesgo768)
            ->where("ciiu","=",$request->ciiu768)
            ->where("codigo","=",$request->codigo768)
            ->first();
            $empresas->fkActividadEconomica768 = ($actividad->id ?? null);
            
            
            $actualizar = $empresas->save();
            

            if ($actualizar) {
                $dataUsu = UsuarioController::dataAdminLogueado();
                Log::channel('gesath')->info("El usuario ".$dataUsu->email.", modificó la empresa con id:".$id);
                $success = true;
                $mensaje = "Empresa actualizada correctamente";
            } else {
                $success = false;
                $mensaje = "Error al actualizar empresa";
            }
            return response()->json(["success" => $success, "mensaje" => $mensaje]);
		}
		catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe una empresa con este ID"]);
		}
    }

    public function delete($id) {
        try{
            $empresa = EmpresaModel::findOrFail($id);
            NominaEmpresaModel::where('fkEmpresa', $id)->delete();
			if($empresa->delete()){
                $dataUsu = UsuarioController::dataAdminLogueado();
                Log::channel('gesath')->info("El usuario ".$dataUsu->email.", eliminó la empresa con id:".$id);
				$success = true;
				$mensaje = "Empresa eliminada con exito";
			} else {
				$success = false;
				$mensaje = "Error al eliminar empresa";
			}
			return response()->json(["success" => $success, "mensaje" => $mensaje]);
		} catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe una empresa con este ID"]);
		}
    }
}