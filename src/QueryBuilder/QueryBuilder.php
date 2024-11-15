<?php

namespace Lab2view\ModelArchive\QueryBuilder;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Class QueryBuilder
 *
 * @method static static onlyArchived()
 */
class QueryBuilder extends EloquentBuilder
{
    /**
     * Deletermine if the selections must rexcute the query on the Database of archives if no match is found
     */
    protected bool $fallbackToArchive = false;

    /**
     * @param  mixed  $columns
     * @return \Illuminate\Database\Eloquent\Collection|array<static>
     */
    public function get($columns = ['*']): Collection|array
    {
        if (($collection = parent::get($columns))->isEmpty() && $this->fallbackToArchive) {
            self::onlyArchived();
            $collection = parent::get($columns);
        }

        return $collection;
    }

    public function exists(): bool
    {
        if (($exists = parent::exists()) == false && $this->fallbackToArchive) {
            self::onlyArchived();
            $exists = parent::exists();
        }

        return $exists;
    }

    /**
     * Define if the selections must rexcute the query on the Database of archives if no match is found
     */
    public function fallbackToArchive(bool $to = true): static
    {
        $this->fallbackToArchive = $to;

        return $this;
    }

    public function getRelation($name)
    {
        $relation = parent::getRelation($name);

        $this_conn = $this->getConnection();
        $relation__conn = $relation->getQuery()->getModel()->getConnection();

        $archive_with = $this->getModel()->archive_with ?? [];
        Log::info('', ["archiveWith" => $archive_with, "model" => $this->getModel()::class]);

        if (
            in_array($name, $archive_with) &&
            $this_conn->getDatabaseName() !== $relation__conn->getDatabaseName()
        ) {
            $query = $relation->getBaseQuery();
            $query->connection = $this_conn;
            $query->grammar = $this_conn->query()->getGrammar();
            $query->processor = $this_conn->query()->getProcessor();
            $query->macro('_fallbackToArchive', function () {
                return true;
            });
        }

        return $relation;
    }
}
