<?php

use Illuminate\Support\Facades\Route;
use Hylk\Locking\Http\Controllers\Api\HeartbeatController;

Route::group([
	'prefix' => 'api/locking',
	'as' => 'api.locking.',
	'middleware' => config('model-locking.middleware', ['api'])
], function () {
	Route::post('heartbeat', HeartbeatController::class)->name('heartbeat');
});