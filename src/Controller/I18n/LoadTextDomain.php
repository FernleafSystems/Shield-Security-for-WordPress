<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LoadTextDomain {

	use PluginControllerConsumer;

	private static bool $processing = false;

	public function run() {
		/**
		 * Translations override - we want to use our in-plugin translations, not those
		 * provided by WordPress.org since getting our existing translations into the WP.org
		 * system is full of friction, though that's where we'd like to end-up eventually.
		 */
		add_filter( 'load_textdomain_mofile', [ $this, 'onLoadTextdomainMofile' ], 100, 2 );
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

	public function onLoadTextdomainMofile( string $moFile, string $domain ) :string {
		if ( !self::$processing && $domain === self::con()->getTextDomain() ) {
			self::$processing = true;
			try {
				$moFile = $this->overrideTranslations( $moFile );
			}
			finally {
				self::$processing = false;
			}
		}
		return $moFile;
	}

	/**
	 * Path of format -
	 * wp-content/languages/plugins/wp-simple-firewall-de_DE.mo
	 */
	private function overrideTranslations( string $moFilePath ) :string {
		// use determine_locale() as it also considers the user's profile preference
		$targetLoc = \function_exists( 'determine_locale' ) ? determine_locale() : Services::WpGeneral()->getLocale();
		$filteredLocale = apply_filters( 'plugin_locale', $targetLoc, self::con()->getTextDomain() );
		if ( !empty( $filteredLocale ) ) {
			$targetLoc = $filteredLocale;
		}

		$targetLoc = (string)$targetLoc;
		$overrideLang = self::con()->opts->optGet( 'language_override' );
		if ( !empty( $overrideLang ) ) {
			$targetLoc = $overrideLang;
		}

		$finalMoPath = $this->findPluginIntegratedMo( $targetLoc );
		if ( empty( $finalMoPath ) ) {
			$finalMoPath = $this->findDynamicMo( $targetLoc );
		}

		if ( empty( $finalMoPath ) && !empty( $overrideLang ) ) {
			// No locale match for the forced language override: use source strings.
			$moFilePath = '';
		}

		return empty( $finalMoPath ) ? $moFilePath : $finalMoPath;
	}

	private function findPluginIntegratedMo( string $targetLocale ) :?string {
		$foundMoPath = null;
		$availableLocales = ( new GetAllAvailableLocales() )->run();

		$exactMoPath = $availableLocales[ $targetLocale ] ?? '';
		if ( !empty( $exactMoPath ) && Services::WpFs()->exists( $exactMoPath ) ) {
			$foundMoPath = $exactMoPath;
		}

		// Then look for mo for that language.
		if ( empty( $foundMoPath ) ) {
			$languageLocale = ( new LocaleLanguageMatcher() )->getFirstLocaleForLanguage( \array_keys( $availableLocales ), $targetLocale );
			if ( !empty( $languageLocale ) ) {
				$moPath = $availableLocales[ $languageLocale ] ?? '';
				if ( !empty( $moPath ) && Services::WpFs()->exists( $moPath ) ) {
					$foundMoPath = $moPath;
				}
			}
		}

		return $foundMoPath;
	}

	private function findDynamicMo( string $targetLocale ) :?string {
		$foundMoPath = null;
		$transDownloaderCon = self::con()->comps->translation_downloads;
		$cachedLocales = \array_keys( $transDownloaderCon->getCachedLocales() );

		// Check for the exact locale match in the cache
		$cachedPath = $transDownloaderCon->getLocaleMoFilePath( $targetLocale );
		if ( !empty( $cachedPath ) ) {
			$foundMoPath = $cachedPath;
		}

		// Try language-only match in cache (e.g., 'de' from 'de_DE')
		if ( empty( $foundMoPath ) ) {
			$languageLocale = ( new LocaleLanguageMatcher() )->getFirstLocaleForLanguage( $cachedLocales, $targetLocale );
			if ( !empty( $languageLocale ) ) {
				$cachedPath = $transDownloaderCon->getLocaleMoFilePath( $languageLocale );
				if ( !empty( $cachedPath ) ) {
					$foundMoPath = $cachedPath;
				}
			}
		}

		// 3. Queue for async download if not found
		if ( empty( $foundMoPath ) ) {
			if ( $transDownloaderCon->isLocaleAvailable( $targetLocale ) ) {
				$localeToQueue = $targetLocale;
			}
			else {
				$localeToQueue = ( new LocaleLanguageMatcher() )->getFirstLocaleForLanguage( $cachedLocales, $targetLocale );
			}
			if ( !empty( $localeToQueue ) ) {
				$transDownloaderCon->enqueueLocaleForDownload( $localeToQueue );
			}
		}

		return $foundMoPath;
	}
}
