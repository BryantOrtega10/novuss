@extends('layouts.admin')
@section('title', 'Ver empleado')
@section('menuLateral')
    @include('layouts.partials.menu', [
        'dataUsu' => $dataUsu
    ])
@endsection

@section('contenido')
<h1 class="ordenSuperior">Ver {{$empleado->nombreTipoDoc}} - {{$empleado->numeroIdentificacion}} - {{ $empleado->primerApellido . ' ' . $empleado->segundoApellido . ' ' . $empleado->primerNombre . ' ' . $empleado->segundoNombre }}</h1>
<nav>
    <div class="navGeneral nav nav-tabs" id="nav-tab" role="tablist">
        <a class="nav-item nav-link @if($destino=="") active @endif" id="nav-datosP-tab" data-toggle="tab" href="#nav-datosP" role="tab" aria-controls="nav-datosP" aria-selected="@if($destino=="") true @else false @endif">Datos Personales</a>
        <a class="nav-item nav-link @if($destino=="infoLab") active @endif" id="nav-infoLab-tab" data-toggle="tab" href="#nav-infoLab" role="tab" aria-controls="nav-infoLab" aria-selected="@if($destino=="infoLab") true @else false @endif">Información Laboral</a>
        <a class="nav-item nav-link @if($destino=="afil") active @endif" id="nav-afiliaciones-tab" data-toggle="tab" href="#nav-afiliaciones" role="tab" aria-controls="nav-afiliaciones" aria-selected="@if($destino=="afil") true @else false @endif">Afiliaciones</a>
        <a class="nav-item nav-link @if($destino=="concFij") active @endif" id="nav-conceptosFijos-tab" data-toggle="tab" href="#nav-conceptosFijos" role="tab" aria-controls="nav-conceptosFijos" aria-selected="@if($destino=="concFij") true @else false @endif">Conceptos Fijos</a>
    </div>
</nav>
<div class="tab-content" id="nav-tabContent">
    <div class="tabGeneral tab-pane fade @if ($destino=="") show active @endif" id="nav-datosP" role="tabpanel" aria-labelledby="nav-datosP-tab">
        <form method="POST" id="formAgregarEmpleado" autocomplete="off" class="formGeneral" action="" enctype="multipart/form-data">
            @csrf
            <input type="hidden" class = "idEmpleado" name="idEmpleado"  value="{{$idEmpleado}}"/>
            <div class="row">
                <div class="col-3">
                    <div class="form-group @isset($empleado->tEmpleado) hasText @endif">
                        <label for="tEmpleado" class="control-label">Tipo de empleado</label>
                        <input type="text" readonly class="form-control" value="{{$empleado->tEmpleado}}" />
                    </div>
                </div>
       
                <div class="col-9 text-right">
                    <input type="hidden" name="fotoAnt" value="{{$empleado->foto}}" />
                    <div class="contFoto">                        
                        <img src="{{ Storage::url($empleado->foto) }}" class="" id="foto" />
                        <input type="file" accept="image/*" id="inputFoto" name="foto">					
                    </div>
                </div>
            </div>
            <section>
                <div class="subTitulo">
                    <h2>Datos personales</h2>
                    <hr />
                </div>
                <div class="row">
                    <div class="col-3">
                        <div class="form-group  @isset($empleado->primerNombre) hasText @endif">
                            <label for="pNombre" class="control-label">Primer Nombre</label>
                            <input type="text" readonly class="form-control" id="pNombre" name="pNombre" value="{{ $empleado->primerNombre }}" />
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group  @isset($empleado->segundoNombre) hasText @endif">
                            <label for="sNombre" class="control-label">Segundo Nombre</label>
                            <input type="text" readonly class="form-control" id="sNombre" name="sNombre" value="{{ $empleado->segundoNombre }}" />
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group  @isset($empleado->primerApellido) hasText @endif">
                            <label for="pApellido" class="control-label">Primer Apellido</label>
                            <input type="text" readonly class="form-control" id="pApellido" name="pApellido" value="{{ $empleado->primerApellido }}" />
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group  @isset($empleado->segundoApellido) hasText @endif">
                            <label for="sApellido" class="control-label">Segundo Apellido</label>
                            <input type="text" readonly class="form-control" id="sApellido" name="sApellido" value="{{ $empleado->segundoApellido }}" />
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-3">
                        <div class="form-group @isset($empleado->fkTipoIdentificacion) hasText @endif">
                            <label for="tIdentificacion" class="control-label">Tipo Identificación</label>
                            @foreach ($tipoidentificacion as $tipoidentificacio)
                                @if($tipoidentificacio->idtipoIdentificacion == $empleado->fkTipoIdentificacion) 
                                <input type="text" readonlyclass="form-control" value="{{$tipoidentificacio->nombre}}" >
                                @endif
                            @endforeach
                        </div>
                        <input type="hidden" id="tIdentificacionAnt" value="{{$empleado->fkTipoIdentificacion}}" name="tIdentificacionAnt" /> 
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($empleado->numeroIdentificacion) hasText @endif">
                            <label for="numIdentificacion" class="control-label">Número Identificación</label>
                            <input type="text" readonly class="form-control" id="numIdentificacion" name="numIdentificacion" value="{{ $empleado->numeroIdentificacion }}"/>
                        </div>
                        <input type="hidden" id="numIdentificacionAnt" value="{{$empleado->numeroIdentificacion}}" name="numIdentificacionAnt" /> 
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($empleado->ubi_pais_exp) hasText @endif">
                            <label for="paisExpedicion" class="control-label">País Expedición</label>
                
                            @foreach ($paises as $pais)
                                @if($pais->idubicacion == $empleado->ubi_pais_exp)
                                <input type="text" readonly class="form-control" value="{{$pais->nombre}}" />
                                @endif
                            @endforeach
                            
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($empleado->ubi_depto_exp) hasText @endif">
                        <label for="deptoExpedicion" class="control-label">Departamento Expedición</label>
                        @foreach ($deptosExp as $deptoExp)
                            @if($deptoExp->idubicacion == $empleado->ubi_depto_exp)
                                <input type="text" readonly class="form-control" value="{{$deptoExp->nombre}}" />
                            @endif
                        @endforeach                         
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-3">
                        <div class="form-group @isset($empleado->fkUbicacionExpedicion) hasText @endif">
                            <label for="lugarExpedicion" class="control-label">Lugar Expedición</label>
                            @foreach ($ciudadesExp as $ciudadExp)
                                @if($ciudadExp->idubicacion == $empleado->fkUbicacionExpedicion)
                                <input type="text" class="form-control" value="{{$ciudadExp->nombre}}" />
                                @endif
                            @endforeach                            
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($empleado->fechaExpedicion) hasText @endif">
                            <label for="fechaExpedicion" class="control-label">Fecha Expedición</label>
                            <input type="date" readonly class="form-control" id="fechaExpedicion" name="fechaExpedicion" value="{{ $empleado->fechaExpedicion }}" />
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($empleado->fkGenero) hasText @endif">
                            <label for="genero" class="control-label">Genero</label>
                            @foreach ($generos as $genero)
                                @if($genero->idGenero == $empleado->fkGenero)
                                    <input type="text" readonly class="form-control" value="{{$genero->nombre}}" />
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($empleado->fkEstadoCivil) hasText @endif">
                            <label for="estadoCivil" class="control-label">Estado Civil</label>
                            @foreach ($estadosCivil as $estadoCivil)
                                @if($estadoCivil->idEstadoCivil == $empleado->fkEstadoCivil)
                                <input type="text" readonly class="form-control" value="{{$estadoCivil->nombre}}" />
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-3">
                        <div class="form-group @isset($empleado->libretaMilitar) hasText @endisset">
                            <label for="libretaMilitar" class="control-label">Libreta Militar</label>
                            <input type="text" readonly class="form-control" id="libretaMilitar" name="libretaMilitar" value="{{ $empleado->libretaMilitar }}"/>
                        </div>
                    </div>
            
                    <div class="col-3">
                        <div class="form-group @isset($empleado->distritoMilitar) hasText @endisset">
                            <label for="distritoMilitar" class="control-label">Distrito Militar - Clase</label>
                            <input type="text" readonly class="form-control" id="distritoMilitar" name="distritoMilitar"  value="{{ $empleado->distritoMilitar }}" />
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($empleado->fkNivelEstudio) hasText @endisset">
                            <label for="nivelEstudio" class="control-label">Nivel estudios</label>                        
                            @foreach ($nivelesEstudios as $nivelEstudio)
                                @if($nivelEstudio->idNivelEstudio == $empleado->fkNivelEstudio)
                                    <input type="text" readonly class="form-control" value="{{$nivelEstudio->nombre}}" />
                                @endif
                            @endforeach                        
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group hasText">
                            <label for="etnia" class="control-label">Etnia</label>
                            @foreach ($etnias as $etnia)
                                @if($etnia->idEtnia == $empleado->fkEtnia)
                                    <input type="text" readonly class="form-control" value="{{$etnia->nombre}}" />
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
            <section>
                <div class="subTitulo">
                    <h2>Fecha y Lugar Nacimiento</h2>
                    <hr />
                </div>
                <div class="row">
                    <div class="col-3">
                        <div class="form-group @isset($empleado->fechaNacimiento) hasText @endisset">
                            <label for="fechaNacimiento" class="control-label">Fecha Nacimiento</label>
                            <input type="date" readonly class="form-control" id="fechaNacimiento" name="fechaNacimiento" value="{{ $empleado->fechaNacimiento }}" />
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($empleado->ubi_pais_nac) hasText @endisset">
                            <label for="paisNacimiento" class="control-label">País Nacimiento</label>
                            @foreach ($paises as $pais)
                                @if($pais->idubicacion == $empleado->ubi_pais_nac) 
                                    <input type="text" readonly class="form-control" value="{{$pais->nombre}}" />
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($empleado->ubi_depto_nac) hasText @endisset">
                            <label for="deptoNacimiento" class="control-label">Departamento Nacimiento</label>
                            
                            
                            @foreach ($deptosNac as $deptoNac)
                                @if($deptoNac->idubicacion == $empleado->ubi_depto_nac)
                                <input type="text" readonly class="form-control" value="{{$deptoNac->nombre}}" />
                                @endif
                            @endforeach
                            
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($empleado->fkUbicacionNacimiento) hasText @endisset">
                            <label for="lugarNacimiento" class="control-label">Lugar Nacimiento</label>
                            @foreach ($ciudadesNac as $ciudadNac)
                                @if($ciudadNac->idubicacion == $empleado->fkUbicacionNacimiento)
                                    <input type="text" readonly class="form-control" value="{{$ciudadNac->nombre}}" />
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </section> 
            <section>
                <div class="subTitulo">
                    <h2>Informaci&oacute;n Residencia</h2>
                    <hr />
                </div>
                <div class="row">
                    <div class="col-3">
                        <div class="form-group @isset($empleado->direccion) hasText @endisset">
                            <label for="direccion" class="control-label">Direccion</label>
                            <input type="text" readonly class="form-control" id="direccion" name="direccion" value="{{ $empleado->direccion }}" />
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($empleado->ubi_pais_res) hasText @endisset">
                            <label for="paisResidencia" class="control-label">País</label>
                            @foreach ($paises as $pais)
                                @if($pais->idubicacion == $empleado->ubi_pais_res)
                                    <input type="text" readonly class="form-control" value="{{$pais->nombre}}" />
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($empleado->ubi_depto_res) hasText @endisset">
                            <label for="deptoResidencia" class="control-label">Departamento</label>
                            @foreach ($deptosRes as $deptoRes)
                                @if($deptoRes->idubicacion == $empleado->ubi_depto_res)
                                    <input type="text" readonly class="form-control" value="{{$deptoRes->nombre}}" />
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($empleado->fkUbicacionResidencia) hasText @endisset">
                            <label for="lugarResidencia" class="control-label">Lugar</label>
                            @foreach ($ciudadesRes as $ciudadRes)
                                @if($ciudadRes->idubicacion == $empleado->fkUbicacionResidencia)
                                    <input type="text" readonly class="form-control" value="{{$ciudadRes->nombre}}" />
                                @endif
                            @endforeach                       
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-3">
                        <div class="form-group @isset($empleado->barrio) hasText @endisset">
                            <label for="barrio" class="control-label">Barrio</label>
                            <input type="text" readonly class="form-control" id="barrio" name="barrio" value="{{ $empleado->barrio }}" />
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($empleado->estrato) hasText @endisset">
                            <label for="estrato" class="control-label">Estrato</label>
                            <input type="text" readonly class="form-control" id="estrato" name="estrato"  value="{{ $empleado->estrato }}" />
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group  @isset($empleado->fkTipoVivienda) hasText @endisset">
                            <label for="tipoVivienda" class="control-label">Tipo vivienda</label>
                            @foreach ($tipo_vivienda as $tipo_viv)
                                @if($tipo_viv->idTipoVivienda == $empleado->fkTipoVivienda)
                                <input type="text" readonly class="form-control" value="{{$tipo_viv->nombre}}" />
                                @endif
                            @endforeach                    
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($empleado->telefonoFijo) hasText @endisset">
                            <label for="telFijo" class="control-label">Telefono fijo</label>
                            <input type="text" readonly class="form-control" id="telFijo" name="telFijo"  value="{{ $empleado->telefonoFijo }}"/>                    
                        </div>
                    </div>
                </div>
                <div class="row">                
                    <div class="col-3">
                        <div class="form-group @isset($empleado->celular) hasText @endisset">
                            <label for="celular" class="control-label">Celular</label>
                            <input type="text" readonly class="form-control" id="celular" name="celular"  value="{{ $empleado->celular }}"/>                    
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($empleado->correo) hasText @endisset">
                            <label for="correo1" class="control-label">Correo principal</label>
                            <input type="email" class="form-control" id="correo1" name="correo1"  value="{{ $empleado->correo }}" />                    
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($empleado->correo2) hasText @endisset">
                            <label for="correo2" class="control-label">Correo secundario</label>
                            <input type="email" class="form-control" id="correo2" name="correo2" value="{{ $empleado->correo2 }}"/>                    
                        </div>
                    </div>


                </div>
                


            </section>
            <section>
                <div class="subTitulo">
                    <h2>Informaci&oacute;n salud ocupacional</h2>
                    <hr />
                </div>
                <div class="row">
                    <div class="col-3">
                        <div class="form-group @isset($empleado->fkGrupoSanguineo) hasText @endisset">
                            <label for="grupoSanguineo" class="control-label">Grupo Sanguineo</label>
                            @foreach ($grupoSanguineo as $grupoSang)
                                @if($grupoSang->idGrupoSanguineo == $empleado->fkGrupoSanguineo)
                                <input type="text" readonly class="form-control" value="{{$grupoSang->nombre}}" />
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($empleado->fkRh) hasText @endisset">
                            <label for="rh" class="control-label">RH</label>
                            @foreach ($rhs as $rh)
                                @if($rh->idRh == $empleado->fkRh)
                                <input type="text" readonly class="form-control" value="{{$rh->nombre}}" />
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($empleado->tallaCamisa) hasText @endisset">
                            <label for="tallaCamisa" class="control-label">Talla Camisa</label>
                            <input type="text" readonly class="form-control" id="tallaCamisa" name="tallaCamisa" value="{{ $empleado->tallaCamisa }}"/>                    
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($empleado->tallaPantalon) hasText @endisset">
                            <label for="tallaPantalon" class="control-label">Talla Pantalon</label>
                            <input type="text" readonly class="form-control" id="tallaPantalon" name="tallaPantalon" value="{{ $empleado->tallaPantalon }}"/>                    
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-3">
                        <div class="form-group @isset($empleado->tallaZapatos) hasText @endisset">
                            <label for="tallaZapatos" class="control-label">Talla Zapatos</label>
                            <input type="text" readonly class="form-control" id="tallaZapatos" name="tallaZapatos" value="{{ $empleado->tallaZapatos }}"/>                    
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($empleado->otros) hasText @endisset">
                            <label for="otros" class="control-label">Otros, Cual?</label>
                            <input type="text" readonly class="form-control" id="otros" name="otros" value="{{ $empleado->otros }}"/>                    
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group @isset($empleado->tallaOtros) hasText @endisset">
                            <label for="tallaOtros" class="control-label">Talla Otros</label>
                            <input type="text" readonly class="form-control" id="tallaOtros" name="tallaOtros" value="{{ $empleado->tallaOtros }}"/>                    
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <h4>Contacto(s) de emergencia</h4>
                       
                    </div>
                </div>
                <div class="emergenciaCont">
                    @for ($i = 1; $i <= sizeof($contactosEmergencia); $i++)
                        <div class="emergencia" data-id="{{$i}}">

                            @if ($i>1)
                            <div class="row">
                                <div class="col-10"></div>
                                
                            </div>
                            @endif
                            <input type="hidden" name="idContactoEmergencia[]" value="{{$contactosEmergencia[$i-1]->idContactoEmergencia}}" />

                            <div class="row">
                                <div class="col-3">
                                    <div class="form-group @isset($contactosEmergencia[$i-1]->nombre) hasText @endisset">
                                        <label for="nombreEmergencia{{$i}}" class="control-label">Nombre</label>
                                        <input type="text" readonly class="form-control" id="nombreEmergencia{{$i}}" data-id="{{$i}}" name="nombreEmergencia[]" value="{{$contactosEmergencia[$i-1]->nombre}}"/>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group @isset($contactosEmergencia[$i-1]->telefono) hasText @endisset">
                                        <label for="telefonoEmergencia{{$i}}" class="control-label">Telefono</label>
                                        <input type="text" readonly class="form-control" id="telefonoEmergencia{{$i}}" data-id="{{$i}}" name="telefonoEmergencia[]" value="{{$contactosEmergencia[$i-1]->telefono}}"/>                    
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group @isset($contactosEmergencia[$i-1]->direccion) hasText @endisset">
                                        <label for="direccionEmergencia{{$i}}" class="control-label">Direccion</label>
                                        <input type="text" readonly class="form-control" id="direccionEmergencia{{$i}}" data-id="{{$i}}" name="direccionEmergencia[]" value="{{$contactosEmergencia[$i-1]->direccion}}"/>                    
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group @isset($contactosEmergencia[$i-1]->ubi_pais_emer) hasText @endisset">
                                        <label for="paisEmergencia{{$i}}" class="control-label">País</label>
                                        @foreach ($paises as $pais)
                                            @if($pais->idubicacion == $contactosEmergencia[$i-1]->ubi_pais_emer)
                                            <input type="text" readonly class="form-control" value="{{$pais->nombre}}" />
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-3">
                                    <div class="form-group @isset($contactosEmergencia[$i-1]->ubi_depto_emer) hasText @endisset">
                                        <label for="deptoEmergencia{{$i}}" class="control-label">Departamento</label>
                                        @foreach ($deptosContactosEmergencia[$i-1] as $deptoCon)
                                            @if($deptoCon->idubicacion == $contactosEmergencia[$i-1]->ubi_depto_emer)
                                            <input type="text" readonly class="form-control" value="{{$deptoCon->nombre}}" />
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group @isset($contactosEmergencia[$i-1]->fkUbicacion) hasText @endisset">
                                        <label for="lugarEmergencia{{$i}}" class="control-label">Lugar</label>
                                        @foreach ($ciudadesContactosEmergencia[$i-1] as $ciudadCon)
                                            @if($ciudadCon->idubicacion == $contactosEmergencia[$i-1]->fkUbicacion)
                                            <input type="text" readonly class="form-control" value="{{$ciudadCon->nombre}}" />
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endfor
                </div>
                <div class="row">
                    <div class="col-12">
                        <h4>Personas con quien vive</h4>
                        
                    </div>
                </div>
                <div class="personaViveCont">

                    @foreach ($nucleofamiliar as $idRow => $nucleo)
                        <div class="personaV" data-id="{{$idRow}}">
                            <div class="row">
                                <div class="col-10">
                                    Persona con quien vive 
                                </div>
                                
                            </div>                            
                            <input type="hidden" name="idNucleoFamiliar[]" value="{{$nucleo->idNucleoFamiliar}}" />
                            <div class="row">
                                <div class="col-3">
                                    <div class="form-group @isset($nucleo->nombre) hasText  @endisset">
                                        <label for="nombrePersonaV{{$idRow}}" class="control-label">Nombre</label>
                                        <input type="text" readonly class="form-control" id="nombrePersonaV{{$idRow}}" data-id="{{$idRow}}" name="nombrePersonaV[]" value="{{$nucleo->nombre}}" />                    
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group @isset($nucleo->fechaNacimiento) hasText  @endisset">
                                        <label for="fechaNacimientoPersonaV{{$idRow}}" class="control-label">Fecha Nacimiento</label>
                                        <input type="date" readonly class="form-control" id="fechaNacimientoPersonaV{{$idRow}}" data-id="{{$idRow}}" name="fechaNacimientoPersonaV[]" value="{{$nucleo->fechaNacimiento}}">                    
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group @isset($nucleo->fkEscolaridad) hasText  @endisset">
                                        <label for="escolaridadPersonaV{{$idRow}}" class="control-label">Escolaridad</label>
                                    
                                        @foreach ($escolaridades as $escolaridad)
                                            @if($escolaridad->idEscolaridad == $nucleo->fkEscolaridad)
                                            <input type="text" readonly class="form-control" value="{{$escolaridad->nombre}}" />
                                            @endif
                                        @endforeach                                                 
                                        
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group @isset($nucleo->fkParentesco) hasText  @endisset">
                                        <label for="parentescoPersonaV{{$idRow}}" class="control-label">Parentesco</label>                                        
                                        @foreach ($parentescos as $parentesco)
                                            @if($parentesco->idParentesco == $nucleo->fkParentesco)
                                            <input type="text" readonly class="form-control" value="{{$parentesco->nombre}}" />
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                    

                </div>
                <div class="row">
                    <div class="col-12">
                        <h4>UPC ADICIONAL</h4>
                        
                    </div>
                </div>
                <div class="upcAdicionalCont">
                    @foreach ($upcAdicional as $idRow => $upcAdic)
                    <div class="upcAdicionalV" data-id="{{$idRow}}">
                        <div class="row">
                            <div class="col-10">
                                UPC ADICIONAL 
                            </div>
                            
                        </div> 
                        <input type="hidden" name="idUpcAdicional[]" value="{{$upcAdic->idUpcAdicional}}" />
                        <div class="row">
                            <div class="col-3">
                                <div class="form-group @isset($upcAdic->primerApellido) hasText  @endisset">
                                    <label for="primerApellidoUpc{{$idRow}}" class="control-label">Primer Apellido:</label>
                                    <input type="text" readonly class="form-control" id="primerApellidoUpc{{$idRow}}" data-id="{{$idRow}}" name="primerApellidoUpc[]" value="{{$upcAdic->primerApellido}}" />                    
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="form-group @isset($upcAdic->segundoApellido) hasText  @endisset">
                                    <label for="segundoApellidoUpc{{$idRow}}" class="control-label">Segundo Apellido:</label>
                                    <input type="text" readonly class="form-control" id="segundoApellidoUpc{{$idRow}}" data-id="{{$idRow}}" name="segundoApellidoUpc[]" value="{{$upcAdic->segundoApellido}}" />                    
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="form-group @isset($upcAdic->primerNombre) hasText  @endisset">
                                    <label for="primerNombreUpc{{$idRow}}" class="control-label">Primer Nombre:</label>
                                    <input type="text" readonly class="form-control" id="primerNombreUpc{{$idRow}}" data-id="{{$idRow}}" name="primerNombreUpc[]" value="{{$upcAdic->primerNombre}}" />                    
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="form-group @isset($upcAdic->segundoNombre) hasText  @endisset">
                                    <label for="segundoNombreUpc{{$idRow}}" class="control-label">Segundo Nombre:</label>
                                    <input type="text" readonly class="form-control" id="segundoNombreUpc{{$idRow}}" data-id="{{$idRow}}" name="segundoNombreUpc[]" value="{{$upcAdic->segundoNombre}}" />                    
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-3">
                                <div class="form-group @isset($upcAdic->fkTipoIdentificacion) hasText @endisset">
                                    <label for="tIdentificacionUpc{{$idRow}}" class="control-label">Tipo Identificación</label>
                                    @foreach ($tipoidentificacion as $tipoidentificacio)                                        
                                            @if($tipoidentificacio->idtipoIdentificacion == $upcAdic->fkTipoIdentificacion)
                                            <input type="text" readonly class="form-control" value="{{$tipoidentificacio->nombre}}" />
                                            @endif
                                    @endforeach                                    
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="form-group @isset($upcAdic->numIdentificacion) hasText @endisset">
                                    <label for="numIdentificacionUpc{{$idRow}}" class="control-label">Número Identificación</label>
                                    <input type="text" readonly class="form-control" id="numIdentificacionUpc{{$idRow}}" data-id="{{$idRow}}" name="numIdentificacionUpc[]" value="{{$upcAdic->numIdentificacion}}"/>                    
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="form-group @isset($upcAdic->fechaNacimiento) hasText @endisset">
                                    <label for="fechaNacimientoUpc{{$idRow}}" class="control-label">Fecha nacimiento</label>
                                    <input type="date" readonly class="form-control" id="fechaNacimientoUpc{{$idRow}}" data-id="{{$idRow}}" name="fechaNacimientoUpc[]" value="{{$upcAdic->fechaNacimiento}}"/>                    
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="form-group @isset($upcAdic->fkGenero) hasText @endisset">
                                    <label for="generoUpc{{$idRow}}" class="control-label">Genero</label>
                                    @foreach ($generosBen as $genero)
                                        @if($genero->idGenero == $upcAdic->fkGenero)
                                            <input type="text" readonly class="form-control" value="{{$genero->nombre}}" />
                                        @endif>
                                    @endforeach                                    
                                </div>
                            </div>
                            
                        </div>
                        <div class="row">
                            <div class="col-3">
                                <div class="form-group @isset($upcAdic->ubi_pais_upc) hasText @endisset">
                                    <label for="paisUpc{{$idRow}}" class="control-label">País</label>
                                    @foreach ($paises as $pais)
                                        @if($pais->idubicacion == $upcAdic->ubi_pais_upc)
                                        <input type="text" readonly class="form-control" value="{{$pais->nombre}}" />
                                        @endif
                                    @endforeach                                    
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="form-group @isset($upcAdic->ubi_depto_upc) hasText @endisset">
                                    <label for="deptoUpc{{$idRow}}" class="control-label">Departamento</label>
                                    @foreach ($deptosUpc[$idRow] as $deptoUpc)
                                        @if($deptoUpc->idubicacion == $upcAdic->ubi_depto_upc)
                                        <input type="text" readonly class="form-control" value="{{$deptoUpc->nombre}}" />
                                        @endif
                                    @endforeach                                    
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="form-group @isset($upcAdic->fkUbicacion) hasText @endisset">
                                    <label for="lugarUpc{{$idRow}}" class="control-label">Lugar</label>
                                    @foreach ($ciudadesUpc[$idRow] as $ciudadUpc)
                                        @if($ciudadUpc->idubicacion == $upcAdic->fkUbicacion)
                                        <input type="text" readonly class="form-control" value="{{$ciudadUpc->nombre}}" />
                                        @endif
                                    @endforeach                                
                                </div>
                            </div>
                            @if (isset($periodo) && $periodo=="15")
                                <div class="col-3">
                                    <div class="form-group hasText">
                                        <label for="periocidad{{$idRow}}" class="control-label">Periocidad</label>
                                        @foreach ($periocidad as $perio)
                                            @if ($upcAdic->fkPeriocidad == $perio->per_id)
                                                <input type="text" readonly class="form-control" value="{{$perio->per_upc}}" />
                                            @endif
                                        @endforeach                                        
                                    </div>
                                </div>
                            @else
                                <input type="hidden" name="periocidad[]" value="1" />
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>

            </section>
            <div class="alert alert-danger" role="alert" id="infoErrorForm" style="display: none;"></div>
            
        </form>

    </div>
    <div class="tabGeneral tab-pane fade @if ($destino=="infoLab") show active @endif" id="nav-infoLab" role="tabpanel" aria-labelledby="nav-infoLab-tab">
        @isset($empleado->fkEmpresa)
            <form class="formGeneral" id="formInfoEmpleado" method="POST" action="" >
                @csrf
                <input type="hidden" class = "idEmpleado" name="idEmpleado" value="{{$idEmpleado}}"/>
                <input type="hidden" name="idEmpresaAnt" value="{{$empleado->fkEmpresa}}"/>
                <input type="hidden" name="fechaIngresoAnt" value="{{$empleado->fechaIngreso}}" />
                <section>
                    <div class="subTitulo">
                        <h2>Informaci&oacute;n general</h2>
                        <hr />
                    </div>
                    <div class="row">
                        <div class="col-3">
                            <div class="form-group @isset($empleado->fkEmpresa) hasText @endisset">
                                <label for="infoEmpresa" class="control-label">Empresa</label>
                                @foreach ($empresas as $empresa)
                                    @if($empresa->idempresa == $empleado->fkEmpresa)
                                    <input type="text" readonly class="form-control" value="{{$empresa->razonSocial}}" />
                                    @endif
                                @endforeach                                
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group @isset($empleado->fkNomina) hasText @endisset">
                                <label for="infoNomina" class="control-label">N&oacute;mina</label>
                                @foreach ($nominas as $nomina)
                                    @if($nomina->idNomina == $empleado->fkNomina)
                                        <input type="text" readonly class="form-control" value="{{$nomina->nombre}}" />
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group @isset($empleado->fechaIngreso) hasText @endisset">
                                <label for="infoFechaIngreso" class="control-label">Fecha ingreso</label>
                                <input type="date" readonly class="form-control" id="infoFechaIngreso" name="infoFechaIngreso" value="{{ $empleado->fechaIngreso }}"/>                    
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group @isset($empleado->tipoRegimen) hasText @endisset">
                                <label for="infoTipoRegimen" class="control-label">Tipo regimen</label>
                                <input type="text" readonly class="form-control" value="{{$empleado->tipoRegimen}}" />
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-3">
                            <div class="form-group @isset($empleado->ubi_pais_tra) hasText @endisset">
                                <label for="infoPaisLabora" class="control-label">País</label>
                                @foreach ($paises as $pais)
                                    @if($pais->idubicacion == $empleado->ubi_pais_tra)
                                        <input type="text" readonly class="form-control" value="{{$pais->nombre}}" />
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        <div class="col-3">
                            
                            <div class="form-group @isset($empleado->ubi_depto_tra) hasText @endisset">
                                <label for="infoDeptoLabora" class="control-label">Departamento</label>
                                @foreach ($deptosTra as $deptoTra)
                                    @if($deptoTra->idubicacion == $empleado->ubi_depto_tra)
                                        <input type="text" readonly class="form-control" value="{{$deptoTra->nombre}}" />
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group @isset($empleado->fkUbicacionLabora) hasText @endisset">
                                <label for="infoLugarLabora" class="control-label">Lugar</label>
                                @foreach ($ciudadesTra as $ciudadTra)
                                    @if($ciudadTra->idubicacion == $empleado->fkUbicacionLabora)
                                    <input type="text" readonly class="form-control" value="{{$ciudadTra->nombre}}" />
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group @isset($empleado->sabadoLaborable) hasText @endisset">
                                <label for="infoSabadoLabora" class="control-label">Sabado laborable?</label>
                                @if ("1" == $empleado->sabadoLaborable) <input type="text" readonly class="form-control" value="SI" /> @endif
                                @if ("0" == $empleado->sabadoLaborable) <input type="text" readonly class="form-control" value="NO" /> @endif
                            </div>
                        </div>
                    </div>
                    <div class="row">                        
                        <div class="col-3">
                            <div class="form-group @isset($empleado->fkCargo) hasText @endisset">
                                <label for="infoCargo" class="control-label">Cargo</label>
                                @foreach ($cargos as $cargo)
                                    @if ($cargo->idCargo == $empleado->fkCargo) 
                                    <input type="text" readonly class="form-control" value="{{$cargo->nombreCargo}}" />
                                    @endif
                                @endforeach                                
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group hasText">
                                <label for="infoUsuario" class="control-label">Usuario</label>
                                <input type="text" readonly class="form-control" id="infoUsuario" name="infoUsuario" readonly value="{{$empleado->usuarioTxt}}"/>                    
                            </div>
                        </div>
                        @if (!$usuExiste)
                        <div class="col-3">
                            <div class="form-group">
                                <label for="password" class="control-label">Contraseña</label>
                                <input type="text" readonly class="form-control pass_usu" id="password" name="password" readonly/>                    
                            </div>
                            
                        </div>
                        @endif
                        <div class="col-3">
                            <div class="form-group hasText">
                                <label for="infoTipoCotizante" class="control-label">Tipo cotizante</label>
                                @foreach ($tiposcotizante as $tipocotizante)
                                    @if ($tipocotizante->idTipoCotizante == $empleado->fkTipoCotizante) 
                                        <input type="text" readonly class="form-control" value="{{$tipocotizante->codigo." - ".$tipocotizante->nombre}}" />
                                    @endif
                                @endforeach                                
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-3">
                            <div class="form-group hasText">
                                <label for="infoSubTipoCotizante" class="control-label">Subtipo cotizante</label>
                                @foreach ($subtiposcotizante as $subtipocotizante)
                                    @if ($subtipocotizante->idSubtipoCotizante == $empleado->esPensionado) 
                                    <input type="text" readonly class="form-control" value="{{$subtipocotizante->codigo." - ".$subtipocotizante->nombre}}" />
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group hasText">
                                <label for="infoAplicaSubsidio" class="control-label">Aplica Subsidio</label>
                                @if ("1" == $empleado->aplicaSubsidio) <input type="text" readonly class="form-control" value="SI" /> @endif
                                @if ("0" == $empleado->aplicaSubsidio) <input type="text" readonly class="form-control" value="NO" /> @endif
                            </div>
                        </div>
                        @if (isset($periodoActivo->fechaFin))
                        <div class="col-3">
                            <div class="form-group hasText">
                                <label for="fechaRetiro" class="control-label">Fecha retiro</label>
                                <input type="date" readonly class="form-control" value="{{$periodoActivo->fechaFin}}" id="fechaRetiro" name="fechaRetiro"/>                    
                            </div>
                        </div>
                        @endif
                    </div>
                </section>
                <section>
                    <div class="row">
                        <div class="col-12">
                            <h4>Centro(s) de costo</h4>
                            
                        </div>
                    </div>
                    <div class="centroCostoCont">
                        @for ($i = 1; $i <= sizeof($centrosCostoxEmpleado); $i++)
                            <div class="centroCosto" data-id="{{$i}}">
                                @if ($i!=1)
                                    <div class="row">
                                        <div class="col-11"></div>
                                        
                                    </div>                                    
                                @endif
                                
                                <input type="hidden" name="idEmpleadoCentroCosto[]" id="idEmpleadoCentroCosto1" value="{{$centrosCostoxEmpleado[$i-1]->idEmpleadoCentroCosto}}" />
                                <div class="row">
                                    <div class="col-3">
                                        <div class="form-group hasText">
                                            <label for="infoCentroCosto{{$i}}" class="control-label">Centro de costo</label>
                                            @foreach ($centrosCostos as $centroCostos)
                                                @if ($centroCostos->idcentroCosto == $centrosCostoxEmpleado[$i-1]->fkCentroCosto)
                                                    <input type="text" readonly class="form-control" value="{{$centroCostos->nombre}}" />
                                                @endif
                                            @endforeach                                            
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group hasText">
                                            <label for="infoPorcentaje{{$i}}" class="control-label">Porcentaje</label>
                                            <input type="text" readonly class="form-control" id="infoPorcentaje{{$i}}" name="infoPorcentaje[]" readonly value="{{$centrosCostoxEmpleado[$i-1]->porcentajeTiempoTrabajado}}%"/>                    
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endfor
                    </div>                    
                </section>
                <section>
                    <div class="subTitulo">
                        <h2>Informaci&oacute;n contrato</h2>
                        <hr />
                    </div>
                    <div class="row">
                        <div class="col-3">
                            <div class="form-group @isset($contratoActivo->fkTipoContrato) hasText @endisset">
                                <label for="infoTipoContrato" class="control-label">Tipo Contrato</label>
                                @foreach ($tipoContratos as $tipoContrato)
                                    @if(isset($contratoActivo->fkTipoContrato) && $contratoActivo->fkTipoContrato == $tipoContrato->idtipoContrato) 
                                    <input type="text" readonly class="form-control" value="{{$tipoContrato->nombre}}" />
                                    @endif
                                @endforeach 
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group  @isset($contratoActivo->tipoDuracionContrato) hasText @endisset">
                                <label for="infoTipoDuracionContrato" class="control-label">Tipo duración</label>
                                <input type="text" readonly class="form-control" value="{{$contratoActivo->tipoDuracionContrato ?? ""}}" />
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group hasText">
                                <label for="infoDuracionContrato" class="control-label">Duración contrato</label>
                                <input type="text" readonly class="form-control" id="infoDuracionContrato" name="infoDuracionContrato" 
                                    @if (isset($contratoActivo) && $contratoActivo->tipoDuracionContrato == "MES")
                                        value="{{$contratoActivo->numeroMeses}}"
                                    @else
                                        @if (isset($contratoActivo) && $contratoActivo->tipoDuracionContrato == "DÍA")
                                            value="{{$contratoActivo->numeroDias}}"
                                        @endif
                                    @endif
                                
                                />
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group @isset($contratoActivo->fechaFin) hasText @endisset">
                                <label for="infoFechaFin" class="control-label">Fecha fin contrato</label>
                                <input type="date" readonly class="form-control" id="infoFechaFin" name="infoFechaFin" readonly value="{{$contratoActivo->fechaFin ?? ""}}"/>
                            </div>
                        </div>
                    </div>
                    @if (sizeof($contratosAnteriores) > 0)
                    <div class="historial_contratos">
                        <div class="subTitulo">
                            <h2>Informaci&oacute;n contratos anteriores</h2>
                            <hr />
                        </div>
                        @foreach ($contratosAnteriores as $contratoAnterior)
                            <div class="row">
                                <div class="col-3">
                                    <div class="form-group @isset($contratoAnterior->fkTipoContrato) hasText @endisset">
                                        <label for="infoTipoContrato" class="control-label">Tipo Contrato</label>
                                        @foreach ($tipoContratos as $tipoContrato)
                                            @if(isset($contratoAnterior->fkTipoContrato) && $contratoAnterior->fkTipoContrato == $tipoContrato->idtipoContrato) 
                                            <input type="text" readonly class="form-control" value="{{$tipoContrato->nombre}}" />
                                            @endif
                                        @endforeach 
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group  @isset($contratoAnterior->tipoDuracionContrato) hasText @endisset">
                                        <label for="infoTipoDuracionContrato" class="control-label">Tipo duración</label>
                                        <input type="text" readonly class="form-control" value="{{$contratoAnterior->tipoDuracionContrato ?? ""}}" />
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group hasText">
                                        <label for="infoDuracionContrato" class="control-label">Duración contrato</label>
                                        <input type="text" readonly class="form-control"  
                                            @if (isset($contratoAnterior) && $contratoAnterior->tipoDuracionContrato == "MES")
                                                value="{{$contratoAnterior->numeroMeses}}"
                                            @else
                                                @if (isset($contratoAnterior) && $contratoAnterior->tipoDuracionContrato == "DÍA")
                                                    value="{{$contratoAnterior->numeroDias}}"
                                                @endif
                                            @endif
                                        
                                        />
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group @isset($contratoAnterior->fechaFin) hasText @endisset">
                                        <label for="infoFechaFin" class="control-label">Fecha fin contrato</label>
                                        <input type="date" readonly class="form-control" value="{{$contratoAnterior->fechaFin ?? ""}}"/>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        
                    </div>
                    @endif
                    
                    
                    <div class="nuevoContrato">
                        <div class="subTitulo">
                            <h2>Informaci&oacute;n nuevo contrato</h2>
                            <hr />
                        </div>
                        <div class="row">
                            <div class="col-3">
                                <div class="form-group">
                                    <label for="infoTipoContratoN" class="control-label">Tipo Contrato</label>
                                    <select disabled class="form-control" id="infoTipoContratoN" name="infoTipoContratoN">
                                        <option value=""></option>
                                        @foreach ($tipoContratos as $tipoContrato)
                                            <option value="{{$tipoContrato->idtipoContrato}}">{{$tipoContrato->nombre}}</option>
                                        @endforeach 
                                    </select>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="form-group">
                                    <label for="infoTipoDuracionContratoN" class="control-label">Tipo duración</label>
                                    <select disabled class="form-control" id="infoTipoDuracionContratoN" name="infoTipoDuracionContratoN">
                                        <option value=""></option>
                                        <option value="MES">MES</option>
                                        <option value="DÍA">DÍA</option>
                                    </select>

                                </div>
                            </div>
                            <div class="col-3">
                                <div class="form-group">
                                    <label for="infoDuracionContratoN" class="control-label">Duración contrato</label>
                                    <input type="text" readonly class="form-control" id="infoDuracionContratoN" name="infoDuracionContratoN"/>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="form-group">
                                    <label for="infoFechaFinN" class="control-label">Fecha fin contrato</label>
                                    <input type="date" readonly class="form-control" id="infoFechaFinN" name="infoFechaFinN" readonly/>
                                </div>
                            </div>
                        </div>
                    </div>

                </section>
                <section>
                    <div class="subTitulo">
                        <h2>Informaci&oacute;n pago n&oacute;mina</h2>
                    </div>
                    <div class="row">
                        <div class="col-3">
                            <div class="form-group @isset($empleado->formaPago) hasText @endisset">
                                <label for="infoFormaPago" class="control-label">Forma Pago</label>
                                <input type="text" readonly class="form-control" value="{{$empleado->formaPago}}" />
                            </div>
                        </div>
                    </div>
                    <div class="row tipoPagoTransferencia @if ($empleado->formaPago == "Transferencia") activoFlex @endif">
                        <div class="col-3">
                            <div class="form-group @isset($empleado->fkEntidad) hasText @endisset">
                                <label for="infoEntidadFinanciera" class="control-label">Entidad financiera</label>
                                @foreach ($entidadesFinancieras as $entidadesFinanciera)
                                    @if ($empleado->fkEntidad == $entidadesFinanciera->idTercero) 
                                    <input type="text" readonly class="form-control" value="{{$entidadesFinanciera->razonSocial}}" />
                                    @endif   
                                @endforeach 
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group @isset($empleado->numeroCuenta) hasText @endisset">
                                <label for="infoNoCuenta" class="control-label">N&uacute;mero de cuenta</label>
                                <input type="text" readonly class="form-control" id="infoNoCuenta" name="infoNoCuenta" value="{{ $empleado->numeroCuenta }}"/>
                            </div>
                        </div>
                        <div class="col-2">
                            <div class="form-group @isset($empleado->tipoCuenta) hasText @endisset">
                                <label for="infoTipoCuenta" class="control-label">Tipo cuenta:</label>
                                <input type="text" readonly class="form-control" value="{{$empleado->tipoCuenta}}" />
                            </div>
                        </div>
                        <div class="col-2">
                            <div class="form-group @isset($empleado->fkTipoIdentificacion) hasText @endif">
                                <label for="infoOtroTIdentificacion" class="control-label">Tipo Identificación</label>
                                @foreach ($tipoidentificacion as $tipoidentificacio)
                                    @if($tipoidentificacio->idtipoIdentificacion == $empleado->fkTipoOtroDocumento) 
                                    <input type="text" readonly class="form-control" value="{{$tipoidentificacio->nombre}}" />
                                    @endif
                                @endforeach                                
                            </div>
                        </div>
                        <div class="col-2">
                            <div class="form-group @isset($empleado->otroDocumento) hasText @endisset">
                                <label for="infoOtroDocumento" class="control-label">Otro documento</label>
                                <input type="text" readonly class="form-control" id="infoOtroDocumento" name="infoOtroDocumento" value="{{$empleado->otroDocumento}}"/>
                            </div>
                        </div>
                    </div>
                    <div class="row tipoPagoOtro @if ($empleado->formaPago == "Otra forma pago") activoFlex @endif">
                        <div class="col-3">
                            <div class="form-group @isset($empleado->otraFormaPago) hasText @endisset">
                                <label for="infoOtraFormaPago" class="control-label">Otra forma de pago:</label>
                                <input type="text" readonly class="form-control" id="infoOtraFormaPago" name="infoOtraFormaPago" value="{{ $empleado->otraFormaPago }}"/>
                            </div>
                        </div>
                    </div>
                    
                </section>
                <section>
                    <div class="subTitulo">
                        <h2>Informaci&oacute;n retencion en la fuente</h2>
                    </div>
                    <div class="row">
                        <div class="col-3">
                            <div class="form-group @isset($empleado->procedimientoRetencion) hasText @endisset">
                                <label for="infoProcedimientoRetencion" class="control-label">Procedimiento retencion:</label>
                                <input type="text" readonly class="form-control" value="{{$empleado->procedimientoRetencion}}" />
                            </div>
                        </div>
                        <div class="col-3 porcentajeRetencion @if ($empleado->procedimientoRetencion == "PORCENTAJE") activo @endif">
                            <div class="form-group @isset($empleado->porcentajeRetencion) hasText @endisset">
                                <label for="infoPorcentajeRetencion" class="control-label">Porcentaje retencion:</label>
                                <input type="text" readonly class="form-control" id="infoPorcentajeRetencion" name="infoPorcentajeRetencion" value="{{ $empleado->porcentajeRetencion }}"/>
                            </div>
                        </div>
                    </div>
                </section>
                <section>
                    <div class="row">
                        <div class="col-12">
                            <h4>Beneficios Tributarios</h4>
                            
                        </div>
                    </div>
                    <div class="beneficiosCont">
                        @for ($i = 1; $i <= sizeof($beneficiosTributarios); $i++)
                            <div class="beneficioTrib" data-id="{{$i}}">
                                <input type="hidden" name="idBeneficioTributario[]" value="{{$beneficiosTributarios[$i-1]->idBeneficioTributario}}"/>
                                <div class="row">                            
                                    
                                </div>
                                <div class="row">
                                    <div class="col-3">
                                        <div class="form-group @isset($beneficiosTributarios[$i-1]->fkTipoBeneficio) hasText @endisset">
                                            <label for="infoTipoBeneficio{{$i}}" class="control-label">Tipo Beneficio</label>
                                            @foreach ($tipobeneficio as $tipobene)
                                                @if($tipobene->idTipoBeneficio == $beneficiosTributarios[$i-1]->fkTipoBeneficio) 
                                                    <input type="text" readonly class="form-control" value="{{$tipobene->nombre}}" />
                                                @endif
                                            @endforeach                                            
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group  @isset($beneficiosTributarios[$i-1]->fechaVigencia) hasText @endisset">
                                            <label for="infoFechaVigencia{{$i}}" class="control-label">Fecha Vigencia</label>
                                            <input type="date" readonly class="form-control" id="infoFechaVigencia{{$i}}" name="infoFechaVigencia[]" value="{{$beneficiosTributarios[$i-1]->fechaVigencia}}"/>                    
                                        </div>
                                    </div>
                                    <div class="col-2">
                                        <div class="infoBeneficioSinPersona @if($beneficiosTributarios[$i-1]->fkTipoBeneficio != "4") activo @endif" data-id="{{$i}}">
                                            <div class="form-group  @isset($beneficiosTributarios[$i-1]->valorTotal) hasText @endisset">
                                                <label for="infoValorTotal{{$i}}" class="control-label">Valor total</label>
                                                <input type="text" readonly class="form-control separadorMiles valorTotalBeneficio"  data-id="{{$i}}"  id="infoValorTotal{{$i}}" name="infoValorTotal[]"  value="{{$beneficiosTributarios[$i-1]->valorTotal}}"/>                    
                                            </div>
                                        </div>
                                        <div class="infoPersonaBeneficio1 @if($beneficiosTributarios[$i-1]->fkTipoBeneficio == "4") activo @endif" data-id="{{$i}}">
                                            <div class="form-group @isset($beneficiosTributarios[$i-1]->fkNucleoFamiliar) hasText @endisset">
                                                <label for="infoPersonaVive{{$i}}" class="control-label">Persona</label>
                                                @foreach ($nucleofamiliar as $nucleofam)
                                                    @if($nucleofam->idNucleoFamiliar == $beneficiosTributarios[$i-1]->fkNucleoFamiliar)
                                                        <input type="text" readonly class="form-control" value="{{$nucleofam->nombre}}" />
                                                    @endif
                                                @endforeach                                                
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-2">
                                        <div class="infoBeneficioSinPersona @if($beneficiosTributarios[$i-1]->fkTipoBeneficio != "4") activo @endif" data-id="{{$i}}">
                                            <div class="form-group  @isset($beneficiosTributarios[$i-1]->numMeses) hasText @endisset">
                                                <label for="infoNumMeses{{$i}}" class="control-label">Num Meses</label>
                                                <input type="text" readonly class="form-control infoNumMesesBeneficio" id="infoNumMeses{{$i}}"  data-id="{{$i}}"  name="infoNumMeses[]"  value="{{$beneficiosTributarios[$i-1]->numMeses}}"/>                    
                                            </div>
                                        </div>
                                        <div class="infoPersonaBeneficio1 @if($beneficiosTributarios[$i-1]->fkTipoBeneficio == "4") activo @endif" data-id="{{$i}}">
                                            <div class="form-group @isset($beneficiosTributarios[$i-1]->fkTipoIdentificacion) hasText @endisset">
                                                <label for="infoTIdentificacion{{$i}}" class="control-label">Tipo Identificación</label>
                                                @foreach ($tipoidentificacion as $tipoidentificacio)
                                                    @if($tipoidentificacio->idtipoIdentificacion == $beneficiosTributarios[$i-1]->fkTipoIdentificacion) 
                                                    <input type="text" readonly class="form-control" value="{{$tipoidentificacio->nombre}}" />
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-2">
                                        <div class="infoBeneficioSinPersona @if($beneficiosTributarios[$i-1]->fkTipoBeneficio != "4") activo @endif" data-id="{{$i}}">
                                            <div class="form-group  @isset($beneficiosTributarios[$i-1]->valorMensual) hasText @endisset">
                                                <label for="infoValorMensual{{$i}}" class="control-label">Valor mensual</label>
                                                <input type="text" readonly class="form-control separadorMiles infoValorMensual" data-id="{{$i}}" id="infoValorMensual{{$i}}" name="infoValorMensual[]" value="{{$beneficiosTributarios[$i-1]->valorMensual}}"/>
                                            </div>
                                        </div>
                                        <div class="infoPersonaBeneficio1 @if($beneficiosTributarios[$i-1]->fkTipoBeneficio == "4") activo @endif" data-id="{{$i}}">
                                            <div class="form-group @isset($beneficiosTributarios[$i-1]->numIdentificacion) hasText @endisset">
                                                <label for="infoNumIdentificacion{{$i}}" class="control-label">Número Identificación</label>
                                                <input type="text" readonly class="form-control" id="infoNumIdentificacion{{$i}}" name="infoNumIdentificacion[]" value="{{$beneficiosTributarios[$i-1]->numIdentificacion}}" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="infoPersonaBeneficio2 @if($beneficiosTributarios[$i-1]->fkTipoBeneficio == "5") activo @endif" data-id="{{$i}}">
                                    <div class="row">
                                        <div class="col-3">
                                            <div class="form-group @isset($beneficiosTributarios[$i-1]->fkNucleoFamiliar) hasText @endisset">
                                                <label for="info2PersonaVive{{$i}}" class="control-label">Persona</label>
                                                @foreach ($nucleofamiliar as $nucleofam)
                                                    @if($nucleofam->idNucleoFamiliar == $beneficiosTributarios[$i-1]->fkNucleoFamiliar)
                                                    <input type="text" readonly class="form-control" value="{{$nucleofam->nombre}}" />
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div class="form-group @isset($beneficiosTributarios[$i-1]->fkTipoIdentificacion) hasText @endisset">
                                                <label for="info2TIdentificacion{{$i}}" class="control-label">Tipo Identificación</label>
                                                @foreach ($tipoidentificacion as $tipoidentificacio)
                                                    @if($tipoidentificacio->idtipoIdentificacion == $beneficiosTributarios[$i-1]->fkTipoIdentificacion)
                                                        <input type="text" readonly class="form-control" value="{{$tipoidentificacio->nombre}}" />
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div class="form-group @isset($beneficiosTributarios[$i-1]->numIdentificacion) hasText @endisset">
                                                <label for="info2NumIdentificacion{{$i}}" class="control-label">Número Identificación</label>
                                                <input type="text" readonly class="form-control" id="info2NumIdentificacion{{$i}}" name="info2NumIdentificacion[]" value="{{$beneficiosTributarios[$i-1]->numIdentificacion}}" />
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div class="form-group @isset($beneficiosTributarios[$i-1]->fkGenero) hasText @endisset">
                                                <label for="info2Genero{{$i}}" class="control-label">Genero</label>
                                                @foreach ($generosBen as $genero)
                                                    @if($genero->idGenero == $beneficiosTributarios[$i-1]->fkGenero)
                                                    <input type="text" readonly class="form-control" value="{{$genero->nombre}}" />
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-3">
                                            <div class="form-group @isset($beneficiosTributarios[$i-1]->direccion) hasText @endisset">
                                                <label for="info2DireccionPersona{{$i}}" class="control-label">Direccion</label>
                                                <input type="text" readonly class="form-control" id="info2DireccionPersona{{$i}}" data-id="{{$i}}" name="info2DireccionPersona[]" value="{{$beneficiosTributarios[$i-1]->direccion}}"/>                    
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div class="form-group @isset($beneficiosTributarios[$i-1]->ubi_pais_benef) hasText @endisset">
                                                <label for="info2PaisPersona{{$i}}" class="control-label">País</label>
                                                @foreach ($paises as $pais)
                                                    @if($pais->idubicacion == $beneficiosTributarios[$i-1]->ubi_pais_benef)
                                                        <input type="text" readonly class="form-control" value="{{$pais->nombre}}" />
                                                    @endif
                                                @endforeach                                                
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div class="form-group @isset($beneficiosTributarios[$i-1]->ubi_depto_benef) hasText @endisset">
                                                <label for="info2DeptoPersona{{$i}}" class="control-label">Departamento</label>
                                                    @foreach ($deptosBeneficiosTributarios[$i-1] as $deptoBen)
                                                       @if($deptoBen->idubicacion == $beneficiosTributarios[$i-1]->ubi_depto_benef)
                                                            <input type="text" readonly class="form-control" value="{{$deptoBen->nombre}}" />
                                                       @endif
                                                    @endforeach
                                                </div>
                                        </div>
                                        <div class="col-3">
                                            <div class="form-group @isset($beneficiosTributarios[$i-1]->fkUbicacion) hasText @endisset">
                                                <label for="info2LugarPersona{{$i}}" class="control-label">Lugar</label>
                                                @foreach ($ciudadesBeneficiosTributarios[$i-1] as $ciudadBen)
                                                    @if($ciudadBen->idubicacion == $beneficiosTributarios[$i-1]->fkUbicacion)
                                                    <input type="text" readonly class="form-control" value="{{$ciudadBen->nombre}}" />
                                                    @endif
                                                @endforeach                
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endfor
                        
                    </div>
                </section>
                <div class="alert alert-danger" role="alert" id="infoErrorFormInfo" style="display: none;"></div>
                
            </form>
        @else
            <form class="formGeneral" id="formInfoEmpleado" method="POST" action="" >
                @csrf
                <input type="hidden" name="idEmpleado" class = "idEmpleado"  value="{{$idEmpleado}}"/>
                <section>
                    <div class="subTitulo">
                        <h2>Informaci&oacute;n general</h2>
                        <hr />
                    </div>
                    <div class="row">
                        <div class="col-3">
                            <div class="form-group">
                                <label for="infoEmpresa" class="control-label">Empresa</label>
                                <select disabled class="form-control" id="infoEmpresa" name="infoEmpresa">
                                    <option value=""></option>        
                                    @foreach ($empresas as $empresa)
                                        <option value="{{$empresa->idempresa}}">{{$empresa->razonSocial}}</option>
                                    @endforeach                    
                                </select>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group">
                                <label for="infoNomina" class="control-label">N&oacute;mina</label>
                                <select disabled class="form-control" id="infoNomina" name="infoNomina">
                                    <option value=""></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group">
                                <label for="infoFechaIngreso" class="control-label">Fecha ingreso</label>
                                <input type="date" readonly class="form-control" id="infoFechaIngreso" name="infoFechaIngreso"/>                    
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group">
                                <label for="infoTipoRegimen" class="control-label">Tipo regimen</label>
                                <select disabled class="form-control" id="infoTipoRegimen" name="infoTipoRegimen">
                                    <option value=""></option>        
                                    <option value="Ley 50">Ley 50</option>        
                                    <option value="Salario Integral">Salario Integral</option>        
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-3">
                            <div class="form-group">
                                <label for="infoPaisLabora" class="control-label">País</label>
                                <select disabled class="form-control" id="infoPaisLabora" name="infoPaisLabora">
                                    <option value=""></option>
                                    @foreach ($paises as $pais)
                                        <option value="{{$pais->idubicacion}}">{{$pais->nombre}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group">
                                <label for="infoDeptoLabora" class="control-label">Departamento</label>
                                <select disabled class="form-control" id="infoDeptoLabora" name="infoDeptoLabora">
                                    <option value=""></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group">
                                <label for="infoLugarLabora" class="control-label">Lugar</label>
                                <select disabled class="form-control" id="infoLugarLabora" name="infoLugarLabora">
                                    <option value=""></option>     
                                </select>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group">
                                <label for="infoSabadoLabora" class="control-label">Sabado laborable?</label>
                                <select disabled class="form-control" id="infoSabadoLabora" name="infoSabadoLabora">
                                    <option value=""></option>
                                    <option value="1">SI</option>    
                                    <option value="0">NO</option>    
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        
                        <div class="col-3">
                            <div class="form-group">
                                <label for="infoCargo" class="control-label">Cargo</label>
                                <select disabled class="form-control" id="infoCargo" name="infoCargo">
                                    <option value=""></option>
                                    @foreach ($cargos as $cargo)
                                        <option value="{{$cargo->idCargo}}">{{$cargo->nombreCargo}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @if (!$usuExiste)
                        <div class="col-3">
                            <div class="form-group hasText">
                                <label for="infoUsuario" class="control-label">Usuario</label>
                                <input type="text" readonly class="form-control" id="infoUsuario" name="infoUsuario" readonly value="{{$empleado->numeroIdentificacion}}"/>                    
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group">
                                <label for="password" class="control-label">Contraseña</label>
                                <input type="text" readonly class="form-control pass_usu" id="password" name="password" readonly/>                    
                            </div>
                            
                        </div>
                        @endif
                        <div class="col-3">
                            <div class="form-group hasText">
                                <label for="infoSubTipoCotizante" class="control-label">Subtipo cotizante</label>
                                <select disabled class="form-control" id="infoSubTipoCotizante" name="infoSubTipoCotizante">
                                    @foreach ($subtiposcotizante as $subtipocotizante)
                                        <option value="{{$subtipocotizante->idSubtipoCotizante}}" @if ($subtipocotizante->idSubtipoCotizante == $empleado->esPensionado) selected @endif>{{$subtipocotizante->codigo." - ".$subtipocotizante->nombre}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>                        
                    </div>
                </section>
                <section>
                    <div class="row">
                        <div class="col-12">
                            <h4>Centro(s) de costo</h4>
                            
                        </div>
                    </div>
                    <div class="centroCostoCont">
                        <div class="centroCosto" data-id="1">
                            <div class="row">
                                <div class="col-3">
                                    <div class="form-group">
                                        <label for="infoCentroCosto1" class="control-label">Centro de costo</label>
                                        <select disabled class="form-control" id="infoCentroCosto1" name="infoCentroCosto[]">
                                            <option value=""></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group hasText">
                                        <label for="infoPorcentaje1" class="control-label">Porcentaje</label>
                                        <input type="text" readonly class="form-control" id="infoPorcentaje1" name="infoPorcentaje[]" readonly value="100%"/>                    
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </section>
                <section>
                    <div class="subTitulo">
                        <h2>Informaci&oacute;n contrato</h2>
                        <hr />
                        <div class="row">
                            <div class="col-3">
                                <div class="form-group">
                                    <label for="infoTipoContrato" class="control-label">Tipo Contrato</label>
                                    <select disabled class="form-control" id="infoTipoContrato" name="infoTipoContrato">
                                        <option value=""></option>
                                        @foreach ($tipoContratos as $tipoContrato)
                                            <option value="{{$tipoContrato->idtipoContrato}}">{{$tipoContrato->nombre}}</option>
                                        @endforeach 
                                    </select>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="form-group">
                                    <label for="infoTipoDuracionContrato" class="control-label">Tipo duración</label>
                                    <select disabled class="form-control" id="infoTipoDuracionContrato" name="infoTipoDuracionContrato">
                                        <option value=""></option>
                                        <option value="MES">MES</option>
                                        <option value="DÍA">DÍA</option>
                                    </select>

                                </div>
                            </div>
                            <div class="col-3">
                                <div class="form-group">
                                    <label for="infoDuracionContrato" class="control-label">Duración contrato</label>
                                    <input type="text" readonly class="form-control" id="infoDuracionContrato" name="infoDuracionContrato"/>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="form-group">
                                    <label for="infoFechaFin" class="control-label">Fecha fin contrato</label>
                                    <input type="date" readonly class="form-control" id="infoFechaFin" name="infoFechaFin" readonly/>
                                </div>
                            </div>
                        </div>
                       
                    </div>
                </section>
                <section>
                    <div class="subTitulo">
                        <h2>Informaci&oacute;n pago n&oacute;mina</h2>
                    </div>

                    <div class="row">
                        <div class="col-3">
                            <div class="form-group">
                                <label for="infoFormaPago" class="control-label">Forma Pago</label>
                                <select disabled class="form-control" id="infoFormaPago" name="infoFormaPago">
                                    <option value=""></option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Otra forma pago">Otra forma pago</option>
                                    <option value="Transferencia">Transferencia</option>                                    
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row tipoPagoTransferencia">
                        <div class="col-3">
                            <div class="form-group">
                                <label for="infoEntidadFinanciera" class="control-label">Entidad financiera</label>
                                <select disabled class="form-control" id="infoEntidadFinanciera" name="infoEntidadFinanciera">
                                    <option value=""></option>
                                    @foreach ($entidadesFinancieras as $entidadesFinanciera)
                                        <option value="{{$entidadesFinanciera->idTercero}}">{{$entidadesFinanciera->razonSocial}}</option>
                                    @endforeach 
                                </select>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group">
                                <label for="infoNoCuenta" class="control-label">N&uacute;mero de cuenta</label>
                                <input type="text" readonly class="form-control" id="infoNoCuenta" name="infoNoCuenta"/>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group">
                                <label for="infoTipoCuenta" class="control-label">Tipo cuenta:</label>
                                <select disabled class="form-control" id="infoTipoCuenta" name="infoTipoCuenta">
                                    <option value=""></option>
                                    <option value="AHORROS">AHORROS</option>
                                    <option value="CORRIENTE">CORRIENTE</option>
                                    <option value="DAVIPLATA">DAVIPLATA</option>
                                    <option value="TARJETA PREPAGO MAESTRO">TARJETA PREPAGO MAESTRO</option>                                    
                                </select>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group">
                                <label for="infoOtroDocumento" class="control-label">Otro documento</label>
                                <input type="text" readonly class="form-control" id="infoOtroDocumento" name="infoOtroDocumento"/>
                            </div>
                        </div>
                    </div>
                    <div class="row tipoPagoOtro">
                        <div class="col-3">
                            <div class="form-group">
                                <label for="infoOtraFormaPago" class="control-label">Otra forma de pago:</label>
                                <input type="text" readonly class="form-control" id="infoOtraFormaPago" name="infoOtraFormaPago"/>
                            </div>
                        </div>
                    </div>
                </section>
                <section>
                    <div class="subTitulo">
                        <h2>Informaci&oacute;n retencion en la fuente</h2>
                    </div>
                    <div class="row">
                        <div class="col-3">
                            <div class="form-group hasText">
                                <label for="infoProcedimientoRetencion" class="control-label">Procedimiento retencion:</label>
                                <select disabled class="form-control" id="infoProcedimientoRetencion" name="infoProcedimientoRetencion">
                                    <option value=""></option>
                                    <option value="TABLA" selected>TABLA</option>
                                    <option value="PORCENTAJE">PORCENTAJE</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-3 porcentajeRetencion">
                            <div class="form-group">
                                <label for="infoPorcentajeRetencion" class="control-label">Porcentaje retencion:</label>
                                <input type="text" readonly class="form-control" id="infoPorcentajeRetencion" name="infoPorcentajeRetencion"/>
                            </div>
                        </div>
                    </div>

                </section>
                <section>
                    <div class="row">
                        <div class="col-12">
                            <h4>Beneficios Tributarios</h4>
                            
                        </div>
                    </div>
                    <div class="beneficiosCont">
                    </div>
                </section>
                <div class="alert alert-danger" role="alert" id="infoErrorFormInfo" style="display: none;"></div>
                
            </form>
        @endisset
    </div>
    <div class="tabGeneral tab-pane fade @if ($destino=="afil") show active @endif" id="nav-afiliaciones" role="tabpanel" aria-labelledby="nav-afiliaciones-tab">
        <form class="formGeneral" id="formAfiliacionEmpleado" method="POST" action="" >
           @csrf
           <input type="hidden" name="idEmpleado" class = "idEmpleado"  value="{{$idEmpleado}}"/>
            <section>
                    <div class="subTitulo">
                        <h2>Seguridad Social</h2>
                        <hr />
                    </div>
                    <div class="row">
                        @if ($empleado->fkTipoCotizante != "12")
                            <div class="col-3">
                                <div class="form-group @isset($empleado->fkNivelArl) hasText @endisset">
                                    <label for="afiliacionLvArl" class="control-label">Nivel arl *</label>
                                    @foreach ($nivelesArl as $nivelArl)
                                            @if($empleado->fkNivelArl == $nivelArl->idnivel_arl)
                                            <input type="text" readonly class="form-control" value="{{$nivelArl->nombre}}" />
                                        @endif
                                    @endforeach                                    
                                </div>
                            </div>
                        @endif                       
                        <div class="col-3">
                            <div class="form-group @isset($empleado->fkCentroTrabajo) hasText @endisset">
                                <label for="afiliacionCentroTrabajo" class="control-label">Centro trabajo *</label>
                                @foreach ($centrosTrabajo as $centroTrabajo)
                                    @if($empleado->fkCentroTrabajo == $centroTrabajo->idCentroTrabajo)
                                        <input type="text" readonly class="form-control" value="{{$centroTrabajo->codigo}} - {{$centroTrabajo->nombre}}" />
                                    @endif
                                @endforeach                                
                            </div>
                        </div>
                    </div>

            </section>
            <section>            
                <div class="subTitulo">
                    <h2>Afiliaciones</h2>
                    <hr />
                </div>    
                <div class="row">
                    <div class="col-12">
                        <h4>Afiliaciones</h4>
                        
                    </div>
                </div>
                <div class="afiliacionesCont">
                    <input type="hidden" name="idsAfiliacionEliminar" id="idsAfiliacionEliminar" value="" />
                    @if (sizeof($afiliaciones)>0)
                        @for ($num = 1; $num <= sizeof($afiliaciones); $num++)          
                            
                            <div class="afiliacion" data-id="{{$num}}">
                                @if ($num>4)
                                    <div class="row">
                                        
                                    </div> 
                                @else
                                    @if ($empleado->esPensionado != 0 && $afiliaciones[$num - 1]->fkTipoAfilicacion == 4)
                                        <div class="row">
                                            
                                        </div> 
                                    @endif
                                @endif
                                
                                <input type="hidden" name="idAfiliacion[]" id="idAfiliacion{{$num}}" value="{{$afiliaciones[$num - 1]->idAfiliacion}}" />
                                <div class="row">
                                    <div class="col-3">
                                        <div class="form-group @isset($afiliaciones[$num - 1]->fkTipoAfilicacion) hasText @endisset">
                                            <label for="afiliacionTipoAfilicacion{{$num}}" class="control-label">Tipo afiliación *</label>
                                            
                                            
                                            @if ($num>4)
                                                @foreach ($tipoafilicaciones as $tipoafilicacion)
                                                    @if ($afiliaciones[$num - 1]->fkTipoAfilicacion == $tipoafilicacion->idTipoAfiliacion)
                                                        <input type="text" readonly class="form-control" value="{{$tipoafilicacion->nombre}}" />
                                                    @endif
                                                @endforeach
                                                
                                            @else
                                                @foreach ($tipoafilicaciones as $tipoafilicacion)
                                                    @if ($afiliaciones[$num - 1]->fkTipoAfilicacion == $tipoafilicacion->idTipoAfiliacion)
                                                        <input type="text" readonly class="form-control" value="{{$tipoafilicacion->nombre}}" readonly/>
                                                        <input type="hidden" name="afiliacionTipoAfilicacion[]" value="{{$tipoafilicacion->idTipoAfiliacion}}" />
                                                    @endif
                                                @endforeach
                                                
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group @isset($afiliaciones[$num - 1]->fkTercero) hasText @endisset">
                                            <label for="afiliacionEntidad{{$num}}" class="control-label">Entidad *</label>
                                            @foreach ($entidadesAfiliacion[$afiliaciones[$num - 1]->idAfiliacion] as $entidad)
                                                @if ($afiliaciones[$num - 1]->fkTercero == $entidad->idTercero)
                                                    <input type="text" readonly class="form-control" value="{{$entidad->razonSocial}}" />
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group @isset($afiliaciones[$num - 1]->fechaAfiliacion) hasText @endisset">
                                            <label for="afiliacionFecha{{$num}}" class="control-label">Fecha Afiliación *</label>
                                            <input type="date" readonly class="form-control" id="afiliacionFecha{{$num}}" name="afiliacionFecha[]" value="{{$afiliaciones[$num - 1]->fechaAfiliacion}}"/>
                                        </div>
                                    </div>
                                    
                                </div>
                                <div class="row cambioAfiliacion"  data-id="{{$num}}">
                                    <div class="col-3">
                                        <div class="form-group  @if (isset($afiliacionesNuevas[$afiliaciones[$num - 1]->idAfiliacion]))
                                            hasText
                                        @endif">
                                            <label for="afiliaFechaInicioCambio{{$num}}" class="control-label">Fecha cambio Inicio</label>
                                            <input type="date" readonly class="form-control" id="afiliaFechaInicioCambio{{$num}}" name="afiliaFechaInicioCambio[]" 
                                            @if (isset($afiliacionesNuevas[$afiliaciones[$num - 1]->idAfiliacion]))
                                                value="{{ $afiliacionesNuevas[$afiliaciones[$num - 1]->idAfiliacion]->fechaCambio }}"
                                            @endif                                            
                                            />
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group @isset($afiliaciones[$num - 1]->fkTercero) hasText @endisset">
                                            <label for="afiliacionEntidadNueva{{$num}}" class="control-label">Entidad *</label>
                                        
                                            @foreach ($entidadesAfiliacion[$afiliaciones[$num - 1]->idAfiliacion] as $entidad)
                                                @if (isset($afiliacionesNuevas[$afiliaciones[$num - 1]->idAfiliacion]) and $afiliacionesNuevas[$afiliaciones[$num - 1]->idAfiliacion]->fkTerceroNuevo == $entidad->idTercero)
                                                <input type="text" readonly class="form-control" value="{{$entidad->razonSocial}}" />
                                                @endif
                                            @endforeach
                                                                                            
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endfor
                    @else
                        <div class="afiliacion" data-id="1">
                            <div class="row">
                                <div class="col-3">
                                    <div class="form-group hasText">
                                        <label for="afiliacionTipoAfilicacion1" class="control-label">Tipo afiliación *</label>
                                        @foreach ($tipoafilicaciones as $tipoafilicacion)
                                            @if ($tipoafilicacion->idTipoAfiliacion == 1)
                                                <input type="text" readonly class="form-control" value="{{$tipoafilicacion->nombre}}" readonly/>
                                                <input type="hidden" name="afiliacionTipoAfilicacion[]" value="{{$tipoafilicacion->idTipoAfiliacion}}" />
                                            @endif
                                        @endforeach                                        
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group">
                                        <label for="afiliacionEntidad1" class="control-label">Entidad *</label>
                                        <select disabled class="form-control" id="afiliacionEntidad1" name="afiliacionEntidad[]">
                                            <option value=""></option>
                                            @foreach ($afiliacionesEnt1 as $afiliacionesEntidad1)
                                                <option value="{{$afiliacionesEntidad1->idTercero}}">{{$afiliacionesEntidad1->razonSocial}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group @isset($empleado->fechaIngreso) hasText @endisset">
                                        <label for="afiliacionFecha1" class="control-label">Fecha Afiliación *</label>
                                        <input type="date" readonly class="form-control" id="afiliacionFecha1" name="afiliacionFecha[]"  value="{{$empleado->fechaIngreso}}" />
                                    </div>
                                </div>
                                
                            </div>
                            <div class="row cambioAfiliacion" data-id="1">
                                <div class="col-3">
                                    <div class="form-group">
                                        <label for="afiliaFechaInicioCambio1" class="control-label">Fecha cambio Inicio</label>
                                        <input type="date" readonly class="form-control" id="afiliaFechaInicioCambio1" name="afiliaFechaInicioCambio[]" />
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group">
                                        <label for="afiliacionEntidadNueva1" class="control-label">Entidad *</label>
                                        <select disabled class="form-control" id="afiliacionEntidadNueva1" name="afiliacionEntidadNueva[]">
                                            <option value=""></option>
                                            @foreach ($afiliacionesEnt1 as $afiliacionesEntidad1)
                                                <option value="{{$afiliacionesEntidad1->idTercero}}">{{$afiliacionesEntidad1->razonSocial}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="afiliacion" data-id="2">
                            <div class="row">
                                <div class="col-3">
                                    <div class="form-group hasText">
                                        <label for="afiliacionTipoAfilicacion2" class="control-label">Tipo afiliación *</label>
                                        @foreach ($tipoafilicaciones as $tipoafilicacion)
                                            @if ($tipoafilicacion->idTipoAfiliacion == 2)
                                                <input type="text" readonly class="form-control" value="{{$tipoafilicacion->nombre}}" readonly/>
                                                <input type="hidden" name="afiliacionTipoAfilicacion[]" value="{{$tipoafilicacion->idTipoAfiliacion}}" />
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group">
                                        <label for="afiliacionEntidad2" class="control-label">Entidad *</label>
                                        <select disabled class="form-control" id="afiliacionEntidad2" name="afiliacionEntidad[]">
                                            <option value=""></option>
                                            @foreach ($afiliacionesEnt2 as $afiliacionesEntidad2)
                                                <option value="{{$afiliacionesEntidad2->idTercero}}">{{$afiliacionesEntidad2->razonSocial}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group @isset($empleado->fechaIngreso) hasText @endisset">
                                        <label for="afiliacionFecha2" class="control-label">Fecha Afiliación *</label>
                                        <input type="date" readonly class="form-control" id="afiliacionFecha2" name="afiliacionFecha[]"  value="{{$empleado->fechaIngreso}}" />
                                    </div>
                                </div>
                                
                            </div>
                            <div class="row cambioAfiliacion" data-id="2">
                                <div class="col-3">
                                    <div class="form-group">
                                        <label for="afiliaFechaInicioCambio2" class="control-label">Fecha cambio Inicio</label>
                                        <input type="date" readonly class="form-control" id="afiliaFechaInicioCambio2" name="afiliaFechaInicioCambio[]" />
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group">
                                        <label for="afiliacionEntidadNueva2" class="control-label">Entidad *</label>
                                        <select disabled class="form-control" id="afiliacionEntidadNueva2" name="afiliacionEntidadNueva[]">
                                            <option value=""></option>
                                            @foreach ($afiliacionesEnt2 as $afiliacionesEntidad2)
                                                <option value="{{$afiliacionesEntidad2->idTercero}}">{{$afiliacionesEntidad2->razonSocial}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="afiliacion" data-id="3">
                            <div class="row">
                                <div class="col-3">
                                    <div class="form-group hasText">
                                        <label for="afiliacionTipoAfilicacion3" class="control-label">Tipo afiliación *</label>
                                        @foreach ($tipoafilicaciones as $tipoafilicacion)
                                            @if ($tipoafilicacion->idTipoAfiliacion == 3)
                                                <input type="text" readonly class="form-control" value="{{$tipoafilicacion->nombre}}" readonly/>
                                                <input type="hidden" name="afiliacionTipoAfilicacion[]" value="{{$tipoafilicacion->idTipoAfiliacion}}" />
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group">
                                        <label for="afiliacionEntidad3" class="control-label">Entidad *</label>
                                        <select disabled class="form-control" id="afiliacionEntidad3" name="afiliacionEntidad[]">
                                            <option value=""></option>
                                            @foreach ($afiliacionesEnt3 as $afiliacionesEntidad3)
                                                <option value="{{$afiliacionesEntidad3->idTercero}}">{{$afiliacionesEntidad3->razonSocial}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group @isset($empleado->fechaIngreso) hasText @endisset">
                                        <label for="afiliacionFecha3" class="control-label">Fecha Afiliación *</label>
                                    <input type="date" readonly class="form-control" id="afiliacionFecha3" name="afiliacionFecha[]" value="{{$empleado->fechaIngreso}}" />
                                    </div>
                                </div>
                                
                            </div>
                            <div class="row cambioAfiliacion" data-id="3">
                                
                                <div class="col-3">
                                    <div class="form-group">
                                        <label for="afiliaFechaInicioCambio3" class="control-label">Fecha cambio Inicio</label>
                                        <input type="date" readonly class="form-control" id="afiliaFechaInicioCambio3" name="afiliaFechaInicioCambio[]" />
                                    </div>
                                </div>

                                <div class="col-3">
                                    <div class="form-group">
                                        <label for="afiliacionEntidadNueva3" class="control-label">Entidad *</label>
                                        <select disabled class="form-control" id="afiliacionEntidadNueva3" name="afiliacionEntidadNueva[]">
                                            <option value=""></option>
                                            @foreach ($afiliacionesEnt3 as $afiliacionesEntidad3)
                                                <option value="{{$afiliacionesEntidad3->idTercero}}">{{$afiliacionesEntidad3->razonSocial}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                            </div>
                        </div>
                        @if ($empleado->esPensionado == 0)
                            <div class="afiliacion" data-id="4">
                                <div class="row">
                                    <div class="col-3">
                                        <div class="form-group hasText">
                                            <label for="afiliacionTipoAfilicacion4" class="control-label">Tipo afiliación *</label>
                                            @foreach ($tipoafilicaciones as $tipoafilicacion)
                                                @if ($tipoafilicacion->idTipoAfiliacion == 4)
                                                    <input type="text" readonly class="form-control" value="{{$tipoafilicacion->nombre}}" readonly/>
                                                    <input type="hidden" name="afiliacionTipoAfilicacion[]" value="{{$tipoafilicacion->idTipoAfiliacion}}" />
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group">
                                            <label for="afiliacionEntidad4" class="control-label">Entidad *</label>
                                            <select disabled class="form-control" id="afiliacionEntidad4" name="afiliacionEntidad[]">
                                                <option value=""></option>
                                                @foreach ($afiliacionesEnt4 as $afiliacionesEntidad4)
                                                    <option value="{{$afiliacionesEntidad4->idTercero}}">{{$afiliacionesEntidad4->razonSocial}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group @isset($empleado->fechaIngreso) hasText @endisset">
                                            <label for="afiliacionFecha4" class="control-label">Fecha Afiliación *</label>
                                            <input type="date" readonly class="form-control" id="afiliacionFecha4" name="afiliacionFecha[]"  value="{{$empleado->fechaIngreso}}" />
                                        </div>
                                    </div>
                                    
                                </div>
                                <div class="row cambioAfiliacion" data-id="4">
                                    <div class="col-3">
                                        <div class="form-group">
                                            <label for="afiliaFechaInicioCambio4" class="control-label">Fecha cambio Inicio</label>
                                            <input type="date" readonly class="form-control" id="afiliaFechaInicioCambio4" name="afiliaFechaInicioCambio[]" />
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group">
                                            <label for="afiliacionEntidadNueva4" class="control-label">Entidad *</label>
                                            <select disabled class="form-control" id="afiliacionEntidadNueva4" name="afiliacionEntidadNueva[]">
                                                <option value=""></option>
                                                @foreach ($afiliacionesEnt4 as $afiliacionesEntidad4)
                                                    <option value="{{$afiliacionesEntidad4->idTercero}}">{{$afiliacionesEntidad4->razonSocial}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                        
                    @endif
                    
                </div>
            </section>
            <div class="alert alert-danger" role="alert" id="afiliacionErrorFormInfo" style="display: none;"></div>
            


        </form>
    </div>
    <div class="tabGeneral tab-pane fade @if ($destino=="concFij") show active @endif" id="nav-conceptosFijos" role="tabpanel" aria-labelledby="nav-conceptosFijos-tab">
        <form class="formGeneral" id="formConceptosFijos" method="POST" action="" >
            @csrf
            <input type="hidden" name="idEmpleado"  value="{{$idEmpleado}}"/>
            <section>            
                <div class="row">
                    <div class="col-12">
                        <h4>Conceptos Fijos</h4>
                        
                    </div>
                </div>
                <div class="conceptosCont">
                    @if (sizeof($conceptosFijos)>1)
                    @for($i = 1; $i<=sizeof($conceptosFijos); $i++)
                        <div class="conceptoFijo" data-id="{{$i}}">

                            @if ($i > 1)
                                <div class="row">
                                    <div class="col-11"></div>
                                    
                                </div>
                            @endif                            
                            <div class="row">
                                <div class="col-3">
                                    <div class="form-group hasText">
                                        <label for="conFiConcepto{{$i}}" class="control-label">Concepto</label>
                                            @if ($i==1)
                                                <input type="text" readonly class="form-control" value="{{$conceptosFijos[$i-1]->nombreConcepto}}" />
                                                @else
                                                @foreach ($conceptos as $concepto)
                                                    @if ($conceptosFijos[$i-1]->fkConcepto==$concepto->idconcepto)
                                                    <input type="text" readonly class="form-control" value="{{$concepto->nombre}}" />
                                                    @endif
                                                @endforeach
                                            @endif
                                           
                                            
                                        </select>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group @isset($conceptosFijos[$i-1]->unidad) hasText @endisset">
                                        <label for="conFiUnidad{{$i}}" class="control-label">Unidad</label>
                                        <input type="text" readonly class="form-control" value="{{$conceptosFijos[$i-1]->unidad}}" />                    
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group @isset($conceptosFijos[$i-1]->valor) hasText @endisset">
                                        <label for="conFiValor{{$i}}" class="control-label">Valor</label>
                                        <input type="text" readonly class="form-control separadorMiles" id="conFiValor{{$i}}" name="conFiValor[]" value="{{$conceptosFijos[$i-1]->valor}}" />
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group @isset($conceptosFijos[$i-1]->porcentaje) hasText @endisset">
                                        <label for="conFiPorcentaje{{$i}}" class="control-label">Porcentaje</label>
                                        <input type="text" readonly class="form-control" id="conFiPorcentaje{{$i}}" name="conFiPorcentaje[]" @if ($i == 1) readonly @endif value="{{$conceptosFijos[$i-1]->porcentaje}}" />
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-3">
                                    <div class="form-group @isset($conceptosFijos[$i-1]->fechaInicio) hasText @endisset">
                                        <label for="conFiFechaInicio{{$i}}" class="control-label">Fecha Inicio</label>
                                        <input type="date" readonly class="form-control" id="conFiFechaInicio{{$i}}" name="conFiFechaInicio[]" value="{{$conceptosFijos[$i-1]->fechaInicio}}" />
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group @isset($conceptosFijos[$i-1]->fechaFin) hasText @endisset">
                                        <label for="conFiFechaFin{{$i}}" class="control-label">Fecha Fin</label>
                                        <input type="date" readonly class="form-control" id="conFiFechaFin{{$i}}" name="conFiFechaFin[]"  value="{{$conceptosFijos[$i-1]->fechaFin}}"/>
                                    </div>
                                </div>
                                @if ($i == 1)
                                    
                                @endif
                            </div>
                            @if ($i == 1)
                                <div class="row cambioConcepto" data-id="{{$i}}">
                                    <div class="col-3">
                                        <div class="form-group @isset($cambioSalario) hasText @endisset">
                                            <label for="conFiFechaInicioCambio{{$i}}" class="control-label">Fecha cambio Inicio</label>
                                            <input type="date" readonly class="form-control" id="conFiFechaInicioCambio{{$i}}" name="conFiFechaInicioCambio" 
                                            @isset($cambioSalario)
                                                    value="{{$cambioSalario->fechaCambio}}"
                                                @endisset
                                                />
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group @isset($cambioSalario) hasText @endisset">
                                            <label for="conValorCambio{{$i}}" class="control-label">Valor Nuevo</label>
                                            <input type="text" readonly class="form-control separadorMiles" id="conValorCambio{{$i}}" name="conValorCambio" 
                                                @isset($cambioSalario)
                                                    value="{{$cambioSalario->valorNuevo}}"
                                                @endisset
                                                />
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endfor
                    @else
                        @if (sizeof($conceptosFijos)==1)
                            <div class="conceptoFijo" data-id="1">
                                <div class="row">
                                    <div class="col-3">
                                        @if ($empleado->tipoRegimen == "Ley 50")
                                            <div class="form-group hasText">
                                                <label for="conFiConcepto1" class="control-label">Concepto</label>       
                                                <input type="text" readonly class="form-control" value="SALARIO BASICO" />                                 
                                            </div>
                                        @else
                                        <div class="form-group hasText">
                                                <label for="conFiConcepto1" class="control-label">Concepto</label>
                                                <input type="text" readonly class="form-control" value="SALARIO INTEGRAL" />
                                            </div>
                                        @endif
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group @isset($contratoActivo->tipoDuracionContrato)
                                            hasText
                                        @endisset">
                                            <label for="conFiUnidad1" class="control-label">Unidad</label>
                                            <select disabled class="form-control" id="conFiUnidad1" name="conFiUnidad[]">
                                                <option value=""></option>
                                                
                                                <option value="DIA" @if (isset($contratoActivo->tipoDuracionContrato) && $contratoActivo->tipoDuracionContrato == "DIA") selected @endif>DIA</option>
                                                <option value="HORA" @if (isset($contratoActivo->tipoDuracionContrato) && $contratoActivo->tipoDuracionContrato == "HORA") selected @endif>HORA</option>
                                                <option value="MES" @if (isset($contratoActivo->tipoDuracionContrato) && $contratoActivo->tipoDuracionContrato == "MES") selected @endif>MES</option>
                                                <option value="UNIDAD" @if (isset($contratoActivo->tipoDuracionContrato) && $contratoActivo->tipoDuracionContrato == "UNIDAD") selected @endif>UNIDAD</option>                                        
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group hasText">
                                            <label for="conFiValor1" class="control-label">Valor</label>
                                            <input type="text" readonly class="form-control separadorMiles" id="conFiValor1" name="conFiValor[]" value="{{$conceptosFijos[0]->valor}}" />
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group">
                                            <label for="conFiPorcentaje1" class="control-label">Porcentaje</label>
                                            <input type="text" readonly class="form-control" id="conFiPorcentaje1" name="conFiPorcentaje[]" readonly />
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-3">
                                        <div class="form-group @isset($contratoActivo->fechaInicio)
                                            hasText
                                        @endisset">
                                            <label for="conFiFechaInicio1" class="control-label">Fecha Inicio</label>
                                            <input type="date" readonly class="form-control" id="conFiFechaInicio1" name="conFiFechaInicio[]"  value="{{$conceptosFijos[0]->fechaInicio}}" />
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group @isset($conceptosFijos[0]->fechaFin) hasText @endisset">
                                            <label for="conFiFechaFin1" class="control-label">Fecha Fin</label>
                                            <input type="date" readonly class="form-control" id="conFiFechaFin1" name="conFiFechaFin[]"  value="{{$conceptosFijos[0]->fechaFin}}" />
                                        </div>
                                    </div>
                                    
                                </div>
                                <div class="row cambioConcepto" data-id="1">
                                    <div class="col-3">
                                        <div class="form-group @isset($cambioSalario) hasText @endisset">
                                            <label for="conFiFechaInicioCambio1" class="control-label">Fecha cambio inicio</label>
                                            <input type="date" readonly class="form-control" id="conFiFechaInicioCambio1" name="conFiFechaInicioCambio" 
                                                @isset($cambioSalario)
                                                    value="{{$cambioSalario->fechaCambio}}"
                                                @endisset
                                            />
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group @isset($cambioSalario) hasText @endisset">
                                            <label for="conValorCambio1" class="control-label">Valor Nuevo</label>
                                            <input type="text" readonly class="form-control separadorMiles" id="conValorCambio1" name="conValorCambio"
                                                @isset($cambioSalario)
                                                    value="{{$cambioSalario->valorNuevo}}"
                                                @endisset
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="conceptoFijo" data-id="1">
                                <div class="row">
                                    <div class="col-3">
                                        @if ($empleado->tipoRegimen == "Ley 50")
                                            <div class="form-group hasText">
                                                <label for="conFiConcepto1" class="control-label">Concepto</label>                                        
                                                <select disabled class="form-control" id="conFiConcepto1" name="conFiConcepto[]">
                                                    <option value="1">SALARIO BASICO</option>
                                                </select>                                       
                                            </div>
                                        @else
                                        <div class="form-group hasText">
                                                <label for="conFiConcepto1" class="control-label">Concepto</label>
                                                <select disabled class="form-control" id="conFiConcepto1" name="conFiConcepto[]">
                                                    <option value="2">SALARIO INTEGRAL</option>
                                                </select>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group @isset($contratoActivo->tipoDuracionContrato)
                                            hasText
                                        @endisset">
                                            <label for="conFiUnidad1" class="control-label">Unidad</label>
                                            <input type="text" readonly class="form-control" value="{{$contratoActivo->tipoDuracionContrato ?? ""}}" />                    
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group">
                                            <label for="conFiValor1" class="control-label">Valor</label>
                                            <input type="text" readonly class="form-control separadorMiles" id="conFiValor1" name="conFiValor[]" />
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group">
                                            <label for="conFiPorcentaje1" class="control-label">Porcentaje</label>
                                            <input type="text" readonly class="form-control" id="conFiPorcentaje1" name="conFiPorcentaje[]" readonly />
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-3">
                                        <div class="form-group @isset($contratoActivo->fechaInicio)
                                            hasText
                                        @endisset">
                                            <label for="conFiFechaInicio1" class="control-label">Fecha Inicio</label>
                                            <input type="date" readonly class="form-control" id="conFiFechaInicio1" name="conFiFechaInicio[]" value="@isset($contratoActivo->fechaInicio){{$contratoActivo->fechaInicio}}@endisset" />
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group">
                                            <label for="conFiFechaFin1" class="control-label">Fecha Fin</label>
                                            <input type="date" readonly class="form-control" id="conFiFechaFin1" name="conFiFechaFin[]" />
                                        </div>
                                    </div>
                                </div>
                                
                            </div>
                            
                        @endif
                    
                    @endif

                    @if (isset($cambioSalarioFin) && sizeof($cambioSalarioFin) > 0)
                   
                        <br><br><br><br>
                        <h1>Salarios anteriores</h1>
                        @foreach ($cambioSalarioFin as $cambioSalarioF)
                            <div class="row">
                                <div class="col-3">
                                    <div class="form-group hasText">
                                        <label for="cambioSalarioFecha{{$cambioSalarioF->idCambioSalario}}" class="control-label">Fecha cambio</label>
                                        <input type="date" readonly class="form-control" id="cambioSalarioFecha{{$cambioSalarioF->idCambioSalario}}" value="{{$cambioSalarioF->fechaCambio}}" />
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-group hasText">
                                        <label for="cambioSalarioValor{{$cambioSalarioF->idCambioSalario}}" class="control-label">Valor</label>
                                        <input type="text" readonly class="form-control separadorMiles" id="cambioSalarioValor{{$cambioSalarioF->idCambioSalario}}" value="{{$cambioSalarioF->valorAnterior}}" />
                                    </div>
                                </div>
                            </div>
                        @endforeach                        
                    @endif
                </div>
            </section>
            
        </form>
    </div>
</div>
<div class="modal fade" id="camposVaciosModal" tabindex="-1" role="dialog" aria-labelledby="camposVaciosModal" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <div class="cerrarPop" data-dismiss="modal"></div>
                <div class="camposVaciosText">
                    <h4>Aun cuenta con campos vacios desea continuar?</h4>
                    <div class="text-center">
                        <a href="#" data-accion="" data-form="" id="btnContinuarCamposVacios" class="btn btn-secondary">Continuar</a>
                        <a data-dismiss="modal" class="btn btn-primary" href="#">Volver</a>
                    </div>                    
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="mensajeEmpleadoModal" tabindex="-1" role="dialog" aria-labelledby="mensajeEmpleadoModal" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <div class="cerrarPop" data-dismiss="modal"></div>
                <div id="respMensaje"></div>                
                <div class="text-center">
                    <a href="#" class="btn btn-primary" id="aceptarMensajeEmpleado">Continuar</a>
                    <a data-dismiss="modal" class="btn btn-secondary" href="#">Volver</a>
                </div>                    
                
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="errorEmpleadoModal" tabindex="-1" role="dialog" aria-labelledby="errorEmpleadoModal" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <div class="cerrarPop" data-dismiss="modal"></div>
                <div id="respError"></div>                
                <div class="text-center">
                    <a data-dismiss="modal" class="btn btn-primary" href="#">Aceptar</a>
                    <a data-dismiss="modal" id="continuarIgual" class="btn btn-secondary" href="#">Continuar de todas maneras</a>
                </div>                    
                
            </div>
        </div>
    </div>
</div>

<script type="text/javascript" src="{{ URL::asset('js/empleado/empleado.js') }}"></script>
<script type="text/javascript" src="{{ URL::asset('js/empleado/datosEmpleado.js') }}"></script>
@isset($empleado->fkEmpresa)
    <script type="text/javascript" src="{{ URL::asset('js/empleado/infoLabEmpleadoMod.js') }}"></script>
@else
    <script type="text/javascript" src="{{ URL::asset('js/empleado/infoLabEmpleado.js') }}"></script>
@endisset
<script type="text/javascript" src="{{ URL::asset('js/empleado/afiliacionEmpleado.js') }}"></script>
<script type="text/javascript" src="{{ URL::asset('js/empleado/conceptosFijosEmpleado.js') }}"></script>
<script type="text/javascript" src="{{ URL::asset('js/jquery.inputmask.js') }}"></script>
<script type="text/javascript" src="{{ URL::asset('js/jquery.inputmask.numeric.extensions.js') }}"></script>
@endsection