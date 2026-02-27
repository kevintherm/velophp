<?php

use App\Infrastructure\Traits\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*')) {
                return (new class
                {
                    use ApiResponse;

                    public function handle($e)
                    {
                        if ($e instanceof ValidationException) {
                            return $this->errorResponse('Validation error', 422, $e->errors());
                        }
                        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                            return $this->errorResponse('Resource not found', 404);
                        }
                        if ($e instanceof AuthenticationException) {
                            return $this->errorResponse('Unauthenticated', 401);
                        }
                        if ($e instanceof AuthorizationException) {
                            return $this->errorResponse('Unauthorized', 403);
                        }
                        if ($e instanceof HttpException) {
                            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
                        }

                        return $this->errorResponse(
                            config('app.debug') ? $e->getMessage() : 'Server Error',
                            500,
                            config('app.debug') ? [
                                'exception' => get_class($e),
                                'file' => $e->getFile(),
                                'line' => $e->getLine(),
                                'trace' => collect($e->getTrace())->take(10),
                            ] : null
                        );
                    }
                })->handle($e);
            }
        });
    })->create();
