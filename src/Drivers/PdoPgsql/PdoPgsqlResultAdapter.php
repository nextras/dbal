<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\PdoPgsql;


use Nextras\Dbal\Exception\InvalidStateException;
use Nextras\Dbal\Exception\NotSupportedException;
use Nextras\Dbal\Result\BufferedResultAdapter;
use Nextras\Dbal\Result\IResultAdapter;
use Nextras\Dbal\Utils\StrictObjectTrait;
use PDO;
use PDOStatement;


class PdoPgsqlResultAdapter implements IResultAdapter
{
	use StrictObjectTrait;


	/** @var PDOStatement<mixed> */
	private $statement;

	/** @var bool */
	private $beforeFirstFetch = true;

	/** @var PdoPgsqlResultNormalizerFactory */
	private $normalizerFactory;


	/**
	 * @param PDOStatement<mixed> $statement
	 */
	public function __construct(PDOStatement $statement, PdoPgsqlResultNormalizerFactory $normalizerFactory)
	{
		$this->statement = $statement;
		$this->normalizerFactory = $normalizerFactory;
	}


	public function toBuffered(): IResultAdapter
	{
		return new BufferedResultAdapter($this);
	}


	public function toUnbuffered(): IResultAdapter
	{
		return $this;
	}


	public function seek(int $index): void
	{
		if ($index === 0 && $this->beforeFirstFetch) {
			return;
		}

		throw new NotSupportedException("PDO does not support rewinding or seeking. Use Result::buffered() before first consume of the result.");
	}


	public function fetch(): ?array
	{
		if ($this->beforeFirstFetch && $this->statement->columnCount() === 0) {
			$this->beforeFirstFetch = false;
			return null;
		}

		$this->beforeFirstFetch = false;
		$fetched = $this->statement->fetch(PDO::FETCH_ASSOC);
		return $fetched !== false ? $fetched : null;
	}


	public function getRowsCount(): int
	{
		return $this->statement->rowCount();
	}


	public function getTypes(): array
	{
		$types = [];
		$count = $this->statement->columnCount();

		for ($i = 0; $i < $count; $i++) {
			$field = $this->statement->getColumnMeta($i);
			if ($field === false) { // @phpstan-ignore-line
				throw new InvalidStateException("Should not happen.");
			}
			$types[(string) $field['name']] = $field['native_type'];
		}

		return $types;
	}


	public function getNormalizers(): array
	{
		return $this->normalizerFactory->resolve($this->getTypes());
	}
}
