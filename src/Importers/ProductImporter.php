<?php

namespace Botble\WordpressImporter\Importers;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\DataSynchronize\Contracts\Importer\WithMapping;
use Botble\DataSynchronize\Importer\ImportColumn;
use Botble\DataSynchronize\Importer\Importer;
use Botble\Ecommerce\Enums\ProductTypeEnum;
use Botble\Ecommerce\Enums\StockStatusEnum;
use Botble\Ecommerce\Models\Product;
use Botble\Ecommerce\Models\ProductCategory;
use Botble\Ecommerce\Services\Products\StoreProductService;
use Botble\Slug\Facades\SlugHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ProductImporter extends Importer implements WithMapping
{
    protected Collection $categories;

    public function headerToSnakeCase(): bool
    {
        return false;
    }

    public function chunkSize(): int
    {
        return 20;
    }

    public function columns(): array
    {
        return [
            ImportColumn::make('id', 'ID'),
            ImportColumn::make('type', 'Type'),
            ImportColumn::make('sku', 'SKU'),
            ImportColumn::make('name', 'Name'),
            ImportColumn::make('published', 'Published'),
            ImportColumn::make('featured', 'Is featured?'),
            ImportColumn::make('catalog_visibility', 'Visibility in catalog'),
            ImportColumn::make('short_description', 'Short description'),
            ImportColumn::make('description', 'Description'),
            ImportColumn::make('date_on_sale_from', 'Date sale price starts'),
            ImportColumn::make('date_on_sale_to', 'Date sale price ends'),
            ImportColumn::make('tax_status', 'Tax status'),
            ImportColumn::make('tax_class', 'Tax class'),
            ImportColumn::make('stock_status', 'In stock?'),
            ImportColumn::make('stock', 'Stock'),
            ImportColumn::make('low_stock_amount', 'Low stock amount'),
            ImportColumn::make('backorders', 'Backorders allowed?'),
            ImportColumn::make('sold_individually', 'Sold individually?'),
            ImportColumn::make('weight', 'Weight (kg)'),
            ImportColumn::make('length', 'Length (cm)'),
            ImportColumn::make('width', 'Width (cm)'),
            ImportColumn::make('height', 'Height (cm)'),
            ImportColumn::make('reviews_allowed', 'Allow customer reviews?'),
            ImportColumn::make('purchase_note', 'Purchase note'),
            ImportColumn::make('sale_price', 'Sale price'),
            ImportColumn::make('regular_price', 'Regular price'),
            ImportColumn::make('category_ids', 'Categories'),
            ImportColumn::make('tag_ids', 'Tags'),
            ImportColumn::make('shipping_class_id', 'Shipping class'),
            ImportColumn::make('images', 'Images'),
            ImportColumn::make('download_limit', 'Download limit'),
            ImportColumn::make('download_expiry', 'Download expiry days'),
            ImportColumn::make('parent_id', 'Parent'),
            ImportColumn::make('grouped_products', 'Grouped products'),
            ImportColumn::make('upsell_ids', 'Upsells'),
            ImportColumn::make('cross_sell_ids', 'Cross-sells'),
            ImportColumn::make('product_url', 'External URL'),
            ImportColumn::make('button_text', 'Button text'),
            ImportColumn::make('position', 'Position'),
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

        $count = 0;

        foreach ($data as $row) {
            $this->storeProduct($row);
        }

        return $count;
    }

    protected function storeProduct(array $row): Product
    {
        $request = new Request();

        $images = $row['images'] ? str($row['images'])
            ->explode(',')
            ->map(fn ($image) => $this->resolveMediaImage(trim($image), 'products'))
            ->all() : [];

        $request->merge([
            ...$row,
            'categories' => $this->resolveCategories($row['category_ids']),
            'images' => $images,
        ]);

        $product = new Product();
        $product = (new StoreProductService())->execute($request, $product);
        SlugHelper::createSlug($product);

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
            'stock_status' => $row['stock_status'] ? StockStatusEnum::IN_STOCK : StockStatusEnum::OUT_OF_STOCK,
            'is_featured' => (bool) $row['featured'],
            'weight' => (float) $row['weight'],
            'height' => (float) $row['height'],
            'length' => (float) $row['length'],
            'wide' => (float) Arr::pull($row, 'width'),
            'sale_price' => (float) $row['sale_price'],
            'price' => (float) $row['regular_price'],
            'start_date' => $row['date_on_sale_from'],
            'end_date' => $row['date_on_sale_to'],
            'is_variation' => (bool) $row['parent_id'],
            'order' => (int) $row['position'],
            'product_type' => match ($row['type']) {
                'simple, downloadable, virtual' => ProductTypeEnum::DIGITAL,
                default => ProductTypeEnum::PHYSICAL,
            },
        ];
    }
}
