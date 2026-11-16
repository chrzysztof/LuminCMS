<?php

namespace App\Cms\Builder;

use App\Cms\Content\Content;

/** Zapisuje wersje projektu edytora wizualnego i pilnuje, żeby historia nie rosła bez końca. */
class RevisionRecorder
{
    /** Ile ręcznych zapisów/publikacji i ile autozapisów przechowujemy na jedną treść. */
    private const KEEP_SAVED = 50;

    private const KEEP_AUTOSAVES = 15;

    /** Zwraca nową wersję albo null, gdy projekt nie różni się od ostatniej zapisanej. */
    public function record(Content $content, array $project, string $kind, ?int $userId): ?ContentRevision
    {
        $json = json_encode($project, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hash = sha1($json);

        $last = ContentRevision::where('content_id', $content->id)->latest('id')->first();

        if ($last && $last->hash === $hash) {
            // Ten sam projekt: tylko "awansujemy" autozapis do ręcznego zapisu, żeby nie zniknął przy czyszczeniu.
            if ($last->kind === ContentRevision::KIND_AUTOSAVE && $kind !== ContentRevision::KIND_AUTOSAVE) {
                $last->update(['kind' => $kind, 'user_id' => $userId]);
            }

            return null;
        }

        $revision = ContentRevision::create([
            'content_id' => $content->id,
            'user_id' => $userId,
            'kind' => $kind,
            'hash' => $hash,
            'builder_data' => $json,
        ]);

        $this->prune($content->id);

        return $revision;
    }

    private function prune(int $contentId): void
    {
        foreach ([
            [[ContentRevision::KIND_SAVE, ContentRevision::KIND_PUBLISH], self::KEEP_SAVED],
            [[ContentRevision::KIND_AUTOSAVE], self::KEEP_AUTOSAVES],
        ] as [$kinds, $keep]) {
            $ids = ContentRevision::where('content_id', $contentId)->whereIn('kind', $kinds)
                ->latest('id')->skip($keep)->take(1000)->pluck('id');

            if ($ids->isNotEmpty()) {
                ContentRevision::whereIn('id', $ids)->delete();
            }
        }
    }
}
