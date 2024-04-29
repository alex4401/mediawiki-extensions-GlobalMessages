<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\GlobalMessages\GlobalMessageRegistry;
use MediaWiki\MediaWikiServices;

return [
    GlobalMessageRegistry::SERVICE_NAME => static function (
        MediaWikiServices $services
    ): GlobalMessageRegistry {
        return new GlobalMessageRegistry(
            new ServiceOptions(
                GlobalMessageRegistry::CONSTRUCTOR_OPTIONS,
                $services->getMainConfig()
            ),
            $services->getDBLoadBalancerFactory(),
            $services->getMainWANObjectCache()
        );
    },
];
