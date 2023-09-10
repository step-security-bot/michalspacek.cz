<?php
declare(strict_types = 1);

namespace MichalSpacekCz\Form;

use Contributte\Translation\Translator;
use DateTime;
use MichalSpacekCz\Application\Locales;
use MichalSpacekCz\Articles\Blog\BlogPost;
use MichalSpacekCz\Articles\Blog\BlogPostFactory;
use MichalSpacekCz\Articles\Blog\BlogPostPreview;
use MichalSpacekCz\Articles\Blog\BlogPostRecommendedLinks;
use MichalSpacekCz\Articles\Blog\BlogPosts;
use MichalSpacekCz\DateTime\Exceptions\InvalidTimezoneException;
use MichalSpacekCz\Form\Controls\TrainingControlsFactory;
use MichalSpacekCz\Formatter\TexyFormatter;
use MichalSpacekCz\ShouldNotHappenException;
use MichalSpacekCz\Tags\Tags;
use MichalSpacekCz\Twitter\Exceptions\TwitterCardNotFoundException;
use MichalSpacekCz\Twitter\TwitterCards;
use Nette\Application\UI\InvalidLinkException;
use Nette\Bridges\ApplicationLatte\DefaultTemplate;
use Nette\Database\UniqueConstraintViolationException;
use Nette\Forms\Controls\TextInput;
use Nette\Utils\Html;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Spaze\ContentSecurityPolicy\CspConfig;
use stdClass;

class PostFormFactory
{

	public function __construct(
		private readonly FormFactory $factory,
		private readonly Translator $translator,
		private readonly BlogPosts $blogPosts,
		private readonly BlogPostFactory $blogPostFactory,
		private readonly Tags $tags,
		private readonly TexyFormatter $texyFormatter,
		private readonly CspConfig $contentSecurityPolicy,
		private readonly TrainingControlsFactory $trainingControlsFactory,
		private readonly BlogPostPreview $blogPostPreview,
		private readonly TwitterCards $twitterCards,
		private readonly BlogPostRecommendedLinks $recommendedLinks,
		private readonly Locales $locales,
	) {
	}


	public function create(callable $onSuccessAdd, callable $onSuccessEdit, DefaultTemplate $template, callable $sendTemplate, ?BlogPost $post): UiForm
	{
		$form = $this->factory->create();
		$form->addInteger('translationGroup', 'Skupina překladů:')
			->setRequired(false);
		$form->addSelect('locale', 'Jazyk:', $this->locales->getAllLocales())
			->setRequired('Zadejte prosím jazyk')
			->setPrompt('- vyberte -');
		$form->addText('title', 'Titulek:')
			->setRequired('Zadejte prosím titulek')
			->addRule($form::MinLength, 'Titulek musí mít alespoň %d znaky', 3);
		$form->addText('slug', 'Slug:')
			->setRequired('Zadejte prosím slug')
			->addRule($form::MinLength, 'Slug musí mít alespoň %d znaky', 3);
		$this->addPublishedDate($form->addText('published', 'Vydáno:'))
			->setDefaultValue(date('Y-m-d') . ' HH:MM');
		$form->addText('previewKey', 'Klíč pro náhled:')
			->setRequired(false)
			->addRule($form::MinLength, 'Klíč pro náhled musí mít alespoň %d znaky', 3);
		$form->addTextArea('lead', 'Perex:')
			->addCondition($form::Filled)
			->addRule($form::MinLength, 'Perex musí mít alespoň %d znaky', 3);
		$form->addTextArea('text', 'Text:')
			->setRequired('Zadejte prosím text')
			->addRule($form::MinLength, 'Text musí mít alespoň %d znaky', 3);
		$form->addTextArea('originally', 'Původně vydáno:')
			->addCondition($form::Filled)
			->addRule($form::MinLength, 'Původně vydáno musí mít alespoň %d znaky', 3);
		$form->addText('ogImage', 'Odkaz na obrázek:')
			->setRequired(false)
			->addRule($form::MaxLength, 'Maximální délka odkazu na obrázek je %d znaků', 200);

		$cards = ['' => 'Žádná karta'];
		foreach ($this->twitterCards->getAll() as $card) {
			$cards[$card->getCard()] = $card->getTitle();
		}
		$form->addSelect('twitterCard', 'Twitter card', $cards);

		$form->addText('tags', 'Tagy:')
			->setRequired(false);
		$form->addText('recommended', 'Doporučené:')
			->setRequired(false);
		$form->addText('editSummary', 'Shrnutí editace:')
			->setRequired(false)
			->setDisabled(true)
			->addCondition($form::Filled)
			->addRule($form::MinLength, 'Shrnutí editace musí mít alespoň %d znaky', 3)
			->endCondition()
			->addRule($form::MaxLength, 'Maximální délka shrnutí editace je %d znaků', 200);

		$label = Html::el()->addText(Html::el('span', ['title' => 'Content Security Policy'])->setText('CSP'))->addText(' snippety:');
		$items = [];
		foreach ($this->contentSecurityPolicy->getSnippets() as $name => $snippet) {
			$allowed = [];
			foreach ($snippet as $directive => $values) {
				$allowed[] = trim($directive . ' ' . implode(' ', $values));
			}
			$items[$name] = $name . ': ' . implode('; ', $allowed);
		}
		$form->addMultiSelect('cspSnippets', $label, $items);

		$items = [];
		foreach ($this->blogPostFactory->getAllowedTags() as $name => $tags) {
			$allowed = [];
			foreach ($tags as $tag => $attributes) {
				$allowed[] = trim('<' . trim($tag . ' ' . implode(' ', $attributes)) . '>');
			}
			$items[$name] = $name . ': ' . implode(', ', $allowed);
		}
		$form->addMultiSelect('allowedTags', 'Povolené tagy:', $items);

		$form->addCheckbox('omitExports', 'Vynechat z RSS');

		$form->addSubmit('submit', 'Přidat');
		$form->addSubmit('preview', $this->translator->translate('messages.label.preview'))
			->setHtmlAttribute('data-loading-value', 'Moment…')
			->onClick[] = function () use ($form, $post, $template, $sendTemplate): void {
				$newPost = $this->buildPost($form->getFormValues(), $post?->getId());
				$this->blogPostPreview->sendPreview($newPost, $template, $sendTemplate);
			};

		$form->onValidate[] = function (UiForm $form) use ($post): void {
			$newPost = $this->buildPost($form->getFormValues(), $post?->getId());
			if ($newPost->needsPreviewKey() && $newPost->getPreviewKey() === null) {
				$input = $form->getComponent('previewKey');
				if (!$input instanceof TextInput) {
					throw new ShouldNotHappenException(sprintf("The 'previewKey' component should be '%s' but it's a %s", TextInput::class, get_debug_type($input)));
				}
				$input->addError(sprintf('Tento %s příspěvek vyžaduje klíč pro náhled', $newPost->getPublishTime() === null ? 'nepublikovaný' : 'budoucí'));
			}
		};
		$form->onSuccess[] = function (UiForm $form) use ($onSuccessAdd, $onSuccessEdit, $post): void {
			$values = $form->getFormValues();
			$newPost = $this->buildPost($values, $post?->getId());
			try {
				if ($post) {
					$this->blogPosts->update($newPost, empty($values->editSummary) ? null : $values->editSummary, $post->getSlugTags());
					$onSuccessEdit($newPost);
				} else {
					$this->blogPosts->add($newPost);
					$onSuccessAdd($newPost);
				}
			} catch (UniqueConstraintViolationException) {
				$slug = $form->getComponent('slug');
				if (!$slug instanceof TextInput) {
					throw new ShouldNotHappenException(sprintf("The 'slug' component should be '%s' but it's a %s", TextInput::class, get_debug_type($slug)));
				}
				$slug->addError($this->texyFormatter->translate('messages.blog.admin.duplicateslug'));
			}
		};
		if ($post) {
			$this->setDefaults($post, $form);
		}
		return $form;
	}


	/**
	 * @throws TwitterCardNotFoundException
	 * @throws JsonException
	 * @throws InvalidTimezoneException
	 * @throws InvalidLinkException
	 */
	private function buildPost(stdClass $values, ?int $postId): BlogPost
	{
		return $this->blogPostFactory->create(
			$postId,
			$values->slug,
			$values->locale,
			$this->locales->getLocaleById($values->locale),
			empty($values->translationGroup) ? null : $values->translationGroup,
			$values->title,
			empty($values->lead) ? null : $values->lead,
			$values->text,
			empty($values->published) ? null : new DateTime($values->published),
			empty($values->previewKey) ? null : $values->previewKey,
			empty($values->originally) ? null : $values->originally,
			empty($values->ogImage) ? null : $values->ogImage,
			empty($values->tags) ? [] : $this->tags->toArray($values->tags),
			empty($values->tags) ? [] : $this->tags->toSlugArray($values->tags),
			$values->recommended ? $this->recommendedLinks->getFromJson($values->recommended) : [],
			empty($values->twitterCard) ? null : $this->twitterCards->getCard($values->twitterCard),
			empty($values->cspSnippets) ? [] : $values->cspSnippets,
			empty($values->allowedTags) ? [] : $values->allowedTags,
			!empty($values->omitExports),
		);
	}


	private function addPublishedDate(TextInput $field, bool $required = false): TextInput
	{
		return $this->trainingControlsFactory->addDate(
			$field,
			$required,
			'YYYY-MM-DD HH:MM nebo DD.MM.YYYY HH:MM',
			'(\d{4}-\d{1,2}-\d{1,2} \d{1,2}:\d{2})|(\d{1,2}\.\d{1,2}\.\d{4} \d{1,2}:\d{2})',
		);
	}


	private function setDefaults(BlogPost $post, UiForm $form): void
	{
		$values = [
			'translationGroup' => $post->getTranslationGroupId(),
			'locale' => $post->getLocaleId(),
			'title' => $post->getTitleTexy(),
			'slug' => $post->getSlug(),
			'published' => $post->getPublishTime()?->format('Y-m-d H:i'),
			'previewKey' => $post->getPreviewKey(),
			'lead' => $post->getSummaryTexy(),
			'text' => $post->getTextTexy(),
			'originally' => $post->getOriginallyTexy(),
			'ogImage' => $post->getOgImage(),
			'twitterCard' => $post->getTwitterCard()?->getCard(),
			'tags' => $post->getTags() ? $this->tags->toString($post->getTags()) : null,
			'recommended' => empty($post->getRecommended()) ? null : Json::encode($post->getRecommended()),
			'cspSnippets' => $post->getCspSnippets(),
			'allowedTags' => $post->getAllowedTagsGroups(),
			'omitExports' => $post->omitExports(),
		];
		$form->setDefaults($values);
		$form->getComponent('editSummary')
			->setDisabled($post->needsPreviewKey());
		$form->getComponent('submit')->caption = 'Upravit';
	}

}
