<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Services\DataScopeService;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Company Name on the Credentials form: a kpncorp.companies
 * contribution_level_code that must belong to the chosen business unit.
 * companies lives in the other database, so this cannot be an exists: rule.
 * Required only when the unit has any company to pick -- the field is hidden
 * otherwise, like Office Location.
 */
trait ValidatesNonEmployeeCompany
{
    protected function companyRules(): array
    {
        $options = app(DataScopeService::class)
            ->companyOptionsFor($this->user(), $this->input('business_unit'));

        return [
            Rule::requiredIf($options->isNotEmpty()),
            'nullable',
            'string',
            'max:255',
            function (string $attribute, mixed $value, Closure $fail) use ($options) {
                if (! $options->contains('code', $value)) {
                    $fail(__('The selected company does not belong to this business unit.'));
                }
            },
        ];
    }
}
