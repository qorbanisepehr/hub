<?php

namespace App\Support;

use App\Domains\Cv\Models\Cv;
use App\Domains\Employee\Models\Employee;
use App\Domains\Questionnaire\Models\Questionnaire;

/**
 * Single source of truth for the documentable "route type" ↔ entity-class
 * mapping (employee / questionnaire / cv). Consumers needing to resolve a
 * route-type string to a model class (e.g. generic document controllers) or a
 * model class back to its route-type string (the DocumentableTrait) derive from
 * this one map instead of re-declaring their own.
 */
final class DocumentRouteType
{
    /** @var array<string, class-string> */
    private const MAP = [
        'employee' => Employee::class,
        'questionnaire' => Questionnaire::class,
        'cv' => Cv::class,
    ];

    /** @return list<string> */
    public static function routeTypes(): array
    {
        return array_keys(self::MAP);
    }

    /** @return class-string|null */
    public static function classFor(string $routeType): ?string
    {
        return self::MAP[$routeType] ?? null;
    }

    public static function routeTypeFor(string $class): ?string
    {
        $routeType = array_search($class, self::MAP, true);

        return $routeType === false ? null : $routeType;
    }
}
