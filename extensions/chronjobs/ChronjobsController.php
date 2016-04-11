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
class ChronjobsController extends OntoWiki_Controller_Component
{

    /**
     *
     * @throws Zend_Controller_Response_Exception
     */
    public function listAction()
    {
        $this->_owApp->getNavigation()->disableNavigation();
    }

    public function runAction()
    {
        $conf = $this->_privateConfig->toArray();
        $jobs = array();
        foreach ($conf as $job) {
            if (isset($job['type']) && ($job['type'] == 'script' || $job['type'] == 'query')) {
                array_push($jobs, $job);
            }
        }

        $storageData = $this->readStorage();

        $newJobs = 0;
        $oldJobs = count($storageData);
        foreach ($jobs as $job) {
            $data = null;

            // merge numbers of already executed (and thus documented) jobs and (possibly new) configured jobs
            $hasJobNumber = false;
            $jobnumber = 0;
            foreach($storageData as $d){
                if($d[0] == $job['name']){
                    $data = $d;
                    $hasJobNumber = true;
                    break;
                }
                $jobnumber++;
            }

            if($hasJobNumber){
                $finalJobNumber = $jobnumber;
            }else{
                $newJobs++;
                $finalJobNumber = $oldJobs + $newJobs;
            }

            if ($this->hasToBeExecuted($job['rhythm'], $job['date'], $job['time'], $job['rectify'], $data)) {
//            if(true){
            if ($job['type'] == 'script') {
                    $controllerAndmethod = explode('/', $job['value']);
                    if (count($controllerAndmethod) == 2) {
                        $storageData[$finalJobNumber][0] = $job['name'];
                        $storageData[$finalJobNumber][1] = date('d.m.Y');
                        $url = new OntoWiki_Url(array('controller' => $controllerAndmethod[0], 'action' => $controllerAndmethod[1]));
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, (string)$url);
                        curl_setopt($ch, CURLOPT_HEADER, 0);
                        curl_exec($ch);
                        curl_close($ch);
                        $storageData[$finalJobNumber][2] = 'success';
                    }
                } elseif ($job['type'] == 'query') {
                    $storageData[$finalJobNumber][0] = $job['name'];
                    $storageData[$finalJobNumber][1] = date('d.m.Y');
                    $options = $this->_owApp->getConfig()->toArray()['store']['virtuoso'];
                    $options['is_open_source_version'] = '1';
                    $backend = new Erfurt_Store_Adapter_Virtuoso($options);
                    $backend->init();
                    $query_results = $backend->sparqlQuery($job['value']);
                    $storageData[$finalJobNumber][2] = 'success';
                }
            }
        }

        $this->writeStorage($storageData);

        $this->getHelper('Layout')->disableLayout();
        $this->getHelper('ViewRenderer')->setNoRender();
    }

    private function readStorage()
    {
        $alldata = array();
        $path = realpath(null) . '/extensions/chronjobs/resources/store.txt';
        if (($handle = fopen($path, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, "#")) !== FALSE) {
                array_push($alldata, $data);
            }
            fclose($handle);
        }
        return $alldata;
    }

    private function writeStorage($storageData)
    {
        $path = realpath(null) . '/extensions/chronjobs/resources/store.txt';
        $writing = fopen($path, 'w');
        ftruncate($writing, 0);
        $lines = array();
        foreach($storageData as $dataset){
            $lines[] = implode('#', $dataset);
        }
        $fileContent = implode(PHP_EOL, $lines);
        fputs($writing, $fileContent);
        fclose($writing);

    }

    private function hasToBeExecuted($rhythm = null, $date = null, $time = null, $rectify = null, $data)
    {

        if ($rhythm == null || $time == null) {
            return false;
        }
        switch ($rhythm) {
            case 'daily':
                break;
            case 'weekly':
                if ($date == null || jddayofweek(cal_to_jd(CAL_GREGORIAN, date("m"), date("d"), date("Y")), 2) != $date) {
                    return false;
                }
                break;
            case 'monthly':
                $actualDate = date("d");
                if ($date == null || $actualDate != $date) {
                    return false;
                }
                break;
            case 'yearly':
                $actualDate = date("d") . "." . date("m");
                if ($date == null || $actualDate != $date) {
                    return false;
                }
                break;
        }

        if ($data != null) {
            $lastsheduled = $data[1];
            $date = date('d.m.Y');
            if ($lastsheduled == $date) {
                return false;
            } else {
                $actualTime = date('h:i');
                if($time < $actualTime){
                    return true;
                }else{
                    return false;
                }
            }

        }else{
            return true;
        }
    }

    public function exampleAction()
    {
        $this->getHelper('Layout')->disableLayout();
        $this->getHelper('ViewRenderer')->setNoRender();
        return true;
    }
}