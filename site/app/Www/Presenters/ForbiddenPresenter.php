<?php
declare(strict_types = 1);

namespace MichalSpacekCz\Www\Presenters;

use Contributte\Translation\Translator;
use Nette\Application\UI\Presenter;
use Nette\Bridges\ApplicationLatte\DefaultTemplate;
use Nette\Http\IResponse;

/**
 * A forbidden presenter.
 *
 * Does not extend BasePresenter to avoid loop in startup().
 *
 * @property-read DefaultTemplate $template
 */
class ForbiddenPresenter extends Presenter
{

	public function __construct(
		private readonly Translator $translator,
		private readonly IResponse $httpResponse,
	) {
		parent::__construct();
	}


	public function actionDefault(): void
	{
		$this->httpResponse->setCode(IResponse::S403_Forbidden);
		$this->template->pageTitle = $this->translator->translate('messages.title.forbidden');
	}

}
