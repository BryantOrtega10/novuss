<?php

namespace App\Http\Requests;

use App\ConceptoWO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConceptoWORequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = ["unidad_medida" => "required"];
        if($this->route("id") !== null){
            $conceptoWO = ConceptoWO::findOrFail($this->route('id'));
            $rules["nombre"] = "required|unique:conceptos_wo,nombre,".$this->route('id');
        }
        else{
            $rules["nombre"] = "required|unique:conceptos_wo,nombre";
        }

        
        return $rules;
       
    }
}
