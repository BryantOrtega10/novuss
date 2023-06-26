@extends('layouts.admin')
@section('title', 'Modificar Mensaje')
@section('menuLateral')
    @include('layouts.partials.menu', [
        'dataUsu' => $dataUsu
    ])
@endsection

@section('contenido')
<div class="row">
    <div class="col-12">
        <h1 class="granAzul">Modificar Mensaje</h1>
    </div>
</div>
<div class="row">
    <div class="col-12">
        <div class="cajaGeneral">
            @if (isset($modificacion) && $modificacion)
                <div class="p-3 mb-2 bg-success text-white desaparece">Modificación exitosa</div>
            @endif
            <form method="POST" action="/mensajes/modificar">
                @csrf
                <input type="hidden" name="idMensaje" value="{{$mensaje->idMensaje}}">
                <div class="text-left">
                    <label for="asunto">Asunto</label>
                </div>
                <input type="text" id="asunto" class="form-control" value="{{$mensaje->asunto}}" name="asunto">
                <textarea name="html" id="tinyMce">{{$mensaje->html}}</textarea><br>
                <div class="text-left">
                    <b>Campos:</b>
                </div>
                <ul class="camposDisponibles">
                    @foreach ($arrayCampos as $valor)
                        <li>{{$valor}}</li>
                    @endforeach
                </ul>
                <button type="submit" class="btnSubmitGen">Modificar Mensaje</button>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.tiny.cloud/1/yp871v34ln01zvn1b7dr1flllyzc3pge887bzbkeuzgd58f1/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>
<script type="text/javascript">
    tinymce.init({
        height: 500,
        selector: '#tinyMce',
        language: 'es',
        plugins: 'autolink lists media table code image',
        toolbar: 'addcomment showcomments casechange checklist code formatpainter pageembed permanentpen table code uploadimage',
        relative_urls : false,
        remove_script_host : false,
        convert_urls : true,
        images_upload_handler: example_image_upload_handler
    });
    function example_image_upload_handler (blobInfo, success, failure, progress) {
        let reader = new FileReader();    
        reader.onloadend = function() {
            console.log(reader);
            success(reader.result);
        }
        reader.readAsDataURL(blobInfo.blob());
        
        
        /*
        var xhr, formData;

        xhr = new XMLHttpRequest();
        xhr.withCredentials = false;
        xhr.open('POST', 'postAcceptor.php');

        xhr.upload.onprogress = function (e) {
            progress(e.loaded / e.total * 100);
        };

        xhr.onload = function() {
            var json;

            if (xhr.status === 403) {
            failure('HTTP Error: ' + xhr.status, { remove: true });
            return;
            }

            if (xhr.status < 200 || xhr.status >= 300) {
            failure('HTTP Error: ' + xhr.status);
            return;
            }

            json = JSON.parse(xhr.responseText);

            if (!json || typeof json.location != 'string') {
            failure('Invalid JSON: ' + xhr.responseText);
            return;
            }

            
        };

        xhr.onerror = function () {
            failure('Image upload failed due to a XHR Transport error. Code: ' + xhr.status);
        };

        formData = new FormData();
        formData.append('file', blobInfo.blob(), blobInfo.filename());

        xhr.send(formData);*/
    };
    $(document).ready(function(){
        $(".camposDisponibles li").click(function(e){
            tinymce.activeEditor.execCommand('mceInsertContent', false, $(this).html());    
        });
        
    });
    </script>
@endsection