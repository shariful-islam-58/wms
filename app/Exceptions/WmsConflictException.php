<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class WmsConflictException extends Exception
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public function __construct(
        string $message,
        public readonly array $errors = [],
        int $code = 409,
    ) {
        parent::__construct($message, $code);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'errors' => $this->errors,
        ], 409);
    }
}
