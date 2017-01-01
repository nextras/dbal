<?php declare(strict_types=1);

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


	public function __construct(callable $callback)
	{
		$this->callback = $callback;
	}


	/**
	 * @return mixed
	 */
	protected function load(string $key)
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
	public function __get(string $key)
	{
		return $this->$key = $this->load($key);
	}

}
