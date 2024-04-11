<?php

namespace App;

use App\Controller\BaseController;

class DatabaseUpdater {

  private string $db_name;
  private BaseController $controller;

  private function getReadConnection() {
    return $this->controller->getReadConnection();
  }

  private function getWriteConnection() {
    return $this->controller->getWriteReadConnection();
  }

  function __construct(BaseController $controller, string $db_name) {

    $this->controller = $controller;
    $this->db_name = $db_name;

    $databaseVersion = $this->getDatabaseVersion();

    $currentDatabaseVersion = 24;

    if($databaseVersion != $currentDatabaseVersion) {

      $has_semaphores = true;

      try {
        $semRes = sem_get(696969);
      } catch(Error $e) {
        $has_semaphores = false;
      }

      if($has_semaphores) {
        if(!sem_acquire($semRes)) {
          throw new Exception("semaphore acquire failed");
        }
      } else {
        // if semaphores aren't available, and another thread is
        // already updating the database, this will throw an error
        // but that's better than database corruption caused by more
        // than one thread trying to upgrade the database
        $this->createLockTable();
      }

      try {
        $databaseVersion = $this->getDatabaseVersion();

        try {
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
              $this->createHashesView();
              $this->setDatabaseVersion(2);
            case 2:
              $this->createHashTypesTable();
              $this->setDatabaseVersion(3);
            case 3:
              $this->createHareTypesTable();
              $this->setDatabaseVersion(4);
            case 4:
              $this->setDatabaseVersion(5);
            case 5:
              $this->createAwardsTables();
              $this->setDatabaseVersion(6);
            case 6:
              $this->dropEmailColumn();
              $this->setDatabaseVersion(7);
            case 7:
              $this->fixStatsConfigKey();
              $this->setDatabaseVersion(8);
            case 8:
              $this->renameStatsConfigToSiteConfig();
              $this->setDatabaseVersion(9);
            case 9:
              $this->addDescriptionColumnToSiteConfig();
              $this->setDatabaseVersion(10);
            case 10:
              $this->moveDefaultKennelAbbreviationToSiteConfig();
              $this->setDatabaseVersion(11);
            case 11:
              $this->moveAdministratorEmailToSiteConfig();
              $this->setDatabaseVersion(12);
            case 12:
              $this->alterSiteConfigNameColumn();
              $this->moveJumboCountsSettingsToSiteConfig();
              $this->setDatabaseVersion(13);
            case 13:
              $this->moveGoogleKeysToSiteConfig();
              $this->setDatabaseVersion(14);
            case 14:
              $this->moveSiteBannerToSiteConfig();
              $this->setDatabaseVersion(15);
            case 15:
              $this->moveHasLegacyHashCountsToSiteConfig();
              $this->setDatabaseVersion(16);
            case 16:
              $this->loadRidiculousStatistics();
              $this->setDatabaseVersion(17);
            case 17:
              $this->addOmniOptionToSiteConfig();
              $this->setDatabaseVersion(18);
            case 18:
              $this->addBudgetOptionToSiteConfig();
              $this->setDatabaseVersion(19);
            case 19:
              $this->addAwardsOptionToSiteConfig();
              $this->setDatabaseVersion(20);
            case 20:
              $this->addDefaultAwardEventHorizonToSiteConfig();
              $this->setDatabaseVersion(21);
            case 21:
              $this->addNumDaysBeforeConsideredInactiveToSiteConfig();
              $this->setDatabaseVersion(22);
            case 22:
              $this->addConsolidatedSwitchKennelPreferenceToSiteConfig();
              $this->setDatabaseVersion(23);
            case 23:
              $this->alterDeceasedColumn();
              $this->addBannedColumn();
              $this->setDatabaseVersion(24);
            default:
              // Overkill, but guarantees the view is up to date with the
              // current database structure.
              $this->recreateHashesView();
              break;
          }
        } finally {
          if($has_semaphores) {
            sem_release($semRes);
          }
        }
      } finally {
        if(!$has_semaphores) {
          $this->dropLockTable();
        }
      }
    }
  }

  private function createLockTable() {
    $sql = "CREATE TABLE DATABASE_UPGRADE_IN_PROGRESS (`A` INT)";
    $this->getWriteConnection()->executeStatement($sql, array());
  }

  private function dropLockTable() {
    $sql = "DROP TABLE DATABASE_UPGRADE_IN_PROGRESS";
    $this->getWriteConnection()->executeStatement($sql, array());
  }

  private function insertIntoSiteConfig(string $name, string $value, string $description) {
    if ($description == "") {
      $this->getWriteConnection()->executeStatement("INSERT INTO SITE_CONFIG(NAME, VALUE) VALUES(?, ?)", array($name, $value));
    } else {
      $this->getWriteConnection()->executeStatement("INSERT INTO SITE_CONFIG(NAME, VALUE, DESCRIPTION) VALUES(?, ?, ?)", array($name, $value, $description));
    }
  }

  private function loadRidiculousStatistics() {
    $stats = array(
      "Hashes where VD was contracted",
      "Hashes where someone got pregnant",
      "Hashes where someone coveted their neighbor's wife",
      "Hashes where hashers were mocked for their Kentucky heritage",
      "Hashes where hashers were mocked for their Michigan heritage",
      "Hashes where people did it on trail",
      "Hashes where a hasher was arrested",
      "Hashes where the police showed up",
      "Hashes where the streams were crossed",
      "Hashes where no harriettes showed up",
      "Hashes that could have used better beer",
      "Hashes that could have used a better trail",
      "Hashes that could have used better hares",
      "Hashes that caused somebody to move away",
      "Hashes where someone shat on trail",
      "Hashes where someone shat themselves",
      "Hashes where someone called the police on us",
      "Hashes that brought great shame to everyone involved",
      "Hashes where dogs did it on trail");

    for($i=0; $i<count($stats); $i++) {
      $name="ridiculous".$i;
      $this->insertIntoSiteConfig($name, $stats[$i], "");
    }
  }

  private function alterDeceasedColumn() {
    $this->executeStatement("UPDATE HASHERS SET DECEASED=1 WHERE DECEASED IS NULL");
    $this->executeStatement("ALTER TABLE HASHERS CHANGE DECEASED DECEASED TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");
  }

  private function addBannedColumn() {
    $this->executeStatement("ALTER TABLE HASHERS ADD BANNED TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");
  }

  private function alterSiteConfigNameColumn() {
    $this->executeStatement("ALTER TABLE SITE_CONFIG CHANGE NAME NAME VARCHAR(100) NOT NULL");
  }

  private function moveHasLegacyHashCountsToSiteConfig() {
    if(defined('HAS_LEGACY_HASH_COUNTS')) {
      $hlhc = HAS_LEGACY_HASH_COUNTS;
    } else {
      $hlhc = false;
    }
    if($hlhc) {
      $hlhc = "true";
    } else {
      $hlhc = "false";
    }
    $this->insertIntoSiteConfig('has_legacy_hash_counts', $hlhc, 'Set to "true" if the LEGACY_HASH_COUNTS table exists and is used on this site.  Leave to set to "false" if you are not using this feature.');
  }

  private function addBudgetOptionToSiteConfig() {
    $this->insertIntoSiteConfig('show_budget_page', "true", 'Set to "true" to show the link to the budget page on the manage event page.');
  }

  private function addNumDaysBeforeConsideredInactiveToSiteConfig() {
    $this->insertIntoSiteConfig('num_days_before_considered_inactive', "90", 'Number of days before a hasher will be considered inactive.');
  }

  private function addDefaultAwardEventHorizonToSiteConfig() {
    $this->insertIntoSiteConfig('default_award_event_horizon', "5", 'Default number of events before being due an award that a hasher is required to have before they will appear on the pending awards page.');
  }

  private function addConsolidatedSwitchKennelPreferenceToSiteConfig() {
    $this->insertIntoSiteConfig('use_consolidated_switch_kennel_page', "false", 'If true, use the consolidated switch kennel page at hashstats.org');
  }

  private function addAwardsOptionToSiteConfig() {
    $this->insertIntoSiteConfig('show_awards_page', "true", 'Set to "true" to show the link to the awards pages on the admin landing page.');
  }

  private function addOmniOptionToSiteConfig() {
    $this->insertIntoSiteConfig('show_omni_analversary_page', "true", 'Set to "true" to show the legacy omni analversary page link on the hash details page.');
  }

  private function moveSiteBannerToSiteConfig() {
    if(defined('SITE_BANNER')) {
      $sb = SITE_BANNER;
    } else {
      $sb = "none";
    }
    if(strlen($sb) == 0) {
      $sb = "none";
    }
    $this->insertIntoSiteConfig('site_banner', $sb, 'Optional header that is displayed on each page.  Set to "none" to leave blank.');
  }

  private function moveGoogleKeysToSiteConfig() {

    if(defined('GOOGLE_ANALYTICS_ID')) {
      $gai = GOOGLE_ANALYTICS_ID;
    } else {
      $gai = "none";
    }
    if(strlen($gai) == 0) {
      $gai = "none";
    }

    if(defined('GOOGLE_PLACES_API_WEB_SERVICE_KEY')) {
      $gpawsk = GOOGLE_PLACES_API_WEB_SERVICE_KEY;
    } else {
      $gpawsk = "you need to put the google places api web service key here";
    }

    if(defined('GOOGLE_MAPS_JAVASCRIPT_API_KEY')) {
      $gmjak = GOOGLE_MAPS_JAVASCRIPT_API_KEY;
    } else {
      $gmjak = "you need to put the google maps javascript api key here";
    }

    $this->insertIntoSiteConfig('google_analytics_id', $gai, 'If you wish to use google analytics on this site, put your google analytics id here.  Set to "none" if you do not want to use google analytics.  Ha ha, ANALytics.');
    $this->insertIntoSiteConfig('google_places_api_web_service_key', $gpawsk, 'Put your google places api web service key here.  Without this key the site will not operate correctly.');
    $this->insertIntoSiteConfig('google_maps_javascript_api_key', $gmjak, 'Put your google maps javascript api key here.  Without this key the site will not operate correctly.');
  }

  private function moveJumboCountsSettingsToSiteConfig() {
    if(defined('JUMBO_COUNTS_MINIMUM_HASH_COUNT')) {
      $jc = JUMBO_COUNTS_MINIMUM_HASH_COUNT;
    } else {
      $jc = 5;
    }
    if(defined('JUMBO_PERCENTAGES_MINIMUM_HASH_COUNT')) {
      $jp = JUMBO_PERCENTAGES_MINIMUM_HASH_COUNT;
    } else {
      $jp = 10;
    }
    $this->insertIntoSiteConfig('jumbo_counts_minimum_hash_count', $jc, 'Minimum number of hashes to appear on the jumbo counts table.');
    $this->insertIntoSiteConfig('jumbo_percentages_minimum_hash_count', $jp, 'Minimum number of hashes to appears on the jumbo percentages table.');
  }

  private function moveDefaultKennelAbbreviationToSiteConfig() {
    if(defined('DEFAULT_KENNEL_ABBREVIATION')) {
      $dka = DEFAULT_KENNEL_ABBREVIATION;
    } else {
      $dka = "**NEEDS UPDATED**";
    }
    $this->insertIntoSiteConfig('default_kennel', $dka, 'The default kennel for this website. This value must match a kennel abbreviation in the KENNELS table.');
  }

  private function moveAdministratorEmailToSiteConfig() {
    if(defined('ADMINISTRATOR_EMAIL')) {
      $em = ADMINISTRATOR_EMAIL;
    } else {
      $em = "**NEEDS UPDATED**";
    }
    $this->insertIntoSiteConfig('administrator_email', $em, 'The email address for the contact person for this website.');
  }

  private function addDescriptionColumnToSiteConfig() {
    $sql = "ALTER TABLE SITE_CONFIG ADD DESCRIPTION VARCHAR(4000)";
    $this->getWriteConnection()->executeStatement($sql, array());

    // ignore error, entry may already exist in table
    $this->executeStatementIgnoreError("INSERT INTO SITE_CONFIG(NAME, VALUE) VALUES('site_domain_name', '-none-')");

    $this->executeStatement("UPDATE SITE_CONFIG SET DESCRIPTION='The base domain name of this website.  Ex: myhashstats.org' WHERE NAME='site_domain_name'");
  }

  private function renameStatsConfigToSiteConfig() {
    $sql = "ALTER TABLE STATS_CONFIG RENAME TO SITE_CONFIG";
    $this->getWriteConnection()->executeStatement($sql, array());
  }

  private function fixStatsConfigKey() {
    $this->executeStatement("DROP INDEX NAME_idx ON STATS_CONFIG");
    $this->executeStatement("ALTER TABLE STATS_CONFIG ADD PRIMARY KEY (`NAME`)");
  }

  private function dropEmailColumn() {
    // ignore errors, column may not exist
    $this->executeStatementIgnoreError("ALTER TABLE HASHERS DROP EMAIL");
  }

  private function createAwardsTables() {
    $this->createTableIfNotExists("AWARD_LEVELS", "
      CREATE TABLE AWARD_LEVELS (
          `KENNEL_KY` int(11) NOT NULL,
          `AWARD_LEVEL` INT NOT NULL,
          PRIMARY KEY (`KENNEL_KY`, `AWARD_LEVEL`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $this->createTableIfNotExists("HASHER_AWARDS", "
      CREATE TABLE HASHER_AWARDS (
          `KENNEL_KY` int(11) NOT NULL,
          `HASHER_KY` int(11) NOT NULL,
          `LAST_AWARD_LEVEL_RECOGNIZED` INT NOT NULL,
          PRIMARY KEY (`KENNEL_KY`, `HASHER_KY`),
          CONSTRAINT `HASHER_AWARDS_HASHER_KY` FOREIGN KEY (`HASHER_KY`) REFERENCES `HASHERS` (`HASHER_KY`) ON DELETE NO ACTION ON UPDATE NO ACTION
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // TODO: need to autopopulate award levels when a new kennel is
    // added to record keeping
    $this->executeStatements(array(
      "INSERT INTO AWARD_LEVELS SELECT KENNEL_KY, 10 FROM KENNELS WHERE IN_RECORD_KEEPING=1",
      "INSERT INTO AWARD_LEVELS SELECT KENNEL_KY, 25 FROM KENNELS WHERE IN_RECORD_KEEPING=1",
      "INSERT INTO AWARD_LEVELS SELECT KENNEL_KY, 50 FROM KENNELS WHERE IN_RECORD_KEEPING=1",
      "INSERT INTO AWARD_LEVELS SELECT KENNEL_KY, 69 FROM KENNELS WHERE IN_RECORD_KEEPING=1",
      "INSERT INTO AWARD_LEVELS SELECT KENNEL_KY, 100 FROM KENNELS WHERE IN_RECORD_KEEPING=1",
      "INSERT INTO AWARD_LEVELS SELECT KENNEL_KY, 200 FROM KENNELS WHERE IN_RECORD_KEEPING=1",
      "INSERT INTO AWARD_LEVELS SELECT KENNEL_KY, 300 FROM KENNELS WHERE IN_RECORD_KEEPING=1",
      "INSERT INTO AWARD_LEVELS SELECT KENNEL_KY, 400 FROM KENNELS WHERE IN_RECORD_KEEPING=1",
      "INSERT INTO AWARD_LEVELS SELECT KENNEL_KY, 500 FROM KENNELS WHERE IN_RECORD_KEEPING=1",
      "INSERT INTO AWARD_LEVELS SELECT KENNEL_KY, 600 FROM KENNELS WHERE IN_RECORD_KEEPING=1",
      "INSERT INTO AWARD_LEVELS SELECT KENNEL_KY, 700 FROM KENNELS WHERE IN_RECORD_KEEPING=1",
      "INSERT INTO AWARD_LEVELS SELECT KENNEL_KY, 800 FROM KENNELS WHERE IN_RECORD_KEEPING=1",
      "INSERT INTO AWARD_LEVELS SELECT KENNEL_KY, 900 FROM KENNELS WHERE IN_RECORD_KEEPING=1",
      "INSERT INTO AWARD_LEVELS SELECT KENNEL_KY, 1000 FROM KENNELS WHERE IN_RECORD_KEEPING=1"));
  }

  private function createHareTypesTable() {
    $this->createTableIfNotExists("HARE_TYPES", "
      CREATE TABLE HARE_TYPES (
          `SEQ` INT NOT NULL,
          `HARE_TYPE` INT NOT NULL,
          `HARE_TYPE_NAME` VARCHAR(25) NOT NULL,
          PRIMARY KEY (`HARE_TYPE`),
          UNIQUE KEY (`SEQ`),
          UNIQUE KEY (`HARE_TYPE_NAME`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $this->executeStatements(array(
      "INSERT INTO HARE_TYPES VALUES(10, 1<<0, 'Traditional')",
      "INSERT INTO HARE_TYPES VALUES(30, 1<<2, 'Hyper')",
      "ALTER TABLE HARINGS ADD `HARE_TYPE` INT",
      "UPDATE HARINGS SET HARE_TYPE=1<<2 WHERE HARINGS_HASH_KY IN (SELECT HASH_KY FROM HASHES_TABLE WHERE HASH_TYPE=2)",
      "UPDATE HARINGS SET HARE_TYPE=1<<0 WHERE HARINGS_HASH_KY IN (SELECT HASH_KY FROM HASHES_TABLE WHERE HASH_TYPE=1)",
      "ALTER TABLE HARINGS CHANGE HARE_TYPE HARE_TYPE INT NOT NULL",
      "ALTER TABLE HASH_TYPES ADD HARE_TYPE_MASK INT",
      "UPDATE HASH_TYPES SET HARE_TYPE_MASK = 1<<0 WHERE HASH_TYPE_NAME='Regular'",
      "UPDATE HASH_TYPES SET HARE_TYPE_MASK = 1<<2 WHERE HASH_TYPE_NAME='Hyper'",
      "ALTER TABLE HASH_TYPES CHANGE HARE_TYPE_MASK HARE_TYPE_MASK INT NOT NULL",
      "ALTER TABLE KENNELS ADD HARE_TYPE_MASK INT",
      "UPDATE KENNELS SET HARE_TYPE_MASK = 1<<0 | 1<<2",
      "ALTER TABLE KENNELS CHANGE HARE_TYPE_MASK HARE_TYPE_MASK INT NOT NULL",
      "ALTER TABLE HARE_TYPES ADD CHART_COLOR VARCHAR(20)",
      "DROP INDEX COMPOSITE1_idx ON HARINGS",
      "CREATE UNIQUE INDEX COMPOSITE1_idx ON HARINGS(HARINGS_HASHER_KY, HARINGS_HASH_KY, HARE_TYPE)",
      "UPDATE HARE_TYPES SET CHART_COLOR='255, 255, 0' WHERE HARE_TYPE_NAME = 'Traditional'",
      "UPDATE HARE_TYPES SET CHART_COLOR='128, 255, 0' WHERE HARE_TYPE_NAME = 'Hyper'",
      "ALTER TABLE HARE_TYPES CHANGE CHART_COLOR CHART_COLOR VARCHAR(20) NOT NULL"));
  }

  private function createHashTypesTable() {
    $this->createTableIfNotExists("HASH_TYPES", "
      CREATE TABLE HASH_TYPES (
          `SEQ` INT NOT NULL,
          `HASH_TYPE` INT NOT NULL,
          `HASH_TYPE_NAME` VARCHAR(25) NOT NULL,
          PRIMARY KEY (`HASH_TYPE`),
          UNIQUE KEY (`SEQ`),
          UNIQUE KEY (`HASH_TYPE_NAME`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $this->executeStatements(array(
      "INSERT INTO HASH_TYPES VALUES(10, 1<<0, 'Regular')",
      "INSERT INTO HASH_TYPES VALUES(20, 1<<1, 'Hyper')",
      "ALTER TABLE HASHES_TABLE ADD `HASH_TYPE` INT",
      "UPDATE HASHES_TABLE SET HASH_TYPE=1<<0 WHERE IS_HYPER=0",
      "UPDATE HASHES_TABLE SET HASH_TYPE=1<<1 WHERE IS_HYPER=1",
      "ALTER TABLE KENNELS ADD HASH_TYPE_MASK INT",
      "UPDATE KENNELS SET HASH_TYPE_MASK = 1<<0 | 1<<1",
      "ALTER TABLE KENNELS CHANGE HASH_TYPE_MASK HASH_TYPE_MASK INT NOT NULL",
      "ALTER TABLE HASHES_TABLE DROP IS_HYPER"));
  }

  private function executeStatements(array $arr) {
    foreach($arr as $sql) {
      $this->executeStatement($sql);
    }
  }

  private function executeStatement(string $sql) {
    $this->getWriteConnection()->executeStatement($sql, array());
  }

  private function createHashesView() {
    $sql = "ALTER TABLE HASHES RENAME TO HASHES_TABLE";
    $this->getWriteConnection()->executeStatement($sql, array());

    $sql = "CREATE VIEW HASHES AS SELECT * FROM HASHES_TABLE WHERE EVENT_DATE <= NOW()";
    $this->getWriteConnection()->executeStatement($sql, array());
  }

  private function recreateHashesView() {
    // If the base table changes, the HASHES view needs to be recreated
    $sql = "DROP VIEW HASHES";
    $this->executeStatementIgnoreError($sql);

    $sql = "CREATE VIEW HASHES AS SELECT * FROM HASHES_TABLE WHERE EVENT_DATE <= NOW()";
    $this->getWriteConnection()->executeStatement($sql, array());
  }

  private function setDatabaseVersion(int $version) {
    if($version < 9) {
      $sql = "UPDATE STATS_CONFIG SET VALUE=? WHERE NAME='database_version'";
    } else {
      $sql = "UPDATE SITE_CONFIG SET VALUE=? WHERE NAME='database_version'";
    }
    $this->getWriteConnection()->executeStatement($sql, array(strval($version)));
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
      $this->getWriteConnection()->executeStatement($sql,array());
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

    $table_exists = $this->getWriteConnection()->fetchOne($checkSql, array($this->db_name, $tableName));
    if(!$table_exists) {
      $this->getWriteConnection()->executeStatement($createTableSql,array());
    }

    return $table_exists;
  }

  private function getDatabaseVersion() {

    $sql = "SELECT value FROM SITE_CONFIG WHERE name='database_version'";
    try {
      return $this->getReadConnection()->fetchOne($sql, array());
    } catch(Exception $e) {
      // ignore - table may not exist
    }

    // former table name before db version 9
    $sql = "SELECT value FROM STATS_CONFIG WHERE name='database_version'";
    try {
      return $this->getReadConnection()->fetchOne($sql, array());
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
      $this->getWriteConnection()->executeStatement($insertSql,array());
    }

    return $this->getWriteConnection()->fetchOne($sql, array());
  }
}
