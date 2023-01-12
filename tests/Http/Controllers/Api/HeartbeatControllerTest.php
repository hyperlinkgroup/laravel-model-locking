<?php

namespace Hylk\Locking\Tests\Requests\Api;

use Hylk\Locking\Http\Controllers\Api\HeartbeatController;
use Hylk\Locking\Http\Requests\Api\HeartbeatRequest;
use Hylk\Locking\Tests\TestClasses\Models\TestModel;
use Hylk\Locking\Tests\TestClasses\Models\TestModel2;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use function Spatie\PestPluginTestTime\testTime;

it ('can handle a single heartbeat', function () {
	/** @var TestModel $testModel */
	$testModel = $this->getTestModel()->first();

	$requestValues = ['heartbeats' => [
		[
			'lockable_type' => TestModel::class,
			'lockable_id' => $testModel->getKey(),
			'request_type' => 'lock',
		],
	]];

	Auth::setUser($this->getUsers()->first());
	/** @var JsonResponse $response */
	$response = call_user_func(new HeartbeatController(), new HeartbeatRequest($requestValues));
	expect($response->getStatusCode())->toBe(200);
	expect($response->getContent())->toContain(',"locked_by":{"id":1,"name":null,"is_current_user":true}}]}');
	expect($testModel->refresh()->isLocked())->toBeTrue();
});

it ('can handle a multiple heartbeats for a model', function () {
	$requestValues = ['heartbeats' => []];
	$testModels = $this->getTestModel(10)->each(function (TestModel $testModel) use (&$requestValues) {
		$requestValues['heartbeats'][] = [
			'lockable_type' => TestModel::class,
			'lockable_id' => $testModel->getKey(),
			'request_type' => 'lock',
		];
	});

	Auth::setUser($this->getUsers()->first());
	/** @var JsonResponse $response */
	$response = call_user_func(new HeartbeatController(), new HeartbeatRequest($requestValues));
	expect($response->getStatusCode())->toBe(200);
	expect($response->getContent())->toContain(',"locked_by":{"id":1,"name":null,"is_current_user":true}}]}');
	$testModels->each(function (TestModel $testModel) {
		expect($testModel->refresh()->isLocked())->toBeTrue();
	});
});

it ('can unlock models', function () {
	Auth::setUser($this->getUsers()->first());

	$requestValues = ['heartbeats' => []];
	$testModels = $this->getTestModel(10)->each(fn(TestModel $testModel) => $testModel->lock())->each(function (TestModel $testModel) use (&$requestValues) {
		$requestValues['heartbeats'][] = [
			'lockable_type' => TestModel::class,
			'lockable_id' => $testModel->getKey(),
			'request_type' => 'unlock',
		];
	});
	$testModels->each(function (TestModel $testModel) {
		expect($testModel->refresh()->isLocked())->toBeTrue();
	});

	/** @var JsonResponse $response */
	$response = call_user_func(new HeartbeatController(), new HeartbeatRequest($requestValues));
	expect($response->getStatusCode())->toBe(200);
	$testModels->each(function (TestModel $testModel) {
		expect($testModel->refresh()->isLocked())->toBeFalse();
	});
});

it ('can handle different models at once', function () {
	/** @var TestModel $testModel */
	$testModel = $this->getTestModel()->first();
	/** @var TestModel2 $testModel */
	$testModel2 = $this->getTestModel2()->first();

	$requestValues = ['heartbeats' => [
		[
			'lockable_type' => TestModel::class,
			'lockable_id' => $testModel->getKey(),
			'request_type' => 'lock',
		],
		[
			'lockable_type' => TestModel2::class,
			'lockable_id' => $testModel2->getKey(),
			'request_type' => 'lock',
		],
	]];

	Auth::setUser($this->getUsers()->first());

	/** @var JsonResponse $response */
	$response = call_user_func(new HeartbeatController(), new HeartbeatRequest($requestValues));
	expect($response->getStatusCode())->toBe(200);
	expect($response->getContent())->toContain(',"locked_by":{"id":1,"name":null,"is_current_user":true}}]}', json_encode(TestModel::class), json_encode(TestModel2::class));
	expect($testModel->refresh()->isLocked())->toBeTrue();
	expect($testModel2->refresh()->isLocked())->toBeTrue();
});

it ('can refresh a lock', function () {
	Auth::setUser($this->getUsers()->first());
	/** @var TestModel $testModel */
	($testModel = $this->getTestModel()->first())->lock();

	$lockedAt = $testModel->locked_at;
	// advance in time
	testTime()->addSeconds(120);

	$requestValues = ['heartbeats' => [
		[
			'lockable_type' => TestModel::class,
			'lockable_id' => $testModel->getKey(),
			'request_type' => 'refresh',
		]
	]];

	/** @var JsonResponse $response */
	$response = call_user_func(new HeartbeatController(), new HeartbeatRequest($requestValues));
	expect($response->getStatusCode())->toBe(200);

	expect($testModel->refresh()->locked_at->greaterThan($lockedAt))->toBeTrue();
});

it ('can request a lock-state', function () {
	Auth::setUser($this->getUsers()->first());
	/** @var TestModel $testModel */
	($testModel = $this->getTestModel()->first())->lock();

	$requestValues = ['heartbeats' => [
		[
			'lockable_type' => TestModel::class,
			'lockable_id' => $testModel->getKey(),
			'request_type' => 'state',
		],
	]];

	/** @var JsonResponse $response */
	$response = call_user_func(new HeartbeatController(), new HeartbeatRequest($requestValues));
	expect($response->getStatusCode())->toBe(200);
	expect($response->getContent())->toContain(',"locked_by":{"id":1,"name":null,"is_current_user":true}}]}');
});