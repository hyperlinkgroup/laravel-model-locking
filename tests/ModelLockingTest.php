<?php

use Illuminate\Database\Schema\Blueprint;
use Hylk\Locking\Providers\ModelLockingServiceProvider;
use Orchestra\Testbench\Concerns\CreatesApplication;

uses(CreatesApplication::class);
beforeEach()->createApplication();

it('can add extend the blueprint for migrations', function () {
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
