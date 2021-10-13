<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\PdoSqlite;


use Closure;
use Nextras\Dbal\Utils\StrictObjectTrait;


/**
 * @internal
 */
class PdoSqliteResultNormalizerFactory
{
	use StrictObjectTrait;


	/** @var Closure(mixed): mixed */
	private $intNormalizer;

	/** @var Closure(mixed): mixed */
	private $floatNormalizer;


	public function __construct()
	{
		$this->intNormalizer = static function ($value): ?int {
			if ($value === null) return null;
			return (int) $value;
		};

		$this->floatNormalizer = static function ($value): ?float {
			if ($value === null) return null;
			return (float) $value;
		};
	}


	/**
	 * @param array<string, mixed> $types
	 * @return array<string, callable (mixed): mixed>
	 */
	public function resolve(array $types): array
	{
		static $ints = [
			'int' => true,
			'integer' => true,
			'tinyint' => true,
			'smallint' => true,
			'mediumint' => true,
			'bigint' => true,
			'unsigned big int' => true,
			'int2' => true,
			'int8' => true,
		];

		static $floats = [
			'real' => self::TYPE_FLOAT,
					'double' => self::TYPE_FLOAT,
					'double precision' => self::TYPE_FLOAT,
					'float' => self::TYPE_FLOAT,
					'numeric' => self::TYPE_FLOAT,
					'decimal' => self::TYPE_FLOAT,
		];

		$normalizers = [];
		foreach ($types as $column => $type) {
			if ($type === 'text' || $type === 'varchar') {
				continue; // optimization
			} elseif ($type === 'integer') {
				$normalizers[$column] = $this->intNormalizer;
			} elseif ($type === 'real') {
				$normalizers[$column] = $this->floatNormalizer;
			}
		}
		return $normalizers;
	}
}
