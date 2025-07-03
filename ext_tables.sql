#
# Table structure for table 'tx_t3hauler_snapshots'
#
CREATE TABLE tx_t3hauler_snapshots (
		uid int(11) NOT NULL AUTO_INCREMENT,
		identifier varchar(255) NOT NULL DEFAULT '',
		table_name varchar(255) NOT NULL DEFAULT '',
		hash varchar(64) NOT NULL DEFAULT '',
		created_at int(11) NOT NULL DEFAULT 0,
		migration_version varchar(255) DEFAULT NULL,
		metadata text,

		PRIMARY KEY (uid),
		UNIQUE KEY identifier (identifier),
		KEY table_name (table_name),
		KEY created_at (created_at),
		KEY migration_version (migration_version)
);

#
# Table structure for table 'tx_t3hauler_migrations'
#
CREATE TABLE tx_t3hauler_migrations (
		uid int(11) NOT NULL AUTO_INCREMENT,
		migration_id varchar(255) NOT NULL DEFAULT '',
		name varchar(255) NOT NULL DEFAULT '',
		description text,
		created_at int(11) NOT NULL DEFAULT 0,
		applied_at int(11) DEFAULT NULL,
		author varchar(255) NOT NULL DEFAULT '',
		source_hash varchar(64) NOT NULL DEFAULT '',
		target_hash varchar(64) DEFAULT NULL,
		status enum('pending','applied','failed','rolled_back') DEFAULT 'pending',
		data_file varchar(255) NOT NULL DEFAULT '',

		PRIMARY KEY (uid),
		UNIQUE KEY migration_id (migration_id),
		KEY status (status),
		KEY created_at (created_at),
		KEY applied_at (applied_at)
);
