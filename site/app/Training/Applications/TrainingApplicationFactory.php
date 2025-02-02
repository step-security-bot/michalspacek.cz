<?php
declare(strict_types = 1);

namespace MichalSpacekCz\Training\Applications;

use MichalSpacekCz\Formatter\TexyFormatter;
use MichalSpacekCz\Training\ApplicationStatuses\TrainingApplicationStatus;
use MichalSpacekCz\Training\ApplicationStatuses\TrainingApplicationStatuses;
use MichalSpacekCz\Training\Files\TrainingFiles;
use MichalSpacekCz\Training\Mails\TrainingMailMessageFactory;
use MichalSpacekCz\Training\Price;
use Nette\Database\Row;
use ParagonIE\Halite\Alerts\HaliteAlert;
use SodiumException;
use Spaze\Encryption\SymmetricKeyEncryption;

readonly class TrainingApplicationFactory
{

	public function __construct(
		private TrainingApplicationStatuses $trainingApplicationStatuses,
		private TrainingMailMessageFactory $trainingMailMessageFactory,
		private SymmetricKeyEncryption $emailEncryption,
		private TexyFormatter $texyFormatter,
		private TrainingApplicationSources $trainingApplicationSources,
		private TrainingFiles $trainingFiles,
	) {
	}


	/**
	 * @throws SodiumException
	 * @throws HaliteAlert
	 */
	public function createFromDatabaseRow(Row $row): TrainingApplication
	{
		$price = new Price($row->price, $row->discount, $row->vatRate, $row->priceVat);
		$status = TrainingApplicationStatus::from($row->status);
		return new TrainingApplication(
			$this->trainingApplicationStatuses,
			$this->trainingMailMessageFactory,
			$this->trainingFiles,
			$row->id,
			$row->name,
			$row->email ? $this->emailEncryption->decrypt($row->email) : null,
			(bool)$row->familiar,
			$row->company,
			$row->street,
			$row->city,
			$row->zip,
			$row->country,
			$row->companyId,
			$row->companyTaxId,
			$row->note,
			$status,
			$row->statusTime,
			in_array($status, $this->trainingApplicationStatuses->getAttendedStatuses(), true),
			in_array($status, $this->trainingApplicationStatuses->getDiscardedStatuses(), true),
			in_array($status, $this->trainingApplicationStatuses->getAllowFilesStatuses(), true),
			$row->dateId,
			$row->trainingId,
			$row->trainingAction,
			$this->texyFormatter->translate($row->trainingName),
			$row->trainingStart,
			$row->trainingEnd,
			(bool)$row->publicDate,
			(bool)$row->remote,
			$row->remoteUrl,
			$row->remoteNotes,
			$row->videoHref,
			$row->feedbackHref,
			$row->venueAction,
			$row->venueName,
			$row->venueNameExtended,
			$row->venueAddress,
			$row->venueCity,
			$row->price,
			$row->vatRate,
			$row->priceVat,
			$price->getPriceWithCurrency(),
			$price->getPriceVatWithCurrency(),
			$row->discount,
			$row->invoiceId,
			$row->paid,
			$row->accessToken,
			$row->sourceAlias,
			$row->sourceName,
			$this->trainingApplicationSources->getSourceNameInitials($row->sourceName),
		);
	}

}
