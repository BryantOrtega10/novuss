<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @include('layouts.partials.portalEmpleado.includes')
        <title>@yield('title','Portal Empleado') | Gesath</title>
    </head>
    <body>
        <nav class="barra_nav navbar navbar-expand-sm justify-content-end">
            <span class="navbar-brand color_blanco margen_izq_brand">
                <div class="foto_perfil_redonda"><img src = "{{ $fotoEmple }}"></div> &nbsp;
                {{ $dataEmple->primerNombre }} {{ $dataEmple->segundoNombre }} {{ $dataEmple->primerApellido }} {{ $dataEmple->segundoApellido }}
                @isset($periodos)
                    @if (sizeof($periodos) > 1)
                    <select id="empresas" name="empresas">
                        @foreach ($periodos as $periodo)
                            <option value="{{$periodo->idPeriodo}}"
                                @if ($periodo->idPeriodo == session('idPeriodo'))
                                    selected
                                @endif
                                >{{$periodo->razonSocial}}</option>
                        @endforeach 
                    </select>
                    @endif
                  
                @endisset
            </span>
            <div class="flex-grow-0" id="navbarSupportedContent">
                <ul class="navbar-nav text-right">
                    <li class="nav-item dropdown">
                        <a class = "cerrar_sesion">
                            <form action = "/logout" method = "GET">
                                @csrf
                                <button class = "btn btn btn-danger" type = "submit">Cerrar sesión</button>
                            </form>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
        <section class="contenido">
            <div class="contenidoInterno">
                @yield('contenidoPortal')
            </div>          
        </section>
    </body>
</html>
