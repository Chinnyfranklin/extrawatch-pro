<?php

/**
 * @file
 * ExtraWatch - A real-time ajax monitor and live stats
 * @package ExtraWatch
 * @version 2.2
 * @revision 1211
 * @license http://www.gnu.org/licenses/gpl-3.0.txt     GNU General Public License v3
 * @copyright (C) 2013 by CodeGravity.com - All rights reserved!
 * @website http://www.codegravity.com
 */

defined('_JEXEC') or die('Restricted access');

class ExtraWatchDownloads
{

    public $database;
    public $config;
    public $helper;
    public $env;
    public $date;

    function __construct($database)
    {
        $this->database = $database;
        $this->env = ExtraWatchEnvFactory::getEnvironment();
        $this->config = new ExtraWatchConfig($this->database);
        $this->helper = new ExtraWatchHelper($this->database);
        $this->date = new ExtraWatchDate($this->database);
    }

    function checkIfReferrerAllowed($file, $referrer) {
        $query = sprintf("SELECT allowedReferrer FROM #__extrawatch_dm_paths where dname='%s'", $this->database->getEscaped($file));
        $allowedReferrer = $this->database->resultQuery($query);
        if (@$allowedReferrer) {    // if there's some, we're going to check it
            if (!$referrer || !strpos($allowedReferrer,$referrer) === 0) {
                return FALSE;
            }
        }
        return TRUE;
    }

    function increaseFileDownload($file) {

        $filepathquery = sprintf("SELECT did FROM #__extrawatch_dm_paths where dname='%s'", $this->database->getEscaped($file));
        $filepathid = $this->database->resultQuery($filepathquery);

        if($file!='')
        {
            $currdate = date("Y-m-d");

            $filesearchquery = sprintf("SELECT COUNT(*) as `count` FROM #__extrawatch_dm_paths where dname='%s'", $this->database->getEscaped($file));
            $filesearchar = $this->database->resultQuery($filesearchquery);
			$ip = ExtraWatchVisit::getRemoteIPAddress();
			$referrer = ExtraWatchVisit::getReferer();

            if (!$this->checkIfReferrerAllowed($file, $referrer)) {
                $adminEmail = $this->helper->getAdminEmail($this->env);
                $adminEmailReplaced = str_replace("@"," {at} ", $adminEmail);
                $emailSubject = sprintf(_EW_DOWNLOADS_EMAIL_RESTRICTED_SUBJECT, $ip);
                $emailContent = sprintf(_EW_DOWNLOADS_EMAIL_RESTRICTED_BODY, $file, $referrer);
                $this->helper->sendEmail($this->env, $adminEmail, $adminEmail, $emailSubject, $emailContent);
                die(sprintf(_EW_DOWNLOADS_NOT_ALLOWED, $adminEmailReplaced));
            }

            $referrerId = $this->findOrAddReferrer($referrer);
            $timestamp = $this->date->getUTCTimestamp();
            if($filesearchar>0)
            {
                $filepathquery_add = sprintf("insert into #__extrawatch_dm_counter (did,ddate,ip,referrerId,`timestamp`) values ('%s','%s','%s','%d','%d')", (int)$filepathid, $this->database->getEscaped($currdate), $ip, (int) $referrerId, (int) $timestamp);
                $this->database->executeQuery($filepathquery_add);
            }
            else
            {
                $file_add = sprintf("insert into #__extrawatch_dm_paths (dname) values ('%s')", $this->database->getEscaped($file));
                $this->database->executeQuery($file_add);

                $path_query = "select did from #__extrawatch_dm_paths where dname = ('$file')";
                $filepathid = $this->database->resultQuery($path_query);

                $counter_add = sprintf("insert into #__extrawatch_dm_counter (did,ddate,ip,referrerId, `timestamp`) values ('%d','%s','%s','%d')", (int) $filepathid, $this->database->getEscaped($currdate), $ip, (int) $referrerId, (int) $timestamp);
                $this->database->executeQuery($counter_add);

            }
            $filepath = $this->env->getRootPath().DS.trim($file);
            $file = basename($filepath);
            if (file_exists($filepath))
            {
                header("Content-Type: application/octet-stream");
                header("Content-Disposition: attachment; filename=".$file);
                header("Content-Transfer-Encoding: binary");
				header('Content-Length: ' . filesize($filepath));
                @ob_clean();
                flush();
				set_time_limit(0);
				$this->readfileChunked($filepath);
                exit;
            } else {
				header('HTTP/1.0 404 Not Found');
			}

			
        }
        else
        {
            echo _EW_DOWNLOADS_FILE_NOT_FOUND;
        }

    }
	
	/* Thanks to php.net */
	function readfileChunked($filename,$retbytes=true) { 
		$chunksize = 1*(1024*1024); // how many bytes per chunk 
		$buffer = ''; 
		$cnt =0; 
		// $handle = fopen($filename, 'rb'); 
		$handle = fopen($filename, 'rb'); 
		if ($handle === false) { 
			return false; 
		} 
		while (!feof($handle)) { 
			$buffer = fread($handle, $chunksize); 
			echo $buffer; 
			ob_flush(); 
			flush(); 
			if ($retbytes) { 
				$cnt += strlen($buffer); 
			} 
		} 
       $status = fclose($handle); 
		if ($retbytes && $status) { 
		return $cnt; // return num. bytes delivered like readfile() does. 
	} 
	return $status; 
	}


    function addExtension($extName) {

        $extensionquery_ht_prev = sprintf("SELECT * FROM #__extrawatch_dm_extension");
        $extensionar_ht_prev = $this->database->objectListQuery($extensionquery_ht_prev);

        $ext_n_prev = "";
        foreach($extensionar_ht_prev as $extensionhtprev)
        {
            if (trim($extensionhtprev->extname)) {
                $ext_n_prev = $ext_n_prev.$extensionhtprev->extname."|";
            }
        }
        $ext_n_prev = substr($ext_n_prev,0,strlen($ext_n_prev)-1);



        $extensionquery_add = sprintf("insert into #__extrawatch_dm_extension (extname) values ('%s')", $this->database->getEscaped($extName));
        $this->database->executeQuery($extensionquery_add);

        $extensionquery_ht = sprintf("SELECT * FROM #__extrawatch_dm_extension");
        $extensionar_ht = $this->database->objectListQuery($extensionquery_ht);

        $ext_n = "";
        foreach($extensionar_ht as $extensionht)
        {
            if (trim($extensionht->extname)) {
                $ext_n = $ext_n.$extensionht->extname."|";
            }
        }
        $ext_n = substr($ext_n,0,strlen($ext_n)-1);

        $path = $this->env->getRootSite().$this->env->getEnvironmentSuffix();

		$env = $this->config->getEnvironment();

        $writingonht_prev = "\nRewriteEngine on"."\n"."RewriteRule ^(.*).(".$ext_n_prev.")$ ".$path.$this->env->renderAjaxLink('ajax','download')."&env=$env&file="."$1.$2&params=".urlencode("&rand=")." [R,L]";

        $root_file = $this->env->getRootPath().DS.".htaccess";

        $existingcode = @file_get_contents($root_file);

        $existingcode_f = str_replace($writingonht_prev,"",$existingcode);

        $writingonht = $existingcode_f."\nRewriteEngine on";
        $writingonht = $writingonht."\n"."RewriteRule ^(.*).(".$ext_n.")$ ".$path.$this->env->renderAjaxLink('ajax','download')."&env=$env&file="."$1.$2&params=".urlencode("&rand=")." [R,L]";

        if (file_exists($root_file))
        {
            if (is_writable($root_file))
            {
                $handle = fopen($root_file,"w");
                fwrite($handle,$writingonht);
                fclose($handle);
                header("location: ".$this->config->renderLink("downloads"));
            }
            else
            {
                echo _EW_DOWNLOADS_HTACCESS_NOT_WRITABLE;
            }
        }
        else
        {
            $handle = fopen($root_file,"w");
            if ($handle)
            {
                fwrite($handle,$writingonht);
                fclose($handle);
                header("location: ".$this->config->renderLink("downloads"));
            }
            else
            {
                echo _EW_DOWNLOADS_HTACCESS_COULD_NOT_BE_CREATED;
            }
        }

    }

    function addFilePath($filepathnamename, $allowedReferrer) {
        $filepathquery_add = sprintf("insert into #__extrawatch_dm_paths (dname, allowedReferrer) values ('%s','%s')", $this->database->getEscaped($filepathnamename), $this->database->getEscaped($allowedReferrer));
        $this->database->executeQuery($filepathquery_add);
        header("location: ".$this->config->renderLink("downloads"));
    }

    function updateExtension($eid, $extname) {

        $extensionquery_ht_prev = sprintf("SELECT * FROM #__extrawatch_dm_extension");
        $extensionar_ht_prev = $this->database->objectListQuery($extensionquery_ht_prev);

        $ext_n_prev = "";
        foreach($extensionar_ht_prev as $extensionhtprev)
        {
            if (trim($extensionhtprev->extname)) {
                $ext_n_prev = $ext_n_prev.$extensionhtprev->extname."|";
            }
        }
        $ext_n_prev = substr($ext_n_prev,0,strlen($ext_n_prev)-1);

        $extensionquery = sprintf("update #__extrawatch_dm_extension set extname='%s' where eid='%d'", $this->database->getEscaped($extname), (int) $eid);
        $this->database->setQuery($extensionquery);
        $this->database->query();

        $extensionquery_ht = sprintf("SELECT * FROM #__extrawatch_dm_extension");
        $extensionar_ht = $this->database->objectListQuery($extensionquery_ht);

        $ext_n = "";
        foreach($extensionar_ht as $extensionht)
        {
            if (trim($extensionht->extname)) {
                $ext_n = $ext_n.$extensionht->extname."|";
            }
        }
        $ext_n = substr($ext_n,0,strlen($ext_n)-1);

        $path = $this->env->getRootSite().$this->env->getEnvironmentSuffix();

		$env = $this->config->getEnvironment();

        $writingonht_prev = "\nRewriteEngine on"."\n"."RewriteRule ^(.*).(".$ext_n_prev.")$ ".$path.$this->env->renderAjaxLink('ajax','download')."&env=$env&file="."$1.$2&params=".urlencode("&rand=")." [R,L]";

        $root_file = $this->env->getRootPath().DS.".htaccess";

        $existingcode = file_get_contents($root_file);

        $existingcode_f = str_replace($writingonht_prev,"",$existingcode);
        //$existingcode_f = str_replace($writingonht_prev1,"",$existingcode);

        $writingonht = $existingcode_f."\nRewriteEngine on";
        $writingonht = $writingonht."\n"."RewriteRule ^(.*).(".$ext_n.")$ ".$path.$this->env->renderAjaxLink('ajax','download')."&env=$env&file="."$1.$2&params=".urlencode("&rand=")." [R,L]";


        if (file_exists($root_file))
        {
            if (is_writable($root_file))
            {
                $handle = fopen($root_file,"w");
                fwrite($handle,$writingonht);
                fclose($handle);
                header("location: ".$this->config->renderLink("downloads"));
            }
            else
            {
                echo _EW_DOWNLOADS_HTACCESS_NOT_WRITABLE;
            }
        }
        else
        {
            $handle = fopen($root_file,"w");
            if ($handle)
            {
                fwrite($handle,$writingonht);
                fclose($handle);
                header("location: ".$this->config->renderLink("downloads"));
            }
            else
            {
                echo _EW_DOWNLOADS_HTACCESS_COULD_NOT_BE_CREATED;
            }
        }


    }

    function findExtensionNameById($eid) {
        $extensionquery_edit = sprintf("SELECT extname FROM #__extrawatch_dm_extension where eid='%d'",(int)$eid);
        $editextname = $this->database->resultQuery($extensionquery_edit);
        return $editextname;
    }

    function  findFilePathNameById($did) {
        $filepathquery_edit = sprintf("SELECT dname FROM #__extrawatch_dm_paths where did='%d'", (int)$did);
        $editfilepathname = $this->database->resultQuery($filepathquery_edit);
        return $editfilepathname;
    }

    function updateFilePath($did, $filepathname, $allowedReferrer) {
        $filepathquery = sprintf("update #__extrawatch_dm_paths set dname='%s', allowedReferrer='%s' where did='%d'", $this->database->getEscaped($filepathname), $this->database->getEscaped($allowedReferrer), (int) $did);
        $this->database->setQuery($filepathquery);
        $this->database->query();
        header("location: ".$this->config->renderLink("downloads"));

    }

    function deleteExtension($co) {
        {

            $extensionquery_ht_prev = sprintf("SELECT * FROM #__extrawatch_dm_extension");
            $extensionar_ht_prev = $this->database->objectListQuery($extensionquery_ht_prev);

            $ext_n_prev = "";
            foreach($extensionar_ht_prev as $extensionhtprev)
            {
                $ext_n_prev = $ext_n_prev.$extensionhtprev->extname."|";
            }
            $ext_n_prev = substr($ext_n_prev,0,strlen($ext_n_prev)-1);

            $extensionquery_del = sprintf("delete from #__extrawatch_dm_extension where eid='%d'", (int) $co);
            $this->database->executeQuery($extensionquery_del);

            $extensionquery_ht = sprintf("SELECT * FROM #__extrawatch_dm_extension");
            $extensionar_ht = $this->database->objectListQuery($extensionquery_ht);

            $ext_n = "";
            foreach($extensionar_ht as $extensionht)
            {
                $ext_n = $ext_n.$extensionht->extname."|";
            }
            $ext_n = substr($ext_n,0,strlen($ext_n)-1);


            $path = $this->env->getRootSite().$this->env->getEnvironmentSuffix();

			$env = $this->config->getEnvironment();

            $writingonht_prev = "\nRewriteEngine on"."\n"."RewriteRule ^(.*).(".$ext_n_prev.")$ ".$path.$this->env->renderAjaxLink('ajax','download')."&env=$env&file="."$1.$2&params=".urlencode("&rand=")." [R,L]";

            $root_file = $this->env->getRootPath().DS.".htaccess";

            $existingcode = file_get_contents($root_file);

            $existingcode_f = str_replace($writingonht_prev,"",$existingcode);
            //$existingcode_f = str_replace($writingonht_prev1,"",$existingcode);

            $writingonht = $existingcode_f."\nRewriteEngine on";
            $writingonht = $writingonht."\n"."RewriteRule ^(.*).(".$ext_n.")$ ".$path.$this->env->renderAjaxLink('ajax','download')."&env=$env&file="."$1.$2&params=".urlencode("&rand=")." [R,L]";

            if (file_exists($root_file))
            {
                if (is_writable($root_file))
                {
                    if($ext_n!="")
                    {
                        $handle = fopen($root_file,"w");
                        fwrite($handle,$writingonht);
                        fclose($handle);
                        header("location: ".$this->config->renderLink("downloads"));
                    }
                }
                else
                {
                    $handle = fopen($root_file,"w");
                    fwrite($handle,$existingcode_f);
                    fclose($handle);
                    header("location: ".$this->config->renderLink("downloads"));
                }
            }
            else
            {
                $handle = fopen($root_file,"w");
                if ($handle)
                {
                    fwrite($handle,$writingonht);
                    fclose($handle);
                    header("location: ".$this->config->renderLink("downloads"));
                }
                else
                {
                    echo _EW_DOWNLOADS_HTACCESS_COULD_NOT_BE_CREATED;
                }
            }

        }
    }

    function deleteEverythingFromHtaccess() {

            $extensionquery_ht_prev = sprintf("SELECT * FROM #__extrawatch_dm_extension");
            $extensionar_ht_prev = $this->database->objectListQuery($extensionquery_ht_prev);

            $ext_n_prev = "";
            foreach($extensionar_ht_prev as $extensionhtprev)
            {
                $ext_n_prev = $ext_n_prev.$extensionhtprev->extname."|";
            }
            $ext_n_prev = substr($ext_n_prev,0,strlen($ext_n_prev)-1);

            $extensionquery_ht = sprintf("SELECT * FROM #__extrawatch_dm_extension");
            $extensionar_ht = $this->database->objectListQuery($extensionquery_ht);

            $ext_n = "";
            foreach($extensionar_ht as $extensionht)
            {
                $ext_n = $ext_n.$extensionht->extname."|";
            }
            $ext_n = substr($ext_n,0,strlen($ext_n)-1);


            $path = $this->env->getRootSite().$this->env->getEnvironmentSuffix();

            $env = $this->config->getEnvironment();

            $writingonht_prev = "\nRewriteEngine on"."\n"."RewriteRule ^(.*).(".$ext_n_prev.")$ ".$path.$this->env->renderAjaxLink('ajax','download')."&env=$env&file="."$1.$2&params=".urlencode("&rand=")." [R,L]";

            $root_file = $this->env->getRootPath().DS.".htaccess";

            $existingcode = file_get_contents($root_file);

            $existingcode_f = str_replace($writingonht_prev,"",$existingcode);

            $writingonht = $existingcode_f."\nRewriteEngine on";
            $writingonht = $writingonht."\n"."RewriteRule ^(.*).(".$ext_n.")$ ".$path.$this->env->renderAjaxLink('ajax','download')."&env=$env&file="."$1.$2&params=".urlencode("&rand=")." [R,L]";

            if (file_exists($root_file))
            {
                if (is_writable($root_file))
                {
                    if($ext_n!="")
                    {
                        $handle = fopen($root_file,"w");
                        fwrite($handle,$writingonht);
                        fclose($handle);
                    }
                }
                else
                {
                    $handle = fopen($root_file,"w");
                    fwrite($handle,$existingcode_f);
                    fclose($handle);
                }
            }
        }



    function deleteFilePath($co) {
        $extensionquery_del = sprintf("delete from #__extrawatch_dm_paths where did='%d'", (int)$co);
        $this->database->setQuery($extensionquery_del);
        $this->database->query();
        header("location: ".$this->config->renderLink("downloads"));
    }

    function getFileStatistics($did) {

        $month = date("m");
        $week = date("N");
        $curryear = date("Y");
        $currmonth = date("Y-m");
        $currdate = date("Y-m-d");

        $yesterday = date("Y-m-d", (strtotime($currdate)-(60*60*24)));
        $currmonthday = $currmonth. "-01";
        $lmonth = $month - 1;
        if($lmonth == 0)
        {
            $lmonth = 12;
            $curryear = $curryear-1;
        }
        $lastmonth =$curryear."-".$lmonth."-01";
        $weekday = date("Y-m-d", (strtotime($currdate)-($week*60*60*24)));
        $lweekday = date("Y-m-d", (strtotime($weekday)-(7*60*60*24)));

        $filepathquery_curr = sprintf("SELECT COUNT(*) FROM #__extrawatch_dm_counter where ddate='%s' and did='%d'", $this->database->getEscaped($currdate), (int) $did);
        $count_curr_dt  = $this->database->resultQuery($filepathquery_curr);

        $filepathquery_yes = sprintf("SELECT COUNT(*) FROM #__extrawatch_dm_counter where ddate='%s' and did='%d'", $this->database->getEscaped($yesterday), (int) $did);
        $count_yes_dt  = $this->database->resultQuery($filepathquery_yes);

        $filepathquery_cw = sprintf("SELECT COUNT(*) FROM #__extrawatch_dm_counter where ddate>'%s' and did='%d'", $this->database->getEscaped($weekday), (int) $did);
        $count_cw_dt  = $this->database->resultQuery($filepathquery_cw);

        $filepathquery_lw = sprintf("SELECT COUNT(*) FROM #__extrawatch_dm_counter where ddate<='%s' and ddate>'%s' and did='%d'", $this->database->getEscaped($weekday), $this->database->getEscaped($lweekday), (int) $did);
        $count_lw_dt  = $this->database->resultQuery($filepathquery_lw);

        $filepathquery_cm = sprintf("SELECT COUNT(*) FROM #__extrawatch_dm_counter where ddate>='%s' and did='%d'", $this->database->getEscaped($currmonthday), (int) $did);
        $count_cm_dt  = $this->database->resultQuery($filepathquery_cm);

        $filepathquery_lm = sprintf("SELECT COUNT(*) FROM #__extrawatch_dm_counter where ddate>='%s' and ddate<'%s' and did='%d'", $this->database->getEscaped($lastmonth), $this->database->getEscaped($currmonthday), (int) $did);
        $count_lm_dt  = $this->database->resultQuery($filepathquery_lm);

        $filepathquery_tot = sprintf("SELECT COUNT(*) FROM #__extrawatch_dm_counter where did='%d'", (int) $did);
        $count_tot_dt  = $this->database->resultQuery($filepathquery_tot);

        return array(
            "count_curr_dt" => $count_curr_dt,
            "count_yes_dt" => $count_yes_dt,
            "count_cw_dt" => $count_cw_dt,
            "count_lw_dt" => $count_lw_dt,
            "count_cm_dt" => $count_cm_dt,
            "count_lm_dt" => $count_lm_dt,
            "count_tot_dt" => $count_tot_dt);

    }

    function getAllExtensions() {
        $extensionquery = sprintf("SELECT * FROM #__extrawatch_dm_extension");
        return $this->database->objectListQuery($extensionquery);
    }

    function getAllFilePaths() {
        $filepathquery = sprintf("SELECT * FROM #__extrawatch_dm_paths");
        return $this->database->objectListQuery($filepathquery);
    }

    function getDownloadLog() {
        $query = sprintf("SELECT *, #__extrawatch_dm_referrer.referrer as referrerURL FROM  `#__extrawatch_dm_counter` JOIN #__extrawatch_dm_paths ON #__extrawatch_dm_paths.did = #__extrawatch_dm_counter.did
        LEFT JOIN #__extrawatch_dm_referrer on #__extrawatch_dm_counter.referrerId = #__extrawatch_dm_referrer.id
        ORDER BY #__extrawatch_dm_counter.id DESC limit 100");
        return $this->database->objectListQuery($query);
    }


    function findOrAddReferrer($referrer)
    {
        $referrer = ExtraWatchHelper::htmlspecialchars($referrer);

        $id = $this->getReferrerIdByName($referrer);

        if (!@$id) {
            $query = sprintf("insert into #__extrawatch_dm_referrer (id, referrer) values ('','%s') ", $this->database->getEscaped($referrer));
            $this->database->executeQuery($query);
            $id = $this->getReferrerIdByName($referrer);;
        }
        return $id;
    }

    private function getReferrerIdByName($referrer)
    {
        $query = sprintf("select id from #__extrawatch_dm_referrer where (`referrer` = '%s') limit 1 ", $this->database->getEscaped($referrer));
        return $this->database->resultQuery($query);
    }

    function getDownloadLogForIPBetweenTimestamps($ip, $earlierTimestamp, $laterTimestamp) {
        $query = sprintf("SELECT * FROM  `#__extrawatch_dm_counter` JOIN #__extrawatch_dm_paths ON #__extrawatch_dm_paths.did = #__extrawatch_dm_counter.did where ip = '%s' and (%d > `timestamp`) and (`timestamp` > %d)  ORDER BY #__extrawatch_dm_counter.id DESC ", $this->database->getEscaped($ip), (int) $earlierTimestamp, (int) $laterTimestamp);
        return $this->database->objectListQuery($query);
    }


}


