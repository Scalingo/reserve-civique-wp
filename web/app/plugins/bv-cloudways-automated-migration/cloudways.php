<?php
/*
Plugin Name: Cloudways WordPress Migrator
Plugin URI: https://www.cloudways.com
Description: The easiest way to migrate your site to cloudways
Author: Cloudways
Author URI: https://www.cloudways.com
Version: 4.62
Network: True
 */

/*  Copyright 2017  Cloudways Migrate  (email : support@blogvault.net)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/* Global response array */

if (!defined('ABSPATH')) exit;

require_once dirname( __FILE__ ) . '/wp_settings.php';
require_once dirname( __FILE__ ) . '/wp_site_info.php';
require_once dirname( __FILE__ ) . '/wp_db.php';
require_once dirname( __FILE__ ) . '/wp_api.php';
require_once dirname( __FILE__ ) . '/wp_actions.php';
require_once dirname( __FILE__ ) . '/info.php';
require_once dirname( __FILE__ ) . '/account.php';


$bvsettings = new CWSWPSettings();
$bvsiteinfo = new CWSWPSiteInfo();
$bvdb = new CWSWPDb();


$bvapi = new CWSWPAPI($bvsettings);
$bvinfo = new CWSInfo($bvsettings);
$wp_action = new CWSWPAction($bvsettings, $bvsiteinfo, $bvapi);

register_uninstall_hook(__FILE__, array('CWSWPAction', 'uninstall'));
register_activation_hook(__FILE__, array($wp_action, 'activate'));
register_deactivation_hook(__FILE__, array($wp_action, 'deactivate'));

add_action('wp_footer', array($wp_action, 'footerHandler'), 100);
add_action('clear_bv_services_config', array($wp_action, 'clear_bv_services_config'));

##WPCLIMODULE##
if (is_admin()) {
	require_once dirname( __FILE__ ) . '/wp_admin.php';
	$wpadmin = new CWSWPAdmin($bvsettings, $bvsiteinfo);
	add_action('admin_init', array($wpadmin, 'initHandler'));
	add_filter('all_plugins', array($wpadmin, 'initBranding'));
	add_filter('plugin_row_meta', array($wpadmin, 'hidePluginDetails'), 10, 2);
	if ($bvsiteinfo->isMultisite()) {
		add_action('network_admin_menu', array($wpadmin, 'menu'));
	} else {
		add_action('admin_menu', array($wpadmin, 'menu'));
	}
	add_filter('plugin_action_links', array($wpadmin, 'settingsLink'), 10, 2);
	add_action('admin_head', array($wpadmin, 'removeAdminNotices'), 3);
	##ACTIVATEWARNING##
	add_action('admin_enqueue_scripts', array($wpadmin, 'cwssecAdminMenu'));
}


if ((array_key_exists('bvreqmerge', $_POST)) || (array_key_exists('bvreqmerge', $_GET))) {
	$_REQUEST = array_merge($_GET, $_POST);
}

if ((array_key_exists('bvplugname', $_REQUEST)) && ($_REQUEST['bvplugname'] == "cloudways")) {
	require_once dirname( __FILE__ ) . '/callback/base.php';
	require_once dirname( __FILE__ ) . '/callback/response.php';
	require_once dirname( __FILE__ ) . '/callback/request.php';
	require_once dirname( __FILE__ ) . '/recover.php';

	$pubkey = CWSAccount::sanitizeKey($_REQUEST['pubkey']);

	if (array_key_exists('rcvracc', $_REQUEST)) {
		$account = CWSRecover::find($bvsettings, $pubkey);
	} else {
		$account = CWSAccount::find($bvsettings, $pubkey);
	}

	$request = new BVCallbackRequest($account, $_REQUEST);
	$response = new BVCallbackResponse($request->bvb64cksize);

	if ($account && (1 === $account->authenticate($request))) {
		##BVBASEPATH##

		require_once dirname( __FILE__ ) . '/callback/handler.php';
		$params = $request->processParams($_REQUEST);
		if ($params === false) {
			$resp = array(
				"account_info" => $account->info(),
				"request_info" => $request->info(),
				"bvinfo" => $bvinfo->info(),
				"statusmsg" => "BVPRMS_CORRUPTED"
			);
			$response->terminate($resp);
		}
		$request->params = $params;
		$callback_handler = new BVCallbackHandler($bvdb, $bvsettings, $bvsiteinfo, $request, $account, $response);
		if ($request->is_afterload) {
			add_action('wp_loaded', array($callback_handler, 'execute'));
		} else if ($request->is_admin_ajax) {
			add_action('wp_ajax_bvadm', array($callback_handler, 'bvAdmExecuteWithUser'));
			add_action('wp_ajax_nopriv_bvadm', array($callback_handler, 'bvAdmExecuteWithoutUser'));
		} else {
			$callback_handler->execute();
		}
	} else {
		$resp = array(
			"account_info" => $account ? $account->info() : array("error" => "ACCOUNT_NOT_FOUND"),
			"request_info" => $request->info(),
			"bvinfo" => $bvinfo->info(),
			"statusmsg" => "FAILED_AUTH",
			"api_pubkey" => substr(CWSAccount::getApiPublicKey($bvsettings), 0, 8),
			"def_sigmatch" => substr(CWSAccount::getSigMatch($request, CWSRecover::getDefaultSecret($bvsettings)), 0, 8)
		);
		$response->terminate($resp);
	}
} else {
	if ($bvinfo->hasValidDBVersion()) {
		##PROTECTMODULE##
		##DYNSYNCMODULE##
		##ACTLOGMODULE##
	}
	##WPAUTOUPDATEBLOCKMODULE##
}