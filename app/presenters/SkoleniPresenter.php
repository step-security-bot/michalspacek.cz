<?php
use \Nette\Application\UI\Form,
	\Nette\Diagnostics\Debugger;

/**
 * Školení presenter.
 *
 * @author     Michal Špaček
 * @package    michalspacek.cz
 */
class SkoleniPresenter extends BasePresenter
{
	/**
	 * Currently processed training info.
	 *
	 * @var boolean
	 */
	private $tentative;


	/**
	 * Past trainings done by Jakub.
	 *
	 * @var array
	 */
	protected $pastTrainingsJakub = array(
		'uvodDoPhp' => array(
			'2011-09-07', '2011-04-20',
			'2010-12-01', '2010-03-02',
			'2009-12-01', '2009-09-14', '2009-06-25', '2009-04-22', '2009-01-20',
			'2008-12-02', '2008-10-13', '2008-02-29',
			'2007-10-25', '2007-02-26',
		),
		'programovaniVPhp5' => array(
			'2011-09-08', '2011-04-21',
			'2010-12-02', '2010-06-08', '2010-03-03',
			'2009-12-02', '2009-09-29', '2009-09-15', '2009-06-26', '2009-04-23', '2009-01-21',
			'2008-12-03', '2008-10-14', '2008-04-08',
			'2007-10-26',
			'2006-11-16', '2006-06-12',
		),
		'bezpecnostPhpAplikaci' => array(
			'2011-09-16', '2011-09-05', '2011-04-29',
			'2010-12-09', '2010-10-08', '2010-06-11', '2010-03-12', '2010-03-09',
			'2009-12-08', '2009-09-17', '2009-06-08', '2009-03-12', '2009-03-10',
			'2008-12-08', '2008-10-21', '2008-06-24', '2008-02-28', '2008-02-25',
			'2007-10-29', '2007-10-23', '2007-06-26', '2007-04-16',
			'2006-10-27', '2006-06-22', '2006-04-25',
		),
		'vykonnostWebovychAplikaci' => array(
			'2011-09-14', '2011-04-27',
			'2010-12-07', '2010-03-10',
			'2009-09-21', '2009-03-11',
			'2008-12-09', '2008-10-22', '2008-06-27',
		),
	);


	public function renderDefault()
	{
		$this->template->pageTitle = 'Školení';

		$this->template->upcomingTrainings = $this->context->createTrainingDates()
			->where('end > NOW()')
			->order('key_training ASC');
	}


	public function actionSkoleni($name)
	{
		$training = $this->context->createTrainingDates()
			->where('training.action', $name)
			->where('end > NOW()')
			->limit(1)
			->fetch();

		$this->tentative = (boolean)$training->tentative;

		$this->template->trainingId       = $training->id_date;
		$this->template->pageTitle        = 'Školení ' . $training->training->name;
		$this->template->description      = $training->training->description;
		$this->template->content          = $training->training->content;
		$this->template->prerequisites    = $training->training->prerequisites;
		$this->template->audience         = $training->training->audience;
		$this->template->start            = $training->start;
		$this->template->end              = $training->end;
		$this->template->tentative        = $this->tentative;
		$this->template->originalUrl      = $training->training->original_url;
		$this->template->capacity         = $training->training->capacity;
		$this->template->services         = $training->training->services;
		$this->template->price            = $training->training->price;
		$this->template->studentDiscount  = $training->training->student_discount;
		$this->template->materials        = $training->training->materials;
		$this->template->venueUrl         = $training->venue->url;
		$this->template->venueName        = $training->venue->name;
		$this->template->venueAddress     = $training->venue->address;
		$this->template->venueDescription = $training->venue->description;

		$this->template->pastTrainingsMe = $this->context->createTrainingDates()
			->where('training.action', $name)
			->where('end < NOW()')
			->order('start DESC')
			->fetchPairs('id_date', 'start');

		$this->template->pastTrainingsJakub = $this->pastTrainingsVrana[$name];
	}


	protected function createComponentApplication($name)
	{
		$form = new Form($this, $name);
		$form->setAction($form->getAction() . '#prihlaska');
		$form->addHidden('trainingId');
		$form->addHidden('date');
		$form->addGroup('Účastník');
		$form->addText('name', 'Jméno a příjmení:')
			->setRequired('Zadejte prosím jméno a příjmení')
			->addRule(Form::MIN_LENGTH, 'Minimální délka jména a příjmení je tři znaky', 3)
			->addRule(Form::MAX_LENGTH, 'Maximální délka jména a příjmení je sto znaků', 100);
		$form->addText('email', 'E-mail:')
			->setRequired('Zadejte prosím e-mailovou adresu')
			->addRule(Form::EMAIL, 'Zadejte platnou e-mailovou adresu')
			->addRule(Form::MAX_LENGTH, 'Maximální délka e-mailu je sto znaků', 100);

		if (!$this->tentative) {
			$form->addGroup('Fakturační údaje');
			$form->addText('company', 'Obchodní jméno:')
				->addCondition(Form::FILLED)
				->addRule(Form::MIN_LENGTH, 'Minimální délka obchodního jména je tři znaky', 3)
				->addRule(Form::MAX_LENGTH, 'Maximální délka obchodního jména je sto znaků', 100);
			$form->addText('street', 'Ulice a číslo:')
				->addCondition(Form::FILLED)
				->addRule(Form::MIN_LENGTH, 'Minimální délka ulice a čísla je tři znaky', 3)
				->addRule(Form::MAX_LENGTH, 'Maximální délka ulice a čísla je sto znaků', 100);
			$form->addText('city', 'Město:')
				->addCondition(Form::FILLED)
				->addRule(Form::MIN_LENGTH, 'Minimální délka města je dva znaky', 2)
				->addRule(Form::MAX_LENGTH, 'Maximální délka města je sto znaků', 100);
			$form->addText('zip', 'PSČ:')
				->addCondition(Form::FILLED)
				->addRule(Form::PATTERN, 'PSČ musí mít 5 číslic', '([0-9]\s*){5}')
				->addRule(Form::MAX_LENGTH, 'Maximální délka PSČ je sto znaků', 100);
			$form->addText('companyId', 'IČ:')
				->addCondition(Form::FILLED)
				->addRule(Form::MIN_LENGTH, 'Minimální délka IČ je tři znaky', 3)
				->addRule(Form::MAX_LENGTH, 'Maximální délka IČ je sto znaků', 100);
			$form->addText('companyTaxId', 'DIČ:')
				->addCondition(Form::FILLED)
				->addRule(Form::MIN_LENGTH, 'Minimální délka DIČ je tři znaky', 3)
				->addRule(Form::MAX_LENGTH, 'Maximální délka DIČ je sto znaků', 100);
		}

		$form->setCurrentGroup(null);
		$form->addText('note', 'Poznámka:')
			->addCondition(Form::FILLED)
			->addRule(Form::MAX_LENGTH, 'Maximální délka poznámky je tisíc znaků', 1000);
		$form->addSubmit('signUp', isset($this->template->date) ? 'Registrovat se' : 'Odeslat');
		$form->onSuccess[] = callback($this, 'submittedApplication');

		return $form;
	}

	public function submittedApplication($form)
	{
		$values = $form->getValues();
		try {
			$datetime = new DateTime();
			if (empty($values['date'])) {
				$this->context->createTrainingInvitations()->insert(array(
					'key_training' => $values['trainingId'],
					'name' => $values['name'],
					'email' => $values['email'],
					'note' => $values['note'],
					'created' => $datetime,
					'created_timezone' => $datetime->getTimezone()->getName(),
				));
				$this->flashMessage('Díky, přihláška odeslána! Dám vám vědět, jakmile budu znát přesný termín.');
			} else {
				$this->context->createTrainingApplications()->insert(array(
					'key_training' => $values['trainingId'],
					'date' => $values['date'],
					'name' => $values['name'],
					'email' => $values['email'],
					'company' => $values['company'],
					'street' => $values['street'],
					'city' => $values['city'],
					'zip' => $values['zip'],
					'company_id' => $values['companyId'],
					'company_tax_id' => $values['companyTaxId'],
					'note' => $values['note'],
					'created' => $datetime,
					'created_timezone' => $datetime->getTimezone()->getName(),
				));
				$this->flashMessage('Díky, přihláška odeslána! Potvrzení společně s fakturou vám přijde do druhého pracovního dne.');
			}
			$action = $form->parent->params['name'];
			$this->redirect($this->getName() . ':' . $action);
		} catch (PDOException $e) {
 			Debugger::log($e, Debugger::ERROR);
			$this->flashMessage('Ups, něco se rozbilo a přihlášku se nepodařilo odeslat, zkuste to za chvíli znovu.', 'error');
		}
	}
}
