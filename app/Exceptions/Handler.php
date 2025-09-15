<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

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
            //
        });
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     */
    protected function unauthenticated($request, \Illuminate\Auth\AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Prefer Filament user panel login if available
        try {
            if (class_exists(\Filament\Facades\Filament::class)) {
                $panel = \Filament\Facades\Filament::getCurrentPanel() ?? \Filament\Facades\Filament::getDefaultPanel();
                if ($panel) {
                    $loginRoute = $panel->getLoginRouteName();
                    if ($loginRoute && \Route::has($loginRoute)) {
                        return redirect()->guest(route($loginRoute));
                    }
                }
            }
        } catch (\Throwable $e) {
            // Fallback below if panel detection fails
        }

        // Fallback to Filament user panel route name if known
        if (\Route::has('filament.user.auth.login')) {
            return redirect()->guest(route('filament.user.auth.login'));
        }

        // Last resort: home page
        return redirect()->guest(url('/'));
    }
}
