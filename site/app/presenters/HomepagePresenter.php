<?php
/**
 * Homepage presenter.
 *
 * @author     Michal Špaček
 * @package    michalspacek.cz
 */
class HomepagePresenter extends BasePresenter
{

	/** @var \MichalSpacekCz\Articles */
	protected $articles;

	/** @var \MichalSpacekCz\Interviews */
	protected $interviews;

	/** @var \MichalSpacekCz\Talks */
	protected $talks;

	/** @var \MichalSpacekCz\Trainings */
	protected $trainings;


	/**
	 * @param \MichalSpacekCz\Articles $articles
	 * @param \MichalSpacekCz\Interviews $interviews
	 * @param \MichalSpacekCz\Talks $talks
	 * @param \MichalSpacekCz\Trainings $trainings
	 */
	public function __construct(
		\MichalSpacekCz\Articles $articles,
		\MichalSpacekCz\Interviews $interviews,
		\MichalSpacekCz\Talks $talks,
		\MichalSpacekCz\Trainings $trainings
	)
	{
		$this->articles = $articles;
		$this->interviews = $interviews;
		$this->talks = $talks;
		$this->trainings = $trainings;
		parent::__construct();
	}


	public function renderDefault()
	{
		$this->template->articles          = $this->articles->getAll(3);
		$this->template->talks             = $this->talks->getAll(5);
		$this->template->upcomingTalks     = $this->talks->getUpcoming();
		$this->template->upcomingTrainings = $this->trainings->getPublicUpcoming();
		$this->template->interviews        = $this->interviews->getAll(5);
		$this->template->lastFreeSeats     = $this->trainings->lastFreeSeatsAnyTraining($this->template->upcomingTrainings);
	}


}
