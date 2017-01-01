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
final class LazyHashMap
{
	/** @var callable */
	private $callback;


	public function __construct(callable $callback)
	{
		$this->callback = $callback;
	}


	/**
	 * @param  string $key
	 * @return mixed
	 */
	public function __get(string $key)
	{
		return $this->$key = $this->load($key);
	}



	/**
	 * @return mixed
	 */
	protected function load(string $key)
	{
		return call_user_func($this->callback, $key);
	}
}
