<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ConceptoWO extends Model
{
    protected $table = 'conceptos_wo';
	public $timestamps = false;
    protected $fillable = [
        'nombre',
        'unidad_medida',
    ];

}
