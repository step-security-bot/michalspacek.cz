<?php
declare(strict_types = 1);

namespace MichalSpacekCz\Form;

class TrainingApplicationAdmin extends ProtectedForm
{

	use Controls\PaidDate;
	use Controls\TrainingAttendee;
	use Controls\TrainingCompany;
	use Controls\TrainingCountry;
	use Controls\TrainingNote;
	use Controls\TrainingSource;

	/** @var \MichalSpacekCz\Training\Applications */
	protected $trainingApplications;

	/** @var \MichalSpacekCz\Training\Dates */
	protected $trainingDates;

	/** @var \Nette\Localization\ITranslator */
	protected $translator;

	/** @var string[] */
	private $deletableFields = [
		'name',
		'email',
		'company',
		'street',
		'city',
		'zip',
		'country',
		'companyId',
		'companyTaxId',
		'note',
	];


	public function __construct(
		\Nette\ComponentModel\IContainer $parent,
		string $name,
		\MichalSpacekCz\Training\Applications $trainingApplications,
		\MichalSpacekCz\Training\Dates $trainingDates,
		\Nette\Localization\ITranslator $translator
	) {
		parent::__construct($parent, $name);
		$this->trainingApplications = $trainingApplications;
		$this->trainingDates = $trainingDates;
		$this->translator = $translator;

		$this->addAttendee($this);
		$this->addCheckbox('familiar', 'Tykání:');
		$this->addSource($this);
		$this->addCompany($this);
		$this->addCountry($this);
		$this->getComponent('country')->setPrompt('- vyberte zemi -');
		$this->addNote($this);
		$this->addPaymentInfo($this);
		$this->addSubmit('submit', 'Uložit');

		foreach ($this->deletableFields as $field) {
			$this->addCheckbox("{$field}Set")->setAttribute('class', 'disableInput');
			$this->getComponent($field)
				->setAttribute('class', 'transparent')
				->setRequired(false);
		}
	}


	protected function addPaymentInfo(\Nette\Forms\Container $container): void
	{
		$this->addText('price', 'Cena bez DPH:')
			->setType('number')
			->setAttribute('title', 'Po případné slevě');
		$this->addText('vatRate', 'DPH:')
			->setType('number');
		$this->addText('priceVat', 'Cena s DPH:')
			->setType('number')
			->setAttribute('title', 'Po případné slevě');
		$this->addText('discount', 'Sleva:')
			->setType('number');
		$this->addText('invoiceId', 'Faktura č.:')
			->setType('number');
		$this->addPaidDate('paid', 'Zaplaceno:', false);
	}


	/**
	 * @param \Nette\Database\Row $application
	 * @return self
	 */
	public function setApplication(\Nette\Database\Row $application): self
	{
		$values = array(
			'name' => $application->name,
			'email' => $application->email,
			'familiar' => $application->familiar,
			'source' => $application->sourceAlias,
			'company' => $application->company,
			'street' => $application->street,
			'city' => $application->city,
			'zip' => $application->zip,
			'country' => $application->country,
			'companyId' => $application->companyId,
			'companyTaxId' => $application->companyTaxId,
			'note' => $application->note,
			'price' => $application->price,
			'vatRate' => ($application->vatRate ? $application->vatRate * 100 : $application->vatRate),
			'priceVat' => $application->priceVat,
			'discount' => $application->discount,
			'invoiceId' => $application->invoiceId,
			'paid' => $application->paid,
		);
		foreach ($this->deletableFields as $field) {
			$values["{$field}Set"] = ($application->$field !== null);
			$this->getComponent($field)->setAttribute('class', $application->$field === null ? 'transparent' : null);
		}
		$this->setDefaults($values);
		if (!isset($application->dateId)) {
			$dates = $this->trainingDates->getPublicUpcoming();
			$upcoming = array();
			if (isset($dates[$application->trainingAction])) {
				foreach ($dates[$application->trainingAction]->dates as $date) {
					$upcoming[$date->dateId] = $date->start;
				}
			}
			$this->addSelect('date', 'Datum:', $upcoming)
				->setPrompt($upcoming ? '- zvolte termín -' : 'Žádný vypsaný termín')
				->setDisabled(!$upcoming);
		}
		return $this;
	}

}
