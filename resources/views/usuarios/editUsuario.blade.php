<form action="/usuarios/editarUsuario/{{ $usuario->id }}" class="formEdit add_user" method = "POST">
    <div class="form-group">
        <label for="username">Nombre de usuario</label>
        <input type="text" class="form-control" id="username" name = "username" value = "{{ $usuario->username }}">
    </div>
    
    
        <div class="form-group" style="position: relative">
            <label for="password">Contraseña (dejar vacia para no efectuar cambios)</label>
            <div class="position-relative">
                <input type="password" class="form-control" id="password" name = "password">
                <div class="ojo-password" data-para="password"><i class="fas fa-eye"></i></div>
            </div>
        </div>
    @if ($usuario->fkRol != 1)
        <div class="form-group">
            <label for="fkRol">Rol</label>
            <select name="fkRol" class="form-control" id = "fkRol">
                <option value="">-- Seleccione una opción --</option>
                <option value="2"
                    @if ($usuario->fkRol == 2)
                        selected="selected"
                    @endif
                >Administrador</option>
                <option value="3"
                    @if ($usuario->fkRol == 3)
                        selected="selected"
                    @endif
                >Superadministrador</option>
            </select>
        </div>
        <div class="cont_empresas @if ($usuario->fkRol == 2) activo @endif">
            <a href="/usuarios/addEmpresa" class="btn btn-secondary addEmpresa">Agregar empresa</a>
            
            <br><br>
            <div class="cont_empresas_add">
                @php
                    $numEmpresa = 0;
                @endphp
                @foreach ($empresas_usuario as $empresaUse)
                    <div class="row filaEmpresa" data-id="{{$numEmpresa}}">
                        <div class="col">
                            <div class="form-group empresa">
                                <label for="empresa_{{$numEmpresa}}">Empresa</label>
                                <select name="empresa[]" class="form-control" id = "empresa_{{$numEmpresa}}" required>
                                    <option value="">-- Seleccione una opción --</option>
                                    @foreach ($empresas as $empresa)
                                        <option value="{{$empresa->idempresa}}" @if ($empresa->idempresa == $empresaUse->fkEmpresa)
                                            selected
                                        @endif                                        
                                            >{{$empresa->razonSocial}}</option>
                                    @endforeach
                                </select>
                            </div> 
                        </div>
                        <div class="col-1 align-bottom"><br>
                            <a href="#" class="btn btn-danger quitarEmpresa" data-id="{{$numEmpresa}}"><i class="fas fa-trash"></i></a>
                        </div>
                    </div>
                    @php
                        $numEmpresa++;
                    @endphp
                @endforeach
            </div>
            <input type="hidden" id="numEmpresa" value="{{$numEmpresa}}"  />
        </div>

        <div class="cont_permisos @if ($usuario->fkRol == 2) activo @endif">
            <ul class="permisos_lv1">
            @foreach ($arrMenu as $menu)
                <li>
                    <div class="form-check">
                        <input class="form-check-input" @if (in_array($menu->idMenu, $arrPermisoUser)) checked @endif type="checkbox" name="permiso[]" value="{{$menu->idMenu}}" id="permiso_{{$menu->idMenu}}" /> 
                        <label class="form-check-label" for="permiso_{{$menu->idMenu}}">{{$menu->nombre}}</label>
                    </div>
                    
                    @if (sizeof($menu->subItems) > 0)
                        <ul class="permisos_lv2">
                        @foreach ($menu->subItems as $menulv2)
                        <li>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" @if (in_array($menulv2->idMenu, $arrPermisoUser)) checked @endif  name="permiso[]" value="{{$menulv2->idMenu}}" id="permiso_{{$menulv2->idMenu}}"  /> 
                                <label class="form-check-label" for="permiso_{{$menulv2->idMenu}}">{{$menulv2->nombre}}</label>
                            </div>
                            @if (sizeof($menulv2->subItems) > 0)
                                <ul class="permisos_lv3">
                                @foreach ($menulv2->subItems as $menulv3)
                                    <li>
                                        <div class="form-check">
                                            <input class="form-check-input" @if (in_array($menulv3->idMenu, $arrPermisoUser)) checked @endif type="checkbox" name="permiso[]" value="{{$menulv3->idMenu}}" id="permiso_{{$menulv3->idMenu}}" />
                                            <label class="form-check-label" for="permiso_{{$menulv3->idMenu}}">{{$menulv3->nombre}}</label>
                                        </div>
                                    </li>
                                @endforeach
                                </ul>
                            @endif
                        </li>
                        @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
            </ul>
        </div>
        <div class="row">
            <div class="col form-group">
                <label for="primerNombre">Nombre</label>
                <input class = "form-control" type = "text" name = "primerNombre" id = "primerNombre" placeholder = "Primer Nombre" required value="{{$usuario->primerNombreUser}}">
            </div>
            <div class="col form-group">
                <label for="primerApellido">Apellido</label>
                <input class = "form-control" type = "text" name = "primerApellido" id = "primerApellido" placeholder = "Primer Apellido" required value="{{$usuario->primerApellidoUser}}">
            </div>
        </div>   
    
    <div class="form-group">
        <label for="foto">Foto</label>
        <div class="contFotoUser">                        
            <img src="{{ Storage::url("imgEmpleados/".$usuario->fotoUser) }}" class="" id="foto" />            
        </div>
        <input class = "form-control" type="file" name = "foto" id = "foto">
    </div>    
    @endif

    <div class="form-group">
        <button type="submit" class="btn btn-primary">Modificar usuario</button>
    </div>
</form>