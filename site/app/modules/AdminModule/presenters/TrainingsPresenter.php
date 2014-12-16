<?php
namespace AdminModule;

use \MichalSpacekCz\Training;
use \Nette\Application\UI\Form;

/**
 * Trainings presenter.
 *
 * @author     Michal Špaček
 * @package    michalspacek.cz
 */
class TrainingsPresenter extends BasePresenter
{

	/** @var \MichalSpacekCz\Training\Applications */
	protected $trainingApplications;

	/** @var \MichalSpacekCz\Training\Dates */
	protected $trainingDates;

	/** @var \MichalSpacekCz\Training\Statuses */
	protected $trainingStatuses;

	/** @var \MichalSpacekCz\Training\Trainings */
	protected $trainings;

	/** @var \MichalSpacekCz\Training\Venues */
	protected $trainingVenues;

	/** @var \MichalSpacekCz\Training\Files */
	protected $trainingFiles;

	/** @var array */
	private $dates;

	/** @var array */
	private $applications;

	/** @var array */
	private $applicationIdsAttended;

	/** @var \Nette\Database\Row */
	private $application;

	private $applicationId;

	/** @var \Nette\Database\Row */
	private $review;

	/** @var \Nette\Database\Row */
	private $training;

	private $dateId;

	private $redirectParam;


	/**
	 * @param \Nette\Localization\ITranslator $translator
	 * @param \MichalSpacekCz\Training\Applications $trainingApplications
	 * @param \MichalSpacekCz\Training\Dates $trainingDates
	 * @param \MichalSpacekCz\Training\Statuses $trainingStatuses
	 * @param \MichalSpacekCz\Training\Trainings $trainings
	 * @param \MichalSpacekCz\Training\Venues $trainingVenues
	 * @param \MichalSpacekCz\Training\Files $trainingFiles
	 */
	public function __construct(
		\Nette\Localization\ITranslator $translator,
		Training\Applications $trainingApplications,
		Training\Dates $trainingDates,
		Training\Statuses $trainingStatuses,
		Training\Trainings $trainings,
		Training\Venues $trainingVenues,
		Training\Files $trainingFiles
	)
	{
		$this->trainingApplications = $trainingApplications;
		$this->trainingDates = $trainingDates;
		$this->trainingStatuses = $trainingStatuses;
		$this->trainings = $trainings;
		$this->trainingVenues = $trainingVenues;
		$this->trainingFiles = $trainingFiles;
		parent::__construct($translator);
	}


	private function addDate($form, $name, $label, $required = true, $defaultValue = null)
	{
		$text = $form->addText($name, $label);
		if ($defaultValue) {
			$text->setDefaultValue($defaultValue);
		}
		$text
			->setAttribute('placeholder', 'YYYY-MM-DD HH:MM:SS nebo DD.MM.YYYY HH:MM:SS nebo NOW')
			->setAttribute('title', 'Formát YYYY-MM-DD HH:MM:SS nebo DD.MM.YYYY HH:MM:SS nebo NOW')
			->addCondition(Form::FILLED)
			->addRule(Form::PATTERN, 'Datum musí být ve formátu YYYY-MM-DD HH:MM:SS nebo DD.MM.YYYY HH:MM:SS nebo NOW', '(\d{4}-\d{1,2}-\d{1,2} \d{1,2}:\d{2}:\d{2})|(\d{1,2}\.\d{1,2}\.\d{4} \d{1,2}:\d{2}:\d{2})|[Nn][Oo][Ww]');
		if ($required) {
			$text->setRequired('Zadejte datum');
		}
	}


	public function actionDate($param)
	{
		$this->dateId = $param;
		$this->redirectParam = $this->dateId;
		$this->training = $this->trainingDates->get($this->dateId);
		$this->applications = $this->trainingApplications->getByDate($this->dateId);
		$attendedStatuses = $this->trainingStatuses->getAttendedStatuses();
		$discardedStatuses = $this->trainingStatuses->getDiscardedStatuses();
		foreach ($this->applications as $application) {
			$application->discarded = in_array($application->status, $discardedStatuses);
			$application->attended = in_array($application->status, $attendedStatuses);
			if ($application->attended) {
				$this->applicationIdsAttended[] = $application->id;
			}
			$application->childrenStatuses = $this->trainingStatuses->getChildrenStatuses($application->status);
		}

		$this->template->pageTitle     = 'Účastníci';
		$this->template->trainingStart = $this->training->start;
		$this->template->trainingName  = $this->training->name;
		$this->template->venueCity     = $this->training->venueCity;
		$this->template->venueName     = $this->training->venueName;
		$this->template->public        = $this->training->public;
		$this->template->applications  = $this->applications;
		$this->template->attendedStatuses = $attendedStatuses;
	}


	public function actionFiles($param)
	{
		$this->applicationId = $param;
		$this->redirectParam = $this->applicationId;
		$application = $this->trainingApplications->getApplicationById($this->applicationId);
		if (!in_array($application->status, $this->trainingStatuses->getAttendedStatuses())) {
			$this->redirect('date', $application->dateId);
		}

		$this->applicationIdsAttended = array($application->applicationId);

		$files = $this->trainingFiles->getFiles($this->applicationId);
		foreach ($files as $file) {
			$file->exists = file_exists("{$file->dirName}/{$file->fileName}");
		}

		$this->training = $this->trainingDates->get($application->dateId);

		$this->template->pageTitle = 'Soubory';
		$this->template->files     = $files;
		$this->template->trainingStart = $this->training->start;
		$this->template->trainingName  = $this->training->name;
		$this->template->trainingCity  = $this->training->venueCity;
		$this->template->name          = $application->name;
		$this->template->dateId        = $application->dateId;
	}


	public function actionReview($param)
	{
		$this->applicationId = $param;
		$this->review = $this->trainings->getReviewByApplicationId($this->applicationId);

		$date = $this->trainingDates->get($this->review->dateId);

		$this->template->pageTitle          = 'Ohlasy';
		$this->template->applicationName    = $this->review->applicationName;
		$this->template->applicationCompany = $this->review->applicationCompany;
		$this->template->trainingStart      = $date->start;
		$this->template->trainingName       = $date->name;
		$this->template->trainingCity  = $date->venueCity;
		$this->template->name          = $this->review->applicationName;
		$this->template->dateId        = $this->review->dateId;
	}


	public function actionApplication($param)
	{
		$this->applicationId = $param;
		$this->application = $this->trainingApplications->getApplicationById($this->applicationId);
		$this->dateId = $this->application->dateId;
		$this->training = $this->trainingDates->get($this->application->dateId);

		$this->template->pageTitle     = "{$this->application->name}";
		$this->template->applicationId = $this->applicationId;
		$this->template->dateId        = $this->dateId;
		$this->template->status        = $this->application->status;
		$this->template->statusTime    = $this->application->statusTime;
		$this->template->trainingName  = $this->training->name;
		$this->template->trainingStart = $this->training->start;
		$this->template->trainingCity  = $this->training->venueCity;
		$this->template->attended      = in_array($this->application->status, $this->trainingStatuses->getAttendedStatuses());
	}


	public function renderDefault()
	{
		$trainings = $this->trainings->getAllTrainings();
		foreach ($trainings as $training) {
			$training->applications = $this->trainingApplications->getValidByDate($training->dateId);
		}

		$this->template->pageTitle = 'Školení';
		$this->template->trainings = $trainings;
		$this->template->now = new \DateTime();
		$this->template->upcomingIds = $this->trainingDates->getPublicUpcomingIds();
	}


	protected function createComponentStatuses($formName)
	{
		$form = new Form($this, $formName);
		$container = $form->addContainer('applications');

		foreach ($this->applications as $application) {
			$select = $container->addSelect($application->id, 'Status')
				->setPrompt('- změnit na -')
				->setItems($application->childrenStatuses, false);
			if (empty($application->childrenStatuses)) {
				$select->setDisabled()
					->setPrompt('nelze dále měnit');
			}
		}

		$this->addDate($form, 'date', 'Datum:');
		$form->addSubmit('submit', 'Změnit');
		$form->onSuccess[] = $this->submittedStatuses;

		return $form;
	}


	public function submittedStatuses($form)
	{
		$values = $form->getValues();
		foreach ($values->applications as $id => $status) {
			if ($status) {
				$this->trainingStatuses->updateStatus($id, $status, $values->date);
			}
		}
		$this->redirect($this->getAction(), $this->dateId);
	}


	protected function createComponentApplications($formName)
	{
		$rules = $this->trainingApplications->getDataRules();

		$sources = array();
		foreach ($this->trainingApplications->getTrainingApplicationSources() as $source) {
			$sources[$source->alias] = $source->name;
		}

		$statuses = array();
		foreach ($this->trainingStatuses->getInitialStatuses() as $status) {
			$statuses[$status] = $status;
		}

		$form = new Form($this, $formName);

		$applicationsContainer = $form->addContainer('applications');
		$count = (isset($_POST['applications']) ? count($_POST['applications']) : 1);
		for ($i = 0; $i < $count; $i++) {
			$dataContainer = $applicationsContainer->addContainer($i);
			$dataContainer->addText('name', 'Jméno')
				->setRequired('Zadejte prosím jméno')
				->addRule(Form::MIN_LENGTH, 'Minimální délka jména je %d znaky', $rules['name'][Form::MIN_LENGTH])
				->addRule(Form::MAX_LENGTH, 'Maximální délka jména je %d znaků', $rules['name'][Form::MAX_LENGTH]);
			$dataContainer->addText('email', 'E-mail')
				->setRequired('Zadejte prosím e-mailovou adresu')
				->addRule(Form::EMAIL, 'Zadejte platnou e-mailovou adresu')
				->addRule(Form::MAX_LENGTH, 'Maximální délka e-mailu je %d znaků', $rules['email'][Form::MAX_LENGTH]);
			$dataContainer->addText('company', 'Společnost')
				->addCondition(Form::FILLED)
				->addRule(Form::MIN_LENGTH, 'Minimální délka společnosti je %d znaky', $rules['company'][Form::MIN_LENGTH])
				->addRule(Form::MAX_LENGTH, 'Maximální délka společnosti je %d znaků', $rules['company'][Form::MAX_LENGTH]);
			$dataContainer->addText('street', 'Ulice')
				->addCondition(Form::FILLED)
				->addRule(Form::MIN_LENGTH, 'Minimální délka ulice je %d znaky', $rules['street'][Form::MIN_LENGTH])
				->addRule(Form::MAX_LENGTH, 'Maximální délka ulice je %d znaků', $rules['street'][Form::MAX_LENGTH]);
			$dataContainer->addText('city', 'Město')
				->addCondition(Form::FILLED)
				->addRule(Form::MIN_LENGTH, 'Minimální délka města je %d znaky', $rules['city'][Form::MIN_LENGTH])
				->addRule(Form::MAX_LENGTH, 'Maximální délka města je %d znaků', $rules['city'][Form::MAX_LENGTH]);
			$dataContainer->addText('zip', 'PSČ')
				->addCondition(Form::FILLED)
				->addRule(Form::PATTERN, 'PSČ musí mít 5 číslic', $rules['zip'][Form::PATTERN])
				->addRule(Form::MAX_LENGTH, 'Maximální délka PSČ je %d znaků', $rules['zip'][Form::MAX_LENGTH]);
			$dataContainer->addText('companyId', 'IČ')
				->addCondition(Form::FILLED)
				->addRule(Form::MIN_LENGTH, 'Minimální délka IČ je %d znaky', $rules['companyId'][Form::MIN_LENGTH])
				->addRule(Form::MAX_LENGTH, 'Maximální délka IČ je %d znaků', $rules['companyId'][Form::MAX_LENGTH]);
			$dataContainer->addText('companyTaxId', 'DIČ')
				->addCondition(Form::FILLED)
				->addRule(Form::MIN_LENGTH, 'Minimální délka DIČ je %d znaky', $rules['companyTaxId'][Form::MIN_LENGTH])
				->addRule(Form::MAX_LENGTH, 'Maximální délka DIČ je %d znaků', $rules['companyTaxId'][Form::MAX_LENGTH]);
			$dataContainer->addText('note', 'Poznámka')
				->addCondition(Form::FILLED)
				->addRule(Form::MAX_LENGTH, 'Maximální délka poznámky je %d znaků', $rules['note'][Form::MAX_LENGTH]);
		}

		$this->addDate($form, 'date', 'Datum:');
		$form->addSelect('status', 'Status:', $statuses)
			->setRequired('Vyberte status')
			->setPrompt('- vyberte status -');
		$form->addSelect('source', 'Zdroj:', $sources)
			->setRequired('Vyberte zdroj')
			->setPrompt('- vyberte zdroj -');

		$form->addSubmit('submit', 'Přidat');
		$form->onSuccess[] = $this->submittedApplications;

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


	public function submittedApplications($form)
	{
		$values = $form->getValues();
		foreach ($values->applications as $application) {
			$this->trainingApplications->insertApplication(
				$this->training,
				$this->dateId,
				$application->name,
				$application->email,
				$application->company,
				$application->street,
				$application->city,
				$application->zip,
				$application->companyId,
				$application->companyTaxId,
				$application->note,
				$values->status,
				$values->source,
				$values->date
			);
		}
		$this->redirect($this->getAction(), $this->dateId);
	}


	protected function createComponentReview($formName)
	{
		$form = new Form($this, $formName);
		$form->addCheckbox('overwriteName', 'Přepsat jméno:')
			->setDefaultValue($this->review->name != null);
		$form->addText('name', 'Jméno:')
			->setDefaultValue($this->review->name);
		$form->addCheckbox('overwriteCompany', 'Přepsat firmu:')
			->setDefaultValue($this->review->company != null);
		$form->addText('company', 'Firma:')
			->setDefaultValue($this->review->company);
		$form->addTextArea('review', 'Ohlas:')
			->setDefaultValue($this->review->review);
		$form->addText('href', 'Odkaz:')
			->setDefaultValue($this->review->href);
		$form->addCheckbox('hidden', 'Skrýt:')
			->setDefaultValue($this->review->hidden);
		$form->addSubmit('save', 'Uložit');
		$form->onSuccess[] = $this->submittedReview;

		return $form;
	}


	public function submittedReview($form)
	{
		$values = $form->getValues();

		$this->trainings->addUpdateReview(
			$this->applicationId,
			$values->overwriteName ? $values->name : null,
			$values->overwriteCompany ? $values->company : null,
			$values->review,
			$values->href,
			$values->hidden
		);

		$this->redirect('date', $this->review->dateId);
	}


	protected function createComponentApplication($formName)
	{
		$form = new \MichalSpacekCz\Form\TrainingApplicationAdmin($this, $formName);
		$form->setApplication($this->application);
		$form->onSuccess[] = $this->submittedApplication;
		return $form;
	}


	public function submittedApplication(\MichalSpacekCz\Form\TrainingApplicationAdmin $form)
	{
		$values = $form->getValues();

		$this->trainingApplications->updateApplicationData(
			$this->applicationId,
			$values->name,
			$values->email,
			$values->company,
			$values->street,
			$values->city,
			$values->zip,
			$values->companyId,
			$values->companyTaxId,
			$values->note,
			$values->price,
			$values->vatRate / 100,
			$values->priceVat,
			$values->discount,
			$values->invoiceId,
			$values->paid,
			$values->familiar
		);
		$this->redirect('date', $this->dateId);
	}


	protected function createComponentFile($formName)
	{
		$form = new Form($this, $formName);
		$form->addUpload('file', 'Soubor:');
		$form->addSubmit('submit', 'Přidat');
		$form->onSuccess[] = $this->submittedFile;
		return $form;
	}


	public function submittedFile($form)
	{
		$values = $form->getValues();
		if ($values->file->isOk()) {
			$name = $this->trainingApplications->addFile($this->training, $values->file, $this->applicationIdsAttended);
			$this->flashMessage(
				\Nette\Utils\Html::el()->add('Soubor ')
					->add(\Nette\Utils\Html::el('code')->setText($name))
					->add(' byl přidán')
			);
		} else {
			$this->flashMessage('Soubor nebyl vybrán nebo došlo k nějaké chybě při nahrávání', 'error');
		}

		$this->redirect($this->getAction(), $this->redirectParam);
	}


	protected function createComponentDate($formName)
	{
		$form = new \MichalSpacekCz\Form\TrainingDate($this, $formName, $this->trainings, $this->trainingDates, $this->trainingVenues);
		$form->setTrainingDate($this->trainingDates->get($this->dateId));
		$form->onSuccess[] = $this->submittedDate;
	}


	public function submittedDate(\MichalSpacekCz\Form\TrainingDate $form)
	{
		$values = $form->getValues();
		$this->trainingDates->update(
			$this->dateId,
			$values->training,
			$values->venue,
			$values->start,
			$values->end,
			$values->status,
			$values->public,
			$values->cooperation
		);
		$this->flashMessage('Termín upraven');
		$this->redirect($this->getAction(), $this->redirectParam);
	}

}
