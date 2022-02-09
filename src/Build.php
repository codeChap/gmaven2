<?php

class Build
{
	/**
	 * Builds the required Gmaven tables
	 */
	public static function now($config)
	{
		try{

			// Connect
			$db = new \PDO("mysql:host=".$config['host'].";dbname=".$config['base'].";charset=utf8", $config['user'], $config['pass']);
			$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

			// SQL
			$query = $db->query("SHOW TABLES");
			$tables = $query->fetchAll(\PDO::FETCH_COLUMN);

			print PHP_EOL;
			print yii\helpers\BaseConsole::renderColoredString("%1                                             %n") . PHP_EOL;
			print yii\helpers\BaseConsole::renderColoredString("%1 This command will drop and recreate tables! %n") . PHP_EOL;
			print yii\helpers\BaseConsole::renderColoredString("%1                                             %n") . PHP_EOL . PHP_EOL;

			if(yii\helpers\BaseConsole::confirm("Sure?", 'yes') == false){
				print yii\helpers\BaseConsole::renderColoredString("%yaborted...%n");
				die();
			}

			// Prefix
			$pfx = $config['pfx'];

			foreach([
				$pfx."gmaven_property_details",
				$pfx."gmaven_contacts_to_properties",
				$pfx."gmaven_brokers_to_properties",
				$pfx."gmaven_categories",
				$pfx."gmaven_provinces",
				$pfx."gmaven_suburbs",
				$pfx."gmaven_cities",
				$pfx."gmaven_properties",
				$pfx."gmaven_units",
				$pfx."gmaven_building_images",
				$pfx."gmaven_unit_images",
				$pfx."gmaven_brokers",
				$pfx."gmaven_contacts",
			] AS $tbl){
				if(in_array($tbl, $tables)){
					$db->exec("DROP TABLE " . $tbl);
					print yii\helpers\BaseConsole::renderColoredString("%y ✔ \"".$tbl."\" droped.%n").PHP_EOL;
				}
			}

			// Build category table
			$table = $pfx."gmaven_categories";
			$q = "
				CREATE TABLE `".$table."`(
					`id`           INT(11) AUTO_INCREMENT PRIMARY KEY,
					`category`     VARCHAR(90) NOT NULL,
					`updated_at`   INT(11) NOT NULL)
					ENGINE=ARIA
				DEFAULT CHARSET=utf8
			";
			$db->exec($q);
			print yii\helpers\BaseConsole::renderColoredString("%g ✔ \"".$table."\" created.%n").PHP_EOL;

			// Build provinces table
			$table = $pfx."gmaven_provinces";
			$q = "
				CREATE TABLE `".$table."`(
					`id`           INT(11) AUTO_INCREMENT PRIMARY KEY,
					`province`     VARCHAR(90) NOT NULL,
					`updated_at`   INT(11) NOT NULL
				)
				ENGINE=ARIA
				DEFAULT CHARSET=utf8
			";
			$db->exec($q);
			print yii\helpers\BaseConsole::renderColoredString("%g ✔ \"".$table."\" created.%n").PHP_EOL;

			// Build suburbs table
			$table = $pfx."gmaven_suburbs";
			$q = "
				CREATE TABLE `".$table."`(
					`id`             INT(11) AUTO_INCREMENT PRIMARY KEY,
					`province_id`    INT(11) NOT NULL,
					`suburb`         VARCHAR(90) NOT NULL,
					`updated_at`     INT(11) NOT NULL,
					INDEX(`province_id`)
				)
				ENGINE=ARIA
				DEFAULT CHARSET=utf8
			";
			$db->exec($q);
			print yii\helpers\BaseConsole::renderColoredString("%g ✔ \"".$table."\" created.%n").PHP_EOL;

			// Build cities table
			$table = $pfx."gmaven_cities";
			$q = "
				CREATE TABLE `".$table."`(
					`id`           INT(11) AUTO_INCREMENT PRIMARY KEY,
					`city`         VARCHAR(90) NOT NULL,
					`updated_at`   INT(11) NOT NULL
				)
				ENGINE=ARIA
				DEFAULT CHARSET=utf8
			";
			$db->exec($q);
			print yii\helpers\BaseConsole::renderColoredString("%g ✔ \"".$table."\" created.%n").PHP_EOL;

			// Build properties table
			$table = $pfx."gmaven_properties";
			$q = "
				CREATE TABLE `".$table."`(
					`id`                   INT(11) AUTO_INCREMENT PRIMARY KEY,
					`did`                  INT(11) NOT NULL COMMENT 'Details ID',
					`lon`                  DECIMAL(9,7) DEFAULT NULL,
					`lat`                  DECIMAL(9,7) DEFAULT NULL,
					`gla`                  INT(9) DEFAULT 0,
					`currentVacantArea`    INT(9) DEFAULT 0,
					`weightedAskingRental` INT(9) DEFAULT 0,
					`for_sale`             TINYINT(1) DEFAULT 0,
					`asking_price`         INT(11) DEFAULT 0,
					`category_id`          INT(11) DEFAULT 0,
					`province_id`          INT(11) DEFAULT 0,
					`city_id`              INT(11) DEFAULT 0,
					`suburb_id`            INT(11) DEFAULT 0,
					`updated_at`           INT(11) NOT NULL,
					`gmv_updated`          INT(11) DEFAULT 0,
					FOREIGN KEY (did) REFERENCES Details(did),
					INDEX(`did`, `category_id`, `province_id`, `city_id`, `suburb_id`)
				)
				ENGINE=ARIA
				DEFAULT CHARSET=utf8
				
			";
			$db->exec($q);
			print yii\helpers\BaseConsole::renderColoredString("%g ✔ \"".$table."\" created.%n").PHP_EOL;

			// Build properties table
			$table = $pfx."gmaven_property_details";
			$q = "
				CREATE TABLE `".$table."`(
					`id`   INT( 11 ) AUTO_INCREMENT PRIMARY KEY,
					`gmv_id`               VARCHAR(90),
					`name`                 VARCHAR(90),
					`customReferenceId`    VARCHAR(40),
					`displayAddress`       TEXT,
					`marketingBlurb`       TEXT,
					FULLTEXT(`displayAddress`),
					FULLTEXT(`marketingBlurb`),
					INDEX(`gmv_id`)
				)
				ENGINE=ARIA
				DEFAULT CHARSET=utf8
			";
			$db->exec($q);
			print yii\helpers\BaseConsole::renderColoredString("%g ✔ \"".$table."\" created.%n").PHP_EOL;

			// Build properties table
			$table = $pfx."gmaven_units";
			$q = "
				CREATE TABLE `".$table."`(
					`id`                INT(11) AUTO_INCREMENT,
					`pid`               INT(90) NOT NULL,
					`gmv_id`            VARCHAR(90) NOT NULL,
					`propertyId`        VARCHAR(90),
					`unitId`            VARCHAR(220),
					`customReferenceId` VARCHAR(220),
					`category_id`       INT(11) DEFAULT 0,
					`gla`               INT(9) DEFAULT 0,
					`gmr`               INT(9) DEFAULT 0,
					`netAskingRental`   INT(9) DEFAULT 0,
					`availableType`     VARCHAR(90),
					`availableFrom`     VARCHAR(90),
					`marketingHeading`  BLOB,
					`description`       BLOB,
					`vacancy`           VARCHAR(90) NOT NULL,
					`sales`             VARCHAR(90),
					`updated_at`        INT(11) NOT NULL,
					`gmv_updated`       INT(11) DEFAULT 0,
					PRIMARY KEY(`id`),
					INDEX(`pid`, `gmv_id`)
				)
				ENGINE=ARIA
				DEFAULT CHARSET=utf8
			";
			$db->exec($q);
			print yii\helpers\BaseConsole::renderColoredString("%g ✔ \"".$table."\" created.%n").PHP_EOL;

			// Build properties table
			$table = $pfx."gmaven_building_images";
			$q = "
				CREATE TABLE `".$table."`(
					`id`                 INT(11) AUTO_INCREMENT PRIMARY KEY,
					`entityDomainKey`    VARCHAR(90) NOT NULL,
					`contentDomainKey`   VARCHAR(90) NOT NULL,
					`rating`             INT(2) DEFAULT 0,
					`updated_at`         INT(11) NOT NULL,
					`gmv_updated`        INT(11) DEFAULT 0,
					`removed`            INT(1) DEFAULT 0,
					INDEX(`entityDomainKey`)
				)
				ENGINE=ARIA
				DEFAULT CHARSET=utf8
			";
			$db->exec($q);
			print yii\helpers\BaseConsole::renderColoredString("%g ✔ \"".$table."\" created.%n").PHP_EOL;

			// Build properties table
			$table = $pfx."gmaven_unit_images";
			$q = "
				CREATE TABLE `".$table."`(
					`id`                 INT(11) AUTO_INCREMENT PRIMARY KEY,
					`entityDomainKey`    VARCHAR(90) NOT NULL,
					`contentDomainKey`   VARCHAR(90) NOT NULL,
					`rating`             INT(2) DEFAULT 0,
					`updated_at`         INT(11) NOT NULL,
					`gmv_updated`        INT(11) DEFAULT 0,
					`removed`            INT(1) DEFAULT 0
				)
				ENGINE=ARIA
				DEFAULT CHARSET=utf8
			";
			$db->exec($q);
			print yii\helpers\BaseConsole::renderColoredString("%g ✔ \"".$table."\" created.%n").PHP_EOL;

			// Build brokers table
			$table = $pfx."gmaven_brokers";
			$q = "
				CREATE TABLE `".$table."`(
					`id` INT(11) AUTO_INCREMENT PRIMARY KEY,
					`gmv_id`     VARCHAR(90) NOT NULL,
					`name`       VARCHAR(90),
					`resp`       VARCHAR(90),
					`tel`        VARCHAR(90),
					`cell`       VARCHAR(90),
					`email`      VARCHAR(90),
					`updated_at` INT(11) NOT NULL,
					INDEX(`gmv_id`)
				)
				ENGINE=ARIA
				DEFAULT CHARSET=utf8
			";
			$db->exec($q);
			print yii\helpers\BaseConsole::renderColoredString("%g ✔ \"".$table."\" created.%n").PHP_EOL;

			// Build brokers to properties table
			$table = $pfx."gmaven_brokers_to_properties";
			$q = "
				CREATE TABLE `".$table."`(
					`pid` INT(11) NOT NULL,
					`bid` INT(11) NOT NULL,
					UNIQUE KEY `pid_bid`(`pid`, `bid`),
					INDEX(`pid`, `bid`)
				)
				ENGINE=ARIA
				DEFAULT CHARSET=utf8
			";
			$db->exec($q);
			print yii\helpers\BaseConsole::renderColoredString("%g ✔ \"".$table."\" created.%n").PHP_EOL;

			// Build contacts table
			$table = $pfx."gmaven_contacts";
			$q = "
				CREATE TABLE `".$table."`(
					`id` INT(11) AUTO_INCREMENT PRIMARY KEY,
					`gmv_id`     VARCHAR(90) NOT NULL,
					`name`       VARCHAR(90),
					`tel`        VARCHAR(90),
					`cell`       VARCHAR(90),
					`email`      VARCHAR(90),
					`updated_at` INT(11) NOT NULL,
					INDEX(`gmv_id`)
				)
				ENGINE=ARIA
				DEFAULT CHARSET=utf8
			";
			$db->exec($q);
			print yii\helpers\BaseConsole::renderColoredString("%g ✔ \"".$table."\" created.%n").PHP_EOL;

			// Build contacts to properties table
			$table = $pfx."gmaven_contacts_to_properties";
			$q = "
				CREATE TABLE `".$table."`(
					`pid` INT(11) NOT NULL,
					`cid` INT(11) NOT NULL,
					UNIQUE KEY `pid_bid`(`pid`, `cid`),
					INDEX(`pid`, `cid`)
				)
				ENGINE=ARIA
				DEFAULT CHARSET=utf8
			";
			$db->exec($q);
			print yii\helpers\BaseConsole::renderColoredString("%g ✔ \"".$table."\" created.%n").PHP_EOL;

		// Close
		$db = null;
		}
		catch(\PDOException $e){
			echo $e->getMessage();
		}
	}
}