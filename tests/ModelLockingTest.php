<?php

namespace Hylk\Locking\Tests;

use Hylk\Locking\Exceptions\InvalidUserException;
use Hylk\Locking\Tests\TestClasses\TestModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Hylk\Locking\Providers\ModelLockingServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\Concerns\CreatesApplication;
use function Pest\Laravel\actingAs as actingAs;

uses(CreatesApplication::class);
beforeEach()->createApplication();

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

/**
 * getTestModel
 *
 * @return mixed
 */
function getTestModel()
{
	Schema::create('test_models', function (Blueprint $table) {
		$table->id();
		$table->string('name');
		$table->lockfields();
	});

	$testModel = TestModel::create([
		'name' => 'Test',
	]);

	return $testModel;
}

/**
 * getUsers
 *
 * @param int $number
 *
 * @return Collection
 */
function getUsers(int $number = 1): Collection
{
	Schema::create('users', function (Blueprint $table) {
		$table->id();
		$table->timestamps();
	});

	$users = new Collection();
	for ($i = 1; $i <= $number; $i++) {
		$users->add((config('auth.providers.users.model',  User::class))::create());
	}

	return $users;
}

it('can lock and unlock a model by a given user', function() {
	$testModel = getTestModel();
	expect($testModel->toArray())->toEqual([
		'id' => 1,
		'name' => 'Test',
		'locked_by' => null,
		'locked_at' => null,
	]);
	$user = getUsers()->first();
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
	$testModel = getTestModel();

	Auth::setUser(getUsers()->first());
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
	$testModel = getTestModel();
	$users = getUsers(2);

	$testModel->lock($users->first());
	$testModel->unlock($users->last());
})->throws(InvalidUserException::class, 'The model is locked by another user.');

it('can force unlock a model locked by a different user', function() {
	$testModel = getTestModel();
	$user = getUsers()->first();

	$testModel->lock($user);
	$testModel->unlockForced();
	expect($testModel->locked_by)->toBeNull();
	expect($testModel->locked_at)->toBeNull();
});
