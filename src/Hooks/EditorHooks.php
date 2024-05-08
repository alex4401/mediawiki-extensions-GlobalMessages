<?php
namespace MediaWiki\Extension\GlobalMessages\Hooks;

use Title;

final class EditorHooks implements
    \MediaWiki\Hook\EditFormPreloadTextHook
{
    /**
     * @param string &$text Text to preload with
     * @param Title $title Page being created
     * @return bool|void True or no return value to continue or false to abort
     */
    public function onEditFormPreloadText( &$text, $title ) {
        if ( defined( NS_GLOBAL_MESSAGE ) && $title->getNamespace() === NS_GLOBAL_MESSAGE ) {
            $msg = wfMessage( $title->getText() );
            if ( $msg && !$msg->isDisabled() ) {
                $text = $msg->plain();
            }
        }
    }
}
