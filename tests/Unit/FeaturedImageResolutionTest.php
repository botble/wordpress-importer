<?php

namespace Botble\WordpressImporter\Tests\Unit;

use Botble\WordpressImporter\WordpressImporter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SimpleXMLElement;

/**
 * The featured image must come from the post's `_thumbnail_id` meta, not from
 * whichever attachment happened to be uploaded to the post last.
 */
class FeaturedImageResolutionTest extends TestCase
{
    private WordpressImporter $importer;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../src/WordpressImporter.php';

        $this->importer = new WordpressImporter();

        $xml = <<<'XML'
<rss xmlns:wp="http://wordpress.org/export/1.2/"><channel>
<item><wp:post_id>11</wp:post_id><wp:post_parent>5</wp:post_parent><wp:post_type>attachment</wp:post_type><wp:attachment_url>https://wp.test/u/featured.jpg</wp:attachment_url></item>
<item><wp:post_id>12</wp:post_id><wp:post_parent>5</wp:post_parent><wp:post_type>attachment</wp:post_type><wp:attachment_url>https://wp.test/u/inline.jpg</wp:attachment_url></item>
<item><wp:post_id>20</wp:post_id><wp:post_parent>0</wp:post_parent><wp:post_type>attachment</wp:post_type><wp:attachment_url>https://wp.test/u/library.jpg</wp:attachment_url></item>
<item><wp:post_id>5</wp:post_id><wp:post_parent>0</wp:post_parent><wp:post_type>post</wp:post_type></item>
</channel></rss>
XML;

        $register = new ReflectionMethod($this->importer, 'registerAttachment');

        foreach ((new SimpleXMLElement($xml))->channel->item as $item) {
            $register->invoke($this->importer, $item->children('wp', true));
        }
    }

    private function resolve(string $postId, array $postMeta): string
    {
        return (new ReflectionMethod($this->importer, 'resolveFeaturedImageUrl'))
            ->invoke($this->importer, $postId, $postMeta);
    }

    public function test_uses_thumbnail_id_even_when_other_images_were_uploaded_later(): void
    {
        $meta = [['meta_key' => '_thumbnail_id', 'meta_value' => '11']];

        $this->assertSame('https://wp.test/u/featured.jpg', $this->resolve('5', $meta));
    }

    public function test_uses_thumbnail_from_elsewhere_in_media_library(): void
    {
        $meta = [['meta_key' => '_thumbnail_id', 'meta_value' => '20']];

        $this->assertSame('https://wp.test/u/library.jpg', $this->resolve('5', $meta));
    }

    public function test_falls_back_to_attached_image_without_thumbnail_meta(): void
    {
        $this->assertSame('https://wp.test/u/inline.jpg', $this->resolve('5', []));
    }

    public function test_unknown_thumbnail_id_falls_back(): void
    {
        $meta = [['meta_key' => '_thumbnail_id', 'meta_value' => '999']];

        $this->assertSame('https://wp.test/u/inline.jpg', $this->resolve('5', $meta));
    }

    public function test_post_without_images_has_none(): void
    {
        $this->assertSame('', $this->resolve('77', []));
    }

    public function test_streaming_parser_yields_every_item_with_readable_thumbnail_meta(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wpx');
        // Compact on purpose: no whitespace between siblings.
        file_put_contents($path, '<?xml version="1.0"?><rss xmlns:wp="http://wordpress.org/export/1.2/" xmlns:content="http://purl.org/rss/1.0/modules/content/"><channel>'
            . '<wp:author><wp:author_login><![CDATA[admin]]></wp:author_login></wp:author>'
            . '<item><content:encoded><![CDATA[<p>Hi</p>]]></content:encoded><wp:post_id>5</wp:post_id>'
            . '<wp:postmeta><wp:meta_key><![CDATA[_thumbnail_id]]></wp:meta_key><wp:meta_value><![CDATA[11]]></wp:meta_value></wp:postmeta></item>'
            . '<item><wp:post_id>6</wp:post_id></item><item><wp:post_id>7</wp:post_id></item>'
            . '</channel></rss>');

        try {
            $property = new \ReflectionProperty($this->importer, 'xmlFilePath');
            $property->setValue($this->importer, $path);

            $items = iterator_to_array((new ReflectionMethod($this->importer, 'streamItems'))->invoke($this->importer), false);

            $this->assertSame(['5', '6', '7'], array_map(fn ($item) => (string) $item->children('wp', true)->post_id, $items));

            $this->assertSame('<p>Hi</p>', (string) $items[0]->children('content', true)->encoded);
            $this->assertSame(['meta_key' => '_thumbnail_id', 'meta_value' => '11'], (array) $items[0]->children('wp', true)->postmeta);

            (new \ReflectionProperty($this->importer, 'useStreamingParser'))->setValue($this->importer, true);

            // processAuthor()/saveCategories() read fields directly ($author->author_login),
            // exactly as the in-memory tree exposes them.
            $authors = iterator_to_array((new ReflectionMethod($this->importer, 'wpChannelElements'))->invoke($this->importer, 'author'), false);

            $this->assertCount(1, $authors);
            $this->assertSame('admin', (string) $authors[0]->author_login);
        } finally {
            @unlink($path);
        }
    }
}
