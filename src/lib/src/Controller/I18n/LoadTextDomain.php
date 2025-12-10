<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LoadTextDomain {

	use PluginControllerConsumer;

	public function run() {
		/**
		 * Translations override - we want to use our in-plugin translations, not those
		 * provided by WordPress.org since getting our existing translations into the WP.org
		 * system is full of friction, though that's where we'd like to end-up eventually.
		 */
		add_filter( 'load_textdomain_mofile', function ( $moFile, $domain ) {
			return $domain === self::con()->getTextDomain() ? $this->overrideTranslations( $moFile ) : $moFile;
		}, 100, 2 );
		/**
		 * No longer needed, apparently:
		 * https://make.wordpress.org/core/2024/10/21/i18n-improvements-6-7/
		 * load_plugin_textdomain(
		 * self::con()->getTextDomain(),
		 * false,
		 * plugin_basename( self::con()->getPath_Languages() )
		 * );
		 */
	}

	/**
	 * Path of format -
	 * wp-content/languages/plugins/wp-simple-firewall-de_DE.mo
	 */
	private function overrideTranslations( string $moFilePath ) :string {
		$finalMoPath = null;

		// use determine_locale() as it also considers the user's profile preference
		$targetLoc = \function_exists( 'determine_locale' ) ? determine_locale() : Services::WpGeneral()->getLocale();
		$filteredLocale = apply_filters( 'plugin_locale', $targetLoc, self::con()->getTextDomain() );
		if ( !empty( $filteredLocale ) ) {
			$targetLoc = $filteredLocale;
		}

		$availableLocales = ( new GetAllAvailableLocales() )->run();
		// First look for exact .mo files.
		foreach ( $availableLocales as $loc => $moPath ) {
			if ( $targetLoc === $loc && Services::WpFs()->exists( $moPath ) ) {
				$finalMoPath = $moPath;
				break;
			}
		}
		if ( empty( $finalMoPath ) ) {
			// Then look for mo for that language.
			$targetLang = \substr( (string)$targetLoc, 0, 2 );
			if ( !empty( $targetLang ) ) {
				foreach ( $availableLocales as $loc => $moPath ) {
					if ( $targetLang === \substr( $loc, 0, 2 ) && Services::WpFs()->exists( $moPath ) ) {
						$finalMoPath = $moPath;
						break;
					}
				}
			}
		}
		return empty( $finalMoPath ) ? $moFilePath : $finalMoPath;
	}
}