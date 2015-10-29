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
 * @package    Extensions_Metadatasourceservice
 * @author     Reik Mueller
 * @copyright  Copyright (c) 2015, {@link http://amsl.technology/}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class MetadatasourceserviceController extends OntoWiki_Controller_Component
{

    /**
     * This method returns a list of meta data sources. On every meta data source a list of meta data collections is
     * attached. The output format is JSON.
     *
     * The get-parameter 'institution' selects meta data information for the institution associated with it. If no
     * parameter is sent a general model will be delivered.
     * The get-parameter 'pretty' enables pretty printing JSON. Default is 'false';
     *
     * @throws Zend_Controller_Response_Exception
     */
    public function listAction()
    {
        // loading all known institutions for which queries are availbale at the moment
        $known_institutions = $this->_privateConfig->toArray()['institutions'];

        // getting the required parameters from the http request
        $institution = $this->getRequest()->getParam('institution');
        $pretty = false;
        if ($this->getRequest()->getParam('pretty') == 'true') {
            $pretty = true;
        }

        // loading the predefined query which corresponds to the institution parameter
        // if no institution parameter was sent a standard query is selected
        if ($institution != null) {
            foreach ($known_institutions as $predefined_institution => $predefined_query) {
                if ($institution == $predefined_institution) {
                    $query = $predefined_query;
                    break;
                }
            }
        } else {
            $query = $known_institutions['general'];
        }

        // Errorhandling -> if the user used an unknown abbreviation in the institution parameter she gets returned a
        // text with the reason of failure and a list of abbreviations which are available at the moment.
        if (!isset($query)) {
            $this->getResponse()->setHttpResponseCode(400);
            $abbreviations = array();
            foreach ($known_institutions as $key => $value) {
                $abbreviations[] = $key;
            }
            $this->getResponse()->setException(new Exception('Dear client. You probably requested a meta data source using malformed parameters. Possible parameters are "institution" and "pretty" (both optional; pretty is referring to JSON-pretty-printing). A list with available abbreviations to be used with the institutions parameter is [' . implode(", ", $abbreviations) . '].'));
            return;
        }

        // querying the meta data sources with their collections
        $options = $this->_owApp->getConfig()->toArray()['store']['virtuoso'];
        $options['is_open_source_version'] = '1';
        $backend = new Erfurt_Store_Adapter_Virtuoso($options);
        $backend->init();
        $query_results = $backend->sparqlQuery($query);

        // reconstruct the result data structure from list to map
        $result = array();
        foreach ($query_results as $key => $value) {
            $metadata_source = $value['source'];
            $metadata_collection = $value['collection'];

            if (isset($result[$metadata_source])) {
                $result[$metadata_source][] = $metadata_collection;
            } else {
                $result[$metadata_source] = array($metadata_collection);
            }
        }

        // encode the results in JSON (pretty printed if necessary)
        $json = Zend_Json::encode($result);
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