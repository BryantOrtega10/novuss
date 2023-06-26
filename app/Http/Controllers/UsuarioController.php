<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Requests\UserRequest;
use App\Http\Requests\CrearUsuarioAdminRequest;
use App\Http\Requests\SoloPassRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\User;
use Illuminate\Support\Facades\Log;

class UsuarioController extends Controller
{
    public function index() {
        $usu = $this->dataAdminLogueado();
        $usuario = Auth::user();
        $usuarios = User::select(
            'users.*',
            'rol.nombre'
        )
        ->join('rol', 'users.fkRol', 'rol.idrol')
        //->where("users.id","<>", $usuario->id)
        ->get();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", ingresó al menu 'Usuarios'");


        return view('/usuarios/usuarios', [
            'usuarios' => $usuarios,
            'dataUsu' => $usu
        ]);
    }

    public static function dataAdminLogueado() {
        $usuario = Auth::user();
        $dataUsu = DB::table('users')->select(
            'users.id',
            'users.username',
            'users.email',
            'users.primerNombreUser as primerNombre',
            'users.primerApellidoUser as primerApellido',
            'users.fotoUser as foto',
            'users.fkRol'
        )
        ->where('users.id', $usuario->id)
        ->first();

        $empresaUsuario = DB::table("user_empresa")->where("fkUser", "=",$usuario->id)->get();
        $permisosUsuario = DB::table("permisos_user")->where("fkUser", "=",$usuario->id)->get();

        $empresasArr = array();
        foreach($empresaUsuario as $empUser){
            array_push($empresasArr, $empUser->fkEmpresa);
        }

        $permisosArr = array();
        foreach($permisosUsuario as $permisoUsuario){
            array_push($permisosArr, $permisoUsuario->fkMenu);
        }
        if($dataUsu->fkRol == 3){
            $menus = DB::table("menu","m")->orderBy("m.fkMenu")->get();
            $permisosArr = array();
            foreach($menus as $menu){
                array_push($permisosArr, $menu->idMenu);
            }
        }
       

        $dataUsu->empresaUsuario = $empresasArr;
        $dataUsu->permisosUsuario = $permisosArr;

        return $dataUsu;
    }

    public function getFormAdd() {
        $empresas = DB::table('empresa')
        ->select(
            'idempresa',
            'razonSocial'
        )
        ->orderBy("razonSocial")
        ->get();

        $menus = DB::table("menu","m")->orderBy("m.fkMenu")->get();
        $arrMenu = array();
        foreach($menus as $itemMenu){
            $itemMenu->subItems = array();
            if(isset($itemMenu->fkMenu)){
                if(isset($arrMenu[$itemMenu->fkMenu])){
                    array_push($arrMenu[$itemMenu->fkMenu]->subItems, $itemMenu);
                }
                else{
                    foreach($arrMenu as $menuLv1){
                        foreach($menuLv1->subItems as $menuLv2){
                            if($menuLv2->idMenu == $itemMenu->fkMenu){
                                array_push($menuLv2->subItems, $itemMenu);
                                break;
                            }
                        }
                    }
                }
            }
            else{
                $arrMenu[$itemMenu->idMenu] = $itemMenu;
            }
        }
        
        return view('/usuarios/addUsuario', [
            'empresas' => $empresas,
            'arrMenu' => $arrMenu
        ]);
    }

    

    public function create(CrearUsuarioAdminRequest $request) {
        // Creamos primero registro de datos personales

        if($request->fkRol == "2"){
            if(!isset($request->empresa)){
                return response()->json([
                    'success' => false, 'mensaje' => 'Error selecciona al menos una empresa'
                ]);
            }
            else{
                foreach($request->empresa as $empresa){
                    if($empresa == ""){
                        return response()->json([
                            'success' => false, 'mensaje' => 'Alguna empresa sin seleccionar'
                        ]);
                    }   
                }
            }

            if(!isset($request->permiso)){
                return response()->json([
                    'success' => false, 'mensaje' => 'Error seleccione al menos un permiso'
                ]);
            }

        }

        
        
        // Creamos registro en la tabla usuarios
        $usuarioEx = User::where('email',"=",$request->username)->first();
        if(isset($usuarioEx)){
            return response()->json([
                'success' => false, 'mensaje' => 'Error el usuario ya se encuentra registrado'
            ]);
        }
        $name="";
        if($request->hasFile("foto")){
            $image = $request->file('foto');
            $name = "imagen_" . time() . '.' . $image->getClientOriginalExtension();
            $destinationPath = storage_path('app/public/imgEmpleados');
            $image->move($destinationPath, $name);
        }   

        $usuario = new User();
        $usuario->username = $request->username;
        $usuario->email = $request->username;
        $usuario->password = $request->password;
        $usuario->fkRol = $request->fkRol;
        $usuario->primerNombreUser = $request->primerNombre;
        $usuario->primerApellidoUser = $request->primerApellido;
        $usuario->fotoUser = $name;
        $usuario->estado = 1;
        $usuario->created_at = date("Y-m-d H:i:s");
        $usuario->updated_at = date("Y-m-d H:i:s");
        $save = $usuario->save();

        $dataUsu = UsuarioController::dataAdminLogueado();
        Log::channel('gesath')->info("El usuario ".$dataUsu->email.", creó un nuevo usuario");

        if(isset($request->empresa)){
            foreach($request->empresa as $empresa){
                $user_emp = DB::table("user_empresa")
                ->where("fkUser","=",$usuario->id)
                ->where("fkEmpresa","=",$empresa)
                ->first();
                if(!isset($user_emp)){
                    DB::table("user_empresa")->insert([
                        "fkUser" => $usuario->id,
                        "fkEmpresa" => $empresa
                    ]);
                }            
            }
        }

        if(isset($request->permiso)){
            foreach($request->permiso as $permiso){
                DB::table("permisos_user")->insert([
                    "fkUser" => $usuario->id,
                    "fkMenu" => $permiso
                ]);
            }
        }


        if ($save) {
            $success = true;
            $mensaje = "Usuario agregado correctamente";
        } else {
            $success = true;
            $mensaje = "Error al agregar usuario";
        }
        return response()->json(["success" => $success, "mensaje" => $mensaje]);
    }

    public function edit($id) {
        try {
            $usuario = User::findOrFail($id);
            $empresas = DB::table("empresa","e")->orderBy("razonSocial")->get();
            $empresas_usuario = DB::table("user_empresa")->where("fkUser", "=",$id)->get();
            $permisos_usuario = DB::table("permisos_user")->where("fkUser", "=",$id)->get();
            $arrPermisoUser = array();
            foreach ($permisos_usuario as $permiso_usuario){
                array_push($arrPermisoUser, $permiso_usuario->fkMenu);
            }

            $menus = DB::table("menu","m")->orderBy("m.fkMenu")->get();
            $arrMenu = array();
            foreach($menus as $itemMenu){
                $itemMenu->subItems = array();
                if(isset($itemMenu->fkMenu)){
                    if(isset($arrMenu[$itemMenu->fkMenu])){
                        array_push($arrMenu[$itemMenu->fkMenu]->subItems, $itemMenu);
                    }
                    else{
                        foreach($arrMenu as $menuLv1){
                            foreach($menuLv1->subItems as $menuLv2){
                                if($menuLv2->idMenu == $itemMenu->fkMenu){
                                    array_push($menuLv2->subItems, $itemMenu);
                                    break;
                                }
                            }
                        }
                    }
                }
                else{
                    $arrMenu[$itemMenu->idMenu] = $itemMenu;
                }
            }
            
            return view('/usuarios/editUsuario', [
                'usuario' => $usuario,
                "empresas" => $empresas,
                "arrPermisoUser" => $arrPermisoUser,
                "empresas_usuario" => $empresas_usuario,
                "arrMenu" => $arrMenu
            ]);
		}
		catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe un usuario con este ID"]);
		}
    }

    public function detail($id) {
        try {
            $usuario = User::findOrFail($id);
            $empresas = DB::table("empresa","e")->orderBy("razonSocial")->get();
            $empresas_usuario = DB::table("user_empresa")->where("fkUser", "=",$id)->get();
            return view('/usuarios/detailUsuario', [
                'usuario' => $usuario,
                "empresas" => $empresas,
                "empresas_usuario" => $empresas_usuario
            ]);
		}
		catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe una usuario con este ID"]);
		}
    }

    public function update(Request $request, $id) {
        try {

            if(!isset($request->fkRol)){
                $request->fkRol = "1";
            }

            if($request->fkRol == "2"){
                if(!isset($request->empresa)){
                    return response()->json([
                        'success' => false, 'mensaje' => 'Error selecciona al menos una empresa'
                    ]);
                }
                else{
                    foreach($request->empresa as $empresa){
                        if($empresa == ""){
                            return response()->json([
                                'success' => false, 'mensaje' => 'Alguna empresa sin seleccionar'
                            ]);
                        }   
                    }
                }
                if(!isset($request->permiso)){
                    return response()->json([
                        'success' => false, 'mensaje' => 'Error seleccione al menos un permiso'
                    ]);
                }
            }
    
            
            
            // Creamos registro en la tabla usuarios
            $usuarioEx = User::where('email',"=",$request->username)
            ->where("id","<>",$id)
            ->first();
            if(isset($usuarioEx)){
                return response()->json([
                    'success' => false, 'mensaje' => 'Error el usuario ya se encuentra registrado'
                ]);
            }

            $usuario = User::findOrFail($id);
            $name=$usuario->fotoUser;
            if($request->hasFile("foto")){
                $image = $request->file('foto');
                $name = "imagen_" . time() . '.' . $image->getClientOriginalExtension();
                $destinationPath = storage_path('app/public/imgEmpleados');
                $image->move($destinationPath, $name);
            }

            $usuario->username = $request->username;
            $usuario->email = $request->username;
            if(isset($usuario->password) && !empty($request->password)){
                $usuario->password = $request->password;
            }
            
            $usuario->fkRol = $request->fkRol;
            $usuario->primerNombreUser = ($request->primerNombre ?? "");
            $usuario->primerApellidoUser = ($request->primerApellido ?? "");
            $usuario->fotoUser = $name;
            $usuario->updated_at = date("Y-m-d H:i:s");
            $save = $usuario->save();
            $dataUsu = UsuarioController::dataAdminLogueado();
            Log::channel('gesath')->info("El usuario ".$dataUsu->email.", modificó el usuario:".$id);
            
            DB::table("user_empresa")->where("fkUser", "=", $usuario->id)->delete();
            if(isset($request->empresa)){
                foreach($request->empresa as $empresa){
                    $user_emp = DB::table("user_empresa")
                    ->where("fkUser","=",$usuario->id)
                    ->where("fkEmpresa","=",$empresa)
                    ->first();
                    if(!isset($user_emp)){
                        DB::table("user_empresa")->insert([
                            "fkUser" => $usuario->id,
                            "fkEmpresa" => $empresa
                        ]);
                    }            
                }
            }
            DB::table("permisos_user")->where("fkUser", "=", $usuario->id)->delete();
            if(isset($request->permiso)){
                foreach($request->permiso as $permiso){
                    DB::table("permisos_user")->insert([
                        "fkUser" => $usuario->id,
                        "fkMenu" => $permiso
                    ]);
                }
            }
            if ($save) {
                $success = true;
                $mensaje = "Usuario actualizado correctamente";
            } else {
                $success = true;
                $mensaje = "Error al actualizar usuario";
            }
            return response()->json(["success" => $success, "mensaje" => $mensaje]);
            }
		catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe una usuario con este ID"]);
		}
    }

    public function hab_deshab_usu($id, $estado){
        if ($estado == 0) {
            $estadoAct = 1;
            $mensaje = "Usuario habilitado correctamente";
        } else {
            $estadoAct = 0;
            $mensaje = "Usuario deshabilitado correctamente";
        }
        try {
            $usuario = User::findOrFail($id);
            $usuario->estado = $estadoAct;
            $save = $usuario->save();
            if ($save) {
                $success = true;
            } else {
                $success = false;
            }
            return response()->json(["success" => $success, "mensaje" => $mensaje]);
            }
		catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe una usuario con este ID"]);
		}
    }

    public function delete($id) {
        try{
            $usuario = User::findOrFail($id);
            if($usuario->delete()){
                $dataUsu = UsuarioController::dataAdminLogueado();
                Log::channel('gesath')->info("El usuario ".$dataUsu->email.", eliminó el usuario:".$id);
                $success = true;
                $mensaje = "Usuario eliminado con exito";
            } else {
                $success = false;
                $mensaje = "Error al eliminar usuario";
            }
			return response()->json(["success" => $success, "mensaje" => $mensaje]);
		} catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe una usuario con este ID"]);
        }
    }

    public function vistaActPass($id) {
        try {
            $usuario = User::findOrFail($id);
            return view('/usuarios/cambiarPass', [
                'usuario' => $usuario
            ]);
		}
		catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe una usuario con este ID"]);
		}
    }

    public function actPass(SoloPassRequest $request, $id) {
        try {
            $usuario = User::findOrFail($id);
            $usuario->password = $request->password;
            $save = $usuario->save();
            if ($save) {
                $success = true;
                $mensaje = "Contraseña modificada correctamente";
            } else {
                $success = false;
                $mensaje = "Error al modificar contraseña";
            }
            return response()->json(["success" => $success, "mensaje" => $mensaje]);
            }
		catch (ModelNotFoundException $e)
		{
		    return response()->json(["success" => false, "mensaje" => "Error, No existe una usuario con este ID"]);
		}
    }

    public function addEmpresa($numEmpresa){
        $empresas = DB::table("empresa","e")->orderBy("razonSocial")->get();
        return view('usuarios/addEmpresa',[
            "numEmpresa" => $numEmpresa,
            "empresas" => $empresas
        ]);
        

    }


}
