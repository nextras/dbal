<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\PdoSqlite;


use Nextras\Dbal\Exception\NotSupportedException;
use Nextras\Dbal\Result\FullyBufferedResultAdapter;
use Nextras\Dbal\Result\IResultAdapter;
use Nextras\Dbal\Utils\StrictObjectTrait;
use PDO;
use PDOStatement;
use function explode;
use function strtolower;


class PdoSqliteResultAdapter implements IResultAdapter
{
	use StrictObjectTrait;


	/** @var PDOStatement<mixed> */
	private $statement;

	/** @var bool */
	private $beforeFirstFetch = true;

	/** @var PdoSqliteResultNormalizerFactory */
	private $normalizerFactory;


	/**
	 * @param PDOStatement<mixed> $statement
	 */
	public function __construct(PDOStatement $statement, PdoSqliteResultNormalizerFactory $normalizerFactory)
	{
		$this->statement = $statement;
		$this->normalizerFactory = $normalizerFactory;
	}


	public function toBuffered(): IResultAdapter
	{
		return new FullyBufferedResultAdapter($this);
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
				// Sqlite does not return meta for special queries (PRAGMA, etc.)
				continue;
			}

			// SQLite exposes sqlite:decl_type at runtime, but PHPStan's built-in PDO shape does not know that key.
			/** @phpstan-ignore-next-line */
			$type = strtolower((string) ($field['sqlite:decl_type'] ?? $field['native_type'] ?? ''));
			$type = explode('(', $type)[0];
			$types[$field['name']] = $type;
		}

		return $types;
	}


	public function getNormalizers(): array
	{
		return $this->normalizerFactory->resolve($this->getTypes());
	}
}
