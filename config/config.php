<?php

return [
	/*
	|--------------------------------------------------------------------------
	| Default Settings Store
	|--------------------------------------------------------------------------
	|
	| This option controls the default settings store that gets used while
	| using this settings library.
	|
	| Supported: "json", "database", "model"
	|
	*/
	'store' => 'model',

	/*
	|--------------------------------------------------------------------------
	| JSON Store
	|--------------------------------------------------------------------------
	|
	| If the store is set to "json", settings are stored in the defined
	| file path in JSON format. Use full path to file.
	|
	*/
	'path' => storage_path().'/settings.json',

	/*
	|--------------------------------------------------------------------------
	| Database Store
	|--------------------------------------------------------------------------
	|
	| If the store is set to "database", settings are stored in the defined
	| database table on the given connection.
	|
	*/
	'connection' => null, // If set to null, the default connection will be used.
	'table' => 'settings', // Name of the table used.
	'keyColumn' => 'key',
	'valueColumn' => 'value',

	/*
	|--------------------------------------------------------------------------
	| Model Store
	|--------------------------------------------------------------------------
	|
	| Model settings are stored in the defined table.
	|
	*/
	'model' => env('SETTINGS_MODEL', \Flamix\Settings\Models\Settings::class),

	/*
	|--------------------------------------------------------------------------
	| Cache settings
	|--------------------------------------------------------------------------
	|
	| If you want all setting calls to go through Laravel's cache system.
	|
	*/
	'enableCache' => false, // Use cache
	'forgetCacheByWrite' => true, // Whether to reset the cache when changing a setting.
	'cacheTtl' => 3600, // TTL in seconds.

	/*
	|--------------------------------------------------------------------------
	| Default Settings
	|--------------------------------------------------------------------------
	|
	| Define all default settings that will be used before any settings are set,
	| this avoids all settings being set to false to begin with and avoids
	| hardcoding the same defaults in all 'Settings::get()' calls
	|
	*/
	'defaults' => [],
];
