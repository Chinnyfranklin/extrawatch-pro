<?php

/**
 * @file
 * ExtraWatch - A real-time ajax monitor and live stats
 * @package ExtraWatch
 * @version 1.2.18
 * @revision 504
 * @license http://www.gnu.org/licenses/gpl-3.0.txt     GNU General Public License v3
 * @copyright (C) 2013 by Matej Koval - All rights reserved!
 * @website http://www.extrawatch.com
 */

/** ensure this file is being included by a parent file */
if (!defined('_JEXEC') && !defined('_VALID_MOS'))
  die('Restricted access');

class ExtraWatchConfig
{

  public $database;
  public $liveSiteCached;
  public $env;

  function __construct($database)
  {
    $this->env = ExtraWatchEnvFactory::getEnvironment();
    $this->database = $database;
  }

  function getAdminitratorDirectoryName()
  {
    return $this->env->getAdminDir();
  }

  function getJavascriptDirectoryName()
  {
    return "components/com_extrawatch/js";
  }

  /**
   * config
   */
  function checkPermissions()
  {
    if (!$this->isPermitted()) {
      die("Access denied. Hacking attempt has been logged and reported.");
    }
  }

  function isPermitted()
  {
    $rand = $this->getRand();
    if (!$rand || $rand != addslashes(strip_tags(@ ExtraWatchHelper::requestGet('rand')))) {
      return FALSE;
    }
    return TRUE;
  }


  /**
   * config
   *
   * @return unknown
   */
  function getRand()
  {
    $query = sprintf("select value from #__extrawatch_config where name = 'rand' order by id desc limit 1; ");
    $rand = $this->database->resultQuery($query);
    return $rand;

  }

  /**
   * config
   */
  function isIgnored($name, $key)
  {
    if (!@$key) {
      return FALSE;
    }
    $name = strtoupper($name);
    $query = sprintf("select value from #__extrawatch_config where name='%s' limit 1", $this->database->getEscaped("EXTRAWATCH_IGNORE_" . $name));
    $rowValue = $this->database->resultQuery($query);
    $exploded = explode("\n", $rowValue);
    foreach ($exploded as $value) {
      if (ExtraWatchHelper::wildcardSearch(trim($value), $key)) {
        return TRUE;
      }
    }
    return FALSE;
  }


  /**
   * config
   */
  function updateHelperCountByKey($key, $value)
  {
    $count = $this->getCountByKey($key);

    if (@ $count) {
      $query = sprintf("update #__extrawatch_config set value = '%s' where (name = '%s' and date = '%d')", $this->database->getEscaped($value), $this->database->getEscaped($key), (int) $value);
      $this->database->executeQuery($query);
    } else {
      $query = sprintf("insert into #__extrawatch_config (id, `name`, `value`) values ('', '%s', '%s')", $this->database->getEscaped($key), $this->database->getEscaped($value));
      $this->database->executeQuery($query);
    }
  }

  /**
   * config
   */

  function getConfigValue($key)
  {

    $query = sprintf("select value from #__extrawatch_config where name = '%s' limit 1", $this->database->getEscaped($key));
    $value = $this->database->resultQuery($query);
    // explicit off for checkboxes
    if ($value == "Off") {
      return FALSE;
    }
    if ($value) {
      return addslashes($value);
    }

    $value = @ constant($key);
    return $value;
  }

  /**
   * config
   */
  function saveConfigValue($key, $value)
  {
    $query = sprintf("select count(name) as count from #__extrawatch_config where name = '%s' limit 1", $this->database->getEscaped($key));
    $count = $this->database->resultQuery($query);

    if ($count) { //update
      $query = sprintf("update #__extrawatch_config set value = '%s' where name = '%s'", $this->database->getEscaped($value), $this->database->getEscaped($key));
      $this->database->executeQuery($query);
    } else { //insert
      $query = sprintf("insert into #__extrawatch_config values ('','%s','%s')", $this->database->getEscaped($key), $this->database->getEscaped($value));
      $this->database->executeQuery($query);
    }

  }

  /**
   * config
   */
  function removeConfigValue($key, $value)
  {
    $query = sprintf("delete from #__extrawatch_config where name = '%s' limit 1", $this->database->getEscaped($key));
    $count = $this->database->resultQuery($query);
  }

  /**
   * config
   */
  function getLanguage()
  {
    $language = $this->getConfigValue("EXTRAWATCH_LANGUAGE");
    return $language;
  }

  /**
   * config
   */
  function checkLicenseAccepted()
  {
    $accepted = $this->getConfigValue("EXTRAWATCH_LICENSE_ACCEPTED");
    if (@ $accepted) {
      return TRUE;
    }
    return FALSE;
  }

  function getLicenseFilePath()
  {
    $config = new JConfig();
    $fileName = md5($this->getLiveSite());
    return $config->tmp_path . DS . $fileName . ".tmp";
  }

  /* function createLicenseFile() {
      $result = 0;
      if (!file_exists($this->getLicenseFilePath())) {
          $ourFileHandle = fopen($this->getLicenseFilePath(), 'w') or die("can't write to temp directory: ".$config->tmp_path);
          $result = fwrite($ourFileHandle, ExtraWatchHelper::getServerTime());
          fclose($ourFileHandle);
      }
      return $result;
  }*/

  function useTrial()
  {
    if (!$this->isTrial()) {
      $this->saveConfigValue("EXTRAWATCH_TRIAL_TIME", ExtraWatchDate::getUTCTimestamp());
    }
  }

  function isTrial()
  {
    if ($this->getConfigValue("EXTRAWATCH_TRIAL_TIME")) {
      return TRUE;
    }
    return FALSE;
  }

  function isTrialTimeOver()
  {
    return (!($this->getTrialVersionTimeLeft() > 0));
  }

  /**
   * config
   */
  function setLicenseAccepted()
  {
    // $this->createLicenseFile(); not used yet
    $this->saveConfigValue("EXTRAWATCH_LICENSE_ACCEPTED", "1");
    $this->setRand();
  }

  /**
   * config
   */
  function setLiveSite($liveSite)
  {
    $this->saveConfigValue("EXTRAWATCH_LIVE_SITE", "$liveSite");
  }

  /**
   * config
   */
  function getCheckboxValue($key)
  {
    $setting = $this->getConfigValue($key);
    if ($setting == '1' || strtolower($setting) == 'on') {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * If on HTTPS site, use the https links
   * @param  $originalLink
   * @return mixed
   */
  static function replaceHttpByHttps($originalLink)
  {
    $env = ExtraWatchEnvFactory::getEnvironment();
    if ($env->isSSL()) {
      return str_ireplace("http://", "https://", $originalLink);
    }
    return str_ireplace("https://", "http://", $originalLink);
  }

  function getLiveSite()
  {
    if ($this->liveSiteCached) {
      return $this->liveSiteCached;
    }
    if (!defined('EXTRAWATCH_LIVE_SITE')) {
      $liveSite = ExtraWatchConfig::replaceHttpByHttps($this->getConfigValue('EXTRAWATCH_LIVE_SITE'));
      $this->liveSiteCached = $liveSite;
      return $liveSite;
    } else {
      $liveSite = ExtraWatchConfig::replaceHttpByHttps(rtrim(constant('EXTRAWATCH_LIVE_SITE')));
      $this->liveSiteCached = $liveSite;
      return $liveSite;
    }
  }

  function checkIfLiveSiteMatches()
  {
    //TODO
  }

  //TODO
  function getAdministratorPath()
  {
    //TODO -> should be changed, there can be other directory
    return $this->getLiveSite() . $this->getAdminitratorDirectoryName() . "/";
  }

  function getAdministratorIndex()
  {
    //TODO -> should be changed, there can be other directory
    return $this->getLiveSite() . $this->getAdminitratorDirectoryName() . "/index.php";
  }


  function cleanUrl($domain)
  {
    $domain = str_replace("http://", "", $domain);
    $domain = str_replace("https://", "", $domain);
    $domain = str_replace("www.", "", $domain);
    return $domain;
  }

  /**
   * Validate whether string is an IP address
   * @param  $string
   * @return bool
   */
  function isIPAddress($string)
  {
    $regexp = '/^((1?\d{1,2}|2[0-4]\d|25[0-5])\.){3}(1?\d{1,2}|2[0-4]\d|25[0-5])$/';
    if (preg_match($regexp, $string)) {
      return TRUE;
    }
    return FALSE;
  }

  function getDomainFromLiveSite()
  {
    $parsedUrl = @ parse_url(@$this->getLiveSite());
    $domainWithSubdomain = trim($this->cleanUrl(@$parsedUrl[host]));

    /* if it's an IP address */
    if ($this->isIPAddress($domainWithSubdomain)) {
      return $domainWithSubdomain;
    }

    /** should extract only domain, not subdomain */
    //        preg_match('/^((.+)\.)?([A-Za-z][0-9A-Za-z\-]{1,63})\.(co\.uk|me\.uk|org\.uk|com|org|net|int|eu)(\/.*)?$/', $domainWithSubdomain, $matches);
    //        return @$matches[0];

    $splittedDomain = explode(".", $domainWithSubdomain);
    $size = sizeof($splittedDomain);

    if ($size <= 1) {
      return $splittedDomain[0]; // if it's localhost or just some hostname
    }

    // by Eman Borg:
    // co.uk, com.br, com.pl fix:
    // if middle domain name is less than 3 chars, we assume it's 1st level domain,
    if (strlen($splittedDomain[$size - 2]) <= 3) {
      return $splittedDomain[$size - 3] . "." . $splittedDomain[$size - 2] . "." . $splittedDomain[$size - 1];
    }
    else {
      return $splittedDomain[$size - 2] . "." . $splittedDomain[$size - 1];
    }
  }


  /**
   * config
   */
  function isAdFree()
  {
    if ($this->getConfigValue("EXTRAWATCH_ADFREE")) {
      return TRUE;
    }
  }

  /**
   * config
   */
  function isFree()
  {
    if ($this->getConfigValue("EXTRAWATCH_FREE")) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * config
   */
  function activate($value)
  {
    $this->saveConfigValue('EXTRAWATCH_ADFREE', $this->database->getEscaped($value));
    $this->saveConfigValue('EXTRAWATCH_FRONTEND_HIDE_LOGO', "on");
    $this->saveConfigValue('EXTRAWATCH_FRONTEND_NOFOLLOW', "on");
    $this->saveConfigValue('EXTRAWATCH_FRONTEND_NO_BACKLINK', "on");
    if ($this->isAdFree()) {
      echo("<span style='color: green'>" . _EW_CONFIG_LICENSE_ACTIVATED . "</span>");
      $this->saveConfigValue('EXTRAWATCH_FREE', 0);
    } else {
      echo("<span style='color: red'>" . _EW_CONFIG_LICENCE_DONT_MATCH . "</span>");
    }
  }


  /**
   * config
   */
  function useFreeVersion()
  {
    $this->saveConfigValue('EXTRAWATCH_FREE', (int)1);
  }

  /**
   * Important when doing an upgrade to data between versions, we need to know to which version these data belong
   * @return void
   */
  function saveVersionIntoDatabase()
  {
    $this->saveConfigValue('EXTRAWATCH_VERSION', EXTRAWATCH_VERSION);
  }


  function setRand()
  {
    $rand = md5(md5(mt_rand()) + mt_rand());
    $query = sprintf("INSERT INTO #__extrawatch_config (id, name, value) values ('', 'rand', '%s') ", $this->database->getEscaped($rand));
    $this->database->executeQuery(trim($query));
  }

  function checkLiveSite()
  {
    if ($this->getLiveSite() == $this->env->getRootSite()) {
      return TRUE;
    }
    return FALSE;
  }

  function getTrialVersionTimeLeft()
  {
    return (int)(16 - ((ExtraWatchDate::getUTCTimestamp() - $this->getConfigValue("EXTRAWATCH_TRIAL_TIME")) / 3600 / 24) - 0.01); //because it will display 15 days as time left
  }

  function getLiveSiteWithSuffix()
  {
    return $this->getLiveSite() . $this->env->getEnvironmentSuffix();
  }

  function renderLink($task = "", $otherParams = "")
  {
    return $this->env->renderLink($task, $otherParams);
  }

  function getEnvironment()
  {
    return get_class($this->env);
  }

  function getRandHash()
  {
    return md5(md5(ExtraWatchConfig::getRand()));
  }

  /**
   * For things like heatmap etc..
   * @return bool
   */
  function isPermittedWithHash($hash)
  {
    $randHash = ExtraWatchConfig::getRandHash();
    if (!$randHash || $randHash != addslashes(strip_tags($hash))) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Used by both - save anti-spam and save settings
   * @param  $checkboxNamesArray
   * @param  $post
   * @return void
   */
  function saveConfigValues($checkboxNamesArray, $post)
  {

    foreach ($post as $key => $value) {
      if (strstr($key, "EXTRAWATCH_")) {
        $this->saveConfigValue($key, trim($value));
      }
    }
    //hack :( explicitly save checkbox values
    foreach (@$checkboxNamesArray as $key => $value) {
      if (@ !$post[$value]) { //if there is no value - checkbox unchecked
        $this->saveConfigValue($value, "Off");
      }
    }
    // explicitly reset chache because of frontend settings
    ExtraWatchCache::clearCache($this->database);

  }

}


