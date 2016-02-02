<?php
/*
Plugin Name: MainWP Broken Links Checker Extension
Plugin URI: https://mainwp.com
Description: MainWP Broken Links Checker Extension allows you to scan and fix broken links on your child sites. Requires the MainWP Dashboard Plugin.
Version: 0.0.6
Author: MainWP
Author URI: https://mainwp.com
Documentation URI: http://docs.mainwp.com/category/mainwp-extensions/mainwp-broken-links-checker-extension/
Icon URI:
*/

if ( ! defined( 'MAINWP_BROKEN_LINKS_CHECKER_FILE' ) ) {
	define( 'MAINWP_BROKEN_LINKS_CHECKER_FILE', __FILE__ );
}

if ( ! defined( 'MWP_BROKEN_LINKS_CHECKER_DIR' ) ) {
	define( 'MWP_BROKEN_LINKS_CHECKER_DIR', plugin_dir_path( __FILE__ ) ); }

if ( ! defined( 'MWP_BROKEN_LINKS_CHECKER_URL' ) ) {
	define( 'MWP_BROKEN_LINKS_CHECKER_URL', plugin_dir_url( __FILE__ ) ); }


class MainWP_Links_Checker_Extension
{
	public  $plugin_handle = 'mainwp-links-checker-extension';
	public $plugin_slug;

	public function __construct() {

		$this->plugin_slug = plugin_basename( __FILE__ );
		add_action( 'init', array( &$this, 'init' ) );
		add_filter( 'plugin_row_meta', array( &$this, 'plugin_row_meta' ), 10, 2 );
		add_action( 'after_plugin_row', array( &$this, 'after_plugin_row' ), 10, 3 );
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		add_filter( 'mainwp-sync-extensions-options', array( &$this, 'mainwp_sync_extensions_options' ), 10, 1 );
		add_action( 'mainwp_applypluginsettings_mainwp-broken-links-checker-extension', array( MainWP_Links_Checker::get_instance(), 'mainwp_apply_plugin_settings' ), 10, 1 );
		MainWP_Links_Checker_DB::get_instance()->install();

	}

	public function init() {

		MainWP_Links_Checker::get_instance()->init();
	}

	public function plugin_row_meta( $plugin_meta, $plugin_file ) {

		if ( $this->plugin_slug != $plugin_file ) { return $plugin_meta; }

		$slug = basename($plugin_file, ".php");
		$api_data = get_option( $slug. '_APIManAdder');		
		if (!is_array($api_data) || !isset($api_data['activated_key']) || $api_data['activated_key'] != 'Activated' || !isset($api_data['api_key']) || empty($api_data['api_key']) ) {
			return $plugin_meta;
		}
		
		$plugin_meta[] = '<a href="?do=checkUpgrade" title="Check for updates.">Check for updates now</a>';
		return $plugin_meta;
	}

	public function after_plugin_row( $plugin_file, $plugin_data, $status ) {	
		if ( $this->plugin_slug != $plugin_file ) {
			return ;
		}	
		$slug = basename($plugin_file, ".php");
		$api_data = get_option( $slug. '_APIManAdder');
		
		if (!is_array($api_data) || !isset($api_data['activated_key']) || $api_data['activated_key'] != 'Activated'){
			if (!isset($api_data['api_key']) || empty($api_data['api_key'])) {
				?>
				<style type="text/css">
					tr#<?php echo $slug;?> td, tr#<?php echo $slug;?> th{
						box-shadow: none;
					}
				</style>
				<tr class="plugin-update-tr active"><td colspan="3" class="plugin-update colspanchange"><div class="update-message api-deactivate">
				<?php echo (sprintf(__("API not activated check your %sMainWP account%s for updates. For automatic update notification please activate the API.", "mainwp"), '<a href="https://mainwp.com/my-account" target="_blank">', '</a>')); ?>
				</div></td></tr>
				<?php
			}
		}	
	}	
	
	function mainwp_sync_extensions_options($values = array()) {
		$values['mainwp-broken-links-checker-extension'] = array(			
			'plugin_name' => 'Broken Link Checker', 
			'plugin_slug' => 'broken-link-checker/broken-link-checker.php'			
		);
		return $values;
	}
			
	public function admin_init() {

		wp_enqueue_style( 'mainwp-linkschecker-extension', MWP_BROKEN_LINKS_CHECKER_URL . 'css/mainwp-linkschecker.css' );
		wp_enqueue_script( 'mainwp-linkschecker-extension', MWP_BROKEN_LINKS_CHECKER_URL . 'js/mainwp-linkschecker.js' );
		MainWP_Links_Checker::get_instance()->admin_init();
		MainWP_Links_Checker_Dashboard::get_instance()->admin_init();
	}
}


function mainwp_links_checker_extension_autoload( $class_name ) {
	$allowedLoadingTypes = array( 'class' );
	$class_name = str_replace( '_', '-', strtolower( $class_name ) );
	foreach ( $allowedLoadingTypes as $allowedLoadingType ) {
		$class_file = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . str_replace( basename( __FILE__ ), '', plugin_basename( __FILE__ ) ) . $allowedLoadingType . DIRECTORY_SEPARATOR . $class_name . '.' . $allowedLoadingType . '.php';
		if ( file_exists( $class_file ) ) {
			require_once( $class_file );
		}
	}
}

if ( function_exists( 'spl_autoload_register' ) ) {
	spl_autoload_register( 'mainwp_links_checker_extension_autoload' );
} else {
	function __autoload( $class_name ) {

		mainwp_links_checker_extension_autoload( $class_name );
	}
}

register_activation_hook( __FILE__, 'mainwp_blc_activate' );
register_deactivation_hook( __FILE__, 'mainwp_blc_deactivate' );

function mainwp_blc_activate() {
	update_option( 'mainwp_blc_activated', 'yes' );
	$extensionActivator = new MainWP_Links_Checker_Extension_Activator();
	$extensionActivator->activate();	
}

function mainwp_blc_deactivate() {
	$extensionActivator = new MainWP_Links_Checker_Extension_Activator();
	$extensionActivator->deactivate();
}


class MainWP_Links_Checker_Extension_Activator
{
	protected $mainwpMainActivated = false;
	protected $childEnabled = false;
	protected $childKey = false;
	protected $childFile;
	protected $plugin_handle = 'mainwp-broken-links-checker-extension';
	protected $product_id = 'MainWP Broken Links Checker Extension';
	protected $software_version = '0.0.6';


	public function __construct() {

		$this->childFile = __FILE__;
		add_filter( 'mainwp-getextensions', array( &$this, 'get_this_extension' ) );
		$this->mainwpMainActivated = apply_filters( 'mainwp-activated-check', false );

		if ( $this->mainwpMainActivated !== false ) {
			$this->activate_this_plugin();
		} else {
			add_action( 'mainwp-activated', array( &$this, 'activate_this_plugin' ) );
		}
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		add_action( 'admin_notices', array( &$this, 'mainwp_error_notice' ) );
	}

	function get_this_extension( $pArray ) {

		$pArray[] = array( 'plugin' => __FILE__, 'api' => $this->plugin_handle, 'mainwp' => true, 'callback' => array( &$this, 'settings' ),  'apiManager' => true );
		return $pArray;
	}

	function admin_init() {
		if ( get_option( 'mainwp_blc_activated' ) == 'yes' ) {
			delete_option( 'mainwp_blc_activated' );
			wp_redirect( admin_url( 'admin.php?page=Extensions' ) );
			return;
		}
	}

	function settings() {
		do_action( 'mainwp-pageheader-extensions', __FILE__ );		
		MainWP_Links_Checker::render();		
		do_action( 'mainwp-pagefooter-extensions', __FILE__ );
	}

	public function get_metaboxes( $metaboxes ) {
		if ( ! is_array( $metaboxes ) ) { $metaboxes = array(); }
		$metaboxes[] = array( 'plugin' => $this->childFile, 'key' => $this->childKey, 'metabox_title' => 'MainWP Broken Links Checker', 'callback' => array( 'MainWP_Links_Checker', 'render_metabox' ) );
		return $metaboxes;
	}


	function activate_this_plugin() {
		$this->mainwpMainActivated = apply_filters( 'mainwp-activated-check', $this->mainwpMainActivated );
		$this->childEnabled = apply_filters( 'mainwp-extension-enabled-check', __FILE__ );
		$this->childKey = $this->childEnabled['key'];
		if ( function_exists( 'mainwp_current_user_can' )&& ! mainwp_current_user_can( 'extension', 'mainwp-broken-links-checker-extension' ) ) {
			return; 			
		}
		add_filter( 'mainwp-getmetaboxes', array( &$this, 'get_metaboxes' ) );
		new MainWP_Links_Checker_Extension();
	}

	public function get_child_key() {

		return $this->childKey;
	}

	public function get_child_file() {

		return $this->childFile;
	}

	function mainwp_error_notice() {

		global $current_screen;
		if ( $current_screen->parent_base == 'plugins' && $this->mainwpMainActivated == false ) {
			echo '<div class="error"><p>MainWP Broken Links Checker Extension ' . __( 'requires <a href="http://mainwp.com/" target="_blank">MainWP Dashboard Plugin</a> to be activated in order to work. Please install and activate <a href="http://mainwp.com/" target="_blank">MainWP Dashboard Plugin</a> first.' ) . '</p></div>';
		}
	}

	public function update_option( $option_name, $option_value ) {

		$success = add_option( $option_name, $option_value, '', 'no' );

		if ( ! $success ) {
			$success = update_option( $option_name, $option_value );
		}

		 return $success;
	}

	public function activate() {
		$options = array(
		'product_id' => $this->product_id,
							'activated_key' => 'Deactivated',
							'instance_id' => apply_filters( 'mainwp-extensions-apigeneratepassword', 12, false ),
							'software_version' => $this->software_version,
						);
		$this->update_option( $this->plugin_handle . '_APIManAdder', $options );
	}

	public function deactivate() {
		$this->update_option( $this->plugin_handle . '_APIManAdder', '' );
	}
}

$mainWPLinksCheckerExtensionActivator = new MainWP_Links_Checker_Extension_Activator();
