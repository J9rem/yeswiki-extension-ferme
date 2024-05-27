<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Core\Service\PageManager;

class AppendToPageRapideHaut extends YesWikiMigration
{
    public const PATH = 'tools/ferme/setup/pages/PageRapideHaut.txt';

    protected $pageManager;

    public function run()
    {
        $this->getServices();
        if (empty($this->pageManager->getOne('AdminWikis'))) {
            $this->updatePageRapideHaut();
        }
    }

    protected function getServices()
    {
        $this->pageManager = $this->getService(PageManager::class);
    }

    protected function updatePageRapideHaut()
    {
        $pageRapideHaut = $this->pageManager->getOne('PageRapideHaut');
        if (empty($pageRapideHaut)) {
            throw new Exception('The "PageRapideHaut" page does not exist.');
        } elseif (!strstr($pageRapideHaut['body'], 'AdminWikis')) {
            $content = file_get_contents(self::PATH);
            $this->pageManager->save(
                'PageRapideHaut',
                str_replace(
                    '{{end elem="buttondropdown"}}',
                    "$content\n{{end elem=\"buttondropdown\"}}",
                    $pageRapideHaut['body']
                ),
                "",
                true
            );
        }
    }
}
