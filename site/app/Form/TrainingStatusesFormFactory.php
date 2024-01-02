<?php
declare(strict_types = 1);

namespace MichalSpacekCz\Form;

use MichalSpacekCz\Form\Controls\TrainingControlsFactory;
use MichalSpacekCz\Training\Applications\TrainingApplication;
use MichalSpacekCz\Training\Applications\TrainingApplications;
use MichalSpacekCz\Training\Statuses\Statuses;
use Nette\Utils\Html;

readonly class TrainingStatusesFormFactory
{

	public function __construct(
		private FormFactory $factory,
		private TrainingControlsFactory $trainingControlsFactory,
		private TrainingApplications $trainingApplications,
		private Statuses $trainingStatuses,
	) {
	}


	/**
	 * @param callable(Html|null): void $onSuccess
	 * @param list<TrainingApplication> $applications
	 */
	public function create(callable $onSuccess, array $applications): UiForm
	{
		$form = $this->factory->create();
		$container = $form->addContainer('applications');
		foreach ($applications as $application) {
			$select = $container->addSelect((string)$application->getId(), 'Status')
				->setPrompt('- změnit na -')
				->setItems($application->getChildrenStatuses(), false);
			if (empty($application->getChildrenStatuses())) {
				$select->setDisabled()
					->setPrompt('nelze dále měnit');
			}
		}
		$this->trainingControlsFactory->addStatusDate($form->addText('date', 'Datum:'), true);
		$submitStatuses = $form->addSubmit('submit', 'Změnit');
		$submitFamiliar = $form->addSubmit('familiar', 'Tykat všem')->setValidationScope([]);

		$submitStatuses->onClick[] = function () use ($form, $onSuccess): void {
			$values = $form->getFormValues();
			foreach ($values->applications as $id => $status) {
				if ($status) {
					$this->trainingStatuses->updateStatus($id, $status, $values->date);
				}
			}
			$onSuccess(null);
		};
		$submitFamiliar->onClick[] = function () use ($form, $onSuccess): void {
			$attendedStatuses = $this->trainingStatuses->getAttendedStatuses();
			$total = 0;
			foreach (array_keys((array)$form->getUntrustedFormValues()->applications) as $id) {
				$application = $this->trainingApplications->getApplicationById($id);
				if (in_array($application->getStatus(), $attendedStatuses) && !$application->isFamiliar()) {
					$this->trainingApplications->setFamiliar($id);
					$total++;
				}
			}

			$statuses = [];
			foreach ($attendedStatuses as $status) {
				$statuses[] = Html::el('code')->setText($status);
			}
			$message = Html::el()
				->setText('Tykání nastaveno pro ' . $total . ' účastníků ve stavu ')
				->addHtml(implode(', ', $statuses));
			$onSuccess($message);
		};

		return $form;
	}

}
