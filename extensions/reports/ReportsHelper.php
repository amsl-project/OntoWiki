<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @category   OntoWiki
 * @package    Extensions_Reports
 * @author     Gregor Tätzner
 * @copyright  Copyright (c) 2015, {@link http://amsl.technology/}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

class ReportsHelper extends OntoWiki_Component_Helper
{
    public function init()
    {
        $user = $this->_owApp->getUser();
        $allowed_users = $this->_privateConfig->allowed_users;
        $allowed_users = $allowed_users && $allowed_users instanceof Zend_Config ? $allowed_users->toArray() : [];

        // don't add menu entry for non-authorized users
        if (!$user->isDbUser() && !in_array($user->getUri(), $allowed_users)) {
            return;
        }

        // register with extras menu
        $translate  = $this->_owApp->translate;
        $url        = new OntoWiki_Url(array('controller' => 'reports', 'action' => 'show'));
        $extrasMenu = OntoWiki_Menu_Registry::getInstance()->getMenu('application')->getSubMenu('Extras');
        $extrasMenu->setEntry($translate->_('Standard Reports'), (string)$url);
    }
}
