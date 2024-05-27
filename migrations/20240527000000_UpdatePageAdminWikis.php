<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;

class UpdatePageAdminWikis extends YesWikiMigration
{
    public const PATH = 'tools/ferme/setup/pages/AdminWikis.txt';

    protected $aclService;
    protected $pageManager;

    public function run()
    {
        $this->getServices();
        $this->updatePage('AdminWikis');
    }

    protected function getServices()
    {
        $this->aclService = $this->getService(AclService::class);
        $this->pageManager = $this->getService(PageManager::class);
    }

    protected function updatePage(
        string $pageName
    ) {
        if (empty($this->pageManager->getOne($pageName))) {
            $content = file_get_contents(self::PATH);
            $this->aclService->delete($pageName); // to clear acl cache
            $this->aclService->save($pageName, 'read', '@admins');
            $this->aclService->save($pageName, 'write', '@admins');
            $this->pageManager->save($pageName, $content, "", true);
        }
    }
}
