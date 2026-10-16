<?php

namespace App\Console\Commands;

use App\Support\Curriculum\ItemImages;
use Illuminate\Console\Command;

class PruneCurriculumImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:prune-curriculum-images {--dry-run : List orphaned files without deleting them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes curriculum image files that no image item references and that are older than 24 hours.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = ItemImages::disk();
        $cutoff = now()->subDay()->getTimestamp();
        $count = 0;

        foreach ($disk->files(ItemImages::DIRECTORY) as $file) {
            // ItemImages::isReferenced uses withDrafts(): DraftScope is active in console and
            // would otherwise hide draft items, so a file used only by a draft would be deleted.
            if (ItemImages::isReferenced($file)) {
                continue;
            }

            // A recent file may belong to a save that is still in progress or was halted.
            if ($disk->lastModified($file) > $cutoff) {
                continue;
            }

            $count++;

            if ($dryRun) {
                $this->line("Would delete {$file}");

                continue;
            }

            $disk->delete($file);
            $this->line("Deleted {$file}");
        }

        $this->info($dryRun
            ? "{$count} orphaned image(s) would be deleted."
            : "{$count} orphaned image(s) deleted.");

        return self::SUCCESS;
    }
}
