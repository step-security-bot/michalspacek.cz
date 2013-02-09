<?php

/**
 * The michalspacek.cz bootstrap file.
 */


// Load Nette Framework or autoloader generated by Composer
require __DIR__ . '/../libs/autoload.php';

// Hide Nette banner
if (!headers_sent()) {
	header('X-Driven-By: Success');
	header('X-Powered-By: Failure');
}

$configurator = new Nette\Config\Configurator;

$environment = (isset($_SERVER['ENVIRONMENT']) ? $_SERVER['ENVIRONMENT'] : 'production');

// Enable Nette Debugger for error visualisation & logging
$configurator->setDebugMode($environment == 'development');
$configurator->enableDebugger(__DIR__ . '/../log');

// Enable RobotLoader - this will load all classes automatically
$configurator->setTempDirectory(__DIR__ . '/../temp');
$configurator->createRobotLoader()
	->addDirectory(__DIR__)
	->register();

// Create Dependency Injection container from config.neon file
$configurator->addConfig(__DIR__ . '/config/config.neon', $environment);
$configurator->addConfig(__DIR__ . '/config/config.local.neon', $configurator::NONE); // none section
$container = $configurator->createContainer();

$httpResponse = $container->httpResponse;
$container->application->onStartup[] = function() use ($httpResponse) {
	$httpResponse->setHeader('X-Content-Type-Options', 'nosniff');
	$httpResponse->setHeader('X-XSS-Protection', '1; mode=block');
};

return $container;
