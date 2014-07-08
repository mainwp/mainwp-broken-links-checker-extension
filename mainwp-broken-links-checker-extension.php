<?php
/*
Plugin Name: MainWP Broken Links Checker Extension
Plugin URI: http://extensions.mainwp.com
Description: MainWP Broken Links Checker Extension.
Version: 0.0.1
Author: MainWP
Author URI: 
Icon URI: http://extensions.mainwp.com/wp-content/uploads/2014/07/mainwp-broken-links-checker-extension.png
*/


class MainWPLinksCheckerExtension
{    
    public  $plugin_handle = "mainwp-links-checker-extension";
    public static $plugin_url;
    public $plugin_slug;
    public $plugin_dir;    
    
    public function __construct()
    {
        $this->plugin_dir = plugin_dir_path(__FILE__);
        self::$plugin_url = plugin_dir_url(__FILE__);
        $this->plugin_slug = plugin_basename(__FILE__);
                
        add_action('init', array(&$this, 'init'));
        add_filter('plugin_row_meta', array(&$this, 'plugin_row_meta'), 10, 2);
        add_action('admin_init', array(&$this, 'admin_init'));
        
        MainWPLinksCheckerDB::Instance()->install();       
        
    }

    public function init()
    {        
        MainWPLinksChecker::Instance()->init();        
    }
 
    public function plugin_row_meta($plugin_meta, $plugin_file)
    {
        if ($this->plugin_slug != $plugin_file) return $plugin_meta;

        $plugin_meta[] = '<a href="?do=checkUpgrade" title="Check for updates.">Check for updates now</a>';
        return $plugin_meta;
    }

    public function admin_init()
    {
        wp_enqueue_style('mainwp-linkschecker-extension', self::$plugin_url . 'css/mainwp-linkschecker.css');
        wp_enqueue_script('mainwp-linkschecker-extension', self::$plugin_url . 'js/mainwp-linkschecker.js');        
        $translation_array = array( 'dashboard_sitename' => get_bloginfo( 'name' ));        
        MainWPLinksChecker::Instance()->admin_init();             
        MainWPLinksCheckerDashboard::Instance()->admin_init();
    }
        
}
 

class MainWPLinksCheckerExtensionActivator
{
    protected $mainwpMainActivated = false;
    protected $childEnabled = false;
    protected $childKey = false;
    protected $childFile;

    public function __construct()
    {
        $this->childFile = __FILE__;        
        add_filter('mainwp-getextensions', array(&$this, 'get_this_extension'));
        $this->mainwpMainActivated = apply_filters('mainwp-activated-check', false);

        if ($this->mainwpMainActivated !== false)
        {
            $this->activate_this_plugin();
        }
        else
        {
            add_action('mainwp-activated', array(&$this, 'activate_this_plugin'));
        }
        add_action('admin_notices', array(&$this, 'mainwp_error_notice'));
        add_filter('mainwp-getmetaboxes', array(&$this, 'getMetaboxes'));
    }

    function get_this_extension($pArray)
    {
        $pArray[] = array('plugin' => __FILE__, /*'api' => 'mainwp-broken-links-checker-extension',*/ 'mainwp' => true, 'callback' => array(&$this, 'settings'));
        return $pArray;
    }
 
    function settings()
    {
        do_action('mainwp-pageheader-extensions', __FILE__);
        if ($this->childEnabled)
        { 
            MainWPLinksChecker::render();
        }
        else
        {
            ?><div class="mainwp_info-box-yellow"><strong><?php _e("The Extension has to be enabled to change the settings."); ?></strong></div><?php
        }
        do_action('mainwp-pagefooter-extensions', __FILE__);
    }
    
    public function getMetaboxes($metaboxes)
    {
        if (!$this->childEnabled) return $metaboxes;
        if (!is_array($metaboxes)) $metaboxes = array();
        $metaboxes[] = array('plugin' => $this->childFile, 'key' => $this->childKey, 'metabox_title' => "MainWP Broken Links Checker", 'callback' =>  array("MainWPLinksChecker", 'renderMetabox'));
        return $metaboxes;
    }
    
    
    function activate_this_plugin()
    {
        $this->mainwpMainActivated = apply_filters('mainwp-activated-check', $this->mainwpMainActivated);

        $this->childEnabled = apply_filters('mainwp-extension-enabled-check', __FILE__);
        if (!$this->childEnabled) return;

        $this->childKey = $this->childEnabled['key'];

        new MainWPLinksCheckerExtension();
    }

    public function getChildKey()
    {
        return $this->childKey;
    }

    public function getChildFile()
    {
        return $this->childFile;
    }

    function mainwp_error_notice()
    {
        global $current_screen;
        if ($current_screen->parent_base == 'plugins' && $this->mainwpMainActivated == false)
        {
            echo '<div class="error"><p>MainWP Broken Links Checker Extension ' . __('requires <a href="http://mainwp.com/" target="_blank">MainWP</a> Plugin to be activated in order to work. Please install and activate <a href="http://mainwp.com/" target="_blank">MainWP</a> first.') . '</p></div>';
        }
    }

}

function mainwp_links_checker_extension_autoload($class_name)
{
    $allowedLoadingTypes = array('class');

    foreach ($allowedLoadingTypes as $allowedLoadingType)
    {
        $class_file = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . str_replace(basename(__FILE__), '', plugin_basename(__FILE__)) . $allowedLoadingType . DIRECTORY_SEPARATOR . $class_name . '.' . $allowedLoadingType . '.php';
        if (file_exists($class_file))
        {
            require_once($class_file);
        }
    }
}

if (function_exists('spl_autoload_register'))
{
    spl_autoload_register('mainwp_links_checker_extension_autoload');
}
else
{
    function __autoload($class_name)
    {
        mainwp_links_checker_extension_autoload($class_name);
    }
}

$mainWPLinksCheckerExtensionActivator = new MainWPLinksCheckerExtensionActivator();
