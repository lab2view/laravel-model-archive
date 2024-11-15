<?php

namespace Lab2view\ModelArchive\QueryBuilder;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Class QueryBuilder
 *
 * @method static static onlyArchived()
 * @property string $archiveConnection
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
        if($this->fallbackToArchive){
            $this->macro('_fallbackToArchive', fn() => $this->getConnection());
        }

        return $this;
    }

    public function getRelation($name)
    {
        $relation = parent::getRelation($name);
        if($this->hasMacro('_fallbackToArchive')){
            $relation->getBaseQuery()->macro('_fallbackToArchive', $this->getMacro('_fallbackToArchive'));
        }
        return $relation;
    }

    protected function eagerLoadRelation(array $models, $name, \Closure $constraints)
    {
        $relation = $this->getRelation($name);

        $relation->addEagerConstraints($models);

        $constraints($relation);

        $eager = $relation->getEager();
        Log::info('', [
            'eager' => $eager->isEmpty(),
            'hasmacro' => $relation->getBaseQuery()->hasMacro('_fallbackToArchive')
        ]);

        if($eager->isEmpty() && $relation->getBaseQuery()->hasMacro('_fallbackToArchive')){
            $query = $relation->getBaseQuery();
            $conn = $relation->getQuery()->getMacro('_fallbackToArchive')();
            $query->connection = $conn;
            $query->grammar = $conn->query()->getGrammar();
            $query->processor = $conn->query()->getProcessor();
            $query->macro('_fallbackToArchive', function () {
                return true;
            });
        }
        return $relation->match(
            $relation->initRelation($models, $name),
            $relation->getEager(), $name
        );
    }
}
 