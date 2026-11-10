<?php

declare(strict_types=1);

use App\Http\Controllers\Api\TokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/tokens', TokenController::class);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', fn (Request $request) => $request->user());
});
