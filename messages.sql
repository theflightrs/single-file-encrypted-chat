CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `message_content` text NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

