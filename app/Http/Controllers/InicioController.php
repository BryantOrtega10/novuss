<?php

namespace App\Http\Controllers;

use App\EmpleadoModel;
use Swift_SmtpTransport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\RecuperarPassMail;
use App\Http\Requests\ActPassRequest;
use App\SMTPConfigModel;
use App\User;
use Config;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Dsn;

class InicioController extends Controller
{
    public function index(Request $request){
        
        if ($request->session()->has('usuario')) {
            header('location: /empleado/');
        }
        else{
            return view('/inicio.inicio');
        }
    }

    public function noPermitido() {
        return view('/noPermitido');
    }

    public function __construct()
    {
        $this->middleware('guest', ['except' => 'logout']);
    }

    protected function username() {
        return 'email';
    }

    public function login(LoginRequest $request)
    {
        $dataUsu = User::where('email', $request->email)->first();
        if ($dataUsu) {
             $emple = DB::table('empleado')->select('fkEstado')->where('idempleado', $dataUsu->fkEmpleado)->first();
             if (isset($emple)) {
                $estadoUsu = $dataUsu->estado;
                if ($estadoUsu == 1) {
                    $credentials = $request->only($this->username(), 'password');
                    $authSuccess = Auth::attempt($credentials);
            
                    if($authSuccess) {
                        $request->session()->regenerate();
                        return response(['success' => true, 'rol' => $dataUsu->fkRol], 200);
                    }
            
                    return response()->json(['success' => false, 'mensaje' => 'Error, usuario o contraseña incorrectos']);
                } else {
                    return response()->json(['success' => false, 'mensaje' => 'Error, el usuario no ha sido activado']);
                }   
            } else if (!isset($emple)){
                $estadoUsu = $dataUsu->estado;
                if ($estadoUsu == 1) {
                    $credentials = $request->only($this->username(), 'password');
                    $authSuccess = Auth::attempt($credentials);
            
                    if($authSuccess) {
                        Log::channel('gesath')->info("El usuario ".$dataUsu->email." ha iniciado sesión");
                        $request->session()->regenerate();
                        return response(['success' => true, 'rol' => $dataUsu->fkRol], 200);
                    }
            
                    return response()->json(['success' => false, 'mensaje' => 'Error, usuario o contraseña incorrectos']);
                } else {
                    return response()->json(['success' => false, 'mensaje' => 'Error, el usuario no ha sido activado']);
                }
            }
            else{
                return response()->json(['success' => false, 'mensaje' => 'Error, el usuario no se encuentra activo']);
            }
        } else {
            return response()->json(['success' => false, 'mensaje' => 'Error, usuario o contraseña incorrectos']);
        }
    }

    public function logout(Request $request)
    {
        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", cerró sesión");

        Auth::logout();
        $request->session()->flush();
        $request->session()->regenerate();

        return redirect('/');
    }

    /* RECUPERACION DE CONTRASEÑA */

    public function vistaRecuperarMail() {
        return view('/auth/passwords.email');
    }

    public function vistaActPass($token) {
        return view('/auth/passwords.reset', [
            'token' => $token
        ]);
    }

    public function validarUsuario(Request $request) {

       
        $user = DB::table("users","u")
        ->select('u.email', 'emp.razonSocial', "emp.idempresa")
        ->join("empleado as e","e.idempleado","=","u.fkEmpleado")
        ->join("periodo as p","p.fkEmpleado","=","e.idempleado")        
        ->join('nomina as n', 'n.idNomina', '=', 'p.fkNomina')
        ->join('empresa as emp', 'emp.idempresa', '=', 'n.fkEmpresa')
        ->join("datospersonales as dp","e.fkDatosPersonales","=","dp.idDatosPersonales")
        ->where("dp.correo","=",$request->email)
        ->where("p.fkEstado","=","1")
        ->first();

        
        if (!$user) {
            return response()->json(['success' => false, 'mensaje' => 'Error, este usuario o correo electrónico no está registrado en el sistema']);
        }


        $dataUsu = $user;

        DB::table('password_resets')->insert([
            'email' => $request->email,
            'token' => $this->generateRandomString(60),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $tokenData = DB::table('password_resets')->where('email', $request->email)->first();

        if ($this->sendResetEmail($request->email, $tokenData->token, $dataUsu->razonSocial, $dataUsu->idempresa)) {
            return response()->json(['success' => true, 'mensaje' => 'Ha sido enviado un enlace de recuperación al correo electrónico. Debes revisar la bandeja de entrada']);
        } else {
            return response()->json(['success' => false, 'mensaje' => 'Error de envío de correo electrónico']);
        }
    }

    private function sendResetEmail($email, $token, $nomEmpre, $idEmpresa) {
        try {
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

            $smtConfig = DB::table("smtp_config","s")
            ->join("empresa as e","e.fkSmtpConf", "=","s.id_smpt")
            ->where("e.idempresa","=",$idEmpresa)->first();     
            
            $mailer = app(Mailer::class);
            $transport = (new Transport(Transport::getDefaultFactories()))->fromDsnObject(new Dsn(
                'smtps',
                $arrSMTPDefault['host'],
                $arrSMTPDefault['user'],
                $arrSMTPDefault['pass'],
                $arrSMTPDefault['port']
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
                    $smtConfig->smtp_port
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
            $mailable = new RecuperarPassMail($email, $token, $nomEmpre,  $sender_mail, $sender_name);
            $mailer->to($email)->send($mailable);
            return true;
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            return false;
        }
    }

    public function resetPassword(ActPassRequest $request) {
        $password = $request->password;

        $tokenData = DB::table('password_resets')->where('token', $request->token)->first();

        if (!$tokenData) return response()->json(['success' => false, 'mensaje' => 'Error, no está disponible la recuperación de contraseña. Repita el proceso']);

        $user = DB::table("users","u")
        ->select('u.id', 'u.email', 'emp.razonSocial', "emp.idempresa")
        ->join("empleado as e","e.idempleado","=","u.fkEmpleado")
        ->join("periodo as p","p.fkEmpleado","=","e.idempleado")        
        ->join('nomina as n', 'n.idNomina', '=', 'p.fkNomina')
        ->join('empresa as emp', 'emp.idempresa', '=', 'n.fkEmpresa')
        ->join("datospersonales as dp","e.fkDatosPersonales","=","dp.idDatosPersonales")
        ->where("dp.correo","=",$tokenData->email)
        ->where("p.fkEstado","=","1")
        ->first();

        $user = User::find($user->id);
        
        if (!$user) return response()->json(['success' => false, 'mensaje' => 'Error, correo electrónico no encontrado']);
        
        $user->password = $password;
        $update = $user->update();

        if ($update) {
            //DB::table('password_resets')->where('token', $request->token)->delete();
            return response()->json(['success' => true, 'mensaje' => 'Contraseña actualizada correctamente']);
        }
    }

    function generateRandomString($length) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
   
}
