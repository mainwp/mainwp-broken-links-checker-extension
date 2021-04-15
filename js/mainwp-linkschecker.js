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

	$( '#mwp_linkschecker_btn_display' ).on('click', function() {
		$( this ).closest( 'form' ).submit();
	});

	$( '.mwp-linkschecker-upgrade-noti-dismiss' ).on('click', function() {
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

	  $( '.mwp-linkschecker-invalid-noti-dismiss' ).on('click', function() {
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

		$( '#mwp_blc_doaction_btn' ).on('click', function() {
			var bulk_act = $( '#mwp_linkschecker_action' ).val();
			mainwp_linkschecker_do_bulk_action( bulk_act );

		});

		$( '#mwp-blc-save-settings-btn' ).on( 'click', function() {

			if ( $( '#check_threshold' ).val() <= 0 ) {
				$( '#mainwp-message-zone' ).html( __( "Settings can not be empty. Please set some values." ) ).show();
        $( '#mainwp-message-zone' ).addClass( 'yellow' );
				return false;
			}

			var data = {
				action: 'mainwp_linkschecker_settings_loading_sites',
				check_threshold: $( '#check_threshold' ).val(),
				max_number_of_links: $( '#max_number_of_links' ).val()
			}

      var me = $( this );

			$( '#mainwp-message-zone' ).html( '' ).hide();
      $( '#mainwp-message-zone' ).removeClass( 'red yellow green' );
			$( '#mainwp-message-zone' ).html( '<i class="notched circle loading icon"></i> Saving settings. Please wait...' ).show();
			me.attr( "disabled","disabled" );
			jQuery.post(ajaxurl, data, function (response) {
				jQuery( '#mainwp-message-zone' ).html( '' ).hide();
				if ( response ) {
					if ( response['success'] && response['result'] ) {
						jQuery('#mwp-blc-save-settings-btn').attr( 'disabled', 'disabled');
						jQuery( '#mainwp-broken-links-checker-settings-tab' ).append( response.result );
            $( '#mainwp-blc-sync-modal').modal( 'show' );
						mainwp_linkschecker_save_settings_start_next();
					} else if (response['error']) {
						$( '#mainwp-message-zone' ).html( response.error ).show();
            $( '#mainwp-message-zone' ).addClass( 'red' );
					} else {
            $( '#mainwp-message-zone' ).html( 'Undefined error occurred. Please try again.' ).show();
            $( '#mainwp-message-zone' ).addClass( 'red' );
					}
				} else {
          $( '#mainwp-message-zone' ).html( 'Undefined error occurred. Please try again.' ).show();
          $( '#mainwp-message-zone' ).addClass( 'red' );
				}
				me.removeAttr( 'disabled' );
			},'json');

		});

		$( '#mwp-blc-start-recheck-btn' ).on('click', function() {

			var data = {
				action: 'mainwp_linkschecker_settings_recheck_loading'
			}

			var me = $( this );

      $( '#mainwp-message-zone' ).html( '' ).hide();
      $( '#mainwp-message-zone' ).removeClass( 'red yellow green' );
			$( '#mainwp-message-zone' ).html( '<i class="notched circle loading icon"></i> Saving settings. Please wait...' ).show();
			me.attr( "disabled","disabled" );
			jQuery.post(ajaxurl, data, function (response) {
				$( '#mainwp-message-zone' ).html( '' ).hide();
				if ( response ) {
					if (response['success'] && response['result']) {
						jQuery('#mwp-blc-save-settings-btn').attr( "disabled","disabled" );
            jQuery( '#mainwp-broken-links-checker-settings-tab' ).append( response.result );
            $( '#mainwp-blc-sync-modal').modal( 'show' );
						mainwp_linkschecker_settings_recheck_start_next();
					} else if (response['error']) {
            $( '#mainwp-message-zone' ).html( response.error ).show();
            $( '#mainwp-message-zone' ).addClass( 'red' );
					} else {
            $( '#mainwp-message-zone' ).html( 'Undefined error occurred. Please try again.' ).show();
            $( '#mainwp-message-zone' ).addClass( 'red' );
					}
				} else {
          $( '#mainwp-message-zone' ).html( 'Undefined error occurred. Please try again.' ).show();
          $( '#mainwp-message-zone' ).addClass( 'red' );
				}
				me.removeAttr( 'disabled' );
			},'json');

		});

    //Load links
    $( '#mwp_sync_links_data' ).on( 'click', function() {

      var statusEl = $( '#mainwp-message-zone' );
      statusEl.html( '<i class="notched circle loading icon"></i> Loading links. Please wait.' ).show();

      var selector = 'table tbody tr input[type="checkbox"]';
      var selected_ids = [];

      jQuery(selector).each(function(){
        if (jQuery(this).is(':checked')) {
          var row = jQuery(this).closest('tr');
          selected_ids.push(row.attr('website-id'));
        }
      });

      if ( selected_ids.length == 0 ) {
        $('.ui.message').html( 'No selected sites. Please, select wanted sites.' );
        return false;
      }

      var data = {
        action: 'mainwp_linkschecker_load_sites',
        siteids: selected_ids
      }

      var me = $( this );
      me.attr( "disabled","disabled" );

      jQuery.post( ajaxurl, data, function (response) {
          statusEl.html( '' ).hide();
          if (response) {
              if (response['success'] && response['result']) {
                $( '#mainwp-broken-links-checker-dashboard-tab' ).append( response.result );
                $( '#mainwp-blc-sync-modal').modal( 'show' );
                mainwp_linkschecker_sync_links_start_next();
              } else if ( response['error'] ) {
                statusEl.html( response.error ).show();
              } else {
                statusEl.html("Undefined error occurred. Please try again." ).show();
              }
          } else {
            statusEl.html("Undefined error occurred. Please try again." ).show();
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
	var statusEl = parent.find( '.visibility' );
	var showhide = pObj.attr( 'showhide' );

	if (bulk) {
		linkschecker_bulkCurrentThreads++;
  }

	var data = {
		action: 'mainwp_linkschecker_showhide_linkschecker',
		websiteId: parent.attr( 'website-id' ),
		showhide: showhide
	}

  statusEl.html( '<i class="notched circle loading icon"></i>' );

	jQuery.post(ajaxurl, data, function (response) {
		statusEl.html( '' );
		pObj.removeClass( 'queue' );
		if (response && response['error']) {
			statusEl.html( '<i class="red times icon"></i>' ).show();
		} else if (response && response['result'] == 'SUCCESS') {
			if (showhide == 'show') {
				pObj.text( __( "Hide Plugin" ) );
				pObj.attr( 'showhide', 'hide' );
				parent.find( '.blc-visibility' ).html( '<span class="visibility"></span>' +  __( 'No' ) );
			} else {
				pObj.text( __( "Unhid Plugin" ) );
				pObj.attr( 'showhide', 'show' );
				parent.find( '.blc-visibility' ).html( '<span class="visibility"></span>' +  __( 'Yes' ) );
			}
			statusEl.html(  '<i class="gren check icon"></i>'  ).show();
			statusEl.fadeOut( 2000 );
		} else {
			statusEl.css( 'color', 'red' );
			statusEl.html( '<i class="red times icon"></i>' ).show();
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
	var parent = pObj.closest( 'tr' );
	var workingRow = parent.find( '.status' );
	var slug = parent.attr( 'plugin-slug' );
	var data = {
		action: 'mainwp_linkschecker_upgrade_plugin',
		websiteId: parent.attr( 'website-id' ),
		type: 'plugin',
		'slugs[]': [slug]
	}

	if (bulk) {
		linkschecker_bulkCurrentThreads++; }

	workingRow.html( '<i class="notched circle loading icon"></i>' );

	jQuery.post(ajaxurl, data, function (response) {
		pObj.removeClass( 'queue' );
		if (response && response['error']) {
			workingRow.html( '<i class="red times icon"></i>' );
		} else if ( response && response['upgrades'][slug] ) {
			parent.removeClass( 'warning' );
			pObj.remove();
		} else {
			workingRow.html( '<i class="red times icon"></i>' );
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
	var parent = pObj.closest( 'tr' );
	var workingRow = parent.find( '.status' );
	var slug = parent.attr( 'plugin-slug' );
	var data = {
		action: 'mainwp_linkschecker_active_plugin',
		websiteId: parent.attr( 'website-id' ),
		'plugins[]': [slug]
	}

	if (bulk) {
		linkschecker_bulkCurrentThreads++; }

	workingRow.html( '<i class="notched circle loading icon"></i>');
	jQuery.post(ajaxurl, data, function (response) {
		workingRow.html();
		pObj.removeClass( 'queue' );
		if (response && response['error']) {
			workingRow.html( '<i class="rec times icon></i>"' );
		} else if (response && response['result']) {
      workingRow.html( '' );
			parent.removeClass( 'negative' );
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

mainwp_linkschecker_save_settings_start_next = function() {
	if (linkschecker_bulkTotalThreads == 0) {
		linkschecker_bulkTotalThreads = jQuery( '.mainwpProccessSitesItem[status="queue"]' ).length; }

	while ((siteToProcess = jQuery( '.mainwpProccessSitesItem[status="queue"]:first' )) && (siteToProcess.length > 0)  && (linkschecker_bulkCurrentThreads < linkschecker_bulkMaxThreads)) {
		mainwp_linkschecker_save_settings_start_specific( siteToProcess );
	}
};

mainwp_linkschecker_save_settings_start_specific = function (pSiteToProcess) {
	linkschecker_bulkCurrentThreads++;
	pSiteToProcess.attr( 'status', 'progress' );
	var statusEl = pSiteToProcess.find( '.status' ).html( '<i class="notched circle loading icon"></i>' );

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
				statusEl.html( '<i class="green check icon"></i>' ).show();
			} else if (response['result'] == 'SUCCESS') {
				statusEl.html( '<i class="green check icon"></i>' ).show();
			} else if (response['error']) {
				statusEl.html( '<i class="red times icon"></i>' ).show();
			} else {
				statusEl.html( __( '<i class="red times icon"></i>' ) ).show();
			}
		} else {
			statusEl.html( '<i class="red times icon"></i>' ).show();
		}

		linkschecker_bulkCurrentThreads--;
		linkschecker_bulkFinishedThreads++;
		if (linkschecker_bulkFinishedThreads == linkschecker_bulkTotalThreads && linkschecker_bulkFinishedThreads != 0) {
      window.location.reload();
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
	var statusEl = pSiteToProcess.find( '.status' ).html( '<i class="notched circle loading icon"></i>' );

	var data = {
		action:'mainwp_linkschecker_perform_recheck',
		siteId: pSiteToProcess.attr( 'siteid' )
	};

	jQuery.post(ajaxurl, data, function (response)
		{
		pSiteToProcess.attr( 'status', 'done' );
		if (response) {
			if (response['result'] == 'SUCCESS') {
				statusEl.html( '<i class="green check icon"></i>' ).show();
			} else if (response['error']) {
				statusEl.html( '<i class="red times icon"></i>' ).show();
			} else {
				statusEl.html( '<i class="red times icon"></i>' ).show();
			}
		} else {
			statusEl.html( '<i class="red times icon"></i>' ).show();
		}

		linkschecker_bulkCurrentThreads--;
		linkschecker_bulkFinishedThreads++;
		if (linkschecker_bulkFinishedThreads == linkschecker_bulkTotalThreads && linkschecker_bulkFinishedThreads != 0) {
      window.location.reload();
		}
		mainwp_linkschecker_settings_recheck_start_next();
	}, 'json');
};


mainwp_linkschecker_sync_links_start_next = function() {
	if (linkschecker_bulkTotalThreads == 0) {
		linkschecker_bulkTotalThreads = jQuery( '.mainwpProccessSitesItem[status="queue"]' ).length; }

	while ((siteToProcess = jQuery( '.mainwpProccessSitesItem[status="queue"]:first' )) && (siteToProcess.length > 0)  && (linkschecker_bulkCurrentThreads < linkschecker_bulkMaxThreads)) {
		mainwp_linkschecker_sync_links_start_specific( siteToProcess );
	}
};

mainwp_linkschecker_sync_links_start_specific = function (pSiteToProcess, offset) {
  pSiteToProcess.attr( 'status', 'progress' );
  var offset_num = 0;
  var first_sync = 0;

	if (typeof offset != 'undefined' && offset > 0) {
            offset_num = offset;
        } else {
            linkschecker_bulkCurrentThreads++;
            first_sync = 1;
        }

	var statusEl = pSiteToProcess.find( '.status' ).html( '<i class="notched circle loading icon"></i>' + ( offset_num > 0 ? offset_num : '') );
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
            var msg = '<i class="green check icon"></i>';
            if ( response['total_sync'] ){
                msg = msg + ' ' + response['total_sync'];
            }
				statusEl.html( msg ).show();
                            }
			} else if (response['error']) {
				statusEl.html( '<i class="red times icon"></i>' ).show();
			} else {
				statusEl.html( '<i class="red times icon"></i>' ).show();
			}
		} else {
			statusEl.html( '<i class="red times icon"></i>' ).show();
		}

		linkschecker_bulkCurrentThreads--;
		linkschecker_bulkFinishedThreads++;
		if (linkschecker_bulkFinishedThreads == linkschecker_bulkTotalThreads && linkschecker_bulkFinishedThreads != 0) {
    window.location.reload();
		}
		mainwp_linkschecker_sync_links_start_next();
	}, 'json');
};
