<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n\LoadTextDomain;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;

class OverrideLocale {

	use ExecOnce;
	use ModConsumer;

	protected function run() {
		$locale = $this->opts()->getOpt( 'locale_override' );
		if ( !empty( $locale ) ) {

			if ( \preg_match( '#^[a-z]{2,3}(_[A-Z]{2,3})?$#', $locale ) ) {
				unload_textdomain( self::con()->getTextDomain() );
				add_filter(
					'plugin_locale',
					function ( $locale, $domain = '' ) {
						return ( $domain === self::con()->getTextDomain() ) ?
							$this->opts()->getOpt( 'locale_override' ) : $locale;
					},
					100, 2
				);
				( new LoadTextDomain() )->run();
			}
			else {
				$this->opts()->setOpt( 'locale_override', '' );
			}
		}
	}
}
