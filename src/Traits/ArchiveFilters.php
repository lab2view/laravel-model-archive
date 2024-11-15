<?php

namespace Lab2view\ModelArchive\Traits;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use lab2view\ModelArchive\QueryBuilder\QueryBuilder;

trait ArchiveFilters
{
    public function scopeCreatedBefore(QueryBuilder $builder, string $date): QueryBuilder
    {
        $builder->where('created_at', '<=', $date);
        /**
         * @var int $readArchiveWhenDaysBefore
         */
        $readArchiveWhenDaysBefore = $this->readArchiveWhenDaysBefore;

        Log::info('', [
            'sub' => (now()->subDays($readArchiveWhenDaysBefore)->gte(Carbon::parse($date))),
        ]);
        if (
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
            $builder->onlyArchived();
        }

        return $builder;
    }
}
