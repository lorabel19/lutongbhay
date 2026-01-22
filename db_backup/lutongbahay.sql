CREATE DATABASE  IF NOT EXISTS `lutongbahay_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `lutongbahay_db`;
-- MySQL dump 10.13  Distrib 8.0.41, for Win64 (x86_64)
--
-- Host: localhost    Database: lutongbahay_db
-- ------------------------------------------------------
-- Server version	8.0.41

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `cart`
--

DROP TABLE IF EXISTS `cart`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cart` (
  `CartID` int NOT NULL AUTO_INCREMENT,
  `CustomerID` int NOT NULL,
  `MealID` int NOT NULL,
  `Quantity` int NOT NULL DEFAULT '1',
  `AddedAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`CartID`),
  KEY `CustomerID` (`CustomerID`),
  KEY `MealID` (`MealID`),
  CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`CustomerID`) REFERENCES `customer` (`CustomerID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`MealID`) REFERENCES `meal` (`MealID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart`
--

LOCK TABLES `cart` WRITE;
/*!40000 ALTER TABLE `cart` DISABLE KEYS */;
INSERT INTO `cart` VALUES (16,1,89,1,'2026-01-21 02:47:00'),(36,3,89,2,'2026-01-21 03:47:24'),(37,3,24,1,'2026-01-21 03:50:37');
/*!40000 ALTER TABLE `cart` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer`
--

DROP TABLE IF EXISTS `customer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer` (
  `CustomerID` int NOT NULL AUTO_INCREMENT,
  `Username` varchar(50) NOT NULL,
  `Email` varchar(150) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `FullName` varchar(100) NOT NULL,
  `ContactNo` varchar(20) DEFAULT NULL,
  `Address` varchar(255) DEFAULT NULL,
  `ImagePath` varchar(500) DEFAULT NULL,
  `CreatedAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`CustomerID`),
  UNIQUE KEY `Username` (`Username`),
  UNIQUE KEY `Email` (`Email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer`
--

LOCK TABLES `customer` WRITE;
/*!40000 ALTER TABLE `customer` DISABLE KEYS */;
INSERT INTO `customer` VALUES (1,'Tay_13','taylor@gmail.com','$2y$10$vztbxq6t8QFYYiOF/WGzheQhSq35RoCaULvffQtcouGsofs4w2YCK','Taylor Swift','09098765432','Parañaque City','uploads/profile_pictures/profile_1_1768962284.jpg','2026-01-20 12:32:11'),(2,'Zayn_01','zayn@gmail.com','$2y$10$CJ51CmYv6H34UavTOrdqGemdKygS8uqzzQ//Ctq1JlAIrQf5BCdQ6','Zayn Malik','09874544343','Pasay City','uploads/profile_pictures/profile_2_1768962569.jpg','2026-01-21 02:28:38'),(3,'Deva_02','deva@gmail.com','$2y$10$zofLf7mNPnU7bTKNERVpH.cKsRA9zFbQFS/Rg2M5kmy638HDQfIii','Deva Cassel','09609876234','Taguig City','uploads/profile_pictures/profile_3_1768962741.jpg','2026-01-21 02:31:19'),(5,'James_02','james@gmail.com','$2y$10$GE01QnyhI87AA6NImYK09.ZRXcXlkqSm9SUZCyKMaZcoFc55UiTOi','James Reid','09098789801','Parañaque City','uploads/profile_pictures/profile_5_1769059137.jpg','2026-01-21 02:37:03');
/*!40000 ALTER TABLE `customer` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `meal`
--

DROP TABLE IF EXISTS `meal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `meal` (
  `MealID` int NOT NULL AUTO_INCREMENT,
  `SellerID` int NOT NULL,
  `Title` varchar(100) NOT NULL,
  `Description` text,
  `Price` decimal(10,2) NOT NULL,
  `ImagePath` varchar(500) DEFAULT NULL,
  `Availability` enum('Available','Not Available') DEFAULT 'Available',
  `Category` enum('Main Dishes','Desserts','Merienda','Vegetarian','Holiday Specials') NOT NULL DEFAULT 'Main Dishes',
  `CreatedAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`MealID`),
  KEY `idx_meal_category` (`Category`),
  KEY `idx_meal_seller` (`SellerID`),
  CONSTRAINT `meal_ibfk_1` FOREIGN KEY (`SellerID`) REFERENCES `seller` (`SellerID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `meal`
--

LOCK TABLES `meal` WRITE;
/*!40000 ALTER TABLE `meal` DISABLE KEYS */;
INSERT INTO `meal` VALUES (1,1,'Chicken Adobo','Chicken simmered in soy sauce, vinegar, and garlic.',180.00,'uploads/1768894410_chicken adobo.jpg','Available','Main Dishes','2026-01-20 07:33:30'),(2,1,'Pork Sinigang','Sour pork soup with vegetables and tamarind broth.',200.00,'uploads/1768894463_Pork Sinigang.jpg','Available','Main Dishes','2026-01-20 07:34:23'),(3,1,'Beef Caldereta','Beef stew cooked in rich tomato sauce.',220.00,'uploads/1768894526_Beef Caldereta.jpg','Available','Main Dishes','2026-01-20 07:35:26'),(4,1,'Leche Flan','Creamy custard with caramel topping.',120.00,'uploads/1768894579_Leche Flan.jpg','Available','Desserts','2026-01-20 07:36:19'),(5,1,'Ube Halaya','Smooth and sweet purple yam dessert.',130.00,'uploads/1768894626_Ube Halaya.jpg','Available','Desserts','2026-01-20 07:37:06'),(6,1,'Buko Pandan','Coconut strips with pandan-flavored cream.',120.00,'uploads/1768894673_Buko Pandan.jpg','Available','Desserts','2026-01-20 07:37:53'),(7,1,'Turon','Fried banana rolls with brown sugar.',60.00,'uploads/1768894721_Turon.jpg','Available','Merienda','2026-01-20 07:38:41'),(8,1,'Banana Cue','Caramelized bananas on a stick.',50.00,'uploads/1768894785_Banana Cue.jpg','Available','Merienda','2026-01-20 07:39:45'),(9,1,'Kutsinta','Chewy rice cake topped with coconut.',70.00,'uploads/1768894841_Kutsinta.jpg','Available','Merienda','2026-01-20 07:40:41'),(10,1,'Pinakbet','Mixed vegetables cooked with bagoong.',150.00,'uploads/1768894947_Pinakbet.jpg','Available','Vegetarian','2026-01-20 07:42:27'),(11,1,'Chopsuey','Stir-fried assorted vegetables.',160.00,'uploads/1768894979_Chopsuey.jpg','Available','Vegetarian','2026-01-20 07:42:59'),(12,1,'Ginisang Tofu','Tofu sautéed with vegetables.',130.00,'uploads/1768895020_Ginisang Tofu.jpg','Available','Vegetarian','2026-01-20 07:43:40'),(13,1,'Lechon Kawali','Crispy deep-fried pork belly.',250.00,'uploads/1768895069_Lechon Kawali.jpg','Available','Holiday Specials','2026-01-20 07:44:29'),(14,1,'Embutido','Filipino-style meatloaf.',200.00,'uploads/1768895105_Embutido.jpg','Available','Holiday Specials','2026-01-20 07:45:05'),(15,1,'Hamonado ','Sweet pork dish for celebrations.',230.00,'uploads/1768895144_Hamonado.jpg','Available','Holiday Specials','2026-01-20 07:45:44'),(16,2,'Pork Menudo','Pork stew with tomato sauce and vegetables.',190.00,'uploads/1768907857_Menudo.jpg','Available','Main Dishes','2026-01-20 11:17:37'),(17,2,'Chicken Tinola','Light chicken soup with ginger.',170.00,'uploads/1768907888_Chicken Tinola.jpg','Available','Main Dishes','2026-01-20 11:18:08'),(18,2,'Pancit Canton','Stir-fried noodles with meat and vegetables.',160.00,'uploads/1768907936_Pancit Canton.jpg','Available','Main Dishes','2026-01-20 11:18:56'),(19,2,'Halo-Halo','Mixed shaved ice dessert with sweets.',150.00,'uploads/1768907965_Halo-Halo.jpg','Available','Desserts','2026-01-20 11:19:25'),(20,2,'Maja Blanca','Coconut pudding with corn.',110.00,'uploads/1768907994_Maja Blanca.jpg','Available','Main Dishes','2026-01-20 11:19:54'),(21,2,'Ginataang Bilo-Bilo','Rice balls cooked in sweet coconut milk.',120.00,'uploads/1768908084_bilobilo.jpg','Available','Desserts','2026-01-20 11:21:24'),(22,2,'Puto ','Soft steamed rice cake.',50.00,'uploads/1768908116_Puto.jpg','Available','Merienda','2026-01-20 11:21:56'),(23,2,'Bibingka','Rice cake with coconut milk and salted egg.',80.00,'uploads/1768908151_Bibingka.jpg','Available','Merienda','2026-01-20 11:22:31'),(24,2,'Camote Cue','Caramelized sweet potato on stick.',50.00,'uploads/1768908189_Camote Cue.jpg','Available','Merienda','2026-01-20 11:23:09'),(25,2,'Laing','Taro leaves cooked in coconut milk.',140.00,'uploads/1768908228_Laing.jpg','Available','Vegetarian','2026-01-20 11:23:48'),(26,2,'Vegetable Lumpia','Crispy rolls filled with vegetables.',140.00,'uploads/1768908257_Vegetable Lumpia.jpg','Available','Vegetarian','2026-01-20 11:24:17'),(27,2,'Ginisang Ampalaya','Bitter melon sautéed with onions.',120.00,'uploads/1768908291_Ginisang ampalaya.jpg','Available','Vegetarian','2026-01-20 11:24:51'),(28,2,'Pancit Palabok','Rice noodles with shrimp sauce and toppings.',180.00,'uploads/1768908377_Pancit Palabok.jpg','Available','Holiday Specials','2026-01-20 11:26:17'),(29,2,'Chicken Relleno ','Stuffed whole chicken dish.',260.00,'uploads/1768908437_Chicken Relleno.jpg','Available','Holiday Specials','2026-01-20 11:27:17'),(30,2,'Morcon ','Rolled beef stuffed with fillings.',240.00,'uploads/1768908502_Morcon.jpg','Available','Holiday Specials','2026-01-20 11:28:22'),(31,3,'Bistek Tagalog',' Beef steak with soy sauce and onions.',200.00,'uploads/1768909103_Bistek Tagalog.jpg','Available','Main Dishes','2026-01-20 11:38:23'),(32,3,'Chicken Afritada','Chicken stew in tomato sauce.',190.00,'uploads/1768909264_Chicken Afritada.jpg','Available','Main Dishes','2026-01-20 11:41:04'),(33,3,'Pork Adobo','Pork cooked in vinegar and soy sauce.',180.00,'uploads/1768909322_Pork Adobo.jpg','Available','Main Dishes','2026-01-20 11:42:02'),(34,3,'Sapin-Sapin','Layered rice cake with coconut flavor.',120.00,'uploads/1768909414_Sapin-Sapin.jpg','Available','Desserts','2026-01-20 11:43:34'),(35,3,'Cassava Cake ','Moist cassava dessert with coconut milk.',130.00,'uploads/1768909471_Cassava Cake.jpg','Available','Desserts','2026-01-20 11:44:31'),(36,3,'Ube Cheese Halaya','Ube dessert topped with cheese.',150.00,'uploads/1768909528_Ube Cheese Halaya.jpg','Available','Desserts','2026-01-20 11:45:28'),(37,3,' Pandesal','Soft Filipino bread roll.',40.00,'uploads/1768909605_Pandesal.jpg','Available','Merienda','2026-01-20 11:46:45'),(38,3,'Cheese Puto','Steamed rice cake with cheese.',60.00,'uploads/1768909638_Cheese Puto.jpg','Available','Merienda','2026-01-20 11:47:18'),(39,3,'Maruya ','Banana fritters coated in batter.',50.00,'uploads/1768909666_Maruya.jpg','Available','Merienda','2026-01-20 11:47:46'),(40,3,'Ginisang Monggo','Sautéed mung beans with vegetables.',130.00,'uploads/1768909695_Ginisang Monggo.jpg','Available','Vegetarian','2026-01-20 11:48:15'),(41,3,'Tortang Talong','Eggplant omelette.',120.00,'uploads/1768909731_Tortang Talong.jpg','Available','Vegetarian','2026-01-20 11:48:51'),(42,3,'Ensaladang Talong ','Grilled eggplant salad.',110.00,'uploads/1768909760_Ensaladang Talong.jpg','Available','Vegetarian','2026-01-20 11:49:20'),(43,3,'Lechon Paksiw ','Leftover lechon cooked in sauce.',240.00,'uploads/1768909788_Lechon Paksiw.jpg','Available','Holiday Specials','2026-01-20 11:49:48'),(44,3,'Fiesta Ham','Sweet cured ham for celebrations.',260.00,'uploads/1768909813_Fiesta Ham.jpg','Available','Holiday Specials','2026-01-20 11:50:13'),(45,3,'Pork BBQ Bilao','Grilled pork skewers for sharing.',280.00,'uploads/1768909920_bbq.jpg','Available','Holiday Specials','2026-01-20 11:52:00'),(46,4,'Chicken Curry','Chicken cooked in coconut curry sauce.',210.00,'uploads/1768910330_Chicken Curry.jpg','Available','Main Dishes','2026-01-20 11:58:50'),(47,4,'Pork Binagoongan','Pork sautéed with shrimp paste.',200.00,'uploads/1768910359_Pork Binagoongan.jpg','Available','Main Dishes','2026-01-20 11:59:19'),(48,4,'Beef Tapa','Cured beef served savory.',190.00,'uploads/1768910395_Beef Tapa.jpg','Available','Main Dishes','2026-01-20 11:59:55'),(49,4,'Yema Cake ','Soft cake with yema frosting.',150.00,'uploads/1768910427_Yema Cake.jpg','Available','Desserts','2026-01-20 12:00:27'),(50,4,'Brazo de Mercedes','Meringue roll with custard filling.',160.00,'uploads/1768910457_Brazo de Mercedes.jpg','Available','Desserts','2026-01-20 12:00:57'),(51,4,'Buko Pie','Coconut-filled pastry pie.',140.00,'uploads/1768910489_Buko Pie.jpg','Available','Desserts','2026-01-20 12:01:29'),(52,4,'Empanada','Pastry filled with meat and vegetables.',70.00,'uploads/1768910519_Empanada.jpg','Available','Merienda','2026-01-20 12:01:59'),(53,4,'Siomai ','Steamed pork dumplings.',60.00,'uploads/1768910548_Siomai.jpg','Available','Merienda','2026-01-20 12:02:28'),(54,4,'Fish Ball ','Street food fish balls with sauce.',40.00,'uploads/1768910589_Fish Ball.jpg','Available','Merienda','2026-01-20 12:03:09'),(55,4,'Ginisang Sitaw at Kalabasa','Sautéed string beans and squash.',120.00,'uploads/1768910619_Ginisang Sitaw at Kalabasa.jpg','Available','Vegetarian','2026-01-20 12:03:39'),(56,4,'Vegetable Kare-Kare','Vegetables in peanut sauce.',160.00,'uploads/1768910643_Vegetable Kare-Kare.jpg','Available','Vegetarian','2026-01-20 12:04:03'),(57,4,'Tofu Sisig','Crispy tofu with sisig seasoning.',140.00,'uploads/1768910673_Tofu Sisig.jpg','Available','Vegetarian','2026-01-20 12:04:33'),(58,4,'Roast Chicken',' Whole chicken roasted with herbs.',260.00,'uploads/1768910715_Roast Chicken.jpg','Available','Holiday Specials','2026-01-20 12:05:15'),(59,4,'Pork Hamonado','Sweet pineapple-flavored pork.',230.00,'uploads/1768910741_Pork Hamonado.jpeg','Available','Holiday Specials','2026-01-20 12:05:41'),(60,4,'Beef Mechado','Beef stew with tomato sauce.',250.00,'uploads/1768910769_Beef Mechado.jpg','Available','Holiday Specials','2026-01-20 12:06:09'),(61,5,'Pork Chop','Pan-fried pork chop with gravy.',180.00,'uploads/1768910934_Pork Chop.jpg','Available','Main Dishes','2026-01-20 12:08:54'),(62,5,'Chicken Inasal','Grilled chicken with garlic marinade.',200.00,'uploads/1768910961_Chicken Inasal.jpg','Available','Main Dishes','2026-01-20 12:09:21'),(63,5,'Bangus Sisig','Sizzling milkfish with onions.',190.00,'uploads/1768910985_Bangus Sisig.jpg','Available','Main Dishes','2026-01-20 12:09:45'),(64,5,'Macapuno Salad ','Sweet coconut salad with cream.',130.00,'uploads/1768911017_Macapuno Salad.jpg','Available','Desserts','2026-01-20 12:10:17'),(65,5,'Fruit Salad','Mixed fruits in sweet cream.',120.00,'uploads/1768911047_Fruit Salad.jpg','Available','Desserts','2026-01-20 12:10:47'),(66,5,'Caramel Bar ','Soft bar topped with caramel.',110.00,'uploads/1768911075_Caramel Bar.jpg','Available','Desserts','2026-01-20 12:11:15'),(67,5,'Hotcake','Fluffy Filipino-style pancake.',50.00,'uploads/1768911107_Hotcake.jpg','Available','Merienda','2026-01-20 12:11:47'),(69,5,'Pichi-Pichi','Cassava dessert with coconut.',70.00,'uploads/1768911234_Pichi-Pichi.jpg','Available','Merienda','2026-01-20 12:13:54'),(70,5,'Ginataang Langka','Young jackfruit cooked in coconut milk.',140.00,'uploads/1768911291_Ginataang Langka.jpg','Available','Vegetarian','2026-01-20 12:14:51'),(71,5,'Vegetable Adobo','Vegetables cooked adobo-style.',130.00,'uploads/1768911326_Vegetable Adobo.jpg','Available','Vegetarian','2026-01-20 12:15:26'),(72,5,'Stir-fry Pechay ','Quick sautéed bok choy.',120.00,'uploads/1768911352_Stir-fry Pechay.jpg','Available','Vegetarian','2026-01-20 12:15:52'),(73,5,'Seafood Paella','Rice dish with mixed seafood.',280.00,'uploads/1768911381_Seafood Paella.jpg','Available','Holiday Specials','2026-01-20 12:16:21'),(74,5,'Beef Morcon ','Stuffed rolled beef dish.',260.00,'uploads/1768911408_Beef Morcon.jpg','Available','Holiday Specials','2026-01-20 12:16:48'),(75,5,'Holiday Spaghetti','Sweet Filipino-style spaghetti.',180.00,'uploads/1768911441_Holiday Spaghetti.jpg','Available','Holiday Specials','2026-01-20 12:17:21'),(76,7,'Chicken Teriyaki (Filipino style)','Sweet-savory glazed chicken.',190.00,'uploads/1768911764_Chicken Teriyaki (Filipino style).jpg','Available','Main Dishes','2026-01-20 12:22:44'),(77,7,'Pork Steak','Pork cooked in soy sauce and onions.',200.00,'uploads/1768911810_Pork Steak.jpg','Available','Main Dishes','2026-01-20 12:23:30'),(78,7,'Bangus Inasal ','Grilled milkfish with inasal flavor.',210.00,'uploads/1768911838_Bangus Inasal.jpg','Available','Main Dishes','2026-01-20 12:23:58'),(79,7,'Chocolate Crinkles','Soft chocolate cookies with sugar coating.',100.00,'uploads/1768911881_Chocolate Crinkles.jpg','Not Available','Desserts','2026-01-20 12:24:41'),(80,7,'Ube Cake','Moist cake flavored with ube.',150.00,'uploads/1768911906_Ube Cake.jpg','Available','Desserts','2026-01-20 12:25:06'),(81,7,'Custard Cake ','Soft cake with custard topping.',130.00,'uploads/1768911944_Custard Cake.jpg','Available','Desserts','2026-01-20 12:25:44'),(82,7,'Lumpiang Shanghai ','Crispy spring rolls with meat.',80.00,'uploads/1768911975_Lumpiang Shanghai.jpg','Available','Merienda','2026-01-20 12:26:15'),(83,7,'Cheese Sticks','Fried cheese rolls.',70.00,'uploads/1768912018_Cheese Sticks.jpg','Available','Merienda','2026-01-20 12:26:58'),(84,7,'Nachos Filipino Style','Chips with local-style toppings.',90.00,'uploads/1768912053_Nachos Filipino Style.jpg','Available','Merienda','2026-01-20 12:27:33'),(85,7,'Mushroom Adobo','Mushrooms cooked adobo-style.',140.00,'uploads/1768912081_Mushroom Adobo.jpg','Available','Vegetarian','2026-01-20 12:28:01'),(86,7,'Vegetable Pancit','Noodles with mixed vegetables.',150.00,'uploads/1768912111_Vegetable Pancit.jpg','Available','Vegetarian','2026-01-20 12:28:31'),(87,7,'Corn & Veggie Salad','Fresh corn mixed with vegetables.',120.00,'uploads/1768912146_Corn & Veggie Salad.jpg','Available','Vegetarian','2026-01-20 12:29:06'),(88,7,'Holiday Carbonara','Creamy pasta with bacon.',220.00,'uploads/1768912174_Holiday Carbonara.jpg','Available','Holiday Specials','2026-01-20 12:29:34'),(89,7,'Baked Macaroni','Oven-baked pasta with cheese.',200.00,'uploads/1768912197_Baked Macaroni.jpg','Available','Holiday Specials','2026-01-20 12:29:57'),(90,7,'Roast Pork Belly','Slow-roasted pork belly.',280.00,'uploads/1768912232_Roast Pork Belly.jpg','Available','Holiday Specials','2026-01-20 12:30:32');
/*!40000 ALTER TABLE `meal` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order`
--

DROP TABLE IF EXISTS `order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order` (
  `OrderID` int NOT NULL AUTO_INCREMENT,
  `CustomerID` int NOT NULL,
  `OrderDate` datetime DEFAULT CURRENT_TIMESTAMP,
  `Status` enum('Pending','Confirmed','Preparing','Out for Delivery','Completed','Cancelled') DEFAULT 'Pending',
  `TotalAmount` decimal(10,2) DEFAULT '0.00',
  `DeliveryAddress` varchar(255) DEFAULT NULL,
  `ContactNo` varchar(15) DEFAULT NULL,
  `Notes` text,
  PRIMARY KEY (`OrderID`),
  KEY `CustomerID` (`CustomerID`),
  CONSTRAINT `order_ibfk_1` FOREIGN KEY (`CustomerID`) REFERENCES `customer` (`CustomerID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order`
--

LOCK TABLES `order` WRITE;
/*!40000 ALTER TABLE `order` DISABLE KEYS */;
INSERT INTO `order` VALUES (1,5,'2026-01-21 10:40:28','Cancelled',80.00,'Parañaque City','09098789801','gusto ko bagong luto\r\n'),(2,5,'2026-01-21 10:40:28','Completed',670.00,'Parañaque City','09098789801','gusto ko bagong luto\r\n'),(3,1,'2026-01-21 10:44:30','Completed',1910.00,'Parañaque City','09098765432','lil bit spicy plz'),(4,1,'2026-01-21 10:45:30','Cancelled',180.00,'Parañaque City','09098765432','wala'),(5,1,'2026-01-21 10:46:15','Cancelled',340.00,'Parañaque City','09098765432','wala po'),(6,2,'2026-01-21 10:47:53','Completed',1000.00,'Pasay City','09874544343','love u'),(7,3,'2026-01-21 10:51:49','Completed',1480.00,'Taguig City','09609876234','pakibilisan'),(8,3,'2026-01-21 11:00:33','Completed',180.00,'Taguig City','09609876234','wala'),(9,3,'2026-01-21 11:03:21','Cancelled',670.00,'Taguig City','09609876234','hehe'),(10,5,'2026-01-21 13:04:52','Completed',450.00,'Parañaque City','09098789801','walarinn'),(11,5,'2026-01-22 12:26:05','Cancelled',80.00,'Parañaque City','09098789801','wala'),(12,5,'2026-01-22 12:26:05','Cancelled',230.00,'Parañaque City','09098789801','wala'),(13,5,'2026-01-22 12:26:22','Completed',230.00,'Parañaque City','09098789801','ww'),(14,5,'2026-01-22 13:31:18','Completed',730.00,'Parañaque City','09098789801','wala');
/*!40000 ALTER TABLE `order` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orderdetails`
--

DROP TABLE IF EXISTS `orderdetails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orderdetails` (
  `OrderDetailID` int NOT NULL AUTO_INCREMENT,
  `OrderID` int NOT NULL,
  `MealID` int NOT NULL,
  `Quantity` int NOT NULL DEFAULT '1',
  `Subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`OrderDetailID`),
  KEY `OrderID` (`OrderID`),
  KEY `MealID` (`MealID`),
  CONSTRAINT `orderdetails_ibfk_1` FOREIGN KEY (`OrderID`) REFERENCES `order` (`OrderID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `orderdetails_ibfk_2` FOREIGN KEY (`MealID`) REFERENCES `meal` (`MealID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orderdetails`
--

LOCK TABLES `orderdetails` WRITE;
/*!40000 ALTER TABLE `orderdetails` DISABLE KEYS */;
INSERT INTO `orderdetails` VALUES (1,1,67,1,50.00),(2,2,89,1,200.00),(3,2,88,2,440.00),(4,3,89,3,600.00),(5,3,90,2,560.00),(6,3,88,2,440.00),(7,3,85,2,280.00),(8,4,80,1,150.00),(9,5,88,1,220.00),(10,5,84,1,90.00),(11,6,88,1,220.00),(12,6,89,1,200.00),(13,6,90,1,280.00),(14,6,87,1,120.00),(15,6,86,1,150.00),(16,7,90,1,280.00),(17,7,89,1,200.00),(18,7,88,1,220.00),(19,7,77,1,200.00),(20,7,76,1,190.00),(21,7,78,1,210.00),(22,7,80,1,150.00),(23,8,80,1,150.00),(24,9,88,1,220.00),(25,9,90,1,280.00),(26,9,85,1,140.00),(27,10,89,1,200.00),(28,10,88,1,220.00),(29,11,24,1,50.00),(30,12,31,1,200.00),(31,13,89,1,200.00),(32,14,90,1,280.00),(33,14,88,1,220.00),(34,14,89,1,200.00);
/*!40000 ALTER TABLE `orderdetails` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `seller`
--

DROP TABLE IF EXISTS `seller`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seller` (
  `SellerID` int NOT NULL AUTO_INCREMENT,
  `Username` varchar(50) NOT NULL,
  `Email` varchar(150) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `FullName` varchar(100) NOT NULL,
  `ContactNo` varchar(20) DEFAULT NULL,
  `Address` varchar(255) DEFAULT NULL,
  `ImagePath` varchar(500) DEFAULT NULL,
  `CreatedAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`SellerID`),
  UNIQUE KEY `Username` (`Username`),
  UNIQUE KEY `Email` (`Email`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seller`
--

LOCK TABLES `seller` WRITE;
/*!40000 ALTER TABLE `seller` DISABLE KEYS */;
INSERT INTO `seller` VALUES (1,'Yumi_01','franceska@gmail.com','$2y$10$qUszTRUybvkrF/5rfOjsreN8TVoPVPSFx.76aofDbiH87RTpQY.HK','Franceska Felonia','09090926572','Parañaque City','uploads/seller_profile/1768894292_619384389_1945673049665209_3139022159354904193_n.jpg','2026-01-20 07:30:12'),(2,'Nath_Nath01','nathaniel@gmail.com','$2y$10$6nFHmJLKHotcwquT6Bng7.sds6YLtKMjZqtYF9FTffgRS9Cr1hA1W','Generalao, Nathaniel','09504382945','Pasay','uploads/seller_profile/1768907796_615821556_746734134716361_4302974115835112926_n.jpg','2026-01-20 07:48:21'),(3,'Jenjen_hehe01','jenniva@gmail.com','$2y$10$GUN55ua2o0J8v53OL1owxOaUvW0i1/YSZSxP7NXjiD.Pl5JHvHrZy','Jenniva Jarapan','09506372836','Quezon City','uploads/seller_profile/1768909004_bfdc2e00-e708-4332-9d2e-31c7006ab905.jpg','2026-01-20 11:36:24'),(4,'Rox_02','roxanne@gmail.com','$2y$10$Lk72TLafAMFUdF/42Jz/Ce9XtatI2cZrMwqlnFycgaaQPRl5SQY4W','Roxanne Magtabog','09067395432','Taguig City','uploads/seller_profile/1768910037_615575673_3031524287039440_3774151267849244996_n.jpg','2026-01-20 11:53:36'),(5,'reyy_na01','reynald@gmail.com','$2y$10$Bsi9jD/g5JeD3CmMbPDt2eU44JPYSiBup4B9I2jqekyDUrdPo9wpK','Reynald Medallada','09765243124','Muntinlupa City','uploads/seller_profile/1768910884_016ec480-494d-489f-8c12-b9825ae22f54.jpg','2026-01-20 12:07:36'),(7,'Lora_19','lorabel@gmail.com','$2y$10$602twg2B7jimmOkBgVprSOi3mTsz4EZcgjP6xZqmtZ4FPtVS6avOW','Lorabel Rabeje jr','09878768743','Parañaque City','uploads/seller_profile/1769060201_deva-cassel-eta-fidanzato-monica-bellucci.jpg','2026-01-20 12:21:45');
/*!40000 ALTER TABLE `seller` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-01-22 13:41:47
