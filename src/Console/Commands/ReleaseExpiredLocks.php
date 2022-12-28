<?php

namespace Hylk\Locking\Console\Commands;

use Hylk\Locking\Models\Concerns\IsLockable;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class ReleaseExpiredLocks extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'locking:release {--p|path=}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'This command is releasing expired locks.';

	/**
	 * Execute the console command.
	 *
	 * @return int
	 */
	public function handle()
	{
		$modelClasses = $this->getModels($this->option('path'));
		$this->info('Found ' . $modelClasses->count() . ' models.');
		foreach($modelClasses as $modelClass) {
			$modelClass::whereNotNull('locked_by')
					   ->where('locked_at', '<', now()->subSeconds(config('locking.lock_expiration')))
					   ->update(['locked_by' => null, 'locked_at' => null]);
			$this->info('Released expired locks for ' . $modelClass);
		}

		return Command::SUCCESS;
	}

	/**
	 * Retrieves all models that are using the IsLockable trait.
	 * Extended from {@link https://stackoverflow.com/a/60310985}
	 *
	 * @param string|null $path
	 *
	 * @return Collection
	 */
	function getModels(?string $path = null): Collection
	{
		$path ??= app_path();

		$models = collect(File::allFiles($path))
			->map(function ($item) {
				$path = $item->getRelativePathName();

				return sprintf('\%s%s',
					Container::getInstance()->getNamespace(),
					strtr(substr($path, 0, strrpos($path, '.')), '/', '\\'));
			})
			->filter(function ($class) {
				$valid = false;

				if (class_exists($class)) {
					$reflection = new \ReflectionClass($class);
					$valid = $reflection->isSubclassOf(Model::class)
						&& array_key_exists(IsLockable::class, $reflection->getTraits())
						&& !$reflection->isAbstract();
				}

				return $valid;
			});

		return $models->values();
	}
}