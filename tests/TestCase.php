<?php

namespace Hylk\Locking\Tests;

use Hylk\Locking\Tests\TestClasses\Models\TestModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
	/**
	 * getTestModel
	 *
	 * @return mixed
	 */
	public function getTestModel()
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
	public function getUsers(int $number = 1): Collection
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
}