<?php

declare(strict_types=1);

namespace MakerMill\HydraType\Benchmarks\Fixtures;

use Closure;
use InvalidArgumentException;
use ReflectionClass;

final class LazyWriterHydrator implements WriterLifecycleHydrator
{
    /** @var ReflectionClass<WriterLifecycleTarget> */
    private ReflectionClass $reflectionClass;
    private ?Closure $camelWriter = null;
    private ?Closure $snakeWriter = null;

    public function __construct()
    {
        $this->reflectionClass = new ReflectionClass(WriterLifecycleTarget::class);
    }

    public function hydrate(array $data): WriterLifecycleTarget
    {
        $writer = $this->writerFor($data);
        $object = $this->reflectionClass->newInstanceWithoutConstructor();
        $writer($object, $data);

        return $object;
    }

    public function hydrateMany(array $dataSet): array
    {
        if ($dataSet === []) {
            throw new InvalidArgumentException('Cannot hydrate an empty data set');
        }

        $firstData = reset($dataSet);
        $writer = $this->writerFor($firstData);
        $objects = [];
        foreach ($dataSet as $data) {
            $object = $this->reflectionClass->newInstanceWithoutConstructor();
            $writer($object, $data);
            $objects[] = $object;
        }

        return $objects;
    }

    private function camelWriter(): Closure
    {
        return $this->camelWriter ??= Closure::bind(
            static function (WriterLifecycleTarget $object, array $data): void {
                $object->id = $data['id'];
                $object->userName = $data['userName'];
                $object->password = $data['password'];
                $object->type = $data['type'];
                $object->active = $data['active'];
            },
            null,
            WriterLifecycleTarget::class
        );
    }

    private function snakeWriter(): Closure
    {
        return $this->snakeWriter ??= Closure::bind(
            static function (WriterLifecycleTarget $object, array $data): void {
                $object->id = $data['id'];
                $object->userName = $data['user_name'];
                $object->password = $data['password'];
                $object->type = $data['type'];
                $object->active = $data['active'];
            },
            null,
            WriterLifecycleTarget::class
        );
    }

    /** @param array<string, mixed> $data */
    private function writerFor(array $data): Closure
    {
        if (array_key_exists('userName', $data)) {
            return $this->camelWriter();
        }
        if (array_key_exists('user_name', $data)) {
            return $this->snakeWriter();
        }

        return $this->camelWriter();
    }
}
