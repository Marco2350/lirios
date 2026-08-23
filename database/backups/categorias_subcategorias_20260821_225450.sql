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
-- Table structure for table `categorias`
--

DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `icono` varchar(10) DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categorias`
--

LOCK TABLES `categorias` WRITE;
/*!40000 ALTER TABLE `categorias` DISABLE KEYS */;
INSERT INTO `categorias` VALUES (1,'ramos-florales','Ramos Florales',NULL,1),(2,'arreglos-en-base','Arreglos en Base',NULL,2),(3,'regalos-y-complementos','Regalos y Complementos',NULL,3),(4,'cumpleanos','Cumpleaños',NULL,4),(5,'amor-y-romance','Amor y Romance',NULL,5),(6,'graduaciones','Graduaciones',NULL,6),(7,'condolencias','Condolencias',NULL,7),(8,'bodas-y-eventos','Bodas y Eventos',NULL,8),(9,'caballero','Caballero',NULL,9),(10,'globos','Globos',NULL,10),(11,'infantil','Infantil',NULL,11),(12,'peluches','Peluches',NULL,12),(13,'flores-amarillas','Flores Amarillas','',0),(14,'girasoles','Girasoles','',0);
/*!40000 ALTER TABLE `categorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subcategorias`
--

DROP TABLE IF EXISTS `subcategorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subcategorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria_id` int(11) NOT NULL,
  `slug` varchar(60) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_categoria_slug` (`categoria_id`,`slug`),
  CONSTRAINT `subcategorias_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subcategorias`
--

LOCK TABLES `subcategorias` WRITE;
/*!40000 ALTER TABLE `subcategorias` DISABLE KEYS */;
INSERT INTO `subcategorias` VALUES (1,1,'rosas','Rosas',1),(2,1,'girasoles','Girasoles',2),(3,1,'hortensias','Hortensias',3),(4,1,'tulipanes','Tulipanes',4),(5,1,'lirios','Lirios',5),(6,1,'gerberas','Gerberas',6),(7,1,'mini-rosas','Mini Rosas',7),(8,1,'mixtos','Mixtos',8),(9,2,'vidrio','Vidrio',1),(10,2,'ceramica','Cerámica',2),(11,2,'madera','Madera',3),(12,2,'acrilico','Acrílico',4),(13,2,'carton-premium','Cartón Premium',5),(14,2,'canastas','Canastas',6),(15,3,'chocolates','Chocolates',1),(16,3,'perfumes','Perfumes',2),(17,3,'vinos-y-whisky','Vinos y Whisky',3),(18,3,'tazas','Tazas',4),(19,3,'stanley','Stanley',5),(20,3,'agendas','Agendas',6),(21,3,'globos','Globos',7),(22,3,'tarjetas','Tarjetas',8),(23,4,'para-ella','Para ella',1),(24,4,'para-el','Para él',2),(25,4,'infantil','Infantil',3),(26,4,'con-globos','Con globos',4),(27,4,'con-chocolates','Con chocolates',5),(28,4,'sorpresas','Sorpresas',6),(29,5,'aniversario','Aniversario',1),(30,5,'te-amo','Te amo',2),(31,5,'pedida-de-perdon','Pedida de perdón',3),(32,5,'primeras-citas','Primeras citas',4),(33,5,'pedida-de-mano','Pedida de mano',5),(34,5,'san-valentin','San Valentín',6),(35,6,'ramos','Ramos',1),(36,6,'arreglos','Arreglos',2),(37,6,'globos','Globos',3),(38,6,'peluches','Peluches',4),(39,6,'chocolates','Chocolates',5),(40,6,'personalizados','Personalizados',6),(41,7,'coronas','Coronas',1),(42,7,'cruces','Cruces',2),(43,7,'corazones','Corazones',3),(44,7,'arreglos-verticales','Arreglos verticales',4),(45,7,'ramos-funebres','Ramos fúnebres',5),(46,8,'ramos-de-novia','Ramos de novia',1),(47,8,'boutonnieres','Boutonnières',2),(48,8,'centros-de-mesa','Centros de mesa',3),(49,8,'decoracion-floral','Decoración floral',4),(50,8,'iglesias','Iglesias',5),(51,8,'recepciones','Recepciones',6),(52,9,'whisky','Whisky',1),(53,9,'vinos','Vinos',2),(54,9,'cervezas','Cervezas',3),(55,9,'chocolates','Chocolates',4),(56,9,'perfumes','Perfumes',5),(57,9,'ramos-elegantes','Ramos elegantes',6),(58,9,'regalos-ejecutivos','Regalos ejecutivos',7),(59,10,'burbuja','Burbuja',1),(60,10,'helio','Helio',2),(61,10,'numeros','Números',3),(62,10,'letras','Letras',4),(63,10,'personalizados','Personalizados',5),(64,10,'arcos-y-bouquets','Arcos y bouquets',6),(65,11,'nacimiento','Nacimiento',1),(66,11,'baby-shower','Baby Shower',2),(67,11,'nina','Niña',3),(68,11,'nino','Niño',4),(69,11,'personajes','Personajes',5),(70,11,'dulces','Dulces',6),(71,12,'osos','Osos',1),(72,12,'stitch','Stitch',2),(73,12,'capibara','Capibara',3),(74,12,'personajes','Personajes',4),(75,12,'gigantes','Gigantes',5),(76,12,'mini-peluches','Mini peluches',6);
/*!40000 ALTER TABLE `subcategorias` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-21 22:54:54
