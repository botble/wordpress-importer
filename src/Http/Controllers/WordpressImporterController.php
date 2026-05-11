<?php

namespace Botble\WordpressImporter\Http\Controllers;

use Botble\Base\Facades\Assets;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Blog\Models\Category;
use Botble\WordpressImporter\Forms\WordpressImporterForm;
use Botble\WordpressImporter\Http\Requests\WordpressImporterRequest;
use Botble\WordpressImporter\Importers\ProductImporter;
use Botble\WordpressImporter\WordpressImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WordpressImporterController extends BaseController
{
    public function index()
    {
        Assets::addScriptsDirectly('vendor/core/plugins/wordpress-importer/js/wordpress-importer.js');

        $this->pageTitle(trans('plugins/wordpress-importer::wordpress-importer.name'));

        $form = WordpressImporterForm::create();
        $productImporter = null;

        if (is_plugin_active('ecommerce')) {
            $productImporter = ProductImporter::make();
        }

        return view('plugins/wordpress-importer::import', compact('form', 'productImporter'));
    }

    public function import(WordpressImporterRequest $request, WordpressImporter $wordpressImporter)
    {
        $validate = $wordpressImporter->verifyRequest($request);

        if ($validate['error']) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage($validate['message']);
        }

        if ($request->input('image_mode') === 'queue' && config('queue.default') === 'sync') {
            // Pre-flight check — still proceed but warn the admin via response.
            $queueSyncWarning = trans('plugins/wordpress-importer::wordpress-importer.queue_driver_sync_warning');
        }

        $result = $wordpressImporter->import();

        $messages = [
            trans('plugins/wordpress-importer::wordpress-importer.import_success', [
                'categories' => $result['categories'],
                'tags' => $result['tags'],
                'posts' => $result['posts'],
                'pages' => $result['pages'],
                'users' => $result['users'],
            ]),
        ];

        $isWarning = false;

        if (! empty($result['failed_images'])) {
            $messages[] = trans('plugins/wordpress-importer::wordpress-importer.import_warning_failed_images', [
                'count' => count($result['failed_images']),
            ]);
            $isWarning = true;
        }

        if (! empty($result['row_errors'])) {
            $messages[] = trans('plugins/wordpress-importer::wordpress-importer.import_warning_row_errors', [
                'count' => count($result['row_errors']),
            ]);
            $isWarning = true;
        }

        if (! empty($result['image_jobs_dispatched'])) {
            $messages[] = trans('plugins/wordpress-importer::wordpress-importer.import_queue_dispatched', [
                'count' => $result['image_jobs_dispatched'],
            ]);
        }

        if (! empty($result['credentials_generated_count'])) {
            $messages[] = trans('plugins/wordpress-importer::wordpress-importer.credentials_generated_warning', [
                'count' => $result['credentials_generated_count'],
            ]);
            $isWarning = true;
        }

        if (isset($queueSyncWarning)) {
            $messages[] = $queueSyncWarning;
            $isWarning = true;
        }

        return $this
            ->httpResponse()
            ->setMessage(implode(' ', $messages))
            ->setData([
                'is_warning' => $isWarning,
                'progress_key' => $result['progress_key'] ?? null,
                'import_id' => $result['import_id'] ?? null,
                'failed_images' => $result['failed_images'] ?? [],
                'row_errors' => $result['row_errors'] ?? [],
                'credentials_generated_count' => $result['credentials_generated_count'] ?? 0,
                'credentials_download_url' => ! empty($result['credentials_generated_count'])
                    ? route('wordpress-importer.credentials.download', ['importId' => $result['import_id']])
                    : null,
                'image_jobs_dispatched' => $result['image_jobs_dispatched'] ?? 0,
                'image_mode' => $result['image_mode'] ?? 'sync',
            ]);
    }

    public function downloadCredentials(string $importId)
    {
        $entries = Cache::pull('wp-import:' . $importId . ':credentials');

        if (! $entries) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage(trans('plugins/wordpress-importer::wordpress-importer.credentials_not_found'));
        }

        $callback = function () use ($entries): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['username', 'email', 'password']);
            foreach ($entries as $entry) {
                fputcsv($handle, [
                    $entry['username'] ?? '',
                    $entry['email'] ?? '',
                    $entry['password'] ?? '',
                ]);
            }
            fclose($handle);
        };

        return response()->streamDownload($callback, 'wordpress-import-credentials-' . $importId . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function progress(string $key)
    {
        if (! str_starts_with($key, 'wp-import:')) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage(trans('plugins/wordpress-importer::wordpress-importer.progress_not_found'));
        }

        $entry = Cache::get($key);

        if (! $entry) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage(trans('plugins/wordpress-importer::wordpress-importer.progress_not_found'));
        }

        return $this->httpResponse()->setData($entry);
    }

    public function retryFailedImages(Request $request, string $importId)
    {
        $imageModel = \Botble\WordpressImporter\Models\WordpressImportImage::class;

        if (! class_exists($imageModel)) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage(trans('plugins/wordpress-importer::wordpress-importer.import_not_found'));
        }

        $failed = $imageModel::query()
            ->where('import_id', $importId)
            ->where('status', 'failed')
            ->get();

        if ($failed->isEmpty()) {
            return $this
                ->httpResponse()
                ->setMessage(trans('plugins/wordpress-importer::wordpress-importer.retry_images_no_failures'));
        }

        $job = \Botble\WordpressImporter\Jobs\DownloadWordPressImage::class;

        foreach ($failed as $row) {
            $row->update(['status' => 'pending', 'last_error' => null]);
            $job::dispatch($row->original_url, $importId);
        }

        return $this
            ->httpResponse()
            ->setMessage(trans('plugins/wordpress-importer::wordpress-importer.retry_images_started', [
                'count' => $failed->count(),
            ]));
    }

    public function imageStatus(string $importId)
    {
        $imageModel = \Botble\WordpressImporter\Models\WordpressImportImage::class;

        if (! class_exists($imageModel)) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage(trans('plugins/wordpress-importer::wordpress-importer.import_not_found'));
        }

        // Aggregate counts in a single GROUP BY query — never hydrate all rows.
        $counts = $imageModel::query()
            ->where('import_id', $importId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $failedRows = $imageModel::query()
            ->where('import_id', $importId)
            ->where('status', 'failed')
            ->select(['original_url', 'last_error'])
            ->limit(500)
            ->get();

        return $this->httpResponse()->setData([
            'total' => (int) $counts->sum(),
            'done' => (int) ($counts['done'] ?? 0),
            'pending' => (int) ($counts['pending'] ?? 0),
            'downloading' => (int) ($counts['downloading'] ?? 0),
            'failed' => (int) ($counts['failed'] ?? 0),
            'failed_urls' => $failedRows->map(fn ($row) => [
                'url' => $row->original_url,
                'error' => $row->last_error,
            ])->values(),
        ]);
    }

    public function ajaxCategories()
    {
        return $this
            ->httpResponse()
            ->setData(Category::query()->select('name', 'id')->paginate());
    }
}
