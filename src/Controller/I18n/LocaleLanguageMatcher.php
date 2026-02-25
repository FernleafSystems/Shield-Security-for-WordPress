<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n;

class LocaleLanguageMatcher {

	public function localeToLang( string $localeOrLang ) :string {
		return \substr( \strtolower( \trim( $localeOrLang ) ), 0, 2 );
	}

	/**
	 * We intentionally pick the first available locale for the matching language.
	 * Canonical locale selection is more complex and deferred.
	 */
	public function getFirstLocaleForLanguage( array $locales, string $targetLocaleOrLang ) :?string {
		$firstLocale = null;
		$targetLang = $this->localeToLang( $targetLocaleOrLang );

		if ( !empty( $targetLang ) ) {
			$locales = \array_filter( \array_map( 'strval', $locales ) );
			\sort( $locales, \SORT_STRING );
			foreach ( $locales as $maybeLocale ) {
				if ( $targetLang === $this->localeToLang( $maybeLocale ) ) {
					$firstLocale = $maybeLocale;
					break;
				}
			}
		}

		return $firstLocale;
	}

	public function isLocaleOrLanguageMatch( string $candidateLocale, string $targetLocaleOrLang ) :bool {
		return $candidateLocale === $targetLocaleOrLang
			   || $this->localeToLang( $candidateLocale ) === $this->localeToLang( $targetLocaleOrLang );
	}
}
