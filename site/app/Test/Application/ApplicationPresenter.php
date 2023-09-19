<?php
declare(strict_types = 1);

namespace MichalSpacekCz\Test\Application;

use Closure;
use MichalSpacekCz\ShouldNotHappenException;
use MichalSpacekCz\Test\PrivateProperty;
use Nette\Application\AbortException;
use Nette\Application\Application;
use Nette\Application\IPresenterFactory;
use Nette\Application\UI\Presenter;
use ReflectionException;

class ApplicationPresenter
{

	public function __construct(
		private readonly IPresenterFactory $presenterFactory,
	) {
	}


	/**
	 * @throws ReflectionException
	 */
	public function setLinkCallback(Application $application, Closure $buildLink): void
	{
		PrivateProperty::setValue($application, 'presenter', new class ($buildLink) extends Presenter {

			/**
			 * @param Closure(string, list<mixed>): string $buildLink
			 * @noinspection PhpMissingParentConstructorInspection
			 */
			public function __construct(
				private readonly Closure $buildLink,
			) {
			}


			/**
			 * @param list<mixed>|mixed $args
			 */
			public function link(string $destination, $args = []): string
			{
				$args = func_num_args() < 3 && is_array($args)
					? $args
					: array_slice(func_get_args(), 1);
				return ($this->buildLink)($destination, array_values($args));
			}

		});
	}


	public function expectSendResponse(callable $function): bool
	{
		try {
			$function();
			return false;
		} catch (AbortException) {
			return true;
		}
	}


	public function createUiPresenter(string $existing, string $name, string $action): Presenter
	{
		$presenter = $this->presenterFactory->createPresenter($existing);
		if (!$presenter instanceof Presenter) {
			throw new ShouldNotHappenException('Presenter is of a wrong class ' . get_debug_type($presenter));
		}
		/** @noinspection PhpInternalEntityUsedInspection */
		$presenter->setParent(null, $name); // Set the name and also rename it
		$presenter->changeAction($action);
		return $presenter;
	}

}
