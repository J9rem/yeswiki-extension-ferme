<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;

class InstallListeOuiNon extends YesWikiMigration
{
    public const PATH = 'tools/ferme/setup/lists/ListeOuiNon.json';

    protected $pageManager;
    protected $tripleStore;

    public function run()
    {
        $this->getServices();
        $this->installListeOuiNon();
    }

    protected function getServices()
    {
        $this->pageManager = $this->getService(PageManager::class);
        $this->tripleStore = $this->getService(TripleStore::class);
    }

    protected function installListeOuiNon()
    {
        // if the OuiNon Lms list doesn't exist, create it
        if (!$this->pageManager->getOne('ListeOuiNon')) {
            // save the page with the list value
            $this->pageManager->save('ListeOuiNon', file_get_contents(self::PATH));
            // in case, there is already some triples for 'ListOuinonLms', delete them
            $this->tripleStore->delete('ListeOuiNon', 'http://outils-reseaux.org/_vocabulary/type', null);
            // create the triple to specify this page is a list
            $this->tripleStore->create('ListeOuiNon', 'http://outils-reseaux.org/_vocabulary/type', 'liste', '', '');
        }
    }
}
