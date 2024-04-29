<?php
namespace MediaWiki\Extension\GlobalMessages;

use WANObjectCache;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\MediaWikiServices;
use ObjectCache;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\LBFactory;
use Wikimedia\Rdbms\LoadBalancer;

class GlobalMessageRegistry {
    public const SERVICE_NAME = 'GlobalMessages.Registry';

    /**
     * @internal Use only in ServiceWiring
     */
    public const CONSTRUCTOR_OPTIONS = [
        'GlobalMessagesCentralWiki',
        'GlobalMessagesReadFromDb',
    ];

    public const CACHE_GENERATION = 1;
    public const LOCAL_CACHE_TTL = 10 * 60;
    public const SHARED_CACHE_TTL = 72 * 60 * 60;

    /** @var ServiceOptions */
    private ServiceOptions $options;

    /** @var LBFactory */
    private LBFactory $dbLoadBalancerFactory;

    /** @var WANObjectCache */
    private WANObjectCache $wanObjectCache;

    /** @var ?array */
    private ?array $processCache = null;

    public function __construct(
        ServiceOptions $options,
        LBFactory $dbLoadBalancerFactory,
        WANObjectCache $wanObjectCache
    ) {
        $options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
        $this->options = $options;
        $this->dbLoadBalancerFactory = $dbLoadBalancerFactory;
        $this->wanObjectCache = $wanObjectCache;
    }

    /**
     * @param string $msgName Message ID
     * @param string $language Language code
     * @return ?string Message contents, if successful
     */
    public function resolve( string $msgName, string $language ): ?string {
        if ( $language === 'qqx' || !$this->options->get( 'GlobalMessagesReadFromDb' ) ) {
            return null;
        }

        $this->loadMessages();

        return null;
    }

    private function loadMessages(): void {
        if ( $this->processCache !== null ) {
            return;
        }

        $centralWiki = $this->options->get( 'GlobalMessagesCentralWiki' );
        $key = $this->wanObjectCache->makeGlobalKey( 'global-messages', self::CACHE_GENERATION, $centralWiki );

        $srvCache = ObjectCache::getLocalServerInstance( 'hash' );
        $this->processCache = $srvCache->getWithSetCallback(
            $key,
            self::LOCAL_CACHE_TTL,
            function () use ( $key, $centralWiki ) {
                return $this->wanObjectCache->getWithSetCallback(
                    $key,
                    self::SHARED_CACHE_TTL,
                    function ( $old, &$ttl, &$setOpts ) use ( $centralWiki ) {
                        $dbr = $this->dbLoadBalancerFactory
                            ->getMainLB( $centralWiki )
                            ->getConnection( DB_REPLICA, [], $centralWiki );
                        $setOpts += Database::getCacheSetOptions( $dbr );

                        $rows = $dbr->newSelectQueryBuilder()
                            ->select( [
                                'gmc_name',
                                'gmc_lang',
                                'gmc_text',
                            ] )
                            ->from( 'global_messages_cache' )
                            ->caller( __METHOD__ )
                            ->fetchResultSet();

                        $results = [
                            '*' => [],
                        ];

                        foreach ( $rows as $row ) {
                            $language = $row->gmc_lang;
                            if ( !array_key_exists( $language, $results ) ) {
                                $results[$language] = [];
                            }
                            $results[$row->gmc_name] = $row->gmc_text;
                        }

                        return $results;
                    }, [
                        // Avoid database stampede
                        'lockTSE' => 300,
                    ]
                );
            }
        );
    }
}
