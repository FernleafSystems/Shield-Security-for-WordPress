<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules;

class WPHooksOrder {

	const NONE = 0;
	const PLUGINS_LOADED = 10;
	const INIT = 20;
	const WP_LOADED = 40;
	const TEMPLATE_REDIRECT = 60;
	const WP = 80;
	const SHUTDOWN = 100;

	public static function HOOK_NAME( int $hookOrder ) :string {
		return [
				   self::PLUGINS_LOADED    => 'plugins_loaded',
				   self::INIT              => 'init',
				   self::WP_LOADED         => 'wp_loaded',
				   self::TEMPLATE_REDIRECT => 'template_redirect',
				   self::WP                => 'wp',
				   self::SHUTDOWN          => 'shutdown',
			   ][ $hookOrder ] ?? '';
	}
}