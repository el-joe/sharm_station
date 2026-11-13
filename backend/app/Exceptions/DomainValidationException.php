<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A business-rule violation that maps to a 422 response (JSON for the API, redirect with errors for the admin).
 */
class DomainValidationException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(string $message, protected array $errors = [])
    {
        parent::__construct($message);
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function withErrors(array $errors): static
    {
        $first = collect($errors)->flatten()->first() ?? 'The given data was invalid.';

        return new static((string) $first, $errors);
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $this->getMessage(), 'errors' => $this->errors], 422);
        }

        return back()->withErrors($this->errors)->withInput();
    }
}
