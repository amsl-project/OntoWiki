<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license   http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * @category   OntoWiki
 * @package    Extensions_Community
 * @author     Philipp Frischmuth <pfrischmuth@googlemail.com>
 * @author     Jonas Brekle <jonas.brekle@gmail.com>
 * @author     Natanael Arndt <arndtn@gmail.com>
 */
class CommunityController extends OntoWiki_Controller_Component
{
    /**
     * list comments
     */
    public function listAction()
    {
        $resourceString = $this->_request->getParam('base');
        $resource = new OntoWiki_Resource(urldecode($resourceString));
        $translate = $this->_owApp->translate;
        $singleResource = true;
        if ($this->_request->getParam('mode') === 'multi') {
            $windowTitle    = $translate->_('Discussion about elements of the list');
            $singleResource = false;
        } else {
            if ($resource->getTitle()) {
                $title = $resource->getTitle();
            } else {
                $title = OntoWiki_Utils::contractNamespace($resource->getIri());
            }
            $windowTitle = sprintf($translate->_('Discussion about %1$s'), $title);
        }

        $this->addModuleContext('main.window.community');
        $this->view->placeholder('main.window.title')->set($windowTitle);

        $limit = $this->_request->getParam('climit');
        if ($limit === null) {
            $limit = 10;
        }

        $helper = $this->_owApp->extensionManager->getComponentHelper('community');
        $comments = $helper->getList($this->view, $singleResource, $limit, $resource);
        if ($comments === null) {
            $this->view->infomessage = 'There are no discussions yet.';
        } else {
            $this->view->comments = $comments;
        }
    }

    /**
     * save a comment
     */
    public function commentAction()
    {
        if (!$this->_owApp->selectedModel->isEditable()) {
            throw new Erfurt_Ac_Exception("Access control violation. Model not editable.");
        }

        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout()->disableLayout();

        $user = $this->_owApp->getUser()->getUri();
        $date = date('c'); // xsd:datetime
        // $date  = date('Y-m-d\TH:i:s'); // xsd:dateTime

        $resource        = $this->getParam('base');
        $aboutProperty   = $this->_privateConfig->about->property;
        $creatorProperty = $this->_privateConfig->creator->property;
        $commentType     = $this->_privateConfig->comment->type;
        $contentProperty = $this->_privateConfig->content->property;
        $dateProperty    = $this->_privateConfig->date->property;
        $content         = $this->getParam('c');

        if (!empty($content)) {
            // make URI
            $commentUri = $this->_owApp->selectedModel->createResourceUri('Comment');

            // preparing versioning
            $versioning                = $this->_erfurt->getVersioning();
            $actionSpec                = array();
            $actionSpec['type']        = 110;
            $actionSpec['modeluri']    = (string)$this->_owApp->selectedModel;
            $actionSpec['resourceuri'] = $commentUri;

            $versioning->startAction($actionSpec);

            // insert comment
            $this->_owApp->selectedModel->addStatement(
                $commentUri,
                $aboutProperty,
                array('value' => $resource, 'type' => 'uri')
            );

            $this->_owApp->selectedModel->addStatement(
                $commentUri,
                EF_RDF_TYPE,
                array('value' => $commentType, 'type' => 'uri')
            );

            $this->_owApp->selectedModel->addStatement(
                $commentUri,
                $creatorProperty,
                array('value' => (string)$user, 'type' => 'uri')
            );

            $this->_owApp->selectedModel->addStatement(
                $commentUri, $dateProperty, array(
                                                 'value'    => $date,
                                                 'type'     => 'literal',
                                                 'datatype' => EF_XSD_NS . 'dateTime'
                                            )
            );
            $this->_owApp->selectedModel->addStatement(
                $commentUri, $contentProperty, array(
                                                    'value' => $content,
                                                    'type'  => 'literal'
                                               )
            );

            // stop Action
            $versioning->endAction();
        }
    }

    /**
     * rate a resource
     */
    public function rateAction()
    {
        if (!$this->_owApp->selectedModel->isEditable()) {
            require_once 'Erfurt/Ac/Exception.php';
            throw new Erfurt_Ac_Exception("Access control violation. Model not editable.");
        }

        $user = $this->_owApp->getUser()->getUri();
        $date = date('rating'); // xsd:datetime

        $resource        = (string)$this->_owApp->selectedResource;
        $aboutProperty   = $this->_privateConfig->about->property;
        $creatorProperty = $this->_privateConfig->creator->property;
        $ratingType      = $this->_privateConfig->rating->type;
        $noteProperty    = $this->_privateConfig->note->property;
        $dateProperty    = $this->_privateConfig->date->property;

        //get rating Value
        $ratingValue = $this->getParam('rating');

        if (!empty($ratingValue)) {

            $query = new Erfurt_Sparql_SimpleQuery();
            $model = OntoWiki::getInstance()->selectedModel;
            // $store    = $this->_erfurt->getStore();

            //query rating and creator of rating

            $query->setProloguePart(
                '
                prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
                prefix ns0: <http://rdfs.org/sioc/ns#>
                prefix ns1: <http://rdfs.org/sioc/types#>
                SELECT *'
            )->setWherePart(
                'where {
                    ?rating rdf:type ns1:Poll.
                    ?rating ns0:about <' . $this->_owApp->selectedResource . '>.
                    ?rating ns0:has_creator ?creator}'
            );

            $results = $model->sparqlQuery($query);

            if ($results) {

                $creatorExists = false;
                foreach ($results as $result) {

                    if ((string)$user == $result['creator']) {
                        $creatorExists = true;
                        $ratingNote    = $result['rating'];
                        break;
                    }
                }

                if ($creatorExists) {
                    $this->_owApp->selectedModel->deleteMatchingStatements($ratingNote, null, null, array());
                }
            }

            // make URI
            $ratingNoteUri = $this->_owApp->selectedModel->createResourceUri('Rating');

            // preparing versioning
            $versioning                = $this->_erfurt->getVersioning();
            $actionSpec                = array();
            $actionSpec['type']        = 110;
            $actionSpec['modeluri']    = (string)$this->_owApp->selectedModel;
            $actionSpec['resourceuri'] = $ratingNoteUri;

            $versioning->startAction($actionSpec);

            // create namespaces (todo: this should be based on used properties)
            $this->_owApp->selectedModel->getNamespacePrefix('http://rdfs.org/sioc/ns#');
            $this->_owApp->selectedModel->getNamespacePrefix('http://rdfs.org/sioc/types#');
            $this->_owApp->selectedModel->getNamespacePrefix('http://localhost/OntoWiki/Config/');

            // insert rating
            $this->_owApp->selectedModel->addStatement(
                $ratingNoteUri,
                $aboutProperty,
                array('value' => $resource, 'type' => 'uri')
            );

            $this->_owApp->selectedModel->addStatement(
                $ratingNoteUri,
                EF_RDF_TYPE,
                array('value' => $ratingType, 'type' => 'uri')
            );

            $this->_owApp->selectedModel->addStatement(
                $ratingNoteUri,
                $creatorProperty,
                array('value' => (string)$user, 'type' => 'uri')
            );

            $this->_owApp->selectedModel->addStatement(
                $ratingNoteUri, $dateProperty, array(
                                                    'value'    => $date,
                                                    'type'     => 'literal',
                                                    'datatype' => EF_XSD_NS . 'dateTime'
                                               )
            );
            $this->_owApp->selectedModel->addStatement(
                $ratingNoteUri, $noteProperty, array(
                                                    'value' => $ratingValue,
                                                    'type'  => 'literal'
                                               )
            );

            $cache = $this->_erfurt->getQueryCache();
            $ret   = $cache->cleanUpCache(array('mode' => 'uninstall'));

        }

        // stop Action
        $versioning->endAction();
    }

    /*
     * View for displaying extended information to last changes of a resource.
     */
    public function listlastchangesAction()
    {
        $this->versioning = $this->_erfurt->getVersioning();
        $this->model = $this->_owApp->selectedModel;
        $params = $this->_request->getParams();
        if (isset($params['page'])){
            $page = $params['page'];
        } else {
            $page = 1;
        }
        $this->results = $this->versioning->getHistoryForGraph($this->model->getModelIri(), $page);
        $titleHelper = new OntoWiki_Model_TitleHelper();
        $translate = $this->_owApp->translate;

        $userArray = $this->_erfurt->getUsers();
        foreach ($this->results as $key => $entry) {

            $this->results[$key]['url'] = $this->_config->urlBase . "view?r=" . urlencode($entry['resource']);
            $titleHelper->addResource($entry['resource']);

            if ($entry['useruri'] == $this->_erfurt->getConfig()->ac->user->anonymousUser) {
                $userArray[$entry['useruri']] = 'Anonymous';
            } else if ($entry['useruri'] == $this->_erfurt->getConfig()->ac->user->superAdmin) {
                $userArray[$entry['useruri']] = 'SuperAdmin';
            } else if (is_array($userArray[$entry['useruri']])) {
                if (isset($userArray[$entry['useruri']]['userName'])) {
                    $userArray[$entry['useruri']] = $userArray[$entry['useruri']]['userName'];
                } else {
                    $titleHelper->addResource($entry['useruri']);
                    $userArray[$entry['useruri']] = $titleHelper->getTitle($entry['useruri']);
                }
            }
        }

        $this->view->placeholder('main.window.title')->set($translate->_('Last changes'));
        $this->view->userArray = $userArray;
        $this->view->translate = $translate;
        $this->view->results = $this->results;
        $this->view->model = $this->model->getTitle();
        $this->view->titleHelper = $titleHelper;
        $this->view->page = $page;
        $this->_owApp->getNavigation()->disableNavigation();
    }
}
