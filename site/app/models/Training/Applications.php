<?php
declare(strict_types = 1);

namespace MichalSpacekCz\Training;

use DateTime;
use MichalSpacekCz\Training\Resolver\Vrana;
use Nette\Database\Context;
use Nette\Database\Drivers\MySqlDriver;
use Nette\Database\Row;
use Nette\Localization\ITranslator;
use Nette\Utils\Random;
use PDOException;
use RuntimeException;
use Spaze\Encryption\Symmetric\StaticKey;
use Tracy\Debugger;

class Applications
{

	private const SOURCE_MICHAL_SPACEK  = 'michal-spacek';
	private const SOURCE_JAKUB_VRANA  = 'jakub-vrana';

	/** @var Context */
	protected $database;

	/** @var Trainings */
	protected $trainings;

	/** @var Dates */
	protected $trainingDates;

	/** @var Statuses */
	protected $trainingStatuses;

	/** @var StaticKey */
	protected $emailEncryption;

	/** @var Price */
	private $price;

	/** @var Vrana */
	protected $vranaResolver;

	/** @var ITranslator */
	protected $translator;

	/** @var array */
	private $byDate = array();


	public function __construct(
		Context $context,
		Trainings $trainings,
		Dates $trainingDates,
		Statuses $trainingStatuses,
		StaticKey $emailEncryption,
		Price $price,
		Vrana $vranaResolver,
		ITranslator $translator
	)
	{
		$this->database = $context;
		$this->trainings = $trainings;
		$this->trainingDates = $trainingDates;
		$this->trainingStatuses = $trainingStatuses;
		$this->emailEncryption = $emailEncryption;
		$this->price = $price;
		$this->vranaResolver = $vranaResolver;
		$this->translator = $translator;
	}


	/**
	 * @param string $status
	 * @return Row[]
	 */
	public function getByStatus(string $status): array
	{
		$result = $this->database->fetchAll(
			'SELECT
				a.id_application AS id,
				a.name,
				a.email,
				a.familiar,
				a.company,
				s.status,
				a.status_time AS statusTime,
				d.id_date AS dateId,
				t.id_training AS trainingId,
				d.start AS trainingStart,
				d.end AS trainingEnd,
				d.public AS publicDate,
				v.name AS venueName,
				v.name_extended AS venueNameExtended,
				v.address AS venueAddress,
				v.city AS venueCity,
				v.action AS venueAction,
				a.price,
				a.vat_rate AS vatRate,
				a.price_vat AS priceVat,
				a.discount,
				a.invoice_id AS invoiceId,
				a.paid,
				a.access_token AS accessToken
			FROM
				training_applications a
				JOIN training_dates d ON a.key_date = d.id_date
				JOIN trainings t ON d.key_training = t.id_training
				JOIN training_venues v ON d.key_venue = v.id_venue
				JOIN training_application_status s ON a.key_status = s.id_status
			WHERE
				s.status = ?
			ORDER BY
				d.start, a.status_time',
			$status
		);

		if ($result) {
			foreach ($result as $row) {
				if ($row->email) {
					$row->email = $this->emailEncryption->decrypt($row->email);
				}
				$row->training = $this->trainings->getById($row->trainingId);
			}
		}

		return $result;
	}


	/**
	 * @param integer $dateId
	 * @return Row[]
	 */
	public function getByDate(int $dateId): array
	{
		if (!isset($this->byDate[$dateId])) {
			$this->byDate[$dateId] = $this->database->fetchAll(
				'SELECT
					a.id_application AS id,
					a.name,
					a.email,
					a.company,
					s.status,
					a.status_time AS statusTime,
					a.note,
					a.price,
					a.vat_rate AS vatRate,
					a.price_vat AS priceVat,
					a.invoice_id AS invoiceId,
					a.paid,
					sr.name AS sourceName
				FROM
					training_applications a
					JOIN training_application_status s ON a.key_status = s.id_status
					JOIN training_application_sources sr ON a.key_source = sr.id_source
				WHERE
					key_date = ?',
				$dateId
			);
			if ($this->byDate[$dateId]) {
				$discardedStatuses = $this->trainingStatuses->getDiscardedStatuses();
				$attendedStatuses = $this->trainingStatuses->getAttendedStatuses();
				foreach ($this->byDate[$dateId] as $row) {
					if ($row->email) {
						$row->email = $this->emailEncryption->decrypt($row->email);
					}
					$row->sourceNameInitials = $this->getSourceNameInitials($row->sourceName);
					$row->discarded = in_array($row->status, $discardedStatuses);
					$row->attended = in_array($row->status, $attendedStatuses);
				}
			}
		}
		return $this->byDate[$dateId];
	}


	/**
	 * @param integer $dateId
	 * @return Row[]
	 */
	public function getValidByDate(int $dateId): array
	{
		$discardedStatuses = $this->trainingStatuses->getDiscardedStatuses();
		return array_filter($this->getByDate($dateId), function($value) use ($discardedStatuses) {
			return !in_array($value->status, $discardedStatuses);
		});
	}


	/**
	 * @param integer $dateId
	 * @return Row[]
	 */
	public function getValidUnpaidByDate(int $dateId): array
	{
		return array_filter($this->getValidByDate($dateId), function($value) {
			return (isset($value->invoiceId) && !isset($value->paid));
		});
	}


	public function getValidUnpaidCount(): int
	{
		$result = $this->database->fetchField(
			'SELECT
				COUNT(1)
			FROM
				training_applications
			WHERE
				key_status NOT IN(?)
				AND invoice_id IS NOT NULL
				AND paid IS NULL',
			array_keys($this->trainingStatuses->getDiscardedStatuses())
		);
		return $result;
	}


	/**
	 * Get canceled but already paid applications by date id.
	 *
	 * @param integer $dateId
	 * @return Row[]
	 */
	public function getCanceledPaidByDate(int $dateId): array
	{
		$canceledStatus = $this->trainingStatuses->getCanceledStatus();
		return array_filter($this->getByDate($dateId), function($value) use ($canceledStatus) {
			return ($value->paid && in_array($value->status, $canceledStatus));
		});
	}


	/**
	 * @param array<string, string|integer|float|DateTime|null> $data
	 * @return string Generated access token
	 */
	private function insertData(array $data): string
	{
		$data['access_token'] = $token = $this->generateAccessCode();
		try {
			$this->database->query('INSERT INTO training_applications', $data);
		} catch (PDOException $e) {
			if ($e->getCode() == '23000') {
				if ($e->errorInfo[1] == MySqlDriver::ERROR_DUPLICATE_ENTRY) {
					// regenerate the access code and try harder this time
					Debugger::log("Regenerating access token, {$token} already exists. Full data: " . implode(', ', $data));
					return $this->insertData($data);
				}
			}
			throw $e;
		}
		return $token;
	}


	public function addInvitation(
		Row $training,
		int $dateId,
		string $name,
		string $email,
		string $company,
		string $street,
		string $city,
		string $zip,
		string $country,
		string $companyId,
		string $companyTaxId,
		string $note
	): int
	{
		return $this->insertApplication(
			$training,
			$dateId,
			$name,
			$email,
			$company,
			$street,
			$city,
			$zip,
			$country,
			$companyId,
			$companyTaxId,
			$note,
			Statuses::STATUS_TENTATIVE,
			$this->resolveSource($note)
		);
	}


	public function addApplication(
		Row $training,
		int $dateId,
		string $name,
		string $email,
		string $company,
		string $street,
		string $city,
		string $zip,
		string $country,
		string $companyId,
		string $companyTaxId,
		string $note
	): int
	{
		return $this->insertApplication(
			$training,
			$dateId,
			$name,
			$email,
			$company,
			$street,
			$city,
			$zip,
			$country,
			$companyId,
			$companyTaxId,
			$note,
			Statuses::STATUS_SIGNED_UP,
			$this->resolveSource($note)
		);
	}


	/**
	 * Add preliminary invitation, to a training with no date set.
	 *
	 * @param Row $training
	 * @param string $name
	 * @param string $email
	 * @return integer application id
	 */
	public function addPreliminaryInvitation(Row $training, string $name, string $email): int
	{
		return $this->insertApplication($training,
			null,
			$name,
			$email,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			Statuses::STATUS_TENTATIVE,
			$this->resolveSource()
		);
	}


	public function insertApplication(
		Row $training,
		?int $dateId,
		string $name,
		string $email,
		?string $company,
		?string $street,
		?string $city,
		?string $zip,
		?string $country,
		?string $companyId,
		?string $companyTaxId,
		?string $note,
		?string $status,
		string $source,
		?string $date = null
	): int
	{
		if (!in_array($status, $this->trainingStatuses->getInitialStatuses())) {
			throw new RuntimeException("Invalid initial status {$status}");
		}

		$statusId = $this->trainingStatuses->getStatusId(Statuses::STATUS_CREATED);
		$datetime = new DateTime($date ?? '');

		$this->price->resolvePriceDiscountVat($training->price, $training->studentDiscount, $status, $note ?? '');

		$data = array(
			'key_date'             => $dateId,
			'name'                 => $name,
			'email'                => $this->emailEncryption->encrypt($email),
			'company'              => $company,
			'street'               => $street,
			'city'                 => $city,
			'zip'                  => $zip,
			'country'              => $country,
			'company_id'           => $companyId,
			'company_tax_id'       => $companyTaxId,
			'note'                 => $note,
			'key_status'           => $statusId,
			'status_time'          => $datetime,
			'status_time_timezone' => $datetime->getTimezone()->getName(),
			'key_source'           => $this->getTrainingApplicationSource($source),
			'price'                => $this->price->getPrice(),
			'vat_rate'             => $this->price->getVatRate(),
			'price_vat'            => $this->price->getPriceVat(),
			'discount'             => $this->price->getDiscount(),
		);
		if ($dateId === null) {
			$data['key_training'] = $training->trainingId;
		}
		return $this->trainingStatuses->updateStatusCallbackReturnId(function () use ($data): int {
			$this->insertData($data);
			return (int)$this->database->getInsertId();
		}, $status, $date);
	}


	public function updateApplication(
		Row $training,
		int $applicationId,
		string $name,
		string $email,
		string $company,
		string $street,
		string $city,
		string $zip,
		string $country,
		string $companyId,
		string $companyTaxId,
		string $note
	): int
	{
		$this->trainingStatuses->updateStatusCallback(
			$applicationId,
			Statuses::STATUS_SIGNED_UP,
			null,
			function () use (
				$training,
				$applicationId,
				$name,
				$email,
				$company,
				$street,
				$city,
				$zip,
				$country,
				$companyId,
				$companyTaxId,
				$note
			): void
			{
				$this->price->resolvePriceDiscountVat($training->price, $training->studentDiscount, Statuses::STATUS_SIGNED_UP, $note);
				$this->database->query(
					'UPDATE training_applications SET ? WHERE id_application = ?',
					array(
						'name'           => $name,
						'email'          => $this->emailEncryption->encrypt($email),
						'company'        => $company,
						'street'         => $street,
						'city'           => $city,
						'zip'            => $zip,
						'country'        => $country,
						'company_id'     => $companyId,
						'company_tax_id' => $companyTaxId,
						'note'           => $note,
						'price'          => $this->price->getPrice(),
						'vat_rate'       => $this->price->getVatRate(),
						'price_vat'      => $this->price->getPriceVat(),
						'discount'       => $this->price->getDiscount(),
					),
					$applicationId
				);
			}
		);
		return $applicationId;
	}


	public function updateApplicationData(
		int $applicationId,
		?string $name,
		?string $email,
		?string $company,
		?string $street,
		?string $city,
		?string $zip,
		?string $country,
		?string $companyId,
		?string $companyTaxId,
		?string $note,
		string $source,
		?int $price = null,
		?float $vatRate = null,
		?int $priceVat = null,
		?int $discount = null,
		?string $invoiceId = null,
		string $paid = null,
		bool $familiar = false,
		?int $dateId = null
	): void
	{
		$paidDate = ($paid ? new DateTime($paid) : null);

		$data = array(
			'name'           => $name,
			'email'          => ($email ? $this->emailEncryption->encrypt($email) : null),
			'company'        => $company,
			'familiar'       => $familiar,
			'street'         => $street,
			'city'           => $city,
			'zip'            => $zip,
			'country'        => $country,
			'company_id'     => $companyId,
			'company_tax_id' => $companyTaxId,
			'note'           => $note,
			'key_source'     => $this->getTrainingApplicationSource($source),
			'price'          => ($price || $discount ? $price : null),
			'vat_rate'       => ($vatRate ?: null),
			'price_vat'      => ($priceVat ?: null),
			'discount'       => ($discount ?: null),
			'invoice_id'     => ((int)$invoiceId ?: null),
			'paid'           => ($paidDate ?: null),
			'paid_timezone'  => ($paidDate ? $paidDate->getTimezone()->getName() : null),
		);
		if ($dateId !== null) {
			$data['key_date'] = $dateId;
		}
		$this->database->query('UPDATE training_applications SET ? WHERE id_application = ?', $data, $applicationId);
	}


	public function updateApplicationInvoiceData(int $applicationId, string $invoiceId): void
	{
		$this->database->query(
			'UPDATE training_applications SET ? WHERE id_application = ?',
			array(
				'invoice_id' => ((int)$invoiceId ?: null),
			),
			$applicationId
		);
	}


	private function resolveSource(string $note = null): string
	{
		if ($note && $this->vranaResolver->isTrainingApplicationOwner($note)) {
			$source = self::SOURCE_JAKUB_VRANA;
		} else {
			$source = self::SOURCE_MICHAL_SPACEK;
		}
		return $source;
	}


	private function generateAccessCode(): string
	{
		return Random::generate(mt_rand(12, 16), '0-9a-zA-Z');
	}


	public function getApplicationById(int $id): ?Row
	{
		$result = $this->database->fetch(
			'SELECT
				ua.action AS trainingAction,
				d.id_date AS dateId,
				d.start AS trainingStart,
				d.end AS trainingEnd,
				a.id_application AS applicationId,
				s.status,
				a.status_time AS statusTime,
				a.name,
				a.email,
				a.familiar,
				a.company,
				a.street,
				a.city,
				a.zip,
				a.country,
				a.company_id AS companyId,
				a.company_tax_id AS companyTaxId,
				a.note,
				a.price,
				a.vat_rate AS vatRate,
				a.price_vat AS priceVat,
				a.discount,
				a.invoice_id AS invoiceId,
				a.paid,
				a.access_token AS accessToken,
				sr.alias AS sourceAlias,
				sr.name AS sourceName
			FROM
				training_applications a
				LEFT JOIN training_dates d ON a.key_date = d.id_date
				JOIN trainings t ON (d.key_training = t.id_training OR a.key_training = t.id_training)
				JOIN training_application_status s ON a.key_status = s.id_status
				JOIN training_application_sources sr ON a.key_source = sr.id_source
				JOIN training_url_actions ta ON t.id_training = ta.key_training
				JOIN url_actions ua ON ta.key_url_action = ua.id_url_action
				JOIN languages l ON ua.key_language = l.id_language
			WHERE
				a.id_application = ?
				AND l.language = ?',
			$id,
			$this->translator->getDefaultLocale()
		);

		if ($result && $result->email) {
			$result->email = $this->emailEncryption->decrypt($result->email);
		}

		return $result;
	}


	/**
	 * @return Row[]
	 */
	public function getPreliminary(): array
	{
		$trainings = array();
		$result = $this->database->fetchAll(
			'SELECT
				t.id_training AS idTraining,
				ua.action,
				t.name
			FROM trainings t
				JOIN training_applications a ON a.key_training = t.id_training
				JOIN training_application_status s ON a.key_status = s.id_status
				JOIN training_url_actions ta ON t.id_training = ta.key_training
				JOIN url_actions ua ON ta.key_url_action = ua.id_url_action
				JOIN languages l ON ua.key_language = l.id_language
			WHERE
				a.key_date IS NULL
				AND s.status != ?
				AND l.language = ?',
			Statuses::STATUS_CANCELED,
			$this->translator->getDefaultLocale()
		);
		foreach ($result as $row) {
			$row->name = $this->translator->translate($row->name);
			$row->applications = array();
			$trainings[$row->idTraining] = $row;
		}

		$applications = $this->database->fetchAll(
			'SELECT
				a.id_application AS id,
				a.key_training AS idTraining,
				a.name,
				a.email,
				a.company,
				s.status,
				a.status_time AS statusTime,
				a.note,
				a.price,
				a.vat_rate AS vatRate,
				a.price_vat AS priceVat,
				a.invoice_id AS invoiceId,
				a.paid,
				sr.name AS sourceName
			FROM
				training_applications a
				JOIN training_application_status s ON a.key_status = s.id_status
				JOIN training_application_sources sr ON a.key_source = sr.id_source
			WHERE
				a.key_date IS NULL
				AND s.status != ?',
			Statuses::STATUS_CANCELED
		);

		if ($applications) {
			foreach ($applications as $row) {
				if ($row->email) {
					$row->email = $this->emailEncryption->decrypt($row->email);
				}
				$row->sourceNameInitials = $this->getSourceNameInitials($row->sourceName);
				$trainings[$row->idTraining]->applications[] = $row;
			}
		}

		return $trainings;
	}


	/**
	 * @return integer[]
	 */
	public function getPreliminaryCounts(): array
	{
		$upcoming = array_keys($this->trainingDates->getPublicUpcoming());

		$total = $dateSet = 0;
		foreach ($this->getPreliminary() as $training) {
			if (in_array($training->action, $upcoming)) {
				$dateSet += count($training->applications);
			}
			$total += count($training->applications);
		}

		return array($total, $dateSet);
	}


	public function getApplicationByToken(string $token): ?Row
	{
		$result = $this->database->fetch(
			'SELECT
				ua.action AS trainingAction,
				d.id_date AS dateId,
				a.id_application AS applicationId,
				a.name,
				a.email,
				a.company,
				a.street,
				a.city,
				a.zip,
				a.country,
				a.company_id AS companyId,
				a.company_tax_id AS companyTaxId,
				a.note
			FROM
				training_applications a
				JOIN training_dates d ON a.key_date = d.id_date
				JOIN trainings t ON d.key_training = t.id_training
				JOIN training_url_actions ta ON t.id_training = ta.key_training
				JOIN url_actions ua ON ta.key_url_action = ua.id_url_action
				JOIN languages l ON ua.key_language = l.id_language
			WHERE
				a.access_token = ?
				AND l.language = ?',
			$token,
			$this->translator->getDefaultLocale()
		);

		if ($result && $result->email) {
			$result->email = $this->emailEncryption->decrypt($result->email);
		}

		return $result;
	}


	public function setPaidDate(string $invoiceId, string $paid): ?int
	{
		$paidDate = ($paid ? new DateTime($paid) : null);

		$result = $this->database->query(
			'UPDATE training_applications SET ? WHERE invoice_id = ?',
			array(
				'paid'           => ($paidDate ?: null),
				'paid_timezone'  => ($paidDate ? $paidDate->getTimezone()->getName() : null),
			),
			(int)$invoiceId
		);
		return $result->getRowCount();
	}


	private function getTrainingApplicationSource(string $source): int
	{
		return $this->database->fetchField('SELECT id_source FROM training_application_sources WHERE alias = ?', $source);
	}


	/**
	 * @return Row[]
	 */
	public function getTrainingApplicationSources(): array
	{
		return $this->database->fetchAll(
			'SELECT
				id_source AS sourceId,
				alias,
				name
			FROM
				training_application_sources'
		);
	}


	public function setAccessTokenUsed(Row $application): void
	{
		if ($application->status != Statuses::STATUS_ACCESS_TOKEN_USED) {
			$this->trainingStatuses->updateStatus($application->applicationId, Statuses::STATUS_ACCESS_TOKEN_USED);
		}
	}


	/**
	 * Shorten source name.
	 *
	 * Removes Czech private limited company designation, if any, and uses only initials from the original name.
	 * Example:
	 *   Michal Špaček -> MŠ
	 *   Internet Info, s.r.o. -> II
	 *
	 * @param string $name
	 * @return string
	 */
	private function getSourceNameInitials(string $name): string
	{
		$name = preg_replace('/,? s\.r\.o./', '', $name);
		preg_match_all('/(?<=\s|\b)\pL/u', $name, $matches);
		return strtoupper(implode('', current($matches)));
	}


	public function setFamiliar(int $applicationId): void
	{
		$this->database->query('UPDATE training_applications SET familiar = TRUE WHERE id_application = ?', $applicationId);
	}

}
