<form action="/empresa/agregarEmpresa" class="formGen" method = "POST">
    <div class="row">
        <div class="col form-group">
            <label for="fkTipoCompania">Tipo de compañia</label>
            <select name="fkTipoCompania" id="fkTipoCompania" class="form-control">
                <option value="">-- Seleccione una opción --</option>
                @foreach ($tipoComp as $comp)
                    <option value="{{ $comp->idtipoCompania}}">{{ $comp->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col form-group">
            <label for="fkTipoAportante">Tipo de aportante</label>
            <select name="fkTipoAportante" id="fkTipoAportante" class="form-control">
                <option value="">-- Seleccione una opción --</option>
                @foreach ($tipoApor as $apor)
                    <option value="{{ $apor->idtipoAportante}}">{{ $apor->nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-group">
        <label for="razonSocial">Razón Social</label>
        <input type="text" class="form-control" id="razonSocial" name = "razonSocial">
    </div>

    <div class="form-group">
        <label for="fkTipoIdentificacion">Tipo de identificación</label>
        <select name="fkTipoIdentificacion" id="fkTipoIdentificacion" class="form-control">
            <option value="">-- Seleccione una opción --</option>
            @foreach ($tipoIdent as $tipo)
                <option value="{{ $tipo->idtipoIdentificacion}}">{{ $tipo->nombre }}</option>
            @endforeach
        </select>
    </div>

    <div class="row">
        <div class="col form-group">
            <label for="documento">NIT</label>
            <input type="number" class="form-control" id="documento" name = "documento">
        </div>
        <div class="col form-group">
            <label for="digitoVerificacion">Dígito verificación</label>
            <input type="number" class="form-control" id="digitoVerificacion" name = "digitoVerificacion">
        </div>
    </div>
    <div class="form-group">
        <label for="documento">Logo de la empresa</label>
        <input type="file" class="form-control" id="logoEmpresa" name = "logoEmpresa">
    </div>

    <div class="row">
        <div class="col form-group">
            <label for="sigla">Sigla</label>
            <input type="text" class="form-control" id="sigla" name = "sigla">
        </div>
        <div class="col form-group">
            <label for="dominio">Dominio</label>
            <input type="text" class="form-control" id="dominio" name = "dominio">
        </div>
    </div>

    <div class="form-group">
        <label for="representanteLegal">Nombre Representante Legal</label>
        <input type="text" class="form-control" id="representanteLegal" name = "representanteLegal">
    </div>

    {{-- <div class="row">
        <div class="col form-group">
            <label for="fkActividadEconomica">Tipo de actividad económica</label>
            <select name="fkActividadEconomica" id="fkActividadEconomica" class="form-control">
                <option value="">-- Seleccione una opción --</option>
                @foreach ($actEconomicas as $actEc)
                    <option value="{{ $actEc->idactividadEconomica}}">{{ $actEc->nombre }}</option>
                @endforeach
            </select>
        </div>
    </div> --}}

    <div class="form-group">
        <label for="docRepresentante">Tipo de identificación representante</label>
        <select name="docRepresentante" id="docRepresentante" class="form-control">
            <option value="">-- Seleccione una opción --</option>
            @foreach ($tipoIdent as $tipo)
                <option value="{{ $tipo->idtipoIdentificacion}}">{{ $tipo->nombre }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label for="numDocRepresentante">Número de documento representante</label>
        <input type="text" class="form-control" id="numDocRepresentante" name = "numDocRepresentante">
    </div>

    <div class="form-group">
        <label for="fkTercero_ARL">Tercero ARL</label>
        <select name="fkTercero_ARL" id="fkTercero_ARL" class="form-control">
            <option value="">-- Seleccione una opción --</option>
            @foreach ($terceroArl as $terArl)
                <option value="{{ $terArl->idTercero}}">{{ $terArl->razonSocial }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label for="pais">País</label>
        <select name="pais" id="pais" class="form-control">
            <option value="">-- Seleccione una opción --</option>
            @foreach ($paises as $p)
                <option value="{{ $p->idubicacion}}">{{ $p->nombre }}</option>
            @endforeach
        </select>
    </div>

    <div class="row">
        <div class="col form-group">
            <label for="deptos">Departamento</label>
            <select name="deptos" id="deptos" class="form-control">
                <option value="">-- Seleccione una opción --</option>
                
            </select>
        </div>
        <div class="col form-group">
            <label for="direccion">Ciudad</label>
            <select name="fkUbicacion" id="fkUbicacion" class="form-control">
                <option value="">-- Seleccione una opción --</option>
                
            </select>
        </div>
    </div>

    <div class="form-group">
        <label for="direccion">Dirección</label>
        <input type="text" class="form-control" id="direccion" name = "direccion">
    </div>

    <div class="form-group">
        <label for="paginaWeb">Página Web</label>
        <input type="text" class="form-control" id="paginaWeb" name = "paginaWeb">
    </div>

    <div class="row">
        <div class="col form-group">
            <label for="telefonoFijo">Teléfono</label>
            <input type="number" class="form-control" id="telefonoFijo" name = "telefonoFijo">
        </div>
        <div class="col form-group">
            <label for="celular">Celular</label>
            <input type="number" class="form-control" id="celular" name = "celular">
        </div>
    </div>

    <div class="row">
        <div class="col form-group">
            <label for="email1">Correo 1</label>
            <input type="email" class="form-control" id="email1" name = "email1">
        </div>
        <div class="col form-group">
            <label for="email2">Correo 2</label>
            <input type="email" class="form-control" id="email2" name = "email2">
        </div>
    </div>

    <div class="form-group">
        <label for="nom_cen_cost">Nombre centro de costo</label>
        <input type="text" class="form-control" id="nom_cen_cost" name = "nom_cen_cost">
    </div>

    <div class="form-group">
        <label for="id_uni_centro">ID Único Centro de Costo</label>
        <input type="text" class="form-control" id="id_uni_centro" name = "id_uni_centro">
    </div>

    <div class="form-group">
        <label for="id_uni_nomina">ID Único Nómina</label>
        <input type="text" class="form-control" id="id_uni_nomina" name = "id_uni_nomina">
    </div>

    <div class="form-group">
        <label for="SoftwareDianId">Software Dian Id nómina electrónica</label>
        <input type="text" class="form-control" id="SoftwareDianId" name = "SoftwareDianId">
    </div>
    <div class="form-group">
        <label for="SoftwareTestSetId">Software Test Set Id nómina electrónica</label>
        <input type="text" class="form-control" id="SoftwareTestSetId" name = "SoftwareTestSetId">
    </div>
    <div class="form-group">
        <label for="PrefijoNominaElectronica">Prefijo nómina electrónica</label>
        <input type="text" class="form-control" id="PrefijoNominaElectronica" name = "PrefijoNominaElectronica">
    </div>
    <div class="form-group">
        <label for="PrefijoNominaElectronicaReemplazo">Prefijo nómina electrónica reemplazo</label>
        <input type="text" class="form-control" id="PrefijoNominaElectronicaReemplazo" name = "PrefijoNominaElectronicaReemplazo">
    </div>

    <div class="form-group">
        <label for="PrefijoNominaElectronicaEliminacion">Prefijo nómina electrónica eliminacion</label>
        <input type="text" class="form-control" id="PrefijoNominaElectronicaEliminacion" name = "PrefijoNominaElectronicaEliminacion">
    </div>
    
    <div class="form-group">
        <label for="TipAmbNominaElectronica">Tip Ambiente nómina electrónica</label>
        <select name="TipAmbNominaElectronica" id="TipAmbNominaElectronica" class="form-control">
            <option value="">-- Seleccione una opción --</option>
            <option value="1">Producción</option>
            <option value="2">Pruebas</option>
        </select>
    </div>

    <div class="row">
        <div class="col form-group">
            <label for="SoftwareDianId">Periodo nómina (en días)</label>
            <select name="periodo" id="periodo" class="form-control">
                <option value="">-- Seleccione una opción --</option>
                <option value="15">15</option>
                <option value="30">30</option>
            </select>
        </div>
        <div class="col form-group">
            <label for="diasCesantias">Días cesantías</label>
            <select name="diasCesantias" id="diasCesantias" class="form-control">
                <option value="">-- Seleccione una opción --</option>
                <option value="30">30</option>
                <option value="36">36</option>
            </select>
        </div>
    </div>
    
    <div class="row para15Dias" style="display: none;">
        <div class="col form-group">
            <label for="fkPeriocidadRetencion">Periocidad retefuente </label>
            <select name="fkPeriocidadRetencion" id="fkPeriocidadRetencion" class="form-control">
                @foreach ($periocidad as $p)
                    <option value="{{ $p->per_id}}">{{ $p->per_nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="form-check">
        <input type="checkbox" class="form-check-input" value="1" id="exento">
        <label class="form-check-label" for="exento">¿Exento de parafiscales?</label>
    </div><br>
    
    <div class="form-check">
        <input type="checkbox" class="form-check-input" value="1" id="LRN_cesantias">
        <label class="form-check-label" for="LRN_cesantias">¿LRN para cesantias?</label>
    </div><br>

    <div class="form-check">
        <input type="checkbox" class="form-check-input" value="1" id="vacacionesNegativas">
        <label class="form-check-label" for="vacacionesNegativas">¿Vacaciones negativas?</label>
    </div><br>

    <div class="form-check">
        <input type="checkbox" class="form-check-input" value="1" id="pagoParafiscales">
        <label class="form-check-label" for="pagoParafiscales">Pago paraficales (sobre el 100% del salario integral)?</label>
    </div><br>
    <fieldset>
        <legend>Actividad econ&oacute;mica Decreto 768</legend>
        <div class="form-group">
            <label for="ciiu768">Ciiu</label>
            <input type="text" class="form-control" id="ciiu768" name = "ciiu768" />
        </div>
        <div class="form-group">
            <label for="riesgo768">Riesgo</label>
            <select name="riesgo768" id="riesgo768" class="form-control">
                <option value="">-- Seleccione una opción --</option>
            </select>
        </div>
        <div class="form-group">
            <label for="codigo768">Código</label>
            <select name="codigo768" id="codigo768" class="form-control">
                <option value="">-- Seleccione una opción --</option>
            </select>
            <span id="nombre_actividad"></span>
        </div>        
    </fieldset>
    
    <div class="form-group">
        <button type="submit" class="btn btn-primary">Agregar Empresa</button>
    </div>
</form>