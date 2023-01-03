<?php

namespace Hylk\Locking\Tests\Requests\Api;

use Hylk\Locking\Http\Requests\Api\HeartbeatRequest;
use Hylk\Locking\Tests\TestClasses\Models\TestModel;
use Illuminate\Validation\ValidationException;

it('requires the heartbeats-array', function() {
	$request = new HeartbeatRequest();
	try {
		$request->validate($request->rules());
	} catch(ValidationException $e) {
		$this->assertEquals([
			'heartbeats' => ['The heartbeats field must be present.'],
		], $e->errors());
	}

	$request = new HeartbeatRequest(['heartbeats' => 'test string']);
	try {
		$request->validate($request->rules());
	} catch(ValidationException $e) {
		$this->assertEquals([
			'heartbeats' => ['The heartbeats must be an array.'],
		], $e->errors());
	}

	$requestValues = ['heartbeats' => []];
	$request = new HeartbeatRequest(['heartbeats' => []]);
	expect($request->validate($request->rules()))->toEqual($requestValues);
});

it('validates the heartbeats', function () {
	// base validation
	$requestValues = ['heartbeats' => [[]]];
	$request = new HeartbeatRequest($requestValues);
	try {
		$request->validate($request->rules());
	} catch(ValidationException $e) {
		$this->assertEquals([
			'heartbeats.0.lockable_type' => ['The heartbeats.0.lockable_type field is required.'],
			'heartbeats.0.request_type' => ['The heartbeats.0.request_type field is required.'],
		], $e->errors());
	}

	// non-existing type
	$requestValues = ['heartbeats' => [
		[
			'lockable_type' => TestModel::class,
			'request_type' => 'not existing type',
		],
	]];
	$request = new HeartbeatRequest($requestValues);
	try {
		$request->validate($request->rules());
	} catch(ValidationException $e) {
		$this->assertEquals([
			'heartbeats.0.request_type' => ['The selected heartbeats.0.request_type is invalid.'],
		], $e->errors());
	}

	// request type lock
	$requestValues = ['heartbeats' => [
		[
			'lockable_type' => TestModel::class,
			'request_type' => 'lock',
		],
	]];
	$request = new HeartbeatRequest($requestValues);
	try {
		$request->validate($request->rules());
	} catch(ValidationException $e) {
		$this->assertEquals([
			'heartbeats.0.lockable_id' => ['The heartbeats.0.lockable id field is required.'],
		], $e->errors());
	}

	data_set($requestValues, 'heartbeats.0.lockable_id', 1);
	$request = new HeartbeatRequest($requestValues);
	expect($request->validate($request->rules()))->toEqual($requestValues);

	// request type refresh
	$requestValues = ['heartbeats' => [
		[
			'lockable_type' => TestModel::class,
			'request_type' => 'refresh',
		],
	]];
	$request = new HeartbeatRequest($requestValues);
	try {
		$request->validate($request->rules());
	} catch(ValidationException $e) {
		$this->assertEquals([
			'heartbeats.0.lockable_id' => ['The heartbeats.0.lockable id field is required.'],
			'heartbeats.0.lock_id' => ['The heartbeats.0.lock id field is required.'],
		], $e->errors());
	}

	data_set($requestValues, 'heartbeats.0.lockable_id', 1);
	data_set($requestValues, 'heartbeats.0.lock_id', 1);
	$request = new HeartbeatRequest($requestValues);
	expect($request->validate($request->rules()))->toEqual($requestValues);

	// request type status
	$requestValues = ['heartbeats' => [
		[
			'lockable_type' => TestModel::class,
			'request_type' => 'status',
		],
	]];
	$request = new HeartbeatRequest($requestValues);
	try {
		$request->validate($request->rules());
	} catch(ValidationException $e) {
		$this->assertEquals([
			'heartbeats.0.lockable_ids' => ['The heartbeats.0.lockable ids field must be present.'],
		], $e->errors());
	}

	data_set($requestValues, 'heartbeats.0.lockable_ids', 1);
	$request = new HeartbeatRequest($requestValues);
	try {
		$request->validate($request->rules());
	} catch(ValidationException $e) {
		$this->assertEquals([
			'heartbeats.0.lockable_ids' => ['The heartbeats.0.lockable ids must be an array.'],
		], $e->errors());
	}

	data_set($requestValues, 'heartbeats.0.lockable_ids', []);
	$request = new HeartbeatRequest($requestValues);
	expect($request->validate($request->rules()))->toEqual($requestValues);

	data_set($requestValues, 'heartbeats.0.lockable_ids', []);
	$request = new HeartbeatRequest($requestValues);
	try {
		$request->validate($request->rules());
	} catch(ValidationException $e) {
		$this->assertEquals([
			'heartbeats.0.lockable_ids.0' => ['The heartbeats.0.lockable_ids.0 field is required.'],
		], $e->errors());
	}

	data_set($requestValues, 'heartbeats.0.lockable_ids', ['I\'m not a valid ID']);
	$request = new HeartbeatRequest($requestValues);
	try {
		$request->validate($request->rules());
	} catch(ValidationException $e) {
		$this->assertEquals([
			'heartbeats.0.lockable_ids.0' => ['The heartbeats.0.lockable_ids.0 must be a valid id or uuid.'],
		], $e->errors());
	}

	data_set($requestValues, 'heartbeats.0.lockable_ids', [1,'ab40f9cf-df52-461b-8271-eacd6a399b9a']);
	$request = new HeartbeatRequest($requestValues);
	expect($request->validate($request->rules()))->toEqual($requestValues);
});