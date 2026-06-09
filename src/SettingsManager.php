<?php

namespace Flamix\Settings;

use Illuminate\Support\Manager;
use Flamix\Settings\Storages\JsonSettingStore;
use Flamix\Settings\Storages\DatabaseSettingStore;
use Flamix\Settings\Storages\ModelSettingStore;
use Flamix\Settings\Storages\MemorySettingStore;

class SettingsManager extends Manager
{
	public function getDefaultDriver()
	{
		return $this->getConfig('settings.store');
	}

	public function createJsonDriver()
	{
		$path = $this->getConfig('settings.path');

		$store = new JsonSettingStore($this->getSupportedContainer()['files'], $path);

		return $this->wrapDriver($store);
	}

	public function createDatabaseDriver()
	{
		$connectionName = $this->getConfig('settings.connection');
		$connection = $this->getSupportedContainer()['db']->connection($connectionName);
		$table = $this->getConfig('settings.table');
		$keyColumn = $this->getConfig('settings.keyColumn');
		$valueColumn = $this->getConfig('settings.valueColumn');

		$store = new DatabaseSettingStore($connection, $table, $keyColumn, $valueColumn);

		return $this->wrapDriver($store);
	}

	public function createModelDriver()
	{
		$model = $this->getConfig('settings.model');
		$store = new ModelSettingStore($model);

		return $this->wrapDriver($store);
	}

	public function createMemoryDriver()
	{
		return $this->wrapDriver(new MemorySettingStore());
	}

	public function createArrayDriver()
	{
		return $this->createMemoryDriver();
	}

	protected function getConfig($key)
	{
		return $this->getSupportedContainer()['config']->get($key);
	}

	protected function wrapDriver($store)
	{
		$store->setDefaults($this->getConfig('settings.defaults'));

		if ($this->getConfig('settings.enableCache')) {
			$store->setCache(
				$this->getSupportedContainer()['cache'],
				$this->getConfig('settings.cacheTtl'),
				$this->getConfig('settings.forgetCacheByWrite')
			);
		}

		return $store;
	}

	protected function getSupportedContainer()
	{
		return isset($this->app) ? $this->app : $this->container;
	}
}