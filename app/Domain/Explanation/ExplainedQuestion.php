<?php

namespace App\Domain\Explanation;

/**
 * One question about the project, answered in plain language.
 */
final readonly class ExplainedQuestion
{
    public const TONE_GOOD = 'good';

    public const TONE_NEUTRAL = 'neutral';

    public const TONE_WARNING = 'warning';

    /**
     * @param  list<string>  $paragraphs  Short sentences, no jargon.
     */
    public function __construct(
        public string $key,
        public string $question,
        public string $headline,
        public string $caption,
        public array $paragraphs,
        public string $tone = self::TONE_NEUTRAL,
    ) {}
}
