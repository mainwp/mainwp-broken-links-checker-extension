jQuery( document ).ready(function($) {
        $( 'a.blc_tab_lnk' ).on('click', function () {

            if ($(this).attr('href') !== '#')
                return;

            $( '.blc_tab_lnk' ).removeClass('mainwp_action_down');
            $( this ).addClass('mainwp_action_down');
            $( '.blc_tab_content' ).hide();
            $( '.blc_tab_content[data-tab="' + $(this).data('tab') + '"]' ).show();
            return false;
	});

	$( '#mwp_linkschecker_btn_display' ).live('click', function() {
		$( this ).closest( 'form' ).submit();
	});

	$( '.mwp-linkschecker-upgrade-noti-dismiss' ).live('click', function() {
		var parent = $( this ).closest( '.ext-upgrade-noti' );
		parent.hide();
		var data = {
			action: 'mainwp_linkschecker_upgrade_noti_dismiss',
			siteId: parent.attr( 'website-id' ),
		}
		jQuery.post(ajaxurl, data, function (response) {

		});
		return false;
	});

	  $( '.mwp-linkschecker-invalid-noti-dismiss' ).live('click', function() {
		  var parent = $( this ).closest( '.ext-invalid-noti' );
		  parent.hide();
		  var data = {
				action: 'mainwp_linkschecker_invalid_noti_dismiss',
				siteId: parent.attr( 'website-id' )
			}
			jQuery.post(ajaxurl, data, function (response) {

			});
			return false;
	  });

		$( '.linkschecker_active_plugin' ).on('click', function() {
			mainwp_linkschecker_active_start_specific( $( this ), false );
			return false;
		});

		$( '.linkschecker_upgrade_plugin' ).on('click', function() {
			mainwp_linkschecker_upgrade_start_specific( $( this ), false );
			return false;
		});

		$( '.linkschecker_showhide_plugin' ).on('click', function() {
			mainwp_linkschecker_showhide_start_specific( $( this ), false );
			return false;
		});

		$( '#mwp_linkschecker_doaction_btn' ).on('click', function() {
			var bulk_act = $( '#mwp_linkschecker_action' ).val();
			mainwp_linkschecker_do_bulk_action( bulk_act );

		});

		$( '#mwp-blc-save-settings-btn' ).live('click', function() {
			if ($( '#check_threshold' ).val() <= 0) {
				$( '#mwp-blc-setting-error-box' ).html( __( "Check each link can not be empty" ) ).fadeIn();
				return false;
			}

			var data = {
				action: 'mainwp_linkschecker_settings_loading_sites',
				check_threshold: $( '#check_threshold' ).val(),
				max_number_of_links: $( '#max_number_of_links' ).val()
			}

                        var me = $( this );
			$( '#mwp-blc-setting-error-box' ).hide();
			$( '#mainwp_blc_setting_loading' ).show();
			me.attr( "disabled","disabled" );
			jQuery.post(ajaxurl, data, function (response) {
				$( '#mainwp_blc_setting_loading' ).hide();
				if (response) {
					if (response['success'] && response['result']) {
						jQuery('#mwp-blc-save-settings-btn').remove();
						$( '#blc_settings_tab .postbox .inside' ).html( response.result );
						mainwp_linkschecker_save_settings_start_next();
					} else if (response['error']) {
						$( '#mwp-blc-setting-error-box' ).html( response.error ).fadeIn();
					} else {
						$( '#mwp-blc-setting-error-box' ).html( __( "Undefined error" ) ).fadeIn();
					}
				} else {
					$( '#mwp-blc-setting-error-box' ).html( __( "Undefined error" ) ).fadeIn();
				}
				me.removeAttr( 'disabled' );
			},'json');

		});

		$( '#mwp-blc-start-recheck-btn' ).live('click', function() {
			var data = {
				action: 'mainwp_linkschecker_settings_recheck_loading'
			}

			var me = $( this );

			$( '#mwp-blc-setting-error-box' ).hide();
			$( '#mainwp_blc_setting_recheck_loading' ).show();
			me.attr( "disabled","disabled" );
			jQuery.post(ajaxurl, data, function (response) {
				$( '#mainwp_blc_setting_recheck_loading' ).hide();
				if (response) {
					if (response['success'] && response['result']) {
						jQuery('#mwp-blc-save-settings-btn').remove();
						$( '#blc_settings_tab .postbox .inside' ).html( response.result );
						mainwp_linkschecker_settings_recheck_start_next();
					} else if (response['error']) {
						$( '#mwp-blc-setting-error-box' ).html( response.error ).fadeIn();
					} else {
						$( '#mwp-blc-setting-error-box' ).html( __( "Undefined error" ) ).fadeIn();
					}
				} else {
					$( '#mwp-blc-setting-error-box' ).html( __( "Undefined error" ) ).fadeIn();
				}
				me.removeAttr( 'disabled' );
			},'json');

		});

    $( '#mwp_sync_links_data' ).on('click', function() {
            var statusEl = $('.sync_links_working');
            statusEl.hide();
            statusEl.css( 'color', '#21759B' );
            var selector = '#the-mwp-linkschecker-list tr input[type="checkbox"]';
            var selected_ids = [];
            jQuery(selector).each(function(){
                    if (jQuery(this).is(':checked')) {
                            var row = jQuery(this).closest('tr');
                            selected_ids.push(row.attr('website-id'));
                    }
            });

            if (selected_ids.length == 0) {
                statusEl.html('Select sites you want to Sync links data.').fadeIn(1000);
                return false;
            }

            statusEl.html('<i class="fa fa-spinner fa-pulse"></i> Running... ').show();
            var data = {
                action: 'mainwp_linkschecker_load_sites',
                siteids: selected_ids
            }
            var me = $( this );
            me.attr( "disabled","disabled" );
            jQuery.post(ajaxurl, data, function (response) {
                statusEl.hide();
                if (response) {
                    if (response['success'] && response['result']) {
                        $( '#mainwp_blc_links_dashboard_content' ).html( response.result );
                        mainwp_linkschecker_sync_links_start_next();
                    } else if (response['error']) {
                        statusEl.css( 'color', 'red' );
                        statusEl.html( response.error ).fadeIn();
                    } else {
                        statusEl.css( 'color', 'red' );
                        statusEl.html( __( "Undefined error" ) ).fadeIn();
                    }
                } else {
                    statusEl.css( 'color', 'red' );
                    statusEl.html( __( "Undefined error" ) ).fadeIn();
                }
                me.removeAttr( 'disabled' );
            },'json');

        });
});


var linkschecker_bulkMaxThreads = 3;
var linkschecker_bulkTotalThreads = 0;
var linkschecker_bulkCurrentThreads = 0;
var linkschecker_bulkFinishedThreads = 0;

mainwp_linkschecker_do_bulk_action = function(act) {
	var selector = '';
	switch (act) {
		case 'activate-selected':
			selector = '#the-mwp-linkschecker-list tr.plugin-update-tr .linkschecker_active_plugin';
			jQuery( selector ).addClass( 'queue' );
			mainwp_linkschecker_active_start_next( selector );
			break;
		case 'update-selected':
			selector = '#the-mwp-linkschecker-list tr.plugin-update-tr .linkschecker_upgrade_plugin';
			jQuery( selector ).addClass( 'queue' );
			mainwp_linkschecker_upgrade_start_next( selector );
			break;
		case 'hide-selected':
			selector = '#the-mwp-linkschecker-list tr .linkschecker_showhide_plugin[showhide="hide"]';
			jQuery( selector ).addClass( 'queue' );
			mainwp_linkschecker_showhide_start_next( selector );
			break;
		case 'show-selected':
			selector = '#the-mwp-linkschecker-list tr .linkschecker_showhide_plugin[showhide="show"]';
			jQuery( selector ).addClass( 'queue' );
			mainwp_linkschecker_showhide_start_next( selector );
			break;
	}
}

mainwp_linkschecker_showhide_start_next = function(selector) {
	while ((objProcess = jQuery( selector + '.queue:first' )) && (objProcess.length > 0) && (linkschecker_bulkCurrentThreads < linkschecker_bulkMaxThreads)) {
		objProcess.removeClass( 'queue' );
		if (objProcess.closest( 'tr' ).find( '.check-column input[type="checkbox"]:checked' ).length == 0) {
			continue;
		}
		mainwp_linkschecker_showhide_start_specific( objProcess, true, selector );
	}
}

mainwp_linkschecker_showhide_start_specific = function(pObj, bulk, selector) {
	var parent = pObj.closest( 'tr' );
	var loader = parent.find( '.linkschecker-action-working .loading' );
	var statusEl = parent.find( '.linkschecker-action-working .status' );
	var showhide = pObj.attr( 'showhide' );
	if (bulk) {
		linkschecker_bulkCurrentThreads++; }

	var data = {
		action: 'mainwp_linkschecker_showhide_linkschecker',
		websiteId: parent.attr( 'website-id' ),
		showhide: showhide
	}
	statusEl.hide();
	loader.show();
	jQuery.post(ajaxurl, data, function (response) {
		loader.hide();
		pObj.removeClass( 'queue' );
		if (response && response['error']) {
			statusEl.css( 'color', 'red' );
			statusEl.html( response['error'] ).show();
		} else if (response && response['result'] == 'SUCCESS') {
			if (showhide == 'show') {
				pObj.text( __( "Hide Broken Link Checker Plugin" ) );
				pObj.attr( 'showhide', 'hide' );
				parent.find( '.plugin_hidden_title' ).html( __( 'No' ) );
			} else {
				pObj.text( __( "Show Broken Link Checker Plugin" ) );
				pObj.attr( 'showhide', 'show' );
				parent.find( '.plugin_hidden_title' ).html( __( 'Yes' ) );
			}

			statusEl.css( 'color', '#21759B' );
			statusEl.html( __( 'Successful' ) ).show();
			statusEl.fadeOut( 3000 );
		} else {
			statusEl.css( 'color', 'red' );
			statusEl.html( __( "Undefined error" ) ).show();
		}

		if (bulk) {
			linkschecker_bulkCurrentThreads--;
			linkschecker_bulkFinishedThreads++;
			mainwp_linkschecker_showhide_start_next( selector );
		}

	},'json');
	return false;
}

mainwp_linkschecker_upgrade_start_next = function(selector) {
	while ((objProcess = jQuery( selector + '.queue:first' )) && (objProcess.length > 0) && (objProcess.closest( 'tr' ).prev( 'tr' ).find( '.check-column input[type="checkbox"]:checked' ).length > 0) && (linkschecker_bulkCurrentThreads < linkschecker_bulkMaxThreads)) {
		objProcess.removeClass( 'queue' );
		if (objProcess.closest( 'tr' ).prev( 'tr' ).find( '.check-column input[type="checkbox"]:checked' ).length == 0) {
			continue;
		}
		mainwp_linkschecker_upgrade_start_specific( objProcess, true, selector );
	}
}

mainwp_linkschecker_upgrade_start_specific = function(pObj, bulk, selector) {
	var parent = pObj.closest( '.ext-upgrade-noti' );
	var workingRow = parent.find( '.linkschecker-row-working' );
	var slug = parent.attr( 'plugin-slug' );
	var data = {
		action: 'mainwp_linkschecker_upgrade_plugin',
		websiteId: parent.attr( 'website-id' ),
		type: 'plugin',
		'slugs[]': [slug]
	}

	if (bulk) {
		linkschecker_bulkCurrentThreads++; }

	workingRow.find( 'i' ).show();
	jQuery.post(ajaxurl, data, function (response) {
		workingRow.find( 'i' ).hide();
		pObj.removeClass( 'queue' );
		if (response && response['error']) {
			workingRow.find( '.status' ).html( '<font color="red">' + response['error'] + '</font>' );
		} else if (response && response['upgrades'][slug]) {
			pObj.after( 'Broken Link Checker plugin has been updated' );
			pObj.remove();
		} else {
			workingRow.find( '.status' ).html( '<font color="red">' + __( "Undefined error" ) + '</font>' );
		}

		if (bulk) {
			linkschecker_bulkCurrentThreads--;
			linkschecker_bulkFinishedThreads++;
			mainwp_linkschecker_upgrade_start_next( selector );
		}

	},'json');
	return false;
}


mainwp_linkschecker_active_start_next = function(selector) {
	while ((objProcess = jQuery( selector + '.queue:first' )) && (objProcess.length > 0) && (objProcess.closest( 'tr' ).prev( 'tr' ).find( '.check-column input[type="checkbox"]:checked' ).length > 0) && (linkschecker_bulkCurrentThreads < linkschecker_bulkMaxThreads)) {
		objProcess.removeClass( 'queue' );
		if (objProcess.closest( 'tr' ).prev( 'tr' ).find( '.check-column input[type="checkbox"]:checked' ).length == 0) {
			continue;
		}
		mainwp_linkschecker_active_start_specific( objProcess, true, selector );
	}
}

mainwp_linkschecker_active_start_specific = function(pObj, bulk, selector) {
	var parent = pObj.closest( '.ext-upgrade-noti' );
	var workingRow = parent.find( '.linkschecker-row-working' );
	var slug = parent.attr( 'plugin-slug' );
	var data = {
		action: 'mainwp_linkschecker_active_plugin',
		websiteId: parent.attr( 'website-id' ),
		'plugins[]': [slug]
	}

	if (bulk) {
		linkschecker_bulkCurrentThreads++; }

	workingRow.find( 'i' ).show();
	jQuery.post(ajaxurl, data, function (response) {
		workingRow.find( 'i' ).hide();
		pObj.removeClass( 'queue' );
		if (response && response['error']) {
			workingRow.find( '.status' ).html( '<font color="red">' + response['error'] + '</font>' );
		} else if (response && response['result']) {
			pObj.after( 'Broken Link Checker plugin has been activated' );
			pObj.remove();
		}
		if (bulk) {
			linkschecker_bulkCurrentThreads--;
			linkschecker_bulkFinishedThreads++;
			mainwp_linkschecker_active_start_next( selector );
		}

	},'json');
	return false;
}

mainwp_linkschecker_save_settings_start_next = function()
{
	if (linkschecker_bulkTotalThreads == 0) {
		linkschecker_bulkTotalThreads = jQuery( '.mainwpProccessSitesItem[status="queue"]' ).length; }

	while ((siteToProcess = jQuery( '.mainwpProccessSitesItem[status="queue"]:first' )) && (siteToProcess.length > 0)  && (linkschecker_bulkCurrentThreads < linkschecker_bulkMaxThreads)) {
		mainwp_linkschecker_save_settings_start_specific( siteToProcess );
	}
};

mainwp_linkschecker_save_settings_start_specific = function (pSiteToProcess)
{
	linkschecker_bulkCurrentThreads++;
	pSiteToProcess.attr( 'status', 'progress' );
	var statusEl = pSiteToProcess.find( '.status' ).html( '<i class="fa fa-spinner fa-pulse"></i> ' + 'running ...' );

	var data = {
		action:'mainwp_linkschecker_performsavelinkscheckersettings',
		siteId: pSiteToProcess.attr( 'siteid' ),
	};
        var $container = jQuery( '#blc_settings_tab .postbox .inside' );
	jQuery.post(ajaxurl, data, function (response)
		{
		pSiteToProcess.attr( 'status', 'done' );
		if (response) {
			if (response['result'] == 'NOTCHANGE') {
				statusEl.html( 'Settings saved with no changes.' ).fadeIn();
			} else if (response['result'] == 'SUCCESS') {
				statusEl.html( 'Successful' ).show();
			} else if (response['error']) {
				statusEl.html( response['error'] ).show();
				statusEl.css( 'color', 'red' );
			} else {
				statusEl.html( __( 'Undefined Error' ) ).show();
				statusEl.css( 'color', 'red' );
			}
		} else {
			statusEl.html( __( 'Undefined Error' ) ).show();
			statusEl.css( 'color', 'red' );
		}

		linkschecker_bulkCurrentThreads--;
		linkschecker_bulkFinishedThreads++;
		if (linkschecker_bulkFinishedThreads == linkschecker_bulkTotalThreads && linkschecker_bulkFinishedThreads != 0) {
                        $container.append('<div class="mainwp_info-box-yellow">Settings saved to child sites.</div>');
                        $container.append('<p><a href="admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension&tab=settings" class="button-primary">' + __('Return to Settings') + '</a></p>');
		}
		mainwp_linkschecker_save_settings_start_next();
	}, 'json');
};

mainwp_linkschecker_settings_recheck_start_next = function()
{
	if (linkschecker_bulkTotalThreads == 0) {
		linkschecker_bulkTotalThreads = jQuery( '.mainwpProccessSitesItem[status="queue"]' ).length; }

	while ((siteToProcess = jQuery( '.mainwpProccessSitesItem[status="queue"]:first' )) && (siteToProcess.length > 0)  && (linkschecker_bulkCurrentThreads < linkschecker_bulkMaxThreads)) {
		mainwp_linkschecker_settings_recheck_start_specific( siteToProcess );
	}
};

mainwp_linkschecker_settings_recheck_start_specific = function (pSiteToProcess)
{
	linkschecker_bulkCurrentThreads++;
	pSiteToProcess.attr( 'status', 'progress' );
	var statusEl = pSiteToProcess.find( '.status' ).html( '<i class="fa fa-spinner fa-pulse"></i> ' + 'running ...' );

	var data = {
		action:'mainwp_linkschecker_perform_recheck',
		siteId: pSiteToProcess.attr( 'siteid' )
	};

        var $container = jQuery( '#blc_settings_tab .postbox .inside' );

	jQuery.post(ajaxurl, data, function (response)
		{
		pSiteToProcess.attr( 'status', 'done' );
		if (response) {
			if (response['result'] == 'SUCCESS') {
				statusEl.html( 'Successful' ).show();
			} else if (response['error']) {
				statusEl.html( response['error'] ).show();
				statusEl.css( 'color', 'red' );
			} else {
				statusEl.html( __( 'Undefined Error' ) ).show();
				statusEl.css( 'color', 'red' );
			}
		} else {
			statusEl.html( __( 'Undefined Error' ) ).show();
			statusEl.css( 'color', 'red' );
		}

		linkschecker_bulkCurrentThreads--;
		linkschecker_bulkFinishedThreads++;
		if (linkschecker_bulkFinishedThreads == linkschecker_bulkTotalThreads && linkschecker_bulkFinishedThreads != 0) {
                        $container.append('<div class="mainwp_info-box-yellow">Recheck started on child sites.</div>');
                        $container.append('<p><a href="admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension&tab=settings" class="button-primary">' + __('Return to Settings') + '</a></p>');
		}
		mainwp_linkschecker_settings_recheck_start_next();
	}, 'json');
};


mainwp_linkschecker_sync_links_start_next = function()
{
	if (linkschecker_bulkTotalThreads == 0) {
		linkschecker_bulkTotalThreads = jQuery( '.mainwpProccessSitesItem[status="queue"]' ).length; }

	while ((siteToProcess = jQuery( '.mainwpProccessSitesItem[status="queue"]:first' )) && (siteToProcess.length > 0)  && (linkschecker_bulkCurrentThreads < linkschecker_bulkMaxThreads)) {
		mainwp_linkschecker_sync_links_start_specific( siteToProcess );
	}
};

mainwp_linkschecker_sync_links_start_specific = function (pSiteToProcess, offset)
{
        pSiteToProcess.attr( 'status', 'progress' );
        var offset_num = 0;
        var first_sync = 0;

	if (typeof offset != 'undefined' && offset > 0) {
            offset_num = offset;
        } else {
            linkschecker_bulkCurrentThreads++;
            first_sync = 1;
        }

	var statusEl = pSiteToProcess.find( '.status' ).html( '<i class="fa fa-spinner fa-pulse"></i> ' + 'Syncing ... ' + (offset_num > 0 ? offset_num : '') );

        var data = {
		action:'mainwp_linkschecker_sync_links_data',
		siteId: pSiteToProcess.attr( 'siteid' ),
                first_sync: first_sync
	};

        if (offset_num > 0) {
             data['offset'] = offset_num;
        }

	jQuery.post(ajaxurl, data, function (response)
		{
		pSiteToProcess.attr( 'status', 'done' );
		if (response) {
			if (response['result'] == 'success') {
                            if (response['sync_offset']){
                                mainwp_linkschecker_sync_links_start_specific(pSiteToProcess, response['sync_offset']);
                                return;
                            } else {
                                var msg = 'Successful';
                                if ( response['total_sync'] ){
                                    msg = msg + ' ' + response['total_sync'];
                                }
				statusEl.html( msg ).show();
                            }
			} else if (response['error']) {
				statusEl.html( response['error'] ).show();
				statusEl.css( 'color', 'red' );
			} else {
				statusEl.html( __( 'Undefined Error' ) ).show();
				statusEl.css( 'color', 'red' );
			}
		} else {
			statusEl.html( __( 'Undefined Error' ) ).show();
			statusEl.css( 'color', 'red' );
		}

		linkschecker_bulkCurrentThreads--;
		linkschecker_bulkFinishedThreads++;
		if (linkschecker_bulkFinishedThreads == linkschecker_bulkTotalThreads && linkschecker_bulkFinishedThreads != 0) {
                        var $container = jQuery( '#mainwp_blc_links_dashboard_content' );
                        $container.append('<div class="mainwp_info-box-yellow">Sync Links Data Finished.</div>');
                        $container.append('<p><a href="admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension&tab=links" class="button-primary">' + __('Return to Links') + '</a></p>');
		}
		mainwp_linkschecker_sync_links_start_next();
	}, 'json');
};


jQuery( document ).ready(function($) {
	jQuery( '.mainwp-show-tut' ).on('click', function(){
		jQuery( '.mainwp-lc-tut' ).hide();
		var num = jQuery( this ).attr( 'number' );
		console.log( num );
		jQuery( '.mainwp-lc-tut[number="' + num + '"]' ).show();
		mainwp_setCookie( 'lc_quick_tut_number', jQuery( this ).attr( 'number' ) );
		return false;
	});

	jQuery( '#mainwp-lc-quick-start-guide' ).on('click', function () {
		if (mainwp_getCookie( 'lc_quick_guide' ) == 'on') {
			mainwp_setCookie( 'lc_quick_guide', '' ); } else {
			mainwp_setCookie( 'lc_quick_guide', 'on' ); }
			lc_showhide_quick_guide();
			return false;
	});
	jQuery( '#mainwp-lc-tips-dismiss' ).on('click', function () {
		mainwp_setCookie( 'lc_quick_guide', '' );
		lc_showhide_quick_guide();
		return false;
	});

	lc_showhide_quick_guide();

	jQuery( '#mainwp-lc-dashboard-tips-dismiss' ).on('click', function () {
		$( this ).closest( '.mainwp_info-box-yellow' ).hide();
		mainwp_setCookie( 'ps_dashboard_notice', 'hide', 2 );
		return false;
	});

});

lc_showhide_quick_guide = function(show, tut) {
	var show = mainwp_getCookie( 'lc_quick_guide' );
	var tut = mainwp_getCookie( 'lc_quick_tut_number' );

	if (show == 'on') {
		jQuery( '#mainwp-lc-tips' ).show();
		jQuery( '#mainwp-lc-quick-start-guide' ).hide();
		lc_showhide_quick_tut();
	} else {
		jQuery( '#mainwp-lc-tips' ).hide();
		jQuery( '#mainwp-lc-quick-start-guide' ).show();
	}

	if ('hide' == mainwp_getCookie( 'ps_dashboard_notice' )) {
		jQuery( '#mainwp-lc-dashboard-tips-dismiss' ).closest( '.mainwp_info-box-yellow' ).hide();
	}
}

lc_showhide_quick_tut = function() {
	var tut = mainwp_getCookie( 'lc_quick_tut_number' );
	jQuery( '.mainwp-lc-tut' ).hide();
	jQuery( '.mainwp-lc-tut[number="' + tut + '"]' ).show();
}



mainwp_broken_links_checker_table_reinit = function () {
	if (jQuery( '#mainwp_blc_links_table' ).hasClass( 'tablesorter-default' )) {
		jQuery( '#mainwp_blc_links_table' ).trigger( "updateAll" ).trigger( 'destroy.pager' ).tablesorterPager( {container:jQuery( "#pager" )} );
	} else {
		jQuery( '#mainwp_blc_links_table' ).tablesorter({
			cssAsc:"desc",
			cssDesc:"asc",
			cssChildRow: "expand-child",
			textExtraction:function (node) {
				if (jQuery( node ).find( 'abbr' ).length == 0) {
					return node.innerHTML
				} else {
					return jQuery( node ).find( 'abbr' )[0].title;
				}
			},
			selectorHeaders: "> thead th:not(:first), > thead td:not(:first), > tfoot th:not(:first), > tfoot td:not(:first)"
		}).tablesorterPager( {container:jQuery( "#pager" )} );
	}
};
