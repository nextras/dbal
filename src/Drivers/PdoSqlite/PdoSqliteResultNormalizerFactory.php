<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\PdoSqlite;


use Closure;
use DateTimeZone;
use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Dbal\Utils\StrictObjectTrait;
use function ctype_digit;
use function date_default_timezone_get;
use function floor;
use function is_int;
use function is_numeric;
use function sprintf;


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

	/** @var Closure(mixed): mixed */
	private $boolNormalizer;

	/** @var Closure(mixed): mixed */
	private $dateTimeNormalizer;

	/** @var Closure(mixed): mixed */
	private $localDateTimeNormalizer;


	public function __construct(PdoSqliteDriver $driver)
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

		$this->dateTimeNormalizer = static function ($value) use ($driver, $applicationTimeZone): ?DateTimeImmutable {
			if ($value === null) return null;

			if (is_int($value) || (is_string($value) && ctype_digit($value))) {
				$milliseconds = (int) $value;
				$seconds = intdiv($milliseconds, 1000);
				$microseconds = ($milliseconds % 1000) * 1000;
				$dateTime = DateTimeImmutable::createFromFormat(
					'U.u',
					sprintf('%d.%06d', $seconds, $microseconds),
					new DateTimeZone('UTC'),
				);
				assert($dateTime !== false);
				return $dateTime->setTimezone($applicationTimeZone);
			}

			$dateTime = new DateTimeImmutable($value . ' ' . $driver->getConnectionTimeZone()->getName());
			return $dateTime->setTimezone($applicationTimeZone);
		};

		$this->localDateTimeNormalizer = static function ($value) use ($applicationTimeZone): ?DateTimeImmutable {
			if ($value === null) return null;
			$dateTime = new DateTimeImmutable((string) $value);
			return $dateTime->setTimezone($applicationTimeZone);
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
			'real' => true,
			'double' => true,
			'double precision' => true,
			'float' => true,
			'numeric' => true,
			'decimal' => true,
		];

		static $bools = [
			'bool' => true,
			'boolean' => true,
			'bit' => true,
			'dbal_bool' => true,
		];

		static $localDateTimes = [
			'date' => true,
			'datetime' => true,
			'time' => true,
			'localdate' => true,
			'localdatetime' => true,
			'localtime' => true,
			'dbal_local_date' => true,
			'dbal_local_datetime' => true,
			'dbal_local_time' => true,
		];

		static $dateTimes = [
			'timestamp' => true,
			'unixtimestamp' => true,
			'dbal_timestamp' => true,
		];

		$normalizers = [];
		foreach ($types as $column => $type) {
			if ($type === 'text' || $type === 'varchar') {
				continue; // optimization
			} elseif (isset($ints[$type])) {
				$normalizers[$column] = $this->intNormalizer;
			} elseif (isset($floats[$type])) {
				$normalizers[$column] = $this->floatNormalizer;
			} elseif (isset($bools[$type])) {
				$normalizers[$column] = $this->boolNormalizer;
			} elseif (isset($localDateTimes[$type])) {
				$normalizers[$column] = $this->localDateTimeNormalizer;
			} elseif (isset($dateTimes[$type])) {
				$normalizers[$column] = $this->dateTimeNormalizer;
			}
		}
		return $normalizers;
	}
}
