<?php
declare(strict_types = 1);

namespace MichalSpacekCz\Form;

use DateTimeInterface;
use MichalSpacekCz\Training\Files\TrainingFiles;
use Nette\Utils\Html;

class TrainingFileFormFactory
{

	public function __construct(
		private readonly FormFactory $factory,
		private readonly TrainingFiles $trainingFiles,
	) {
	}


	/**
	 * @param callable(Html|string, string): void $onSuccess
	 * @param array<int, int> $applicationIdsAllowedFiles
	 */
	public function create(callable $onSuccess, DateTimeInterface $trainingStart, array $applicationIdsAllowedFiles): UiForm
	{
		$form = $this->factory->create();
		$form->addUpload('file', 'Soubor:');
		$form->addSubmit('submit', 'Přidat');
		$form->onSuccess[] = function (UiForm $form) use ($onSuccess, $trainingStart, $applicationIdsAllowedFiles): void {
			$values = $form->getFormValues();
			if ($values->file->isOk()) {
				$filename = $this->trainingFiles->addFile($trainingStart, $values->file, $applicationIdsAllowedFiles);
				$message = Html::el()->setText('Soubor ')
					->addHtml(Html::el('code')->setText($filename))
					->addHtml(Html::el()->setText(' byl přidán'));
				$onSuccess($message, 'info');
			} else {
				$onSuccess('Soubor nebyl vybrán nebo došlo k nějaké chybě při nahrávání', 'error');
			}
		};
		return $form;
	}

}
