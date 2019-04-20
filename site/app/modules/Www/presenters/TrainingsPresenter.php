<?php
namespace App\WwwModule\Presenters;

use MichalSpacekCz\CompanyInfo\Info;
use MichalSpacekCz\Form\TrainingApplication;
use MichalSpacekCz\Form\TrainingApplicationPreliminary;
use MichalSpacekCz\Formatter\Texy;
use MichalSpacekCz\Training\Applications;
use MichalSpacekCz\Training\CompanyTrainings;
use MichalSpacekCz\Training\Dates;
use MichalSpacekCz\Training\Files;
use MichalSpacekCz\Training\Locales;
use MichalSpacekCz\Training\Mails;
use MichalSpacekCz\Training\Reviews;
use MichalSpacekCz\Training\Trainings;
use MichalSpacekCz\Vat;
use Nette\Application\AbortException;
use Nette\Application\BadRequestException;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Database\Row;
use Nette\Http\IResponse;
use Nette\Utils\ArrayHash;
use Netxten\Templating\Helpers;
use OutOfBoundsException;
use PDOException;
use Tracy\Debugger;
use UnexpectedValueException;

class TrainingsPresenter extends BasePresenter
{

	/** @var Texy */
	protected $texyFormatter;

	/** @var Applications */
	protected $trainingApplications;

	/** @var Mails */
	protected $trainingMails;

	/** @var Dates */
	protected $trainingDates;

	/** @var Files */
	protected $trainingFiles;

	/** @var Trainings */
	protected $trainings;

	/** @var CompanyTrainings */
	protected $companyTrainings;

	/** @var Locales */
	protected $trainingLocales;

	/** @var Reviews */
	protected $trainingReviews;

	/** @var Vat */
	protected $vat;

	/** @var Helpers */
	protected $netxtenHelpers;

	/** @var Info */
	protected $companyInfo;

	/** @var IResponse */
	protected $httpResponse;

	/** @var Row */
	private $training;

	/** @var Row[] */
	private $dates;


	public function __construct(
		Texy $texyFormatter,
		Applications $trainingApplications,
		Mails $trainingMails,
		Dates $trainingDates,
		Files $trainingFiles,
		Trainings $trainings,
		CompanyTrainings $companyTrainings,
		Locales $trainingLocales,
		Reviews $trainingReviews,
		Vat $vat,
		Helpers $netxtenHelpers,
		Info $companyInfo,
		IResponse $httpResponse
	)
	{
		$this->texyFormatter = $texyFormatter;
		$this->trainingApplications = $trainingApplications;
		$this->trainingMails = $trainingMails;
		$this->trainingDates = $trainingDates;
		$this->trainingFiles = $trainingFiles;
		$this->trainings = $trainings;
		$this->companyTrainings = $companyTrainings;
		$this->trainingLocales = $trainingLocales;
		$this->trainingReviews = $trainingReviews;
		$this->vat = $vat;
		$this->netxtenHelpers = $netxtenHelpers;
		$this->companyInfo = $companyInfo;
		$this->httpResponse = $httpResponse;
		parent::__construct();
	}


	public function renderDefault()
	{
		$this->template->pageTitle = $this->translator->translate('messages.title.trainings');
		$this->template->upcomingTrainings = $this->trainingDates->getPublicUpcoming();
		$this->template->companyTrainings = $this->companyTrainings->getWithoutPublicUpcoming();
		$this->template->lastFreeSeats = $this->trainings->lastFreeSeatsAnyTraining($this->template->upcomingTrainings);
		$this->template->discontinued = $this->trainings->getAllDiscontinued();
	}


	/**
	 * @param string $name
	 * @throws BadRequestException
	 * @throws AbortException
	 */
	public function actionTraining($name)
	{
		$this->training = $this->trainings->get($name);
		if (!$this->training) {
			throw new BadRequestException("I don't do {$name} training, yet", IResponse::S404_NOT_FOUND);
		}

		if ($this->training->successorId !== null) {
			$this->redirect('training', $this->trainings->getActionById($this->training->successorId));
		}

		$this->dates = $this->trainings->getDates($this->training->trainingId);

		$session = $this->getSession();
		$session->start();  // in createComponentApplication() it's too late as the session cookie cannot be set because the output is already sent

		$this->template->name             = $this->training->action;
		$this->template->pageTitle        = $this->texyFormatter->translate('messages.title.training', [$this->training->name]);
		$this->template->title            = $this->training->name;
		$this->template->description      = $this->training->description;
		$this->template->content          = $this->training->content;
		$this->template->upsell           = $this->training->upsell;
		$this->template->prerequisites    = $this->training->prerequisites;
		$this->template->audience         = $this->training->audience;
		$this->template->capacity         = $this->training->capacity;
		$this->template->price            = $this->training->price;
		$this->template->priceVat         = $this->vat->addVat($this->training->price);
		$this->template->studentDiscount  = $this->training->studentDiscount;
		$this->template->materials        = $this->training->materials;
		$this->template->lastFreeSeats    = $this->trainingDates->lastFreeSeatsAnyDate($this->dates);
		$this->template->dates            = $this->dates;
		$this->template->dataRetention    = 30;

		$this->template->reviews = $this->trainingReviews->getVisibleReviews($this->training->trainingId, 3);

		$this->template->loadCompanyDataVisible = $this->companyInfo->isLoadCompanyDataVisible();

		if ($this->training->discontinuedId !== null) {
			$this->template->discontinued = [$this->trainings->getDiscontinued($this->training->discontinuedId)];
			$this->httpResponse->setCode(IResponse::S410_GONE);
		}
	}


	public function actionApplication($name, $param)
	{
		$training  = $this->trainings->get($name);
		if (!$training || $training->discontinuedId) {
			throw new BadRequestException("I don't do {$name} training", IResponse::S404_NOT_FOUND);
		}

		$session = $this->getSession('training');

		$application = $this->trainingApplications->getApplicationByToken($param);
		if (!$application) {
			unset(
				$session->application,
				$session->name,
				$session->email,
				$session->company,
				$session->street,
				$session->city,
				$session->zip,
				$session->country,
				$session->companyId,
				$session->companyTaxId,
				$session->note
			);
			$this->redirect('training', $name);
		}

		$data                 = (array)$session->application;
		$data[$name]          = array('id' => $application->applicationId, 'dateId' => $application->dateId);
		$session->application = $data;

		$session->name         = $application->name;
		$session->email        = $application->email;
		$session->company      = $application->company;
		$session->street       = $application->street;
		$session->city         = $application->city;
		$session->zip          = $application->zip;
		$session->country      = $application->country;
		$session->companyId    = $application->companyId;
		$session->companyTaxId = $application->companyTaxId;
		$session->note         = $application->note;

		$this->redirect('training', $application->trainingAction);
	}


	protected function createComponentApplication($formName)
	{
		$form = new TrainingApplication($this, $formName, $this->dates, $this->translator, $this->netxtenHelpers);
		$form->setApplicationFromSession($this->session->getSection('training'));
		$form->onSuccess[] = [$this, 'submittedApplication'];
	}


	public function submittedApplication(TrainingApplication $form, $values)
	{
		$session = $this->getSession('training');
		/** @var string $name */
		$name = $this->training->action;

		try {
			$this->checkSpam($values, $name);
			$this->checkTrainingDate($values, $name);

			$date = $this->dates[$values->trainingId];
			if ($date->tentative) {
				$this->trainingApplications->addInvitation(
					$this->training,
					$values->trainingId,
					$values->name,
					$values->email,
					$values->company,
					$values->street,
					$values->city,
					$values->zip,
					$values->country,
					$values->companyId,
					$values->companyTaxId,
					$values->note
				);
			} else {
				if (isset($session->application[$name]) && $session->application[$name]['dateId'] == $values->trainingId) {
					$applicationId = $this->trainingApplications->updateApplication(
						$this->training,
						$session->application[$name]['id'],
						$values->name,
						$values->email,
						$values->company,
						$values->street,
						$values->city,
						$values->zip,
						$values->country,
						$values->companyId,
						$values->companyTaxId,
						$values->note
					);
					$session->application[$name] = null;
				} else {
					$applicationId = $this->trainingApplications->addApplication(
						$this->training,
						$values->trainingId,
						$values->name,
						$values->email,
						$values->company,
						$values->street,
						$values->city,
						$values->zip,
						$values->country,
						$values->companyId,
						$values->companyTaxId,
						$values->note
					);
				}
				/** @var Template $template */
				$template = $this->createTemplate();
				$this->trainingMails->sendSignUpMail(
					$applicationId,
					$template,
					$values->email,
					$values->name,
					$date->start,
					$date->end,
					$name,
					$this->training->name,
					$date->venueName,
					$date->venueNameExtended,
					$date->venueAddress,
					$date->venueCity
				);
			}
			$session->trainingId   = $values->trainingId;
			$session->name         = $values->name;
			$session->email        = $values->email;
			$session->company      = $values->company;
			$session->street       = $values->street;
			$session->city         = $values->city;
			$session->zip          = $values->zip;
			$session->country      = $values->country;
			$session->companyId    = $values->companyId;
			$session->companyTaxId = $values->companyTaxId;
			$session->note         = $values->note;
			$this->redirect('success', $name);
		} catch (UnexpectedValueException $e) {
			Debugger::log($e);
			$this->flashMessage($this->translator->translate('messages.trainings.spammyapplication'), 'error');
		} catch (PDOException $e) {
			Debugger::log($e, Debugger::ERROR);
			$this->flashMessage($this->translator->translate('messages.trainings.errorapplication'), 'error');
		}
	}


	protected function createComponentApplicationPreliminary($formName)
	{
		if ($this->training->discontinuedId) {
			throw new BadRequestException("No signups for discontinued trainings id {$this->training->discontinuedId}", IResponse::S404_NOT_FOUND);
		}
		$form = new TrainingApplicationPreliminary($this, $formName, $this->translator);
		$form->onSuccess[] = [$this, 'submittedApplicationPreliminary'];
	}


	public function submittedApplicationPreliminary(TrainingApplicationPreliminary $form, $values)
	{
		$this->trainingApplications->addPreliminaryInvitation($this->training, $values->name, $values->email);
		$this->flashMessage($this->translator->translate('messages.trainings.submitted.preliminary'));
		$this->redirect('training#' . $this->translator->translate('html.id.application'), $this->training->action);
	}


	private function checkTrainingDate(ArrayHash $values, $name)
	{
		if (!isset($this->dates[$values->trainingId])) {
			$this->logData($values, $name);
			$message = "Training date id {$values->trainingId} is not an upcoming training, should be one of " . implode(', ', array_keys($this->dates));
			throw new OutOfBoundsException($message);
		}
	}


	private function checkSpam(ArrayHash $values, $name)
	{
		if (preg_match('~\s+href="\s*https?://~', $values->note)) {
			$this->logData($values, $name);
			throw new UnexpectedValueException('Spammy note: ' . $values->note);
		}
	}


	private function logData(ArrayHash $values, $name)
	{
		$session = $this->getSession('training');
		$logValues = $logSession = array();
		if (isset($session->application[$name])) {
			foreach ($session->application[$name] as $key => $value) {
				$logSession[] = "{$key} => \"{$value}\"";
			}
		}
		foreach ($values as $key => $value) {
			$logValues[] = "{$key} => \"{$value}\"";
		}
		$message = sprintf('Application session data for %s: %s, form values: %s',
			$name,
			(empty($logSession) ? 'empty' : implode(', ', $logSession)),
			implode(', ', $logValues)
		);
		Debugger::log($message);
	}


	/**
	 * @param string $name
	 * @param string|null $param
	 * @throws BadRequestException
	 */
	public function actionReviews($name, $param)
	{
		if ($param !== null) {
			throw new BadRequestException('No param here, please', IResponse::S404_NOT_FOUND);
		}

		$training = $this->trainings->get($name);
		if (!$training) {
			throw new BadRequestException("I don't do {$name} training, yet", IResponse::S404_NOT_FOUND);
		}

		$this->template->name             = $training->action;
		$this->template->pageTitle        = $this->texyFormatter->translate('messages.title.trainingreviews', [$training->name]);
		$this->template->title            = $training->name;
		$this->template->description      = $training->description;
		$this->template->reviews = $this->trainingReviews->getVisibleReviews($training->trainingId);

		if ($training->discontinuedId !== null) {
			$this->template->discontinued = [$this->trainings->getDiscontinued($training->discontinuedId)];
			$this->httpResponse->setCode(IResponse::S410_GONE);
		}
	}


	public function actionFiles($name, $param)
	{
		$session = $this->getSession('application');

		if ($param !== null) {
			$application = $this->trainingApplications->getApplicationByToken($param);
			$session->token = $param;
			$session->applicationId = ($application ? $application->applicationId : null);
			$this->redirect('files', ($application ? $application->trainingAction : $name));
		}

		if (!$session->applicationId || !$session->token) {
			throw new BadRequestException("Unknown application id, missing or invalid token", IResponse::S404_NOT_FOUND);
		}

		$training = $this->trainings->getIncludingCustom($name);
		if (!$training) {
			throw new BadRequestException("I don't do {$name} training, yet", IResponse::S404_NOT_FOUND);
		}

		$application = $this->trainingApplications->getApplicationById($session->applicationId);
		if (!$application) {
			throw new BadRequestException("No training application for id {$session->applicationId}", IResponse::S404_NOT_FOUND);
		}

		if ($application->trainingAction != $name) {
			$this->redirect('files', $application->trainingAction);
		}

		$files = $this->trainingFiles->getFiles($application->applicationId);
		$this->trainingApplications->setAccessTokenUsed($application);
		if (!$files) {
			throw new BadRequestException("No files for application id {$session->applicationId}", IResponse::S404_NOT_FOUND);
		}

		$this->template->trainingTitle = $training->name;
		$this->template->trainingName = ($training->custom ? null : $training->action);
		$this->template->trainingStart = $application->trainingStart;
		$this->template->trainingEnd = $application->trainingEnd;
		$this->template->familiar = $application->familiar;

		$this->template->pageTitle = $this->texyFormatter->translate('messages.title.trainingmaterials', [$training->name]);
		$this->template->files = $files;
	}


	/**
	 * @param string $name
	 * @param string|null $param
	 * @throws BadRequestException
	 * @throws AbortException
	 */
	public function actionSuccess($name, $param)
	{
		if ($param !== null) {
			throw new BadRequestException('No param here, please', IResponse::S404_NOT_FOUND);
		}

		$training = $this->trainings->get($name);
		if (!$training || $training->discontinuedId) {
			throw new BadRequestException("I don't do {$name} training", IResponse::S404_NOT_FOUND);
		}
		$this->dates = $this->trainings->getDates($training->trainingId);
		if (empty($this->dates)) {
			throw new BadRequestException("No dates for {$name} training", IResponse::S503_SERVICE_UNAVAILABLE);
		}

		$session = $this->getSession('training');
		if (!isset($session->trainingId)) {
			$this->redirect('training', $name);
		}

		if (!isset($this->dates[$session->trainingId])) {
			$date = $this->trainingDates->get($session->trainingId);
			$this->redirect('success', $date->action);
		}

		$date = $this->dates[$session->trainingId];
		if ($date->tentative) {
			$this->flashMessage($this->translator->translate('messages.trainings.submitted.tentative'));
		} else {
			$this->flashMessage($this->translator->translate('messages.trainings.submitted.confirmed'));
		}

		$this->template->name             = $training->action;
		$this->template->pageTitle        = $this->texyFormatter->translate('messages.title.trainingapplication', [$training->name]);
		$this->template->title            = $training->name;
		$this->template->description      = $training->description;
		$this->template->lastFreeSeats    = false;
		$this->template->start            = $date->start;
		$this->template->end              = $date->end;
		$this->template->venueCity        = $date->venueCity;
		$this->template->tentative        = $date->tentative;

		$upcoming = $this->trainingDates->getPublicUpcoming();
		unset($upcoming[$name]);
		$this->template->upcomingTrainings = $upcoming;

		$this->template->form = $this->createComponentApplication('application');
		$this->template->reviews = $this->trainingReviews->getVisibleReviews($training->trainingId, 3);
	}


	/**
	 * Translated locale parameters for trainings.
	 *
	 * @return array
	 */
	protected function getLocaleLinkParams(): array
	{
		if ($this->getAction() === 'default') {
			return parent::getLocaleLinkParams();
		} else {
			$params = [];
			foreach ($this->trainingLocales->getLocaleActions($this->getParameter('name')) as $key => $value) {
				$params[$key] = ['name' => $value];
			}
			return $params;
		}
	}

}
