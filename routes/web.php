<?php

use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\Platform\PlatformController;
use App\Http\Controllers\Platform\SupportInboxController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\Support\SupportController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes
|--------------------------------------------------------------------------
| Sign-in and shared pages live here. The business application is split by
| area under routes/business/ and loaded inside one group, so every business
| route is signed in, past the first-sign-in password change and checked
| against the business access map (config/business_access.php).
*/

// "/" sends signed-in people to their home page and everyone else to sign in.
Route::get('/', [RouteController::class, 'guard']);

// Sign-in with an emailed one-time code. There is no self-registration.
Auth::routes(['register' => false]);
Route::get('auth', [OtpController::class, 'index'])->name('auth');
Route::post('resend', [OtpController::class, 'resendOTP'])->name('resendOTP');
Route::resource('validateOTP', OtpController::class);

// The old default redirect target; kept because existing links point to it.
Route::redirect('home', '/')->name('home');

Route::get('/health', [HealthCheckController::class, 'index']);

// security.txt (RFC 9116) from SECURITY_EMAIL; not served when no contact is configured.
Route::get('.well-known/security.txt', function () {
    $email = config('app.security_email');
    abort_unless($email, 404);
    $body = "Contact: mailto:{$email}\nExpires: " . now()->addYear()->startOfDay()->toIso8601ZuluString() . "\nPreferred-Languages: en\nCanonical: " . url('/.well-known/security.txt') . "\n";
    return response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
});

Route::middleware('auth')->group(function () {
    // Support tickets from the "Create ticket" modal: topics, a new ticket and its files.
    // People see their tickets under /support/tickets (business group, below).
    Route::get('/support', [SupportController::class, 'index'])->name('support.index');
    Route::post('/support', [SupportController::class, 'store'])->middleware('throttle:10,1')->name('support.store');
    Route::post('/support-upload-files', [SupportController::class, 'uploadFiles'])->middleware('throttle:30,1')->name('support.upload');

    // Required password change for accounts created with a temporary password.
    Route::get('/password/change', [ChangePasswordController::class, 'show'])->name('password.change');
    Route::post('/password/change', [ChangePasswordController::class, 'update'])->name('password.change.update');
});

// Platform administration (the "admin" role; 404 for everyone else): staff, roles, modules and who answers support.
Route::group(['middleware' => ['auth', 'password.changed', 'platform.admin'], 'prefix' => 'platform', 'as' => 'platform.'], function () {
    Route::get('/', [PlatformController::class, 'index'])->name('index');
    Route::post('/modules', [PlatformController::class, 'module'])->name('modules');
    Route::post('/roles', [PlatformController::class, 'seedRoles'])->name('roles');
    Route::post('/people/{user}/restore', [PlatformController::class, 'restorePerson'])->whereNumber('user')->name('people.restore');
    Route::post('/people/{user}/support', [PlatformController::class, 'supportAgent'])->whereNumber('user')->name('people.support');
});

// Support inbox: every ticket posted on this installation. Platform administrators and support agents
// (people an administrator lets answer tickets) only; 404 for everyone else.
Route::group(['middleware' => ['auth', 'password.changed', 'support.agent'], 'prefix' => 'platform/support', 'as' => 'platform.support.'], function () {
    Route::get('/', [SupportInboxController::class, 'index'])->name('index');
    Route::get('/{ticket:reference}', [SupportInboxController::class, 'show'])->name('show');
    Route::post('/{ticket:reference}/reply', [SupportInboxController::class, 'reply'])->name('reply');
    Route::post('/{ticket:reference}/update', [SupportInboxController::class, 'update'])->name('update');
    Route::get('/{ticket:reference}/files/{attachment}', [SupportInboxController::class, 'file'])->whereNumber('attachment')->name('file');
});

// The business application, one file per area.
Route::group(['middleware' => ['role:business', 'auth', 'password.changed', 'business.access']], function () {
    foreach (['dashboard', 'account', 'team', 'properties', 'listings', 'tenancies', 'agents', 'crm', 'maintenance', 'sales', 'expenses', 'legal', 'reports'] as $area) {
        require __DIR__ . "/business/{$area}.php";
    }
});
