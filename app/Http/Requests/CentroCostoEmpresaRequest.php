<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CentroCostoEmpresaRequest extends FormRequest
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
        return [
            'nombre' => 'required',
            'fkEmpresa' => 'required'
        ];
    }

    public function messages()
    {
        return [
            'nombre.required' => 'Nombre de centro de costo requerido',
            'fkEmpresa.required' => 'ID de empresa requerido'
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json([
            'error_code'=> 'VALIDATION_ERROR', 
            'message'   => 'Asegurese de enviar los datos correctos', 
            'errors'    => $validator->errors()
        ], 422));
    }
}
