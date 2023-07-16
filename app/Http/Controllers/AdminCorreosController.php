<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use App\Mail\ComprobantesPagoMail;
use App\Mail\MensajeGeneralMail;
use App\SMTPConfigModel;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Dsn;

class AdminCorreosController extends Controller
{
    public $arrayCampos = [
        "nombreCompleto" => "__nombreCompleto__",
        "primerNombre" => "__primerNombre__",
        "segundoNombre" => "__segundoNombre__",
        "primerApellido" => "__primerApellido__",
        "segundoApellido" => "__segundoApellido__",
        "numeroIdentificacion" => "__numeroIdentificacion__",
        "tipoidentificacion" => "__tipoIdentificacion__",
        "nombreEmpresa" => "__empresa__",
        "nitEmpresa" => "__nitEmpresa__",
        "telefonoEmpresa" => "__telefonoEmpresa__",
        "correoEmpresa" => "__correoEmpresa__",
        "nombreNomina" => "__nomina__",
        "periodoNomina" => "__periodoNomina__",
        "nombreCargo" => "__nombreCargo__",
        "tipoContrato" => "__tipoContrato__",
        "fechaIngreso" => "__fechaIngreso__",
        "fechaRetiro" => "__fechaRetiro__",
        "salario" => "__salario__",
        "salarioLetras" => "__salarioLetras__",
        "conceptosFijos" => "__conceptosFijos__",
        "conceptosFijosLetras" => "__conceptosFijosLetras__",        
        "ciudadEmpresa" => "__ciudadEmpresa__",
        "fechaActual" => "__fechaActual__",
        "contratos" => "__contratos__"
    ];
    public function indexEnviarCorreosxLiquidacion($idLiquidacionNomina){
        $enviosxLiquidacion = DB::table("envio_correo_liquidacion")
        ->join("estado as est", "est.idEstado", "=","envio_correo_liquidacion.fkEstado")
        ->where("fkLiquidacion","=",$idLiquidacionNomina)->get();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al envio de correos para la liquidación #".$idLiquidacionNomina);

        $usu = UsuarioController::dataAdminLogueado();
        return view('/envioCorreosLiquidacion.index',[
            "enviosxLiquidacion" => $enviosxLiquidacion,
            "idLiquidacionNomina" => $idLiquidacionNomina,
            "dataUsu" => $usu
        ]);
    }

    public function crearEnvioCorreo(Request $req){

        $bouchers = DB::table('boucherpago',"bp")
        ->where("bp.fkLiquidacion","=",$req->idLiquidacionNomina)
        ->count();


        $idEnvioCorreoLiq = DB::table("envio_correo_liquidacion")->insertGetId([
            "fkLiquidacion" => $req->idLiquidacionNomina,
            "numRegistros" => $bouchers,
            "numActual" => "0"
        ], "idEnvioCorreoLiq");

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", creo un nuevo envio de correos para la liquidación #".$req->idLiquidacionNomina);

        return response()->json([
            "success" => true,
            "idEnvioCorreoLiq" => $idEnvioCorreoLiq
        ]);



    }

    public function verEnvioCorreo($idEnvioCorreoLiq){

        $envioxLiquidacion = DB::table("envio_correo_liquidacion")->where("idEnvioCorreoLiq","=",$idEnvioCorreoLiq)->first();
        $empleados = DB::table("empleado","e")
        ->select("dp.*","ti.nombre as tipoidentificacion", "estado.nombre as estado", "iecl.mensaje")
        ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion")
        ->join("boucherpago as bp", "bp.fkEmpleado","=","e.idempleado")
        ->leftJoin('item_envio_correo_liquidacion as iecl', function($join) use ($idEnvioCorreoLiq){
            $join->on('iecl.fkBoucherPago', '=', 'bp.idBoucherPago')
                ->where('iecl.fkEnvioCorreoLiq', '=', $idEnvioCorreoLiq);
        })
        ->leftJoin("estado","estado.idestado","=","iecl.fkEstado")
        ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
        ->join("envio_correo_liquidacion as ecl","ecl.fkLiquidacion","=","ln.idLiquidacionNomina")
        ->where("ecl.idEnvioCorreoLiq","=",$idEnvioCorreoLiq)->get();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó a ver el estado de envio de correos #".$idEnvioCorreoLiq);

        $usu = UsuarioController::dataAdminLogueado();
        return view('/envioCorreosLiquidacion.verEnvioCorreo', [
            "empleados" => $empleados,
            "envioxLiquidacion" => $envioxLiquidacion,
            "dataUsu" => $usu
        ]);
    }
    public function enviarProximosRegistroReporte($idEnvioCorreoRep){
        $numeroRegistrosAEnviar = 3;
        $envioxReporte = DB::table("envio_correo_reporte")->where("id_envio_correo_reporte","=",$idEnvioCorreoRep)->first();
        $empleadosxReporte = DB::table("item_envio_correo_reporte", "iec")
        ->join("empleado as e","e.idempleado", "=","iec.fkEmpleado")
        ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
        ->where("iec.fkEnvioCoreoReporte", "=",$idEnvioCorreoRep)
        ->offset($envioxReporte->numActual)
        ->limit($numeroRegistrosAEnviar)
        ->get();
        $numActual = $envioxReporte->numActual;

        
        if(sizeof($empleadosxReporte) > 0){
            foreach($empleadosxReporte as $empleadoReporte){
                if($envioxReporte->fkMensaje=="4"){
                    if(isset($empleadoReporte->fkBoucherPago)){

                        $jsonMensaje = $this->enviarCorreoBoucher($empleadoReporte->fkBoucherPago);

                        if($jsonMensaje->original["success"]){
                            DB::table("item_envio_correo_reporte")
                            ->where("id_item_envio_correo_reporte","=",$empleadoReporte->id_item_envio_correo_reporte)
                            ->update([
                                "fkEstado" => "48"
                            ]);
                        }
                        else{
                            DB::table("item_envio_correo_reporte")
                            ->where("id_item_envio_correo_reporte","=",$empleadoReporte->id_item_envio_correo_reporte)
                            ->update([
                                "fkEstado" => "36",
                                "mensaje" => $jsonMensaje->original["error"]
                            ]);
                        }
                        $numActual++;                
                    }
                }
                else{
                    $jsonMensaje = $this->enviarCorreoGeneral($empleadoReporte->fkEmpleado, $envioxReporte->fkMensaje);
                    if($jsonMensaje->original["success"]){
                        DB::table("item_envio_correo_reporte")
                        ->where("id_item_envio_correo_reporte","=",$empleadoReporte->id_item_envio_correo_reporte)
                        ->update([
                            "fkEstado" => "48"
                        ]);
                    }
                    else{
                        DB::table("item_envio_correo_reporte")
                        ->where("id_item_envio_correo_reporte","=",$empleadoReporte->id_item_envio_correo_reporte)
                        ->update([
                            "fkEstado" => "36",
                            "mensaje" => $jsonMensaje->original["error"]
                        ]);
                    }
                    $numActual++;      
                    
                }
            }
            DB::table("envio_correo_reporte")
            ->where("id_envio_correo_reporte","=",$idEnvioCorreoRep)
            ->update([
                "numActual" => $numActual
            ]);
            $mensaje = "";
            $empleados = DB::table("item_envio_correo_reporte", "iec")
            ->select("dp.*","ti.nombre as tipoidentificacion", "est.nombre as estado", "iec.mensaje")
            ->join("empleado as e","e.idempleado", "=","iec.fkEmpleado")
            ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
            ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion")
            ->join("estado as est", "est.idEstado", "=","iec.fkEstado")
            ->where("iec.fkEnvioCoreoReporte", "=",$idEnvioCorreoRep)
            ->get();
            foreach ($empleados as $empleado){
                $mensaje.='<tr>
                    <th scope="row">'.$empleado->tipoidentificacion.' - '. $empleado->numeroIdentificacion .'</th>
                    <td>'. $empleado->primerApellido .' '. $empleado->segundoApellido .' '. $empleado->primerNombre .' '. $empleado->segundoNombre .'</td>
                    <td>'. $empleado->estado .' '. $empleado->mensaje .'</td>
                </tr>';
            }
            return response()->json([
                "success" => true,
                "seguirSubiendo" => true,
                "numActual" =>  ($numActual),
                "mensaje" => $mensaje,
                "porcentaje" => ceil(($numActual / $envioxReporte->numRegistros)*100)."%"
            ]);

          
        }
        else{
            $mensaje = "";
            $empleados = DB::table("item_envio_correo_reporte", "iec")
            ->select("dp.*","ti.nombre as tipoidentificacion", "est.nombre as estado", "iec.mensaje")
            ->join("empleado as e","e.idempleado", "=","iec.fkEmpleado")
            ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
            ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion")
            ->join("estado as est", "est.idEstado", "=","iec.fkEstado")
            ->where("iec.fkEnvioCoreoReporte", "=",$idEnvioCorreoRep)
            ->get();
            foreach ($empleados as $empleado){
                $mensaje.='<tr>
                    <th scope="row">'.$empleado->tipoidentificacion.' - '. $empleado->numeroIdentificacion .'</th>
                    <td>'. $empleado->primerApellido .' '. $empleado->segundoApellido .' '. $empleado->primerNombre .' '. $empleado->segundoNombre .'</td>
                    <td>'. $empleado->estado .' '. $empleado->mensaje .'</td>
                </tr>';
            }
            DB::table("envio_correo_reporte")
            ->where("id_envio_correo_reporte","=",$idEnvioCorreoRep)
            ->update([
                "numActual" => $envioxReporte->numRegistros,
                "fkEstado" => "5"
            ]);
            return response()->json([
                "success" => true,
                "seguirSubiento" => false,
                "mensaje" => $mensaje,
                "numActual" => $envioxReporte->numRegistros,
                "numRegistros" => $envioxReporte->numRegistros
            ]);
        }

    }

    public function enviarProximosRegistro($idEnvioCorreoLiq){
        $numeroRegistrosAEnviar = 3;

        $envioxLiquidacion = DB::table("envio_correo_liquidacion")->where("idEnvioCorreoLiq","=",$idEnvioCorreoLiq)->first();
        $bouchersXLiquidacion  = DB::table("boucherpago", "bp")
        ->where("bp.fkLiquidacion", "=",$envioxLiquidacion->fkLiquidacion)
        ->offset($envioxLiquidacion->numActual)
        ->limit($numeroRegistrosAEnviar)
        ->get();
        $numActual = $envioxLiquidacion->numActual;
        if(sizeof($bouchersXLiquidacion) > 0){
            foreach($bouchersXLiquidacion as $boucher){
                $jsonMensaje = $this->enviarCorreoBoucher($boucher->idBoucherPago);


                if($jsonMensaje->original["success"]){
                    DB::table("item_envio_correo_liquidacion")->insert([
                        "fkBoucherPago" => $boucher->idBoucherPago,
                        "fkEnvioCorreoLiq" => $idEnvioCorreoLiq,
                        "fkEstado" => "48"
                    ]);
                }
                else{
                    DB::table("item_envio_correo_liquidacion")->insert([
                        "fkBoucherPago" => $boucher->idBoucherPago,
                        "fkEnvioCorreoLiq" => $idEnvioCorreoLiq,
                        "fkEstado" => "36",
                        "mensaje" => $jsonMensaje->original["error"]
                    ]);
                }
                $numActual++;                
            }
            DB::table("envio_correo_liquidacion")
            ->where("idEnvioCorreoLiq","=",$idEnvioCorreoLiq)
            ->update([
                "numActual" => $numActual
            ]);

            $empleados = DB::table("empleado","e")
            ->select("dp.*","ti.nombre as tipoidentificacion", "estado.nombre as estado", "iecl.mensaje")
            ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
            ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion")
            ->join("boucherpago as bp", "bp.fkEmpleado","=","e.idempleado")
            ->leftJoin('item_envio_correo_liquidacion as iecl', function($join) use ($idEnvioCorreoLiq){
                $join->on('iecl.fkBoucherPago', '=', 'bp.idBoucherPago')
                    ->where('iecl.fkEnvioCorreoLiq', '=', $idEnvioCorreoLiq);
            })
            ->leftJoin("estado","estado.idestado","=","iecl.fkEstado")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("envio_correo_liquidacion as ecl","ecl.fkLiquidacion","=","ln.idLiquidacionNomina")
            ->where("ecl.idEnvioCorreoLiq","=",$idEnvioCorreoLiq)->get();
            $mensaje = "";
            foreach ($empleados as $empleado){
                $mensaje.='<tr>
                    <th scope="row">'.$empleado->tipoidentificacion.' - '. $empleado->numeroIdentificacion .'</th>
                    <td>'. $empleado->primerApellido .' '. $empleado->segundoApellido .' '. $empleado->primerNombre .' '. $empleado->segundoNombre .'</td>
                    <td>'. $empleado->estado .' '. $empleado->mensaje .'</td>
                </tr>';
            }

            return response()->json([
                "success" => true,
                "seguirSubiendo" => true,
                "numActual" =>  ($numActual),
                "mensaje" => $mensaje,
                "porcentaje" => ceil(($numActual / $envioxLiquidacion->numRegistros)*100)."%"
            ]);
        }
        else{
            $empleados = DB::table("empleado","e")
            ->select("dp.*","ti.nombre as tipoidentificacion", "estado.nombre as estado", "iecl.mensaje")
            ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales")
            ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion")
            ->join("boucherpago as bp", "bp.fkEmpleado","=","e.idempleado")
            ->leftJoin('item_envio_correo_liquidacion as iecl', function($join) use ($idEnvioCorreoLiq){
                $join->on('iecl.fkBoucherPago', '=', 'bp.idBoucherPago')
                    ->where('iecl.fkEnvioCorreoLiq', '=', $idEnvioCorreoLiq);
            })
            ->leftJoin("estado","estado.idestado","=","iecl.fkEstado")
            ->join("liquidacionnomina as ln","ln.idLiquidacionNomina","=","bp.fkLiquidacion")
            ->join("envio_correo_liquidacion as ecl","ecl.fkLiquidacion","=","ln.idLiquidacionNomina")
            ->where("ecl.idEnvioCorreoLiq","=",$idEnvioCorreoLiq)->get();
            $mensaje = "";
            foreach ($empleados as $empleado){
                $mensaje.='<tr>
                    <th scope="row">'.$empleado->tipoidentificacion.' - '. $empleado->numeroIdentificacion .'</th>
                    <td>'. $empleado->primerApellido .' '. $empleado->segundoApellido .' '. $empleado->primerNombre .' '. $empleado->segundoNombre .'</td>
                    <td>'. $empleado->estado .' '. $empleado->mensaje .'</td>
                </tr>';
            }
            DB::table("envio_correo_liquidacion")
            ->where("idEnvioCorreoLiq","=",$idEnvioCorreoLiq)
            ->update([
                "numActual" => $envioxLiquidacion->numRegistros,
                "fkEstado" => "5"
            ]);
            return response()->json([
                "success" => true,
                "seguirSubiento" => false,
                "mensaje" => $mensaje,
                "numActual" => $envioxLiquidacion->numRegistros,
                "numRegistros" => $envioxLiquidacion->numRegistros
            ]);
        }
    }

    public function enviarCorreoBoucher($idBoucherPago) {

        $smtpDef = SMTPConfigModel::find(1);

        $arrSMTPDefault = array(
            'host' => $smtpDef->smtp_host,
            'user' => $smtpDef->smtp_username,
            'pass' => $smtpDef->smtp_password,
            'encrypt' => $smtpDef->smtp_encrypt,
            'port' => $smtpDef->smtp_port,
            'sender_mail' => $smtpDef->smtp_mail_envia,
            'sender_name' => $smtpDef->smtp_nombre_envia
        );

        $empleado = DB::table("empleado", "e")
        ->selectRaw('dp.*,e.*,ti.nombre as tipoidentificacion, emp.razonSocial as nombreEmpresa, 
                    emp.telefonoFijo as telefonoEmpresa,
                    emp.email1 as correoEmpresa,
                    n.nombre as nombreNomina,
                    CONCAT(emp.documento,"-",emp.digitoVerificacion) as nitEmpresa,
                    CONCAT_WS(" ",dp.primerApellido, dp.segundoApellido, dp.primerNombre, dp.segundoNombre) as nombreCompleto, 
                    c.nombreCargo, u.nombre as ciudadEmpresa')
        ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales","left")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion","left")
        ->join("nomina as n","n.idNomina", "=","e.fkNomina","left")
        ->join("empresa as emp","emp.idempresa", "=","e.fkEmpresa","left")
        ->join("ubicacion as u","u.idubicacion", "=","emp.fkUbicacion","left")
        ->join("boucherpago as bp","bp.fkEmpleado", "=","e.idempleado")
        ->join("cargo as c","c.idCargo", "=","e.fkCargo","left")
        ->where("bp.idBoucherPago","=",$idBoucherPago)
        ->first();
        
        $empresayLiquidacion = DB::table("empresa", "e")
        ->select("e.*", "ln.*", "n.nombre as nom_nombre", "bp.*")
        ->join("nomina as n","n.fkEmpresa", "e.idempresa")
        ->join("liquidacionnomina as ln","ln.fkNomina", "n.idNomina")
        ->join("boucherpago as bp","bp.fkLiquidacion", "ln.idLiquidacionNomina")
        ->where("bp.idBoucherPago","=",$idBoucherPago)
        ->first();

        $smtConfig = DB::table("smtp_config","s")
        ->join("empresa as e","e.fkSmtpConf", "=","s.id_smpt")
        ->where("e.idempresa","=",$empresayLiquidacion->idempresa)->first();

        $novedadesRetiro = DB::table("novedad","n")
            ->select("r.fecha", "r.fechaReal","mr.nombre as motivoRet")
            ->join("retiro AS r", "r.idRetiro","=","n.fkRetiro")
            ->join("motivo_retiro as mr","mr.idMotivoRetiro","=","r.fkMotivoRetiro")
            ->where("n.fkEmpleado", "=", $empleado->idempleado)
            ->whereIn("n.fkEstado",["7", "8"])
            ->whereNotNull("n.fkRetiro")
            ->whereBetween("n.fechaRegistro",[$empresayLiquidacion->fechaInicio, $empresayLiquidacion->fechaFin])->first();
        
        $mensaje = DB::table("mensaje")->where("tipo","=", "4")
        ->where("fkEmpresa", "=",$empresayLiquidacion->idempresa)
        ->first();
        if(!isset($mensaje)){
            $mensaje = DB::table("mensaje")->where("idMensaje","=", "4")->first();
        }


        if(($empresayLiquidacion->fkTipoLiquidacion == "2" || $empresayLiquidacion->fkTipoLiquidacion == "3" || $empresayLiquidacion->fkTipoLiquidacion == "7") && isset($novedadesRetiro)){
            
            $mensaje = DB::table("mensaje")->where("tipo","=", "5")
            ->where("fkEmpresa", "=",$empresayLiquidacion->idempresa)
            ->first();
            if(!isset($mensaje)){
                $mensaje = DB::table("mensaje")->where("idMensaje","=", "5")->first();
            }
            
        }  
        $mailer = app(Mailer::class);
        $transport = (new Transport(Transport::getDefaultFactories()))->fromDsnObject(new Dsn(
            'smtps',
            $arrSMTPDefault['host'],
            $arrSMTPDefault['user'],
            $arrSMTPDefault['pass'],
            $arrSMTPDefault['port'],
        ));        
        $stream = $transport->getStream();
        $streamOptions = $stream->getStreamOptions();
        $streamOptions['ssl']['allow_self_signed'] = true;
        $streamOptions['ssl']['verify_peer'] = false;
        $streamOptions['ssl']['verify_peer_name'] = false;
        $stream->setStreamOptions($streamOptions);
        $mailer->setSymfonyTransport(
            $transport
        );
        $sender_mail = $arrSMTPDefault['sender_mail'];
        $sender_name = $arrSMTPDefault['sender_name'];

        if(isset($smtConfig)){

            $transport = (new Transport(Transport::getDefaultFactories()))->fromDsnObject(new Dsn(
                'smtps',
                $smtConfig->smtp_host,
                $smtConfig->smtp_username,
                $smtConfig->smtp_password,
                $smtConfig->smtp_port,
            ));        
            $stream = $transport->getStream();
            $streamOptions = $stream->getStreamOptions();
            $streamOptions['ssl']['allow_self_signed'] = true;
            $streamOptions['ssl']['verify_peer'] = false;
            $streamOptions['ssl']['verify_peer_name'] = false;
            $stream->setStreamOptions($streamOptions);
            $mailer->setSymfonyTransport(
                $transport
            );
            $sender_mail = $smtConfig->smtp_mail_envia;
            $sender_name = $smtConfig->smtp_nombre_envia;
            
        }
        


        $periodo = DB::table("periodo")
        ->select("periodo.*","cargo.nombreCargo","tipocontrato.nombre as nombreTipoContrato","n.nombre as nombreNomina", 
                "emp.razonSocial as nombreEmpresa", DB::raw('CONCAT(emp.documento,"-",emp.digitoVerificacion) as nitEmpresa'),
                "emp.telefonoFijo as telefonoEmpresa",
                "emp.email1 as correoEmpresa",
                "u.nombre as ciudadEmpresa")
        ->leftJoin("cargo","cargo.idCargo","=","periodo.fkCargo")
        ->leftJoin("tipocontrato","tipocontrato.idtipoContrato","=","periodo.fkTipoContrato")
        ->leftJoin("nomina as n", "n.idNomina","=","periodo.fkNomina")
        ->leftJoin("empresa as emp","emp.idempresa", "=","n.fkEmpresa")
        ->join("ubicacion as u","u.idubicacion", "=","emp.fkUbicacion","left")
        ->where("idPeriodo","=",$empresayLiquidacion->fkPeriodoActivo)
        ->orderBy("idPeriodo","desc")
        ->first();

        

        $contrato = DB::table("contrato","con")
        ->select("tc.nombre as tipoContrato")        
        ->join("tipocontrato as tc","tc.idtipoContrato","=","con.fkTipoContrato")
        ->where("con.fkPeriodoActivo","=",$empresayLiquidacion->fkPeriodoActivo)        
        ->first();


        $arrDatos =  (array) $empleado;
        
        
        $arrDatos["periodoNomina"] = $empresayLiquidacion->fechaLiquida;


        if(isset($periodo->ciudadEmpresa)){
            $arrDatos["ciudadEmpresa"] = $periodo->ciudadEmpresa;
        }        

        if(isset($periodo->nombreEmpresa)){
            $arrDatos["nombreEmpresa"] = $periodo->nombreEmpresa;
        }

        if(isset($periodo->nitEmpresa)){
            $arrDatos["nitEmpresa"] = $periodo->nitEmpresa;
        }

        if(isset($periodo->telefonoEmpresa)){
            $arrDatos["telefonoEmpresa"] = $periodo->telefonoEmpresa;
        }

        if(isset($periodo->correoEmpresa)){
            $arrDatos["correoEmpresa"] = $periodo->correoEmpresa;
        }

        if(isset($periodo->nombreNomina)){
            $arrDatos["nombreNomina"] = $periodo->nombreNomina;
        }

        if(isset($periodo->nombreCargo)){
            $arrDatos["nombreCargo"] = $periodo->nombreCargo;
        }

        if(isset($periodo->nombreTipoContrato)){
            $arrDatos["tipoContrato"] = $periodo->nombreTipoContrato;
        }
        else{
            $arrDatos["tipoContrato"] = $contrato->tipoContrato;
        }

        $arrDatos["fechaIngreso"] = $periodo->fechaInicio;
        

        if(isset($periodo->fechaFin)){
            $arrDatos["fechaRetiro"] = $periodo->fechaFin;
        }
        else{
            $arrDatos["fechaRetiro"] = "Actual";
        }

        if(isset($periodo->salario)){
            $arrDatos["salario"] = $periodo->salario;
            $arrDatos["salarioLetras"] = $this->convertir(intval($periodo->salario));
        }
        else{
            $conceptoSalario = DB::table("conceptofijo")
            ->where("fkEmpleado","=",$empleado->idempleado)
            ->where("fkPeriodoActivo","=",$periodo->idPeriodo)
            ->whereIn("fkConcepto",[1,2,53,54,154])->first();
            $arrDatos["salario"] = $conceptoSalario->valor;            
            $arrDatos["salarioLetras"] = $this->convertir(intval($conceptoSalario->valor));
        }
        setlocale(LC_ALL, "es_ES", 'Spanish_Spain', 'Spanish');
        $fechaCarta = ucwords(iconv('ISO-8859-2', 'UTF-8', strftime("%A, %d de %B de %Y", strtotime(date('Y-m-d')))));
        $arrDatos["fechaActual"] = $fechaCarta;
        
        
        $mensaje->html = $this->reemplazarCampos($mensaje->html, $arrDatos);
        $mensaje->asunto = $this->reemplazarCampos($mensaje->asunto, $arrDatos);
        

        $reportes = new ReportesNominaController();

        $pdf = $reportes->boucherCorreo($idBoucherPago);

        try {
            if(!isset($empleado->correo)){
                
                if(isset($empleado->correo2) && !empty($empleado->correo2)){

                    $mailable = new ComprobantesPagoMail($mensaje->asunto, $mensaje->html, $sender_mail, $sender_name, $pdf);
                    //$mailer->to($empleado->correo2)->send($mailable);
                    return response()->json([
                        "success" => true
                    ]);
                }
                else{
                    return response()->json([
                        "success" => false,
                        "error" => "Correos vacios"
                    ]);
                }
               
            }
            else{

                $mailable = new ComprobantesPagoMail($mensaje->asunto, $mensaje->html, $sender_mail, $sender_name, $pdf);
                //$mailer->to($empleado->correo)->send($mailable);

                if(isset($empleado->correo2) && !empty($empleado->correo2)){
                    //$mailer->to($empleado->correo2)->send($mailable);
                }
                return response()->json([
                    "success" => true
                ]);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                "success" => false,
                "error" => $e->getMessage()
            ]);
        }
    }
    private function reemplazarCampos($mensaje, $datos){
   
        foreach($this->arrayCampos as $id => $campo){
            if(isset($datos[$id])){
                $mensaje = str_replace($campo, $datos[$id], $mensaje);
            }
            else{
                $mensaje = str_replace($campo, "", $mensaje);
            }
        }
        return $mensaje;
        
    }
    
    public function enviarCorreoGeneral($idEmpleado, $tipoMensaje) {

        $smtpDef = SMTPConfigModel::find(1);
        $arrSMTPDefault = array(
            'host' => $smtpDef->smtp_host,
            'user' => $smtpDef->smtp_username,
            'pass' => $smtpDef->smtp_password,
            'encrypt' => $smtpDef->smtp_encrypt,
            'port' => $smtpDef->smtp_port,
            'sender_mail' => $smtpDef->smtp_mail_envia,
            'sender_name' => $smtpDef->smtp_nombre_envia
        );

        $empleado = DB::table("empleado", "e")
        ->selectRaw('dp.*,e.*,ti.nombre as tipoidentificacion, emp.razonSocial as nombreEmpresa,
                    emp.telefonoFijo as telefonoEmpresa,
                    emp.email1 as correoEmpresa,
                    n.nombre as nombreNomina,
                    CONCAT(emp.documento,"-",emp.digitoVerificacion) as nitEmpresa,
                    CONCAT_WS(" ",dp.primerApellido, dp.segundoApellido, dp.primerNombre, dp.segundoNombre) as nombreCompleto,
                    c.nombreCargo, u.nombre as ciudadEmpresa')
        ->join("datospersonales as dp","dp.idDatosPersonales", "=", "e.fkDatosPersonales","left")
        ->join("tipoidentificacion as ti","ti.idtipoIdentificacion", "=", "dp.fkTipoIdentificacion","left")
        ->join("nomina as n","n.idNomina", "=","e.fkNomina","left")
        ->join("empresa as emp","emp.idempresa", "=","e.fkEmpresa","left")
        ->join("ubicacion as u","u.idubicacion", "=","emp.fkUbicacion","left")
        ->join("cargo as c","c.idCargo", "=","e.fkCargo","left")
        ->where("e.idempleado","=",$idEmpleado)
        ->first();

        $periodo = DB::table("periodo")
        ->select("periodo.*","cargo.nombreCargo","tipocontrato.nombre as nombreTipoContrato","n.nombre as nombreNomina",
                "emp.razonSocial as nombreEmpresa", DB::raw('CONCAT(emp.documento,"-",emp.digitoVerificacion) as nitEmpresa'), 
                "emp.telefonoFijo as telefonoEmpresa",
                "emp.email1 as correoEmpresa",
                "n.nombre as nombreNomina",
                "u.nombre as ciudadEmpresa")
        ->leftJoin("cargo","cargo.idCargo","=","periodo.fkCargo")
        ->leftJoin("tipocontrato","tipocontrato.idtipoContrato","=","periodo.fkTipoContrato")
        ->leftJoin("nomina as n", "n.idNomina","=","periodo.fkNomina")
        ->leftJoin("empresa as emp","emp.idempresa", "=","n.fkEmpresa")
        ->join("ubicacion as u","u.idubicacion", "=","emp.fkUbicacion","left")
        ->where("periodo.fkEmpleado","=",$idEmpleado)
        ->orderBy("idPeriodo","desc")
        ->first();



        $empleado->fkEmpresa = ($periodo->fkEmpresa ?? $empleado->fkEmpresa);

        

        $smtConfig = DB::table("smtp_config","s")
        ->join("empresa as e","e.fkSmtpConf", "=","s.id_smpt")
        ->where("e.idempresa","=",$empleado->fkEmpresa)->first();       
        
        $mensaje = DB::table("mensaje")->where("tipo","=", $tipoMensaje)
        ->where("fkEmpresa", "=",$empleado->fkEmpresa)
        ->first();
        if(!isset($mensaje)){
            $mensaje = DB::table("mensaje")->where("idMensaje","=", $tipoMensaje)->first();
        }

        $mailer = app(Mailer::class);
        $transport = (new Transport(Transport::getDefaultFactories()))->fromDsnObject(new Dsn(
            'smtps',
            $arrSMTPDefault['host'],
            $arrSMTPDefault['user'],
            $arrSMTPDefault['pass'],
            $arrSMTPDefault['port'],
        ));        
        $stream = $transport->getStream();
        $streamOptions = $stream->getStreamOptions();
        $streamOptions['ssl']['allow_self_signed'] = true;
        $streamOptions['ssl']['verify_peer'] = false;
        $streamOptions['ssl']['verify_peer_name'] = false;
        $stream->setStreamOptions($streamOptions);
        $mailer->setSymfonyTransport(
            $transport
        );
        $sender_mail = $arrSMTPDefault['sender_mail'];
        $sender_name = $arrSMTPDefault['sender_name'];

        if(isset($smtConfig)){
            $transport = (new Transport(Transport::getDefaultFactories()))->fromDsnObject(new Dsn(
                'smtps',
                $smtConfig->smtp_host,
                $smtConfig->smtp_username,
                $smtConfig->smtp_password,
                $smtConfig->smtp_port,
            ));        
            $stream = $transport->getStream();
            $streamOptions = $stream->getStreamOptions();
            $streamOptions['ssl']['allow_self_signed'] = true;
            $streamOptions['ssl']['verify_peer'] = false;
            $streamOptions['ssl']['verify_peer_name'] = false;
            $stream->setStreamOptions($streamOptions);
            $mailer->setSymfonyTransport(
                $transport
            );
            $sender_mail = $smtConfig->smtp_mail_envia;
            $sender_name = $smtConfig->smtp_nombre_envia;
            
        }

        $contrato = DB::table("contrato","con")
        ->select("tc.nombre as tipoContrato")        
        ->join("tipocontrato as tc","tc.idtipoContrato","=","con.fkTipoContrato")
        ->where("con.fkEmpleado","=",$idEmpleado)      
        ->orderBy("idcontrato","desc")
        ->first();


        $arrDatos =  (array) $empleado;
        
        
        $arrDatos["periodoNomina"] = "";

        if(isset($periodo->ciudadEmpresa)){
            $arrDatos["ciudadEmpresa"] = $periodo->ciudadEmpresa;
        }

        
        if(isset($periodo->nombreEmpresa)){
            $arrDatos["nombreEmpresa"] = $periodo->nombreEmpresa;
        }

        if(isset($periodo->nitEmpresa)){
            $arrDatos["nitEmpresa"] = $periodo->nitEmpresa;
        }

        if(isset($periodo->telefonoEmpresa)){
            $arrDatos["telefonoEmpresa"] = $periodo->telefonoEmpresa;
        }

        if(isset($periodo->correoEmpresa)){
            $arrDatos["correoEmpresa"] = $periodo->correoEmpresa;
        }

        if(isset($periodo->nombreNomina)){
            $arrDatos["nombreNomina"] = $periodo->nombreNomina;
        }

        if(isset($periodo->nombreCargo)){
            $arrDatos["nombreCargo"] = $periodo->nombreCargo;
        }

        if(isset($periodo->nombreTipoContrato)){
            $arrDatos["tipoContrato"] = $periodo->nombreTipoContrato;
        }
        else{
            $arrDatos["tipoContrato"] = $contrato->tipoContrato;
        }

        $arrDatos["fechaIngreso"] = $periodo->fechaInicio;
        

        if(isset($periodo->fechaFin)){
            $arrDatos["fechaRetiro"] = $periodo->fechaFin;
        }
        else{
            $arrDatos["fechaRetiro"] = "Actual";
        }

        if(isset($periodo->salario)){
            $arrDatos["salario"] = $periodo->salario;
            $arrDatos["salarioLetras"] = $this->convertir($periodo->salario);

        }
        else{
            $conceptoSalario = DB::table("conceptofijo")
            ->where("fkEmpleado","=",$empleado->idempleado)
            ->where("fkPeriodoActivo","=",$periodo->idPeriodo)
            ->whereIn("fkConcepto",[1,2,53,54,154])->first();
            $arrDatos["salario"] = $conceptoSalario->valor;
            $arrDatos["salarioLetras"] = $this->convertir($conceptoSalario->valor);
        }
        setlocale(LC_ALL, "es_ES", 'Spanish_Spain', 'Spanish');
        $fechaCarta = ucwords(iconv('ISO-8859-2', 'UTF-8', strftime("%A, %d de %B de %Y", strtotime(date('Y-m-d')))));
        $arrDatos["fechaActual"] = $fechaCarta;
        
        $mensaje->html = $this->reemplazarCampos($mensaje->html, $arrDatos);
        $mensaje->asunto = $this->reemplazarCampos($mensaje->asunto, $arrDatos);
        try {
            if(!isset($empleado->correo)){
                return response()->json([
                    "success" => false,
                    "error" => "Correo vacio"
                ]);
            }
            else{
                $mailable = new MensajeGeneralMail($mensaje->asunto, $mensaje->html, $sender_mail, $sender_name);
                //$mailer->to($empleado->correo)->send($mailable);
                return response()->json([
                    "success" => true,
                    "msj" => $mensaje->html
                ]);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                "success" => false,
                "error" => $e->getMessage()
            ]);
        }
    }

    public function basico($numero) {
        $valor = array ('uno','dos','tres','cuatro','cinco','seis','siete','ocho',
        'nueve','diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve',
        'veinte','veintiuno','veintidos','veintitres','veinticuatro','veinticinco',
        'veintiséis','veintisiete','veintiocho','veintinueve');
        return $valor[$numero - 1];
    }        
    public function decenas($n) {
        $decenas = array (30=>'treinta',40=>'cuarenta',50=>'cincuenta',60=>'sesenta',
        70=>'setenta',80=>'ochenta',90=>'noventa');
        if( $n <= 29){
            return $this->basico($n);
        } 
        $x = $n % 10;
        if ( $x == 0 ) {
            return $decenas[$n];
        } else{
            return $decenas[$n - $x].' y '. $this->basico($x);  
        } 
    }        
    public function centenas($n) {
        $cientos = array (100 =>'cien',200 =>'doscientos',300=>'trecientos',
        400=>'cuatrocientos', 500=>'quinientos',600=>'seiscientos',
        700=>'setecientos',800=>'ochocientos', 900 =>'novecientos');
        if( $n >= 100) {
            if ( $n % 100 == 0 ) {
                return $cientos[$n];
            } 
            else {
                $u = (int) substr($n,0,1);
                $d = (int) substr($n,1,2);
                return (($u == 1)?'ciento':$cientos[$u*100]).' '.$this->decenas($d);
            }
        } else return $this->decenas($n);
    }        
    public function miles($n) {
        if($n > 999) {
            if( $n == 1000) {
                return 'mil';
            }
            else {
                $l = strlen($n);
                $c = (int)substr($n,0,$l-3);
                $x = (int)substr($n,-3);
                if($c == 1) {
                    $cadena = 'mil '.$this->centenas($x);
                }
                else if($x != 0) {
                    $cadena = $this->centenas($c).' mil '.$this->centenas($x);
                }
                else{
                    $cadena = $this->centenas($c). ' mil';
                }
                return $cadena;
            }
        } 
        else{
            return $this->centenas($n);
        }
    }        
    public function millones($n) {
        if($n == 1000000) {
            return 'un millón';
        }
        else {
            $l = strlen($n);
            $c = (int)substr($n,0,$l-6);
            $x = (int)substr($n,-6);
            if($c == 1) {
                $cadena = ' millón ';
            } else {
                $cadena = ' millones ';
            }
            return $this->miles($c).$cadena.(($x > 0)?$this->miles($x):'');
        }
    }
    public function convertir($n) {
        switch (true) {
            case ($n <= 0):
                return "CERO";
                break;
            case ( $n >= 1 && $n <= 29):
                return $this->basico($n);
                break;
            case ( $n >= 30 && $n < 100):
                return $this->decenas($n); 
                break;
            case ( $n >= 100 && $n < 1000):
                return $this->centenas($n); 
                break;
            case ($n >= 1000 && $n <= 999999): 
                return $this->miles($n); 
                break;
            case ($n >= 1000000): 
                return $this->millones($n);
                break;
        }
    }
}
