<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|string|in:VIP,Standard,Basic',
            'price' => 'required|numeric|min:0.01',
            'quantity' => 'required|integer|min:1',
        ];
    }
}
