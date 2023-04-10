<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\Sqlsrv;


use Closure;
use DateTimeZone;
use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Dbal\Utils\StrictObjectTrait;
use function date_default_timezone_get;


/**
 * @internal
 * @see https://docs.microsoft.com/en-us/sql/connect/php/sqlsrv-field-metadata
 */
class SqlsrvResultNormalizationFactory
{
	use StrictObjectTrait;


	private const TYPE_WVARCHAR = -9;
	private const TYPE_VARCHAR = 12;
	private const TYPE_BIGINT = -5;
	private const TYPE_INT = 4;
	private const TYPE_TYNIINT = -6;
	private const TYPE_SMALLIINT = 5;
	private const TYPE_REAL = 7;
	private const TYPE_BIT = -7;
	private const TYPE_TIME = -154;
	private const TYPE_DATE = 91;
	private const TYPE_DATETIME_DATETIME2_SMALLDATETIME = 93;
	private const TYPE_DATETIMEOFFSET = -155;

	private Closure $intNormalizer;
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
	 * @param array<string, mixed> $types
	 * @return array<string, callable (mixed): mixed>
	 */
	public function resolve(array $types): array
	{
		static $ok = [
			self::TYPE_WVARCHAR => true,
			self::TYPE_VARCHAR => true,
			self::TYPE_INT => true,
			self::TYPE_TYNIINT => true,
			self::TYPE_SMALLIINT => true,
			self::TYPE_REAL => true,
		];

		static $dateTimes = [
			self::TYPE_TIME => true,
			self::TYPE_DATE => true,
			self::TYPE_DATETIME_DATETIME2_SMALLDATETIME => true,
		];

		$normalizers = [];
		foreach ($types as $column => $type) {
			if (isset($ok[$type])) {
				continue; // optimization
			} elseif ($type === self::TYPE_BIGINT) {
				$normalizers[$column] = $this->intNormalizer;
			} elseif (isset($dateTimes[$type])) {
				$normalizers[$column] = $this->dateTimeNormalizer;
			} elseif ($type === self::TYPE_DATETIMEOFFSET) {
				$normalizers[$column] = $this->offsetDateTimeNormalizer;
			} elseif ($type === self::TYPE_BIT) {
				$normalizers[$column] = $this->boolNormalizer;
			}
		}
		return $normalizers;
	}
}
