<?php
namespace App\PulseModule\Presenters;

use Nette\Http\Response;

/**
 * Pulse password storages presenter.
 *
 * @author Michal Špaček
 * @package pulse.michalspacek.cz
 */
class PasswordsStoragesPresenter extends \App\WwwModule\Presenters\BasePresenter
{

	/** @var \MichalSpacekCz\Pulse\Passwords */
	protected $passwords;

	/** @var \MichalSpacekCz\Pulse\Passwords\Rating */
	protected $passwordsRating;


	public function __construct(\MichalSpacekCz\Pulse\Passwords $passwords, \MichalSpacekCz\Pulse\Passwords\Rating $passwordsRating)
	{
		$this->passwords = $passwords;
		$this->passwordsRating = $passwordsRating;
		parent::__construct();
	}


	/**
	 * Storages action handler.
	 *
	 * @param string $param
	 */
	public function actionDefault($param)
	{
		// Keep old, published URLs alive
		if ($param) {
			$this->redirect(Response::S301_MOVED_PERMANENTLY, 'site', $param);
		}
		$data = $this->passwords->getAllStorages();
		$this->template->isDetail = false;
		$this->template->pageTitle = 'Password storage disclosures';
		$this->template->data = $data;
		$this->template->ratingGuide = $this->passwordsRating->getRatingGuide();
	}


	/**
	 * Storages by site action handler.
	 *
	 * @param string $param
	 */
	public function actionSite($param)
	{
		if (empty($param)) {
			$this->redirect(Response::S301_MOVED_PERMANENTLY, 'default');
		}

		$sites = explode(',', $param);
		$data = $this->passwords->getStoragesBySite($sites);
		if (empty($data->sites)) {
			throw new \Nette\Application\BadRequestException('Unknown site alias', Response::S404_NOT_FOUND);
		}

		$this->template->isDetail = true;
		$this->template->pageTitle = implode(', ', $sites) . ' password storage disclosures';
		$this->template->data = $data;
		$this->template->ratingGuide = $this->passwordsRating->getRatingGuide();
		$this->setView('default');
	}


	/**
	 * Storages by company action handler.
	 *
	 * @param string $param
	 */
	public function actionCompany($param)
	{
		if (empty($param)) {
			$this->redirect(Response::S301_MOVED_PERMANENTLY, 'default');
		}

		$companies = explode(',', $param);
		$data = $this->passwords->getStoragesByCompany($companies);
		if (empty($data->sites)) {
			throw new \Nette\Application\BadRequestException('Unknown company alias', Response::S404_NOT_FOUND);
		}

		$names = [];
		foreach ($data->companies as $item) {
			$names[] = ($item->tradeName ?: $item->companyName);
		}

		$this->template->isDetail = true;
		$this->template->pageTitle = implode(', ', $names) . ' password storage disclosures';
		$this->template->data = $data;
		$this->template->ratingGuide = $this->passwordsRating->getRatingGuide();
		$this->setView('default');
	}


	/**
	 * Storages rating action handler.
	 */
	public function actionRating()
	{
		$this->template->pageTitle = 'Password storage disclosures rating guide';
		$this->template->ratingGuide = $this->passwordsRating->getRatingGuide();
		$this->template->slowHashes = $this->passwords->getSlowHashes();
		$this->template->visibleDisclosures = $this->passwords->getVisibleDisclosures();
		$this->template->invisibleDisclosures = $this->passwords->getInvisibleDisclosures();
	}


	/**
	 * Storages questions action handler.
	 */
	public function actionQuestions()
	{
		$this->template->pageTitle = 'Password storage disclosures questions';
	}

}
