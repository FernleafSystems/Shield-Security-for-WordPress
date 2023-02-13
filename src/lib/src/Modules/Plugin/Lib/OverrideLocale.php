<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n\LoadTextDomain;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;

class OverrideLocale extends ExecOnceModConsumer {

	public const MOD = ModCon::SLUG;

	protected function run() {
		$locale = $this->getOptions()->getOpt( 'locale_override' );
		if ( !empty( $locale ) ) {

			if ( preg_match( '#^[a-z]{2,3}(_[A-Z]{2,3})?$#', $locale ) ) {
				unload_textdomain( $this->getCon()->getTextDomain() );
				add_filter(
					'plugin_locale',
					function ( $locale, $domain = '' ) {
						return ( $domain === $this->getCon()->getTextDomain() ) ?
							$this->getOptions()->getOpt( 'locale_override' )
							: $locale;
					},
					100, 2
				);
				( new LoadTextDomain() )
					->setCon( $this->getCon() )
					->run();
			}
			else {
				$this->getOptions()->setOpt( 'locale_override', '' );
			}
		}
	}
}
