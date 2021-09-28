<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\Pgsql;


use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Result\IResultAdapter;
use Nextras\Dbal\Utils\StrictObjectTrait;
use function pg_fetch_array;
use function pg_field_name;
use function pg_field_type;
use function pg_free_result;
use function pg_num_fields;
use function pg_num_rows;
use function pg_result_seek;


class PgsqlResultAdapter implements IResultAdapter
{
	use StrictObjectTrait;


	/** @var resource */
	private $result;

	/** @var PgsqlResultNormalizerFactory */
	private $normalizerFactory;


	/**
	 * @param resource $result
	 */
	public function __construct($result, PgsqlResultNormalizerFactory $normalizerFactory)
	{
		$this->result = $result;
		$this->normalizerFactory = $normalizerFactory;
	}


	public function __destruct()
	{
		pg_free_result($this->result);
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
		if (pg_num_rows($this->result) !== 0 && !pg_result_seek($this->result, $index)) {
			throw new InvalidArgumentException("Unable to seek in row set to {$index} index.");
		}
	}


	public function fetch(): ?array
	{
		$row = pg_fetch_array($this->result, null, PGSQL_ASSOC);
		return $row !== false ? $row : null;
	}


	public function getRowsCount(): int
	{
		return pg_num_rows($this->result);
	}


	public function getTypes(): array
	{
		$types = [];
		$count = pg_num_fields($this->result);

		for ($i = 0; $i < $count; $i++) {
			$nativeType = pg_field_type($this->result, $i);
			$name = pg_field_name($this->result, $i);
			assert($name !== false); // @phpstan-ignore-line
			$types[$name] = $nativeType;
		}

		return $types;
	}


	public function getNormalizers(): array
	{
		return $this->normalizerFactory->resolve($this->getTypes());
	}
}
