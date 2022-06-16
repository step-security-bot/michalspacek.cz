<?php
declare(strict_types = 1);

namespace MichalSpacekCz\Www\Presenters;

use MichalSpacekCz\Formatter\TexyFormatter;
use MichalSpacekCz\Interviews\Exceptions\InterviewDoesNotExistException;
use MichalSpacekCz\Interviews\Interviews;
use MichalSpacekCz\Templating\Embed;
use Nette\Application\BadRequestException;

class InterviewsPresenter extends BasePresenter
{

	public function __construct(
		private readonly TexyFormatter $texyFormatter,
		private readonly Interviews $interviews,
		private readonly Embed $embed,
	) {
		parent::__construct();
	}


	public function renderDefault(): void
	{
		$this->template->pageTitle = $this->translator->translate('messages.title.interviews');
		$this->template->interviews = $this->interviews->getAll();
	}


	/**
	 * @throws BadRequestException
	 */
	public function actionInterview(string $name): void
	{
		try {
			$interview = $this->interviews->get($name);
		} catch (InterviewDoesNotExistException $e) {
			throw new BadRequestException($e->getMessage(), previous: $e);
		}

		$this->template->pageTitle = $this->texyFormatter->translate('messages.title.interview', [$interview->title]);
		$this->template->pageHeader = $interview->title;
		$this->template->description = $interview->description;
		$this->template->href = $interview->href;
		$this->template->date = $interview->date;
		$this->template->audioHref = $interview->audioHref;
		$this->template->sourceName = $interview->sourceName;
		$this->template->sourceHref = $interview->sourceHref;

		$this->template->videoHref = $interview->videoHref;
		foreach ($this->embed->getVideoTemplateVars($interview) as $key => $value) {
			$this->template->$key = $value;
		}
	}

}
