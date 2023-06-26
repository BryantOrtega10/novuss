<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ActPassRequest extends FormRequest
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
            'token' => 'required',
            'password' => [
                'required', 
                'min:6', 
                'confirmed'
            ],
            'password_confirmation' => [
                'required', 
                'min:6', 
                
            ],
        ];
    }

    public function messages()
    {
        return [
            'token.required' => 'Es necesario el token de activación',
            
            'password.required' => 'Contraseña requerida',
            'password.min' => 'Mínimo 6 caracteres que contenga letras y números',
            'password.confirmed' => 'Las contraseñas deben ser iguales',
            'password_confirmation.required' => 'Contraseña de confirmación requerida',
            'password_confirmation.min' => 'Mínimo 6 caracteres que contenga letras y números',

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
