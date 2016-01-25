<?php

/**
 * Reports controller.
 *
 *
 *
 * @category   OntoWiki
 * @package    Extensions_Reports
 * @author     Gregor Tätzner
 * @copyright  Copyright (c) 2015, {@link http://amsl.technology/}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class ReportsController extends OntoWiki_Controller_Component
{
    private $db_backend;
    private $allowed_formats = ['text/csv', 'text/html'];
    private $default_query_limit = 200;
    private $current_limit = NULL;

    public function init()
    {
        parent::init();

        // create virtuoso adapter
        $config = $this->_owApp->getConfig();
        if (!isset($config->store->virtuoso)) {
            require_once 'Erfurt/Exception.php';
            throw new Erfurt_Exception('This extension needs virtuoso database to run succesfully!');
        }
        $options = $config->store->virtuoso;
        $options->is_open_source_version = '1';
        $this->db_backend = new Erfurt_Store_Adapter_Virtuoso($options->toArray());
        $this->db_backend->init();
    }

    /**
     * Validate parameter - this is very critical to prevent sparql injections
     * For now we only allow urls and numeric types
     *
     * @param $value parameter value
     * @return bool
     */
    private function validate_parameter($value)
    {
        // first check if it's numeric
        if (is_numeric($value))
            return true;
        // it's an url?
        else if (filter_var($value, FILTER_VALIDATE_URL))
            return true;

        return false;
    }

    private function runQuery($id, $parameter = NULL, $limit)
    {
        $cfg = $this->_privateConfig;
        if (!isset($cfg->queries) || !isset($cfg->queries->$id))
            throw new OntoWiki_Component_Exception("Can't find query with id $id!");

        $query = $cfg->queries->$id;
        $this->view->query = $query;

        // check parameter
        if ($query->parameter) {
            if (!$parameter)
                throw new OntoWiki_Component_Exception("No Parameter provided for query $id!");

            foreach ($parameter as $param_id => $value) {
                if (!$value)
                    throw new OntoWiki_Component_Exception("Parameter $param_id is empty!");

                if (!$this->validate_parameter($value)) {
                    throw new OntoWiki_Component_Exception("Parameter $param_id has invalid value!");
                }

                $query->query = str_replace('$$' . $param_id . '$$', $value, $query->query);
            }
        }

        if ($limit) {
            $this->current_limit = $query->get('limit', $this->default_query_limit);
            $query->query .= " LIMIT $this->current_limit";
        }


        return $this->db_backend->sparqlQuery($query->query);
    }

    private function returnCSV($result)
    {
        $f = fopen('php://memory', 'r+');
        if ($f) {
            // print fields to first line
            fputcsv($f, array_keys($result[0]), ",");

            // print content
            foreach ($result as $line) {
                fputcsv($f, array_values($line), ",");
            }
            rewind($f);

            $this->getHelper('Layout')->disableLayout();
            $this->getHelper('ViewRenderer')->setNoRender();

            // write JSON to response
            echo stream_get_contents($f);
        }
    }

    private function returnHTML($result)
    {
        $header = array();
        if (is_array($result) && isset($result[0]) && is_array($result[0])) {
            $header = array_keys($result[0]);
        } else if (is_bool($result)) {
            $result = $result ? 'yes' : 'no';
        } else if (is_int($result)) {
            $result = (string)$result;
        }

        $this->view->data = $result;
        $this->view->header = $header;
        $this->view->limit = $this->current_limit;
    }

    private function checkAuth($queryID = null, $set_response = true)
    {
        $user = $this->_owApp->getUser();
        // check global access list
        $allowed_users = $this->_privateConfig->allowed_users;

        // global access list can be overridden by query based access list
        if ($queryID !== null && isset($this->_privateConfig->queries->$queryID->allowed_users)) {
            $allowed_users = $this->_privateConfig->queries->$queryID->allowed_users;
        }

        // just check
        $allowed_users = $allowed_users ? $allowed_users->toArray() : [];

        // access for dba as always allowed
        if ($user->isDbUser())
            return true;

        if (!in_array($user->getUri(), $allowed_users)) {
            if ($set_response) {
                $this->_response->setHttpResponseCode(403)->setBody('forbidden');
                $this->getHelper('Layout')->disableLayout();
                $this->getHelper('ViewRenderer')->setNoRender();
            }
            return false;
        }

        return true;
    }

    public function reportAction()
    {
        if (!isset($this->_request->queryID))
            throw new OntoWiki_Component_Exception('No query id specified!');

        if (!$this->checkAuth($this->_request->queryID))
            return;

        // get format
        if (isset($this->_request->html_format))
            $format = 'text/html';
        else if (isset($this->_request->csv_format))
            $format = 'text/csv';
        else
            throw new OntoWiki_Component_Exception("Report format not specified or not allowed!");

        // HACK limit results to reduce response time for html view
        $limit = ($format === 'text/html');

        try {
            $result = $this->runQuery($this->_request->queryID, $this->_request->parameter, $limit);
        }
        catch (Exception $e) {
            $this->view->error = $e->getMessage();
            $this->view->data = '';
            $this->view->header = '';
            return;
        }

        $this->_owApp->getNavigation()->disableNavigation();
        $this->view->placeholder('main.window.title')->set($this->_owApp->translate->_('Report Results'));
        $this->view->headLink()->appendStylesheet($this->_componentUrlBase . 'resources/css/result.css');

        // extra view for empty results
        if (!$result) {
            $this->view->placeholder('main.window.title')->set($this->_owApp->translate->_('No Results!'));
            $this->view->no_results = true;
            return;
        }

        $this->getResponse()->setHeader('Content-Type', $format);

        switch ($format) {
            case 'text/csv':
                return $this->returnCSV($result);
            case 'text/html':
                return $this->returnHTML($result);
        }
    }

    public function listAction()
    {
        if (!$this->checkAuth())
            return;

        $this->view->placeholder('main.window.title')->set($this->_owApp->translate->_('Standard Reports'));
        $this->_owApp->getNavigation()->disableNavigation();

        $this->view->headLink()->appendStylesheet($this->_componentUrlBase . 'resources/css/reports.css');
        $this->view->headScript()->appendFile($this->_componentUrlBase . 'resources/js/handlebars-v4.0.5.js');
        $this->view->headScript()->appendFile($this->_componentUrlBase . 'resources/js/typeahead.bundle.js');
        $this->view->headScript()->appendFile($this->_componentUrlBase . 'resources/js/list.js');

        // get query list and filter queries this user is not allowed to run
        $queries = [];
        foreach ($this->_privateConfig->queries as $key => $value) {
            if ($this->checkAuth($key, false))
                $queries[$key] = $value;
        }
        $this->view->queries = $queries;
    }

    public function completeAction()
    {
        if (!isset($this->_request->search))
            throw new OntoWiki_Component_Exception('No search parameter specified!');
        if (!isset($this->_request->query_id))
            throw new OntoWiki_Component_Exception('No query id specified!');
        if (!isset($this->_request->parameter_id))
            throw new OntoWiki_Component_Exception('No parameter id specified!');

        if (!$this->checkAuth($this->_request->query_id))
            return;

        $search = $this->_request->search;
        $query_id = $this->_request->query_id;
        $parameter_id = $this->_request->parameter_id;

        // find type
        $queries = $this->_privateConfig->queries->toArray();
        $type = $queries[$query_id]['parameter'][$parameter_id]['type'];

        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();
        $this->getResponse()->setHeader('Content-Type', 'application/json');

        $query = '
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
SELECT DISTINCT ?uri ?literal WHERE { ?uri a <' . $type . '> .
{ ?uri rdfs:label ?literal } UNION
{ ?uri <http://www.w3.org/2006/vcard/ns#organization-name> ?literal } UNION
{ ?uri <http://xmlns.com/foaf/0.1/name> ?literal } UNION
{ ?uri <http://purl.org/dc/elements/1.1/title> ?literal }
FILTER (isURI(?uri) && isLITERAL(?literal) && REGEX(?literal, "' . $search . '", "i") && REGEX(?literal, "^.{1,150}$")) } LIMIT 15';

        $store = $this->_erfurt->getStore();
        $result = $store->sparqlQuery(
            $query,
            array(
                'result_format' => 'plain'
            )
        );

        $this->_response->setBody(json_encode($result));
    }
}