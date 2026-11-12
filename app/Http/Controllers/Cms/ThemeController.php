<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Themes\Theme;
use App\Cms\Themes\ThemeInstaller;
use App\Cms\Themes\ThemeManager;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Wygląd → Motywy: lista, aktywacja, instalacja z ZIP-a, eksport i usuwanie motywów. */
class ThemeController extends Controller
{
    private const SCREENSHOTS = ['screenshot.png', 'screenshot.jpg', 'screenshot.webp'];

    public function __construct(private readonly ThemeManager $themes, private readonly ThemeInstaller $installer) {}

    public function index(): Response
    {
        $active = $this->themes->active()->slug;
        $all = $this->themes->all();

        return Inertia::render('themes/index', [
            'themes' => collect($all)->map(fn (Theme $t) => [
                'slug' => $t->slug,
                'name' => $t->name(),
                'version' => $t->version(),
                'author' => $t->author(),
                'description' => $t->description(),
                'parent' => $t->parentSlug(),
                'parent_name' => $t->parentSlug() ? ($all[$t->parentSlug()]?->name() ?? $t->parentSlug()) : null,
                'error' => $t->error,
                'active' => $t->slug === $active,
                'is_default' => $t->slug === ThemeManager::DEFAULT,
                'has_screenshot' => $this->screenshot($t) !== null,
                'can_delete' => $t->slug !== ThemeManager::DEFAULT && $t->slug !== $active
                    && collect($all)->every(fn (Theme $o) => $o->parentSlug() !== $t->slug),
            ])->sortBy(fn ($t) => [! $t['active'], $t['name']])->values(),
            'uploadsEnabled' => (bool) config('cms.theme_uploads'),
        ]);
    }

    public function activate(string $slug): RedirectResponse
    {
        try {
            $this->themes->activate($slug);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['theme' => __('Tego motywu nie można aktywować.')]);
        }

        return back();
    }

    public function upload(Request $request): RedirectResponse
    {
        abort_unless(config('cms.theme_uploads'), 403);

        $data = $request->validate(['file' => ['required', 'file', 'mimes:zip', 'max:15360']]);

        try {
            $this->installer->install(
                $data['file']->getRealPath(),
                Str::slug(pathinfo($data['file']->getClientOriginalName(), PATHINFO_FILENAME)),
            );
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        return back();
    }

    public function export(string $slug): BinaryFileResponse
    {
        $theme = $this->themes->all()[$slug] ?? abort(404);

        return response()->download($this->installer->export($theme), "{$slug}.zip")->deleteFileAfterSend();
    }

    public function destroy(string $slug): RedirectResponse
    {
        try {
            $this->installer->delete($slug);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['theme' => $e->getMessage()]);
        }

        return back();
    }

    public function screenshotImage(string $slug): BinaryFileResponse
    {
        $theme = $this->themes->all()[$slug] ?? abort(404);

        return response()->file($this->screenshot($theme) ?? abort(404));
    }

    private function screenshot(Theme $theme): ?string
    {
        foreach (self::SCREENSHOTS as $file) {
            if (is_file($theme->path.'/'.$file)) {
                return $theme->path.'/'.$file;
            }
        }

        return null;
    }
}
