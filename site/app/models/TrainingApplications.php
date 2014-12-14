<?php
namespace MichalSpacekCz;

use \Nette\Application\UI\Form;

/**
 * Training applications model.
 *
 * @author     Michal Špaček
 * @package    michalspacek.cz
 */
class TrainingApplications
{

	const STATUS_CREATED             = 'CREATED';              // 1
	const STATUS_TENTATIVE           = 'TENTATIVE';            // 2
	const STATUS_INVITED             = 'INVITED';              // 3
	const STATUS_SIGNED_UP           = 'SIGNED_UP';            // 4
	const STATUS_INVOICE_SENT        = 'INVOICE_SENT';         // 5
	const STATUS_NOTIFIED            = 'NOTIFIED';             // 6
	const STATUS_ATTENDED            = 'ATTENDED';             // 7
	const STATUS_MATERIALS_SENT      = 'MATERIALS_SENT';       // 8
	const STATUS_ACCESS_TOKEN_USED   = 'ACCESS_TOKEN_USED';    // 9
	const STATUS_CANCELED            = 'CANCELED';             // 10
	const STATUS_IMPORTED            = 'IMPORTED';             // 13
	const STATUS_NON_PUBLIC_TRAINING = 'NON_PUBLIC_TRAINING';  // 14
	const STATUS_REMINDED            = 'REMINDED';             // 15

	const DEFAULT_SOURCE  = 'michal-spacek';

	/** @var \Nette\Database\Connection */
	protected $database;

	/**
	 * php.vrana.cz notifier.
	 *
	 * @var \MichalSpacekCz\Notifier\Vrana
	 */
	protected $vranaNotifier;

	/** @var \MichalSpacekCz\TrainingDates */
	protected $trainingDates;

	/** @var \MichalSpacekCz\TrainingStatuses */
	protected $trainingStatuses;

	/** @var \MichalSpacekCz\Encryption\Email */
	protected $emailEncryption;

	/**
	 * Files directory, ends with a slash.
	 *
	 * @var string
	 */
	protected $filesDir;

	protected $emailFrom;

	private $dataRules = array(
		'name'         => array(Form::MIN_LENGTH => 3, Form::MAX_LENGTH => 200),
		'email'        => array(Form::MAX_LENGTH => 200),
		'company'      => array(Form::MIN_LENGTH => 3, Form::MAX_LENGTH => 200),
		'street'       => array(Form::MIN_LENGTH => 3, Form::MAX_LENGTH => 200),
		'city'         => array(Form::MIN_LENGTH => 2, Form::MAX_LENGTH => 200),
		'zip'          => array(Form::PATTERN => '([0-9]\s*){5}', Form::MAX_LENGTH => 200),
		'companyId'    => array(Form::MIN_LENGTH => 6, Form::MAX_LENGTH => 200),
		'companyTaxId' => array(Form::MIN_LENGTH => 6, Form::MAX_LENGTH => 200),
		'note'         => array(Form::MAX_LENGTH => 2000),
	);

	private $statusCallbacks = array();


	public function __construct(
		\Nette\Database\Connection $connection,
		\MichalSpacekCz\Notifier\Vrana $vranaNotifier,
		\MichalSpacekCz\TrainingDates $trainingDates,
		\MichalSpacekCz\TrainingStatuses $trainingStatuses,
		\MichalSpacekCz\Encryption\Email $emailEncryption
	)
	{
		$this->database = $connection;
		$this->vranaNotifier = $vranaNotifier;
		$this->trainingDates = $trainingDates;
		$this->trainingStatuses = $trainingStatuses;
		$this->emailEncryption = $emailEncryption;
		$this->statusCallbacks[self::STATUS_NOTIFIED] = array($this, 'notifyCallback');
	}


	public function getByStatus($status)
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
				t.name AS trainingName,
				t.action AS trainingAction,
				d.start AS trainingStart,
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
				a.access_token AS accessToken
			FROM
				training_applications a
				JOIN training_dates d ON a.key_date = d.id_date
				JOIN trainings t ON d.key_training = t.id_training
				JOIN training_venues v ON d.key_venue = v.id_venue
				JOIN training_application_status s ON a.key_status = s.id_status
			WHERE
				s.status = ?',
			$status
		);

		if ($result) {
			foreach ($result as $row) {
				$row->email = $this->emailEncryption->decrypt($row->email);
			}
		}

		return $result;
	}


	public function getByDate($dateId)
	{
		$result = $this->database->fetchAll(
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
				a.paid
			FROM
				training_applications a
				JOIN training_application_status s ON a.key_status = s.id_status
			WHERE
				key_date = ?',
			$dateId
		);

		if ($result) {
			foreach ($result as $row) {
				$row->email = $this->emailEncryption->decrypt($row->email);
			}
		}

		return $result;
	}


	public function getValidByDate($dateId)
	{
		$discardedStatuses = $this->trainingStatuses->getDiscardedStatuses();
		return array_filter($this->getByDate($dateId), function($value) use ($discardedStatuses) {
			return !in_array($value->status, $discardedStatuses);
		});
	}


	public function getValidUnpaidByDate($dateId)
	{
		return array_filter($this->getValidByDate($dateId), function($value) {
			return !isset($value->paid);
		});
	}


	private function insertData($data)
	{
		$data['access_token'] = $this->generateAccessCode();
		try {
			$this->database->query('INSERT INTO training_applications', $data);
		} catch (\PDOException $e) {
			if ($e->getCode() == '23000') {
				if ($e->errorInfo[1] == '1062') {  // Integrity constraint violation: 1062 Duplicate entry '...' for key 'access_code_UNIQUE'
					// regenerate the access code and try harder this time
					\Nette\Diagnostics\Debugger::log("Regenerating access token, {$data['access_token']} already exists. Full data: " . implode(', ', $data));
					return $this->insertData($data);
				}
			}
			throw $e;
		}
		return $data['access_token'];
	}


	public function addInvitation($trainingId, $name, $email, $company, $street, $city, $zip, $companyId, $companyTaxId, $note)
	{
		return $this->insertApplication(
			$trainingId,
			$name,
			$email,
			$company,
			$street,
			$city,
			$zip,
			$companyId,
			$companyTaxId,
			$note,
			self::STATUS_TENTATIVE,
			self::DEFAULT_SOURCE
		);
	}


	public function addApplication($trainingId, $name, $email, $company, $street, $city, $zip, $companyId, $companyTaxId, $note)
	{
		return $this->insertApplication(
			$trainingId,
			$name,
			$email,
			$company,
			$street,
			$city,
			$zip,
			$companyId,
			$companyTaxId,
			$note,
			self::STATUS_SIGNED_UP,
			self::DEFAULT_SOURCE
		);
	}


	public function insertApplication($trainingId, $name, $email, $company, $street, $city, $zip, $companyId, $companyTaxId, $note, $status, $source, $date = null)
	{
		if (!in_array($status, $this->getInitialStatuses())) {
			throw new \RuntimeException("Invalid initial status {$status}");
		}

		$statusId = $this->trainingStatuses->getStatusId(self::STATUS_CREATED);
		$datetime = new \DateTime($date);

		$this->database->beginTransaction();
		$data = array(
			'key_date'             => $trainingId,
			'name'                 => $name,
			'email'                => $this->emailEncryption->encrypt($email),
			'company'              => $company,
			'street'               => $street,
			'city'                 => $city,
			'zip'                  => $zip,
			'company_id'           => $companyId,
			'company_tax_id'       => $companyTaxId,
			'note'                 => $note,
			'key_status'           => $statusId,
			'status_time'          => $datetime,
			'status_time_timezone' => $datetime->getTimezone()->getName(),
			'key_source'           => $this->getTrainingApplicationSource($source),
		);
		$code = $this->insertData($data);
		$applicationId = $this->database->getInsertId();
		$this->setStatus($applicationId, $status, $date);
		$this->database->commit();

		return $applicationId;
	}


	public function updateApplication($applicationId, $name, $email, $company, $street, $city, $zip, $companyId, $companyTaxId, $note)
	{
		$this->database->beginTransaction();
		$this->updateApplicationData(
			$applicationId,
			$name,
			$email,
			$company,
			$street,
			$city,
			$zip,
			$companyId,
			$companyTaxId,
			$note
		);
		$this->setStatus($applicationId, self::STATUS_SIGNED_UP, null);
		$this->database->commit();
		return $applicationId;
	}


	public function updateApplicationData($applicationId, $name, $email, $company, $street, $city, $zip, $companyId, $companyTaxId, $note, $price = null, $vatRate = null, $priceVat = null, $discount = null, $invoiceId = null, $paid = null, $familiar = false)
	{
		if ($paid) {
			$paid = new \DateTime($paid);
		}

		$this->database->query(
			'UPDATE training_applications SET ? WHERE id_application = ?',
			array(
				'name'           => $name,
				'email'          => $this->emailEncryption->encrypt($email),
				'company'        => $company,
				'familiar'       => $familiar,
				'street'         => $street,
				'city'           => $city,
				'zip'            => $zip,
				'company_id'     => $companyId,
				'company_tax_id' => $companyTaxId,
				'note'           => $note,
				'price'          => ($price || $discount ? $price : null),
				'vat_rate'       => ($vatRate ?: null),
				'price_vat'      => ($priceVat ?: null),
				'discount'       => ($discount ?: null),
				'invoice_id'     => ($invoiceId ?: null),
				'paid'           => ($paid ?: null),
				'paid_timezone'  => ($paid ? $paid->getTimezone()->getName() : null),
			),
			$applicationId
		);
	}


	public function updateApplicationInvoiceData($applicationId, $invoiceId)
	{
		$this->database->query(
			'UPDATE training_applications SET ? WHERE id_application = ?',
			array(
				'invoice_id' => ($invoiceId ?: null),
			),
			$applicationId
		);
	}


	private function generateAccessCode()
	{
		return \Nette\Utils\Strings::random(mt_rand(32, 48), '0-9a-zA-Z');
	}


	/**
	 * Needs to be wrapped in transaction, not for public consumption, updateStatus() instead.
	 */
	protected function setStatus($applicationId, $status, $date)
	{
		$statusId = $this->trainingStatuses->getStatusId($status);

		$prevStatus = $this->database->fetch(
			'SELECT
				key_status AS statusId,
				status_time AS statusTime,
				status_time_timezone AS statusTimeTimeZone
			FROM
				training_applications
			WHERE
				id_application = ?',
			$applicationId
		);

		$datetime = new \DateTime($date);
		$this->database->query(
			'UPDATE training_applications SET ? WHERE id_application = ?',
			array(
				'key_status'           => $statusId,
				'status_time'          => $datetime,
				'status_time_timezone' => $datetime->getTimezone()->getName(),
			),
			$applicationId
		);

		$result = $this->database->query(
			'INSERT INTO training_application_status_history',
			array(
				'key_application'      => $applicationId,
				'key_status'           => $prevStatus->statusId,
				'status_time'          => $prevStatus->statusTime,
				'status_time_timezone' => $prevStatus->statusTimeTimeZone,
			)
		);

		if (isset($this->statusCallbacks[$status]) && is_callable($this->statusCallbacks[$status])) {
			call_user_func($this->statusCallbacks[$status], $applicationId);
		}

		return $result;
	}


	public function updateStatus($applicationId, $status, $date = null)
	{
		$this->database->beginTransaction();
		try {
			$this->setStatus($applicationId, $status, $date);
			$this->database->commit();
		} catch (\Exception $e) {
			$this->database->rollBack();
		}
	}


	public function getApplicationById($id)
	{
		$result = $this->database->fetch(
			'SELECT
				t.action,
				d.id_date AS dateId,
				d.start AS trainingStart,
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
				a.company_id AS companyId,
				a.company_tax_id AS companyTaxId,
				a.note,
				a.price,
				a.vat_rate AS vatRate,
				a.price_vat AS priceVat,
				a.discount,
				a.invoice_id AS invoiceId,
				a.paid
			FROM
				training_applications a
				JOIN training_dates d ON a.key_date = d.id_date
				JOIN trainings t ON d.key_training = t.id_training
				JOIN training_application_status s ON a.key_status = s.id_status
			WHERE
				id_application = ?',
			$id
		);

		if ($result) {
			$result->email = $this->emailEncryption->decrypt($result->email);
		}

		return $result;
	}


	public function getApplicationByToken($token)
	{
		$result = $this->database->fetch(
			'SELECT
				t.action,
				d.id_date AS dateId,
				a.id_application AS applicationId,
				a.name,
				a.email,
				a.company,
				a.street,
				a.city,
				a.zip,
				a.company_id AS companyId,
				a.company_tax_id AS companyTaxId,
				a.note
			FROM
				training_applications a
				JOIN training_dates d ON a.key_date = d.id_date
				JOIN trainings t ON d.key_training = t.id_training
			WHERE
				access_token = ?',
			$token
		);

		if ($result) {
			$result->email = $this->emailEncryption->decrypt($result->email);
		}

		return $result;
	}


	private function getTrainingApplicationSource($source)
	{
		return $this->database->fetchField('SELECT id_source FROM training_application_sources WHERE alias = ?', $source);
	}


	public function getTrainingApplicationSources()
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


	public function setFilesDir($dir)
	{
		if ($dir[strlen($dir) - 1] != '/') {
			$dir .= '/';
		}
		$this->filesDir = $dir;
	}


	public function getFiles($applicationId)
	{
		$files = $this->database->fetchAll(
			'SELECT
				f.id_file AS fileId,
				f.filename AS fileName,
				CAST(DATE(d.start) AS char) AS date
			FROM
				files f
				JOIN training_materials m ON f.id_file = m.key_file
				JOIN training_applications a ON m.key_application = a.id_application
				JOIN training_application_status s ON a.key_status = s.id_status
				JOIN training_dates d ON a.key_date = d.id_date
			WHERE
				a.id_application = ?
				AND s.status IN (?, ?, ?)',
			$applicationId,
			self::STATUS_ATTENDED,
			self::STATUS_MATERIALS_SENT,
			self::STATUS_ACCESS_TOKEN_USED
		);

		foreach ($files as $file) {
			$file->dirName = $this->filesDir . $file->date;
		}

		return $files;
	}


	public function getFile($applicationId, $token, $filename)
	{
		$file = $this->database->fetch(
			'SELECT
				f.id_file AS fileId,
				f.filename AS fileName,
				CAST(DATE(d.start) AS char) AS date
			FROM
				files f
				JOIN training_materials m ON f.id_file = m.key_file
				JOIN training_applications a ON m.key_application = a.id_application
				JOIN training_application_status s ON a.key_status = s.id_status
				JOIN training_dates d ON a.key_date = d.id_date
			WHERE
				a.id_application = ?
				AND a.access_token = ?
				AND f.filename = ?
				AND s.status IN (?, ?, ?)',
			$applicationId,
			$token,
			$filename,
			self::STATUS_ATTENDED,
			self::STATUS_MATERIALS_SENT,
			self::STATUS_ACCESS_TOKEN_USED
		);

		if ($file) {
			$file->dirName = $this->filesDir . $file->date;
		}

		return $file;
	}


	public function addFile(\Nette\Database\Row $training, \Nette\Http\FileUpload $file, array $applicationIds)
	{
		$name = basename($file->getSanitizedName());
		$file->move($this->filesDir . $training->start->format('Y-m-d') . '/' . $name);

		$datetime = new \DateTime();
		$this->database->beginTransaction();

		$this->database->query(
			'INSERT INTO files',
			array(
				'filename'       => $name,
				'added'          => $datetime,
				'added_timezone' => $datetime->getTimezone()->getName(),
			)
		);
		$fileId = $this->database->getInsertId();
		foreach ($applicationIds as $applicationId) {
			$this->database->query(
				'INSERT INTO training_materials',
				array(
					'key_file'        => $fileId,
					'key_application' => $applicationId,
				)
			);
		}
		$this->database->commit();
		return $name;
	}


	public function logFileDownload($applicationId, $downloadId)
	{
		$this->database->query('INSERT INTO training_material_downloads', array(
			'key_application'   => $applicationId,
			'key_file_download' => $downloadId,
		));
	}


	public function setAccessTokenUsed(\Nette\Database\Row $application)
	{
		if ($application->status != self::STATUS_ACCESS_TOKEN_USED) {
			$this->database->beginTransaction();
			$this->setStatus($application->applicationId, self::STATUS_ACCESS_TOKEN_USED, null);
			$this->database->commit();
		}
	}


	public function getDataRules()
	{
		return $this->dataRules;
	}


	private function notifyCallback($applicationId)
	{
		$application = $this->getApplicationById($applicationId);
		$date = $this->trainingDates->get($application->dateId);
		if ($date->public && !$date->cooperationId) {
			$this->vranaNotifier->addTrainingApplication($application);
		}
	}


}
