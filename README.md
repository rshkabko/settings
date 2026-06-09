# Laravel Settings

Persistent, application-wide settings for Laravel.

Despite the package name, this package should work with Laravel 8+ (though some versions are not automatically tested).

## Installation

1. `composer require flamix/settings`
2. Publish the config file by running `php artisan vendor:publish --provider="Flamix\Settings\ServiceProvider" --tag="config"`. The config file will give you control over which storage engine to use as well as some storage-specific settings.

## Usage

You can either access the setting store via its facade or inject it by type-hinting towards the abstract class `Flamix\Settings\SettingStore`.

```php
<?php
Setting::set('foo', 'bar');
Setting::get('foo', 'default value');
Setting::get('nested.element');
Setting::forget('foo'); // Do not forget save() after this!!
$settings = Setting::all();
?>
```

Call `Setting::save()` explicitly to save changes made.

You could also use the `setting()` helper:

```php
// Get the store instance
setting();

// Get values
setting('foo');
setting('foo.bar');
setting('foo', 'default value');
setting()->get('foo');

// Set values
setting(['foo' => 'bar']);
setting(['foo.bar' => 'baz']);
setting()->set('foo', 'bar');

// Method chaining
setting(['foo' => 'bar'])->save();
```

### Store cache

When reading from the store, you can enable the cache.

You can also configure flushing of the cache when writing and configure time to live.

Reading will come from the store, and then from the cache, this can reduce load on the store.

```php
// Cache usage configurations.
'enableCache' => false,
'forgetCacheByWrite' => true,
'cacheTtl' => 3600,
```

### JSON storage

You can modify the path used on run-time using `Setting::setPath($path)`.

### Database storage

#### Using Migration File

The package migration is loaded automatically — just run `php artisan migrate`. It creates the settings table (`key`/`value` columns by default, names are configurable). Scope columns for `setExtraColumns()` (e.g. `user_id`) are not part of this package — the consumer that introduces the scope ships its own migration (in Flamix projects that's `ui-saas-user`).

#### Example

For example, if you want to store settings for multiple users/clients in the same database you can do so by specifying extra columns:

```php
<?php
Setting::setExtraColumns([
	'user_id' => Auth::id(),
]);
?>
```

`where user_id = x` will now be added to the database query when settings are retrieved, and when new settings are saved, the `user_id` will be populated.

If you need more fine-tuned control over which data gets queried, you can use the `setConstraint` method which takes a closure with two arguments:

- `$query` is the query builder instance
- `$insert` is a boolean telling you whether the query is an insert or not. If it is an insert, you usually don't need to do anything to `$query`.

```php
<?php
Setting::setConstraint(function($query, $insert) {
	if ($insert) return;
	$query->where(/* ... */);
});
?>
```

### Model storage (default)

The default store is `model`. It works like the database store, but reads and writes through an Eloquent model instead of a raw connection, so the model defines the table and connection. The model is configured via the `SETTINGS_MODEL` env variable or the `model` config key (defaults to `Flamix\Settings\Models\Settings`).

Scoping works the same way as with the database store:

```php
<?php
// Per-user settings
Setting::setExtraColumns(['user_id' => Auth::id()]);
Setting::set('date_format', 'd.m.Y');
Setting::save();
?>
```

Note: `setExtraColumns()` resets the loaded state, so the next read re-queries the store within the new scope. The table needs the extra columns and a composite unique index, e.g. `(key, user_id)`.

### Custom stores

This package uses the Laravel `Manager` class under the hood, so it's easy to add your own custom session store driver if you want to store in some other way. All you need to do is extend the abstract `SettingStore` class, implement the abstract methods and call `Setting::extend`.

```php
<?php
class MyStore extends Flamix\Settings\SettingStore {
	// ...
}
Setting::extend('mystore', function($app) {
	return $app->make('MyStore');
});
?>
```

## TODO / Known issues

Prioritized backlog (P0 = most urgent).

### P0 — data-loss risks

- [ ] `save()` does not call `load()`: after `forgetAll()` (called directly or implicitly by `setExtraColumns()`) a `save()` writes an empty diff and **deletes all rows in the current scope**. `save()` must force-load before diffing, and `forgetAll()` should not mark the store as unsaved (reads currently set `unsaved = true` via `setExtraColumns()`).
- [ ] No upsert: `write()` is a read-modify-write (pluck → update → insert → delete in separate queries). Concurrent requests cause lost updates or unique constraint violations on `(key, user_id)`. Switch to `upsert()` (available since Laravel 8, which is already the minimum).
- [ ] `set($key, null)` silently deletes the key: `isset()` in the write-diff treats null as absent, and nulls are stripped from inserts ("Remove unsupported values"). This is undocumented and inconsistent with `get()`. Define explicit null semantics.

### P1 — bugs & infrastructure

- [ ] `ModelSettingStore::parseReadData()` references undefined `$this->valueColumn` in the `is_array($row)` branch — latent bug, currently masked because Eloquent always returns objects.
- [ ] Tests are broken: they reference the pre-`Storages\` namespace, `composer.json` has no `require-dev` (no PHPUnit/Mockery), and `phpunit.xml` targets PHPUnit ≤ 9. Restore the suite (Orchestra Testbench, real DB) and add CI.
- [x] `ServiceProvider::$defer = true` was dead code since Laravel 5.8 — dropped `$defer`/`provides()`, the provider is intentionally eager: deferral is incompatible with boot-time side effects (`loadMigrationsFrom()`, `publishes()`) and saves nothing here.
- [x] Migrations were both auto-loaded (`loadMigrationsFrom()`) and publishable — publishing is removed, auto-load only. Scope columns (e.g. `user_id`) intentionally ship with the consumer package (`ui-saas-user`), not here — the settings package stays generic key-value.
- [ ] Cache vs scoped reads: `cacheKey()` concatenates extra-column **values** only (scopes with different column names but same values collide), and every scoped read goes through `setExtraColumns()` → full reload, so enabling `enableCache` produces multiple cache round-trips per read.

### P2 — architecture

- [ ] `DatabaseSettingStore` and `ModelSettingStore` are ~85% copy-paste (`write`, `forget`, `prepareInsertData`, `parseReadData`) — extract the shared diff/persist logic (inheritance or trait) so fixes land once.
- [ ] No value serialization: everything is stored as plain text. Arrays survive only via dot-flattening, `false` becomes `''`, empty arrays and nulls cannot be stored, types are lost on read. JSON-encode values.
- [ ] No request-level memoization for scoped reads — consumer helpers that fall back from a user scope to the global scope pay two queries per read when the cache is disabled.
- [ ] `JsonSettingStore`: constructor side effect (creates the file inside `setPath()`), non-atomic writes without locks.
- [x] `DatabaseSettingStore::write()` carried the Laravel < 5.3 `lists`/`pluck` fallback — replaced with a direct `pluck()` call (the package requires illuminate >= 8, where `lists()` never exists).
