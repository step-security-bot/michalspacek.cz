<?php
declare(strict_types = 1);

namespace MichalSpacekCz\Form\Controls;

trait Date
{

	/**
	 * Adds date input control to the form.
	 * @param string $name
	 * @param string $label
	 * @param boolean $required
	 * @param string $format Format for the title attribute
	 * @param string $pattern Validation pattern
	 * @param \Nette\Forms\Container|null $container
	 * @return \Nette\Forms\Controls\TextInput
	 */
	protected function addDate(string $name, string $label, bool $required, string $format, string $pattern, ?\Nette\Forms\Container $container = null): \Nette\Forms\Controls\TextInput
	{
		return ($container === null ? $this : $container)->addText($name, $label)
			->setHtmlAttribute('placeholder', $format)
			->setHtmlAttribute('title', "Formát {$format}")
			->setRequired($required ? 'Zadejte datum' : false)
			->addRule(self::PATTERN, "Datum musí být ve formátu {$format}", $pattern);
	}

}
