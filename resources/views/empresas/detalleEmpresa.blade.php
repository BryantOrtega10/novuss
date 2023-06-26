<form action="/empresa/editarEmpresa" class="formEdit" method = "POST">
    <div class="form-group text-center">
        <h3>Logo</h3>
        <img style = "max-width: 200px;" src = "/storage/logosEmpresas/{{ $empresa->logoEmpresa }}">
    </div>
    <div class="row">
        <div class="col form-group">
            <label for="fkTipoCompania">Tipo de compañia</label>
            <select name="fkTipoCompania" id="fkTipoCompania" class="form-control" disabled>
                <option value="">-- Seleccione una opción --</option>
                @foreach ($tipoComp as $comp)
                    <option value="{{ $comp->idtipoCompania}}"
                    @if ($comp->idtipoCompania == old('fkTipoCompania', $empresa->fkTipoCompania))
                        selected="selected"
                    @endif
                    >{{ $comp->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col form-group">
            <label for="fkTipoAportante">Tipo de aportante</label>
            <select name="fkTipoAportante" id="fkTipoAportante" class="form-control" disabled>
                <option value="">-- Seleccione una opción --</option>
                @foreach ($tipoApor as $apor)
                    <option value="{{ $apor->idtipoAportante}}"
                    @if ($apor->idtipoAportante == old('fkTipoAportante', $empresa->fkTipoAportante))
                        selected="selected"
                    @endif
                    >{{ $apor->nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-group">
        <label for="razonSocial">Razón Social</label>
        <input type="text" class="form-control" id="razonSocial" name = "razonSocial" value = "{{ $empresa->razonSocial}}" disabled>
    </div>

    <div class="form-group">
        <label for="fkTipoIdentificacion">Tipo de identificación</label>
        <select name="fkTipoIdentificacion" id="fkTipoIdentificacion" class="form-control" disabled>
            <option value="">-- Seleccione una opción --</option>
            @foreach ($tipoIdent as $tipo)
                <option value="{{ $tipo->idtipoIdentificacion}}"
                @if ($tipo->idtipoIdentificacion == old('fkTipoIdentificacion', $empresa->fkTipoIdentificacion))
                    selected="selected"
                @endif
                >{{ $tipo->nombre }}</option>
            @endforeach
        </select>
    </div>

    <div class="row">
        <div class="col form-group">
            <label for="documento">NIT</label>
            <input type="number" class="form-control" id="documento" name = "documento" value = "{{ $empresa->documento }}" disabled>
        </div>
        <div class="col form-group">
            <label for="digitoVerificacion">Dígito verificación</label>
            <input type="number" class="form-control" id="digitoVerificacion" name = "digitoVerificacion" value = "{{ $empresa->digitoVerificacion }}" disabled>
        </div>
    </div>

    <div class="row">
        <div class="col form-group">
            <label for="sigla">Sigla</label>
            <input type="text" class="form-control" id="sigla" name = "sigla" value = "{{ $empresa->sigla }}" disabled>
        </div>
        <div class="col form-group">
            <label for="dominio">Dominio</label>
            <input type="text" class="form-control" id="dominio" name = "dominio" value = "{{ $empresa->dominio }}" disabled>
        </div>
    </div>

    <div class="form-group">
        <label for="representanteLegal">Nombre Representante Legal</label>
        <input type="text" class="form-control" id="representanteLegal" name = "representanteLegal" value = "{{ $empresa->representanteLegal}}" disabled>
    </div>

    <div class="form-group">
        <label for="docRepresentante">Tipo de identificación representante</label>
        <select name="docRepresentante" id="docRepresentante" class="form-control" disabled>
            <option value="">-- Seleccione una opción --</option>
            @foreach ($tipoIdent as $tipo)
                <option value="{{ $tipo->idtipoIdentificacion}}"
                    @if ($tipo->idtipoIdentificacion == old('docRepresentante', $empresa->docRepresentante))
                    selected="selected"
                @endif
                >{{ $tipo->nombre }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label for="numDocRepresentante">Número Representante Legal</label>
        <input type="text" class="form-control" id="numDocRepresentante" name = "numDocRepresentante" value = "{{ $empresa->numDocRepresentante }}" disabled>
    </div>

    {{-- <div class="row">
        <div class="col form-group">
            <label for="fkActividadEconomica">Tipo de actividad económica</label>
            <select name="fkActividadEconomica" id="fkActividadEconomica" class="form-control" disabled>
                <option value="">-- Seleccione una opción --</option>
                @foreach ($actEconomicas as $actEc)
                    <option value="{{ $actEc->idactividadEconomica}}"
                    @if ($actEc->idactividadEconomica == old('fkActividadEconomica', $empresa->fkActividadEconomica))
                        selected="selected"
                    @endif
                    >{{ $actEc->nombre }}</option>
                @endforeach
            </select>
        </div>
    </div> --}}

    <div class="form-group">
        <label for="fkTercero_ARL">Tercero ARL</label>
        <select name="fkTercero_ARL" id="fkTercero_ARL" class="form-control" disabled>
            <option value="">-- Seleccione una opción --</option>
            @foreach ($terceroArl as $terArl)
                <option value="{{ $terArl->idTercero}}"
                    @if ($terArl->idTercero == old('fkTercero_ARL', $empresa->fkTercero_ARL))
                        selected="selected"
                    @endif
                    >{{ $terArl->razonSocial }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label for="pais">País</label>
        <select name="pais" id="pais" class="form-control" disabled>
            <option value="">-- Seleccione una opción --</option>
            @foreach ($paises as $p)
                <option value="{{ $p->idubicacion }}"
                 @if ($p->idubicacion == old('ubi_tres', $empresa->ubi_tres))
                    selected="selected"
                @endif
                >{{ $p->nombre }}</option>
            @endforeach
        </select>
    </div>

    <div class="row">
        <div class="col form-group">
            <label for="deptos">Departamento</label>
            <select name="deptos" id="deptos" class="form-control" disabled>
                <option value="">-- Seleccione una opción --</option>
                @foreach ($deptos as $d)
                    <option value="{{ $d->idubicacion }}"
                     @if ($d->idubicacion == old('ubi_dos', $empresa->ubi_dos))
                        selected="selected"
                    @endif
                    >{{ $d->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col form-group">
            <label for="direccion">Ciudad</label>
            <select name="fkUbicacion" id="fkUbicacion" class="form-control" disabled>
                <option value="">-- Seleccione una opción --</option>
                @foreach ($ciudades as $c)
                    <option value="{{ $c->idubicacion }}"
                     @if ($c->idubicacion == old('ubi', $empresa->ubi))
                        selected="selected"
                    @endif
                    >{{ $c->nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>
    
    <div class="form-group">
        <label for="direccion">Dirección</label>
        <input type="text" class="form-control" id="direccion" name = "direccion" value = "{{ $empresa->direccion }}" disabled>
    </div>
    
    <div class="form-group">
        <label for="paginaWeb">Página Web</label>
        <input type="text" class="form-control" id="paginaWeb" name = "paginaWeb" value = "{{ $empresa->paginaWeb }}" disabled>
    </div>

    <div class="row">
        <div class="col form-group">
            <label for="telefonoFijo">Teléfono</label>
            <input type="number" class="form-control" id="telefonoFijo" name = "telefonoFijo" value = "{{ $empresa->telefonoFijo }}" disabled>
        </div>
        <div class="col form-group">
            <label for="celular">Celular</label>
            <input type="number" class="form-control" id="celular" name = "celular" value = "{{ $empresa->celular }}" disabled>
        </div>
    </div>

    <div class="row">
        <div class="col form-group">
            <label for="email1">Correo 1</label>
            <input type="email" class="form-control" id="email1" name = "email1" value = "{{ $empresa->email1 }}" disabled>
        </div>
        <div class="col form-group">
            <label for="email2">Correo 2</label>
            <input type="email" class="form-control" id="email2" name = "email2" value = "{{ $empresa->email2 }}" disabled>
        </div>
    </div>
    <div class="form-group">
        <label for="SoftwareDianId">Software Dian Id nómina electrónica</label>
        <input type="text" class="form-control" id="SoftwareDianId" disabled name = "SoftwareDianId" value="{{ $empresa->SoftwareDianId }}">
    </div>
    <div class="form-group">
        <label for="SoftwareTestSetId">Software Test Set Id nómina electrónica</label>
        <input type="text" class="form-control" id="SoftwareTestSetId" disabled name = "SoftwareTestSetId" value="{{ $empresa->SoftwareTestSetId }}">
    </div>
    <div class="form-group">
        <label for="PrefijoNominaElectronica">Prefijo nómina electrónica</label>
        <input type="text" class="form-control" id="PrefijoNominaElectronica" disabled name = "PrefijoNominaElectronica" value="{{ $empresa->PrefijoNominaElectronica }}">
    </div>
    <div class="form-group">
        <label for="ConsecutivoNominaElectronica">Consecutivo nómina electrónica</label>
        <input type="number" class="form-control" id="ConsecutivoNominaElectronica" disabled name = "ConsecutivoNominaElectronica" value="{{ $empresa->ConsecutivoNominaElectronica }}">
    </div>

    <div class="form-group">
        <label for="PrefijoNominaElectronicaReemplazo">Prefijo nómina electrónica reemplazo</label>
        <input type="text" class="form-control" id="PrefijoNominaElectronicaReemplazo" disabled name = "PrefijoNominaElectronicaReemplazo" value="{{ $empresa->PrefijoNominaElectronicaReemplazo }}">
    </div>
    <div class="form-group">
        <label for="ConsecutivoNominaElectronicaReemplazo">Consecutivo nómina electrónica reemplazo</label>
        <input type="number" class="form-control" id="ConsecutivoNominaElectronicaReemplazo" disabled name = "ConsecutivoNominaElectronicaReemplazo" value="{{ $empresa->ConsecutivoNominaElectronicaReemplazo }}">
    </div>

    <div class="form-group">
        <label for="PrefijoNominaElectronicaEliminacion">Prefijo nómina electrónica eliminacion</label>
        <input type="text" class="form-control" id="PrefijoNominaElectronicaEliminacion" disabled name = "PrefijoNominaElectronicaEliminacion" value="{{ $empresa->PrefijoNominaElectronicaEliminacion }}">
    </div>
    <div class="form-group">
        <label for="ConsecutivoNominaElectronicaEliminacion">Consecutivo nómina electrónica eliminacion</label>
        <input type="number" class="form-control" id="ConsecutivoNominaElectronicaEliminacion" disabled name = "ConsecutivoNominaElectronicaEliminacion" value="{{ $empresa->ConsecutivoNominaElectronicaEliminacion }}">
    </div>
    <div class="form-group">
        <label for="TipAmbNominaElectronica">Tip Ambiente nómina electrónica</label>
        <select name="TipAmbNominaElectronica" id="TipAmbNominaElectronica" class="form-control" disabled>
            <option value="">-- Seleccione una opción --</option>
            <option value="1" @if ($empresa->TipAmbNominaElectronica == "1") selected @endif>Producción</option>
            <option value="2" @if ($empresa->TipAmbNominaElectronica == "2") selected @endif>Pruebas</option>
        </select>
    </div>

    <div class="row para15Dias" @if(!isset($nominasQuincenlaes)) style="display: none;"  @endif>
        <div class="col form-group">
            <label for="fkPeriocidadRetencion">Periocidad retefuente </label>
            <select disabled name="fkPeriocidadRetencion" id="fkPeriocidadRetencion" class="form-control">
                @foreach ($periocidad as $p)
                    <option value="{{ $p->per_id}}" @if ($empresa->fkPeriocidadRetencion == $p->per_id )
                        selected
                    @endif>{{ $p->per_nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>      

    <div class="form-check">
        <input type="checkbox" class="form-check-input" id="exento"
            @if($empresa->exento == 1)
                checked
            @endif
        disabled>
        <label class="form-check-label" for="exento">¿Exento de parafiscales?</label>
    </div><br>
    
    <div class="form-check">
        <input type="checkbox" class="form-check-input" id="vacacionesNegativas"
            @if($empresa->vacacionesNegativas == 1)
                checked
            @endif
        disabled>
        <label class="form-check-label" for="vacacionesNegativas">¿Vacaciones negativas?</label>
    </div><br>
    <div class="form-check">
        <input  type="checkbox" class="form-check-input" id="pagoParafiscales"
            @if($empresa->pagoParafiscales == 1)
                checked
            @endif disabled>
        <label class="form-check-label" for="pagoParafiscales">Pago paraficales (sobre el 100% del salario integral)?</label>
    </div><br>
    <fieldset>
        <legend>Actividad econ&oacute;mica Decreto 768</legend>
        <div class="form-group">
            <label for="ciiu768">Ciiu</label>
            <input type="text" disabled class="form-control" id="ciiu768" name = "ciiu768" value="{{$ciiuSelect}}" />
        </div>
        <div class="form-group">
            <label for="riesgo768">Riesgo</label>
            <select name="riesgo768" disabled id="riesgo768" class="form-control">
                <option value="">-- Seleccione una opción --</option>
                @foreach ($riesgos as $riesgo)
                    <option value="{{ $riesgo->riesgo}}" @if ($riesgoSelect == $riesgo->riesgo )
                        selected
                    @endif>{{ $riesgo->riesgo }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="codigo768">Código</label>
            <select name="codigo768" disabled id="codigo768" class="form-control">
                <option value="">-- Seleccione una opción --</option>
                @foreach ($codigos as $codigo)
                    <option value="{{ $codigo->codigo}}" @if ($codigoSelect == $codigo->codigo )
                        selected
                    @endif>{{ $codigo->codigo }}</option>
                @endforeach
            </select>
            <span id="nombre_actividad">{{$actividad_nombre}}</span>
        </div>
        
    </fieldset>

</form>