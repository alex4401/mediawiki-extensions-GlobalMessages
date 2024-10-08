<?php
namespace MediaWiki\Extension\GlobalMessages;

use IDBAccessObject;
use Language;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use TextContent;
use Wikimedia\Rdbms\IDatabase;

class GlobalMessageUpdater {
    /** @var GlobalMessageRegistry */
    private GlobalMessageRegistry $registry;
    /** @var LanguageNameUtils */
    private LanguageNameUtils $languageUtils;
    /** @var RevisionLookup */
    private RevisionLookup $revisionLookup;
    /** @var IDatabase */
    private IDatabase $dbw;

    /** @var array[] */
    private array $messageCachePurges;

    public function __construct(
        GlobalMessageRegistry $registry,
        LanguageNameUtils $languageUtils,
        RevisionLookup $revisionLookup,
        IDatabase $dbw
    ) {
        $this->registry = $registry;
        $this->languageUtils = $languageUtils;
        $this->revisionLookup = $revisionLookup;
        $this->dbw = $dbw;
        $this->messageCachePurges = [];
    }

    public function insertByPageId( int $pageId, ?RevisionRecord $revision = null ): GlobalMessageUpdater {
        if ( $pageId <= 0 ) {
            return $this;
        }

        $title = Title::newFromID( $pageId, IDBAccessObject::READ_LATEST );
        if ( $title === null ) {
            return $this;
        }

        return $this->insertInternal( $title, $revision );
    }

    public function insertByTitle( Title $title, ?RevisionRecord $revision = null ): GlobalMessageUpdater {
        return $this->insertInternal( $title, $revision );
    }

    // TODO: use transactions

    private function insertInternal( Title $title, ?RevisionRecord $revision = null ): GlobalMessageUpdater {
        if ( !$title->exists() ) {
            return $this;
        }

        // Fetch message name and language code
        $pageName = $title->getText();
        $pageLang = $title->getSubpageText();
        if ( $this->languageUtils->isKnownLanguageTag( $pageLang ) ) {
            $pageName = $title->getBaseText();
        } else {
            $pageLang = '*';
        }

        // Fetch the contents, unless a revision has already been provided for us
        if ( $revision === null ) {
            $revision = $this->revisionLookup->getRevisionByTitle( $title, 0, IDBAccessObject::READ_LATEST );
        }
        if ( !$revision ) {
            return $this;
        }
        $content = $revision->getContent( SlotRecord::MAIN );
        $pageText = ( $content instanceof TextContent ) ? $content->getText() : '';

        // Update the database
        $this->delete( $title->getId() );
        $this->dbw->insert(
            'global_messages_cache',
            [
                'gmc_page_id' => $title->getId(),
                'gmc_name' => $pageName,
                'gmc_lang' => $pageLang,
                'gmc_text' => $pageText,
            ],
            __METHOD__
        );

        $this->messageCachePurges[] = [ $title, $content ];

        return $this;
    }

    public function delete( int $pageId ): GlobalMessageUpdater {
        $this->dbw->delete(
            'global_messages_cache',
            [
                'gmc_page_id' => $pageId,
            ],
            __METHOD__
        );
        return $this;
    }

    public function finalise(): void {
        $this->registry->purgeCache();

        $msgCache = MediaWikiServices::getInstance()->getMessageCache();
        foreach ( $this->messageCachePurges as $pending ) {
            $msgCache->updateMessageOverride( $pending[0], $pending[1] );
        }
    }
}
