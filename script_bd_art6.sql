-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema mydb
-- -----------------------------------------------------
-- -----------------------------------------------------
-- Schema art_objects_store2
-- -----------------------------------------------------

-- -----------------------------------------------------
-- Schema art_objects_store2
-- -----------------------------------------------------
CREATE SCHEMA IF NOT EXISTS `art_objects_store2` DEFAULT CHARACTER SET utf8mb3 ;
USE `art_objects_store2` ;

-- -----------------------------------------------------
-- Table `art_objects_store2`.`artists`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `art_objects_store2`.`artists` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `fio` VARCHAR(255) NULL DEFAULT NULL,
  `bio` TEXT NULL DEFAULT NULL,
  `strana` VARCHAR(100) NULL DEFAULT NULL,
  `email` VARCHAR(255) NULL DEFAULT NULL,
  `photo` VARCHAR(255) NULL DEFAULT NULL,
  `year_of_birth` YEAR NULL DEFAULT NULL,
  `year_of_death` YEAR NULL DEFAULT NULL,
  `year_of_career_start` YEAR NULL DEFAULT NULL,
  `style` VARCHAR(255) NULL DEFAULT NULL,
  `brief_introduction` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_UNIQUE` (`id` ASC) VISIBLE)
ENGINE = InnoDB
AUTO_INCREMENT = 7
DEFAULT CHARACTER SET = utf8mb3;


-- -----------------------------------------------------
-- Table `art_objects_store2`.`categories`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `art_objects_store2`.`categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NULL DEFAULT NULL,
  `opisanie` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_UNIQUE` (`id` ASC) VISIBLE)
ENGINE = InnoDB
AUTO_INCREMENT = 9
DEFAULT CHARACTER SET = utf8mb3;


-- -----------------------------------------------------
-- Table `art_objects_store2`.`products`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `art_objects_store2`.`products` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NULL DEFAULT NULL,
  `opisanie` TEXT NULL DEFAULT NULL,
  `price` DECIMAL(10,2) NULL DEFAULT NULL,
  `size` VARCHAR(100) NULL DEFAULT NULL,
  `weight_kg` DECIMAL(5,2) NULL DEFAULT NULL,
  `material` VARCHAR(255) NULL DEFAULT NULL,
  `year_created` YEAR NULL DEFAULT NULL,
  `stock_quantity` INT NULL DEFAULT NULL,
  `image` VARCHAR(255) NULL DEFAULT NULL,
  `category_id` INT NOT NULL,
  `artist_id` INT NOT NULL,
  `art_style` VARCHAR(255) NULL DEFAULT NULL,
  `discount_price` DECIMAL(10,2) NULL DEFAULT NULL,
  `discount_percent` DECIMAL(10,2) NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_UNIQUE` (`id` ASC) VISIBLE,
  INDEX `categories_connection_idx` (`category_id` ASC) VISIBLE,
  INDEX `artists_products_idx` (`artist_id` ASC) VISIBLE,
  CONSTRAINT `artists_products`
    FOREIGN KEY (`artist_id`)
    REFERENCES `art_objects_store2`.`artists` (`id`),
  CONSTRAINT `categories_connection`
    FOREIGN KEY (`category_id`)
    REFERENCES `art_objects_store2`.`categories` (`id`))
ENGINE = InnoDB
AUTO_INCREMENT = 13
DEFAULT CHARACTER SET = utf8mb3;


-- -----------------------------------------------------
-- Table `art_objects_store2`.`users`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `art_objects_store2`.`users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NULL DEFAULT NULL,
  `fio` VARCHAR(255) NULL DEFAULT NULL,
  `password_hash` VARCHAR(255) NULL DEFAULT NULL,
  `role` VARCHAR(45) NULL DEFAULT NULL,
  `phone` VARCHAR(45) NULL DEFAULT NULL,
  `is_active` TINYINT(1) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `city` VARCHAR(255) NULL DEFAULT NULL,
  `address` VARCHAR(255) NULL DEFAULT NULL,
  `reset_token` VARCHAR(128) NULL DEFAULT NULL,
  `reset_token_expiry` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_UNIQUE` (`id` ASC) VISIBLE)
ENGINE = InnoDB
AUTO_INCREMENT = 16
DEFAULT CHARACTER SET = utf8mb3;


-- -----------------------------------------------------
-- Table `art_objects_store2`.`cart_items`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `art_objects_store2`.`cart_items` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NULL DEFAULT NULL,
  `session_id` VARCHAR(45) NULL DEFAULT NULL,
  `product_id` INT NULL DEFAULT NULL,
  `quantity` INT NULL DEFAULT NULL,
  `added_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_UNIQUE` (`id` ASC) VISIBLE,
  INDEX `cart_items_users_idx` (`user_id` ASC) VISIBLE,
  INDEX `cart_items_products_idx` (`product_id` ASC) VISIBLE,
  CONSTRAINT `cart_items_products`
    FOREIGN KEY (`product_id`)
    REFERENCES `art_objects_store2`.`products` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `cart_items_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `art_objects_store2`.`users` (`id`))
ENGINE = InnoDB
AUTO_INCREMENT = 67
DEFAULT CHARACTER SET = utf8mb3;


-- -----------------------------------------------------
-- Table `art_objects_store2`.`favorites_artists`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `art_objects_store2`.`favorites_artists` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `artist_id` INT NOT NULL,
  `added_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_UNIQUE` (`id` ASC) VISIBLE,
  INDEX `favorites_artists_users_idx` (`user_id` ASC) VISIBLE,
  INDEX `favorites_artists_artists_idx` (`artist_id` ASC) VISIBLE,
  CONSTRAINT `favorites_artists_artists`
    FOREIGN KEY (`artist_id`)
    REFERENCES `art_objects_store2`.`artists` (`id`),
  CONSTRAINT `favorites_artists_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `art_objects_store2`.`users` (`id`))
ENGINE = InnoDB
AUTO_INCREMENT = 26
DEFAULT CHARACTER SET = utf8mb3;


-- -----------------------------------------------------
-- Table `art_objects_store2`.`favorites_products`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `art_objects_store2`.`favorites_products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `added_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `favorites_products_users_idx` (`user_id` ASC) VISIBLE,
  INDEX `favorites_products_products_idx` (`product_id` ASC) VISIBLE,
  CONSTRAINT `favorites_products_products`
    FOREIGN KEY (`product_id`)
    REFERENCES `art_objects_store2`.`products` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `favorites_products_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `art_objects_store2`.`users` (`id`))
ENGINE = InnoDB
AUTO_INCREMENT = 44
DEFAULT CHARACTER SET = utf8mb3;


-- -----------------------------------------------------
-- Table `art_objects_store2`.`newsletter_subscribers`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `art_objects_store2`.`newsletter_subscribers` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `subscribed_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` TINYINT(1) NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE INDEX `email_UNIQUE` (`email` ASC) VISIBLE)
ENGINE = InnoDB
AUTO_INCREMENT = 5
DEFAULT CHARACTER SET = utf8mb3;


-- -----------------------------------------------------
-- Table `art_objects_store2`.`orders`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `art_objects_store2`.`orders` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NULL DEFAULT NULL,
  `guest_email` VARCHAR(255) NULL DEFAULT NULL,
  `guest_phone` VARCHAR(45) NULL DEFAULT NULL,
  `order_date` DATETIME NULL DEFAULT NULL,
  `total_price` DECIMAL(10,2) NULL DEFAULT NULL,
  `status` VARCHAR(50) NULL DEFAULT NULL,
  `shipping_address` TEXT NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `payment_method` VARCHAR(255) NULL DEFAULT NULL,
  `payment_status` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_UNIQUE` (`id` ASC) VISIBLE,
  INDEX `orders_users_idx` (`user_id` ASC) VISIBLE,
  CONSTRAINT `orders_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `art_objects_store2`.`users` (`id`))
ENGINE = InnoDB
AUTO_INCREMENT = 43
DEFAULT CHARACTER SET = utf8mb3;


-- -----------------------------------------------------
-- Table `art_objects_store2`.`order_items`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `art_objects_store2`.`order_items` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `order_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `price` DECIMAL(10,2) NULL DEFAULT NULL,
  `quantity` INT NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_UNIQUE` (`id` ASC) VISIBLE,
  INDEX `order_items_orders_idx` (`order_id` ASC) VISIBLE,
  INDEX `order_items_products_idx` (`product_id` ASC) VISIBLE,
  CONSTRAINT `order_items_orders`
    FOREIGN KEY (`order_id`)
    REFERENCES `art_objects_store2`.`orders` (`id`),
  CONSTRAINT `order_items_products`
    FOREIGN KEY (`product_id`)
    REFERENCES `art_objects_store2`.`products` (`id`))
ENGINE = InnoDB
AUTO_INCREMENT = 58
DEFAULT CHARACTER SET = utf8mb3;


-- -----------------------------------------------------
-- Table `art_objects_store2`.`product_images`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `art_objects_store2`.`product_images` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `product_id` INT NOT NULL,
  `image_path` VARCHAR(255) NULL DEFAULT NULL,
  `is_main` TINYINT(1) NULL DEFAULT '0',
  `sort_order` INT NULL DEFAULT '0',
  `alt_text` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_UNIQUE` (`id` ASC) VISIBLE,
  INDEX `product_image_product_idx` (`product_id` ASC) VISIBLE,
  CONSTRAINT `product_image_product`
    FOREIGN KEY (`product_id`)
    REFERENCES `art_objects_store2`.`products` (`id`))
ENGINE = InnoDB
AUTO_INCREMENT = 16
DEFAULT CHARACTER SET = utf8mb3;


-- -----------------------------------------------------
-- Table `art_objects_store2`.`product_questions`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `art_objects_store2`.`product_questions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `product_id` INT NOT NULL,
  `user_id` INT NULL DEFAULT NULL,
  `question` TEXT NOT NULL,
  `answer` TEXT NULL DEFAULT NULL,
  `answered_at` DATETIME NULL DEFAULT NULL,
  `answered_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `status` ENUM('pending', 'published', 'hidden') NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  INDEX `idx_product_id` (`product_id` ASC) VISIBLE,
  INDEX `idx_user_id` (`user_id` ASC) VISIBLE,
  INDEX `idx_status` (`status` ASC) VISIBLE,
  CONSTRAINT `fk_questions_product`
    FOREIGN KEY (`product_id`)
    REFERENCES `art_objects_store2`.`products` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_questions_user`
    FOREIGN KEY (`user_id`)
    REFERENCES `art_objects_store2`.`users` (`id`)
    ON DELETE SET NULL)
ENGINE = InnoDB
AUTO_INCREMENT = 5
DEFAULT CHARACTER SET = utf8mb3;


-- -----------------------------------------------------
-- Table `art_objects_store2`.`reviews`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `art_objects_store2`.`reviews` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `product_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `rating` TINYINT NULL DEFAULT NULL,
  `comment` TEXT NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `status` VARCHAR(50) NULL DEFAULT NULL,
  `order_item_id` INT NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_UNIQUE` (`id` ASC) VISIBLE,
  INDEX `reviews_users_idx` (`user_id` ASC) VISIBLE,
  INDEX `reviews_products_idx` (`product_id` ASC) VISIBLE,
  INDEX `reviews_order_items_idx` (`order_item_id` ASC) VISIBLE,
  CONSTRAINT `reviews_order_items`
    FOREIGN KEY (`order_item_id`)
    REFERENCES `art_objects_store2`.`order_items` (`id`),
  CONSTRAINT `reviews_products`
    FOREIGN KEY (`product_id`)
    REFERENCES `art_objects_store2`.`products` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `reviews_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `art_objects_store2`.`users` (`id`))
ENGINE = InnoDB
AUTO_INCREMENT = 12
DEFAULT CHARACTER SET = utf8mb3;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
