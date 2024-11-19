<?php

namespace Lab2view\ModelArchive\Traits;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Lab2view\ModelArchive\Eloquent\Builder as ArchiveBuilder;
use Lab2view\ModelArchive\Scopes\ReadArchiveScope;

/**
 * @template TModel of Model
 *
 * @mixin Model
 *
 * class ReadArchive
 *
 * @property ?int $readArchiveWhenDaysBefore
 * @property ?array<string, string> $uniqueBy
 * @property ?array<int, string> $archiveWith
 */
trait ReadArchive
{
    /**
     * Retrieve the model's archive connection
     */
    public function getArchiveConnection(): string
    {
        return Config::get('model-archive.archive_db_connection');
    }

    /**
     * Boot the ReadArchive trait for a model.
     */
    public static function bootReadArchive()
    {
        $instance = new static;
        if ($instance->getArchiveConnection()) {
            static::addGlobalScope(
                new ReadArchiveScope(
                    $instance->getArchiveConnection(),
                    $instance->readArchiveWhenDaysBefore ?? null
                )
            );
        }
    }

    /**
     * Get a new query builder that doesn't have any global scopes or eager loading.
     * @param  QueryBuilder  $builder
     * @return EloquentBuilder<TModel>
     */
    public function newEloquentBuilder(QueryBuilder $builder): ArchiveBuilder
    {
        return new ArchiveBuilder($builder);
    }

    /**
     * Get relations archived with model
     * @return array<int, string>
     */
    public function getArchiveWith(): array
    {
        return $this->archiveWith ?? [];
    }

    /**
     * Get data that makes the model unique
     * @return array<string, mixed>
     */
    public function getUniqueBy(): array
    {
        $uniqueBy = [];
        foreach ($this->uniqueBy ?? ['id' => 'id'] as $field => $value) {
            $uniqueBy[$field] = $this->$value;
        }

        return $uniqueBy;
    }
}
