<?php

namespace Hylk\Locking\Providers;

use Hylk\Locking\Console\Commands\ReleaseExpiredLocks;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\ServiceProvider;

class ModelLockingServiceProvider extends ServiceProvider
{
	public function register()
	{
		$this->registerBlueprintMacro();
		$this->registerConfig();
	}

	public function boot()
	{
		if ($this->app->runningInConsole()) $this->registerCommands();

		$this->registerTranslations();
	}

	protected function registerCommands(): void
	{
		$this->commands([
			ReleaseExpiredLocks::class,
		]);
	}

	protected function registerBlueprintMacro(): void
	{
		Blueprint::macro('lockfields', function () {
			$this->foreignIdFor(config('auth.providers.users.model', User::class), 'locked_by')
				 ->nullable()
				 ->default(null);
			$this->timestamp('locked_at')->nullable();
		});

		Blueprint::macro('dropLockfields', function () {
			$this->dropColumn('locked_by', 'locked_at');
		});
	}

	protected function registerTranslations(): void
	{
		$this->loadTranslationsFrom(__DIR__ . '/../lang', 'model-locking');

		$this->publishes([
			__DIR__ . '/../lang' => $this->app->langPath('vendor/model-locking'),
		]);
	}

	protected function registerConfig(): void
	{
		$this->mergeConfigFrom(__DIR__ . '/../../config/model-locking.php', 'model-locking');

		$this->publishes([
			__DIR__ . '/../../config/model-locking.php' => 'model-locking-config',
		]);
	}
}