<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Updates\UpdateChecker;
use App\Cms\Updates\UpdateException;
use App\Cms\Updates\Updater;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Powiadomienia o nowych wersjach systemu i aktualizacja z panelu. Tylko dla administratorów (manage_options). */
class UpdateController extends Controller
{
    public function __construct(private readonly UpdateChecker $checker, private readonly Updater $updater) {}

    public function index(): Response
    {
        $release = $this->checker->cached();
        $state = $this->checker->state();

        return Inertia::render('updates/index', [
            'current' => $this->checker->current(),
            'enabled' => $this->checker->enabled(),
            'release' => $release?->toArray(),
            'available' => $this->checker->updateAvailable(),
            'installable' => $release?->installable() ?? false,
            'blocker' => $this->updater->blocker(),
            'checkedAt' => $state['checked_at'] ?? null,
            'error' => $state['error'] ?? null,
        ]);
    }

    public function check(): RedirectResponse
    {
        $this->checker->forget();
        $this->checker->refresh();

        $error = $this->checker->state()['error'] ?? null;
        Inertia::flash('toast', $error
            ? ['type' => 'error', 'message' => __('Nie udało się sprawdzić aktualizacji: :e', ['e' => $error])]
            : ['type' => 'success', 'message' => $this->checker->updateAvailable()
                ? __('Dostępna jest nowa wersja.') : __('Masz aktualną wersję.')]);

        return back();
    }

    public function install(): RedirectResponse
    {
        $release = $this->checker->latest(force: true);

        try {
            if (! $release || ! $this->checker->updateAvailable()) {
                throw new UpdateException(__('Nie ma nowszej wersji do zainstalowania.'));
            }
            $this->updater->install($release);
        } catch (UpdateException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        // Po podmianie kodu nowa wersja jest już w plikach; czyścimy cache wyniku i odświeżamy stronę.
        $this->checker->forget();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Zaktualizowano do wersji :v.', ['v' => $release->version])]);

        return back();
    }
}
