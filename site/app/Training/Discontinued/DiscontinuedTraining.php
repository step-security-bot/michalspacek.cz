<?php
declare(strict_types = 1);

namespace MichalSpacekCz\Training\Discontinued;

class DiscontinuedTraining
{

	/**
	 * @param list<string> $trainings
	 */
	public function __construct(
		private readonly string $description,
		private readonly array $trainings,
		private readonly string $newHref,
	) {
	}


	public function getDescription(): string
	{
		return $this->description;
	}


	/**
	 * @return list<string>
	 */
	public function getTrainings(): array
	{
		return $this->trainings;
	}


	public function getNewHref(): string
	{
		return $this->newHref;
	}

}
