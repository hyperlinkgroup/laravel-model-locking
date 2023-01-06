<?php

namespace Hylk\Locking\Tests;

use Carbon\Carbon;
use Hylk\Locking\Exceptions\InvalidUserException;
use Illuminate\Database\Schema\Blueprint;
use Hylk\Locking\ModelLockingServiceProvider;
use Illuminate\Support\Facades\Auth;
use function Spatie\PestPluginTestTime\testTime;

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
	app()->register(ModelLockingServiceProvider::class);
	$testModel = $this->getTestModel()->first();
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
	app()->register(ModelLockingServiceProvider::class);
	$testModel = $this->getTestModel()->first();

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

it('can refresh a lock', function() {
	app()->register(ModelLockingServiceProvider::class);
	$testModel = $this->getTestModel()->first();

	Auth::setUser($this->getUsers()->first());
	$testModel->lock();
	expect($testModel->isLocked())->toBeTrue();
	/** @var Carbon $lockedAtFrist */
	$lockedAtFrist = $testModel->locked_at;

	// advance in time
	testTime()->addSeconds(10);

	$testModel->lock();
	/** @var Carbon $lockedAtRefreshed */
	$lockedAtRefreshed = $testModel->locked_at;
	expect($lockedAtFrist->isBefore($lockedAtRefreshed))->toBeTrue();
});

it('cannot unlock a model locked by different user', function() {
	app()->register(ModelLockingServiceProvider::class);
	$testModel = $this->getTestModel()->first();
	$users = $this->getUsers(2);

	$testModel->lock($users->first());
	$testModel->unlock($users->last());
})->throws(InvalidUserException::class, 'The model is locked by another user.');

it('can force unlock a model locked by a different user', function() {
	app()->register(ModelLockingServiceProvider::class);
	$testModel = $this->getTestModel()->first();
	$user = $this->getUsers()->first();

	$testModel->lock($user);
	$testModel->unlockForced();
	expect($testModel->locked_by)->toBeNull();
	expect($testModel->locked_at)->toBeNull();
});

it ('can unlock a model with an expired lock automatically', function () {
	app()->register(ModelLockingServiceProvider::class);
	$testModel = $this->getTestModel()->first();
	$testModel->lock($this->getUsers()->first());

	$originalPropertyReflection = (new \ReflectionClass($testModel))->getProperty('original');
	$originalPropertyReflection->setAccessible(true);

	expect($testModel->isLocked())->toBeTrue();
	$lockedAtValue = data_get($originalPropertyReflection->getValue($testModel->refresh()), 'locked_at');
	expect($lockedAtValue)->not()->toBeNull();

	// advance in time
	testTime()->addSeconds(config('model-locking.lock_duration') + 1);

	expect($testModel->isLocked())->toBeFalse();
	$lockedAtValue = data_get($originalPropertyReflection->getValue($testModel->refresh()), 'locked_at');
	expect($lockedAtValue)->not()->toBeNull(); // the new value is not yet saved
});

it ('can unlock a model with an expired lock automatically and save this to the database', function () {
	app()->register(ModelLockingServiceProvider::class);
	$testModel = $this->getTestModel()->first();
	$testModel->lock($this->getUsers()->first());

	$originalPropertyReflection = (new \ReflectionClass($testModel))->getProperty('original');
	$originalPropertyReflection->setAccessible(true);

	expect($testModel->isLocked())->toBeTrue();
	$lockedAtValue = data_get($originalPropertyReflection->getValue($testModel->refresh()), 'locked_at');
	expect($lockedAtValue)->not()->toBeNull();

	// advance in time
	testTime()->addSeconds(config('model-locking.lock_duration') + 1);

	expect($testModel->isLocked(true))->toBeFalse();
	$lockedAtValue = data_get($originalPropertyReflection->getValue($testModel->refresh()), 'locked_at');
	expect($lockedAtValue)->toBeNull(); // the new value is saved
});
