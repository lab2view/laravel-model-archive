<?php

namespace Lab2view\ModelArchive\Console\Commands;

use Lab2view\ModelArchive\Console\Commands\Base\Command;
use Lab2view\ModelArchive\Models\Archive;
use Lab2view\ModelArchive\Traits\Archivable;

class ValidateArchiveModel extends Command
{
    protected $signature = 'lab2view:validate-archive-model';

    public $description = 'Check if the archives were done correctly and delete the originals from the main database.';

    public function handle(): int
    {
        $unvalidatedCommitsQuery = Archive::select('*')->unvalidated();

        $bar = $this->output->createProgressBar($unvalidatedCommitsQuery->clone()->count());
        $bar->start();
        foreach ($unvalidatedCommitsQuery->cursor() as $commit) {
            /**
             * @var Archivable | null $source
             */
            $source = $commit->archivable_type::withoutGlobalScopes()
                ->where('id', $commit->archivable_id)
                ->with($archive->archive_with ?? [])
                ->select('*')
                ->first();

            if ($source) {
                if ($source->validateArchive($commit)) {
                    $commit->validated_at = now();
                    $commit->save();
                    $source->delete();
                }

            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        return self::SUCCESS;
    }
}
