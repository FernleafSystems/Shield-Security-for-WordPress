<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LoadTextDomain {

	use PluginControllerConsumer;

	public function run() {
		$con = $this->getCon();

		/**
		 * Translations override - we want to use our in-plugin translations, not those
		 * provided by WordPress.org since getting our existing translations into the WP.org
		 * system is full of friction, though that's where we'd like to end-up eventually.
		 */
		add_filter( 'load_textdomain_mofile', function ( $mofile, $domain ) {
			if ( $domain == $this->getCon()->getTextDomain() ) {
				$mofile = $this->overrideTranslations( (string)$mofile );
			}
			return $mofile;
		}, 100, 2 );

		load_plugin_textdomain(
			$con->getTextDomain(),
			false,
			plugin_basename( $con->getPath_Languages() )
		);
	}

	/**
	 * Path of format -
	 * wp-content/languages/plugins/wp-simple-firewall-de_DE.mo
	 * @param string $moFilePath
	 * @return string
	 */
	private function overrideTranslations( string $moFilePath ) {
		$con = $this->getCon();

		// use determine_locale() as it also considers the user's profile preference
		$locale = apply_filters(
			'plugin_locale',
			function_exists( 'determine_locale' ) ? determine_locale() : Services::WpGeneral()->getLocale(),
			$con->getTextDomain()
		);

		/**
		 * Cater for duplicate language translations that don't exist (yet)
		 * E.g. where Spanish-Spain is present
		 * This isn't ideal, and in-time we'll like full localizations, but we aren't there.
		 */
		$country = substr( $locale, 0, 2 );
		$aDuplicateMappings = [
			'en' => 'en_GB',
			'es' => 'es_ES',
			'fr' => 'fr_FR',
			'pt' => 'pt_PT',
		];
		if ( array_key_exists( $country, $aDuplicateMappings ) ) {
			$locale = $aDuplicateMappings[ $country ];
		}

		$maybeMo = path_join( $con->getPath_Languages(), $con->getTextDomain().'-'.$locale.'.mo' );
		return Services::WpFs()->exists( $maybeMo ) ? $maybeMo : $moFilePath;
	}
}