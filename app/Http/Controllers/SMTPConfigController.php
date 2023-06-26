<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SMTPConfigRequest;
use App\SMTPConfigModel;
use App\EmpresaModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SMTPConfigController extends Controller
{
    public function index($id) {
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

        $smtp = SMTPConfigModel::select([
            'smtp_config.*',
            'empresa.fkSmtpConf'
        ])
        ->join('empresa', 'smtp_config.id_smpt', 'empresa.fkSmtpConf')
        ->where('empresa.idempresa', $id)
        ->first();

        $usu = UsuarioController::dataAdminLogueado();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó a la accion configurar smtp por empresa para la empresa".$id);
        
        return view('/empresas/smtpConf.smtpConfig', [
            "smtp" => $smtp,
            "smtpDefault" => $arrSMTPDefault,
            'dataUsu' => $usu,
            "idEmpresa" => $id
        ]);
    }

    public function create(SMTPConfigRequest $request) {
        $success = '';
        $mensaje = '';
        if ($request->confPropia == 'true') {
            $guardarSMTP = $this->agregarSMTPaBD(
                $request->smtp_host,
                $request->smtp_port,
                $request->smtp_username,
                $request->smtp_password,
                $request->smtp_encrypt,
                $request->smtp_mail_envia,
                $request->smtp_nombre_envia
            );
            if ($guardarSMTP) {
                $dataUsu = UsuarioController::dataAdminLogueado();
                Log::channel('gesath')->info("El usuario ".$dataUsu->email.", creó una nueva configuración smtp para la empresa:".$request->idEmpresa);
                $actConfEmpresa = $this->actConfSmtpEmpresa(
                    $request->idEmpresa,
                    $guardarSMTP->id_smpt
                );
                if ($actConfEmpresa) {
                    $success = true;
                    $mensaje = "Configuración actualizada correctamente";
                } else {
                    $success = false;
                    $mensaje = "Error al actualizar configuración";
                }
            } else {
                $success = false;
                $mensaje = "Error al actualizar configuración";
            }
        } else {
            $actConfEmpresa = $this->actConfSmtpEmpresa(
                $request->idEmpresa,
                11
            );
            if ($actConfEmpresa) {
                $success = true;
                $mensaje = "Configuración actualizada correctamente";
            } else {
                $success = false;
                $mensaje = "Error al actualizar configuración";
            }
        }

        return response()->json(["success" => $success, "mensaje" => $mensaje]);
    }

    public function actConfSmtpEmpresa($id, $idSmtp) {
        $retorno = false;
        $empresaModel = EmpresaModel::findOrFail($id);
        $empresaModel->fkSmtpConf = $idSmtp;
        $actualizar = $empresaModel->save();
        if ($actualizar) {
            $retorno = true;
        }

        return $retorno;
    }

    public function agregarSMTPaBD($host, $puerto,  $usuario, $pass, $encrypt, $mailEnvia, $nomEnvia) {
        $retorno = false;
        $smtp = new SMTPConfigModel();
        $smtp->smtp_host = $host;
        $smtp->smtp_port = $puerto;
        $smtp->smtp_username = $usuario;
        $smtp->smtp_password = $pass;
        $smtp->smtp_encrypt = $encrypt;
        $smtp->smtp_mail_envia = $mailEnvia;
        $smtp->smtp_nombre_envia = $nomEnvia;
        $insertar = $smtp->save();
        if ($insertar) {
            return $smtp;
        }

        return $retorno;
    }

    public function getSmtpGeneral(){
        $smtp = SMTPConfigModel::where("id_smpt","=","1")->first();
        $usu = UsuarioController::dataAdminLogueado();
        $url = action('SMTPConfigController@modificarSmtpGeneral');

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu de configuración smtp general");

        return view('/smtpGeneral/index', [
            "smtp" => $smtp,
            'dataUsu' => $usu,
            "url" => $url
        ]);
    }

    public function modificarSmtpGeneral(SMTPConfigRequest $request){
        
        $smtp = SMTPConfigModel::find($request->id_smpt);
        $smtp->smtp_host = $request->smtp_host;
        $smtp->smtp_port = $request->smtp_port;
        $smtp->smtp_username = $request->smtp_username;
        $smtp->smtp_password = $request->smtp_password;
        $smtp->smtp_encrypt = $request->smtp_encrypt;
        $smtp->smtp_mail_envia = $request->smtp_mail_envia;
        $smtp->smtp_nombre_envia = $request->smtp_nombre_envia;
        $update = $smtp->save();
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", modificó la configuración smtp general");
        return response()->json(["success" => true, "mensaje" => "Se modificó correctamente"]);
    }


}