<?php

namespace Hylk\Locking\Tests;

use Hylk\Locking\Tests\TestClasses\Models\TestModel;
use Hylk\Locking\Tests\TestClasses\Models\TestModel2;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
	private function createTestModelTables(): void
	{
		if (!Schema::hasTable('test_models')) {
			Schema::create('test_models', function (Blueprint $table) {
				$table->id();
				$table->string('name');
				$table->lockfields();
			});
		}
		if (!Schema::hasTable('test_models2')) {
			Schema::create('test_models2', function (Blueprint $table) {
				$table->id();
				$table->string('name');
				$table->lockfields();
			});
		}
	}

	/**
	 * getTestModel
	 *
	 * @param int $number
	 *
	 * @return Collection
	 */
	public function getTestModel(int $number = 1): Collection
	{
		$this->createTestModelTables();

		$models = new Collection();
		for ($i = 1; $i <= $number; $i++) {
			$models->add(TestModel::create(['name' => 'Test']));
		}

		return $models;
	}

	/**
	 * getTestModel
	 *
	 * @param int $number
	 *
	 * @return Collection
	 */
	public function getTestModel2(int $number = 1): Collection
	{
		$this->createTestModelTables();

		$models = new Collection();
		for ($i = 1; $i <= $number; $i++) {
			$models->add(TestModel2::create(['name' => 'Test']));
		}

		return $models;
	}

	/**
	 * getUsers
	 *
	 * @param int $number
	 *
	 * @return Collection
	 */
	public function getUsers(int $number = 1): Collection
	{
		Schema::create('users', function (Blueprint $table) {
			$table->id();
			$table->string('name')->nullable();
			$table->timestamps();
		});

		$users = new Collection();
		for ($i = 1; $i <= $number; $i++) {
			$user = new (config('auth.providers.users.model',  User::class))();
			$user->name = 'TestUser';
			$user->save();
			$users->add($user);
		}

		return $users;
	}
}