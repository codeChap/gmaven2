<?php

namespace codechap\Gmaven2;

class Gmv
{
	private $call;

	private $db;

	private $time;

	/**
	 * Builds the required Gmaven tables
	 */
	public static function build($config = [])
	{
		include("Build.php");
		\Build::now($config);
	}

	/**
	 * Load and setup call and db connections
	 */
	public function __construct($config = [])
	{
		include("Call.php");
		$this->call = new Call($config);

		include("Db.php");
		$this->db = new Db($config);

		$this->time = time();
	}

	public function sync()
	{
		print \yii\helpers\BaseConsole::renderColoredString("%G Getting categories %n") . PHP_EOL;
		$this->getCategories();
		
		print \yii\helpers\BaseConsole::renderColoredString("%G Getting provinces %n") . PHP_EOL;
		$this->getProvinces();
		
		print \yii\helpers\BaseConsole::renderColoredString("%G Getting suburbs %n") . PHP_EOL;
		$this->getSuburbs();

		print \yii\helpers\BaseConsole::renderColoredString("%G Getting cities %n") . PHP_EOL;
		$this->getCities();

		print \yii\helpers\BaseConsole::renderColoredString("%G Getting properties %n") . PHP_EOL;
		$this->getProperties();

		print \yii\helpers\BaseConsole::renderColoredString("%G Getting units %n") . PHP_EOL;
		$this->getUnits();

		print \yii\helpers\BaseConsole::renderColoredString("%G Getting building images %n") . PHP_EOL;
		$this->getImages();

		print \yii\helpers\BaseConsole::renderColoredString("%G Getting unit images %n") . PHP_EOL;
		$this->getUnitImages();

		// Takes too long
		//print \yii\helpers\BaseConsole::renderColoredString("%G Getting brokers %n") . PHP_EOL;
		//$this->getBrokers();

		// Takes too long
		//print \yii\helpers\BaseConsole::renderColoredString("%G Getting contacts %n") . PHP_EOL;
		//$this->getContacts();
	}

	/**
	 * Get all categories
	 * 
	 * @return Int total
	 */
	private function getCategories()
	{
		// Call Gmaven
		$r = $this->call->Post('data/default/property/aggregates', [
			'size'       => -1,
			'aggregates' => [
				'basic.primaryCategory' => 1
			]
		]);

		// Gather data
		$data = array_filter($r->aggregates->{'basic.primaryCategory$$distinct'});
		$t = count($data);

		// Insert
		if($t){
			$this->db->query("TRUNCATE TABLE `#gmaven_categories`")->exec();
			foreach($data as $i => $category){
				$this->db->query("INSERT INTO `#gmaven_categories` (`category`, `updated_at`) VALUES('".addslashes($category)."', ".$this->time.")")->exec();
				print \yii\helpers\BaseConsole::renderColoredString("%g ✔ ".$category."%n") . PHP_EOL;
			}
		}

		// Return total
		return $t;
	}

	/**
	 * Get all provinces
	 * 
	 * @return Int total
	 */
	private function getProvinces()
	{
		// Call Gmaven
		$r = $this->call->post('data/default/property/aggregates', [
			'size'       => -1,
			'aggregates' => [
				'basic.province' => 1
			]
		]);

		// Gather data
		$data = array_filter($r->aggregates->{'basic.province$$distinct'});
		$t = count($data);

		// Insert
		if($t){
			$this->db->query("TRUNCATE TABLE `#gmaven_provinces`")->exec();
			foreach($data AS $province){
				$this->db->query("INSERT INTO `#gmaven_provinces` (`province`, `updated_at`) VALUES('".addslashes($province)."', ".$this->time.")")->exec();
				print \yii\helpers\BaseConsole::renderColoredString("%g ✔ ".$province."%n") . PHP_EOL;
			}
		}

		// Return total
		return $t;
	}

	/**
	 * Get all suburbs
	 *
	 * @return Int Total
	 */
	private function getSuburbs()
	{
		// Clear out table
		$this->db->query("TRUNCATE TABLE `#gmaven_suburbs`")->exec();

		// We need a list of property ids
		$provinces = $this->db->query("SELECT `id`, `province` FROM `#gmaven_provinces`")->get();

		// Count
		$tt = [];

		// Call Gmaven on each province
		foreach($provinces as $p){

			// Call
			$r = $this->call->post('data/default/property/aggregates', [
				'size'  => -1,
				'query' => [
					'basic.province' => [
						'$in' => $p['province']
					]
				],
				'aggregates' => [
					'basic.suburb' => 1
				]
			]);

			// Gather data
			$data = array_filter($r->aggregates->{'basic.suburb$$distinct'});
			$t = count($data);
			$tt[] = $t;

			if($t){

				// Insert
				foreach($data AS $suburb){
					$this->db->query("
						INSERT INTO `#gmaven_suburbs`
						(`suburb`, `province_id`, `updated_at`)
						VALUES(
							'".addslashes($suburb)."',
							".$p['id'].",
							".$this->time."
						)
					")->exec();

					// Info
					print \yii\helpers\BaseConsole::renderColoredString("%g ✔ ".$suburb."%n") . PHP_EOL;
				}
			}
		}

		// Return totals
		if(count($tt)){
			return array_sum($tt);
		}
		else{
			return 0;
		}
	}

	/**
	 * Get all cities
	 *
	 * @return Int Total
	 */
	private function getCities()
	{
		// Call Gmaven
		$r = $this->call->post('data/default/property/aggregates', [
			'size'       => -1,
			'aggregates' => [
				'basic.city' => 1
			]
		]);

		// Gather data
		$data = array_filter($r->aggregates->{'basic.city$$distinct'});
		$t = count($data);

		// Insert
		if($t){
			$this->db->query("TRUNCATE TABLE `#gmaven_cities`")->exec();
			foreach($data AS $city){
				$this->db->query("INSERT INTO `#gmaven_cities` (`city`, `updated_at`) VALUES('".addslashes($city)."', ".$this->time.")")->exec();
				print \yii\helpers\BaseConsole::renderColoredString("%g ✔ ".$city."%n") . PHP_EOL;
			}
		}

		// Return total
		return $t;
	}


	/**
	 * Get all properties and re-insert them into the database
	 *
	 * Total results may differ from what you see in CRE, Gmaven apply filters to the API to ensure that no obviously "incomplete"
	 * properties are displayed (e.g. ones that're missing critical location or price information) which would account for this
	 * difference.
	 *
	 * @param Date of when to start syncing
	 *
	 * @return Int Total
	 */
	private function getProperties($fromWhen = false)
	{
		// Vars
		$query = [];
		$from  = [];

		// Ignore archived
		$query = [
			'isArchived' => [
				"\$in" => ["\$null", "false"]
			],
			//'id' => ["\$in" => ['15871ad8-b8b4-4e64-b8e2-a8484d76f299']]
		];

		// Partial or full sync
		if($fromWhen){
			$from = [
				"_updated" => ["\$gte" => $fromWhen]
			];
		}

		// Call Gmaven to get total properties including archived ones
		$r = $this->call->post('data/default/property/search', [
			'sourceFields' => ['id'],
			'query'        => $query + $from,
			'page'         => ['number' => 1, 'size' => 1]
		]);

		// Find total
		$t = $r->md->totalResults;

		// Only continue if there is work to be done
		if($t == 0){
			return;
		}

		// Now pull everything!
		$r = $this->call->post('data/default/property/search', [
			'sourceFields' => [
				'id',
				'_updated',
				'basic.name',
				'basic.province',
				'basic.suburb',
				'basic.city',
				'basic.displayAddress',
				'basic.primaryCategory',
				'basic.marketingBlurb',
				'basic.forSale',
				'basic.gla',
				'basic.customReferenceId',
				'office.amenities._key',
				'office.amenities.exists',
				'geo.lat',
				'geo.lon',
				'vacancy.weightedAskingRental',
				'sales.askingPrice',
				'sales.valueM2'
			],
			'query' => $query + $from,
			'page'  => ['number' => 1, 'size' => $t]
		]);

		// Clear out existing entries when fetching everything
		if($fromWhen == false){
			$this->db->query("TRUNCATE TABLE `#gmaven_properties`")->exec();
			$this->db->query("TRUNCATE TABLE `#gmaven_property_details`")->exec();
		}

		// Loop over results
		foreach($r->list as $i => $p){

			// Try find the entry
			if($fromWhen){
				if($exists = $this->db->query("SELECT `id` FROM `#gmaven_property_details` WHERE `gmv_id` = '".addslashes($p->id)."'")->get()){

					// Find pid
					$property = $this->db->query("SELECT `id` FROM `#gmaven_properties` WHERE `did` = ".$exists[0]['id'])->get();
					$pid = $property[0]['id'];
					$did = $exists[0]['id'];

					// Delete property, property.details & property.units
					$r = "
						BEGIN;
						DELETE FROM `#gmaven_properties` WHERE id = ".$pid.";
						DELETE FROM `#gmaven_property_details` WHERE did = ".$did.";
						DELETE FROM `#gmaven_units` WHERE pid = ".$pid.";
						COMMIT;
					";

					$this->db->query($r)->exec();
				}
			}

			// Check for other private stock flags
			if(isset($p->basic->sales->privateStock) and !empty($p->basic->sales->privateStock)){
				switch($p->basic->sales->privateStock){
					case 'true' :
					case 1      :
					case 'yes'  :
					case 'Yes'  :
					case 'YES'  :
					continue 2;
				}
			}

			// Find province, city, suburb and category id
			if(isset($p->basic->primaryCategory) and !empty($p->basic->primaryCategory)){
				$catId = $this->db->query("SELECT `id` FROM `#gmaven_categories` WHERE `category` = '".(addslashes($p->basic->primaryCategory))."'")->get_one('id');
			}else{
				continue;
			}
			if(isset($p->basic->province) and !empty($p->basic->province)){
				$pid   = $this->db->query("SELECT `id` FROM `#gmaven_provinces` WHERE `province` = '".(addslashes($p->basic->province))."'")->get_one('id');
			}
			else{
				continue;
			}
			if(isset($p->basic->city) and !empty($p->basic->city)){
				$cid   = $this->db->query("SELECT `id` FROM `#gmaven_cities` WHERE `city` = '".(addslashes($p->basic->city))."'")->get_one('id');
			}else{
				continue;
			}
			if(isset($p->basic->suburb) and !empty($p->basic->suburb)){
				$sid   = $this->db->query("SELECT `id` FROM `#gmaven_suburbs` WHERE `suburb` = '".(addslashes($p->basic->suburb))."'")->get_one('id');
			}
			else{
				continue;
			}

			// Check geo points
			if( ! isset($p->geo->lon) or empty($p->geo->lon)){
				continue;
			}
			if( ! isset($p->geo->lat) or empty($p->geo->lat)){
				continue;
			}

			$errors = [];
			try{

				// Insert data
				$q = "
				
				INSERT INTO `#gmaven_property_details`
				(`gmv_id`, `name`, `customReferenceId`, `displayAddress`, `marketingBlurb`)
				VALUES (
				'".addslashes($p->id)."',
				".((isset($p->basic->name) and !empty($p->basic->name))                            ? "'".addslashes($p->basic->name)."'"              : 'NULL').",
				".((isset($p->basic->customReferenceId) and !empty($p->basic->customReferenceId))  ? "'".addslashes($p->basic->customReferenceId)."'" : 'NULL').",
				".((isset($p->basic->displayAddress) and !empty($p->basic->displayAddress))        ? "'".addslashes($p->basic->displayAddress)."'"    : 'NULL').",
				".((isset($p->basic->marketingBlurb) and !empty($p->basic->marketingBlurb))        ? "'".addslashes($p->basic->marketingBlurb)."'"    : 'NULL')."
				);
				INSERT INTO `#gmaven_properties`
				(`did`, `lon`, `lat`, `gla`, `currentVacantArea`, `weightedAskingRental`, `for_sale`, `asking_price`, `category_id`, `province_id`, `city_id`, `suburb_id` ,`updated_at`, `gmv_updated`)
				VALUES (
				LAST_INSERT_ID(),
				".$p->geo->lon.",
				".$p->geo->lat.",
				".((isset($p->basic->gla)                    and !empty($p->basic->gla))                    ? $p->basic->gla                           : 0).",
				".((isset($p->vacancy->currentVacantArea)    and !empty($p->vacancy->currentVacantArea))    ? round($p->vacancy->currentVacantArea)    : 0).",
				".((isset($p->vacancy->weightedAskingRental) and !empty($p->vacancy->weightedAskingRental) and is_int($p->vacancy->weightedAskingRental)) ? round($p->vacancy->weightedAskingRental) : 0).",
				".((isset($p->basic->forSale)                and !empty($p->basic->forSale))                ? $p->basic->forSale                       : 0).",
				".((isset($p->sales->askingPrice)            and !empty($p->sales->askingPrice))            ? $p->sales->askingPrice                   : 0).",
				".$catId.",
				".$pid.",
				".$cid.",
				".$sid.",
				".$this->time.",
				".(isset($p->updated) ? round($p->_updated) : 0)."
				);
				";

				// Insert
				$this->db->query($q)->exec();

				// Info
				print \yii\helpers\BaseConsole::renderColoredString("%g ✔ ".$p->basic->name."%n") . PHP_EOL;
			}
			catch(\Exception $e){
				//$errors[] = $e->getMessage();
			}
		}

		// Done
		return $t;
	}

	/**	
	 * 
	 */
	public function getUnits($fromWhen = false)
	{
		// Fire up database and get properties
		$r = $this->db->query("SELECT `gmv_id` FROM `#gmaven_property_details`")->get();
		//$r = $db->query("SELECT `gmv_id` FROM `#gmaven_property_details` WHERE `gmv_id` = '5b83ecbf-5c1b-438b-bfd9-99394d5b1774'")->get();

		// Clear out old records
		if($fromWhen == false){
			$this->db->query("TRUNCATE TABLE `#gmaven_units`");
			$this->db->exec();
		}

		// Do not max out Gmaven
		$sets = array_chunk($r, ceil(count($r) / 3));

		// Vars
		$query = [];
		$from = [];
		$rT = 0;

		// We have to do this one by one because Gmaven currently maxes out
		foreach($sets as $k => $arr){

			// Build array of properties
			$gmvIds = [];
			foreach($arr as $v){
				$gmvIds[] = $v['gmv_id'];
			}

			// Query
			$query = [
				'propertyId' => [
					"\$in" => $gmvIds
				]
			];

			// Call Gmaven to get total properties
			$r = $this->call->post('data/custom/propertyUnit/search', [
				'sourceFields' => ['id'],
				'query'        => $query + $from,
				'page'         => ['number' => 1, 'size' => 1]
			]);
			$t = $r->md->totalResults;

			// Now pull everything
			$r = $this->call->post('data/custom/propertyUnit/search', [
				'sourceFields' => [
					'id',
					'_updated',
					'propertyId',
					'unitDetails.unitId',
					'unitDetails.customReferenceId',
					'unitDetails.gla',
					'unitDetails.primaryCategory',
					'vacancy.marketing.availableType',
					'vacancy.marketing.availableFrom',
					'vacancy.marketing.noticePeriod',
					'vacancy.unitDetails.gmr',
					'vacancy.unitDetails.netAskingRental',
					'vacancy.sales.marketingHeading',
					'vacancy.sales.description',
					'vacancy.unitManagement.status',
					'vacancy.leasingStatus',
					'sales.salesStatus'
				],
				'query' => $query + $from,
				'page'  => ['number' => 1, 'size' => $t]
			]);

			// Find total
			$rT = $rT + $t;

			// Loop over results
			foreach($r->list as $i => $u){

				// Defaults
				$pid = 0;
				$catId = 0;

				// Go
				if(isset($u->propertyId) and !empty($u->propertyId) ){

					// Ignore these
					if(empty($u->vacancy->leasingStatus) or $u->vacancy->leasingStatus == ''){continue;}

					// Find Property id
					if(isset($u->propertyId) and $propertyId = addslashes($u->propertyId)){
						
						// Build query
						$q = '
							SELECT P.`id` FROM `#gmaven_property_details` D
							LEFT JOIN `#gmaven_properties` P ON P.`did` = D.`id`
							WHERE D.`gmv_id` = "'.$propertyId.'"';

						// Execute query
						$pid = $this->db->query($q)->get_one('id');

						if(empty($pid)){
							$pid = 0;
						}
					}

					// Find category id
					if(isset($u->unitDetails->primaryCategory) and $catgoryId = addslashes($u->unitDetails->primaryCategory)){
						$catId = $this->db->query("SELECT `id` FROM `#gmaven_categories` WHERE `category` = '".$catgoryId."'")->get_one('id');
					}

					// Check for existing entry
					if($eId = $this->db->query("SELECT `id` FROM `#gmaven_units` WHERE `gmv_id` = '".$u->id."'")->get_one('id')){
						$this->db->query("DELETE FROM `#gmaven_units` WHERE `id` = ".$eId)->exec();
					}

					// Insert data
					$q = "
					INSERT INTO `#gmaven_units`
					(
					 `pid`,
					 `category_id`,
					 `gla`,
					 `gmr`,
					 `netAskingRental`,
					 `availableFrom`,
					 `propertyId`,
					 `gmv_id`,
					 `unitId`,
					 `customReferenceId`,
					 `availableType`,
					 `marketingHeading`,
					 `description`,
					 `vacancy`,
					 `sales`,
					 `updated_at`,
					 `gmv_updated`
					)
					VALUES (
					".$pid.",
					".$catId.",
					".((isset($u->unitDetails->gla) and is_numeric($u->unitDetails->gla))                                           ? $u->unitDetails->gla : 0).",
					".((isset($u->vacancy->unitDetails->gmr) and is_numeric($u->vacancy->unitDetails->gmr))                         ? $u->vacancy->unitDetails->gmr : 0).",
					".((isset($u->vacancy->unitDetails->netAskingRental) and is_numeric($u->vacancy->unitDetails->netAskingRental)) ? $u->vacancy->unitDetails->netAskingRental : 0).",
					".((isset($u->vacancy->marketing->availableFrom) and is_numeric($u->vacancy->marketing->availableFrom))         ? round($u->vacancy->marketing->availableFrom) : 0).",
					'".$propertyId."',
					'".addslashes($u->id)."',
					".((isset($u->unitDetails->unitId) and !empty($u->unitDetails->unitId))                             ? "'".addslashes($u->unitDetails->unitId)."'"               : 'NULL').",
					".((isset($u->unitDetails->customReferenceId) and !empty($u->unitDetails->customReferenceId))       ? "'".addslashes($u->unitDetails->customReferenceId)."'"    : 'NULL').",
					".((isset($u->vacancy->marketing->availableType) and !empty($u->vacancy->marketing->availableType)) ? "'".addslashes($u->vacancy->marketing->availableType)."'" : 'NULL').",
					".((isset($u->vacancy->sales->marketingHeading) and !empty($u->vacancy->sales->marketingHeading))   ? "'".addslashes($u->vacancy->sales->marketingHeading)."'"  : 'NULL').",
					".((isset($u->vacancy->sales->description) and !empty($u->vacancy->sales->description))             ? "'".addslashes($u->vacancy->sales->description)."'"       : 'NULL').",
					".((isset($u->vacancy->leasingStatus) and !empty($u->vacancy->leasingStatus))                       ? "'".addslashes($u->vacancy->leasingStatus)."'"            : 'NULL').",
					".((isset($u->sales->salesStatus) and !empty($u->sales->salesStatus))                               ? "'".addslashes($u->sales->salesStatus)."'"                : 'NULL').",
					".$this->time.",
					".(isset($u->_updated) ? round($u->_updated) : 0)."
					);
					";

					// Insert
					$this->db->query($q)->exec();

					// Info
					print \yii\helpers\BaseConsole::renderColoredString("%g ✔ ".$u->unitDetails->unitId."%n") . PHP_EOL;
				}
			}
		}

		// Return totals
		return $rT;
	}

	/**
	 * Match images to properties
	 */
	public function getImages()
	{
		// Call Gmaven to get total properties
		$r = $this->call->post('data/content/entity/property/search', [
			'contentCategory' => 'Image',
		]);
		$t = count($r->list);

		// Only continue if there is work to be done
		if($t == 0){
			return;
		}

		// Clear out existing entries
		$this->db->query("TRUNCATE TABLE `#gmaven_building_images`")->exec();

		// Loop over results
		foreach($r->list AS $img){

			// Insert data
			$q = "
			INSERT INTO `#gmaven_building_images`
			(`entityDomainKey`, `contentDomainKey`, `rating`, `updated_at`)
			VALUES (
			 '".$img->entityDomainKey."',
			 '".$img->contentDomainKey."',
			 ".( isset($img->metadata->Rating) ? $img->metadata->Rating : 0 ).",
			 ".$this->time."
			);
			";

			// Insert
			$this->db->query($q)->exec();

			// Info
			print \yii\helpers\BaseConsole::renderColoredString("%g ✔ ".$img->contentDomainKey."%n") . PHP_EOL;
		}

		// Done
		return $t;
	}

	/**
	 * Sync unit images
	 *
	 * @param
	 * @return
	 */
	public function getUnitImages()
	{
		// Call Gmaven to get total units
		$r = $this->call->post('data/content/entity/propertyUnit/search', [
			'contentCategory' => 'Image',
		]);
		$t = count($r->list);

		// Only continue if there is work to be done
		if($t == 0){
			return;
		}

		// Clear out existing entries
		$this->db->query("TRUNCATE TABLE `#gmaven_unit_images`")->exec();

		// Loop over results
		foreach($r->list AS $img){

			// Find propertyUnit entityDomainKey
			if( ! empty($img->entities)){
				foreach($img->entities as $e){
					if($e->entityName == 'propertyUnit'){

						// Insert data
						$q = "
						INSERT INTO `#gmaven_unit_images`
						(`entityDomainKey`, `contentDomainKey`, `rating`, `updated_at`, `gmv_updated`)
						VALUES (
						 '".$e->entityDomainKey."',
						 '".$img->contentDomainKey."',
						 ".( isset($img->metadata->Rating) ? $img->metadata->Rating : 0 ).",
						 ".$this->time.",
						 ".( isset($img->updated) ? round($img->updated) : 0)."
						);
						";

						// Insert
						$this->db->query($q)->exec();

						// Info
						print \yii\helpers\BaseConsole::renderColoredString("%g ✔ ".$img->contentDomainKey."%n") . PHP_EOL;
					}
				}
			}
		}

		// Done
		return $t;
	}

	/**
	 * Match brokers to properties
	 */
	public function getBrokers()
	{
		// Gather team
		$team = $this->call->get('cre/user/team/current/user');

		// We need a list of property ids
		$list = $this->db->query(
			"
			SELECT D.`gmv_id`, P.`id`  FROM `#gmaven_property_details`D
			LEFT JOIN `#gmaven_properties` P ON P.`did` = D.`id`
			"
		)->get();
		$t = count($list);

		if($t == 0){
			return;
		}

		// Clear out existing entries
		$this->db->query("TRUNCATE TABLE `#gmaven_brokers`")->exec();
		$this->db->query("TRUNCATE TABLE `#gmaven_brokers_to_properties`")->exec();

		// Loop over each property
		foreach($list AS $p){

			// Fetch info
			$r = $this->call->get('data/entity/property/'.$p['gmv_id'].'/responsibility');

			// Loop over everything and mach up
			if(isset($r->list) and count($r->list)){
				foreach($r->list as $l){
					if(isset($l->userDomainKey) and ! empty($l->userDomainKey)){
						foreach($team->list as $member){
							if(isset($member->_id)){
								if($l->userDomainKey == $member->_id){
									$this->brokerInset($member, $p, $l->responsibility);
								}
							}
						}
					}
				}
			}

			print \yii\helpers\BaseConsole::renderColoredString("%g.%n");
		}

		// Done
		return $t;
	}

	/**
	 * Inserts a new broker and matches new or existing brokers to a property
	 *
	 * @param Object The member of the team
	 * @param Array  Property array to assign the broker to
	 * @param String Responsibility of the broker
	 *
	 * @return void
	 */
	private function brokerInset($member, $p, $r)
	{
		// Check  if the broker exists
		if($this->db->query("SELECT * FROM `#gmaven_brokers` WHERE `gmv_id` = '".$member->_id."'")->get_one('id', false) == false){

			// Inset new broker
			$q = "
			INSERT INTO `#gmaven_brokers`
			(`gmv_id`, `name`, `resp`, `tel`, `cell`, `email`, `updated_at`)
			VALUES (
			 '".$member->_id."',
			 '".$member->name."',
			 '".$r."',
			 '".(!empty($member->tel)   ? $member->tel   : '')."',
			 '".(!empty($member->cell)  ? $member->cell  : '')."',
			 '".(!empty($member->email) ? $member->email : '')."',
			 ".$this->time."
			);
			";

			try{
				$this->db->query($q)->exec();
				print \yii\helpers\BaseConsole::renderColoredString("%g ✔ ".$member->name."%n") . PHP_EOL;
			}
			catch(\Exception $e){
				
			}
		}

		// Get property id
		$pid = $p['id'];

		// Get broker id
		$bid = $this->db->query("SELECT `id` FROM `#gmaven_brokers` WHERE `gmv_id` = '".$member->_id."'")->get_one('id');

		// Match up
		if($bid and $pid){
			$check = $this->db->query("SELECT COUNT(*) AS 'T' FROM `#gmaven_brokers_to_properties` WHERE `pid` = ".$pid." AND bid = ".$bid)->get_one('T');
			if($check == 0){
				$this->db->query("INSERT INTO `#gmaven_brokers_to_properties` (`pid`, `bid`) VALUES (".$pid.", ".$bid.")")->exec();
			}
		}
	}

	/**
	 *
	 */
	public function getContacts()
	{
		// Total to return
		$t = 0;

		// We need a list of property ids
		$list = $this->db->query(
			"
			SELECT D.`gmv_id`, P.`id`  FROM `#gmaven_property_details`D
			LEFT JOIN `#gmaven_properties` P ON P.`did` = D.`id`
			"
		)->get();

		// Clear out existing entries
		$this->db->query("TRUNCATE TABLE `#gmaven_contacts`")->exec();
		$this->db->query("TRUNCATE TABLE `#gmaven_contacts_to_properties`")->exec();

		// Buid up an array of property ids and a string to query with
		$ids = [];
		foreach($list as $property){

			// Find contacts listed on a property
			$r = $this->call->post('data/default/property/search', [
				'sourceFields' => [
					'contacts._id',
				],
				'query' => [
					'id' => [
						"\$eq" => $property['gmv_id']
					]
				],
				'page'  => ['number' => 1, 'size' => 1]
			]);

			// Check for results and exit if nothing
			if(count($r->list) > 0){

				// Reset array
				$arr = [];

				// Pull out all the contact ids
				foreach($r->list as $objArr){
					if(isset($objArr->contacts)){
						foreach($objArr->contacts as $obj){
							$arr[] = $obj->_id;
						}
					}
				}

				// If we have array data to work with
				if(count($arr)){

					// Call Gmaven to get total properties
					$result = $this->call->post('data/default/contact/search', [
						'sourceFields' => ['id', 'name', 'tel', 'cell', 'email'],
						'query' => [
							"id" => [
								"\$in" => $arr
							]
						]
					]);

					if($result->md->totalResults > 0){
						foreach($result->list as $contact){
							if($this->contactInsert($contact, $property)){
								$t++;
							}
						}
					}
				}
			}
		}

		// Done
		return $t;
	}

	/**
	 * Inserts a new contact and matches new or existing contacts to a property
	 *
	 * @param Object The contact object
	 * @param Array  Property array to assign the broker to
	 */
	private function contactInsert($contact, $property)
	{
		// Check  if the broker exists
		if($this->db->query("SELECT * FROM `#gmaven_contacts` WHERE `gmv_id` = '".$contact->id."'")->get_one('id', false) == false){

			// Inset new broker
			$q = "
			INSERT INTO `#gmaven_contacts`
			(`gmv_id`, `name`, `tel`, `cell`, `email`, `updated_at`)
			VALUES (
			 '".$contact->id."',
			 '".(!empty(addslashes($contact->name))  ? $contact->name  : '')."',
			 '".(!empty($contact->tel)   ? $contact->tel   : '')."',
			 '".(!empty($contact->cell)  ? $contact->cell  : '')."',
			 '".(!empty($contact->email) ? $contact->email : '')."',
			 ".$this->time."
			);
			";

			try{
				$this->db->query($q)->exec();
				print \yii\helpers\BaseConsole::renderColoredString("%g ✔ ".$contact->name."%n") . PHP_EOL;
			}
			catch(\Exception  $e){
				
			}
		}

		// Get property id
		$pid = $property['id'];

		// Get contact id
		$cid = $this->db->query("SELECT `id` FROM `#gmaven_contacts` WHERE `gmv_id` = '".$contact->id."'")->get_one('id');

		// Match up
		if($cid and $pid){
			$check = $this->db->query("SELECT COUNT(*) AS 'T' FROM `#gmaven_contacts_to_properties` WHERE `pid` = ".$pid." AND cid = ".$cid)->get_one('T');
			if($check == 0){
				$this->db->query("INSERT INTO `#gmaven_contacts_to_properties` (`pid`, `cid`) VALUES (".$pid.", ".$cid.")")->exec();
				return true;
			}
		}

		// No match
		return false;
	}
}