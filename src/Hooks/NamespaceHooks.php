<?php
namespace MediaWiki\Extension\GlobalMessages\Hooks;

use Config;
use MediaWiki\MainConfigNames;
use WikiMap;

// @phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

final class NamespaceHooks implements
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

    /**
     * Registers Map namespace if configured so (default behaviour). Sets the robot policy if namespace ID is 2900.
     *
     * @param string[] &$namespaces
     * @return void
     */
    public function onCanonicalNamespaces( &$namespaces ) {
        if ( $this->mainConfig->get( MainConfigNames::SharedDB ) === WikiMap::getCurrentWikiId() ) {
            $namespaces[NS_GLOBAL_MESSAGE] = 'Global_message';
            $namespaces[NS_GLOBAL_MESSAGE_TALK] = 'Global_message_talk';
        }

        global $wgNamespaceRobotPolicies;
        $wgNamespaceRobotPolicies[NS_GLOBAL_MESSAGE] = 'noindex,follow';
        $wgNamespaceRobotPolicies[NS_GLOBAL_MESSAGE_TALK] = 'noindex,follow';
    }
}
