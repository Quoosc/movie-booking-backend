<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Support\Facades\Log;
use App\Exceptions\{CustomException, ResourceNotFoundException, SeatLockedException, LockExpiredException, MaxSeatsExceededException};

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            Log::error('Exception occurred', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        });
    }

    public function render($request, Throwable $e)
    {
        if ($e instanceof CustomException) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }

        if ($e instanceof ResourceNotFoundException) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        }

        if ($e instanceof SeatLockedException) {
            return response()->json([
                'message' => $e->getMessage(),
                'unavailableSeats' => $e->getUnavailableSeats(),
            ], $e->getCode() ?: 409);
        }

        if ($e instanceof LockExpiredException) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 410);
        }

        if ($e instanceof MaxSeatsExceededException) {
            return response()->json([
                'message' => $e->getMessage(),
                'maxSeats' => $e->maxSeats,
                'requestedSeats' => $e->requestedSeats,
            ], 400);
        }

        return parent::render($request, $e);
    }
}
