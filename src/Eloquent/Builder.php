<?php

namespace Lab2view\ModelArchive\Eloquent;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Lab2view\ModelArchive\Traits\ReadArchive;

/**
 * @template TModel of ReadArchive
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
    protected bool $fallback = false;

    /**
     * Previous database connection (Before calling to onlyArchived)
     */
    public ?string $prevConnection = null;

    public function get($columns = ['*']):array| Collection
    {
        
        $collection = parent::get($columns);
        if(!$collection->isEmpty()){
            if($this->fallbackToArchive || ($this->fallback && !$this->onArchive)){
                $this->onlyArchived();
                $collection = parent::get($columns);

            }elseif($this->fallback && $this->onArchive){
                $this->backToPreviousConnection($this);
                $collection = parent::get($columns);
            }
        }
        
        return $collection;
    }

    public function exists(): bool
    {
        $exists = parent::exists();
        if(!$exists){
            if($this->fallbackToArchive && ($this->fallback && !$this->onArchive)){
                $this->onlyArchived();
                $exists = parent::exists();

            }elseif($this->fallback && $this->onArchive){
                $this->backToPreviousConnection($this);
                $exists = parent::exists();
            }
        }
        
        return $exists;
    }

    public function getRelation($name)
    {
        $relation = parent::getRelation($name);
        // If the relationship does not use this builder, return to the previous connection
        if($this->prevConnection && !$relation->getQuery() instanceof self){
            $this->backToPreviousConnection($relation->getQuery());
        }elseif($this->prevConnection){
            // If the relationship uses this builder, perpetuate the configuration defined at the base
            $relation->getQuery()->setPrevConnection($this->prevConnection);
            $relation->getQuery()->setOnArchive($this->onArchive);
            if($this->fallback) $relation->getQuery()->fallback();
        }

        return $relation;
    }
    
    /**
     * Define if the selections must rexcute the query on the Database of archives if no match is found
     */
    public function fallbackToArchive(): self
    {
        $this->fallbackToArchive = true;
        return $this;
    }

    /**
     * Change the query connection to set it to the archive database
     */
    public function onlyArchived(): static
    {
        $this->putConnection($this->getModel()->getArchiveConnection(), $this);
        $this->setOnArchive(true);
        return $this;
    }

    /**
     * Set  the relationship recovery strategy
     */
    public function fallback(){
        $this->fallback = true;
    }

    /**
     * Set if pending connection is archive connection
     * @param bool $on
     * @return static
     */
    public function setOnArchive(bool $on){
        $this->onArchive = $on;
        return $this;
    }

    /**
     * Save previous connection
     */
    public function setPrevConnection(string $conn){
        $this->prevConnection = $conn;
    }

    /**
     * Exit from the archive connection database and return to the previous database
     * @param EloquentBuilder $builder
     */
    private function backToPreviousConnection(EloquentBuilder $builder){
        /**
         * @var string $prevConnection
         */
        $prevConnection = $this->prevConnection;
        $this->putConnection($prevConnection, $builder);

        if($builder instanceof self){
            $builder->setOnArchive(false);
        }
        return $this;
    }

    /**
     * Set a connection on a given Eloquent builder
     */
    private function putConnection(string $conn, self | EloquentBuilder $builder): EloquentBuilder
    {
        if ($builder instanceof self && $this->prevConnection) {
            $builder->setPrevConnection($this->prevConnection);
        }
        if($builder instanceof self && !$builder->prevConnection){
            $builder->setPrevConnection($this->getModel()->getConnection()->getName());
        }

        // Set the connection on the underlying model instance so that generated
        // Relationships/pivots use the same connection
        $builder->getModel()->setConnection($conn);
        $conn = $builder->getModel()->getConnection();

        // Set the same connection on the query builder
        $query = $builder->getQuery();
        $query->connection = $conn;
        $query->grammar = $conn->query()->getGrammar();
        $query->processor = $conn->query()->getProcessor();

        $builder->setQuery($query);

        return $builder;
    }
}
