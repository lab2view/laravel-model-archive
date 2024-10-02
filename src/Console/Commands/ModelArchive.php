<?php

namespace Lab2view\ModelArchive\Console\Commands;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Lab2view\ModelArchive\Console\Commands\Base\Command;
use Lab2view\ModelArchive\Models\Archive;
use Lab2view\ModelArchive\Traits\Archivable;

class ModelArchive extends Command
{
    protected $signature = 'lab2view:model_archive';

    public $description = 'Archive all archivable models in the dedicated archives database.';

    public function handle(): int
    {
        $models = $this->getArchivables();

        if (count($models) === 0) {
            $this->error('>> There are no achivable model.');
        }

        foreach ($models as $model) {

            $archive_with = $model::$archive_with;

            /** @var \Illuminate\Database\Eloquent\Builder $query */
            $query = $model::withoutGlobalScopes(array_key_exists(SoftDeletes::class, (new \ReflectionClass($model))->getTraits()) ? [SoftDeletes::class] : [])
                ->select('*')
                ->archivable()
                ->with($archive_with);

            foreach ($query->cursor() as $item) {
                $archiveConnection = $model::$archiveConnection;

                DB::beginTransaction();
                DB::connection($archiveConnection)->beginTransaction();

                try {
                    $this->archive($item, $archive_with, $archiveConnection);
                    foreach ($archive_with as $relation) {
                        if (! ($item->$relation() instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo)) {
                            $this->error('>> The relation '.$relation.' in '.$model.'.$archive_with is not instanceof BelongTo.');

                            continue;
                        }

                        $relationValue = $item->$relation;

                        if ($relationValue) {
                            $this->archive($relationValue, [], $archiveConnection, false);
                        }
                    }

                    DB::commit();
                    DB::connection($archiveConnection)->commit();
                } catch (\Throwable $th) {
                    DB::rollBack();
                    DB::connection($archiveConnection)->rollBack();
                }
            }
            $this->comment('>> Archive of model '.$model.' done.');
        }

        $this->comment('>> Archive of all models done.');

        return self::SUCCESS;
    }

    /**
     * Copy a model to the archive database
     *
     * @param  array<string>  $archiveWith
     */
    private function archive(Model $model, array $archiveWith, string $archiveConnection, bool $commit = true): void
    {
        $modelName = $model::class;
        $original = $model->getRawOriginal();
        $id = $original['id'];
        $uniqueBy = [
            'id' => $id,
        ];

        DB::connection($archiveConnection)->table($model->getTable())->upsert($original, $uniqueBy, $original);

        if ($commit) {
            (new Archive([
                'archivable_id' => $id,
                'archivable_type' => $modelName,
                'archive_with' => $archiveWith,
            ]))->save();
        }
    }

    /**
     * Get all archivable models.
     */
    private function getArchivables(): Collection
    {
        $models = collect(File::allFiles(app_path()))
            ->map(function ($item) {
                $path = $item->getRelativePathName();

                $class = sprintf('\%s%s',
                    app()->getNamespace(),
                    strtr(substr($path, 0, strrpos($path, '.')), '/', '\\'));

                return $class;
            })
            ->filter(function ($class) {
                $valid = false;

                if (class_exists($class)) {
                    $reflection = new \ReflectionClass($class);
                    $valid = ! $reflection->isAbstract() &&
                            $reflection->isSubclassOf(Model::class) &&
                            array_key_exists(Archivable::class, $reflection->getTraits());
                }

                return $valid;
            });

        return $models->values();
    }
}
