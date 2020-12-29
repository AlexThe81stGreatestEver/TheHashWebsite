<?php

use Silex\Application;

class DatabaseUpdater {

  private Application $app;
  private string $db;

  function __construct(Application $app, string $db) {

    $this->app = $app;
    $this->db = $db;

    $databaseVersion = $this->getDatabaseVersion();

    switch(intVal($databaseVersion)) {
      case 0:
        $this->createAuditTable();
        $this->createHashersTable();
        $this->createKennelsTable();
        $this->createHashesTable();
        $this->createHashingsTable();
        $this->createHaringsTable();
        $this->createHashesTagsTable();
        $this->createHashesTagJunctionTable();
        $this->createCompositeIndexes();
        $this->setDatabaseVersion(1);
      case 1:
      default:
        break;
    }
  }

  private function setDatabaseVersion(int $version) {
    $sql = "UPDATE STATS_CONFIG SET VALUE=? WHERE NAME='database_version'";
    $this->app['dbs']['mysql_write']->executeStatement($sql, array(strval($version)));
  }

  private function createCompositeIndexes() {
    // TODO: figure out way to check if index exists instead of trying to create and ignoring error
    $this->executeStatementIgnoreError("CREATE INDEX COMPOSITE1_idx ON HARINGS(HARINGS_HASHER_KY, HARINGS_HASH_KY)");
    $this->executeStatementIgnoreError("CREATE INDEX COMPOSITE1_idx ON HASHERS(HASHER_KY, HASHER_NAME)");
    $this->executeStatementIgnoreError("CREATE INDEX COMPOSITE1_idx ON HASHES(HASH_KY, KENNEL_KY)");
    $this->executeStatementIgnoreError("CREATE INDEX COMPOSITE2_idx ON HASHES(HASH_KY, EVENT_DATE, KENNEL_KY)");
    $this->executeStatementIgnoreError("CREATE INDEX COMPOSITE3_idx ON HASHES(EVENT_DATE, HASH_KY, KENNEL_KY)");
    $this->executeStatementIgnoreError("CREATE INDEX COMPOSITE1_idx ON HASHES_TAG_JUNCTION(HASHES_KY, HASHES_TAGS_KY)");
    $this->executeStatementIgnoreError("CREATE INDEX COMPOSITE1_idx ON HASHES_TAGS(HASHES_TAGS_KY, TAG_TEXT)");
    $this->executeStatementIgnoreError("CREATE INDEX COMPOSITE1_idx ON HASHINGS(HASHER_KY, HASH_KY)");
  }

  private function executeStatementIgnoreError(string $sql) {
    try {
      $this->app['dbs']['mysql_write']->executeStatement($sql,array());
    } catch(Exception $e) {
    }
  }

  private function createHashesTagJunctionTable() {
    $this->createTableIfNotExists("HASHES_TAG_JUNCTION", "
      CREATE TABLE `HASHES_TAG_JUNCTION` (
        `HASHES_TAG_JUNCTION_PKY` INT NOT NULL AUTO_INCREMENT,
        `HASHES_KY` INT NOT NULL,
        `HASHES_TAGS_KY` INT NOT NULL,
        `CREATED_BY` varchar(45) NOT NULL,
        `CREATION_DTTM` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `MODIFICATION_DTTM` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`HASHES_TAG_JUNCTION_PKY`))");
  }

  private function createHashesTagsTable() {
    $this->createTableIfNotExists("HASHES_TAGS", "
      CREATE TABLE `HASHES_TAGS` (
        `HASHES_TAGS_KY` INT NOT NULL AUTO_INCREMENT,
        `TAG_TEXT` varchar(45) NOT NULL,
        `CREATED_BY` varchar(45) NOT NULL,
        `CREATION_DTTM` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `MODIFICATION_DTTM` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`HASHES_TAGS_KY`),
        UNIQUE INDEX `TAG_TEXT_UNIQUE` (`TAG_TEXT` ASC))");
  }

  private function createHaringsTable() {
    $this->createTableIfNotExists("HARINGS", "
      CREATE TABLE `HARINGS` (
        `HARINGS_HASHER_KY` int(11) NOT NULL,
        `HARINGS_HASH_KY` int(11) NOT NULL,
        KEY `HASHER_KY_idx` (`HARINGS_HASHER_KY`),
        KEY `HASH_KY_idx` (`HARINGS_HASH_KY`),
        CONSTRAINT `HARINGS_HASHER_KY` FOREIGN KEY (`HARINGS_HASHER_KY`) REFERENCES `HASHERS` (`HASHER_KY`) ON DELETE NO ACTION ON UPDATE NO ACTION,
        CONSTRAINT `HARINGS_HASH_KY` FOREIGN KEY (`HARINGS_HASH_KY`) REFERENCES `HASHES` (`HASH_KY`) ON DELETE NO ACTION ON UPDATE NO ACTION
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
  }

  private function createHashingsTable() {
    $this->createTableIfNotExists("HASHINGS", "
      CREATE TABLE `HASHINGS` (
        `HASHER_KY` int(11) NOT NULL,
        `HASH_KY` int(11) NOT NULL,
        KEY `HASHER_KY_idx` (`HASHER_KY`),
        KEY `HASH_KY_idx` (`HASH_KY`),
        CONSTRAINT `HASHER_KY` FOREIGN KEY (`HASHER_KY`) REFERENCES `HASHERS` (`HASHER_KY`) ON DELETE NO ACTION ON UPDATE NO ACTION,
        CONSTRAINT `HASH_KY` FOREIGN KEY (`HASH_KY`) REFERENCES `HASHES` (`HASH_KY`) ON DELETE NO ACTION ON UPDATE NO ACTION
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
  }

  private function createHashesTable() {
    $this->createTableIfNotExists("HASHES", "
      CREATE TABLE `HASHES` (
        `HASH_KY` int(11) NOT NULL AUTO_INCREMENT,
        `KENNEL_KY` int(11) NOT NULL,
        `KENNEL_EVENT_NUMBER` varchar(45) NOT NULL,
        `EVENT_DATE` datetime NOT NULL,
        `EVENT_LOCATION` varchar(45) DEFAULT NULL,
        `EVENT_CITY` varchar(45) DEFAULT NULL,
        `EVENT_STATE` varchar(45) DEFAULT NULL,
        `SPECIAL_EVENT_DESCRIPTION` varchar(135) DEFAULT NULL,
        `VIRGIN_COUNT` int(10) unsigned zerofill DEFAULT '0000000000',
        `IS_HYPER` int(10) unsigned zerofill DEFAULT '0000000000',
        `STREET_NUMBER` VARCHAR(45) NULL,
        `ROUTE` VARCHAR(45) NULL,
        `COUNTY` VARCHAR(45) NULL,
        `POSTAL_CODE` VARCHAR(45) NULL,
        `NEIGHBORHOOD` VARCHAR(45) NULL,
        `COUNTRY` VARCHAR(45) NULL,
        `FORMATTED_ADDRESS` VARCHAR(90) NULL,
        `PLACE_ID` VARCHAR(135) NULL,
        `LAT` DECIMAL(10,8) NULL,
        `LNG` DECIMAL(11,8) NULL,
        PRIMARY KEY (`HASH_KY`),
        UNIQUE KEY `HASH_KY_UNIQUE` (`HASH_KY`)
      ) ENGINE=InnoDB AUTO_INCREMENT=1008 DEFAULT CHARSET=utf8");
  }

  private function createKennelsTable() {
    $this->createTableIfNotExists("KENNELS", "
      CREATE TABLE `KENNELS` (
        `KENNEL_KY` int(11) NOT NULL AUTO_INCREMENT,
        `KENNEL_NAME` varchar(90) NOT NULL,
        `KENNEL_DESCRIPTION` varchar(90) DEFAULT NULL,
        `KENNEL_ABBREVIATION` varchar(45) DEFAULT NULL,
        `IN_RECORD_KEEPING` int(11) DEFAULT '0',
        `SITE_ADDRESS` VARCHAR(100),
        PRIMARY KEY (`KENNEL_KY`)
      ) ENGINE=InnoDB AUTO_INCREMENT=105 DEFAULT CHARSET=utf8");
  }

  private function createHashersTable() {
    $this->createTableIfNotExists("HASHERS", "
      CREATE TABLE `HASHERS` (
        `HASHER_KY` int(11) NOT NULL AUTO_INCREMENT,
        `HASHER_NAME` varchar(90) NOT NULL,
        `HASHER_ABBREVIATION` varchar(45) DEFAULT NULL,
        `LAST_NAME` varchar(45) DEFAULT NULL,
        `FIRST_NAME` varchar(45) DEFAULT NULL,
        `EMAIL` varchar(45) DEFAULT NULL,
        `HOME_KENNEL` varchar(45) DEFAULT NULL,
        `HOME_KENNEL_KY` int(10) unsigned zerofill DEFAULT '0000000000',
        `DECEASED` int(10) unsigned zerofill DEFAULT '0000000000',
        PRIMARY KEY (`HASHER_KY`)
      ) ENGINE=InnoDB AUTO_INCREMENT=2692 DEFAULT CHARSET=utf8");
  }

  private function createAuditTable() {
    $this->createTableIfNotExists("AUDIT", "
      CREATE TABLE `AUDIT` (
        `AUDIT_KY` int(11) NOT NULL AUTO_INCREMENT,
        `USERNAME` varchar(45) DEFAULT NULL,
        `AUDIT_TIME` datetime DEFAULT NULL,
        `ACTION_TYPE` varchar(45) DEFAULT NULL,
        `ACTION_DESCRIPTION` varchar(270) DEFAULT NULL,
        `IP_ADDR` varchar(45) DEFAULT NULL,
        PRIMARY KEY (`AUDIT_KY`)
      ) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=latin1");
  }

  private function createTableIfNotExists(string $tableName, string $createTableSql) {
    $checkSql = "
      SELECT EXISTS(
        SELECT 1
          FROM information_schema.tables
         WHERE table_schema = ?
           AND table_name = ?)";

    $table_exists = $this->app['db']->fetchOne($checkSql, array($this->db, $tableName));
    if(!$table_exists) {
      $this->app['dbs']['mysql_write']->executeStatement($createTableSql,array());
    }

    return $table_exists;
  }

  private function getDatabaseVersion() {

    $sql = "SELECT value FROM STATS_CONFIG WHERE name='database_version'";
    try {
      return $this->app['db']->fetchOne($sql, array());
    } catch(Exception $e) {
      // ignore - table may not exist
    }

    if(!$this->createTableIfNotExists("STATS_CONFIG", "
      CREATE TABLE `STATS_CONFIG` (
        `NAME` varchar(25) NOT NULL,
        `VALUE` varchar(200) NOT NULL,
        KEY `NAME_idx` (`NAME`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8")) {
      $insertSql = "INSERT INTO STATS_CONFIG VALUES('database_version', '0')";
      $this->app['dbs']['mysql_write']->executeStatement($insertSql,array());
    }

    return $this->app['db']->fetchOne($sql, array());
  }
}
