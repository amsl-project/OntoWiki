<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2012, {@link http://aksw.org AKSW}
 * @license   http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

require_once 'OntoWiki/Plugin.php';

/**
 * @category   OntoWiki
 * @package    Extensions_Selectlanguage
 *
 * http://localhost/OntoWiki-link/model/info/?archive=false
 */
class ArchivePlugin extends OntoWiki_Plugin
{
    protected $_config = null;
    protected $_supportedLanguages = null;
    public $owApp;

    public function init()
    {
        $this->_config = $this->_privateConfig;
        $this->owApp = OntoWiki::getInstance();
    }

    public function onBeforeInitController()
    {
        $config = $this->_privateConfig->toArray();
        if(filter_var($config['archiveEndpoint'], FILTER_VALIDATE_URL)) {
            $translate = $this->owApp->translate;
            $translate->addTranslation(
                $this->_pluginRoot . 'languages',
                null,
                array('scan' => Zend_Translate::LOCALE_FILENAME)
            );
            $locale = $this->owApp->getConfig()->languages->locale;
            $translate->setLocale($locale);

            $request = new OntoWiki_Request();
            $getRequest = $request->getRequestUri();

            $extrasMenu = OntoWiki_Menu_Registry::getInstance()->getMenu('application')->getSubMenu('Extras');

            if (!array_key_exists('archive', $_SESSION['ONTOWIKI'])) {
                $_SESSION['ONTOWIKI']['archive'] = '';
            }
            if ($_SESSION['ONTOWIKI']['archive'] == '') {
                $lanMenuEntry = $translate->_('Enable Archive Mode');
                if (strpos($getRequest, '/?') != false) {
                    $getRequest = str_replace("&archive=false", '', $getRequest);
                    $getRequest = $getRequest . '&archive=true';
                } else {
                    $getRequest = str_replace("?archive=false", '', $getRequest);
                    $getRequest = $getRequest . '?archive=true';
                }
            } else {
                $lanMenuEntry = $translate->_('Disable Archive Mode');
                if (strpos($getRequest, '/?') != false) {
                    $getRequest = str_replace("&archive=true", '', $getRequest);
                    $getRequest = $getRequest . '&archive=false';
                } else {
                    $getRequest = str_replace("?archive=true", '', $getRequest);
                    $getRequest = $getRequest . '?archive=false';
                }
            }
            $extrasMenu->setEntry($lanMenuEntry, $getRequest);
        }
    }

    public function onPostBootstrap($event)
    {
        $request = new OntoWiki_Request();
        $requestedLanguage = $request->getParam("archive");
        if ($requestedLanguage == 'true') {

            $config = $this->_privateConfig->toArray();
            $_SESSION['ONTOWIKI']['archive'] = $config['archiveEndpoint'];
        } elseif ($requestedLanguage == 'false') {
            $_SESSION['ONTOWIKI']['archive'] = '';
        }

        $request = new OntoWiki_Request();
        $this->view->requestURI = $request->getRequestUri();
    }
}
