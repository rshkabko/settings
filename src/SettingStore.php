<?php

namespace Flamix\Settings;

abstract class SettingStore
{
    /**
     * The settings data.
     *
     * @var array
     */
    protected $data = [];

    /**
     * The settings updated data.
     *
     * @var array
     */
    protected $updatedData = [];

    /**
     * The settings data as last read from the store.
     *
     * @var array
     */
    protected $persistedData = [];

    /**
     * Whether the store has changed since it was last loaded.
     *
     * @var boolean
     */
    protected $unsaved = false;

    /**
     * Whether the settings data are loaded.
     *
     * @var boolean
     */
    protected $loaded = false;

    /**
     * Default values.
     *
     * @var array
     */
    protected $defaults = [];

    /**
     * @var \Illuminate\Contracts\Cache\Store|\Illuminate\Cache\StoreInterface
     */
    protected $cache = null;

    /**
     * Cache TTL in seconds.
     *
     * @var int
     */
    protected $cacheTtl = 3600;

    /**
     * Whether to reset the cache when changing a setting.
     *
     * @var boolean
     */
    protected $cacheForgetOnWrite = true;

    /**
     * Set default values.
     *
     * @param  array  $defaults
     */
    public function setDefaults(array $defaults)
    {
        $this->defaults = $defaults;
    }

    /**
     * Set the cache.
     * @param  \Illuminate\Contracts\Cache\Store|\Illuminate\Cache\StoreInterface  $cache
     * @param  int  $ttl
     * @param  bool  $forgetOnWrite
     */
    public function setCache($cache, $ttl = null, $forgetOnWrite = null)
    {
        $this->cache = $cache;
        if ($ttl !== null) {
            $this->cacheTtl = $ttl;
        }
        if ($forgetOnWrite !== null) {
            $this->cacheForgetOnWrite = $forgetOnWrite;
        }
    }

    /**
     * Get a specific key from the settings data.
     *
     * @param  string|array  $key
     * @param  mixed  $default  Optional default value.
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if ($default === null) {
            $default = ArrayUtil::get($this->defaults, $key);
        } elseif (is_array($key) && is_array($default)) {
            $default = array_merge(ArrayUtil::get($this->defaults, $key, []), $default);
        }

        $this->load();

        return ArrayUtil::get($this->data, $key, $default);
    }

    /**
     * Determine if a key exists in the settings data.
     *
     * @param  string  $key
     *
     * @return boolean
     */
    public function has($key)
    {
        $this->load();

        return ArrayUtil::has($this->data, $key);
    }

    /**
     * Set a specific key to a value in the settings data.
     *
     * @param  string|array  $key  Key string or associative array of key => value
     * @param  mixed  $value  Optional only if the first argument is an array
     */
    public function set($key, $value = null)
    {
        $this->load();
        $this->unsaved = true;

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                ArrayUtil::set($this->data, $k, $v);
                ArrayUtil::set($this->updatedData, $k, $v);
            }
        } else {
            ArrayUtil::set($this->data, $key, $value);
            ArrayUtil::set($this->updatedData, $key, $value);
        }
    }

    /**
     * Unset a key in the settings data.
     *
     * @param  string  $key
     */
    public function forget($key)
    {
        $this->unsaved = true;

        if ($this->has($key)) {
            ArrayUtil::forget($this->data, $key);
            ArrayUtil::forget($this->updatedData, $key);
        }
    }

    /**
     * Unset all keys in the settings data.
     *
     * @return void
     */
    public function forgetAll()
    {
        $this->unsaved = true;
        $this->data = [];
        $this->updatedData = [];
        $this->loaded = false;
    }

    /**
     * Get all settings data.
     *
     * @return array
     */
    public function all()
    {
        $this->load();

        return $this->data;
    }

    /**
     * Save any changes done to the settings data.
     *
     * @return void
     */
    public function save()
    {
        if (!$this->unsaved) {
            // either nothing has been changed, or data has not been loaded, so
            // do nothing by returning early
            return;
        }

        if ($this->cache && $this->cacheForgetOnWrite) {
            $this->cache->forget($this->cacheKey());
        }

        $this->write($this->data);
        $this->unsaved = false;
    }

    /**
     * Make sure data is loaded.
     *
     * @param  bool  $force  Force a reload of data. Default false.
     */
    public function load(bool $force = false)
    {
        if (!$this->loaded || $force) {
            $this->data = $this->readData();
            $this->persistedData = $this->data;
            $this->data = $this->updatedData + $this->data;
            $this->loaded = true;
        }
    }

    /**
     * Read data from a store or cache
     *
     * @return array
     */
    private function readData()
    {
        if ($this->cache) {
            return $this->cache->remember($this->cacheKey(), $this->cacheTtl, function () {
                return $this->read();
            });
        }

        return $this->read();
    }

    /**
     * Get real cache key with extra columns.
     * Replace static::CACHE_KEY in older version.
     *
     * @param  string  $key
     * @return string
     */
    private function cacheKey(string $key = ''): string
    {
        $key = "setting:cache:{$key}";
        if (isset($this->extraColumns)) {
            $key .= ':'.implode(':', $this->extraColumns);
        }
        return $key;
    }

    /**
     * Read the data from the store.
     *
     * @return array
     */
    abstract protected function read();

    /**
     * Write the data into the store.
     *
     * @param  array  $data
     *
     * @return void
     */
    abstract protected function write(array $data);
}