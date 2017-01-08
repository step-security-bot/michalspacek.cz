<?php
declare(strict_types = 1);

namespace MichalSpacekCz;

/**
 * Embed model.
 *
 * @author     Michal Špaček
 * @package    michalspacek.cz
 */
class Embed
{

	public const EMBED_SLIDES_SLIDESHARE = 'slideshare';

	public const EMBED_SLIDES_SPEAKERDECK = 'speakerdeck';

	public const EMBED_VIDEO_VIMEO = 'vimeo';

	public const EMBED_VIDEO_YOUTUBE = 'youtube';

	public const EMBED_VIDEO_SLIDESLIVE = 'slideslive';


	/**
	 * Get template vars for slides
	 * @param string|null $type
	 * @param string|null $embedHref
	 * @param int|null $slide
	 * @return string[slidesEmbed, slidesDataSlide]
	 */
	public function getSlidesTemplateVars(?string $type, ?string $embedHref, ?int $slide): array
	{
		$dataSlide = null;

		if ($slide !== null) {
			switch ($type) {
				case self::EMBED_SLIDES_SLIDESHARE:
					$url = new \Nette\Http\Url($embedHref);
					$url->appendQuery('startSlide=' . $slide);
					$embedHref = $url->getAbsoluteUrl();
					break;

				case self::EMBED_SLIDES_SPEAKERDECK:
					$dataSlide = $slide;
					break;
			}
		}

		return array(
			'slidesEmbed'     => $embedHref,
			'slidesDataSlide' => $dataSlide,
		);
	}


	/**
	 * @param string $href
	 * @return string
	 */
	public function getSlidesType(string $href): string
	{
		switch (parse_url($href, PHP_URL_HOST)) {
			case 'www.slideshare.net':
				$type = self::EMBED_SLIDES_SLIDESHARE;
				break;
			case 'speakerdeck.com':
				$type = self::EMBED_SLIDES_SPEAKERDECK;
				break;
			default:
				throw new \RuntimeException("Unknown slides type for {$href}");
				break;
		}
		return $type;
	}


	/**
	 * @param string $href
	 * @return string
	 */
	public function getVideoType(string $href): string
	{
		switch (parse_url($href, PHP_URL_HOST)) {
			case 'www.youtube.com':
				$type = self::EMBED_VIDEO_YOUTUBE;
				break;
			case 'vimeo.com':
				$type = self::EMBED_VIDEO_VIMEO;
				break;
			case 'slideslive.com':
				$type = self::EMBED_VIDEO_SLIDESLIVE;
				break;
			default:
				throw new \RuntimeException("Unknown video type for {$href}");
				break;
		}
		return $type;
	}

}
