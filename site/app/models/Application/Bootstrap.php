<?php
namespace MichalSpacekCz\Application;

// Load Nette Framework or autoloader generated by Composer
require __DIR__ . '/../../../vendor/autoload.php';

/**
 * The michalspacek.cz bootstrap class.
 *
 * @author     Michal Špaček
 * @package    michalspacek.cz
 */
class Bootstrap extends \Nette\Object
{

	/** @var string */
	const MODE_PRODUCTION = 'production';

	/** @var string */
	const MODE_DEVELOPMENT = 'development';

	/** @var string */
	const DEFAULT_PRESENTER = 'Homepage';

	/** @var string */
	const DEFAULT_ACTION = \Nette\Application\UI\Presenter::DEFAULT_ACTION;

	/** @var \Nette\Http\Request */
	private $httpRequest;

	/** @var \Nette\DI\Container */
	private $container;

	/** @var \Nette\Http\Response */
	private $httpResponse;

	/** @var \MichalSpacekCz\SecurityHeaders */
	private $securityHeaders;

	/** @var string */
	private $appDir;

	/** @var string */
	private $logDir;

	/** @var string */
	private $tempDir;

	/** @var string */
	private $environment;


	/**
	 * @param string $appDir
	 * @param string $logDir
	 * @param string $tempDir
	 * @param string $environment
	 */
	public function __construct($appDir, $logDir, $tempDir, $environment)
	{
		$this->appDir = $appDir;
		$this->logDir = $logDir;
		$this->tempDir = $tempDir;
		$this->environment = $environment;
	}


	public function run()
	{
		$configurator = new \Nette\Configurator();
		$configurator->addParameters(['appDir' => $this->appDir]);

		$configurator->setDebugMode($this->isDebugMode());
		$configurator->enableDebugger($this->logDir);
		$configurator->setTempDirectory($this->tempDir);

		$configurator->createRobotLoader()
			->addDirectory($this->appDir)
			->register();

		$existingFiles = array_filter($this->getConfigurationFiles(), function ($path) {
			return is_file($path);
		});
		foreach ($existingFiles as $filename) {
			$configurator->addConfig($filename, $configurator::NONE);
		}

		$this->container = $configurator->createContainer();

		$this->httpRequest = $this->container->getByType(\Nette\Http\IRequest::class);
		$this->httpResponse = $this->container->getByType(\Nette\Http\IResponse::class);

		$this->securityHeaders = $this->container->getByType(\MichalSpacekCz\SecurityHeaders::class);
		$this->securityHeaders->sendHeaders();

		$this->redirectToSecure();

		$application = $this->container->getByType(\Nette\Application\Application::class);
		$application->onRequest[] = function(\Nette\Application\Application $sender, \Nette\Application\Request $request) {
			$this->securityHeaders->sendCspHeader($request->getPresenterName(), $request->getParameter(\Nette\Application\UI\Presenter::ACTION_KEY));
		};
		$application->run();
	}


	private function getConfigurationFiles()
	{
		return array_unique(array(
			$this->appDir . '/config/extensions.neon',
			$this->appDir . '/config/config.neon',
			$this->appDir . '/config/parameters.neon',
			$this->appDir . '/config/presenters.neon',
			$this->appDir . '/config/services.neon',
			$this->appDir . '/config/routes.neon',
			$this->appDir . '/config/config.extra-' . $_SERVER['SERVER_NAME'] . '.neon',
			$this->appDir . '/config/config.local.neon',
		));
	}


	private function isDebugMode()
	{
		return ($this->environment === self::MODE_DEVELOPMENT);
	}


	private function redirectToSecure()
	{
		$fqdn = $this->container->getParameters()['domain']['fqdn'];
		$uri = $_SERVER['REQUEST_URI'];
		if ($_SERVER['HTTP_HOST'] !== $fqdn) {
			$this->securityHeaders->sendCspHeader(self::DEFAULT_PRESENTER, self::DEFAULT_ACTION);
			$this->httpResponse->redirect("https://{$fqdn}{$uri}", \Nette\Http\IResponse::S301_MOVED_PERMANENTLY);
			exit();
		}
	}

}
