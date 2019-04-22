<?php
declare(strict_types = 1);

namespace MichalSpacekCz;

use Nette\Database\Row;
use Nette\Http\Url;
use Spaze\ContentSecurityPolicy\Config;

class Embed
{

	public const SLIDES_SLIDESHARE = 'slideshare';

	public const SLIDES_SPEAKERDECK = 'speakerdeck';

	public const VIDEO_VIMEO = 'vimeo';

	public const VIDEO_YOUTUBE = 'youtube';

	public const VIDEO_SLIDESLIVE = 'slideslive';

	/** @var Config */
	protected $contentSecurityPolicy;


	public function __construct(Config $contentSecurityPolicy)
	{
		$this->contentSecurityPolicy = $contentSecurityPolicy;
	}


	/**
	 * Get template vars for slides
	 * @param Row $talk
	 * @param int|null $slide
	 * @return array<string, null> with keys slidesEmbed, slidesDataSlide, slidesEmbedType
	 */
	public function getSlidesTemplateVars(Row $talk, ?int $slide = null): array
	{
		$type = $this->getSlidesType($talk);
		if ($type !== null) {
			$this->contentSecurityPolicy->addSnippet($type);
		}

		$embedHref = $talk->slidesEmbed;
		$dataSlide = null;

		if ($slide !== null) {
			switch ($type) {
				case self::SLIDES_SLIDESHARE:
					$url = new Url($embedHref);
					$url->appendQuery('startSlide=' . $slide);
					$embedHref = $url->getAbsoluteUrl();
					break;

				case self::SLIDES_SPEAKERDECK:
					$dataSlide = $slide;
					break;
			}
		}

		return array(
			'slidesEmbed' => $embedHref,
			'slidesDataSlide' => $dataSlide,
			'slidesEmbedType' => $type,
		);
	}


	/**
	 * Get template vars for video.
	 * @param Row $talk
	 * @return string[] with keys videoEmbed, videoEmbedType
	 */
	public function getVideoTemplateVars(Row $talk): array
	{
		$type = $this->getVideoType($talk);
		if ($type !== null) {
			$this->contentSecurityPolicy->addSnippet($type);
		}

		return [
			'videoEmbed' => $talk->videoEmbed,
			'videoEmbedType' => $type,
		];
	}


	/**
	 * @param Row $talk
	 * @return string|null
	 */
	private function getSlidesType(Row $talk): ?string
	{
		if (!$talk->slidesHref) {
			return null;
		}

		switch (parse_url($talk->slidesHref, PHP_URL_HOST)) {
			case 'www.slideshare.net':
				$type = self::SLIDES_SLIDESHARE;
				break;
			case 'speakerdeck.com':
				$type = self::SLIDES_SPEAKERDECK;
				break;
			default:
				$type = null;
				break;
		}
		return $type;
	}


	/**
	 * @param Row $video
	 * @return string|null
	 */
	private function getVideoType(Row $video): ?string
	{
		if (!$video->videoHref) {
			return null;
		}

		switch (parse_url($video->videoHref, PHP_URL_HOST)) {
			case 'www.youtube.com':
				$type = self::VIDEO_YOUTUBE;
				break;
			case 'vimeo.com':
				$type = self::VIDEO_VIMEO;
				break;
			case 'slideslive.com':
				$type = self::VIDEO_SLIDESLIVE;
				break;
			default:
				$type = null;
				break;
		}
		return $type;
	}

}
