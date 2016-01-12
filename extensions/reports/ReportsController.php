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

        // get query list
        $this->view->queries = $this->_privateConfig->queries;
    }

    public function completeAction()
    {
        if (!isset($this->_request->search))
            throw new OntoWiki_Component_Exception('No search parameter specified!');
        if (!isset($this->_request->query_id))
            throw new OntoWiki_Component_Exception('No query id specified!');
        if (!isset($this->_request->parameter_id))
            throw new OntoWiki_Component_Exception('No parameter id specified!');

        $search = $this->_request->search;
        $query_id = $this->_request->query_id;
        $parameter_id = $this->_request->parameter_id;
//        $search = 'AC';
//        $query_id = '0';
//        $parameter_id = '0';

        // find type
        $queries = $this->_privateConfig->queries->toArray();
        $type = $queries[$query_id]['parameter'][$parameter_id]['type'];

        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();
        $this->getResponse()->setHeader('Content-Type', 'application/json');

        $query = 'PREFIX populatedplaces: <http://dbpedia.org/ontology/PopulatedPlace/>
PREFIX yago: <http://dbpedia.org/class/yago/>
PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
PREFIX vs: <http://www.w3.org/2003/06/sw-vocab-status/ns#>
PREFIX vcard: <http://www.w3.org/2006/vcard/ns#>
PREFIX vann: <http://purl.org/vocab/vann/>
PREFIX umbel: <http://umbel.org/umbel/rc/>
PREFIX terms: <http://vocab.ub.uni-leipzig.de/terms/>
PREFIX sysont: <http://ns.ontowiki.net/SysOnt/>
PREFIX sysBase: <http://ns.ontowiki.net/SysBase/>
PREFIX sushi: <http://vocab.ub.uni-leipzig.de/sushi/>
PREFIX str: <http://exslt.org/strings>
PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
PREFIX sioct: <http://rdfs.org/sioc/types#>
PREFIX sioc: <http://rdfs.org/sioc/ns#>
PREFIX schema: <http://schema.org/>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX rdavocab: <http://rdvocab.info/>
PREFIX prov: <http://www.w3.org/ns/prov#>
PREFIX owl: <http://www.w3.org/2002/07/owl#>
PREFIX org: <http://www.w3.org/ns/org#>
PREFIX ns9: <http://purl.org/ontology/bibo/>
PREFIX ns8: <http://www.w3.org/ns/org#>
PREFIX ns7: <http://amsl.technology/consortial/Organisationen_foaf/>
PREFIX ns6: <http://purl.org/ontology/daia/>
PREFIX ns5: <http://purl.org/lobid/lv#>
PREFIX ns4: <http://ubl.amsl.technology/erm/Vertragsbasisdaten_konsortial/>
PREFIX ns3: <http://ubl.amsl.technology/erm/Vertragsbasisdaten/>
PREFIX ns2: <https://amsl.technology/consortial/>
PREFIX ns1: <http://vocab.ub.uni-leipzig.de/counter/>
PREFIX ns12: <http://vocab.ub.uni-leipzig.de/terms/>
PREFIX ns11: <http://rdaregistry.info/>
PREFIX ns10: <http://rdaregistry.info/Elements/u/>
PREFIX ns0: <http://vocab.ub.uni-leipzig.de/amsl/>
PREFIX lobid: <http://purl.org/lobid/lv#>
PREFIX lib: <http://purl.org/library/>
PREFIX grs: <http://www.georss.org/georss/>
PREFIX gnd: <http://d-nb.info/standards/elementset/gnd#>
PREFIX gml: <http://www.opengis.net/gml/>
PREFIX geonames: <http://www.geonames.org/ontology#>
PREFIX foaf: <http://xmlns.com/foaf/0.1/>
PREFIX doap: <http://usefulinc.com/ns/doap#>
PREFIX dnb_intern: <http://dnb.de/>
PREFIX dct: <http://purl.org/dc/terms/>
PREFIX dc: <http://purl.org/dc/elements/1.1/>
PREFIX dbpprop: <http://dbpedia.org/property/>
PREFIX dbpedia-owl: <http://dbpedia.org/ontology/>
PREFIX dbpedia-datatype: <http://dbpedia.org/datatype/>
PREFIX daia: <http://purl.org/ontology/daia/>
PREFIX counter: <http://vocab.ub.uni-leipzig.de/counter/>
PREFIX cc: <http://creativecommons.org/ns#>
PREFIX bibo: <http://purl.org/ontology/bibo/>
PREFIX amsl: <http://vocab.ub.uni-leipzig.de/amsl/>
PREFIX aiiso: <http://purl.org/vocab/aiiso/schema#>
PREFIX item: <http://ubl.amsl.technology/erm/resource/item/0a5456a018407ca6311b387454a04a5b/>
SELECT DISTINCT ?uri ?literal WHERE {?uri rdfs:label ?literal . ?uri a <' . $type . '> . FILTER (isURI(?uri) && isLITERAL(?literal) && REGEX(?literal, "' . $search . '", "i") && REGEX(?literal, "^.{1,150}$")) } LIMIT 15';

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