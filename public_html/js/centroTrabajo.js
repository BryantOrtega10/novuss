function cargando() {
    if (typeof $("#cargando")[0] !== 'undefined') {
        $("#cargando").css("display", "flex");
    } else {
        $("body").append('<div id="cargando" style="display: flex;">Cargando...</div>');
    }
}
$(document).ready(function() {
    $("#centroTrabajo").DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.10.22/i18n/Spanish.json'
        }
    });
    $(document).on('show.bs.modal', '.modal', function(event) {
        var zIndex = 1040 + (10 * $('.modal:visible').length);
        $(this).css('z-index', zIndex);
        setTimeout(function() {
            $('.modal-backdrop').not('.modal-stack').css('z-index', zIndex - 1).addClass('modal-stack');
        }, 0);
    });
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $("body").on("click", ".quitarConcepto", function(e) {
        e.preventDefault();
        $(".cargoMasCont[data-id='" + $(this).attr("data-id") + "']").remove();
    });

    $("#addCentroTrabajo").click(function(e) {
        e.preventDefault();
        cargando();
        $.ajax({
            type: 'GET',
            url: `/empresa/centroTrabajo/getFormAdd/${$(this).attr('data-id')}`,
            success: function(data) {
                $("#cargando").css("display", "none");
                $(".respForm[data-para='centroTrabajo']").html(data);
                $('#centroTrabajoModal').modal('show');
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
    $("body").on("submit", ".formGen", function(e) {
        e.preventDefault();
        cargando();
        $(".close").trigger('click');
        var formdata = new FormData(this);
        $.ajax({
            type: 'POST',
            url: $(this).attr("action"),
            cache: false,
            processData: false,
            contentType: false,
            data: formdata,
            success: function(data) {
                if (data.success) {
                    retornarAlerta(
                        '¡Hecho!',
                        data.mensaje,
                        'success',
                        'Aceptar'
                    );
                    window.location.reload();
                } else {
                    $("#infoErrorForm").css("display", "block");
                    $("#infoErrorForm").html(data.mensaje);
                }
            },
            error: function(data) {
                const error = data.responseJSON;
                if (error.error_code === 'VALIDATION_ERROR') {
                    mostrarErrores(error.errors);
                } else {
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
            }
        });
    });

    $(".editar").click((e) => {
        e.preventDefault();
        const idCentroTrabajo = e.target.attributes.dataId.value;
        cargando();
        $.ajax({
            type: 'GET',
            url: `/empresa/centroTrabajo/datosCentroTrabajoXId/${idCentroTrabajo}`,
            success: function(data) {
                $("#cargando").css("display", "none");
                $(".respForm[data-para='centroTrabajo']").html(data);
                $('#centroTrabajoModal').modal('show');
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

    $("body").on("submit", ".formEdit", function(e) {
        e.preventDefault();
        cargando();
        $(".close").trigger('click');
        const idEditar = $("#idCentroTrabajo").val();
        var formdata = new FormData(this);
        $.ajax({
            type: 'POST',
            url: `${$(this).attr("action")}/${idEditar}`,
            cache: false,
            processData: false,
            contentType: false,
            data: formdata,
            success: function(data) {
                if (data.success) {
                    retornarAlerta(
                        '¡Hecho!',
                        data.mensaje,
                        'success',
                        'Aceptar'
                    );
                    window.location.reload();
                } else {
                    $("#infoErrorForm").css("display", "block");
                    $("#infoErrorForm").html(data.mensaje);
                }
            },
            error: function(data) {
                const error = data.responseJSON;
                if (error.error_code === 'VALIDATION_ERROR') {
                    mostrarErrores(error.errors);
                } else {
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
            }
        });
    });

    $("body").on("click", ".detalle", (e) => {
        e.preventDefault();
        cargando();
        const idCentroTrabajo = e.target.attributes.dataId.value;
        $.ajax({
            type: 'GET',
            url: `/empresa/centroTrabajo/detalleCentroTrabajo/${idCentroTrabajo}`,
            success: function(data) {
                $("#cargando").css("display", "none");
                $(".respForm[data-para='centroTrabajo']").html(data);
                $('#centroTrabajoModal').modal('show');
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

    $("body").on("click", ".eliminar", (e) => {
        const confirmar = confirm('¿Está seguro de realizar esta acción?');
        e.preventDefault();
        cargando();
        const idCentroTrabajo = e.target.attributes.dataId.value;
        if (confirmar) {
            $.ajax({
                type: 'POST',
                url: `/empresa/centroTrabajo/eliminarCentroTrabajo/${idCentroTrabajo}`,
                cache: false,
                processData: false,
                contentType: false,
                success: function(data) {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        $("#infoErrorForm").css("display", "block");
                        $("#infoErrorForm").html(data.mensaje);
                    }
                },
                error: function(data) {
                    console.log(data);
                }
            });
        }
    });
    $("body").on("keypress", "#ciiu768", (e) => {
        if(e.keyCode == 13){
            e.preventDefault();
            const ciiu768 = $("#ciiu768").val();
            $("#codigo768").html(`<option value="">-- Seleccione una opción --</option>`);
            $("#nombre_actividad").html("");
            solicitudAjax(`/empresa/actividades_economicas/${ciiu768}`, 'GET', null,
            (data) => {
                $("#riesgo768").html(`<option value="">-- Seleccione una opción --</option>`);
                data.riesgos.forEach(riesgo => {
                    $("#riesgo768").append(`<option value="${riesgo}">${riesgo}</option>`);    
                });
               
                
            }, (err) => {
                console.log(error);
            }
            );
        }
        /**/
    });
    $("body").on("change", "#riesgo768", (e) => {
        const riesgo768 = $("#riesgo768 option:selected").val();
        const ciiu768 = $("#ciiu768").val();
        $("#nombre_actividad").html("");
        solicitudAjax(`/empresa/actividades_economicas/${riesgo768}/${ciiu768}`, 'GET', null,
        (data) => {
            $("#codigo768").html(`<option value="">-- Seleccione una opción --</option>`);
            data.codigos.forEach(codigo => {
                $("#codigo768").append(`<option value="${codigo}">${codigo}</option>`);    
            });

        }, (err) => {
            console.log(error);
        }
        );
    });
    
    $("body").on("change", "#codigo768", (e) => {
        const codigo768 = $("#codigo768 option:selected").val();
        const ciiu768 = $("#ciiu768").val();
        const riesgo768 = $("#riesgo768 option:selected").val();

        solicitudAjax(`/empresa/actividades_economicas/${riesgo768}/${ciiu768}/${codigo768}`, 'GET', null,
        (data) => {
            $("#nombre_actividad").html(data.nom_actividad);
        }, (err) => {
            console.log(error);
        }
        );
    });
});