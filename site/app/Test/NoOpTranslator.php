<?php
/** @noinspection PhpMissingParentConstructorInspection */
declare(strict_types = 1);

namespace MichalSpacekCz\Test;

use Contributte\Translation\Exceptions\InvalidArgument;
use Contributte\Translation\Translator;
use Contributte\Translation\Wrappers\Message;
use Contributte\Translation\Wrappers\NotTranslate;

class NoOpTranslator extends Translator
{

	/**
	 * @param list<string> $availableLocales
	 */
	public function __construct(
		private readonly array $availableLocales,
		private readonly string $defaultLocale,
	) {
	}


	public function getDefaultLocale(): string
	{
		return $this->defaultLocale;
	}


	public function getLocale(): string
	{
		return $this->defaultLocale;
	}


	public function translate(mixed $message, mixed ...$parameters): string
	{
		if ($message === null || $message === '') {
			return '';
		}
		if ($message instanceof NotTranslate) {
			return $message->message;
		}
		if ($message instanceof Message) {
			return $message->message;
		} elseif (is_int($message)) {
			return (string)$message;
		}
		if (!is_string($message)) {
			throw new InvalidArgument('Message must be string, ' . gettype($message) . ' given.');
		}
		return $message;
	}


	public function getAvailableLocales(): array
	{
		return $this->availableLocales;
	}


	public function setFallbackLocales(array $locales): void
	{
	}

}
