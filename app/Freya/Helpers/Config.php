<?php

namespace Freya\Helpers;

class Config implements \ArrayAccess
{

	/**
	 * The loader implementation.
	 *
	 * @var \Illuminate\Config\LoaderInterface
	 */
	protected $loader;

	/**
	 * The current environment.
	 *
	 * @var string
	 */
	protected $environment;

	/**
	 * All of the configuration items.
	 *
	 * @var array
	 */
	protected $items = array();

	/**
	 * All of the registered packages.
	 *
	 * @var array
	 */
	protected $packages = array();

	/**
	 * The after load callbacks for namespaces.
	 *
	 * @var array
	 */
	protected $afterLoad = array();

    /**
	 * A cache of the parsed items.
	 *
	 * @var array
	 */
	protected $parsed = array();

	/**
	 * Create a new configuration repository.
	 *
	 * @param  \Freya\Files\LoaderInterface  $loader
	 * @param  string  $environment
	 * @return void
	 */
	public function __construct(\Freya\Files\LoaderInterface $loader, $environment)
	{
		$this->loader = $loader;
		$this->environment = $environment;
	}

	/**
	 * Determine if the given configuration value exists.
	 *
	 * @param  string  $key
	 * @return bool
	 */
	public function has($key)
	{
		$default = microtime(true);

		return $this->get($key, $default) !== $default;
	}

	/**
	 * Determine if a configuration group exists.
	 *
	 * @param  string  $key
	 * @return bool
	 */
	public function hasGroup($key)
	{
		list($namespace, $group, $item) = $this->parseKey($key);

		return $this->loader->exists($group, $namespace);
	}

	/**
	 * Get the specified configuration value.
	 *
	 * @param  string  $key
	 * @param  mixed   $default
	 * @return mixed
	 */
	public function get($key, $default = null)
	{
		list($namespace, $group, $item) = $this->parseKey($key);

		// Configuration items are actually keyed by "collection", which is simply a
		// combination of each namespace and groups, which allows a unique way to
		// identify the arrays of configuration items for the particular files.
		$collection = $this->getCollection($group, $namespace);

		$this->load($group, $namespace, $collection);

		return $this->array_get($this->items[$collection], $item, $default);
	}

	/**
	 * Set a given configuration value.
	 *
	 * @param  string  $key
	 * @param  mixed   $value
	 * @return void
	 */
	public function set($key, $value)
	{
		list($namespace, $group, $item) = $this->parseKey($key);

		$collection = $this->getCollection($group, $namespace);

		// We'll need to go ahead and lazy load each configuration groups even when
		// we're just setting a configuration item so that the set item does not
		// get overwritten if a different item in the group is requested later.
		$this->load($group, $namespace, $collection);

		if (is_null($item))
		{
			$this->items[$collection] = $value;
		}
		else
		{
			array_set($this->items[$collection], $item, $value);
		}
	}

	/**
	 * Load the configuration group for the key.
	 *
	 * @param  string  $group
	 * @param  string  $namespace
	 * @param  string  $collection
	 * @return void
	 */
	protected function load($group, $namespace, $collection)
	{
		$env = $this->environment;

		// If we've already loaded this collection, we will just bail out since we do
		// not want to load it again. Once items are loaded a first time they will
		// stay kept in memory within this class and not loaded from disk again.
		if (isset($this->items[$collection]))
		{
			return;
		}

		$items = $this->loader->load($env, $group, $namespace);

		// If we've already loaded this collection, we will just bail out since we do
		// not want to load it again. Once items are loaded a first time they will
		// stay kept in memory within this class and not loaded from disk again.
		if (isset($this->afterLoad[$namespace]))
		{
			$items = $this->callAfterLoad($namespace, $group, $items);
		}

		$this->items[$collection] = $items;
	}

	/**
	 * Call the after load callback for a namespace.
	 *
	 * @param  string  $namespace
	 * @param  string  $group
	 * @param  array   $items
	 * @return array
	 */
	protected function callAfterLoad($namespace, $group, $items)
	{
		$callback = $this->afterLoad[$namespace];

		return call_user_func($callback, $this, $group, $items);
	}

	/**
	 * Parse an array of namespaced segments.
	 *
	 * @param  string  $key
	 * @return array
	 */
	protected function parseNamespacedSegments($key)
	{
		list($namespace, $item) = explode('::', $key);

		// If the namespace is registered as a package, we will just assume the group
		// is equal to the namespace since all packages cascade in this way having
		// a single file per package, otherwise we'll just parse them as normal.
		if (in_array($namespace, $this->packages))
		{
			return $this->parsePackageSegments($key, $namespace, $item);
		}

		list($namespace, $item) = explode('::', $key);

		// First we'll just explode the first segment to get the namespace and group
		// since the item should be in the remaining segments. Once we have these
		// two pieces of data we can proceed with parsing out the item's value.
		$itemSegments = explode('.', $item);

		$groupAndItem = array_slice($this->parseBasicSegments($itemSegments), 1);

		return array_merge(array($namespace), $groupAndItem);
	}

	/**
	 * Parse the segments of a package namespace.
	 *
	 * @param  string  $key
	 * @param  string  $namespace
	 * @param  string  $item
	 * @return array
	 */
	protected function parsePackageSegments($key, $namespace, $item)
	{
		$itemSegments = explode('.', $item);

		// If the configuration file doesn't exist for the given package group we can
		// assume that we should implicitly use the config file matching the name
		// of the namespace. Generally packages should use one type or another.
		if ( ! $this->loader->exists($itemSegments[0], $namespace))
		{
			return array($namespace, 'config', $item);
		}

		return parent::parseNamespacedSegments($key);
	}

	/**
	 * Register a package for cascading configuration.
	 *
	 * @param  string  $package
	 * @param  string  $hint
	 * @param  string  $namespace
	 * @return void
	 */
	public function package($package, $hint, $namespace = null)
	{
		$namespace = $this->getPackageNamespace($package, $namespace);

		$this->packages[] = $namespace;

		// First we will simply register the namespace with the repository so that it
		// can be loaded. Once we have done that we'll register an after namespace
		// callback so that we can cascade an application package configuration.
		$this->addNamespace($namespace, $hint);

		$this->afterLoading($namespace, function($me, $group, $items) use ($package)
		{
			$env = $me->getEnvironment();

			$loader = $me->getLoader();

			return $loader->cascadePackage($env, $package, $group, $items);
		});
	}

	/**
	 * Get the configuration namespace for a package.
	 *
	 * @param  string  $package
	 * @param  string  $namespace
	 * @return string
	 */
	protected function getPackageNamespace($package, $namespace)
	{
		if (is_null($namespace))
		{
			list($vendor, $namespace) = explode('/', $package);
		}

		return $namespace;
	}

	/**
	 * Register an after load callback for a given namespace.
	 *
	 * @param  string   $namespace
	 * @param  \Closure  $callback
	 * @return void
	 */
	public function afterLoading($namespace, Closure $callback)
	{
		$this->afterLoad[$namespace] = $callback;
	}

	/**
	 * Get the collection identifier.
	 *
	 * @param  string  $group
	 * @param  string  $namespace
	 * @return string
	 */
	protected function getCollection($group, $namespace = null)
	{
		$namespace = $namespace ?: '*';

		return $namespace.'::'.$group;
	}

	/**
	 * Add a new namespace to the loader.
	 *
	 * @param  string  $namespace
	 * @param  string  $hint
	 * @return void
	 */
	public function addNamespace($namespace, $hint)
	{
		$this->loader->addNamespace($namespace, $hint);
	}

	/**
	 * Returns all registered namespaces with the config
	 * loader.
	 *
	 * @return array
	 */
	public function getNamespaces()
	{
		return $this->loader->getNamespaces();
	}

	/**
	 * Get the loader implementation.
	 *
	 * @return \Illuminate\Config\LoaderInterface
	 */
	public function getLoader()
	{
		return $this->loader;
	}

	/**
	 * Set the loader implementation.
	 *
	 * @param \Illuminate\Config\LoaderInterface  $loader
	 * @return void
	 */
	public function setLoader(LoaderInterface $loader)
	{
		$this->loader = $loader;
	}

	/**
	 * Get the current configuration environment.
	 *
	 * @return string
	 */
	public function getEnvironment()
	{
		return $this->environment;
	}

	/**
	 * Get the after load callback array.
	 *
	 * @return array
	 */
	public function getAfterLoadCallbacks()
	{
		return $this->afterLoad;
	}

	/**
	 * Get all of the configuration items.
	 *
	 * @return array
	 */
	public function getItems()
	{
		return $this->items;
	}

	/**
	 * Determine if the given configuration option exists.
	 *
	 * @param  string  $key
	 * @return bool
	 */
	public function offsetExists($key)
	{
		return $this->has($key);
	}

	/**
	 * Get a configuration option.
	 *
	 * @param  string  $key
	 * @return mixed
	 */
	public function offsetGet($key)
	{
		return $this->get($key);
	}

	/**
	 * Set a configuration option.
	 *
	 * @param  string  $key
	 * @param  mixed  $value
	 * @return void
	 */
	public function offsetSet($key, $value)
	{
		$this->set($key, $value);
	}

	/**
	 * Unset a configuration option.
	 *
	 * @param  string  $key
	 * @return void
	 */
	public function offsetUnset($key)
	{
		$this->set($key, null);
	}

	/**
	 * Parse a key into namespace, group, and item.
	 *
	 * @param  string  $key
	 * @return array
	 */
	public function parseKey($key)
	{
		// If we've already parsed the given key, we'll return the cached version we
		// already have, as this will save us some processing. We cache off every
		// key we parse so we can quickly return it on all subsequent requests.
		if (isset($this->parsed[$key]))
		{
			return $this->parsed[$key];
		}

		$segments = explode('.', $key);

		// If the key does not contain a double colon, it means the key is not in a
		// namespace, and is just a regular configuration item. Namespaces are a
		// tool for organizing configuration items for things such as modules.
		if (strpos($key, '::') === false)
		{
			$parsed = $this->parseBasicSegments($segments);
		}
		else
		{
			$parsed = $this->parseNamespacedSegments($key);
		}

		// Once we have the parsed array of this key's elements, such as its groups
		// and namespace, we will cache each array inside a simple list that has
		// the key and the parsed array for quick look-ups for later requests.
		return $this->parsed[$key] = $parsed;
	}

	/**
	 * Parse an array of basic segments.
	 *
	 * @param  array  $segments
	 * @return array
	 */
	protected function parseBasicSegments(array $segments)
	{
		// The first segment in a basic array will always be the group, so we can go
		// ahead and grab that segment. If there is only one total segment we are
		// just pulling an entire group out of the array and not a single item.
		$group = $segments[0];

		if (count($segments) == 1)
		{
			return array(null, $group, null);
		}

		// If there is more than one segment in this group, it means we are pulling
		// a specific item out of a groups and will need to return the item name
		// as well as the group so we know which item to pull from the arrays.
		else
		{
			$item = implode('.', array_slice($segments, 1));

			return array(null, $group, $item);
		}
	}

	/**
	 * Set the parsed value of a key.
	 *
	 * @param  string  $key
	 * @param  array   $parsed
	 * @return void
	 */
	public function setParsedKey($key, $parsed)
	{
		$this->parsed[$key] = $parsed;
	}
	
	protected function array_get($array, $key, $default = null)
	{
        if (is_null($key)) {
            return $array;
        }

        if (isset($array[$key])) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if ( ! is_array($array) or ! array_key_exists($segment, $array)) {
                return value($default);
            }

            $array = $array[$segment];
        }

        return $array;
    }
}
