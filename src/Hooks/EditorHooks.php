<?php
namespace MediaWiki\Extension\GlobalMessages\Hooks;

use EditPage;
use Html;
use MediaWiki\Extension\GlobalMessages\GlobalMessageRegistry;
use MediaWiki\User\User;
use MessageCache;
use MessageSpecifier;
use OutputPage;
use Title;

final class EditorHooks implements
    \MediaWiki\Hook\EditFormPreloadTextHook,
    \MediaWiki\Hook\EditPage__showEditForm_initialHook,
    \MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook
{
    private MessageCache $messageCache;
    private GlobalMessageRegistry $globalMsgRegistry;

    public function __construct(
        MessageCache $messageCache,
        GlobalMessageRegistry $globalMsgRegistry
    ) {
        $this->messageCache = $messageCache;
        $this->globalMsgRegistry = $globalMsgRegistry;
    }

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

        if ( $title->getNamespace() === NS_MEDIAWIKI ) {
            [ $msgName, $lang ] = $this->messageCache->figureMessage( $title->getText() );
            $msg = $this->globalMsgRegistry->resolve( $msgName, $lang );
            if ( $msg ) {
                $text = $msg;
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

	/**
	 * Implement local system message locking via globalmsg-protected-messages.
	 *
	 * @param Title $title Title being checked against
	 * @param User $user Current user
	 * @param string $action Action being checked
	 * @param array|string|MessageSpecifier &$result User permissions error to add. If none, return true.
	 *   For consistency, error messages should be plain text with no special coloring,
	 *   bolding, etc. to show that they're errors; presenting them properly to the
	 *   user as errors is done by the caller.
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
        if ( $action === 'read' || $title->getNamespace() !== NS_GLOBAL_MESSAGE ) {
            return true;
        }

		$reason = $this->globalMsgRegistry->getEditRestrictionInfo( $title->getBaseText() );

        if ( $reason !== null && $user->isAllowed( 'editglobalinterface' ) ) {
            $result = 'globalmsg-protected';
            return false;
        }

		return true;
	}
}
