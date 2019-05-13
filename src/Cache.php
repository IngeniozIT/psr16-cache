<?php

namespace IngeniozIT\Psr16;

use Psr\SimpleCache\CacheInterface;
use IngeniozIT\Psr16\InvalidArgumentException;

class Cache implements CacheInterface
{
    protected $path;

    public function __construct(string $path)
    {
        $this->path = realpath($path);

        if (false === $this->path || !is_dir($this->path)) {
            throw new InvalidArgumentException('Path "'.$path.'" is not a directory.');
        }
    }

    protected function getItemPath(string $key): string
    {
        $this->checkLegalString($key);
        return $this->path.'/'.$key;
    }

    protected function checkLegalString(string $key)
    {
        if (!is_string($key) || !preg_match('/^[A-Za-z0-9_.]{1,64}$/', $key)) {
            throw new InvalidArgumentException('Key "'.$key.'" is not legal.');
        }
    }

    protected function checkTraversable($values)
    {
        if (!is_iterable($values)) {
            throw new InvalidArgumentException('Values are not traversable.');
        }
    }

    /**
     * Fetches a value from the cache.
     *
     * @param string $key     The unique key of this item in the cache.
     * @param mixed  $default Default value to return if the key does not exist.
     *
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function get($key, $default = null)
    {
        if (!$this->has($key)) {
            return $default;
        }

        $content = unserialize(file_get_contents($this->getItemPath($key)));
        if (null !== $content['expires'] && $content['expires'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $content['value'];
    }

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string                 $key   The key of the item to store.
     * @param mixed                  $value The value of the item to store, must be serializable.
     * @param null|int|\DateInterval $ttl   Optional. The TTL value of this item. If no value is sent and
     *                                      the driver supports TTL then the library may set a default value
     *                                      for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function set($key, $value, $ttl = null)
    {
        if (null !== $ttl) {
            if (!is_int($ttl)) {
                if (!is_a($ttl, \DateInterval::class)) {
                    throw new InvalidArgumentException('Ttl must either be null, int or DateInterval.');
                }
                $ttl = date_create('@0')->add($ttl)->getTimestamp();
            }

            if ($ttl <= 0) {
                if ($this->has($key)) {
                    return $this->delete($key);
                }
                return true;
            }

            $ttl += time();
        }

        return file_put_contents(
            $this->getItemPath($key), serialize(
                [
                'expires' => $ttl,
                'value' => $value
                ]
            )
        );
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function delete($key)
    {
        return $this->has($key) && unlink($this->getItemPath($key));
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear()
    {
        $success = false;
        if ($handle = opendir($this->path)) {
            $success = true;

            while (false !== ($key = readdir($handle))) {
                if (is_dir($this->getItemPath($key))) {
                    continue;
                }
                $success = $success && $this->delete($key);
            }

        }
        return $success;
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys    A list of keys that can obtained in a single operation.
     * @param mixed    $default Default value to return for keys that do not exist.
     *
     * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function getMultiple($keys, $default = null)
    {
        $this->checkTraversable($keys);

        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable               $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|\DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
     *                                       the driver supports TTL then the library may set a default value
     *                                       for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $values is neither an array nor a Traversable,
     *   or if any of the $values are not a legal value.
     */
    public function setMultiple($values, $ttl = null)
    {
        $this->checkTraversable($values);

        $success = true;

        foreach ($values as $key => $value) {
            $success = $success && $this->set($key, $value, $ttl);
        }

        return $success;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable $keys A list of string-based keys to be deleted.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function deleteMultiple($keys)
    {
        $this->checkTraversable($keys);

        $success = true;

        foreach ($keys as $key) {
            $success = $success && $this->delete($key);
        }

        return $success;
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * NOTE: It is recommended that has() is only to be used for cache warming type purposes
     * and not to be used within your live applications operations for get/set, as this method
     * is subject to a race condition where your has() will return true and immediately after,
     * another script can remove it making the state of your app out of date.
     *
     * @param string $key The cache item key.
     *
     * @return bool
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function has($key)
    {
        $this->checkLegalString($key);

        $path = $this->getItemPath($key);
        return file_exists($path) && !is_dir($path);
    }
}
