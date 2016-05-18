<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2012, {@link http://aksw.org AKSW}
 * @license   http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki module – showproperties
 *
 * Add instance properties to the list view
 *
 * @category   OntoWiki
 * @package    Extensions_Listmodules
 * @author     Norman Heino <norman.heino@gmail.com>
 * @copyright  Copyright (c) 2012, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class ShowpropertiesModule extends OntoWiki_Module
{
    protected $_instances;

    public function init()
    {
        $listHelper       = Zend_Controller_Action_HelperBroker::getStaticHelper('List');
        $this->_instances = $listHelper->getLastList();
    }

    public function getTitle()
    {
        return 'Show Properties';
    }

    public function shouldShow()
    {
        return ($this->_instances instanceof OntoWiki_Model_Instances) && $this->_instances->hasData();
    }

    public function getContents()
    {
        $this->view->headScript()->appendFile($this->view->moduleUrl . 'showproperties.js');

        $allShownProperties     = $this->_instances->getShownPropertiesPlain();
        $shownProperties        = array();
        $shownInverseProperties = array();
        foreach ($allShownProperties as $prop) {
            if ($prop['inverse']) {
                $shownInverseProperties[] = $prop['uri'];
            } else {
                $shownProperties[] = $prop['uri'];
            }
        }
        $this->view->headScript()->appendScript(
            'var shownProperties = ' . json_encode($shownProperties) . ';
            var shownInverseProperties = ' . json_encode($shownInverseProperties) . ';'
        );

        $url = new OntoWiki_Url(array('controller' => 'resource', 'action' => 'instances'));
        $url->setParam(
            'instancesconfig', json_encode(
                array('filter' => array(array('id'    => 'propertyUsage', 'action' => 'add', 'mode' => 'query',
                                              'query' => (string)$this->_instances->getAllPropertiesQuery(false))))
            )
        );
        $url->setParam('init', true);
        $this->view->propertiesListLink = (string)$url;
        $url->setParam(
            'instancesconfig', json_encode(
                array('filter' => array(array('id'    => 'propertyUsage', 'action' => 'add', 'mode' => 'query',
                                              'query' => (string)$this->_instances->getAllPropertiesQuery(true))))
            )
        );
        $this->view->inversePropertiesListLink = (string)$url;

        if ($this->_privateConfig->filterhidden || $this->_privateConfig->filterlist) {
            $this->view->properties        = $this->filterProperties($this->_instances->getAllPropertiesBySingleQuery(false));
            $this->view->reverseProperties = $this->filterProperties($this->_instances->getAllProperties(true));
        } else {
            $this->view->properties        = $this->_instances->getAllPropertiesBySingleQuery(false);

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

//            $this->view->reverseProperties = $this->_instances->getAllProperties(true);
            $this->view->reverseProperties = array();
        }

        return $this->render('showproperties');

    }

    public function getStateId()
    {
        $id = $this->_owApp->selectedModel
            . $this->_owApp->selectedResource;

        return $id;
    }

    private function filterProperties($properties)
    {
        $uriToFilter        = array();
        $filteredProperties = array();

        if ($this->_privateConfig->filterhidden) {
            $store = $this->_owApp->erfurt->getStore();
            //query for hidden properties
            $query = new Erfurt_Sparql_SimpleQuery();
            $query->setProloguePart(
                'PREFIX sysont: <http://ns.ontowiki.net/SysOnt/>
                                     SELECT ?uri'
            )
                ->setWherePart('WHERE {?uri sysont:hidden \'true\'.}');
            $uriToFilter = $store->sparqlQuery($query);
        }

        if ($this->_privateConfig->filterlist) {
            //get properties to hide from privateconfig
            $toFilter = $this->_privateConfig->property->toArray();
            foreach ($toFilter as $element) {
                array_push($uriToFilter, array('uri' => $element));
            }
        }

        foreach ($properties as $property) {
            $toFilter = false;
            foreach ($uriToFilter as $element) {
                if ($element['uri'] == $property['uri']) {
                    $toFilter = true;
                    break;
                }
            }
            if (!$toFilter) {
                array_push($filteredProperties, $property);
            }
        }

        return $filteredProperties;
    }
}
