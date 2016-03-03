<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2012, {@link http://aksw.org AKSW}
 * @license   http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

require_once 'OntoWiki/Plugin.php';

/**
 * @category   OntoWiki
 * @package    Extensions_Selectlanguage
 *
 * http://localhost/OntoWiki-link/model/info/?archive=false
 */
class ListviewPlugin extends OntoWiki_Plugin
{
    protected $_config = null;
    protected $_supportedLanguages = null;
    public $owApp;

    public function init()
    {
        $this->_config = $this->_privateConfig;
        $this->owApp = OntoWiki::getInstance();
    }

//    public function onBeforeInitController()
//    {
//        $request = new OntoWiki_Request();
//        $uri = $request->getRequestUri();
//        if(strpos($uri, '&useListViewPlugin=saekfh') !== false){
//            $listviewURI = str_replace('/queries/editor/', '/listview/list/', $uri);
//            $request->setRequestUri($listviewURI);
//            $request->setModuleName($this->_getModule());
//            $request->setControllerName($this->_getController());
//            $request->setActionName($this->_getAction());
//            $request->setModuleName('listview');
//            $request->setControllerName('listview');
//            $request->setActionName('list');
//            $this->_redirect($listviewURI);
//        }
//
//    }
//
//    public function onPostBootstrap($event)
//    {
//        $request = new OntoWiki_Request();
//        $uri = $request->getRequestUri();
//        if(strpos($uri, '&useListViewPlugin=saekfh') !== false){
//            $listviewURI = str_replace('/queries/editor/', '/listview/list/', $uri);
//            $request->setRequestUri($listviewURI);
//            $request->setModuleName('listview');
//            $request->setControllerName('listview');
//            $request->setActionName('list');
//        }
//    }

}