<?php

namespace Lab2view\ModelArchive\Eloquent;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lab2view\ModelArchive\Traits\ReadArchive;

/**
 * @template TModel of Model
 * Class Builder
 *
 * @extends EloquentBuilder<TModel>
 */
class Builder extends EloquentBuilder
{
   /**
     * Deletermine if the selections must execute the query only on the Database of archives
     */
    private bool $onArchive = false;

    /**
     * Deletermine if the selections must rexecute the query on the Database of archives if no match is found
     */
    private bool $fallbackToArchive = false;

    /**
     * Determine the relationship recovery strategy. Defunct whether to search for the element on the previous connection if it is not found
     */
    private bool $fallbackRelation = false;

    /**
     * Previous database connection (Before calling to onlyArchived)
     */
    private ?string $prevConnection = null;

    /**
     * Determines whether the builder is responsible for the original connection switch to the archives and not a relationship, nested or not
     */
    protected bool $isOriginalSwitching = false;

    /**
     * Execute the query as a "select" statement. Allows to switch database connection if the element is not found and re-execute the query
     * @param mixed $columns
     * @return static[]|Collection<TModel>
     */
    public function get($columns = ['*'])
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

    public function getRelation($name)
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
                /**
                 * @var mixed
                 */
                $model = $this->getModel();
                $archiveWith = $model->getArchiveWith();
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
     */
    public function fallbackToArchive(): static
    {
        $this->fallbackToArchive = true;

        return $this;
    }

    /**
     * Change the query connection to set it to the archive database
     */
    public function onlyArchived(): static
    {
        if (! $this->prevConnection) {
            $this->isOriginalSwitching = true;
        }
        /**
         * @var mixed
         */
        $model = $this->getModel();
        $this->putConnection($this, $model->getArchiveConnection());
        $this->setOnArchive(true);

        return $this;
    }

    /**
     * Set  the relationship recovery strategy
     */
    public function fallbackRelation(): static
    {
        $this->fallbackRelation = true;

        return $this;
    }

    /**
     * Set if pending connection is archive connection
     *
     * @return static
     */
    public function setOnArchive(bool $on): static
    {
        $this->onArchive = $on;

        return $this;
    }

    /**
     * Save previous connection
     */
    public function setPrevConnection(string $conn): static
    {
        $this->prevConnection = $conn;

        return $this;
    }

    /**
     * Exit from the archive connection database and return to the previous database
     * @return static
     */
     private function backToPreviousConnection(EloquentBuilder $builder): static
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
             * @var string
             */
            $connection = $this->getModel()->getConnection()->getName();
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
