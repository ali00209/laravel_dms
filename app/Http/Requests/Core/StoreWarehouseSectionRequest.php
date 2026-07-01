<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseSectionRequest extends BaseFormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'integer', $this->existsActive('organizations')],
            'warehouse_id' => ['required','integer', $this->existsActive('warehouses')],
            'parent_section_id' => ['nullable','integer', $this->existsActive('warehouse_sections')],
            'section_category_id' => ['nullable','integer', $this->existsActive('section_categories')],
            'hierarchy_path' => ['nullable','string','max:255'],
            'level' => ['nullable','integer','min:1'],
            'name' => ['required','string','max:255'],
            'code' => ['nullable','string','max:100',
                Rule::unique('warehouse_sections')->where(fn($q)=> $q->where('warehouse_id', $this->input('warehouse_id'))),
            ],
            'zone' => ['nullable','string','max:100'],
            'aisle' => ['nullable','string','max:100'],
            'rack' => ['nullable','string','max:100'],
            'shelf' => ['nullable','string','max:100'],
            'bin' => ['nullable','string','max:100'],
            'description' => ['nullable','string'],
            'is_active' => ['nullable','boolean'],
        ];
    }
}
