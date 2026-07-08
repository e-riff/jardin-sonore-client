<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Doctrine\DBAL\Platforms\MySQL84Platform;
use LongitudeOne\Spatial\DBAL\Types\Geometry\MultiPolygonType;
use LongitudeOne\Spatial\DBAL\Types\Geometry\PointType;
use LongitudeOne\Spatial\DBAL\Types\Geometry\PolygonType;
use LongitudeOne\Spatial\ORM\Query\AST\Functions\MySql\SpDistanceSphere;
use LongitudeOne\Spatial\ORM\Query\AST\Functions\MySql\SpMbrContains;
use LongitudeOne\Spatial\ORM\Query\AST\Functions\MySql\SpMbrIntersects;
use LongitudeOne\Spatial\ORM\Query\AST\Functions\MySql\SpMbrWithin;
use LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StAsText;
use LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StContains;
use LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StDistance;
use LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StGeomFromText;
use LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StIntersects;
use LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StWithin;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonContains;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonExtract;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonLength;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonSearch;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonUnquote;

return App::config([
    'doctrine' => [
        'dbal' => [
            'url' => '%env(resolve:DATABASE_URL)%',
            'types' => [
                'geometry_point' => PointType::class,
                'geometry_polygon' => PolygonType::class,
                'geometry_multipolygon' => MultiPolygonType::class,
            ],
            'profiling_collect_backtrace' => '%kernel.debug%',
        ],
        'orm' => [
            'validate_xml_mapping' => true,
            'naming_strategy' => 'doctrine.orm.naming_strategy.underscore',
            'identity_generation_preferences' => [
                MySQL84Platform::class => 'identity',
            ],
            'auto_mapping' => false,
            'mappings' => [
                'AppInfrastructureDoctrineEntity' => [
                    'type' => 'php',
                    'is_bundle' => false,
                    'dir' => '%kernel.project_dir%/src/Infrastructure/Doctrine/Mapping',
                    'prefix' => 'App\\Infrastructure\\Doctrine\\Entity',
                    'alias' => 'AppInfrastructureDoctrineEntity',
                ],
            ],
            'dql' => [
                'string_functions' => [
                    'JSON_EXTRACT' => JsonExtract::class,
                    'JSON_UNQUOTE' => JsonUnquote::class,
                    'JSON_SEARCH' => JsonSearch::class,
                    'ST_ASTEXT' => StAsText::class,
                    'ST_GEOMFROMTEXT' => StGeomFromText::class,
                ],
                'numeric_functions' => [
                    'JSON_CONTAINS' => JsonContains::class,
                    'JSON_LENGTH' => JsonLength::class,
                    'ST_DISTANCE' => StDistance::class,
                    'ST_DISTANCE_SPHERE' => SpDistanceSphere::class,
                    'ST_CONTAINS' => StContains::class,
                    'ST_INTERSECTS' => StIntersects::class,
                    'ST_WITHIN' => StWithin::class,
                    'ST_MBRCONTAINS' => SpMbrContains::class,
                    'ST_MBRINTERSECTS' => SpMbrIntersects::class,
                    'ST_MBRWITHIN' => SpMbrWithin::class,
                ],
                'datetime_functions' => [],
            ],
        ],
    ],
    'when@test' => [
        'doctrine' => [
            'dbal' => [
                'dbname_suffix' => '_test%env(default::TEST_TOKEN)%',
            ],
        ],
    ],
    'when@prod' => [
        'framework' => [
            'cache' => [
                'pools' => [
                    'doctrine.result_cache_pool' => [
                        'adapter' => 'cache.app',
                    ],
                    'doctrine.system_cache_pool' => [
                        'adapter' => 'cache.system',
                    ],
                ],
            ],
        ],
        'doctrine' => [
            'orm' => [
                'query_cache_driver' => [
                    'type' => 'pool',
                    'pool' => 'doctrine.system_cache_pool',
                ],
                'result_cache_driver' => [
                    'type' => 'pool',
                    'pool' => 'doctrine.result_cache_pool',
                ],
            ],
        ],
    ],
]);
