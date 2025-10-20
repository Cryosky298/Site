<?php

$servername = "localhost";
$username = "68933a083ead7_anim1";
$password = "Anime11";
$connect = mysqli_connect($servername, $username, $password, $username);

mysqli_query($connect,"CREATE TABLE IF NOT EXISTS `user_id` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(250) NOT NULL,
  `status` text NOT NULL,
  `refid` varchar(11) NOT NULL,
  `sana` varchar(250) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
mysqli_query($connect,"CREATE TABLE IF NOT EXISTS `status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(250) NOT NULL,
  `kun` varchar(250) NOT NULL,
  `date` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
mysqli_query($connect,"CREATE TABLE IF NOT EXISTS `send` (
  `send_id` int(11) NOT NULL,
  `time1` text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `time2` text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `start_id` text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `stop_id` text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `admin_id` text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `message_id` text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `reply_markup` text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `step` text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `time3` text NOT NULL,
  `time4` text NOT NULL,
  `time5` text NOT NULL,
  PRIMARY KEY(`send_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
mysqli_query($connect,"CREATE TABLE IF NOT EXISTS `kabinet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(250) NOT NULL,
  `pul` varchar(250) NOT NULL,
  `pul2` varchar(250) NOT NULL,
  `odam` varchar(250) NOT NULL,
  `ban` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
mysqli_query($connect,"CREATE TABLE IF NOT EXISTS `anime_datas` (
  `data_id` int(11) NOT NULL AUTO_INCREMENT,
  `id` text NOT NULL,
  `file_id` text NOT NULL,
  `qism` text NOT NULL,
  `sana` text DEFAULT NULL,
  PRIMARY KEY (`data_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
mysqli_query($connect,"CREATE TABLE IF NOT EXISTS `animelar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` text NOT NULL,
  `rams` text NOT NULL,
  `qismi` text NOT NULL,
  `davlat` text NOT NULL,
  `tili` text NOT NULL,
  `yili` text NOT NULL,
  `janri` text NOT NULL,
`qidiruv` text NOT NULL,
  `sana` text NOT NULL,
 `type` varchar(10) NOT NULL DEFAULT 'anime',
  `aniType` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

// --- JADVALNI YANGILASH UCHUN TEKSHIRUV ---
// Bu kod `animelar` jadvalida `type` ustuni bor-yo'qligini tekshiradi
// Agar yo'q bo'lsa, uni avtomatik qo'shadi.
$check_column = mysqli_query($connect, "SHOW COLUMNS FROM `animelar` LIKE 'type'");
if (mysqli_num_rows($check_column) == 0) {
    mysqli_query($connect, "ALTER TABLE `animelar` ADD `type` VARCHAR(10) NOT NULL DEFAULT 'anime' AFTER `aniType`");
}

// --- JADVALNI YANGILASH UCHUN TEKSHIRUV ---
// Bu kod `animelar` jadvalida `type` ustuni bor-yo'qligini tekshiradi
// Agar yo'q bo'lsa, uni avtomatik qo'shadi.
$check_column = mysqli_query($connect, "SHOW COLUMNS FROM `animelar` LIKE 'type'");
if (mysqli_num_rows($check_column) == 0) {
    mysqli_query($connect, "ALTER TABLE `animelar` ADD `type` VARCHAR(10) NOT NULL DEFAULT 'anime' AFTER `aniType`");
}
// --- TEKSHIRUV TUGADI ---