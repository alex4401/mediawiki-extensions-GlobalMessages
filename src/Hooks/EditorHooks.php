<?php
namespace MediaWiki\Extension\GlobalMessages\Hooks;

use EditPage;
use Html;
use OutputPage;
use Title;

final class EditorHooks implements
    \MediaWiki\Hook\EditFormPreloadTextHook,
    \MediaWiki\Hook\EditPage__showEditForm_initialHook
{
    /**
     * @param string &$text Text to preload with
     * @param Title $title Page being created
     * @return bool|void True or no return value to continue or false to abort
     */
    public function onEditFormPreloadText( &$text, $title ) {
        if ( defined( 'NS_GLOBAL_MESSAGE' ) && $title->getNamespace() === NS_GLOBAL_MESSAGE ) {
            $msg = wfMessage( $title->getText() );
            if ( $msg && !$msg->isDisabled() ) {
                $text = $msg->plain();
            }
        }
    }

	/**
	 * Adds a warning above the edit field for global messages.
	 *
	 * @param EditPage $editor
	 * @param OutputPage $out OutputPage instance to write to
	 * @return bool|void True or no return value without altering $error to allow the
	 *   edit to continue. Modifying $error and returning true will cause the contents
	 *   of $error to be echoed at the top of the edit form as wikitext. Return false
	 *   to halt editing; you'll need to handle error messages, etc. yourself.
	 */
	public function onEditPage__showEditForm_initial( $editor, $out ) {
        $out->addHTML( Html::warningBox( wfMessage( 'globalmsg-onmsgedit' ) ) );
    }
}
