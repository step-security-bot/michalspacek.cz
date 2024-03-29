<?php
declare(strict_types = 1);

namespace MichalSpacekCz\Test\Database;

use DateTime;
use DateTimeInterface;
use MichalSpacekCz\Test\WillThrow;
use Nette\Database\Explorer;
use Nette\Database\Row;
use Override;

class Database extends Explorer
{

	use WillThrow;


	private string $insertId = '';

	/** @var array<string, array<string|int, string|int|bool|null>> */
	private array $queriesScalarParams = [];

	/** @var array<string, array<int, array<string, string|int|bool|null>>> */
	private array $queriesArrayParams = [];

	/** @var array<string, int|string|bool|DateTime|null> */
	private array $fetchResult = [];

	private mixed $fetchFieldDefaultResult = null;

	/** @var list<mixed> */
	private array $fetchFieldResults = [];

	private int $fetchFieldResultsPosition = 0;

	/** @var array<int|string, string|int|DateTimeInterface> */
	private array $fetchPairsDefaultResult = [];

	/** @var list<array<int|string, string|int|DateTimeInterface>> */
	private array $fetchPairsResults = [];

	private int $fetchPairsResultsPosition = 0;

	/** @var list<Row> */
	private array $fetchAllDefaultResult = [];

	/** @var list<list<Row>> */
	private array $fetchAllResults = [];

	private int $fetchAllResultsPosition = 0;


	public function reset(): void
	{
		$this->queriesScalarParams = [];
		$this->queriesArrayParams = [];
		$this->fetchResult = [];
		$this->fetchPairsDefaultResult = [];
		$this->fetchPairsResults = [];
		$this->fetchPairsResultsPosition = 0;
		$this->fetchFieldDefaultResult = null;
		$this->fetchFieldResults = [];
		$this->fetchFieldResultsPosition = 0;
		$this->fetchAllDefaultResult = [];
		$this->fetchAllResults = [];
		$this->fetchAllResultsPosition = 0;
		$this->wontThrow();
	}


	#[Override]
	public function beginTransaction(): void
	{
	}


	#[Override]
	public function commit(): void
	{
	}


	#[Override]
	public function rollBack(): void
	{
	}


	public function setInsertId(string $insertId): void
	{
		$this->insertId = $insertId;
	}


	#[Override]
	public function getInsertId(?string $sequence = null): string
	{
		return $this->insertId;
	}


	/**
	 * @param literal-string $sql
	 * @param string|int|bool|DateTimeInterface|null|array<string, string|int|bool|DateTimeInterface|null> ...$params
	 * @return ResultSet
	 */
	#[Override]
	public function query(string $sql, ...$params): ResultSet
	{
		$this->maybeThrow();
		foreach ($params as $param) {
			if (is_array($param)) {
				$arrayParams = [];
				foreach ($param as $key => $value) {
					$arrayParams[$key] = $this->formatValue($value);
				}
				$this->queriesArrayParams[$sql][] = $arrayParams;
			} else {
				$this->queriesScalarParams[$sql][] = $this->formatValue($param);
			}
		}
		return new ResultSet();
	}


	/**
	 * Emulate how values are stored in database.
	 *
	 * For example datetime is stored without timezone info.
	 * The DateTime format here is the same as in \Nette\Database\Drivers\MySqlDriver::formatDateTime() but without the quotes.
	 */
	private function formatValue(string|int|bool|DateTimeInterface|null $value): string|int|bool|null
	{
		return $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value;
	}


	/**
	 * @return array<string|int, string|int|bool|DateTimeInterface|null>
	 */
	public function getParamsForQuery(string $query): array
	{
		return $this->queriesScalarParams[$query] ?? [];
	}


	/**
	 * @return array<int, array<string, string|int|bool|DateTimeInterface|null>>
	 */
	public function getParamsArrayForQuery(string $query): array
	{
		return $this->queriesArrayParams[$query] ?? [];
	}


	/**
	 * @param array<string, int|string|bool|DateTime|null> $fetchResult
	 */
	public function setFetchResult(array $fetchResult): void
	{
		$this->fetchResult = $fetchResult;
	}


	/**
	 * @param literal-string $sql
	 * @param string ...$params
	 */
	#[Override]
	public function fetch(string $sql, ...$params): ?Row
	{
		$row = $this->createRow($this->fetchResult);
		return $row->count() > 0 ? $row : null;
	}


	public function setFetchFieldDefaultResult(mixed $fetchFieldDefaultResult): void
	{
		$this->fetchFieldDefaultResult = $fetchFieldDefaultResult;
	}


	public function addFetchFieldResult(mixed $fetchFieldResult): void
	{
		$this->fetchFieldResults[] = $fetchFieldResult;
	}


	/**
	 * @param literal-string $sql
	 * @param string ...$params
	 */
	#[Override]
	public function fetchField(string $sql, ...$params): mixed
	{
		return $this->fetchFieldResults[$this->fetchFieldResultsPosition++] ?? $this->fetchFieldDefaultResult;
	}


	/**
	 * @param array<int|string, string|int|DateTimeInterface> $fetchPairsDefaultResult
	 */
	public function setFetchPairsDefaultResult(array $fetchPairsDefaultResult): void
	{
		$this->fetchPairsDefaultResult = $fetchPairsDefaultResult;
	}


	/**
	 * @param array<int|string, string|int|DateTimeInterface> $fetchPairsResult
	 */
	public function addFetchPairsResult(array $fetchPairsResult): void
	{
		$this->fetchPairsResults[] = $fetchPairsResult;
	}


	/**
	 * @param literal-string $sql
	 * @param string ...$params
	 * @return array<int|string, string|int|DateTimeInterface>
	 */
	#[Override]
	public function fetchPairs(string $sql, ...$params): array
	{
		return $this->fetchPairsResults[$this->fetchPairsResultsPosition++] ?? $this->fetchPairsDefaultResult;
	}


	/**
	 * @param list<array<string, int|string|bool|DateTime|null>> $fetchAllDefaultResult
	 * @return void
	 */
	public function setFetchAllDefaultResult(array $fetchAllDefaultResult): void
	{
		$this->fetchAllDefaultResult = $this->getRows($fetchAllDefaultResult);
	}


	/**
	 * @param list<array<string, int|string|bool|DateTime|null>> $fetchAllResult
	 * @return void
	 */
	public function addFetchAllResult(array $fetchAllResult): void
	{
		$this->fetchAllResults[] = $this->getRows($fetchAllResult);
	}


	/**
	 * @param list<array<string, int|string|bool|DateTime|null>> $fetchAllResult
	 * @return list<Row>
	 */
	private function getRows(array $fetchAllResult): array
	{
		$result = [];
		foreach ($fetchAllResult as $row) {
			$result[] = $this->createRow($row);
		}
		return $result;
	}


	/**
	 * @param literal-string $sql
	 * @param string ...$params
	 * @return list<Row>
	 */
	#[Override]
	public function fetchAll(string $sql, ...$params): array
	{
		return $this->fetchAllResults[$this->fetchAllResultsPosition++] ?? $this->fetchAllDefaultResult;
	}


	/**
	 * Almost the same as Row::from() but with better/simpler types.
	 *
	 * @param array<string, int|string|bool|DateTime|null> $array
	 */
	private function createRow(array $array): Row
	{
		$row = new Row();
		foreach ($array as $key => $value) {
			$row->$key = $value;
		}
		return $row;
	}

}
