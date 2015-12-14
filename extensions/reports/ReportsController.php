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

    private function runQuery($id)
    {
        $cfg = $this->_privateConfig;
        if (!isset($cfg->queries) || !isset($cfg->queries->$id))
            throw new OntoWiki_Component_Exception("Can't find query with id $id!");

        $query = $cfg->queries->$id;
        return $this->db_backend->sparqlQuery($query->query);
    }

    private function returnCSV($result)
    {
        $f = fopen('php://memory', 'r+');
        if ($f) {
            // print fields to first line
            fputcsv($f, array_keys($result[0]), "\t");

            // print content
            foreach ($result as $line) {
                fputcsv($f, array_values($line), "\t");
            }
            rewind($f);

            $this->getHelper('Layout')->disableLayout();
            $this->getHelper('ViewRenderer')->setNoRender();

            // write JSON to response
            echo stream_get_contents($f);
        }
    }

    public function reportAction()
    {
        if (!isset($this->_request->queryID))
            throw new OntoWiki_Component_Exception('No query id specified!');


        if (!isset($this->_request->format))
            $format = 'text/html';
        else
            $format = $this->_request->format;

        if (!in_array($format, $this->allowed_formats))
            throw new OntoWiki_Component_Exception("Format $format is not allowed!");

        $result = $this->runQuery($this->_request->queryID);

        // TODO empty result

        $this->getResponse()->setHeader('Content-Type', $format);

        switch ($format) {
            case 'text/csv':
                return $this->returnCSV($result);
            case 'text/html':
                throw new OntoWiki_Component_Exception("HTML format not implemented!");
        }
    }

    public function showAction()
    {
        $this->view->placeholder('main.window.title')->set($this->_owApp->translate->_('Standard Reports'));
        $this->_owApp->getNavigation()->disableNavigation();

        // get query list
        $queries = $this->_privateConfig->queries;
        $this->view->queries = $queries;
    }
}