<?php

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;

class UpdatePageAjouterWiki extends YesWikiMigration
{
    public const PATH = 'tools/ferme/setup/pages/AjouterWiki.txt';

    protected $aclService;
    protected $pageManager;
    protected $params;

    public function run()
    {
        $this->getServices();
        $this->updatePage('AjouterWiki', ['{FarmFormId}' => $this->params->get('bazar_farm_id')]);
    }

    protected function getServices()
    {
        $this->aclService = $this->getService(AclService::class);
        $this->pageManager = $this->getService(PageManager::class);
        $this->params = $this->getService(ParameterBagInterface::class);
    }

    protected function updatePage(
        string $pageName,
        array $replacements = []
    ) {
        if (empty($this->pageManager->getOne($pageName))) {
            $content = file_get_contents(self::PATH);
            if (!empty($replacements)) {
                $content = str_replace(array_keys($replacements), array_values($replacements), $content);
            }
            $this->aclService->delete($pageName); // to clear acl cache
            $this->aclService->save($pageName, 'read', '@admins');
            $this->aclService->save($pageName, 'write', '@admins');
            $this->pageManager->save($pageName, $content, "", true);
        }
    }
}
