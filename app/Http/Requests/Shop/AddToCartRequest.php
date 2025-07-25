<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'sometimes|integer|min:1|max:10',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'O produto é obrigatório.',
            'product_id.integer' => 'ID do produto deve ser um número.',
            'product_id.exists' => 'Produto não encontrado.',
            'quantity.integer' => 'Quantidade deve ser um número.',
            'quantity.min' => 'Quantidade mínima é 1.',
            'quantity.max' => 'Quantidade máxima é 10.',
        ];
    }

    /**
     * Get the validated data with defaults
     */
    public function getValidatedData(): array
    {
        $data = $this->validated();
        $data['quantity'] = $data['quantity'] ?? 1;
        
        return $data;
    }
}