<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

use MakerMill\HydraType\Mutators\JsonValue;

final class JsonValueRecord
{
    /**
     * @param array<string, mixed>      $settings
     * @param array<string, mixed>|null $optionalSettings
     */
    public function __construct(
        #[JsonValue(encodeFlags: JSON_UNESCAPED_SLASHES)]
        private array $settings,
        #[JsonValue(associative: false, depth: 64, decodeFlags: JSON_BIGINT_AS_STRING)]
        private object $metadata,
        #[JsonValue]
        private ?array $optionalSettings,
    ) {
    }

    /**
     * @return array{
     *     settings: array<string, mixed>,
     *     metadata: object,
     *     optionalSettings: array<string, mixed>|null
     * }
     */
    public function values(): array
    {
        return [
            'settings' => $this->settings,
            'metadata' => $this->metadata,
            'optionalSettings' => $this->optionalSettings,
        ];
    }
}
