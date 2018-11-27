<?php
declare(strict_types = 1);

namespace MichalSpacekCz\Form\Controls;

/**
 * Training country trait.
 *
 * @author Michal Špaček
 * @package michalspacek.cz
 */
trait TrainingCountry
{

	/**
	 * Add country input.
	 *
	 * @param \Nette\Forms\Container $container
	 */
	protected function addCountry(\Nette\Forms\Container $container): void
	{
		$container->addSelect('country', 'Země:', ['cz' => 'Česká republika', 'sk' => 'Slovensko'])
			->setRequired('Vyberte prosím zemi');
	}

}
