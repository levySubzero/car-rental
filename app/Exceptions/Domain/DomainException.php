<?php

namespace App\Exceptions\Domain;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

/**
 * A business rule was broken. Services throw these; the user sees the
 * message as an error toast on the page they came from.
 */
abstract class DomainException extends RuntimeException
{
    public function render(Request $request): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() && ! $request->inertia()) {
            $field = $this->errorField();

            return response()->json(array_filter([
                'message' => $this->getMessage(),
                // Shaped like a validation error, so useHttp shows it on the field.
                'errors' => $field !== null ? [$field => [$this->getMessage()]] : null,
            ]), 422);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $this->getMessage()]);

        return back();
    }

    /**
     * The input field a JSON client should show the message on, if any.
     */
    protected function errorField(): ?string
    {
        return null;
    }

    /**
     * Business rule violations are expected; they are not reported to Sentry.
     */
    public function report(): bool
    {
        return true;
    }
}
