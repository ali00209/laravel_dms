<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateOutletSectionRequest extends BaseFormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('outlet_section') ?? $this->route('id');

        return [
            'organization_id' => ['sometimes','required','integer', $this->existsActive('organizations')],
            'outlet_id' => ['sometimes','required','integer', $this->existsActive('outlets')],
            'name' => [
                'sometimes','required','string','max:255',
                Rule::unique('outlet_sections')->where(fn($q)=> $q->where('outlet_id', $this->input('outlet_id') ?? $this->route('outlet_id')))->ignore($id),
            ],
            'code' => ['sometimes','nullable','string','max:100'],
            'is_pos_counter' => ['sometimes','nullable','boolean'],
            'display_order' => ['sometimes','nullable','integer'],
            'is_active' => ['sometimes','nullable','boolean'],
        ];
    }
}
