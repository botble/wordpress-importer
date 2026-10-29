<?php

namespace Botble\WordpressImporter\Tests\Unit;

use Botble\Blog\Models\Category;
use Botble\Blog\Models\Tag;
use Botble\WordpressImporter\WordpressImporter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use SimpleXMLElement;

/**
 * A WordPress post lists every category and tag as a <category> element. Only the
 * first one used to be read, so posts lost their extra categories and all tags.
 */
class PostTermsAndSlugsTest extends TestCase
{
    private WordpressImporter $importer;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../src/WordpressImporter.php';

        $this->importer = new WordpressImporter();

        $this->setProperty('categories', [
            'news' => ['id' => 3],
            'guides' => ['id' => 4],
        ]);
        $this->setProperty('tags', [
            'laravel' => ['id' => 7],
        ]);

        // Terms from an earlier run are found through their _wp_import_* meta.
        // Seed that lookup so the test needs no database.
        $this->setProperty('importedIdsMap', [
            Category::class . '|_wp_import_category_nicename' => ['archive' => 9],
            Tag::class . '|_wp_import_tag_slug' => ['php' => 12],
        ]);
    }

    private function setProperty(string $name, array $value): void
    {
        (new ReflectionProperty($this->importer, $name))->setValue($this->importer, $value);
    }

    private function termsFor(string $categoriesXml): array
    {
        $xml = '<item>' . $categoriesXml . '</item>';

        return (new ReflectionMethod($this->importer, 'postTermIds'))
            ->invoke($this->importer, new SimpleXMLElement($xml));
    }

    public function test_attaches_every_category_and_tag(): void
    {
        $terms = $this->termsFor(
            '<category domain="post_tag" nicename="laravel"><![CDATA[Laravel]]></category>'
            . '<category domain="category" nicename="news"><![CDATA[News]]></category>'
            . '<category domain="category" nicename="guides"><![CDATA[Guides]]></category>'
        );

        $this->assertSame([3, 4], $terms['categories']);
        $this->assertSame([7], $terms['tags']);
    }

    public function test_finds_terms_imported_in_an_earlier_run(): void
    {
        $terms = $this->termsFor(
            '<category domain="category" nicename="archive">Archive</category>'
            . '<category domain="post_tag" nicename="php">PHP</category>'
        );

        $this->assertSame([9], $terms['categories']);
        $this->assertSame([12], $terms['tags']);
    }

    public function test_ignores_unknown_terms_other_taxonomies_and_duplicates(): void
    {
        $terms = $this->termsFor(
            '<category domain="category" nicename="news">News</category>'
            . '<category domain="category" nicename="news">News</category>'
            . '<category domain="product_cat" nicename="shoes">Shoes</category>'
            . '<category domain="category" nicename="">Empty</category>'
        );

        $this->assertSame([3], $terms['categories']);
        $this->assertSame([], $terms['tags']);
    }

    public function test_decodes_percent_encoded_wordpress_slugs(): void
    {
        $candidate = new ReflectionMethod($this->importer, 'wpSlugCandidate');

        $this->assertSame('tin-tức', $candidate->invoke($this->importer, 'tin-t%e1%bb%a9c'));
        $this->assertSame('about-us', $candidate->invoke($this->importer, ' about-us '));
        $this->assertSame('', $candidate->invoke($this->importer, null));
    }
}
