<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2012, {@link http://aksw.org AKSW}
 * @license   http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki module – linkinhere
 *
 * Add instance properties to the list view
 *
 * @category   OntoWiki
 * @package    Extensions_Resourcemodule
 * @author     Norman Heino <norman.heino@gmail.com>
 * @copyright  Copyright (c) 2012, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class LinkinghereModule extends OntoWiki_Module
{
    private $_predicates = null;

    /**
     * Constructor
     */
    public function init()
    {
        $query = new Erfurt_Sparql_SimpleQuery();

        $query->setProloguePart('SELECT DISTINCT ?subject ?uri')
            ->setWherePart(
                'WHERE {
                    ?subject ?uri <' . (string)$this->_owApp->selectedResource . '> .
                }'
            );

        $result           = $this->_owApp->selectedModel->sparqlQuery($query, array('result_format' => 'extended'));
        $_predicatesResult = array();

        if (isset($result['results']['bindings'])) {
            foreach ($result['results']['bindings'] as $row) {
                if ($row['subject']['type'] === 'uri') {
                    if(!isset($_predicatesResult[$row['uri']['value']])){
                        $_predicatesResult[$row['uri']['value']] = array();
                    }
                    $_predicatesResult[$row['uri']['value']][] = $row['subject']['value'];
                }
            }
        }
        $this->_predicates = $_predicatesResult;

        // I removed the isURI(?subject) here as well as the limit, since the query is way faster
        // without filter! We kick out bnodes manually!
    }

    public function getTitle()
    {
        return "Instances linking here";
    }

    public function shouldShow()
    {
        // show only if there are predicates
        if ($this->_predicates) {
            return true;
        } else {
            return false;
        }
    }

    public function getContents()
    {
        $titleHelper = new OntoWiki_Model_TitleHelper($this->_owApp->selectedModel);

        $results = false;

        $_predicates = $this->_predicates;
        $properties = array();
        $instances  = array();
        $url        = new OntoWiki_Url(array('route' => 'properties'), array('r'));

        $titleHelper->addResources($_predicates, 'uri');

        foreach ($_predicates as $predicate => $subjects1) {
            $predicateUri = $predicate;

            $url->setParam('r', $predicateUri, true); // create properties url for the relation
            $properties[$predicateUri]['uri']   = $predicateUri;
            $properties[$predicateUri]['url']   = (string)$url;
            $properties[$predicateUri]['title'] = $titleHelper->getTitle($predicateUri, $this->_lang);

            if (true) {
                $subjects = $subjects1;
                $results = true;

                // has_more is used for the dots
                if (count($subjects) > OW_SHOW_MAX) {
                    $properties[$predicateUri]['has_more'] = true;
                    $subjects                              = array_splice($subjects, 0, OW_SHOW_MAX);
                } else {
                    $properties[$predicateUri]['has_more'] = false;
                }
                $subs = array();
                foreach($subjects as $sub){
                    $subs[] = array("uri" => $sub);
                }
                $subjectTitleHelper = new OntoWiki_Model_TitleHelper($this->_owApp->selectedModel);
                $subjectTitleHelper->addResources($subjects);
                foreach ($subjects as $subject1) {
                    $subject = array();
                    $subjectUri       = $subject1;
                    $subject['title'] = $subjectTitleHelper->getTitle($subjectUri, $this->_lang);
                    $subject['uri'] = $subjectUri;
                    // set URL
                    $url->setParam('r', $subjectUri, true);
                    $subject['url'] = (string)$url;

                    if (array_key_exists($predicateUri, $instances)) {
                        if (!array_key_exists($subjectUri, $instances[$predicateUri])) {
                            $instances[$predicateUri][$subjectUri] = $subject;
                        }
                    } else {
                        $instances[$predicateUri] = array(
                            $subjectUri => $subject
                        );
                    }
                }
            }
        }

        $this->view->resource   = $this->_owApp->selectedResource;
        $this->view->properties = $properties;

        // sort instances list nicely
        foreach ($instances as &$instance) {
            usort($instance, function($a, $b) {
                return strnatcasecmp($a['title'], $b['title']);
            });
        }
        $this->view->instances  = $instances;

        if (!$results) {
            $this->view->message = 'No matches.';
        }

        return $this->render('linkinghere');
    }

    public function getStateId()
    {
        $id = $this->_owApp->selectedModel->getModelIri()
            . $this->_owApp->selectedResource;

        return $id;
    }
}


