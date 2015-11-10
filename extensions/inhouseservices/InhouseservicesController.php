<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * Meta data source service controller.
 *
 * This controller provides information about meta data sources used by the system.
 *
 * @category   OntoWiki
 * @package    Extensions_Inhouseservices
 * @author     Reik Mueller
 * @copyright  Copyright (c) 2015, {@link http://amsl.technology/}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class InhouseservicesController extends OntoWiki_Controller_Component
{

    /**
     * This method returns the result of a predefined query. The query is defined in the config of this module
     * and associated with an identifier. The request contains of two parameter "action" and "pretty".
     * Action containing the identifier of the query to be executed and pretty whether the result should be formatted.
     * The other The output format is JSON. By default no formatting is done.
     *
     * @throws Zend_Controller_Response_Exception
     */
    public function listAction()
    {
        // getting the required parameters from the http request
        $action = $this->getRequest()->getParam('do');
        $pretty = false;
        if ($this->getRequest()->getParam('pretty') == 'true') {
            $pretty = true;
        }

        // loading the query associated with the user action
        $query = $this->_privateConfig->toArray()[$action];

        // querying the meta data sources with their collections
        $options = $this->_owApp->getConfig()->toArray()['store']['virtuoso'];
        $options['is_open_source_version'] = '1';
        $backend = new Erfurt_Store_Adapter_Virtuoso($options);
        $backend->init();
        $query_results = $backend->sparqlQuery($query);

        // encode the results in JSON (pretty printed if necessary)
        $json = Zend_Json::encode($query_results);
        if ($pretty) {
            $json = Zend_Json::prettyPrint($json, array("indent" => " "));
            $json = str_replace('\/','/', $json);
        }

        // prepare JSON response
        $this->getHelper('Layout')->disableLayout();
        $this->getHelper('ViewRenderer')->setNoRender();
        $this->getResponse()->setHeader('Content-Type', 'application/json');

        // write JSON to response
        echo $json;
        return;
    }
}