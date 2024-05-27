<?php

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiMigration;

class InstallFarmForm extends YesWikiMigration
{
    public const PATHS = [
        'Farm description' => 'tools/ferme/setup/forms/Form - Farm.json',
        'Farm template' => 'tools/ferme/setup/forms/Form - Farm - template.txt'
    ];

    protected $formManager;
    protected $params;

    public function run()
    {
        $this->getServices();
        $this->installFarmForm();
    }

    protected function getServices()
    {
        $this->formManager = $this->getService(FormManager::class);
        $this->params = $this->getService(ParameterBagInterface::class);
    }

    protected function installFarmForm()
    {
        // test if the FARM form exists, if not, install it
        $formDescription = json_decode($this->loadFileContent('Farm description'), true);
        $formTemplate = $this->loadFileContent('Farm template');
        $formTemplate = str_replace('{UtilisationDonnees}', $this->wiki->Href('', 'UtilisationDonnees'), $formTemplate);
        $formTemplate = str_replace('{Contact}', $this->wiki->Href('', 'Contact'), $formTemplate);
        if (empty($formTemplate)) {
            throw new Exception('Not possible to add "farm" form because empty template !');
        } else {
            $this->checkAndAddForm(
                $this->params->get('bazar_farm_id'),
                $formDescription["FARM_FORM_NOM"],
                $formDescription["FARM_FORM_DESCRIPTION"],
                $formTemplate
            );
        }
    }

    protected function loadFileContent(string $name): string
    {
        if (!isset(self::PATHS[$name])) {
            return '';
        }
        $path = self::PATHS[$name];
        return file_get_contents($path);
    }

    protected function checkAndAddForm(
        $formId,
        $formName,
        $formDescription,
        $formTemplate
    ) {
        $form = $this->formManager->getOne($formId);
        if (empty($form)) {
            $this->formManager->create([
                'bn_id_nature' => $formId,
                'bn_label_nature' => $formName,
                'bn_template' => $formTemplate,
                'bn_description' => $formDescription,
                'bn_sem_context' => $formDescription,
                'bn_sem_type' => '',
                'bn_sem_use_template' => '1',
                'bn_condition' => '',
            ]);
        }
    }
}
