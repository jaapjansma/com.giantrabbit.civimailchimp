-- /*******************************************************
-- *
-- * civimailchimp_sync_settings
-- *
-- *******************************************************/
CREATE TABLE `civimailchimp_sync_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique Identifier',
  `civicrm_group_id` int unsigned NOT NULL COMMENT 'CiviCRM Group ID',
  `mailchimp_list_id` varchar(255) NOT NULL COMMENT 'Mailchimp List ID',
  PRIMARY KEY (`id`),
  CONSTRAINT FK_civimailchimp_sync_settings_group_id FOREIGN KEY (`civicrm_group_id`) REFERENCES `civicrm_group`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

-- /*******************************************************
-- *
-- * civimailchimp_interest_groups_sync_settings
-- *
-- *******************************************************/
CREATE TABLE `civimailchimp_interest_groups_sync_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique Identifier',
  `civimailchimp_sync_settings_id` int unsigned NOT NULL COMMENT 'CiviCRM Sync Settings ID',
  `mailchimp_interest_grouping_id` varchar(255) NOT NULL COMMENT 'Mailchimp Interest Grouping ID',
  `mailchimp_interest_group_id` varchar(255) DEFAULT NULL COMMENT 'Mailchimp Interest Group ID',
  `mailchimp_interest_group_name` varchar(255) DEFAULT NULL COMMENT 'Mailchimp Interest Group Name',
  PRIMARY KEY (`id`),
  CONSTRAINT FK_civimailchimp_interest_groups_sync_settings_id FOREIGN KEY (`civimailchimp_sync_settings_id`) REFERENCES `civimailchimp_sync_settings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

-- /*******************************************************
-- *
-- * civimailchimp_sync_log
-- *
-- *******************************************************/
CREATE TABLE `civimailchimp_sync_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique Identifier',
  `type` varchar(255) NOT NULL COMMENT 'The log message type.',
  `direction` varchar(255) NOT NULL COMMENT 'The log message direction.',
  `message` text NOT NULL COMMENT 'The log message.',
  `details` longtext DEFAULT NULL COMMENT 'The log message details.',
  `cleared` tinyint(1) DEFAULT 0 COMMENT 'The log message details.',
  `civicrm_queue_item_id` int unsigned DEFAULT NULL COMMENT 'ID of the civicrm_queue_item record related to this error message.',
  `timestamp` int unsigned NOT NULL COMMENT 'The log message timestamp.',
  PRIMARY KEY (`id`),
  CONSTRAINT FK_civimailchimp_sync_log_queue_item_id FOREIGN KEY (`civicrm_queue_item_id`) REFERENCES `civicrm_queue_item`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;
