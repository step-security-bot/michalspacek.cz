<?php
declare(strict_types = 1);

namespace MichalSpacekCz\Application\Cli;

use Override;

class NoCliArgs implements CliArgsProvider
{

	#[Override]
	public static function getArgs(): array
	{
		return [];
	}

}
