function cargando() {
    if (typeof $("#cargando")[0] !== 'undefined') {
        $("#cargando").css("display", "flex");
    } else {
        $("body").append('<div id="cargando" style="display: flex;">Cargando...</div>');
    }
}
$(document).ready(function() {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $("body").on("click", "#btnContinuarCamposVacios", function(e) {
        e.preventDefault();
        if ($(this).attr("data-accion") == "enviarFormulario") {
            $($(this).attr("data-form")).submit();
            $('#camposVaciosModal').modal('hide');
        }
    });
    
    $("body").on("change","#cambiarEmpresasActivas", function(e){
        e.preventDefault();
        window.open("/empleado/formModificar/"+$("#idEmpleadoCambio").val()+"/"+$(this).val() + "?destino=infoLab", "_self");
    });

    $("body").on("submit", "#generarNuevaEmpresa", function(e) {
        e.preventDefault();
        cargando();
        var formdata = new FormData(this);
        $.ajax({
            type: 'POST',
            url: $(this).attr("action"),
            cache: false,
            processData: false,
            contentType: false,
            data: formdata,
            success: function(data) {
                $("#cargando").css("display", "none");
                if (data.success) {
                    window.open("/empleado/formModificar/"+data.idEmpleado+"/"+data.idPeriodo+"?destino=infoLab", "_self");
                } else {
                    alert(data.mensaje);
                }
            },
            error: function(data) {
                $("#cargando").css("display", "none");
                retornarAlerta(
                    data.responseJSON.exception,
                    data.responseJSON.message + ", en la linea: " + data.responseJSON.line,
                    'error',
                    'Aceptar'
                );
                console.log("error");
                console.log(data);
            }
        });
    });


    $("body").on("submit", "#formAgregarEmpleado2", function(e) {
        e.preventDefault();
        cargando();
        var formdata = new FormData(this);
        $.ajax({
            type: 'POST',
            url: $(this).attr("action"),
            cache: false,
            processData: false,
            contentType: false,
            data: formdata,
            success: function(data) {
                $("#cargando").css("display", "none");
                if (data.success) {
                    alert("La carga de empleados ha sido exitosa");
                    window.open("/empleado/", "_self");
                } else {
                    alert(data.respuesta);
                }
            },
            error: function(data) {
                $("#cargando").css("display", "none");
                retornarAlerta(
                    data.responseJSON.exception,
                    data.responseJSON.message + ", en la linea: " + data.responseJSON.line,
                    'error',
                    'Aceptar'
                );
                console.log("error");
                console.log(data);
            }
        });
    });


    $(".verPorqueFalla").click(function(e) {
        e.preventDefault();
        $("#respMensaje").html("");
        cargando();
        $.ajax({
            type: 'GET',
            url: $(this).attr("href"),
            success: function(data) {
                $("#cargando").css("display", "none");
                $("#respMensaje").html(data);
                $("#mostrarPorqueFalla").modal("show");

            },
            error: function(data) {
                $("#cargando").css("display", "none");
                retornarAlerta(
                    data.responseJSON.exception,
                    data.responseJSON.message + ", en la linea: " + data.responseJSON.line,
                    'error',
                    'Aceptar'
                );
                console.log("error");
                console.log(data);
            }
        });


    });
    $(".eliminarUsuario").click(function(e) {
        if (confirm("En verdad desea desactivar este usuario?")) {
            e.preventDefault();
            cargando();
            $.ajax({
                type: 'GET',
                url: '/empleado/desactivarEmpleado/' + $(this).attr("data-id") + '/' + $(this).attr("data-idPeriodo"),
                success: function(data) {
                    $("#cargando").css("display", "none");
                    if (data.success) {
                        window.location.reload();
                    }
                },
                error: function(data) {
                    $("#cargando").css("display", "none");
                    retornarAlerta(
                        data.responseJSON.exception,
                        data.responseJSON.message + ", en la linea: " + data.responseJSON.line,
                        'error',
                        'Aceptar'
                    );
                    console.log("error");
                    console.log(data);
                }
            });
        }


    });

    $(".recargar").click(function(e) {
        e.preventDefault();
        window.open("/empleado/", "_self");
    });
    $(".reactivarUsuario").click(function(e) {
        if (confirm("En verdad desea reactivar este usuario?")) {
            e.preventDefault();
            cargando();
            $.ajax({
                type: 'GET',
                url: '/empleado/reactivarEmpleado/' + $(this).attr("data-id") + '/' + $(this).attr("data-idPeriodo"),
                success: function(data) {
                    $("#cargando").css("display", "none");
                    if (data.success) {
                        window.location.reload();
                    }
                },
                error: function(data) {
                    $("#cargando").css("display", "none");
                    retornarAlerta(
                        data.responseJSON.exception,
                        data.responseJSON.message + ", en la linea: " + data.responseJSON.line,
                        'error',
                        'Aceptar'
                    );
                    console.log("error");
                    console.log(data);
                }
            });
        }
    });
    $(".eliminarDefUsuario").click(function(e) {
        if (confirm("En verdad desea eliminar definitivamente este usuario?")) {
            e.preventDefault();
            cargando();
            $.ajax({
                type: 'GET',
                url: '/empleado/eliminarDefUsuario/' + $(this).attr("data-id") + '/' + $(this).attr("data-idPeriodo"),
                success: function(data) {
                    $("#cargando").css("display", "none");
                    if (data.success) {
                        window.location.reload();
                    }
                    else{
                        alert(data.message);
                    }
                },
                error: function(data) {
                    $("#cargando").css("display", "none");
                    retornarAlerta(
                        data.responseJSON.exception,
                        data.responseJSON.message + ", en la linea: " + data.responseJSON.line,
                        'error',
                        'Aceptar'
                    );
                    console.log("error");
                    console.log(data);
                }
            });
        }
    });
    $(".ver_reintegro").click(function(e) {
        e.preventDefault();
        $("#respMensaje").html("");
        cargando();
        $.ajax({
            type: 'GET',
            url: $(this).attr("href"),
            success: function(data) {
                $("#cargando").css("display", "none");
                $("#respMensaje").html(data);
                $("#mostrarPorqueFalla").modal("show");

            },
            error: function(data) {
                $("#cargando").css("display", "none");
                retornarAlerta(
                    data.responseJSON.exception,
                    data.responseJSON.message + ", en la linea: " + data.responseJSON.line,
                    'error',
                    'Aceptar'
                );
                console.log("error");
                console.log(data);
            }
        });
    });

    $("body").on("change", "#empresaNueva", function(e) {
        e.preventDefault();

        $("#nominaNueva").html('<option value=""></option>');
        const idEmpresa = $(this).val();
        if (idEmpresa != "") {
            cargando();



            $.ajax({
                type: 'GET',
                url: "/empleado/cargarDatosPorEmpresa/" + idEmpresa,
                success: function(data) {
                    $("#cargando").css("display", "none");
                    $("#nominaNueva").html(data.opcionesNomina);
                    
                },
                error: function(data) {
                    $("#cargando").css("display", "none");
                    retornarAlerta(
                        data.responseJSON.exception,
                        data.responseJSON.message + ", en la linea: " + data.responseJSON.line,
                        'error',
                        'Aceptar'
                    );
                    console.log("error");
                    console.log(data);
                }
            });
        }


    });
});