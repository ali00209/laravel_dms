<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateSectionCategoryRequest extends BaseFormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('section_category') ?? $this->route('id');

        return [
            'organization_id' => ['sometimes','required','integer', $this->existsActive('organizations')],
            'name' => [
                'sometimes','required','string','max:255',
                Rule::unique('section_categories')->where(fn($q) => $q->where('organization_id', $this->input('organization_id') ?? $this->route('organization_id')))->ignore($id),
            ],
            'description' => ['sometimes','nullable','string'],
        ];
    }
}
