<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::resource('devices', DeviceController::class)->except(['show']);
Route::post('devices/{device}/test', [DeviceController::class, 'test'])->name('devices.test');
Route::get('devices/{device}/users', [DeviceController::class, 'users'])->name('devices.users');
Route::post('devices/{device}/sync-users', [DeviceController::class, 'syncUsers'])->name('devices.sync-users');

Route::get('users', [UserController::class, 'index'])->name('users.index');
Route::put('users/bulk', [UserController::class, 'bulkUpdate'])->name('users.bulk-update');
Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');

Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
Route::get('attendance/create', [AttendanceController::class, 'create'])->name('attendance.create');
Route::post('attendance', [AttendanceController::class, 'store'])->name('attendance.store');
Route::post('attendance/sync', [AttendanceController::class, 'sync'])->name('attendance.sync');

Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
Route::post('settings/test', [SettingsController::class, 'test'])->name('settings.test');
Route::post('settings/push-now', [SettingsController::class, 'pushNow'])->name('settings.push-now');
