<?php

namespace Lab2view\ModelArchive\Traits;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Lab2view\ModelArchive\Models\Archive;
use Lab2view\ModelArchive\Scopes\ArchivableScope;

trait Archivable
{
    /**
     * Database connection dedicated to archiving
     */
    protected static $archiveConnection;

    /**
     * Boot the archivable model.
     *
     * @return void
     */
    protected static function boot()
    {
        if(!self::$archiveConnection){
            self::$archiveConnection = Config::get('model-archive.archive_db_connection');
        }
        parent::boot(); 
    }

    /**
     * Boot the archivable trait for a model.
     *
     * @return void
     */
    public static function bootArchivable()
    {
        static::addGlobalScope(new ArchivableScope);
    }

    /**
     * Get the model's archive.
     */
    public function archive(): MorphOne
    {
        return $this->morphOne(Archive::class, 'archivable');
    }

    /**
     * Check that the archiving of a model has been carried out correctly.
     * Verifies by default that all the relations defined as having to be archived with the model that has been
     */
    public function validateArchive(): bool
    {
        $archive = $this->archive;

        if ($archive) {
            /** @var array<string> $archive_with */
            $archiveWith = $archive->archive_with;

            $archived = DB::connection(static::$archiveConnection)->table($this->getTable())->where('id', $this->id)->first();

            if ($archived) {
                foreach ($archiveWith as $with) {
                    $sourceRelationship = $this->$with;

                    if ($sourceRelationship) {
                        $relationArchived = DB::connection(static::$archiveConnection)
                            ->table($sourceRelationship->getTable())
                            ->where('id', $sourceRelationship->id)
                            ->exists();

                        if (! $relationArchived) {
                            return false;
                        }
                    }

                }
            } else {
                return false;
            }

        }

        return true;
    }
}
