<?php
declare(strict_types = 1);

namespace MichalSpacekCz\Www\Presenters;

use MichalSpacekCz\Application\LocaleLinkGeneratorInterface;
use MichalSpacekCz\Media\Exceptions\ContentTypeException;
use MichalSpacekCz\Media\SlidesPlatform;
use MichalSpacekCz\Talks\Exceptions\TalkDoesNotExistException;
use MichalSpacekCz\Talks\Exceptions\UnknownSlideException;
use MichalSpacekCz\Talks\TalkLocaleUrls;
use MichalSpacekCz\Talks\Talks;
use MichalSpacekCz\Talks\TalkSlides;
use MichalSpacekCz\Talks\TalksList;
use MichalSpacekCz\Talks\TalksListFactory;
use MichalSpacekCz\Training\Dates\UpcomingTrainingDates;
use Nette\Application\BadRequestException;
use Nette\Application\UI\InvalidLinkException;
use Nette\Http\IResponse;

class TalksPresenter extends BasePresenter
{

	/** @var array<string, array{name: string}> */
	private array $localeLinkParams = [];


	public function __construct(
		private readonly Talks $talks,
		private readonly TalkSlides $talkSlides,
		private readonly TalkLocaleUrls $talkLocaleUrls,
		private readonly UpcomingTrainingDates $upcomingTrainingDates,
		private readonly TalksListFactory $talksListFactory,
		private readonly LocaleLinkGeneratorInterface $localeLinkGenerator,
	) {
		parent::__construct();
	}


	/**
	 * @throws ContentTypeException
	 */
	public function renderDefault(): void
	{
		$this->template->pageTitle = $this->translator->translate('messages.title.talks');
		$this->template->favoriteTalks = $this->talks->getFavorites();
		$this->template->upcomingTalks = $this->talks->getUpcoming();

		$talks = [];
		foreach ($this->talks->getAll() as $talk) {
			$talks[$talk->getDate()->format('Y')][] = $talk;
		}
		$this->template->talks = $talks;
	}


	/**
	 * @param string $name
	 * @param string|null $slide
	 * @throws InvalidLinkException
	 * @throws ContentTypeException
	 */
	public function actionTalk(string $name, ?string $slide = null): void
	{
		try {
			$talk = $this->talks->get($name);
			if ($talk->getLocale() !== $this->translator->getDefaultLocale()) {
				$this->redirectUrl($this->localeLinkGenerator->links(...$this->getLocaleLinksGeneratorParams())[$talk->getLocale()]->getUrl(), IResponse::S301_MovedPermanently);
			}
			if ($talk->getSlidesTalkId()) {
				$slidesTalk = $this->talks->getById($talk->getSlidesTalkId());
				$slides = ($slidesTalk->isPublishSlides() ? $this->talkSlides->getSlides($slidesTalk->getId(), $slidesTalk->getFilenamesTalkId()) : []);
				$slideNo = $this->talkSlides->getSlideNo($talk->getSlidesTalkId(), $slide);
				$this->template->canonicalLink = $this->link('//:Www:Talks:talk', [$slidesTalk->getAction()]);
			} else {
				$slides = ($talk->isPublishSlides() ? $this->talkSlides->getSlides($talk->getId(), $talk->getFilenamesTalkId()) : []);
				$slideNo = $this->talkSlides->getSlideNo($talk->getId(), $slide);
				if ($slideNo !== null) {
					$this->template->canonicalLink = $this->link('//:Www:Talks:talk', [$talk->getAction()]);
				}
			}
		} catch (UnknownSlideException | TalkDoesNotExistException $e) {
			throw new BadRequestException($e->getMessage(), previous: $e);
		}
		foreach ($this->talkLocaleUrls->get($talk) as $locale => $action) {
			$this->localeLinkParams[$locale] = ['name' => $action];
		}

		$this->template->pageTitle = $this->talks->pageTitle('messages.title.talk', $talk);
		$this->template->pageHeader = $talk->getTitle();
		$this->template->talk = $talk;
		$this->template->slideNo = $slideNo;
		$this->template->slides = $slides;
		$this->template->ogImage = ($slides[$slideNo ?? 1]->image ?? ($talk->getOgImage() !== null ? sprintf($talk->getOgImage(), $slideNo ?? 1) : null));
		$this->template->upcomingTrainings = $this->upcomingTrainingDates->getPublicUpcoming();
		$this->template->video = $talk->getVideo()->setLazyLoad(count($slides) > 3);
		$this->template->slidesPlatform = $talk->getSlidesHref() ? SlidesPlatform::tryFromUrl($talk->getSlidesHref())?->getName() : null;
	}


	protected function getLocaleLinkAction(): string
	{
		return (count($this->localeLinkParams) > 1 ? parent::getLocaleLinkAction() : 'Www:Talks:');
	}


	/**
	 * @return array<string, array{name: string}>
	 */
	protected function getLocaleLinkParams(): array
	{
		return $this->localeLinkParams;
	}


	protected function createComponentTalksList(string $name): TalksList
	{
		return $this->talksListFactory->create();
	}

}
