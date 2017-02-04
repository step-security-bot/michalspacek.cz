<?php
declare(strict_types = 1);

namespace MichalSpacekCz\Form;

/**
 * UPC keys form.
 *
 * @author     Michal Špaček
 * @package    michalspacek.cz
 */
class UpcKeys extends UnprotectedForm
{

	/**
	 * @param \Nette\ComponentModel\IContainer $parent
	 * @param string|null $name
	 * @param string $ssid
	 */
	public function __construct(\Nette\ComponentModel\IContainer $parent, string $name, ?string $ssid, \MichalSpacekCz\UpcKeys $upcKeys)
	{
		parent::__construct($parent, $name);
		$this->addText('ssid', 'SSID:')
			->setAttribute('placeholder', $upcKeys->getSsidPlaceholder())
			->setAttribute('title', '"UPC" and 7 digits')
			->setDefaultValue($ssid)
			->setRequired('Please enter an SSID')
			->addRule(self::PATTERN, 'Wi-Fi network name has to be "UPC" and 7 digits (UPC1234567)', '\s*' . $upcKeys->getValidSsidPattern() . '\s*');
		$this->addSubmit('submit', 'Get keys')
			->setHtmlId('submit')
			->setAttribute('data-alt', 'Wait…');
	}

}
