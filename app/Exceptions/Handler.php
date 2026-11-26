<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        // API clients get a JSON 404, not a redirect.
        if ($exception instanceof NotFoundHttpException && !$request->is('api/*')) {
            // Also reached for records outside a team member's assigned properties and units: say so, without detail.
            $message = 'That page does not exist or is not available to you.';
            if(url()->previous() && url()->previous() !== $request->fullUrl()) {
                return redirect()->back()->with('not_available', $message);
            } else {
                return redirect('/home')->with('not_available', $message);
            }
        }

        // A refusal inside a business page (abort(403, ...)) gets the same "no access" page as the route guard.
        if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface && $exception->getStatusCode() === 403
            && !$request->expectsJson() && !$request->is('api/*') && ($user = $request->user()) && ($user->is_sub_account || $user->hasRole('business'))) {
            return response()->view('pages.business.errors.forbidden', [
                'message' => $exception->getMessage() ?: 'Your role does not allow access to this page.',
                'landing' => app(\App\Services\Access\BusinessAccessResolver::class)->landingUrl($user),
            ], 403);
        }

        return parent::render($request, $exception);
    }
}
