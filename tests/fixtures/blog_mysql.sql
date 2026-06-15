-- Self-contained test fixture for pathpdo (MySQL/MariaDB).
-- Creates the blog schema (categories, users, posts, comments) with the exact
-- data the test suite expects: 12 posts (11 announcement, 1 article) and 6
-- comments (2 on post 1, 4 on post 2). Loaded once by tests/bootstrap.php.

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

DROP TABLE IF EXISTS `comments`;
DROP TABLE IF EXISTS `posts`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `categories` (`id`, `name`) VALUES
(1, 'announcement'),
(2, 'article'),
(3, 'comment');

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`, `username`) VALUES
(1, 'user1'),
(2, 'user2');

CREATE TABLE `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `content` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `posts_category_id_fkey` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `posts_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 11 posts in category 1 (announcement), 1 post in category 2 (article).
INSERT INTO `posts` (`id`, `user_id`, `category_id`, `content`) VALUES
(1, 1, 1, 'blog started'),
(2, 1, 1, 'second post'),
(3, 1, 1, 'third post'),
(4, 1, 1, 'fourth post'),
(5, 1, 1, 'fifth post'),
(6, 1, 1, 'sixth post'),
(7, 1, 1, 'seventh post'),
(8, 1, 1, 'eighth post'),
(9, 1, 1, 'ninth post'),
(10, 1, 1, 'tenth post'),
(11, 1, 1, 'eleventh post'),
(12, 1, 2, 'an article');

CREATE TABLE `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`),
  CONSTRAINT `comments_post_id_fkey` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `comments` (`id`, `post_id`, `message`) VALUES
(1, 1, 'great!'),
(2, 1, 'nice!'),
(3, 2, 'interesting'),
(4, 2, 'cool'),
(5, 2, 'wow'),
(6, 2, 'amazing');

SET foreign_key_checks = 1;
