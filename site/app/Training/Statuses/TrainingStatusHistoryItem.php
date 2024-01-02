<?php
declare(strict_types = 1);

namespace MichalSpacekCz\Training\Statuses;

use DateTimeImmutable;

readonly class TrainingStatusHistoryItem
{

	public function __construct(
		private int $id,
		private int $statusId,
		private string $status,
		private DateTimeImmutable $statusTime,
	) {
	}


	public function getId(): int
	{
		return $this->id;
	}


	public function getStatusId(): int
	{
		return $this->statusId;
	}


	public function getStatus(): string
	{
		return $this->status;
	}


	public function getStatusTime(): DateTimeImmutable
	{
		return $this->statusTime;
	}

}
