<?php

declare(strict_types = 1);

namespace MakerMill\HydraType;

/** @internal Compile-time classification of reflected property types. */
enum TypeConstruct: string
{
    case ClassType = 'class';
    case EnumType = 'enum';
    case IntersectionType = 'intersection';
    case ScalarType = 'scalar';
    case Undefined = 'undefined';
    case UnionType = 'union';
}
