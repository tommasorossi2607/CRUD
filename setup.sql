-- setup.sql
-- Esegui questo script in phpMyAdmin oppure tramite MySQL CLI
-- per creare il database e la tabella persona

CREATE DATABASE IF NOT EXISTS `bucigno5dinf`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE `bucigno5dinf`;

CREATE TABLE IF NOT EXISTS `persona` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `nome`       VARCHAR(50)  NOT NULL,
  `cognome`    VARCHAR(50)  NOT NULL,
  `email`      VARCHAR(100) NOT NULL UNIQUE,
  `eta`        INT(3)       DEFAULT NULL,
  `creato_il`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dati di esempio
INSERT INTO `persona` (`nome`, `cognome`, `email`, `eta`) VALUES
('Andrea',  'Bucigno',  'bucignoandrea@gmail.com', 20),
('Mario',   'Rossi',    'mario.rossi@example.com',  35),
('Giulia',  'Bianchi',  'giulia.bianchi@example.com', 28);
