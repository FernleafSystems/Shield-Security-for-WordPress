<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n\LoadTextDomain;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class OverrideLocale {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return !empty( self::con()->opts->optGet( 'locale_override' ) );
	}

	protected function run() {
		unload_textdomain( self::con()->getTextDomain() );
		add_filter(
			'plugin_locale',
			function ( $locale, $domain = '' ) {
				$override = \method_exists( self::con()->opts, 'optGet' ) ? self::con()->opts->optGet( 'locale_override' )
					: $this->opts()->getOpt( 'locale_override' );
				return ( $domain === self::con()->getTextDomain() ) ? $override : $locale;
			},
			100, 2
		);
		( new LoadTextDomain() )->run();
	}
}
