<?php

namespace HASH\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class BaseController {

  protected function obtainKennelKeyFromKennelAbbreviation(
      Request $request, Application $app, string $kennel_abbreviation) {

    #Define the SQL to RuntimeException
    $sql = "SELECT KENNEL_KY FROM KENNELS WHERE KENNEL_ABBREVIATION = ?";

    #Query the database
    $kennelValue = $app['db']->fetchAssoc($sql,
      array((string) $kennel_abbreviation));

    #Obtain the kennel ky from the returned object
    $returnValue = $kennelValue['KENNEL_KY'];

    #return the return value
    return $returnValue;
  }

  protected function getHareTypes($app, $kennelKy) {

    #Define the SQL to RuntimeException
    $sql = "SELECT HARE_TYPE, HARE_TYPE_NAME, CHART_COLOR
              FROM HARE_TYPES
              JOIN KENNELS
                ON KENNELS.HARE_TYPE_MASK & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE
             WHERE KENNELS.KENNEL_KY = ?
             ORDER BY HARE_TYPES.SEQ";

    #Query the database
    $hareTypes = $app['db']->fetchAll($sql, array((int) $kennelKy));

    #return the return value
    return $hareTypes;
  }

  protected function getHashTypes($app, $kennelKy, $hare_type) {

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
    $hashTypes = $app['db']->fetchAll($sql, $args);

    #return the return value
    return $hashTypes;
  }

  protected function getHareTypeName($app, $hare_type) {
    $sql = "SELECT HARE_TYPE_NAME
              FROM HARE_TYPES
             WHERE HARE_TYPES.HARE_TYPE = ?";

    #Query the database
    $result = $app['db']->fetchAssoc($sql, array((int) $hare_type));

    #return the return value
    return $result['HARE_TYPE_NAME'];
  }

  protected function getLegacyHashingsCountSubquery(
      string $hashersTableName = "HASHERS") {
    if(HAS_LEGACY_HASH_COUNTS) {
      return "COALESCE((SELECT LEGACY_HASHINGS_COUNT
         FROM LEGACY_HASHINGS
        WHERE LEGACY_HASHINGS.HASHER_KY = $hashersTableName.HASHER_KY
          AND LEGACY_HASHINGS.KENNEL_KY = HASHES.KENNEL_KY), 0)";
    }
    return "0";
  }

  protected function getHashingCountsQuery() {
   return "SELECT HASHERS.HASHER_KY AS THE_KEY,
                  HASHERS.HASHER_NAME AS NAME,
                  COUNT(0) + ".$this->getLegacyHashingsCountSubquery().
                  " AS VALUE
             FROM HASHERS
             JOIN HASHINGS ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
             JOIN HASHES on HASHINGS.HASH_KY = HASHES.HASH_KY
            WHERE HASHES.KENNEL_KY = ?
            GROUP BY HASHERS.HASHER_KY, HASHERS.HASHER_NAME
            ORDER BY VALUE DESC";
  }

  protected function getPersonsHashingCountQuery() {
    return "SELECT COUNT(*) + ".
                   $this->getLegacyHashingsCountSubquery("HASHINGS").
                   " AS THE_COUNT
              FROM HASHINGS
              JOIN HASHES
                ON HASHINGS.HASH_KY = HASHES.HASH_KY
             WHERE HASHER_KY = ? AND KENNEL_KY = ?";
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
               WHERE HASHES.KENNEL_KY = ?
               GROUP BY HARINGS.HARINGS_HASHER_KY
              ) HARING_COUNT_TEMP_TABLE ON ((HASHERS.HASHER_KY = HARING_COUNT_TEMP_TABLE.HARINGS_HASHER_KY)))
        WHERE (HASH_COUNT_TEMP_TABLE.HASH_COUNT >= ?)
        ORDER BY ((HARING_COUNT_TEMP_TABLE.HARING_COUNT / HASH_COUNT_TEMP_TABLE.HASH_COUNT) * 100) DESC";
  }

  protected function getHoundAnalversariesForEvent() {
    return "SELECT HASHERS.HASHER_NAME AS HASHER_NAME,
                   (COUNT(*)) + ".$this->getLegacyHashingsCountSubquery().
                   " AS THE_COUNT,
                   MAX(HASHES.EVENT_DATE) AS MAX_EVENT_DATE
              FROM ((HASHERS
              JOIN HASHINGS ON ((HASHERS.HASHER_KY = HASHINGS.HASHER_KY)))
              JOIN HASHES ON ((HASHINGS.HASH_KY = HASHES.HASH_KY)))
             WHERE (HASHERS.DECEASED = 0) AND
                   HASHES.EVENT_DATE <= (SELECT EVENT_DATE FROM HASHES WHERE HASHES.HASH_KY = ?) AND
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
      "SELECT HASHERS.HASHER_NAME AS HASHER_NAME,
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
                                     SELECT @NUMBERX:=@NUMBERX+100 AS THE_NUMBER
                                       FROM (SELECT null FROM HASHINGS LIMIT 10) AS CART1,
                                            (SELECT null FROM HASHINGS LIMIT 10) AS CART2,
                                            (SELECT @NUMBERX:=0) NUMBERX) DERIVEDX) DERIVEDY
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
                               SELECT THE_NUMBER
                                 FROM (SELECT @NUMBERX:=@NUMBERX+100 AS THE_NUMBER
                                         FROM (SELECT null FROM HASHINGS LIMIT 10) AS CART1,
                                              (SELECT null FROM HASHINGS LIMIT 10) AS CART2,
                                              (SELECT @NUMBERX:=0) NUMBERX
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
}
