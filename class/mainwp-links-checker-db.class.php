<?php
class MainWP_Links_Checker_DB
{
	private $mainwp_linkschecker_db_version = '2.5';
	private $table_prefix;
	var $field_format;
	//Singleton
	private static $instance = null;

	static function get_instance() {

		if ( null == MainWP_Links_Checker_DB::$instance ) {
			MainWP_Links_Checker_DB::$instance = new MainWP_Links_Checker_DB();
		}
		return MainWP_Links_Checker_DB::$instance;
	}
	//Constructor
	function __construct() {

		global $wpdb;
		$this->table_prefix = $wpdb->prefix . 'mainwp_';
		
		$this->field_format = array(
			'link_id' => '%d',
			'url' => '%s',
			'first_failure' => 'datetime',
			'last_check' => 'datetime',
			'last_success' => 'datetime',
			'last_check_attempt' => 'datetime',
			'check_count' => '%d',
			'final_url' => '%s',
			'redirect_count' => '%d',
			'log' => '%s',
			'http_code' => '%d',
			'request_duration' => '%F',
			'timeout' => 'bool',
			//'result_hash' => '%s',
			'broken' => 'bool',
			'warning' => 'bool',
			'false_positive' => 'bool',
			'may_recheck' => 'bool',
			'being_checked' => 'bool',
		 	'status_text' => '%s',
		 	'status_code' => '%s',
			'dismissed' => 'bool',
			'extra_info' => '%s',
			'link_text' => '%s',
			'synced' => 'bool'
		);
		
		
	}

	function table_name( $suffix ) {

		return $this->table_prefix . $suffix;
	}

	//Support old & new versions of wordpress (3.9+)
	public static function use_mysqli() {

		/** @var $wpdb wpdb */
		if ( ! function_exists( 'mysqli_connect' ) ) { return false; }

		global $wpdb;
		return ($wpdb->dbh instanceof mysqli);
	}

	//Installs new DB
	function install() {

		global $wpdb;
		$currentVersion = get_site_option( 'mainwp_linkschecker_db_version' );
		if ( $currentVersion == $this->mainwp_linkschecker_db_version ) { return; }

		$charset_collate = $wpdb->get_charset_collate();
		$sql = array();
		
		$tbl = 'CREATE TABLE `' . $this->table_name( 'linkschecker' ) . '` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`site_id` int(11) NOT NULL,
`last_sync` datetime NOT NULL DEFAULT "0000-00-00 00:00:00",
`current_paged` int(4) unsigned NOT NULL DEFAULT "0",
`count_broken` int(4) unsigned NOT NULL DEFAULT "0",
`count_redirects` int(4) unsigned NOT NULL DEFAULT "0",
`count_dismissed` int(4) unsigned NOT NULL DEFAULT "0",
`count_total` int(4) unsigned NOT NULL DEFAULT "0",
`active` tinyint(1) NOT NULL DEFAULT 1,
`hide_plugin` tinyint(1) NOT NULL DEFAULT 0';
		if ( '' == $currentVersion ) {
					$tbl .= ',
PRIMARY KEY  (`id`)  '; }
		$tbl .= ') ' . $charset_collate;
		$sql[] = $tbl;

$not_found_links_table = false;
$rslt = self::query( "SHOW TABLES LIKE '" . $this->table_name( 'linkschecker_links' ) . "'" );
if ( self::num_rows( $rslt ) == 0 ) {
	$not_found_links_table = true;
}
		
$tbl = 'CREATE TABLE `' . $this->table_name( 'linkschecker_links' ) . '` (
		`linkschecker_link_id` int(20) unsigned NOT NULL AUTO_INCREMENT,				
		`link_id` int(20) unsigned NOT NULL,
		`site_id` int(11) NOT NULL,
		`url` text CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
		`first_failure` datetime NOT NULL DEFAULT "0000-00-00 00:00:00",
		`last_check` datetime NOT NULL DEFAULT "0000-00-00 00:00:00",
		`last_success` datetime NOT NULL DEFAULT "0000-00-00 00:00:00",
		`last_check_attempt` datetime NOT NULL DEFAULT "0000-00-00 00:00:00",
		`check_count` int(4) unsigned NOT NULL DEFAULT "0",
		`final_url` text CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
		`redirect_count` smallint(5) unsigned NOT NULL DEFAULT "0",
		`log` text NOT NULL,
		`http_code` smallint(6) NOT NULL DEFAULT "0",
		`status_code` varchar(100) DEFAULT "",
		`status_text` varchar(250) DEFAULT "",
		`request_duration` float NOT NULL DEFAULT "0",
		`timeout` tinyint(1) unsigned NOT NULL DEFAULT "0",
		`broken` tinyint(1) unsigned NOT NULL DEFAULT "0",
		`warning` tinyint(1) unsigned NOT NULL DEFAULT "0",
		`may_recheck` tinyint(1) NOT NULL DEFAULT "1",
		`being_checked` tinyint(1) NOT NULL DEFAULT "0",
		`false_positive` tinyint(1) NOT NULL DEFAULT "0",
		`dismissed` tinyint(1) NOT NULL DEFAULT "0",
		`link_text` text NOT NULL DEFAULT "",
		`synced` tinyint(1) unsigned NOT NULL DEFAULT "1",
		`extra_info` text NOT NULL';

		if ( '' == $currentVersion || $not_found_links_table) {
					$tbl .= ',						
			KEY (`link_id`),
			KEY `url` (`url`(150)),
			KEY `final_url` (`final_url`(150)),
			KEY `http_code` (`http_code`),
			KEY `broken` (`broken`),
			PRIMARY KEY  (`linkschecker_link_id`)  '; 					
		}
		$tbl .= ') ' . $charset_collate;
		$sql[] = $tbl;

		error_reporting( 0 ); // make sure to disable any error output
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		foreach ( $sql as $query ) {
			dbDelta( $query );
		}
		//        global $wpdb;
		//        echo $wpdb->last_error;
		//        exit();
		update_option( 'mainwp_linkschecker_db_version', $this->mainwp_linkschecker_db_version );
	}

	public function update_links_checker( $data ) {

		 /** @var $wpdb wpdb */
		global $wpdb;
		$id = isset( $data['id'] ) ? $data['id'] : 0;
		$site_id = isset( $data['site_id'] ) ? $data['site_id'] : '';
		//print_r($data);
		if ( ! empty( $id ) ) {
			if ( $wpdb->update( $this->table_name( 'linkschecker' ), $data, array( 'id' => intval( $id ) ) ) ) {
				return $this->get_links_checker_by( 'id', $id );
			}
			//echo $wpdb->last_error;
		} else if ( ! empty( $site_id ) ) {
			$current = $this->get_links_checker_by( 'site_id', $site_id );
			if ( ! empty( $current ) ) {
				if ( $wpdb->update( $this->table_name( 'linkschecker' ), $data, array( 'site_id' => $site_id ) ) ) {
					return $this->get_links_checker_by( 'site_id', $site_id ); }
			} else {
				if ( $wpdb->insert( $this->table_name( 'linkschecker' ), $data ) ) {
					return $this->get_links_checker_by( 'id', $wpdb->insert_id ); }
			}
		} else {
			unset( $data['id'] );
			if ( $wpdb->insert( $this->table_name( 'linkschecker' ), $data ) ) {
				return $this->get_links_checker_by( 'id', $wpdb->insert_id );
			}
		}
		return false;
	}

	// not used
	public function delete_links_checker( $by = 'id', $value = null ) {
		global $wpdb;
		
		if ( empty( $by ) ) {
			return false; 			
		}
		
		$delete_links_sql = '';
		$sql = '';
		if ( 'id' == $by ) {
			$sql = $wpdb->prepare( 'DELETE FROM ' . $this->table_name( 'linkschecker' ) . ' WHERE `id`=%d ', $value );
			$delete_links_sql = $wpdb->prepare( 'DELETE FROM ' . $this->table_name( 'linkschecker_links' ) . ' WHERE `id` = %d ', $value );			
		} else if ( 'site_id' == $by ) {
			$sql = $wpdb->prepare( 'DELETE FROM ' . $this->table_name( 'linkschecker' ) . ' WHERE `site_id` = %d ', $value );			
			$delete_links_sql = $wpdb->prepare( 'DELETE FROM ' . $this->table_name( 'linkschecker_links' ) . ' WHERE `site_id` = %d ', $value );			
		} else if ( 'all' == $by ) {
			$wpdb->query( 'TRUNCATE TABLE ' . $this->table_name( 'linkschecker' ) );
			$wpdb->query( 'TRUNCATE TABLE ' . $this->table_name( 'linkschecker_links' ) );
		}

		if ( ! empty( $sql ) ) {
			$wpdb->query( $sql ); 		
			if (!empty($delete_links_sql)) {
				$wpdb->query( $delete_links_sql ); 	
			}				
		}
		return true;
	}

	public function get_links_checker_by( $by = 'id', $value = null, $active = null ) {
		global $wpdb;
				
		$where = '';
		if ( null !== $active ) {
			$where = ' AND active = ' . intval( $active ); 			
		}
				
		$sql = '';
		if ( 'id' == $by ) {
			$sql = $wpdb->prepare( 'SELECT * FROM ' . $this->table_name( 'linkschecker' ) . ' WHERE `id`=%d ' , $value );
		} else if ( 'site_id' == $by ) {
			$sql = $wpdb->prepare( 'SELECT * FROM ' . $this->table_name( 'linkschecker' ) . ' WHERE `site_id` = %d ', $value );
		} else if ( 'all' == $by ) {
			$sql = 'SELECT * FROM ' . $this->table_name( 'linkschecker' ) . ' WHERE 1 ' . $where;		
			$data = $wpdb->get_results( $sql ); 	
			return $data;
		}
		
		$data = array();
		if ( ! empty( $sql ) ) {
			$data = $wpdb->get_row( $sql ); 			
		}
				
		return $data;
	}
			
	public function get_filter_links( $site_ids, $filters = array() , $extra_params = array()) {
		global $wpdb;
				
		$defaults = array(
			'offset' => 0,
			'max_results' => 0,	
			'order_exprs' => array(),
			'count_only' => false
		);
		
		$params = array_merge($defaults, $extra_params);
		
		$filter_id = isset($filters['filter_id']) ? $filters['filter_id'] : '';
		$filter_url = isset($filters['filter_url']) ? $filters['filter_url'] : '';
		
		$where_expr = '';
		if ( !empty( $filter_id ) && 'all' != $filter_id ) {
			if ( 'broken' == $filter_id ) {
				$where_expr .= " AND broken = 1 ";				
			} else if ( 'dismissed' == $filter_id ) {
				$where_expr .= " AND dismissed = 1 ";			
			} else if ( 'redirects' == $filter_id ) {
				$where_expr .= " AND dismissed = 0 AND redirect_count > 0 ";				
			}			
		}		
		
		if (!empty($filter_url)) {
			$where_expr .= " AND url LIKE '%" . $filter_url . "%' ";
		}
		
		if (is_array($site_ids) && count($site_ids) > 0) {
			$where_expr .= " AND site_id IN (" . implode(",", $site_ids) . ") ";				
		}
		
		//Optional sorting
		if ( !empty($params['order_exprs']) ) {
			$order_clause = 'ORDER BY ' . implode(', ', $params['order_exprs']);
		} else {
			$order_clause = '';
		}
		
		if ( $params['count_only'] ){
			//Only get the number of matching links.
			$sql = "
				SELECT COUNT(*)
				FROM (	
					SELECT 0
					
					FROM 
						" . $this->table_name( 'linkschecker_links' ) . " AS links
					
					WHERE 1 = 1
						$where_expr					
				   ) AS foo";
			//echo $sql;
			return $wpdb->get_var($sql);
		}
		
		//Select the required links.
		$sql = "SELECT 
				 links.*
				FROM 
				   " . $this->table_name( 'linkschecker_links' ) . " AS links

				WHERE 1 = 1
				 $where_expr
			   {$order_clause}"; //Note: would be a lot faster without GROUP BY, GROUP BY links.site_id, links.link_id
			   
		//Add the LIMIT clause
		if ( $params['max_results'] || $params['offset'] ){
			$sql .= sprintf("\nLIMIT %d, %d", $params['offset'], $params['max_results']);
		}
		//echo $sql;
		$results = $wpdb->get_results($sql);
			
		if ( empty($results) ){
			return array();
		}
				
		return $results;
	}	
		
	public function update_links_data( $site_id, $links_data ) {
		global $wpdb;
		
		if (!is_array($links_data))
			return false;
		
		$filter_fields = array(
			'link_id',
			'url',
			'being_checked',
			'last_check',
			'last_check_attempt',
			'check_count',
			'http_code',
			'request_duration',
			'timeout',
			'redirect_count',
			'final_url',
			'broken',
			'warning',
			'first_failure',
			'last_success',
			'may_recheck',
			'false_positive',
			//'result_hash',
			'dismissed',
			'status_text',
			'status_code',
			'log',
			'extra_info',
			'link_text',
			'synced'
		);
		
		$where_site = sprintf( "AND site_id = %d", $site_id );
		
		foreach ($links_data as $data) {
			if (!property_exists($data, 'link_id'))
				continue;			

			$values = array();
			foreach($this->field_format as $field => $format){				
				$values[$field] = property_exists($data, $field) ? $data->$field : '';
			}
			$values = $this->to_db_format($values);

			
			$sql = $wpdb->prepare(
				"SELECT link_id FROM " . $this->table_name( 'linkschecker_links' ) . " WHERE link_id = %d {$where_site}",
				$data->link_id
			);
			$existing_id = $wpdb->get_var($sql);
			
			if ( !empty($existing_id) ){
				$set_exprs = array();				
				foreach($values as $name => $value){
					$set_exprs[] = "$name = $value";
				}
				$set_exprs = implode(', ', $set_exprs);

				$sql = sprintf(
					"UPDATE " . $this->table_name( 'linkschecker_links' ) . " SET %s WHERE link_id=%d AND site_id = %d",
					$set_exprs,
					intval($data->link_id),
					$site_id	
				);
				$wpdb->query( $sql ); 	
			} else {
				$values['site_id'] = $site_id;
				//Insert a new row
				$sql = sprintf(
					"INSERT INTO " . $this->table_name( 'linkschecker_links' ) . " ( %s ) VALUES( %s )", 
					implode(', ', array_keys($values)), 
					implode(', ', array_values($values))
				);				
				$wpdb->query( $sql ); 	
			}	
		}
//		
//		$sql = $wpdb->prepare( 'DELETE FROM  ' . $this->table_name( 'linkschecker_links' ) . ' WHERE `site_id` = %d ', $site_id );		
//		$wpdb->query( $sql ); 			
	}	
	
	public function set_start_sync_links( $site_id ) {
		if (empty($site_id))
			return;
		global $wpdb;
		$sql = $wpdb->prepare(
				"UPDATE " . $this->table_name( 'linkschecker_links' ) . " SET synced = 0 WHERE site_id = %d",
				$site_id
			);		
		return $wpdb->query($sql);
	}
	
	public function clean_missing_links( $site_id ) {
		if (empty($site_id))
			return;
		global $wpdb;
		$sql = $wpdb->prepare(
				"DELETE FROM " . $this->table_name( 'linkschecker_links' ) . " WHERE synced = 0 AND site_id = %d",
				$site_id
			);		
		return $wpdb->query($sql);
	}
	
	
	function to_db_format($values){
		global $wpdb; /** @var wpdb $wpdb  */
		
		$dbvalues = array();
		
		foreach($values as $name => $value){
			//Skip fields that don't exist in the blc_links table.
			if ( !isset($this->field_format[$name]) ){
				continue;
			}
			
			$format = $this->field_format[$name];
			
			//Convert native values to a format comprehensible to the DB
			switch($format){
				
				case 'datetime' :
					if ( empty($value) ){
						$value = '0000-00-00 00:00:00';
					} else {
						$value = date('Y-m-d H:i:s', $value);
					}
					$format = '%s';
					break;
					
				case 'bool':
					if ( $value ){
						$value = 1;
					} else {
						$value = 0;
					}
					$format = '%d';
					break;
			}
			
			//Escapize
			$value = $wpdb->prepare($format, $value);
			
			$dbvalues[$name] = $value;
		}
		
		return $dbvalues;		
	}
	

	
	protected function escape( $data ) {

		/** @var $wpdb wpdb */
		global $wpdb;
		if ( function_exists( 'esc_sql' ) ) { return esc_sql( $data ); } else { return $wpdb->escape( $data ); }
	}

	public function query( $sql ) {

		if ( null == $sql ) { return false; }
		/** @var $wpdb wpdb */
		global $wpdb;
		$result = @self::_query( $sql, $wpdb->dbh );

		if ( ! $result || (@self::num_rows( $result ) == 0) ) { return false; }
		return $result;
	}

	public static function _query( $query, $link ) {

		if ( self::use_mysqli() ) {
			return mysqli_query( $link, $query );
		} else {
			return mysql_query( $query, $link );
		}
	}

	public static function fetch_object( $result ) {

		if ( self::use_mysqli() ) {
			return mysqli_fetch_object( $result );
		} else {
			return mysql_fetch_object( $result );
		}
	}

	public static function free_result( $result ) {

		if ( self::use_mysqli() ) {
			return mysqli_free_result( $result );
		} else {
			return mysql_free_result( $result );
		}
	}

	public static function data_seek( $result, $offset ) {

		if ( self::use_mysqli() ) {
			return mysqli_data_seek( $result, $offset );
		} else {
			return mysql_data_seek( $result, $offset );
		}
	}

	public static function fetch_array( $result, $result_type = null ) {

		if ( self::use_mysqli() ) {
			return mysqli_fetch_array( $result, (null == $result_type ? MYSQLI_BOTH : $result_type) );
		} else {
			return mysql_fetch_array( $result, (null == $result_type ? MYSQL_BOTH : $result_type) );
		}
	}

	public static function num_rows( $result ) {

		if ( self::use_mysqli() ) {
			return mysqli_num_rows( $result );
		} else {
			return mysql_num_rows( $result );
		}
	}

	public static function is_result( $result ) {

		if ( self::use_mysqli() ) {
			return ($result instanceof mysqli_result);
		} else {
			return is_resource( $result );
		}
	}

	public function get_results_result( $sql ) {

		if ( null == $sql ) { return null; }
		/** @var $wpdb wpdb */
		global $wpdb;
		return $wpdb->get_results( $sql, OBJECT_K );
	}
}
