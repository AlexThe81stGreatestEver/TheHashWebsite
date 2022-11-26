<?php

namespace HASH\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Container\ContainerInterface;

class BaseController {

  protected ContainerInterface $container;

  protected function __construct(ContainerInterface $container) {
    $this->container = $container;
  }

  // fetch all - ignore query errors
  // used in slashaction2 to allow the home page to render even if there
  // are integrity issues in the database (currently having duplicate event
  // date/times causes issues)
  protected function fetchAllIgnoreErrors(string $sql, array $args = null) {
    try {
      return $this->fetchAll($sql, $args);
    } catch(\Exception $e) {
      // ignore
    }
    return [];
  }

  protected function fetchAll(string $sql, array $args = null) {
    if($args == null) {
      $result = $this->container->get('db')->fetchAllAssociative($sql);
    } else {
      $result = $this->container->get('db')->fetchAllAssociative($sql, $args);
    }
    if(defined('SHOW_WARNINGS')) {
      $this->show_warnings($sql);
    }
    return $result;
  }

  protected function fetchOne(string $sql, array $args = null) {
    if($args == null) {
      $result = $this->container->get('db')->fetchOne($sql);
    } else {
      $result = $this->container->get('db')->fetchOne($sql, $args);
    }
    if(defined('SHOW_WARNINGS')) {
      $this->show_warnings($sql);
    }
    return $result;
  }

  protected function fetchAssoc(string $sql, array $args = null) {
    if($args == null) {
      $result = $this->container->get('db')->fetchAssociative($sql);
    } else {
      $result = $this->container->get('db')->fetchAssociative($sql, $args);
    }
    if(defined('SHOW_WARNINGS')) {
      $this->show_warnings($sql);
    }
    return $result;
  }

  private function show_warnings(string $sql) {
    if(SHOW_WARNINGS) {
      $warnings = $this->container->get('db')->fetchAllAssociative("SHOW WARNINGS");
      foreach($warnings as $warning) {
        print("WARNING:");
        foreach($warning as $message) {
          print($message);
        }
        print("($sql)");
      }
    }
  }

  // Add common page arguments, then dispatch to twig to render page
  protected function render(string $template, array $args) {

    $args['google_analytics_id'] = $this->getGoogleAnalyticsId();
    $args['site_banner'] = $this->getSiteBanner();
    $args['use_consolidated_switch_kennel_page'] = $this->useConsolidatedSwitchKennelPage();

    return new Response($this->container->get('twig')->render($template, $args));
  }

  protected function hasLegacyHashCounts() {
    $sql = "SELECT value FROM SITE_CONFIG WHERE name='has_legacy_hash_counts'";
    return $this->fetchOne($sql) == "true";
  }

  private function useConsolidatedSwitchKennelPage() {
    $sql = "SELECT value FROM SITE_CONFIG WHERE name='use_consolidated_switch_kennel_page'";
    return $this->fetchOne($sql) == "true";
  }

  protected function showOmniAnalversaryPage() {
    $sql = "SELECT value FROM SITE_CONFIG WHERE name='show_omni_analversary_page'";
    return $this->fetchOne($sql) == "true";
  }

  protected function showBudgetPage() {
    $sql = "SELECT value FROM SITE_CONFIG WHERE name='show_budget_page'";
    return $this->fetchOne($sql) == "true";
  }

  protected function showAwardsPage() {
    $sql = "SELECT value FROM SITE_CONFIG WHERE name='show_awards_page'";
    return $this->fetchOne($sql) == "true";
  }

  protected function getSiteBanner() {
    $sql = "SELECT value FROM SITE_CONFIG WHERE name='site_banner'";
    return $this->fetchOne($sql);
  }

  protected function getAdministratorEmail() {
    $sql = "SELECT value FROM SITE_CONFIG WHERE name='administrator_email'";
    return $this->fetchOne($sql);
  }

  protected function getDefaultKennel() {
    $sql = "SELECT value FROM SITE_CONFIG WHERE name='default_kennel'";
    return $this->fetchOne($sql);
  }

  protected function getGoogleAnalyticsId() {
    $sql = "SELECT value FROM SITE_CONFIG WHERE name='google_analytics_id'";
    return $this->fetchOne($sql);
  }

  protected function getGooglePlacesApiWebServiceKey() {
    $sql = "SELECT value FROM SITE_CONFIG WHERE name='google_places_api_web_service_key'";
    return $this->fetchOne($sql);
  }

  protected function getGoogleMapsJavascriptApiKey() {
    $sql = "SELECT value FROM SITE_CONFIG WHERE name='google_maps_javascript_api_key'";
    return $this->fetchOne($sql);
  }

  protected function getDefaultAwardEventHorizon() {
    $sql = "SELECT value FROM SITE_CONFIG WHERE name='default_award_event_horizon'";
    return (int)$this->fetchOne($sql);
  }

  protected function getSiteConfigItemAsInt(string $name, int $defaultValue) {
    $sql = "SELECT VALUE FROM SITE_CONFIG WHERE NAME = ?";

    $value = (int) $this->fetchOne($sql, array($name));
    if(!$value) {
      $value = $defaultValue;
    }

    return $value;
  }

  protected function getSiteConfigItem(string $name, string $defaultValue) {
    $sql = "SELECT VALUE FROM SITE_CONFIG WHERE NAME = ?";

    $value = $this->fetchOne($sql, array($name));
    if(!$value) {
      $value = $defaultValue;
    }

    return $value;
  }

  protected function obtainKennelKeyFromKennelAbbreviation(string $kennel_abbreviation) {

    #Define the SQL to RuntimeException
    $sql = "SELECT KENNEL_KY FROM KENNELS WHERE KENNEL_ABBREVIATION = ?";

    #Query the database
    $kennelValue = $this->fetchAssoc($sql, array($kennel_abbreviation));

    if(!$kennelValue) {
      // don't fail after initial install when kennel abbrev might not yet be set
      if($kennel_abbreviation != "**NEEDS UPDATED**") {
        throw new \Exception("Bad kennel abbreviation");
      }
      return 0;
    }

    #Obtain the kennel ky from the returned object
    return (int) $kennelValue['KENNEL_KY'];
  }

  protected function getHareTypes($kennelKy) {

    #Define the SQL to RuntimeException
    $sql = "SELECT HARE_TYPE, HARE_TYPE_NAME, CHART_COLOR
              FROM HARE_TYPES
              JOIN KENNELS
                ON KENNELS.HARE_TYPE_MASK & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE
             WHERE KENNELS.KENNEL_KY = ?
             ORDER BY HARE_TYPES.SEQ";

    #Query the database
    $hareTypes = $this->fetchAll($sql, array((int) $kennelKy));

    #return the return value
    return $hareTypes;
  }

  protected function getHashTypes($kennelKy, $hare_type) {

    #Define the SQL to RuntimeException
    $sql = "SELECT HASH_TYPES.HASH_TYPE, HASH_TYPES.HASH_TYPE_NAME
	      FROM HASH_TYPES
	      JOIN KENNELS
		ON HASH_TYPES.HASH_TYPE & KENNELS.HASH_TYPE_MASK != 0
	     WHERE KENNELS.KENNEL_KY = ?".
	     ($hare_type == 0 ? "" : "AND HASH_TYPES.HARE_TYPE_MASK & ? != 0")."
	     ORDER BY HASH_TYPES.SEQ";

    #Query the database
    $args = array((int) $kennelKy);
    if($hare_type != 0) array_push($args, $hare_type);
    $hashTypes = $this->fetchAll($sql, $args);

    #return the return value
    return $hashTypes;
  }

  protected function getHareTypeName($hare_type) {
    $sql = "SELECT HARE_TYPE_NAME
              FROM HARE_TYPES
             WHERE HARE_TYPES.HARE_TYPE = ?";

    #Query the database
    $result = $this->fetchAssoc($sql, array((int) $hare_type));

    #return the return value
    return $result['HARE_TYPE_NAME'];
  }

  protected function getLegacyHashingsCountSubquery(
      string $hashersTableName = "HASHERS") {
    if($this->hasLegacyHashCounts()) {
      return "COALESCE((SELECT LEGACY_HASHINGS_COUNT
         FROM LEGACY_HASHINGS
        WHERE LEGACY_HASHINGS.HASHER_KY = $hashersTableName.HASHER_KY
          AND LEGACY_HASHINGS.KENNEL_KY = HASHES.KENNEL_KY), 0)";
    }
    return "0";
  }

  function getLatestEventSubquery(bool $includeLatestEvent) {
    if($includeLatestEvent) {
      return ", (
        SELECT MAX(HASHES2.EVENT_DATE)
          FROM HASHES HASHES2
          JOIN HASHINGS
            ON HASHINGS.HASH_KY = HASHES2.HASH_KY
         WHERE HASHINGS.HASHER_KY = THE_KEY
           AND HASHES2.KENNEL_KY = ?) AS LATEST_EVENT
      ";
    }

    return "";
  }

  protected function getHaringCountsQuery(bool $includeLatestEvent = false) {

    $le = $this->getLatestEventSubquery($includeLatestEvent);

    return
      "SELECT HASHERS.HASHER_KY AS THE_KEY,
	      HASHERS.HASHER_NAME AS NAME,
	      COUNT(0) AS VALUE
              $le
         FROM HASHERS
	 JOIN HARINGS ON HASHERS.HASHER_KY = HARINGS.HARINGS_HASHER_KY
         JOIN HARE_TYPES ON HARINGS.HARE_TYPE & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE
	 JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
        WHERE HASHES.KENNEL_KY = ?
        GROUP BY HASHERS.HASHER_KY, HASHERS.HASHER_NAME
        ORDER BY VALUE DESC";
  }

  protected function getHaringCountsByTypeQuery(bool $includeLatestEvent = false) {

    $le = $this->getLatestEventSubquery($includeLatestEvent);

    return
      "SELECT HASHERS.HASHER_KY AS THE_KEY,
	      HASHERS.HASHER_NAME AS NAME,
	      COUNT(0) AS VALUE
              $le
         FROM HASHERS
	 JOIN HARINGS ON HASHERS.HASHER_KY = HARINGS.HARINGS_HASHER_KY
	 JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
        WHERE HARINGS.HARE_TYPE & ? != 0
          AND HASHES.KENNEL_KY = ?
     GROUP BY HASHERS.HASHER_KY, HASHERS.HASHER_NAME
     ORDER BY VALUE DESC";
  }

  protected function getHashingCountsQuery(bool $considerLegacyRuns = true, bool $includeLatestEvent = false) {

   $le1 = "";
   $le2 = "";
   $le3 = "";

   if($includeLatestEvent) {
     $le1 = ", MAX(HASHES.EVENT_DATE) AS LATEST_EVENT";
     $le2 = ", NULL AS LATEST_EVENT";
     $le3 = ", LATEST_EVENT";
   }

   if($this->hasLegacyHashCounts() && $considerLegacyRuns) {
     return "SELECT THE_KEY, NAME, SUM(VALUE) AS VALUE, KENNEL_KY $le3
               FROM (
             SELECT HASHERS.HASHER_KY AS THE_KEY,
                    HASHERS.HASHER_NAME AS NAME,
                    COUNT(0) AS VALUE,
                    HASHES.KENNEL_KY AS KENNEL_KY
                    $le1
               FROM HASHERS
               JOIN HASHINGS ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
               JOIN HASHES on HASHINGS.HASH_KY = HASHES.HASH_KY
              WHERE HASHES.KENNEL_KY = ?
              GROUP BY HASHERS.HASHER_KY, HASHERS.HASHER_NAME
              UNION ALL
             SELECT HASHERS.HASHER_KY AS THE_KEY,
                    HASHERS.HASHER_NAME AS NAME,
                    LEGACY_HASHINGS.LEGACY_HASHINGS_COUNT AS VALUE,
                    LEGACY_HASHINGS.KENNEL_KY AS KENNEL_KY
                    $le2
               FROM HASHERS
               JOIN LEGACY_HASHINGS ON HASHERS.HASHER_KY = LEGACY_HASHINGS.HASHER_KY
              WHERE LEGACY_HASHINGS.KENNEL_KY = ?) AS HASH_COUNTS_INNER
              GROUP BY THE_KEY, NAME, KENNEL_KY
              ORDER BY VALUE DESC";
   }

   return "SELECT HASHERS.HASHER_KY AS THE_KEY,
                  HASHERS.HASHER_NAME AS NAME,
                  COUNT(0) AS VALUE,
                  HASHES.KENNEL_KY AS KENNEL_KY
                  $le1
             FROM HASHERS
             JOIN HASHINGS ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
             JOIN HASHES on HASHINGS.HASH_KY = HASHES.HASH_KY
            WHERE HASHES.KENNEL_KY = ? AND ? != -1
            GROUP BY HASHERS.HASHER_KY, HASHERS.HASHER_NAME
            ORDER BY VALUE DESC";
  }

  protected function getPersonsHashingCountQuery() {
    if($this->hasLegacyHashCounts()) {
      return "SELECT SUM(THE_COUNT) AS THE_COUNT
                FROM (
              SELECT COUNT(*) AS THE_COUNT
                FROM HASHINGS
                JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
               WHERE HASHER_KY = ? AND KENNEL_KY = ?
               UNION ALL
              SELECT LEGACY_HASHINGS.LEGACY_HASHINGS_COUNT AS THE_COUNT
                FROM LEGACY_HASHINGS
               WHERE HASHER_KY = ? AND KENNEL_KY = ?) AS INNER_QUERY";
    }

    return "SELECT COUNT(*) AS THE_COUNT
              FROM HASHINGS
              JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
             WHERE HASHER_KY = ? AND KENNEL_KY = ?
               AND ? != -1 AND ? != -1";
  }

  protected function getHaringPercentageByHareTypeQuery() {
    return
      "SELECT HASHERS.HASHER_NAME AS HASHER_NAME,
              HASH_COUNT_TEMP_TABLE.HASH_COUNT AS HASH_COUNT,
              HARING_COUNT_TEMP_TABLE.HARING_COUNT AS HARING_COUNT,
              ((HARING_COUNT_TEMP_TABLE.HARING_COUNT / HASH_COUNT_TEMP_TABLE.HASH_COUNT) * 100) AS HARE_PERCENTAGE
         FROM ((HASHERS
         JOIN (SELECT HASHINGS.HASHER_KY AS HASHER_KY,
                      COUNT(HASHINGS.HASHER_KY) + ".
                      $this->getLegacyHashingsCountSubquery("HASHINGS").
                      " AS HASH_COUNT
                 FROM HASHINGS
                 JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
                WHERE HASHES.KENNEL_KY = ?
                GROUP BY HASHINGS.HASHER_KY
              ) HASH_COUNT_TEMP_TABLE ON ((HASHERS.HASHER_KY = HASH_COUNT_TEMP_TABLE.HASHER_KY)))
         JOIN (SELECT HARINGS.HARINGS_HASHER_KY AS HARINGS_HASHER_KY,
                      COUNT(HARINGS.HARINGS_HASHER_KY) AS HARING_COUNT
                 FROM HARINGS
                 JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
                WHERE HASHES.KENNEL_KY = ?
                  AND HARINGS.HARE_TYPE & ? != 0
                GROUP BY HARINGS.HARINGS_HASHER_KY
              ) HARING_COUNT_TEMP_TABLE ON ((HASHERS.HASHER_KY = HARING_COUNT_TEMP_TABLE.HARINGS_HASHER_KY)))
        WHERE (HASH_COUNT_TEMP_TABLE.HASH_COUNT >= ?)
        ORDER BY ((HARING_COUNT_TEMP_TABLE.HARING_COUNT / HASH_COUNT_TEMP_TABLE.HASH_COUNT) * 100) DESC";
  }

  protected function getHaringPercentageAllHashesQuery() {
    return
      "SELECT HASHERS.HASHER_NAME AS HASHER_NAME,
              HASH_COUNT_TEMP_TABLE.HASH_COUNT AS HASH_COUNT,
              HARING_COUNT_TEMP_TABLE.HARING_COUNT AS HARING_COUNT,
              ((HARING_COUNT_TEMP_TABLE.HARING_COUNT / HASH_COUNT_TEMP_TABLE.HASH_COUNT) * 100) AS HARE_PERCENTAGE
         FROM ((HASHERS
         JOIN (SELECT HASHINGS.HASHER_KY AS HASHER_KY,
                      COUNT(HASHINGS.HASHER_KY) + ".
                      $this->getLegacyHashingsCountSubquery("HASHINGS").
                      " AS HASH_COUNT
                 FROM HASHINGS
                 JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
                WHERE HASHES.KENNEL_KY = ?
                GROUP BY HASHINGS.HASHER_KY
              ) HASH_COUNT_TEMP_TABLE ON ((HASHERS.HASHER_KY = HASH_COUNT_TEMP_TABLE.HASHER_KY)))
         JOIN (SELECT HARINGS.HARINGS_HASHER_KY AS HARINGS_HASHER_KY,
                      COUNT(HARINGS.HARINGS_HASHER_KY) AS HARING_COUNT
                FROM HARINGS
                JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
                JOIN HARE_TYPES ON HARINGS.HARE_TYPE & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE
               WHERE HASHES.KENNEL_KY = ?
               GROUP BY HARINGS.HARINGS_HASHER_KY
              ) HARING_COUNT_TEMP_TABLE ON ((HASHERS.HASHER_KY = HARING_COUNT_TEMP_TABLE.HARINGS_HASHER_KY)))
        WHERE (HASH_COUNT_TEMP_TABLE.HASH_COUNT >= ?)
        ORDER BY ((HARING_COUNT_TEMP_TABLE.HARING_COUNT / HASH_COUNT_TEMP_TABLE.HASH_COUNT) * 100) DESC";
  }

  protected function getPendingCenturionsForEvent() {
    return "SELECT HASHERS.HASHER_NAME AS HASHER_NAME,
                   (COUNT(*)) + ".$this->getLegacyHashingsCountSubquery().
                   " AS THE_COUNT,
                   MAX(HASHES.EVENT_DATE) AS MAX_EVENT_DATE
              FROM ((HASHERS
              JOIN HASHINGS ON ((HASHERS.HASHER_KY = HASHINGS.HASHER_KY)))
              JOIN HASHES ON ((HASHINGS.HASH_KY = HASHES.HASH_KY)))
             WHERE HASHES.EVENT_DATE <= (SELECT EVENT_DATE FROM HASHES WHERE HASHES.HASH_KY = ?) AND
                   HASHES.KENNEL_KY = ?
             GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
            HAVING (((THE_COUNT + 1) % 100) = 0)
	       AND MAX_EVENT_DATE = (SELECT EVENT_DATE FROM HASHES WHERE HASHES.HASH_KY = ?)
             ORDER BY THE_COUNT DESC";
  }

  protected function getHoundAnalversariesForEvent() {
    return "SELECT HASHERS.HASHER_NAME AS HASHER_NAME,
                   (COUNT(*)) + ".$this->getLegacyHashingsCountSubquery().
                   " AS THE_COUNT,
                   MAX(HASHES.EVENT_DATE) AS MAX_EVENT_DATE
              FROM ((HASHERS
              JOIN HASHINGS ON ((HASHERS.HASHER_KY = HASHINGS.HASHER_KY)))
              JOIN HASHES ON ((HASHINGS.HASH_KY = HASHES.HASH_KY)))
             WHERE HASHES.EVENT_DATE <= (SELECT EVENT_DATE FROM HASHES WHERE HASHES.HASH_KY = ?) AND
                   HASHES.KENNEL_KY = ?
             GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
            HAVING ((((THE_COUNT % 5) = 0)
                OR ((THE_COUNT % 69) = 0)
                OR ((THE_COUNT % 666) = 0)
                OR (((THE_COUNT - 69) % 100) = 0)))
	       AND MAX_EVENT_DATE = (SELECT EVENT_DATE FROM HASHES WHERE HASHES.HASH_KY = ?)
             ORDER BY THE_COUNT DESC";
  }

  protected function getPendingHasherAnalversariesQuery() {
    return
      "SELECT HASHERS.HASHER_NAME AS HASHER_NAME, HASHERS.HASHER_KY,
              COUNT(0) + ? + ".$this->getLegacyHashingsCountSubquery().
              " AS THE_COUNT_INCREMENTED,
              TIMESTAMPDIFF(YEAR, MAX(HASHES.EVENT_DATE), CURDATE()) AS YEARS_ABSENCE
         FROM ((HASHERS
         JOIN HASHINGS ON ((HASHERS.HASHER_KY = HASHINGS.HASHER_KY)))
         JOIN HASHES ON ((HASHINGS.HASH_KY = HASHES.HASH_KY)))
        WHERE (HASHERS.DECEASED = 0)
          AND HASHES.KENNEL_KY = ?
        GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
       HAVING ((((THE_COUNT_INCREMENTED % 5) = 0)
           OR ((THE_COUNT_INCREMENTED % 69) = 0)
           OR ((THE_COUNT_INCREMENTED % 666) = 0)
           OR (((THE_COUNT_INCREMENTED - 69) % 100) = 0))
          AND (YEARS_ABSENCE < ?))
        ORDER BY THE_COUNT_INCREMENTED DESC";
  }

  protected function getPredictedHasherAnalversariesQuery() {
    return
      "SELECT HASHER_NAME, HASHER_KEY, TOTAL_HASH_COUNT, NEXT_MILESTONE,
              CURDATE() + INTERVAL ROUND(DAYS_BETWEEN_HASHES * (NEXT_MILESTONE - TOTAL_HASH_COUNT)) DAY AS PREDICTED_MILESTONE_DATE
         FROM (SELECT HASHER_NAME, OUTER_HASHER_KY AS HASHER_KEY, TOTAL_HASH_COUNT,
                      ((DATEDIFF(CURDATE(),RECENT_FIRST_HASH.EVENT_DATE)) / RECENT_HASH_COUNT) AS DAYS_BETWEEN_HASHES, (
                      SELECT MIN(MILESTONE)
                        FROM (SELECT 25 AS MILESTONE
                               UNION
                              SELECT 50
                               UNION
                              SELECT 69
                               UNION
                              SELECT THE_NUMBER FROM (
                                     SELECT 100 * ROW_NUMBER() OVER() AS THE_NUMBER
                                       FROM (SELECT null FROM HASHINGS LIMIT 10) AS CART1,
                                            (SELECT null FROM HASHINGS LIMIT 10) AS CART2
                                            ) DERIVEDX) DERIVEDY
                               WHERE MILESTONE > TOTAL_HASH_COUNT
                                 AND KENNEL_KY=?) AS NEXT_MILESTONE
                 FROM (SELECT HASHERS.*, HASHERS.HASHER_KY AS OUTER_HASHER_KY, (
                              SELECT COUNT(*) + ".$this->getLegacyHashingsCountSubquery()."
                                FROM HASHINGS
                                JOIN HASHES
                                  ON HASHINGS.HASH_KY = HASHES.HASH_KY
                               WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                                 AND HASHES.KENNEL_KY = ?
                      ) AS TOTAL_HASH_COUNT, (
                              SELECT COUNT(*)
                                FROM HASHINGS
                                JOIN HASHES
                                  ON HASHINGS.HASH_KY = HASHES.HASH_KY
                               WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                                 AND HASHES.KENNEL_KY = ?
                                 AND HASHES.EVENT_DATE >= (CURDATE() - INTERVAL ? DAY)) AS RECENT_HASH_COUNT, (
                                     SELECT HASHES.HASH_KY
                                       FROM HASHINGS
                                       JOIN HASHES
                                         ON HASHINGS.HASH_KY = HASHES.HASH_KY
                                      WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                                        AND HASHES.KENNEL_KY = ?
                                        AND HASHES.EVENT_DATE >= (CURDATE() - INTERVAL ? DAY)
                                      ORDER BY HASHES.EVENT_DATE ASC LIMIT 1) AS RECENT_FIRST_HASH_KEY
                         FROM HASHERS) AS MAIN_TABLE
                 JOIN HASHES RECENT_FIRST_HASH ON RECENT_FIRST_HASH.HASH_KY = RECENT_FIRST_HASH_KEY
                WHERE RECENT_HASH_COUNT > 1) AS OUTER1
        ORDER BY PREDICTED_MILESTONE_DATE";
  }

  protected function getPredictedCenturionsQuery() {
    return
      "SELECT HASHER_NAME, HASHER_KEY, TOTAL_HASH_COUNT, NEXT_MILESTONE,
              CURDATE() + INTERVAL ROUND(DAYS_BETWEEN_HASHES * (NEXT_MILESTONE - TOTAL_HASH_COUNT)) DAY AS PREDICTED_MILESTONE_DATE
         FROM (SELECT HASHER_NAME, OUTER_HASHER_KY AS HASHER_KEY, TOTAL_HASH_COUNT,
                      ((DATEDIFF(CURDATE(),RECENT_FIRST_HASH.EVENT_DATE)) / RECENT_HASH_COUNT) AS DAYS_BETWEEN_HASHES,
                      (SELECT MIN(MILESTONE)
                         FROM (SELECT 100 AS MILESTONE
                                UNION
                              SELECT THE_NUMBER FROM (
                                     SELECT 100 * ROW_NUMBER() OVER() AS THE_NUMBER
                                       FROM (SELECT null FROM HASHINGS LIMIT 10) AS CART1,
                                            (SELECT null FROM HASHINGS LIMIT 10) AS CART2
                                            ) DERIVEDX) DERIVEDY
                                WHERE MILESTONE > TOTAL_HASH_COUNT
                                  AND KENNEL_KY=?) AS NEXT_MILESTONE
                 FROM (SELECT HASHERS.*, HASHERS.HASHER_KY AS OUTER_HASHER_KY, (
                              SELECT COUNT(*) + ".$this->getLegacyHashingsCountSubquery()."
                                FROM HASHINGS JOIN HASHES
                                  ON HASHINGS.HASH_KY = HASHES.HASH_KY
                               WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                                 AND HASHES.KENNEL_KY = ?
                              ) AS TOTAL_HASH_COUNT, (
                              SELECT COUNT(*)
                                FROM HASHINGS JOIN HASHES
                                  ON HASHINGS.HASH_KY = HASHES.HASH_KY
                               WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                                 AND HASHES.KENNEL_KY = ?
                                 AND HASHES.EVENT_DATE >= (CURDATE() - INTERVAL ? DAY)) AS RECENT_HASH_COUNT, (
                                     SELECT HASHES.HASH_KY
                                       FROM HASHINGS
                                       JOIN HASHES
                                         ON HASHINGS.HASH_KY = HASHES.HASH_KY
                                      WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                                        AND HASHES.KENNEL_KY = ?
                                        AND HASHES.EVENT_DATE >= (CURDATE() - INTERVAL ? DAY)
                                      ORDER BY HASHES.EVENT_DATE ASC LIMIT 1) AS RECENT_FIRST_HASH_KEY
                         FROM HASHERS) AS MAIN_TABLE
                 JOIN HASHES RECENT_FIRST_HASH
                   ON RECENT_FIRST_HASH.HASH_KY = RECENT_FIRST_HASH_KEY
                WHERE RECENT_HASH_COUNT > 1) AS OUTER1
        ORDER BY PREDICTED_MILESTONE_DATE";
  }

  protected function auditTheThings(Request $request, string $actionType, string $actionDescription) {

    #Define the client ip address
    $theClientIP = $request->getClientIp();

    #Establish the datetime representation of "now"
    date_default_timezone_set('US/Eastern');
    $nowDateTime = date("Y-m-d H:i:s");

    #Define the username (default to UNKNOWN)
    $user = "UNKNOWN";

    #Determine the username
    $token = $this->container->get('security.token_storage')->getToken();
    if (null !== $token) {
      $user = $token->getUser();
    }

    #Define the sql insert statement
    $sql = "
      INSERT INTO AUDIT (
        USERNAME,
        AUDIT_TIME,
        ACTION_TYPE,
        ACTION_DESCRIPTION,
        IP_ADDR
      ) VALUES (?, ?, ?, ?, ?)";

    #Execute the insert statement
    $this->container->get('dbs')['mysql_write']->executeUpdate($sql,array(
      $user,
      $nowDateTime,
      $actionType,
      $actionDescription,
      $theClientIP
    ));
  }

  protected function getMostRecentHash(int $kennelKy) {
    # Declare the SQL to get the most recent hash
    $sqlMostRecentHash = "SELECT KENNEL_EVENT_NUMBER, EVENT_LOCATION
      FROM HASHES
      WHERE HASHES.KENNEL_KY = ?
      ORDER BY HASHES.EVENT_DATE DESC
      LIMIT 1";

    # Execute the SQL to get the most recent hash
    $theMostRecentHashValue = $this->fetchAssoc($sqlMostRecentHash, array($kennelKy));

    return "The most recent hash was: $theMostRecentHashValue[KENNEL_EVENT_NUMBER]
      at $theMostRecentHashValue[EVENT_LOCATION]";
  }

  private function getCsrfKeyForUser(string $key) {
    return $key."-".$this->container->get('user').'username';
  }

  protected function getCsrfToken(string $key) {
    return $this->container->get('csrf.token_manager')->getToken(
      $this->getCsrfKeyForUser($key));
  }

  protected function validateCsrfToken(string $key, string $token) {
    if($token != $this->getCsrfToken($key)) {
      throw new \Exception("Bad request");
    }
  }
}
