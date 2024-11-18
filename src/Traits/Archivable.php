<?php

namespace Lab2view\ModelArchive\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Lab2view\ModelArchive\Eloquent\Builder;
use Lab2view\ModelArchive\Models\Archive;

/**
 * @template TModel of Model
 * @mixin TModel
 *
 * class Archivable
 *
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
     * @param  Builder<TModel>  $builder
     * @return Builder<TModel>
     */
    public function scopeArchivable($builder)
    {
        return $builder;
    }

    /**
     * Check that the archiving of a model has been carried out correctly.
     * Verifies by default that all the relations defined as having to be archived with the model that has been
     */
    public function validateArchive(Archive $commit): bool
    {
        $archiveWith = $commit->archive_with;
        /**
         * @var array $selfWith
         */
        $selfWith = $this->with ?? [];
        $withOnSelf = array_filter($archiveWith, fn ($w) => in_array($w, $selfWith));
        /**
         * @var static | null $archive
         */
        $archive = static::withoutGlobalScopes()
            ->where($this->getUniqueBy())
            ->onlyArchived()
            ->with($withOnSelf)
            ->first();
            
        if ($archive) {
            foreach ($archiveWith as $relation) {
                if ($this->$relation !== null && $archive->$relation == null) {
                    return false;
                }
            }
        }

        return true;
    }

}
