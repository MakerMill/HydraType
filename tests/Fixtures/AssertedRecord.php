<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

use MakerMill\HydraType\Assertions\NotEmpty;
use MakerMill\HydraType\Mutators\Trim;
use MakerMill\HydraType\Rules\Optional;

final class AssertedRecord
{
    #[Trim]
    #[NotEmpty]
    private string $name;

    /** @var list<string> */
    #[NotEmpty]
    private array $tags;

    #[NotEmpty]
    private ?string $note;

    #[Optional]
    #[NotEmpty]
    private string $label = 'fallback';

    /**
     * @param list<string> $tags
     */
    public function __construct(
        string $name,
        array $tags,
        ?string $note,
        string $label = 'fallback',
    ) {
        $this->name = $name;
        $this->tags = $tags;
        $this->note = $note;
        $this->label = $label;
    }

    /** @return array{name: string, tags: list<string>, note: string|null, label: string} */
    public function values(): array
    {
        return [
            'name' => $this->name,
            'tags' => $this->tags,
            'note' => $this->note,
            'label' => $this->label,
        ];
    }
}
