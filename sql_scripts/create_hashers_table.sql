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
) ENGINE=InnoDB AUTO_INCREMENT=2692 DEFAULT CHARSET=utf8;
