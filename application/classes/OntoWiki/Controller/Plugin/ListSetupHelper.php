<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2006-2013, {@link http://aksw.org AKSW}
 * @license   http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * ListSetupHelper handles list.
 * reacts on parameters prior ComponentHelper instantiation
 *
 * @category  OntoWiki
 * @package   OntoWiki_Classes_Controller_Plugin
 * @copyright Copyright (c) 2012, {@link http://aksw.org AKSW}
 * @license   http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 * @author    Jonas Brekle <jonas.brekle@gmail.com>
 */
class OntoWiki_Controller_Plugin_ListSetupHelper extends Zend_Controller_Plugin_Abstract
{
    protected $_isSetup = false;

    /**
     * RouteStartup is triggered before any routing happens.
     */
    public function routeStartup(Zend_Controller_Request_Abstract $request)
    {
        /**
         * @trigger onRouteStartup
         */
        $event = new Erfurt_Event('onRouteStartup');
        $event->trigger();
    }

    /**
     * RouteShutdown is the earliest event in the dispatch cycle, where a
     * fully routed request object is available
     */
    public function routeShutdown(Zend_Controller_Request_Abstract $request)
    {
        if (isset($request->noListRedirect)) {
            return;
        }

        $ontoWiki = OntoWiki::getInstance();

        // TODO: Refactor! The list helper is from an extension! Do not access extensions
        // from core code!
        if (!Zend_Controller_Action_HelperBroker::hasHelper('List')) {
            return;
        }

        $listHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('List');
        // only once and only when possible
        if (!$this->_isSetup
            && $ontoWiki->selectedModel != null
            && (isset($request->init)
            || isset($request->instancesconfig)
            || isset($request->s)
            || isset($request->class)
            || isset($request->p)
            || isset($request->limit))
        ) {
            $frontController = Zend_Controller_Front::getInstance();
            $store           = $ontoWiki->erfurt->getStore();
            $resource        = $ontoWiki->selectedResource;
            $session         = $ontoWiki->session;

            // when switching to another class:
            // reset session vars (regarding the list)
            if (isset($request->init)) {
                //echo 'kill list session';
                // reset the instances object
                unset($session->instances);

                //reset config from tag explorer
                unset($session->cloudproperties);
            }

            //react on m parameter to set the selected model
            if (isset($request->m)) {

                try {
                    $model                   = $store->getModel($request->getParam('m', null, false));
                    $ontoWiki->selectedModel = $model;
                } catch (Erfurt_Store_Exception $e) {
                    $model                   = null;
                    $ontoWiki->selectedModel = null;
                }
            }

            $list = $listHelper->getLastList();

            if ((!isset($request->list)
                && $list == null)
                || isset($request->init)
            ) {
                // instantiate model, that selects all resources
                $list = new OntoWiki_Model_Instances($store, $ontoWiki->selectedModel, array(), $request->title);
            } else {
                // use the object from the session
                if (isset($request->list) && $request->list != $listHelper->getLastListName()) {
                    if ($listHelper->listExists($request->list)) {
                        $list = $listHelper->getList($request->list);
                        $ontoWiki->appendMessage(new OntoWiki_Message('reuse list'));
                    } else {
                        throw new OntoWiki_Exception(
                            'your trying to configure a list, but there is no list name specified'
                        );
                    }
                }

                $list->setStore($store); // store is not serialized in session! reset it
            }

            // load instances config
            $config = [];

            if (isset($request->instancesconfig)) {
                $config = json_decode($request->instancesconfig, true);
                if ($config === null) {
                    throw new OntoWiki_Exception('Invalid parameter instancesconfig (json_decode failed)');
                }
            }

            //a shortcut for search param
            if (isset($request->s)) {
                if (!isset($config['filter'])) {
                    $config['filter'] = array();
                }
                $config['filter'][] = array(
                    'action'     => 'add',
                    'mode'       => 'search',
                    'searchText' => $request->s
                );
                $request->setParam('instancesconfig', json_encode($config));
            }
            //a shortcut for class param
            if (isset($request->class)) {
                if (!isset($config['filter'])) {
                    $config['filter'] = array();
                }
                $config['filter'][] = array(
                    'action'    => 'add',
                    'mode'      => 'rdfsclass',
                    'rdfsclass' => $request->class
                );
                $request->setParam('instancesconfig', json_encode($config));
            }

            // check if we have the property configuration saved
            $modelIri = $ontoWiki->selectedModel->getModelIri();
            $listName = $list->getTitle();

            $session = new Zend_Session_Namespace('ONTOWIKI_USER_PROFILE');

            // retrieve session
            if (isset($session->config)) {
                $persConfig = $session->config;

                // set empty default config, if this list is not configured
                if (!array_key_exists($modelIri, $persConfig))
                    $persConfig[$modelIri] = [$listName => []];

                if (!array_key_exists($listName, $persConfig[$modelIri]))
                    $persConfig[$modelIri][$listName] = [];
            }
            else {
                $persConfig = [$modelIri => [$listName => []]];
            }

            $listConfig = $persConfig[$modelIri][$listName];

            // init list configuration with saved config
            if (array_key_exists('shownProperties', $listConfig) && !empty($listConfig['shownProperties']) && empty($list->getShownPropertiesPlain())) {
                $config['shownProperties'] = [];

                // mark following properties to be added
                foreach ($listConfig['shownProperties'] as $prop) {
                    $config['shownProperties'][] = ['uri' => $prop['uri'], 'label' => $prop['name'], 'inverse' => $prop['inverse'], 'action' => 'add'];
                }
            }

            if (array_key_exists('sort', $listConfig) && !empty($listConfig['sort']) && empty($list->getShownPropertiesPlain())) {
                $config['sort'] = $listConfig['sort'];
            }

            // check for change-requests
            if (!empty($config)) {
                if (isset($config['sort'])) {
                    if ($config['sort'] !== null)
                        $sortParam = $config['sort']['uri'];
                    $query = new Erfurt_Sparql_SimpleQuery();
                    $query->setProloguePart('SELECT DISTINCT ?range')
                        ->setWherePart('WHERE { <' . $sortParam . '> <http://www.w3.org/2000/01/rdf-schema#range> ?range . }');

                    $result = $store->sparqlQuery($query);
                    if(array_key_exists("0", $result)) {
                        if ($result[0]['range'] === "http://www.w3.org/2001/XMLSchema#integer" || $result[0]['range'] === "http://www.w3.org/2001/XMLSchema#decimal") {
                            $_SESSION['ONTOWIKI']['StringSort'] = false;
                        } else {
                            $_SESSION['ONTOWIKI']['StringSort'] = true;
                        }
                    }
                        $list->setOrderProperty($config['sort']['uri'], $config['sort']['asc']);

                    $listConfig['sort'] = $config['sort'];
                } else {
                 //   $listConfig['sort'] = [];
                    $listConfig['sort'] = array('uri' => 'http://www.w3.org/2000/01/rdf-schema#label' , 'asc' => 'true');
                    $list->setOrderProperty($listConfig['sort']['uri'], $listConfig['sort']['asc']);
                }

                if (isset($config['shownProperties'])) {

                    // add or remove property from list
                    foreach ($config['shownProperties'] as $prop) {
                        if ($prop['action'] == 'add') {
                            $list->addShownProperty($prop['uri'], $prop['label'], $prop['inverse']);
                        } else {
                            $list->removeShownProperty($prop['uri'], $prop['inverse']);
                        }
                    }

                    // get current list property configuration and persist it
                    $listConfig['shownProperties'] = $list->getShownPropertiesPlain();
                }

                if (isset($config['filter'])) {
                    foreach ($config['filter'] as $filter) {
                        // set default value for action and mode if they're not assigned
                        if (!isset($filter['action'])) {
                            $filter['action'] = 'add';
                        }
                        if (!isset($filter['mode'])) {
                            $filter['mode'] = 'box';
                        }

                        if ($filter['action'] == 'add') {

                            switch ($filter['mode']) {
                                case 'box':
                                    $list->addFilter(
                                        $filter['property'],
                                        isset($filter['isInverse']) ? $filter['isInverse'] : false,
                                        isset($filter['propertyLabel']) ? $filter['propertyLabel'] : 'defaultLabel',
                                        $filter['filter'],
                                        isset($filter['value1']) ? $filter['value1'] : null,
                                        isset($filter['value2']) ? $filter['value2'] : null,
                                        isset($filter['valuetype']) ? $filter['valuetype'] : 'literal',
                                        isset($filter['literaltype']) ? $filter['literaltype'] : null,
                                        isset($filter['hidden']) ? $filter['hidden'] : false,
                                        isset($filter['id']) ? $filter['id'] : null,
                                        isset($filter['negate']) ? $filter['negate'] : false
                                    );
                                    break;
                                case 'search':
                                    $list->addSearchFilter(
                                        $filter['searchText'],
                                        isset($filter['id']) ? $filter['id'] : null
                                    );
                                    break;
                                case 'rdfsclass':
                                    $list->addTypeFilter(
                                        $filter['rdfsclass'],
                                        isset($filter['id']) ? $filter['id'] : null
                                    );
                                    break;
                                case 'cnav':
                                    $list->addTripleFilter(
                                        NavigationHelper::getInstancesTriples($filter['uri'], $filter['cnav']),
                                        isset($filter['id']) ? $filter['id'] : null
                                    );
                                    break;
                                case 'query':
                                    try {
                                        //echo $filter->query."   ";
                                        $query = Erfurt_Sparql_Query2::initFromString($filter['query']);
                                        // TODO what the hell is this?!
                                        if (!($query instanceof Exception)) {
                                            $list->addTripleFilter(
                                                $query->getWhere()->getElements(),
                                                isset($filter['id']) ? $filter['id'] : null
                                            );
                                        }
                                        //echo $query->getSparql();
                                    } catch (Erfurt_Sparql_ParserException $e) {
                                        $ontoWiki->appendMessage('the query could not be parsed');
                                    }
                                    break;
                                default:
                                    throw new OntoWiki_Exception('Invalid filter mode for list!');
                            }

                        } else {
                            $list->removeFilter($filter['id']);
                        }
                    }
                }

                if (isset($config['order'])) {
                    foreach ($config['order'] as $prop) {
                        if ($prop['action'] == 'set') {
                            if ($prop['mode'] == 'var') {
                                $list->setOrderVar($prop['var']);
                            } else {
                                $list->setOrderUri($prop['uri']);
                            }
                        }
                    }
                }
            }

            // persist config
            $persConfig[$modelIri][$listName] = $listConfig;
            $session->config = $persConfig;

            if (isset($request->limit)) { // how many results per page
                $list->setLimit($request->limit);
            } else {
                $list->setLimit(30);
            }
            if (isset($request->p)) { // p is the page number
                $list->setOffset(
                    ($request->p * $list->getLimit()) - $list->getLimit()
                );
            } else {
                $list->setOffset(0);
            }

            //save to session
            $name = (isset($request->list) ? $request->list : 'instances');
            $listHelper->updateList($name, $list, true);

            // avoid setting up twice
            $this->_isSetup = true;
            // redirect normal requests if config-params are given to a param-free uri
            // (so a browser reload by user does nothing unwanted)
            if (!$request->isXmlHttpRequest()) {
                //strip of url parameters that modify the list
                $url = new OntoWiki_Url(
                    array(),
                    null,
                    array('init', 'instancesconfig', 's', 'p', 'limit', 'class', 'list')
                );
                //redirect
                $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
                $redirector->gotoUrl($url);
            }
        }
    }
}
