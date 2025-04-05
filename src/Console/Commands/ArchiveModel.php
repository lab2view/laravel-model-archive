<?php

namespace Lab2view\ModelArchive\Console\Commands;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Lab2view\ModelArchive\Console\Commands\Base\Command;
use Lab2view\ModelArchive\Eloquent\Builder;
use Lab2view\ModelArchive\Models\ArchivableModel;
use Lab2view\ModelArchive\Models\Archive;
use Lab2view\ModelArchive\Models\ReadArchiveModel;
use Lab2view\ModelArchive\Traits\Archivable;
use Lab2view\ModelArchive\Traits\ReadArchive;

class ArchiveModel extends Command
{
    protected $signature = 'lab2view:archive-model';

    public $description = 'Archive all archivable models in the dedicated archives database.';

    public function handle(): int
    {
        $archivables = $this->getArchivables();
        if ($archivables->isEmpty()) {
            $this->error('>> There are no archivable models.');

            return self::FAILURE;
        }

        foreach ($archivables as $archivable) {
            $this->comment('>> Archive '.$archivable);

            $instance = new $archivable;
            $archiveWith = $instance->getArchiveWith();
            /**
             * @var string
             */
            $archiveConnection = $instance->getArchiveConnection();

            /** @var Builder<ArchivableModel> $query */
            $query = $archivable::withoutGlobalScopes();
            /** @var Builder<ArchivableModel> $archivablesQuery */
            $archivablesQuery = $query->clone()->archivable();
            if ($query->toSql() === $archivablesQuery->clone()->toSql()) {
                $this->error('<< The scopeArchivable method is not implemented to select data to archive on the model '.$archivable);

                return self::FAILURE;
            }
            $archivablesQuery = $archivablesQuery
                ->select('*')
                ->with($archiveWith);

            $bar = $this->output->createProgressBar($archivablesQuery->clone()->count());
            $bar->start();

            foreach ($archivablesQuery->cursor() as $model) {
                DB::beginTransaction();
                DB::connection($archiveConnection)->beginTransaction();

                try {
                    $this->archive($model, $archiveWith, $archiveConnection);
                    foreach ($archiveWith as $relation) {
                        $relationClass = $model->$relation();
                        if (! ($relationClass instanceof BelongsTo || $relationClass instanceof HasOne)) {
                            $this->comment(
                                <<<EOF
                                    >> The relation $archivable->$relation is not instanceof BelongTo or HasOne. 
                                    It will not be archived. 
                                    Make sure to archive it manually if necessary.
                                EOF
                            );

                            continue;
                        }
                        if (! in_array(ReadArchive::class, class_uses_recursive($relationClass->getQuery()->getModel()::class))) {
                            $this->error(">> The relation $archivable -> $relation model does not use ReadArchive trait.");

                            return self::FAILURE;
                        }
                        $relation = $model->$relation;
                        if ($relation) {
                            $this->archive($relation, [], $archiveConnection, false);
                        }
                    }
                    DB::commit();
                    DB::connection($archiveConnection)->commit();
                } catch (\Throwable $th) {
                    $this->error($th->getMessage());
                    DB::rollBack();
                    DB::connection($archiveConnection)->rollBack();
                }
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
        }

        return self::SUCCESS;
    }

    /**
     * Copy a model to the archive database
     *
     * @param  ReadArchiveModel  $model
     * @param  array<string>  $archiveWith
     */
    private function archive(Model $model, array $archiveWith, string $archiveConnection, bool $commit = true): void
    {
        $modelName = get_class($model);
        $dbRawData = $model->getRawOriginal();
        $uniqueBy = $model->getUniqBy();

        DB::connection($archiveConnection)
            ->table($model->getTable())
            ->upsert(
                $dbRawData,
                $uniqueBy,
                $dbRawData
            );

        if ($commit) {
            $id = $dbRawData['id'];
            $commitData = ['archivable_id' => $id, 'archivable_type' => $modelName, 'archive_with' => $archiveWith];
            if (Archive::where(['archivable_id' => $id, 'archivable_type' => $modelName])->doesntExist()) {
                Archive::create($commitData);
            }
        }
    }

    /**
     * Get all archivable models.
     *
     * @return Collection<int, class-string<ArchivableModel>>
     */
    private function getArchivables(): Collection
    {
        $models = collect(File::allFiles(app_path()))
            ->map(function ($item) {
                $path = $item->getRelativePathName();
                $class = sprintf('\%s%s',
                    app()->getNamespace(),
                    /** @phpstan-ignore-next-line */
                    strtr(substr($path, 0, strrpos($path, '.')), '/', '\\'));

                return $class;
            })
            ->filter(function ($class) {
                $valid = false;
                if (class_exists($class)) {
                    $reflection = new \ReflectionClass($class);
                    $valid = ! $reflection->isAbstract() &&
                            $reflection->isSubclassOf(Model::class) &&
                            (in_array(Archivable::class, class_uses_recursive($class)));
                }

                return $valid;
            });
        /**
         * @var Collection<int, class-string<ArchivableModel>>
         */
        $values = $models->values();

        return $values;
    }
}
