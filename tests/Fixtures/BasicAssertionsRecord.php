<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures;

use MakerMill\HydraType\Assertions\Between;
use MakerMill\HydraType\Assertions\Contains;
use MakerMill\HydraType\Assertions\EndsWith;
use MakerMill\HydraType\Assertions\Equal;
use MakerMill\HydraType\Assertions\GreaterThan;
use MakerMill\HydraType\Assertions\GreaterThanOrEqual;
use MakerMill\HydraType\Assertions\LessThan;
use MakerMill\HydraType\Assertions\LessThanOrEqual;
use MakerMill\HydraType\Assertions\MaxItems;
use MakerMill\HydraType\Assertions\MaxLength;
use MakerMill\HydraType\Assertions\MaxValue;
use MakerMill\HydraType\Assertions\MinItems;
use MakerMill\HydraType\Assertions\MinLength;
use MakerMill\HydraType\Assertions\MinValue;
use MakerMill\HydraType\Assertions\MatchesPattern;
use MakerMill\HydraType\Assertions\Negative;
use MakerMill\HydraType\Assertions\NonNegative;
use MakerMill\HydraType\Assertions\NonPositive;
use MakerMill\HydraType\Assertions\NotBlank;
use MakerMill\HydraType\Assertions\NotEqual;
use MakerMill\HydraType\Assertions\NotOneOf;
use MakerMill\HydraType\Assertions\OneOf;
use MakerMill\HydraType\Assertions\Positive;
use MakerMill\HydraType\Assertions\StartsWith;

final class BasicAssertionsRecord
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        #[Between(18, 120)]
        #[GreaterThan(17)]
        #[GreaterThanOrEqual(18)]
        #[LessThan(121)]
        #[LessThanOrEqual(120)]
        #[MinValue(18)]
        #[MaxValue(120)]
        #[Positive]
        #[NonNegative]
        #[NotEqual(0)]
        private int $age,
        #[MinLength(2)]
        #[MaxLength(4)]
        private string $name,
        #[MinItems(1)]
        #[MaxItems(2)]
        private array $tags,
        #[Negative]
        #[NonPositive]
        private int $balance,
        #[Equal(7)]
        private int $code,
        #[Between(2.0, 3.0)]
        #[Equal(2.5)]
        #[NotEqual(2.0)]
        #[GreaterThan(2.0)]
        #[GreaterThanOrEqual(2.5)]
        #[LessThan(3.0)]
        #[LessThanOrEqual(2.5)]
        #[MinValue(2.5)]
        #[MaxValue(2.5)]
        private float $score,
        #[OneOf(['active', 'pending', 'deleted'])]
        #[NotOneOf(['deleted', 'blocked'])]
        private string $status,
        #[StartsWith('user_')]
        #[Contains('account')]
        #[EndsWith('_id')]
        private string $identifier,
        #[NotBlank]
        private string $label,
        #[MatchesPattern('/^[A-Z]{2}-\d{3}$/D')]
        private string $reference,
    ) {
    }

    /**
     * @return array{
     *     age: int,
     *     name: string,
     *     tags: list<string>,
     *     balance: int,
     *     code: int,
     *     score: float,
     *     status: string,
     *     identifier: string,
     *     label: string,
     *     reference: string
     * }
     */
    public function values(): array
    {
        return [
            'age' => $this->age,
            'name' => $this->name,
            'tags' => $this->tags,
            'balance' => $this->balance,
            'code' => $this->code,
            'score' => $this->score,
            'status' => $this->status,
            'identifier' => $this->identifier,
            'label' => $this->label,
            'reference' => $this->reference,
        ];
    }
}
