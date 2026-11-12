<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Content\ContentType;
use App\Cms\Importer\Exporter;
use App\Cms\Importer\Importer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Struktura → Import i eksport: JSON z typami treści, taksonomiami, grupami pól i treściami. */
class ImportExportController extends Controller
{
    public function __construct(private readonly Importer $importer, private readonly Exporter $exporter) {}

    public function index(): Response
    {
        return Inertia::render('tools/import', [
            'types' => ContentType::where('key', '!=', 'part')->orderBy('id')->get(['key', 'label_plural']),
            'report' => null,
        ]);
    }

    /** Import (albo próba, `dry_run`): zwraca stronę z raportem. */
    public function import(Request $request): Response
    {
        $data = $request->validate([
            'json' => ['required', 'string', 'max:20000000'],
            'dry_run' => ['boolean'],
            'existing' => ['required', Rule::in(['skip', 'update'])],
        ]);

        $payload = json_decode($data['json'], true);

        if (! is_array($payload)) {
            throw ValidationException::withMessages(['json' => __('To nie jest poprawny plik JSON.')]);
        }

        $report = $this->importer->run($payload, (bool) ($data['dry_run'] ?? false), $data['existing'], $request->user());

        return Inertia::render('tools/import', [
            'types' => ContentType::where('key', '!=', 'part')->orderBy('id')->get(['key', 'label_plural']),
            'report' => $report->toArray(),
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => [Rule::in(['content_types', 'taxonomies', 'field_groups', 'contents'])],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', Rule::exists('content_types', 'key')],
        ]);

        return response()->json($this->exporter->build($data['sections'], $data['types'] ?? []), 200, [
            'Content-Disposition' => 'attachment; filename="lumin-export-'.now()->format('Y-m-d').'.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
