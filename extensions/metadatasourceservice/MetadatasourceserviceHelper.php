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
class MetadatasourceserviceHelper extends OntoWiki_Component_Helper
{
    public function init()
    {
        OntoWiki::getInstance()->getNavigation()->register(
            'test',
            array(
                'controller' => 'Metadatasourceservice',        // test controller
                'action'     => 'list',        // list action
                'name'       => 'Metadatasourceservice',
                'priority'   => 30
            )
        );
    }
}

