<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\Rule;
class StoreUserAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            "user_id" => (int) $this->route("user_id"),
        ]);
    }

    public function rules(): array
    {
        return [
            "user_id" => "required|integer|exists:users,id",
            "role_id" => "required|integer|exists:roles,id",
            // 🔥 Validate against your Morph Map keys only
            "assignable_type" => [
                "required",
                "string",
                Rule::in(array_keys(Relation::morphMap())),
            ],
            "assignable_id" => "required|integer",
            "is_active" => "nullable|boolean",
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $modelClass = Relation::getMorphedModel($this->assignable_type);

            if (!$modelClass) {
                $v->errors()->add(
                    "assignable_type",
                    "Invalid assignable_type.",
                );

                return;
            }

            if (!$modelClass::whereKey($this->assignable_id)->exists()) {
                $v->errors()->add("assignable_id", "Invalid assignable_id.");
            }
        });
    }
}
