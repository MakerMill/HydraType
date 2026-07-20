<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Tests\Fixtures\ObjectShape;

final class VisibilityRecord
{
    public int $publicId = 0;
    protected string $protectedName = '';
    private bool $privateEnabled = false;

    /** @return array{publicId: int, protectedName: string, privateEnabled: bool} */
    public function values(): array
    {
        return [
            'publicId' => $this->publicId,
            'protectedName' => $this->protectedName,
            'privateEnabled' => $this->privateEnabled,
        ];
    }
}
