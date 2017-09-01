<?php
namespace App\WwwModule\Presenters;

/**
 * Company Trainings presenter.
 *
 * @author     Michal Špaček
 * @package    michalspacek.cz
 */
class CompanyTrainingsPresenter extends BasePresenter
{

	/** @var \MichalSpacekCz\Formatter\Texy */
	protected $texyFormatter;

	/** @var \MichalSpacekCz\Training\Trainings */
	protected $trainings;

	/** @var \MichalSpacekCz\Training\CompanyTrainings */
	protected $companyTrainings;

	/** @var \MichalSpacekCz\Training\Locales */
	protected $trainingLocales;

	/** @var \MichalSpacekCz\Training\Reviews */
	protected $trainingReviews;

	/** @var \MichalSpacekCz\Vat */
	protected $vat;


	/**
	 * @param \MichalSpacekCz\Formatter\Texy $texyFormatter
	 * @param \MichalSpacekCz\Training\Trainings $trainings
	 * @param \MichalSpacekCz\Training\CompanyTrainings $companyTrainings
	 * @param \MichalSpacekCz\Training\Locales $trainingLocales
	 * @param \MichalSpacekCz\Training\Reviews $trainingReviews
	 * @param \MichalSpacekCz\Vat $vat
	 */
	public function __construct(
		\MichalSpacekCz\Formatter\Texy $texyFormatter,
		\MichalSpacekCz\Training\Trainings $trainings,
		\MichalSpacekCz\Training\CompanyTrainings $companyTrainings,
		\MichalSpacekCz\Training\Locales $trainingLocales,
		\MichalSpacekCz\Training\Reviews $trainingReviews,
		\MichalSpacekCz\Vat $vat
	)
	{
		$this->texyFormatter = $texyFormatter;
		$this->trainings = $trainings;
		$this->companyTrainings = $companyTrainings;
		$this->trainingLocales = $trainingLocales;
		$this->trainingReviews = $trainingReviews;
		$this->vat = $vat;
		parent::__construct();
	}


	public function renderDefault()
	{
		$this->template->pageTitle = $this->translator->translate('messages.title.companytrainings');
		$this->template->trainings = $this->trainings->getNames();
	}


	public function actionTraining($name)
	{
		$training = $this->companyTrainings->getInfo($name);
		if (!$training) {
			throw new \Nette\Application\BadRequestException("I don't do {$name} training, yet", \Nette\Http\Response::S404_NOT_FOUND);
		}
		$this->template->name = $training->action;
		$this->template->pageTitle = $this->texyFormatter->translate('messages.title.companytraining', [$training->name]);
		$this->template->title = $training->name;
		$this->template->description = $training->description;
		$this->template->content = $training->content;
		$this->template->upsell = $training->upsell;
		$this->template->prerequisites = $training->prerequisites;
		$this->template->audience = $training->audience;
		$this->template->duration = $training->duration;
		$this->template->alternativeDuration = $training->alternativeDuration;
		$this->template->price = $training->price;
		$this->template->priceVat = $this->vat->addVat($training->price);
		$this->template->alternativeDurationPriceText = $training->alternativeDurationPriceText;
		$this->template->materials = $training->materials;
		$this->template->reviews = $this->trainingReviews->getReviews($training->trainingId, 3);
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
