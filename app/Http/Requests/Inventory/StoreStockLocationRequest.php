<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockLocationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            "organization_id" => $this->user()->activeContext()
                ->organization_id,
        ]);
    }

    public function rules(): array
    {
        return [
            "organization_id" => "required|integer|exists:organizations,id",
            "name" => ["required", "string", "max:255"],
            "code" => ["nullable", "string", "max:50"],
            "locatable_type" => ["required", "string"],
            "locatable_id" => ["required", "integer"],
            "is_active" => ["nullable", "boolean"],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $modelClass = Relation::getMorphedModel($this->locatable_type);

            if (!$modelClass) {
                $v->errors()->add("locatable_type", "Invalid locatable type.");
                return;
            }

            $model = $modelClass::find($this->locatable_id);

            if (!$model) {
                $v->errors()->add("locatable_id", "Invalid locatable Id.");
                return;
            }

            if (
                (int) $model->organization_id !== (int) $this->organization_id
            ) {
                $v->errors()->add(
                    "locatable_id",
                    "Locatable must belong to same organization.",
                );
            }
        });
    }
}
