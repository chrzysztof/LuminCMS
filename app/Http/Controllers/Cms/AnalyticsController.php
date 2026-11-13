<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Analytics\GoogleAnalyticsReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Dane do widżetu odwiedzin na pulpicie (Google Analytics 4). */
class AnalyticsController extends Controller
{
    public function __invoke(Request $request, GoogleAnalyticsReport $report): JsonResponse
    {
        $data = $report->report($request->boolean('fresh'));

        return response()->json($data, isset($data['error']) ? 422 : 200);
    }
}
