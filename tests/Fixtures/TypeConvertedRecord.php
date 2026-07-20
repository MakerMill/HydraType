<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

final class TypeConvertedRecord
{
    private static string $metadata = 'unchanged';

    /**
     * @param array<mixed> $tags
     * @param mixed        $untyped
     */
    public function __construct(
        private int $id,
        private float $score,
        private string $name,
        private bool $enabled,
        private array $tags,
        private object $settings,
        private mixed $payload,
        private $untyped,
        private AccessLevel $accessLevel,
    ) {
    }

    /** @return array<string, mixed> */
    public function values(): array
    {
        return [
            'id' => $this->id,
            'score' => $this->score,
            'name' => $this->name,
            'enabled' => $this->enabled,
            'tags' => $this->tags,
            'settings' => $this->settings,
            'payload' => $this->payload,
            'untyped' => $this->untyped,
            'accessLevel' => $this->accessLevel,
        ];
    }

    public static function metadata(): string
    {
        return self::$metadata;
    }
}
