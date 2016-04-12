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
class AdaptivecacheController extends OntoWiki_Controller_Component
{

    /**
     *
     * @throws Zend_Controller_Response_Exception
     */
    public function listAction()
    {
        $mode = $this->_privateConfig->toArray()['mode'];

//        OntoWiki::getInstance()->getNavigation()->disableNavigation();
//        $reducedQuerySet = array();
//        $reducedQuerySet2 = array();
//
//        //get queries and how often they occurred
//        $queries = $this->readLog();
//        // if queries are sorted by occurrence evaluation will be faster in erfurt-store
//        arsort($queries);
//        // count queries and occurrences
//        $numberOfQueries = 0;
//        $totalAmount = 0;
//        foreach ($queries as $query => $amount) {
//            $numberOfQueries++;
//            $totalAmount = $totalAmount + $amount;
//        }
//        // calculate expected value
//        $expectedValue = $totalAmount / $numberOfQueries;
//        // calculate standard deviation
//        $sum = 0;
//        foreach ($queries as $query => $amount) {
//            $sum = $sum + (($amount - $expectedValue) * ($amount - $expectedValue));
//        }
//        $standardDeviation = bcsqrt($sum / $numberOfQueries, 4);
//
//        // determine which queries should be cached
//        $queriesToBeCached = array();
//        $border = $expectedValue - $standardDeviation;
//        foreach ($queries as $query => $amount) {
//            if ($amount < $standardDeviation + $expectedValue) {
//                $reducedQuerySet[$query] = $amount;
//            } else {
//                $reducedQuerySet2[$query] = $amount;
//            }
//            if ($amount > ($expectedValue)) {
//                $queriesToBeCached[] = $query;
//            }
//        }
//
//
//        $reducedNumberOfQueries = 0;
//        $reducedTotalAmount = 0;
//        foreach ($reducedQuerySet as $query => $amount) {
//            $reducedNumberOfQueries++;
//            $reducedTotalAmount = $reducedTotalAmount + $amount;
//        }
//        $reducedExpectedValue = $reducedTotalAmount / $reducedNumberOfQueries;
//        $reducedSum = 0;
//        foreach ($reducedQuerySet as $query => $amount) {
//            $reducedSum = $reducedSum + (($amount - $reducedExpectedValue) * ($amount - $reducedExpectedValue));
//        }
//        $reducedStandardDeviation = bcsqrt($reducedSum / $reducedNumberOfQueries, 4);
//        $reducedQueriesToBeCached = array();
//        foreach ($reducedQuerySet as $query => $amount) {
//            if ($amount > ($reducedExpectedValue)) {
//                $reducedQueriesToBeCached[] = $query;
//            }
//        }
//        foreach ($reducedQuerySet2 as $query => $amount) {
//            $reducedQueriesToBeCached[] = $query;
//        }

        $path = realpath(null) . '/extensions/adaptivecache/resources/store.txt';
        $this->view->logFileSize = round((filesize($path) / 1048576), 1);

//        $this->view->totalAmountOfQueries = $totalAmount;
//        $this->view->numberOfQueries = $numberOfQueries;
//        $this->view->cachedQueries = count($queriesToBeCached);
//        $this->view->expectedValue = round($expectedValue, 2);
//        $this->view->standardDeviation = round($standardDeviation, 2);
//
//        $this->view->rnumberOfQueries = $reducedNumberOfQueries;
//        $this->view->rcachedQueries = count($reducedQueriesToBeCached);
//        $this->view->rexpectedValue = round($reducedExpectedValue, 2);
//        $this->view->rstandardDeviation = round($reducedStandardDeviation, 2);

        $response = $this->calculateWithFullDataSet();
        $this->view->totalAmountOfQueries = $response['totalAmountOfQueries'];
        $this->view->numberOfQueries = $response['numberOfQueries'];
        $this->view->cachedQueries = count($response['cachedQueries']);
        $this->view->expectedValue = round($response['expectedValue'], 2);
        $this->view->standardDeviation = round($response['standardDeviation'], 2);

        $response = $this->calculateWithoutPeaks();
        $this->view->rnumberOfQueries = $response['numberOfQueries'];
        $this->view->rcachedQueries = count($response['cachedQueries']);
        $this->view->rexpectedValue = round($response['expectedValue'], 2);
        $this->view->rstandardDeviation = round($response['standardDeviation'], 2);

        $response = $this->calculateWithPeaksOnly();
        $this->view->ptotalAmount = $response['totalAmountOfQueries'];
        $this->view->pcachedQueries = count($response['cachedQueries']);
        $this->view->pexpectedValue = round($response['expectedValue'], 2);
        $this->view->pstandardDeviation = round($response['standardDeviation'], 2);

        $this->view->mode = $mode;
    }

    public function logAction()
    {
        $query = $this->_request->getParam('query');
        $this->writeLog($query);
    }

    private function calculateWithFullDataSet()
    {
        $response = array();
        //get queries and how often they occurred
        $queries = $this->readLog();
        // if queries are sorted by occurrence evaluation will be faster in erfurt-store
        arsort($queries);
        // count queries and occurrences
        $numberOfQueries = 0;
        $totalAmount = 0;
        foreach ($queries as $query => $amount) {
            $numberOfQueries++;
            $totalAmount = $totalAmount + $amount;
        }
        // calculate expected value
        $expectedValue = $totalAmount / $numberOfQueries;
        // calculate standard deviation
        $sum = 0;
        foreach ($queries as $query => $amount) {
            $sum = $sum + (($amount - $expectedValue) * ($amount - $expectedValue));
        }
        $standardDeviation = bcsqrt($sum / $numberOfQueries, 4);

        // determine which queries should be cached
        $queriesToBeCached = array();
        foreach ($queries as $query => $amount) {
            if ($amount > ($expectedValue)) {
                $queriesToBeCached[] = $query;
            }
        }

        $response['totalAmountOfQueries'] = $totalAmount;
        $response['numberOfQueries'] = $numberOfQueries;
        $response['cachedQueries'] = $queriesToBeCached;
        $response['expectedValue'] = $expectedValue;
        $response['standardDeviation'] = $standardDeviation;
        return $response;
    }

    private function calculateWithoutPeaks()
    {
        //get queries and how often they occurred
        $queries = $this->readLog();
        $totalAmount = 0;
        $numberOfQueries = 0;
        foreach ($queries as $query => $amount) {
            $numberOfQueries++;
            $totalAmount = $totalAmount + $amount;
        }
        //get pre-calculated data
        $response = $this->calculateWithFullDataSet();
        $expectedValue = $response['expectedValue'];
        $standardDeviation = $response['standardDeviation'];

        // refine data
        foreach ($queries as $query => $amount) {
            if ($amount < $standardDeviation + $expectedValue) {
                $reducedQuerySet[$query] = $amount;
            } else {
                $reducedQuerySet2[$query] = $amount;
            }
        }

        $reducedNumberOfQueries = 0;
        $reducedTotalAmount = 0;
        foreach ($reducedQuerySet as $query => $amount) {
            $reducedNumberOfQueries++;
            $reducedTotalAmount = $reducedTotalAmount + $amount;
        }
        $reducedExpectedValue = $reducedTotalAmount / $reducedNumberOfQueries;
        $reducedSum = 0;
        foreach ($reducedQuerySet as $query => $amount) {
            $reducedSum = $reducedSum + (($amount - $reducedExpectedValue) * ($amount - $reducedExpectedValue));
        }
        $reducedStandardDeviation = bcsqrt($reducedSum / $reducedNumberOfQueries, 4);
        $reducedQueriesToBeCached = array();
        foreach ($reducedQuerySet as $query => $amount) {
            if ($amount > ($reducedExpectedValue)) {
                $reducedQueriesToBeCached[] = $query;
            }
        }
        foreach ($reducedQuerySet2 as $query => $amount) {
            $reducedQueriesToBeCached[] = $query;
        }

        $response['totalAmountOfQueries'] = $reducedTotalAmount;
        $response['numberOfQueries'] = $reducedNumberOfQueries;
        $response['cachedQueries'] = $reducedQueriesToBeCached;
        $response['expectedValue'] = $reducedExpectedValue;
        $response['standardDeviation'] = $reducedStandardDeviation;

        return $response;
    }

    private function calculateWithPeaksOnly(){
        //get queries and how often they occurred
        $queries = $this->readLog();
        $totalAmount = 0;
        $numberOfQueries = 0;
        foreach ($queries as $query => $amount) {
            $numberOfQueries++;
            $totalAmount = $totalAmount + $amount;
        }

        //get pre-calculated data
        $response = $this->calculateWithFullDataSet();
        $expectedValue = $response['expectedValue'];
        $standardDeviation = $response['standardDeviation'];

        // refine data
        foreach ($queries as $query => $amount) {
            if ($amount > $standardDeviation + $expectedValue) {
                $reducedQuerySet[$query] = $amount;
            }
        }

        $reducedNumberOfQueries = 0;
        $reducedTotalAmount = 0;
        foreach ($reducedQuerySet as $query => $amount) {
            $reducedNumberOfQueries++;
            $reducedTotalAmount = $reducedTotalAmount + $amount;
        }
        $reducedExpectedValue = $reducedTotalAmount / $reducedNumberOfQueries;
        $reducedSum = 0;
        foreach ($reducedQuerySet as $query => $amount) {
            $reducedSum = $reducedSum + (($amount - $reducedExpectedValue) * ($amount - $reducedExpectedValue));
        }
        $reducedStandardDeviation = bcsqrt($reducedSum / $reducedNumberOfQueries, 4);
        $reducedQueriesToBeCached = array();
        foreach ($reducedQuerySet as $query => $amount) {
            $reducedQueriesToBeCached[] = $query;
        }

        $response['totalAmountOfQueries'] = $reducedTotalAmount;
        $response['cachedQueries'] = $reducedQueriesToBeCached;
        $response['expectedValue'] = $reducedExpectedValue;
        $response['standardDeviation'] = $reducedStandardDeviation;

        return $response;
    }

    public function analyzeAction()
    {
        $mode = $this->_privateConfig->toArray()['mode'];
        $ok = false;
        if ($mode === '1') {
            $data = $this->calculateWithFullDataSet();
            $ok = true;
        }
        if ($mode === '2') {
            $data = $this->calculateWithoutPeaks();
            $ok = true;
        }
        if ($mode === '3') {
            $data = $this->calculateWithPeaksOnly();
            $ok = true;
        }

        if ($ok) {
            $queriesToBeCached = $data['cachedQueries'];
            // write query look-up
            $path = realpath(null) . '/extensions/adaptivecache/resources/queries.txt';
            file_put_contents($path, implode('', $queriesToBeCached), LOCK_EX);
            // shorten log
            $this->shortenLog();
        }
        $this->getHelper('Layout')->disableLayout();
        $this->getHelper('ViewRenderer')->setNoRender();
    }

    private function writeLog($query)
    {
        $path = realpath(null) . '/extensions/adaptivecache/resources/store.txt';
        file_put_contents($path, $query, FILE_APPEND | LOCK_EX);
    }

    private function readLog()
    {
        $alldata = array();
        $path = realpath(null) . '/extensions/adaptivecache/resources/store.txt';
        $linecount = 0;
        $handle = fopen($path, "r");
        while (!feof($handle)) {
            $line = fgets($handle);
            $linecount++;
            if (isset($alldata[$line])) {
                $alldata[$line] = $alldata[$line] + 1;
            } else {
                $alldata[$line] = 1;
            }
        }
        fclose($handle);
        // if queries are sorted by occurrence evaluation will be faster in erfurt-store
        arsort($alldata);
        return $alldata;
    }

    private function shortenLog()
    {
        $array = $this->_privateConfig->toArray();
        $numberOfLinesToKeep = $array['evaluationBase'];

        $path = realpath(null) . '/extensions/adaptivecache/resources/store.txt';
        $linecount = 0;
        $handle = fopen($path, "r");
        while (!feof($handle)) {
            $linecount++;
            $line = fgets($handle);
        }
        fclose($handle);

        $linesToKeep = array();

        if ($linecount > $numberOfLinesToKeep) {
            $borderLine = $linecount - $numberOfLinesToKeep;
            $linecount = 0;
            $handle = fopen($path, "r");
            while (!feof($handle)) {
                $linecount++;
                $line = fgets($handle);
                if ($linecount > $borderLine) {
                    if ($line !== PHP_EOL) {
                        $linesToKeep[] = $line;
                    }
                }
            }
            fclose($handle);
            file_put_contents($path, implode('', $linesToKeep), LOCK_EX);
        }
    }

    private function readStorage()
    {
        $alldata = array();
        $path = realpath(null) . '/extensions/adaptivecache/resources/store.txt';
        if (($handle = fopen($path, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, "#")) !== FALSE) {
                array_push($alldata, $data);
            }
            fclose($handle);
        }
        return $alldata;
    }
}