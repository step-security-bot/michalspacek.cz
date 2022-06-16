<?php
declare(strict_types = 1);

namespace MichalSpacekCz\Admin\Presenters;

use DateTime;
use MichalSpacekCz\Tls\Certificate;
use MichalSpacekCz\Tls\Certificates;
use MichalSpacekCz\Training\Applications;
use MichalSpacekCz\Training\Dates;
use MichalSpacekCz\Training\Mails;
use MichalSpacekCz\Training\Trainings;

class HomepagePresenter extends BasePresenter
{

	protected bool $haveBacklink = false;


	public function __construct(
		private readonly Applications $trainingApplications,
		private readonly Mails $trainingMails,
		private readonly Dates $trainingDates,
		private readonly Certificates $certificates,
		private readonly Trainings $trainings,
	) {
		parent::__construct();
	}


	public function actionDefault(): void
	{
		$trainings = $this->trainingDates->getAllTrainingsInterval('-1 week');
		foreach ($this->trainingDates->getAllUpcoming() as $training) {
			foreach ($training->dates as $date) {
				$trainings[] = $date;
			}
		}
		$dates = [];
		foreach ($trainings as $date) {
			$date->applications = $this->trainingApplications->getValidByDate($date->dateId);
			$date->canceledApplications = $this->trainingApplications->getCanceledPaidByDate($date->dateId);
			$date->validCount = count($date->applications);
			$date->requiresAttention = ($date->canceledApplications || ($date->status == Dates::STATUS_CANCELED && $date->validCount));
			$dates[$date->start->getTimestamp()] = $date;
		}
		ksort($dates);
		$this->template->upcomingApplications = $dates;
		$this->template->now = new DateTime();
		$this->template->upcomingIds = $this->trainingDates->getPublicUpcomingIds();

		$this->template->pageTitle = 'Administrace';
		$this->template->emailsToSend = count($this->trainingMails->getApplications());
		$this->template->unpaidInvoices = $this->trainingApplications->getValidUnpaidCount();
		$this->template->certificates = $certificates = $this->certificates->getNewest();
		$this->template->certificatesNeedAttention = $this->certsNeedAttention($certificates);
		[$this->template->preliminaryTotal, $this->template->preliminaryDateSet] = $this->trainingApplications->getPreliminaryCounts();
		$this->template->pastWithPersonalData = count($this->trainings->getPastWithPersonalData());
	}


	/**
	 * Check if at least one certificate is expired or expires soon.
	 *
	 * @param array<int, Certificate> $certificates
	 * @return bool
	 */
	private function certsNeedAttention(array $certificates): bool
	{
		foreach ($certificates as $certificate) {
			if ($certificate->isExpired() || $certificate->isExpiringSoon()) {
				return true;
			}
		}
		return false;
	}

}
