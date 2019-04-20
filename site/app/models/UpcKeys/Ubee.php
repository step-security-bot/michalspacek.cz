<?php
declare(strict_types = 1);

namespace MichalSpacekCz\UpcKeys;

class Ubee implements RouterInterface
{

	/** @var string */
	private const OUI_UBEE = '647c34';

	/** @var \Nette\Database\Context */
	protected $database;

	/** @var string */
	protected $prefix;

	/** @var string */
	protected $model;


	public function __construct(\Nette\Database\Context $context)
	{
		$this->database = $context;
	}


	public function setPrefixes(array $prefixes): void
	{
		if (count($prefixes) > 1) {
			throw new \RuntimeException('Ubee can has only one prefix');
		}
		$this->prefix = current($prefixes);
	}


	public function setModel(string $model): void
	{
		$this->model = $model;
	}


	/**
	 * Get serial number prefixes to get keys for.
	 *
	 * @return array of prefixes
	 */
	public function getModelWithPrefixes(): array
	{
		return [$this->model => [$this->prefix]];
	}


	/**
	 * Get keys from database.
	 *
	 * @param string $ssid
	 * @return array of \stdClass (serial, key, type)
	 */
	public function getKeys(string $ssid): array
	{
		$rows = $this->database->fetchAll('SELECT mac, `key` FROM keys_ubee WHERE ssid = ?', substr($ssid, 3));
		$result = array();
		foreach ($rows as $row) {
			$result[$row->mac] = $this->buildKey($row->mac, (int)$row->key);
		}
		ksort($result);
		return array_values($result);
	}


	private function buildKey(int $mac, int $key): \stdClass
	{
		$result = new \stdClass();
		$result->serial = $this->prefix;
		$result->oui = self::OUI_UBEE;
		$result->mac = sprintf('%06x', $mac);
		$result->type = \MichalSpacekCz\UpcKeys::SSID_TYPE_UNKNOWN;

		$result->key = '';
		for ($i = 7; $i >= 0; $i--) {
			$result->key .= chr((($key >> $i * 5) & 0x1F) + 0x41);
		}

		return $result;
	}

}
