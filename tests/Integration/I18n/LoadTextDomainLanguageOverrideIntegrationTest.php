<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\I18n;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n\GetAllAvailableLocales;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n\LoadTextDomain;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldWordPressTestCase;

class LoadTextDomainLanguageOverrideIntegrationTest extends ShieldWordPressTestCase {

	private LoadTextDomain $loader;

	public function set_up() {
		parent::set_up();

		$this->loader = new LoadTextDomain();
		$this->loader->run();
		$this->setLanguageOverride( '' );
	}

	public function tear_down() {
		$this->setLanguageOverride( '' );
		remove_filter( 'load_textdomain_mofile', [ $this->loader, 'onLoadTextdomainMofile' ], 100 );
		remove_action( 'shutdown', [ $this->loader, 'queueLocalesForDownload' ] );
		parent::tear_down();
	}

	public function testLanguageOverrideSelectsMatchingIntegratedLocale() :void {
		$con = self::con();
		$this->assertNotNull( $con );

		$expectedPath = $this->firstIntegratedLocalePathForLanguage( 'de' );
		if ( $expectedPath === null ) {
			$this->markTestSkipped( 'No integrated German locale is available in this environment.' );
		}

		$this->setLanguageOverride( 'de' );
		$sourcePath = path_join( $con->getPath_Languages(), \sprintf( '%s-%s.mo', $con->getTextDomain(), 'fr_FR' ) );

		$this->assertSame( $expectedPath, $this->resolveMoForPluginDomain( $sourcePath ) );
	}

	public function testLanguageOverrideUsesFirstAvailableLocaleForLanguage() :void {
		$con = self::con();
		$this->assertNotNull( $con );

		$expectedPath = $this->firstIntegratedLocalePathForLanguage( 'zh' );
		if ( $expectedPath === null ) {
			$this->markTestSkipped( 'No integrated Chinese locale is available in this environment.' );
		}

		$this->setLanguageOverride( 'zh' );
		$sourcePath = path_join( $con->getPath_Languages(), \sprintf( '%s-%s.mo', $con->getTextDomain(), 'fr_FR' ) );

		$this->assertSame( $expectedPath, $this->resolveMoForPluginDomain( $sourcePath ) );
	}

	public function testLanguageOverrideWithoutMatchAvoidsUnrelatedLocaleFile() :void {
		$sourcePath = $this->firstIntegratedLocalePathForLanguage( 'de' );
		if ( $sourcePath === null ) {
			$this->markTestSkipped( 'No integrated German locale is available in this environment.' );
		}

		$this->setLanguageOverride( 'en' );
		$resolved = $this->resolveMoForPluginDomain( $sourcePath );

		$expectedEnglishPath = $this->firstIntegratedLocalePathForLanguage( 'en' );
		if ( $expectedEnglishPath !== null ) {
			$this->assertSame( $expectedEnglishPath, $resolved );
		}
		else {
			$this->assertSame( '', $resolved );
		}
	}

	public function testNoOverrideUsesPluginLocaleTarget() :void {
		$expectedFrenchPath = $this->firstIntegratedLocalePathForLanguage( 'fr' );
		if ( $expectedFrenchPath === null ) {
			$this->markTestSkipped( 'No integrated French locale is available in this environment.' );
		}

		$sourcePath = $this->firstIntegratedLocalePathForLanguage( 'de' );
		if ( $sourcePath === null ) {
			$this->markTestSkipped( 'No integrated German locale is available in this environment.' );
		}

		$this->setLanguageOverride( '' );
		$resolved = $this->invokeWithPluginLocale( 'fr_FR', fn() => $this->resolveMoForPluginDomain( $sourcePath ) );

		$this->assertSame( $expectedFrenchPath, $resolved );
	}

	public function testOverrideLanguageTakesPrecedenceOverPluginLocale() :void {
		$expectedGermanPath = $this->firstIntegratedLocalePathForLanguage( 'de' );
		if ( $expectedGermanPath === null ) {
			$this->markTestSkipped( 'No integrated German locale is available in this environment.' );
		}

		$sourcePath = $this->firstIntegratedLocalePathForLanguage( 'fr' );
		if ( $sourcePath === null ) {
			$this->markTestSkipped( 'No integrated French locale is available in this environment.' );
		}

		$this->setLanguageOverride( 'de' );
		$resolved = $this->invokeWithPluginLocale( 'fr_FR', fn() => $this->resolveMoForPluginDomain( $sourcePath ) );

		$this->assertSame( $expectedGermanPath, $resolved );
	}

	public function testLanguageOverrideIsNormalisedOnStore() :void {
		$con = self::con();
		$this->assertNotNull( $con );

		$con->opts->optSet( 'language_override', 'EN' )->store();
		$this->assertSame( 'en', (string)$con->opts->optGet( 'language_override' ) );
	}

	public function testInvalidLanguageOverrideIsSanitizedToEmptyOnStore() :void {
		$con = self::con();
		$this->assertNotNull( $con );

		$con->opts->optSet( 'language_override', 'ENG<script>' )->store();
		$this->assertSame( '', (string)$con->opts->optGet( 'language_override' ) );
	}

	public function testOverrideHoldsWhenStringsAreTranslatedDuringRealTextdomainLoad() :void {
		global $wp_textdomain_registry;
		$con = self::con();
		$this->assertNotNull( $con );
		$domain = $con->getTextDomain();
		$locale = determine_locale();
		$sentinel = 'Shield nested-load sentinel';

		$dir = trailingslashit( get_temp_dir() ).'shield-i18n-'.wp_generate_password( 8, false, false );
		$this->assertTrue( wp_mkdir_p( $dir ) );
		$moFile = $dir.'/'.$domain.'-'.$locale.'.mo';
		$mo = new \MO();
		$mo->add_entry( new \Translation_Entry( [ 'singular' => $sentinel, 'translations' => [ 'Foreign sentinel' ] ] ) );
		$this->assertTrue( $mo->export_to_file( $moFile ) );

		$previousPath = $wp_textdomain_registry->get( $domain, $locale );
		$translatedDuringChoice = false;
		$translateDuringChoice = function ( $pluginLocale, $filterDomain ) use ( $domain, $sentinel, &$translatedDuringChoice ) {
			if ( $filterDomain === $domain && !$translatedDuringChoice ) {
				$translatedDuringChoice = true;
				__( $sentinel, $domain );
			}
			return $pluginLocale;
		};

		try {
			unload_textdomain( $domain, true );
			$wp_textdomain_registry->set( $domain, $locale, trailingslashit( $dir ) );

			// Control: without an override, the catalogue loads.
			load_textdomain( $domain, $moFile, $locale );
			$this->assertSame( 'Foreign sentinel', __( $sentinel, $domain ) );
			unload_textdomain( $domain, true );

			$this->setLanguageOverride( 'en' );
			add_filter( 'plugin_locale', $translateDuringChoice, 10, 2 );
			load_textdomain( $domain, $moFile, $locale );

			$this->assertTrue( $translatedDuringChoice, 'A string should be translated while the file is chosen.' );
			$this->assertSame( $sentinel, __( $sentinel, $domain ), 'The override must hold after a nested load.' );
		}
		finally {
			remove_filter( 'plugin_locale', $translateDuringChoice, 10 );
			unload_textdomain( $domain, true );
			$wp_textdomain_registry->set( $domain, $locale, $previousPath );
			@\unlink( $moFile );
			@\rmdir( $dir );
		}
	}

	public function testFilterIgnoredForDifferentDomain() :void {
		$con = self::con();
		$this->assertNotNull( $con );

		$this->setLanguageOverride( 'de' );
		$sourcePath = path_join( $con->getPath_Languages(), \sprintf( '%s-%s.mo', $con->getTextDomain(), 'fr_FR' ) );
		$resolved = apply_filters( 'load_textdomain_mofile', $sourcePath, 'another-text-domain' );

		$this->assertSame( $sourcePath, $resolved );
	}

	/**
	 * @return mixed
	 */
	private function invokeWithPluginLocale( string $locale, callable $invoke ) {
		$filter = fn( $targetLocale, $domain ) => $domain === self::con()->getTextDomain() ? $locale : $targetLocale;
		add_filter( 'plugin_locale', $filter, 10, 2 );
		try {
			return $invoke();
		}
		finally {
			remove_filter( 'plugin_locale', $filter, 10 );
		}
	}

	private function resolveMoForPluginDomain( string $moFilePath ) :string {
		return (string)apply_filters( 'load_textdomain_mofile', $moFilePath, self::con()->getTextDomain() );
	}

	private function setLanguageOverride( string $value ) :void {
		$con = self::con();
		if ( $con !== null ) {
			$con->opts->optSet( 'language_override', $value );
		}
	}

	private function firstIntegratedLocalePathForLanguage( string $targetLang ) :?string {
		$found = null;

		foreach ( ( new GetAllAvailableLocales() )->run() as $locale => $path ) {
			if ( \substr( $locale, 0, 2 ) === $targetLang ) {
				$found = $path;
				break;
			}
		}

		return $found;
	}
}
