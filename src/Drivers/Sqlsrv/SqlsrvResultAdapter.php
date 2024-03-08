<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\Sqlsrv;


use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Result\IResultAdapter;
use Nextras\Dbal\Utils\StrictObjectTrait;
use function sqlsrv_fetch;
use function sqlsrv_fetch_array;
use function sqlsrv_field_metadata;
use function sqlsrv_free_stmt;
use function sqlsrv_num_rows;


class SqlsrvResultAdapter implements IResultAdapter
{
	use StrictObjectTrait;


	private ?int $index = null;


	/**
	 * @param resource $statement
	 */
	public function __construct(
		private $statement,
		private readonly SqlsrvResultNormalizationFactory $normalizationFactory,
	)
	{
	}


	public function __destruct()
	{
		sqlsrv_free_stmt($this->statement);
	}


	public function toBuffered(): IResultAdapter
	{
		return $this;
	}


	public function toUnbuffered(): IResultAdapter
	{
		return $this;
	}


	public function seek(int $index): void
	{
		if ($index !== 0 && sqlsrv_num_rows($this->statement) !== 0 && sqlsrv_fetch($this->statement, SQLSRV_SCROLL_ABSOLUTE, $index) !== true) {
			throw new InvalidArgumentException("Unable to seek in row set to {$index} index.");
		}
		$this->index = $index;
	}


	public function fetch(): ?array
	{
		if ($this->index !== null) {
			$index = $this->index;
			$this->index = null;
			$fetch = sqlsrv_fetch_array($this->statement, SQLSRV_FETCH_ASSOC, SQLSRV_SCROLL_ABSOLUTE, $index);
			if ($fetch === false) {
				return null;
			}
			return $fetch;
		}
		$fetch = sqlsrv_fetch_array($this->statement, SQLSRV_FETCH_ASSOC, SQLSRV_SCROLL_NEXT);
		if ($fetch === false) {
			return null;
		}
		return $fetch;
	}


	public function getRowsCount(): int
	{
		/** @var int<0, max>|false $count */
		$count = sqlsrv_num_rows($this->statement);
		return $count === false ? 0 : $count;
	}


	public function getTypes(): array
	{
		$types = [];
		$fields = sqlsrv_field_metadata($this->statement);
		$fields = $fields === false ? [] : $fields;
		foreach ($fields as $field) {
			$types[(string) $field['Name']] = $field['Type'];
		}
		return $types;
	}


	public function getNormalizers(): array
	{
		return $this->normalizationFactory->resolve($this->getTypes());
	}
}
