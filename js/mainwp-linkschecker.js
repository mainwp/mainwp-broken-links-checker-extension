
jQuery(document).ready(function($) {  
        
    $('#blc_dashboard_tab_lnk').on('click', function () {   
        showBLCheckerTab(true, false);
        return false;
    });
    
    $('#blc_broken_links_tab_lnk').on('click', function () {  
        showBLCheckerTab(false, true);
        return false;
    });
    
    $('#mwp_linkschecker_btn_display').live('click', function() {                     
       $(this).closest('form').submit();
    });
    
    $('.mwp-linkschecker-upgrade-noti-dismiss').live('click', function() {
        var parent = $(this).closest('.ext-upgrade-noti');
        parent.hide();
        var data = {
            action: 'mainwp_linkschecker_upgrade_noti_dismiss',
            siteId: parent.attr('website-id'),           
        }        
        jQuery.post(ajaxurl, data, function (response) {
            
        });        
        return false;
    }); 
    
      $('.mwp-linkschecker-invalid-noti-dismiss').live('click', function() {
        var parent = $(this).closest('.ext-invalid-noti');
        parent.hide();
        var data = {
            action: 'mainwp_linkschecker_invalid_noti_dismiss',
            siteId: parent.attr('website-id')            
        }        
        jQuery.post(ajaxurl, data, function (response) {
            
        });        
        return false;
    });    
    
    $('.linkschecker_active_plugin').on('click', function() {
        mainwp_linkschecker_active_start_specific($(this), false);
        return false;
    }); 
    
    $('.linkschecker_upgrade_plugin').on('click', function() {
        mainwp_linkschecker_upgrade_start_specific($(this), false);
        return false;
    }); 
    
    $('.linkschecker_showhide_plugin').on('click', function() {
        mainwp_linkschecker_showhide_start_specific($(this), false);
        return false;
    });   
    
    $('#mwp_linkschecker_doaction_btn').on('click', function() {
        var bulk_act = $('#mwp_linkschecker_action').val();
        mainwp_linkschecker_do_bulk_action(bulk_act);
           
    });  
    
    $('#wpps-settings-extension-save-btn').on('click', function() {        
        var data = { 
            action: 'mainwp_linkschecker_save_ext_setting',            
            scoreNoti: $('select[name="mainwp_linkschecker_score_noti"]').val(),
            scheduleNoti: $('select[name="mainwp_linkschecker_schedule_noti"]').val(),            
        }
        var statusEl = $('#mwps-setting-ext-working .status');
        statusEl.html('');
        $('#mwps-setting-ext-working .loading').show();
        jQuery.post(ajaxurl, data, function (response) {
            $('#mwps-setting-ext-working .loading').hide();            
            if (response) {
                if (response == 'SUCCESS') {
                    statusEl.css('color', '#21759B');
                    statusEl.html(__('Updated')).show();   
                    statusEl.fadeOut(3000); 
                } else if (response['id']) {     
                    $('#mainwp_linkschecker_site_id').val(response['id']);                
                    statusEl.css('color', '#21759B');
                    statusEl.html(__('Updated')).show();   
                    statusEl.fadeOut(3000); 
                } else {
                    statusEl.css('color', 'red');
                    statusEl.html(__("Update failed")).show();
                }
            } else {
                statusEl.css('color', 'red');
                statusEl.html(__("Undefined error")).show();
            } 
        },'json'); 
    });
        
});
  

showBLCheckerTab = function(dashboard,links) {
    var dashboard_tab_lnk = jQuery("#blc_dashboard_tab_lnk");
    if (dashboard)  dashboard_tab_lnk.addClass('mainwp_action_down');
    else dashboard_tab_lnk.removeClass('mainwp_action_down'); 

    var links_tab_lnk = jQuery("#blc_broken_links_tab_lnk");
    if (links) links_tab_lnk.addClass('mainwp_action_down');
    else links_tab_lnk.removeClass('mainwp_action_down');
    
    var dashboard_tab = jQuery("#blc_dashboard_tab");    
    var links_tab = jQuery("#blc_broken_links_tab");    
    
    if (dashboard) {
        dashboard_tab.show();
        links_tab.hide();            
    } else if (links) {
        dashboard_tab.hide();        
        links_tab.show();       
    }   
};

var linkschecker_bulkMaxThreads = 3;
var linkschecker_bulkTotalThreads = 0;
var linkschecker_bulkCurrentThreads = 0;
var linkschecker_bulkFinishedThreads = 0;

mainwp_linkschecker_do_bulk_action = function(act) { 
    var selector = '';
    switch(act) {
        case 'activate-selected':   
            selector = '#the-mwp-linkschecker-list tr.plugin-update-tr .linkschecker_active_plugin';
            jQuery(selector).addClass('queue');
            mainwp_linkschecker_active_start_next(selector);            
            break;
        case 'update-selected':   
            selector = '#the-mwp-linkschecker-list tr.plugin-update-tr .linkschecker_upgrade_plugin';
            jQuery(selector).addClass('queue');
            mainwp_linkschecker_upgrade_start_next(selector);            
            break;
        case 'hide-selected':       
            selector = '#the-mwp-linkschecker-list tr .linkschecker_showhide_plugin[showhide="hide"]';
            jQuery(selector).addClass('queue');            
            mainwp_linkschecker_showhide_start_next(selector);   
            break;  
        case 'show-selected':     
            selector = '#the-mwp-linkschecker-list tr .linkschecker_showhide_plugin[showhide="show"]';
            jQuery(selector).addClass('queue');
            mainwp_linkschecker_showhide_start_next(selector);   
            break;                
    }
}
     
mainwp_linkschecker_showhide_start_next = function(selector) {     
    while ((objProcess = jQuery(selector + '.queue:first')) && (objProcess.length > 0) && (linkschecker_bulkCurrentThreads < linkschecker_bulkMaxThreads))
    {   
        objProcess.removeClass('queue');
        if (objProcess.closest('tr').find('.check-column input[type="checkbox"]:checked').length == 0) {            
            continue;
        }                   
        mainwp_linkschecker_showhide_start_specific(objProcess, true, selector);
    }
}
  
mainwp_linkschecker_showhide_start_specific = function(pObj, bulk, selector) {    
    var parent = pObj.closest('tr');
    var loader = parent.find('.linkschecker-action-working .loading');  
    var statusEl = parent.find('.linkschecker-action-working .status');        
    var showhide = pObj.attr('showhide');
    if (bulk) 
        linkschecker_bulkCurrentThreads++;
    
    var data = {
        action: 'mainwp_linkschecker_showhide_linkschecker',
        websiteId: parent.attr('website-id'),
        showhide: showhide
    }
    statusEl.hide();
    loader.show();
    jQuery.post(ajaxurl, data, function (response) {
        loader.hide();
        pObj.removeClass('queue');
        if (response && response['error']) {
            statusEl.css('color', 'red');
            statusEl.html(response['error']).show();
        }
        else if (response && response['result'] == 'SUCCESS') {                
            if (showhide == 'show') {
                pObj.text(__("Hide Broken Link Checker Plugin"));
                pObj.attr('showhide', 'hide');
                parent.find('.plugin_hidden_title').html(__('No'));
            } else {
                pObj.text(__("Show Broken Link Checker Plugin"));        
                pObj.attr('showhide', 'show');
                parent.find('.plugin_hidden_title').html(__('Yes'));
            }
            
            statusEl.css('color', '#21759B');
            statusEl.html(__('Successful')).show();   
            statusEl.fadeOut(3000); 
        }  
        else {
            statusEl.css('color', 'red');
            statusEl.html(__("Undefined error")).show();               
        } 
        
        if (bulk) {
            linkschecker_bulkCurrentThreads--;
            linkschecker_bulkFinishedThreads++;
            mainwp_linkschecker_showhide_start_next(selector);
        }
        
    },'json');        
    return false;  
}

mainwp_linkschecker_upgrade_start_next = function(selector) {    
    while ((objProcess = jQuery(selector + '.queue:first')) && (objProcess.length > 0) && (objProcess.closest('tr').prev('tr').find('.check-column input[type="checkbox"]:checked').length > 0) && (linkschecker_bulkCurrentThreads < linkschecker_bulkMaxThreads))
    {           
        objProcess.removeClass('queue');
        if (objProcess.closest('tr').prev('tr').find('.check-column input[type="checkbox"]:checked').length == 0) {            
            continue;
        }
        mainwp_linkschecker_upgrade_start_specific(objProcess, true, selector);
    }
}

mainwp_linkschecker_upgrade_start_specific = function(pObj, bulk, selector) {
    var parent = pObj.closest('.ext-upgrade-noti');
    var workingRow = parent.find('.linkschecker-row-working');         
    var slug = parent.attr('plugin-slug');        
    var data = {
        action: 'mainwp_linkschecker_upgrade_plugin',
        websiteId: parent.attr('website-id'),
        type: 'plugin',
        'slugs[]': [slug]
    }  
    
    if (bulk) 
        linkschecker_bulkCurrentThreads++;
   
    workingRow.find('img').show();
    jQuery.post(ajaxurl, data, function (response) {
        workingRow.find('img').hide();
        pObj.removeClass('queue');
        if (response && response['error']) {
            workingRow.find('.status').html('<font color="red">'+response['error']+'</font>');
        }
        else if (response && response['upgrades'][slug]) {           
            pObj.after('Broken Link Checker plugin has been updated');
            pObj.remove();
        }  
        else {
           workingRow.find('.status').html('<font color="red">'+__("Undefined error")+'</font>'); 
        } 
        
        if (bulk) {
            linkschecker_bulkCurrentThreads--;
            linkschecker_bulkFinishedThreads++;
            mainwp_linkschecker_upgrade_start_next(selector);
        }
        
    },'json');        
    return false;
}


mainwp_linkschecker_active_start_next = function(selector) {            
    while ((objProcess = jQuery(selector + '.queue:first')) && (objProcess.length > 0) && (objProcess.closest('tr').prev('tr').find('.check-column input[type="checkbox"]:checked').length > 0) && (linkschecker_bulkCurrentThreads < linkschecker_bulkMaxThreads))
    {       
        objProcess.removeClass('queue');
        if (objProcess.closest('tr').prev('tr').find('.check-column input[type="checkbox"]:checked').length == 0) {            
            continue;
        }
        mainwp_linkschecker_active_start_specific(objProcess, true, selector);
    }
}

mainwp_linkschecker_active_start_specific = function(pObj, bulk, selector) {
    var parent = pObj.closest('.ext-upgrade-noti');
    var workingRow = parent.find('.linkschecker-row-working'); 
    var slug = parent.attr('plugin-slug');        
    var data = {
        action: 'mainwp_linkschecker_active_plugin',
        websiteId: parent.attr('website-id'),
        'plugins[]': [slug]
    }  
  
    if (bulk) 
        linkschecker_bulkCurrentThreads++;
  
    workingRow.find('img').show();
    jQuery.post(ajaxurl, data, function (response) {
        workingRow.find('img').hide();
        pObj.removeClass('queue');
        if (response && response['error']) {
            workingRow.find('.status').html('<font color="red">'+response['error']+'</font>');
        }
        else if (response && response['result']) {
            pObj.after('Broken Link Checker plugin has been activated');
            pObj.remove();
        }           
        if (bulk) {
            linkschecker_bulkCurrentThreads--;
            linkschecker_bulkFinishedThreads++;
            mainwp_linkschecker_active_start_next(selector);
        }
        
    },'json');        
    return false;
}

mainwp_linkschecker_save_settings_start_next = function()
{
    if (linkschecker_bulkTotalThreads == 0)
        linkschecker_bulkTotalThreads = jQuery('.mainwpProccessSitesItem[status="queue"]').length;
		
    while ((siteToProcess = jQuery('.mainwpProccessSitesItem[status="queue"]:first')) && (siteToProcess.length > 0)  && (linkschecker_bulkCurrentThreads < branding_MaxThreads))
    {                  
        mainwp_linkschecker_save_settings_start_specific(siteToProcess);
    }	
};

mainwp_linkschecker_save_settings_start_specific = function (pSiteToProcess)
{
    linkschecker_bulkCurrentThreads++;	
    pSiteToProcess.attr('status', 'progress');
    var statusEl = pSiteToProcess.find('.status').html('<img src="' + mainwpParams['image_url'] + 'loader.gif"> ' + 'running ..');

    var data = {
        action:'mainwp_linkschecker_performsavelinkscheckersettings',
        siteId: pSiteToProcess.attr('siteid')
    };

    jQuery.post(ajaxurl, data, function (response)
    {
        pSiteToProcess.attr('status', 'done');   
        
        if (response) {
            if (response['result'] == 'NOTCHANGE') {			
                statusEl.html('Settings saved with no changes.').fadeIn();						
            } else if (response['result'] == 'SUCCESS') {			
                statusEl.html('Successful').show();	                        
            } else if (response['error']) {			
                statusEl.html(response['error']).show();
                statusEl.css('color', 'red');
            } else { 						
                statusEl.html(__('Undefined Error')).show();
                statusEl.css('color', 'red');
            }
        } else {
            statusEl.html(__('Undefined Error')).show();
            statusEl.css('color', 'red');
        }
        
        linkschecker_bulkCurrentThreads--;
        linkschecker_bulkFinishedThreads++;
        if (linkschecker_bulkFinishedThreads == linkschecker_bulkTotalThreads && linkschecker_bulkFinishedThreads != 0) {
            jQuery('#mainwp_linkschecker_apply_setting_ajax_message_zone').html('Saved Settings to child sites.').fadeIn(100);
            setTimeout(function() {
                location.href = 'admin.php?page=Extensions-Mainwp-Broken-Links-Checker-Extension&action=setting';
            }, 3000);              
        }
        mainwp_linkschecker_save_settings_start_next();     
    }, 'json');
};



jQuery(document).ready(function($) {       
    jQuery('.mainwp-show-tut').on('click', function(){
        jQuery('.mainwp-lc-tut').hide();   
        var num = jQuery(this).attr('number');
        console.log(num);
        jQuery('.mainwp-lc-tut[number="' + num + '"]').show();
        mainwp_setCookie('lc_quick_tut_number', jQuery(this).attr('number'));
        return false;
    }); 
    
    jQuery('#mainwp-lc-quick-start-guide').on('click', function () {
        if(mainwp_getCookie('lc_quick_guide') == 'on')
            mainwp_setCookie('lc_quick_guide', '');
        else 
            mainwp_setCookie('lc_quick_guide', 'on');        
        lc_showhide_quick_guide();
        return false;
    });
    jQuery('#mainwp-lc-tips-dismiss').on('click', function () {    
        mainwp_setCookie('lc_quick_guide', '');
        lc_showhide_quick_guide();
        return false;
    });
    
    lc_showhide_quick_guide();

    jQuery('#mainwp-lc-dashboard-tips-dismiss').on('click', function () {    
        $(this).closest('.mainwp_info-box-yellow').hide();
        mainwp_setCookie('ps_dashboard_notice', 'hide', 2);        
        return false;
    });

});

lc_showhide_quick_guide = function(show, tut) {
    var show = mainwp_getCookie('lc_quick_guide');
    var tut = mainwp_getCookie('lc_quick_tut_number');
    
    if (show == 'on') {
        jQuery('#mainwp-lc-tips').show();
        jQuery('#mainwp-lc-quick-start-guide').hide();   
        lc_showhide_quick_tut();        
    } else {
        jQuery('#mainwp-lc-tips').hide();
        jQuery('#mainwp-lc-quick-start-guide').show();    
    }
    
    if ('hide' == mainwp_getCookie('ps_dashboard_notice')) {
        jQuery('#mainwp-lc-dashboard-tips-dismiss').closest('.mainwp_info-box-yellow').hide();
    }
}

lc_showhide_quick_tut = function() {
    var tut = mainwp_getCookie('lc_quick_tut_number');
    jQuery('.mainwp-lc-tut').hide();   
    jQuery('.mainwp-lc-tut[number="' + tut + '"]').show();   
}



mainwp_broken_links_checker_table_reinit = function () {
    if (jQuery('#mainwp_blc_links_table').hasClass('tablesorter-default'))
    {
        jQuery('#mainwp_blc_links_table').trigger("updateAll").trigger('destroy.pager').tablesorterPager({container:jQuery("#pager")});
    }
    else
    {
        jQuery('#mainwp_blc_links_table').tablesorter({
            cssAsc:"desc",
            cssDesc:"asc",
            cssChildRow: "expand-child",
            textExtraction:function (node) {
                if (jQuery(node).find('abbr').length == 0) {
                    return node.innerHTML
                } else {
                    return jQuery(node).find('abbr')[0].title;
                }
            },
            selectorHeaders: "> thead th:not(:first), > thead td:not(:first), > tfoot th:not(:first), > tfoot td:not(:first)"
        }).tablesorterPager({container:jQuery("#pager")});
    }
};
