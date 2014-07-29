<?php

if (!defined('MWP_BLC_LINK_STATUS_UNKNOWN'))
    define('MWP_BLC_LINK_STATUS_UNKNOWN', 'unknown');
if (!defined('MWP_BLC_LINK_STATUS_OK'))
    define('MWP_BLC_LINK_STATUS_OK', 'ok');
if (!defined('MWP_BLC_LINK_STATUS_INFO'))
    define('MWP_BLC_LINK_STATUS_INFO', 'info');
if (!defined('MWP_BLC_LINK_STATUS_WARNING'))
    define('MWP_BLC_LINK_STATUS_WARNING', 'warning');
if (!defined('MWP_BLC_LINK_STATUS_ERROR'))
    define('MWP_BLC_LINK_STATUS_ERROR', 'error');

class MainWPLinksChecker
{   
    public static $instance = null;
    protected $option_handle = 'mainwp_linkschecker_options';
    protected $option;
      
    var $http_status_codes = array(
        // [Informational 1xx]  
        100=>'Continue',  
        101=>'Switching Protocols',  
        // [Successful 2xx]  
        200=>'OK',  
        201=>'Created',  
        202=>'Accepted',  
        203=>'Non-Authoritative Information',  
        204=>'No Content',  
        205=>'Reset Content',  
        206=>'Partial Content',  
        // [Redirection 3xx]  
        300=>'Multiple Choices',  
        301=>'Moved Permanently',  
        302=>'Found',  
        303=>'See Other',  
        304=>'Not Modified',  
        305=>'Use Proxy',  
        //306=>'(Unused)',  
        307=>'Temporary Redirect',  
        // [Client Error 4xx]  
        400=>'Bad Request',  
        401=>'Unauthorized',  
        402=>'Payment Required',  
        403=>'Forbidden',  
        404=>'Not Found',  
        405=>'Method Not Allowed',  
        406=>'Not Acceptable',  
        407=>'Proxy Authentication Required',  
        408=>'Request Timeout',  
        409=>'Conflict',  
        410=>'Gone',  
        411=>'Length Required', 
        412=>'Precondition Failed',  
        413=>'Request Entity Too Large',  
        414=>'Request-URI Too Long',  
        415=>'Unsupported Media Type',  
        416=>'Requested Range Not Satisfiable',  
        417=>'Expectation Failed',  
        // [Server Error 5xx]  
        500=>'Internal Server Error',  
        501=>'Not Implemented',  
        502=>'Bad Gateway',  
        503=>'Service Unavailable',  
        504=>'Gateway Timeout',  
        505=>'HTTP Version Not Supported',
        509=>'Bandwidth Limit Exceeded',
        510=>'Not Extended',
    );
	
    
    static function Instance()
    {
        if (MainWPLinksChecker::$instance == null) MainWPLinksChecker::$instance = new MainWPLinksChecker();
        return MainWPLinksChecker::$instance;
    }

    public function __construct() {   
        $this->option = get_option($this->option_handle);
        if (isset($_GET['page']) && $_GET['page'] == 'Extensions-Mainwp-Broken-Links-Checker-Extension') {
            if (isset($_GET['trashed_site_id']) && $_GET['trashed_site_id'] ) {
                if (isset($_GET['trashed_post_id']) && $_GET['trashed_post_id'])
                    $this->trashed_container($_GET['trashed_post_id'], $_GET['trashed_site_id'], true);
                else if (isset($_GET['trashed_comment_id']) && $_GET['trashed_comment_id']) {
                    $this->trashed_container($_GET['trashed_comment_id'], $_GET['trashed_site_id']);
                }
            }
        }
        
        if (get_option('mainwp_blc_refresh_total_link_info') == 1) {
            global $mainWPLinksCheckerExtensionActivator;
            $websites = apply_filters('mainwp-getsites', $mainWPLinksCheckerExtensionActivator->getChildFile(), $mainWPLinksCheckerExtensionActivator->getChildKey(), null);
            $all_sites = array();
            if (is_array($websites)) {
                foreach ($websites as $website) {
                    $all_sites[] = $website['id'];
                }
            }
                    
            $link_values = MainWPLinksCheckerDB::Instance()->getLinksData(array('link_info', 'site_id'));
            $total = array();
            if (is_array($link_values)) {
                foreach($link_values as $value) {
                    if (in_array($value->site_id, $all_sites)) {
                        $data = unserialize($value->link_info);                    
                        $total['broken'] += isset($data['broken']) ? intval($data['broken']) : 0;
                        $total['redirects'] += isset($data['redirects']) ? intval($data['redirects']) : 0;
                        $total['dismissed'] += isset($data['dismissed']) ? intval($data['dismissed']) : 0;
                        $total['all'] += isset($data['all']) ? intval($data['all']) : 0;            
                    } else {
                        MainWPLinksCheckerDB::Instance()->deleteLinksChecker('site_id', $value->site_id);
                    }                        
                }
            }
            $this->set_option('total_link_info', $total);            
            update_option('mainwp_blc_refresh_total_link_info', '');            
        }        
        add_filter('mainwp_managesites_column_url', array(&$this, 'managesites_column_url'), 10, 2);                
    }
    
    public function init() {        
        add_action('mainwp-site-synced', array(&$this, 'site_synced'), 10, 1);  
        add_action('mainwp_delete_site', array(&$this, 'mwp_delete_site'), 10, 1);
        add_action( 'wp_ajax_mainwp_broken_links_checker_edit_link', array($this,'ajax_edit_link') );
        add_action( 'wp_ajax_mainwp_broken_links_checker_unlink', array($this,'ajax_unlink') );
        add_action( 'wp_ajax_mainwp_broken_links_checker_dismiss', array($this,'ajax_dismiss') );
        add_action( 'wp_ajax_mainwp_broken_links_checker_undismiss', array($this,'ajax_undismiss') );
        add_action( 'wp_ajax_mainwp_broken_links_checker_discard', array($this,'ajax_discard') );
        add_action( 'wp_ajax_mainwp_broken_links_checker_comment_trash', array($this,'ajax_comment_trash') );
        add_action( 'wp_ajax_mainwp_broken_links_checker_post_trash', array($this,'ajax_post_trash') );        
        add_action( 'wp_ajax_mainwp_linkschecker_settings_loading_sites', array($this,'ajax_settings_loading_sites') );        
        add_action( 'wp_ajax_mainwp_linkschecker_performsavelinkscheckersettings', array($this,'ajax_settings_perform_save') );        
        add_action( 'wp_ajax_mainwp_linkschecker_settings_recheck_loading', array($this,'ajax_settings_recheck_loading') );        
        add_action( 'wp_ajax_mainwp_linkschecker_perform_recheck', array($this,'ajax_perform_recheck') );        
    }
    
    public function admin_init() {
        
    }     
    
    public function site_synced($website) {                
        $this->linkschecker_sync_data($website->id);
    }
    
    public function mwp_delete_site($website) 
    {
        global $wpdb;
        if (isset($_POST['submit'])) {            
            if ($website) {                
                MainWPLinksCheckerDB::Instance()->deleteLinksChecker('site_id', $website->id);             
            }
        }
    }    
    
    public function managesites_column_url($actions, $websiteid) {
        $actions['linkschecker'] = '<a href="admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension&site_id='. $websiteid . '&filter_id=all">' . __("Check Links") . '</a>';
        return $actions; 
    }
    
    public function linkschecker_sync_data($website_id) {
        $linkschecker = MainWPLinksCheckerDB::Instance()->getLinkscheckerBy('site_id', $website_id);   
        $post_data = array('mwp_action' => 'sync_data',
                           'site_id' => $website_id // to mark links of site
                    ); 
        global $mainWPLinksCheckerExtensionActivator;
        $information = apply_filters('mainwp_fetchurlauthed', $mainWPLinksCheckerExtensionActivator->getChildFile(), $mainWPLinksCheckerExtensionActivator->getChildKey(), $website_id, 'links_checker', $post_data);			        
        //print_r($information);
        if (is_array($information) && isset($information['data']) && is_array($information['data'])) {
            $data = $information['data'];             
            $link_info = array();
            $link_info['broken'] = isset($data['broken']) ? intval($data['broken']) : 0;
            $link_info['redirects'] = isset($data['redirects']) ? intval($data['redirects']) : 0;
            $link_info['dismissed'] = isset($data['dismissed']) ? intval($data['dismissed']) : 0;
            $link_info['all'] = isset($data['all']) ? intval($data['all']) : 0;            
            $link_data = isset($data['link_data']) ? $data['link_data'] : ""; 
            $update = array('id' => ($linkschecker && $linkschecker->id) ? $linkschecker->id : 0,                                                                     
                            'site_id' => $website_id,                                
                            'link_info' => serialize($link_info),
                            'link_data' => serialize($link_data),                            
                            'active' => 1
                            );  
            $out = MainWPLinksCheckerDB::Instance()->updateLinkschecker($update);    
            //print_r($out);
            //print_r($update);
            //print_r($information);
        } else {
             MainWPLinksCheckerDB::Instance()->updateLinkschecker(array('site_id' => $website_id, 'active' => 0));     
        }
        update_option('mainwp_blc_refresh_total_link_info', 1);
    }
  
    public function trashed_container($container_id, $site_id, $is_post = false) {
        if (empty($container_id) || empty($site_id))
            return;
        
        $data = MainWPLinksCheckerDB::Instance()->getLinksCheckerBy('site_id', $site_id);
        $link_data = unserialize($data->link_data);
        if (is_array($link_data)) {
            $new_link_data = array();
            foreach($link_data as $link) {                
                if (!$is_post) {
                    if (($link->source_data  && $link->source_data['container_anypost']) || $link->container_id != $container_id) {                                       
                        $new_link_data[] = $link;
                    }
                } else {                    
                    if (!$link->source_data || !$link->source_data['container_anypost'] || $link->container_id != $container_id) {                        
                        $new_link_data[] = $link;
                    }                        
                }                
            }
            $new_link_info = $this->get_new_link_info($new_link_data);            
            $data_update = array('site_id' => $site_id, 
                            'link_data' => serialize($new_link_data),
                            'link_info' => serialize($new_link_info)
                            );           
            MainWPLinksCheckerDB::Instance()->updateLinksChecker($data_update);            
        }    
    }
    
    public static function renderMetabox() {
        if (isset($_GET['page']) && $_GET['page'] == "managesites") {        
            self::childsite_metabox();
        } else {
            self::network_metabox();
        }
    }
    
    function ajax_comment_trash()
    {
        if (!check_ajax_referer('mwp_blc_trash_comment', false, false)){
            die( json_encode( array(
                 'error' => __("You're not allowed to do that!") 
             )));
        }

        MainWPRecentComments::trash();
        die();
    }
    
    function ajax_post_trash()
    {
        if (!check_ajax_referer('mwp_blc_trash_post', false, false)){
            die( json_encode( array(
                 'error' => __("You're not allowed to do that!") 
             )));
        }
         MainWPRecentPosts::trash();
        die();
    }
    
    function ajax_settings_loading_sites()
    {
        $check_threshold = intval($_POST['check_threshold']);
        if ($check_threshold <= 0) {
            die(json_encode(array('error' => __("Every hour value must not be empty."))));
        } else {
            $this->set_option('check_threshold', $check_threshold);
        }
        
        $sites = MainWPLinksCheckerDB::Instance()->getLinksData(array('site_id'));
        $site_ids = array();
        foreach($sites as $site) {
            $site_ids[] = $site->site_id;            
        }
        //print_r($site_ids);
        $dbwebsites = array();
        if (count($site_ids) > 0) {
            global $mainWPLinksCheckerExtensionActivator;
            $dbwebsites = apply_filters('mainwp-getdbsites', $mainWPLinksCheckerExtensionActivator->getChildFile(), $mainWPLinksCheckerExtensionActivator->getChildKey(), $site_ids, array());              
        }
        //print_r($dbwebsites);
        if (is_array($dbwebsites) && count($dbwebsites) > 0) {
            $html = '<input type="hidden" id="mainwp-blc-setting-check_threshold" value="' . $check_threshold . '">';            
            foreach($dbwebsites as $site) {
                $html .= '<div class="mainwpProccessSitesItem" status="queue" siteid="' . $site->id . '"><strong>' . $site->name. '</strong>: <span class="status"></span></div>';
            }
            $html .= '<br><div id="mainwp_blc_setting_ajax_message_zone" class="mainwp_info-box-yellow hidden-field"></div>';            
            die(json_encode(array('success'=> true, 'result' => $html)));
        } else {
            die(json_encode(array('error' => __("There are not sites with the Broken Link Checker plugin activated."))));
        }            
    }
        
    function ajax_settings_recheck_loading()
    {        
        $sites = MainWPLinksCheckerDB::Instance()->getLinksData(array('site_id'));
        $site_ids = array();
        foreach($sites as $site) {
            $site_ids[] = $site->site_id;            
        }
        //print_r($site_ids);
        $dbwebsites = array();
        if (count($site_ids) > 0) {
            global $mainWPLinksCheckerExtensionActivator;
            $dbwebsites = apply_filters('mainwp-getdbsites', $mainWPLinksCheckerExtensionActivator->getChildFile(), $mainWPLinksCheckerExtensionActivator->getChildKey(), $site_ids, array());              
        }
        //print_r($dbwebsites);
        if (is_array($dbwebsites) && count($dbwebsites) > 0) {
            foreach($dbwebsites as $site) {
                $html .= '<div class="mainwpProccessSitesItem" status="queue" siteid="' . $site->id . '"><strong>' . $site->name. '</strong>: <span class="status"></span></div>';
            }
            $html .= '<br><div id="mainwp_blc_setting_ajax_message_zone" class="mainwp_info-box-yellow hidden-field"></div>';            
            die(json_encode(array('success'=> true, 'result' => $html)));
        } else {
            die(json_encode(array('error' => __("There are not sites with the Broken Link Checker plugin activated."))));
        }            
    }
    
    
    public function ajax_settings_perform_save() {
        
        $siteid = $_POST['siteId'];	
        $check_threshold = $_POST['check_threshold'];	        
        
        if (empty($siteid))	
            die(json_encode(array('error' => 'Error: site_id empty'))); 
        
        global $mainWPLinksCheckerExtensionActivator;
        
        $post_data = array('mwp_action' => 'save_settings');
        $post_data['check_threshold'] = $check_threshold;
        $information = apply_filters('mainwp_fetchurlauthed', $mainWPLinksCheckerExtensionActivator->getChildFile(), $mainWPLinksCheckerExtensionActivator->getChildKey(), $siteid, 'links_checker', $post_data);			

        //unset($information['data']);        
        die(json_encode($information)); 		
    }	
      
    
    
    public function ajax_perform_recheck() {
        
        $siteid = $_POST['siteId'];	
        
        if (empty($siteid))	
            die(json_encode(array('error' => 'Error: site_id empty'))); 
        
        global $mainWPLinksCheckerExtensionActivator;
        
        $post_data = array('mwp_action' => 'force_recheck');        
        $information = apply_filters('mainwp_fetchurlauthed', $mainWPLinksCheckerExtensionActivator->getChildFile(), $mainWPLinksCheckerExtensionActivator->getChildKey(), $siteid, 'links_checker', $post_data);			
        //unset($information['data']);        
        die(json_encode($information)); 		
    }	
    
    
    public static function network_metabox() {
        
        $lnks_data = MainWPLinksCheckerDB::Instance()->getLinkscheckerBy('all', null, 1); 
        $broken = $redirects = $dismissed = $all = 0;
        foreach($lnks_data as $value) {
            $links_info = unserialize($value->link_info);
            $broken += isset($links_info['broken']) ? $links_info['broken'] : 0; 
            $redirects += isset($links_info['redirects']) ? $links_info['redirects'] : 0;
            $dismissed += isset($links_info['dismissed']) ? $links_info['dismissed'] : 0;
            $all += isset($links_info['all']) ? $links_info['all'] : 0; 
        }
        
        ?>
        <div id="mainwp_widget_linkschecker_content" style="margin-top: 1em;">
            <div>
                <span class="mwp_lc_count_links broken"><span class="number"><?php echo $broken . "</span><br/>" . __("Broken Links"); ?></span>
                <span class="mwp_lc_count_links redirects"><span class="number"><?php echo $redirects . "</span><br/>" . __("Redirect"); ?></span>
                <span class="mwp_lc_count_links dismissed"><span class="number"><?php echo $dismissed . "</span><br/>" . __("Dismissed"); ?></span>
                <span class="mwp_lc_count_links all"><span class="number"><?php echo $all . "</span><br/>" . __(strtoupper("All")); ?></span>
            </div>
            <br class="clearfix">
            <div class="mwp_network_links_checker_detail"><?php _e("Network Links Checker"); ?></div>
            <div><a href="admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension" class="button button-primary"><?php _e('Broken Links Checker Dashboard','mainwp'); ?></a></div>
        </div>
        <?php
    }   
    
    public static function childsite_metabox() {
        $site_id = isset($_GET['dashboard']) ? $_GET['dashboard'] : 0;        
        if (empty($site_id))
            return;        
             
        $lc = MainWPLinksCheckerDB::Instance()->getLinkscheckerBy('site_id', $site_id); 
        $links_info = unserialize($lc->link_info);
        $broken = $redirects = $dismissed = $all = 0;
        $broken_link = $redirects_link = $dismissed_link = $all_link = "";
        $link_prefix = "admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension&site_id=" . $site_id . "&filter_id=";        
        if (is_array($links_info) && $lc->active) {
            $broken = isset($links_info['broken']) ? $links_info['broken'] : 0; 
            $redirects = isset($links_info['redirects']) ? $links_info['redirects'] : 0;
            $dismissed = isset($links_info['dismissed']) ? $links_info['dismissed'] : 0;
            $all = isset($links_info['all']) ? $links_info['all'] : 0;            
            
            $broken_link = '<span class="edit"><a href="' . $link_prefix .'broken" >' . $broken . '</a></span>';
            $redirects_link = '<span class="edit"><a href="' . $link_prefix .'redirects" >' . $redirects . '</a></span>';
            $dismissed_link = '<span class="edit"><a href="' . $link_prefix .'dismissed">' . $dismissed . '</a></span>';
            $all_link = '<span class="edit"><a href="' . $link_prefix .'all">' . $all . '</a></span>';
        }
        
        ?>
        <div id="mainwp_widget_linkschecker_content" style="margin-top: 1em;">
            <?php 
            if (!$lc->active) {
                echo '<br class="clearfix">';
                echo "<span style=\"float:left\">". __("Broken Link Checker plugin not found or not activated on the website.") . "</span>";
                echo '<br class="clearfix">';
                echo '<br class="clearfix">';
            } else {
            ?>                
            <div>
                <span class="mwp_lc_count_links broken"><span class="number"><?php echo $broken_link . "</span><br/>" . __("Broken Links"); ?></span>
                <span class="mwp_lc_count_links redirects"><span class="number"><?php echo $redirects_link . "</span><br/>" . __("Redirect"); ?></span>
                <span class="mwp_lc_count_links dismissed"><span class="number"><?php echo $dismissed_link . "</span><br/>" . __("Dismissed"); ?></span>
                <span class="mwp_lc_count_links all"><span class="number"><?php echo $all_link . "</span><br/>" . __(strtoupper("All")); ?></span>
            </div>
            <br class="clearfix">            
            <?php } ?>
            <div><a href="admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension" class="button button-primary"><?php _e('Broken Links Checker Dashboard','mainwp'); ?></a></div>
        </div>
        <?php
    }
    
    public function ajax_edit_link(){
        if (!check_ajax_referer('mwp_blc_edit', false, false)){
            die( json_encode( array(
                'error' => __("You're not allowed to do that!") 
             )));
        }

        if ( empty($_POST['site_id']) || empty($_POST['link_id']) || empty($_POST['new_url']) || !is_numeric($_POST['link_id']) ) {
            die( json_encode( array(
                    'error' => __("Error : site_id, link_id or new_url not specified")
            )));
        }

        $post_data = array('mwp_action' => 'edit_link',
                            'link_id' => $_POST['link_id'],
                            'new_text' => $_POST['new_text'],
                            'new_url' => $_POST['new_url']                                        
                        ); 
        global $mainWPLinksCheckerExtensionActivator;
        $information = apply_filters('mainwp_fetchurlauthed', $mainWPLinksCheckerExtensionActivator->getChildFile(), $mainWPLinksCheckerExtensionActivator->getChildKey(), $_POST['site_id'], 'links_checker', $post_data);			        
        //print_r($information);
        if (is_array($information) && isset($information['cnt_okay']) && $information['cnt_okay'] > 0) {
            $update = $information;
            $update['site_id'] = $_POST['site_id'];            
            $this->update_link($update);
            $information['ui_link_text'] = preg_replace("/src=\".*\/images\/font-awesome\/(.+)/is", 'src="' . MWP_BROKEN_LINKS_CHECKER_URL . '/images/font-awesome/' . '${1}', $information['ui_link_text']);
        }
        
        die(json_encode($information));
    }
    
    public function ajax_unlink(){
        if (!check_ajax_referer('mwp_blc_unlink', false, false)){
            die( json_encode( array(
                'error' => __("You're not allowed to do that!") 
             )));
        }

        if ( empty($_POST['site_id']) || empty($_POST['link_id']) || !is_numeric($_POST['link_id']) ) {
            die( json_encode( array(
                    'error' => __("Error : site_id or link_id not specified")
            )));
        }

        $post_data = array('mwp_action' => 'unlink',
                            'link_id' => $_POST['link_id'],
                            'new_text' => $_POST['new_text']
                        ); 
        global $mainWPLinksCheckerExtensionActivator;
        
        $information = apply_filters('mainwp_fetchurlauthed', $mainWPLinksCheckerExtensionActivator->getChildFile(), $mainWPLinksCheckerExtensionActivator->getChildKey(), $_POST['site_id'], 'links_checker', $post_data);			        
        //print_r($information);
        if (is_array($information) && isset($information['cnt_okay']) && $information['cnt_okay'] > 0) {
            $update = array();
            $update['link_id'] = $_POST['link_id'];
            $update['site_id'] = $_POST['site_id'];
            $this->update_unlink($update);
        }        
        die(json_encode($information));
    }
    
    public function ajax_dismiss(){
        if (!check_ajax_referer('mwp_blc_dismiss', false, false)){
            die( json_encode( array(
                'error' => __("You're not allowed to do that!") 
             )));
        }        
        $this->ajax_set_dismissed(true);
        die();
    }
    
    public function ajax_undismiss(){
        if (!check_ajax_referer('mwp_blc_undismiss', false, false)){
            die( json_encode( array(
                'error' => __("You're not allowed to do that!") 
             )));
        }        
        $this->ajax_set_dismissed(false);
        die();
    }
    
    public function ajax_set_dismissed($dismiss){
        
        if ( empty($_POST['site_id']) || empty($_POST['link_id']) || !is_numeric($_POST['link_id']) ) {
            die( json_encode( array(
                    'error' => __("Error : site_id or link_id not specified")
            )));
        }

        $post_data = array('mwp_action' => 'set_dismiss',
                            'dismiss' => $dismiss,
                            'link_id' => $_POST['link_id']                           
                        ); 
        
        global $mainWPLinksCheckerExtensionActivator;
        
        $information = apply_filters('mainwp_fetchurlauthed', $mainWPLinksCheckerExtensionActivator->getChildFile(), $mainWPLinksCheckerExtensionActivator->getChildKey(), $_POST['site_id'], 'links_checker', $post_data);			        
        //print_r($information);
        if ($information === 'OK') {
            $update = array();
            $update['link_id'] = $_POST['link_id'];
            $update['site_id'] = $_POST['site_id'];
            $update['dismiss'] = $dismiss;
            $this->update_link_dismissed($update);
        }        
        die(json_encode($information));
    }
    
    public function update_link_dismissed($update) {
        if (!isset($update['link_id']) || empty($update['link_id']) || 
            !isset($update['site_id']) || empty($update['site_id']))
            return false;
        
        $data = MainWPLinksCheckerDB::Instance()->getLinksCheckerBy('site_id', $update['site_id']);
        $link_data = unserialize($data->link_data);
        if (is_array($link_data) && isset($update['link_id']) && $update['link_id']) {
            $new_link_data = array();
            foreach($link_data as $link) {                
                if ($link->link_id == $update['link_id']) {
                    $link->dismissed = $update['dismiss'];
                }
                $new_link_data[] = $link;
            }
            $new_link_info = $this->get_new_link_info($new_link_data); 
            $data_update = array('site_id' => $update['site_id'], 
                            'link_data' => serialize($new_link_data),
                            'link_info' => serialize($new_link_info),
                            );            
            MainWPLinksCheckerDB::Instance()->updateLinksChecker($data_update);
            return true;   
        }
        return false;
    }
    
    function ajax_discard(){
        if (!check_ajax_referer('mwp_blc_discard', false, false)){
            die( json_encode( array(
                 'error' => __("You're not allowed to do that!") 
             )));
        }

        if ( empty($_POST['site_id']) || empty($_POST['link_id']) || !is_numeric($_POST['link_id']) ) {
            die( json_encode( array(
                    'error' => __("Error : site_id or link_id not specified")
            )));
        }
        $post_data = array('mwp_action' => 'discard',                          
                            'link_id' => $_POST['link_id']                           
                        ); 
        
        global $mainWPLinksCheckerExtensionActivator;
        
        $information = apply_filters('mainwp_fetchurlauthed', $mainWPLinksCheckerExtensionActivator->getChildFile(), $mainWPLinksCheckerExtensionActivator->getChildKey(), $_POST['site_id'], 'links_checker', $post_data);			        
        //print_r($information);
        if (is_array($information) && isset($information['status']) && $information['status'] == 'OK') {
            $update = array();
            $update['link_id'] = $_POST['link_id'];
            $update['site_id'] = $_POST['site_id'];            
            $update['last_check_attempt'] = $information['last_check_attempt']; 
            $this->update_discard($update);
        }        
        die(json_encode($information));
        
    }
    
    public function update_discard($update) {
        if (!isset($update['link_id']) || empty($update['link_id']) || 
            !isset($update['site_id']) || empty($update['site_id']))
            return false;
        
        $data = MainWPLinksCheckerDB::Instance()->getLinksCheckerBy('site_id', $update['site_id']);
        $link_data = unserialize($data->link_data);
        if (is_array($link_data) && isset($update['link_id']) && $update['link_id']) {
            $new_link_data = array();
            foreach($link_data as $link) {                
                if ($link->link_id == $update['link_id']) {                    
                    $link->broken = false;  
                    $link->false_positive = true;
                    $link->last_check_attempt = $update['last_check_attempt'];
                    $link->log = __("This link was manually marked as working by the user.");
                }
                $new_link_data[] = $link;
            }
            $new_link_info = $this->get_new_link_info($new_link_data); 
            $data_update = array('site_id' => $update['site_id'], 
                            'link_data' => serialize($new_link_data),
                            'link_info' => serialize($new_link_info)
                            );
            MainWPLinksCheckerDB::Instance()->updateLinksChecker($data_update);
            return true;   
        }
        return false;
    }
    
    public function update_link($update) {
        if (!isset($update['new_link_id']) || empty($update['new_link_id']) || 
            !isset($update['site_id']) || empty($update['site_id']))
            return false;
        
        $data = MainWPLinksCheckerDB::Instance()->getLinksCheckerBy('site_id', $update['site_id']);
        $link_data = unserialize($data->link_data);
        if (is_array($link_data) && isset($update['link_id']) && $update['link_id']) {
            $new_link_data = array();
            foreach($link_data as $link) {                
                if ($link->link_id == $update['new_link_id']) {
                    $link->status_code = $update['status_code'];
                    $link->http_code = $update['http_code'];
                    $link->url = $update['url'];
                    $link->link_text = $update['link_text'];                    
                    $link->data_link_text = $update['ui_link_text'];                    
                    $link->last_check = 0;
                    $link->broken = 0;
                }
                $new_link_data[] = $link;
            }
            $new_link_info = $this->get_new_link_info($new_link_data); 
            $data_update = array('site_id' => $update['site_id'], 
                            'link_data' => serialize($new_link_data),
                            'link_info' => serialize($new_link_info),
                            );
            MainWPLinksCheckerDB::Instance()->updateLinksChecker($data_update);
            return true;   
        }
        return false;
    }
    
     public function update_unlink($update) {
        if (!isset($update['link_id']) || empty($update['link_id']) || 
            !isset($update['site_id']) || empty($update['site_id']))
            return false;
        
        $data = MainWPLinksCheckerDB::Instance()->getLinksCheckerBy('site_id', $update['site_id']);
        $link_data = unserialize($data->link_data);
        if (is_array($link_data) && isset($update['link_id']) && $update['link_id']) {
            $new_link_data = array();
            foreach($link_data as $link) {                
                if ($link->link_id !== $update['link_id']) {
                    $new_link_data[] = $link;
                }                
            }
            $new_link_info = $this->get_new_link_info($new_link_data);            
            $data_update = array('site_id' => $update['site_id'], 
                            'link_data' => serialize($new_link_data),
                            'link_info' => serialize($new_link_info)
                            );
            MainWPLinksCheckerDB::Instance()->updateLinksChecker($data_update);
            return true;   
        }
        return false;
    }
    
    function get_new_link_info($all_links) {
        $link_info = array( 'broken' => 0, 'redirects' => 0, 'dismissed' => 0, 'all' => 0 );        
        foreach($all_links as $link) {
            if ($link->broken == 1)
              $link_info['broken'] += 1;  
            if ($link->redirect_count > 0 && !$link->dismissed)
              $link_info['redirects'] += 1; 
            if ($link->dismissed == 1)
              $link_info['dismissed'] += 1; 
            $link_info['all'] += 1; 
        }
        update_option('mainwp_blc_refresh_total_link_info', 1);
        return $link_info;
    }
    
    public static function render() {              
        self::renderTabs();
    }
    
    public static function renderTabs() {
        $style_dashboard_tab = $style_broken_links_tab = $style_settings_tab = ' style="display: none" ';        
        $filter_id = "all";
        if (isset($_POST['mainwp_blc_links_groups_select']) || isset($_POST['mainwp_blc_select_site']) || isset($_GET['sl'])) {
            $style_broken_links_tab = ""; 
        } else if (isset($_GET['filter_id']) && !empty($_GET['filter_id'])) {
            $filter_id = $_GET['filter_id'];
            $style_broken_links_tab = ""; 
        } else {
            $style_dashboard_tab = "";
        } 
       
        global $mainWPLinksCheckerExtensionActivator;
        
        $websites = apply_filters('mainwp-getsites', $mainWPLinksCheckerExtensionActivator->getChildFile(), $mainWPLinksCheckerExtensionActivator->getChildKey(), null);              
        $sites_id = array();
        if (is_array($websites)) {
            foreach ($websites as $website) {
                $sites_id[] = $website['id'];
                
            }                
        }                
        $option = array('plugin_upgrades' => true, 
                        'plugins' => true);
        
        $dbwebsites = apply_filters('mainwp-getdbsites', $mainWPLinksCheckerExtensionActivator->getChildFile(), $mainWPLinksCheckerExtensionActivator->getChildKey(), $sites_id, array(), $option);              
              
        $selected_dashboard_group = 0;       
        
        if(isset($_POST['mainwp_linkschecker_groups_select'])) {
            $selected_dashboard_group = intval($_POST['mainwp_linkschecker_groups_select']);            
        } 
        $linkschecker_data = array();
        $results = MainWPLinksCheckerDB::Instance()->getLinkscheckerBy('all');
        foreach ($results as $value) {          
            if (!empty($value->site_id))
                $linkschecker_data[$value->site_id] = MainWPLinksCheckerUtility::mapSite($value, array('hide_plugin', 'link_info', 'link_data'));
        }
//        print_r($linkschecker_data);
//        print_r($results);
        
        $search = "";
        if (isset($_GET['s']) && !empty($_GET['s']))
            $search = $_GET['s'];
        
        $dbwebsites_dashboard_linkschecker = MainWPLinksCheckerDashboard::Instance()->get_websites_linkschecker($dbwebsites, $selected_dashboard_group, $search, $linkschecker_data);         
        $dbwebsites_linkschecker = MainWPLinksCheckerDashboard::Instance()->get_websites_linkschecker($dbwebsites, 0, "", $linkschecker_data);         
        
        //print_r($dbwebsites_linkschecker);       
        
        unset($dbwebsites);
           ?>
            <div class="wrap" id="mainwp-ap-option">
            <div class="clearfix"></div>           
            <div class="inside">   
                <div  class="mainwp_error" id="wpps-error-box" ></div>
                <div  class="mainwp_info-box-yellow hidden-field" id="wpps-info-box" ></div>
                <?php self::MainWPLinksCheckerQSG(); ?>                                
                <a id="blc_dashboard_tab_lnk" href="#" class="mainwp_action left <?php  echo (empty($style_dashboard_tab) ? "mainwp_action_down" : ""); ?>"><?php _e("Broken Links Checker Dashboard"); ?></a><a id="blc_broken_links_tab_lnk" href="#" class="mainwp_action mid <?php  echo (empty($style_broken_links_tab) ? "mainwp_action_down" : ""); ?>"><?php _e("Broken Links"); ?></a><a id="blc_settings_tab_lnk" href="#" class="mainwp_action right <?php  echo (empty($style_settings_tab) ? "mainwp_action_down" : ""); ?>"><?php _e("Settings"); ?></a>
                <br />                             
                <div id="blc_dashboard_tab" <?php echo $style_dashboard_tab; ?>> 
                    <div id="mainwp_linkschecker_option">                        
                        <div class="clear">                        
                            <div>
                                <div class="tablenav top">
                                <?php MainWPLinksCheckerDashboard::gen_select_boxs($dbwebsites_dashboard_linkschecker, $selected_dashboard_group); ?>  
                                <input type="button" class="mainwp-upgrade-button button-primary button" 
                                       value="<?php _e("Sync Data"); ?>" id="dashboard_refresh" style="background-image: none!important; float:right; padding-left: .6em !important;">
                                </div>                            
                                <?php MainWPLinksCheckerDashboard::gen_dashboard_tab($dbwebsites_dashboard_linkschecker); ?>                            
                            </div>                                                                          
                        </div>
                    <div class="clear"></div>
                    </div>
                </div>
                <div id="blc_broken_links_tab" <?php echo $style_broken_links_tab; ?>> 
                    <?php self::gen_broken_links_tab($dbwebsites_linkschecker, $filter_id); ?>
                </div>
                <div id="blc_settings_tab" <?php echo $style_settings_tab; ?>> 
                    <?php self::gen_settings_tab(); ?>
                </div>
            </div>
        </div>              
    <?php
    }
    
    static function gen_settings_tab() {
        $check_threshold = MainWPLinksChecker::Instance()->get_option('check_threshold');
    ?>
    <br>
    <div class="mainwp_info-box-red hidden-field" id="mwp-blc-setting-error-box"></div>
    <div class="postbox">
        <h3 class="mainwp_box_title"><span><?php _e("Settings", "mainwp"); ?></span></h3>
        <div class="inside">
        <h4 id="mwp_blc_settings_saving_title" class="hidden-field"><?php _e("Saving settings to child sites ...", "mainwp"); ?></h4>            
        <h4 id="mwp_blc_settings_start_recheck_title" class="hidden-field"><?php _e("Rechecking on child sites ...", "mainwp"); ?></h4>            
        <div class="mainwp_info-box hidden"></div>
        <div id="mainwp-blc-setting-tab-content">
            <table class="form-table">
                <tbody>            
                <tr>
                    <th scope="row">
                        <?php _e("Check each link", "mainwp"); ?>
                    </th>
                    <td><?php _e("Every", "mainwp"); ?> <input type="text" maxlength="5" size="5" value="<?php echo $check_threshold; ?>" id="check_threshold" name="check_threshold"> <?php _e("hours", "mainwp"); ?><br>
                        <span class="description"><?php _e("Existing links will be checked this often. New links will usually be checked ASAP."); ?></span>
                    </td>
                </tr>   
                <tr valign="top">
                    <th scope="row"><?php _e("Forced recheck", "mainwp"); ?></th>
                    <td>
                        <input type="button" value="<?php _e("Re-check all pages", "mainwp"); ?>" id="mwp-blc-start-recheck-btn" name="mwp-blc-start-recheck-btn" class="button">
                        <span id="mainwp_blc_setting_recheck_loading" class="hidden-field"><img src="<?php echo plugins_url('images/loader.gif', dirname(__FILE__)); ?>"></span> 
                        <input type="hidden" id="recheck" value="" name="recheck">
                        <br>
                        <span class="description"><?php _e("Click this button to make the plugin empty its link database and recheck the entire site from scratch. Please, be patient until new list gets generated.", "mainwp"); ?></span>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>    
    </div>
    </div>
    <p class="submit">                                    
        <span style="float:left;">
            <input type="button" name="button_preview" id="mwp-blc-save-settings-btn" class="button-primary" value="<?php _e("Save Settings", "mainwp"); ?>">                                        
            <span id="mainwp_blc_setting_loading" class="hidden-field"><img src="<?php echo plugins_url('images/loader.gif', dirname(__FILE__)); ?>"></span> 
        </span>
    </p>
    <?php
    }
    
    static function gen_broken_links_tab($all_websites, $filter = "all") {
        $all_links = $sites_url = array();    
        $link_info = "";
        $selected_site = $selected_group = 0;           
        $group_sites_ids = null;         
        $search_sites = array(); 
        $filter_search = $filter_site = $filter_group = "";
        
        if (isset($_GET['site_id']) && !empty($_GET['site_id'])) {
            $filter_site = $selected_site = $_GET['site_id'];
        } else if (isset($_GET['filter_search']) && !empty($_GET['filter_search'])) {
            $filter_search = $_GET['filter_search'];
        } else if (isset($_GET['group_id']) && !empty($_GET['group_id'])) {
            $filter_group = $selected_group = $_GET['group_id'];
        } if(isset($_POST['mainwp_blc_links_groups_select']) && !empty($_POST['mainwp_blc_links_groups_select'])) {
            $filter_group = $selected_group = intval($_POST['mainwp_blc_links_groups_select']);             
        } else if(isset($_POST['mainwp_blc_select_site'])) {
            $filter_site = $selected_site = intval($_POST['mainwp_blc_select_site']);            
        } else if ((isset($_GET['sl']) && !empty($_GET['sl']))) {
            $filter_search = trim($_GET['sl']);
        }
        
        $do_filter = false; 
        
        if ($filter_group) {
            global $mainWPLinksCheckerExtensionActivator;                
            $group_websites = apply_filters('mainwp-getdbsites', $mainWPLinksCheckerExtensionActivator->getChildFile(), $mainWPLinksCheckerExtensionActivator->getChildKey(), array(), array($filter_group));  
            $group_sites_ids = array();
            foreach($group_websites as $site) {
                $group_sites_ids[] = $site->id;
            }   
            $do_filter = true;
        } else if ($filter_search) {
            $find = $filter_search;
            foreach($all_websites as $website ) {                
                if (stripos($website['name'], $find) !== false || stripos($website['url'], $find) !== false) {
                    $search_sites[] = $website;
                }
            }
            $websites = $search_sites;
            $do_filter = true;
        } else if (is_array($group_sites_ids)) {
            if (count($group_sites_ids) > 0) {
                foreach($all_websites as $website ) {                
                    if (in_array($website['id'], $group_sites_ids)) {
                        $search_sites[] = $website;
                    }
                }
            }
            $websites = $search_sites;
            $do_filter = true;
        } else if ($selected_site) {
            foreach($all_websites as $website ) {                
                if ($website['id'] == $selected_site) {
                    $search_sites[] = $website;
                }
            }
            $websites = $search_sites;
            $do_filter = true;
        }  
        unset($search_sites);    
        
        if (!$do_filter) {
           $websites = $all_websites; 
        }
        
        foreach($websites as $website) {            
            $link_data = $website['link_data'];   
            $sites_url[$website['id']] = $website['url'];
            if (is_array($link_data) && !empty($link_data[0])) { 
                if (empty($filter) || $filter == "all") {
                    $all_links = array_merge($all_links, $link_data);
                } else {
                    $selected_links = array();
                    if ($filter == "broken") {
                        foreach($link_data as $link) {
                            if ($link->broken == 1)
                                $selected_links[] = $link;
                        }
                    } else if ($filter == "dismissed") {
                        foreach($link_data as $link) {
                            if ($link->dismissed  == 1)
                                $selected_links[] = $link;
                        }
                    } else if ($filter == "redirects") {
                        foreach($link_data as $link) {                                
                            if (!$link->dismissed && $link->redirect_count > 0)
                                $selected_links[] = $link;
                        }
                    }
                    $all_links = array_merge($all_links, $selected_links);
                }
            }
        }
     
        ?>
        <div id="mainwp_blc_links_content"> 
            <div class="tablenav top">
                <div class="alignleft">
                    <?php MainWPLinksChecker::Instance()->print_filter_menu($filter, $websites, $filter_search, $filter_site, $filter_group); ?>
                </div>
                <br class="clearfix">
                <?php MainWPLinksChecker::Instance()->gen_select_boxs($all_websites, $filter_search, $selected_group, $selected_site); ?>
                <div class="alignright">
                    <input type="button" style="background-image: none!important; float:right; padding-left: .6em !important;" id="dashboard_refresh" value="<?php _e("Sync Data", 'mainwp'); ?>" class="mainwp-upgrade-button button-primary button">
                </div>
            </div>            
            <table class="wp-list-table widefat fixed posts tablesorter color-code-link-status" id="mainwp_blc_links_table"
                   cellspacing="0">
                <thead>
                <tr>                   
                    <th scope="col" id="title" class="manage-column column-title sortable desc" style="">
                        <a href="#" onclick="return false;"><span><?php _e('URL','mainwp'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" id="status" class="manage-column mwp-column-status sortable desc" style="">
                        <a href="#" onclick="return false;"><span><?php _e('Status','mainwp'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" id="new-link-text" class="manage-column mwp-column-new-link-text sortable desc" style="">
                        <a href="#" onclick="return false;"><span><?php _e('Link Text','mainwp'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" id="redirect-url" class="manage-column column-redirect-url sortable desc" style="">
                        <a href="#" onclick="return false;"><span><?php _e('Redirect URL','mainwp'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" id="source" class="manage-column column-source sortable desc" style="">
                        <a href="#" onclick="return false;"><span><?php _e('Source','mainwp'); ?></span><span class="sorting-indicator"></span></a>
                    </th> 
                    <th scope="col" id="url" class="manage-column column-url sortable desc" style="">
                        <a href="#" onclick="return false;"><span><?php _e('Site URL','mainwp'); ?></span><span class="sorting-indicator"></span></a>
                    </th> 
                </tr>
                </thead>

                <tfoot>
                <tr>                    
                    <th scope="col" id="title" class="manage-column column-title sortable desc" style="">
                        <a href="#" onclick="return false;"><span><?php _e('URL','mainwp'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" id="status" class="manage-column mwp-column-status sortable desc" style="">
                        <a href="#" onclick="return false;"><span><?php _e('Status','mainwp'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" id="new-link-text" class="manage-column mwp-column-new-link-text sortable desc" style="">
                        <a href="#" onclick="return false;"><span><?php _e('Link Text','mainwp'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" id="redirect-url" class="manage-column column-redirect-url sortable desc" style="">
                        <a href="#" onclick="return false;"><span><?php _e('Redirect URL','mainwp'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" id="source" class="manage-column column-source sortable desc" style="">
                        <a href="#" onclick="return false;"><span><?php _e('Source','mainwp'); ?></span><span class="sorting-indicator"></span></a>
                    </th> 
                    <th scope="col" id="url" class="manage-column column-url sortable desc" style="">
                        <a href="#" onclick="return false;"><span><?php _e('Site URL','mainwp'); ?></span><span class="sorting-indicator"></span></a>
                    </th> 
                </tr>
                </tfoot> 
                <tbody class="list:posts">
                <?php 
                    self::renderTableLinksContent($all_links, $sites_url);
                ?>
                </tbody>
            </table>
            <div class="tablenav bottom">
                <div class="pager" id="pager">
                    <form>
                        <?php do_action('mainwp_renderImage', 'images/first.png', 'First', 'first'); ?>
                        <?php do_action('mainwp_renderImage', 'images/prev.png', 'Previous', 'prev'); ?>
                        <input type="text" class="pagedisplay" />
                        <?php do_action('mainwp_renderImage', 'images/next.png', 'Next', 'next'); ?>
                        <?php do_action('mainwp_renderImage', 'images/last.png', 'Last', 'last'); ?>
                        <span>&nbsp;&nbsp;<?php _e('Show:','mainwp'); ?> </span><select class="pagesize">
                            <option selected="selected" value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="1000000000">All</option>
                        </select><span> <?php _e('Links per page','mainwp'); ?></span>
                    </form>
                </div>
            </div>
            <div class="clear"></div>
        </div>
        <script>mainwp_broken_links_checker_table_reinit();</script>
    <?php
        MainWPLinksChecker::Instance()->inline_editor();
        include_once MWP_BROKEN_LINKS_CHECKER_DIR . 'includes/mwp-links-page-js.php';        
    }
    
    public static function gen_select_boxs($all_sites, $filter_search, $selected_group, $selected_site) {
        global $mainWPLinksCheckerExtensionActivator;
        $groups = apply_filters('mainwp-getgroups', $mainWPLinksCheckerExtensionActivator->getChildFile(), $mainWPLinksCheckerExtensionActivator->getChildKey(), null);        
        $search = $filter_search
        ?> 
                   
        <div class="alignleft actions">
            <form action="" method="GET">
                <input type="hidden" name="page" value="Extensions-Mainwp-Broken-Links-Checker-Extension">
                <span role="status" aria-live="polite" class="ui-helper-hidden-accessible"><?php _e('No search results.','mainwp'); ?></span>
                <input type="text" class="mainwp_autocomplete ui-autocomplete-input" name="sl" autocompletelist="sites" value="<?php echo stripslashes($search); ?>" autocomplete="off">
                <datalist id="sites">
                    <?php
                    if (is_array($all_sites) && count($all_sites) > 0) {
                        foreach ($all_sites as $website) {                    
                            echo "<option>" . $website['name']. "</option>";                    
                        }
                    }
                    ?>                
                </datalist>
                <input type="submit" name="" class="button" value="<?php _e("Search Sites", "mainwp"); ?>">
            </form>
        </div>    
        
        <div class="alignleft actions">
            <form method="post" action="admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension">
                <select name="mainwp_blc_select_site" id="mainwp_blc_select_site">
                    <option value="0"><?php _e("Select a Site"); ?></option>
                <?php
                foreach ($all_sites as $site) {
                    $_select = "";
                    if ($site['id'] == intval($selected_site)) {
                        $_select = "selected";
                    }                                        
                ?>
                    <option value="<?php echo $site['id']; ?>" <?php echo $_select; ?>><?php echo stripslashes($site['name']); ?></option>
                <?php
                }
                ?>
                </select>
                <input type="submit" id="mainwp_blc_select_site_btn_display" class="button" value="<?php _e("Display"); ?>" />
            </form>  
       </div>                                
        <div class="alignleft actions">
            <form method="post" action="admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension">
                <select name="mainwp_blc_links_groups_select">
                <option value="0"><?php _e("Select a group"); ?></option>
                <?php
                if (is_array($groups) && count($groups) > 0) {
                    foreach ($groups as $group) {
                        $_select = "";
                        if ($selected_group == $group['id'])
                            $_select = 'selected ';                    
                        echo '<option value="' . $group['id'] . '" ' . $_select . '>' . $group['name'] . '</option>';
                    }     
                }
                ?>
                </select>                    
                <input class="button" type="submit" name="mwp_blc_groups_btn_display" id="mwp_blc_groups_btn_display" value="<?php _e("Display", "mainwp"); ?>">
            </form>  
        </div>    
        <?php       
        return;
    }
    
    function get_filters($websites = null) { 
        
        $total = array();
        if ($websites === null) {
            $total = $this->get_option('total_link_info', array());        
        } else {
             if (is_array($websites) && count($websites) > 0) {                  
                foreach($websites as $site) {                                       
                    $total['broken'] += isset($site['broken']) ? intval($site['broken']) : 0;
                    $total['redirects'] += isset($site['redirects']) ? intval($site['redirects']) : 0;
                    $total['dismissed'] += isset($site['dismissed']) ? intval($site['dismissed']) : 0;
                    $total['all'] += isset($site['all']) ? intval($site['all']) : 0;   
                }
            }        
        }
        
        if (!is_array($total)) 
            $total = array();
       
        $filters = array(   
                        'broken' => array( 'name' => __("Broken", 'mainwp'),
                                           'count' => isset($total['broken']) ? $total['broken'] : 0
                                         ),
                        'redirects' => array(   'name' => __("Redirects", 'mainwp'),
                                                'count' => isset($total['redirects']) ? $total['redirects'] : 0
                                            ),
                        'dismissed' => array(   'name' => __("Dismissed", 'mainwp'),
                                                'count' => isset($total['dismissed']) ? $total['dismissed'] : 0
                                            ),
                        'all' => array(     'name' => __("All", 'mainwp'),
                                            'count' => isset($total['all']) ? $total['all'] : 0
                                        )
                    );
        return $filters;
    }
    
    function print_filter_menu($current = '', $websites = array(), $filter_search = "", $filter_site = "", $filter_group = ""){
        
        if (!empty($filter_search) || !empty($filter_site) || !empty($filter_group))
            $filters = $this->get_filters($websites);
        else 
            $filters = $this->get_filters();
        
        $str_site = "";
        if (!empty($filter_search))
            $str_site = "&filter_search=" . $filter_search;
        else if (!empty($filter_site))
            $str_site = "&site_id=" . $filter_site;
        else if (!empty($filter_group))
            $str_site = "&group_id=" . $filter_group;
        
        
        echo '<ul class="subsubsub">';
        
        //Construct a submenu of filter types
        $items = array();
        foreach ($filters as $filter => $data){
//                if ( !empty($data['hidden']) ) continue; //skip hidden filters

                $class = '';
                $number_class = 'filter-' . $filter . '-link-count';

                if ( $current == $filter ) {
                        $class = 'class="current"';
                        $number_class .= ' current-link-count';
                }

                $items[] = "<li><a href='admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension&filter_id=$filter$str_site' {$class}>
                        {$data['name']}</a> <span class='count'>(<span class='$number_class'>{$data['count']}</span>)</span>";
        }
        echo implode(' |</li>', $items);

        echo '</ul>';
    }
        
    function link_details_row($link){
        printf(
                '<tr id="link-details-%d-siteid-%d" class="blc-link-details expand-child"><td colspan="%d">',
                $link->link_id, $link->site_id, 5
        );
        $this->details_row_contents($link);
        echo '</td></tr>';
    }
        
    public static function details_row_contents($link){
        ?>
        <div class="blc-detail-container">
                <div class="blc-detail-block" style="float: left; width: 49%;">
                <ol style='list-style-type: none;'>
                <?php if (!empty($link->post_date) ) { ?>
                <li><strong><?php _e('Post published on'); ?>:</strong>
                <span class='post_date'><?php
                                echo date_i18n(get_option('date_format'),strtotime($link->post_date));
                ?></span></li>
                <?php } ?>
                
                <li><strong><?php _e('Link last checked'); ?>:</strong>
                <span class='check_date'><?php
                                $last_check = $link->last_check;
                        if ( $last_check < strtotime('-10 years') ){
                                        _e('Never');
                                } else {
                                echo date_i18n(get_option('date_format'), $last_check);
                        }
                ?></span></li>

                <li><strong><?php _e('HTTP code'); ?>:</strong>
                <span class='http_code'><?php 
                        print $link->http_code; 
                ?></span></li>

                <li><strong><?php _e('Response time'); ?>:</strong>
                <span class='request_duration'><?php 
                        printf( __('%2.3f seconds'), $link->request_duration); 
                ?></span></li>

                <li><strong><?php _e('Final URL'); ?>:</strong>
                <span class='final_url'><?php 
                        print $link->final_url; 
                ?></span></li>

                <li><strong><?php _e('Redirect count'); ?>:</strong>
                <span class='redirect_count'><?php 
                        print $link->redirect_count; 
                ?></span></li>

                <li><strong><?php _e('Instance count'); ?>:</strong>
                <span class='instance_count'><?php 
                    print $link->count_instance; 
                ?></span></li>

                <?php if ( $link->broken && (intval( $link->check_count ) > 0) ){ ?>
                <li><br/>
                        <?php 
                                printf(
                                        _n('This link has failed %d time.', 'This link has failed %d times.', $link->check_count),
                                        $link->check_count
                                );

                                echo '<br>';

                                $delta = time() - $link->first_failure;
                                printf(
                                        __('This link has been broken for %s.'),
                                        MainWPLinksCheckerUtility::fuzzy_delta($delta)
                                );
                        ?>
                        </li>
                <?php } ?>
                        </ol>
                </div>

                <div class="blc-detail-block" style="float: right; width: 50%;">
                <ol style='list-style-type: none;'>
                        <li><strong><?php _e('Log'); ?>:</strong>
                <span class='blc_log'><?php 
                        print nl2br($link->log); 
                ?></span></li>
                        </ol>
                </div>

                <div style="clear:both;"> </div>
        </div>
        <?php
    }
        
    static function renderTableLinksContent($links, $sites_url) {
        $rownum = 0;
        foreach($links as $link) { 
            if (empty($link->link_id))
                continue;
            
            $status = self::analyse_status($link); 	
            $rownum++;

            $rowclass = ($rownum % 2)? 'alternate' : '';
            if ( $link->redirect_count > 0){
                    $rowclass .= ' blc-redirect';
            }
                
            if ( $link->broken ){
                //Add a highlight to broken links that appear to be permanently broken
                if ( $link->permanently_broken ){
                    $rowclass .= ' blc-permanently-broken';
                    if ($link->permanently_broken_highlight){
                        $rowclass .= ' blc-permanently-broken-hl';
                    }
                }
            }
            
            $data_link_text = "";
            if (!empty($link->data_link_text)) 
                $data_link_text = ' data-link-text="' . $link->data_link_text . '"';
            
            $rowattr = sprintf(
                ' data-days-broken="%d" data-can-edit-url="%d" data-can-edit-text="%d"%s ',
                 $link->days_broken,
                 $link->can_edit_url ? 1 : 0,
                 $link->can_edit_text ? 1 : 0,
                 $data_link_text
            );
           
            $link_text = preg_replace("/src=\".*\/images\/font-awesome\/(.+)/is", 'src="' . MWP_BROKEN_LINKS_CHECKER_URL . '/images/font-awesome/' . '${1}', $link->link_text);

            ?>              
            <tr valign="top" id="blc-row-<?php echo $link->link_id; ?>-siteid-<?php echo $link->site_id; ?>" class="blc-row link-status-<?php echo $status['code'] ?> <?php echo $rowclass; ?>" <?php echo $rowattr; ?>>                
                <td class="post-title page-title column-title">
                    <?php self::column_new_url($link); ?>
                </td>
                <td class="status mwp-column-status">
                    <?php MainWPLinksChecker::Instance()->column_status($link, $status); ?>
                </td>
                <td class="new-link-text mwp-column-new-link-text">   
                    <span><?php echo $link_text; ?></span>
                </td>
                <td class="redirect-url column-redirect-url">    
                    <?php 
                        MainWPLinksChecker::Instance()->column_redirect_url($link);
                    ?>
                </td>                
                <td class="source column-source">                  
                    <?php                         
                        $website_data = new stdClass();
                        $website_data->id = $link->site_id; 
                        $website_data->url = $sites_url[$link->site_id];
                        MainWPLinksChecker::Instance()->column_source($link, $website_data); 
                    ?>
                </td>    
                <td class="url column-url">       
                    <a href="<?php echo $sites_url[$link->site_id]; ?>" target="_blank"><?php echo $sites_url[$link->site_id]; ?></a><br/>
                    <div class="row-actions">
                        <span class="edit">
                            <a href="admin.php?page=managesites&dashboard=<?php echo $link->site_id; ?>"><?php _e("Dashboard"); ?></a>                                                       
                        </span> | <span class="edit">
                            <a target="_blank" href="admin.php?page=SiteOpen&newWindow=yes&websiteid=<?php echo $link->site_id; ?>"><?php _e("Open WP-Admin");?></a>
                        </span>
                    </div>                    
                </td>    
            </tr>
    <?php 
            MainWPLinksChecker::Instance()->link_details_row($link);        
        }; 
        
    }
    
    /*
    PluginName: Broken Link Checker
    PluginURI: http://w-shadow.com/blog/2007/08/05/broken-link-checker-for-wordpress/
    Description: Checks your blog for broken links and missing images and notifies you on the dashboard if any are found.
    Version: 1.9.2
    Author: Janis Elsts
    AuthorURI: http://w-shadow.com/
    TextDomain: broken-link-checker
    */ 
    
    function column_status($link, $status){
            printf(
                    '<table class="mini-status" title="%s">',
                    esc_attr(__('Show more info about this link', 'broken-link-checker'))
            );

            //$status = $link->analyse_status();

            printf(
                    '<tr class="link-status-row link-status-%s">
                            <td>
                                    <span class="http-code">%s</span> <span class="status-text">%s</span>
                            </td>
                    </tr>',
                    $status['code'],
                    empty($link->http_code)?'':$link->http_code,
                    $status['text']
            );

            //Last checked...
//            if ( $link->last_check != 0 ){
//                    $last_check = _x('Checked', 'checked how long ago', 'broken-link-checker') . ' ';
//                    $last_check .= MainWPLinksCheckerUtility::fuzzy_delta(time() - $link->last_check, 'ago');
//
//                    printf(
//                            '<tr class="link-last-checked"><td>%s</td></tr>',
//                            $last_check
//                    );
//            }


            //Broken for...
//            if ( $link->broken ){
//                    $delta = time() - $link->first_failure;
//                    $broken_for = MainWPLinksCheckerUtility::fuzzy_delta($delta);
//                    printf(
//                            '<tr class="link-broken-for"><td>%s %s</td></tr>',
//                            __('Broken for', 'broken-link-checker'),
//                            $broken_for
//                    );
//            }

            echo '</table>';
    }

        
    function column_redirect_url($link) {
        if ( $link->redirect_count > 0 ) {
            printf(
                '<a href="%1$s" target="_blank" class="blc-redirect-url" title="%1$s">%2$s</a>',
                esc_attr($link->final_url),
                esc_html($link->final_url)
            );
        }
    }
        
    static function analyse_status($link){
        $code = MWP_BLC_LINK_STATUS_UNKNOWN;
        $text = __('Unknown');

        if ( $link->broken ){

                $code = MWP_BLC_LINK_STATUS_WARNING;
                $text = __('Unknown Error');

                if ( $link->timeout ){

                        $text = __('Timeout');
                        $code = MWP_BLC_LINK_STATUS_WARNING;

                } elseif ( $link->http_code ) {

                        //Only 404 (Not Found) and 410 (Gone) are treated as broken-for-sure.
                        if ( in_array($link->http_code, array(404, 410)) ){
                                $code = MWP_BLC_LINK_STATUS_ERROR;
                        } else {
                                $code = MWP_BLC_LINK_STATUS_WARNING;
                        }

                        if ( array_key_exists(intval($link->http_code), MainWPLinksChecker::Instance()->http_status_codes) ){
                                $text = MainWPLinksChecker::Instance()->http_status_codes[intval($link->http_code)];
                        }
                }

        } else {

                if ( !$link->last_check ) {
                        $text = __('Not checked');
                        $code = MWP_BLC_LINK_STATUS_UNKNOWN;
                } elseif ( $link->false_positive ) {
                        $text = __('False positive');
                        $code = MWP_BLC_LINK_STATUS_UNKNOWN;
                } else {
                        $text = __('OK');
                        $code = MWP_BLC_LINK_STATUS_OK;
                }

        }
        return compact('text', 'code');
    }
        
    static function column_new_url($link){
    ?>
        <a href="<?php print esc_attr($link->url); ?>" target='_blank' class='blc-link-url' title="<?php echo esc_attr($link->url); ?>">
            <?php print $link->url; ?></a>
            <?php
            //Output inline action links for the link/URL                  	
            $actions = array();

            $actions['edit'] = "<span class='edit'><a href='javascript:void(0)' class='mwp-blc-edit-button' title='" . esc_attr( __('Edit this link' ) ) . "'>". __('Edit URL' ) ."</a>";

            $actions['delete'] = "<span class='delete'><a class='submitdelete mwp-blc-unlink-button' title='" . esc_attr( __('Remove this link from all posts') ). "' ".
                            "href='javascript:void(0);'>" . __('Unlink') . "</a>";

            if ( $link->broken ){
                    $actions['discard'] = sprintf(
                            '<span><a href="#" title="%s" class="mwp-blc-discard-button">%s</a>',
                            esc_attr(__('Remove this link from the list of broken links and mark it as valid')),
                            __('Not broken')
                    );
            }

            if ( !$link->dismissed && ($link->broken || ($link->redirect_count > 0)) ) {
                    $actions['dismiss'] = sprintf(
                            '<span><a href="#" title="%s" class="mwp-blc-dismiss-button">%s</a>',
                            esc_attr(__('Hide this link and do not report it again unless its status changes' )),
                            __('Dismiss')
                    );
            } else if ( $link->dismissed ) {
                    $actions['undismiss'] = sprintf(
                            '<span><a href="#" title="%s" class="blc-undismiss-button">%s</a>',
                            esc_attr(__('Undismiss this link')),
                            __('Undismiss')
                    );
            }

            echo '<div class="row-actions">';
            echo implode(' | </span>', $actions) .'</span>';
            echo '</div>';
            echo '<div class="working-status hidden"></div>';  

            ?>
            <div class="mwp-blc-url-editor-buttons">
                    <input type="button" class="button-secondary cancel alignleft mwp-blc-cancel-button" value="<?php echo esc_attr(__('Cancel')); ?>" />
                    <input type="button" class="button-primary save alignright blc-update-url-button" value="<?php echo esc_attr(__('Update URL')); ?>" />
                    <img class="waiting" style="display:none;" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" />
            </div>
            <?php
    }
    
    function column_source($link, $website) {       
        if (is_array($link->source_data)) {
            if ($link->container_type == 'comment') {                
                $image = "";
                if (isset($link->source_data['image'])) {
                    $image = sprintf(
                            '<img src="%s/images/%s" class="blc-small-image" title="%3$s" alt="%3$s"> ',
                            MWP_BROKEN_LINKS_CHECKER_URL,
                            $link->source_data['image'],
                            __('Comment', 'mainwp')
                    );
                }
                $html = "";
                if (isset($link->source_data['text_sample'])) {                    
                    $edit_href = 'admin.php?page=SiteOpen&websiteid=' . $website->id . '&location=' . base64_encode('comment.php?action=editcomment&c=' . $link->source_data['comment_id']);
                    $html = sprintf(
                            '<a href="%s" title="%s"><b>%s</b> &mdash; %s</a>',
                            $edit_href,
                            esc_attr__('Edit comment'),
                            $link->source_data['comment_author'],
                            $link->source_data['text_sample']		
                    );
                }
                echo $image . $html;                
                if ($link->source_data['comment_id'] && ($link->source_data['comment_status'] != 'trash') && ($link->source_data['comment_status'] != 'spam')) { ?>
                <span class="hidden source_column_data" data-comment_id="<?php echo $link->source_data['comment_id']; ?>" data-site_id_encode="<?php echo base64_encode($link->site_id); ?>"></span>
                    <div class="row-actions">
                       <span class="edit">
                           <a href="admin.php?page=SiteOpen&websiteid=<?php echo $website->id; ?>&location=<?php echo base64_encode('comment.php?action=editcomment&c=' . $link->source_data['comment_id']); ?>"
                              title="Edit this item"><?php _e('Edit','mainwp'); ?></a>
                       </span>
                        <span class="trash">
                            | <a class="blc_comment_submitdelete" title="<?php _e("Move this item to the Trash", "mainwp"); ?>" href="#"><?php _e('Trash','mainwp'); ?></a>
                        </span>
                       <?php 
                        $per_link = $website->url . (substr($website->url, -1) != '/' ? '/' : '') . '?p=' . $link->source_data['container_post_ID'];
                        if ( in_array($link->source_data['container_post_status'], array('pending', 'draft')) ) {
                            printf(
                                    '<span class="view">| <a href="%s" title="%s" rel="permalink">%s</a>',
                                    esc_url( add_query_arg( 'preview', 'true', $per_link )),
                                    esc_attr(sprintf(__('Preview &#8220;%s&#8221;'), $link->source_data['container_post_title'])),
                                    __('Preview Post')
                            );
                        } elseif ( 'trash' != $link->source_data['container_post_status'] ) {
                            printf(
                                    '<span class="view">| <a href="%s" title="%s" rel="permalink" target="_blank">%s</a></span>',
                                    $per_link,
                                    esc_attr(sprintf(__('View &#8220;%s&#8221;'), $link->source_data['container_post_title'])),
                                    __('View Post')
                            );
                        } 
                        ?>
                    </div> 
                    <?php
                }
            } else {
                 if (isset($link->source_data['container_anypost']) && $link->source_data['container_anypost']) {                     
                        $edit_href = 'admin.php?page=SiteOpen&websiteid=' . $website->id . '&location=' . base64_encode('post.php?post=' .$link->container_id . '&action=edit');
                        $source = sprintf(
                                '<a class="row-title" href="%s" title="%s">%s</a>',
                                $edit_href,
                                esc_attr(__('Edit this item')),
                                $link->source_data['post_title']
                        );
                        echo $source;
                         ?>
                        <span class="hidden source_column_data" data-post_id="<?php echo $link->container_id; ?>" ></span>
                        <div class="row-actions">
                            <?php if ($link->source_data['post_status'] != 'trash') { ?>
                            <span class="edit"><a
                                    href="admin.php?page=SiteOpen&websiteid=<?php echo $website->id; ?>&location=<?php echo base64_encode('post.php?post=' .$link->container_id . '&action=edit'); ?>"
                                    title="Edit this item"><?php _e('Edit','mainwp'); ?></a></span>
                            <span class="trash">
                                | <a class="blc_post_submitdelete" title="<?php _e("Move this item to the Trash", "mainwp"); ?>" href="#"><?php _e('Trash','mainwp'); ?></a>
                            </span>
                            <?php } ?>
                            
                            <?php
                            $per_link = $website->url . (substr($website->url, -1) != '/' ? '/' : '') . '?p=' . $link->container_id;
                            if ( in_array($link->source_data['post_status'], array('pending', 'draft')) ) {
                                printf(
                                        '<span class="view">| <a href="%s" title="%s" rel="permalink">%s</a>',
                                        esc_url( add_query_arg( 'preview', 'true', $per_link )),
                                        esc_attr(sprintf(__('Preview &#8220;%s&#8221;'), $link->source_data['post_title'])),
                                        __('Preview')
                                );
                            } elseif ( 'trash' != $link->source_data['post_status'] ) {
                                printf(
                                        '<span class="view">| <a href="%s" title="%s" rel="permalink" target="_blank">%s</a></span>',
                                        $per_link,
                                        esc_attr(sprintf(__('View &#8220;%s&#8221;'), $link->source_data['post_title'])),
                                        __('View')
                                );
                            }
                            ?>
                        </div>
                        <?php
                 }
            }
            ?>            
            <div class="working-status hidden"></div> 
            <?php
        }
    }
    
    protected function inline_editor($visible_columns = 6) {
        ?>
        <table style="display: none;"><tbody>
                <tr id="blc-inline-edit-row" class="blc-inline-editor">
                        <td class="blc-colspan-change" colspan="<?php echo $visible_columns; ?>">
                                <div class="blc-inline-editor-content">
                                        <h4><?php echo _x('Edit Link', 'inline editor title'); ?></h4>
                                        <div class="mainwp_info-box-red hidden" id="mwp_blc_edit_link_error_box"></div>    
                                        <div class="mainwp_info-box-yellow hidden" id="mwp_blc_edit_link_info_box"></div>    
                                        <label>
                                                <span class="title"><?php echo _x('Text', 'inline link editor'); ?></span>
                                                <span class="blc-input-text-wrap"><input type="text" name="link_text" value="" class="blc-link-text-field" /></span>
                                        </label>

                                        <label>
                                                <span class="title"><?php echo _x('URL', 'inline link editor'); ?></span>
                                                <span class="blc-input-text-wrap"><input type="text" name="link_url" value="" class="blc-link-url-field" /></span>
                                        </label>

                                        <div class="blc-url-replacement-suggestions" style="display: none;">
                                                <h4><?php echo _x('Suggestions', 'inline link editor'); ?></h4>
                                                <ul class="blc-suggestion-list">
                                                        <li>...</li>
                                                </ul>
                                        </div>

                                        <div class="submit blc-inline-editor-buttons">
                                                <input type="button" class="button-secondary cancel alignleft mwp-blc-cancel-button" value="<?php echo esc_attr(__('Cancel')); ?>" />
                                                <input type="button" class="button-primary save alignright mwp-blc-update-link-button" value="<?php echo esc_attr(__('Update')); ?>" />

                                                <img class="waiting" style="display:none;" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" />
                                                <div class="clear"></div>
                                        </div>
                                </div>
                        </td>
                </tr>
        </tbody></table>

        <ul id="blc-suggestion-template" style="display: none;">
                <li>
                        <input type="button" class="button-secondary blc-use-url-button" value="<?php echo esc_attr(__('Use this URL')); ?>" />

                        <div class="blc-suggestion-details">
                                <span class="blc-suggestion-name">
                                        <a href="http://example.com/" target="_blank">Suggestion name</a>
                                </span>
                                <code class="blc-suggestion-url">suggestion URL</code>
                        </div>
                </li>
        </ul>
        <?php
    }

    public function get_option($key = null, $default = '') {
        if (isset($this->option[$key]))
            return $this->option[$key];
        return $default;
    }
    
    public function set_option($key, $value) {
    $this->option[$key] = $value;
    return update_option($this->option_handle, $this->option);
    }

    public static function MainWPLinksCheckerQSG() {
        ?>
         <div  class="mainwp_info-box" id="ps-pth-notice-box"><b><?php echo __("Need Help?"); ?></b> <?php echo __("Review the Extension"); ?> <a href="http://docs.mainwp.com/category/mainwp-extensions/mainwp-page-speed-extension/" target="_blank"><?php echo __('Documentation'); ?></a>. 
                    <a href="#" id="mainwp-lc-quick-start-guide"><?php _e('Show Quick Start Guide','mainwp'); ?></a></div>
                    <div  class="mainwp_info-box-yellow" id="mainwp-lc-tips" style="color: #333!important; text-shadow: none!important;">
                      <span><a href="#" class="mainwp-show-tut" number="1"><?php _e('Tut 1','mainwp') ?></a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="#" class="mainwp-show-tut"  number="2"><?php _e('Tut 2','mainwp') ?></a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="#" class="mainwp-show-tut"  number="3"><?php _e('Tut 3','mainwp') ?></a></span><span><a href="#" id="mainwp-lc-tips-dismiss" style="float: right;"><?php _e('Dismiss','mainwp'); ?></a></span>
                      <div class="clear"></div>
                      <div id="mainwp-lc-tuts">
                        <div class="mainwp-lc-tut" number="1">
                            <h3>Tut 1</h3>
                        </div>
                        <div class="mainwp-lc-tut"  number="2">
                            <h3>Tut 2</h3>
                            
                        </div>
                        <div class="mainwp-lc-tut"  number="3">
                            <h3>Tut 3</h3>
                        </div>
                      </div>
                    </div>
        <?php
}

}
