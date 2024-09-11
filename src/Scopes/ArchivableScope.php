<?php

namespace Lab2view\ModelArchive\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;

class ArchivableScope implements Scope
{
    public function __construct(protected string $archiveConnection) {}

    /**
     * All of the extensions to be added to the builder.
     *
     * @var string[]
     */
    protected $extensions = ['Archivable', 'Unarchived', 'OnlyArchived'];

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TModel>  $builder
     * @param  TModel  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->unarchived();
    }

    /**
     * Extend the query builder with the needed functions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $builder
     * @return void
     */
    public function extend(Builder $builder)
    {
        foreach ($this->extensions as $extension) {
            $this->{"add{$extension}"}($builder);
        }
    }

    /**
     * Add the archivable extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $builder
     * @return void
     */
    public function addArchivable(Builder $builder)
    {
        $builder->macro('archivable', function (Builder $builder) {
            return $builder;
        });
    }

    /**
     * Add the Unarchived extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $builder
     * @return void
     */
    public function addUnarchived(Builder $builder)
    {
        $builder->macro('unarchived', function (Builder $builder) {
            $model = $builder->getModel();

            return $builder->getQuery()->whereNotExists(function ($query) use ($model) {
                $query->select(DB::raw(1))
                    ->from('archives')
                    ->whereRaw('archives.archivable_id = '.$model->getTable().'.id')
                    ->where('archives.archivable_type', $model::class);
            });
        });
    }

    /**
     * Add the only-archived archived extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $builder
     * @return void
     */
    public function addOnlyArchived(Builder $builder)
    {
        $builder->macro('onlyArchived', function (Builder $builder) {
            $conn = DB::connection($this->archiveConnection);

            $query = $builder->getQuery();
            $query->connection = $conn;
            $query->grammar = $conn->query()->getGrammar();
            $query->processor = $conn->query()->getProcessor();

            return $builder->setQuery($query);
        });
    }
}
