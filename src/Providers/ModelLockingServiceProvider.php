<?php

namespace Hylk\Locking\Providers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\ServiceProvider;

class ModelLockingServiceProvider extends ServiceProvider
{
	public function register()
	{
		Blueprint::macro('lockfields', function () {
			$this->foreignIdFor(config('auth.providers.users.model',  User::class), 'locked_by')
				 ->nullable()
				 ->default(null);
			$this->timestamp('locked_at')->nullable();
		});
	}

	public function boot()
	{
		if ($this->app->runningInConsole()) $this->registerCommands();
	}

	protected function registerCommands(): void
	{

	}
}