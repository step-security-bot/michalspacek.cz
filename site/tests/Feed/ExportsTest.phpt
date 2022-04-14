<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection PhpDocRedundantThrowsInspection */
declare(strict_types = 1);

namespace MichalSpacekCz\Feed;

use DateTime;
use MichalSpacekCz\Articles\Articles;
use MichalSpacekCz\Formatter\Texy;
use MichalSpacekCz\Post\Edit;
use Nette\Caching\Storage;
use Nette\Caching\Storages\DevNullStorage;
use Nette\Database\Row;
use Nette\Utils\Html;
use SimpleXMLElement;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/../bootstrap.php';

/** @testCase */
class ExportsTest extends TestCase
{

	private Articles $articles;
	private Texy $texyFormatter;
	private Storage $cacheStorage;
	private Exports $exports;


	protected function setUp()
	{
		$this->articles = new class () extends Articles {

			/** @var array<int, Row> */
			private array $articles = [];


			/** @noinspection PhpMissingParentConstructorInspection Intentionally */
			public function __construct()
			{
			}


			public function getNearestPublishDate(): ?DateTime
			{
				return null;
			}


			public function addBlogPost(int $articleId, DateTime $published, string $suffix, ?array $edits = null): void
			{
				$this->articles[] = Row::from([
					'articleId' => $articleId,
					'title' => "Title {$suffix}",
					'href' => "https://example.com/{$suffix}",
					'published' => $published,
					'excerpt' => "Excerpt {$suffix}",
					'text' => "Text {$suffix}",
					'edits' => $edits,
					'isBlogPost' => true,
					'slugTags' => [],
				]);
			}


			/**
			 * @param int|null $limit
			 * @return array<int, Row>
			 */
			public function getAll(?int $limit = null): array
			{
				return $this->articles;
			}

		};
		$this->texyFormatter = new class () extends Texy {

			/** @noinspection PhpMissingParentConstructorInspection Intentionally */
			public function __construct()
			{
			}


			public function translate(string $message, array $replacements = []): Html
			{
				return Html::el()->setHtml($message);
			}

		};
		$this->cacheStorage = new DevNullStorage();
		$this->exports = new Exports($this->articles, $this->texyFormatter, $this->cacheStorage);
	}


	/**
	 * @throws \Nette\Application\BadRequestException No articles
	 */
	public function testGetArticlesNoArticles(): void
	{
		$this->exports->getArticles('https://example.com/no-articles');
	}


	public function testGetArticles(): void
	{
		$this->articles->addBlogPost(1, new DateTime(), 'one');
		$this->articles->addBlogPost(2, new DateTime(), 'two');
		$feed = $this->exports->getArticles('https://example.com/');
		$xml = simplexml_load_string((string)$feed);
		$this->assertEntry($xml->entry[0], 'one');
		$this->assertEntry($xml->entry[1], 'two');
	}


	public function testGetArticlesWithEdits(): void
	{
		$editsOne = [
			$this->buildEdit('2022-03-14 12:13:14 UTC', 'Edit one one'),
			$this->buildEdit('2022-04-14 12:13:14 UTC', 'Edit one two'),
		];
		$editsTwo = [
			$this->buildEdit('2022-03-15 12:13:14 UTC', 'Edit two one'),
		];
		$this->articles->addBlogPost(1, new DateTime(), 'one', $editsOne);
		$this->articles->addBlogPost(2, new DateTime(), 'two', $editsTwo);
		$feed = $this->exports->getArticles('https://example.com/');
		$xml = simplexml_load_string((string)$feed);
		$this->assertEntry($xml->entry[0], 'one', '<h3>messages.blog.post.edits</h3><ul><li><em><strong>14.3.</strong> Edit one one</em></li><li><em><strong>14.4.</strong> Edit one two</em></li></ul>Text one');
		$this->assertEntry($xml->entry[1], 'two', '<h3>messages.blog.post.edits</h3><ul><li><em><strong>15.3.</strong> Edit two one</em></li></ul>Text two');
	}


	private function assertEntry(SimpleXMLElement $entry, string $suffix, ?string $text = null): void
	{
		$link = "https://example.com/{$suffix}";
		Assert::same($link, (string)$entry->id, $suffix);
		Assert::same("Excerpt {$suffix}", (string)$entry->summary, $suffix);
		Assert::same("Title {$suffix}", (string)$entry->title, $suffix);
		Assert::same($link, (string)$entry->link['href'], $suffix);
		Assert::same($text ?? "Text {$suffix}", (string)$entry->content, $suffix);
	}


	private function buildEdit(string $editedAt, string $summary): Edit
	{
		$edit = new Edit();
		$edit->editedAt = new DateTime($editedAt);
		$edit->summary = Html::el()->setHtml($summary);
		$edit->summaryTexy = $summary;
		return $edit;
	}

}

(new ExportsTest())->run();
