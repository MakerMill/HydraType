<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Support;

use Closure;
use InvalidArgumentException;

final readonly class BenchmarkCase
{
    /**
     * The runner measures only the operation closure. Warm-up and verification
     * stay outside the timed interval so shared infrastructure cannot alter the
     * operation being compared.
     *
     * @param Closure(): mixed      $operation
     * @param Closure(mixed): void $verify
     * @param null|Closure(): mixed $warmup
     * @param null|Closure(): void  $prepare
     */
    public function __construct(
        public Closure $operation,
        public int $operations,
        public Closure $verify,
        public ?Closure $warmup = null,
        public ?Closure $prepare = null,
    ) {
        if ($this->operations < 1) {
            throw new InvalidArgumentException('A benchmark case must complete at least one operation.');
        }
    }
}
