<?php

declare(strict_types=1);

use MakerMill\HydraType\CacheFingerprint;
use MakerMill\HydraType\ClassDescriptor;
use MakerMill\HydraType\Configuration;

it('invalidates generated code when a compiled dependency changes', function () {
    [$className, $sourceFiles] = createFingerprintFixture();
    $namespace = 'MakerMill\\HydraType\\Tests\\Fingerprint' . bin2hex(random_bytes(6));
    $directory = testHydratorDirectory() . '/fingerprint-cache-' . bin2hex(random_bytes(6));
    $configuration = new Configuration($namespace, $directory);
    $configuration->getHydratorFactory()->create($className);

    $generatedFile = (new ClassDescriptor($className, $configuration))->getHydratorFilePath();
    expect(CacheFingerprint::matches($generatedFile))->toBeTrue();

    foreach ($sourceFiles as [$sourceFile, $marker]) {
        $previousHeader = generatedFingerprintHeader($generatedFile);
        replaceSourceMarker($sourceFile, $marker . '-a', $marker . '-b');

        expect(CacheFingerprint::matches($generatedFile))->toBeFalse();

        (new Configuration($namespace, $directory))->getHydratorFactory()->create($className);

        expect(CacheFingerprint::matches($generatedFile))->toBeTrue()
            ->and(generatedFingerprintHeader($generatedFile))->not->toBe($previousHeader);
    }
});

it('regenerates cache entries with malformed fingerprint metadata', function () {
    [$className] = createFingerprintFixture();
    $namespace = 'MakerMill\\HydraType\\Tests\\MalformedFingerprint' . bin2hex(random_bytes(6));
    $directory = testHydratorDirectory() . '/malformed-fingerprint-' . bin2hex(random_bytes(6));
    $configuration = new Configuration($namespace, $directory);
    $configuration->getHydratorFactory()->create($className);

    $generatedFile = (new ClassDescriptor($className, $configuration))->getHydratorFilePath();
    $generatedCode = readGeneratedFile($generatedFile);
    $malformedCode = preg_replace(
        '/\A<\?php\R[^\r\n]*/',
        "<?php\n// malformed-cache-metadata",
        $generatedCode,
        1,
    );
    if ($malformedCode === null || file_put_contents($generatedFile, $malformedCode) === false) {
        throw new RuntimeException('Unable to prepare malformed cache metadata.');
    }

    expect(CacheFingerprint::matches($generatedFile))->toBeFalse();

    (new Configuration($namespace, $directory))->getHydratorFactory()->create($className);

    expect(CacheFingerprint::matches($generatedFile))->toBeTrue();
});

/**
 * @return array{
 *     class-string,
 *     list<array{string, string}>
 * }
 */
function createFingerprintFixture(): array
{
    $suffix = bin2hex(random_bytes(6));
    $namespace = "MakerMill\\HydraType\\Tests\\DynamicFingerprint{$suffix}";
    $directory = testHydratorDirectory() . '/fingerprint-source-' . $suffix;
    if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create fingerprint fixture directory.');
    }

    $mutatorFile = $directory . '/CacheMutator.php';
    $extractionMutatorFile = $directory . '/CacheExtractionMutator.php';
    $enumFile = $directory . '/RecordState.php';
    $parentFile = $directory . '/ParentRecord.php';
    $traitFile = $directory . '/RecordTrait.php';
    $targetFile = $directory . '/TargetRecord.php';

    writeFingerprintFixtureFile($mutatorFile, <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use Attribute;
        use MakerMill\HydraType\Interfaces\MutatorInterface;

        // mutator-version-a
        #[Attribute(Attribute::TARGET_PROPERTY)]
        final readonly class CacheMutator implements MutatorInterface
        {
            public function compile(string \$inputExpression): string
            {
                return "(string) {\$inputExpression}";
            }

            public function outputType(): string
            {
                return 'string';
            }
        }
        PHP);
    writeFingerprintFixtureFile($enumFile, <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        // enum-version-a
        enum RecordState: int
        {
            case Active = 1;
        }
        PHP);
    writeFingerprintFixtureFile($extractionMutatorFile, <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use Attribute;
        use MakerMill\HydraType\Interfaces\ExtractionMutatorInterface;

        // extraction-mutator-version-a
        #[Attribute(Attribute::TARGET_PROPERTY)]
        final readonly class CacheExtractionMutator implements ExtractionMutatorInterface
        {
            public function compileExtraction(string \$inputExpression): string
            {
                return \$inputExpression;
            }
        }
        PHP);
    writeFingerprintFixtureFile($parentFile, <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        // parent-version-a
        class ParentRecord
        {
            protected int \$sequence;
        }
        PHP);
    writeFingerprintFixtureFile($traitFile, <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        // trait-version-a
        trait RecordTrait
        {
            private bool \$active;
        }
        PHP);
    writeFingerprintFixtureFile($targetFile, <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        // target-version-a
        final class TargetRecord extends ParentRecord
        {
            use RecordTrait;

            public function __construct(
                #[CacheMutator]
                #[CacheExtractionMutator]
                private string \$value,
                private RecordState \$state,
            ) {
            }
        }
        PHP);

    require_once $mutatorFile;
    require_once $extractionMutatorFile;
    require_once $enumFile;
    require_once $parentFile;
    require_once $traitFile;
    require_once $targetFile;

    $className = $namespace . '\\TargetRecord';
    if (!class_exists($className)) {
        throw new RuntimeException('Unable to load fingerprint fixture class.');
    }

    return [
        $className,
        [
            [$targetFile, 'target-version'],
            [$parentFile, 'parent-version'],
            [$traitFile, 'trait-version'],
            [$mutatorFile, 'mutator-version'],
            [$extractionMutatorFile, 'extraction-mutator-version'],
            [$enumFile, 'enum-version'],
        ],
    ];
}

function writeFingerprintFixtureFile(string $fileName, string $contents): void
{
    if (file_put_contents($fileName, $contents) === false) {
        throw new RuntimeException("Unable to write fingerprint fixture '{$fileName}'.");
    }
}

function replaceSourceMarker(string $fileName, string $from, string $to): void
{
    $modifiedTime = filemtime($fileName);
    $source = file_get_contents($fileName);
    if ($modifiedTime === false || $source === false) {
        throw new RuntimeException("Unable to read fingerprint fixture '{$fileName}'.");
    }

    $updatedSource = str_replace($from, $to, $source, $replacementCount);
    if (
        $replacementCount !== 1
        || file_put_contents($fileName, $updatedSource) === false
        || !touch($fileName, $modifiedTime)
    ) {
        throw new RuntimeException("Unable to update fingerprint fixture '{$fileName}'.");
    }
    clearstatcache(true, $fileName);

    if (filemtime($fileName) !== $modifiedTime) {
        throw new RuntimeException("Unable to preserve fingerprint fixture timestamp '{$fileName}'.");
    }
}

function generatedFingerprintHeader(string $generatedFile): string
{
    $lines = file($generatedFile, FILE_IGNORE_NEW_LINES);
    if ($lines === false || !isset($lines[1])) {
        throw new RuntimeException("Unable to read fingerprint header from '{$generatedFile}'.");
    }

    return $lines[1];
}
