<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\I18n;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n\GetAllAvailableLocales;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n\LoadTextDomain;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldWordPressTestCase;

class LoadTextDomainLanguageOverrideIntegrationTest extends ShieldWordPressTestCase {

	private LoadTextDomain $loader;
	private \ReflectionMethod $overrideTranslationsMethod;

	public function set_up() {
		parent::set_up();

		$this->loader = new LoadTextDomain();
		$reflection = new \ReflectionClass( $this->loader );
		$this->overrideTranslationsMethod = $reflection->getMethod( 'overrideTranslations' );
		$this->overrideTranslationsMethod->setAccessible( true );

		$this->setLanguageOverride( '' );
	}

	public function tear_down() {
		$this->setLanguageOverride( '' );
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

		$this->assertSame( $expectedPath, $this->invokeOverrideTranslations( $sourcePath ) );
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

		$this->assertSame( $expectedPath, $this->invokeOverrideTranslations( $sourcePath ) );
	}

	public function testLanguageOverrideWithoutMatchAvoidsUnrelatedLocaleFile() :void {
		$con = self::con();
		$this->assertNotNull( $con );

		$sourcePath = $this->firstIntegratedLocalePathForLanguage( 'de' );
		if ( $sourcePath === null ) {
			$this->markTestSkipped( 'No integrated German locale is available in this environment.' );
		}

		$this->setLanguageOverride( 'en' );
		$resolved = $this->invokeOverrideTranslations( $sourcePath );

		$expectedEnglishPath = $this->firstIntegratedLocalePathForLanguage( 'en' );
		if ( $expectedEnglishPath !== null ) {
			$this->assertSame( $expectedEnglishPath, $resolved );
		}
		else {
			$this->assertSame( '', $resolved );
		}
	}

	public function testNoOverrideUsesPluginLocaleTarget() :void {
		$con = self::con();
		$this->assertNotNull( $con );

		$expectedFrenchPath = $this->firstIntegratedLocalePathForLanguage( 'fr' );
		if ( $expectedFrenchPath === null ) {
			$this->markTestSkipped( 'No integrated French locale is available in this environment.' );
		}

		$sourcePath = $this->firstIntegratedLocalePathForLanguage( 'de' );
		if ( $sourcePath === null ) {
			$this->markTestSkipped( 'No integrated German locale is available in this environment.' );
		}

		$this->setLanguageOverride( '' );
		$resolved = $this->invokeWithPluginLocale( 'fr_FR', fn() => $this->invokeOverrideTranslations( $sourcePath ) );

		$this->assertSame( $expectedFrenchPath, $resolved );
	}

	public function testOverrideLanguageTakesPrecedenceOverPluginLocale() :void {
		$con = self::con();
		$this->assertNotNull( $con );

		$expectedGermanPath = $this->firstIntegratedLocalePathForLanguage( 'de' );
		if ( $expectedGermanPath === null ) {
			$this->markTestSkipped( 'No integrated German locale is available in this environment.' );
		}

		$sourcePath = $this->firstIntegratedLocalePathForLanguage( 'fr' );
		if ( $sourcePath === null ) {
			$this->markTestSkipped( 'No integrated French locale is available in this environment.' );
		}

		$this->setLanguageOverride( 'de' );
		$resolved = $this->invokeWithPluginLocale( 'fr_FR', fn() => $this->invokeOverrideTranslations( $sourcePath ) );

		$this->assertSame( $expectedGermanPath, $resolved );
	}

	public function testInvalidOverrideIsSanitizedToEmptyOnStore() :void {
		$con = self::con();
		$this->assertNotNull( $con );

		$this->setLanguageOverride( 'ENG<script>' );
		$con->opts->store();

		$this->assertSame( '', (string)$con->opts->optGet( 'language_override' ) );
	}

	private function invokeOverrideTranslations( string $moFilePath ) :string {
		return (string)$this->overrideTranslationsMethod->invoke( $this->loader, $moFilePath );
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
