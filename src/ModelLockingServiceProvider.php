<?php

namespace Hylk\Locking;

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
		if ($this->app->runningInConsole()) {
			$this->registerCommands();
			$this->registerVueComponents();
		}

		$this->registerTranslations();
		$this->registerRoutes();
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
		], 'model-locking-translations');
	}

	protected function registerConfig(): void
	{
		$this->mergeConfigFrom(__DIR__ . '/../../config/model-locking.php', 'model-locking');

		$this->publishes([
			__DIR__ . '/../../config/model-locking.php' => config_path('model-locking'),
		], 'model-locking-config');
	}

	protected function registerVueComponents(): void
	{
		$this->publishes([
			__DIR__ . '/../../resources/js' => resource_path('js/vendor/hylk/laravel-model-locking'),
		], 'model-locking-vue');
	}

	protected function registerRoutes(): void
	{
		$this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
	}
}