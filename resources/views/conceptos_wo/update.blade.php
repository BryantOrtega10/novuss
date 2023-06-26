@extends('layouts.admin')
@section('title', 'Modificar Conceptos World Office')
@section('menuLateral')
    @include('layouts.partials.menu', [
        'dataUsu' => $dataUsu
    ])
@endsection

@section('contenido')
<div class="row">
    <div class="col-12">
        <h1 class="granAzul">Modificar Conceptos World Office</h1>
    </div>
 </div>

<div class="cajaGeneral">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{route('conceptos_wo.index')}}">Conceptos World Office</a></li>
            <li class="breadcrumb-item active" aria-current="page">Modificar</li>
        </ol>
    </nav>
    <form method="POST" class="formGeneral" action="{{ route('conceptos_wo.update', ['id' => $conceptoWO->id]) }}" enctype="multipart/form-data">
        @csrf
        <div class="row">
            <div class="col-md-3">
                <div class="form-group form-nuevo @if (old('nombre',$conceptoWO->nombre) != null ) hasText @endif">
                    <label for="nombre" class="control-label">Nombre</label>
                    <input type="text" class="form-control @error('nombre') is-invalid @enderror" id="nombre" name="nombre" value="{{old('nombre',$conceptoWO->nombre)}}" />
                    @error('nombre')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>                
            </div>
            <div class="col-md-3">
                <div class="form-group form-nuevo @if (old('unidad_medida',$conceptoWO->unidad_medida) != null ) hasText @endif">
                    <label for="unidad_medida" class="control-label">Unidad de Medida</label>
                    <select class="form-control @error('unidad_medida') is-invalid @enderror" id="unidad_medida" name="unidad_medida" >
                        <option value="" style="display: none;"></option>
                        <option value="D" @if (old('unidad_medida',$conceptoWO->unidad_medida) == "D") selected @endif>D</option>
                        <option value="Und." @if (old('unidad_medida',$conceptoWO->unidad_medida) == "Und.") selected @endif>Und.</option>
                        <option value="h"  @if (old('unidad_medida',$conceptoWO->unidad_medida) == "h") selected @endif>h</option>
                    </select>
                    @error('unidad_medida')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
                
            </div>
            
        </div>
        <button type="submit" class="btn btn-primary text-center">Modificar</button>
    </form>
</div>

@endsection
