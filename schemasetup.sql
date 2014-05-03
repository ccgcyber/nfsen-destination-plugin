
/*!40000 DROP DATABASE IF EXISTS `dest`*/;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `dest` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `dest`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rrdgraph_tcp_bytes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timeslot` mediumtext,
  `domain` text,
  `frequency` mediumtext,
  `addedon` datetime DEFAULT NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=150543 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rrdgraph_tcp_flows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timeslot` mediumtext,
  `domain` text,
  `frequency` mediumtext,
  `addedon` datetime DEFAULT NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=151273 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rrdgraph_tcp_packets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timeslot` mediumtext,
  `domain` text,
  `frequency` mediumtext,
  `addedon` datetime DEFAULT NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=150873 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rrdgraph_udp_bytes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timeslot` mediumtext,
  `domain` text,
  `frequency` mediumtext,
  `addedon` datetime DEFAULT NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=147142 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rrdgraph_udp_flows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timeslot` mediumtext,
  `domain` text,
  `frequency` mediumtext,
  `addedon` datetime DEFAULT NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=149322 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rrdgraph_udp_packets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `timeslot` mediumtext,
  `domain` text,
  `frequency` mediumtext,
  `addedon` datetime DEFAULT NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=148372 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `whoiscache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cidr` text NOT NULL,
  `org` text NOT NULL,
  `added_on` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=8256 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
