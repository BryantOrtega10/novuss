function cargando() {
    if (typeof $("#cargando")[0] !== 'undefined') {
        $("#cargando").css("display", "flex");
    } else {
        $("body").append('<div id="cargando" style="display: flex;">Cargando...</div>');
    }
}
$(document).ready(function() {
    $("#empresas").DataTable({
        "order": [
            [1, "asc"]
        ],
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
        $(".conceptoMasCont[data-id='" + $(this).attr("data-id") + "']").remove();
    });


    /* $("body").on("click", "#masConceptos", function(e) {
        e.preventDefault();
        cargando();
        var numConceptos = $("#numConceptos").val();
        numConceptos++;
        var url = "/grupoConcepto/getForm/masConceptos/" + numConceptos;

        $.ajax({
            type: 'GET',
            url: url,
            success: function(data) {
                $("#cargando").css("display", "none");

                $("#numConceptos").val(numConceptos)
                $('.conceptosCont').append(data);
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
    }); */



    $("#addEmpresa").click(function(e) {
        e.preventDefault();
        cargando();
        $.ajax({
            type: 'GET',
            url: "/empresa/getForm/add",
            success: function(data) {
                $("#cargando").css("display", "none");
                $(".respForm[data-para='empresas']").html(data);
                $('#empresasModal').modal('show');
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
        const exentoParaf = $("#exento")[0].checked;
        const vacacionesNeg = $("#vacacionesNegativas")[0].checked;
        const pagoParafiscales = $("#pagoParafiscales")[0].checked;
        const LRN_cesantias = $("#LRN_cesantias")[0].checked;

        if (exentoParaf) {
            formdata.append('exento', 1);
        } else {
            formdata.append('exento', 0);
        }

        if (vacacionesNeg) {
            formdata.append('vacacionesNegativas', 1);
        } else {
            formdata.append('vacacionesNegativas', 0);
        }

        if (LRN_cesantias) {
            formdata.append('LRN_cesantias', 1);
        } else {
            formdata.append('LRN_cesantias', 0);
        }

        if (pagoParafiscales) {
            formdata.append('pagoParafiscales', 1);
        } else {
            formdata.append('pagoParafiscales', 0);
        }


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
                $("#cargando").css("display", "none");
                const error = data.responseJSON;
                if (error.error_code === 'VALIDATION_ERROR') {
                    mostrarErrores(error.errors);
                } else {
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
        const idEmpresa = e.target.attributes.dataId.value;
        cargando();
        $.ajax({
            type: 'GET',
            url: `/empresa/datosEmpresaXId/${idEmpresa}`,
            success: function(data) {
                $("#cargando").css("display", "none");
                $(".respForm[data-para='empresas']").html(data);
                $('#empresasModal').modal('show');
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
        const idEditar = $("#idempresa").val();
        var formdata = new FormData(this);
        const exentoParaf = $("#exento")[0].checked;
        const vacacionesNeg = $("#vacacionesNegativas")[0].checked;
        const pagoParafiscales = $("#pagoParafiscales")[0].checked;
        const LRN_cesantias = $("#LRN_cesantias")[0].checked;

        if (exentoParaf) {
            formdata.append('exento', 1);
        } else {
            formdata.append('exento', 0);
        }

        if (vacacionesNeg) {
            formdata.append('vacacionesNegativas', 1);
        } else {
            formdata.append('vacacionesNegativas', 0);
        }

        if (pagoParafiscales) {
            formdata.append('pagoParafiscales', 1);
        } else {
            formdata.append('pagoParafiscales', 0);
        }

        if (LRN_cesantias) {
            formdata.append('LRN_cesantias', 1);
        } else {
            formdata.append('LRN_cesantias', 0);
        }

        $.ajax({
            type: 'POST',
            url: `${$(this).attr("action")}/${idEditar}`,
            cache: false,
            processData: false,
            contentType: false,
            data: formdata,
            success: function(data) {
                $("#cargando").css("display", "none");
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
                $("#cargando").css("display", "none");
                const error = data.responseJSON;
                if (error.error_code === 'VALIDATION_ERROR') {
                    mostrarErrores(error.errors);
                } else {
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
        const idEmpresa = e.target.attributes.dataId.value;

        $.ajax({
            type: 'GET',
            url: `/empresa/detalleEmpresa/${idEmpresa}`,
            success: function(data) {
                $("#cargando").css("display", "none");
                $(".respForm[data-para='empresas']").html(data);
                $('#empresasModal').modal('show');
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
        const idEmpresa = e.target.attributes.dataId.value;
        $.ajax({
            type: 'POST',
            url: `/empresa/eliminarEmpresa/${idEmpresa}`,
            cache: false,
            processData: false,
            contentType: false,
            success: function(data) {
                $("#cargando").css("display", "none");
                if (data.success) {
                    window.location.reload();
                } else {
                    $("#infoErrorForm").css("display", "block");
                    $("#infoErrorForm").html(data.mensaje);
                }
            },
            error: function(data) {
                $("#cargando").css("display", "none");
                console.log(data);
            }
        });
    });


    $("body").on("change", "#periodo", (e) => {
        const periodo = $("#periodo option:selected").val();
        if (periodo == "15") {
            $(".para15Dias").css("display", "block");
        } else {
            $(".para15Dias").css("display", "none");
        }
    });

    $("body").on("change", "#pais", (e) => {
        const idUbi = $("#pais option:selected").val();
        traerUbicacionesFk('#deptos', idUbi);
    });

    $("body").on("change", "#deptos", (e) => {
        const idUbi = $("#deptos option:selected").val();
        traerUbicacionesFk('#fkUbicacion', idUbi);
    });

    $("body").on("keypress", "#dominio", function(e) {
        var regex = new RegExp("^[a-zA-Z'.\S]{1,40}$");
        var key = String.fromCharCode(!e.charCode ? e.which : e.charCode);
        if (regex.test(key)) {
            return true;
        }

        e.preventDefault();
        return false;
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

function traerUbicacionesFk(domAppend, idUbi) {
    solicitudAjax(`/ubicaciones/obtenerHijos/${idUbi}`, 'GET', null,
        (data) => {
            $(domAppend).empty();
            $(domAppend).append(data.opciones);
        }, (err) => {
            console.log(error);
        }
    );
}