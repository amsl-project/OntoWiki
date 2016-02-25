<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * Helper class for the meta-data component.
 *
 * - register the tab for all navigations except the instances list
 *   (this should be undone if the history can be created from a Query2 too)
 *
 * @category   OntoWiki
 * @package    Extensions_Metadatasourceservice
 * @author     Reik Mueller
 * @copyright  Copyright (c) 2015, {@link http://amsl.technology/}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class ChronjobsHelper extends OntoWiki_Component_Helper
{
    public function init()
    {
        $user = $this->_owApp->getUser();
        $allowed_users = $this->_privateConfig->allowed_users;
        $allowed_users = $allowed_users && $allowed_users instanceof Zend_Config ? $allowed_users->toArray() : [];

        // don't add menu entry for non-authorized users
//        if (!$user->isDbUser() && !in_array($user->getUri(), $allowed_users)) {
//            return;
//        }

        // register with extras menu
        $translate  = $this->_owApp->translate;
        $url        = new OntoWiki_Url(array('controller' => 'chronjobs', 'action' => 'run'));
        $extrasMenu = OntoWiki_Menu_Registry::getInstance()->getMenu('application')->getSubMenu('Extras');
        $extrasMenu->setEntry($translate->_('ChronJobs'), (string)$url);
    }
}

