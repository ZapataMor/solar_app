<?php

namespace App\Services;

use App\Models\SolarProject;
use App\Models\SolarProjectAiMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SolarProjectAiHistoryService
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function record(SolarProject $solarProject, array $attributes): SolarProjectAiMessage
    {
        if (! $this->tableExists()) {
            return new SolarProjectAiMessage([
                ...$attributes,
                'solar_project_id' => $solarProject->id,
                'sequence' => 0,
                'generated_at' => $attributes['generated_at'] ?? now(),
            ]);
        }

        return DB::transaction(function () use ($solarProject, $attributes): SolarProjectAiMessage {
            $type = (string) ($attributes['type'] ?? 'recommendation');
            $lastSequence = SolarProjectAiMessage::query()
                ->where('solar_project_id', $solarProject->id)
                ->where('type', $type)
                ->lockForUpdate()
                ->max('sequence');

            return $solarProject->aiMessages()->create([
                'type' => $type,
                'role' => (string) ($attributes['role'] ?? 'assistant'),
                'sequence' => ((int) $lastSequence) + 1,
                'focus' => $attributes['focus'] ?? null,
                'focus_label' => $attributes['focus_label'] ?? null,
                'source' => $attributes['source'] ?? null,
                'title' => $attributes['title'] ?? null,
                'message' => $attributes['message'] ?? null,
                'summary' => $attributes['summary'] ?? null,
                'metadata' => $attributes['metadata'] ?? null,
                'generated_at' => $attributes['generated_at'] ?? now(),
            ]);
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recommendationHistory(SolarProject $solarProject, int $limit = 80): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        return $this->messages($solarProject, 'recommendation', $limit)
            ->filter(fn (SolarProjectAiMessage $message) => $message->role !== 'assistant' || $this->isUsefulMessage($message->message))
            ->map(fn (SolarProjectAiMessage $message) => $this->serializeRecommendationMessage($message))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function predictionHistory(SolarProject $solarProject, int $limit = 20): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        return $this->messages($solarProject, 'prediction', $limit)
            ->map(fn (SolarProjectAiMessage $message) => $this->serializePredictionMessage($message))
            ->values()
            ->all();
    }

    private function messages(SolarProject $solarProject, string $type, int $limit): Collection
    {
        return $solarProject->aiMessages()
            ->where('type', $type)
            ->orderByDesc('sequence')
            ->limit($limit)
            ->get()
            ->sortBy('sequence')
            ->values();
    }

    private function tableExists(): bool
    {
        return Schema::hasTable('solar_project_ai_messages');
    }

    private function isUsefulMessage(?string $message): bool
    {
        $trimmed = trim((string) $message);
        if ($trimmed === '') {
            return false;
        }

        $normalized = strtolower(str_replace([' ', '-'], '_', $trimmed));
        $placeholders = [
            'title',
            'text',
            'message',
            'summary',
            'status',
            'diagnostic',
            'diagnostico',
            'action',
            'accion',
            'impact',
            'impacto',
            'severity',
            'type',
            'alert',
            'description',
            'data_source',
            'date_context',
        ];

        return ! in_array($normalized, $placeholders, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeRecommendationMessage(SolarProjectAiMessage $message): array
    {
        return [
            'id' => 'ai-history-'.$message->id,
            'role' => $message->role,
            'content' => (string) $message->message,
            'streaming' => false,
            'source' => $message->source,
            'focus' => $message->focus,
            'focus_label' => $message->focus_label,
            'created_at' => $message->generated_at?->toIso8601String() ?? $message->created_at?->toIso8601String(),
            'created_label' => ($message->generated_at ?? $message->created_at)?->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializePredictionMessage(SolarProjectAiMessage $message): array
    {
        $metadata = is_array($message->metadata) ? $message->metadata : [];

        return [
            'id' => $message->id,
            'source' => $message->source,
            'title' => $message->title,
            'prediction' => (string) $message->message,
            'temperature_outlook' => (string) ($metadata['temperature_outlook'] ?? ''),
            'solar_window' => (string) ($metadata['solar_window'] ?? ''),
            'actions' => array_values(array_filter($metadata['actions'] ?? [], 'is_string')),
            'confidence' => (string) ($metadata['confidence'] ?? 'media'),
            'error' => $metadata['error'] ?? null,
            'generated_at' => $message->generated_at?->toIso8601String() ?? $message->created_at?->toIso8601String(),
            'generated_label' => ($message->generated_at ?? $message->created_at)?->format('d/m/Y H:i'),
        ];
    }
}
