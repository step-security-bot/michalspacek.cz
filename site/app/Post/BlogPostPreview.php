<?php
declare(strict_types = 1);

namespace MichalSpacekCz\Post;

use MichalSpacekCz\Formatter\TexyFormatter;
use MichalSpacekCz\Training\Dates;
use Nette\Bridges\ApplicationLatte\DefaultTemplate;
use Spaze\ContentSecurityPolicy\Config;

class BlogPostPreview
{

	public function __construct(
		private readonly TexyFormatter $texyFormatter,
		private readonly Post $blogPost,
		private readonly Dates $trainingDates,
		private readonly Config $contentSecurityPolicy,
	) {
	}


	/**
	 * @param Data $post
	 * @param DefaultTemplate $template
	 * @param callable(): never $sendTemplate
	 * @return never
	 */
	public function sendPreview(Data $post, DefaultTemplate $template, callable $sendTemplate): never
	{
		$this->texyFormatter->disableCache();
		$template->setFile(__DIR__ . '/../Www/Presenters/templates/Post/default.latte');
		$template->post = $this->blogPost->format($post);
		$template->edits = $post->postId ? $this->blogPost->getEdits($post->postId) : [];
		$template->upcomingTrainings = $this->trainingDates->getPublicUpcoming();
		$template->showBreadcrumbsMenu = false;
		$template->showHeaderTabs = false;
		$template->showFooter = false;
		$this->blogPost->setTemplateTitleAndHeader($post, $template);
		foreach ($post->cspSnippets as $snippet) {
			$this->contentSecurityPolicy->addSnippet($snippet);
		}
		$sendTemplate();
	}

}
