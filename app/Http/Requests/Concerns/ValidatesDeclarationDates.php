<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesDeclarationDates
{
    /**
     * Date-range answers are free-form strings inside the responses JSON, so
     * nothing downstream constrains their shape -- the column is JSON, not a
     * DATE, and it will happily store "275760-01-15".
     *
     * The browsers disagree here: Chrome and Edge let you type a six-digit
     * year straight into the year segment, and a Safari old enough to lack
     * native date support renders a plain text box where min/max mean nothing.
     * The client clamps what it can; this is the guard that holds regardless
     * of browser (or of the request coming from a browser at all).
     */
    protected function validateDeclarationDates(Validator $validator): void
    {
        foreach ((array) $this->input('responses', []) as $question => $response) {

            foreach ((array) ($response['details'] ?? []) as $index => $detail) {

                foreach ((array) $detail as $field => $value) {

                    if (! is_string($value) || $value === '') {
                        continue;
                    }

                    if (! preg_match('/_(from|to)$/', (string) $field)) {
                        continue;
                    }

                    if (! $this->isCalendarDate($value)) {
                        $validator->errors()->add(
                            "responses.$question.details.$index.$field",
                            'Please enter a valid date with a 4-digit year.'
                        );
                    }
                }
            }
        }
    }

    /**
     * Strict YYYY-MM-DD with a real calendar day, so both a 6-digit year and
     * an impossible date like 2025-02-31 are rejected.
     */
    private function isCalendarDate(string $value): bool
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)) {
            return false;
        }

        return checkdate(
            (int) $parts[2],
            (int) $parts[3],
            (int) $parts[1]
        );
    }
}
