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
        add_action( 'wp_ajax_mainwp_broken_links_checker_edit_link', array($this,'ajax_edit_link') );
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
    
    public function ajax_edit_link(){
        //if (!check_ajax_referer('mwp_blc_edit', false, false)){
                die( json_encode( array(
                    'error' => __("You're not allowed to do that!") 
                 )));
        //}

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
        die(json_encode($information));
    }

    public static function render() {              
        self::renderTabs();
    }
    
    public static function renderTabs() {           
        
        $style_dashboard_tab = $style_broken_links_tab = ' style="display: none" ';        
        if (isset($_GET['action']) && $_GET['action'] == "links") {
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
              
        $selected_group = 0;       
        
        if(isset($_POST['mainwp_linkschecker_groups_select'])) {
            $selected_group = intval($_POST['mainwp_linkschecker_groups_select']);            
        } 
        $linkschecker_data = array();
        $results = MainWPLinksCheckerDB::Instance()->getLinkscheckerBy('all');
        foreach ($results as $value) {          
            if (!empty($value->site_id))
                $linkschecker_data[$value->site_id] = MainWPLinksCheckerUtility::mapSite($value, array('hide_plugin', 'link_info', 'link_data'));
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
                <a id="blc_dashboard_tab_lnk" href="#" class="mainwp_action left <?php  echo (empty($style_dashboard_tab) ? "mainwp_action_down" : ""); ?>"><?php _e("Broken Links Checker Dashboard"); ?></a><a id="blc_broken_links_tab_lnk" href="#" class="mainwp_action right <?php  echo (empty($style_broken_links_tab) ? "mainwp_action_down" : ""); ?>"><?php _e("Broken Links"); ?></a>
                <br /><br />                              
                <div id="blc_dashboard_tab" <?php echo $style_dashboard_tab; ?>> 
                    <div id="mainwp_linkschecker_option">                        
                        <div class="clear">                        
                            <div>
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
                <div id="blc_broken_links_tab" <?php echo $style_broken_links_tab; ?>> 
                    <?php self::gen_broken_links_tab($dbwebsites_linkschecker); ?>
                </div>
            </div>
        </div>              
    <?php
    }
    
    static function gen_broken_links_tab($websites) {
        $all_links = array();        
        foreach($websites as $website) {
            $link_data = $website['link_data'];            
            if (is_array($link_data) && !empty($link_data[0])) {
                $all_links = array_merge($all_links, $link_data);
            }            
        }
     
        ?>
        <div id="mainwp_blc_links_content">
            <table class="wp-list-table widefat fixed posts tablesorter color-code-link-status" id="mainwp_blc_links_table"
                   cellspacing="0">
                <thead>
                <tr>
                    <th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input
                            type="checkbox"></th>
                    <th scope="col" id="title" class="manage-column column-title sortable desc" style="">
                        <a href="#" onclick="return false;"><span><?php _e('URL','mainwp'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" id="status" class="manage-column mwp-column-status sortable desc" style="">
                        <a href="#" onclick="return false;"><span><?php _e('Status','mainwp'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" id="link-text" class="manage-column mwp-column-link-text sortable desc" style="">
                        <a href="#" onclick="return false;"><span><?php _e('Link Text','mainwp'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" id="redirect-url" class="manage-column column-redirect-url sortable desc" style="">
                        <a href="#" onclick="return false;"><span><?php _e('Redirect URL','mainwp'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" id="source" class="manage-column column-source sortable desc" style="">
                        <a href="#" onclick="return false;"><span><?php _e('Source','mainwp'); ?></span><span class="sorting-indicator"></span></a>
                    </th>                                           
                </tr>
                </thead>

                <tfoot>
                <tr>
                    <th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input
                            type="checkbox"></th>
                    <th scope="col" id="title" class="manage-column column-title sortable desc" style="">
                        <a href="#" onclick="return false;"><span><?php _e('URL','mainwp'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" id="status" class="manage-column mwp-column-status sortable desc" style="">
                        <a href="#" onclick="return false;"><span><?php _e('Status','mainwp'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" id="link-text" class="manage-column mwp-column-link-text sortable desc" style="">
                        <a href="#" onclick="return false;"><span><?php _e('Link Text','mainwp'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" id="redirect-url" class="manage-column column-redirect-url sortable desc" style="">
                        <a href="#" onclick="return false;"><span><?php _e('Redirect URL','mainwp'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" id="source" class="manage-column column-source sortable desc" style="">
                        <a href="#" onclick="return false;"><span><?php _e('Source','mainwp'); ?></span><span class="sorting-indicator"></span></a>
                    </th>  
                </tr>
                </tfoot> 
                <tbody class="list:posts">
                <?php 
                    self::renderTableLinksContent($all_links);
                ?>
                </tbody>
            </table>
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
                    </select><span> <?php _e('Articles per page','mainwp'); ?></span>
                </form>
            </div>
            <div class="clear"></div>
        </div>
        <script>mainwp_broken_links_checker_table_reinit();</script>
    <?php
        MainWPLinksChecker::Instance()->inline_editor();
        include_once MWP_BROKEN_LINKS_CHECKER_DIR . 'includes/mwp-links-page-js.php';        
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
        
    static function renderTableLinksContent($links) {
        $rownum = 0;
        foreach($links as $link) { 
            if (empty($link->link_id))
                continue;
            
            $status = self::analyse_status($link); 	
            $str_status = sprintf('<span class="http-code">%s</span> <span class="status-text">%s</span>',
                                            empty($link->http_code)?'':$link->http_code,
                                            $status['text']
                                    );
            //class="blc-row %s" data-days-broken="%d" data-can-edit-url="%d" data-can-edit-text="%d"%s
            $rownum++;

            $rowclass = ($rownum % 2)? 'alternate' : '';
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
                <th class="check-column" scope="row">
                    <input type="checkbox" value="1" name="link[]">
                </th>
                <td class="post-title page-title column-title">
                    <?php self::column_new_url($link); ?>
                </td>
                <td class="status mwp-column-status">
                    <span><?php echo $str_status ?></span>
                <?php
                    //Last checked...
                    if ( $link->last_check != 0 ){
                            $last_check = _x('Checked', 'checked how long ago') . ' ';
                            $last_check .= MainWPLinksCheckerUtility::fuzzy_delta(time() - $link->last_check, 'ago');

                            printf(
                                    '<br><span class="link-last-checked">%s</span>',
                                    $last_check
                            );
                    }
                ?>
                </td>
                <td class="link-text mwp-column-link-text">   
                    <span><?php echo $link_text; ?></span>
                </td>
                <td class="redirect-url column-redirect-url">    
                    <?php 
                        MainWPLinksChecker::Instance()->column_redirect_url($link);
                    ?>
                </td>                
                <td class="source column-source">                  
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

                        if ( array_key_exists(intval($link->http_code), $link->http_status_codes) ){
                                $text = $link->http_status_codes[intval($link->http_code)];
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

            $actions['delete'] = "<span class='delete'><a class='submitdelete blc-unlink-button' title='" . esc_attr( __('Remove this link from all posts') ). "' ".
                            "href='javascript:void(0);'>" . __('Unlink') . "</a>";

            if ( $link->broken ){
                    $actions['discard'] = sprintf(
                            '<span><a href="#" title="%s" class="blc-discard-button">%s</a>',
                            esc_attr(__('Remove this link from the list of broken links and mark it as valid')),
                            __('Not broken')
                    );
            }

            if ( !$link->dismissed && ($link->broken || ($link->redirect_count > 0)) ) {
                    $actions['dismiss'] = sprintf(
                            '<span><a href="#" title="%s" class="blc-dismiss-button">%s</a>',
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

            ?>
            <div class="mwp-blc-url-editor-buttons">
                    <input type="button" class="button-secondary cancel alignleft mwp-blc-cancel-button" value="<?php echo esc_attr(__('Cancel')); ?>" />
                    <input type="button" class="button-primary save alignright blc-update-url-button" value="<?php echo esc_attr(__('Update URL')); ?>" />
                    <img class="waiting" style="display:none;" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" />
            </div>
            <?php
    }

    protected function inline_editor($visible_columns = 6) {
        ?>
        <table style="display: none;"><tbody>
                <tr id="blc-inline-edit-row" class="blc-inline-editor">
                        <td class="blc-colspan-change" colspan="<?php echo $visible_columns; ?>">
                                <div class="blc-inline-editor-content">
                                        <h4><?php echo _x('Edit Link', 'inline editor title'); ?></h4>
                                        <div class="mainwp_info-box-red" id="mwp_blc_edit_link_error_box"></div>    
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
