<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\PdoSqlsrv;


use Closure;
use DateTimeZone;
use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Dbal\Utils\StrictObjectTrait;
use function date_default_timezone_get;
use function substr;


/**
 * @internal
 */
class PdoSqlsrvResultNormalizerFactory
{
	use StrictObjectTrait;


	private Closure $intNormalizer;
	private Closure $floatNormalizer;
	private Closure $boolNormalizer;
	private Closure $dateTimeNormalizer;
	private Closure $offsetDateTimeNormalizer;


	public function __construct()
	{
		$applicationTimeZone = new DateTimeZone(date_default_timezone_get());

		$this->intNormalizer = static function ($value): ?int {
			if ($value === null) return null;
			return (int) $value;
		};

		$this->floatNormalizer = static function ($value): ?float {
			if ($value === null) return null;
			return (float) $value;
		};

		$this->boolNormalizer = static function ($value): ?bool {
			if ($value === null) return null;
			return (bool) $value;
		};

		$this->dateTimeNormalizer = static function ($value) use ($applicationTimeZone): ?DateTimeImmutable {
			if ($value === null) return null;
			$dateTime = new DateTimeImmutable($value);
			return $dateTime->setTimezone($applicationTimeZone);
		};

		$this->offsetDateTimeNormalizer = static function ($value): ?DateTimeImmutable {
			if ($value === null) return null;
			return new DateTimeImmutable($value);
		};
	}


	/**
	 * @param array<string, array<string, mixed>> $types map of column name to the raw PDO column metadata
	 * @return array<string, callable (mixed): mixed>
	 */
	public function resolve(array $types): array
	{
		static $ints = [
			'bigint' => true,
			'int' => true,
			'smallint' => true,
			'tinyint' => true,
		];

		static $dateTimes = [
			'time' => true,
			'date' => true,
			'smalldatetime' => true,
			'datetime' => true,
			'datetime2' => true,
		];

		$normalizers = [];
		foreach ($types as $column => $field) {
			$type = $field['sqlsrv:decl_type'] ?? null;
			if (str_ends_with((string) $type, ' identity')) { // strip " identity" suffix
				$type = substr((string) $type, 0, -9);
			}

			if ($type === 'nvarchar' || $type === 'varchar') {
				continue; // optimization
			} elseif (isset($ints[$type])) {
				$normalizers[$column] = $this->intNormalizer;
			} elseif (($type === 'numeric' || $type === 'decimal') && $field['precision'] === 0) {
				// pdo_sqlsrv reports the numeric/decimal scale in the "precision" meta key;
				// a zero scale has no fractional part, so it is safe to read as an integer;
				// a non-zero scale is kept as a string to avoid precision loss
				$normalizers[$column] = $this->intNormalizer;
			} elseif ($type === 'real') {
				$normalizers[$column] = $this->floatNormalizer;
			} elseif (isset($dateTimes[$type])) {
				$normalizers[$column] = $this->dateTimeNormalizer;
			} elseif ($type === 'datetimeoffset') {
				$normalizers[$column] = $this->offsetDateTimeNormalizer;
			} elseif ($type === 'bit') {
				$normalizers[$column] = $this->boolNormalizer;
			}
		}
		return $normalizers;
	}
}
