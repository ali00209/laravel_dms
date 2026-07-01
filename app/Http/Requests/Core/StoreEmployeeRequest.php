<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\BaseFormRequest;

class StoreEmployeeRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'exists:organizations,id'],
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
