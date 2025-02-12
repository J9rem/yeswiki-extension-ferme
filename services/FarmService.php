<?php

namespace YesWiki\Ferme\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Controller\CsrfTokenController;
use YesWiki\Wiki;
use YesWiki\Bazar\Service\EntryManager;

class FarmService
{
    protected $wiki;
    protected $sourceWikiVersion = '';
    protected $params;

    public function __construct(Wiki $wiki)
    {
        $this->wiki = $wiki;
        $this->params = $this->wiki->services->get(ParameterBagInterface::class);
        $this->sourceWikiVersion = $this->params->get('yeswiki_release');
        $this->initFarmConfig();
    }

    /** initFarmConfig() - test le fichier de configuration et ajoute des valuers par defaut, si besoin
     *
     * @return   void
     */
    public function initFarmConfig()
    {
        // test de l'existence des variables de configuration de la ferme, mise en place de valeurs par défaut sinon
        if (!isset($this->wiki->config['yeswiki-farm-root-url'])) {
            $this->wiki->config['yeswiki-farm-root-url'] = str_replace(
                array('wakka.php?wiki=', '?'),
                '',
                $this->wiki->config['base_url']
            );
            $this->wiki->config['yeswiki-farm-root-folder'] = '.';
        } elseif (!isset($this->wiki->config['yeswiki-farm-root-folder'])) {
            exit('<div class="alert alert-danger">Il faut indiquer le chemin relatif des wikis'
                . ' avec la valeur "yeswiki-farm-root-folder" dans le fichier de configuration.</div>');
        }
        // themes supplémentaires
        if (
            !isset($this->wiki->config['yeswiki-farm-extra-themes'])
            || !is_array($this->wiki->config['yeswiki-farm-extra-themes'])
        ) {
            $this->wiki->config['yeswiki-farm-extra-themes'] = array();
        }

        // extensions supplémentaires
        if (
            !isset($this->wiki->config['yeswiki-farm-extra-tools'])
            || !is_array($this->wiki->config['yeswiki-farm-extra-tools'])
        ) {
            $this->wiki->config['yeswiki-farm-extra-tools'] = array();
        }

        // theme par defaut
        if (
            !isset($this->wiki->config['yeswiki-farm-themes'])
            or !is_array($this->wiki->config['yeswiki-farm-themes'])
        ) {
            $this->wiki->config['yeswiki-farm-themes'][0]['label'] = 'Margot (theme de base)';
            $this->wiki->config['yeswiki-farm-themes'][0]['screenshot'] = 'margot.jpg';
            $this->wiki->config['yeswiki-farm-themes'][0]['theme'] = THEME_PAR_DEFAUT;
            $this->wiki->config['yeswiki-farm-themes'][0]['squelette'] = SQUELETTE_PAR_DEFAUT;
            $this->wiki->config['yeswiki-farm-themes'][0]['style'] = CSS_PAR_DEFAUT;
        } else {
            // verifier l'existence des themes
            foreach ($this->wiki->config['yeswiki-farm-themes'] as $key => $theme) {
                if (!isset($theme['label']) or empty($theme['label'])) {
                    exit('<div class="alert alert-danger">Au moins un label pour les themes de la ferme n\'a'
                        . ' pas été bien renseigné.</div>');
                }
                if (!isset($theme['screenshot']) or empty($theme['screenshot'])) {
                    exit('<div class="alert alert-danger">Au moins un screenshot pour les themes de la ferme n\'a'
                        . ' pas été bien renseigné.</div>');
                } elseif (!is_file('tools/ferme/screenshots/' . $theme['screenshot'])) {
                    $theme['screenshot'] = false;
                }
                if (!isset($theme['theme']) or empty($theme['theme'])) {
                    exit('<div class="alert alert-danger">Au moins un theme pour les themes de la ferme n\'a'
                        . ' pas été bien renseigné.</div>');
                } elseif (!is_dir('themes/' . $theme['theme']) and ($theme['theme'] == "yeswiki" and !is_dir('tools/templates/themes/' . $theme['theme']))) {
                    exit('<div class="alert alert-danger">Le dossier "themes/' . $theme['theme']
                        . '" n\'a pas été trouvé.</div>');
                }
                if (!isset($theme['squelette']) or empty($theme['squelette'])) {
                    exit('<div class="alert alert-danger">Au moins un squelette pour les themes de la ferme n\'a'
                        . ' pas été bien renseigné.</div>');
                } elseif (!is_file('themes/' . $theme['theme'] . '/squelettes/' . $theme['squelette']) and ($theme['theme'] == "yeswiki" and !is_file('tools/templates/themes/' . $theme['theme'] . '/squelettes/' . $theme['squelette']))) {
                    exit('<div class="alert alert-danger">Le squelette "themes/' . $theme['theme']
                        . '/squelettes/' . $theme['squelette'] . '" n\'a pas été trouvé.</div>');
                }
                if (!isset($theme['style']) or empty($theme['style'])) {
                    exit('<div class="alert alert-danger">Au moins un style css pour les themes de la ferme n\'a'
                        . ' pas été bien renseigné.</div>');
                } elseif (!is_file('themes/' . $theme['theme'] . '/styles/' . $theme['style']) and ($theme['theme'] == "yeswiki" and !is_file('tools/templates/themes/' . $theme['theme'] . '/styles/' . $theme['style']))) {
                    exit('<div class="alert alert-danger">Le style css "themes/' . $theme['theme'] . '/styles/' . $theme['style']
                        . '" n\'a pas été trouvé.</div>');
                }
            }
        }

        if (is_null($this->wiki->config['yeswiki_symlinked_files'])) {
            $this->wiki->config['yeswiki_symlinked_files'] = [];
        }

        if (!isset($this->wiki->config['yeswiki-farm-bg-img'])) {
            $this->wiki->config['yeswiki-farm-bg-img'] = '';
        }

        // acls
        if (
            !isset($this->wiki->config['yeswiki-farm-acls'])
            or !is_array($this->wiki->config['yeswiki-farm-acls'])
        ) {
            $this->wiki->config['yeswiki-farm-acls'][0]['label'] = 'Wiki ouvert';
            $this->wiki->config['yeswiki-farm-acls'][0]['read'] = '*';
            $this->wiki->config['yeswiki-farm-acls'][0]['write'] = '*';
            $this->wiki->config['yeswiki-farm-acls'][0]['comments'] = 'comments-closed';
        } else {
            // verifier l'existence des acls
            foreach ($this->wiki->config['yeswiki-farm-acls'] as $key => $acls) {
                if (!isset($acls['label']) or empty($acls['label'])) {
                    exit('<div class="alert alert-danger">Au moins un label pour les acls de la ferme n\'a'
                        . ' pas été bien renseigné.</div>');
                }
                if (!isset($acls['read']) or empty($acls['read'])) {
                    exit('<div class="alert alert-danger">Au moins un droit en lecture (read) n\'a'
                        . ' pas été bien renseigné.</div>');
                }
                if (!isset($acls['write']) or empty($acls['write'])) {
                    exit('<div class="alert alert-danger">Au moins un droit en lecture (write) n\'a'
                        . ' pas été bien renseigné.</div>');
                }
                if (!isset($acls['comments']) or empty($acls['comments'])) {
                    exit('<div class="alert alert-danger">Au moins un droit des commentaires (comments) n\'a'
                        . ' pas été bien renseigné.</div>');
                }
            }
        }

        // sql d'installation par défaut
        if (
            !isset($this->wiki->config['yeswiki-farm-models'])
            or !is_array($this->wiki->config['yeswiki-farm-models'])
        ) {
            $this->wiki->config['yeswiki-farm-models'][] = 'default-content';
        } else {
            // verifier l'existence des parametres des fichiers sql
            foreach ($this->wiki->config['yeswiki-farm-models'] as $key => $folder) {
                if ($folder != 'default-content') {
                    if (!is_dir('custom/wiki-models/' . $folder)) {
                        unset($this->wiki->config['yeswiki-farm-models'][$key]);
                        trigger_error('<div class="alert alert-danger">le dossier "custom/wiki-models/' . $folder . '" ne semble pas exister.</div>');
                    } elseif (!is_file('custom/wiki-models/' . $folder . '/default-content.sql')) {
                        unset($this->wiki->config['yeswiki-farm-models'][$key]);
                        trigger_error('<div class="alert alert-danger">Le fichier sql "custom/wiki-models/' . $folder . '/default-content.sql" n\'a pas été trouvé.</div>');
                    }
                }
            }
        }

        // création d'un utilisateur dans le wiki initial (sert pour des cas spécifiques avec une bd centralisée)
        if (!isset($this->wiki->config['yeswiki-farm-create-user'])) {
            $this->wiki->config['yeswiki-farm-create-user'] = false;
        }

        // Utilisateur WikiAdmin par défaut (laisser vide pour demander à la création du wiki)
        if (!isset($this->wiki->config['yeswiki-farm-default-WikiAdmin'])) {
            $this->wiki->config['yeswiki-farm-default-WikiAdmin'] = 'WikiAdmin';
        }

        // Mot de passe WikiAdmin par défaut (laisser vide pour demander à la création du wiki)
        if (!isset($this->wiki->config['yeswiki-farm-password-WikiAdmin'])) {
            $this->wiki->config['yeswiki-farm-password-WikiAdmin'] = '';
        }

        // Email par défaut (laisser vide pour demander à la création du wiki)
        if (!isset($this->wiki->config['yeswiki-farm-email-WikiAdmin'])) {
            $this->wiki->config['yeswiki-farm-email-WikiAdmin'] = 'bf_mail';
        }

        // page d'accueil des wikis de la ferme
        if (!isset($this->wiki->config['yeswiki-farm-homepage'])) {
            $this->wiki->config['yeswiki-farm-homepage'] = $this->wiki->config['root_page'];
        }

        // prefixe par default
        if (!isset($this->wiki->config['yeswiki-farm-prefix'])) {
            $this->wiki->config['yeswiki-farm-prefix'] = 'yeswiki_';
        }

        // admin de la ferme
        if (!isset($this->wiki->config['yeswiki-farm-admin-name'])) {
            $this->wiki->config['yeswiki-farm-admin-name'] = '';
        }
        if (!isset($this->wiki->config['yeswiki-farm-admin-pass'])) {
            $this->wiki->config['yeswiki-farm-admin-pass'] = '';
        }
    }

    public function getWikiConfig($wiki)
    {
        $wakkaConfig = [];
        if ($this->wiki->config['yeswiki-farm-root-folder'] == '.') {
            $path = getcwd() . DIRECTORY_SEPARATOR . $wiki . '/wakka.config.php';
        } else {
            $path = getcwd() . DIRECTORY_SEPARATOR
                . $this->wiki->config['yeswiki-farm-root-folder'] . DIRECTORY_SEPARATOR
                . $wiki . '/wakka.config.php';
        }
        if (file_exists($path)) {
            include_once realpath($path);
        }
        return $wakkaConfig;
    }

    public function hasFarmAdmin($wiki)
    {
        return;
    }

    public function addFarmAdmin($wiki)
    {
        $wikiConf = $this->getWikiConfig($wiki);
        if (!empty($this->wiki->config['yeswiki-farm-admin-name']) && !empty($this->wiki->config['yeswiki-farm-admin-pass'])) {
            if (!empty($wikiConf['table_prefix'])) {
                // change database
                $sql =  'USE ' . $wikiConf['mysql_database'] . ';';
                $this->wiki->query($sql);

                $sql = 'SELECT value FROM `' . $wikiConf['table_prefix'] . 'triples` WHERE resource = "ThisWikiGroup:admins";';
                $list = $this->wiki->LoadSingle($sql);
                $list = explode("\n", $list['value']);
                if (!in_array($this->wiki->config['yeswiki-farm-admin-name'], $list)) {
                    $list[] = $this->wiki->config['yeswiki-farm-admin-name'];
                }
                $list = array_map('trim', $list);
                $list = implode("\n", $list);
                $sql = 'UPDATE `' . $wikiConf['table_prefix'] . 'triples` SET value="' . addslashes($list) . '" WHERE resource = "ThisWikiGroup:admins";';
                $this->wiki->Query($sql);

                $sql = 'INSERT INTO `' . $wikiConf['table_prefix'] . 'users` (`name`, `password`, `email`, `motto`, `revisioncount`, `changescount`, `doubleclickedit`, `signuptime`, `show_comments`) VALUES (\'' . $this->wiki->config['yeswiki-farm-admin-name'] . '\', MD5(\'' . $this->wiki->config['yeswiki-farm-admin-pass'] . '\'), \'\', \'\', \'20\', \'50\', \'Y\', NOW(), \'N\')';
                $this->wiki->Query($sql);

                // back to main database
                $sql =  'USE ' . $this->wiki->config['mysql_database'] . ';';
                $this->wiki->query($sql);

                return [
                    'success' => [_t('Super user added for the wiki') . ' :' . $wiki . '.']
                ];
            } else {
                return [
                    'errors' => [_t('No table prefix found for the wiki') . ' :' . $wiki . '.']
                ];
            }
        } else {
            return [
                'errors' => [_t('No yeswiki-farm-admin-name or yeswiki-farm-admin-pass in config.')]
            ];
        }
    }

    public function removeFarmAdmin($wiki)
    {
        $wikiConf = $this->getWikiConfig($wiki);
        if (!empty($wikiConf['table_prefix'])) {
            // change database
            $sql =  'USE ' . $wikiConf['mysql_database'] . ';';
            $this->wiki->query($sql);

            $sql = 'SELECT value FROM `' . $wikiConf['table_prefix'] . 'triples` WHERE resource = "ThisWikiGroup:admins";';
            $list = $this->wiki->LoadSingle($sql);
            $list = explode("\n", $list['value']);
            if (in_array($this->wiki->config['yeswiki-farm-admin-name'], $list)) {
                $list = array_diff($list, array($this->wiki->config['yeswiki-farm-admin-name']));
            }
            $list = array_map('trim', $list);
            $list = implode("\n", $list);
            $sql = 'UPDATE `' . $wikiConf['table_prefix'] . 'triples` SET value="' . addslashes($list) . '" WHERE resource = "ThisWikiGroup:admins";';
            $this->wiki->Query($sql);

            $sql = 'DELETE FROM ' . $wikiConf['table_prefix'] . 'users WHERE name="' . $this->wiki->config['yeswiki-farm-admin-name'] . '";';
            $this->wiki->Query($sql);

            // back to main database
            $sql =  'USE ' . $this->wiki->config['mysql_database'] . ';';
            $this->wiki->query($sql);
        }
    }

    public function createWikiFromEntry($entry, $fieldName)
    {
        if ($entry[$fieldName . '_wikiname'] == '{{folder}}') {
            $entry[$fieldName . '_wikiname'] = genere_nom_wiki(
                $entry[$fieldName],
                0
            );
            if ($this->wiki->LoadUser($entry[$fieldName . '_wikiname'])) {
                throw new \Exception('L\'utilisateur ' . $entry[$fieldName . '_wikiname']
                    . ' existe déjà, veuillez trouver un autre nom pour votre wiki.');
            }
        }

        // replace e_mail with the right email if referenced via other field like bf_mail
        $entry[$fieldName . '_email'] = (!empty($entry[$fieldName . '_email']) && !empty($entry[$entry[$fieldName . '_email']]))
            ? $entry[$entry[$fieldName . '_email']] : $entry[$fieldName . '_email'];

        // creation d'un user?
        if ($this->wiki->config['yeswiki-farm-create-user']) {
            if ($this->wiki->LoadUser($entry[$fieldName . '_wikiname'])) {
                throw new \Exception('L\'utilisateur ' . $entry[$fieldName . '_wikiname']
                    . ' existe déjà, veuillez trouver un autre nom pour votre utilisateur.');
            }
            $this->wiki->Query(
                "insert into " . $this->wiki->config["table_prefix"] . "users set " .
                    "signuptime = now(), " .
                    "name = '" . mysqli_real_escape_string($this->wiki->dblink, $entry[$fieldName . '_wikiname']) . "', " .
                    "email = '" . mysqli_real_escape_string($this->wiki->dblink, $entry[$fieldName . '_email']) . "', " .
                    "password = md5('" . mysqli_real_escape_string($this->wiki->dblink, $entry[$fieldName . '_password']) . "')"
            );
        }

        $url = $this->wiki->config['yeswiki-farm-root-url'] . $entry[$fieldName];
        $srcfolder = getcwd() . DIRECTORY_SEPARATOR;
        $destfolder = $this->getAbsolutePath(
            getcwd() . DIRECTORY_SEPARATOR
                . $this->wiki->config['yeswiki-farm-root-folder'] . DIRECTORY_SEPARATOR
                . $entry[$fieldName]
        );

        // test l'existence du dossier choisi
        if (is_dir($destfolder)) {
            throw new \Exception('L\'adresse ' . $url . ' est déja utilisée, veuillez en prendre une autre.');
        } else {
            // on copie les fichier du wiki si l'on a accès en écriture
            if (is_writable($this->wiki->config['yeswiki-farm-root-folder'])) {
                // create root folder and empty folders
                mkdir($destfolder, 0777, true);
                foreach ($this->wiki->config['yeswiki_empty_folders'] as $folder) {
                    // mise a jour des fichier de YesWiki qui ne sont pas des symlink
                    if (!in_array($folder, $this->wiki->config['yeswiki_symlinked_files'])) {
                        mkdir($destfolder . $folder, 0777, true);
                    }
                }

                // main yeswiki files
                foreach ($this->wiki->config['yeswiki_files'] as $file) {
                    if (!in_array($file, $this->wiki->config['yeswiki_symlinked_files'])) {
                        $this->copyRecursive($srcfolder . $file, $destfolder . $file);
                    }
                }

                // symlinked files
                foreach ($this->wiki->config['yeswiki_symlinked_files'] as $file) {
                    symlink($srcfolder . $file, $destfolder . $file);
                }

                // extra themes
                foreach ($this->wiki->config['yeswiki-farm-extra-themes'] as $themes) {
                    $this->copyRecursive(
                        $srcfolder . 'themes' . DIRECTORY_SEPARATOR . $themes,
                        $destfolder . 'themes' . DIRECTORY_SEPARATOR . $themes
                    );
                }

                // extensions supplémentaires
                foreach ($this->wiki->config['yeswiki-farm-extra-tools'] as $tools) {
                    $this->copyRecursive(
                        $srcfolder . 'tools' . DIRECTORY_SEPARATOR . $tools,
                        $destfolder . 'tools' . DIRECTORY_SEPARATOR . $tools
                    );
                }

                // droits d'accès par aux pages
                $rights = $this->wiki->config['yeswiki-farm-acls'][$entry['yeswiki-farm-acls']];
                if ($rights["write"] == '{{user}}') {
                    if (!empty($entry["access-username"])) {
                        $rights["write"] = $entry["access-username"];
                    } else {
                        $rights["write"] = $entry[$fieldName . '_wikiname'];
                    }
                }
                if ($rights["read"] == '{{user}}') {
                    if (!empty($entry["access-username"])) {
                        $rights["read"] = $entry["access-username"];
                    } else {
                        $rights["read"] = $entry[$fieldName . '_wikiname'];
                    }
                }
                if ($rights["comments"] == '{{user}}') {
                    if (!empty($entry["access-username"])) {
                        $rights["comments"] = $entry["access-username"];
                    } else {
                        $rights["comments"] = $entry[$fieldName . '_wikiname'];
                    }
                }

                // theme choisi
                $theme = $this->wiki->config['yeswiki-farm-themes'][$_POST['yeswiki-farm-theme']];
                $this->wiki->config['yeswiki-farm-fav-theme'] = $theme['theme'];
                $this->wiki->config['yeswiki-farm-fav-style'] = $theme['style'];
                $this->wiki->config['yeswiki-farm-fav-squelette'] = $theme['squelette'];
                $this->wiki->config['yeswiki-farm-fav-preset'] = $theme['preset'] ?? '';
                $this->wiki->config['yeswiki-farm-bg-img'] = $theme['bg-img'] ?? '';

                // generation du prefixe
                $prefix = empty($entry['bf_prefixe']) ?
                    $this->wiki->config['yeswiki-farm-prefix'] . str_replace('-', '_', $entry[$fieldName]) . '__' :
                    $entry['bf_prefixe'];

                // ecriture du fichier de configuration
                $config = array(
                    'wakka_version' => $this->wiki->config['wakka_version'],
                    'wikini_version' => $this->wiki->config['wikini_version'],
                    'yeswiki_version' => $this->wiki->config['yeswiki_version'],
                    'yeswiki_release' => $this->wiki->config['yeswiki_release'],
                    'debug' => $this->wiki->config['debug'],
                    'mysql_host' => $this->wiki->config['mysql_host'],
                    'mysql_database' => $this->wiki->config['mysql_database'],
                    'mysql_user' => $this->wiki->config['mysql_user'],
                    'mysql_password' => $this->wiki->config['mysql_password'],
                    'table_prefix' => $prefix,
                    'root_page' => $this->wiki->config['yeswiki-farm-homepage'],
                    'wakka_name' => addslashes($entry['bf_titre']),
                    'base_url' => $this->wiki->config['yeswiki-farm-root-url']
                        . $entry[$fieldName] . '/?',
                    'rewrite_mode' => $this->wiki->config['rewrite_mode'],
                    'meta_keywords' => $this->wiki->config['meta_keywords'],
                    'meta_description' => $this->wiki->config['meta_description'],
                    'action_path' => 'actions',
                    'handler_path' => 'handlers',
                    'header_action' => 'header',
                    'footer_action' => 'footer',
                    'navigation_links' => $this->wiki->config['navigation_links'],
                    'referrers_purge_time' => $this->wiki->config['referrers_purge_time'],
                    'pages_purge_time' => $this->wiki->config['pages_purge_time'],
                    'default_write_acl' => $rights["write"],
                    'default_read_acl' => $rights["read"],
                    'default_comment_acl' => $rights["comments"],
                    'preview_before_save' => $this->wiki->config['preview_before_save'],
                    'allow_raw_html' => $this->wiki->config['allow_raw_html'],
                    'default_language' => $this->wiki->config['default_language'],
                    'favorite_theme' => $this->wiki->config['yeswiki-farm-fav-theme'],
                    'favorite_style' => $this->wiki->config['yeswiki-farm-fav-style'],
                    'favorite_squelette' => $this->wiki->config['yeswiki-farm-fav-squelette'],
                    'favorite_preset' => $this->wiki->config['yeswiki-farm-fav-preset'],
                    'favorite_background_image' => $this->wiki->config['yeswiki-farm-bg-img'],
                    'source_url' =>  $this->wiki->href('', $entry['id_fiche']),
                    'db_charset' =>  'utf8mb4',
                );
                if (
                    isset($this->wiki->config['yeswiki-farm-extra-config'])
                    and is_array($this->wiki->config['yeswiki-farm-extra-config'])
                ) {
                    $config = array_merge($config, $this->wiki->config['yeswiki-farm-extra-config']);
                }

                if (isset($entry['bf_description'])) {
                    $config['meta_description'] = addslashes(
                        substr(
                            str_replace('<br>', ' ', strip_tags($entry['bf_description'], '<br>')),
                            0,
                            150
                        )
                    );
                }

                // convert config array into PHP code
                $configCode = "<?php\n// wakka.config.php " . _t('CREATED') . " " . date("Y-m-d H:i:s") . "\n// " .
                    _t('DONT_CHANGE_YESWIKI_VERSION_MANUALLY') . " !\n\n\$wakkaConfig = ";
                $configCode .= var_export($config, true) . ";\n?>";

                if ($fp = @fopen($destfolder . 'wakka.config.php', "w")) {
                    fwrite($fp, $configCode);
                    // write
                    fclose($fp);
                } else {
                    throw new \Exception('Ecriture du fichier de configuration impossible');
                }
                // creation des tables de la base de données
                /* create sql connection*/
                $link = mysqli_connect(
                    $this->wiki->config['mysql_host'],
                    $this->wiki->config['mysql_user'],
                    $this->wiki->config['mysql_password'],
                    $this->wiki->config['mysql_database'],
                    isset($this->wiki->config['mysql_port']) ? $this->wiki->config['mysql_port'] : ini_get("mysqli.default_port")
                );
                // necessaire pour les versions de mysql qui ont un autre encodage par defaut
                mysqli_set_charset($link, 'utf8mb4');

                // dans certains cas (ovh), set_charset ne passe pas, il faut faire une requete sql
                $charset = mysqli_character_set_name($link);
                if ($charset != 'utf8mb4') {
                    mysqli_query($link, 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
                }

                $replacements = [
                    'prefix' => $prefix,
                    'siteTitle' => $config['wakka_name'],
                    'WikiName' => $entry[$fieldName . '_wikiname'],
                    'password' => $entry[$fieldName . '_password'],
                    'email' => $entry[$fieldName . '_email'],
                    'rootPage' => $config['root_page'],
                ];

                $notExistingTables = array_filter(
                    ['pages', 'links', 'acls', 'triples', 'nature', 'referrers', 'users'],
                    function ($tableName) use ($link, $prefix) {
                        return (mysqli_num_rows(mysqli_query($link, "SHOW TABLES LIKE '$prefix$tableName'")) === 0);
                    }
                );

                // default tables
                mysqli_begin_transaction($link);
                mysqli_autocommit($link, false);
                try {
                    $sqlReport = $this->querySqlFile($link, 'setup/sql/create-tables.sql', $replacements) . '<hr />';
                } catch (\Throwable $th) {
                    $this->resetSQLTransactionWhenError($link, $notExistingTables, $prefix);
                    throw $th;
                }

                // get the datas to insert from the model
                $sqlfilepath = $_POST['yeswiki-farm-model'] == 'default-content' ?
                    'setup/sql/default-content.sql'
                    : 'custom/wiki-models/' . $_POST['yeswiki-farm-model'] . '/default-content.sql';
                try {
                    $sqlReport .= $this->querySqlFile($link, $sqlfilepath, $replacements);
                } catch (\Throwable $th) {
                    $this->resetSQLTransactionWhenError($link, $notExistingTables, $prefix);
                    throw $th;
                }
                mysqli_commit($link);
                mysqli_autocommit($link, true);

                if (!empty($_GET['debug']) || $this->wiki->config['debug'] == 'yes') {
                    if (function_exists('flash')) {
                        flash($sqlReport, 'success');
                    } else {
                        $this->wiki->SetMessage($sqlReport);
                    }
                }

                if ($_POST['yeswiki-farm-model'] != 'default-content') {
                    // copy model files
                    $modelFiles = 'custom/wiki-models/' . $_POST['yeswiki-farm-model'] . '/files';
                    if (is_dir($modelFiles)) {
                        $this->copyRecursive($modelFiles, $destfolder . 'files');
                    }

                    // copy model custom files
                    $modelCustomFiles = 'custom/wiki-models/' . $_POST['yeswiki-farm-model'] . '/custom';
                    if (is_dir($modelCustomFiles)) {
                        $this->copyRecursive($modelCustomFiles, $destfolder . 'custom');
                    }
                }

                if (!empty($entry["access-username"])) {
                    $this->wiki->Query("INSERT INTO `{$prefix}__users` " .
                        "(`name`, `password`, `email`, `motto`, `revisioncount`, `changescount`, `doubleclickedit`, `signuptime`, `show_comments`) " .
                        "VALUES ('" . mysqli_real_escape_string($link, $entry["access-username"]) . "', " .
                        "md5('" . mysqli_real_escape_string($link, $entry["access-password"]) . "'), " .
                        "'" . $entry[$fieldName . '_email'] . "', '', '20', '50', 1, now(), 2);");
                }

                if (!empty($entry["yeswiki-farm-options"])) {
                    $taboptions = explode(',', $entry["yeswiki-farm-options"]);
                    foreach ($taboptions as $option) {
                        $this->wiki->Query('UPDATE `' . $prefix . '__pages` SET body=CONCAT(body, "' . $this->wiki->config['yeswiki-farm-options'][$option]['content'] . '") WHERE tag="' . $this->wiki->config['yeswiki-farm-options'][$option]['page'] . '" AND latest="Y";');
                    }
                }
            } else {
                throw new \Exception('Le dossier ' . $this->wiki->config['yeswiki-farm-root-folder']
                    . ' n\'est pas accessible en écriture');
            }
        }

        // creation d'un groupe et ajout des membres
        if (isset($this->wiki->config['yeswiki-farm-group']) && is_array($this->wiki->config['yeswiki-farm-group'])) {
            // generation du prefixe
            $tripletable = $this->wiki->config['yeswiki-farm-prefix'] . str_replace('-', '_', $entry[$fieldName]) . '__triples';

            // on efface les anciennes valeurs du groupe
            $remsql = 'DELETE FROM `' . $tripletable
                . '` WHERE `resource`="ThisWikiGroup:' . $this->wiki->config['yeswiki-farm-group']['groupname']
                . '" and `property`="http://www.wikini.net/_vocabulary/acls";';
            $this->wiki->Query($remsql);

            // on ajoute les nouvelles valeurs du groupe
            $users = $entry[$this->wiki->config['yeswiki-farm-group']['group_members_field']];
            $addsql = 'INSERT INTO `' . $tripletable . '` (`resource`, `property`, `value`)'
                . ' VALUES (\'ThisWikiGroup:' . $this->wiki->config['yeswiki-farm-group']['groupname'] . '\','
                . ' \'http://www.wikini.net/_vocabulary/acls\', \'' . implode("\n", explode(',', $users)) . '\');';
            $this->wiki->Query($addsql);
        }
    }

    private function resetSQLTransactionWhenError($link, $notExistingTables, $prefix)
    {
        mysqli_rollback($link);
        mysqli_autocommit($link, true);
        foreach ($notExistingTables as $tableName) {
            try {
                if (
                    mysqli_num_rows(mysqli_query($link, "SHOW TABLES LIKE \"$prefix$tableName\";")) !== 0
                    && mysqli_num_rows(mysqli_query($link, "SELECT * FROM `$prefix$tableName`;")) === 0
                ) {
                    mysqli_query($link, "DROP TABLE IF EXISTS `$prefix$tableName`;");
                }
            } catch (\Throwable $th2) {
            }
        }
    }

    public function updateWiki($wiki)
    {
        $output = '';
        $srcfolder = getcwd() . DIRECTORY_SEPARATOR;
        if ($this->wiki->config['yeswiki-farm-root-folder'] == '.') {
            $destfolder = realpath(getcwd() . DIRECTORY_SEPARATOR . $wiki) . DIRECTORY_SEPARATOR;
        } else {
            $destfolder = realpath(getcwd() . DIRECTORY_SEPARATOR
                . $this->wiki->config['yeswiki-farm-root-folder'] . DIRECTORY_SEPARATOR
                . $wiki) . DIRECTORY_SEPARATOR;
        }

        include_once $destfolder . 'wakka.config.php';
        $output .=  '<div class="alert alert-info">' . _t('FERME_UPDATING') . $wiki . '.</div>';

        // nettoyage des anciens tools non utilises TODO : make a migration
        $oldFoldersToDelete = ['tools/despam', 'tools/hashcash', 'tools/ipblock', 'tools/nospam'];
        foreach ($oldFoldersToDelete as $folderToDelete) {
            if (is_dir($destfolder . $folderToDelete)) {
                $this->rrmdir($destfolder . $folderToDelete);
            }
        }

        // mise a jour des fichiers de YesWiki qui ne sont pas des symlink
        foreach ($this->wiki->config['yeswiki_files'] as $file) {
            if (!in_array($file, $this->wiki->config['yeswiki_symlinked_files'])) {
                if (
                    file_exists($destfolder . $file)
                    && !in_array($file, $this->wiki->config['yeswiki_empty_folders'])
                ) {
                    $this->rrmdir($destfolder . $file);
                }
                $this->copyRecursive($srcfolder . $file, $destfolder . $file);
            }
        }
        // mise a jour des fichiers de YesWiki qui ne sont pas des symlink
        foreach ($this->wiki->config['yeswiki_symlinked_files'] as $file) {
            if (
                file_exists($destfolder . $file)
                && !in_array($file, $this->wiki->config['yeswiki_empty_folders'])
            ) {
                $this->rrmdir($destfolder . $file);
            }
            symlink($srcfolder . $file, $destfolder . $file);
        }

        // change the config file to update yeswiki version
        include_once 'tools/templates/libs/Configuration.php';
        $config = new \Configuration($destfolder . 'wakka.config.php');
        $config->load();
        $config->yeswiki_version = $this->wiki->config['yeswiki_version'];
        $config->yeswiki_release = $this->wiki->config['yeswiki_release'];
        $config->write();

        // execute post update
        $output .= 'cd ' . $destfolder . ';chmod +x tools/autoupdate/commands/console;tools/autoupdate/commands/console update:postupdate 2>&1';
        $output .= shell_exec('cd ' . $destfolder . ';chmod +x tools/autoupdate/commands/console;tools/autoupdate/commands/console update:postupdate 2>&1');

        $output .=  '<div class="alert alert-success">' . _t('FERME_WIKI') . $wiki . _t('FERME_UPDATED') . '</div>';
        return $output;
    }

    public function deleteWikiFromEntry($id)
    {
        $userCanDelete = $this->wiki->UserIsAdmin() || $this->wiki->UserIsOwner();
        $entryManager = $this->wiki->services->get(EntryManager::class);
        if ($entryManager->isEntry($id) && $userCanDelete) {
            try {
                if ($this->wiki->services->get(CsrfTokenController::class)->checkToken('main', 'POST', 'csrf-token', false)) {

                    $tab_valeurs = $entryManager->getOne($id);
                    if (isset($tab_valeurs["bf_dossier-wiki"]) && !empty($tab_valeurs["bf_dossier-wiki"])) {
                        $src = realpath(getcwd() . '/' . (!empty($this->wiki->config['yeswiki-farm-root-folder']) ? $this->wiki->config['yeswiki-farm-root-folder'] : '.') . '/' . $tab_valeurs["bf_dossier-wiki"]);
                        if (is_dir($src)) {
                            // get the table prefix from the real config file, it's more secure
                            $config = $this->getWikiConfig($tab_valeurs["bf_dossier-wiki"]);
                            // supprimer le wiki
                            $this->rrmdir($src);
                            // supprime les tables mysql
                            $prefix = $config['table_prefix'];
                            $query = 'DROP TABLE `' . $prefix . 'acls`, `' . $prefix . 'links`, `' . $prefix . 'nature`, `' . $prefix . 'pages`, `' . $prefix . 'referrers`, `' . $prefix . 'triples`, `' . $prefix . 'users`;';
                            $this->wiki->Query($query);
                        }
                    }
                }
            } catch (Throwable $th) {
                exit('No CSRF token'); // do nothing
            }
        }
    }

    public function getWikiList()
    {
        $entryManager = $this->wiki->services->get(EntryManager::class);
        $bazarFarmId = $this->params->get('bazar_farm_id');
        // check id if wakka.config.php contains a bad value (like string not corresponding to a form's id)
        $bazarFarmId = (!empty($bazarFarmId) && (strval($bazarFarmId) == strval(intval($bazarFarmId)))) ? $bazarFarmId : '1100';
        $fiches = $entryManager->search([
            'formsIds' => [$bazarFarmId]
        ]);
        $GLOBALS['ordre'] = 'asc';
        $GLOBALS['champ'] = 'bf_titre';
        usort($fiches, 'champCompare');

        foreach ($fiches as $i => $fiche) {
            $wakkaConfig = array();
            if ($this->wiki->config['yeswiki-farm-root-folder'] == '.') {
                $wikiConfigFile = realpath(getcwd() . '/' . $fiche['bf_dossier-wiki'] . '/wakka.config.php');
            } else {
                $wikiConfigFile = realpath(getcwd() . '/' . $this->wiki->config['yeswiki-farm-root-folder'] . '/' . $fiche['bf_dossier-wiki'] . '/wakka.config.php');
            }
            if (file_exists($wikiConfigFile)) {
                include $wikiConfigFile;
                if (!empty($wakkaConfig['table_prefix'])) {
                    $fiche['url'] = $wakkaConfig['base_url'] . $wakkaConfig['root_page'];

                    $fiche['version'] = empty($wakkaConfig['yeswiki_version']) ? (empty($wakkaConfig['yeswiki_release']) ? '' : 'Inconnue') : $wakkaConfig['yeswiki_version'];
                    $fiche['version'] .= !empty($fiche['version']) ? '<br />' : '';

                    $fiche['version'] .= (empty($wakkaConfig['yeswiki_release']) ? 'Inconnue' : $wakkaConfig['yeswiki_release']);
                    if ($this->wiki->config['yeswiki_version'] !== $wakkaConfig['yeswiki_version']) {
                        $fiche['version'] .= '<br /><i>version principale différente du wiki source</i>';
                    } elseif (empty($wakkaConfig['yeswiki_release']) || ($wakkaConfig['yeswiki_release'] < $this->wiki->config['yeswiki_release'])) {
                        $fiche['version'] .= '<br /><a class="btn btn-xs btn-danger" href="' . $this->wiki->href('', $this->wiki->GetPageTag(), 'maj=' . $fiche['bf_dossier-wiki']) . '">Mettre à jour vers ' . $this->wiki->config['yeswiki_version'] . '</a>';
                    } else {
                        $fiche['version'] .= '<br /><i>à jour avec le wiki source</i>';
                    }

                    // we use database name of local wiki
                    $sql =  'USE ' . $wakkaConfig['mysql_database'] . ';';
                    $this->wiki->query($sql);

                    // test de la presence d'un admin pour la ferme
                    if (!empty($this->wiki->config['yeswiki-farm-admin-name']) and !empty($this->wiki->config['yeswiki-farm-admin-pass'])) {
                        $sql = 'SELECT name FROM ' . $wakkaConfig['table_prefix'] . 'users WHERE name="' . $this->wiki->config['yeswiki-farm-admin-name'] . '"';
                        $userresults = $this->wiki->LoadAll($sql);
                        if (count($userresults) > 0) {
                            $text = ' présent <a class="btn btn-xs btn-danger" href="' . $this->wiki->href('', $this->wiki->GetPageTag(), 'nosuperadmin=' . $fiche['bf_dossier-wiki']) . '">supprimer le compte</a>';
                        } else {
                            $text = ' absent <a class="btn btn-xs btn-success" href="' . $this->wiki->href('', $this->wiki->GetPageTag(), 'superadmin=' . $fiche['bf_dossier-wiki']) . '">ajouter le compte</a>';
                        }
                        $fiche['admin'] = $this->wiki->config['yeswiki-farm-admin-name'] . $text;
                    }

                    // last modified time
                    $sql = 'SELECT time FROM `' . $wakkaConfig['table_prefix'] . 'pages` WHERE latest="Y" ORDER BY time DESC LIMIT 1';
                    $wikiresults = $this->wiki->LoadAll($sql);
                    $fiche['last_modification_iso'] = $wikiresults[0]['time'];
                    $date = new \DateTime($wikiresults[0]['time']);
                    $fiche['last_modification'] = $date->format('d.m.Y H:i:s');
                    $fiche['dashboard_link'] = $wakkaConfig['base_url'] . 'TableauDeBord';

                    // we go back to main wiki database
                    $sql =  'USE ' . $this->wiki->config['mysql_database'] . ';';
                    $this->wiki->query($sql);
                }
                $fiches[$i] = $fiche;
            } else {
                $fiche['error'] = '<div class="alert alert-danger">' . _t('FERME_FILE') . $fiche['bf_dossier-wiki'] . '/wakka.config.php' . _t('FERME_NOT_FOUND') . '</div>';
                $fiches[$i] = $fiche;
            }
        }
        return $fiches;
    }

    /**
     * recursive remove file or folder
     *
     * @param string $src path
     * @return void
     */
    public function rrmdir($src)
    {
        $dir = opendir($src);
        if ($dir) {
            while (false !== ($file = readdir($dir))) {
                if (($file != '.') && ($file != '..')) {
                    $full = $src . '/' . $file;
                    if (is_dir($full)) {
                        $this->rrmdir($full);
                    } else {
                        unlink($full);
                    }
                }
            }
            closedir($dir);
            rmdir($src);
        }
    }

    /**
     * recursive copy file or filder
     *
     * @param string $path : source path
     * @param string $dest : destination path
     * @return void
     */
    public function copyRecursive($path, $dest)
    {
        if (is_dir($path)) {
            @mkdir($dest, 0777, true);
            $objects = scandir($path);
            if (sizeof($objects) > 0) {
                foreach ($objects as $file) {
                    if ($file == "." || $file == ".." || $file == ".git" || $file == "bower_components") {
                        continue;
                    }
                    // go on
                    if (is_dir($path . DIRECTORY_SEPARATOR . $file)) {
                        $this->copyRecursive($path . DIRECTORY_SEPARATOR . $file, $dest . DIRECTORY_SEPARATOR . $file);
                    } else {
                        copy($path . DIRECTORY_SEPARATOR . $file, $dest . DIRECTORY_SEPARATOR . $file);
                    }
                }
            }
            return true;
        } elseif (is_file($path) && file_exists($path)) {
            return copy($path, $dest);
        } else {
            return false;
        }
    }

    /**
     * Returns the real path of given path even for non existent path, with trailing /
     *
     * @param string $path
     * @return string
     */
    public function getAbsolutePath($path)
    {
        $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
        $absolutes = array();
        foreach ($parts as $part) {
            if ('.' == $part) {
                continue;
            }
            if ('..' == $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }
        return DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $absolutes) . DIRECTORY_SEPARATOR;
    }

    /**
     * replace tokens in sql file and query sql
     * inspired from /setup/install.helpers.php ->querySqlFile()
     *
     * @param object $dblink mysqli link resource
     * @param string $sqlFile patho to sql file
     * @param array $replacements token to replace in sql file
     * @return string the report of the queries
     */
    public function querySqlFile($dblink, $sqlFile, $replacements = [])
    {
        $sqlReport = '<h4>' . _t('FERME_REPORT') . ' ' . $sqlFile . '</h4>';
        if ($sql = file_get_contents($sqlFile)) {
            foreach ($replacements as $keyword => $replace) {
                $sql = str_replace(
                    '{{' . $keyword . '}}',
                    mysqli_real_escape_string($dblink, $replace),
                    $sql
                );
            }
            // first statements
            $index = 1;
            if (!mysqli_multi_query($dblink, $sql)) {
                throw new \Exception(str_replace(
                    ['{num}', '{file}', '{errorMsg}'],
                    [$index, $sqlFile, mysqli_error($dblink)],
                    _t('FERME_INSERTION_ERROR')
                ));
            } else {
                $sqlReport .= str_replace(
                    ['{num}', '{nbRows}'],
                    [$index, mysqli_affected_rows($dblink)],
                    _t('FERME_INSERTION')
                ) . '<br/>';
                while (mysqli_more_results($dblink)) {
                    $index = $index + 1;
                    if (!mysqli_next_result($dblink)) {
                        throw new \Exception(str_replace(
                            ['{num}', '{file}', '{errorMsg}'],
                            [$index, $sqlFile, mysqli_error($dblink)],
                            _t('FERME_INSERTION_ERROR')
                        ));
                    } else {
                        $sqlReport .= str_replace(
                            ['{num}', '{nbRows}'],
                            [$index, mysqli_affected_rows($dblink)],
                            _t('FERME_INSERTION')
                        ) . '<br/>';
                    }
                }
            }
        } else {
            throw new \Exception(_t('SQL_FILE_NOT_FOUND') . ' "' . $sqlFile . '".');
        }
        return $sqlReport;
    }

    public function getModelLabels()
    {
        // get labels for models
        $models = [];
        foreach ($this->wiki->config['yeswiki-farm-models'] as $model) {
            if ($model != 'default-content') {
                $json = \json_decode(\file_get_contents('custom/wiki-models/' . $model . '/infos.json'), true);
            } else {
                $json = [];
                $json['label'] = _t('FERME_BASIC_INSTALL');
            }
            $models[$model] = $json['label'];
        }
        return $models;
    }
}
