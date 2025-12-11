-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Gép: localhost:3306
-- Létrehozás ideje: 2025. Dec 11. 08:13
-- Kiszolgáló verziója: 10.11.14-MariaDB-cll-lve-log
-- PHP verzió: 8.4.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Adatbázis: `skdneoaa_Felhasznalok`
--

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `comments`
--

CREATE TABLE `comments` (
  `comment_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `content` mediumtext NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `friends`
--

CREATE TABLE `friends` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `friend_id` int(11) NOT NULL,
  `status` enum('pending','accepted') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

--
-- A tábla adatainak kiíratása `friends`
--

INSERT INTO `friends` (`id`, `user_id`, `friend_id`, `status`, `created_at`) VALUES
(17, 25, 24, 'accepted', '2025-12-09 12:19:54'),
(16, 21, 23, '', '2025-12-09 12:19:46'),
(15, 21, 25, 'accepted', '2025-12-09 12:19:45'),
(14, 21, 24, '', '2025-12-09 12:19:45'),
(13, 21, 22, '', '2025-12-09 12:19:43');

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `games`
--

CREATE TABLE `games` (
  `game_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` mediumtext DEFAULT NULL,
  `main_image` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `game_categories`
--

CREATE TABLE `game_categories` (
  `id` int(11) NOT NULL,
  `game_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `images`
--

CREATE TABLE `images` (
  `image_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `image_url` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  `file_path` varchar(255) DEFAULT NULL,
  `file_type` enum('image','video') DEFAULT NULL,
  `media` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

--
-- A tábla adatainak kiíratása `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `message`, `created_at`, `is_read`, `file_path`, `file_type`, `media`) VALUES
(93, 21, 25, '', '2025-12-09 12:22:28', 0, NULL, NULL, 'uploads/6938148446fd8_1765282948.png'),
(92, 21, 25, 'a little bit yea', '2025-12-09 12:20:14', 0, NULL, NULL, NULL),
(91, 25, 21, 'its fucked', '2025-12-09 12:20:08', 0, NULL, NULL, NULL),
(90, 21, 25, 'hello', '2025-12-09 12:20:00', 0, NULL, NULL, NULL),
(89, 21, 24, 'dddddddd', '2025-12-09 12:16:32', 0, NULL, NULL, NULL),
(88, 21, 24, 'dddddddd', '2025-12-09 12:16:29', 0, NULL, NULL, NULL),
(87, 24, 25, 'pooö', '2025-12-09 12:16:25', 0, NULL, NULL, NULL),
(86, 21, 22, 'nigger', '2025-12-09 12:16:19', 0, NULL, NULL, NULL),
(85, 24, 21, 'ááá', '2025-12-09 12:15:57', 0, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` datetime DEFAULT current_timestamp(),
  `profile_img` varchar(255) DEFAULT NULL,
  `remember_token` varchar(64) DEFAULT NULL,
  `remember_selector` char(12) DEFAULT NULL,
  `remember_validator` char(64) DEFAULT NULL,
  `remember_expires` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

--
-- A tábla adatainak kiíratása `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password_hash`, `role`, `created_at`, `profile_img`, `remember_token`, `remember_selector`, `remember_validator`, `remember_expires`) VALUES
(21, 'Zero9282', 'geovanyattilahernandez@gmail.com', '$2y$10$e22G7MuKAcE.yQWCqlpO0.Ta1QBCMDyyB18uc.t.xK4Bm6q7QHt7e', 'user', '2025-12-09 13:04:07', 'uploads/6938120a6f0a6_Képernyőkép 2025-10-26 135519.png', NULL, NULL, NULL, NULL),
(22, 'ruben', 'rubenkatona23@gmail.com', '$2y$10$oGplrOGPr/Rjcv3us6vkdOXHkjr7dICgTwiD.JSldrMj5gs7bZu8K', 'user', '2025-12-09 13:05:19', NULL, '$2y$10$vakFRS/a5o7y/MlXAY0Jh.ETgJJOMeUDva/H6Ql68js/lyvcL.0ba', NULL, NULL, NULL),
(23, 'tezst', 'tezst@gmail.com', '$2y$10$VWVpXO4DwUjW97Gx292/SOdg/p87rSDnZY6kf0rlXVAUMyzYt8YTi', 'user', '2025-12-09 13:10:03', NULL, NULL, NULL, NULL, NULL),
(24, 'bableves', 'bableves@gmail.com', '$2y$10$YjaliTMongCRh6UIBq8RhOwnw6lVm0sAXdF7gviPxGtTJWcS5ieNq', 'user', '2025-12-09 13:13:20', 'uploads/6938126de8a2d_smiley.png', NULL, NULL, NULL, NULL),
(25, 'Krista', 'krista@gmail.com', '$2y$10$vfgBGy.bxQLVbRnrv5bMN.8BsMtDKzwm1TP7kG7SJu3JQ8TCpEZHq', 'user', '2025-12-09 13:13:51', 'uploads/693812ac546ca_flat,750x1000,075,t.jpg', NULL, NULL, NULL, NULL);

--
-- Indexek a kiírt táblákhoz
--

--
-- A tábla indexei `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `user_id` (`user_id`);

--
-- A tábla indexei `friends`
--
ALTER TABLE `friends`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`friend_id`),
  ADD KEY `friend_id` (`friend_id`);

--
-- A tábla indexei `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`game_id`),
  ADD KEY `created_by` (`created_by`);

--
-- A tábla indexei `game_categories`
--
ALTER TABLE `game_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `game_id` (`game_id`);

--
-- A tábla indexei `images`
--
ALTER TABLE `images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `game_id` (`game_id`);

--
-- A tábla indexei `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- A tábla indexei `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`);

--
-- A tábla indexei `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`) USING HASH;

--
-- A kiírt táblák AUTO_INCREMENT értéke
--

--
-- AUTO_INCREMENT a táblához `comments`
--
ALTER TABLE `comments`
  MODIFY `comment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT a táblához `friends`
--
ALTER TABLE `friends`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT a táblához `games`
--
ALTER TABLE `games`
  MODIFY `game_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT a táblához `game_categories`
--
ALTER TABLE `game_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- AUTO_INCREMENT a táblához `images`
--
ALTER TABLE `images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT a táblához `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT a táblához `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT a táblához `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
