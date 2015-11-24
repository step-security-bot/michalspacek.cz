<?php
namespace MichalSpacekCz\Formatter;

use Nette\Utils\Html;

class Texy extends \Netxten\Formatter\Texy
{

	/** @var string */
	const TRAINING_DATE = 'TRAINING_DATE';

	/** @var \Nette\Localization\ITranslator */
	protected $translator;

	/** @var \Nette\Application\Application */
	protected $application;

	/** @var \MichalSpacekCz\Training\Dates */
	protected $trainingDates;

	/** @var \Netxten\Templating\Helpers */
	protected $netxtenHelpers;


	/**
	 * @param \Nette\Caching\IStorage $cacheStorage
	 * @param \Nette\Localization\ITranslator $translator
	 * @param \Nette\Application\Application $application
	 * @param \MichalSpacekCz\Training\Dates $trainingDates
	 * @param \Netxten\Templating\Helpers $netxtenHelpers
	 */
	public function __construct(
		\Nette\Caching\IStorage $cacheStorage,
		\Nette\Localization\ITranslator $translator,
		\Nette\Application\Application $application,
		\MichalSpacekCz\Training\Dates $trainingDates,
		\Netxten\Templating\Helpers $netxtenHelpers
	)
	{
		$this->translator = $translator;
		$this->application = $application;
		$this->trainingDates = $trainingDates;
		$this->netxtenHelpers = $netxtenHelpers;
		parent::__construct($cacheStorage);
	}


	/**
	 * @param string $format
	 * @param string $args
	 * @return Html
	 */
	public function substitute($format, $args)
	{
		return parent::format(vsprintf($format, $args));
	}


	/**
	 * @param string $message
	 * @param array $replacements
	 * @return string
	 */
	public function translate($message, array $replacements = null)
	{
		return $this->substitute($this->translator->translate($message), $replacements);
	}


	public function addHandlers()
	{
		$this->addHandler('phrase', [$this, 'phraseHandler']);
	}


	/**
	 * @param \Texy\HandlerInvocation  handler invocation
	 * @param string
	 * @param string
	 * @param \Texy\Modifier
	 * @param \Texy\Link
	 * @return \Texy\HtmlElement|string|FALSE
	 */
	function phraseHandler($invocation, $phrase, $content, $modifier, $link)
	{
		if (!$link) {
			return $invocation->proceed();
		}

		if (strncmp($link->URL, 'link:', 5) === 0) {
			$args = preg_split('/[\s,]/', substr($link->URL, 5));
			$link->URL = $this->application->getPresenter()->link(':' . array_shift($args), $args);
		}

		if (strncmp($link->URL, 'training:', 9) === 0) {
			$texy = $invocation->getTexy();
			$name = substr($link->URL, 9);
			$link->URL = $this->application->getPresenter()->link(':Trainings:training', $name);
			$el = \Texy\HtmlElement::el();
			$el->add($texy->phraseModule->solve(null, $phrase, $content, $modifier, $link));
			$el->add($texy->protect($this->getTrainingSuffix($name), $texy::CONTENT_TEXTUAL));
			return $el;
		}

		return $invocation->proceed();
	}


	/**
	 * @param string $training Training name
	 * @return string
	 */
	private function getTrainingSuffix($training)
	{
		$el = Html::el()
			->add(Html::el()->setText(' '))
			->add(Html::el('small')->setText(sprintf('(**%s:%s**)', self::TRAINING_DATE, $training)));
		return $el;
	}


	/**
	 * @param string|null $text
	 * @return Html
	 */
	public function format($text)
	{
		if (empty($text)) {
			return Html::el();
		}
		$result = parent::format($text);
		return $this->replace($result);
	}


	/**
	 * @param Html $result
	 * @return Html
	 */
	private function replace(Html $result)
	{
		$replacements = array(
			self::TRAINING_DATE => [$this, 'replaceTrainingDate'],
		);

		$result = preg_replace_callback('~\*\*([^:]+):([^*]+)\*\*~', function ($matches) use ($replacements) {
			if (isset($replacements[$matches[1]])) {
				return call_user_func($replacements[$matches[1]], $matches[2]);
			}
		}, $result);
		return Html::el()->setHtml($result);
	}


	/**
	 * @param string $name
	 * @return string
	 */
	private function replaceTrainingDate($name)
	{
		$upcoming = $this->trainingDates->getPublicUpcoming();
		$dates = array();
		if (!isset($upcoming[$name]) || empty($upcoming[$name]['dates'])) {
			$dates[] = $this->translator->translate('messages.trainings.nodateyet');
		} else {
			foreach ($upcoming[$name]['dates'] as $date) {
				$format = ($date->tentative ? '%B %Y' : 'j. n. Y');
				$start = $this->netxtenHelpers->localDate($date->start, 'cs', $format);
				$el = Html::el()
					->add(Html::el('strong')->setText($start))
					->add(Html::el()->setText(' '))
					->add(Html::el()->setText($date->venueCity));
				$dates[] = $el;
			}
		}
		return implode(', ', $dates);
	}

}
