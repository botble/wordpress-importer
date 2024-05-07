<?php

namespace Botble\WordpressImporter\Importers;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Events\CreatedContentEvent;
use Botble\Base\Facades\BaseHelper;
use Botble\DataSynchronize\Contracts\Importer\WithMapping;
use Botble\DataSynchronize\Importer\ImportColumn;
use Botble\DataSynchronize\Importer\Importer;
use Botble\Ecommerce\Enums\ProductTypeEnum;
use Botble\Ecommerce\Enums\StockStatusEnum;
use Botble\Ecommerce\Models\Product;
use Botble\Ecommerce\Models\ProductAttribute;
use Botble\Ecommerce\Models\ProductAttributeSet;
use Botble\Ecommerce\Models\ProductCategory;
use Botble\Ecommerce\Models\ProductVariation;
use Botble\Ecommerce\Services\Products\StoreProductService;
use Botble\Ecommerce\Services\StoreProductTagService;
use Botble\Media\Facades\RvMedia;
use Botble\Slug\Facades\SlugHelper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

class ProductImporter extends Importer implements WithMapping
{
    protected Collection $categories;

    protected Collection $productAttributeSets;

    protected Collection $productAttributes;

    public function getLabel(): string
    {
        return trans('plugins/wordpress-importer::wordpress-importer.data_synchronize.import_products.name');
    }

    public function headerToSnakeCase(): bool
    {
        return false;
    }

    public function chunkSize(): int
    {
        return 20;
    }

    public function showRulesCheatSheet(): bool
    {
        return false;
    }

    public function mergeWithUndefinedColumns(): bool
    {
        return true;
    }

    public function columns(): array
    {
        return [
            ImportColumn::make('type', 'Type')
                ->rules(['required', 'string', Rule::in(['simple, downloadable, virtual', 'simple', 'grouped', 'external', 'variation', 'variable'])]),
            ImportColumn::make('sku', 'SKU')
                ->rules(['nullable', 'string']),
            ImportColumn::make('name', 'Name')
                ->rules(['required', 'string']),
            ImportColumn::make('published', 'Published')
                ->rules(['required', 'boolean']),
            ImportColumn::make('featured', 'Is featured?')
                ->rules(['required', 'boolean']),
            ImportColumn::make('short_description', 'Short description')
                ->rules(['nullable', 'string', 'max:400']),
            ImportColumn::make('description', 'Description')
                ->rules(['nullable', 'string', 'max:300000']),
            ImportColumn::make('date_on_sale_from', 'Date sale price starts')
                ->rules(['nullable', 'datetime']),
            ImportColumn::make('date_on_sale_to', 'Date sale price ends')
                ->rules(['nullable', 'datetime']),
            ImportColumn::make('in_stock', 'In stock?')
                ->rules(['required', 'boolean']),
            ImportColumn::make('stock', 'Stock')
                ->rules(['nullable', 'numeric']),
            ImportColumn::make('weight', 'Weight (kg)')
                ->rules(['nullable', 'numeric']),
            ImportColumn::make('length', 'Length (cm)')
                ->rules(['nullable', 'numeric']),
            ImportColumn::make('width', 'Width (cm)')
                ->rules(['nullable', 'numeric']),
            ImportColumn::make('height', 'Height (cm)')
                ->rules(['nullable', 'numeric']),
            ImportColumn::make('sale_price', 'Sale price')
                ->rules(['nullable', 'numeric']),
            ImportColumn::make('regular_price', 'Regular price')
                ->rules(['nullable', 'numeric']),
            ImportColumn::make('category_ids', 'Categories')
                ->rules(['nullable', 'string']),
            ImportColumn::make('tags', 'Tags')
                ->rules(['nullable', 'string']),
            ImportColumn::make('images', 'Images')
                ->rules(['nullable', 'string']),
            ImportColumn::make('parent', 'Parent')
                ->rules(['nullable', 'string']),
            ImportColumn::make('position', 'Position')
                ->rules(['required', 'numeric']),
        ];
    }

    public function getValidateUrl(): string
    {
        return route('tools.data-synchronize.import.woocommerce-products.validate');
    }

    public function getImportUrl(): string
    {
        return route('tools.data-synchronize.import.woocommerce-products.store');
    }

    public function handle(array $data): int
    {
        $this->categories = ProductCategory::all();
        $this->productAttributeSets = ProductAttributeSet::query()
            ->with('attributes')
            ->get();
        $this->productAttributes = ProductAttribute::query()->get();

        $count = 0;

        $productFiles = [];

        foreach ($data as $row) {
            if ($row['type'] === 'variation' && $row['parent']) {
                $this->resolveProductAttributes($row);
                $parentProduct = $this->getProduct($row['parent']) ?: $this->storeProduct($row);
                $product = $this->storeVariation($parentProduct, $row);
            } else {
                $this->resolveProductAttributeSet($row);
                $product = $this->storeProduct($row);
                $productFile = $this->resolveProductLinks($row, $product);

                if ($productFile) {
                    $productFiles[] = $productFile;
                }
            }

            if ($product->wasRecentlyCreated) {
                $count++;
            }
        }

        if (! empty($productFiles)) {
            $this->saveDigitalProductFiles($productFiles);
        }

        return $count;
    }

    protected function resolveProductAttributes(array &$row): void
    {
        $attributes = [];
        $setKey = null;

        foreach ($row as $key => $value) {
            if (empty($value) || empty($key)) {
                continue;
            }

            if (preg_match('/^Attribute (\d+) (name|value\(s\))$/', $key, $matches)) {
                if ($matches[2] === 'name') {
                    $setKey = $value;
                } else {
                    $attributes[$setKey] = $value;
                }
            }
        }

        $attributeSets = [];
        $index = 0;

        foreach ($attributes as $key => $value) {
            $attributeSet = $this->productAttributeSets
                ->where('title', $key)
                ->first();

            if ($attributeSet) {
                $attribute = $this->productAttributes
                    ->where('attribute_set_id', $attributeSet->id)
                    ->where('title', $value)
                    ->first();

                if (! $attribute) {
                    $attribute = $attributeSet->attributes()->create([
                        'title' => $value,
                        'slug' => Str::slug($value),
                        'is_default' => $index === 0,
                    ]);

                    $this->productAttributes->push($attribute);
                }

                $attributeSets[$attributeSet->id] = $attribute->id;
            }

            $index++;
        }

        $row['attribute_sets'] = $attributeSets;
    }

    protected function resolveProductAttributeSet(array &$row): void
    {
        $sets = [];
        $setKey = null;

        foreach ($row as $key => $value) {
            if (empty($value) || empty($key)) {
                continue;
            }

            if (preg_match('/^Attribute (\d+) (name|value\(s\))$/', $key, $matches)) {
                if ($matches[2] === 'name') {
                    $setKey = $value;
                } else {
                    if (! str_contains($value, ', ')) {
                        continue;
                    }

                    $sets[] = $setKey;
                }
            }
        }

        $attributeSets = [];

        foreach ($sets as $set) {
            $attribute = $this->productAttributeSets
                ->filter(fn ($value) => $value['title'] === $set)
                ->first();

            if (! $attribute) {
                /** @var ProductAttributeSet $attribute */
                $attribute = ProductAttributeSet::query()->create([
                    'title' => $set,
                    'slug' => Str::slug($set),
                ]);

                $this->productAttributeSets->push($attribute);
            }

            $attributeSets[] = $attribute->getKey();
        }

        $row['attribute_sets'] = $attributeSets;
    }

    protected function resolveProductLinks(array $row, Product $product): array
    {
        if ($row['product_type'] !== ProductTypeEnum::DIGITAL) {
            return [];
        }

        $links = [];

        foreach ($row as $key => $value) {
            if (empty($value)) {
                continue;
            }

            if (preg_match('/^Download (\d+) (name|URL)$/', $key, $matches)) {
                $newKey = $matches[2] === 'URL' ? 'link' : 'name';

                $links[$matches[1]][$newKey] = $value;
                $links[$matches[1]]['product'] = $product;
            }
        }

        return $links;
    }

    protected function saveDigitalProductFiles(array $productFiles): void
    {
        $storeProductService = new StoreProductService();
        $maximumAllowedSize = 10 * 1024 * 1024;
        $serverMaxUploadFileSize = RvMedia::getServerConfigMaxUploadFileSize();
        $maximumAllowedSize = min($serverMaxUploadFileSize, $maximumAllowedSize);

        foreach ($productFiles as $productFile) {
            foreach ($productFile as $value) {
                if (empty($value['link']) || empty($value['name']) || empty($value['product'])) {
                    continue;
                }

                $downloadedFile = false;
                $fileSize = 0;
                $link = $value['link'];
                $product = $value['product'];

                try {
                    $fileSize = get_headers($link, true)['Content-Length'];

                    if ($fileSize < $maximumAllowedSize) {
                        $info = pathinfo($link);

                        $response = Http::withoutVerifying()->get($link);

                        if ($response->ok() && $response->body()) {
                            $contents = $response->body();

                            $path = '/tmp';
                            File::ensureDirectoryExists($path);

                            $path = $path . '/' . Str::limit($info['basename'], 50, '');
                            file_put_contents($path, $contents);

                            $fileUpload = $this->newUploadedFile($path);

                            $data = $storeProductService->saveProductFile($fileUpload);
                            $link = $data['url'];

                            $downloadedFile = true;
                        }
                    }
                } catch (Throwable $throwable) {
                    BaseHelper::logError($throwable);
                }

                $product->productFiles()->create([
                    'url' => $link,
                    'extras' => [
                        'is_external' => ! $downloadedFile,
                        'name' => $value['name'],
                        'size' => $fileSize,
                    ],
                ]);
            }
        }
    }

    protected function newUploadedFile(string $path, ?string $defaultMimeType = null): UploadedFile
    {
        $mimeType = RvMedia::getMimeType($path);

        if (empty($mimeType)) {
            $mimeType = $defaultMimeType;
        }

        $fileName = File::name($path);
        $fileExtension = File::extension($path);

        if (empty($fileExtension) && $mimeType) {
            $mimeTypeDetection = (new MimeTypes())->getExtensions($mimeType);

            $fileExtension = Arr::first($mimeTypeDetection);
        }

        return new UploadedFile($path, $fileName . '.' . $fileExtension, $mimeType, null, true);
    }

    protected function getImages(string $data): array
    {
        $images = [];
        $items = array_filter(explode(',', $data));

        foreach ($items as $image) {
            $images[] = $this->resolveMediaImage(trim($image), 'products');
        }

        return $images;
    }

    protected function storeProduct(array $row): Product
    {
        /** @var Product $product */
        $product = Product::query()->where('sku', $row['sku'])->first();

        if ($product) {
            return $product;
        }

        $request = new Request();

        $request->merge([
            ...$row,
            'stock_status' => $row['in_stock'] ? StockStatusEnum::IN_STOCK : StockStatusEnum::OUT_OF_STOCK,
            'categories' => $this->resolveCategories($row['category_ids']),
            'images' => $this->getImages($row['images']),
        ]);

        $product = new Product();
        $product = (new StoreProductService())->execute($request, $product);
        SlugHelper::createSlug($product);

        if ($row['attribute_sets']) {
            $product->productAttributeSets()->sync($row['attribute_sets']);
        }

        $tags = str(Arr::get($row, 'tags'))->explode(',')->map(fn ($tag) => ['value' => trim($tag)])->toJson();

        if ($tags) {
            $request->merge(['tag' => $tags]);
        }

        (new StoreProductTagService())->execute($request, $product);

        return $product;
    }

    protected function storeVariation(Product $product, array $row): Product|ProductVariation
    {
        $request = new Request();
        $request->merge([
            ...$row,
            'stock_status' => $row['in_stock'] ? StockStatusEnum::IN_STOCK : StockStatusEnum::OUT_OF_STOCK,
        ]);

        $addedAttributes = $request->input('attribute_sets', []);

        $result = ProductVariation::getVariationByAttributesOrCreate($product->id, $addedAttributes);

        $variation = $result['variation'];

        $version = [...$variation->toArray(), ...$request->toArray()];
        $sku = Arr::get($version, 'sku');

        /** @var Product $existingVariation */
        $existingVariation = Product::query()->where('is_variation', true)->where('sku', $sku)->first();

        if ($sku && $existingVariation) {
            return $existingVariation;
        }

        $version['variation_default_id'] = Arr::get($version, 'is_variation_default') ? $version['id'] : null;
        $version['attribute_sets'] = $addedAttributes;

        if ($version['description']) {
            $version['description'] = BaseHelper::clean($version['description']);
        }

        if ($version['content']) {
            $version['content'] = BaseHelper::clean($version['content']);
        }

        $productRelatedToVariation = new Product();
        $productRelatedToVariation->fill($version);

        $productRelatedToVariation->name = $product->name;
        $productRelatedToVariation->status = $product->status;
        $productRelatedToVariation->brand_id = $product->brand_id;
        $productRelatedToVariation->is_variation = 1;

        $productRelatedToVariation->sku = Arr::get($version, 'sku');
        if (! $productRelatedToVariation->sku && Arr::get($version, 'auto_generate_sku')) {
            $productRelatedToVariation->sku = $product->sku;
            foreach ($version['attribute_sets'] as $setId => $attributeId) {
                $attributeSet = $this->productAttributeSets->firstWhere('id', $setId);
                if ($attributeSet) {
                    $attribute = $attributeSet->attributes->firstWhere('id', $attributeId);
                    if ($attribute) {
                        $productRelatedToVariation->sku .= '-' . Str::upper($attribute->slug);
                    }
                }
            }
        }

        $productRelatedToVariation->price = Arr::get($version, 'price', $product->price);
        $productRelatedToVariation->sale_price = Arr::get($version, 'sale_price', $product->sale_price);

        if (Arr::get($version, 'description')) {
            $productRelatedToVariation->description = BaseHelper::clean($version['description']);
        }

        if (Arr::get($version, 'content')) {
            $productRelatedToVariation->content = BaseHelper::clean($version['content']);
        }

        $productRelatedToVariation->length = Arr::get($version, 'length', $product->length);
        $productRelatedToVariation->wide = Arr::get($version, 'wide', $product->wide);
        $productRelatedToVariation->height = Arr::get($version, 'height', $product->height);
        $productRelatedToVariation->weight = Arr::get($version, 'weight', $product->weight);

        $productRelatedToVariation->sale_type = (int) Arr::get($version, 'sale_type', $product->sale_type);

        if ($productRelatedToVariation->sale_type == 0) {
            $productRelatedToVariation->start_date = null;
            $productRelatedToVariation->end_date = null;
        } else {
            $productRelatedToVariation->start_date = Carbon::parse(
                Arr::get($version, 'start_date', $product->start_date)
            )->toDateTimeString();
            $productRelatedToVariation->end_date = Carbon::parse(
                Arr::get($version, 'end_date', $product->end_date)
            )->toDateTimeString();
        }

        $productRelatedToVariation->images = json_encode($this->getImages($row['images']));

        $productRelatedToVariation->status = strtolower(Arr::get($version, 'status', $product->status));

        $productRelatedToVariation->product_type = $product->product_type;
        $productRelatedToVariation->save();

        event(new CreatedContentEvent(PRODUCT_MODULE_SCREEN_NAME, $request, $productRelatedToVariation));

        $variation->product_id = $productRelatedToVariation->getKey();

        $variation->is_default = Arr::get($version, 'variation_default_id', 0) == $variation->id;

        $variation->save();

        if ($version['attribute_sets']) {
            $variation->productAttributes()->sync($version['attribute_sets']);
        }

        return $variation;
    }

    protected function getProduct(string $sku): ?Product
    {
        /** @var Product $product */
        $product = Product::query()
            ->where('sku', $sku)
            ->first();

        return $product;
    }

    protected function resolveCategories(string $categoriesIds): array
    {
        $categories = [];

        $categoriesIds = explode(',', $categoriesIds);

        foreach ($categoriesIds as $category) {
            $category = explode('>', trim($category));

            $parent = 0;
            foreach ($category as $categoryName) {
                if (empty($categoryName)) {
                    continue;
                }

                $categoryName = trim($categoryName);

                $categoryModel = $this->categories->firstWhere('name', $categoryName);

                if ($categoryModel) {
                    $parent = $categoryModel->id;
                } else {
                    $categoryModel = ProductCategory::query()->create([
                        'name' => $categoryName,
                        'parent_id' => $parent,
                    ]);

                    $this->categories->push($categoryModel);
                    $parent = $categoryModel->getKey();
                }

                $categories[] = $categoryModel->getKey();
            }
        }

        return $categories;
    }

    public function map(mixed $row): array
    {
        return [
            ...$row,
            'name' => $row['name'],
            'description' => $row['short_description'],
            'content' => $row['description'],
            'status' => $row['published'] == 1 ? BaseStatusEnum::PUBLISHED : BaseStatusEnum::DRAFT,
            'images' => $row['images'],
            'sku' => $row['sku'],
            'quantity' => (int) $row['stock'],
            'in_stock' => (bool) $row['in_stock'],
            'is_featured' => (bool) $row['featured'],
            'weight' => (float) $row['weight'],
            'height' => (float) $row['height'],
            'length' => (float) $row['length'],
            'wide' => (float) Arr::pull($row, 'width'),
            'sale_price' => (float) $row['sale_price'],
            'price' => (float) $row['regular_price'],
            'start_date' => $row['date_on_sale_from'],
            'end_date' => $row['date_on_sale_to'],
            'is_variation' => (bool) $row['parent'],
            'order' => (int) $row['position'],
            'product_type' => match ($row['type']) {
                'simple, downloadable, virtual' => ProductTypeEnum::DIGITAL,
                default => ProductTypeEnum::PHYSICAL,
            },
        ];
    }
}
