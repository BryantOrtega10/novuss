<form method="post" action="/centroTrabajo/editarCentroTrabajo/{{ $centro->idCentroTrabajo }}" class="formGen">
	<h2>Detalle calendario</h2>
	@csrf
	<div class="form-group">
		<label for="codigo" class="control-label">Código *</label>
		<input type="number" class="form-control" id="codigo" disabled name="codigo" value = "{{ $centro->codigo }}" />
	</div>
	<div class="form-group">
		<label for="nombre" class="control-label">Nombre *</label>
		<input type="text" class="form-control" id="nombre" disabled name="nombre" value = "{{ $centro->nombre }}" />
	</div>
	<div class="form-group">
		<label for="ciiu768">Ciiu</label>
		<input type="text" class="form-control" disabled id="ciiu768" name = "ciiu768" value="{{$ciiuSelect}}" />
	</div>
	<div class="form-group">
		<label for="riesgo768">Riesgo</label>
		<select name="riesgo768" id="riesgo768" disabled class="form-control">
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
		<select name="codigo768" id="codigo768" disabled class="form-control">
			<option value="">-- Seleccione una opción --</option>
			@foreach ($codigos as $codigo)
				<option value="{{ $codigo->codigo}}" @if ($codigoSelect == $codigo->codigo )
					selected
				@endif>{{ $codigo->codigo }}</option>
			@endforeach
		</select>
		<span id="nombre_actividad">{{$actividad_nombre}}</span>
	</div>
</form>