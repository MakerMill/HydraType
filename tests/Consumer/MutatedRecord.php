<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Consumer;

use MakerMill\HydraType\Rules\Optional;
use MakerMill\HydraType\Tests\Consumer\Mutators\Base64Value;
use MakerMill\HydraType\Tests\Consumer\Mutators\ReverseString;

final class MutatedRecord
{
    #[Base64Value]
    #[ReverseString]
    private string $secret;

    #[Base64Value]
    private ?string $note = null;

    #[Optional]
    #[Base64Value]
    private string $label = 'fallback';

    public function __construct(
        string $secret,
        ?string $note = null,
        string $label = 'fallback',
    ) {
        $this->secret = $secret;
        $this->note = $note;
        $this->label = $label;
    }

    /** @return array{secret: string, note: string|null, label: string} */
    public function values(): array
    {
        return [
            'secret' => $this->secret,
            'note' => $this->note,
            'label' => $this->label,
        ];
    }
}
