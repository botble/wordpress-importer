<?php

namespace Botble\WordpressImporter;

use Botble\ACL\Models\User;
use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Facades\BaseHelper;
use Botble\Base\Facades\MetaBox;
use Botble\Blog\Models\Category;
use Botble\Blog\Models\Post;
use Botble\Blog\Models\Tag;
use Botble\Language\Facades\Language;
use Botble\Language\Models\LanguageMeta;
use Botble\Media\Facades\RvMedia;
use Botble\Page\Models\Page;
use Botble\Slug\Facades\SlugHelper;
use Botble\WordpressImporter\Services\RemoteImageDownloader;
use Botble\WordpressImporter\Support\ContentImageUrlRewriter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SimpleXMLElement;
use Throwable;
use XMLReader;

class WordpressImporter
{
    protected SimpleXMLElement $wpXML;

    protected array $users = [];

    protected array $attachments = [];

    /**
     * @var array<string, string> WP attachment ID => attachment URL
     */
    protected array $attachmentsById = [];

    protected ?RemoteImageDownloader $imageDownloader = null;

    protected array $categories = [];

    protected array $tags = [];

    protected array $posts = [];

    protected array $pages = [];

    protected bool $copyImages = true;

    protected bool $copyCategories = true;

    protected ?int $defaultCategoryId;

    protected bool $isUsingMultiLanguageV1 = false;

    protected bool $loadSEOMetaFromYoastSEO = true;

    protected array $imageUrlCache = [];

    protected array $failedImages = [];

    protected array $rowErrors = [];

    protected array $generatedPasswords = [];

    protected string $progressKey = '';

    protected bool $useStreamingParser = false;

    protected ?string $xmlFilePath = null;

    protected string $imageMode = 'sync';

    protected ?string $importId = null;

    /**
     * @var array<class-string, array<string, int>> Map of model class => [wp_meta_value => model_id]
     */
    protected array $importedIdsMap = [];

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
        $timeout = (int) $request->input('timeout', 900);

        @set_time_limit($timeout);
        @ini_set('max_execution_time', $timeout);
        @ini_set('default_socket_timeout', $timeout);

        // Lock memory_limit to a safe allow-list — `-1` (unlimited) would let
        // a crafted XML drive the process into OOM territory.
        $memoryLimit = (string) $request->input('memory_limit', '1024M');
        if (! in_array($memoryLimit, ['512M', '1024M', '2048M', '4096M'], true)) {
            $memoryLimit = '1024M';
        }
        @ini_set('memory_limit', $memoryLimit);

        $this->copyImages = (bool) $request->input('copyimages');
        $this->copyCategories = (bool) $request->input('copy_categories');
        if ($request->has('default_category_id')) {
            $this->defaultCategoryId = $request->input('default_category_id');
        }
        $this->loadSEOMetaFromYoastSEO = (bool) $request->input('load_seo_meta_from_yoast_seo');

        $this->imageMode = (string) $request->input('image_mode', 'sync');
        if (! in_array($this->imageMode, ['sync', 'external', 'queue'], true)) {
            $this->imageMode = 'sync';
        }

        // "Copy images" off means nothing is downloaded in any mode — featured
        // images included, which queue mode used to fetch regardless.
        if (! $this->copyImages) {
            $this->imageMode = 'external';
        }

        $this->progressKey = (string) $request->input('progress_key', 'wp-import:' . Str::uuid());
        $this->importId = (string) $request->input('import_id', (string) Str::uuid());

        $threshold = (int) config('plugins.wordpress-importer.streaming_threshold_bytes', 25 * 1024 * 1024);
        $fileSize = (int) @filesize($xmlFile);
        $this->useStreamingParser = $threshold > 0 && $fileSize > $threshold;
        $this->xmlFilePath = $xmlFile;

        // Harden libxml against billion-laughs / external-entity attacks:
        // disable the network resolver and any external entity loader. Then
        // reject XML that declares its own entities (a WP export never does).
        libxml_set_external_entity_loader(static fn () => null);

        if (! $this->useStreamingParser) {
            $xmlString = File::get($xmlFile);

            if ($this->containsEntityDeclaration($xmlString)) {
                return [
                    'error' => true,
                    'message' => trans('plugins/wordpress-importer::wordpress-importer.xml_entity_declaration_rejected'),
                ];
            }

            // LIBXML_NONET blocks network entity resolution; LIBXML_NOCDATA keeps existing behaviour.
            $this->wpXML = new SimpleXMLElement(
                $this->stripInvalidXml($xmlString),
                LIBXML_NOCDATA | LIBXML_NONET
            );
        } else {
            // Probe the first 64 KB of the streaming file the same way.
            $sniff = (string) @file_get_contents($xmlFile, false, null, 0, 65536);
            if ($this->containsEntityDeclaration($sniff)) {
                return [
                    'error' => true,
                    'message' => trans('plugins/wordpress-importer::wordpress-importer.xml_entity_declaration_rejected'),
                ];
            }
        }

        return [
            'error' => false,
            'progress_key' => $this->progressKey,
            'import_id' => $this->importId,
        ];
    }

    public function getProgressKey(): string
    {
        return $this->progressKey;
    }

    public function getImportId(): string
    {
        return $this->importId ?? '';
    }

    /**
     * Looks for `<!ENTITY` / `<!DOCTYPE ... [` declarations. A genuine WordPress
     * export never emits either — their presence is a strong signal that the
     * XML was crafted to mount a billion-laughs or external-entity attack.
     */
    protected function containsEntityDeclaration(string $xml): bool
    {
        // Strip leading whitespace/BOM; only need to scan the prologue + first DOCTYPE block.
        $head = substr($xml, 0, 16384);

        if (preg_match('/<!ENTITY\b/i', $head)) {
            return true;
        }

        // Reject any DOCTYPE that includes an internal subset ("<!DOCTYPE name [ ... ]").
        if (preg_match('/<!DOCTYPE\s+[^>\[]*\[/i', $head)) {
            return true;
        }

        return false;
    }

    protected function stripInvalidXml(string $value): string
    {
        $ret = '';
        if (empty($value)) {
            return $ret;
        }

        $length = strlen($value);
        for ($i=0; $i < $length; $i++) {
            $current = ord($value[$i]);
            if (($current == 0x9) ||
                ($current == 0xA) ||
                ($current == 0xD) ||
                (($current >= 0x20) && ($current <= 0xD7FF)) ||
                (($current >= 0xE000) && ($current <= 0xFFFD)) ||
                (($current >= 0x10000) && ($current <= 0x10FFFF))) {
                $ret .= chr($current);
            } else {
                $ret .= ' ';
            }
        }

        return $ret;
    }

    public function import(): array
    {
        if (defined('LANGUAGE_MODULE_SCREEN_NAME') && ! config('plugins.blog.general.use_language_v2', false)) {
            $this->isUsingMultiLanguageV1 = true;
        }

        $this->emitProgress('starting', 0);

        if ($this->useStreamingParser) {
            $this->saveAttachmentsStreaming();
            $this->saveAuthorsStreaming();
        } else {
            $this->saveAttachments();
            $this->saveAuthors();
        }

        if (is_plugin_active('blog')) {
            if ($this->copyCategories) {
                $this->saveCategories();
            }

            $this->saveTags();
            $this->savePostsAndPages();
        }

        $this->savePostsAndPages('page');

        $this->emitProgress('done', count($this->posts) + count($this->pages));

        $imageJobsDispatched = $this->dispatchQueuedImageJobs();

        // Stash raw passwords in cache (out-of-band) instead of returning them
        // through the HTTP response — kept for 30 min, retrievable by import_id.
        if (! empty($this->generatedPasswords) && $this->importId) {
            Cache::put(
                'wp-import:' . $this->importId . ':credentials',
                $this->generatedPasswords,
                now()->addMinutes(30)
            );
        }

        return [
            'categories' => count($this->categories),
            'tags' => count($this->tags),
            'posts' => count($this->posts),
            'pages' => count($this->pages),
            'users' => count($this->users),
            'failed_images' => $this->failedImages,
            'row_errors' => $this->rowErrors,
            'credentials_generated_count' => count($this->generatedPasswords),
            'import_id' => $this->importId,
            'progress_key' => $this->progressKey,
            'image_jobs_dispatched' => $imageJobsDispatched,
            'image_mode' => $this->imageMode,
        ];
    }

    protected function saveAttachments(): array
    {
        foreach ($this->wpXML->channel->item as $item) {
            $this->registerAttachment($item->children('wp', true));
        }

        return $this->attachments;
    }

    protected function saveAttachmentsStreaming(): array
    {
        foreach ($this->streamItems() as $item) {
            $this->registerAttachment($item->children('wp', true));
        }

        return $this->attachments;
    }

    protected function registerAttachment(SimpleXMLElement $wpData): void
    {
        if ($wpData->post_type != 'attachment') {
            return;
        }

        $url = (string) $wpData->attachment_url;

        // By ID for `_thumbnail_id` lookups; by parent as a fallback for exports
        // that lack the meta (last upload attached to the post wins).
        $this->attachmentsById[(string) $wpData->post_id] = $url;
        $this->attachments[(string) $wpData->post_parent] = $url;
    }

    /**
     * The featured image is the attachment named by the post's `_thumbnail_id`
     * meta. It may live anywhere in the media library, so only fall back to
     * "an image uploaded to this post" when the meta is missing.
     */
    protected function resolveFeaturedImageUrl(string $wpPostId, array $postMeta): string
    {
        $thumbnailId = (string) $this->getMetaValue($postMeta, '_thumbnail_id');

        if ($thumbnailId !== '' && isset($this->attachmentsById[$thumbnailId])) {
            return $this->attachmentsById[$thumbnailId];
        }

        return $this->attachments[$wpPostId] ?? '';
    }

    protected function saveAuthors(): array
    {
        $wpData = $this->wpXML->channel->children('wp', true);

        foreach ($wpData->author as $author) {
            $this->processAuthor($author);
        }

        return $this->users;
    }

    protected function saveAuthorsStreaming(): array
    {
        foreach ($this->wpChannelElements('author') as $author) {
            $this->processAuthor($author);
        }

        return $this->users;
    }

    /**
     * Channel-level <wp:author>, <wp:category> and <wp:tag> entries, shaped the
     * way the in-memory parser exposes them ($element->field) in either mode.
     */
    protected function wpChannelElements(string $name): iterable
    {
        if (! $this->useStreamingParser) {
            yield from $this->wpXML->channel->children('wp', true)->{$name};

            return;
        }

        $matches = fn (XMLReader $reader) => $reader->localName === $name && $reader->namespaceURI !== '';

        foreach ($this->streamElements($matches) as $element) {
            // A streamed node is its own document root: select the wp namespace
            // so its fields read like the in-memory tree's.
            yield $element->children('wp', true);
        }
    }

    protected function processAuthor(SimpleXMLElement $author): void
    {
        $this->preloadImportedIds(User::class, '_wp_import_user_login');

        $username = (string) $author->author_login;
        if (empty($username)) {
            return;
        }

        $email = (string) $author->author_email;

        $existingUser = User::query()
            ->when($email !== '', fn ($q) => $q->where('email', $email))
            ->orWhere('username', $username)
            ->first();

        if ($existingUser) {
            $this->users[$username] = [
                'first_name' => (string) $author->author_first_name,
                'last_name' => (string) $author->author_last_name,
                'email' => $email,
                'username' => $username,
                'id' => $existingUser->id,
            ];

            return;
        }

        $rawPassword = Str::random(16);

        $payload = [
            'first_name' => (string) $author->author_first_name,
            'last_name' => (string) $author->author_last_name,
            'email' => $email,
            'username' => $username,
            'password' => $rawPassword,
        ];

        $newUser = User::query()->create($payload);

        $this->users[$username] = $payload;
        $this->users[$username]['id'] = $newUser->id;

        $this->generatedPasswords[] = [
            'username' => $username,
            'email' => $email,
            'password' => $rawPassword,
        ];

        $this->markWpImported($newUser, '_wp_import_user_login', $username);
    }

    protected function saveCategories(): array
    {
        $this->preloadImportedIds(Category::class, '_wp_import_category_nicename');

        $order = 1;
        foreach ($this->wpChannelElements('category') as $category) {
            $nicename = (string) $category->category_nicename;
            if ($nicename === '') {
                continue;
            }

            if ($this->findWpImportedModel(Category::class, '_wp_import_category_nicename', $nicename)) {
                continue;
            }

            $this->categories[$nicename] = [
                'order' => $order,
                'name' => (string) $category->cat_name,
                'description' => (string) $category->category_description,
                'author_id' => auth()->id(),
                'author_type' => User::class,
            ];

            /**
             * @var Category $newCategory
             */
            $newCategory = Category::query()->create($this->categories[$nicename]);

            SlugHelper::createSlug($newCategory);

            if ($this->isUsingMultiLanguageV1) {
                LanguageMeta::saveMetaData($newCategory, Language::getDefaultLocaleCode());
            }

            $this->markWpImported($newCategory, '_wp_import_category_nicename', $nicename);

            $this->categories[$nicename]['parent'] = (string) $category->category_parent;
            $this->categories[$nicename]['id'] = $newCategory->id;

            $order += 1;
        }

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
        $this->preloadImportedIds(Tag::class, '_wp_import_tag_slug');

        $order = 1;

        foreach ($this->wpChannelElements('tag') as $tag) {
            $slug = (string) $tag->tag_slug;
            if ($slug === '') {
                continue;
            }

            if ($this->findWpImportedModel(Tag::class, '_wp_import_tag_slug', $slug)) {
                continue;
            }

            $this->tags[$slug] = [
                'order' => $order,
                'name' => (string) $tag->tag_name,
                'author_id' => auth()->id(),
                'author_type' => User::class,
            ];

            /**
             * @var Tag $newTag
             */
            $newTag = Tag::query()->create($this->tags[$slug]);

            SlugHelper::createSlug($newTag);

            if ($this->isUsingMultiLanguageV1) {
                LanguageMeta::saveMetaData($newTag, Language::getDefaultLocaleCode());
            }

            $this->markWpImported($newTag, '_wp_import_tag_slug', $slug);

            $this->tags[$slug]['id'] = $newTag->id;

            $order += 1;
        }

        return $this->tags;
    }

    protected function savePostsAndPages(string $type = 'post'): array
    {
        $chunkSize = (int) config('plugins.wordpress-importer.chunk_size', 50);
        if ($chunkSize < 1) {
            $chunkSize = 50;
        }

        $modelClass = $type === 'page' ? Page::class : Post::class;
        $metaKey = $type === 'page' ? '_wp_import_page_id' : '_wp_import_post_id';
        $this->preloadImportedIds($modelClass, $metaKey);

        $totalProcessed = 0;

        if ($this->useStreamingParser) {
            // In streaming mode buffering SimpleXMLElement keeps the underlying DOM
            // subtree alive — defeats the purpose. Process each item in its own
            // transaction; trade transaction overhead for predictable memory.
            foreach ($this->streamItems() as $item) {
                $wpData = $item->children('wp', true);

                if ((string) $wpData->post_type !== $type) {
                    continue;
                }

                if ($this->processSingleItem($item, $type)) {
                    $totalProcessed++;
                }

                unset($item);

                if (($totalProcessed % $chunkSize) === 0 && $totalProcessed > 0) {
                    gc_collect_cycles();
                    $this->emitProgress("posts.{$type}", $totalProcessed);
                }
            }
        } else {
            $processedInChunk = 0;
            $chunkBuffer = [];

            foreach ($this->wpXML->channel->item as $item) {
                $wpData = $item->children('wp', true);

                if ((string) $wpData->post_type !== $type) {
                    continue;
                }

                $chunkBuffer[] = $item;
                $processedInChunk++;

                if ($processedInChunk >= $chunkSize) {
                    $totalProcessed += $this->processItemChunk($chunkBuffer, $type);
                    $chunkBuffer = [];
                    $processedInChunk = 0;
                    gc_collect_cycles();
                    $this->emitProgress("posts.{$type}", $totalProcessed);
                }
            }

            if (! empty($chunkBuffer)) {
                $totalProcessed += $this->processItemChunk($chunkBuffer, $type);
                gc_collect_cycles();
                $this->emitProgress("posts.{$type}", $totalProcessed);
            }
        }

        return [
            'posts' => $this->posts,
            'pages' => $this->pages,
        ];
    }

    protected function processSingleItem(SimpleXMLElement $item, string $type): bool
    {
        $processed = false;

        try {
            DB::transaction(function () use ($item, $type, &$processed) {
                $processed = $this->processItem($item, $type);
            });
        } catch (Throwable $exception) {
            $wpData = $item->children('wp', true);
            $this->rowErrors[] = [
                'wp_post_id' => (string) $wpData->post_id,
                'title' => (string) $item->title,
                'error' => $exception->getMessage(),
            ];
            BaseHelper::logError($exception);
        }

        return $processed;
    }

    protected function processItemChunk(array $items, string $type): int
    {
        $processed = 0;

        DB::transaction(function () use ($items, $type, &$processed) {
            foreach ($items as $item) {
                try {
                    if ($this->processItem($item, $type)) {
                        $processed++;
                    }
                } catch (Throwable $e) {
                    $wpData = $item->children('wp', true);
                    $this->rowErrors[] = [
                        'wp_post_id' => (string) $wpData->post_id,
                        'title' => (string) $item->title,
                        'error' => $e->getMessage(),
                    ];
                    BaseHelper::logError($e);
                }
            }
        });

        return $processed;
    }

    protected function processItem(SimpleXMLElement $item, string $type): bool
    {
        $wpData = $item->children('wp', true);
        $wpPostId = (string) $wpData->post_id;
        $metaKey = $type === 'page' ? '_wp_import_page_id' : '_wp_import_post_id';
        $modelClass = $type === 'page' ? Page::class : Post::class;

        if ($wpPostId !== '' && $existing = $this->findWpImportedModel($modelClass, $metaKey, $wpPostId)) {
            $this->repairExternalImages($existing);

            return false;
        }

        $postMeta = [];
        foreach ($wpData->postmeta as $value) {
            $postMeta[] = (array) $value;
        }

        $content = $item->children('content', true);
        $excerpt = $item->children('excerpt', true);
        $image = $this->resolveFeaturedImageUrl($wpPostId, $postMeta);

        $author = null;
        $dc = $item->children('dc', true);
        if (isset($dc->creator)) {
            $author = (string) $dc->creator;
        }

        $category = null;
        if (isset($item->category['nicename'])) {
            $category = (string) $item->category['nicename'];
        }

        $status = BaseStatusEnum::PUBLISHED;
        if (isset($wpData->status) && $wpData->status != 'publish') {
            $status = BaseStatusEnum::DRAFT;
        }

        $slug = (string) $wpData->post_name;
        if (empty($slug)) {
            $slug = $type === 'post' ? 'post-' . $wpPostId : 'page-' . $wpPostId;
        }

        if ($type === 'post') {
            $data = [
                'author_id' => ! empty($this->users[$author]['id']) ? $this->users[$author]['id'] : auth()->id(),
                'author_type' => User::class,
                'name' => trim((string) $item->title, '"'),
                'description' => Str::limit(trim((string) $excerpt->encoded, '" \n'), 400, ''),
                'content' => $this->replaceContentImages($this->autop(trim((string) $content->encoded, '" \n'))),
                'image' => $this->resolveFeaturedImage($image),
                'status' => $status,
            ];

            $this->posts[] = $data;

            $post = new Post();
            $post->fill($data);
            if ($wpData->post_date) {
                $post->created_at = Carbon::parse((string) $wpData->post_date);
                $post->updated_at = Carbon::parse((string) $wpData->post_date);
            }
            $post->views = $this->getMetaValue($postMeta, 'post_views_count', 0);
            $post->save();

            if (! $this->copyCategories && ! empty($this->defaultCategoryId)) {
                $post->categories()->attach($this->defaultCategoryId);
            } elseif (! empty($this->categories[$category]['id'])) {
                $post->categories()->attach($this->categories[$category]['id']);
            }

            if (SlugHelper::turnOffAutomaticUrlTranslationIntoLatin()) {
                $slug = urldecode($slug);
            }

            SlugHelper::createSlug($post, $slug);

            if ($this->isUsingMultiLanguageV1) {
                LanguageMeta::saveMetaData($post, Language::getDefaultLocaleCode());
            }

            $this->saveMetaBoxData($post, $postMeta);
            if ($wpPostId !== '') {
                $this->markWpImported($post, $metaKey, $wpPostId);
            }
        } else {
            $data = [
                'user_id' => ! empty($this->users[$author]['id']) ? $this->users[$author]['id'] : auth()->id(),
                'name' => trim((string) $item->title, '"'),
                'description' => Str::limit(trim((string) $excerpt->encoded, '" \n'), 400, ''),
                'content' => $this->replaceContentImages($this->autop(trim((string) $content->encoded, '" \n'))),
                'image' => $this->resolveFeaturedImage($image),
                'status' => $status,
                'template' => 'default',
            ];

            $this->pages[] = $data;

            $page = new Page();
            $page->fill($data);
            if ($item->pubDate) {
                $page->created_at = Carbon::parse((string) $item->pubDate);
                $page->updated_at = Carbon::parse((string) $item->pubDate);
            }
            $page->save();

            SlugHelper::createSlug($page);

            if ($this->isUsingMultiLanguageV1) {
                LanguageMeta::saveMetaData($page, Language::getDefaultLocaleCode());
            }

            $this->saveMetaBoxData($page, $postMeta);
            if ($wpPostId !== '') {
                $this->markWpImported($page, $metaKey, $wpPostId);
            }
        }

        return true;
    }

    protected function saveMetaBoxData(Model $model, array $postMeta): void
    {
        if (! $this->loadSEOMetaFromYoastSEO) {
            return;
        }

        $seoMeta = [];

        $titleKeys = ['_yoast_wpseo_title', 'rank_math_title'];
        $descKeys = ['_yoast_wpseo_metadesc', 'rank_math_description'];

        foreach ($titleKeys as $key) {
            if ($value = $this->getMetaValue($postMeta, $key)) {
                $seoMeta['seo_title'] = $value;
                break;
            }
        }

        foreach ($descKeys as $key) {
            if ($value = $this->getMetaValue($postMeta, $key)) {
                $seoMeta['seo_description'] = $value;
                break;
            }
        }

        if ($seoMeta) {
            MetaBox::saveMetaBoxData($model, 'seo_meta', $seoMeta);
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

    protected function replaceContentImages(string $content): string
    {
        if ($this->imageMode === 'external') {
            return $content;
        }

        return ContentImageUrlRewriter::rewrite($content, function (string $originalUrl): ?string {
            if ($this->imageMode === 'queue') {
                $this->registerImageForQueue($originalUrl);

                return null;
            }

            // Image columns store the media path; HTML needs the public URL.
            return RvMedia::url($this->getImage($originalUrl));
        });
    }

    /**
     * Already-imported posts/pages are skipped, so re-running the import after
     * fixing the source (e.g. allowing the server through Cloudflare) would leave
     * images on the old host. Re-download whatever still points off-site.
     */
    protected function repairExternalImages(Model $model): void
    {
        if ($this->imageMode === 'external') {
            return;
        }

        $image = (string) $model->image;

        if (Str::startsWith($image, ['http://', 'https://'])) {
            $model->image = $this->resolveFeaturedImage($image);
        }

        // Compare before assigning: content casts may re-clean HTML and mark it dirty for nothing.
        $content = (string) $model->getRawOriginal('content');
        $localContent = $this->replaceContentImages($content);

        if ($localContent !== $content) {
            $model->content = $localContent;
        }

        if ($model->isDirty(['image', 'content'])) {
            $model->save();
        }
    }

    protected function resolveFeaturedImage(?string $image): ?string
    {
        if (empty($image) || $this->imageMode === 'external') {
            return $image;
        }

        if ($this->imageMode === 'queue') {
            $this->registerImageForQueue($image);

            return $image;
        }

        return $this->getImage($image);
    }

    protected function getImage(?string $image): ?string
    {
        if (empty($image) || $this->imageMode === 'external') {
            return $image;
        }

        if (array_key_exists($image, $this->imageUrlCache)) {
            return $this->imageUrlCache[$image];
        }

        try {
            $result = $this->imageDownloader()->download($image, 'posts', $this->importId);
        } catch (Throwable $exception) {
            // Keep the original URL so the post still renders while the source is up.
            $this->failedImages[] = ['url' => $image, 'error' => $exception->getMessage()];
            $result = $image;
        }

        return $this->imageUrlCache[$image] = $result;
    }

    protected function imageDownloader(): RemoteImageDownloader
    {
        return $this->imageDownloader ??= new RemoteImageDownloader();
    }

    protected function registerImageForQueue(string $url): void
    {
        if (! ContentImageUrlRewriter::isAbsoluteUrl($url)) {
            return;
        }

        if (! isset($this->imageUrlCache[$url])) {
            $this->imageUrlCache[$url] = $url;
        }
    }

    protected function dispatchQueuedImageJobs(): int
    {
        if ($this->imageMode !== 'queue' || empty($this->imageUrlCache)) {
            return 0;
        }

        if (! class_exists(\Botble\WordpressImporter\Models\WordpressImportImage::class)) {
            return 0;
        }

        $allowedHosts = (array) config('plugins.wordpress-importer.allowed_image_hosts', ['*']);

        $imageModel = \Botble\WordpressImporter\Models\WordpressImportImage::class;
        $job = \Botble\WordpressImporter\Jobs\DownloadWordPressImage::class;

        $dispatched = 0;

        foreach (array_keys($this->imageUrlCache) as $url) {
            if (! is_string($url) || $url === '') {
                continue;
            }

            if (! $this->imageDownloader()->isHostAllowed($url, $allowedHosts)) {
                $this->failedImages[] = ['url' => $url, 'error' => 'Host not allowed'];

                continue;
            }

            $hash = $imageModel::hashUrl($url);

            $row = $imageModel::query()->firstOrCreate(
                [
                    'import_id' => $this->importId,
                    'url_hash' => $hash,
                ],
                [
                    'original_url' => $url,
                    'status' => 'pending',
                    'attempts' => 0,
                ]
            );

            if ($row->status === 'done') {
                continue;
            }

            $job::dispatch($row->original_url, $this->importId);
            $dispatched++;
        }

        return $dispatched;
    }

    protected function emitProgress(string $stage, int $count): void
    {
        if ($this->progressKey === '') {
            return;
        }

        Cache::put($this->progressKey, [
            'stage' => $stage,
            'count' => $count,
            'updated_at' => now()->toIso8601String(),
        ], now()->addMinutes(30));
    }

    protected function streamItems(): \Generator
    {
        yield from $this->streamElements(fn (XMLReader $reader) => $reader->localName === 'item' && $reader->namespaceURI === '');
    }

    /**
     * Yield each element accepted by $matches as SimpleXML, one at a time.
     *
     * After a match, next() jumps over its subtree so the parser can release it;
     * the loop must not read() again after that, or an element directly
     * following (compact XML, no whitespace between items) would be skipped.
     */
    protected function streamElements(callable $matches): \Generator
    {
        $reader = new XMLReader();
        // LIBXML_NONET blocks network resolution of external entities/DTDs in the streamed input.
        if (! $reader->open($this->xmlFilePath, null, LIBXML_NONET)) {
            return;
        }

        $more = $reader->read();

        while ($more) {
            if ($reader->nodeType === XMLReader::ELEMENT && $matches($reader)) {
                $node = $this->readerNodeToSimpleXml($reader);

                if ($node !== null) {
                    yield $node;
                }

                $more = $reader->next();

                continue;
            }

            $more = $reader->read();
        }

        $reader->close();
    }

    /**
     * simplexml_import_dom($reader->expand()) returns null ("Imported Node must
     * have associated Document"), which broke every import over the streaming
     * threshold. Re-parse the node's XML instead; NOCDATA matches the in-memory
     * parser so CDATA postmeta (e.g. _thumbnail_id) reads as plain strings.
     */
    protected function readerNodeToSimpleXml(XMLReader $reader): ?SimpleXMLElement
    {
        $xml = $reader->readOuterXml();

        if ($xml === '') {
            return null;
        }

        $node = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);

        // Compare to false explicitly: an element whose children are all namespaced
        // (<item> holding only wp:* nodes) is falsy as a SimpleXMLElement.
        return $node === false ? null : $node;
    }

    /**
     * Pre-load all wp-imported IDs for a given (modelClass, metaKey) pair into an
     * in-memory map. Subsequent calls to findWpImportedModel() become O(1) lookups
     * instead of one DB query per row.
     */
    protected function preloadImportedIds(string $modelClass, string $metaKey): void
    {
        $cacheKey = $modelClass . '|' . $metaKey;
        if (isset($this->importedIdsMap[$cacheKey])) {
            return;
        }

        $this->importedIdsMap[$cacheKey] = [];

        try {
            \Botble\Base\Models\MetaBox::query()
                ->where('meta_key', $metaKey)
                ->where('reference_type', $modelClass)
                ->select(['reference_id', 'meta_value'])
                ->chunkById(500, function ($rows) use ($cacheKey) {
                    foreach ($rows as $row) {
                        $value = is_array($row->meta_value) ? ($row->meta_value[0] ?? null) : $row->meta_value;
                        if ($value === null || $value === '') {
                            continue;
                        }
                        $this->importedIdsMap[$cacheKey][(string) $value] = (int) $row->reference_id;
                    }
                });
        } catch (Throwable $exception) {
            BaseHelper::logError($exception);
        }
    }

    protected function findWpImportedModel(string $modelClass, string $metaKey, string $metaValue): ?Model
    {
        $cacheKey = $modelClass . '|' . $metaKey;

        if (! isset($this->importedIdsMap[$cacheKey])) {
            $this->preloadImportedIds($modelClass, $metaKey);
        }

        $id = $this->importedIdsMap[$cacheKey][$metaValue] ?? null;

        if ($id === null) {
            return null;
        }

        try {
            return $modelClass::query()->find($id);
        } catch (Throwable) {
            return null;
        }
    }

    protected function markWpImported(Model $model, string $metaKey, string $metaValue): void
    {
        try {
            MetaBox::saveMetaBoxData($model, $metaKey, $metaValue);
            $cacheKey = $model::class . '|' . $metaKey;
            $this->importedIdsMap[$cacheKey][$metaValue] = (int) $model->getKey();
        } catch (Throwable $exception) {
            BaseHelper::logError($exception);
        }
    }
}
