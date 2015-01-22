<?php

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Drivers\Mysql;

use Nextras\Dbal\Exceptions;
use Nextras\Dbal\Drivers\IDriverProvider;
use Nextras\Dbal\Drivers\IDriverException;


class MysqlDriverProvider implements IDriverProvider
{

	public function connect(array $params, $username, $password)
	{
		try {
			return new MysqlDriver($params, $username, $password);

		} catch (IDriverException $e) {
			throw $this->convertException($e->getMessage(), $e);
		}
	}


	/**
	 * This method is based on Doctrine\DBAL project.
	 * @link www.doctrine-project.org
	 */
	public function convertException($message, IDriverException $exception)
	{
		$code = (int) $exception->getErrorCode();
		if (in_array($code, [1216, 1217, 1451, 1452, 1701], TRUE)) {
			return new Exceptions\ForeignKeyConstraintViolationException($message, $exception);

		} elseif (in_array($code, [1062, 1557, 1569, 1586], TRUE)) {
			return new Exceptions\UniqueConstraintViolationException($message, $exception);

		} elseif (in_array($code, [1044, 1045, 1046, 1049, 1095, 1142, 1143, 1227, 1370, 2002, 2005], TRUE)) {
			return new Exceptions\ConnectionException($message, $exception);

		} elseif (in_array($code, [1048, 1121, 1138, 1171, 1252, 1263, 1566], TRUE)) {
			return new Exceptions\NotNullConstraintViolationException($message, $exception);

		} else {
			return new Exceptions\DbalException($message, $exception);
		}
	}

}
