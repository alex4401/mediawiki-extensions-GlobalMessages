<?php
namespace MediaWiki\Extension\GlobalMessages;

use WANObjectCache;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Languages\LanguageConverterFactory;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionLookup;
use ObjectCache;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\IDatabase;
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
    public const LOCAL_CACHE_TTL = 1 * 60;
    public const SHARED_CACHE_TTL = 72 * 60 * 60;

    /** @var ServiceOptions */
    private ServiceOptions $options;
    /** @var LBFactory */
    private LBFactory $dbLoadBalancerFactory;
    /** @var WANObjectCache */
    private WANObjectCache $wanObjectCache;
    /** @var LanguageNameUtils */
    private LanguageNameUtils $languageNameUtils;
    /** @var LanguageFactory */
    private LanguageFactory $languageFactory;
    /** @var LanguageConverterFactory */
    private LanguageConverterFactory $languageConverterFactory;
    /** @var RevisionLookup */
    private RevisionLookup $revisionLookup;

    /** @var ?array */
    private ?array $processCache = null;

    public function __construct(
        ServiceOptions $options,
        LBFactory $dbLoadBalancerFactory,
        WANObjectCache $wanObjectCache,
        LanguageNameUtils $languageNameUtils,
        LanguageFactory $languageFactory,
        LanguageConverterFactory $languageConverterFactory,
        RevisionLookup $revisionLookup
    ) {
        $options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
        $this->options = $options;
        $this->dbLoadBalancerFactory = $dbLoadBalancerFactory;
        $this->wanObjectCache = $wanObjectCache;
        $this->languageNameUtils = $languageNameUtils;
        $this->languageFactory = $languageFactory;
        $this->languageConverterFactory = $languageConverterFactory;
        $this->revisionLookup = $revisionLookup;
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

        $langObj = $this->languageFactory->getLanguage( $language );
        $codes = [
            $language,
            $this->languageConverterFactory->getLanguageConverter( $langObj )->getDefaultVariant(),
            '*',
        ];

        $msg = null;
        foreach ( $codes as $code ) {
            if ( array_key_exists( $code, $this->processCache ) ) {
                if (
                    $msg = ( $this->processCache[$code][$msgName] ?? $this->processCache[$code]["$msgName/$code"]
                        ?? null )
                ) {
                    break;
                }
            }
        }

        return $msg;
    }

    private function getCacheKey(): string {
        return $this->wanObjectCache->makeGlobalKey(
            'global-messages',
            self::CACHE_GENERATION,
            $this->options->get( 'GlobalMessagesCentralWiki' )
        );
    }

    private function getDatabaseConnectionRef( int $index ): IDatabase {
        $centralWiki = $this->options->get( 'GlobalMessagesCentralWiki' );
        return $this->dbLoadBalancerFactory
            ->getMainLB( $centralWiki )
            ->getConnection( $index, [], $centralWiki );
    }

    private function loadMessages(): void {
        if ( $this->processCache !== null ) {
            return;
        }

        $key = $this->getCacheKey();

        $srvCache = ObjectCache::getLocalServerInstance( 'hash' );
        $this->processCache = $srvCache->getWithSetCallback(
            $key,
            self::LOCAL_CACHE_TTL,
            function () use ( $key ) {
                return $this->wanObjectCache->getWithSetCallback(
                    $key,
                    self::SHARED_CACHE_TTL,
                    function ( $old, &$ttl, &$setOpts ) {
                        $dbr = $this->getDatabaseConnectionRef( DB_REPLICA );
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
                            $results[$language][$row->gmc_name] = $row->gmc_text;
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

    public function purgeCache(): void {
        $srvCache = ObjectCache::getLocalServerInstance( 'hash' );

        $key = $this->getCacheKey();
        $this->processCache = null;
        $srvCache->delete( $key );
        $this->wanObjectCache->delete( $key );
    }

    public function createUpdater(): GlobalMessageUpdater {
        return new GlobalMessageUpdater(
            $this,
            $this->languageNameUtils,
            $this->revisionLookup,
            $this->getDatabaseConnectionRef( DB_PRIMARY )
        );
    }
}
