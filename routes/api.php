<?php

use Illuminate\Support\Facades\Route;
use Hylk\Locking\Http\Api\Controllers\HeartbeatController;

Route::group(['prefix' => 'locking', 'as' => 'locking.'], function () {
	Route::get('heartbeat', HeartbeatController::class)->name('heartbeat');
});