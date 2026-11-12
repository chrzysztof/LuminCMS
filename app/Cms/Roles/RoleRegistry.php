<?php

namespace App\Cms\Roles;

/**
 * Role i uprawnienia dokładnie jak w WordPressie: Administrator, Redaktor, Autor,
 * Współpracownik i Subskrybent. Uprawnienia treści występują w dwóch zestawach:
 * "post" (edit_posts...) i "page" (edit_pages...), zależnie od typu treści.
 */
class RoleRegistry
{
    public const ADMINISTRATOR = 'administrator';

    public const EDITOR = 'editor';

    public const AUTHOR = 'author';

    public const CONTRIBUTOR = 'contributor';

    public const SUBSCRIBER = 'subscriber';

    /** Akcje na treści; pełna nazwa uprawnienia to "{akcja}_{post|page}s", np. edit_others_pages. */
    public const CONTENT_ACTIONS = ['edit', 'edit_others', 'edit_published', 'publish', 'delete', 'delete_others', 'delete_published'];

    public const LABELS = [
        self::ADMINISTRATOR => 'Administrator',
        self::EDITOR => 'Redaktor',
        self::AUTHOR => 'Autor',
        self::CONTRIBUTOR => 'Współpracownik',
        self::SUBSCRIBER => 'Subskrybent',
    ];

    public const DESCRIPTIONS = [
        self::ADMINISTRATOR => 'Pełna kontrola nad witryną: ustawienia, pola własne, użytkownicy i całość treści.',
        self::EDITOR => 'Zarządza wszystkimi treściami (także cudzymi), publikuje i usuwa strony oraz wpisy.',
        self::AUTHOR => 'Publikuje własne wpisy, dodaje pliki do biblioteki mediów i zarządza swoimi treściami.',
        self::CONTRIBUTOR => 'Pisze własne wpisy i wysyła je do recenzji, ale nie może ich publikować ani dodawać plików.',
        self::SUBSCRIBER => 'Może tylko zarządzać własnym profilem.',
    ];

    /** Uprawnienia ogólne (poza treściami) i ich opisy do macierzy ról w panelu. */
    public const CAPABILITY_LABELS = [
        'read' => 'Odczyt i własny profil',
        'upload_files' => 'Biblioteka mediów (wgrywanie plików)',
        'manage_categories' => 'Zarządzanie kategoriami i tagami',
        'manage_options' => 'Ustawienia witryny, SEO, przekierowania i pola własne',
        'list_users' => 'Podgląd listy użytkowników',
        'create_users' => 'Dodawanie użytkowników',
        'edit_users' => 'Edycja użytkowników',
        'delete_users' => 'Usuwanie użytkowników',
        'promote_users' => 'Zmiana ról użytkowników',
    ];

    /** @return array<string, list<string>> uprawnienia ról (bez rozwinięcia dziedziczenia nie ma: każda rola jest pełna) */
    public static function definitions(): array
    {
        $contributor = ['read', 'edit_posts', 'delete_posts'];
        $author = [...$contributor, 'upload_files', 'edit_published_posts', 'publish_posts', 'delete_published_posts'];
        $editor = [
            ...$author,
            'edit_others_posts', 'delete_others_posts', 'manage_categories',
            'edit_pages', 'edit_others_pages', 'edit_published_pages', 'publish_pages',
            'delete_pages', 'delete_others_pages', 'delete_published_pages',
        ];
        $administrator = [
            ...$editor,
            'manage_options', 'list_users', 'create_users', 'edit_users', 'delete_users', 'promote_users',
        ];

        return [
            self::ADMINISTRATOR => $administrator,
            self::EDITOR => $editor,
            self::AUTHOR => $author,
            self::CONTRIBUTOR => $contributor,
            self::SUBSCRIBER => ['read'],
        ];
    }

    /** @return list<string> */
    public static function roles(): array
    {
        return array_keys(self::LABELS);
    }

    public static function exists(?string $role): bool
    {
        return $role !== null && isset(self::LABELS[$role]);
    }

    public static function label(?string $role): string
    {
        return self::LABELS[$role] ?? (string) $role;
    }

    /** @return list<string> */
    public static function capabilities(?string $role): array
    {
        return self::definitions()[$role] ?? [];
    }

    public static function grants(?string $role, string $capability): bool
    {
        return in_array($capability, self::capabilities($role), true);
    }

    /** Wszystkie znane uprawnienia w stałej kolejności (do macierzy ról). @return list<string> */
    public static function allCapabilities(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::definitions()))));
    }
}
