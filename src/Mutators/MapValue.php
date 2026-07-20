<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Mutators;

use Attribute;
use InvalidArgumentException;
use MakerMill\HydraType\Compiler\PhpLiteral;
use MakerMill\HydraType\Interfaces\MutatorInterface;
use MakerMill\HydraType\Type;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class MapValue implements MutatorInterface
{
    /** @var non-empty-array<int|string, bool|float|int|string> */
    private array $map;

    /** @param array<int|string, bool|float|int|string> $map */
    public function __construct(array $map)
    {
        $this->map = self::validateMap($map);
    }

    /**
     * @param array<int|string, mixed> $map
     *
     * @return non-empty-array<int|string, bool|float|int|string>
     */
    private static function validateMap(array $map): array
    {
        if ($map === []) {
            throw new InvalidArgumentException('Value map must not be empty.');
        }

        $validatedMap = [];
        foreach ($map as $key => $value) {
            if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value)) {
                throw new InvalidArgumentException('Mapped values must be strings, integers, floats, or booleans.');
            }

            // Rebuilding the array preserves the runtime shape while proving the narrowed value union to static analysis.
            $validatedMap[$key] = $value;
        }

        return $validatedMap;
    }

    public function compile(string $inputExpression): string
    {
        $entries = [];
        foreach ($this->map as $input => $output) {
            $entries[] = PhpLiteral::value($input) . ' => ' . PhpLiteral::value($output);
        }
        $compiledEntries = implode(', ', $entries);

        // Strict match is fastest for textual maps. Numeric keys need PHP array-key coercion, so they use a guarded
        // inline lookup that also passes through values which cannot be array keys.
        if ($this->canUseMatch()) {
            return "match (\$hydraMutatorValue = {$inputExpression}) {"
                . $compiledEntries
                . ', default => $hydraMutatorValue}';
        }

        return "((is_int(\$hydraMutatorValue = {$inputExpression}) || is_string(\$hydraMutatorValue))"
            . " ? ([{$compiledEntries}][\$hydraMutatorValue] ?? \$hydraMutatorValue)"
            . ' : $hydraMutatorValue)';
    }

    public function outputType(): string
    {
        return Type::Mixed->value;
    }

    private function canUseMatch(): bool
    {
        foreach ($this->map as $input => $_) {
            if (!is_string($input) || $input === '') {
                return false;
            }
        }

        return true;
    }
}
