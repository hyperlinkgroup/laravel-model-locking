<?php

namespace Hylk\Locking\Tests\Console\Commands;

use Hylk\Locking\Console\Commands\ReleaseExpiredLocks;
use Hylk\Locking\ModelLockingServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use function Spatie\PestPluginTestTime\testTime;

function mockAppPathToFetchTestModel(): void
{
	$appMock = App::partialMock()
				  ->shouldReceive('getNamespace')
				  ->andReturn('Hylk\\Locking\\Tests\\TestClasses\\');
	Container::setInstance($appMock->getMock());
	app()->useAppPath(__DIR__ . '/../../TestClasses');
}

it('can find models', function () {
	$command = new ReleaseExpiredLocks();
	$commandReflection = new \ReflectionClass($command);
	mockAppPathToFetchTestModel();

	expect($commandReflection->getMethod('getModels')->invoke($command)->toArray())
		->toEqual([
			'\Hylk\Locking\Tests\TestClasses\Models\TestModel',
			'\Hylk\Locking\Tests\TestClasses\Models\TestModel2',
		]);
});

it('does not expire valid locks', function () {
	app()->register(ModelLockingServiceProvider::class);
	app()->useAppPath(__DIR__ . '/../../TestClasses');
	app()->setBasePath(__DIR__ . '/../../TestClasses');

	$testModel = $this->getTestModel()->first();
	Auth::setUser($this->getUsers()->first());
	$testModel->lock();
	$this->artisan('locking:release -p ' . __DIR__ . '/../../TestClasses')
		 ->assertExitCode(Command::SUCCESS);

	$testModel->refresh();
	expect($testModel->isLocked())->toBeTrue();
});

it('does release expired locks', function () {
	app()->register(ModelLockingServiceProvider::class);
	app()->useAppPath(__DIR__ . '/../../TestClasses');
	app()->setBasePath(__DIR__ . '/../../TestClasses');

	$testModel = $this->getTestModel()->first();
	Auth::setUser($this->getUsers()->first());
	$testModel->lock();
	// Advance the time behind the expired date
	testTime()->addSeconds(config('model-locking.lock_duration') + 1);

	$this->artisan('locking:release -p ' . __DIR__ . '/../../TestClasses')
		 ->assertExitCode(Command::SUCCESS);

	$testModel->refresh();
	expect($testModel->isLocked())->toBeFalse();
});