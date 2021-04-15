<?php

if ( ! defined( 'MWP_BLC_LINK_STATUS_UNKNOWN' ) ) {
	define( 'MWP_BLC_LINK_STATUS_UNKNOWN', 'unknown' );
}

if ( ! defined( 'MWP_BLC_LINK_STATUS_OK' ) ) {
	define( 'MWP_BLC_LINK_STATUS_OK', 'ok' );
}

if ( ! defined( 'MWP_BLC_LINK_STATUS_INFO' ) ) {
	define( 'MWP_BLC_LINK_STATUS_INFO', 'info' );
}

if ( ! defined( 'MWP_BLC_LINK_STATUS_WARNING' ) ) {
	define( 'MWP_BLC_LINK_STATUS_WARNING', 'warning' );
}

if ( ! defined( 'MWP_BLC_LINK_STATUS_ERROR' ) ) {
	define( 'MWP_BLC_LINK_STATUS_ERROR', 'error' );
}

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
			die( json_encode( array( 'error' => __( 'Every hour value can not be empty.', 'mainwp-broken-links-checker-extension' ) ) ) );
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
		$this->do_load_sites( 'sync' , true, true );
	}

	function do_load_sites( $what = 'save_settings',  $with_postbox = false, $check_site_ids = false) {
		global $mainWPLinksCheckerExtensionActivator;

        if ($check_site_ids) {
            $sites_ids = isset($_POST['siteids']) ? $_POST['siteids'] : false;
            if ( empty($sites_ids) || !is_array( $sites_ids ) ) {
                die( json_encode( array( 'error' => __( 'Invalid site ID. Please try again.', 'mainwp-broken-links-checker-extension' ) ) ) );
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
			$title = __( 'Settings Synchronization', 'mainwp-broken-links-checker-extension' );
		} else if ($what == 'recheck') {
			$title = __( 'Recheck Links', 'mainwp-broken-links-checker-extension' );
		} else if ($what == 'sync') {
			$title = __( 'Links Synchronization', 'mainwp-broken-links-checker-extension' );
		}

		$html .= '<div class="ui modal" id="mainwp-blc-sync-modal">';
		$html .= '<div class="header">' . $title . '</div>';
		$html .= '<div class="scrolling content">';
		$html .= '<div class="ui relaxed divided list">';
		if ( is_array( $dbwebsites_activate_links ) && count( $dbwebsites_activate_links ) > 0 ) {
			foreach ( $dbwebsites_activate_links as $site ) {
				$html .= '<div class="item mainwpProccessSitesItem" status="queue" siteid="' . $site['id'] . '">' . stripslashes( $site['name'] ) . '<span class="right floated status"><i class="clock outline icon"></i></span></div>';
			}
		}
		$html .= '</div>';
		$html .= '</div>';
		$html .= '<div class="actions">';
		$html .= '<div class="ui cancel button">' . __( 'Close', 'mainwp-broken-links-checker-extension' ) . '</div>';
		$html .= '</div>';
		$html .= '</div>';

		if ( is_array( $dbwebsites_activate_links ) && count( $dbwebsites_activate_links ) > 0 ) {
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
		<div class="ui grid">
			<div class="twelve wide column">
				<h3 class="ui header handle-drag">
					Broken Links Checker
					<div class="sub header"><?php _e( 'Network Links Checker', 'mainwp-broken-links-checker-extension' ); ?></div>
				</h3>
			</div>
			<div class="four wide column right aligned"></div>
		</div>
		<div class="ui hidden divider"></div>
		<div class="ui grid">
			<div class="four wide column center aligned">
				<div class="ui large red statistic">
			    <div class="value"><?php echo $broken; ?></div>
			    <div class="label"><?php _e( 'Broken', 'mainwp-broken-links-checker-extension' ); ?></div>
			  </div>
			</div>
			<div class="four wide column center aligned">
				<div class="ui large statistic">
			    <div class="value"><?php echo $redirects; ?></div>
			    <div class="label"><?php _e( 'Redirected', 'mainwp-broken-links-checker-extension' ); ?></div>
			  </div>
			</div>
			<div class="four wide column center aligned">
				<div class="ui large statistic">
			    <div class="value"><?php echo $dismissed; ?></div>
			    <div class="label"><?php _e( 'Dismissed', 'mainwp-broken-links-checker-extension' ); ?></div>
			  </div>
			</div>
			<div class="four wide column center aligned">
				<div class="ui large green statistic">
			    <div class="value"><?php echo $all; ?></div>
			    <div class="label"><?php _e( 'All Links', 'mainwp-broken-links-checker-extension' ); ?></div>
			  </div>
			</div>
		</div>
		<div class="ui hidden divider"></div>
		<div class="ui center aligned segment">
			<a href="admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension" class="ui big green button"><?php _e( 'Broken Links Checker Dashboard','mainwp-broken-links-checker-extension' ); ?></a>
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
		$link_prefix = esc_attr( 'admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension&site_id=' . $site_id . '&tab=links&filter_id=' );
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

		<div class="ui grid">
			<div class="twelve wide column">
				<h3 class="ui header handle-drag">
					Broken Links Checker
					<div class="sub header"><?php _e( 'Network Links Checker', 'mainwp-broken-links-checker-extension' ); ?></div>
				</h3>
			</div>
			<div class="four wide column right aligned"></div>
		</div>
		<div class="ui hidden divider"></div>
		<?php if ( !$result || !$result->active ) : ?>
			<h2 class="ui icon header">
        <i class="unlink icon"></i>
        <div class="content">
          No links available!
					<div class="sub header">Make sure you have the Broken Links Checker plugin installed.</div>
        </div>
      </h2>
		<?php else : ?>
			<div class="ui grid">
				<div class="four wide column center aligned">
					<div class="ui large red statistic">
				    <div class="value"><?php echo $broken_link; ?></div>
				    <div class="label"><?php _e( 'Broken', 'mainwp-broken-links-checker-extension' ); ?></div>
				  </div>
				</div>
				<div class="four wide column center aligned">
					<div class="ui large statistic">
				    <div class="value"><?php echo $redirects_link; ?></div>
				    <div class="label"><?php _e( 'Redirected', 'mainwp-broken-links-checker-extension' ); ?></div>
				  </div>
				</div>
				<div class="four wide column center aligned">
					<div class="ui large statistic">
				    <div class="value"><?php echo $dismissed_link; ?></div>
				    <div class="label"><?php _e( 'Dismissed', 'mainwp-broken-links-checker-extension' ); ?></div>
				  </div>
				</div>
				<div class="four wide column center aligned">
					<div class="ui large green statistic">
				    <div class="value"><?php echo $all_link; ?></div>
				    <div class="label"><?php _e( 'All Links', 'mainwp-broken-links-checker-extension' ); ?></div>
				  </div>
				</div>
			</div>
		<?php endif; ?>
		<div class="ui hidden divider"></div>
		<div class="ui center aligned segment">
			<a href="admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension" class="ui big green button"><?php _e( 'Broken Links Checker Dashboard','mainwp-broken-links-checker-extension' ); ?></a>
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

	public static function render_tabs() {

		$curent_tab = 'dashboard';

    if ( isset( $_GET['tab'] ) ) {
      if ( 'settings' == $_GET['tab'] ) {
        $curent_tab = 'settings';
      } else if ( 'links' == $_GET['tab'] ) {
				$curent_tab = 'links';
			}
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
		$total_count = MainWP_Links_Checker_DB::get_instance()->get_filter_links( $selected_site_ids, $current_filters, $params );
		// to get links
		unset($params['count_only']);

		$search_links_data = self::get_search_links( $selected_site_ids, $current_filters, $params );

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
			$search = $_GET['s'];
		}

		$dbwebsites_dashboard_linkschecker = MainWP_Links_Checker_Dashboard::get_instance()->get_websites_linkschecker( $dbwebsites, $selected_dashboard_group, $search, $linkschecker_data );
		$dbwebsites_linkschecker = MainWP_Links_Checker_Dashboard::get_instance()->get_websites_linkschecker( $dbwebsites, 0, '', $linkschecker_data );

		unset( $dbwebsites );
		?>
		<div class="ui labeled icon inverted menu mainwp-sub-submenu" id="mainwp-broken-links-checker-menu">
			<a href="admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension&tab=dashboard" class="item <?php echo ( $curent_tab == 'dashboard' ? 'active' : '' ); ?>"><i class="tasks icon"></i> <?php _e( 'Dashboard', 'mainwp-broken-links-checker-extension' ); ?></a>
			<a href="admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension&tab=links" class="item <?php echo ( $curent_tab == 'links' ? 'active' : '' ); ?>"><i class="linkify icon"></i> <?php _e( 'Links', 'mainwp-broken-links-checker-extension' ); ?></a>
			<a href="admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension&tab=settings" class="item <?php echo ( $curent_tab == 'settings' ? 'active' : '' ); ?>"><i class="cog icon"></i> <?php _e( 'Broken Links Checker Settings', 'mainwp-broken-links-checker-extension' ); ?></a>
		</div>
		<?php if ( $curent_tab == 'dashboard' || $curent_tab == '' ) : ?>
			<div id="mainwp-broken-links-checker-dashboard-tab">
				<?php MainWP_Links_Checker_Dashboard::render_actions_bar(); ?>
				<div class="ui segment">
					<div class="ui message" id="mainwp-message-zone" style="display:none"></div>
					<?php MainWP_Links_Checker_Dashboard::gen_dashboard_tab( $dbwebsites_dashboard_linkschecker ); ?>
				</div>
			</div>
		<?php endif; ?>
		<?php if ( $curent_tab == 'links' ) : ?>
			<div id="mainwp-broken-links-checker-links-tab">
				<div class="mainwp-actions-bar">
					<div class="ui grid">
						<div class="middle aligned column">
							<?php MainWP_Links_Checker::get_instance()->gen_nav_filters( $dbwebsites_dashboard_linkschecker, $current_filters ); ?>
						</div>
					</div>
				</div>
				<?php self::gen_broken_links_tab( $search_links_data, $current_filters, $sites_url ); ?>
			</div>
		<?php endif; ?>
		<?php if ( $curent_tab == 'settings' ) : ?>
			<div class="ui alt segment" id="mainwp-broken-links-checker-settings-tab">
				<div class="mainwp-main-content">
					<?php self::gen_settings_tab(); ?>
				</div>
				<div class="mainwp-side-content">
					<p><?php echo __( 'The Broken Link Checker Extension combines the power of your MainWP Dashboard with the popular Broken Link Checker WordPress Plugin to display any broken links across your network in an easy to use central location.', 'mainwp-broken-links-checker-extension' ); ?></p>
					<p><?php echo __( 'It will allow you to Edit, Link/Unlink and Dismiss links quickly and easily with no need to login to your child site to fix problems.', 'mainwp-broken-links-checker-extension' ); ?></p>
					<a class="ui green big fluid button" target="_blank" href="https://mainwp.com/help/docs/broken-links-checker-extension/"><?php echo __( 'Extension Documentation', 'mainwp-broken-links-checker-extension' ); ?></a>
				</div>
				<div class="ui clearing hidden divider"></div>
			</div>
		<?php endif; ?>
    <?php
	}

	static function gen_settings_tab() {
		$check_threshold = MainWP_Links_Checker::get_instance()->get_option( 'check_threshold', 72 );
		?>
		<div class="ui form">
			<div class="ui hidden divider"></div>
			<div class="ui message" id="mainwp-message-zone" style="display:none"></div>
			<h3 class="header"><?php echo __( 'Broken Links Checker Options', 'mainwp-broken-links-checker-extension' ); ?></h3>
			<div class="ui grid field">
				<label class="six wide column middle aligned"><?php _e( 'Check each link (hours)', 'mainwp-broken-links-checker-extension' ); ?></label>
			  <div class="ten wide column" data-tooltip="<?php _e( 'Click this button to make the plugin empty its link database and recheck the entire site from scratch. Please, be patient until new list gets generated.', 'mainwp-broken-links-checker-extension' ); ?>" data-inverted="">
					<input type="text" value="<?php echo $check_threshold; ?>" id="check_threshold" name="check_threshold">
				</div>
			</div>
			<div class="ui grid field">
				<label class="six wide column middle aligned"><?php _e( 'Force recheck', 'mainwp-broken-links-checker-extension' ); ?></label>
			  <div class="ten wide column">
					<span data-tooltip="<?php _e( 'Existing links will be checked this often. New links will usually be checked ASAP.', 'mainwp-broken-links-checker-extension' ); ?>" data-inverted="">
						<input type="hidden" id="recheck" value="" name="recheck">
						<input type="button" value="<?php _e( 'Re-check All Pages', 'mainwp-broken-links-checker-extension' ); ?>" id="mwp-blc-start-recheck-btn" name="mwp-blc-start-recheck-btn" class="ui green basic button">
					</span>
				</div>
			</div>
			<div class="ui grid field">
				<label class="six wide column middle aligned"><?php _e( 'Max links per site', 'mainwp-broken-links-checker-extension' ); ?></label>
			  <div class="ten wide column">
					<input type="text" name="max_number_of_links" id="max_number_of_links" value="<?php echo get_option( 'mainwp_blc_max_number_of_links', 50 ); ?>">
				</div>
			</div>
			<div class="ui divider"></div>
			<input type="button" name="button_preview" id="mwp-blc-save-settings-btn" class="ui big right floated green button" value="<?php _e( 'Save Settings', 'mainwp-broken-links-checker-extension' ); ?>">
		</div>
    <?php
	}

	static function gen_broken_links_tab( $links, $filters, $sites_url ) {
		?>
		<div class="ui segment">
			<table class="ui table color-code-link-status" id="mainwp-blc-links-table">
	      <thead>
		      <tr>
	          <th id="title"><?php _e( 'URL','mainwp-broken-links-checker-extension' ); ?></th>
						<th id="status"><?php _e( 'Status','mainwp-broken-links-checker-extension' ); ?></th>
						<th id="new-link-text"><?php _e( 'Links Text','mainwp-broken-links-checker-extension' ); ?></th>
						<th id="redirect-url"><?php _e( 'Redirect URL','mainwp-broken-links-checker-extension' ); ?></th>
						<th id="source"><?php _e( 'Source','mainwp-broken-links-checker-extension' ); ?></th>
						<th id="url"><?php _e( 'Site URL','mainwp-broken-links-checker-extension' ); ?></th>
						<th class="collapsing no-sort"></th>
		      </tr>
	      </thead>
				<tbody>
					<?php self::render_table_links_content( $links, $sites_url ); ?>
				</tbody>
				<thead>
		      <tr>
	          <th id="title"><?php _e( 'URL','mainwp-broken-links-checker-extension' ); ?></th>
						<th id="status"><?php _e( 'Status','mainwp-broken-links-checker-extension' ); ?></th>
						<th id="new-link-text"><?php _e( 'Links Text','mainwp-broken-links-checker-extension' ); ?></th>
						<th id="redirect-url"><?php _e( 'Redirect URL','mainwp-broken-links-checker-extension' ); ?></th>
						<th id="source"><?php _e( 'Source','mainwp-broken-links-checker-extension' ); ?></th>
						<th id="url"><?php _e( 'Site URL','mainwp-broken-links-checker-extension' ); ?></th>
						<th class="collapsing no-sort"></th>
		      </tr>
	      </thead>
		  </table>
			<script type="text/javascript">
			jQuery( '#mainwp-blc-links-table' ).DataTable( {
				"columnDefs": [ { "orderable": false, "targets": "no-sort" } ],
				"order": [ [ 0, "asc" ] ],
				"language": { "emptyTable": "No links found." }
			} );
			</script>
		</div>
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

	function link_details_row( $link , $extra) {
		?>
		<div class="ui modal" id="link-details-<?php echo $link->link_id; ?>-siteid-<?php echo $link->site_id; ?>">
			<div class="header">Details</div>
			<div class="scrolling content">
 				<?php $this->details_row_contents( $link, $extra ); ?>
			</div>
			<div class="actions">
				<div class="ui cancel button">Close</div>
			</div>
		</div>
		<?php
	}

	public static function details_row_contents( $link, $extra ) {
		?>
        <div class="blc-detail-container">
            <div class="blc-detail-block" style="float: left; width: 49%;">
              <ol style='list-style-type: none;'>
                <?php if ( ! empty( $extra['post_date'] ) ) { ?>
                <li><strong><?php _e( 'Post published on' ); ?>:</strong><span class='post_date'><?php echo date_i18n( get_option( 'date_format' ),strtotime( $extra['post_date'] ) ); ?></span></li>
                <?php } ?>
                <li><strong><?php _e( 'Link last checked' ); ?>:</strong> <span class='check_date'><?php $last_check = $link->last_check;
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

			$extra_info = unserialize( base64_decode( $link->extra_info ) );

			if ( !is_array( $extra_info ) )
				$extra_info = array();

			$status = self::analyse_status( $link );
			$rownum++;

			$rowclass = ( $rownum % 2 )? 'alternate' : '';
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
      	<td><?php self::column_new_url( $link ); ?></td>
        <td><?php MainWP_Links_Checker::get_instance()->column_status( $link, $status ); ?></td>
        <td><?php echo $link_text; ?></td>
        <td><?php MainWP_Links_Checker::get_instance()->column_redirect_url( $link ); ?></td>
        <td>
					<?php $site_url = $sites_url[ $link->site_id ]; ?>
          <?php MainWP_Links_Checker::get_instance()->column_source( $link, $site_url ); ?>
        </td>
        <td><a href="<?php echo $sites_url[ $link->site_id ]; ?>" target="_blank"><?php echo $sites_url[ $link->site_id ]; ?></a></td>
				<td>
					<div class="ui left pointing dropdown icon mini basic green button" style="z-index:999">
						<a href="javascript:void(0)"><i class="ellipsis horizontal icon"></i></a>
						<div class="menu">
							<a class="item mwp-blc-edit-button" href='javascript:void(0)'><?php _e( 'Edit URL', 'mainwp-broken-links-checker-extension' ); ?></a>
							<a class="item submitdelete mwp-blc-unlink-button" href='javascript:void(0)'><?php _e( 'Unlink', 'mainwp-broken-links-checker-extension' ); ?></a>
							<?php if ( $link->broken ) : ?>
								<a class="item mwp-blc-discard-button" href="#"><?php _e( 'Not Broken', 'mainwp-broken-links-checker-extension' ); ?></a>
							<?php endif; ?>
							<?php if ( ! $link->dismissed && ( $link->broken || ( $link->redirect_count > 0 ) ) ) : ?>
								<a class="item mwp-blc-dismiss-button" href="#"><?php _e( 'Dismiss', 'mainwp-broken-links-checker-extension' ); ?></a>
							<?php endif; ?>
							<?php if ( $link->dismissed ) : ?>
								<a class="item blc-undismiss-button" href="#"><?php _e( 'Undismiss', 'mainwp-broken-links-checker-extension' ); ?></a>
							<?php endif; ?>
							<a class="item blc-details-button" href="#"><?php _e( 'Details', 'mainwp-broken-links-checker-extension' ); ?></a>
						</div>
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

			printf( '<span class="link-status-row link-status-%s"><span><span class="http-code">%s</span> <span class="status-text">%s</span></span></span>',
				$status['code'],
				empty( $link->http_code )?'':$link->http_code,
				$status['text']
			);

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
    <a href="<?php print esc_attr( $link->url ); ?>" target='_blank' class='blc-link-url' title="<?php echo esc_attr( $link->url ); ?>"><?php print $link->url; ?></a>
    <div class="mwp-blc-url-editor-buttons" style="display:none">
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
				echo $html;
				if ( $extra['source_data']['comment_id'] && ($extra['source_data']['comment_status'] != 'trash') && ($extra['source_data']['comment_status'] != 'spam') ) { ?>
          <span class="hidden source_column_data" data-comment_id="<?php echo $extra['source_data']['comment_id']; ?>" data-site_id_encode="<?php echo base64_encode( $link->site_id ); ?>"></span>
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
        <?php
				}
			}
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


}
