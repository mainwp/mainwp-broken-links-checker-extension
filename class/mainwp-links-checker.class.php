<?php

if ( ! defined( 'MWP_BLC_LINK_STATUS_UNKNOWN' ) ) {
	define( 'MWP_BLC_LINK_STATUS_UNKNOWN', 'unknown' ); }
if ( ! defined( 'MWP_BLC_LINK_STATUS_OK' ) ) {
	define( 'MWP_BLC_LINK_STATUS_OK', 'ok' ); }
if ( ! defined( 'MWP_BLC_LINK_STATUS_INFO' ) ) {
	define( 'MWP_BLC_LINK_STATUS_INFO', 'info' ); }
if ( ! defined( 'MWP_BLC_LINK_STATUS_WARNING' ) ) {
	define( 'MWP_BLC_LINK_STATUS_WARNING', 'warning' ); }
if ( ! defined( 'MWP_BLC_LINK_STATUS_ERROR' ) ) {
	define( 'MWP_BLC_LINK_STATUS_ERROR', 'error' ); }

class MainWP_Links_Checker
{
	public static $instance = null;
	protected $option_handle = 'mainwp_linkschecker_options';
	protected $option;
	var $current_filters = array();

	var $http_status_codes = array(
		// [Informational 1xx]
		100 => 'Continue',
		101 => 'Switching Protocols',
		// [Successful 2xx]
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		// [Redirection 3xx]
		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		//306=>'(Unused)',
		307 => 'Temporary Redirect',
		// [Client Error 4xx]
		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Requested Range Not Satisfiable',
		417 => 'Expectation Failed',
		// [Server Error 5xx]
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported',
		509 => 'Bandwidth Limit Exceeded',
		510 => 'Not Extended',
	);


	static function get_instance() {

		if ( null == MainWP_Links_Checker::$instance ) { MainWP_Links_Checker::$instance = new MainWP_Links_Checker(); }
		return MainWP_Links_Checker::$instance;
	}

	public function __construct() {
		$this->option = get_option( $this->option_handle );
		if ( isset( $_GET['page'] ) && 'Extensions-Mainwp-Broken-Links-Checker-Extension' == $_GET['page'] ) {
			if ( isset( $_GET['trashed_site_id'] ) && $_GET['trashed_site_id'] ) {
				if ( isset( $_GET['trashed_post_id'] ) && $_GET['trashed_post_id'] ) {
					$this->trashed_container( $_GET['trashed_post_id'], $_GET['trashed_site_id'], true ); } else if ( isset( $_GET['trashed_comment_id'] ) && $_GET['trashed_comment_id'] ) {
					$this->trashed_container( $_GET['trashed_comment_id'], $_GET['trashed_site_id'] );
					}
			}
		}

		add_filter( 'mainwp_managesites_column_url', array( &$this, 'managesites_column_url' ), 10, 2 );
		add_filter( 'set-screen-option', array( $this, 'set_screen_option' ), 10, 3 );

		if (!defined('DOING_AJAX') && isset($_GET['page']) && $_GET['page'] == 'Extensions-Mainwp-Broken-Links-Checker-Extension') {
			if ( get_option( 'mainwp_blc_refresh_count_links_info' ) == 1 ) {
				global $mainWPLinksCheckerExtensionActivator;
				$websites = apply_filters( 'mainwp-getsites', $mainWPLinksCheckerExtensionActivator->get_child_file(), $mainWPLinksCheckerExtensionActivator->get_child_key(), null );
				$all_sites = array();
				if ( is_array( $websites ) ) {
					foreach ( $websites as $website ) {
						$all_sites[] = $website['id'];
					}
				}

				$link_values = MainWP_Links_Checker_DB::get_instance()->get_links_checker_by('all');
				$total = array( 'broken' => 0, 'redirects' => 0, 'dismissed' => 0, 'all' => 0 );
				if ( is_array( $link_values ) ) {
					foreach ( $link_values as $value ) {
						if ( in_array( $value->site_id, $all_sites ) ) {
							$total['broken'] += intval( $value->count_broken );
							$total['redirects'] += intval( $value->count_redirects );
							$total['dismissed'] += intval( $value->count_dismissed );
							$total['all'] += intval( $value->count_total);
						} else {
							MainWP_Links_Checker_DB::get_instance()->delete_links_checker( 'site_id', $value->site_id );
						}
					}
				}
				$this->set_option( 'count_total_links', $total );
				update_option( 'mainwp_blc_refresh_count_links_info', '' );
			}
		}

	}

	public function init() {
                add_filter( 'mainwp-sync-others-data', array( $this, 'sync_others_data' ), 10, 2 );
		add_action( 'mainwp-site-synced', array( &$this, 'site_synced' ), 10, 2 );
                add_filter( 'mainwp_brokenlinks_get_data', array( &$this, 'brokenlinks_get_data' ), 10, 4 ); //ok
		add_action( 'mainwp_delete_site', array( &$this, 'on_delete_site' ), 10, 1 );
		add_action( 'wp_ajax_mainwp_broken_links_checker_edit_link', array( $this, 'ajax_edit_link' ) );
		add_action( 'wp_ajax_mainwp_broken_links_checker_unlink', array( $this, 'ajax_unlink' ) );
		add_action( 'wp_ajax_mainwp_broken_links_checker_dismiss', array( $this, 'ajax_dismiss' ) );
		add_action( 'wp_ajax_mainwp_broken_links_checker_undismiss', array( $this, 'ajax_undismiss' ) );
		add_action( 'wp_ajax_mainwp_broken_links_checker_discard', array( $this, 'ajax_discard' ) );
		add_action( 'wp_ajax_mainwp_broken_links_checker_comment_trash', array( $this, 'ajax_comment_trash' ) );
		add_action( 'wp_ajax_mainwp_broken_links_checker_post_trash', array( $this, 'ajax_post_trash' ) );
		add_action( 'wp_ajax_mainwp_linkschecker_settings_loading_sites', array( $this, 'ajax_settings_loading_sites' ) );
		add_action( 'wp_ajax_mainwp_linkschecker_performsavelinkscheckersettings', array( $this, 'ajax_settings_perform_save' ) );
		add_action( 'wp_ajax_mainwp_linkschecker_settings_recheck_loading', array( $this, 'ajax_settings_recheck_loading' ) );
		add_action( 'wp_ajax_mainwp_linkschecker_load_sites', array( $this, 'ajax_sync_load_sites' ) );
		add_action( 'wp_ajax_mainwp_linkschecker_sync_links_data', array( $this, 'ajax_sync_links_data' ) );
		add_action( 'wp_ajax_mainwp_linkschecker_perform_recheck', array( $this, 'ajax_perform_recheck' ) );
	}

	public static function on_load_page() {
		$screen = get_current_screen();
		if ( ! is_object( $screen ) || $screen->id != 'mainwp_page_Extensions-Mainwp-Broken-Links-Checker-Extension' ) {
			return;
		}

		$args = array(
			'label'   => 'Links per page',
			'default' => 30,
			'option'  => 'mainwp_blc_links_per_page',
		);
		add_screen_option( 'per_page', $args );
	}

	public static function set_screen_option( $status, $option, $value ) {
		if ( 'mainwp_blc_links_per_page' == $option ) {
			return $value;
		}
                // to fix bug
		return $value;
	}


	public function on_delete_site( $website ) {
		if ( $website ) {
			MainWP_Links_Checker_DB::get_instance()->delete_links_checker( 'site_id', $website->id );
		}
	}

	public function managesites_column_url( $actions, $websiteid ) {
		$actions['linkschecker'] = '<a href="admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension&site_id='. $websiteid . '&filter_id=all">' . __( 'Check Links' ) . '</a>';
		return $actions;
	}

	function ajax_sync_links_data() {
		$website_id = $_POST['siteId'];
		$offset = isset($_POST['offset']) ? $_POST['offset'] : 0;
		$first_sync = isset($_POST['first_sync']) && !empty($_POST['first_sync']) ? 1 : 0;

		if ( empty( $website_id ) ) {
			die( json_encode( array( 'error' => 'Error: site id empty' ) ) );
		}

		if ($first_sync) {
			MainWP_Links_Checker_DB::get_instance()->set_start_sync_links($website_id);
		}

		$post_data = array(
                    'mwp_action' => 'sync_links_data',
                    'max_results' => get_option( 'mainwp_blc_max_number_of_links', 50 ),
                    'offset' => $offset
                );

		global $mainWPLinksCheckerExtensionActivator;
		$information = apply_filters( 'mainwp_fetchurlauthed', $mainWPLinksCheckerExtensionActivator->get_child_file(), $mainWPLinksCheckerExtensionActivator->get_child_key(), $website_id, 'links_checker', $post_data );

		if ( is_array( $information ) && isset($information['result'])) {
			if ($first_sync && isset( $information['data'] ) ) {
				$data = $information['data'];
				$update = array(
							'site_id' => $website_id,
							'count_broken' => intval( $data['broken'] ),
							'count_redirects' => intval( $data['redirects'] ),
							'count_dismissed' => intval( $data['dismissed'] ),
							'count_total' => intval( $data['all'] ),
							'active' => 1
						);
				MainWP_Links_Checker_DB::get_instance()->update_links_checker( $update );
				update_option( 'mainwp_blc_refresh_count_links_info', 1 );
			}

			if (isset( $information['links_data'] ) && is_array( $information['links_data'] ) && count( $information['links_data'] ) > 0 ) {
				MainWP_Links_Checker_DB::get_instance()->update_links_data( $website_id, $information['links_data'] );
				unset($information['links_data']);
			}

			if (isset($information['last_sync']) && $information['last_sync']) {
				MainWP_Links_Checker_DB::get_instance()->clean_missing_links($website_id);
			}
		}
		die( json_encode( $information));
	}

        public function sync_others_data( $data, $pWebsite = null ) {
		if ( ! is_array( $data ) ) {
                    $data = array();
                }
		$data['syncBrokenLinksCheckerData'] = 1;
		return $data;
	}

	public function site_synced( $website, $information = array()) {

                $activated = false;
		$plugins = json_decode( $website->plugins, 1 );
		if ( is_array( $plugins ) && count( $plugins ) != 0 ) {
			foreach ( $plugins as $plugin ) {
				if ( 'broken-link-checker/broken-link-checker.php' == $plugin['slug'] || false !== strpos( $plugin['slug'], '/broken-link-checker.php' ) ) {
					if ($plugin['active']) {
						$activated = true;
						break;
					}

				}
			}
		}

		$website_id = $website->id;

		// do not sync links data
		if (!$activated) {
                    MainWP_Links_Checker_DB::get_instance()->update_links_checker( array( 'site_id' => $website_id, 'active' => 0 ) );
                    return;
		}
		$update = array(
                    'site_id' => $website_id,
                    'active' => 1
                );

                if ( is_array( $information ) && isset( $information['syncBrokenLinksCheckerData'] ) && is_array( $information['syncBrokenLinksCheckerData'] ) && isset( $information['syncBrokenLinksCheckerData']['data'] )) {
                    $data = $information['syncBrokenLinksCheckerData']['data'];
                    $update['count_broken'] = intval( $data['broken'] );
                    $update['count_redirects'] = intval( $data['redirects'] );
                    $update['count_dismissed'] = intval( $data['dismissed'] );
                    $update['count_total'] = intval( $data['all'] );
                }

                MainWP_Links_Checker_DB::get_instance()->update_links_checker( $update );
		update_option( 'mainwp_blc_refresh_count_links_info', 1 );

	}

         function brokenlinks_get_data( $input, $site_id ) {
		if ( empty( $site_id ) ) {
                    return $input;
                }
		global $mainWPLinksCheckerExtensionActivator;
		$websites = apply_filters( 'mainwp-getsites', $mainWPLinksCheckerExtensionActivator->get_child_file(), $mainWPLinksCheckerExtensionActivator->get_child_key(), $site_id );
                $website = null;
                if ( is_array( $websites ) ) {
                    $website = current( $websites );
		}
		if ( ! $website ) {
                    return $input;
                }

                $data = MainWP_Links_Checker_DB::get_instance()->get_links_checker_by( 'site_id', $site_id );

                if (is_object( $data ) ) {
                    $input['brokenlinks.links.broken'] = intval($data->count_broken);
                    $input['brokenlinks.links.redirect'] = intval($data->count_redirects);
                    $input['brokenlinks.links.dismissed'] = intval($data->count_dismissed);
                    $input['brokenlinks.links.all'] = intval($data->count_total);
		}
		return $input;
	}

	public function sync_links_data( $website ) {
		$activated = false;
		$plugins = json_decode( $website->plugins, 1 );
		if ( is_array( $plugins ) && count( $plugins ) != 0 ) {
			foreach ( $plugins as $plugin ) {
				if ( 'broken-link-checker/broken-link-checker.php' == $plugin['slug'] || false !== strpos( $plugin['slug'], '/broken-link-checker.php' ) ) {
					if ($plugin['active']) {
						$activated = true;
						break;
					}

				}
			}
		}

		$website_id = $website->id;

		// do not sync links data
		if (!$activated) {
			MainWP_Links_Checker_DB::get_instance()->update_links_checker( array( 'site_id' => $website_id, 'active' => 0 ) );
			return;
		}

		$post_data = array(
                                'mwp_action' => 'sync_data',
                                'max_results' => get_option( 'mainwp_blc_max_number_of_links', 50 )
                            );

		global $mainWPLinksCheckerExtensionActivator;
		$information = apply_filters( 'mainwp_fetchurlauthed', $mainWPLinksCheckerExtensionActivator->get_child_file(), $mainWPLinksCheckerExtensionActivator->get_child_key(), $website_id, 'links_checker', $post_data );
		//print_r($information);
		if ( is_array( $information ) && isset( $information['data'] ) && is_array( $information['data'] ) ) {
			$data = $information['data'];
			$update = array(
                                    'site_id' => $website_id,
                                    'count_broken' => intval( $data['broken'] ),
                                    'count_redirects' => intval( $data['redirects'] ),
                                    'count_dismissed' => intval( $data['dismissed'] ),
                                    'count_total' => intval( $data['all'] ),
                                    'active' => 1
                                );
			MainWP_Links_Checker_DB::get_instance()->update_links_checker( $update );
//			if (isset($data['link_data'])) {
//				MainWP_Links_Checker_DB::get_instance()->update_links_data( $website_id, $data['link_data'] );
//			}
		} else {
			 MainWP_Links_Checker_DB::get_instance()->update_links_checker( array( 'site_id' => $website_id, 'active' => 0 ) );
		}
		update_option( 'mainwp_blc_refresh_count_links_info', 1 );
	}

	public function trashed_container( $container_id, $site_id, $is_post = false ) {
		if ( empty( $container_id ) || empty( $site_id ) ) {
			return;
		}
		$lnks_data = MainWP_Links_Checker_DB::get_instance()->get_filter_links( array($site_id) );
		if ( is_array( $lnks_data ) ) {
			$new_lnks_data = array();
			foreach ( $lnks_data as $link ) {
				$extra = unserialize(base64_decode($link->extra_info));
				if ( ! $is_post ) {
					if ( ($extra['source_data']  && $extra['source_data']['container_anypost']) || $extra['container_id'] != $container_id ) {
						$new_lnks_data[] = $link;
					}
				} else {
					if ( ! $extra['source_data'] || ! $extra['source_data']['container_anypost'] || $extra['container_id'] != $container_id ) {
						$new_lnks_data[] = $link;
					}
				}
			}
			$data_update = $this->get_count_info( $new_lnks_data );
			$data_update['site_id'] = $site_id;
			MainWP_Links_Checker_DB::get_instance()->update_links_checker( $data_update );
			MainWP_Links_Checker_DB::get_instance()->update_links_data( $site_id, $new_lnks_data );
		}
	}

	public static function render_metabox() {
		if ( isset( $_GET['page'] ) && 'managesites' == $_GET['page'] ) {
			self::childsite_metabox();
		} else {
			self::network_metabox();
		}
	}

	function ajax_comment_trash() {

		if ( ! check_ajax_referer( 'mwp_blc_trash_comment', false, false ) ) {
			die( json_encode( array(
				 'error' => __( "You're not allowed to do that!", 'mainwp-broken-links-checker-extension' ),
			 )));
		}

		MainWPRecentComments::trash();
		die();
	}

	function ajax_post_trash() {

		if ( ! check_ajax_referer( 'mwp_blc_trash_post', false, false ) ) {
			die( json_encode( array(
				 'error' => __( "You're not allowed to do that!", 'mainwp-broken-links-checker-extension' ),
			 )));
		}
		 MainWPRecentPosts::trash();
		die();
	}

	function ajax_settings_loading_sites() {

		$check_threshold = intval( $_POST['check_threshold'] );
		if ( $check_threshold <= 0 ) {
			die( json_encode( array( 'error' => __( 'Every hour value must not be empty.', 'mainwp-broken-links-checker-extension' ) ) ) );
		} else {
			$this->set_option( 'check_threshold', $check_threshold );
		}
		update_option( 'mainwp_blc_max_number_of_links', intval( $_POST['max_number_of_links'] ) );
		$this->do_load_sites('save_settings');

	}

	function ajax_settings_recheck_loading() {
		$this->do_load_sites('recheck');
	}

	function ajax_sync_load_sites() {
		$this->do_load_sites('sync' , true, true);
	}

	function do_load_sites( $what = 'save_settings',  $with_postbox = false, $check_site_ids = false) {
		global $mainWPLinksCheckerExtensionActivator;

        if ($check_site_ids) {
            $sites_ids = isset($_POST['siteids']) ? $_POST['siteids'] : false;
            if ( empty($sites_ids) || !is_array( $sites_ids ) ) {
                die( json_encode( array( 'error' => __( 'Invalid site ids data. Please try again.', 'mainwp-broken-links-checker-extension' ) ) ) );
            }
        } else {
            $websites = apply_filters( 'mainwp-getsites', $mainWPLinksCheckerExtensionActivator->get_child_file(), $mainWPLinksCheckerExtensionActivator->get_child_key(), null );
            $sites_ids = array();
            if ( is_array( $websites ) ) {
                foreach ( $websites as $website ) {
                    $sites_ids[] = $website['id'];
                }
                unset( $websites );
            }
        }

		$option = array(
			'plugin_upgrades' => true,
			'plugins' => true,
		);
		$dbwebsites = apply_filters( 'mainwp-getdbsites', $mainWPLinksCheckerExtensionActivator->get_child_file(), $mainWPLinksCheckerExtensionActivator->get_child_key(), $sites_ids, array(), $option );
		$dbwebsites_activate_links = MainWP_Links_Checker_Dashboard::get_instance()->get_websites_linkschecker( $dbwebsites, 0, '', array(), true );

		unset( $dbwebsites );

		$html = '';
		$title = '';

		if ($what == 'save_settings') {
			$title = __('Saving settings to child sites ...', 'mainwp-broken-links-checker-extension' );
		} else if ($what == 'recheck') {
			$title = __('Rechecking on child sites ...', 'mainwp-broken-links-checker-extension' );
		} else if ($what == 'sync') {
			$title = __('Syncing links data on child sites ...', 'mainwp-broken-links-checker-extension' );
		}

		if ($with_postbox) {
			$html .= '<br/><div class="postbox">';
			$html .= '<h3 class="mainwp_box_title"><span><i class="fa fa-cog"></i> ' . $title . '</span></h3>';
			$html .= '<div class="inside">';
		} else {
			if (!empty($title)) {
				$html .= '<h2>' . esc_html($title) . '</h2><br/>';
			}
		}

		//print_r($dbwebsites);
		if ( is_array( $dbwebsites_activate_links ) && count( $dbwebsites_activate_links ) > 0 ) {
			foreach ( $dbwebsites_activate_links as $site ) {
				$html .= '<div class="mainwpProccessSitesItem" status="queue" siteid="' . $site['id'] . '"><strong>' . stripslashes( $site['name'] ) . '</strong>: <span class="status"></span></div>';
			}
			if ($with_postbox) {
				$html .= '</div>';
				$html .= '</div>';
			}
			die( json_encode( array( 'success' => true, 'result' => $html ) ) );
		} else {
			die( json_encode( array( 'error' => __( 'There are not sites with the Broken Link Checker plugin activated.', 'mainwp-broken-links-checker-extension' ) ) ) );
		}

	}

	public function ajax_settings_perform_save() {
		$siteid = $_POST['siteId'];
		if ( empty( $siteid ) ) {
			die( json_encode( array( 'error' => 'Error: site id empty' ) ) );
		}
		$information = $this->perform_save_settings($siteid);
		die( json_encode( $information ) );
	}


	function mainwp_apply_plugin_settings($siteid) {
		$information = $this->perform_save_settings($siteid);
		$return = array();
		if (is_array($information)) {
			if ($information['result'] == 'NOTCHANGE' || $information['result'] == 'SUCCESS') {
				$return = array('result' => 'success');
			} else if ($information['error']) {
				$return = array('error' => $information['error']);
			} else {
				$return = array('result' => 'failed');
			}
		} else {
			$return = array('result' => 'failed');
		}
		die( json_encode( $return ) );
	}

	function perform_save_settings($siteid) {
		global $mainWPLinksCheckerExtensionActivator;
		$check_threshold = MainWP_Links_Checker::get_instance()->get_option( 'check_threshold', 72 );
		$post_data = array( 'mwp_action' => 'save_settings',
							'check_threshold' => $check_threshold
							);
		$information = apply_filters( 'mainwp_fetchurlauthed', $mainWPLinksCheckerExtensionActivator->get_child_file(), $mainWPLinksCheckerExtensionActivator->get_child_key(), $siteid, 'links_checker', $post_data );
		return $information;
	}

	public function ajax_perform_recheck() {

		$siteid = $_POST['siteId'];

		if ( empty( $siteid ) ) {
			die( json_encode( array( 'error' => 'Error: site id empty' ) ) ); }

		global $mainWPLinksCheckerExtensionActivator;

		$post_data = array( 'mwp_action' => 'force_recheck' );
		$information = apply_filters( 'mainwp_fetchurlauthed', $mainWPLinksCheckerExtensionActivator->get_child_file(), $mainWPLinksCheckerExtensionActivator->get_child_key(), $siteid, 'links_checker', $post_data );
		//unset($information['data']);
		die( json_encode( $information ) );
	}


	public static function network_metabox() {

		$lnks_info = MainWP_Links_Checker_DB::get_instance()->get_links_checker_by('all');
		$broken = $redirects = $dismissed = $all = 0;
		foreach ( $lnks_info as $link ) {
			$broken += intval( $link->count_broken );
			$redirects += intval( $link->count_redirects );
			$dismissed += intval( $link->count_dismissed );
			$all += intval( $link->count_total);
		}

		?>
        <div id="mainwp_widget_linkschecker_content" style="margin-top: 1em;">
            <div>
                <span class="mwp_lc_count_links broken"><span class="number"><?php echo $broken . '</span><br/>' . __( 'Broken Links', 'mainwp-broken-links-checker-extension' ); ?></span>
                <span class="mwp_lc_count_links redirects"><span class="number"><?php echo $redirects . '</span><br/>' . __( 'Redirect', 'mainwp-broken-links-checker-extension' ); ?></span>
                <span class="mwp_lc_count_links dismissed"><span class="number"><?php echo $dismissed . '</span><br/>' . __( 'Dismissed', 'mainwp-broken-links-checker-extension' ); ?></span>
                <span class="mwp_lc_count_links all"><span class="number"><?php echo $all . '</span><br/>' . __( 'All', 'mainwp-broken-links-checker-extension' ); ?></span>
            </div>
            <br class="clearfix">
            <div class="mwp_network_links_checker_detail"><?php _e( 'Network Links Checker', 'mainwp-broken-links-checker-extension' ); ?></div>
            <div><a href="admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension" class="button button-primary"><?php _e( 'Broken Links Checker Dashboard','mainwp-broken-links-checker-extension' ); ?></a></div>
        </div>
        <?php
	}

	public static function childsite_metabox() {
		$site_id = isset( $_GET['dashboard'] ) ? $_GET['dashboard'] : 0;
		if ( empty( $site_id ) ) {
			return;
		}

		$result = MainWP_Links_Checker_DB::get_instance()->get_links_checker_by( 'site_id', $site_id );
		$broken = $redirects = $dismissed = $all = 0;
		$broken_link = $redirects_link = $dismissed_link = $all_link = '';
		$link_prefix = esc_attr( 'admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension&site_id=' . $site_id . '&filter_id=' );
		if ( $result && $result->active ) {
			$broken = intval( $result->count_broken );
			$redirects = intval( $result->count_redirects );
			$dismissed = intval( $result->count_dismissed );
			$all = intval( $result->count_total );

			$broken_link = '<span class="edit"><a href="' . $link_prefix .'broken" >' . $broken . '</a></span>';
			$redirects_link = '<span class="edit"><a href="' . $link_prefix .'redirects" >' . $redirects . '</a></span>';
			$dismissed_link = '<span class="edit"><a href="' . $link_prefix .'dismissed">' . $dismissed . '</a></span>';
			$all_link = '<span class="edit"><a href="' . $link_prefix .'all">' . $all . '</a></span>';
		}

		?>
        <div id="mainwp_widget_linkschecker_content" style="margin-top: 1em;">
            <?php
			if ( !$result || !$result->active ) {
				echo '<br class="clearfix">';
				echo '<span style="float:left">'. __( 'Broken Link Checker plugin not found or not activated on the website.' ) . '</span>';
				echo '<br class="clearfix">';
				echo '<br class="clearfix">';
			} else {
			?>
            <div>
                <span class="mwp_lc_count_links broken"><span class="number"><?php echo $broken_link . '</span><br/>' . __( 'Broken Links', 'mainwp-broken-links-checker-extension' ); ?></span>
                <span class="mwp_lc_count_links redirects"><span class="number"><?php echo $redirects_link . '</span><br/>' . __( 'Redirect', 'mainwp-broken-links-checker-extension' ); ?></span>
                <span class="mwp_lc_count_links dismissed"><span class="number"><?php echo $dismissed_link . '</span><br/>' . __( 'Dismissed', 'mainwp-broken-links-checker-extension' ); ?></span>
                <span class="mwp_lc_count_links all"><span class="number"><?php echo $all_link . '</span><br/>' . __( 'All', 'mainwp-broken-links-checker-extension' ); ?></span>
            </div>
            <br class="clearfix">
            <?php } ?>
            <div><a href="admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension" class="button button-primary"><?php _e( 'Broken Links Checker Dashboard','mainwp-broken-links-checker-extension' ); ?></a></div>
        </div>
        <?php
	}

	public function ajax_edit_link() {
		if ( ! check_ajax_referer( 'mwp_blc_edit', false, false ) ) {
			die( json_encode( array(
				'error' => __( "You're not allowed to do that!", 'mainwp-broken-links-checker-extension' ),
			 )));
		}

		if ( empty( $_POST['site_id'] ) || empty( $_POST['link_id'] ) || empty( $_POST['new_url'] ) || ! is_numeric( $_POST['link_id'] ) ) {
			die( json_encode( array(
					'error' => __( 'Error : site_id, link_id or new_url not specified' ),
			)));
		}

		$post_data = array(
		'mwp_action' => 'edit_link',
							'link_id' => $_POST['link_id'],
							'new_text' => $_POST['new_text'],
							'new_url' => $_POST['new_url'],
						);
		global $mainWPLinksCheckerExtensionActivator;
		$information = apply_filters( 'mainwp_fetchurlauthed', $mainWPLinksCheckerExtensionActivator->get_child_file(), $mainWPLinksCheckerExtensionActivator->get_child_key(), $_POST['site_id'], 'links_checker', $post_data );
		//print_r($information);
		if ( is_array( $information ) && isset( $information['cnt_okay'] ) && $information['cnt_okay'] > 0 ) {
			$update = $information;
			$update['site_id'] = $_POST['site_id'];
			$this->update_link( $update );
			$information['ui_link_text'] = preg_replace( '/src=".*\/images\/font-awesome\/(.+)/is', 'src="' . MWP_BROKEN_LINKS_CHECKER_URL . '/images/font-awesome/' . '${1}', $information['ui_link_text'] );
		}

		die( json_encode( $information ) );
	}

	public function ajax_unlink() {
		if ( ! check_ajax_referer( 'mwp_blc_unlink', false, false ) ) {
			die( json_encode( array(
				'error' => __( "You're not allowed to do that!" ),
			 )));
		}

		if ( empty( $_POST['site_id'] ) || empty( $_POST['link_id'] ) || ! is_numeric( $_POST['link_id'] ) ) {
			die( json_encode( array(
					'error' => __( 'Error : site_id or link_id not specified' ),
			)));
		}

		$post_data = array(
		'mwp_action' => 'unlink',
							'link_id' => $_POST['link_id'],
							'new_text' => $_POST['new_text'],
						);
		global $mainWPLinksCheckerExtensionActivator;

		$information = apply_filters( 'mainwp_fetchurlauthed', $mainWPLinksCheckerExtensionActivator->get_child_file(), $mainWPLinksCheckerExtensionActivator->get_child_key(), $_POST['site_id'], 'links_checker', $post_data );
		//print_r($information);
		if ( is_array( $information ) && isset( $information['cnt_okay'] ) && $information['cnt_okay'] > 0 ) {
			$update = array();
			$update['link_id'] = $_POST['link_id'];
			$update['site_id'] = $_POST['site_id'];
			$this->update_unlink( $update );
		}
		die( json_encode( $information ) );
	}

	public function ajax_dismiss() {
		if ( ! check_ajax_referer( 'mwp_blc_dismiss', false, false ) ) {
			die( json_encode( array(
				'error' => __( "You're not allowed to do that!" ),
			 )));
		}
		$this->ajax_set_dismissed( true );
		die();
	}

	public function ajax_undismiss() {
		if ( ! check_ajax_referer( 'mwp_blc_undismiss', false, false ) ) {
			die( json_encode( array(
				'error' => __( "You're not allowed to do that!" ),
			 )));
		}
		$this->ajax_set_dismissed( false );
		die();
	}

	public function ajax_set_dismissed( $dismiss ) {

		if ( empty( $_POST['site_id'] ) || empty( $_POST['link_id'] ) || ! is_numeric( $_POST['link_id'] ) ) {
			die( json_encode( array(
					'error' => __( 'Error : site_id or link_id not specified' ),
			)));
		}

		$post_data = array(
		'mwp_action' => 'set_dismiss',
							'dismiss' => $dismiss,
							'link_id' => $_POST['link_id'],
						);

		global $mainWPLinksCheckerExtensionActivator;

		$information = apply_filters( 'mainwp_fetchurlauthed', $mainWPLinksCheckerExtensionActivator->get_child_file(), $mainWPLinksCheckerExtensionActivator->get_child_key(), $_POST['site_id'], 'links_checker', $post_data );
		//print_r($information);
		if ( 'OK' === $information ) {
			$update = array();
			$update['link_id'] = $_POST['link_id'];
			$update['site_id'] = $_POST['site_id'];
			$update['dismiss'] = $dismiss;
			$this->update_link_dismissed( $update );
		}
		die( json_encode( $information ) );
	}

	public function update_link_dismissed( $update ) {
		if ( ! isset( $update['link_id'] ) || empty( $update['link_id'] ) ||
			! isset( $update['site_id'] ) || empty( $update['site_id'] ) ) {
			return false; }

		$site_id = $update['site_id'];
		$lnks_data = MainWP_Links_Checker_DB::get_instance()->get_filter_links( array($site_id) );
		if ( is_array( $lnks_data ) && isset( $update['link_id'] ) && $update['link_id'] ) {
			$new_lnks_data = array();
			foreach ( $lnks_data as $link ) {
				if ( $link->link_id == $update['link_id'] ) {
					$link->dismissed = $update['dismiss'];
				}
				$new_lnks_data[] = $link;
			}

			$data_update = $this->get_count_info( $new_lnks_data );
			$data_update['site_id'] = $site_id;
			MainWP_Links_Checker_DB::get_instance()->update_links_checker( $data_update );
			MainWP_Links_Checker_DB::get_instance()->update_links_data( $site_id, $new_lnks_data );

			return true;
		}
		return false;
	}

	function ajax_discard() {
		if ( ! check_ajax_referer( 'mwp_blc_discard', false, false ) ) {
			die( json_encode( array(
				 'error' => __( "You're not allowed to do that!" ),
			 )));
		}

		if ( empty( $_POST['site_id'] ) || empty( $_POST['link_id'] ) || ! is_numeric( $_POST['link_id'] ) ) {
			die( json_encode( array(
					'error' => __( 'Error : site_id or link_id not specified' ),
			)));
		}
		$post_data = array(
		'mwp_action' => 'discard',
							'link_id' => $_POST['link_id'],
						);

		global $mainWPLinksCheckerExtensionActivator;

		$information = apply_filters( 'mainwp_fetchurlauthed', $mainWPLinksCheckerExtensionActivator->get_child_file(), $mainWPLinksCheckerExtensionActivator->get_child_key(), $_POST['site_id'], 'links_checker', $post_data );
		//print_r($information);
		if ( is_array( $information ) && isset( $information['status'] ) && 'OK' == $information['status'] ) {
			$update = array();
			$update['link_id'] = $_POST['link_id'];
			$update['site_id'] = $_POST['site_id'];
			$update['last_check_attempt'] = $information['last_check_attempt'];
			$this->update_discard( $update );
		}
		die( json_encode( $information ) );

	}

	public function update_discard( $update ) {
		if ( ! isset( $update['link_id'] ) || empty( $update['link_id'] ) ||
			! isset( $update['site_id'] ) || empty( $update['site_id'] ) ) {
			return false; }
		$site_id = $update['site_id'];
		$lnks_data = MainWP_Links_Checker_DB::get_instance()->get_filter_links( array($site_id) );
		if ( is_array( $lnks_data ) && isset( $update['link_id'] ) && $update['link_id'] ) {
			$new_lnks_data = array();
			foreach ( $lnks_data as $link ) {
				if ( $link->link_id == $update['link_id'] ) {
					$link->broken = false;
					$link->false_positive = true;
					$link->last_check_attempt = $update['last_check_attempt'];
					$link->log = __( 'This link was manually marked as working by the user.' );
				}
				$new_lnks_data[] = $link;
			}
			$data_update = $this->get_count_info( $new_lnks_data );
			$data_update['site_id'] = $site_id;
			MainWP_Links_Checker_DB::get_instance()->update_links_checker( $data_update );
			MainWP_Links_Checker_DB::get_instance()->update_links_data( $site_id, $new_lnks_data );


			return true;
		}
		return false;
	}

	public function update_link( $update ) {
		if ( ! isset( $update['new_link_id'] ) || empty( $update['new_link_id'] ) ||
			! isset( $update['site_id'] ) || empty( $update['site_id'] ) ) {
			return false; }
		$site_id = $update['site_id'];
		$lnks_data = MainWP_Links_Checker_DB::get_instance()->get_filter_links( array($site_id) );
		if ( is_array( $lnks_data ) && isset( $update['link_id'] ) && $update['link_id'] ) {
			$new_lnks_data = array();
			foreach ( $lnks_data as $link ) {
				$extra = unserialize(base64_decode($link->extra_info));
				if ( $link->link_id == $update['new_link_id'] ) {
					$link->status_code = $update['status_code'];
					$link->http_code = $update['http_code'];
					$link->url = $update['url'];
					$link->link_text = $update['link_text'];
					$link->last_check = 0;
					$link->broken = 0;
					$extra['data_link_text'] = $update['ui_link_text'];
					$link->extra_info = base64_encode(serialize($extra));
				}
				$new_lnks_data[] = $link;
			}

			$data_update = $this->get_count_info( $new_lnks_data );
			$data_update['site_id'] = $site_id;
			MainWP_Links_Checker_DB::get_instance()->update_links_checker( $data_update );
			MainWP_Links_Checker_DB::get_instance()->update_links_data( $site_id, $new_lnks_data );

			return true;
		}
		return false;
	}

	public function update_unlink( $update ) {
		if ( ! isset( $update['link_id'] ) || empty( $update['link_id'] ) ||
		   ! isset( $update['site_id'] ) || empty( $update['site_id'] ) ) {
			return false; }
		$site_id = $update['site_id'];
		$lnks_data = MainWP_Links_Checker_DB::get_instance()->get_filter_links( array($site_id) );
		if ( is_array( $lnks_data ) && isset( $update['link_id'] ) && $update['link_id'] ) {
			$new_lnks_data = array();
			foreach ( $lnks_data as $link ) {
				if ( $link->link_id !== $update['link_id'] ) {
					$new_lnks_data[] = $link;
				}
			}

			$data_update = $this->get_count_info( $new_lnks_data );
			$data_update['site_id'] = $site_id;
			MainWP_Links_Checker_DB::get_instance()->update_links_checker( $data_update );
			MainWP_Links_Checker_DB::get_instance()->update_links_data( $site_id, $new_lnks_data );
			return true;
		}
		return false;
	}

	function get_count_info( $all_links ) {
		$count_links = array( 'count_broken' => 0, 'count_redirects' => 0, 'count_dismissed' => 0 , 'count_total' => 0);
		foreach ( $all_links as $link ) {
			if ( $link->broken == 1 ) {
				$count_links['count_broken'] += 1; }
			if ( $link->redirect_count > 0 && ! $link->dismissed ) {
				$count_links['count_redirects'] += 1; }
			if ( $link->dismissed == 1 ) {
				$count_links['count_dismissed'] += 1; }
			$count_links['count_total'] += 1;
		}
		update_option( 'mainwp_blc_refresh_count_links_info', 1 );
		return $count_links ;
	}

	public static function render() {
		self::render_tabs();
	}

	public static function render_tabs() {
		$style_dashboard_tab = $style_broken_links_tab = $style_settings_tab = ' style="display: none" ';

		$url_links_tab = 'admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension&tab=links';
		if ( isset( $_POST['mainwp_blc_links_groups_select'] ) || isset( $_REQUEST['blc_select_site'] ) || isset( $_GET['sl'] )  || isset( $_GET['filter_id'] ) ) {
			$style_broken_links_tab = '';
		} else if (isset($_GET['tab'])) {
			if ($_GET['tab'] == 'settings') {
				$style_settings_tab = '';
			} else if ($_GET['tab'] == 'links') {
				$style_broken_links_tab = '';
				$url_links_tab = '#';
			} else{
				$style_dashboard_tab = '';
			}
		} else {
			$style_dashboard_tab = '';
		}

		global $mainWPLinksCheckerExtensionActivator;

		$sites_id = $sites_url = array();
		$websites = apply_filters( 'mainwp-getsites', $mainWPLinksCheckerExtensionActivator->get_child_file(), $mainWPLinksCheckerExtensionActivator->get_child_key(), null );
		if ( is_array( $websites ) ) {
			foreach ( $websites as $website ) {
				$sites_id[] = $website['id'];
				$sites_url[ $website['id'] ] = $website['url'];
			}
		}

		$current_filters = self::get_current_filters();
		$selected_site_ids = self::get_filter_site_ids($websites, $current_filters);


		$per_page = (int) get_user_option( 'mainwp_blc_links_per_page' );
		if ($per_page < 1){
			$per_page = 30;
		} else if ($per_page > 500){
			$per_page = 500;
		}

		$page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
		//Page number must be > 0
		if ($page < 1) $page = 1;
		$offset = ($page-1) * $per_page;

		$params= array(
			'offset' => $offset,
			'max_results' => $per_page,
			'count_only' => true
		);
		//count number of links
		$total_count = MainWP_Links_Checker_DB::get_instance()->get_filter_links($selected_site_ids, $current_filters, $params);
		// to get links
		unset($params['count_only']);
		$search_links_data = self::get_search_links($selected_site_ids, $current_filters, $params);

		$pagination_html = self::gen_pagination($current_filters, $page, $per_page, $total_count  );

		$option = array(
			'plugin_upgrades' => true,
			'plugins' => true,
		);

		$dbwebsites = apply_filters( 'mainwp-getdbsites', $mainWPLinksCheckerExtensionActivator->get_child_file(), $mainWPLinksCheckerExtensionActivator->get_child_key(), $sites_id, array(), $option );

		$selected_dashboard_group = 0;
		if ( isset( $_POST['mainwp_linkschecker_groups_select'] ) ) {
			$selected_dashboard_group = intval( $_POST['mainwp_linkschecker_groups_select'] );
		}

		$results = MainWP_Links_Checker_DB::get_instance()->get_links_checker_by('all');

		$linkschecker_data = array();
		foreach ( $results as $value ) {
			if ( ! empty( $value->site_id ) ) {
				$linkschecker_data[ $value->site_id ] = MainWP_Links_Checker_Utility::map_site( $value, array( 'hide_plugin', 'count_broken', 'count_redirects', 'count_dismissed', 'count_total' ) );
			}
		}

		$search = '';
		if ( isset( $_GET['s'] ) && ! empty( $_GET['s'] ) ) {
			$search = $_GET['s']; }

		$dbwebsites_dashboard_linkschecker = MainWP_Links_Checker_Dashboard::get_instance()->get_websites_linkschecker( $dbwebsites, $selected_dashboard_group, $search, $linkschecker_data );
		$dbwebsites_linkschecker = MainWP_Links_Checker_Dashboard::get_instance()->get_websites_linkschecker( $dbwebsites, 0, '', $linkschecker_data );

		unset( $dbwebsites );
			?>
		<div class="wrap" id="mainwp-ap-option">
			<div class="clearfix"></div>
            <div class="inside">
                <div  class="mainwp_error" id="wpps-error-box" ></div>
                <div  class="mainwp_info-box-yellow hidden-field" id="wpps-info-box" ></div>
                <?php self::render_qsg(); ?>
                <a href="#" data-tab="dashboard" class="blc_tab_lnk mainwp_action left <?php  echo (empty( $style_dashboard_tab ) ? 'mainwp_action_down' : ''); ?>"><?php _e( 'Broken Links Checker Dashboard', 'mainwp-broken-links-checker-extension' ); ?></a><a data-tab="links" href="<?php echo $url_links_tab; ?>" class="blc_tab_lnk mainwp_action mid <?php  echo (empty( $style_broken_links_tab ) ? 'mainwp_action_down' : ''); ?>"><?php _e( 'Broken Links', 'mainwp-broken-links-checker-extension' ); ?></a><a data-tab="settings" href="#" class="blc_tab_lnk mainwp_action right <?php  echo (empty( $style_settings_tab ) ? 'mainwp_action_down' : ''); ?>"><?php _e( 'Settings', 'mainwp-broken-links-checker-extension' ); ?></a>
                <br />
                <div class="blc_tab_content" data-tab="dashboard" <?php echo $style_dashboard_tab; ?>>
					<br>
                    <div id="mainwp_linkschecker_option">
                        <div class="clear">
                           <div id="mainwp_blc_links_dashboard_content">
                               <span class="sync_links_working" style="float: right; margin-right: 10px;"></span>
                                <div class="tablenav top">
                                <?php MainWP_Links_Checker_Dashboard::gen_select_boxs( $dbwebsites_dashboard_linkschecker, $current_filters ); ?>
                                <input type="button" class="mainwp-upgrade-button button-primary button"
                                       value="<?php _e( 'Sync Data' ); ?>" id="dashboard_refresh" style="background-image: none!important; float:right; padding-left: .6em !important;">
								<input type="button" class="mainwp-upgrade-button button-primary button"
								   value="<?php _e( 'Sync Links Data' ); ?>" id="mwp_sync_links_data" style="background-image: none!important; float:right; padding-left: .6em !important; margin-right: 10px !important;">
                                </div>
                                <?php MainWP_Links_Checker_Dashboard::gen_dashboard_tab( $dbwebsites_dashboard_linkschecker ); ?>
                            </div>
                        </div>
                    <div class="clear"></div>
                    </div>
                </div>
                <div class="blc_tab_content" data-tab="links" <?php echo $style_broken_links_tab; ?>>
					<div id="mainwp_blc_links_content">
						<div class="tablenav top">
							<div class="alignleft">
								<?php MainWP_Links_Checker::get_instance()->gen_nav_filters( $dbwebsites_dashboard_linkschecker, $current_filters ); ?>
							</div>
						</div>
						<div class="tablenav top">

							<?php MainWP_Links_Checker::get_instance()->gen_select_boxs( $dbwebsites_linkschecker, $current_filters ); ?>
							<div class="alignright">
								<?php echo $pagination_html; ?>
							</div>
						</div>
                    <?php self::gen_broken_links_tab( $search_links_data, $current_filters, $sites_url ); ?>
						<div class="tablenav bottom">
							<div class="alignright">
								<?php echo $pagination_html; ?>
							</div>
						</div>
					</div>
				</div>
                <div id="blc_settings_tab" class="blc_tab_content" data-tab="settings" <?php echo $style_settings_tab; ?>>
                    <?php self::gen_settings_tab(); ?>
                </div>
            </div>
        </div>
    <?php
	}

	static function gen_settings_tab() {
		$check_threshold = MainWP_Links_Checker::get_instance()->get_option( 'check_threshold', 72 );
	?>
    <br>
    <div class="mainwp_info-box-red hidden-field" id="mwp-blc-setting-error-box"></div>
    <div class="postbox">
        <h3 class="mainwp_box_title"><span><i class="fa fa-cog"></i> <?php _e( 'Settings', 'mainwp-broken-links-checker-extension' ); ?></span></h3>
        <div class="inside">
		<div class="mainwp_info-box-yellow"><?php _e( 'If loading links is taking too long, please visit child site and make sure the Broken Link Checker plugin is recording data properly. If it is not try to disable and re-enable the plugin and try again','mainwp-broken-links-checker-extension' ); ?></div>
        <div class="mainwp_info-box hidden"></div>
        <div id="mainwp-blc-setting-tab-content">
            <table class="form-table">
                <tbody>
                <tr>
                    <th scope="row">
                        <?php _e( 'Check each link', 'mainwp-broken-links-checker-extension' ); ?>
                    </th>
                    <td><?php _e( 'Every', 'mainwp-broken-links-checker-extension' ); ?> <input type="text" maxlength="5" size="5" value="<?php echo $check_threshold; ?>" id="check_threshold" name="check_threshold"> <?php _e( 'hours', 'mainwp-broken-links-checker-extension' ); ?><br>
                        <span class="description"><?php _e( 'Existing links will be checked this often. New links will usually be checked ASAP.' , 'mainwp-broken-links-checker-extension' ); ?></span>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e( 'Forced recheck', 'mainwp-broken-links-checker-extension' ); ?></th>
                    <td>
                        <input type="button" value="<?php _e( 'Re-check all pages', 'mainwp-broken-links-checker-extension' ); ?>" id="mwp-blc-start-recheck-btn" name="mwp-blc-start-recheck-btn" class="button">
                        <span id="mainwp_blc_setting_recheck_loading" class="hidden-field"><i class="fa fa-spinner fa-pulse"></i></span>
                        <input type="hidden" id="recheck" value="" name="recheck">
                        <br>
                        <span class="description"><?php _e( 'Click this button to make the plugin empty its link database and recheck the entire site from scratch. Please, be patient until new list gets generated.', 'mainwp-broken-links-checker-extension' ); ?></span>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e( 'Maximum Number Of Links', 'mainwp-broken-links-checker-extension' ); ?> <?php do_action( 'mainwp_renderToolTip', __( '0 for unlimited, CAUTION: a large amount will decrease the speed and might crash the communication.','mainwp-broken-links-checker-extension' ) ); ?></th>
                    <td>
                        <input type="text" name="max_number_of_links" id="max_number_of_links" value="<?php echo get_option( 'mainwp_blc_max_number_of_links', 50 ); ?>" size="5" maxlength="5"> <i><?php _e( '(Default: 50)', 'mainwp-broken-links-checker-extension' ); ?></i>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
    </div>
    <p class="submit">
        <span style="float:left;">
            <input type="button" name="button_preview" id="mwp-blc-save-settings-btn" class="button-primary" value="<?php _e( 'Save Settings', 'mainwp-broken-links-checker-extension' ); ?>">
            <span id="mainwp_blc_setting_loading" class="hidden-field"><i class="fa fa-spinner fa-pulse"></i></span>
        </span>
    </p>
    <?php
	}

	static function gen_broken_links_tab( $links, $filters, $sites_url ) {

		$url_order =  $linktext_order = $site_order = $redirect_order = 'desc';
		$sorted_url = $sorted_linktext = $sorted_site = $sorted_redirect = '';

		$order = isset($filters['order']) ? $filters['order'] : 'desc';

		if (isset($filters['order_by'])) {
			if ($filters['order_by'] == 'url') {
				$url_order = $order;
				$sorted_url = 'sorted ' . $order;
			} else if ($filters['order_by'] == 'link_text') {
				$linktext_order = $order;
				$sorted_linktext = 'sorted ' . $order;
			} else if ($filters['order_by'] == 'site_id') {
				$site_order = $order;
				$sorted_site = 'sorted ' . $order;
			} else if ($filters['order_by'] == 'redirect_url') {
				$redirect_order = $order;
				$sorted_redirect = 'sorted ' . $order;
			}
		}

		?>
            <table class="wp-list-table widefat fixed posts tablesorter color-code-link-status" id="mainwp_blc_links_table"
                   cellspacing="0">
                <thead>
                <tr>
                    <th scope="col" id="title" class="manage-column column-title <?php echo !empty($sorted_url) ? $sorted_url : 'sortable desc'; ?>" style="">
                        <a href="<?php echo self::gen_filters_link($filters, 'url', ($url_order == 'desc' ? 'asc' : 'desc')); ?>" ><span><?php _e( 'URL','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" id="status" class="manage-column mwp-column-status" style="">
                        <span><?php _e( 'Status','mainwp-broken-links-checker-extension' ); ?></span>
                    </th>
                    <th scope="col" id="new-link-text" class="manage-column mwp-column-new-link-text <?php echo !empty($sorted_linktext) ? $sorted_linktext : 'sortable desc'; ?>" style="">
                        <a href="<?php echo self::gen_filters_link($filters, 'link_text', ($linktext_order =='desc' ? 'asc' : 'desc')); ?>" ><span><?php _e( 'Link Text','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" id="redirect-url" class="manage-column column-redirect-url <?php echo !empty($sorted_redirect) ? $sorted_redirect : 'sortable desc'; ?>" style="">
                        <a href="<?php echo self::gen_filters_link($filters, 'redirect_url', ($redirect_order =='desc' ? 'asc' : 'desc')); ?>" ><span><?php _e( 'Redirect URL','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" id="source" class="manage-column column-source" style="">
                        <span><?php _e( 'Source','mainwp-broken-links-checker-extension' ); ?></span>
                    </th>
                    <th scope="col" id="url" class="manage-column column-url <?php echo !empty($sorted_site) ? $sorted_site : 'sortable desc'; ?>" style="">
                        <a href="<?php echo self::gen_filters_link($filters, 'site_id', ($site_order =='desc' ? 'asc' : 'desc')); ?>" ><span><?php _e( 'Site URL','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                </tr>
                </thead>

                <tfoot>
                <tr>
                     <th scope="col" id="title" class="manage-column column-title <?php echo !empty($sorted_url) ? $sorted_url : 'sortable desc'; ?>" style="">
                        <a href="<?php echo self::gen_filters_link($filters, 'url', ($url_order == 'desc' ? 'asc' : 'desc')); ?>" ><span><?php _e( 'URL','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" id="status" class="manage-column mwp-column-status" style="">
                        <span><?php _e( 'Status','mainwp-broken-links-checker-extension' ); ?></span>
                    </th>
                    <th scope="col" id="new-link-text" class="manage-column mwp-column-new-link-text <?php echo !empty($sorted_linktext) ? $sorted_linktext : 'sortable desc'; ?>" style="">
                        <a href="<?php echo self::gen_filters_link($filters, 'link_text', ($linktext_order =='desc' ? 'asc' : 'desc')); ?>" ><span><?php _e( 'Link Text','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" id="redirect-url" class="manage-column column-redirect-url <?php echo !empty($sorted_redirect) ? $sorted_redirect : 'sortable desc'; ?>" style="">
                        <a href="<?php echo self::gen_filters_link($filters, 'redirect_url', ($redirect_order =='desc' ? 'asc' : 'desc')); ?>" ><span><?php _e( 'Redirect URL','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" id="source" class="manage-column column-source" style="">
                        <span><?php _e( 'Source','mainwp-broken-links-checker-extension' ); ?></span>
                    </th>
                    <th scope="col" id="url" class="manage-column column-url <?php echo !empty($sorted_site) ? $sorted_site : 'sortable desc'; ?>" style="">
                        <a href="<?php echo self::gen_filters_link($filters, 'site_id', ($site_order =='desc' ? 'asc' : 'desc')); ?>" ><span><?php _e( 'Site URL','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                </tr>
                </tfoot>
                <tbody class="list:posts">
                <?php
                        self::render_table_links_content( $links, $sites_url );
                ?>
                </tbody>
            </table>
            <div class="clear"></div>
    <?php
		MainWP_Links_Checker::get_instance()->inline_editor();
		include_once MWP_BROKEN_LINKS_CHECKER_DIR . 'includes/mwp-links-page-js.php';
	}

	static function gen_filters_link($filters, $order_by = null, $order = null) {
		$url = 'admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension';

		if ($order_by !== null)
			$filters['order_by'] = $order_by;
		if ($order !== null)
			$filters['order'] = $order;
		foreach($filters as $key => $value) {
			$url .= '&' . $key .'=' . $value;
		}
		$url .= '&tab=links';
		return $url;
	}

	static function get_current_filters( ) {

		$filters = array();

		$selected_site = $selected_group = 0;
		$filter_search = '';
		$filter_link = '';

		if ( isset( $_GET['site_id'] ) && ! empty( $_GET['site_id'] ) ) {
			$selected_site = $_GET['site_id'];
		} else if ( isset( $_GET['filter_search'] ) && ! empty( $_GET['filter_search'] ) ) {
			$filter_search = $_GET['filter_search'];
		} else if ( isset( $_GET['group_id'] ) && ! empty( $_GET['group_id'] ) ) {
			$selected_group = $_GET['group_id'];
		} else if ( isset( $_POST['mainwp_blc_links_groups_select'] ) && ! empty( $_POST['mainwp_blc_links_groups_select'] ) ) {
			$selected_group = intval( $_POST['mainwp_blc_links_groups_select'] );
		}

		if ( isset( $_REQUEST['blc_select_site'] ) ) {
			$selected_site = intval( $_REQUEST['blc_select_site'] );
		}

		if ( (isset( $_GET['sl'] ) && ! empty( $_GET['sl'] )) ) {
			$filter_link = trim( $_GET['sl'] );
		}

		if ( isset( $_GET['filter_id'] ) && ! empty( $_GET['filter_id'] ) ) {
			$filters['filter_id'] = $_GET['filter_id'];
		}

		if ( isset( $_GET['order_by'] ) && ! empty( $_GET['order_by'] ) ) {
			$filters['order_by'] = $_GET['order_by'];
		}

		if ( isset( $_GET['order'] ) && ! empty( $_GET['order'] ) ) {
			$filters['order'] = $_GET['order'];
		}

		if(!empty($selected_site)) {
			$filters['site_id'] = $selected_site;
		}
		if(!empty($selected_group)) {
			$filters['group_id'] = $selected_group;
		}
		if(!empty($filter_search)) {
			$filters['filter_search'] = $filter_search;
		}
		if(!empty($filter_link)) {
			$filters['filter_url'] = $filter_link;
		}
		return $filters;
	}

	static function get_filter_site_ids($all_websites, $filters) {
		$selected_site_ids = array();
		if ( isset($filters['group_id']) && !empty($filters['group_id']) ) {
			global $mainWPLinksCheckerExtensionActivator;
			$group_websites = apply_filters( 'mainwp-getdbsites', $mainWPLinksCheckerExtensionActivator->get_child_file(), $mainWPLinksCheckerExtensionActivator->get_child_key(), array(), array( $filters['group_id'] ) );
			foreach ( $group_websites as $site ) {
				$selected_site_ids[] = $site->id;
			}
		} else if ( isset($filters['filter_search']) && !empty($filters['filter_search']) ) {
			$find = $filters['filter_search'];
			foreach ( $all_websites as $website ) {
				if ( stripos( $website['name'], $find ) !== false || stripos( $website['url'], $find ) !== false ) {
					$selected_site_ids[] = $website['id'];
				}
			}
		} else if ( isset($filters['site_id']) && !empty($filters['site_id']) ) {
			$selected_site_ids[] = $filters['site_id'];
		} else {
			foreach ( $all_websites as $website ) {
				$selected_site_ids[] = $website['id'];
			}
		}
		return $selected_site_ids;
	}

	static function get_search_links($selected_site_ids, $filters, $params = array() ) {
		if (empty($selected_site_ids))
			return array();

	//Optionally sorting is also possible
		$order_exprs = array();

		if ( !empty($filters['order_by']) ) {
			$allowed_columns = array(
				'url' => 'links.url',
				'link_text' => 'links.link_text',
				'redirect_url' => 'links.final_url',
				'site_id' => 'links.site_id'
			);
			$column = $filters['order_by'];

			$direction = !empty($filters['order']) ? strtolower($filters['order']) : 'asc';
			if ( !in_array($direction, array('asc', 'desc')) ) {
				$direction = 'asc';
			}

			if ( array_key_exists($column, $allowed_columns) ) {
				if ( $column === 'redirect_url' ) {
					//Sort links that are not redirects last.
					$order_exprs[] = '(links.redirect_count > 0) DESC';
				}

				$order_exprs[] = $allowed_columns[$column] . ' ' . $direction;
			}
		}

		$params['order_exprs'] = $order_exprs;

		return MainWP_Links_Checker_DB::get_instance()->get_filter_links($selected_site_ids, $filters, $params);
	}

	static function gen_pagination($filters, $page, $per_page, $total = 0) {

		$max_pages = ceil( $total / $per_page);
		$filters['paged'] = '%#%';

                if (!isset($filters['tab']) || $filters['tab'] != 'links')
                    $filters['tab'] = 'links';

		//WP has a built-in function for pagination
		$page_links = paginate_links( array(
			'base' => add_query_arg( $filters ),
			'format' => '',
			'prev_text' => __('&laquo;'),
			'next_text' => __('&raquo;'),
			'total' => $max_pages,
			'current' => $page
		));

		if ( $page_links ) {
			$pagination_html = '<div class="tablenav-pages">';
			$pagination_html .= sprintf(
				'<span class="displaying-num">' . __( 'Displaying %s&#8211;%s of <span class="current-link-count">%s</span>', 'mainwp-broken-links-checker-extension' ) . '</span>%s',
				number_format_i18n( ( $page - 1 ) * $per_page + 1 ),
				number_format_i18n( min( $page * $per_page, $total ) ),
				number_format_i18n( $total ),
				$page_links
			);
			$pagination_html .= '</div>';
		} else {
			$pagination_html = '';
		}
		return $pagination_html;
	}

	public static function gen_select_boxs( $all_sites, $filters ) {
		global $mainWPLinksCheckerExtensionActivator;

		$filter_url = isset($filters['filter_url']) ? $filters['filter_url'] : '';
		$selected_group = isset($filters['group_id']) ? $filters['group_id'] : 0;
		$selected_site = isset($filters['site_id']) ? $filters['site_id'] : 0;

		$groups = apply_filters( 'mainwp-getgroups', $mainWPLinksCheckerExtensionActivator->get_child_file(), $mainWPLinksCheckerExtensionActivator->get_child_key(), null );
		?>

	<form method="get" action="admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension">
        <div class="alignleft actions">
                <input type="hidden" name="page" value="Extensions-Mainwp-Broken-Links-Checker-Extension">
                <span role="status" aria-live="polite" class="ui-helper-hidden-accessible"><?php _e( 'No search results.','mainwp-broken-links-checker-extension' ); ?></span>
                <input type="text" class="mainwp_autocomplete ui-autocomplete-input" name="sl" autocompletelist="sites" value="<?php echo stripslashes( $filter_url ); ?>" autocomplete="off">
                <span><?php _e( 'Search Url', 'mainwp-broken-links-checker-extension' ); ?></span>
        </div>
        <div class="alignleft actions">
                <select name="blc_select_site" class="mainwp-select2">
                    <option value="0"><?php _e( 'Select a Site' ); ?></option>
                <?php
				foreach ( $all_sites as $site ) {
					$_select = '';
					if ( intval( $selected_site ) == $site['id'] ) {
						$_select = 'selected';
					}
				?>
                    <option value="<?php echo $site['id']; ?>" <?php echo $_select; ?>><?php echo stripslashes( $site['name'] ); ?></option>
                <?php
				}
				?>
                </select>
                <input type="submit" id="mainwp_blc_select_site_btn_display" class="button" value="<?php _e( 'Display' ); ?>" />
       </div>
	</form>
        <div class="alignleft actions">
            <form method="post" action="admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension">
                <select name="mainwp_blc_links_groups_select">
                <option value="0"><?php _e( 'Select a group' ); ?></option>
                <?php
				if ( is_array( $groups ) && count( $groups ) > 0 ) {
					foreach ( $groups as $group ) {
						$_select = '';
						if ( $selected_group == $group['id'] ) {
							$_select = 'selected '; }
						echo '<option value="' . $group['id'] . '" ' . $_select . '>' . $group['name'] . '</option>';
					}
				}
				?>
                </select>
                <input class="button" type="submit" name="mwp_blc_groups_btn_display" id="mwp_blc_groups_btn_display" value="<?php _e( 'Display', 'mainwp-broken-links-checker-extension' ); ?>">
            </form>
        </div>
        <?php
		return;
	}

	function get_filters( $websites = null , $selected_site_ids = array()) {

		$total = array( 'broken' => 0, 'redirects' => 0, 'dismissed' => 0, 'all' => 0 );
		if ( empty($websites) ) {
			$total = $this->get_option( 'count_total_links', array() );
		} else {
			if ( is_array( $websites )) {
				foreach ( $websites as $site ) {
					if (!in_array($site['id'], $selected_site_ids))
						continue;
					$total['broken'] += isset( $site['broken'] ) ? intval( $site['broken'] ) : 0;
					$total['redirects'] += isset( $site['redirects'] ) ? intval( $site['redirects'] ) : 0;
					$total['dismissed'] += isset( $site['dismissed'] ) ? intval( $site['dismissed'] ) : 0;
					$total['all'] += isset( $site['all'] ) ? intval( $site['all'] ) : 0;
				}
			}
		}

		if ( ! is_array( $total ) ) {
			$total = array(); }

		$filters = array(
						'broken' => array(
						'name' => __( 'Broken', 'mainwp-broken-links-checker-extension' ),
										   'count' => isset( $total['broken'] ) ? $total['broken'] : 0,
										 ),
						'redirects' => array(
						'name' => __( 'Redirects', 'mainwp-broken-links-checker-extension' ),
												'count' => isset( $total['redirects'] ) ? $total['redirects'] : 0,
											),
						'dismissed' => array(
						'name' => __( 'Dismissed', 'mainwp-broken-links-checker-extension' ),
												'count' => isset( $total['dismissed'] ) ? $total['dismissed'] : 0,
											),
						'all' => array(
						'name' => __( 'All', 'mainwp-broken-links-checker-extension' ),
											'count' => isset( $total['all'] ) ? $total['all'] : 0,
										),
					);
		return $filters;
	}

	function gen_nav_filters( $websites = array(), $filters) {

		$current = isset($filters['filter_id']) ? $filters['filter_id'] : 'all';

		$selected_site_ids = self::get_filter_site_ids($websites, $filters);

		$nav_filters = $this->get_filters( $websites , $selected_site_ids);

		echo '<ul class="subsubsub">';

		//Construct a submenu of filter types
		$items = array();
		foreach ( $nav_filters as $filter => $data ) {
			//                if ( !empty($data['hidden']) ) continue; //skip hidden filters

				$class = '';
				$number_class = 'filter-' . $filter . '-link-count';

				if ( $current == $filter ) {
						$class = 'class="current"';
						$number_class .= ' current-link-count';
				}

				$filters['filter_id'] =  $filter;

				$items[] = "<li><a href=\"" . self::gen_filters_link($filters) . "\" {$class}>
				{$data['name']}</a> <span class='count'>(<span class='$number_class'>{$data['count']}</span>)</span>";
		}
		echo implode( ' |</li>', $items );

		echo '</ul>';
	}

	function link_details_row( $link , $extra) {
		printf(
			'<tr id="link-details-%d-siteid-%d" class="blc-link-details expand-child"><td colspan="%d">',
			$link->link_id, $link->site_id, 5
		);
		$this->details_row_contents( $link, $extra );
		echo '</td></tr>';
	}

	public static function details_row_contents( $link, $extra ) {
		?>
        <div class="blc-detail-container">
                <div class="blc-detail-block" style="float: left; width: 49%;">
                <ol style='list-style-type: none;'>
                <?php if ( ! empty( $extra['post_date'] ) ) { ?>
                <li><strong><?php _e( 'Post published on' ); ?>:</strong>
                <span class='post_date'><?php
								echo date_i18n( get_option( 'date_format' ),strtotime( $extra['post_date'] ) );
				?></span></li>
                <?php } ?>

                <li><strong><?php _e( 'Link last checked' ); ?>:</strong>
                <span class='check_date'><?php
								$last_check = $link->last_check;
				if ( $last_check < strtotime( '-10 years' ) ) {
								_e( 'Never' );
				} else {
					echo date_i18n( get_option( 'date_format' ), $last_check );
				}
				?></span></li>

                <li><strong><?php _e( 'HTTP code' ); ?>:</strong>
                <span class='http_code'><?php
						print $link->http_code;
				?></span></li>

                <li><strong><?php _e( 'Response time' ); ?>:</strong>
                <span class='request_duration'><?php
						printf( __( '%2.3f seconds' ), $link->request_duration );
				?></span></li>

                <li><strong><?php _e( 'Final URL' ); ?>:</strong>
                <span class='final_url'><?php
						print $link->final_url;
				?></span></li>

                <li><strong><?php _e( 'Redirect count' ); ?>:</strong>
                <span class='redirect_count'><?php
						print $link->redirect_count;
				?></span></li>

                <li><strong><?php _e( 'Instance count' ); ?>:</strong>
                <span class='instance_count'><?php
					print property_exists($link, 'count_instance') ? $link->count_instance : '' ;
				?></span></li>

                <?php if ( $link->broken && (intval( $link->check_count ) > 0) ) {  ?>
                <li><br/>
                        <?php
								printf(
									_n( 'This link has failed %d time.', 'This link has failed %d times.', $link->check_count ),
									$link->check_count
								);

								echo '<br>';

								$delta = time() - $link->first_failure;
								printf(
									__( 'This link has been broken for %s.' ),
									MainWP_Links_Checker_Utility::fuzzy_delta( $delta )
								);
						?>
                        </li>
                <?php } ?>
                        </ol>
                </div>

                <div class="blc-detail-block" style="float: right; width: 50%;">
                <ol style='list-style-type: none;'>
                        <li><strong><?php _e( 'Log' ); ?>:</strong>
                <span class='blc_log'><?php
						print nl2br( $link->log );
				?></span></li>
                        </ol>
                </div>

                <div style="clear:both;"> </div>
        </div>
        <?php
	}

	static function render_table_links_content( $links, $sites_url ) {
		$rownum = 0;

		foreach ( $links as $link ) {
			if ( empty( $link->link_id ) ) {
				continue;
			}

			$extra_info = unserialize(base64_decode($link->extra_info));

			if (!is_array($extra_info))
				$extra_info = array();

			$status = self::analyse_status( $link );
			$rownum++;

			$rowclass = ($rownum % 2)? 'alternate' : '';
			if ( $link->redirect_count > 0 ) {
					$rowclass .= ' blc-redirect';
			}

			if ( $link->broken ) {
				//Add a highlight to broken links that appear to be permanently broken
				if ( isset($extra_info['permanently_broken']) ) {
					$rowclass .= ' blc-permanently-broken';
					if ( isset($extra_info['permanently_broken_highlight']) ) {
						$rowclass .= ' blc-permanently-broken-hl';
					}
				}
			}

			$data_link_text = '';
			if ( isset($extra_info['data_link_text']) && ! empty( $extra_info['data_link_text'] ) ) {
				$data_link_text = ' data-link-text="' . $extra_info['data_link_text'] . '"'; }

			$rowattr = sprintf(
				' data-days-broken="%d" data-can-edit-url="%d" data-can-edit-text="%d"%s ',
				$extra_info['days_broken'],
				isset( $extra_info['can_edit_url'] ) && ! empty( $extra_info['can_edit_url'] ) ?  1 : 0,
				isset( $extra_info['can_edit_text'] ) && ! empty( $extra_info['can_edit_text'] ) ? 1 : 0,
				$data_link_text
			);

			$link_text = preg_replace( '/src=".*\/images\/font-awesome\/(.+)/is', 'src="' . MWP_BROKEN_LINKS_CHECKER_URL . '/images/font-awesome/' . '${1}', (isset($extra_info['link_text']) ? $extra_info['link_text'] : '') );

			?>
            <tr valign="top" id="blc-row-<?php echo $link->link_id; ?>-siteid-<?php echo $link->site_id; ?>" class="blc-row link-status-<?php echo $status['code'] ?> <?php echo $rowclass; ?>" <?php echo $rowattr; ?>>
                <td class="post-title page-title column-title">
                    <?php self::column_new_url( $link ); ?>
                </td>
                <td class="status mwp-column-status">
                    <?php MainWP_Links_Checker::get_instance()->column_status( $link, $status ); ?>
                </td>
                <td class="new-link-text mwp-column-new-link-text">
                    <span><?php echo $link_text; ?></span>
                </td>
                <td class="redirect-url column-redirect-url">
                    <?php
                            MainWP_Links_Checker::get_instance()->column_redirect_url( $link );
                    ?>
                </td>
                <td class="source column-source">
                    <?php
                            $site_url = $sites_url[ $link->site_id ];
                            MainWP_Links_Checker::get_instance()->column_source( $link, $site_url );
                    ?>
                </td>
                <td class="url column-url">
                    <a href="<?php echo $sites_url[ $link->site_id ]; ?>" target="_blank"><?php echo $sites_url[ $link->site_id ]; ?></a><br/>
                    <div class="row-actions">
                        <span class="edit">
                            <a href="admin.php?page=managesites&dashboard=<?php echo $link->site_id; ?>"><?php _e( 'Overview' ); ?></a>
                        </span> | <span class="edit">
                            <a target="_blank" href="admin.php?page=SiteOpen&newWindow=yes&websiteid=<?php echo $link->site_id; ?>"><?php _e( 'Open WP-Admin' );?></a>
                        </span>
                    </div>
                </td>
            </tr>
             <?php
                    MainWP_Links_Checker::get_instance()->link_details_row( $link , $extra_info);
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

	function column_status( $link, $status ) {
			printf(
				'<table class="mini-status" title="%s">',
				esc_attr( __( 'Show more info about this link', 'mainwp-broken-links-checker-extension' ) )
			);

			//$status = $link->analyse_status();

			printf(
				'<tr class="link-status-row link-status-%s">
                            <td>
                                    <span class="http-code">%s</span> <span class="status-text">%s</span>
                            </td>
                    </tr>',
				$status['code'],
				empty( $link->http_code )?'':$link->http_code,
				$status['text']
			);

			//Last checked...
			//            if ( $link->last_check != 0 ){
			//                    $last_check = _x('Checked', 'checked how long ago', 'mainwp-broken-links-checker-extension') . ' ';
			//                    $last_check .= MainWP_Links_Checker_Utility::fuzzy_delta(time() - $link->last_check, 'ago');
			//
			//                    printf(
			//                            '<tr class="link-last-checked"><td>%s</td></tr>',
			//                            $last_check
			//                    );
			//            }

			//Broken for...
			//            if ( $link->broken ){
			//                    $delta = time() - $link->first_failure;
			//                    $broken_for = MainWP_Links_Checker_Utility::fuzzy_delta($delta);
			//                    printf(
			//                            '<tr class="link-broken-for"><td>%s %s</td></tr>',
			//                            __('Broken for', 'mainwp-broken-links-checker-extension'),
			//                            $broken_for
			//                    );
			//            }

			echo '</table>';

			//"Details" link.
			echo '<div class="row-actions">';
			printf(
				'<span><a href="#" class="blc-details-button" title="%s">%s</a></span>',
				esc_attr(__('Show more info about this link', 'mainwp-broken-links-checker-extension')),
				_x('Details', 'link in the "Status" column', 'mainwp-broken-links-checker-extension')
			);
			echo '</div>';
	}


	function column_redirect_url( $link ) {
		if ( $link->redirect_count > 0 ) {
			printf(
				'<a href="%1$s" target="_blank" class="blc-redirect-url" title="%1$s">%2$s</a>',
				esc_attr( $link->final_url ),
				esc_html( $link->final_url )
			);
		}
	}

	static function analyse_status( $link ) {
		$code = MWP_BLC_LINK_STATUS_UNKNOWN;
		$text = __( 'Unknown' );

		if ( $link->broken ) {

				$code = MWP_BLC_LINK_STATUS_WARNING;
				$text = __( 'Unknown Error' );

			if ( $link->timeout ) {

					$text = __( 'Timeout' );
					$code = MWP_BLC_LINK_STATUS_WARNING;

			} elseif ( $link->http_code ) {

					//Only 404 (Not Found) and 410 (Gone) are treated as broken-for-sure.
				if ( in_array( $link->http_code, array( 404, 410 ) ) ) {
						$code = MWP_BLC_LINK_STATUS_ERROR;
				} else {
						$code = MWP_BLC_LINK_STATUS_WARNING;
				}

				if ( array_key_exists( intval( $link->http_code ), MainWP_Links_Checker::get_instance()->http_status_codes ) ) {
						$text = MainWP_Links_Checker::get_instance()->http_status_codes[ intval( $link->http_code ) ];
				}
			}
		} else {

			if ( ! $link->last_check ) {
					$text = __( 'Not checked' );
					$code = MWP_BLC_LINK_STATUS_UNKNOWN;
			} elseif ( $link->false_positive ) {
				$text = __( 'False positive' );
				$code = MWP_BLC_LINK_STATUS_UNKNOWN;
			} else {
					$text = __( 'OK' );
					$code = MWP_BLC_LINK_STATUS_OK;
			}
		}
		return compact( 'text', 'code' );
	}

	static function column_new_url( $link ) {
	?>
        <a href="<?php print esc_attr( $link->url ); ?>" target='_blank' class='blc-link-url' title="<?php echo esc_attr( $link->url ); ?>">
            <?php print $link->url; ?></a>
            <?php
			//Output inline action links for the link/URL
			$actions = array();

			$actions['edit'] = "<span class='edit'><a href='javascript:void(0)' class='mwp-blc-edit-button' title='" . esc_attr( __( 'Edit this link', 'mainwp-broken-links-checker-extension' ) ) . "'>". __( 'Edit URL', 'mainwp-broken-links-checker-extension' ) .'</a>';

			$actions['delete'] = "<span class='delete'><a class='submitdelete mwp-blc-unlink-button' title='" . esc_attr( __( 'Remove this link from all posts', 'mainwp-broken-links-checker-extension' ) ). "' ".
							"href='javascript:void(0);'>" . __( 'Unlink' ) . '</a>';

			if ( $link->broken ) {
					$actions['discard'] = sprintf(
						'<span><a href="#" title="%s" class="mwp-blc-discard-button">%s</a>',
						esc_attr( __( 'Remove this link from the list of broken links and mark it as valid', 'mainwp-broken-links-checker-extension' ) ),
						__( 'Not broken', 'mainwp-broken-links-checker-extension' )
					);
			}

			if ( ! $link->dismissed && ($link->broken || ($link->redirect_count > 0)) ) {
					$actions['dismiss'] = sprintf(
						'<span><a href="#" title="%s" class="mwp-blc-dismiss-button">%s</a>',
						esc_attr( __( 'Hide this link and do not report it again unless its status changes', 'mainwp-broken-links-checker-extension' ) ),
						__( 'Dismiss' )
					);
			} else if ( $link->dismissed ) {
					$actions['undismiss'] = sprintf(
						'<span><a href="#" title="%s" class="blc-undismiss-button">%s</a>',
						esc_attr( __( 'Undismiss this link', 'mainwp-broken-links-checker-extension' ) ),
						__( 'Undismiss' )
					);
			}

			echo '<div class="row-actions">';
			echo implode( ' | </span>', $actions ) .'</span>';
			echo '</div>';
			echo '<div class="working-status hidden"></div>';

			?>
            <div class="mwp-blc-url-editor-buttons">
                    <input type="button" class="button-secondary cancel alignleft mwp-blc-cancel-button" value="<?php echo esc_attr( __( 'Cancel' ) ); ?>" />
                    <input type="button" class="button-primary save alignright blc-update-url-button" value="<?php echo esc_attr( __( 'Update URL', 'mainwp-broken-links-checker-extension' ) ); ?>" />
                    <img class="waiting" style="display:none;" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" />
            </div>
            <?php
	}

	function column_source( $link, $site_url ) {
		$site_url = $site_url . (substr( $site_url, -1 ) != '/' ? '/' : '');
		$extra = unserialize(base64_decode($link->extra_info));
		if ( isset( $extra['source_data'] ) && is_array( $extra['source_data'] ) ) {
			if ( $extra['container_type'] == 'comment' ) {
				$image = '';
				if ( isset( $extra['source_data']['image'] ) ) {
					$image = sprintf(
						'<img src="%s/images/%s" class="blc-small-image" title="%3$s" alt="%3$s"> ',
						MWP_BROKEN_LINKS_CHECKER_URL,
						$extra['source_data']['image'],
						__( 'Comment', 'mainwp-broken-links-checker-extension' )
					);
				}
				$html = '';
				if ( isset( $extra['source_data']['text_sample'] ) ) {
					$edit_href = 'admin.php?page=SiteOpen&websiteid=' . $link->site_id . '&location=' . base64_encode( 'comment.php?action=editcomment&c=' . $extra['source_data']['comment_id'] );
					$html = sprintf(
						'<a href="%s" title="%s"><b>%s</b> &mdash; %s</a>',
						$edit_href,
						esc_attr__( 'Edit comment' ),
						$extra['source_data']['comment_author'],
						$extra['source_data']['text_sample']
					);
				}
				echo $image . $html;
				if ( $extra['source_data']['comment_id'] && ($extra['source_data']['comment_status'] != 'trash') && ($extra['source_data']['comment_status'] != 'spam') ) { ?>
                <span class="hidden source_column_data" data-comment_id="<?php echo $extra['source_data']['comment_id']; ?>" data-site_id_encode="<?php echo base64_encode( $link->site_id ); ?>"></span>
                    <div class="row-actions">
                       <span class="edit">
                           <a href="admin.php?page=SiteOpen&websiteid=<?php echo $link->site_id; ?>&location=<?php echo base64_encode( 'comment.php?action=editcomment&c=' . $extra['source_data']['comment_id'] ); ?>"
                              target="_blank" title="Edit this item"><?php _e( 'Edit','mainwp-broken-links-checker-extension' ); ?></a>
                       </span>
                        <span class="trash">
                            | <a class="blc_comment_submitdelete" title="<?php _e( 'Move this item to the Trash', 'mainwp-broken-links-checker-extension' ); ?>" href="#"><?php _e( 'Trash','mainwp-broken-links-checker-extension' ); ?></a>
                        </span>
						<?php
						$per_link = $site_url . '?p=' . $extra['source_data']['container_post_ID'];
						if ( in_array( $extra['source_data']['container_post_status'], array( 'pending', 'draft' ) ) ) {
							printf(
								'<span class="view">| <a href="%s" title="%s" rel="permalink">%s</a>',
								esc_url( add_query_arg( 'preview', 'true', $per_link ) ),
								esc_attr( sprintf( __( 'Preview &#8220;%s&#8221;' ), $extra['source_data']['container_post_title'] ) ),
								__( 'Preview Post' )
							);
						} elseif ( 'trash' != $extra['source_data']['container_post_status'] ) {
							printf(
								'<span class="view">| <a href="%s" title="%s" rel="permalink" target="_blank">%s</a></span>',
								$per_link,
								esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $extra['source_data']['container_post_title'] ) ),
								__( 'View Post' )
							);
						}
						?>
                    </div>
                    <?php
				}
			} else {
				if ( isset( $extra['source_data']['container_anypost'] ) && $extra['source_data']['container_anypost'] ) {
					   $edit_href = 'admin.php?page=SiteOpen&websiteid=' . $link->site_id . '&location=' . base64_encode( 'post.php?post=' .$extra['container_id'] . '&action=edit' );
					   $source = sprintf(
						   '<a class="row-title" href="%s" target="_blank" title="%s">%s</a>',
						   $edit_href,
						   esc_attr( __( 'Edit this item' ) ),
						   $extra['source_data']['post_title']
					   );
					   echo $source;
						?>
                        <span class="hidden source_column_data" data-post_id="<?php echo $extra['container_id']; ?>" ></span>
                        <div class="row-actions">
                            <?php if ( $extra['source_data']['post_status'] != 'trash' ) { ?>
                            <span class="edit"><a target="_blank"
                                    href="admin.php?page=SiteOpen&websiteid=<?php echo $link->site_id; ?>&location=<?php echo base64_encode( 'post.php?post=' .$extra['container_id'] . '&action=edit' ); ?>"
                                    title="Edit this item"><?php _e( 'Edit','mainwp-broken-links-checker-extension' ); ?></a></span>
                            <span class="trash">
                                | <a class="blc_post_submitdelete" title="<?php _e( 'Move this item to the Trash', 'mainwp-broken-links-checker-extension' ); ?>" href="#"><?php _e( 'Trash','mainwp-broken-links-checker-extension' ); ?></a>
                            </span>
                            <?php } ?>

                            <?php
							$per_link = $site_url . '?p=' . $extra['container_id'];
							if ( in_array( $extra['source_data']['post_status'], array( 'pending', 'draft' ) ) ) {
								printf(
									'<span class="view">| <a href="%s" title="%s" rel="permalink">%s</a>',
									esc_url( add_query_arg( 'preview', 'true', $per_link ) ),
									esc_attr( sprintf( __( 'Preview &#8220;%s&#8221;' ), $extra['source_data']['post_title'] ) ),
									__( 'Preview' )
								);
							} elseif ( 'trash' != $extra['source_data']['post_status'] ) {
								printf(
									'<span class="view">| <a href="%s" title="%s" rel="permalink" target="_blank">%s</a></span>',
									$per_link,
									esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $extra['source_data']['post_title'] ) ),
									__( 'View' )
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

	protected function inline_editor( $visible_columns = 6 ) {
		?>
        <table style="display: none;"><tbody>
                <tr id="blc-inline-edit-row" class="blc-inline-editor">
                        <td class="blc-colspan-change" colspan="<?php echo $visible_columns; ?>">
                                <div class="blc-inline-editor-content">
                                        <h4><?php echo _x( 'Edit Link', 'inline editor title' ); ?></h4>
                                        <div class="mainwp_info-box-red hidden" id="mwp_blc_edit_link_error_box"></div>
                                        <div class="mainwp_info-box-yellow hidden" id="mwp_blc_edit_link_info_box"></div>
                                        <label>
                                                <span class="title"><?php echo _x( 'Text', 'inline link editor' ); ?></span>
                                                <span class="blc-input-text-wrap"><input type="text" name="link_text" value="" class="blc-link-text-field" /></span>
                                        </label>

                                        <label>
                                                <span class="title"><?php echo _x( 'URL', 'inline link editor' ); ?></span>
                                                <span class="blc-input-text-wrap"><input type="text" name="link_url" value="" class="blc-link-url-field" /></span>
                                        </label>

                                        <div class="blc-url-replacement-suggestions" style="display: none;">
                                                <h4><?php echo _x( 'Suggestions', 'inline link editor' ); ?></h4>
                                                <ul class="blc-suggestion-list">
                                                        <li>...</li>
                                                </ul>
                                        </div>

                                        <div class="submit blc-inline-editor-buttons">
                                                <input type="button" class="button-secondary cancel alignleft mwp-blc-cancel-button" value="<?php echo esc_attr( __( 'Cancel' ) ); ?>" />
                                                <input type="button" class="button-primary save alignright mwp-blc-update-link-button" value="<?php echo esc_attr( __( 'Update' ) ); ?>" />

                                                <img class="waiting" style="display:none;" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" />
                                                <div class="clear"></div>
                                        </div>
                                </div>
                        </td>
                </tr>
        </tbody></table>

        <ul id="blc-suggestion-template" style="display: none;">
                <li>
                        <input type="button" class="button-secondary blc-use-url-button" value="<?php echo esc_attr( __( 'Use this URL' ) ); ?>" />

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

	public function get_option( $key = null, $default = '' ) {
		if ( isset( $this->option[ $key ] ) ) {
			return $this->option[ $key ]; }
		return $default;
	}

	public function set_option( $key, $value ) {
		$this->option[ $key ] = $value;
		return update_option( $this->option_handle, $this->option );
	}

	public static function render_qsg() {
		$plugin_data = get_plugin_data( MAINWP_BROKEN_LINKS_CHECKER_FILE, false );
		$description = $plugin_data['Description'];
		$extraHeaders = array( 'DocumentationURI' => 'Documentation URI' );
		$file_data = get_file_data( MAINWP_BROKEN_LINKS_CHECKER_FILE, $extraHeaders );
		$documentation_url  = $file_data['DocumentationURI'];
		?>
         <div  class="mainwp_ext_info_box" id="ps-pth-notice-box">
            <div class="mainwp-ext-description"><?php echo $description; ?></div><br/>
            <b><?php echo __( 'Need Help?' ); ?></b> <?php echo __( 'Review the Extension' ); ?> <a href="<?php echo $documentation_url; ?>" target="_blank"><i class="fa fa-book"></i> <?php echo __( 'Documentation' ); ?></a>.
                    <a href="#" id="mainwp-lc-quick-start-guide"><i class="fa fa-info-circle"></i> <?php _e( 'Show Quick Start Guide','mainwp-broken-links-checker-extension' ); ?></a></div>
                    <div  class="mainwp_ext_info_box" id="mainwp-lc-tips" style="color: #333!important; text-shadow: none!important;">
                      <span><a href="#" class="mainwp-show-tut" number="1"><i class="fa fa-book"></i> <?php _e( 'Broken Links Checker Dashboard','mainwp-broken-links-checker-extension' ) ?></a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="#" class="mainwp-show-tut"  number="2"><i class="fa fa-book"></i> <?php _e( 'MainWP Broken Links Checker Widgets','mainwp-broken-links-checker-extension' ) ?></a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="#" class="mainwp-show-tut"  number="3"><i class="fa fa-book"></i> <?php _e( 'Manage Links with the MainWP Broken Links Checker Extension','mainwp-broken-links-checker-extension' ) ?></a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="#" class="mainwp-show-tut"  number="4"><i class="fa fa-book"></i> <?php _e( 'Extension Settings','mainwp-broken-links-checker-extension' ) ?></a></span><span><a href="#" id="mainwp-lc-tips-dismiss" style="float: right;"><i class="fa fa-times-circle"></i> <?php _e( 'Dismiss','mainwp-broken-links-checker-extension' ); ?></a></span>
                      <div class="clear"></div>
                      <div id="mainwp-lc-tuts">
                        <div class="mainwp-lc-tut" number="1">
                            <h3>Broken Links Checker Dashboard</h3>
                            <p>From the Broken Links Checker Dashboard page, you can monitor all of your child sites where you have the Broken Links Checker plugin installed. In the sites list, you will be notified if the plugin has an update available or if the plugin is deactivated.</p>
                            <p>The provided links and bulk actions will allow you to Update and Activate the Plugin.</p>
                            <p>Also the Broken, Redirects, Dismissed and the All columns will show you the number of checked links. Clicking on the number will show you the list of associated links. You can also hide the Plugin on child sites. Simply by clicking the Hide Broken Links Checker you can hide it on a single site (Show Broken Links Checker for unhiding it), or use bulk actions to hide on multiple sites. Select the sites where you want to hide the plugin, choose the Hide action and click the Apply button.</p>
                            <p>To unhide the plugin on multiple sites, select the wanted sites, choose the Show action and click the Apply button.</p>
                        </div>
                        <div class="mainwp-lc-tut"  number="2">
                            <h3>MainWP Broken Links Checker Widgets</h3>
                            <p>This extension adds the Widget on your Main Dashboard and Individual Site Dashboard.</p>
                            <img src="//docs.mainwp.com/wp-content/uploads/2014/07/child-widget.png">
                            <p>In the individual site dashboard, the widget will show details for the site. It will show you the number of Broken, Redirected, Dismissed and All checked links.</p>
                            <p>If you click on a number you will drill down a list of checked links.</p>
                            <p>The Main Dashboard widget shows the overall number of checked links for entire network.</p>
                        </div>
                        <div class="mainwp-lc-tut"  number="3">
                            <h3>Manage Links with the MainWP Broken Links Checker Extension</h3>
                            <p>The MainWP Broken Links Checker Extension allows you to manage links on your child site directly from your Dashboard.</p>
                            <p>The Broken Links tab will show you all links from your network. Using the  filters you will be able to get specific links/sites.</p>
                            <p>Under a link, in the URL column, you will be able to find available actions. Here you can Edit, Unlink/Link and Dismiss links.</p>
                            <p><strong>Edit URL</strong></p>
                            <p>To edit a link, click the Edit URL link.</p>
                            <p>When you are done editing, click the Update button.</p>
                            <img src="//docs.mainwp.com/wp-content/uploads/2014/07/blc-edit-1024x220.jpg">
                            <p><strong>URL Details</strong></p>
                            <p>By clicking the Status or Link Text column data, you will get the link details.</p>
                            <img src="//docs.mainwp.com/wp-content/uploads/2014/07/blc-details-1024x461.jpg">
                        </div>
                        <div class="mainwp-lc-tut"  number="4">
                            <h3>Extension Settings</h3>
                            <p>The Extension settings tab allows you to set how often you want to re-check links on your child sites and gives you the option to force a recheck of your links.</p>
                            <p><strong>A forced recheck will force the plugin to empty its link database and recheck all links from scratch. After forcing a recheck it may take a few minutes or longer depending on the size of your site until a new links list is generated.</strong></p>
                      </div>
                    </div>
                    </div>
        <?php
	}
}
