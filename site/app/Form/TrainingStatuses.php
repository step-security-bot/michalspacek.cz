<?php
declare(strict_types = 1);

namespace MichalSpacekCz\Form;

use Nette\ComponentModel\IContainer;
use Nette\Database\Row;
use Nette\Localization\Translator;

class TrainingStatuses extends ProtectedForm
{

	private Translator $translator;


	/**
	 * @param IContainer $parent
	 * @param string $name
	 * @param Row[] $applications
	 * @param TrainingControlsFactory $trainingControlsFactory
	 * @param Translator $translator
	 */
	public function __construct(IContainer $parent, string $name, array $applications, TrainingControlsFactory $trainingControlsFactory, Translator $translator)
	{
		parent::__construct($parent, $name);
		$this->translator = $translator;

		$container = $this->addContainer('applications');

		foreach ($applications as $application) {
			$select = $container->addSelect((string)$application->id, 'Status')
				->setPrompt('- změnit na -')
				->setItems($application->childrenStatuses, false);
			if (empty($application->childrenStatuses)) {
				$select->setDisabled()
					->setPrompt('nelze dále měnit');
			}
		}
		$trainingControlsFactory->addStatusDate($this, 'date', 'Datum:', true);
		$this->addSubmit('submit', 'Změnit');
		$this->addSubmit('familiar', 'Tykat všem')->setValidationScope([]);
	}

}
