<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules;

class WPHooksOrder {

	public const NONE = 0;
	public const PLUGINS_LOADED = 10;
	public const INIT = 20;
	public const WP_LOADED = 40;
	public const TEMPLATE_REDIRECT = 60;
	public const REST_API_INIT = 70;
	public const REST_API_AUTH = 73;
	public const WP = 80;
	public const SHUTDOWN = 100;

	public static function HOOK_NAME( int $hookOrder ) :string {
		return [
				   self::PLUGINS_LOADED    => 'plugins_loaded',
				   self::INIT              => 'init',
				   self::WP_LOADED         => 'wp_loaded',
				   self::TEMPLATE_REDIRECT => 'template_redirect',
				   self::REST_API_INIT     => 'rest_api_init',
				   self::WP                => 'wp',
				   self::SHUTDOWN          => 'shutdown',
			   ][ $hookOrder ] ?? '';
	}
}