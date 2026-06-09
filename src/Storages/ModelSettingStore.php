<?php

namespace Flamix\Settings\Storages;

use Illuminate\Support\Arr;
use Flamix\Settings\SettingStore;
use Flamix\Settings\ArrayUtil;

class ModelSettingStore extends SettingStore
{
	/**
	 * Model instance.
	 *
	 * @var object
	 */
	protected object $model;

	/**
	 * Any extra columns that should be added to the rows.
	 *
	 * @var array
	 */
	protected $extraColumns = [];

	public function __construct(string $model)
	{
		$this->model = app($model);
	}

	/**
	 * Set the model instance.
	 *
	 * @param object $model
	 */
	public function setModel($model)
	{
		$this->model = $model;
	}

	/**
	 * Set extra columns to be added to the rows.
	 *
	 * @param array $columns
	 */
	public function setExtraColumns(array $columns): void
	{
		$this->forgetAll(); // Reboot old filters
		$this->extraColumns = $columns;
	}

	/**
	 * Forget setting by key.
	 * Do not forget save(), if you need to save the changes.
	 * By default method only forgets dynamically set settings.
	 *
	 * @param $key
	 * @return void
	 */
	public function forget($key): void
	{
		parent::forget($key);

		// because the database store cannot store empty arrays, remove empty
		// arrays to keep data consistent before and after saving
		$segments = explode('.', $key);
		array_pop($segments);

		while ($segments) {
			$segment = implode('.', $segments);

			// non-empty array - exit out of the loop
			if ($this->get($segment)) {
				break;
			}

			// remove the empty array and move on to the next segment
			$this->forget($segment);
			array_pop($segments);
		}
	}

	/**
	 * Save settings to database.
	 * Do not forget save(), if you need to save the changes.
	 *
	 * @param array $data
	 * @return void
	 */
	protected function write(array $data): void
	{
		$keysQuery = $this->newQuery();
		$keys = $keysQuery->pluck('key');

		$insertData = Arr::dot($data);
		$updatedData = Arr::dot($this->updatedData);
		$persistedData = Arr::dot($this->persistedData);
		$updateData = [];
		$deleteKeys = [];

		foreach ($keys as $key) {
			if (isset($updatedData[$key]) && isset($persistedData[$key]) && (string)$updatedData[$key] !== (string)$persistedData[$key]) {
				$updateData[$key] = $updatedData[$key];
			} elseif (!isset($insertData[$key])) {
				$deleteKeys[] = $key;
			}
			unset($insertData[$key]);
		}

		foreach ($updateData as $key => $value) {
			$this->newQuery()
				->where('key', '=', strval($key))
				->update(['value' => $value]);
		}

		// Remove unsupported values
		foreach ($insertData as $key => $value) {
			// Empty array
			if (is_array($value) && empty($value)) {
				unset($insertData[$key]);
			}

			// Null
			if (is_null($value)) {
				unset($insertData[$key]);
			}
		}

		if ($insertData) {
			$this->newQuery(true)
				->insert($this->prepareInsertData($insertData));
		}

		if ($deleteKeys) {
			$this->newQuery()
				->whereIn('key', $deleteKeys)
				->delete();
		}
	}

	/**
	 * Transforms settings data into an array ready to be inserted into the
	 * database. Call Arr::dot on a multidimensional array before passing it
	 * into this method!
	 *
	 * @param  array $data Call Arr::dot on a multidimensional array before passing it into this method!
	 *
	 * @return array
	 */
	protected function prepareInsertData(array $data): array
	{
		foreach ($data as $key => $value) {
			$dbData[] = array_merge(
				$this->extraColumns ?? [],
				['key' => $key, 'value' => $value]
			);
		}

		return $dbData ?? [];
	}

	/**
	 * Get settings from database.
	 *
	 * @return mixed
	 */
	protected function read(): mixed
	{
		return $this->parseReadData($this->newQuery()->get());
	}

	/**
	 * Parse data coming from the database.
	 *
	 * @param  array $data
	 *
	 * @return array
	 */
	public function parseReadData($data): array
	{
		$results = [];

		foreach ($data as $row) {
			if (is_array($row)) {
				$key = $row['key'];
				$value = $row[$this->valueColumn];
			} elseif (is_object($row)) {
				$key = $row->key;
				$value = $row->value;
			} else {
				$msg = 'Expected array or object, got '.gettype($row);
				throw new \UnexpectedValueException($msg);
			}

			ArrayUtil::set($results, $key, $value);
		}

		return $results;
	}

	/**
	 * Create a new query builder instance.
	 *
	 * @param  $insert  boolean  Whether the query is an insert or not.
	 *
	 * @return \Illuminate\Database\Query\Builder
	 */
	protected function newQuery(bool $insert = false): object
	{
		$model = $this->model;

		if (!$insert) {
			foreach ($this->extraColumns as $key => $value) {
				$model = $model->where($key, '=', $value);
			}
		}

		return $model;
	}
}
