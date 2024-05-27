<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Bazar\Service\EntryManager;

class RemoveBfDossierField extends YesWikiMigration
{
    protected $entryManager;

    public function run()
    {
        $this->getServices();
        $this->removeBfDossierField();
    }

    protected function getServices()
    {
        $this->entryManager = $this->getService(EntryManager::class);
    }

    protected function removeBfDossierField()
    {
        if (method_exists(EntryManager::class, 'removeAttributes')) {
            $this->entryManager->removeAttributes([], ['bf_dossier-wiki_wikiname','bf_dossier-wiki_email','bf_dossier-wiki_password'], true);
        } else {
            throw new Exception("Not possible to remove bf_dossier fields from bazar entries in {$this->dbService->prefixTable('pages')} table.");
        }
    }
}
