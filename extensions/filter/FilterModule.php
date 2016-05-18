<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2012, {@link http://aksw.org AKSW}
 * @license   http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

require_once 'OntoWiki/Module.php';

/**
 * OntoWiki module – filter
 *
 * Add instance properties to the list view
 *
 * @category   OntoWiki
 * @package    Extensions_Filter
 * @author     Norman Heino <norman.heino@gmail.com>
 * @copyright  Copyright (c) 2012, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class FilterModule extends OntoWiki_Module
{
    protected $_instances = null;

    public function init()
    {
        $listHelper       = Zend_Controller_Action_HelperBroker::getStaticHelper('List');
        $this->_instances = $listHelper->getLastList();
    }


    public function getTitle()
    {
        return 'Filter';
    }

    public function getContents()
    {
        if (!($this->_instances instanceof OntoWiki_Model_Instances)) {
            return "Error: List not found";
        }

        $this->store       = $this->_owApp->erfurt->getStore();
        $this->model       = $this->_owApp->selectedModel;
        $this->titleHelper = new OntoWiki_Model_TitleHelper($this->_owApp->selectedModel);

        $this->view->headLink()->appendStylesheet($this->view->moduleUrl . 'resources/filter.css');
        //$this->view->headScript()->appendFile($this->view->moduleUrl . 'resources/jquery.dump.js');

        $this->view->properties        = $this->_instances->getAllPropertiesBySingleQuery(false);
        usort($this->view->properties, function($a, $b) {
            return strnatcasecmp($a['title'], $b['title']);
        });

        // Dear person who will work on that later:
        // The following line of code is replaced by the latter for good reasons.
        //
        // Background:
        // Retrieving inverse properties is done by loading all triples (concrete predicates ans objects only)
        // containing the actual subject as object where the set of different predicates is the set of inverse
        // properties of the current subject.
        //
        // Reason for change:
        // As the amount of triples for calculating inverse properties can be huge (15000 and more) this task is time
        // consuming. Since the value of that feature is yet not applicable to the user is is enough to comment it out.
        // More over processing is done on many pages even if the user does not want to use the module.
        //
        // This slows down the system significantly!!!
        // (more than 2min (in words "two minutes") of delay are overwhelming - and lead to time outs at the web-server)
        //
        // Advice for a possible refactoring:
        // The user might be more patient in waiting for system response if longer delays are rare and if she awaits
        // some unusual task to be done. Instead of pre-processing the data every time the module is shown using AJAX
        // while working with the module could be appreciated by the user.
        //

//        $this->view->inverseProperties = $this->_instances->getAllProperties(true);
        $this->view->inverseProperties = array();

        $this->view->actionUrl = $this->_config->staticUrlBase . 'index.php/list/';
        $this->view->s         = $this->_request->s;

        $this->view->filter = $this->_instances->getFilter();
        if (is_array($this->view->filter)) {
            foreach ($this->view->filter as $key => $filter) {
                switch ($filter['mode']) {
                    case 'box':
                        if ($filter['property']) {
                            $this->view->filter[$key]['property'] = trim($filter['property']);
                            $this->titleHelper->addResource($filter['property']);
                        }
                        if ($filter['valuetype'] == 'uri' && !empty($filter['value1'])) {
                            $this->titleHelper->addResource($filter['value1']);
                        }
                        if ($filter['valuetype'] == 'uri' && !empty($filter['value2'])) {
                            $this->titleHelper->addResource($filter['value2']);
                        }
                        break;
                    case 'rdfsclass':
                        $this->titleHelper->addResource($filter['rdfsclass']);
                        break;
                }
            }
        }

        $this->view->titleHelper = $this->titleHelper;

        $this->view->headScript()->appendFile($this->view->moduleUrl . 'resources/filter.js');

        $content = $this->render('filter/filter');

        return $content;
    }

    public function getMenu()
    {
        $edit = new OntoWiki_Menu();
        $edit->setEntry('Add', 'javascript:showAddFilterBox()')
            ->setEntry('Remove all', 'javascript:removeAllFilters()');

        $help = new OntoWiki_Menu();
        $help->setEntry('Toggle help', 'javascript:toggleHelp()');

        $main = new OntoWiki_Menu();
        $main->setEntry('Edit', $edit);
        $main->setEntry('Help', $help);

        return $main;
    }
}

