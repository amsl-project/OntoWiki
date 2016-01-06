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

    private function runQuery($id, $parameter = NULL)
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
                $query->query = str_replace('$$' . $param_id . '$$', $value, $query->query);
            }
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
    }

    private function checkAuth()
    {
        $user = $this->_owApp->getUser();
        $allowed_users = $this->_privateConfig->allowed_users;
        $allowed_users = $allowed_users ? $allowed_users->toArray() : [];

        // access for dba as always allowed
        if ($user->isDbUser())
            return true;

        if (!in_array($user->getUri(), $allowed_users)) {
            $this->_response->setHttpResponseCode(403)->setBody('forbidden');
            $this->getHelper('Layout')->disableLayout();
            $this->getHelper('ViewRenderer')->setNoRender();
            return false;
        }

        return true;
    }

    public function reportAction()
    {
        if (!$this->checkAuth())
            return;

        if (!isset($this->_request->queryID))
            throw new OntoWiki_Component_Exception('No query id specified!');

        // get format
        if (isset($this->_request->html_format))
            $format = 'text/html';
        else if (isset($this->_request->csv_format))
            $format = 'text/csv';
        else
            throw new OntoWiki_Component_Exception("Report format not specified or not allowed!");

        try {
            $result = $this->runQuery($this->_request->queryID, $this->_request->parameter);
        }
        catch (Exception $e) {
            $this->view->error = $e->getMessage();
            $this->view->data = '';
            $this->view->header = '';
            return;
        }

        // extra view for empty results
        if (!$result) {
            $this->view->placeholder('main.window.title')->set($this->_owApp->translate->_('No Results!'));
            $this->_owApp->getNavigation()->disableNavigation();
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

    public function showAction()
    {
        if (!$this->checkAuth())
            return;

        $this->view->placeholder('main.window.title')->set($this->_owApp->translate->_('Standard Reports'));
        $this->_owApp->getNavigation()->disableNavigation();

        $this->view->headLink()->appendStylesheet(
            $this->_componentUrlBase . 'resources/css/reports.css'
        );

        // get query list
        $this->view->queries = $this->_privateConfig->queries;
    }
}