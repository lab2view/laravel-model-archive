<?php

namespace Lab2view\ModelArchive\Console\Commands;

use Illuminate\Support\Facades\DB;
use Lab2view\ModelArchive\Console\Commands\Base\Command;
use Lab2view\ModelArchive\Models\Archive;

class ValidateArchiveModel extends Command
{
    protected $signature = 'lab2view:validate-archive-model';

    public $description = 'Check if the archiving was done correctly '
        .'(if the archived data, still present in the main database exists '
        .'in the archive database, as well as its archived relations) '
        .'and if so, delete this data from the main database.';

    public function handle(): int
    {
        $unvalidatedCommitsQuery = Archive::select('*')->unvalidated();

        $bar = $this->output->createProgressBar($unvalidatedCommitsQuery->clone()->count());
        $bar->start();
      
        /**
         * @var Archive $commit
         */
        foreach ($unvalidatedCommitsQuery->cursor() as $commit) {
            $this->validate($commit);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return self::SUCCESS;
    }

    private function validate(Archive $commit): void
    {
        // Check if the archive model class still exists. If it doesn't exist anymore, ignore.
        if (! class_exists($commit->archivable_type)) {
            $this->error('>> The model '.$commit->archivable_type.' no longer exists.');

            return;
        }

        $archiveConnection = (new $commit->archivable_type)->getArchiveConnection();

        DB::beginTransaction();
        DB::connection($archiveConnection)->beginTransaction();

        try {
            $with = array_filter(
                $commit->archive_with,
                fn ($w) => method_exists($commit->archivable_type, $w)
            );

            $source = $commit->archivable_type::withoutGlobalScopes()
                ->where('id', $commit->archivable_id)
                ->with($with)
                ->select('*')
                ->first();

            if ($source && $source->validateArchive($commit)) {
                $commit->validated_at = now();
                $commit->save();

                $source->forceDelete();
            }

            DB::commit();
            DB::connection($archiveConnection)->commit();
        } catch (\Throwable $th) {
            $this->error($th->getMessage());
            DB::rollBack();
            DB::connection($archiveConnection)->rollBack();
        }
    }
}
