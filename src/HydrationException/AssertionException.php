<?php

declare(strict_types=1);

namespace MakerMill\HydraType\HydrationException;

final class AssertionException extends HydrationException
{
    /**
     * @param class-string $targetClass
     * @param class-string $assertionClass
     */
    private function __construct(
        private readonly string $targetClass,
        private readonly string $propertyName,
        private readonly string $assertionClass,
        private readonly string $reason,
    ) {
        parent::__construct(
            "Hydration assertion failed for property '{$propertyName}' in class '{$targetClass}': {$reason}",
        );
    }

    /**
     * @param class-string $targetClass
     * @param class-string $assertionClass
     */
    public static function forProperty(
        string $targetClass,
        string $propertyName,
        string $assertionClass,
        string $reason,
    ): self {
        return new self($targetClass, $propertyName, $assertionClass, $reason);
    }

    /** @return class-string */
    public function targetClass(): string
    {
        return $this->targetClass;
    }

    public function propertyName(): string
    {
        return $this->propertyName;
    }

    /** @return class-string */
    public function assertionClass(): string
    {
        return $this->assertionClass;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
