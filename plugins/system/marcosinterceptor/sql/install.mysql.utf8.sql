CREATE TABLE IF NOT EXISTS `#__mi_iptable` (
  `ip` VARCHAR(40) NOT NULL COMMENT 'ip to char',
  `firsthacktime` datetime NOT NULL,
  `lasthacktime` datetime NOT NULL,
  `hackcount` int(11) NOT NULL DEFAULT '1',
  `autodelete` tinyint(4) NOT NULL DEFAULT '1',
  PRIMARY KEY (`ip`)
);

