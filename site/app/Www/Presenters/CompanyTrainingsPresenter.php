<?php
declare(strict_types = 1);

namespace MichalSpacekCz\Www\Presenters;

use MichalSpacekCz\Formatter\TexyFormatter;
use MichalSpacekCz\Training\CompanyTrainings;
use MichalSpacekCz\Training\Discontinued\DiscontinuedTrainings;
use MichalSpacekCz\Training\Prices;
use MichalSpacekCz\Training\Reviews\TrainingReviews;
use MichalSpacekCz\Training\TrainingLocales;
use MichalSpacekCz\Training\Trainings;
use Nette\Application\BadRequestException;

class CompanyTrainingsPresenter extends BasePresenter
{

	private ?string $trainingAction = null;


	public function __construct(
		private readonly TexyFormatter $texyFormatter,
		private readonly Trainings $trainings,
		private readonly CompanyTrainings $companyTrainings,
		private readonly DiscontinuedTrainings $discontinuedTrainings,
		private readonly TrainingLocales $trainingLocales,
		private readonly TrainingReviews $trainingReviews,
		private readonly Prices $prices,
	) {
		parent::__construct();
	}


	public function renderDefault(): void
	{
		$this->template->pageTitle = $this->translator->translate('messages.title.companytrainings');
		$this->template->trainings = $this->trainings->getNames();
		$this->template->discontinued = $this->discontinuedTrainings->getAllDiscontinued();
	}


	public function actionTraining(string $name): void
	{
		$this->trainingAction = $name;
		$training = $this->companyTrainings->getInfo($name);
		if (!$training) {
			throw new BadRequestException("I don't do {$name} training, yet");
		}

		if ($training->successorId !== null) {
			$this->redirectPermanent('this', $this->trainings->getActionById($training->successorId));
		}

		$price = $this->prices->resolvePriceVat($training->price);

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
		$this->template->priceWithCurrency = $price->getPriceWithCurrency();
		$this->template->priceVatWithCurrency = $price->getPriceVatWithCurrency();
		$this->template->alternativeDurationPriceText = $training->alternativeDurationPriceText;
		$this->template->materials = $training->materials;
		$this->template->reviews = $this->trainingReviews->getVisibleReviews($training->trainingId, 3);
		$this->discontinuedTrainings->maybeMarkAsDiscontinued($this->template, $training->discontinuedId);
	}


	/**
	 * Translated locale parameters for trainings.
	 *
	 * @return array<string, array<string, string|null>>
	 */
	protected function getLocaleLinkParams(): array
	{
		return $this->trainingLocales->getLocaleLinkParams($this->trainingAction, $this->getParameters());
	}

}
