<?php

namespace Lab2view\ModelArchive\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Lab2view\ModelArchive\Eloquent\Builder;
use Lab2view\ModelArchive\Scopes\ReadArchiveScope;

/**
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

    public function newEloquentBuilder($builder): Builder
    {
        return new Builder($builder);
    }

    public function getArchiveWith(): array
    {
        return $this->archiveWith ?? [];
    }

    public function getUniqueBy(): array
    {
        $uniqueBy = [];
        foreach ($this->uniqueBy ?? ['id' => 'id'] as $field => $value) {
            $uniqueBy[$field] = $this->$value;
        }

        return $uniqueBy;
    }
}
