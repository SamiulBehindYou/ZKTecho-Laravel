<?php

use App\Http\Controllers\ZKTecoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('zkteco')->group(function () {
    Route::post('/connect', [ZKTecoController::class, 'connect']);
    Route::post('/disconnect', [ZKTecoController::class, 'disconnect']);
    Route::get('/info', [ZKTecoController::class, 'info']);
    Route::get('/users', [ZKTecoController::class, 'users']);
    Route::post('/users', [ZKTecoController::class, 'setUser']);
    Route::delete('/users/{uid}', [ZKTecoController::class, 'removeUser']);
    Route::delete('/users', [ZKTecoController::class, 'clearUsers']);
    Route::get('/attendance', [ZKTecoController::class, 'attendance']);
    Route::delete('/attendance', [ZKTecoController::class, 'clearAttendance']);
    Route::get('/time', [ZKTecoController::class, 'getTime']);
    Route::post('/time', [ZKTecoController::class, 'setTime']);
    Route::post('/restart', [ZKTecoController::class, 'restart']);
    Route::post('/test-voice', [ZKTecoController::class, 'testVoice']);
});
