<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'sometimes|integer|exists:organizations,id',
            'name' => 'required|string|max:255',
            'monthly_salary' => 'nullable|numeric|min:0',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'designation' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'hire_date' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ];
    }
}
