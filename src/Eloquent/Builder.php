<?php

namespace Lab2view\ModelArchive\Eloquent;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
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
     * Deletermine if the selections must execute the query only on the Database of archives
     */
    protected bool $onArchive = false;

    /**
     * Deletermine if the selections must rexecute the query on the Database of archives if no match is found
     */
    protected bool $fallbackToArchive = false;

    /**
     * Determine the relationship recovery strategy. Defunct whether to search for the element on the previous connection if it is not found
     */
    protected bool $fallbackRelation = false;

    /**
     * Previous database connection (Before calling to onlyArchived)
     */
    public ?string $prevConnection = null;

    /**
     * Determines whether the builder is responsible for the original connection switch to the archives and not a relationship, nested or not
     */
    protected bool $isOriginalSwitching = false;

    /**
     * @param  mixed  $columns
     * @return array<TModel>|Collection<TModel>
     */
    public function get($columns = ['*']): array|Collection
    {
        $collection = parent::get($columns);
        if ($collection->isEmpty()) {
            if ($this->fallbackToArchive ||
             (! $this->isOriginalSwitching && $this->fallbackRelation && ! $this->onArchive)
            ) {
                $this->onlyArchived();
                $collection = parent::get($columns);

            } elseif (! $this->isOriginalSwitching && $this->fallbackRelation && $this->onArchive) {
                $this->backToPreviousConnection($this);
                $collection = parent::get($columns);
            }
        }

        return $collection;
    }

    public function exists(): bool
    {
        $exists = parent::exists();
        if (! $exists) {
            if ($this->fallbackToArchive &&
            (! $this->isOriginalSwitching && $this->fallbackRelation && ! $this->onArchive)
            ) {
                $this->onlyArchived();
                $exists = parent::exists();

            } elseif ($this->fallbackRelation && $this->onArchive) {
                $this->backToPreviousConnection($this);
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
        if ($this->prevConnection) {
            // If the relationship does not use this eloquent builder, go back to the previous connection and stay there permanently.
            if (! $relation->getQuery() instanceof self) {
                // Back to previous connection
                $this->backToPreviousConnection($relation->getQuery());
            } else {
                $archiveWith = $this->getModel()->getArchiveWith();
                // If the relationship is defined as being archives and we are already on the archives,
                // maintain the connection of the relationship in the archives and perpetuate the base
                // configuration (fallbackRelation, previous connection ...)
                if (in_array($name, $archiveWith) && $this->onArchive) {
                    // Stay on archives
                    $relation->getQuery()->onlyArchived();
                    // Perpetuate
                    $this->fallbackRelation && $relation->getQuery()->fallbackRelation();
                }
                // If the relationship is defined as archives and we are on the previous connection,
                // change the relationship connection in the archives and perpetuate the base configuration (fallbackRelation, previous connection...)
                if (in_array($name, $archiveWith) && ! $this->onArchive) {
                    // Go to archives
                    $relation->getQuery()->onlyArchived();
                    // Perpetuate
                    $this->fallbackRelation && $relation->getQuery()->fallbackRelation();
                }
                // If the relationship is not defined as being archived, redefine the connection of the relationship
                // on the previous database while preserving the configuration defined at the base (fallback relationship, prevConnection, ...)
                if (! in_array($name, $archiveWith)) {
                    // Back to previous connection
                    $this->backToPreviousConnection($relation->getQuery());
                    // Perpetuate
                    $this->fallbackRelation && $relation->getQuery()->fallbackRelation();
                }
            }
        }

        return $relation;
    }

    /**
     * Re-execute the query on the Database of archives if no match is found
     *
     * @return Builder<TModel>
     */
    public function fallbackToArchive(): self
    {
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
        if (! $this->prevConnection) {
            $this->isOriginalSwitching = true;
        }
        $this->putConnection($this, $this->getModel()->getArchiveConnection());
        $this->setOnArchive(true);

        return $this;
    }

    /**
     * Set  the relationship recovery strategy
     *
     * @return Builder<TModel>
     */
    public function fallbackRelation(): self
    {
        $this->fallbackRelation = true;

        return $this;
    }

    /**
     * Set if pending connection is archive connection
     *
     * @return Builder<TModel>
     */
    public function setOnArchive(bool $on): self
    {
        $this->onArchive = $on;

        return $this;
    }

    /**
     * Save previous connection
     *
     * @return Builder<TModel>
     */
    public function setPrevConnection(string $conn): self
    {
        $this->prevConnection = $conn;

        return $this;
    }

    /**
     * Exit from the archive connection database and return to the previous database
     *
     * @return Builder<TModel>
     */
    private function backToPreviousConnection(EloquentBuilder $builder): self
    {
        $this->putConnection(builder: $builder);

        if ($builder instanceof self) {
            $builder->setOnArchive(false);
        }

        return $this;
    }

    /**
     * Set a connection on a given Eloquent builder
     */
    private function putConnection(self|EloquentBuilder $builder, ?string $c = null): EloquentBuilder
    {
        if (! $this->prevConnection) {
            /**
             * @var ReadArchiveModel
             */
            $model = $this->getModel();
            /**
             * @var string
             */
            $connection = $model->getConnection()->getName();
            $this->setPrevConnection($connection);
        }
        if ($builder instanceof self) {
            /**
             * @var string
             */
            $connection = $this->prevConnection;
            $builder->setPrevConnection($connection);
        }
        if (! $c) {
            $c = $this->prevConnection;
        }
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
