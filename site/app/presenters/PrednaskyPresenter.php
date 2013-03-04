<?php
/**
 * Přednášky presenter.
 *
 * @author     Michal Špaček
 * @package    michalspacek.cz
 */
class PrednaskyPresenter extends BasePresenter
{


	public function renderDefault()
	{
		$this->template->pageTitle = 'Přednášky';
		$this->template->talks     = $this->talks->getAll();
		$this->template->upcomingTalks = $this->talks->getUpcoming();
	}


	public function actionPrednaska($name)
	{
		$talk = $this->talks->get($name);
		if (!$talk) {
			throw new \Nette\Application\BadRequestException("I haven't talked about {$name}, yet", \Nette\Http\Response::S404_NOT_FOUND);
		}

		$this->template->pageTitle = 'Přednáška ' . $talk->title;
		$this->template->href = $talk->href;
		$this->template->date = $talk->date;
		$this->template->eventHref = $talk->eventHref;
		$this->template->event = $talk->event;
		$this->template->slidesHref = $talk->slidesHref;
		$this->template->slidesEmbed = $talk->slidesEmbed;
		$this->template->slidesEmbedType = $this->talks->getSlidesEmbedType($talk->slidesHref);
		$this->template->videoHref = $talk->videoHref;
		$this->template->videoEmbed = $talk->videoEmbed;
		$this->template->videoEmbedType = $this->talks->getVideoEmbedType($talk->videoHref);
	}

}
