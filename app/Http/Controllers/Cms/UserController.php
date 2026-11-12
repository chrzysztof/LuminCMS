<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Content\Content;
use App\Cms\Roles\RoleRegistry;
use App\Cms\Settings\Settings;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Zarządzanie użytkownikami i rolami w stylu WordPressa. */
class UserController extends Controller
{
    public function __construct(private readonly Settings $settings) {}

    public function index(Request $request): Response
    {
        $this->authorizeCap($request, 'list_users');

        $role = $request->string('role')->toString();
        $search = $request->string('search')->trim()->toString();

        $query = User::query()->withCount('contents')->orderBy('name');

        if (RoleRegistry::exists($role)) {
            $query->where('role', $role);
        }

        if ($search !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        $counts = User::selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

        return Inertia::render('users/index', [
            'users' => $query->paginate(20)->withQueryString()->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'role_label' => RoleRegistry::label($u->role),
                'contents_count' => $u->contents_count,
                'created_at' => $u->created_at?->toIso8601String(),
                'is_self' => $u->id === $request->user()->id,
            ]),
            'roles' => $this->roleOptions(),
            'counts' => ['all' => $counts->sum(), ...collect(RoleRegistry::roles())->mapWithKeys(fn ($r) => [$r => $counts[$r] ?? 0])->all()],
            'filters' => ['role' => $role, 'search' => $search],
            'can' => [
                'create' => $request->user()->can('create_users'),
                'edit' => $request->user()->can('edit_users'),
                'delete' => $request->user()->can('delete_users'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeCap($request, 'create_users');

        return Inertia::render('users/edit', [
            'user' => null,
            'roles' => $this->roleOptions(),
            'defaultRole' => $this->defaultRole(),
            'canPromote' => true,
            'others' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeCap($request, 'create_users');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'role' => ['required', Rule::in(RoleRegistry::roles())],
        ]);

        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        $user->role = $data['role'];
        $user->email_verified_at = now();
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Użytkownik dodany.')]);

        return to_route('cms.users.edit', $user);
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorizeCap($request, 'edit_users');

        return Inertia::render('users/edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'contents_count' => $user->contents()->count(),
                'is_self' => $user->id === $request->user()->id,
            ],
            'roles' => $this->roleOptions(),
            'defaultRole' => $this->defaultRole(),
            'canPromote' => $request->user()->can('promote_users'),
            'canDelete' => $request->user()->can('delete_users') && $user->id !== $request->user()->id,
            'others' => User::where('id', '!=', $user->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeCap($request, 'edit_users');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'role' => ['required', Rule::in(RoleRegistry::roles())],
        ]);

        if ($data['role'] !== $user->role) {
            abort_unless($request->user()->can('promote_users'), 403);
            $this->guardLastAdministrator($user);
            $user->role = $data['role'];
        }

        $user->fill(['name' => $data['name'], 'email' => $data['email']]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Zapisano.')]);

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorizeCap($request, 'delete_users');

        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages(['user' => __('Nie możesz usunąć własnego konta.')]);
        }

        $this->guardLastAdministrator($user);

        $data = $request->validate(['reassign_to' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNot('id', $user->id)]]);

        DB::transaction(function () use ($user, $data) {
            // Treści usuniętego autora przechodzą do wskazanej osoby (albo zostają bez autora).
            Content::withTrashed()->where('author_id', $user->id)->update(['author_id' => $data['reassign_to'] ?? null]);
            $user->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Użytkownik usunięty.')]);

        return to_route('cms.users.index');
    }

    public function roles(Request $request): Response
    {
        $this->authorizeCap($request, 'manage_options');

        $definitions = RoleRegistry::definitions();
        $caps = collect(RoleRegistry::allCapabilities());

        return Inertia::render('users/roles', [
            'roles' => collect(RoleRegistry::roles())->map(fn ($r) => [
                'key' => $r,
                'label' => RoleRegistry::label($r),
                'description' => RoleRegistry::description($r),
                'users' => User::where('role', $r)->count(),
                'capabilities' => $definitions[$r],
            ])->values(),
            'capabilities' => $caps->values(),
            'labels' => collect(RoleRegistry::CAPABILITY_LABELS)->map(fn ($l) => __($l))->all(),
            'settings' => $this->settings->group('users'),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorizeCap($request, 'manage_options');

        $data = $request->validate([
            'default_role' => ['required', Rule::in(RoleRegistry::roles())],
            'registration_enabled' => ['boolean'],
        ]);

        $this->settings->save('users', [
            'default_role' => $data['default_role'],
            'registration_enabled' => (bool) ($data['registration_enabled'] ?? false),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ustawienia użytkowników zapisane.')]);

        return back();
    }

    private function authorizeCap(Request $request, string $capability): void
    {
        abort_unless($request->user()->can($capability), 403);
    }

    /** Witryna musi zawsze mieć co najmniej jednego administratora. */
    private function guardLastAdministrator(User $user): void
    {
        if ($user->isAdministrator() && User::where('role', RoleRegistry::ADMINISTRATOR)->count() <= 1) {
            throw ValidationException::withMessages(['role' => __('To ostatni administrator — witryna musi mieć co najmniej jednego.')]);
        }
    }

    private function defaultRole(): string
    {
        return $this->settings->get('users', 'default_role', RoleRegistry::SUBSCRIBER);
    }

    /** @return list<array{key: string, label: string, description: string}> */
    private function roleOptions(): array
    {
        return collect(RoleRegistry::roles())->map(fn ($r) => [
            'key' => $r,
            'label' => RoleRegistry::label($r),
            'description' => RoleRegistry::description($r),
        ])->all();
    }
}
