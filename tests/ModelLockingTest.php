<?php

namespace Hylk\Locking\Tests;

use Hylk\Locking\Exceptions\InvalidUserException;
use Illuminate\Database\Schema\Blueprint;
use Hylk\Locking\Providers\ModelLockingServiceProvider;
use Illuminate\Support\Facades\Auth;

it('can extend the blueprint for migrations', function () {
	app()->register(ModelLockingServiceProvider::class);

	$blueprint = new Blueprint('test');
	$blueprint->lockfields();
	expect($blueprint->getAddedColumns())->toHaveCount(2);
	expect($blueprint->getAddedColumns()[0]->toArray())->toEqual([
		'type' => 'bigInteger',
		'name' => 'locked_by',
		'autoIncrement' => false,
		'unsigned' => true,
		'nullable' => true,
		'default' => null,
	]);
	expect($blueprint->getAddedColumns()[1]->toArray())->toEqual([
		'type' => 'timestamp',
		'name' => 'locked_at',
		'precision' => 0,
		'nullable' => true,
	]);
});

it('can load the config-file', function () {
	app()->register(ModelLockingServiceProvider::class);

	expect(config('model-locking'))->toEqual([
		'lock_duration' => 70,
		'intervals' => [
			'heartbeat_refresh' => 60,
			'heartbeat_status' => 15,
		],
	]);
});

it('can lock and unlock a model by a given user', function() {
	$testModel = $this->getTestModel();
	expect($testModel->toArray())->toEqual([
		'id' => 1,
		'name' => 'Test',
		'locked_by' => null,
		'locked_at' => null,
	]);
	$user = $this->getUsers()->first();
	$testModel->lock($user);
	expect($testModel->isLocked())->toBeTrue();
	expect($testModel->isUnlocked())->toBeFalse();
	expect($testModel->is_locked)->toBeTrue();
	expect($testModel->is_unlocked)->toBeFalse();
	expect($testModel->locked_by)->toEqual($user->id);
	expect($testModel->locked_at?->unix())->toBeNumeric();

	$testModel->unlock($user);
	expect($testModel->locked_by)->toBeNull();
	expect($testModel->locked_at)->toBeNull();
});

it('can lock and unlock a model by the current user', function() {
	$testModel = $this->getTestModel();

	Auth::setUser($this->getUsers()->first());
	$testModel->lock();
	expect($testModel->isLocked())->toBeTrue();
	expect($testModel->isUnlocked())->toBeFalse();
	expect($testModel->is_locked)->toBeTrue();
	expect($testModel->is_unlocked)->toBeFalse();
	expect($testModel->locked_by)->toEqual(1);
	expect($testModel->locked_at?->unix())->toBeNumeric();

	$testModel->unlock();
	expect($testModel->locked_by)->toBeNull();
	expect($testModel->locked_at)->toBeNull();
});

it('cannot unlock a model locked by different user', function() {
	$testModel = $this->getTestModel();
	$users = $this->getUsers(2);

	$testModel->lock($users->first());
	$testModel->unlock($users->last());
})->throws(InvalidUserException::class, 'The model is locked by another user.');

it('can force unlock a model locked by a different user', function() {
	$testModel = $this->getTestModel();
	$user = $this->getUsers()->first();

	$testModel->lock($user);
	$testModel->unlockForced();
	expect($testModel->locked_by)->toBeNull();
	expect($testModel->locked_at)->toBeNull();
});
