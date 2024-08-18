<?php
namespace MediaWiki\Extension\GlobalMessages\Hooks;

use Config;
use WikiMap;

final class InitialisationHooks implements
    \MediaWiki\Hook\SetupAfterCacheHook,
    \MediaWiki\Hook\CanonicalNamespacesHook
{
    private const GRANT_ID = 'editglobalinterface';
    private const RIGHT_ID = 'editglobalinterface';
    private const GROUP_ID = 'global-interface-admin';

    /** @var Config */
    private Config $mainConfig;

    /**
     * @param Config $mainConfig
     */
    public function __construct( Config $mainConfig ) {
        $this->mainConfig = $mainConfig;
    }

    public function onSetupAfterCache() {
        global $wgGlobalMessagesCentralWiki,
            $wgGroupPermissions,
            $wgAddGroups,
            $wgGrantPermissions,
            $wgGrantRiskGroups,
            $wgGrantPermissionGroups,
            $wgPrivilegedGroups;
    
        if ( $wgGlobalMessagesCentralWiki === false ) {
            $wgGlobalMessagesCentralWiki = WikiMap::getCurrentWikiId();
        }

        if ( WikiMap::getCurrentWikiId() === $wgGlobalMessagesCentralWiki ) {
            $wgAddGroups['bureaucrat'][] = self::GROUP_ID;
            $wgGroupPermissions[self::GROUP_ID]['edit'] = true;
            $wgGroupPermissions[self::GROUP_ID][self::RIGHT_ID] = true;
            $wgGrantPermissionGroups[self::GRANT_ID] = 'administration';
            $wgGrantRiskGroups[self::GRANT_ID] = 'security';
            $wgGrantPermissions[self::GRANT_ID] = [ self::RIGHT_ID => true ];
            $wgPrivilegedGroups[] = self::GROUP_ID;
        }
    }

    /**
     * @param string[] &$namespaces
     * @return void
     */
    public function onCanonicalNamespaces( &$namespaces ) {
        if ( $this->mainConfig->get( 'GlobalMessagesCentralWiki' ) !== WikiMap::getCurrentWikiId() ) {
            return;
        }
        
        global $wgNamespaceRobotPolicies;

        $namespaces[NS_GLOBAL_MESSAGE] = 'Global_message';
        $namespaces[NS_GLOBAL_MESSAGE_TALK] = 'Global_message_talk';
        $wgNamespaceRobotPolicies[NS_GLOBAL_MESSAGE] = 'noindex,follow';
        $wgNamespaceRobotPolicies[NS_GLOBAL_MESSAGE_TALK] = 'noindex,follow';
    }
}
