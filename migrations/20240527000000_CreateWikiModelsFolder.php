<?php

use YesWiki\Core\YesWikiMigration;

class CreateWikiModelsFolder extends YesWikiMigration
{
    public function run()
    {
        $this->createWikiModelsFolder();
    }

    protected function createWikiModelsFolder()
    {
        $customWikiModelDir = 'custom/wiki-models/';
        if (!is_dir($customWikiModelDir)) {
            if (!mkdir($customWikiModelDir, 0777, true)) {
                throw new Exception("Not possible to create folder '$customWikiModelDir' !");
            }
        }
    }
}
