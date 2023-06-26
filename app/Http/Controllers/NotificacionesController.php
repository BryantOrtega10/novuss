<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificacionesController extends Controller
{
    public function index(Request $req){
        $notificaciones = DB::table("notificacion","n")
        ->join("empleado as e","e.idempleado", "=","n.fkEmpleado")
        ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales");
        
        $filtroVisto = true;
        if(isset($req->numDoc)){
            $filtroVisto = false;
            $notificaciones = $notificaciones->where("dp.numeroIdentificacion","LIKE","%".$req->numDoc."%");
        }
        if(isset($req->nombre)){
            $filtroVisto = false;
            $notificaciones = $notificaciones->where(function($query) use($req){
                $query->where("dp.primerNombre","LIKE","%".$req->nombre."%")
                ->orWhere("dp.segundoNombre","LIKE","%".$req->nombre."%")
                ->orWhere("dp.primerApellido","LIKE","%".$req->nombre."%")
                ->orWhere("dp.segundoApellido","LIKE","%".$req->nombre."%")
                ->orWhereRaw("CONCAT(dp.primerApellido,' ',dp.segundoApellido,' ',dp.primerNombre,' ',dp.segundoNombre) LIKE '%".$req->nombre."%'");
            });
        }
        if(isset($req->fechaInicio)){
            $filtroVisto = false;
            $notificaciones = $notificaciones->where("n.fecha",">=",$req->fechaInicio);
        }
        if(isset($req->fechaFin)){
            $filtroVisto = false;
            $notificaciones = $notificaciones->where("n.fecha","<=",$req->fechaFin);
        }
        
        if($filtroVisto){
            $notificaciones = $notificaciones->where("n.visto","=","0");
        }
        $dataUsu = UsuarioController::dataAdminLogueado();
        if(isset($dataUsu) && $dataUsu->fkRol == 2){
            $notificaciones = $notificaciones->whereIn("e.fkEmpresa", $dataUsu->empresaUsuario);
        }
        
        $notificaciones = $notificaciones->orderBy("n.fecha","desc")->paginate();
        
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu de 'Notificaciones'");


        return view('notificacion.index', [
            "notificaciones" => $notificaciones,
            "req" => $req,
            "dataUsu" => $dataUsu
        ]);
    }
    public function numeroNotificaciones(){
        $numNoVistos = DB::table("notificacion")
        ->selectRaw("count(visto) as suma")
        ->where("visto","=","0")
        ->first();
        
        return response()->json([
            "success" => true,
            "numNoVistos" => ($numNoVistos->suma ?? 0)
        ]);
    }
    
    public function modificarVisto(){
        DB::table("notificacion")->where("visto","=","0")->update([
            "visto" => 1
        ]);
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", modificó el estado visto de las notificaciones");
        return redirect("/notificaciones");
    }

    public function verificarContratos(Request $req){
        try{
            if($req->has('fecha')){
                $fecha = $req->fecha;
            }
            else{
                $fecha = date("Y-m-d");
            }
            

            $contratosFijosVencenHoy = DB::table("contrato","con")
            ->join("empleado as e","e.idempleado", "=","con.fkEmpleado")
            ->join("empresa as emp","emp.idempresa", "=","e.fkEmpresa")
            ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
            ->join("periodo as p","p.idPeriodo", "=","con.fkPeriodoActivo")
            ->where("p.fkEstado","=","1")
            ->where("con.fechaFin","=",$fecha)
            
            ->get();

            $contratos20PorFijosVencen = DB::table("contrato","con")
            ->join("empleado as e","e.idempleado", "=","con.fkEmpleado")
            ->join("empresa as emp","emp.idempresa", "=","e.fkEmpresa")
            ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
            ->join("periodo as p","p.idPeriodo", "=","con.fkPeriodoActivo")
            ->where("p.fkEstado","=","1")
            ->where("con.fkTipoContrato","=","1")
            ->whereRaw('CAST((DATEDIFF("'.$fecha.'", con.fechaInicio)*100)/DATEDIFF(con.fechaFin, con.fechaInicio) as SIGNED)=20')
            ->get();

            $contratosFijos40DiasAntes = DB::table("contrato","con")
            ->join("empleado as e","e.idempleado", "=","con.fkEmpleado")
            ->join("empresa as emp","emp.idempresa", "=","e.fkEmpresa")
            ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
            ->join("periodo as p","p.idPeriodo", "=","con.fkPeriodoActivo")
            ->where("p.fkEstado","=","1")
            ->where("con.fkTipoContrato","=","1")
            ->whereRaw('DATEDIFF(con.fechaFin,"'.$fecha.'") = 40')
            ->get();
            //dd( $contratosFijos40DiasAntes);

            foreach($contratosFijosVencenHoy as $contratoFijosVencenHoy){
                DB::table("notificacion")->insert([
                    "mensaje" => "Empresa: ".$contratoFijosVencenHoy->razonSocial." El contrato vence hoy para ".$contratoFijosVencenHoy->numeroIdentificacion.": ".$contratoFijosVencenHoy->primerApellido." ".$contratoFijosVencenHoy->primerNombre,
                    "fkEmpleado" => $contratoFijosVencenHoy->idempleado
                ]);
            }
            foreach($contratos20PorFijosVencen as $contratoFijosVencenHoy){
                DB::table("notificacion")->insert([
                    "mensaje" => "Empresa: ".$contratoFijosVencenHoy->razonSocial." El contrato ha completado el 20% para ".$contratoFijosVencenHoy->numeroIdentificacion.": ".$contratoFijosVencenHoy->primerApellido." ".$contratoFijosVencenHoy->primerNombre,
                    "fkEmpleado" => $contratoFijosVencenHoy->idempleado
                ]);
            }
            foreach($contratosFijos40DiasAntes as $contratoFijosVencenHoy){
                DB::table("notificacion")->insert([
                    "mensaje" => "Empresa: ".$contratoFijosVencenHoy->razonSocial." El contrato finalizará en 40 días para ".$contratoFijosVencenHoy->numeroIdentificacion.": ".$contratoFijosVencenHoy->primerApellido." ".$contratoFijosVencenHoy->primerNombre,
                    "fkEmpleado" => $contratoFijosVencenHoy->idempleado
                ]);
            }

            return response()->json([
                "success" => true,
                "contratosFijosVencenHoy" => $contratosFijosVencenHoy,
                "contratos20PorFijosVencen" => $contratos20PorFijosVencen,
                "contratosFijos40DiasAntes" => $contratosFijos40DiasAntes,
            ]);
        }catch(Exception $e){
            dd($e->getMessage());
        }
        
        
    
    }

}
