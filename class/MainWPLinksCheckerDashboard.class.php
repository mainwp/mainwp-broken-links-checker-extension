<?php
class MainWPLinksCheckerDashboard
{    
    private $option_handle = 'mainwp_linkschecker_dashboard_option';
    private $option = array();
   
    private static $order = "";
    private static $orderby = "";
    
    //Singleton
    private static $instance = null;    
    static function Instance()
    {
        if (MainWPLinksCheckerDashboard::$instance == null) {
            MainWPLinksCheckerDashboard::$instance = new MainWPLinksCheckerDashboard();
        }
        return MainWPLinksCheckerDashboard::$instance;
    }
    
    public function __construct() {
        $this->option = get_option($this->option_handle);
    }
    
    public function admin_init() {       
        add_action('wp_ajax_mainwp_linkschecker_upgrade_noti_dismiss', array($this,'dismissNoti'));        
        add_action('wp_ajax_mainwp_linkschecker_active_plugin', array($this,'active_plugin'));
        add_action('wp_ajax_mainwp_linkschecker_upgrade_plugin', array($this,'upgrade_plugin')); 
        add_action('wp_ajax_mainwp_linkschecker_showhide_linkschecker', array($this,'showhide_linkschecker')); 
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
    
    public static function gen_dashboard_tab($websites) {
       $_orderby = "name";    
       $_order = "desc";
       if (isset($_GET['orderby']) && !empty($_GET['orderby'])) {            
           $_orderby = $_GET['orderby'];
       }    
       if (isset($_GET['order']) && !empty($_GET['order'])) {            
           $_order = $_GET['order'];
       }        

       $site_order = $url_order = $broken_order = $redirects_order = $dismissed_order = $all_order = $version_order = $hidden_order ="";           
       if ($_orderby == "site") {            
           $site_order = ($_order == "desc") ? "asc" : "desc";                     
       } else if ($_orderby == "url") {            
           $url_order = ($_order == "desc") ? "asc" : "desc";                     
       } else if ($_orderby == "broken") {
           $broken_order = ($_order == "desc") ? "asc" : "desc";                     
       } else if ($_orderby == "redirects") {
           $redirects_order = ($_order == "desc") ? "asc" : "desc";                     
       } else if ($_orderby == "dismissed") {
           $dismissed_order = ($_order == "desc") ? "asc" : "desc";                     
       } else if ($_orderby == "all") {
           $all_order = ($_order == "desc") ? "asc" : "desc";                     
       } else if ($_orderby == "version") {
           $version_order = ($_order == "desc") ? "asc" : "desc";                     
       } else if ($_orderby == "hidden") {
           $hidden_order = ($_order == "desc") ? "asc" : "desc";                     
       }
               
       self::$order = $_order;
       self::$orderby = $_orderby;        
       usort($websites, array('MainWPLinksCheckerDashboard', "dashboard_data_sort"));        
    
   ?>
       <table id="mainwp-table-plugins" class="wp-list-table widefat plugins" cellspacing="0">
         <thead>
         <tr>
           <th class="check-column">
               <input type="checkbox"  id="cb-select-all-2" >
           </th>
           <th scope="col" class="manage-column sortable <?php echo $site_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=site&order=<?php echo (empty($site_order) ? 'asc' : $site_order); ?>"><span><?php _e('Site','mainwp'); ?></span><span class="sorting-indicator"></span></a>
           </th>
           <th scope="col" class="manage-column sortable <?php echo $url_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=url&order=<?php echo (empty($url_order) ? 'asc' : $url_order); ?>"><span><?php _e('URL','mainwp'); ?></span><span class="sorting-indicator"></span></a>
           </th>            
           <th style="text-align: center;" scope="col" class="manage-column sortable <?php echo $broken_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=broken&order=<?php echo (empty($broken_order) ? 'asc' : $broken_order); ?>"><span><?php _e('Broken','mainwp'); ?></span><span class="sorting-indicator"></span></a>
           </th>       
            <th style="text-align: center;" scope="col" class="manage-column sortable <?php echo $redirects_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=redirects&order=<?php echo (empty($redirects_order) ? 'asc' : $redirects_order); ?>"><span><?php _e('Redirects','mainwp'); ?></span><span class="sorting-indicator"></span></a>
           </th>  
           <th style="text-align: center;" scope="col" class="manage-column sortable <?php echo $dismissed_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=dismissed&order=<?php echo (empty($dismissed_order) ? 'asc' : $dismissed_order); ?>"><span><?php _e('Dismissed','mainwp'); ?></span><span class="sorting-indicator"></span></a>
           </th> 
           <th style="text-align: center;" scope="col" class="manage-column sortable <?php echo $all_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=all&order=<?php echo (empty($all_order) ? 'asc' : $all_order); ?>"><span><?php _e('All','mainwp'); ?></span><span class="sorting-indicator"></span></a>
           </th>           
           <th style="text-align: center;" scope="col" class="manage-column sortable <?php echo $version_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=version&order=<?php echo (empty($version_order) ? 'asc' : $version_order); ?>"><span><?php _e('Plugin Version','mainwp'); ?></span><span class="sorting-indicator"></span></a>
           </th>           
           <th style="text-align: center;" scope="col" class="manage-column <?php echo $hidden_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=hidden&order=<?php echo (empty($hidden_order) ? 'asc' : $hidden_order); ?>"><span><?php _e('Plugin Hidden','mainwp'); ?></span><span class="sorting-indicator"></span></a>
           </th>
         </tr>
         </thead>
         <tfoot>
         <tr>
           <th class="check-column">
               <input type="checkbox"  id="cb-select-all-2" >
           </th>
           <th scope="col" class="manage-column sortable <?php echo $site_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=site&order=<?php echo (empty($site_order) ? 'asc' : $site_order); ?>"><span><?php _e('Site','mainwp'); ?></span><span class="sorting-indicator"></span></a>
           </th>
           <th scope="col" class="manage-column sortable <?php echo $url_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=url&order=<?php echo (empty($url_order) ? 'asc' : $url_order); ?>"><span><?php _e('URL','mainwp'); ?></span><span class="sorting-indicator"></span></a>
           </th>            
           <th style="text-align: center;" scope="col" class="manage-column sortable <?php echo $broken_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=broken&order=<?php echo (empty($broken_order) ? 'asc' : $broken_order); ?>"><span><?php _e('Broken','mainwp'); ?></span><span class="sorting-indicator"></span></a>
           </th>             
           <th style="text-align: center;" scope="col" class="manage-column sortable <?php echo $redirects_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=redirects&order=<?php echo (empty($redirects_order) ? 'asc' : $redirects_order); ?>"><span><?php _e('Redirects','mainwp'); ?></span><span class="sorting-indicator"></span></a>
           </th>             
           <th style="text-align: center;" scope="col" class="manage-column sortable <?php echo $dismissed_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=dismissed&order=<?php echo (empty($dismissed_order) ? 'asc' : $dismissed_order); ?>"><span><?php _e('Dismissed','mainwp'); ?></span><span class="sorting-indicator"></span></a>
           </th> 
           <th style="text-align: center;" scope="col" class="manage-column sortable <?php echo $all_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=all&order=<?php echo (empty($all_order) ? 'asc' : $all_order); ?>"><span><?php _e('All','mainwp'); ?></span><span class="sorting-indicator"></span></a>
           </th>           
           <th style="text-align: center;" scope="col" class="manage-column sortable <?php echo $version_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=version&order=<?php echo (empty($version_order) ? 'asc' : $version_order); ?>"><span><?php _e('Plugin Version','mainwp'); ?></span><span class="sorting-indicator"></span></a>
           </th>           
           <th style="text-align: center;" scope="col" class="manage-column <?php echo $hidden_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=hidden&order=<?php echo (empty($hidden_order) ? 'asc' : $hidden_order); ?>"><span><?php _e('Plugin Hidden','mainwp'); ?></span><span class="sorting-indicator"></span></a>
           </th>
         </tr>
         </tfoot>
           <tbody id="the-mwp-linkschecker-list">
            <?php 
            if (is_array($websites) && count($websites) > 0) {                
               self::getDashboardTableRow($websites);                  
            } else {
               _e("<tr><td colspan=\"6\">No websites were found with the Broken Link Checker plugin installed.</td></tr>");
            }
            ?>
           </tbody>
     </table>
   <?php
   }

    public static function getDashboardTableRow($websites) {   
       $dismiss = array();
       if (session_id() == '') session_start();   
       if (isset($_SESSION['mainwp_linkschecker_dismiss_upgrade_plugin_notis'])) {
           $dismiss = $_SESSION['mainwp_linkschecker_dismiss_upgrade_plugin_notis'];
       }                
       if (!is_array($dismiss))
           $dismiss = array(); 
       
       $url_loader = plugins_url('images/loader.gif', dirname(__FILE__));
       
       foreach ($websites as $website) {    
           //print_r($website);
           $website_id = $website['id'];           
           $cls_active = (isset($website['linkschecker_active']) && !empty($website['linkschecker_active'])) ? "active" : "inactive";
           $cls_update = (isset($website['linkschecker_upgrade'])) ? "update" : "";
           $cls_update = ($cls_active == "inactive") ? "update" : $cls_update;
           $showhide_action = ($website['hide_linkschecker'] == 1) ? 'show' : 'hide';           
           
           $showhide_link = $open_link = $invalid_link = $not_found_mess = "";
           $broken_link = $redirects_link = $dismissed_link = $all_link = "";
           
           if (isset($website['linkschecker_active']) && $website['linkschecker_active']) {
                $location = "options-general.php?page=link-checker-settings";                   
                $open_link = ' | <span class="edit"><a href="admin.php?page=SiteOpen&newWindow=yes&websiteid=' . $website_id . '&location=' . base64_encode($location) . '" target="_blank">' . __("Broken Link Checker Settings") . '</a></span>';
                
                $location2 = "tools.php?page=view-broken-links&filter_id=broken";                   
                $broken_link = '<span class="edit"><a href="admin.php?page=SiteOpen&newWindow=yes&websiteid=' . $website_id . '&location=' . base64_encode($location2) . '" target="_blank">' . $website['broken'] . '</a></span>';
                
                $location3 = "tools.php?page=view-broken-links&filter_id=redirects";                   
                $redirects_link = '<span class="edit"><a href="admin.php?page=SiteOpen&newWindow=yes&websiteid=' . $website_id . '&location=' . base64_encode($location3) . '" target="_blank">' . $website['redirects'] . '</a></span>';
                
                $location4 = "tools.php?page=view-broken-links&filter_id=dismissed";                   
                $dismissed_link = '<span class="edit"><a href="admin.php?page=SiteOpen&newWindow=yes&websiteid=' . $website_id . '&location=' . base64_encode($location4) . '" target="_blank">' . $website['dismissed'] . '</a></span>';
                
                $location5 = "tools.php?page=view-broken-links&filter_id=all";                   
                $all_link = '<span class="edit"><a href="admin.php?page=SiteOpen&newWindow=yes&websiteid=' . $website_id . '&location=' . base64_encode($location5) . '" target="_blank">' . $website['all'] . '</a></span>';
                
                $showhide_link = ' | <a href="#" class="linkschecker_showhide_plugin" showhide="' . $showhide_action . '">'. ($showhide_action === "show" ? __('Show Broken Link Checker Plugin') : __('Hide Broken Link Checker Plugin')) . '</a>';
           }
           
           $cls_update = !empty($invalid_link) || !empty($not_found_mess) ? "update" : $cls_update;
           
           ?>
           <tr class="<?php echo $cls_active . " " . $cls_update; ?>" website-id="<?php echo $website_id; ?>">
               <th class="check-column">
                   <input type="checkbox"  name="checked[]">
               </th>
               <td>
                   <a href="admin.php?page=managesites&dashboard=<?php echo $website_id; ?>"><?php echo $website['name']; ?></a><br/>
                   <div class="row-actions"><span class="dashboard"><a href="admin.php?page=managesites&dashboard=<?php echo $website_id; ?>"><?php _e("Dashboard");?></a></span> |  <span class="edit"><a href="admin.php?page=managesites&id=<?php echo $website_id; ?>"><?php _e("Edit");?></a><?php echo $showhide_link; ?></span></div>                    
                   <div class="linkschecker-action-working"><span class="status" style="display:none;"></span><span class="loading" style="display:none;"><img src="<?php echo $url_loader; ?>"> <?php _e("Please wait..."); ?></span></div>
               </td>
               <td>
                   <a href="<?php echo $website['url']; ?>" target="_blank"><?php echo $website['url']; ?></a><br/>
                   <div class="row-actions"><span class="edit"><a target="_blank" href="admin.php?page=SiteOpen&newWindow=yes&websiteid=<?php echo $website_id; ?>"><?php _e("Open WP-Admin");?></a></span><?php echo $open_link; ?></div>                    
               </td>
              
               <td align="center">
                <?php echo $broken_link; ?> 
               </td> 
               
               <td align="center">
                <?php echo $redirects_link; ?> 
               </td> 
               <td align="center">
                   <?php echo $dismissed_link; ?> 
               </td>
                <td align="center">
                   <?php echo $all_link; ?> 
               </td>
               <td align="center">
               <?php 
                   if (isset($website['linkschecker_plugin_version']))
                       echo $website['linkschecker_plugin_version'];
                   else 
                       echo "&nbsp;";
               ?>
               </td>     
               <td align="center">
                   <span class="plugin_hidden_title"><?php 
                        echo ($website['hide_linkschecker'] == 1) ? __("Yes") : __("No"); 
                   ?>
                </span>
               </td>
           </tr>        
            <?php  
            $active_link = $update_link = $version = $link_row = "";    
            
            if (!isset($dismiss[$website_id])) {                    
                $plugin_slug = "broken-link-checker/broken-link-checker.php";
               if (isset($website['linkschecker_active']) && empty($website['linkschecker_active']))
                   $active_link = '<a href="#" class="linkschecker_active_plugin" >' . __('Activate Broken Link Checker plugin') . '</a>';


               if (isset($website['linkschecker_upgrade'])) { 
                   if (isset($website['linkschecker_upgrade']['new_version']))
                       $version = $website['linkschecker_upgrade']['new_version'];
                   $update_link = ' | <a href="#" class="linkschecker_upgrade_plugin" >' . __('Update Broken Link Checker plugin'). '</a>';
                   if (isset($website['linkschecker_upgrade']['plugin']))
                       $plugin_slug = $website['linkschecker_upgrade']['plugin'];
               }
               
               $link_row = $active_link . $update_link;
               $link_row = ltrim($link_row, ' | ');                
            }
            
            if (!empty($link_row) || !empty($invalid_noti)) {
                 ?>
                 <tr class="plugin-update-tr">
                     <td colspan="9" class="plugin-update">
                         <?php if (!empty($link_row)) { ?>
                         <div class="ext-upgrade-noti update-message" plugin-slug="<?php echo $plugin_slug; ?>" website-id="<?php echo $website_id; ?>" version="<?php echo $version; ?>">
                             <span style="float:right"><a href="#" class="mwp-linkschecker-upgrade-noti-dismiss"><?php _e("Dismiss"); ?></a></span>                    
                             <?php echo $link_row; ?>
                             <span class="linkschecker-row-working"><span class="status"></span><img class="hidden-field" src="<?php echo plugins_url('images/loader.gif', dirname(__FILE__)); ?>"/></span>
                         </div>
                         <?php } ?>      
                         <?php echo $invalid_noti; ?>
                     </td>
                 </tr>
                 <?php  
             }         
       }
   }
        
    public static function dashboard_data_sort($a, $b) {    
           
        if (self::$orderby == "version") {
            $a = $a['linkschecker_plugin_version'];
            $b = $b['linkschecker_plugin_version'];
            $cmp = version_compare($a, $b);            
        } else if (self::$orderby == "url"){
            $a = $a['url'];
            $b = $b['url'];   
            $cmp = strcmp($a, $b); 
        } else if ( self::$orderby == "broken"  || self::$orderby == "redirects"  || 
                    self::$orderby == "dismissed"  || self::$orderby == "all"){
            $a = $a[self::$orderby];
            $b = $b[self::$orderby];   
            $cmp = $a - $b; 
        } else if (self::$orderby == "hidden"){
            $a = $a['hide_linkschecker'];
            $b = $b['hide_linkschecker'];   
            $cmp = $a - $b; 
        } else {
            $a = $a['name'];
            $b = $b['name'];   
            $cmp = strcmp($a, $b); 
        }     
        if ($cmp == 0)
            return 0;
        
        if (self::$order == 'desc')
            return ($cmp > 0) ? -1 : 1;
        else 
            return ($cmp > 0) ? 1 : -1;                        
    }
    
    public static function get_score_color($score) {  
        if ($score <= 20)
            $color = "red";
        else if ($score > 21 && $score <= 80)
            $color = "yellow";
        else if ($score > 80)
            $color = "green";
        return $color;
    }            
    
    public function get_websites_linkschecker($websites, $selected_group = 0, $linkschecker_data) {                       
        $websites_plugin = array();        
        
        if (is_array($websites) && count($websites)) {
            if (empty($selected_group)) {            
                foreach($websites as $website) {
                    if ($website && $website->plugins != '')  {                         
                        $site = $this->get_linkschecker_site_data($website, $linkschecker_data);
                        if ($site !== false)
                            $websites_plugin[] = $site;
                    }
                }            
            } else {
                global $mainWPLinksCheckerExtensionActivator;
                
                $group_websites = apply_filters('mainwp-getdbsites', $mainWPLinksCheckerExtensionActivator->getChildFile(), $mainWPLinksCheckerExtensionActivator->getChildKey(), array(), array($selected_group));  
                $sites = array();
                foreach($group_websites as $site) {
                    $sites[] = $site->id;
                }                 
                foreach($websites as $website) {
                    if ($website && $website->plugins != '' && in_array($website->id, $sites))  {                                                
                        $site = $this->get_linkschecker_site_data($website, $linkschecker_data);
                        if ($site !== false)
                            $websites_plugin[] = $site;
                    }
                }   
            }
        } 
        
        // if search action
        $search_sites = array();               
        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $find = trim($_GET['s']);
            foreach($websites_plugin as $website ) {                
                if (stripos($website['name'], $find) !== false || stripos($website['url'], $find) !== false) {
                    $search_sites[] = $website;
                }
            }
            $websites_plugin = $search_sites;
        }
        unset($search_sites);        
       
        return $websites_plugin;
    } 
        
    public function get_linkschecker_site_data($website, $linkschecker_data) { 
        $plugins = json_decode($website->plugins, 1);
        if (is_array($plugins) && count($plugins) != 0) {                            
            foreach ($plugins as $plugin)
            {                            
                if ($plugin['slug'] == "broken-link-checker/broken-link-checker.php" || strpos($plugin['slug'], "/broken-link-checker.php") !== false) {                                    
                    $site = MainWPLinksCheckerUtility::mapSite($website, array('id', 'name', 'url' ));
                    if ($plugin['active'])
                        $site['linkschecker_active'] = 1;
                    else 
                        $site['linkschecker_active'] = 0;      
                    
                    $links_data = isset($linkschecker_data[$site['id']]) ? $linkschecker_data[$site['id']] : array();
                    $site['link_data'] = unserialize($links_data['link_data']);
                    $site['hide_linkschecker'] = isset($links_data['hide_plugin']) && $links_data['hide_plugin'] ? 1 : 0;                    
                    $link_info = unserialize($links_data['link_info']);
                    //print_r($links_data);                    
                    $site['broken'] = $site['redirects'] = $site['dismissed'] = $site['all'] = 0;
                    if (is_array($link_info)) {
                        $site['broken'] = $link_info['broken'];
                        $site['redirects'] = $link_info['redirects'];
                        $site['dismissed'] = $link_info['dismissed'];
                        $site['all'] = $link_info['all'];
                    }                        
                    // get upgrade info
                    $site['linkschecker_plugin_version'] = $plugin['version'];
                    $plugin_upgrades = json_decode($website->plugin_upgrades, 1);                                     
                    if (is_array($plugin_upgrades) && count($plugin_upgrades) > 0) {                                        
                        if (isset($plugin_upgrades["broken-link-checker/broken-link-checker.php"])) {
                            $upgrade = $plugin_upgrades["broken-link-checker/broken-link-checker.php"];
                            if (isset($upgrade['update'])) {                                                
                                $site['linkschecker_upgrade'] = $upgrade['update'];                                                
                            }
                        }
                    }                                    
                    return $site;                                    
                    break;                                    
                }
            }
        }
        return false;
    }

    public static function gen_select_boxs($websites, $selected_group) {
        global $mainWPLinksCheckerExtensionActivator;
        $groups = apply_filters('mainwp-getgroups', $mainWPLinksCheckerExtensionActivator->getChildFile(), $mainWPLinksCheckerExtensionActivator->getChildKey(), null);        
        $search = (isset($_GET['s']) && !empty($_GET['s'])) ? trim($_GET['s']) : "";
        ?> 
                   
        <div class="alignleft actions bulkactions">
            <select id="mwp_linkschecker_action">
                <option selected="selected" value="-1"><?php _e("Bulk Actions"); ?></option>
                <option value="activate-selected"><?php _e("Active"); ?></option>
                <option value="update-selected"><?php _e("Update"); ?></option>
                <option value="hide-selected"><?php _e("Hide"); ?></option>
                <option value="show-selected"><?php _e("Show"); ?></option>
            </select>
            <input type="button" value="<?php _e("Apply"); ?>" class="button action" id="mwp_linkschecker_doaction_btn" name="">
        </div>
                   
        <div class="alignleft actions">
            <form action="" method="GET">
                <input type="hidden" name="page" value="Extensions-Mainwp-Broken-Links-Checker-Extension">
                <span role="status" aria-live="polite" class="ui-helper-hidden-accessible"><?php _e('No search results.','mainwp'); ?></span>
                <input type="text" class="mainwp_autocomplete ui-autocomplete-input" name="s" autocompletelist="sites" value="<?php echo stripslashes($search); ?>" autocomplete="off">
                <datalist id="sites">
                    <?php
                    if (is_array($websites) && count($websites) > 0) {
                        foreach ($websites as $website) {                    
                            echo "<option>" . $website['name']. "</option>";                    
                        }
                    }
                    ?>                
                </datalist>
                <input type="submit" name="" class="button" value="Search Sites">
            </form>
        </div>    
        <div class="alignleft actions">
            <form method="post" action="admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension">
                <select name="mainwp_linkschecker_groups_select">
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
                </select>&nbsp;&nbsp;                     
                <input class="button" type="button" name="mwp_linkschecker_btn_display" id="mwp_linkschecker_btn_display"value="<?php _e("Display", "mainwp"); ?>">
            </form>  
        </div>    
        <?php       
        return;
    }
            
    public function dismissNoti() {
        $website_id = intval($_POST['siteId']);        
        if ($website_id) {    
            if (session_id() == '') session_start();   
            $dismiss = $_SESSION['mainwp_linkschecker_dismiss_upgrade_plugin_notis'];
            if (is_array($dismiss) && count($dismiss) > 0) {
                $dismiss[$website_id] = 1;
            } else {
                $dismiss = array();
                $dismiss[$website_id] = 1;
            }
            $_SESSION['mainwp_linkschecker_dismiss_upgrade_plugin_notis'] = $dismiss;
            die('updated');
        }
        die('nochange');
    }
    
    public function dismissInvalidNoti() {
        $website_id = intval($_POST['siteId']);        
        if ($website_id) {    
            if (session_id() == '') session_start();   
            $dismiss = isset($_SESSION['mainwp_linkschecker_dismiss_invalid_api_notis']) ? $_SESSION['mainwp_linkschecker_dismiss_invalid_api_notis'] : array();
            if (is_array($dismiss) && count($dismiss) > 0) {
                $dismiss[$website_id] = 1;
            } else {
                $dismiss = array();
                $dismiss[$website_id] = 1;
            }
            $_SESSION['mainwp_linkschecker_dismiss_invalid_api_notis'] = $dismiss;
            die('updated');
        }
        die('nochange');
    }
    
    public function active_plugin() {
        do_action('mainwp_activePlugin');
        die();
    }
    
    public function upgrade_plugin() {
        do_action('mainwp_upgradePluginTheme');
        die();
    }
    
    public function showhide_linkschecker() {        
        
        $siteid = isset($_POST['websiteId']) ? $_POST['websiteId'] : null;
        $showhide = isset($_POST['showhide']) ? $_POST['showhide'] : null;
        if ($siteid !== null && $showhide !== null) {            
            global $mainWPLinksCheckerExtensionActivator;
            $post_data = array( 'mwp_action' => 'set_showhide',
                                'showhide' => $showhide
                            );
            $information = apply_filters('mainwp_fetchurlauthed', $mainWPLinksCheckerExtensionActivator->getChildFile(), $mainWPLinksCheckerExtensionActivator->getChildKey(), $siteid, 'links_checker', $post_data);			
            if (is_array($information) && isset($information['result']) && $information['result'] === "SUCCESS") {
                $website = apply_filters('mainwp-getsites', $mainWPLinksCheckerExtensionActivator->getChildFile(), $mainWPLinksCheckerExtensionActivator->getChildKey(), $siteid);            
                if ($website && is_array($website)) {
                    $website = current($website);
                }  
                if (!empty($website))                    
                    MainWPLinksCheckerDB::Instance()->updateLinksChecker(array('site_id' => $website['id'], 
                                                            'hide_plugin' => ($showhide === "hide") ? 1 : 0)
                                                        );
            }            
            die(json_encode($information)); 
        }
        die();
    }
       
}
