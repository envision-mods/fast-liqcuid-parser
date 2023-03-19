<?php

/*
 * This file is part of the Liquid package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package Liquid
 */

namespace Liquid;

/**
 * Context keeps the variable stack and resolves variables, as well as keywords.
 */
class Context
{
	/**
	 * Local scopes
	 *
	 * @var array
	 */
	protected $assigns;

	/**
	 * Registers for non-variable state data
	 *
	 * @var array
	 */
	public $registers;

	/**
	 * The filterbank holds all the filters
	 *
	 * @var Filterbank
	 */
	protected $filterbank;

	/**
	 * Global scopes
	 *
	 * @var array
	 */
	public $environments = array();

	/**
	 * Called "sometimes" while rendering. For example to abort the execution of a rendering.
	 *
	 * @var null|callable
	 */
	private $tickFunction = null;

	/** @var int Internal pointer for array of scopes */
	private int $ptr = 0;

	/**
	 * Constructor
	 *
	 * @param array $assigns
	 * @param array $registers
	 */
	public function __construct(array $assigns = array(), array $registers = array())
	{
		$this->assigns = array($assigns);
		$this->registers = $registers;
		$this->filterbank = new Filterbank($this);

		// first empty array serves as source for overrides, e.g. as in TagDecrement
		$this->environments = array(array(), array());

		if (Liquid::$config['EXPOSE_SERVER']) {
			$this->environments[1] = $_SERVER;
		} else {
			$this->environments[1] = array_filter(
				$_SERVER,
				function ($key) {
					return in_array(
						$key,
						(array)Liquid::$config['SERVER_SUPERGLOBAL_WHITELIST']
					);
				},
				ARRAY_FILTER_USE_KEY
			);
		}
	}

	/**
	 * Sets a tick function, this function is called sometimes while liquid is rendering a template.
	 *
	 * @param callable $tickFunction
	 */
	public function setTickFunction(callable $tickFunction)
	{
		$this->tickFunction = $tickFunction;
	}

	/**
	 * Add a filter to the context
	 *
	 * @param mixed $filter
	 */
	public function addFilters($filter, callable $callback = null)
	{
		$this->filterbank->addFilter($filter, $callback);
	}

	/**
	 * Invoke the filter that matches given name
	 *
	 * @param string $name The name of the filter
	 * @param mixed $value The value to filter
	 * @param array $args Additional arguments for the filter
	 *
	 * @return string
	 */
	public function invoke($name, $value, array $args = array())
	{
		try {
			return $this->filterbank->invoke($name, $value, $args);
		} catch (\TypeError $typeError) {
			throw new LiquidException($typeError->getMessage(), 0, $typeError);
		}
	}

	/**
	 * Merges the given assigns into the current assigns
	 *
	 * @param array $newAssigns
	 */
	public function merge($newAssigns)
	{
		$this->assigns[$this->ptr] = array_merge($this->assigns[$this->ptr], $newAssigns);
	}

	/**
	 * Push new local scope on the stack.
	 *
	 * @return bool
	 */
	public function push()
	{
		$newPtr = $this->ptr + 1;
		$this->assigns[] = array();
		$this->ptr = $newPtr;
		return true;
	}

	/**
	 * Pops the current scope from the stack.
	 *
	 * @throws LiquidException
	 * @return bool
	 */
	public function pop()
	{
		if (count($this->assigns) == 1) {
			throw new LiquidException('No elements to pop');
		}

		array_pop($this->assigns);
		$this->ptr--;
	}

	/**
	 * Replaces []
	 *
	 * @param string
	 * @param mixed $key
	 *
	 * @return mixed
	 */
	public function get($key)
	{
		return $this->resolve($key);
	}

	/**
	 * Replaces []=
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param bool $global
	 */
	public function set($key, $value, $global = false)
	{
		if ($global) {
			for ($i = 0; $i <= $this->ptr; $i++) {
				$this->assigns[$i][$key] = $value;
			}
		} else {
			$this->assigns[$this->ptr][$key] = $value;
		}
	}

	/**
	 * Returns true if the given key will properly resolve
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public function hasKey($key)
	{
		return (!is_null($this->resolve($key)));
	}

	/**
	 * Resolve a key by either returning the appropriate literal or by looking up the appropriate variable
	 *
	 * Test for empty has been moved to interpret condition, in Decision
	 *
	 * @param string $key
	 *
	 * @throws LiquidException
	 * @return mixed
	 */
	private function resolve($key)
	{
		if (is_array($key)) {
			throw new LiquidException("Cannot resolve arrays as key");
		} elseif ($key === null || $key === 'null') {
			return null;
		} elseif ($key === 'true') {
			return true;
		} elseif ($key === 'false') {
			return false;
		} elseif (isset($key[0]) && (($key[0] === '"' && $key[-1] === '"') || ($key[0] === '\'' && $key[-1] === '\''))) {
			return substr($key, 1, -1);
		} elseif (preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/', $key, $match)) {
			return $match[0];
		}

		return $this->variable($key);
	}

	/**
	 * Fetches the current key in all the scopes
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	private function fetch($key)
	{
		// TagDecrement depends on environments being checked before assigns
		foreach ($this->environments as $environment) {
			if (array_key_exists($key, $environment)) {
				return $environment[$key];
			}
		}

		for ($i = $this->ptr; $i >= 0; $i--) {
			if (isset($this->assigns[$i][$key])) {
				$obj = $this->assigns[$i][$key];

				if ($obj instanceof Drop) {
					$obj->setContext($this);
				}

				return $obj;
			}
		}

		return null;
	}

	/**
	 * Resolved the namespaced queries gracefully.
	 *
	 * @param string $key
	 *
	 * @see Decision::stringValue
	 * @see AbstractBlock::renderAll
	 *
	 * @throws LiquidException
	 * @return mixed
	 */
	private function variable($key)
	{
		// Support numeric and variable array indicies
		if (preg_match("|\[[0-9]+\]|", $key)) {
			$key = preg_replace("|\[([0-9]+)\]|", ".$1", $key);
		} elseif (preg_match("|\[[0-9a-z._]+\]|", $key, $matches)) {
			$index = $this->get(str_replace(array("[", "]"), "", $matches[0]));
			if (strlen($index)) {
				$key = preg_replace("|\[([0-9a-z._]+)\]|", ".$index", $key);
			}
		}

		$parts = explode('.', $key);

		$object = $this->fetch($parts[0]);

		for ($i = 1, $n = count($parts); $i < $n; $i++) {
			// since we still have a part to consider
			// and since we can't dig deeper into plain values
			// it can be thought as if it has a property with a null value
			if (!is_object($object) && !is_array($object) && !is_string($object)) {
				return null;
			}

			// first try to cast an object to an array or value
			if (is_object($object)) {
				if (method_exists($object, 'toLiquid')) {
					$object = $object->toLiquid();
				} elseif (method_exists($object, 'toArray')) {
					$object = $object->toArray();
				}
			}

			if ($object === null) {
				return null;
			}

			if ($object instanceof Drop) {
				$object->setContext($this);
			}

			if (is_string($object)) {
				if ($parts[$i] === 'size') {
					// if the last part of the context variable is .size we return the string length
					return mb_strlen($object);
				}

				// no other special properties for strings, yet
				return null;
			}

			if (is_array($object)) {
				// if the last part of the context variable is .first we return the first array element
				if ($parts[$i] == 'first' && $i === $n - 1 && !array_key_exists('first', $object)) {
					return StandardFilters::first($object);
				}

				// if the last part of the context variable is .last we return the last array element
				if ($parts[$i] == 'last' && $i === $n - 1 && !array_key_exists('last', $object)) {
					return StandardFilters::last($object);
				}

				// if the last part of the context variable is .size we just return the count
				if ($parts[$i] == 'size' && $i === $n - 1 && !array_key_exists('size', $object)) {
					return count($object);
				}

				// no key - no value
				if (!array_key_exists($parts[$i], $object)) {
					return null;
				}

				$object = $object[$parts[$i]];
				continue;
			}

			if (!is_object($object)) {
				// we got plain value, yet asked to resolve a part
				// think plain values have a null part with any name
				return null;
			}

			if ($object instanceof \Countable) {
				// if the last part of the context variable is .size we just return the count
				if ($parts[$i] == 'size' && $i === $n - 1) {
					return count($object);
				}
			}

			if ($object instanceof Drop) {
				// if the object is a drop, make sure it supports the given method
				if (!$object->hasKey($parts[$i])) {
					return null;
				}

				$object = $object->invokeDrop($parts[$i]);
				continue;
			}

			// if it has `get` or `field_exists` methods
			if (method_exists($object, Liquid::$config['HAS_PROPERTY_METHOD'])) {
				if (!call_user_func(array($object, Liquid::$config['HAS_PROPERTY_METHOD']), $parts[$i])) {
					return null;
				}

				$object = call_user_func(array($object, Liquid::$config['GET_PROPERTY_METHOD']), $parts[$i]);
				continue;
			}

			// if it's just a regular object, attempt to access a public method
			if (is_callable(array($object, $parts[$i]))) {
				$object = call_user_func(array($object, $parts[$i]));
				continue;
			}

			// if a magic accessor method present...
			if (is_object($object) && method_exists($object, '__get')) {
				$object = $object->{$parts[$i]};
				continue;
			}

			// Inexistent property is a null, PHP-speak
			if (!property_exists($object, $parts[$i])) {
				return null;
			}

			// then try a property (independent of accessibility)
			if (property_exists($object, $parts[$i])) {
				$object = $object->{$parts[$i]};
				continue;
			}

			// we'll try casting this object in the next iteration
		}

		// lastly, try to get an embedded value of an object
		// value could be of any type, not just string, so we have to do this
		// conversion here, not later in AbstractBlock::renderAll
		if (is_object($object) && method_exists($object, 'toLiquid')) {
			$object = $object->toLiquid();
		}

		/*
		 * Before here were checks for object types and object to string conversion.
		 *
		 * Now we just return what we have:
		 * - Traversable objects are taken care of inside filters
		 * - Object-to-string conversion is handled at the last moment in Decision::stringValue, and in AbstractBlock::renderAll
		 *
		 * This way complex objects could be passed between templates and to filters
		 */

		return $object;
	}

	public function tick()
	{
		if ($this->tickFunction === null) {
			return;
		}

		$tickFunction = $this->tickFunction;
		$tickFunction($this);
	}
}
