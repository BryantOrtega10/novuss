@extends('layouts.admin')
@section('title', 'Conceptos World Office')
@section('menuLateral')
    @include('layouts.partials.menu', [
        'dataUsu' => $dataUsu
    ])
@endsection

@section('contenido')
<div class="row">
    <div class="col-10">
        <h1 class="granAzul">Conceptos World Office</h1>
    </div>
    @if (in_array("156",$dataUsu->permisosUsuario))
    <div class="col-2 text-right">
        <a class="btn btnAzulGen btnGeneral text-center" href="{{route('conceptos_wo.create')}}" id="addConcepto">Agregar</a>
    </div>
    @endif
</div>

<div class="cajaGeneral">
    @if (session('message'))
        <div class="alert alert-success">
            {{ session('message') }}
        </div>
    @endif
    <br>
    @if($errors->any())
        <div class="alert alert-danger">
            <strong>{{$errors->first()}}</strong>
        </div>
        <br>
    @endif
    
    <div class="table-responsive">
        <table class="table table-hover table-striped" id = "conceptos">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Nombre</th>
                    <th scope="col">Unidad de medida</th>
                    <th scope="col"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($conceptos as $concepto)
                <tr>
                    <th scope="row">{{ $concepto->id }}</th>
                    <td>{{ $concepto->nombre }}</td>
                    <td>{{ $concepto->unidad_medida }}</td>
                    <td>
                        @if (in_array("157",$dataUsu->permisosUsuario))
                        <a href="{{route('conceptos_wo.update', ['id' => $concepto->id])}}" class="update"><i class="fas fa-edit"></i></a>
                        @endif
                        @if (in_array("158",$dataUsu->permisosUsuario))
                        <a href="{{route('conceptos_wo.delete', ['id' => $concepto->id])}}" class="delete"><i class="fas fa-trash"></i></a>
                        @endif                    
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<div class="modal fade modal-delete" tabindex="-1" role="dialog" aria-labelledby="modal-delete" aria-hidden="true">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="my-0">En verdad desea eliminar ese concepto?</h6>
            </div>
            <div class="modal-body">                    
                <form method="POST" action="" id="form-delete">
                    @csrf
                    <div class="row">
                        <div class="col-sm-6 text-center">
                            <input type="submit" class="btn btn-danger full-width" value="Eliminar" />
                        </div>
                        <div class="col-sm-6 text-center">
                            <input type="button" class="btn btn-light full-width" data-dismiss="modal" value="Cancelar" />
                        </div>
                    </div>                                                
                </form>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript" src="{{ URL::asset('js/concepto_wo.js') }}"></script>
@endsection
