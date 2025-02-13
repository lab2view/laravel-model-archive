<?php

namespace Lab2view\ModelArchive\Traits;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use Lab2view\ModelArchive\Eloquent\Builder;
use Lab2view\ModelArchive\Models\ArchivableModel;
use Lab2view\ModelArchive\Models\Archive;

/**
 * @template TModel of ArchivableModel
 *
 * @mixin TModel
 *
 * class Archivable
 *
 * @use ReadArchive<TModel>
 */
trait Archivable
{
    use ReadArchive;

    /**
     * Get the model's archive.
     */
    public function archive(): MorphOne
    {
        return $this->morphOne(Archive::class, 'archivable');
    }

    /**
     * Scope request to customize the definition of archivable elements on a given archivable model
     *
     * @return Builder<TModel>
     */
    public function scopeArchivable(Builder $builder): Builder
    {
        return $builder;
    }

    /**
     * Check that the archiving of a model has been carried out correctly.
     * Verifies by default that all the relations defined as having to be archived with the model that has been
     */
    public function validateArchive(Archive $commit): bool
    {
        $archiveWithOnSelf = array_filter(
            $commit->archive_with,
            fn ($w) => method_exists($this, $w)
        );

        /**
         * @var self | null $selfArchiveClone
         */
        $selfArchiveClone = self::query()
            ->clone()
            ->withoutGlobalScopes()
            ->where($this->getUniqBy())
            ->onlyArchived()
            ->with($archiveWithOnSelf)
            ->first();

        if ($selfArchiveClone) {
            foreach ($archiveWithOnSelf as $relation) {
                if ($this->$relation !== null && $selfArchiveClone->$relation == null) {
                    return false;
                }
            }
        } else {
            return false;
        }

        return true;
    }
}
