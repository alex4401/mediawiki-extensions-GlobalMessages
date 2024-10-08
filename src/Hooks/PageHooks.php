<?php
namespace MediaWiki\Extension\GlobalMessages\Hooks;

use Config;
use ManualLogEntry;
use MediaWiki\Extension\GlobalMessages\GlobalMessageRegistry;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\User\UserIdentity;
use Title;
use WikiMap;
use WikiPage;

final class PageHooks implements
    \MediaWiki\Page\Hook\PageDeleteCompleteHook,
    \MediaWiki\Page\Hook\ArticleUndeleteHook,
    \MediaWiki\Storage\Hook\PageSaveCompleteHook,
    \MediaWiki\Hook\PageMoveCompleteHook
{
    /** @var Config */
    private Config $mainConfig;
    /** @var GlobalMessageRegistry */
    private GlobalMessageRegistry $registry;

    /**
     * @param Config $mainConfig
     */
    public function __construct(
        Config $mainConfig,
        GlobalMessageRegistry $registry
    ) {
        $this->mainConfig = $mainConfig;
        $this->registry = $registry;
    }

    private function isCentralWiki(): bool {
        return WikiMap::getCurrentWikiId() === $this->mainConfig->get( 'GlobalMessagesCentralWiki' );
    }

	/**
	 * @param ProperPageIdentity|LinkTarget $page
	 * @return bool
	 */
	private function canActOnPage( $page ): bool {
		return $this->isCentralWiki() && $page->getNamespace() === NS_GLOBAL_MESSAGE;
	}

	/**
	 * @param ProperPageIdentity $page Page that was deleted.
	 * @param Authority $deleter Who deleted the page
	 * @param string $reason Reason the page was deleted
	 * @param int $pageID ID of the page that was deleted
	 * @param RevisionRecord $deletedRev Last revision of the deleted page
	 * @param ManualLogEntry $logEntry ManualLogEntry used to record the deletion
	 * @param int $archivedRevisionCount Number of revisions archived during the deletion
	 * @return true|void
	 */
	public function onPageDeleteComplete(
		ProperPageIdentity $page,
		Authority $deleter,
		string $reason,
		int $pageID,
		RevisionRecord $deletedRev,
		ManualLogEntry $logEntry,
		int $archivedRevisionCount
	) {
        if ( $this->canActOnPage( $page ) ) {
			$this->registry->createUpdater()
				->delete( $pageID )
				->finalise();
        }
    }

	/**
	 * @param Title $title Title corresponding to the article restored
	 * @param bool $create Whether or not the restoration caused the page to be created (i.e. it
	 *   didn't exist before)
	 * @param string $comment Comment associated with the undeletion
	 * @param int $oldPageId ID of page previously deleted (from archive table). This ID will be used
	 *   for the restored page.
	 * @param array $restoredPages Set of page IDs that have revisions restored for this undelete,
	 *   with keys set to page IDs and values set to 'true'
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onArticleUndelete( $title, $create, $comment, $oldPageId,
		$restoredPages
	) {
        if ( $this->canActOnPage( $title ) ) {
	        $this->registry->createUpdater()
    	        ->insert( $title->getId() )
        	    ->finalise();
		}
    }

	/**
	 * @param WikiPage $wikiPage WikiPage modified
	 * @param UserIdentity $user User performing the modification
	 * @param string $summary Edit summary/comment
	 * @param int $flags Flags passed to WikiPage::doUserEditContent()
	 * @param RevisionRecord $revisionRecord New RevisionRecord of the article
	 * @param EditResult $editResult Object storing information about the effects of this edit,
	 *   including which edits were reverted and which edit is this based on (for reverts and null
	 *   edits).
	 * @return bool|void True or no return value to continue or false to stop other hook handlers
	 *    from being called; save cannot be aborted
	 */
	public function onPageSaveComplete(
		$wikiPage,
		$user,
		$summary,
		$flags,
		$revisionRecord,
		$editResult
	) {
        if ( $this->canActOnPage( $wikiPage ) ) {
	        $this->registry->createUpdater()
    	        ->insert( $wikiPage->getId(), $revisionRecord )
        	    ->finalise();
		}
    }

	/**
	 * @param LinkTarget $old Old title
	 * @param LinkTarget $new New title
	 * @param UserIdentity $user User who did the move
	 * @param int $pageid Database ID of the page that's been moved
	 * @param int $redirid Database ID of the created redirect
	 * @param string $reason Reason for the move
	 * @param RevisionRecord $revision RevisionRecord created by the move
	 * @return bool|void True or no return value to continue or false stop other hook handlers,
	 *     doesn't abort the move itself
	 */
	public function onPageMoveComplete( $old, $new, $user, $pageid, $redirid,
		$reason, $revision
	) {
        if ( $this->canActOnPage( $new ) ) {
        	$this->registry->createUpdater()
    	        ->insert( $pageid )
	            ->finalise();
		}
    }
}
