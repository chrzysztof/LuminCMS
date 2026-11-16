<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Builder\SavedBlock;
use App\Cms\Roles\RoleRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Biblioteka własnych bloków (zapisanych sekcji) edytora wizualnego. */
class SavedBlockController extends Controller
{
    private const MAX_HTML_BYTES = 200_000;

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'html' => ['required', 'string', 'max:'.self::MAX_HTML_BYTES],
        ]);

        $block = SavedBlock::create([...$data, 'user_id' => $request->user()->id]);

        return response()->json($block->only(['id', 'name', 'html', 'user_id']), 201);
    }

    public function destroy(Request $request, SavedBlock $block): JsonResponse
    {
        // Własne bloki usuwa autor, cudze - administrator.
        abort_unless(
            $block->user_id === $request->user()->id || RoleRegistry::grants($request->user()->role, 'manage_options'),
            403,
        );

        $block->delete();

        return response()->json(['ok' => true]);
    }
}
