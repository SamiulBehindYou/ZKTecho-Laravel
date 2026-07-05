<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::resource('devices', DeviceController::class)->except(['show']);
Route::post('devices/{device}/test', [DeviceController::class, 'test'])->name('devices.test');
Route::get('devices/{device}/users', [DeviceController::class, 'users'])->name('devices.users');
Route::post('devices/{device}/sync-users', [DeviceController::class, 'syncUsers'])->name('devices.sync-users');

Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
Route::post('attendance/sync', [AttendanceController::class, 'sync'])->name('attendance.sync');
