<?php
/*
Plugin Name: API Management for AllAround
Plugin URI: https://allaround.co.il/
Description: AllAround API Management for main site and Mini Store.
Version: 0.1.2
Author: AllAround
Text Domain: hello-elementor
*/

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}


class AlrndAPIManagement
{

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const version = '0.1';

	/**
	 * Call this method to get the singleton
	 *
	 * @return AlrndAPIManagement|null
	 */
	public static function instance()
	{

		static $instance = null;
		if (is_null($instance)) {
			$instance = new AlrndAPIManagement();
		}

		return $instance;
	}

	public function __construct()
	{

		$this->define_constanst();

		register_activation_hook(__FILE__, array($this, 'activation'));
		register_deactivation_hook(__FILE__, array($this, 'deactivation'));

		//run on uninstall
		register_uninstall_hook(__FILE__, array('AlrndAPIManagement', 'uninstall'));

		add_action('plugins_loaded', array($this, 'init'));

		load_plugin_textdomain('hello-elementor', false, basename(dirname(__FILE__)) . '/languages');
	}

	/**
	 * Init
	 */
	public function init()
	{
		require_once(AlRNDAPI_PATH . '/includes/functions.php');
		require_once(AlRNDAPI_PATH . '/includes/class-api.php');
		require_once(AlRNDAPI_PATH . '/includes/class-hook.php');
	}

	/**
	 *  Runs on plugin uninstall.
	 *  a static class method or function can be used in an uninstall hook
	 *
	 * @since 0.1
	 */
	public static function uninstall()
	{
	}


	/**
	 * plugin activation
	 *
	 * @return void
	 */
	public function activation()
	{
	}

	/**
	 * plugin activation
	 *
	 * @return void
	 */
	public function deactivation()
	{
	}

	/**
	 * Define require constansts
	 * 
	 * @return void
	 */
	public function define_constanst()
	{
		define('AlRNDAPI_VERSION', self::version);
		define("AlRNDAPI_URL", plugins_url("/", __FILE__));
		define("AlRNDAPI_PATH", plugin_dir_path(__FILE__));
	}


}

(new AlrndAPIManagement());




