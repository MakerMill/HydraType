<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

use MakerMill\HydraType\Mutators\JsonDecode;

final class JsonDecodedRecord
{
    /**
     * @param array<mixed>      $settings
     * @param array<mixed>      $largeNumber
     * @param array<mixed>|null $optionalSettings
     */
    public function __construct(
        #[JsonDecode]
        private array $settings,
        #[JsonDecode(associative: false, depth: 64)]
        private object $metadata,
        #[JsonDecode(flags: JSON_BIGINT_AS_STRING)]
        private array $largeNumber,
        #[JsonDecode]
        private ?array $optionalSettings,
    ) {
    }

    /** @return array<string, mixed> */
    public function values(): array
    {
        return [
            'settings' => $this->settings,
            'metadata' => $this->metadata,
            'largeNumber' => $this->largeNumber,
            'optionalSettings' => $this->optionalSettings,
        ];
    }
}
