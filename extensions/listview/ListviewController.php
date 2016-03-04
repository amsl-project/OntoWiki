<?php
/**
 * Listexporter component controller. This controller is used for demonstration and export
 * purposes of the listexporter.
 *
 * This file is part of the {@link http://amsl.technology amsl} project.
 *
 * @author Sebastian Nuck
 * @copyright Copyright (c) 2015, {@link http://ub.uni-leipzig.de Leipzig University Library}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class ListviewController extends OntoWiki_Controller_Component
{

    public function listAction()
    {
        $translate = $this->_owApp->translate;

        $this->view->placeholder('main.window.title')->set('Listview');
        $this->addModuleContext('main.window.listmodules.list');
        OntoWiki::getInstance()->getNavigation()->disableNavigation();

        $query = $_GET['query'];

        $titleHelper = new OntoWiki_Model_TitleHelper($this->_owApp->selectedModel);
        $subjectUri = $_GET['subject'];
        $subjectTitle = $titleHelper->getTitle($subjectUri);

        $propertyUri = $_GET['property'];
        $propertyTitle = $titleHelper->getTitle($propertyUri);
        //query selected model
        $result = $this->_owApp->selectedModel->sparqlQuery(
            $query
        );

        $this->view->placeholder('main.window.title')->set($this->_owApp->translate->_('List-view for Resource') . ': ' . $subjectTitle);
        $this->view->subjectUri = $subjectUri;
        $this->view->subjectTitle = $subjectTitle;
        $this->view->propertyUri = $propertyUri;
        $this->view->propertyTitle = $propertyTitle;
        $this->view->results = $result;
        return;
    }
}
