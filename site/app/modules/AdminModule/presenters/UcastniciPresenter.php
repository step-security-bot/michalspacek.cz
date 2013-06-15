<?php
namespace AdminModule;

use \MichalSpacekCz\Trainings;
use \Nette\Application\UI\Form;

/**
 * Ucastnici presenter.
 *
 * @author     Michal Špaček
 * @package    michalspacek.cz
 */
class UcastniciPresenter extends \BasePresenter
{

	/** @var array */
	private $dates;

	/** @var array */
	private $hasFiles = array(
		Trainings::STATUS_ATTENDED,
		Trainings::STATUS_MATERIALS_SENT,
		Trainings::STATUS_ACCESS_TOKEN_USED,
	);


	public function actionNovy()
	{
		$this->template->pageTitle = 'Nový účastník';
	}


	public function actionTermin($param)
	{
		$applications = $this->trainingApplications->getByDate($param);
		foreach ($applications as $application) {
			$application->hasFiles = in_array($application->status, $this->hasFiles);
		}

		$this->template->pageTitle = 'Účastníci zájezdu';
		$this->template->applications = $applications;
	}


	public function actionSoubory($param)
	{
		$application = $this->trainings->getApplicationById($param);
		if (!in_array($application->status, $this->hasFiles)) {
			$this->redirect('termin', $application->dateId);
		}

		$files = $this->trainings->getFiles($param);
		foreach ($files as $file) {
			$file->exists = file_exists("{$file->dirName}/{$file->fileName}");
		}

		$this->template->pageTitle = 'Soubory';
		$this->template->files = $files;
	}


	public function renderDefault()
	{
		$this->template->pageTitle = 'Import';
		$this->template->trainings = $this->trainings->getAllTrainings();
	}


	protected function createComponentApplication($formName)
	{
		$helpers = new \Bare\Next\Templating\Helpers();

		$this->dates = $this->trainings->getUpcoming();
		foreach ($this->dates as $training) {
			foreach ($training->dates as $date) {
				if ($date->tentative) {
					$start = $helpers->localDate($date->start, 'cs', '%B %Y');
				} else {
					$start = \Nette\Templating\Helpers::date($date->start, 'j. n. Y');
				}
				$dates[$date->dateId] = "{$start} {$date->venueCity}" . ($date->tentative ? ' (předběžný termín)' : '') . " - {$training->name}";
			}
		}

		$sources = array();
		foreach ($this->trainings->getTrainingApplicationSources() as $source) {
			$sources[$source->alias] = $source->name;
		}

		$statuses = array();
		foreach ($this->trainings->getInitialStatuses() as $status) {
			$statuses[$status] = $status;
		}

		$session = $this->getSession('application');

		$form = new Form($this, $formName);
		$form->addSelect('trainingId', 'Termín školení:', $dates)
			->setDefaultValue($session->trainingId)
			->setRequired('Vyberte prosím termín a místo školení')
			->setPrompt('- vyberte termín a místo -');

		$form->addGroup('Účastník');
		$form->addText('name', 'Jméno a příjmení:')
			->setRequired('Zadejte prosím jméno a příjmení')
			->addRule(Form::MIN_LENGTH, 'Minimální délka jména a příjmení je %d znaky', 3)
			->addRule(Form::MAX_LENGTH, 'Maximální délka jména a příjmení je %d znaků', 200);
		$form->addText('email', 'E-mail:')
			->setRequired('Zadejte prosím e-mailovou adresu')
			->addRule(Form::EMAIL, 'Zadejte platnou e-mailovou adresu')
			->addRule(Form::MAX_LENGTH, 'Maximální délka e-mailu je %d znaků', 200);

		$form->addGroup('Fakturační údaje');
		$form->addText('company', 'Obchodní jméno:')
			->addCondition(Form::FILLED)
			->addRule(Form::MIN_LENGTH, 'Minimální délka obchodního jména je %d znaky', 3)
			->addRule(Form::MAX_LENGTH, 'Maximální délka obchodního jména je %d znaků', 200);
		$form->addText('street', 'Ulice a číslo:')
			->addCondition(Form::FILLED)
			->addRule(Form::MIN_LENGTH, 'Minimální délka ulice a čísla je %d znaky', 3)
			->addRule(Form::MAX_LENGTH, 'Maximální délka ulice a čísla je %d znaků', 200);
		$form->addText('city', 'Město:')
			->addCondition(Form::FILLED)
			->addRule(Form::MIN_LENGTH, 'Minimální délka města je %d znaky', 2)
			->addRule(Form::MAX_LENGTH, 'Maximální délka města je %d znaků', 200);
		$form->addText('zip', 'PSČ:')
			->addCondition(Form::FILLED)
			->addRule(Form::PATTERN, 'PSČ musí mít 5 číslic', '([0-9]\s*){5}')
			->addRule(Form::MAX_LENGTH, 'Maximální délka PSČ je %d znaků', 200);
		$form->addText('companyId', 'IČ:')
			->addCondition(Form::FILLED)
			->addRule(Form::MIN_LENGTH, 'Minimální délka IČ je %d znaky', 6)
			->addRule(Form::MAX_LENGTH, 'Maximální délka IČ je %d znaků', 200);
		$form->addText('companyTaxId', 'DIČ:')
			->addCondition(Form::FILLED)
			->addRule(Form::MIN_LENGTH, 'Minimální délka DIČ je %d znaky', 6)
			->addRule(Form::MAX_LENGTH, 'Maximální délka DIČ je %d znaků', 200);

		$form->setCurrentGroup(null);
		$form->addText('note', 'Poznámka:')
			->addCondition(Form::FILLED)
			->addRule(Form::MAX_LENGTH, 'Maximální délka poznámky je %d znaků', 2000);
		$form->addSelect('status', 'Status:', $statuses)
			->setDefaultValue($session->status)
			->setRequired('Vyberte status')
			->setPrompt('- vyberte status -');
		$form->addSelect('source', 'Zdroj:', $sources)
			->setDefaultValue($session->source)
			->setRequired('Vyberte zdroj')
			->setPrompt('- vyberte zdroj -');

		$form->addSubmit('signUp', 'Odeslat');
		$form->onSuccess[] = new \Nette\Callback($this, 'submittedApplication');

		return $form;
	}


	private function findDate($dateId)
	{
		foreach ($this->dates as $training) {
			foreach ($training->dates as $date) {
				if ($date->dateId == $dateId) {
					return $date;
				}
			}
		}
		return false;
	}


	public function submittedApplication($form)
	{
		$session = $this->getSession('application');

		$values = $form->getValues();
		$date = $this->findDate($values->trainingId);
		try {
			$this->trainings->insertApplication(
				$values->trainingId,
				$values->name,
				$values->email,
				$values->company,
				$values->street,
				$values->city,
				$values->zip,
				$values->companyId,
				$values->companyTaxId,
				$values->note,
				($date->tentative ? Trainings::STATUS_TENTATIVE : $values->status),
				$values->source
			);
			$session->trainingId = $values->trainingId;
			$session->status     = $values->status;
			$session->source     = $values->source;
			$this->redirect($this->getAction());
		} catch (PDOException $e) {
			Debugger::log($e, Debugger::ERROR);
			$this->flashMessage('Ups, něco se rozbilo a přihlášku se nepodařilo odeslat, zkuste to za chvíli znovu.', 'error');
		}
	}


}
