-- MySQL dump 10.13  Distrib 8.0.19, for Win64 (x86_64)
--
-- Host: localhost    Database: solicitudes_cursos
-- ------------------------------------------------------
-- Server version	5.5.5-10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `solicitud_acceso`
--

DROP TABLE IF EXISTS `solicitud_acceso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `solicitud_acceso` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `apellido` varchar(255) DEFAULT NULL,
  `curso` varchar(255) DEFAULT NULL,
  `dni` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `estado` enum('PENDIENTE','EN_GESTION','RESUELTO') DEFAULT 'PENDIENTE',
  `fecha_creacion` datetime(6) DEFAULT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `telefono` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `solicitud_acceso`
--

LOCK TABLES `solicitud_acceso` WRITE;
/*!40000 ALTER TABLE `solicitud_acceso` DISABLE KEYS */;
INSERT INTO `solicitud_acceso` VALUES (1,'Lopez','ofimatica','12345678A','pepe@correo.com','RESUELTO',NULL,'Pepe','bagnshmdjg','123456789'),(2,'Perez','prevencion-de-riesgos','12345678A','lolo@correo.com','RESUELTO','2026-04-30 12:29:21.000000','Lolo','guiuh ñoij ','123456789'),(3,'perez','ingles','08463427R','pili@clase.com','PENDIENTE','2026-04-30 14:11:19.000000','pili',NULL,'678345659'),(4,'tonel','electricidad','08463427R','lolo@gmail.com','RESUELTO','2026-05-05 08:51:33.000000','lolo',NULL,'648593900'),(5,'pelaez ','ciberseguridad','08463427R','sofia@gmail.com','PENDIENTE','2026-05-06 09:03:16.000000','sofia',NULL,'123456789'),(6,'Flores','electricidad','12345678G','lola@gmail.com','PENDIENTE','2026-05-06 09:14:00.000000','Lola',NULL,'123456789'),(7,'flores','marketing-digital','12345678G','pepa@gmail.com','PENDIENTE','2026-05-06 09:44:28.000000','pepa',NULL,'123456789'),(8,'lopez','administracion','12345678G','rita@gmail.com','EN_GESTION','2026-05-06 09:49:05.000000','Rita',NULL,'987654321'),(9,'lopez','diseno-grafico','87655432w','pepa@gmail.com','PENDIENTE','2026-05-06 11:18:06.000000','pepa','juhliuh ño','123456789'),(12,'Florez','ofimatica','12345678D','lola@gmail.com','PENDIENTE',NULL,'Lola',NULL,'543678902'),(13,'Loren','Diseño Gráfico','12345678D','sofia@gmail.com','PENDIENTE',NULL,'Sofia',NULL,'234567894'),(21,'botella','ciberseguridad','876543212E','pepe@clase.es','PENDIENTE',NULL,'pepe',NULL,'12345678'),(22,'Lopez','ingles','12345678D','trini@clase.es','EN_GESTION',NULL,'Trini',NULL,'12345678'),(23,'Trini','ingles','12345678D','trini@clase.es','PENDIENTE',NULL,'Mari ',NULL,'12345678');
/*!40000 ALTER TABLE `solicitud_acceso` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `rol` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'admin','1234','EMPLEADO'),(10,'maria','1234','EMPLEADO'),(11,'juan','1234','EMPLEADO'),(12,'laura','1234','EMPLEADO'),(13,'carlos','1234','EMPLEADO'),(14,'ana','1234','EMPLEADO'),(15,'sergio','1234','EMPLEADO'),(16,'patricia','1234','EMPLEADO');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'solicitudes_cursos'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-12 13:10:41
