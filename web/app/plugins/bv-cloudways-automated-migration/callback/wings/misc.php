<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('BVMiscCallback')) :
	
class BVMiscCallback extends BVCallbackBase {
	public $settings;
	public $bvinfo;
	public $siteinfo;
	public $account;
	public $bvapi;

	public function __construct($callback_handler) {
		$this->settings = $callback_handler->settings;
		$this->siteinfo = $callback_handler->siteinfo;
		$this->account = $callback_handler->account;
		$this->bvinfo = new CWSInfo($callback_handler->settings);
		$this->bvapi = new CWSWPAPI($callback_handler->settings);
	}

	public function refreshPluginUpdates() {
		global $wp_current_filter;
		$wp_current_filter[] = 'load-update-core.php';
	
		wp_update_plugins();

		array_pop($wp_current_filter);

		wp_update_plugins();

		return array("wpupdateplugins" => true);
	}

	public function refreshThemeUpdates() {
		global $wp_current_filter;
		$wp_current_filter[] = 'load-update-core.php';

		wp_update_themes();

		array_pop($wp_current_filter);

		wp_update_themes();

		return array("wpupdatethemes" => true);
	}

	public function process($request) {
		$bvinfo = $this->bvinfo;
		$settings = $this->settings;
		$params = $request->params;
		switch ($request->method) {
		case "dummyping":
			$resp = array();
			$resp = array_merge($resp, $this->siteinfo->info());
			$resp = array_merge($resp, $this->account->info());
			$resp = array_merge($resp, $this->bvinfo->info());
			break;
		case "pngbv":
			$info = array();
			$this->siteinfo->basic($info);
			$this->bvapi->pingbv('/bvapi/pingbv', $info);
			$resp = array("status" => true);
			break;
		case "enablebadge":
			$option = $bvinfo->badgeinfo;
			$badgeinfo = array();
			$badgeinfo['badgeurl'] = $params['badgeurl'];
			$badgeinfo['badgeimg'] = $params['badgeimg'];
			$badgeinfo['badgealt'] = $params['badgealt'];
			$settings->updateOption($option, $badgeinfo);
			$resp = array("status" => $settings->getOption($option));
			break;
		case "disablebadge":
			$option = $bvinfo->badgeinfo;
			$settings->deleteOption($option);
			$resp = array("status" => !$settings->getOption($option));
			break;
		case "getoption":
			$resp = array('getoption' => $settings->getOption($params['opkey']));
			break;
		case "setdynplug":
			$settings->updateOption('bvdynplug', $params['dynplug']);
			$resp = array("setdynplug" => $settings->getOption('bvdynplug'));
			break;
		case "unsetdynplug":
			$settings->deleteOption('bvdynplug');
			$resp = array("unsetdynplug" => $settings->getOption('bvdynplug'));
			break;
		case "wpupplgs":
			$resp = $this->refreshPluginUpdates();
			break;
		case "wpupthms":
			$resp = $this->refreshThemeUpdates(); 
			break;
		case "wpupcre":
			$resp = array("wpupdatecore" => wp_version_check());
			break;
		case "phpinfo":
			phpinfo();
			die();
			break;
		case "dlttrsnt":
			$resp = array("dlttrsnt" => $settings->deleteTransient($params['key']));
			break;
		case "setbvss":
			$resp = array("status" => $settings->updateOption('bv_site_settings', $params['bv_site_settings']));
			break;
		case "stsrvcs":
			$resp = array();
			$deleted_configs = array();
			$updated_configs = array();
			if (array_key_exists("configs_to_delete", $params)) {
				foreach($params["configs_to_delete"] as $config_name) {
					$deleted_configs[$config_name] = $settings->deleteOption($config_name);
				}
			}
			if (array_key_exists("configs_to_update", $params)) {
				foreach($params["configs_to_update"] as $config_name => $config_value) {
					$settings->updateOption($config_name, $config_value);
					$updated_configs[$config_name] = $settings->getOption($config_name);
				}
			}
			$resp["updated_configs"] = $updated_configs;
			$resp["deleted_configs"] = $deleted_configs;
			break;
		default:
			$resp = false;
		}
		return $resp;
	}
}
endif;