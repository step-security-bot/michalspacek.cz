<?php
use \Nette\Application\BadRequestException,
	\Nette\Application\Responses\FileResponse,
	\Nette\Http\Response;

/**
 * Soubory presenter.
 *
 * @author     Michal Špaček
 * @package    michalspacek.cz
 */
class SouboryPresenter extends BasePresenter
{


	public function actionSkoleni($filename)
	{
		$session = $this->getSession('application');
		if (!$session->applicationId) {
			throw new BadRequestException("Unknown application id, missing or invalid token", Response::S404_NOT_FOUND);
		}

		$file = $this->trainings->getFile($session->applicationId, $session->token, $filename);
		if (!$file) {
			throw new BadRequestException("No file {$filename} for application id {$session->applicationId}", Response::S404_NOT_FOUND);
		}

		$downloadId = $this->files->logDownload($file->fileId);
		$this->trainings->logFileDownload($session->applicationId, $downloadId);
		$this->sendFile("{$file->dirName}/{$file->fileName}");
	}


	public function actionSoubor($filename)
	{
		throw new BadRequestException("Cannot download {$filename}", Response::S404_NOT_FOUND);
	}


	protected function sendFile($file)
	{
		$this->sendResponse(new FileResponse($file, null, \Nette\Utils\MimeTypeDetector::fromFile($file)));
	}


}
