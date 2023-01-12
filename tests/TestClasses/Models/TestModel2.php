<?php

namespace Hylk\Locking\Tests\TestClasses\Models;

use Hylk\Locking\Models\Concerns\IsLockable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 */
class TestModel2 extends Model
{
	use IsLockable;

	protected $table = 'test_models2';

	public $timestamps = false;

	protected $fillable = [
		'name',
	];
}