<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * History component controller.
 * 
 * @category   OntoWiki
 * @package    Extensions_Test
 * @author     Christoph Rieß <c.riess.dev@googlemail.com>
 * @copyright  Copyright (c) 2012, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class MetadatasourceserviceController extends OntoWiki_Controller_Component
{

    public function listAction()
    {
        // loading all known institutions for which queries are availbale at the moment
        $known_institutions = $this->_privateConfig->toArray()['institutions'];

        // getting the required parameters from the http request
        $institution = $this->getRequest()->getParam('institution');
        $pretty = false;
        if($this->getRequest()->getParam('pretty') == 'true'){
            $pretty = true;
        }

        // loading the predefined query which corresponds to the institution parameter
        // if no institution parameter was sent a standard query is selected
        if($institution != null){
            foreach($known_institutions as $predefined_institution=>$predefined_query){
                if($institution == $predefined_institution){
                    $query = $predefined_query;
                    break;
                }
            }
        }else{
            $query = $known_institutions['general'];
        }

        // Errorhandling -> if the user used an unknown abbreviation in the institution parameter she gets returned a text with the reason of failure and a list of abbreviations which are available at the moment.
        if(!isset($query)){
            $this->getResponse()->setHttpResponseCode(400);
            $abbreviations = array();
            foreach($known_institutions as $key=>$value){
                $abbreviations[] = $key;
            }
            $this->getResponse()->setException(new Exception('Dear client. You probably requested a meta data source using malformed parameters. Possible parameters are "institution" and "pretty" (both optional; pretty is referring to JSON-pretty-printing). A list with available abbreviations to be used with the institutions parameter is [' . implode(", ", $abbreviations) . '].'));
            return;
        }

        // querying the meta data sources with their collections
        $model = new Erfurt_Owl_Model('http://amsl.technology/discovery/');
        $query_results = $model->sparqlQuery($query);

        // reconstruct the result data structure from list to map
        $result = array();
        foreach($query_results as $key=>$value){
            $metadata_source = $value['source'];
            $metadata_collection = $value['collection'];
            if(isset($result[$metadata_source])){
                $result[$metadata_source][] = $metadata_collection;
            }else{
                $result[$metadata_source] = array($metadata_collection);
            }
        }

        // encode the results in json (pretty printed if necessary)
        $json = Zend_Json::encode($result);
        if($pretty){
            $json = Zend_Json::prettyPrint($json, array("indent" => " "));
        }

        // prepare json response
        $this->getHelper('Layout')
            ->disableLayout();
        $this->getHelper('ViewRenderer')
            ->setNoRender();
        $this->getResponse()
            ->setHeader('Content-Type', 'application/json');
        // write json to response
        echo $json;

        return;
    }


}