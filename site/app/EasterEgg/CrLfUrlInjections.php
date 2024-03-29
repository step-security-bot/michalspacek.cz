<?php
declare(strict_types = 1);

namespace MichalSpacekCz\EasterEgg;

use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\Utils\Strings;

readonly class CrLfUrlInjections
{

	private const string COOKIE_NAME = 'crlfinjection';


	public function __construct(
		private IRequest $httpRequest,
		private IResponse $httpResponse,
	) {
	}


	public function detectAttempt(): bool
	{
		$url = $this->httpRequest->getUrl()->getAbsoluteUrl();
		if (!str_contains($url, "\r") && !str_contains($url, "\n")) {
			return false;
		}
		$matches = Strings::matchAll($url, sprintf('/Set\-Cookie:%s=([a-z0-9]+)/i', self::COOKIE_NAME));
		foreach ($matches as $match) {
			// Don't use any cookie name from the request to avoid e.g. session fixation
			$this->httpResponse->setCookie(
				self::COOKIE_NAME,
				$match[1],
				time() - 31337 * 1337,
				'/expired=31337*1337seconds/(1.3years)/ago',
			);
		}
		$this->httpResponse->setCode(IResponse::S204_NoContent, 'U WOT M8');
		$this->httpResponse->setHeader('Hack-the-Planet', 'https://youtu.be/u3CKgkyc7Qo?t=20');
		return true;
	}

}
