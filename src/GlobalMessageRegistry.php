<?php
namespace MediaWiki\Extension\GlobalMessages;

use WANObjectCache;
use MediaWiki\Config\ServiceOptions;

class GlobalMessageRegistry {
    public const SERVICE_NAME = 'GlobalMessages.Registry';

    /**
     * @internal Use only in ServiceWiring
     */
    public const CONSTRUCTOR_OPTIONS = [];

    public const CACHE_GENERATION = 1;
    public const CACHE_TTL = 72 * 60 * 60;

    /** @var ServiceOptions */
    private ServiceOptions $options;

    /** @var WANObjectCache */
    private WANObjectCache $wanObjectCache;

    public function __construct(
        ServiceOptions $options,
        WANObjectCache $wanObjectCache
    ) {
        $options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
        $this->options = $options;
        $this->wanObjectCache = $wanObjectCache;
    }

    /**
     * @param string $msgName Message ID
     * @param string $language Language code
     * @return ?string Message contents, if successful
     */
    public function resolve( string $msgName, string $language ): ?string {
        return null;
    }
}
