<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace  Nextras\Dbal;


/**
 * @internal
 */
abstract class LazyHashMapBase
{
	/** @var callable */
	private $callback;


	public function __construct($callback)
	{
		$this->callback = $callback;
	}


	/**
	 * @param  string $key
	 * @return mixed
	 */
	protected function load($key)
	{
		return call_user_func($this->callback, $key);
	}

}


/**
 * @internal
 */
final class LazyHashMap extends LazyHashMapBase
{

	/**
	 * @param  string $key
	 * @return mixed
	 */
	public function __get($key)
	{
		return $this->$key = $this->load($key);
	}

}
