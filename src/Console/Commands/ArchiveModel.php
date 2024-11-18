<?php

namespace Lab2view\ModelArchive\Console\Commands;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File; 
use Lab2view\ModelArchive\Models\Archive;
use Lab2view\ModelArchive\Traits\Archivable;
use Lab2view\ModelArchive\Traits\ReadArchive;
use Lab2view\ModelArchive\Console\Commands\Base\Command; 
class ArchiveModel extends Command
{
    protected $signature = 'lab2view:archive-model';

    public $description = 'Archive all archivable models in the dedicated archives database.';

    public function handle(): int
    {
        $archivables = $this->getArchivables();
        if ($archivables->isEmpty()) {
            $this->error('>> There are no achivable model.');

            return self::FAILURE;
        }

        foreach ($archivables as $archivable) {
            $this->comment('>> Archive '.$archivable);

            $instance = new $archivable;
            $archiveWith = $instance->getArchiveWith();
            $archiveConnection = $instance->getArchiveConnection();

            $archivablesQuery = $archivable::withoutGlobalScopes()
                ->archivable()
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
                        if (! ($relationClass instanceof BelongsTo)) {
                            $this->comment(">> The relation $archivable -> $relation is not instanceof BelongTo. There are not archived");
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
     * @param Archivable $model
     * @param  array<string>  $archiveWith
     */
    private function archive(Model $model, array $archiveWith, string $archiveConnection, bool $commit = true): void
    {
        $modelName = get_class($model);
        $original = $model->getRawOriginal();
        $id = $original['id'];
        $uniqueBy = $model->getUniqueBy();

        DB::connection($archiveConnection)
            ->table($model->getTable())
            ->upsert(
                $original,
                $uniqueBy,
                $original
            );
        
        if ($commit) {
            $data = ['archivable_id' => $id, 'archivable_type' => $modelName, 'archive_with' => $archiveWith];
            if(!Archive::where(['archivable_id' => $id, 'archivable_type' => $modelName])->exists()){
                Archive::create($data);
            }
        }
    }

    /**
     * Get all archivable models.
     *
     * @return Collection<class-string<Archivable>>
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
