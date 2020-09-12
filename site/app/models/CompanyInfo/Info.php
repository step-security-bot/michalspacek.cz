<?php
declare(strict_types = 1);

namespace MichalSpacekCz\CompanyInfo;

use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\Http\IResponse;
use RuntimeException;

class Info
{

	/** @var Ares */
	private $ares;

	/** @var RegisterUz */
	private $registerUz;

	/** @var Cache */
	private $cache;

	/** @var boolean */
	private $loadCompanyDataVisible = true;


	public function __construct(Ares $ares, RegisterUz $registerUz, IStorage $cacheStorage)
	{
		$this->ares = $ares;
		$this->registerUz = $registerUz;
		$this->cache = new Cache($cacheStorage, self::class);
	}


	public function getData(string $country, string $companyId): Data
	{
		return $this->cache->load("{$country}/{$companyId}", function (&$dependencies) use ($country, $companyId) {
			switch ($country) {
				case 'cz':
					$data = $this->ares->getData($companyId);
					break;
				case 'sk':
					$data = $this->registerUz->getData($companyId);
					break;
				default:
					throw new RuntimeException('Unsupported country');
			}
			$dependencies[Cache::EXPIRATION] = ($data->status === IResponse::S200_OK ? '3 days' : '15 minutes');
			return $data;
		});
	}


	public function setLoadCompanyDataVisible(bool $visible): void
	{
		$this->loadCompanyDataVisible = $visible;
	}


	public function isLoadCompanyDataVisible(): bool
	{
		return $this->loadCompanyDataVisible;
	}

}
