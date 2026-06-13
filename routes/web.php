<?php

use App\Http\Controllers\PasskeyController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:6,1')->group(function () {
    Route::get('/passkeys/login/options', [PasskeyController::class, 'loginOptions']);
    Route::post('/passkeys/login', [PasskeyController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user/passkeys', [PasskeyController::class, 'index']);
    Route::get('/user/passkeys/options', [PasskeyController::class, 'registrationOptions']);
    Route::post('/user/passkeys', [PasskeyController::class, 'store']);
    Route::delete('/user/passkeys/{passkey}', [PasskeyController::class, 'destroy']);
});

Route::view('/{path?}', 'app')->where('path', '^(?!api|up|storage).*$');
