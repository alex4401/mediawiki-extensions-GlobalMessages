<?php
namespace MediaWiki\Extension\GlobalMessages\Hooks;

use Config;
use WikiMap;

final class InitialisationHooks implements
    \MediaWiki\Hook\SetupAfterCacheHook,
    \MediaWiki\Hook\CanonicalNamespacesHook
{
    /** @var Config */
    private Config $mainConfig;

    /**
     * @param Config $mainConfig
     */
    public function __construct( Config $mainConfig ) {
        $this->mainConfig = $mainConfig;
    }

    public function onSetupAfterCache() {
        global $wgGlobalMessagesCentralWiki;
    
        if ( $wgGlobalMessagesCentralWiki === false ) {
            $wgGlobalMessagesCentralWiki = WikiMap::getCurrentWikiId();
        }
    }

    /**
     * @param string[] &$namespaces
     * @return void
     */
    public function onCanonicalNamespaces( &$namespaces ) {
        if ( $this->mainConfig->get( 'GlobalMessagesCentralWiki' ) === WikiMap::getCurrentWikiId() ) {
            $namespaces[NS_GLOBAL_MESSAGE] = 'Global_message';
            $namespaces[NS_GLOBAL_MESSAGE_TALK] = 'Global_message_talk';
        }

        global $wgNamespaceRobotPolicies;
        $wgNamespaceRobotPolicies[NS_GLOBAL_MESSAGE] = 'noindex,follow';
        $wgNamespaceRobotPolicies[NS_GLOBAL_MESSAGE_TALK] = 'noindex,follow';
    }
}
