> **Warning**
> This Package is still work in progress!

# Laravel Model Locking by [HYPERLINK GROUP](https://github.com/orgs/hyperlinkgroup)

## Installation

You can install the package via composer:

```bash
composer require hyperlinkgroup/laravel-model-locking
```

You can publish the config file with:

```bash
php artisan vendor:publish --provider="Hylk\Locking\ModelLockingServiceProvider" --tag="model-locking-config"
```

You can publish the translation files with:

```bash
php artisan vendor:publish --provider="Hylk\Locking\ModelLockingServiceProvider" --tag="model-locking-translations"
```

You can publish thevue-components via:

```bash
php artisan vendor:publish --provider="Hylk\Locking\ModelLockingServiceProvider" --tag="model-locking-vue"
```

## Usage

### Setting up your models
Within a model just use the `IsLockable`-Trait.

```php
class Post extends Model {
    use \Hylk\ModelLocking\IsLockable;
    
    ...
}
```

Additionally you have to extend the database-tables for the Model.

```php
return new class extends Migration {
    public function up()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->lockfields();
        });
    }
    
    public function down()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropLockfields();
        });
    }
}
```

### simple Model-Locking
If you want just a simple version of the locking, just use the Traits methods within your controller.

```php
class PostController {
    public function show(Post $post) {
      $post->lock();
    }
  
    public function update(Request $request, Post $post) {
      $post->update($request->all());
      $post->unlock();
    }
}
```

To make sure no locks are missed you should use the `locking:release` Artisan command in your scheduler.
Additionally, you should publish the config and set the lock duration to around 15 minutes.

### Model-Locking by heartbeat (Vue)