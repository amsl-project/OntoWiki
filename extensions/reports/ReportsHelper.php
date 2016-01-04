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
    public function __construct()
    {
        $owApp = OntoWiki::getInstance();

        // register with extras menu
        $translate  = $owApp->translate;
        $url        = new OntoWiki_Url(array('controller' => 'reports', 'action' => 'show'));
        $extrasMenu = OntoWiki_Menu_Registry::getInstance()->getMenu('application')->getSubMenu('Extras');
        $extrasMenu->setEntry($translate->_('Standard Reports'), (string)$url);
    }
}
