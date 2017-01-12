<?php
class MainWP_Links_Checker_Dashboard
{
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
			return $this->option[ $key ]; }
		return $default;
	}

	public function set_option( $key, $value ) {
		$this->option[ $key ] = $value;
		return update_option( $this->option_handle, $this->option );
	}

	public static function gen_dashboard_tab( $websites ) {
		$_orderby = 'name';
		$_order = 'desc';
		if ( isset( $_GET['orderby'] ) && ! empty( $_GET['orderby'] ) ) {
			$_orderby = $_GET['orderby'];
		}
		if ( isset( $_GET['order'] ) && ! empty( $_GET['order'] ) ) {
			$_order = ('desc' == $_GET['order']) ? 'asc' : 'desc';
		}

		$site_order = $url_order = $broken_order = $redirects_order = $dismissed_order = $all_order = $version_order = $hidden_order = '';
		if ( 'site' == $_orderby ) {
			$site_order = ('desc' == $_order) ? 'asc' : 'desc';
		} else if ( 'url' == $_orderby ) {
			$url_order = ('desc' == $_order) ? 'asc' : 'desc';
		} else if ( 'broken' == $_orderby ) {
			$broken_order = ('desc' == $_order) ? 'asc' : 'desc';
		} else if ( 'redirects' == $_orderby ) {
			$redirects_order = ('desc' == $_order) ? 'asc' : 'desc';
		} else if ( 'dismissed' == $_orderby ) {
			$dismissed_order = ('desc' == $_order) ? 'asc' : 'desc';
		} else if ( 'all' == $_orderby ) {
			$all_order = ('desc' == $_order) ? 'asc' : 'desc';
		} else if ( 'version' == $_orderby ) {
			$version_order = ('desc' == $_order) ? 'asc' : 'desc';
		} else if ( 'hidden' == $_orderby ) {
			$hidden_order = ('desc' == $_order) ? 'asc' : 'desc';
		} else {
			$_orderby = 'name';
		}

		self::$order = $_order;
		self::$orderby = $_orderby;
		usort( $websites, array( 'MainWP_Links_Checker_Dashboard', 'dashboard_data_sort' ) );

	?>
       <table id="mainwp-table-plugins" class="wp-list-table widefat plugins" cellspacing="0">
         <thead>
         <tr>
           <th class="check-column">
               <input type="checkbox"  id="cb-select-all-2" >
           </th>
           <th scope="col" class="manage-column sortable <?php echo $site_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=site&order=<?php echo (empty( $site_order ) ? 'asc' : $site_order); ?>"><span><?php _e( 'Site','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
           </th>
           <th scope="col" class="manage-column sortable <?php echo $url_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=url&order=<?php echo (empty( $url_order ) ? 'asc' : $url_order); ?>"><span><?php _e( 'URL','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
           </th>            
           <th style="text-align: center;" scope="col" class="manage-column sortable <?php echo $broken_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=broken&order=<?php echo (empty( $broken_order ) ? 'asc' : $broken_order); ?>"><span><?php _e( 'Broken','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
           </th>       
            <th style="text-align: center;" scope="col" class="manage-column sortable <?php echo $redirects_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=redirects&order=<?php echo (empty( $redirects_order ) ? 'asc' : $redirects_order); ?>"><span><?php _e( 'Redirects','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
           </th>  
           <th style="text-align: center;" scope="col" class="manage-column sortable <?php echo $dismissed_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=dismissed&order=<?php echo (empty( $dismissed_order ) ? 'asc' : $dismissed_order); ?>"><span><?php _e( 'Dismissed','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
           </th> 
           <th style="text-align: center;" scope="col" class="manage-column sortable <?php echo $all_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=all&order=<?php echo (empty( $all_order ) ? 'asc' : $all_order); ?>"><span><?php _e( 'All','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
           </th>           
           <th style="text-align: center;" scope="col" class="manage-column sortable <?php echo $version_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=version&order=<?php echo (empty( $version_order ) ? 'asc' : $version_order); ?>"><span><?php _e( 'Plugin Version','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
           </th>           
           <th style="text-align: center;" scope="col" class="manage-column <?php echo $hidden_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=hidden&order=<?php echo (empty( $hidden_order ) ? 'asc' : $hidden_order); ?>"><span><?php _e( 'Plugin Hidden','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
           </th>
         </tr>
         </thead>
         <tfoot>
         <tr>
           <th class="check-column">
               <input type="checkbox"  id="cb-select-all-2" >
           </th>
           <th scope="col" class="manage-column sortable <?php echo $site_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=site&order=<?php echo (empty( $site_order ) ? 'asc' : $site_order); ?>"><span><?php _e( 'Site','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
           </th>
           <th scope="col" class="manage-column sortable <?php echo $url_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=url&order=<?php echo (empty( $url_order ) ? 'asc' : $url_order); ?>"><span><?php _e( 'URL','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
           </th>            
           <th style="text-align: center;" scope="col" class="manage-column sortable <?php echo $broken_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=broken&order=<?php echo (empty( $broken_order ) ? 'asc' : $broken_order); ?>"><span><?php _e( 'Broken','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
           </th>             
           <th style="text-align: center;" scope="col" class="manage-column sortable <?php echo $redirects_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=redirects&order=<?php echo (empty( $redirects_order ) ? 'asc' : $redirects_order); ?>"><span><?php _e( 'Redirects','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
           </th>             
           <th style="text-align: center;" scope="col" class="manage-column sortable <?php echo $dismissed_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=dismissed&order=<?php echo (empty( $dismissed_order ) ? 'asc' : $dismissed_order); ?>"><span><?php _e( 'Dismissed','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
           </th> 
           <th style="text-align: center;" scope="col" class="manage-column sortable <?php echo $all_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=all&order=<?php echo (empty( $all_order ) ? 'asc' : $all_order); ?>"><span><?php _e( 'All','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
           </th>           
           <th style="text-align: center;" scope="col" class="manage-column sortable <?php echo $version_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=version&order=<?php echo (empty( $version_order ) ? 'asc' : $version_order); ?>"><span><?php _e( 'Plugin Version','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
           </th>           
           <th style="text-align: center;" scope="col" class="manage-column <?php echo $hidden_order; ?>">
               <a href="?page=Extensions-Mainwp-Broken-Links-Checker-Extension&orderby=hidden&order=<?php echo (empty( $hidden_order ) ? 'asc' : $hidden_order); ?>"><span><?php _e( 'Plugin Hidden','mainwp-broken-links-checker-extension' ); ?></span><span class="sorting-indicator"></span></a>
           </th>
         </tr>
         </tfoot>
           <tbody id="the-mwp-linkschecker-list">
            <?php
			if ( is_array( $websites ) && count( $websites ) > 0 ) {
				self::get_dashboard_table_row( $websites );
			} else {
				_e( '<tr><td colspan="6">No websites were found with the Broken Link Checker plugin installed.</td></tr>' );
			}
			?>
           </tbody>
     </table>
	<?php
	}

	public static function get_dashboard_table_row( $websites ) {
		$dismiss = array();
		if ( session_id() == '' ) { session_start(); }
		if ( isset( $_SESSION['mainwp_linkschecker_dismiss_upgrade_plugin_notis'] ) ) {
			$dismiss = $_SESSION['mainwp_linkschecker_dismiss_upgrade_plugin_notis'];
		}
		if ( ! is_array( $dismiss ) ) {
			$dismiss = array(); }

		foreach ( $websites as $website ) {
			//print_r($website);
			$website_id = esc_attr( $website['id'] );
			$cls_active = (isset( $website['linkschecker_active'] ) && ! empty( $website['linkschecker_active'] )) ? 'active' : 'inactive';
			$cls_update = (isset( $website['linkschecker_upgrade'] )) ? 'update' : '';
			$cls_update = ('inactive' == $cls_active) ? 'update' : $cls_update;
			$showhide_action = (1 == $website['hide_linkschecker']) ? 'show' : 'hide';

			$showhide_link = $open_link = $invalid_link = $not_found_mess = '';
			$broken_link = $redirects_link = $dismissed_link = $all_link = '';
			$link_prefix = 'admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension&site_id=' . $website_id . '&filter_id=';

			if ( isset( $website['linkschecker_active'] ) && $website['linkschecker_active'] ) {
				$location = 'options-general.php?page=link-checker-settings';
				$open_link = ' | <span class="edit"><a href="admin.php?page=SiteOpen&newWindow=yes&websiteid=' . $website_id . '&location=' . base64_encode( $location ) . '" target="_blank">' . __( 'Broken Link Checker Settings', 'mainwp-broken-links-checker-extension' ) . '</a></span>';
				$broken_link = '<span class="edit"><a href="' . $link_prefix .'broken" >' . esc_html( $website['broken'] ) . '</a></span>';
				$redirects_link = '<span class="edit"><a href="' . $link_prefix .'redirects" >' . esc_html( $website['redirects'] ) . '</a></span>';
				$dismissed_link = '<span class="edit"><a href="' . $link_prefix .'dismissed">' . esc_html( $website['dismissed'] ) . '</a></span>';
				$all_link = '<span class="edit"><a href="' . $link_prefix .'all">' . esc_html( $website['all'] ) . '</a></span>';
				$showhide_link = ' | <a href="#" class="linkschecker_showhide_plugin" showhide="' . $showhide_action . '">'. ('show' === $showhide_action ? __( 'Show Broken Link Checker Plugin', 'mainwp-broken-links-checker-extension' ) : __( 'Hide Broken Link Checker Plugin', 'mainwp-broken-links-checker-extension' )) . '</a>';
			}

			$cls_update = ! empty( $invalid_link ) || ! empty( $not_found_mess ) ? 'update' : $cls_update;

			?>
			<tr class="<?php echo $cls_active . ' ' . $cls_update; ?>" website-id="<?php echo $website_id; ?>">
               <th class="check-column">
                   <input type="checkbox"  name="checked[]">
               </th>
               <td>
                   <a href="admin.php?page=managesites&dashboard=<?php echo $website_id; ?>"><?php echo esc_html( stripslashes( $website['name'] ) ); ?></a><br/>
                   <div class="row-actions"><span class="dashboard"><a href="admin.php?page=managesites&dashboard=<?php echo $website_id; ?>"><?php _e( 'Overview' );?></a></span> |  <span class="edit"><a href="admin.php?page=managesites&id=<?php echo $website_id; ?>"><?php _e( 'Edit' );?></a><?php echo $showhide_link; ?></span></div>                    
                   <div class="linkschecker-action-working"><span class="status" style="display:none;"></span><span class="loading" style="display:none;"><i class="fa fa-spinner fa-pulse"></i> <?php _e( 'Please wait...', 'mainwp-broken-links-checker-extension' ); ?></span></div>
               </td>
               <td>
                   <a href="<?php echo esc_attr( $website['url'] ); ?>" target="_blank"><?php echo esc_html( $website['url'] ); ?></a><br/>
                   <div class="row-actions"><span class="edit"><a target="_blank" href="admin.php?page=SiteOpen&newWindow=yes&websiteid=<?php echo $website_id; ?>"><?php _e( 'Open WP-Admin', 'mainwp-broken-links-checker-extension' );?></a></span><?php echo $open_link; ?></div>                    
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
				if ( isset( $website['linkschecker_plugin_version'] ) ) {
					echo $website['linkschecker_plugin_version']; } else {
					echo '&nbsp;'; }
				?>
				</td>     
				<td align="center">
                   <span class="plugin_hidden_title"><?php
						echo (1 == $website['hide_linkschecker']) ? __( 'Yes' ) : __( 'No' );
					?>
				 </span>
				</td>
			</tr>        
				<?php
				$active_link = $update_link = $version = $link_row = '';

				if ( ! isset( $dismiss[ $website_id ] ) ) {
					$plugin_slug = 'broken-link-checker/broken-link-checker.php';
					if ( isset( $website['linkschecker_active'] ) && empty( $website['linkschecker_active'] ) ) {
						$active_link = '<a href="#" class="linkschecker_active_plugin" >' . __( 'Activate Broken Link Checker plugin', 'mainwp-broken-links-checker-extension' ) . '</a>'; }

					if ( isset( $website['linkschecker_upgrade'] ) ) {
						if ( isset( $website['linkschecker_upgrade']['new_version'] ) ) {
							$version = $website['linkschecker_upgrade']['new_version']; }
						$update_link = ' | <a href="#" class="linkschecker_upgrade_plugin" >' . __( 'Update Broken Link Checker plugin', 'mainwp-broken-links-checker-extension' ). '</a>';
						if ( isset( $website['linkschecker_upgrade']['plugin'] ) ) {
							$plugin_slug = $website['linkschecker_upgrade']['plugin']; }
					}

					$link_row = $active_link . $update_link;
					$link_row = ltrim( $link_row, ' | ' );
				}

				if ( ! empty( $link_row ) || ! empty( $invalid_noti ) ) {
						?>
					 <tr class="plugin-update-tr">
						<td colspan="9" class="plugin-update">
							<?php if ( ! empty( $link_row ) ) { ?>
                         <div class="ext-upgrade-noti update-message" plugin-slug="<?php echo $plugin_slug; ?>" website-id="<?php echo $website_id; ?>" version="<?php echo esc_attr( $version ); ?>">
                             <span style="float:right"><a href="#" class="mwp-linkschecker-upgrade-noti-dismiss"><?php _e( 'Dismiss', 'mainwp-broken-links-checker-extension' ); ?></a></span>                    
								<?php echo $link_row; ?>
                             <span class="linkschecker-row-working"><span class="status"></span><i class="fa fa-spinner fa-pulse" style="display:none"></i></span>
                         </div>
							<?php } ?>      
							<?php echo $invalid_noti; ?>
                     </td>
					 </tr>
					<?php
				}
		}
	}

	public static function dashboard_data_sort( $a, $b ) {

		if ( 'version' == self::$orderby ) {
			$a = $a['linkschecker_plugin_version'];
			$b = $b['linkschecker_plugin_version'];
			$cmp = version_compare( $a, $b );
		} else if ( 'url' == self::$orderby ) {
			$a = $a['url'];
			$b = $b['url'];
			$cmp = strcmp( $a, $b );
		} else if ( 'broken' == self::$orderby  || 'redirects' == self::$orderby  ||
		            'dismissed' == self::$orderby || 'all' == self::$orderby ) {
			$a = $a[ self::$orderby ];
			$b = $b[ self::$orderby ];
			$cmp = $a - $b;
		} else if ( 'hidden' == self::$orderby ) {
			$a = $a['hide_linkschecker'];
			$b = $b['hide_linkschecker'];
			$cmp = $a - $b;
		} else {
			$a = $a['name'];
			$b = $b['name'];
			$cmp = strcmp( $a, $b );
		}
		if ( 0 == $cmp ) {
			return 0; }

		if ( 'desc' == self::$order ) {
			return ($cmp > 0) ? -1 : 1; } else {
			return ($cmp > 0) ? 1 : -1; }
	}

	public static function get_score_color( $score ) {
		if ( $score <= 20 ) {
			$color = 'red'; } else if ( $score > 21 && $score <= 80 ) {
			$color = 'yellow'; } else if ( $score > 80 ) {
				$color = 'green'; }
			return $color;
	}

	public function get_websites_linkschecker( $websites, $selected_group = 0, $search = '', $linkschecker_data = array(), $active_only = null) {
		$websites_plugin = array();

		if ( is_array( $websites ) && count( $websites ) ) {
			if ( empty( $selected_group ) ) {
				foreach ( $websites as $website ) {
					if ( $website && $website->plugins != '' ) {
						$plugins = json_decode( $website->plugins, 1 );						
						if ( is_array( $plugins ) && count( $plugins ) != 0 ) {
							foreach ( $plugins as $plugin ) {
								if ( 'broken-link-checker/broken-link-checker.php' == $plugin['slug'] || false !== strpos( $plugin['slug'], '/broken-link-checker.php' ) ) {					
									if (! empty($active_only) && ! $plugin['active'])
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
	
	public static function gen_select_boxs( $websites, $filters ) {
		$selected_group = isset($filters['group_id']) ? $filters['group_id'] : 0;
		global $mainWPLinksCheckerExtensionActivator;
		$groups = apply_filters( 'mainwp-getgroups', $mainWPLinksCheckerExtensionActivator->get_child_file(), $mainWPLinksCheckerExtensionActivator->get_child_key(), null );
		$search = (isset( $_GET['s'] ) && ! empty( $_GET['s'] )) ? trim( $_GET['s'] ) : '';
		?> 
                   
        <div class="alignleft actions bulkactions">
            <select id="mwp_linkschecker_action">
                <option selected="selected" value="-1"><?php _e( 'Bulk Actions', 'mainwp-broken-links-checker-extension' ); ?></option>
                <option value="activate-selected"><?php _e( 'Active', 'mainwp-broken-links-checker-extension' ); ?></option>
                <option value="update-selected"><?php _e( 'Update', 'mainwp-broken-links-checker-extension' ); ?></option>
                <option value="hide-selected"><?php _e( 'Hide', 'mainwp-broken-links-checker-extension' ); ?></option>
                <option value="show-selected"><?php _e( 'Show', 'mainwp-broken-links-checker-extension' ); ?></option>
            </select>
            <input type="button" value="<?php _e( 'Apply', 'mainwp-broken-links-checker-extension' ); ?>" class="button action" id="mwp_linkschecker_doaction_btn" name="">            
        </div>
                   
        <div class="alignleft actions">
            <form action="" method="GET">
                <input type="hidden" name="page" value="Extensions-Mainwp-Broken-Links-Checker-Extension">
                <span role="status" aria-live="polite" class="ui-helper-hidden-accessible"><?php _e( 'No search results.','mainwp-broken-links-checker-extension' ); ?></span>
                <input type="text" class="mainwp_autocomplete ui-autocomplete-input" name="s" autocompletelist="sites" value="<?php echo esc_attr( stripslashes( $search ) ); ?>" autocomplete="off">
                <datalist id="sites">
                    <?php
					if ( is_array( $websites ) && count( $websites ) > 0 ) {
						foreach ( $websites as $website ) {
							echo '<option>' . stripslashes( $website['name'] ) . '</option>';
						}
					}
					?>                
                </datalist>
                <input type="submit" name="" class="button" value="<?php _e( 'Search Sites', 'mainwp-broken-links-checker-extension' ); ?>">
            </form>
        </div>    
        <div class="alignleft actions">
            <form method="post" action="admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension">
                <select name="mainwp_linkschecker_groups_select">
                <option value="0"><?php _e( 'Select a group' ); ?></option>
                <?php
				if ( is_array( $groups ) && count( $groups ) > 0 ) {
					foreach ( $groups as $group ) {
						$_select = '';
						if ( $selected_group == $group['id'] ) {
							$_select = 'selected '; }
						echo '<option value="' . esc_attr( $group['id'] ) . '" ' . $_select . '>' . esc_html( $group['name'] ) . '</option>';
					}
				}
				?>
                </select>                     
                <input class="button" type="button" name="mwp_linkschecker_btn_display" id="mwp_linkschecker_btn_display"value="<?php _e( 'Display', 'mainwp-broken-links-checker-extension' ); ?>">
            </form>  
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
					MainWP_Links_Checker_DB::get_instance()->update_links_checker(array(
						'site_id' => $website['id'],
						'hide_plugin' => ('hide' === $showhide) ? 1 : 0,
						)
					); }
			}
			die( json_encode( $information ) );
		}
		die();
	}
}
