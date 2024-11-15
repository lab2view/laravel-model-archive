<?php

namespace Lab2view\ModelArchive\Traits;

use Illuminate\Support\Carbon;
use lab2view\ModelArchive\QueryBuilder\QueryBuilder;

trait ArchiveFilters
{
    public function scopeCreatedBefore(QueryBuilder $builder, string $date): QueryBuilder
    {
        $builder->where('created_at', '<=', $date);
        /**
         * @var int|null $readArchiveWhenDaysBefore
         */
        $readArchiveWhenDaysBefore = $this->readArchiveWhenDaysBefore ?? null;

        if (
            $readArchiveWhenDaysBefore &&
            (now()->subDays($readArchiveWhenDaysBefore)->gte(Carbon::parse($date)))
        ) {
            $builder->onlyArchived();
        }

        return $builder;
    }

    public function scopeCreatedAfter(QueryBuilder $builder, string $date): QueryBuilder
    {
        return $builder->where('created_at', '>=', $date);
    }

    public function scopeArchived(QueryBuilder $builder, string $archived): QueryBuilder
    {
        if (in_array($archived, ['true', '1'])) {
            $builder->clone()->onlyArchived();
        }

        return $builder;
    }
}
