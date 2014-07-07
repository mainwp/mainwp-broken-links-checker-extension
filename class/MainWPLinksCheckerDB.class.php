<?php
class MainWPLinksCheckerDB
{    
    private $mainwp_linkschecker_db_version = "1.2";        
    private $table_prefix;
    
    //Singleton
    private static $instance = null;
    
    static function Instance()
    {
        if (MainWPLinksCheckerDB::$instance == null) {
            MainWPLinksCheckerDB::$instance = new MainWPLinksCheckerDB();
        }
        return MainWPLinksCheckerDB::$instance;
    }
    //Constructor
    function __construct()
    {
        global $wpdb;
        $this->table_prefix = $wpdb->prefix . "mainwp_"; 
    }
	
    function tableName($suffix)
    {
        return $this->table_prefix . $suffix;
    }
		
    //Support old & new versions of wordpress (3.9+)
    public static function use_mysqli()
    {
        /** @var $wpdb wpdb */
        if (!function_exists( 'mysqli_connect' ) ) return false;

        global $wpdb;
        return ($wpdb->dbh instanceof mysqli);
    }
	
    //Installs new DB
    function install()
    {
        global $wpdb;
        $currentVersion = get_site_option('mainwp_linkschecker_db_version');        
        if ($currentVersion == $this->mainwp_linkschecker_db_version) return;    
        
        $charset_collate = $wpdb->get_charset_collate();        
        $sql = array();
        
        $tbl = 'CREATE TABLE `' . $this->tableName('linkschecker') . '` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`site_id` text NOT NULL,
`link_info` text NOT NULL DEFAULT "",
`active` tinyint(1) NOT NULL DEFAULT 1,
`hide_plugin` tinyint(1) NOT NULL DEFAULT 0';
        if ($currentVersion == '')
                    $tbl .= ',
PRIMARY KEY  (`id`)  ';
        $tbl .= ') ' . $charset_collate;
        $sql[] = $tbl;
        
        error_reporting(0); // make sure to disable any error output
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        foreach ($sql as $query)
        {
            dbDelta($query);
        }  
//        global $wpdb;
//        echo $wpdb->last_error;
//        exit();         
        update_option('mainwp_linkschecker_db_version', $this->mainwp_linkschecker_db_version);
    } 
   
    public function updateLinksChecker($setting)
    {
         /** @var $wpdb wpdb */
        global $wpdb;        
        $id = isset($setting['id']) ? $setting['id'] : 0;		        
        $site_id = isset($setting['site_id']) ? $setting['site_id'] : "";
        //print_r($setting);
        if (!empty($id)) {
            if ($wpdb->update($this->tableName('linkschecker'), $setting, array('id' => intval($id)))) { 				                
                return $this->getLinksCheckerBy('id', $id);      			                
            }
            //echo $wpdb->last_error;
        } else if (!empty($site_id)) {
            $current = $this->getLinksCheckerBy('site_id', $site_id);              
            if (!empty($current)) {
                if ($wpdb->update($this->tableName('linkschecker'), $setting, array('site_id' => $site_id))) 				
                    return $this->getLinksCheckerBy('site_id', $site_id);      			
            } else {
               if($wpdb->insert($this->tableName('linkschecker'), $setting))                   
                    return $this->getLinksCheckerBy('id', $wpdb->insert_id);                  
            }
        } else {
            unset($setting['id']);
            if($wpdb->insert($this->tableName('linkschecker'), $setting)) {                   
                return $this->getLinksCheckerBy('id', $wpdb->insert_id);  
            }            
        }        		
        return false;
    }
    
    // not used
    public function deleteLinksChecker($by = 'id', $value = null) {
        global $wpdb;        
        if (empty($by))
            return null;        
        $sql = "";
        if ($by == 'id') {
            $sql = $wpdb->prepare("DELETE FROM " . $this->tableName('linkschecker') . " WHERE `id`=%d ", $value);
        } else if ($by == 'site_id') {
            $sql = $wpdb->prepare("DELETE FROM " . $this->tableName('linkschecker') . " WHERE `site_id` = %d ", $value);
        } else if ($by == 'all') {          
            $wpdb->query("TRUNCATE TABLE " . $this->tableName('linkschecker'));
        }  
        
        if (!empty($sql))
            $wpdb->query($sql);  
        
        return true;
    }
    
    public function getLinksCheckerBy($by = 'id', $value = null, $active = null) {
        global $wpdb;        
        if (empty($by))
            return null;
        $where = "";
        if ($active !== null)
            $where = " AND active = " . $active;
        
        $sql = "";
        if ($by == 'id') {
            $sql = $wpdb->prepare("SELECT * FROM " . $this->tableName('linkschecker') . " WHERE `id`=%d " , $value);
        } else if ($by == 'site_id') {
            $sql = $wpdb->prepare("SELECT * FROM " . $this->tableName('linkschecker') . " WHERE `site_id` = %d ", $value);
        } else if ($by == 'all') {
            $sql = "SELECT * FROM " . $this->tableName('linkschecker') . " WHERE 1 " . $where;           
            return $wpdb->get_results($sql);
        }  
        $setting = null;
        if (!empty($sql))
            $setting = $wpdb->get_row($sql);                
        return $setting;
    }
    
    protected function escape($data)
    {
        /** @var $wpdb wpdb */
        global $wpdb;
        if (function_exists('esc_sql')) return esc_sql($data);
        else return $wpdb->escape($data);
    }    
    
    public function query($sql)
    {
        if ($sql == null) return false;
        /** @var $wpdb wpdb */
        global $wpdb;
        $result = @self::_query($sql, $wpdb->dbh);

        if (!$result || (@self::num_rows($result) == 0)) return false;
        return $result;
    }	
	
    public static function _query($query, $link)
    {
        if (self::use_mysqli())
        {
            return mysqli_query($link, $query);
        }
        else
        {
            return mysql_query($query, $link);
        }
    }

    public static function fetch_object($result)
    {
        if (self::use_mysqli())
        {
            return mysqli_fetch_object($result);
        }
        else
        {
            return mysql_fetch_object($result);
        }
    }

    public static function free_result($result)
    {
        if (self::use_mysqli())
        {
            return mysqli_free_result($result);
        }
        else
        {
            return mysql_free_result($result);
        }
    }

    public static function data_seek($result, $offset)
    {
        if (self::use_mysqli())
        {
            return mysqli_data_seek($result, $offset);
        }
        else
        {
            return mysql_data_seek($result, $offset);
        }
    }

    public static function fetch_array($result, $result_type = null)
    {
        if (self::use_mysqli())
        {
            return mysqli_fetch_array($result, ($result_type == null ? MYSQLI_BOTH : $result_type));
        }
        else
        {
            return mysql_fetch_array($result, ($result_type == null ? MYSQL_BOTH : $result_type));
        }
    }

    public static function num_rows($result)
    {
        if (self::use_mysqli())
        {
            return mysqli_num_rows($result);
        }
        else
        {
            return mysql_num_rows($result);
        }
    }

    public static function is_result($result)
    {
        if (self::use_mysqli())
        {
            return ($result instanceof mysqli_result);
        }
        else
        {
            return is_resource($result);
        }
    }
	
    public function getResultsResult($sql)
    {
        if ($sql == null) return null;
        /** @var $wpdb wpdb */
        global $wpdb;
        return $wpdb->get_results($sql, OBJECT_K);
    }
}