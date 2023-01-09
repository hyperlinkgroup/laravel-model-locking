> **Warning**
> This Package is still work in progress!

# Laravel Model Locking by [HYPERLINK GROUP](https://github.com/orgs/hyperlinkgroup)

## Installation

You can install the package via composer:

```bash
composer require hylk/laravel-model-locking
```

You can publish the config file with:

```bash
php artisan vendor:publish --provider="Hylk\Locking\ModelLockingServiceProvider" --tag="model-locking-config"
```

You can publish the translation files with:

```bash
php artisan vendor:publish --provider="Hylk\Locking\ModelLockingServiceProvider" --tag="model-locking-translations"
```

You can publish the vue-components via:

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
If you want just a simple version of the locking, just use the Traits methods within your controller. `HEARTBEAT_LOCK_DURATION` should be set to something like 15 minutes (900 seconds).

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
The more advanced approach is to handle the locks via a heartbeat. This only works for `Vue` and `axios`.

1. Publish the vue-components
2. register the global HeartbeatManager
    ```javascript
   import Vue from 'vue';
   import HeartbeatManager from './vendor/hylk/laravel-model-locking/heartbeat-manager';
   ...
   window.axios = require('axios');
   ...
   Vue.use(HeartbeatManager);
    ```
3. register the Listener-Components
   1. for index-pages
    ```HTML
   <template>
        <div>
            <HeartbeatListener model-class="App\Models\Post"
                :model-id="model_id"
                @locked="setLockState"
                @unlocked="deleteLockState" />
            ...
        </div>
   </template>
    ```
   Handle the wished behavior like showing the current locker by the `lock` event and delete this information on the `unlock`-event.
   ```HTML
   ...
      <span v-if="isLocked(model_id)">Locked by {{ getLock(model_id).locked_by.name }}</span>
   ...
   ```
4. register the LockRefresher on your Edit-form.
   ```HTML
   <template>
      <div>
         <HeartbeatLockRefresher model-class="App\Models\Post"
             :model-id="model_id"
             @lost="reloadRoute()" />
         ...
      </div>
    </template>
   ```
   The `lost`-Event shows if the component tries to render if the model is locked by another user than the logged in.

### Environment variables

| Variable | Default | Description |
| --- | --- | --- |
| `HEARTBEAT_LOCK_DURATION` | 70 | The time in seconds a model is locked. |
| `MIX_HEARTBEAT_REFRESH` | 60 | The time in seconds between the heartbeats. Should be a multiple of the `MIX_HEARTBEAT_STATUS`-interval. |
| `MIX_HEARTBEAT_STATUS` | 30 | The time in seconds between the heartbeats for status-request (index-Listener). |

### config
Beside the environment variables, there is a `middleware` key to determine the middleware(s) used by the heartbeat-route. Default it's set to `api`.