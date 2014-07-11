<?php
/**
 * Base class for all application presenters.
 *
 * @author     Michal Špaček
 * @package    michalspacek.cz
 */
abstract class BasePresenter extends \Nette\Application\UI\Presenter
{

	/** @var \Nette\Localization\ITranslator */
	protected $translator;


	public function __construct(\Nette\Localization\ITranslator $translator)
	{
		$this->translator = $translator;
	}


	protected function startup()
	{
		parent::startup();
		$authenticator = $this->getContext()->getByType(\MichalSpacekCz\UserManager::class);
		if ($authenticator->isForbidden()) {
			$this->forward('Forbidden:');
		}
	}


	public function beforeRender()
	{
		$webTracking = $this->getContext()->getByType(\MichalSpacekCz\WebTracking::class);
		$this->template->trackingCode = $webTracking->isEnabled();
 		$this->template->setTranslator($this->translator);
 	}


	protected function createTemplate($class = null)
	{
		$helpers = $this->getContext()->getByType(\MichalSpacekCz\Templating\Helpers::class);

		$template = parent::createTemplate($class);
		$template->getLatte()->addFilter(null, [new \Bare\Next\Templating\Helpers(), 'loader']);
		$template->getLatte()->addFilter(null, [$helpers, 'loader']);
		return $template;
	}


    protected function translate($message, $count = null)
	{
		return $this->translator->translate($message, $count);
	}

}
