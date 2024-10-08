<?php

namespace Lab2view\ModelArchive\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Config;

class Archive extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'archivable_id',
        'archivable_type',
        'archive_with',
    ];

    public function getConnectionName(){
        return Config::get('model-archive.archive_db_connection');
    }
    
    /**
     * Interact with the attribute archive_with.
     */
    protected function archiveWith(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => json_decode($value),
            set: fn (array $value) => json_encode($value),
        );
    }

    /**
     * Get the parent archivable model.
     */
    public function archivable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeUnvalidated(Builder $query): Builder
    {
        return $query->whereNull('validated_at');
    }
}
