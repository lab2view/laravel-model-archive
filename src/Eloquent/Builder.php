<?php

namespace Lab2view\ModelArchive\Eloquent;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Lab2view\ModelArchive\Models\ReadArchiveModel;

/**
 * @template TModel of ReadArchiveModel
 * Class Builder
 *
 * @extends EloquentBuilder<TModel>
 */
class Builder extends EloquentBuilder
{
    /**
     * Determine if any of the methods to use archives (fallbackToArchive, onlyArchive, fallbackRelation) were called
     */
    public bool $useArchive = false;

    /**
     * Determine if the selections must rexecute the query on the Database of archives if no match is found
     */
    private bool $fallbackToArchive = false;

    /**
     * Determine the recovery strategy for the relation. Defines whether to look for the element on the second connection if it is not found
     */
    private bool $fallbackRelation = false;

    /**
     * Determine if the builder originally defined the ability to fall back to archives
     */
    private bool $isOriginalSwitching = false;

    /**
     * @param  mixed  $columns
     * @return array<TModel>|Collection<TModel>
     */
    public function get($columns = ['*']): array|Collection
    {
        $collection = parent::get($columns);
        if ($collection->isEmpty() && $this->useArchive) {
            if (
                $this->fallbackToArchive ||
                (! $this->isOriginalSwitching && $this->fallbackRelation && ! $this->onArchive())
            ) {
                $this->fallbackToOnlyArchive();
                $collection = parent::get($columns);
            } elseif (! $this->isOriginalSwitching && $this->fallbackRelation && $this->onArchive()) {
                $this->fallbackToMainConnection($this);
                $collection = parent::get($columns);
            }
        }

        return $collection;
    }

    /**
     * Paginate the given query.
     *
     * @param  int|null  $perPage
     * @param  array<int, string>  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @param  int|null  $total
     * @return LengthAwarePaginator
     *
     * @throws InvalidArgumentException
     */
    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null, $total = null): LengthAwarePaginator
    {
        Log::debug('paginate', [
            'perPage' => $perPage,
            'columns' => $columns,
            'pageName' => $pageName,
            'page' => $page,
            'total' => $total,
        ]);
        return parent::paginate($perPage, $columns, $pageName, $page, $total);
    }

    public function exists(): bool
    {
        $exists = parent::exists();
        if (! $exists && $this->useArchive) {
            if (
                $this->fallbackToArchive ||
                (! $this->isOriginalSwitching && $this->fallbackRelation && ! $this->onArchive())
            ) {
                $this->fallbackToOnlyArchive();
                $exists = parent::exists();
            } elseif (! $this->isOriginalSwitching && $this->fallbackRelation && $this->onArchive()) {
                $this->fallbackToMainConnection($this);
                $exists = parent::exists();
            }
        }

        return $exists;
    }

    public function getRelation($name): Relation
    {
        $relation = parent::getRelation($name);
        // When a connection change has been made to the archive.
        // Whether we are still there or not (fallbackRelation strategy).
        if ($this->useArchive) {
            // If the relationship does not use this eloquent builder, go back to the previous connection and stay there permanently.
            if (! $relation->getQuery() instanceof self) {
                // Back to previous connection
                $this->fallbackToMainConnection($relation->getQuery());
            } else {
                // perpetuate the base configuration (fallbackRelation, previous connection ...)
                $this->fallbackRelation && $relation->getQuery()->fallbackRelation();

                $archiveWith = $this->getModel()->getArchiveWith();
                // If the relationship is defined as being archives and we are already on the archives,
                // maintain the connection of the relationship in the archives
                if (in_array($name, $archiveWith) && $this->onArchive()) {
                    // Stay on archives
                    $relation->getQuery()->fallbackToOnlyArchive();
                }
                // If the relationship is defined as archives and we are on the previous connection,
                // change the relationship connection in the archives
                if (in_array($name, $archiveWith) && ! $this->onArchive()) {
                    // Go to archives
                    $relation->getQuery()->fallbackToOnlyArchive();
                }
                // If the relationship is not defined as being archived, redefine the connection of the relationship
                // on the previous database
                if (! in_array($name, $archiveWith)) {
                    // Back to previous connection
                    $this->fallbackToMainConnection($relation->getQuery());
                }
            }
        }

        return $relation;
    }

    /**
     * Check if current query using archive connection
     */
    private function onArchive(): bool
    {
        return $this->getModel()->getConnectionName() === $this->getModel()->getArchiveConnection();
    }

    /**
     * Re-execute the query on the Database of archives if no match is found
     *
     * @return Builder<TModel>
     */
    public function fallbackToArchive(): self
    {
        $this->useArchive = true;
        $this->fallbackToArchive = true;

        return $this;
    }

    /**
     * Change the query connection to set it to the archive database
     *
     * @return Builder<TModel>
     */
    public function onlyArchived(): self
    {
        $this->useArchive = true;
        /**
         * @var string
         */
        $connection = $this->getModel()->getArchiveConnection();
        $this->putConnection($this, $connection);
        $this->isOriginalSwitching = true;

        return $this;
    }

    /**
     * Set the relationship recovery strategy
     *
     * @return Builder<TModel>
     */
    public function fallbackRelation(): self
    {
        $this->useArchive = true;
        $this->fallbackRelation = true;

        return $this;
    }

    private function fallbackToOnlyArchive(): self
    {
        $this->useArchive = true;
        /**
         * @var string
         */
        $connection = $this->getModel()->getArchiveConnection();
        $this->putConnection($this, $connection);

        return $this;
    }

    /**
     * Exit from the archive connection database and return to the main database
     */
    private function fallbackToMainConnection(EloquentBuilder $builder): static
    {
        /**
         * @var string
         */
        $connection = $this->getModel()->getMainConnection();
        $this->putConnection($builder, $connection);
        if ($builder instanceof self) {
            $builder->useArchive = true;
        }

        return $this;
    }

    /**
     * Set a connection on a given Eloquent builder
     */
    private function putConnection(self|EloquentBuilder $builder, string $c): EloquentBuilder
    {
        // Set the connection on the underlying model instance so that generated
        // Relationships/pivots use the same connection
        $builder->getModel()->setConnection($c);
        $connection = $builder->getModel()->getConnection();

        // Set the same connection on the query builder
        $query = $builder->getQuery();
        $query->connection = $connection;
        $query->grammar = $connection->query()->getGrammar();
        $query->processor = $connection->query()->getProcessor();

        $builder->setQuery($query);

        return $builder;
    }
}
