<?php

declare(strict_types = 1);

namespace MakerMill\HydraType;

/** Stable type identifiers for the output declared by a compile-time mutator. */
enum Type: string
{
    case Array = "array";
    case Bool = "bool";
    case Float = "float";
    case Int = "int";
    case Mixed = "mixed";
    case Object = "object";
    case String = "string";
}
