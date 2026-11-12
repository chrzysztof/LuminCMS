<?php

namespace App\Http\Middleware;

use App\Cms\Content\ContentType;
use App\Cms\Roles\ContentAccess;
use App\Cms\Roles\RoleRegistry;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'role' => $request->user()?->role,
                'role_label' => $request->user() ? RoleRegistry::label($request->user()->role) : null,
                'capabilities' => $request->user() ? RoleRegistry::capabilities($request->user()->role) : [],
            ],
            // Menu pokazuje tylko typy treści, które użytkownik może edytować.
            'contentTypes' => fn () => $request->user()
                ? ContentType::orderBy('id')->get(['key', 'label', 'label_plural', 'icon', 'capability_type'])
                    ->filter(fn (ContentType $t) => app(ContentAccess::class)->canCreate($request->user(), $t))
                    ->map(fn (ContentType $t) => $t->only(['key', 'label', 'label_plural', 'icon']))
                    ->values()
                : [],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
