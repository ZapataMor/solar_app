<?php

use App\Domain\Solar\AnalysisPeriod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Projects used to be created with a one-day analysis period (the creation day), and a day with
 * only its morning measured sized thousands of panels. Periods shorter than a month take the last
 * three months up to their end date, and no period ends after today (AnalysisPeriod).
 *
 * The widened ones are marked as changed, so their calculation shows up as outdated.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $today = now(config('app.display_timezone', config('app.timezone')))->toDateString();

        DB::table('solar_projects')
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->get(['id', 'start_date', 'end_date'])
            ->each(function (object $project) use ($today): void {
                // Dates compare as Y-m-d text; the column may also carry a midnight time.
                $start = substr((string) $project->start_date, 0, 10);
                $end = min(substr((string) $project->end_date, 0, 10), $today);
                $changes = [];

                if ($end !== substr((string) $project->end_date, 0, 10)) {
                    $changes['end_date'] = "{$end} 00:00:00";
                }

                if (! AnalysisPeriod::isLongEnough(new DateTimeImmutable($start), new DateTimeImmutable($end))) {
                    $changes['start_date'] = AnalysisPeriod::defaultStart(new DateTimeImmutable($end))->format('Y-m-d 00:00:00');
                    $changes['updated_at'] = now();
                }

                if ($changes !== []) {
                    DB::table('solar_projects')->where('id', $project->id)->update($changes);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The old periods are not kept: they were what sized thousands of panels.
    }
};
