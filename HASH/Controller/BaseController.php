<?php

namespace HASH\Controller;

class BaseController {

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
                   MAX(HASHINGS.HASH_KY) AS MAX_HASH_KY
              FROM ((HASHERS
              JOIN HASHINGS ON ((HASHERS.HASHER_KY = HASHINGS.HASHER_KY)))
              JOIN HASHES ON ((HASHINGS.HASH_KY = HASHES.HASH_KY)))
             WHERE (HASHERS.DECEASED = 0) AND
                   HASHES.HASH_KY <= ? AND
                   HASHES.KENNEL_KY = ?
             GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
            HAVING ((((THE_COUNT % 5) = 0)
                OR ((THE_COUNT % 69) = 0)
                OR ((THE_COUNT % 666) = 0)
                OR (((THE_COUNT - 69) % 100) = 0)))
               AND MAX_HASH_KY = ?
             ORDER BY THE_COUNT DESC";
  }
}
