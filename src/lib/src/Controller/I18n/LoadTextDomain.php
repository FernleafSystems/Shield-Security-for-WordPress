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
			if ( $domain == self::con()->getTextDomain() ) {
				$moFile = $this->overrideTranslations( (string)$moFile );
			}
			return $moFile;
		}, 100, 2 );

		/**
		 * No longer needed, apparently:
		 * https://make.wordpress.org/core/2024/10/21/i18n-improvements-6-7/
		load_plugin_textdomain(
			self::con()->getTextDomain(),
			false,
			plugin_basename( self::con()->getPath_Languages() )
		);
		 */
	}

	/**
	 * Path of format -
	 * wp-content/languages/plugins/wp-simple-firewall-de_DE.mo
	 */
	private function overrideTranslations( string $moFilePath ) :string {
		$con = self::con();

		// use determine_locale() as it also considers the user's profile preference
		$locale = \function_exists( 'determine_locale' ) ? determine_locale() : Services::WpGeneral()->getLocale();
		$filteredLocale = apply_filters( 'plugin_locale', $locale, $con->getTextDomain() );
		if ( !empty( $filteredLocale ) ) {
			$locale = $filteredLocale;
		}

		/**
		 * Cater for duplicate language translations that don't exist (yet)
		 * E.g. where Spanish-Spain is present
		 * This isn't ideal, and in-time we'll like full localizations, but we aren't there.
		 */
		$country = \substr( (string)$locale, 0, 2 );
		$duplicateMappings = [
			'en' => 'en_GB',
			'es' => 'es_ES',
			'fr' => 'fr_FR',
			'pt' => 'pt_PT',
		];
		if ( \array_key_exists( $country, $duplicateMappings ) ) {
			$locale = $duplicateMappings[ $country ];
		}

		$maybeMo = path_join( $con->getPath_Languages(), $con->getTextDomain().'-'.$locale.'.mo' );
		return Services::WpFs()->exists( $maybeMo ) ? $maybeMo : $moFilePath;
	}
}