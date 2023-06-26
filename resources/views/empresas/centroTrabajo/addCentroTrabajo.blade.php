<form method="post" action="/empresa/centroTrabajo/agregarCentroTrabajo/{{ $idEmpresa }}" class="formGen">
	<h2>Agregar centro trabajo</h2>
	@csrf
	<div class="form-group">
		<label for="codigo" class="control-label">Código *</label>
		<input type="number" class="form-control" id="codigo" required name="codigo" />
	</div>
	<div class="form-group">
		<label for="nombre" class="control-label">Nombre *</label>
		<input type="text" class="form-control" id="nombre" required name="nombre" />
	</div>

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
	<button type="submit" class="btn btn-success">Crear centro trabajo</button>
</form>