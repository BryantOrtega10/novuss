<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @include('layouts.partials.head')
		<link rel="stylesheet" href="{{ URL::asset('css/styleGen.css') }}">
        <title>Actualizar contraseña</title>
    </head>
    <body>        
        <div class="inicioSesion">
            <img src="{{ URL::asset('img/logo.png') }}" />
            <form method="POST" id = "act_pass" action="/act_pass">
                @csrf
                <input type = "hidden" name = "token" value = "{{ $token }}">
                <div class="form-group row">
                    <input id="password" type="password" class="form-control form_log" name="password" placeholder = "Contraseña nueva" required>
                </div>

                <div class="form-group row">
                    <input id="password_confirmation" type="password" class="form-control form_log" name="password_confirmation" placeholder = "Confirmar Contraseña" required>
                </div>

                <div class="form-group row mb-0">
                    <div class="col">
                        <input type="submit" value="Cambiar contraseña" />
                    </div>
                </div>
            </form>
        </div>
    </body>
    <style>
        input[type="email"].form_log, input[type="password"].form_log {
            padding-left: 17px;
            padding-top: 18px;
            height: 50px;
        }
    </style>
    <script src = "{{ URL::asset('js/funcionesGenerales.js') }}"></script>
    <script src = "{{ URL::asset('js/recuperarPass.js') }}"></script>
</html>