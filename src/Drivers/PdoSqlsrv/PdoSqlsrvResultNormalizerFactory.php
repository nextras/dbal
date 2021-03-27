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


	/** @var Closure(mixed): mixed */
	private $intNormalizer;

	/** @var Closure(mixed): mixed */
	private $floatNormalizer;

	/** @var Closure(mixed): mixed */
	private $boolNormalizer;

	/** @var Closure(mixed): mixed */
	private $dateTimeNormalizer;

	/** @var Closure(mixed): mixed */
	private $offsetDateTimeNormalizer;

	/** @var Closure(mixed): mixed */
	private $moneyNormalizer;


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

		$this->moneyNormalizer = static function ($value) {
			if ($value === null) return null;
			return strpos($value, '.') === false ? (int) $value : (float) $value;
		};
	}


	/**
	 * @param array<string, mixed> $types
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

		static $money = [
			'numeric' => true,
			'decimal' => true,
			'money' => true,
			'smallmoney' => true,
		];

		$normalizers = [];
		foreach ($types as $column => $type) {
			if (substr($type, -9) === ' identity') { // strip " identity" suffix
				$type = substr($type, 0, -9);
			}

			if ($type === 'nvarchar' || $type === 'varchar') {
				continue; // optimization
			} elseif (isset($ints[$type])) {
				$normalizers[$column] = $this->intNormalizer;
			} elseif ($type === 'real') {
				$normalizers[$column] = $this->floatNormalizer;
			} elseif (isset($dateTimes[$type])) {
				$normalizers[$column] = $this->dateTimeNormalizer;
			} elseif ($type === 'datetimeoffset') {
				$normalizers[$column] = $this->offsetDateTimeNormalizer;
			} elseif ($type === 'bit') {
				$normalizers[$column] = $this->boolNormalizer;
			} elseif (isset($money[$type])) {
				$normalizers[$column] = $this->moneyNormalizer;
			}
		}
		return $normalizers;
	}
}
