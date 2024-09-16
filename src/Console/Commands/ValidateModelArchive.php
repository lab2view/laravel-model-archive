<?php

namespace Lab2view\ModelArchive\Console\Commands;

use Illuminate\Database\Eloquent\SoftDeletes;
use Lab2view\ModelArchive\Console\Commands\Base\Command;
use Lab2view\ModelArchive\Models\Archive;
use Lab2view\ModelArchive\Scopes\ArchivableScope;

class ValidateModelArchive extends Command
{
    protected $signature = 'lab2view:validate_model_archive';

    public $description = 'Check if archivables models as been good archived and remove them to main database';

    public function handle(): int
    {
        $archives = Archive::select('*')->unvalidated()->get();

        foreach ($archives as $archive) {
            /**
             * @var array<class-string> $withoutGlobalScope
             */
            $withoutGlobalScope = [ArchivableScope::class];
            if (array_key_exists(SoftDeletes::class, (new \ReflectionClass($archive->archivable_type))->getTraits())) {
                array_push($withoutGlobalScope, SoftDeletes::class);
            }

            $archived = $archive->archivable_type::withoutGlobalScopes($withoutGlobalScope)
                ->where('id', $archive->archivable_id)
                ->with($archive->archive_with)
                ->select('*')
                ->first();

            if ($archived) {
                $archived->archive = $archive;

                if ($archived->validateArchive()) {
                    $archive->validated_at = now();
                    $archive->save();
                }
            }

            // if($isSoftDelete){
            //     $archived->forceDelete();
            // }else{
            //     $archived->delete();
            // }
        }

        $this->comment('>> Validation of the archives done.');

        return self::SUCCESS;
    }
}
