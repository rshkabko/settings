<?php

namespace Flamix\Settings\Storages;

use Illuminate\Filesystem\Filesystem;
use Flamix\Settings\SettingStore;

class JsonSettingStore extends SettingStore
{
	/** @var \Illuminate\Filesystem\Filesystem */
	public $files;

	/** @var string Path to settings file. */
	public $path;

	/**
	 * @param \Illuminate\Filesystem\Filesystem $files
	 * @param string                            $path
	 */
	public function __construct(Filesystem $files, $path = null)
	{
		$this->files = $files;
		$this->setPath($path ?: storage_path() . '/settings.json');
	}

	/**
	 * Set the path for the JSON file.
	 *
	 * @param string $path
	 */
	public function setPath($path)
	{
		$this->path = $path;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function read()
	{
		// The file is created lazily on the first write
		if (!$this->files->exists($this->path)) {
			return [];
		}

		$contents = $this->files->get($this->path);

		$data = json_decode($contents, true);

		if ($data === null) {
			throw new \RuntimeException("Invalid JSON in {$this->path}");
		}

		return $data;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function write(array $data)
	{
		$contents = $data ? json_encode($data) : '{}';

		// LOCK_EX prevents interleaved concurrent writes; last write still wins
		if ($this->files->put($this->path, $contents, true) === false) {
			throw new \InvalidArgumentException("Could not write to {$this->path}.");
		}
	}
}
