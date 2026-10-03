<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * One run of a climate sync command (ADR-0016). The command opens it with begin() and closes it
 * with complete() or fail(); the climate data page reads the latest run of each source.
 */
class SyncRun extends Model
{
    use Prunable;

    public const OK = 'ok';

    public const EMPTY = 'empty';

    public const ERROR = 'error';

    /** Days a run is kept. */
    public const KEEP_DAYS = 30;

    public $timestamps = false;

    protected $fillable = ['source', 'started_at', 'finished_at', 'result', 'received', 'created', 'message'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'received' => 'integer',
            'created' => 'integer',
        ];
    }

    public static function begin(string $source): self
    {
        return self::query()->create(['source' => $source, 'started_at' => now()]);
    }

    /**
     * @param  int  $created  New or updated rows; none means the run found nothing new.
     */
    public function complete(int $created, ?int $received = null, ?string $message = null): void
    {
        $this->update([
            'finished_at' => now(),
            'result' => $created > 0 ? self::OK : self::EMPTY,
            'created' => $created,
            'received' => $received,
            'message' => $message,
        ]);
    }

    /**
     * @param  string  $message  Without API keys: strip them first (AmbientWeatherService::withoutKeys()).
     */
    public function fail(string $message): void
    {
        $this->update([
            'finished_at' => now(),
            'result' => self::ERROR,
            'message' => mb_substr($message, 0, 500),
        ]);
    }

    public function prunable(): Builder
    {
        return self::query()->where('started_at', '<', now()->subDays(self::KEEP_DAYS));
    }
}
