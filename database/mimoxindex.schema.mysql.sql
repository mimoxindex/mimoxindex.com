-- phpMyAdmin SQL Dump
-- version 4.0.10deb1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Feb 22, 2016 at 08:54 AM
-- Server version: 5.5.47-0ubuntu0.14.04.1
-- PHP Version: 5.5.9-1ubuntu4.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `mimoxindex`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mimox_index`
--

CREATE TABLE IF NOT EXISTS `mimox_index` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mimoxid` int(11) NOT NULL,
  `termid` int(11) NOT NULL,
  `TermName` varchar(255) NOT NULL,
  `TermSearchName` text,
  `TermFirstDate` datetime NOT NULL,
  `TermCnt` int(6) NOT NULL,
  `TermRank` int(6) NOT NULL,
  `TermRankDate` datetime NOT NULL,
  `TermTrend` int(11) DEFAULT '0',
  `TermTrendChangeDate` datetime DEFAULT '0000-00-00 00:00:00',
  `TermTrend90` int(11) DEFAULT '0',
  `TermTrendChangeDate90` datetime DEFAULT '0000-00-00 00:00:00',
  `mimox_group` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `TermName` (`TermName`),
  KEY `mimoxid` (`mimoxid`),
  KEY `TermRank` (`TermRank`),
  KEY `TermCnt` (`TermCnt`),
  KEY `mimox_group` (`mimox_group`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `rss`
--

CREATE TABLE IF NOT EXISTS `rss` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site` varchar(255) DEFAULT NULL,
  `sitelink` varchar(255) DEFAULT NULL,
  `title` text,
  `link` text,
  `pubdate` datetime DEFAULT NULL,
  `description` text,
  `hash` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hash` (`hash`),
  KEY `site` (`site`),
  KEY `sitelink` (`sitelink`),
  KEY `pubdate` (`pubdate`),
  FULLTEXT KEY `link` (`link`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `termcount`
--

CREATE TABLE IF NOT EXISTS `termcount` (
  `termid` int(11) NOT NULL,
  `rssid` int(11) NOT NULL,
  UNIQUE KEY `termcnt` (`termid`,`rssid`),
  KEY `termid` (`termid`),
  KEY `rssid` (`rssid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `terms`
--

CREATE TABLE IF NOT EXISTS `terms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mimoxid` int(11) NOT NULL,
  `origtext` varchar(255) NOT NULL,
  `enabled` tinyint(4) DEFAULT '1',
  `is_default` int(11) DEFAULT '0',
  `default_terms_id` int(11) DEFAULT '0',
  `mimox_group` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `origtext` (`origtext`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `trendhisory`
--

CREATE TABLE IF NOT EXISTS `trendhisory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trenddate` datetime NOT NULL,
  `pickledump` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `trenddate` (`trenddate`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


CREATE TABLE `rss_growing` (
  `id` int(11) NOT NULL,
  `site` varchar(255) DEFAULT NULL,
  `sitelink` varchar(255) DEFAULT NULL,
  `title` text,
  `link` text,
  `pubdate` datetime DEFAULT NULL,
  `description` text,
  `hash` varchar(128) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


ALTER TABLE `rss_growing`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `hash` (`hash`),
  ADD KEY `site` (`site`),
  ADD KEY `sitelink` (`sitelink`),
  ADD KEY `pubdate` (`pubdate`);
ALTER TABLE `rss_growing` ADD FULLTEXT KEY `link` (`link`);


ALTER TABLE `rss_growing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
