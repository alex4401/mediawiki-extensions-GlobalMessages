<?php
namespace MediaWiki\Extension\GlobalMessages\Hooks;

use DatabaseUpdater;
use WikiMap;

final class UpdaterHooks implements
    \MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook
{
    /**
     * @param DatabaseUpdater $updater
     * @return bool
     */
    public function onLoadExtensionSchemaUpdates( $updater ) {
        global $wgGlobalMessagesCentralWiki;

        if ( WikiMap::getCurrentWikiId() !== $wgGlobalMessagesCentralWiki ) {
            // Nothing to add
            return true;
        }

		$dbType = $updater->getDB()->getType();
		$dir = dirname( __DIR__ ) . "/../schema/$dbType";

        $updater->addExtensionTable(
            'global_messages_cache',
            "$dir/table-global_messages_cache.sql"
        );

        return true;
    }
}
