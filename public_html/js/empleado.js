function cargando(){
	if(typeof $("#cargando")[0] !== 'undefined'){
		$("#cargando").css("display", "flex");
	}
	else{
		$("body").append('<div id="cargando" style="display: flex;">Cargando...</div>');
	}
}
$(document).ready(function(){
	$.ajaxSetup({
	    headers: {
	        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
	    }
	});
	
	$("body").on("click", "#btnContinuarCamposVacios",function(e){
		e.preventDefault();
		if($(this).attr("data-accion") == "enviarFormulario"){
			$($(this).attr("data-form")).submit();
			$('#camposVaciosModal').modal('hide');
		}
	});
	
	
});
