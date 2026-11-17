<?php

namespace App\Cms\Themes;

use Illuminate\Pagination\LengthAwarePaginator;

/** Kontekst bieżącego archiwum lub strony terminu dla bloków szablonów (Theme Builder); pusty poza listingiem. */
class Listing
{
    public ?string $heading = null;

    public string $description = '';

    public ?LengthAwarePaginator $items = null;

    public function active(): bool
    {
        return $this->items !== null;
    }

    public function set(string $heading, string $description, LengthAwarePaginator $items): void
    {
        [$this->heading, $this->description, $this->items] = [$heading, $description, $items];
    }
}
