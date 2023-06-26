@extends('layouts.partials.portalEmpleado.head', [
    'dataEmple' => $dataEmple,
    'fotoEmple' => $fotoEmple,
    'periodos' => $periodos
])
@section('title', 'Home | Portal Empleado')
@section('contenidoPortal')
<h1 class="granAzul">Portal empleado</h1>
<!-- Sección de botones portal empleado -->
<input type = "hidden" name = "idUsu" id = "idUsu" value = "{{ $dataUsu->fkEmpleado }}">
<div class="row">
    @if (substr($dataEmpr->permisosGenerales, 0, 1) == "1")
        <div class="col-lg-3 col-md-6 col-sm-12 col-xs-12">
            <div class="card tarjeta_hover puntero alto_tarjeta info_laboral">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">Información laboral</h5>
                    <p class="card-text mt-auto">Consulte información sobre su actual trabajo.</p>
                </div>
            </div>
        </div>
    @endif
    @if (substr($dataEmpr->permisosGenerales, 2, 1) == "1")    
    <div class="col-lg-3 col-md-6 col-sm-12 col-xs-12">
        <div class="card tarjeta_hover puntero alto_tarjeta vacaciones_emple">
            <div class="card-body d-flex flex-column">
                <h5 class="card-title">Vacaciones</h5>
                <p class="card-text mt-auto">Enterese de sus vacaciones disfrutadas o por disfrutar.</p>
            </div>
        </div>
    </div>
    @endif
    @if (substr($dataEmpr->permisosGenerales, 4, 1) == "1")    
    <div class="col-lg-3 col-md-6 col-sm-12 col-xs-12">
        <div class="card tarjeta_hover puntero alto_tarjeta certificado_laboral"
            data-idempleado = "{{ $dataUsu->fkEmpleado }}
        ">
            <form id = "certificadoLaboral" method = "GET" target="_blank" action = "/portal/generarCertificadoLaboral/{{ $dataUsu->fkEmpleado }}"></form>
            <div class="card-body d-flex flex-column">
                <h5 class="card-title">Certificado laboral</h5>
                <p class="card-text mt-auto">Genere su certificado laboral seleccionando un periodo</p>
            </div>
        </div>
    </div>
    @endif
    @if (substr($dataEmpr->permisosGenerales, 6, 1) == "1")   
    <div class="col-lg-3 col-md-6 col-sm-12 col-xs-12">
        <div class="card tarjeta_hover puntero alto_tarjeta comprobantes_pago"
            data-idempleado = "{{ $dataUsu->fkEmpleado }}"
        >
            <div class="card-body d-flex flex-column">
                <h5 class="card-title">Comprobante de pago</h5>
                <p class="card-text mt-auto">Genere su comprobante de pago</p>
            </div>
        </div>
    </div>
    @endif
    @if (substr($dataEmpr->permisosGenerales, 8, 1) == "1")   
    <div class="col-lg-3 col-md-6 col-sm-12 col-xs-12">
        <div class="card tarjeta_hover puntero alto_tarjeta certificado_dosveinte"
            data-idempresa = "{{ $dataEmpr->idempresa }}"
            data-nomina = "{{ $dataEmpr->fkNomina }}"
            data-idempleado = "{{ $dataUsu->fkEmpleado }}"
            data-fechaexp = "2020"
            data-agente = "{{ $dataEmpr->razonSocial }}"
            >
            <div class="card-body d-flex flex-column">
                <h5 class="card-title">Certificado de ingreso y retenciones</h5>
                <p class="card-text mt-auto">Genere su certificado de ingreso y retenciones</p>
            </div>
        </div>
    </div>
    @endif
    @if (substr($dataEmpr->permisosGenerales, 10, 1) == "1")   
    <div class="col-lg-3 col-md-6 col-sm-12 col-xs-12">
        <div class="card tarjeta_hover puntero alto_tarjeta cambiar_pass">
            <div class="card-body d-flex flex-column">
                <h5 class="card-title">Cambiar contraseña</h5>
                <p class="card-text mt-auto">Cambie la contraseña para ingresar al portal</p>
            </div>
        </div>
    </div>
    @endif
    
    @if (substr($dataEmpr->permisosGenerales, 12, 1) == "1")   
    <div class="col-lg-3 col-md-6 col-sm-12 col-xs-12">
        <div class="card tarjeta_hover puntero alto_tarjeta perfil_emple">
            <div class="card-body d-flex flex-column">
                <h5 class="card-title">Perfil</h5>
                <p class="card-text mt-auto">Actualice los datos del portal de empleado</p>
            </div>
        </div>
    </div>
    @endif
</div>
<div class="modal fade" id="portalEmpleModal" tabindex="-1" role="dialog" aria-labelledby="variableModal" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <div class="cerrarPop" data-dismiss="modal"></div>
                <div class="respForm" data-para='portalEmple'></div>
            </div>
        </div>
    </div>
</div>
@endsection