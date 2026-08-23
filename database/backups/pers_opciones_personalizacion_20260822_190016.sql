-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 204.93.224.89    Database: lqgbxzjo_liriosFloristeria
-- ------------------------------------------------------
-- Server version	11.4.12-MariaDB-cll-lve-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `pers_flores`
--

DROP TABLE IF EXISTS `pers_flores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pers_flores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `kind` varchar(30) NOT NULL DEFAULT 'rose',
  `orden` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pers_flores`
--

LOCK TABLES `pers_flores` WRITE;
/*!40000 ALTER TABLE `pers_flores` DISABLE KEYS */;
INSERT INTO `pers_flores` VALUES (1,'rosas','Rosas',95.00,'rose',1),(7,'margaritas','Margaritas',55.00,'daisy',7),(8,'astromelias','Astromelias',75.00,'aster',8),(9,'mixto','Hortencia',85.00,'mixed',9),(10,'eucalipto','Eucalipto',45.00,'rose',10),(11,'limonium','Limonium',35.00,'mixed',11),(12,'peonia','Peonia',895.00,'rose',12);
/*!40000 ALTER TABLE `pers_flores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pers_colores`
--

DROP TABLE IF EXISTS `pers_colores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pers_colores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `css` varchar(150) NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pers_colores`
--

LOCK TABLES `pers_colores` WRITE;
/*!40000 ALTER TABLE `pers_colores` DISABLE KEYS */;
INSERT INTO `pers_colores` VALUES (1,'rojo','Rojo','#B3261E',1),(2,'rosado','Rosado','#E88BAD',2),(3,'blanco','Blanco','#F5EFE6',3),(4,'amarillo','Amarillo','#E7C544',4),(5,'lila','Lila','#B497D6',5),(6,'naranja','Naranja','#E8894A',6),(7,'mixto','Mixto','linear-gradient(135deg, #B3261E 0%, #E7C544 50%, #B497D6 100%)',7);
/*!40000 ALTER TABLE `pers_colores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pers_wraps`
--

DROP TABLE IF EXISTS `pers_wraps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pers_wraps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `color` varchar(20) NOT NULL DEFAULT '#CCCCCC',
  `descripcion` varchar(150) DEFAULT '',
  `orden` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pers_wraps`
--

LOCK TABLES `pers_wraps` WRITE;
/*!40000 ALTER TABLE `pers_wraps` DISABLE KEYS */;
INSERT INTO `pers_wraps` VALUES (1,'kraft','Papel Kraft Natural',60.00,'#d2b48c','Textura natural y rústica',1),(2,'blanco','Papel Blanco Texturizado',80.00,'#f5f0e6','Elegante y limpio',2),(3,'rosado','Papel Rosado Suave',75.00,'#f8d7d7','Romántico y delicado',3),(4,'verde','Papel Verde Salvia',70.00,'#a8b5a0','Fresco y moderno',4);
/*!40000 ALTER TABLE `pers_wraps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pers_ribbons`
--

DROP TABLE IF EXISTS `pers_ribbons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pers_ribbons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `color` varchar(20) NOT NULL DEFAULT '#CCCCCC',
  `orden` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pers_ribbons`
--

LOCK TABLES `pers_ribbons` WRITE;
/*!40000 ALTER TABLE `pers_ribbons` DISABLE KEYS */;
INSERT INTO `pers_ribbons` VALUES (1,'dorado','Listón Dorado',45.00,'#C8860B',1),(2,'rosado','Listón Rosado',40.00,'#E88BAD',2),(3,'blanco','Listón Blanco',35.00,'#f5f0e6',3),(4,'verde','Listón Verde',40.00,'#5a7a5a',4),(5,'negro','Listón Negro Elegante',50.00,'#2B2118',5);
/*!40000 ALTER TABLE `pers_ribbons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pers_extras`
--

DROP TABLE IF EXISTS `pers_extras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pers_extras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `orden` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pers_extras`
--

LOCK TABLES `pers_extras` WRITE;
/*!40000 ALTER TABLE `pers_extras` DISABLE KEYS */;
INSERT INTO `pers_extras` VALUES (1,'chocolates','Chocolates',120.00,1),(2,'peluche','Peluche pequeño',200.00,2);
/*!40000 ALTER TABLE `pers_extras` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-22 19:00:22
