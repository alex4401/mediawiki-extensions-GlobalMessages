<?php
namespace MediaWiki\Extension\GlobalMessages;

use IDBAccessObject;
use Language;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use TextContent;
use Title;
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

    // TODO: use transactions

    public function insert( int $pageId, ?RevisionRecord $revision = null ): GlobalMessageUpdater {
        if ( $pageId <= 0 ) {
            return $this;
        }

        $title = Title::newFromID( $pageId );

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
            $revision = $this->revisionLookup->getRevisionByPageId( $pageId, 0, IDBAccessObject::READ_LATEST );
        }
        if ( !$revision ) {
            return $this;
        }
        $content = $revision->getContent( SlotRecord::MAIN );
        $pageText = ( $content instanceof TextContent ) ? $content->getText() : '';

        // Update the database
        $this->delete( $pageId );
        $this->dbw->insert(
            'global_messages_cache',
            [
                'gmc_page_id' => $pageId,
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
