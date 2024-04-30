<?php
namespace MediaWiki\Extension\GlobalMessages;

class GlobalMessageUpdater {
    /** @var GlobalMessageRegistry */
    private GlobalMessageRegistry $registry;

    public function __construct(
        GlobalMessageRegistry $registry
    ) {
        $this->registry = $registry;
    }

    public function insert( int $pageId ): GlobalMessageUpdater {
        return $this;
    }

    public function delete( int $pageId ): GlobalMessageUpdater {
        return $this;
    }

    public function finalise(): void {
        $this->registry->purgeCache();
    }
}
