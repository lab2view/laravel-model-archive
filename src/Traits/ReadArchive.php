<?php

namespace Lab2view\ModelArchive\Traits;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Lab2view\ModelArchive\Eloquent\Builder as ArchiveBuilder;
use Lab2view\ModelArchive\Models\ReadArchiveModel;
use Lab2view\ModelArchive\Scopes\ReadArchiveScope;

/**
 * @template TModel of ReadArchiveModel
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
    public function getArchiveConnection(): ?string
    {
        return Config::get('model-archive.archive_db_connection');
    }

    public function getMainConnection(): ?string
    {
        return Config::get('model-archive.main_db_connection');
    }

    /**
     * Boot the ReadArchive trait for a model.
     */
    public static function bootReadArchive(): void
    {
        /** @phpstan-ignore-next-line */
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
     * Begin querying a model with eager loading.
     *
     * @param  array<mixed>|string  $relations
     */
    public static function with($relations, ?bool $fallback = false): EloquentBuilder
    {
        $query = static::query()->with(
            is_string($relations) ? func_get_args() : $relations
        );

        if ($fallback) {
            $query->fallbackRelation();
        }

        return $query;
    }

    /**
     * Eager load relations on the model.
     *
     * @param  array<mixed>|string  $relations
     * @return $this
     */
    public function load($relations, ?bool $fallback = false)
    {
        $query = $this->newQueryWithoutRelationships();

        if ($fallback) {
            $query->fallbackRelation();
        }

        $query->with(
            is_string($relations) ? func_get_args() : $relations
        );

        $query->eagerLoadRelations([$this]);

        return $this;
    }

    /**
     * Create a new Eloquent query builder for the model.
     */
    public function newEloquentBuilder($builder): ArchiveBuilder
    {
        return new ArchiveBuilder($builder);
    }

    /**
     * Get relations archived with model
     *
     * @return array<int, string>
     */
    public function getArchiveWith(): array
    {
        return $this->archiveWith ?? [];
    }

    /**
     * Get data that makes the model unique
     *
     * @return array<string, mixed>
     */
    public function getUniqBy(): array
    {
        $uniqueBy = [];
        foreach ($this->uniqueBy ?? ['id' => 'id'] as $field => $value) {
            $uniqueBy[$field] = $this->$value;
        }

        return $uniqueBy;
    }
}
