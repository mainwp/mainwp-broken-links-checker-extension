<?php
class MainWP_Links_Checker_Dashboard {

	private $option_handle = 'mainwp_linkschecker_dashboard_option';
	private $option = array();
	private static $order = '';
	private static $orderby = '';

	//Singleton
	private static $instance = null;

	static function get_instance() {
		if ( null == MainWP_Links_Checker_Dashboard::$instance ) {
			MainWP_Links_Checker_Dashboard::$instance = new MainWP_Links_Checker_Dashboard();
		}
		return MainWP_Links_Checker_Dashboard::$instance;
	}

	public function __construct() {
		$this->option = get_option( $this->option_handle );
	}

	public function admin_init() {
		add_action( 'wp_ajax_mainwp_linkschecker_upgrade_noti_dismiss', array( $this, 'dismiss_notice' ) );
		add_action( 'wp_ajax_mainwp_linkschecker_active_plugin', array( $this, 'active_plugin' ) );
		add_action( 'wp_ajax_mainwp_linkschecker_upgrade_plugin', array( $this, 'upgrade_plugin' ) );
		add_action( 'wp_ajax_mainwp_linkschecker_showhide_linkschecker', array( $this, 'showhide_linkschecker' ) );
	}

	public function get_option( $key = null, $default = '' ) {
		if ( isset( $this->option[ $key ] ) ) {
			return $this->option[ $key ];
		}
		return $default;
	}

	public function set_option( $key, $value ) {
		$this->option[ $key ] = $value;
		return update_option( $this->option_handle, $this->option );
	}

	public static function gen_dashboard_tab( $websites ) {
		?>
		<table class="ui single line table" id="mainwp-broken-links-checker-sites-table">
   		<thead>
         <tr>
					 <th class="no-sort collapsing check-column"><span class="ui checkbox"><input type="checkbox"></span></th>
					 <th><?php _e( 'Site', 'mainwp-broken-links-checker-extension' ); ?></th>
					 <th class="no-sort collapsing"><i class="sign in icon"></i></th>
					 <th><?php _e( 'URL', 'mainwp-broken-links-checker-extension' ); ?></th>
					 <th><?php _e( 'Broken', 'mainwp-broken-links-checker-extension' ); ?></th>
					 <th><?php _e( 'Redirects', 'mainwp-broken-links-checker-extension' ); ?></th>
					 <th><?php _e( 'Dismissed', 'mainwp-broken-links-checker-extension' ); ?></th>
					 <th><?php _e( 'All', 'mainwp-broken-links-checker-extension' ); ?></th>
					 <th><?php _e( 'Version', 'mainwp-broken-links-checker-extension' ); ?></th>
					 <th><?php _e( 'Hidden', 'mainwp-broken-links-checker-extension' ); ?></th>
					 <th class="no-sort collapsing"><?php _e( '', 'mainwp-broken-links-checker-extension' ); ?></th>
         </tr>
       </thead>
			 <tbody>
 				<?php if ( is_array( $websites ) && count( $websites ) > 0 ) : ?>
 					<?php self::get_dashboard_table_row( $websites ); ?>
 				<?php else : ?>
 					<tr>
 						<td colspan="11"><?php _e( 'No websites were found with the Broken Links Checker plugin installed.', 'mainwp-broken-links-checker-extension' ); ?></td>
 					</tr>
 				<?php endif; ?>
 			</tbody>
		  <tfoot>
      	<tr>
		   		<th class="no-sort collapsing"><span class="ui checkbox"><input type="checkbox"></span></th>
		  		<th><?php _e( 'Site', 'mainwp-broken-links-checker-extension' ); ?></th>
		  		<th class="no-sort collapsing"><i class="sign in icon"></i></th>
		  		<th><?php _e( 'URL', 'mainwp-broken-links-checker-extension' ); ?></th>
		  		<th><?php _e( 'Broken', 'mainwp-broken-links-checker-extension' ); ?></th>
		  		<th><?php _e( 'Redirects', 'mainwp-broken-links-checker-extension' ); ?></th>
		  		<th><?php _e( 'Dismissed', 'mainwp-broken-links-checker-extension' ); ?></th>
		  		<th><?php _e( 'All', 'mainwp-broken-links-checker-extension' ); ?></th>
		  		<th><?php _e( 'Version', 'mainwp-broken-links-checker-extension' ); ?></th>
		  		<th><?php _e( 'Hidden', 'mainwp-broken-links-checker-extension' ); ?></th>
		  		<th class="no-sort collapsing"><?php _e( '', 'mainwp-broken-links-checker-extension' ); ?></th>
      	</tr>
    	</tfoot>
		</table>
		<script type="text/javascript">
            jQuery( '#mainwp-broken-links-checker-sites-table' ).DataTable( {
                    "columnDefs": [ { "orderable": false, "targets": "no-sort" } ],
                    "order": [ [ 1, "asc" ] ],
                    //"pageLength": 1,
                    "drawCallback": function( settings ) {
                        jQuery('#mainwp-broken-links-checker-sites-table .ui.checkbox').checkbox();
                        jQuery( '#mainwp-broken-links-checker-sites-table .ui.dropdown').dropdown();
                    },
                    "language": { "emptyTable": "No websites were found with the Broken Links Checker plugin installed." }
            });
		</script>
	<?php
	}

	public static function get_dashboard_table_row( $websites ) {
		$plugin_slug = 'broken-link-checker/broken-link-checker.php';
		foreach ( $websites as $website ) {
			$website_id = esc_attr( $website['id'] );

			$class_active = ( isset( $website['linkschecker_active'] ) && ! empty( $website['linkschecker_active'] )) ? '' : 'negative';
			$class_update = ( isset( $website['linkschecker_upgrade'] ) ) ? 'warning' : '';
			$class_update = ( 'negative' == $class_active ) ? 'negative' : $class_update;

			$link_prefix = 'admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension&site_id=' . $website_id . '&tab=links&filter_id=';

			if ( isset( $website['linkschecker_active'] ) && $website['linkschecker_active'] ) {
				$location = 'options-general.php?page=link-checker-settings';
				$broken_link = '<a href="' . $link_prefix .'broken" >' . esc_html( $website['broken'] ) . '</a>';
				$redirects_link = '<a href="' . $link_prefix .'redirects" >' . esc_html( $website['redirects'] ) . '</a>';
				$dismissed_link = '<a href="' . $link_prefix .'dismissed">' . esc_html( $website['dismissed'] ) . '</a>';
				$all_link = '<a href="' . $link_prefix .'all">' . esc_html( $website['all'] ) . '</a>';
			} else {
				$location = '';
				$broken_link = 'N/A';
				$redirects_link = 'N/A';
				$dismissed_link = 'N/A';
				$all_link = 'N/A';
			}

			$version = "";

			if ( isset( $website['linkschecker_upgrade'] ) ) {
				if ( isset( $website['linkschecker_upgrade']['new_version'] ) ) {
					$version = $website['linkschecker_upgrade']['new_version'];
				}
				if ( isset( $website['linkschecker_upgrade']['plugin'] ) ) {
					$plugin_slug = $website['linkschecker_upgrade']['plugin'];
				}
			}
			?>

			<tr class="<?php echo $class_active . ' ' . $class_update; ?>" website-id="<?php echo $website_id; ?>" plugin-slug="<?php echo $plugin_slug; ?>" version="<?php echo $version; ?>">
				<td class="check-column"><span class="ui checkbox"><input type="checkbox" name="checked[]"></span></td>
				<td><a href="admin.php?page=managesites&dashboard=<?php echo $website_id; ?>"><?php echo stripslashes( $website['name'] ); ?></a></td>
				<td><a target="_blank" href="admin.php?page=SiteOpen&newWindow=yes&websiteid=<?php echo $website_id; ?>"><i class="sign in icon"></i></a></td>
				<td><a href="<?php echo $website['url']; ?>" target="_blank"><?php echo $website['url']; ?></a></td>
				<td><?php echo $broken_link; ?></td>
				<td><?php echo $redirects_link; ?></td>
				<td><?php echo $dismissed_link; ?></td>
				<td><?php echo $all_link; ?></td>
				<td><span class="status"></span><?php echo ( isset( $website['linkschecker_upgrade'] ) ) ? '<i class="exclamation circle icon"></i>' : ''; ?> <?php echo ( isset( $website['linkschecker_plugin_version'] ) ) ? $website['linkschecker_plugin_version'] : 'N/A'; ?></td>
				<td class="blc-visibility"><span class="visibility"></span><?php echo ( 1 == $website['hide_linkschecker'] ) ? __( 'Yes', 'mainwp-broken-links-checker-extension' ) : __( 'No', 'mainwp-broken-links-checker-extension' ); ?></td>
				<td>
					<div class="ui left pointing dropdown icon mini basic green button" style="z-index:999">
						<a href="javascript:void(0)"><i class="ellipsis horizontal icon"></i></a>
						<div class="menu">
							<a class="item" href="admin.php?page=managesites&dashboard=<?php echo $website_id; ?>"><?php _e( 'Overview', 'mainwp-broken-links-checker-extension' ); ?></a>
							<a class="item" href="admin.php?page=managesites&id=<?php echo $website_id; ?>"><?php _e( 'Edit', 'mainwp-broken-links-checker-extension' ); ?></a>
							<a class="item" href="admin.php?page=SiteOpen&newWindow=yes&websiteid=<?php echo $website_id; ?>&location=<?php echo base64_encode( $location ); ?>" target="_blank"><?php _e( 'Open Broken Links Checker', 'mainwp-broken-links-checker-extension' ); ?></a>
							<?php if ( 1 == $website['hide_linkschecker'] ) : ?>
							<a class="item linkschecker_showhide_plugin" href="#" showhide="show"><?php _e( 'Unhide Plugin', 'mainwp-broken-links-checker-extension' ); ?></a>
							<?php else : ?>
							<a class="item linkschecker_showhide_plugin" href="#" showhide="hide"><?php _e( 'Hide Plugin', 'mainwp-broken-links-checker-extension' ); ?></a>
							<?php endif; ?>
							<?php if ( isset( $website['linkschecker_active'] ) && empty( $website['linkschecker_active'] ) ) : ?>
							<a class="item linkschecker_active_plugin" href="#"><?php _e( 'Activate Plugin', 'mainwp-broken-links-checker-extension' ); ?></a>
							<?php endif; ?>
							<?php if ( isset( $website['linkschecker_upgrade'] ) ) : ?>
							<a class="item linkschecker_upgrade_plugin" href="#"><?php _e( 'Update Plugin', 'mainwp-broken-links-checker-extension' ); ?></a>
							<?php endif; ?>
						</div>
					</div>
				</td>
			</tr>
			<?php
		}
	}

	public function get_websites_linkschecker( $websites, $selected_group = 0, $search = '', $linkschecker_data = array(), $active_only = null ) {
		$websites_plugin = array();

		if ( is_array( $websites ) && count( $websites ) ) {
			if ( empty( $selected_group ) ) {
				foreach ( $websites as $website ) {
					if ( $website && $website->plugins != '' ) {
						$plugins = json_decode( $website->plugins, 1 );
						if ( is_array( $plugins ) && count( $plugins ) != 0 ) {
							foreach ( $plugins as $plugin ) {
								if ( 'broken-link-checker/broken-link-checker.php' == $plugin['slug'] || false !== strpos( $plugin['slug'], '/broken-link-checker.php' ) ) {
									if ( ! empty($active_only) && ! $plugin['active'] )
										break;

									$site = MainWP_Links_Checker_Utility::map_site( $website, array( 'id', 'name', 'url' ) );
									if ( $plugin['active'] ) {
										$site['linkschecker_active'] = 1;
									} else {
										$site['linkschecker_active'] = 0;
									}

									if ( is_array($linkschecker_data) && count($linkschecker_data) > 0 ) {
										$links_data = isset( $linkschecker_data[ $site['id'] ] ) ? $linkschecker_data[ $site['id'] ] : array();
										$site['hide_linkschecker'] = intval( $links_data['hide_plugin'] );
										$site['broken'] = $links_data['count_broken'];
										$site['redirects'] = $links_data['count_redirects'];
										$site['dismissed'] = $links_data['count_dismissed'];
										$site['all'] = $links_data['count_total'];
									}
									// get upgrade info
									$site['linkschecker_plugin_version'] = $plugin['version'];
									$plugin_upgrades = json_decode( $website->plugin_upgrades, 1 );
									if ( is_array( $plugin_upgrades ) && count( $plugin_upgrades ) > 0 ) {
										if ( isset( $plugin_upgrades['broken-link-checker/broken-link-checker.php'] ) ) {
											$upgrade = $plugin_upgrades['broken-link-checker/broken-link-checker.php'];
											if ( isset( $upgrade['update'] ) ) {
												$site['linkschecker_upgrade'] = $upgrade['update'];
											}
										}
									}
									$websites_plugin[] = $site;
									break;
								}
							}
						}
					}
				}
			} else {
				global $mainWPLinksCheckerExtensionActivator;

				$group_websites = apply_filters( 'mainwp-getdbsites', $mainWPLinksCheckerExtensionActivator->get_child_file(), $mainWPLinksCheckerExtensionActivator->get_child_key(), array(), array( $selected_group ) );
				$sites = array();
				foreach ( $group_websites as $site ) {
					$sites[] = $site->id;
				}
				foreach ( $websites as $website ) {
					if ( $website && $website->plugins != '' && in_array( $website->id, $sites ) ) {
						$plugins = json_decode( $website->plugins, 1 );
						if ( is_array( $plugins ) && count( $plugins ) != 0 ) {
							foreach ( $plugins as $plugin ) {
								if ( 'broken-link-checker/broken-link-checker.php' == $plugin['slug'] || false !== strpos( $plugin['slug'], '/broken-link-checker.php' ) ) {
									if (!empty($active_only) && !$plugin['active'])
										break;

									$site = MainWP_Links_Checker_Utility::map_site( $website, array( 'id', 'name', 'url' ) );
									if ( $plugin['active'] ) {
										$site['linkschecker_active'] = 1;
									} else {
										$site['linkschecker_active'] = 0;
									}

									if ( is_array($linkschecker_data) && count($linkschecker_data) > 0 ) {
										$links_data = isset( $linkschecker_data[ $site['id'] ] ) ? $linkschecker_data[ $site['id'] ] : array();
										$site['hide_linkschecker'] = intval( $links_data['hide_plugin'] );
										$site['broken'] = $links_data['count_broken'];
										$site['redirects'] = $links_data['count_redirects'];
										$site['dismissed'] = $links_data['count_dismissed'];
										$site['all'] = $links_data['count_total'];
									}
									// get upgrade info
									$site['linkschecker_plugin_version'] = $plugin['version'];
									$plugin_upgrades = json_decode( $website->plugin_upgrades, 1 );
									if ( is_array( $plugin_upgrades ) && count( $plugin_upgrades ) > 0 ) {
										if ( isset( $plugin_upgrades['broken-link-checker/broken-link-checker.php'] ) ) {
											$upgrade = $plugin_upgrades['broken-link-checker/broken-link-checker.php'];
											if ( isset( $upgrade['update'] ) ) {
												$site['linkschecker_upgrade'] = $upgrade['update'];
											}
										}
									}
									$websites_plugin[] = $site;
									break;
								}
							}
						}
					}
				}
			}
		}

		// if search action
		$search_sites = array();
		if ( ! empty( $search ) ) {
			$find = trim( $search );
			foreach ( $websites_plugin as $website ) {
				if ( stripos( $website['name'], $find ) !== false || stripos( $website['url'], $find ) !== false ) {
					$search_sites[] = $website;
				}
			}
			$websites_plugin = $search_sites;
		}
		unset( $search_sites );

		return $websites_plugin;
	}

	public static function render_actions_bar() {
		?>
		<div class="mainwp-actions-bar">
			<div class="ui grid">
				<div class="ui two column row">
					<div class="column">
						<select class="ui dropdown" id="mainwp-broken-links-checker-actions">
							<option value="-1"><?php _e( 'Bulk Actions', 'mainwp-broken-links-checker-extension' ); ?></option>
							<option value="activate-selected"><?php _e( 'Active', 'mainwp-broken-links-checker-extension' ); ?></option>
							<option value="update-selected"><?php _e( 'Update', 'mainwp-broken-links-checker-extension' ); ?></option>
							<option value="hide-selected"><?php _e( 'Hide', 'mainwp-broken-links-checker-extension' ); ?></option>
							<option value="show-selected"><?php _e( 'Show', 'mainwp-broken-links-checker-extension' ); ?></option>
						</select>
						<input type="button" name="mwp_blc_doaction_btn" id="mwp_blc_doaction_btn" class="ui basic button" value="<?php _e( 'Apply', 'mainwp-rocket-extension' ); ?>"/>
						<?php do_action( 'mainwp_blc_actions_bar_right' ); ?>
					</div>
					<div class="right aligned column">
						<a href="#" class="ui green right floated button" id="mwp_sync_links_data"><?php _e( 'Synchronize Links', 'mainwp-broken-links-checker-extension' ); ?></a>
						<?php do_action( 'mainwp_blc_actions_bar_right' ); ?>
					</div>
				</div>
			</div>
		</div>
    <?php
		return;
	}

	public function dismiss_notice() {
		$website_id = intval( $_POST['siteId'] );
		if ( $website_id ) {
			if ( session_id() == '' ) { session_start(); }
			$dismiss = $_SESSION['mainwp_linkschecker_dismiss_upgrade_plugin_notis'];
			if ( is_array( $dismiss ) && count( $dismiss ) > 0 ) {
				$dismiss[ $website_id ] = 1;
			} else {
				$dismiss = array();
				$dismiss[ $website_id ] = 1;
			}
			$_SESSION['mainwp_linkschecker_dismiss_upgrade_plugin_notis'] = $dismiss;
			die( 'updated' );
		}
		die( 'nochange' );
	}

	public function active_plugin() {
		do_action( 'mainwp_activePlugin' );
		die();
	}

	public function upgrade_plugin() {
		do_action( 'mainwp_upgradePluginTheme' );
		die();
	}

	public function showhide_linkschecker() {
		$siteid = isset( $_POST['websiteId'] ) ? $_POST['websiteId'] : null;
		$showhide = isset( $_POST['showhide'] ) ? $_POST['showhide'] : null;
		if ( null !== $siteid && null !== $showhide ) {
			global $mainWPLinksCheckerExtensionActivator;
			$post_data = array(
				'mwp_action' => 'set_showhide',
				'showhide' => $showhide,
			);
			$information = apply_filters( 'mainwp_fetchurlauthed', $mainWPLinksCheckerExtensionActivator->get_child_file(), $mainWPLinksCheckerExtensionActivator->get_child_key(), $siteid, 'links_checker', $post_data );

			if ( is_array( $information ) && isset( $information['result'] ) && 'SUCCESS' === $information['result'] ) {
				$website = apply_filters( 'mainwp-getsites', $mainWPLinksCheckerExtensionActivator->get_child_file(), $mainWPLinksCheckerExtensionActivator->get_child_key(), $siteid );
				if ( $website && is_array( $website ) ) {
					$website = current( $website );
				}

				if ( ! empty( $website ) ) {
					MainWP_Links_Checker_DB::get_instance()->update_links_checker( array(
						'site_id' => $website['id'],
						'hide_plugin' => ( 'hide' === $showhide ) ? 1 : 0,
						)
					);
				}
			}
			die( json_encode( $information ) );
		}
		die();
	}
}
