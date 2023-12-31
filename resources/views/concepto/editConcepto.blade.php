<form method="post" action="/concepto/editarConcepto" class="formEdit">
	<h2>Editar concepto</h2>
	@csrf
	<div class="form-group">
		<label for="nombre" class="control-label">Nombre :</label>
		<input type="text" class="form-control" id="nombre" name="nombre" value = "{{ $conceptos->nombre }}"/>
	</div>
	<div class="form-group">
		<label for="fkNaturaleza" class="control-label">Naturaleza</label>
		<select class="form-control" id="fkNaturaleza" name="fkNaturaleza">
			<option value="">Seleccione uno</option>
			@foreach($naturalezas as $naturaleza)
				<option value="{{$naturaleza->idnaturalezaConcepto}}"
				@if ($naturaleza->idnaturalezaConcepto == old('fkNaturaleza', $conceptos->fkNaturaleza))
					selected="selected"
				@endif
				>{{$naturaleza->nombre}}</option>
			@endforeach
		</select>
	</div>
	<div class="form-group">
		<label for="fkTipoConcepto" class="control-label">Tipo: </label>
		<select class="form-control" id="fkTipoConcepto" name="fkTipoConcepto">
			<option value="">Seleccione uno</option>
			@foreach($tiposConcepto as $tipoConcepto)
				<option value="{{$tipoConcepto->idtipo_concepto}}"
				@if ($tipoConcepto->idtipo_concepto == old('fkTipoConcepto', $conceptos->fkTipoConcepto))
					selected="selected"
				@endif
				>{{$tipoConcepto->nombre}}</option>
			@endforeach
		</select>
	</div>
	<div class="form-group">
		<label for="subTipo" class="control-label">Sub tipo:</label>
		<select class="form-control" id="subTipo" name="subTipo">
			<option value="">Seleccione uno</option>
			<option value="Dias"
			@if ($conceptos->subTipo == old('subTipo', "Dias"))
				selected="selected"
			@endif
			>Dias</option>
			<option value="Formula"
			@if ($conceptos->subTipo == old('subTipo', "Formula"))
				selected="selected"
			@endif
			>Formula</option>
			<option value="Porcentaje"
			@if ($conceptos->subTipo == old('subTipo', "Porcentaje"))
				selected="selected"
			@endif
			>Porcentaje</option>
			<option value="Tabla"
			@if ($conceptos->subTipo == old('subTipo', "Tabla"))
				selected="selected"
			@endif
			>Tabla</option>
			<option value="Valor"
			@if ($conceptos->subTipo == old('subTipo', "Valor"))
				selected="selected"
			@endif
			>Valor</option>
		</select>
	</div>
	<div class="respFormulaConcepto"></div>
	<div class="form-group elementoVariable">
		<label for="fkVariable" class="control-label">Variable</label>
		<select class="form-control" id="fkVariable" name="fkVariable">
			<option value="">Seleccione uno</option>
			@foreach($variables as $variable)
				<option value="{{$variable->idVariable}}"
					@if ($variable->idVariable == old('fkVariable', $conceptos->fkVariable))
                        selected="selected"
                    @endif
				>{{$variable->nombre}}</option>
			@endforeach
		</select>
	</div>
	<div class="form-group">
		<label for="numRetefuente" class="control-label">Numero en retefuente:</label>
		<input type="text" class="form-control" id="numRetefuente" name="numRetefuente" value = "{{ $conceptos->numRetefuente }}" />
	</div>
	<div class="form-group">
		<label for="generacionAutomatica" class="control-label">Generaci&oacute;n</label>
		<select class="form-control" id="generacionAutomatica" name="generacionAutomatica">
			<option value="1"
			@if ($conceptos->generacionAutomatica == old('generacionAutomatica', 1))
				selected="selected"
			@endif
			>AUTOMATICA</option>
			<option value="0"
			@if ($conceptos->generacionAutomatica == old('generacionAutomatica', 0))
				selected="selected"
			@endif
			>MANUAL</option>
		</select>
	</div>
	<div class="form-group">
		<label for="fkGrupoNomina" class="control-label">N&oacute;mina electr&oacute;nica *</label>
		<select class="form-control" id="fkGrupoNomina"  name="fkGrupoNomina">
			<option value="">SIN ASIGNAR</option>
			@foreach ($gruposNomina as $grupoNomina)
				<option value="{{$grupoNomina->idGrupoNominaElectronica}}"
					@if ($grupoNomina->idGrupoNominaElectronica == old('fkGrupoNomina', $conceptos->fkGrupoNomina))
						selected="selected"
					@endif
					>{{$grupoNomina->descripcion_corta." (".$grupoNomina->codigo.")"}}</option>
			@endforeach
		</select>
	</div>
	<button type="submit" class="btn btn-success">Guardar cambios</button>
</form>