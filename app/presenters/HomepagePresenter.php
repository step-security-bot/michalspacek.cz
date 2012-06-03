<?php
/**
 * Homepage presenter.
 *
 * @author     Michal Špaček
 * @package    michalspacek.cz
 */
class HomepagePresenter extends BasePresenter
{

	public function renderDefault()
	{
		$this->template->trainings = $this->trainings;

		$database = $this->getContext()->nette->database->default;

		$articles = $database->table('articles')->order('date DESC')->limit(3);
		$this->template->articles = $articles;

		$talks = $database->table('talks')->order('date DESC')->limit(5);
		$this->template->talks = $talks;
	}

}
