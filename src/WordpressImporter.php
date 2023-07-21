<?php

namespace Botble\WordpressImporter;

use Botble\ACL\Models\User;
use Botble\Base\Enums\BaseStatusEnum;
use Botble\Blog\Models\Category;
use Botble\Blog\Models\Post;
use Botble\Blog\Models\Tag;
use Botble\Language\Models\LanguageMeta;
use Botble\Page\Models\Page;
use Botble\Slug\Models\Slug;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Language;
use MetaBox;
use Mimey\MimeTypes;
use RvMedia;
use SimpleXMLElement;
use SlugHelper;

class WordpressImporter
{
    protected SimpleXMLElement $wpXML;

    protected array $users = [];

    protected array $attachments = [];

    protected array $categories = [];

    protected array $tags = [];

    protected array $posts = [];

    protected array $pages = [];

    protected bool $copyImages = true;

    protected bool $copyCategories = true;

    protected int|null $defaultCategoryId;

    protected string $userDefaultPassword = 'password';

    protected bool $isUsingMultiLanguageV1 = false;

    protected bool $loadSEOMetaFromYoastSEO = true;

    public function verifyRequest(Request $request): array
    {
        if (! $request->hasFile('wpexport')) {
            return [
                'error' => true,
                'message' => trans('plugins/wordpress-importer::wordpress-importer.xml_file_required'),
            ];
        }

        $mimeType = $request->file('wpexport')->getMimeType();

        if (! in_array($mimeType, ['text/xml', 'application/xml'])) {
            return [
                'error' => true,
                'message' => trans('plugins/wordpress-importer::wordpress-importer.invalid_xml_file'),
            ];
        }

        $xmlFile = $request->file('wpexport')->getRealPath();
        $timeout = $request->input('timeout', 900);

        @set_time_limit($timeout);
        @ini_set('max_execution_time', $timeout);
        @ini_set('default_socket_timeout', $timeout);

        $this->copyImages = (bool)$request->input('copyimages');
        $this->wpXML = simplexml_load_file($xmlFile, 'SimpleXMLElement', LIBXML_NOCDATA);
        $this->copyCategories = (bool)$request->input('copy_categories');
        if ($request->has('default_category_id')) {
            $this->defaultCategoryId = $request->input('default_category_id');
        }
        $this->loadSEOMetaFromYoastSEO = (bool)$request->input('load_seo_meta_from_yoast_seo');

        return [
            'error' => false,
        ];
    }

    public function import(): array
    {
        if (defined('LANGUAGE_MODULE_SCREEN_NAME') && ! config('plugins.blog.general.use_language_v2', false)) {
            $this->isUsingMultiLanguageV1 = true;
        }

        $this->saveAttachments();
        $this->saveAuthors();

        if ($this->copyCategories) {
            $this->saveCategories();
        }

        $this->saveTags();
        $this->savePostsAndPages();
        $this->savePostsAndPages('page');

        return [
            'categories' => count($this->categories),
            'tags' => count($this->tags),
            'posts' => count($this->posts),
            'pages' => count($this->pages),
            'users' => count($this->users),
        ];
    }

    protected function saveAttachments(): array
    {
        foreach ($this->wpXML->channel->item as $item) {
            // Save The Attachments in an array
            $wpData = $item->children('wp', true);
            if ($wpData->post_type == 'attachment') {
                $this->attachments[(string)$wpData->post_parent] = (string)$wpData->attachment_url;
            }
        }

        return $this->attachments;
    }

    protected function saveAuthors(): array
    {
        $wpData = $this->wpXML->channel->children('wp', true);

        foreach ($wpData->author as $author) {
            $username = (string)$author->author_login;
            $this->users[$username] = [
                'first_name' => (string)$author->author_first_name,
                'last_name' => (string)$author->author_last_name,
                'email' => (string)$author->author_email,
                'password' => Hash::make($this->userDefaultPassword),
                'username' => $username,
            ];

            $newUser = User::query()
                ->where('email', (string)$author->author_email)
                ->orWhere('username', $username)
                ->first();

            if (! $newUser) {
                $newUser = User::query()->create($this->users[$username]);
            }

            // store the new id in the array
            $this->users[$username]['id'] = $newUser->id;
        }

        return $this->users;
    }

    protected function saveCategories(): array
    {
        $wpData = $this->wpXML->channel->children('wp', true);

        $order = 1;
        foreach ($wpData->category as $category) {
            $this->categories[(string)$category->category_nicename] = [
                'order' => $order,
                'name' => (string)$category->cat_name,
                'description' => (string)$category->category_description,
                'author_id' => auth()->id(),
                'author_type' => User::class,
            ];

            $newCategory = Category::query()->create($this->categories[(string)$category->category_nicename]);

            Slug::query()->create([
                'reference_type' => Category::class,
                'reference_id' => $newCategory->id,
                'key' => Str::slug((string)$category->category_nicename),
                'prefix' => SlugHelper::getPrefix(Category::class),
            ]);

            if ($this->isUsingMultiLanguageV1) {
                LanguageMeta::saveMetaData($newCategory, Language::getDefaultLocaleCode());
            }

            $this->categories[(string)$category->category_nicename]['parent'] = (string)$category->category_parent;
            $this->categories[(string)$category->category_nicename]['id'] = $newCategory->id;

            $order += 1;
        }

        // Save any parent categories to their children
        foreach ($this->categories as $category) {
            if (! empty($category['parent'])) {
                $slug = SlugHelper::getSlug($category['parent'], SlugHelper::getPrefix(Category::class), Category::class);
                if ($slug) {
                    $category['parent_id'] = $slug->reference_id;
                    $thisCategory = Category::query()->find($category['id']);
                    if (isset($thisCategory->id)) {
                        $thisCategory->parent_id = $slug->reference_id;
                        $thisCategory->save();
                    }
                }
            }
        }

        return $this->categories;
    }

    protected function saveTags(): array
    {
        $wpData = $this->wpXML->channel->children('wp', true);

        $order = 1;

        foreach ($wpData->tag as $tag) {
            $this->tags[(string)$tag->tag_slug] = [
                'order' => $order,
                'name' => (string)$tag->tag_name,
                'author_id' => auth()->id(),
                'author_type' => User::class,
            ];

            $newTag = Tag::query()->create($this->tags[(string)$tag->tag_slug]);

            Slug::query()->create([
                'reference_type' => Tag::class,
                'reference_id' => $newTag->id,
                'key' => Str::slug((string)$tag->tag_slug),
                'prefix' => SlugHelper::getPrefix(Tag::class),
            ]);

            if ($this->isUsingMultiLanguageV1) {
                LanguageMeta::saveMetaData($newTag, Language::getDefaultLocaleCode());
            }

            $this->tags[(string)$tag->tag_slug]['id'] = $newTag->id;

            $order += 1;
        }

        return $this->tags;
    }

    protected function savePostsAndPages(string $type = 'post'): array
    {
        foreach ($this->wpXML->channel->item as $item) {
            $wpData = $item->children('wp', true);

            if (! in_array($wpData->post_type, ['post', 'page'])) {
                continue;
            }

            $postMeta = [];
            foreach ($wpData->postmeta as $value) {
                $postMeta[] = (array) $value;
            }

            $content = $item->children('content', true);
            $excerpt = $item->children('excerpt', true);
            $image = $this->attachments[(string)$wpData->post_id] ?? '';

            $author = null;
            $dc = $item->children('dc', true);
            if (isset($dc->creator)) {
                $author = (string)$dc->creator;
            }

            $category = null;
            if (isset($item->category['nicename'])) {
                $category = (string)$item->category['nicename'];
            }

            $status = BaseStatusEnum::PUBLISHED;
            if (isset($wpData->status) && $wpData->status != 'publish') {
                $status = BaseStatusEnum::DRAFT;
            }

            $slug = (string)$wpData->post_name;
            if (empty($slug)) {
                if ($type == 'post') {
                    $slug = 'post-' . $wpData->post_id;
                } elseif ($type == 'page') {
                    $slug = 'page-' . $wpData->post_id;
                }
            }

            if ($wpData->post_type == $type) {
                if ($type == 'post') {
                    $data = [
                        'author_id' => ! empty($this->users[$author]['id']) ? $this->users[$author]['id'] : auth()->id(),
                        'author_type' => User::class,
                        'name' => trim((string)$item->title, '"'),
                        'description' => Str::limit(trim((string)$excerpt->encoded, '" \n'), 400, ''),
                        'content' => $this->autop(trim((string)$content->encoded, '" \n')),
                        'image' => $this->getImage($image),
                        'status' => $status,
                    ];

                    $this->posts[] = $data;

                    $post = new Post();
                    $post->fill($data);
                    if ($wpData->post_date) {
                        $post->created_at = Carbon::parse((string)$wpData->post_date);
                        $post->updated_at = Carbon::parse((string)$wpData->post_date);
                    }
                    $post->views = $this->getMetaValue($postMeta, 'post_views_count', 0);
                    $post->save();

                    if (! $this->copyCategories && ! empty($this->defaultCategoryId)) {
                        $post->categories()->attach($this->defaultCategoryId);
                    } elseif (! empty($this->categories[$category]['id'])) {
                        $post->categories()->attach($this->categories[$category]['id']);
                    }

                    Slug::query()->create([
                        'reference_type' => Post::class,
                        'reference_id' => $post->getKey(),
                        'key' => Str::slug($slug),
                        'prefix' => SlugHelper::getPrefix(Post::class),
                    ]);

                    if ($this->isUsingMultiLanguageV1) {
                        LanguageMeta::saveMetaData($post, Language::getDefaultLocaleCode());
                    }

                    $this->saveMetaBoxData($post, $postMeta);
                } elseif ($type == 'page') {
                    $data = [
                        'user_id' => ! empty($this->users[$author]['id']) ? $this->users[$author]['id'] : auth()->id(),
                        'name' => trim((string)$item->title, '"'),
                        'description' => Str::limit(trim((string)$excerpt->encoded, '" \n'), 400, ''),
                        'content' => $this->autop(trim((string)$content->encoded, '" \n')),
                        'image' => $this->getImage($image),
                        'status' => $status,
                        'template' => 'default',
                    ];

                    $this->pages[] = $data;

                    $page = new Page();
                    $page->fill($data);
                    if ($item->pubDate) {
                        $page->created_at = Carbon::parse((string)$item->pubDate);
                        $page->updated_at = Carbon::parse((string)$item->pubDate);
                    }
                    $page->save();

                    Slug::query()->create([
                        'reference_type' => Page::class,
                        'reference_id' => $page->getKey(),
                        'key' => Str::slug($slug),
                        'prefix' => SlugHelper::getPrefix(Page::class),
                    ]);

                    if ($this->isUsingMultiLanguageV1) {
                        LanguageMeta::saveMetaData($page, Language::getDefaultLocaleCode());
                    }

                    $this->saveMetaBoxData($page, $postMeta);
                }
            }
        }

        return [
            'posts' => $this->posts,
            'pages' => $this->pages,
        ];
    }

    protected function saveMetaBoxData(Model $model, array $postMeta): void
    {
        if ($this->loadSEOMetaFromYoastSEO) {
            $seoMeta = [];

            if ($seoTitle = $this->getMetaValue($postMeta, '_yoast_wpseo_title')) {
                $seoMeta['seo_title'] = $seoTitle;
            }

            if ($seoDesc = $this->getMetaValue($postMeta, '_yoast_wpseo_metadesc')) {
                $seoMeta['seo_description'] = $seoDesc;
            }

            if ($seoMeta) {
                MetaBox::saveMetaBoxData($model, 'seo_meta', $seoMeta);
            }
        }
    }

    protected function getMetaValue(array $postMeta, string $key, $default = '')
    {
        return Arr::get(Arr::first($postMeta, function ($value) use ($key) {
            return Arr::get($value, 'meta_key') == $key;
        }, []), 'meta_value', $default);
    }

    /**
     * Replaces double line-breaks with paragraph elements.
     *
     * A group of regex replaces used to identify text formatted with newlines and
     * replace double line-breaks with HTML paragraph tags. The remaining
     * line-breaks after conversion become <<br />> tags, unless $br is set to '0'
     * or 'false'.
     *
     * https://gist.github.com/joshhartman/5381116
     *
     * @param string $pee The text which has to be formatted.
     * @param bool $br Optional. If set, this will convert all remaining line-breaks after paragraphing. Default true.
     * @return string Text which has been converted into correct paragraph tags.
     */
    protected function autop($pee, bool $br = true)
    {
        $preTags = [];

        if (trim($pee) === '') {
            return '';
        }

        $pee = $pee . "\n"; // just to make things a little easier, pad the end

        if (str_contains($pee, '<pre')) {
            $peeParts = explode('</pre>', $pee);
            $lastPee = array_pop($peeParts);
            $pee = '';
            $index = 0;

            foreach ($peeParts as $peePart) {
                $start = strpos($peePart, '<pre');

                // Malformed html?
                if ($start === false) {
                    $pee .= $peePart;

                    continue;
                }

                $name = '<pre wp-pre-tag-' . $index . '></pre>';
                $preTags[$name] = substr($peePart, $start) . '</pre>';

                $pee .= substr($peePart, 0, $start) . $name;
                $index++;
            }

            $pee .= $lastPee;
        }

        $pee = preg_replace('|<br />\s*<br />|', "\n\n", $pee);
        // Space things out a little
        $allBlocks = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|option|form|map|area|blockquote|address|math|style|p|h[1-6]|hr|fieldset|noscript|samp|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';
        $pee = preg_replace('!(<' . $allBlocks . '[^>]*>)!', "\n$1", $pee);
        $pee = preg_replace('!(</' . $allBlocks . '>)!', "$1\n\n", $pee);
        $pee = str_replace(["\r\n", "\r"], "\n", $pee); // cross-platform newlines
        if (str_contains($pee, '<object')) {
            $pee = preg_replace('|\s*<param([^>]*)>\s*|', '<param$1>', $pee); // no pee inside object/embed
            $pee = preg_replace('|\s*</embed>\s*|', '</embed>', $pee);
        }
        $pee = preg_replace("/\n\n+/", "\n\n", $pee); // take care of duplicates
        // make paragraphs, including one at the end
        $pees = preg_split('/\n\s*\n/', $pee, -1, PREG_SPLIT_NO_EMPTY);
        $pee = '';
        foreach ($pees as $tinkle) {
            $pee .= '<p>' . trim($tinkle, "\n") . "</p>\n";
        }
        $pee = preg_replace(
            '|<p>\s*</p>|',
            '',
            $pee
        ); // under certain strange conditions it could create a P of entirely whitespace
        $pee = preg_replace('!<p>([^<]+)</(div|address|form)>!', '<p>$1</p></$2>', $pee);
        $pee = preg_replace('!<p>\s*(</?' . $allBlocks . '[^>]*>)\s*</p>!', '$1', $pee); // don't pee all over a tag
        $pee = preg_replace('|<p>(<li.+?)</p>|', '$1', $pee); // problem with nested lists
        $pee = preg_replace('|<p><blockquote([^>]*)>|i', '<blockquote$1><p>', $pee);
        $pee = str_replace('</blockquote></p>', '</p></blockquote>', $pee);
        $pee = preg_replace('!<p>\s*(</?' . $allBlocks . '[^>]*>)!', '$1', $pee);
        $pee = preg_replace('!(</?' . $allBlocks . '[^>]*>)\s*</p>!', '$1', $pee);
        if ($br) {
            $pee = preg_replace_callback(
                '/<(script|style).*?<\/\\1>/s',
                function ($matches) {
                    return str_replace("\n", '<PreserveNewline />', $matches[0]);
                },
                $pee
            );
            $pee = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $pee); // optionally make line breaks
            $pee = str_replace('<PreserveNewline />', "\n", $pee);
        }
        $pee = preg_replace('!(</?' . $allBlocks . '[^>]*>)\s*<br />!', '$1', $pee);
        $pee = preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $pee);
        $pee = preg_replace("|\n</p>$|", '</p>', $pee);

        if (! empty($preTags)) {
            $pee = str_replace(array_keys($preTags), array_values($preTags), $pee);
        }

        return $pee;
    }

    protected function getImage(string|null $image): string|null
    {
        if (! empty($image) && $this->copyImages) {
            $info = pathinfo($image);

            try {
                $contents = file_get_contents($image);
            } catch (Exception) {
                return $image;
            }

            if (empty($contents)) {
                return $image;
            }

            $path = '/tmp';
            if (! File::isDirectory($path)) {
                File::makeDirectory($path);
            }

            $path = $path . '/' . $info['basename'];
            file_put_contents($path, $contents);

            $mimeType = (new MimeTypes())->getMimeType(File::extension($image));

            $fileUpload = new UploadedFile($path, $info['basename'], $mimeType, null, true);

            $result = RvMedia::handleUpload($fileUpload, 0, 'posts');

            File::delete($path);

            if (! $result['error']) {
                $image = $result['data']->url;
            }
        }

        return $image;
    }
}
