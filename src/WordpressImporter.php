<?php

namespace Botble\WordpressImporter;

use Botble\ACL\Models\User;
use Botble\ACL\Repositories\Interfaces\UserInterface;
use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Events\CreatedContentEvent;
use Botble\Blog\Models\Category;
use Botble\Blog\Models\Post;
use Botble\Blog\Models\Tag;
use Botble\Blog\Repositories\Interfaces\CategoryInterface;
use Botble\Blog\Repositories\Interfaces\TagInterface;
use Botble\Page\Models\Page;
use Carbon\Carbon;
use DB;
use Exception;
use File;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Language;
use Mimey\MimeTypes;
use RvMedia;
use SlugHelper;

class WordpressImporter
{
    /**
     * @var \SimpleXMLElement
     */
    protected $wpXML;

    /**
     * @var array
     */
    protected $users = [];

    /**
     * @var array
     */
    protected $attachments = [];

    /**
     * @var array
     */
    protected $categories = [];

    /**
     * @var array
     */
    protected $tags = [];

    /**
     * @var array
     */
    protected $posts = [];

    /**
     * @var array
     */
    protected $pages = [];

    /**
     * @var bool
     */
    protected $copyImages = true;

    /**
     * @var bool
     */
    protected $copyCategories = true;

    /**
     * @var int
     */
    protected $defaultCategoryId;

    /**
     * @var string
     */
    protected $userDefaultPassword = 'password';

    /**
     * @param Request $request
     * @return array|false[]
     */
    public function verifyRequest(Request $request): array
    {
        if (!$request->hasFile('wpexport')) {
            return [
                'error'   => true,
                'message' => trans('plugins/wordpress-importer::wordpress-importer.xml_file_required'),
            ];
        }

        $mimeType = $request->file('wpexport')->getMimeType();

        if (!in_array($mimeType, ['text/xml', 'application/xml'])) {
            return [
                'error'   => true,
                'message' => trans('plugins/wordpress-importer::wordpress-importer.invalid_xml_file'),
            ];
        }

        $xmlFile = $request->file('wpexport')->getRealPath();
        $timeout = $request->input('timeout', 900);

        set_time_limit($timeout);
        ini_set('max_execution_time', $timeout);
        ini_set('default_socket_timeout', $timeout);

        $this->copyImages = (bool)$request->input('copyimages');
        $this->wpXML = simplexml_load_file($xmlFile, 'SimpleXMLElement', LIBXML_NOCDATA);
        $this->copyCategories = (bool)$request->input('copy_categories');
        if ($request->has('default_category_id')) {
            $this->defaultCategoryId = $request->input('default_category_id');
        }

        return [
            'error' => false,
        ];
    }

    /**
     * @return array
     */
    public function import()
    {
        $this->saveAttachments();
        $this->saveAuthors();

        if ($this->copyCategories) {
            $this->saveCategories();
            $this->syncLanguage(Category::class);
        }

        $this->saveTags();
        $this->savePostsAndPages('post');
        $this->syncLanguage(Tag::class);
        $this->syncLanguage(Post::class);
        $this->savePostsAndPages('page');
        $this->syncLanguage(Page::class);

        return [
            'categories' => count($this->categories),
            'tags'       => count($this->tags),
            'posts'      => count($this->posts),
            'pages'      => count($this->pages),
            'users'      => count($this->users),
        ];
    }

    /**
     * Save all the attachments in an array
     * @return array
     */
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

    /**
     * Create new users and load them into array
     * @return array
     */
    protected function saveAuthors(): array
    {
        $wpData = $this->wpXML->channel->children('wp', true);

        foreach ($wpData->author as $author) {
            $username = (string)$author->author_login;
            $this->users[$username] = [
                'first_name' => (string)$author->author_first_name,
                'last_name'  => (string)$author->author_last_name,
                'email'      => (string)$author->author_email,
                'password'   => Hash::make($this->userDefaultPassword),
                'username'   => $username,
            ];

            $newUser = app(UserInterface::class)
                ->getModel()
                ->where('email', (string)$author->author_email)
                ->orWhere('username', $username)
                ->first();

            if (!$newUser) {
                $newUser = app(UserInterface::class)->createOrUpdate($this->users[$username]);
            }

            // store the new id in the array
            $this->users[$username]['id'] = $newUser->id;
        }

        return $this->users;
    }

    /**
     * Create new categories and store them in the array
     * @return array
     */
    protected function saveCategories(): array
    {
        $wpData = $this->wpXML->channel->children('wp', true);

        $order = 1;
        foreach ($wpData->category as $category) {

            $this->categories[(string)$category->category_nicename] = [
                'order'       => $order,
                'name'        => (string)$category->cat_name,
                'description' => (string)$category->category_description,
                'author_id'   => auth()->user()->getAuthIdentifier(),
                'author_type' => User::class,
            ];

            $newCategory = app(CategoryInterface::class)->createOrUpdate($this->categories[(string)$category->category_nicename]);

            request()->merge([
                'slug' => (string)$category->category_nicename,
            ]);

            event(new CreatedContentEvent(CATEGORY_MODULE_SCREEN_NAME, request(), $newCategory));

            $this->categories[(string)$category->category_nicename]['parent'] = (string)$category->category_parent;
            $this->categories[(string)$category->category_nicename]['id'] = $newCategory->id;

            $order += 1;
        }

        // Save any parent categories to their children
        foreach ($this->categories as $category) {
            if (!empty($category['parent'])) {
                $slug = SlugHelper::getSlug($category['parent'], SlugHelper::getPrefix(Category::class), Category::class);
                if ($slug) {
                    $category['parent_id'] = $slug->reference_id;
                    $thisCategory = app(CategoryInterface::class)->findById($category['id']);
                    if (isset($thisCategory->id)) {
                        $thisCategory->parent_id = $slug->reference_id;
                        $thisCategory->save();
                    }
                }
            }
        }

        return $this->categories;
    }

    /**
     * @param string $reference
     * @return bool
     */
    protected function syncLanguage(string $reference): bool
    {
        if (defined('LANGUAGE_MODULE_SCREEN_NAME')) {
            if (!Language::getDefaultLanguage()) {
                return false;
            }

            $ids = DB::table('language_meta')
                ->where('reference_type', $reference)
                ->pluck('reference_id')
                ->all();

            $referenceIds = DB::table((new $reference)->getTable())
                ->whereNotIn('id', $ids)
                ->pluck('id')
                ->all();

            $data = [];
            foreach ($referenceIds as $referenceId) {
                $data[] = [
                    'reference_id'     => $referenceId,
                    'reference_type'   => $reference,
                    'lang_meta_code'   => Language::getDefaultLocaleCode(),
                    'lang_meta_origin' => md5($referenceId . $reference . time()),
                ];
            }

            DB::table('language_meta')->insert($data);
        }

        return true;
    }

    /**
     * @return array
     */
    protected function saveTags(): array
    {
        $wpData = $this->wpXML->channel->children('wp', true);

        $order = 1;

        foreach ($wpData->tag as $tag) {

            $this->tags[(string)$tag->tag_slug] = [
                'order'       => $order,
                'name'        => (string)$tag->tag_name,
                'author_id'   => auth()->user()->getAuthIdentifier(),
                'author_type' => User::class,
            ];

            $newTag = app(TagInterface::class)->createOrUpdate($this->tags[(string)$tag->tag_slug]);

            request()->merge([
                'slug' => (string)$tag->tag_slug,
            ]);

            event(new CreatedContentEvent(TAG_MODULE_SCREEN_NAME, request(), $newTag));

            $this->tags[(string)$tag->tag_slug]['id'] = $newTag->id;

            $order += 1;
        }

        return $this->tags;
    }

    /**
     * @param string $type
     * @return array
     */
    protected function savePostsAndPages(string $type = 'post'): array
    {
        foreach ($this->wpXML->channel->item as $item) {
            $wpData = $item->children('wp', true);

            if (!in_array($wpData->post_type, ['post', 'page'])) {
                continue;
            }

            $content = $item->children('content', true);
            $excerpt = $item->children('excerpt', true);
            $image = isset($this->attachments[(string)$wpData->post_id]) ? $this->attachments[(string)$wpData->post_id] : '';

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
                    $slug = 'post-' . (string)$wpData->post_id;
                } elseif ($type == 'page') {
                    $slug = 'page-' . (string)$wpData->post_id;
                }
            }

            if ($wpData->post_type == $type) {

                request()->merge(compact('slug'));

                if ($type == 'post') {

                    $data = [
                        'author_id'   => !empty($this->users[$author]['id']) ? $this->users[$author]['id'] : auth()->user()->getAuthIdentifier(),
                        'author_type' => User::class,
                        'name'        => trim((string)$item->title, '"'),
                        'description' => trim((string)$excerpt->encoded, '" \n'),
                        'content'     => $this->autop(trim((string)$content->encoded, '" \n')),
                        'image'       => $this->getImage($image),
                        'status'      => $status,
                    ];

                    $this->posts[] = $data;

                    $post = new Post;
                    $post->fill($data);
                    $post->created_at = Carbon::parse((string)$wpData->post_date);
                    $post->updated_at = Carbon::parse((string)$wpData->post_date);
                    $post->save();

                    if (!$this->copyCategories && !empty($this->defaultCategoryId)) {
                        $post->categories()->attach($this->defaultCategoryId);
                    } elseif (!empty($this->categories[$category]['id'])) {
                        $post->categories()->attach($this->categories[$category]['id']);
                    }

                    event(new CreatedContentEvent(POST_MODULE_SCREEN_NAME, request(), $post));

                } elseif ($type == 'page') {

                    $data = [
                        'user_id'     => !empty($this->users[$author]['id']) ? $this->users[$author]['id'] : auth()->user()->getAuthIdentifier(),
                        'name'        => trim((string)$item->title, '"'),
                        'description' => trim((string)$excerpt->encoded, '" \n'),
                        'content'     => $this->autop(trim((string)$content->encoded, '" \n')),
                        'image'       => $this->getImage($image),
                        'status'      => $status,
                        'template'    => 'default',
                    ];

                    $this->pages[] = $data;

                    $page = new Page;
                    $page->fill($data);
                    $page->created_at = Carbon::parse((string)$item->pubDate);
                    $page->updated_at = Carbon::parse((string)$item->pubDate);
                    $page->save();

                    event(new CreatedContentEvent(PAGE_MODULE_SCREEN_NAME, request(), $page));
                }
            }
        }

        return [
            'posts' => $this->posts,
            'pages' => $this->pages,
        ];
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
    protected function autop($pee, $br = true)
    {
        $preTags = [];

        if (trim($pee) === '') {
            return '';
        }

        $pee = $pee . "\n"; // just to make things a little easier, pad the end

        if (strpos($pee, '<pre') !== false) {
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
        if (strpos($pee, '<object') !== false) {
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
        $pee = preg_replace('|<p>\s*</p>|', '',
            $pee); // under certain strange conditions it could create a P of entirely whitespace
        $pee = preg_replace('!<p>([^<]+)</(div|address|form)>!', '<p>$1</p></$2>', $pee);
        $pee = preg_replace('!<p>\s*(</?' . $allBlocks . '[^>]*>)\s*</p>!', '$1', $pee); // don't pee all over a tag
        $pee = preg_replace('|<p>(<li.+?)</p>|', '$1', $pee); // problem with nested lists
        $pee = preg_replace('|<p><blockquote([^>]*)>|i', '<blockquote$1><p>', $pee);
        $pee = str_replace('</blockquote></p>', '</p></blockquote>', $pee);
        $pee = preg_replace('!<p>\s*(</?' . $allBlocks . '[^>]*>)!', '$1', $pee);
        $pee = preg_replace('!(</?' . $allBlocks . '[^>]*>)\s*</p>!', '$1', $pee);
        if ($br) {
            $pee = preg_replace_callback('/<(script|style).*?<\/\\1>/s',
                function ($matches) {
                    return str_replace("\n", '<PreserveNewline />', $matches[0]);
                }, $pee);
            $pee = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $pee); // optionally make line breaks
            $pee = str_replace('<PreserveNewline />', "\n", $pee);
        }
        $pee = preg_replace('!(</?' . $allBlocks . '[^>]*>)\s*<br />!', '$1', $pee);
        $pee = preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $pee);
        $pee = preg_replace("|\n</p>$|", '</p>', $pee);

        if (!empty($preTags)) {
            $pee = str_replace(array_keys($preTags), array_values($preTags), $pee);
        }

        return $pee;
    }

    /**
     * @param string $image
     * @return string
     */
    protected function getImage($image)
    {
        if (!empty($image) && $this->copyImages) {
            $info = pathinfo($image);
            try {
                $contents = file_get_contents($image);
            } catch (Exception $exception) {
                return $image;
            }

            if (empty($contents)) {
                return $image;
            }

            $path = '/tmp';
            if (!File::isDirectory($path)) {
                File::makeDirectory($path, 0755);
            }

            $path = $path . '/' . $info['basename'];
            file_put_contents($path, $contents);

            $mimeType = (new MimeTypes)->getMimeType(File::extension($image));

            $fileUpload = new UploadedFile($path, $info['basename'], $mimeType, null, true);

            $result = RvMedia::handleUpload($fileUpload, 0, 'posts');

            File::delete($path);

            if ($result['error'] == false) {
                $image = $result['data']->url;
            }
        }

        return $image;
    }
}
