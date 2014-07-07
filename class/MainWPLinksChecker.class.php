<?php
class MainWPLinksChecker
{   
    public static $instance = null;
    protected $option_handle = 'mainwp_linkschecker_options';
    protected $option;
      
    static function Instance()
    {
        if (MainWPLinksChecker::$instance == null) MainWPLinksChecker::$instance = new MainWPLinksChecker();
        return MainWPLinksChecker::$instance;
    }

    public function __construct() {
        $this->option = get_option($this->option_handle);
        add_filter('mainwp_managesites_column_url', array(&$this, 'managesites_column_url'), 10, 2);                
    }
    
    public function init() {        
        add_action('mainwp-site-synced', array(&$this, 'site_synced'), 10, 1);  
        add_action('mainwp_delete_site', array(&$this, 'mwp_delete_site'), 10, 1);
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
        $location = "tools.php?page=view-broken-links&filter_id=all";                           
        $actions['linkschecker'] = '<a href="admin.php?page=SiteOpen&newWindow=yes&websiteid=' . $websiteid . '&location=' . base64_encode($location) . '" target="_blank">' . __("Check Links") . '</a>';
        return $actions; 
    }
    
    public function linkschecker_sync_data($website_id) {
        $linkschecker = MainWPLinksCheckerDB::Instance()->getLinkscheckerBy('site_id', $website_id);   
        $post_data = array('mwp_action' => 'sync_data'); 
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
            $update = array('id' => ($linkschecker && $linkschecker->id) ? $linkschecker->id : 0,                                                                     
                            'site_id' => $website_id,                                
                            'link_info' => serialize($link_info),
                            'active' => 1
                            );  
            $out = MainWPLinksCheckerDB::Instance()->updateLinkschecker($update);    
            //print_r($out);
            //print_r($update);
            //print_r($information);
        } else {
             MainWPLinksCheckerDB::Instance()->updateLinkschecker(array('site_id' => $website_id, 'active' => 0));     
        }
    }
  
    public static function renderMetabox() {
        if (isset($_GET['page']) && $_GET['page'] == "managesites") {        
            self::childsite_metabox();
        } else {
            self::network_metabox();
        }
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
        if (is_array($links_info) && $lc->active) {
            $broken = isset($links_info['broken']) ? $links_info['broken'] : 0; 
            $redirects = isset($links_info['redirects']) ? $links_info['redirects'] : 0;
            $dismissed = isset($links_info['dismissed']) ? $links_info['dismissed'] : 0;
            $all = isset($links_info['all']) ? $links_info['all'] : 0;            
            
            $location2 = "tools.php?page=view-broken-links&filter_id=broken";                   
            $broken_link = '<span class="edit"><a href="admin.php?page=SiteOpen&newWindow=yes&websiteid=' . $site_id . '&location=' . base64_encode($location2) . '" target="_blank">' . $broken . '</a></span>';

            $location3 = "tools.php?page=view-broken-links&filter_id=redirects";                   
            $redirects_link = '<span class="edit"><a href="admin.php?page=SiteOpen&newWindow=yes&websiteid=' . $site_id . '&location=' . base64_encode($location3) . '" target="_blank">' . $redirects . '</a></span>';

            $location4 = "tools.php?page=view-broken-links&filter_id=dismissed";                   
            $dismissed_link = '<span class="edit"><a href="admin.php?page=SiteOpen&newWindow=yes&websiteid=' . $site_id . '&location=' . base64_encode($location4) . '" target="_blank">' . $dismissed . '</a></span>';

            $location5 = "tools.php?page=view-broken-links&filter_id=all";                   
            $all_link = '<span class="edit"><a href="admin.php?page=SiteOpen&newWindow=yes&websiteid=' . $site_id . '&location=' . base64_encode($location5) . '" target="_blank">' . $all . '</a></span>';                
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
    
    public static function render() {              
        self::renderTabs();
    }
    
    public static function renderTabs() {           
        
        $style_tab_dashboard = $style_tab_settings = ' style="display: none" ';        
        if (isset($_GET['action']) && $_GET['action'] == "setting") {
            $style_tab_settings = ""; 
        } else if (isset($_POST['mwp_linkschecker_setting_plugin_submit'])) {                
            $style_tab_settings = "";
        } else {
            $style_tab_dashboard = "";
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
              
        $selected_group = 0;       
        
        if(isset($_POST['mainwp_linkschecker_groups_select'])) {
            $selected_group = intval($_POST['mainwp_linkschecker_groups_select']);            
        } 
        $linkschecker_data = array();
        $results = MainWPLinksCheckerDB::Instance()->getLinkscheckerBy('all');
        foreach ($results as $value) {
            if (!empty($value->site_id))
                $linkschecker_data[$value->site_id] = MainWPLinksCheckerUtility::mapSite($value, array('hide_plugin', 'link_info'));
        }
//        print_r($linkschecker_data);
//        print_r($results);
        $dbwebsites_linkschecker = MainWPLinksCheckerDashboard::Instance()->get_websites_linkschecker($dbwebsites, $selected_group, $linkschecker_data);         
        //print_r($dbwebsites_linkschecker);       
        
        unset($dbwebsites);
           ?>
            <div class="wrap" id="mainwp-ap-option">
            <div class="clearfix"></div>           
            <div class="inside">   
                <div  class="mainwp_error" id="wpps-error-box" ></div>
                <div  class="mainwp_info-box-yellow hidden-field" id="wpps-info-box" ></div>
                <?php self::MainWPLinksCheckerQSG(); ?>
                <h3><?php _e("Broken Links Checker"); ?></h3>
                <div id="mainwp_linkschecker_option">
                    <div class="mainwp_error" id="mwp_lc_ajax_error_box"></div>
                    <div class="clear">                        
                        <div id="mwp_lc_dashboard_tab" <?php echo $style_tab_dashboard; ?>>
                            <div class="tablenav top">
                            <?php MainWPLinksCheckerDashboard::gen_select_boxs($dbwebsites_linkschecker, $selected_group); ?>  
                            <input type="button" class="mainwp-upgrade-button button-primary button" 
                                   value="<?php _e("Sync Data"); ?>" id="dashboard_refresh" style="background-image: none!important; float:right; padding-left: .6em !important;">
                            </div>                            
                            <?php MainWPLinksCheckerDashboard::gen_dashboard_tab($dbwebsites_linkschecker); ?>                            
                        </div>                                                                          
                    </div>
                <div class="clear"></div>
                </div>
            </div>
        </div>              
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
