<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Concepto;
use App\ConceptoWO;
use App\Variable;
use Illuminate\Support\Facades\Log;
use League\Csv\Writer;
use League\Csv\Reader;
use SplTempFileObject;          


class ConceptoController extends Controller
{
    public function index(Request $req){
    	
        $conceptos = Concepto::select('concepto.idconcepto','concepto.nombre','concepto.subTipo',
        								'concepto.numRetefuente','n.nombre AS naturaleza','tc.nombre AS tipoConcepto','gne.codigo')
        						->join('naturalezaconcepto AS n', 'n.idnaturalezaConcepto', '=', 'concepto.fkNaturaleza')
                                ->join('tipo_concepto AS tc', 'tc.idtipo_concepto', '=', 'concepto.fkTipoConcepto')
                                ->join('grupos_nomina_electronica AS gne', 'gne.idGrupoNominaElectronica', '=', 'concepto.fkGrupoNomina',"left");
        $arrConsulta = array();
        if(isset($req->nombre)){
            $conceptos->where("concepto.nombre", "LIKE", "%".$req->nombre."%");
            $arrConsulta["nombre"] = $req->nombre;
        }
        if(isset($req->naturaleza)){
            $conceptos->where("concepto.fkNaturaleza", "=", $req->naturaleza);
            $arrConsulta["naturaleza"] = $req->naturaleza;
        }

        $naturalezas = DB::table('naturalezaconcepto')->get();
        $conceptos = $conceptos->orderBy("concepto.nombre")->get();

        $usu = UsuarioController::dataAdminLogueado();
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu de conceptos");
    	return view('/concepto.concepto', [
            'conceptos' => $conceptos, 
            'naturalezas' => $naturalezas, 
            'req' => $req, 
            'arrConsulta' => $arrConsulta,
            'dataUsu' => $usu
        ]);
    }
    
    public function getFormAdd(){
        $naturalezas = DB::table('naturalezaconcepto')->get();
        $tiposConcepto = DB::table('tipo_concepto')->get();
        $variables = Variable::orderBy("nombre")->get();
        
        $gruposConcepto = DB::table("grupoconcepto")->orderBy("nombre")->get();

        $gruposNomina = DB::table("grupos_nomina_electronica")->orderBy("descripcion_corta")->get();

        $conceptosWO = ConceptoWO::orderBy("nombre")->get();

    	return view('/concepto.add', [
            'naturalezas' => $naturalezas, 
            'tiposConcepto' => $tiposConcepto, 
            'variables' => $variables,
            "gruposConcepto" => $gruposConcepto,
            "gruposNomina" => $gruposNomina,            
            "conceptosWO" => $conceptosWO
        ]);
    }

    public function getFormulaConceptoAdd(){
        $variables = Variable::whereIn("fkTipoCampo", ["1","2","4"])->orderBy("nombre")->get();
        $tipoOperaciones = DB::table('tipooperacion')->get();
        $conceptos = DB::table('concepto')->get();
        $grupoconceptos = DB::table('grupoconcepto')->get();

        return view('/concepto.formulaConcepto.addFormulaConcepto', ['variables' => $variables, 
                                                            'tipoOperaciones' => $tipoOperaciones,
                                                            'conceptos' => $conceptos,
                                                            'grupoConceptos' => $grupoconceptos
                                                            ]);
    }
    public function getFormulaConceptoMas($idRegistro){
        $variables = Variable::whereIn("fkTipoCampo", ["1","2","4"])->orderBy("nombre")->get();
        $tipoOperaciones = DB::table('tipooperacion')->get();
        $conceptos = DB::table('concepto')->get();
        $grupoconceptos = DB::table('grupoconcepto')->get();

        return view('/concepto.formulaConcepto.masFormulaConcepto', ['variables' => $variables, 
                                                            'tipoOperaciones' => $tipoOperaciones, 
                                                            'conceptos' => $conceptos,
                                                            'grupoConceptos' => $grupoconceptos,
                                                            'idRegistro' => $idRegistro]);
    }

    public function fillFormula(Request $req){
        $html="";
        if($req->tipoInicio == "variable"){
            $html = '<input type="hidden" name="fkVariableInicial" value="'.$req->variableInicial.'" />';
        }
        else if($req->tipoInicio == "valor"){
            $html = '<input type="hidden" name="valorInicial" value="'.$req->valorInicio.'" />';
        }
        else if($req->tipoInicio == "grupo"){
            $html = '<input type="hidden" name="grupoInicial" value="'.$req->grupoInicial.'" />';
        }
        else if($req->tipoInicio == "concepto"){
            $html = '<input type="hidden" name="conceptoInicial" value="'.$req->conceptoInicial.'" />';
        }

        foreach ($req->tipoOperacion as $key => $operacion) {
            $html .= '<input type="hidden" name="fkTipoOperacion[]" value="'.$operacion.'" />';
            $html .= '<input type="hidden" name="fkVariableFinal[]" value="'.$req->variableFin[$key].'" />';
            $html .= '<input type="hidden" name="valorFinal[]" value="'.$req->valorFin[$key].'" />';
            $html .= '<input type="hidden" name="grupoFinal[]" value="'.$req->grupoFin[$key].'" />';
            $html .= '<input type="hidden" name="conceptoFinal[]" value="'.$req->conceptoFin[$key].'" />';
        }

        return response()->json([
            "success" => true,
            "html" => $html
        ]);
    }

    public function getFormulaConceptoSSAdd(){
        $variables = Variable::whereIn("fkTipoCampo", ["1","2","4"])->orderBy("nombre")->get();
        $tipoOperaciones = DB::table('tipooperacion')->get();
        $conceptos = DB::table('concepto')->get();
        $grupoconceptos = DB::table('grupoconcepto')->get();

        return view('/concepto.formulaConceptoSS.addFormulaConcepto', ['variables' => $variables, 
                                                            'tipoOperaciones' => $tipoOperaciones,
                                                            'conceptos' => $conceptos,
                                                            'grupoConceptos' => $grupoconceptos
                                                            ]);
    }
    public function getFormulaConceptoSSMas($idRegistro){
        $variables = Variable::whereIn("fkTipoCampo", ["1","2","4"])->orderBy("nombre")->get();
        $tipoOperaciones = DB::table('tipooperacion')->get();
        $conceptos = DB::table('concepto')->get();
        $grupoconceptos = DB::table('grupoconcepto')->get();

        return view('/concepto.formulaConceptoSS.masFormulaConcepto', ['variables' => $variables, 
                                                            'tipoOperaciones' => $tipoOperaciones, 
                                                            'conceptos' => $conceptos,
                                                            'grupoConceptos' => $grupoconceptos,
                                                            'idRegistro' => $idRegistro]);
    }

    public function fillFormulaSS(Request $req){
        $html="";
        if($req->tipoInicio == "variable"){
            $html = '<input type="hidden" name="fkVariableInicialSS" value="'.$req->variableInicial.'" />';
        }
        else if($req->tipoInicio == "valor"){
            $html = '<input type="hidden" name="valorInicialSS" value="'.$req->valorInicio.'" />';
        }
        else if($req->tipoInicio == "grupo"){
            $html = '<input type="hidden" name="grupoInicialSS" value="'.$req->grupoInicial.'" />';
        }
        else if($req->tipoInicio == "concepto"){
            $html = '<input type="hidden" name="conceptoInicialSS" value="'.$req->conceptoInicial.'" />';
        }

        foreach ($req->tipoOperacion as $key => $operacion) {
            $html .= '<input type="hidden" name="fkTipoOperacionSS[]" value="'.$operacion.'" />';
            $html .= '<input type="hidden" name="fkVariableFinalSS[]" value="'.$req->variableFin[$key].'" />';
            $html .= '<input type="hidden" name="valorFinalSS[]" value="'.$req->valorFin[$key].'" />';
            $html .= '<input type="hidden" name="grupoFinalSS[]" value="'.$req->grupoFin[$key].'" />';
            $html .= '<input type="hidden" name="conceptoFinalSS[]" value="'.$req->conceptoFin[$key].'" />';
        }

        return response()->json([
            "success" => true,
            "html" => $html
        ]);
    }
    public function getFormulaConceptoSSMod($idConcepto){
        $variables = Variable::whereIn("fkTipoCampo", ["1","2","4"])->orderBy("nombre")->get();
        $tipoOperaciones = DB::table('tipooperacion')->get();
        $conceptos = DB::table('concepto')->get();
        $grupoconceptos = DB::table('grupoconcepto')->get();

        $formulaConcepto = DB::table('formulaconcepto_ss', 'fc')->where("fkConcepto", "=", $idConcepto)->get();
        if(sizeof($formulaConcepto) == 0){
            return view('/concepto.formulaConceptoSS.addFormulaConcepto', ['variables' => $variables, 
                'tipoOperaciones' => $tipoOperaciones,
                'conceptos' => $conceptos,
                'grupoConceptos' => $grupoconceptos
            ]);
        }
        else{
            return view('/concepto.formulaConceptoSS.modFormulaConcepto', [
                'variables' => $variables, 
                'tipoOperaciones' => $tipoOperaciones,
                'conceptos' => $conceptos,
                'grupoConceptos' => $grupoconceptos,
                'formulaConcepto' => $formulaConcepto
            ]);
        }
    


        
    }

    public function getFormulaConceptoSSVer($idConcepto){
        $variables = Variable::whereIn("fkTipoCampo", ["1","2","4"])->orderBy("nombre")->get();
        $tipoOperaciones = DB::table('tipooperacion')->get();
        $conceptos = DB::table('concepto')->get();
        $grupoconceptos = DB::table('grupoconcepto')->get();

        $formulaConcepto = DB::table('formulaconcepto_ss', 'fc')->where("fkConcepto", "=", $idConcepto)->get();
        if(sizeof($formulaConcepto) == 0){
            return view('/concepto.formulaConceptoSS.verFormulaConceptoNohay');
        }
        else{
            return view('/concepto.formulaConceptoSS.verFormulaConcepto', [
                'variables' => $variables, 
                'tipoOperaciones' => $tipoOperaciones,
                'conceptos' => $conceptos,
                'grupoConceptos' => $grupoconceptos,
                'formulaConcepto' => $formulaConcepto
            ]);
        }
    


        
    }

    public function insert(Request $req){
    	

    	$concepto = new Concepto;
    	$concepto->nombre = $req->nombre; 
    	$concepto->fkNaturaleza = $req->fkNaturaleza; 
    	$concepto->fkTipoConcepto = $req->fkTipoConcepto; 
        $concepto->subTipo  = $req->subTipo; 
        if($req->fkVariable != ""){
        	$concepto->fkVariable = $req->fkVariable; 
        }        
        $concepto->numRetefuente = $req->numRetefuente; 
        $concepto->generacionAutomatica = $req->generacionAutomatica; 
        $concepto->fkGrupoNomina = $req->fkGrupoNomina; 
        $concepto->fk_concepto_wo = $req->fk_concepto_wo; 
        $concepto->save();
        
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", creó un nuevo concepto");

        if(isset($req->gruposConcepto)){
            foreach($req->gruposConcepto as $key => $grupo){    
                DB::table('grupoconcepto_concepto')->insert([
                    "fkConcepto" => $concepto->idconcepto,
                    "fkGrupoConcepto" => $grupo
                ]);
            }
        }

        if($req->subTipo == "Formula"){
            $arrInsertFormulaConcepto = array("fkConcepto" => $concepto->idconcepto);
            if(isset($req->fkVariableInicial)){
                $arrInsertFormulaConcepto["fkVariableInicial"] = $req->fkVariableInicial;
            }
            if(isset($req->valorInicial)){
                $arrInsertFormulaConcepto["valorInicial"] = $req->valorInicial;
            }
            if(isset($req->grupoInicial)){
                $arrInsertFormulaConcepto["fkGrupoConceptoInicial"] = $req->grupoInicial;
            }
            if(isset($req->conceptoInicial)){
                $arrInsertFormulaConcepto["fkConceptoInicial"] = $req->conceptoInicial;
            }

            $jerarquia = 1;
            if(isset($req->fkTipoOperacion)){
                foreach ($req->fkTipoOperacion as $key => $operacion) {
                
                    $arrInsertFormulaConcepto["fkTipoOperacion"] = $operacion;
                    if(isset($req->fkVariableFinal[$key]) && !empty($req->fkVariableFinal[$key])){
                        $arrInsertFormulaConcepto["fkVariableFinal"] = $req->fkVariableFinal[$key];
                    }
                    if(isset($req->valorFinal[$key])){
                        $arrInsertFormulaConcepto["valorFinal"] = $req->valorFinal[$key];
                    }
                    if(isset($req->grupoFinal[$key]) && !empty($req->grupoFinal[$key])){
                        $arrInsertFormulaConcepto["fkGrupoConceptoFinal"] = $req->grupoFinal[$key];
                    }
                    if(isset($req->conceptoFinal[$key]) && !empty($req->conceptoFinal[$key])){
                        $arrInsertFormulaConcepto["fkConceptoFinal"] = $req->conceptoFinal[$key];
                    }
                    $arrInsertFormulaConcepto["jerarquia"] = $jerarquia;
                    $idFormulaConcepto = DB::table('formulaconcepto')->insertGetId($arrInsertFormulaConcepto, "idformulaConcepto");
                    $arrInsertFormulaConcepto = array("fkConcepto" => $concepto->idconcepto, "fkFormulaConcepto" => $idFormulaConcepto);
                    $jerarquia++;
                }
            }
            
        }
        DB::table('conceptosxtipoliquidacion')->insert([
            "fkConcepto" => $concepto->idconcepto,
            "fkTipoLiquidacion" => "1",
            "tipoRelacion" => "1"
        ]);
        DB::table('conceptosxtipoliquidacion')->insert([
            "fkConcepto" => $concepto->idconcepto,
            "fkTipoLiquidacion" => "2",
            "tipoRelacion" => "1"
        ]);
        DB::table('conceptosxtipoliquidacion')->insert([
            "fkConcepto" => $concepto->idconcepto,
            "fkTipoLiquidacion" => "3",
            "tipoRelacion" => "1"
        ]);
        DB::table('conceptosxtipoliquidacion')->insert([
            "fkConcepto" => $concepto->idconcepto,
            "fkTipoLiquidacion" => "4",
            "tipoRelacion" => "1"
        ]);
        DB::table('conceptosxtipoliquidacion')->insert([
            "fkConcepto" => $concepto->idconcepto,
            "fkTipoLiquidacion" => "5",
            "tipoRelacion" => "1"
        ]);
        DB::table('conceptosxtipoliquidacion')->insert([
            "fkConcepto" => $concepto->idconcepto,
            "fkTipoLiquidacion" => "6",
            "tipoRelacion" => "1"
        ]);
        DB::table('conceptosxtipoliquidacion')->insert([
            "fkConcepto" => $concepto->idconcepto,
            "fkTipoLiquidacion" => "9",
            "tipoRelacion" => "1"
        ]);
       




        return response()->json([
			"success" => true
        ]);


    }
    public function getFormCopy($idConcepto){
        $naturalezas = DB::table('naturalezaconcepto')->get();
        $tiposConcepto = DB::table('tipo_concepto')->get();
        $variables = Variable::orderBy("nombre")->get();
        
        $concepto = DB::table('concepto','c')->where("idconcepto","=", $idConcepto)->first();
        $formulaConcepto = DB::table('formulaconcepto', 'fc')->where("fkConcepto", "=", $idConcepto)->get();

        $gruposConcepto = DB::table("grupoconcepto","gc")
        ->select("gc.*", "gcc.fkConcepto as relacion")
        ->leftjoin('grupoconcepto_concepto as gcc', function($join) use($idConcepto){
            $join->on('gcc.fkGrupoConcepto','=','gc.idgrupoConcepto'); 
            $join->where('gcc.fkConcepto','=',$idConcepto);
        })
        ->orderBy("gc.nombre")->get();

        $gruposNomina = DB::table("grupos_nomina_electronica")->orderBy("descripcion_corta")->get();
        $conceptosWO = ConceptoWO::orderBy("nombre")->get();
    	return view('/concepto.copy', [
            'naturalezas' => $naturalezas,
            'tiposConcepto' => $tiposConcepto,
            'variables' => $variables,
            'formulaConcepto' => $formulaConcepto,
            'concepto' => $concepto,
            "gruposConcepto" => $gruposConcepto,
            "gruposNomina" => $gruposNomina,
            "conceptosWO" => $conceptosWO
        ]);
    }

    public function getFormVer($idConcepto){
        $naturalezas = DB::table('naturalezaconcepto')->get();
        $tiposConcepto = DB::table('tipo_concepto')->get();
        $variables = Variable::orderBy("nombre")->get();
        
        $concepto = DB::table('concepto','c')->where("idconcepto","=", $idConcepto)->first();
        $formulaConcepto = DB::table('formulaconcepto', 'fc')->where("fkConcepto", "=", $idConcepto)->get();

        $gruposConcepto = DB::table("grupoconcepto","gc")
        ->select("gc.*", "gcc.fkConcepto as relacion")
        ->leftjoin('grupoconcepto_concepto as gcc', function($join) use($idConcepto){
            $join->on('gcc.fkGrupoConcepto','=','gc.idgrupoConcepto'); 
            $join->where('gcc.fkConcepto','=',$idConcepto);
        })
        ->orderBy("gc.nombre")->get();

        $gruposNomina = DB::table("grupos_nomina_electronica")->orderBy("descripcion_corta")->get();
        $conceptosWO = ConceptoWO::orderBy("nombre")->get();

    	return view('/concepto.ver', [
            'naturalezas' => $naturalezas,
            'tiposConcepto' => $tiposConcepto,
            'variables' => $variables,
            'formulaConcepto' => $formulaConcepto,
            'concepto' => $concepto,
            "gruposConcepto" => $gruposConcepto,
            "gruposNomina" => $gruposNomina,
            "conceptosWO" => $conceptosWO
        ]);
    }
    public function getFormEdit($idConcepto){
        $naturalezas = DB::table('naturalezaconcepto')->get();
        $tiposConcepto = DB::table('tipo_concepto')->get();
        $variables = Variable::orderBy("nombre")->get();
        
        $concepto = DB::table('concepto','c')->where("idconcepto","=", $idConcepto)->first();
        $formulaConcepto = DB::table('formulaconcepto', 'fc')->where("fkConcepto", "=", $idConcepto)->get();
        $formulaConceptoSS = DB::table('formulaconcepto_ss', 'fc')->where("fkConcepto", "=", $idConcepto)->get();

        $gruposConcepto = DB::table("grupoconcepto","gc")
        ->select("gc.*", "gcc.fkConcepto as relacion")
        ->leftjoin('grupoconcepto_concepto as gcc', function($join) use($idConcepto){
            $join->on('gcc.fkGrupoConcepto','=','gc.idgrupoConcepto'); 
            $join->where('gcc.fkConcepto','=',$idConcepto);
        })
        ->orderBy("gc.nombre")->get();
        
        $gruposNomina = DB::table("grupos_nomina_electronica")->orderBy("descripcion_corta")->get();
        $conceptosWO = ConceptoWO::orderBy("nombre")->get();

    	return view('/concepto.edit', [
            'naturalezas' => $naturalezas,
            'tiposConcepto' => $tiposConcepto,
            'variables' => $variables,
            'formulaConcepto' => $formulaConcepto,
            'formulaConceptoSS' => $formulaConceptoSS,
            'concepto' => $concepto,
            "gruposConcepto" => $gruposConcepto,
            "gruposNomina" => $gruposNomina,
            "conceptosWO" => $conceptosWO
        ]);
    }
    public function getFormulaConceptoMod($idConcepto){
        $variables = Variable::whereIn("fkTipoCampo", ["1","2","4"])->orderBy("nombre")->get();
        $tipoOperaciones = DB::table('tipooperacion')->get();
        $conceptos = DB::table('concepto')->get();
        $grupoconceptos = DB::table('grupoconcepto')->get();

        $formulaConcepto = DB::table('formulaconcepto', 'fc')->where("fkConcepto", "=", $idConcepto)->get();
        if(sizeof($formulaConcepto) == 0){
            return view('/concepto.formulaConcepto.addFormulaConcepto', ['variables' => $variables, 
                'tipoOperaciones' => $tipoOperaciones,
                'conceptos' => $conceptos,
                'grupoConceptos' => $grupoconceptos
            ]);
        }
        else{
            return view('/concepto.formulaConcepto.modFormulaConcepto', [
                'variables' => $variables, 
                'tipoOperaciones' => $tipoOperaciones,
                'conceptos' => $conceptos,
                'grupoConceptos' => $grupoconceptos,
                'formulaConcepto' => $formulaConcepto
            ]);
        }
    


        
    }

    public function getFormulaConceptoVer($idConcepto){
        $variables = Variable::whereIn("fkTipoCampo", ["1","2","4"])->orderBy("nombre")->get();
        $tipoOperaciones = DB::table('tipooperacion')->get();
        $conceptos = DB::table('concepto')->get();
        $grupoconceptos = DB::table('grupoconcepto')->get();

        $formulaConcepto = DB::table('formulaconcepto', 'fc')->where("fkConcepto", "=", $idConcepto)->get();
        if(sizeof($formulaConcepto) == 0){
            return view('/concepto.formulaConcepto.verFormulaConceptoNohay');
        }
        else{
            return view('/concepto.formulaConcepto.verFormulaConcepto', [
                'variables' => $variables, 
                'tipoOperaciones' => $tipoOperaciones,
                'conceptos' => $conceptos,
                'grupoConceptos' => $grupoconceptos,
                'formulaConcepto' => $formulaConcepto
            ]);
        }
    


        
    }
    public function update(Request $req){

        
        $concepto = Concepto::find($req->idconcepto);
        
        $concepto->nombre = $req->nombre; 
    	$concepto->fkNaturaleza = $req->fkNaturaleza; 
    	$concepto->fkTipoConcepto = $req->fkTipoConcepto; 
        $concepto->subTipo  = $req->subTipo; 
        if($req->fkVariable != ""){
        	$concepto->fkVariable = $req->fkVariable; 
        }        
        $concepto->numRetefuente = $req->numRetefuente; 
        $concepto->generacionAutomatica = $req->generacionAutomatica; 
        $concepto->fkGrupoNomina = $req->fkGrupoNomina; 
        $concepto->fk_concepto_wo = $req->fk_concepto_wo; 
        $concepto->save();
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", modificó el concepto con id ".$req->idconcepto);
        if(isset($req->gruposConceptoRelacion)){
            foreach($req->gruposConceptoRelacion as $key => $relacion){    
               
                if($relacion != "0"){

                    $valid=false;
                    foreach($req->gruposConcepto as $grupo){
                        if($grupo==$req->gruposConceptoIds[$key]){
                            $valid=true;
                        }
                    }

                    if(!$valid){            
                        DB::table('grupoconcepto_concepto')
                        ->where("fkConcepto", "=",$concepto->idconcepto)
                        ->where("fkGrupoConcepto", "=", $req->gruposConceptoIds[$key])
                        ->delete();
                    }
                }
                else{
                    $valid=false;
                    if(isset($req->gruposConcepto)){
                        foreach($req->gruposConcepto as $grupo){
                            if($grupo==$req->gruposConceptoIds[$key]){
                                $valid=true;
                            }
                        }
                    }
                    
                    if($valid){
                        DB::table('grupoconcepto_concepto')->insert([
                            "fkConcepto" => $concepto->idconcepto,
                            "fkGrupoConcepto" => $req->gruposConceptoIds[$key]
                        ]);
                    }
                    
                   
                }
               
            }
        }

        
        DB::table('formulaconcepto')->where("fkConcepto", "=", $req->idconcepto)->delete();
        if($req->subTipo == "Formula"){
            $arrInsertFormulaConcepto = array("fkConcepto" => $concepto->idconcepto);
            if(isset($req->fkVariableInicial)){
                $arrInsertFormulaConcepto["fkVariableInicial"] = $req->fkVariableInicial;
            }
            if(isset($req->valorInicial)){
                $arrInsertFormulaConcepto["valorInicial"] = $req->valorInicial;
            }
            if(isset($req->grupoInicial)){
                $arrInsertFormulaConcepto["fkGrupoConceptoInicial"] = $req->grupoInicial;
            }
            if(isset($req->conceptoInicial)){
                $arrInsertFormulaConcepto["fkConceptoInicial"] = $req->conceptoInicial;
            }

            $jerarquia = 1;
            if(isset($req->fkTipoOperacion)){
                foreach ($req->fkTipoOperacion as $key => $operacion) {
                
                    $arrInsertFormulaConcepto["fkTipoOperacion"] = $operacion;
                    if(isset($req->fkVariableFinal[$key]) && !empty($req->fkVariableFinal[$key])){
                        $arrInsertFormulaConcepto["fkVariableFinal"] = $req->fkVariableFinal[$key];
                    }
                    if(isset($req->valorFinal[$key])){
                        $arrInsertFormulaConcepto["valorFinal"] = $req->valorFinal[$key];
                    }
                    if(isset($req->grupoFinal[$key]) && !empty($req->grupoFinal[$key])){
                        $arrInsertFormulaConcepto["fkGrupoConceptoFinal"] = $req->grupoFinal[$key];
                    }
                    if(isset($req->conceptoFinal[$key]) && !empty($req->conceptoFinal[$key])){
                        $arrInsertFormulaConcepto["fkConceptoFinal"] = $req->conceptoFinal[$key];
                    }
                    $arrInsertFormulaConcepto["jerarquia"] = $jerarquia;
                    $idFormulaConcepto = DB::table('formulaconcepto')->insertGetId($arrInsertFormulaConcepto, "idformulaConcepto");
                    $arrInsertFormulaConcepto = array("fkConcepto" => $concepto->idconcepto, "fkFormulaConcepto" => $idFormulaConcepto);
                    $jerarquia++;
                }
            }
            
        }


        DB::table('formulaconcepto_ss')->where("fkConcepto", "=", $req->idconcepto)->delete();
        if($req->subTipo == "Formula"){

            $arrInsertFormulaConceptoSS = array("fkConcepto" => $concepto->idconcepto);

            if(isset($req->fkVariableInicialSS)){
                $arrInsertFormulaConceptoSS["fkVariableInicial"] = $req->fkVariableInicialSS;
            }
            if(isset($req->valorInicialSS)){
                $arrInsertFormulaConceptoSS["valorInicial"] = $req->valorInicialSS;
            }
            if(isset($req->grupoInicialSS)){
                $arrInsertFormulaConceptoSS["fkGrupoConceptoInicial"] = $req->grupoInicialSS;
            }
            if(isset($req->conceptoInicialSS)){
                $arrInsertFormulaConceptoSS["fkConceptoInicial"] = $req->conceptoInicialSS;
            }

            $jerarquia = 1;
            if(isset($req->fkTipoOperacionSS)){
                foreach ($req->fkTipoOperacionSS as $key => $operacion) {
                
                    $arrInsertFormulaConceptoSS["fkTipoOperacion"] = $operacion;
                    if(isset($req->fkVariableFinal[$key]) && !empty($req->fkVariableFinalSS[$key])){
                        $arrInsertFormulaConceptoSS["fkVariableFinal"] = $req->fkVariableFinalSS[$key];
                    }
                    if(isset($req->valorFinalSS[$key]) && !empty($req->valorFinalSS[$key])){
                        $arrInsertFormulaConceptoSS["valorFinal"] = $req->valorFinalSS[$key];
                    }
                    if(isset($req->grupoFinalSS[$key]) && !empty($req->grupoFinalSS[$key])){
                        $arrInsertFormulaConceptoSS["fkGrupoConceptoFinal"] = $req->grupoFinalSS[$key];
                    }
                    if(isset($req->conceptoFinalSS[$key]) && !empty($req->conceptoFinalSS[$key])){
                        $arrInsertFormulaConceptoSS["fkConceptoFinal"] = $req->conceptoFinalSS[$key];
                    }
                    $arrInsertFormulaConceptoSS["jerarquia"] = $jerarquia;
                    $idFormulaConceptoSS = DB::table('formulaconcepto_ss')->insertGetId($arrInsertFormulaConceptoSS, "idformulaConcepto");
                    $arrInsertFormulaConceptoSS = array("fkConcepto" => $concepto->idconcepto, "fkFormulaConcepto" => $idFormulaConceptoSS);
                    $jerarquia++;
                }
            }
            
        }




        return response()->json([
			"success" => true
        ]);


    }
    public function copy(Request $req){
    	

    	$concepto = new Concepto;
    	$concepto->nombre = $req->nombre; 
    	$concepto->fkNaturaleza = $req->fkNaturaleza; 
    	$concepto->fkTipoConcepto = $req->fkTipoConcepto; 
        $concepto->subTipo  = $req->subTipo; 
        if($req->fkVariable != ""){
        	$concepto->fkVariable = $req->fkVariable; 
        }        
        $concepto->numRetefuente = $req->numRetefuente; 
        $concepto->generacionAutomatica = $req->generacionAutomatica; 
        $concepto->fkGrupoNomina = $req->fkGrupoNomina; 
        $concepto->fk_concepto_wo = $req->fk_concepto_wo; 
        $concepto->save();
        
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", copió el concepto con id ".$req->idconcepto);

        if(isset($req->gruposConcepto)){
            foreach($req->gruposConcepto as $key => $grupo){    
                DB::table('grupoconcepto_concepto')->insert([
                    "fkConcepto" => $concepto->idconcepto,
                    "fkGrupoConcepto" => $grupo
                ]);
            }
        }

        if($req->subTipo == "Formula"){
            $arrInsertFormulaConcepto = array("fkConcepto" => $concepto->idconcepto);
            if(isset($req->fkVariableInicial)){
                $arrInsertFormulaConcepto["fkVariableInicial"] = $req->fkVariableInicial;
            }
            if(isset($req->valorInicial)){
                $arrInsertFormulaConcepto["valorInicial"] = $req->valorInicial;
            }
            if(isset($req->grupoInicial)){
                $arrInsertFormulaConcepto["fkGrupoConceptoInicial"] = $req->grupoInicial;
            }
            if(isset($req->conceptoInicial)){
                $arrInsertFormulaConcepto["fkConceptoInicial"] = $req->conceptoInicial;
            }

            $jerarquia = 1;
            if(isset($req->fkTipoOperacion)){
                foreach ($req->fkTipoOperacion as $key => $operacion) {
                
                    $arrInsertFormulaConcepto["fkTipoOperacion"] = $operacion;
                    if(isset($req->fkVariableFinal[$key]) && !empty($req->fkVariableFinal[$key])){
                        $arrInsertFormulaConcepto["fkVariableFinal"] = $req->fkVariableFinal[$key];
                    }
                    if(isset($req->valorFinal[$key])){
                        $arrInsertFormulaConcepto["valorFinal"] = $req->valorFinal[$key];
                    }
                    if(isset($req->grupoFinal[$key]) && !empty($req->grupoFinal[$key])){
                        $arrInsertFormulaConcepto["fkGrupoConceptoFinal"] = $req->grupoFinal[$key];
                    }
                    if(isset($req->conceptoFinal[$key]) && !empty($req->conceptoFinal[$key])){
                        $arrInsertFormulaConcepto["fkConceptoFinal"] = $req->conceptoFinal[$key];
                    }
                    $arrInsertFormulaConcepto["jerarquia"] = $jerarquia;
                    $idFormulaConcepto = DB::table('formulaconcepto')->insertGetId($arrInsertFormulaConcepto, "idformulaConcepto");
                    $arrInsertFormulaConcepto = array("fkConcepto" => $concepto->idconcepto, "fkFormulaConcepto" => $idFormulaConcepto);
                    $jerarquia++;
                }
            }
            
        }


        $tipoLiquidacionConConcepto = DB::table('conceptosxtipoliquidacion', "ctl")
        ->where("ctl.fkConcepto","=",$req->idconcepto)
        ->get();
        foreach ($tipoLiquidacionConConcepto as $tipoLiquidacionCon)
        {   
            $arrConceptoTipoLiquidacion = array(
                "fkConcepto" => $concepto->idconcepto,
                "fkTipoLiquidacion" => $tipoLiquidacionCon->fkTipoLiquidacion,
                "tipoRelacion" => $tipoLiquidacionCon->tipoRelacion
            );
            DB::table('conceptosxtipoliquidacion')->insert($arrConceptoTipoLiquidacion);
        }
/*
        $grupoConceptoConcepto = DB::table('grupoconcepto_concepto')
        ->where("fkConcepto","=",$req->idconcepto)
        ->get();
        foreach ($grupoConceptoConcepto as $grupoConceptoCon)
        {   
            $arrGrupoconcepto_concepto = array(
                "fkConcepto" => $concepto->idconcepto,
                "fkGrupoConcepto" => $grupoConceptoCon->fkGrupoConcepto
            );
            DB::table('grupoconcepto_concepto')->insert($arrGrupoconcepto_concepto);
        }
*/
        $tiponovconceptotipoent = DB::table('tiponovconceptotipoent')
        ->where("fkConcepto","=",$req->idconcepto)
        ->get();

        foreach ($tiponovconceptotipoent as $tiponovconceptotipo)
        {   
            $arrTiponovconceptotipo = array(
                "fkConcepto" => $concepto->idconcepto,
                "fkTipoNovedad" => $tiponovconceptotipo->fkTipoNovedad,
                "fkTipoAfilicacion" => $tiponovconceptotipo->fkTipoAfilicacion,
                "tipoReporte" => $tiponovconceptotipo->tipoReporte
            );
            DB::table('tiponovconceptotipoent')->insert($arrTiponovconceptotipo);
        }




        return response()->json([
			"success" => true
        ]);


    }
    public function exportar(){

        $conceptos = DB::table("concepto", "c")
        ->select("c.*","n.nombre as naturaleza", "gne.codigo", "gne.descripcion_corta")
        ->join("naturalezaconcepto as n","n.idnaturalezaConcepto", "=","c.fkNaturaleza")
        ->join("grupos_nomina_electronica as gne","gne.idGrupoNominaElectronica", "=","c.fkGrupoNomina","left")
        ->where("fkEstado","=","1")->get();
        $arrDef = array([
            "idConcepto",
            "Nombre",
            "Naturaleza",
            "Nómina electrónica"
        ]);
        foreach ($conceptos as $concepto){
            array_push($arrDef, [
                $concepto->idconcepto,
                $concepto->nombre,
                $concepto->naturaleza,
                (isset($concepto->codigo) ? $concepto->descripcion_corta."( ".$concepto->codigo." )" : "SIN ASIGNAR")           
            ]);
        }

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", exportó un archivo plano de los conceptos");

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename=conceptos.csv');

        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->setDelimiter(';');
        $csv->insertAll($arrDef);
        $csv->setOutputBOM(Reader::BOM_UTF8);
        $csv->output('conceptos.csv');
    }   
}
