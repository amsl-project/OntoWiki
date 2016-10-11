<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * EZB-holdings import controller.
 *
 * This controller provides import functionality for EZB kbart files.
 *
 * @category   OntoWiki
 * @package    Extensions_Ezbholdings
 * @author     Reik Mueller
 * @copyright  Copyright (c) 2015, {@link http://amsl.technology/}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class EzbholdingsController extends OntoWiki_Controller_Component
{
    var $backend = null;
    var $meta = array();


    public function init()
    {
        parent::init();
        $action = $this->_request->getActionName();

        $this->view->placeholder('main.window.title')->set('Process EZB-holdings File');
        $this->view->formActionUrl = $this->_config->urlBase . 'ezbholdings/uploadholdingsfile';
        $this->view->formEncoding = 'multipart/form-data';
        $this->view->formClass = 'simple-input input-justify-left';
        $this->view->formMethod = 'post';
        $this->view->formName = 'importdata';
        $this->view->supportedFormats = $this->_erfurt->getStore()->getSupportedImportFormats();

        $this->_owApp = OntoWiki::getInstance();
        $this->_model = $this->_owApp->selectedModel;
        $this->_translate = $this->_owApp->translate;

        // add a standard toolbar
        $toolbar = $this->_owApp->toolbar;
        $toolbar->appendButton(
            OntoWiki_Toolbar::SUBMIT,
            array('name' => $this->_translate->translate('Import Data'), 'id' => 'importdata')
        );
        $this->view->placeholder('main.window.toolbar')->set($toolbar);

        // setup the navigation
        OntoWiki::getInstance()->getNavigation()->reset();
        OntoWiki::getInstance()->getNavigation()->register(
            'form',
            array(
                'controller' => 'ezbholdings',
                'action' => 'uploadholdingsfile',
                'name' => 'Upload and process EZB-holdings file.',
                'position' => 0,
                'active' => true
            )
        );

        $meta["tripleWritte"] = array();
    }

    private function init2()
    {
        $options = $this->_owApp->getConfig()->toArray()['store']['virtuoso'];
        $options['is_open_source_version'] = '1';
        $this->backend = new Erfurt_Store_Adapter_Virtuoso($options);
        $this->backend->init();
    }

    public function holdingsfilefromdiscoveryAction()
    {
        // get the url of the holdings file from the discovery graph
        $config = $this->_privateConfig->toArray();
        $holdingFileData = $this->downloadHoldingsFile($config['amslURL']);
        $holdingFileDataLines = explode(PHP_EOL, $holdingFileData);
        $holdingFileCSVData = null;
        $counter = 0;
        $line_deleted = false;
        foreach ($holdingFileDataLines as $line) {
            if (!$line_deleted) {
                $counter++;
                if ($counter == 1 && strpos($line, 'publication_title') === 0) {
                    $line_deleted = true;
                    continue;
                }
            }
            $holdingFileCSVData[] = str_getcsv($line, "\t");
        }
        $this->processHoldingsFile($holdingFileCSVData);
        $this->getHelper('Layout')->disableLayout();
        $this->getHelper('ViewRenderer')->setNoRender();
    }

    public function uploadholdingsfileAction()
    {
        $this->view->placeholder('main.window.title')->set('Upload a counter xml file');

        if ($this->_request->isPost()) {
            $upload = new Zend_File_Transfer();
            $filesArray = $upload->getFileInfo();

            $message = '';
            switch (true) {
                case empty($filesArray):
                    $message = $this->_translate->translate(
                        'The upload went wrong. check post_max_size in your php.ini or ask your IT.'
                    );
                    break;
                case ($filesArray['source']['error'] == UPLOAD_ERR_INI_SIZE):
                    $message = $message = $this->_translate->translate(
                        'The uploaded files\'s size exceeds the upload_max_filesize directive in php.ini.'
                    );
                    break;
                case ($filesArray['source']['error'] == UPLOAD_ERR_PARTIAL):
                    $message = $message = $this->_translate->translate(
                        'The file was only partially uploaded.');
                    break;
                case ($filesArray['source']['error'] >= UPLOAD_ERR_NO_FILE):
                    $message = $message = $this->_translate->translate(
                        'Please select a file to upload.'
                    );
                    break;
            }

            if ($message != '') {
                $this->_owApp->appendErrorMessage($message);
                return;
            }

            $file = $filesArray['source']['tmp_name'];
            $holdingFileData = null;
            // setting permissions to read the tempfile for everybody
            // (e.g. if db and webserver owned by different users)
            chmod($file, 0644);
            $fp = fopen($file, 'r');
            $counter = 0;
            $line_deleted = false;
            while (!feof($fp)) {
                $line = fgets($fp, 2048);
                if (!$line_deleted) {
                    $counter++;
                    if ($counter == 1 && strpos($line, 'publication_title') === 0) {
                        $line_deleted = true;
                        continue;
                    }
                }
                $holdingFileData[] = str_getcsv($line, ",");
            }
            fclose($fp);
            $this->processHoldingsFile($holdingFileData);
            $this->view->success = "true";
        }
    }

    private function downloadHoldingsFile($url)
    {
        $curlSession = curl_init();
        curl_setopt($curlSession, CURLOPT_URL, $url);
        curl_setopt($curlSession, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlSession, CURLOPT_ENCODING, '');
        $holdingFileData = curl_exec($curlSession);
        curl_close($curlSession);
        return $holdingFileData;
    }

    /**
     * This is a convenience function that examines the cross-product of packages x anchors x contracts and creates the
     * following structure:
     * root:
     * - paketA
     *    - anchors
     *       - anchorA
     *       - anchorB
     *    - contracts
     *       - contractA
     *       - contractB
     * - paketB
     *    ...
     *
     * @return array
     */
    private function getFoldedPackageData()
    {
        $licensePackages = $this->getLicensePackages();
        $result = array();
        foreach ($licensePackages as $data) {
            // create packet
            if (!isset($result[$data["paket"]])) {
                $result[$data["paket"]] = array();
            }

            // create anchor-list
            if (!isset($result[$data["paket"]]["anchors"])) {
                $result[$data["paket"]]["anchors"] = array();
            }

            // create anchor
            if (!in_array($data["anchor"], $result[$data["paket"]]["anchors"])) {
                $result[$data["paket"]]["anchors"][] = $data["anchor"];
            }

            // create contract-list
            if (!isset($result[$data["paket"]]["contracts"])) {
                $result[$data["paket"]]["contracts"] = array();
            }

            // create contract
            if (!in_array($data["contract"], $result[$data["paket"]]["contracts"])) {
                $result[$data["paket"]]["contracts"][] = $data["contract"];
            }
        }
        return $result;
    }

    private function processHoldingsFile($holdingFileData)
    {
        $this->init2();
        $data = $this->getFoldedPackageData();
        foreach ($data as $paket => $paketdata) {
            $writeNewHoldings = false;
            foreach ($paketdata["contracts"] as $contract) {
                if ($this->isInContractPeriod($contract)) {
                    $this->deleteHoldings($paket);
                    $writeNewHoldings = true;
                    break;
                }
            }

            if ($writeNewHoldings) {
                foreach ($paketdata["anchors"] as $anchor) {
                    foreach ($holdingFileData as $holdingsDataset) {
                        if (isset($holdingsDataset[16])) {
                            $holdingsAnchor = $holdingsDataset[16];
                            $licensePackageAnchor = $anchor;
                            if ($licensePackageAnchor === $holdingsAnchor) {
                                $this->createHolding($paket, $holdingsDataset);
                            }
                        }
                    }
                }
            }
        }
    }

    private function deleteHoldings($package)
    {
        // get all holdings associated with a certain package
        $query = 'select distinct ?holding FROM <http://ubl.amsl.technology/erm/> where { ?holding <http://vocab.ub.uni-leipzig.de/amsl/holdingsItemOf> <' . $package . '> }';
        $query_results = $this->backend->sparqlQuery($query);
        // delete all these holdings
        foreach ($query_results as $resultSet) {
            $holding = $resultSet['holding'];
            $this->backend->deleteMatchingStatements('http://ubl.amsl.technology/erm/', $holding, null, null);
        }
    }

    private function createHolding($package, $holdingsDataset)
    {
        // create holding id
        $holding = 'http://ubl.amsl.technology/erm/' . md5(rand());
        // write holding
        $objectSpec = array();
        $objectSpec['type'] = 'uri';
        $objectSpec['value'] = 'http://vocab.ub.uni-leipzig.de/amsl/HoldingsItem';
        $this->backend->addStatement('http://ubl.amsl.technology/erm/', $holding, 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type', $objectSpec);
        // write association of holding and package
        $objectSpec['type'] = 'uri';
        $objectSpec['value'] = $package;
        $this->backend->addStatement('http://ubl.amsl.technology/erm/', $holding, 'http://vocab.ub.uni-leipzig.de/amsl/holdingsItemOf', $objectSpec);
        // write properties of holding
        foreach ($this->getProperties() as $propertySet) {
            $subject = $holding;
            $predicate = $propertySet['property'];
            if ($holdingsDataset[$propertySet['ezbIndex']] != "") {
                $objectSpec['value'] = $holdingsDataset[$propertySet['ezbIndex']];
                switch ($propertySet['type']) {
                    case 'literal':
                        $objectSpec['type'] = 'literal';
                        break;
                    case 'uri':
                        $objectSpec['type'] = 'uri';
                        break;
                    case 'issn':
                        $objectSpec['type'] = 'uri';
                        $objectSpec['value'] = 'urn:ISSN:' . $holdingsDataset[$propertySet['ezbIndex']];
                        break;
                }
                $this->backend->addStatement('http://ubl.amsl.technology/erm/', $subject, $predicate, $objectSpec);
            }
        }
    }

    private function isInContractPeriod($contract)
    {
        $startResultSet = $this->getRessourceDetail($contract, 'http://vocab.ub.uni-leipzig.de/amsl/licenseStartDate');
        $endResultSet = $this->getRessourceDetail($contract, 'http://vocab.ub.uni-leipzig.de/amsl/licenseEndDate');

        if (isset($startResultSet[0]['detail'])) {
            $start = $startResultSet[0]['detail'];
        } else {
            return false;
        }
        if (isset($endResultSet[0]['detail'])) {
            $end = $endResultSet[0]['detail'];
        }

        $currentTime = date('Y-m-d');
        if ($currentTime >= $start && (!isset($end) || $currentTime <= $end)) {
            return true;
        }
        return false;
    }

    private function getRessourceDetail($contract, $property)
    {
        $query = 'select distinct ?detail FROM <http://ubl.amsl.technology/erm/> where {<' . $contract . '> <' . $property . '> ?detail . }';
        $query_results = $this->backend->sparqlQuery($query);
        return $query_results;
    }

    private function getLicensePackages()
    {

        $query = 'select distinct ?paket ?anchor ?contract FROM <http://ubl.amsl.technology/erm/> where {?paket a <http://vocab.ub.uni-leipzig.de/amsl/LicensePackage> . ?paket <http://vocab.ub.uni-leipzig.de/amsl/ezbAnchor> ?anchor . ?paket <http://vocab.ub.uni-leipzig.de/amsl/contractContainingPackage> ?contract . }';
        $query_results = $this->backend->sparqlQuery($query);
        return $query_results;
    }

    private function getProperties()
    {
        $properties = array();
        $properties[] = array(
            'ezbIndex' => 0,
            'property' => 'http://purl.org/dc/elements/1.1/title',
            'type' => 'literal',
        );
        $properties[] = array(
            'ezbIndex' => 1,
            'property' => 'http://vocab.ub.uni-leipzig.de/amsl/pissn',
            'type' => 'issn',
        );
        $properties[] = array(
            'ezbIndex' => 2,
            'property' => 'http://vocab.ub.uni-leipzig.de/amsl/eissn',
            'type' => 'issn',
        );
        $properties[] = array(
            'ezbIndex' => 3,
            'property' => 'http://vocab.ub.uni-leipzig.de/amsl/dateFirstIssueOnline',
            'type' => 'literal',
        );
        $properties[] = array(
            'ezbIndex' => 4,
            'property' => 'http://vocab.ub.uni-leipzig.de/amsl/numberFirstVolumeOnline',
            'type' => 'literal',
        );
        $properties[] = array(
            'ezbIndex' => 5,
            'property' => 'http://vocab.ub.uni-leipzig.de/amsl/numberFirstIssueOnline',
            'type' => 'literal',
        );
        $properties[] = array(
            'ezbIndex' => 6,
            'property' => 'http://vocab.ub.uni-leipzig.de/amsl/dateLastIssueOnline',
            'type' => 'literal',
        );
        $properties[] = array(
            'ezbIndex' => 7,
            'property' => 'http://vocab.ub.uni-leipzig.de/amsl/numberLastVolumeOnline',
            'type' => 'literal',
        );
        $properties[] = array(
            'ezbIndex' => 8,
            'property' => 'http://vocab.ub.uni-leipzig.de/amsl/numberLastIssueOnline',
            'type' => 'literal',
        );
        $properties[] = array(
            'ezbIndex' => 9,
            'property' => 'http://vocab.ub.uni-leipzig.de/amsl/primaryAccessURI',
            'type' => 'uri',
        );
        $properties[] = array(
            'ezbIndex' => 12,
            'property' => 'http://vocab.ub.uni-leipzig.de/amsl/embargoInfo',
            'type' => 'value',
        );
        $properties[] = array(
            'ezbIndex' => 13,
            'property' => 'http://vocab.ub.uni-leipzig.de/amsl/coverageDepth',
            'type' => 'literal',
        );
        $properties[] = array(
            'ezbIndex' => 14,
            'property' => 'http://vocab.ub.uni-leipzig.de/amsl/coverageNotes',
            'type' => 'literal',
        );
        $properties[] = array(
            'ezbIndex' => 15,
            'property' => 'http://purl.org/dc/elements/1.1/publisher',
            'type' => 'literal',
        );
        $properties[] = array(
            'ezbIndex' => 11,
            'property' => 'http://vocab.ub.uni-leipzig.de/amsl/ezbTitleID',
            'type' => 'literal',
        );
        $properties[] = array(
            'ezbIndex' => 22,
            'property' => 'http://vocab.ub.uni-leipzig.de/amsl/zdbID',
            'type' => 'literal',
        );
        return $properties;
    }
}