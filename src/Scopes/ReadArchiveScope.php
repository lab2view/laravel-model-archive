<?php

namespace Lab2view\ModelArchive\Scopes;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;

class ReadArchiveScope implements Scope
{
    public function __construct(
        protected string $archiveConnection,
        protected ?int $readArchiveWhenDaysBefore
    ) {}

    /**
     * Set of extensions to read archives
     *
     * @var array<int, string>
     */
    protected array $extensions = ['Validated', 'CreatedBefore', 'CreatedAfter', 'Archived'];

    public function apply(Builder $builder, Model $model) {}

    public function extend(Builder $builder): void
    {
        foreach ($this->extensions as $extension) {
            $this->{"add{$extension}"}($builder);
        }
    }

    /**
     * Added extension to retrieve only validated archives
     */
    public function addValidated(Builder $builder): void
    {
        $validated = function ($builder) {
            $model = $builder->getModel();

            return $builder->getQuery()->whereExists(function (Builder $query) use ($model) {
                $query->select(DB::raw(1))
                    ->from('archives')
                    ->whereRaw('archives.archivable_id = '.$model->getTable().'.id')
                    ->where('archives.archivable_type', $model::class)
                    ->whereNotNull('archives.validated_at');
            });
        };
        $builder->macro('validated', $validated);
    }

    /**
     * Added the extension to have the elements created before a date. Beyond the date defined
     * in the readArchiveWhenDaysBefore parameter, reading is done on the archive database
     */
    public function addCreatedBefore(Builder $builder): void
    {
        $days = $this->readArchiveWhenDaysBefore;
        $createdBefore = function ($builder, string $date) use ($days) {
            $builder->where('created_at', '<=', $date);

            if ($days && (now()->subDays($days)->gte(Carbon::parse($date)))) {
                $builder->onlyArchived();
            }

            return $builder;
        };
        $builder->macro('createdBefore', $createdBefore);
    }

    /**
     * Added extension to have the elements created after a date
     */
    public function addCreatedAfter(Builder $builder): void
    {
        $createdAfter = function ($builder, string $date) {
            return $builder->where('created_at', '>=', $date);
        };
        $builder->macro('createdAfter', $createdAfter);
    }

    /**
     * Added an extension to recover only archives
     */
    public function addArchived(Builder $builder): void
    {
        $archived = function ($builder, string $archived) {
            if (in_array($archived, ['true', '1'])) {
                $builder->onlyArchived();
            }

            return $builder;
        };
        $builder->macro('archived', $archived);
    }
}
