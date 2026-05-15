<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Integrations;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers\Base as SpamBase;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\SpamController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers\Base as UserFormsBase;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers\WordPress;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\UserFormsController;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class BotProviderRegistryContractTest extends TestCase {

	use PluginPathsTrait;

	public function testSpamProviderRegistryIsValid() :void {
		$this->assertProviderRegistry(
			'spam',
			( new SpamController() )->enumProviders(),
			SpamBase::class
		);
	}

	public function testUserFormsProviderRegistryIsValid() :void {
		$this->assertProviderRegistry(
			'user_forms',
			( new UserFormsController() )->enumProviders(),
			UserFormsBase::class
		);
	}

	public function testWordPressUserFormsProviderIsAlwaysAvailable() :void {
		$this->assertSame( 'wordpress', WordPress::Slug() );
		$this->assertTrue( WordPress::IsProviderInstalled() );
		$this->assertTrue( WordPress::IsProviderAvailable() );
	}

	public function testProviderRegistriesMatchSourceOptionContracts() :void {
		$this->assertProviderRegistryMatchesOption(
			'form_spam_providers',
			( new SpamController() )->enumProviders()
		);
		$this->assertProviderRegistryMatchesOption(
			'user_form_providers',
			( new UserFormsController() )->enumProviders()
		);
	}

	public function testIntegrationProviderOptionsExposeStableDefaults() :void {
		$options = $this->sourceOptionsByKey();

		$this->assertSame( [], $options[ 'auto_integrations_track' ][ 'default' ] ?? null );
		$this->assertSame( 'array', $options[ 'auto_integrations_track' ][ 'type' ] ?? null );

		$this->assertSame( 'N', $options[ 'enable_auto_integrations' ][ 'default' ] ?? null );
		$this->assertSame( 'checkbox', $options[ 'enable_auto_integrations' ][ 'type' ] ?? null );
		$this->assertSame( true, $options[ 'enable_auto_integrations' ][ 'premium' ] ?? false );

		$this->assertSame( [], $options[ 'form_spam_providers' ][ 'default' ] ?? null );
		$this->assertSame( 'multiple_select', $options[ 'form_spam_providers' ][ 'type' ] ?? null );
		$this->assertSame( true, $options[ 'form_spam_providers' ][ 'premium' ] ?? false );

		$this->assertSame( [ 'wordpress' ], $options[ 'user_form_providers' ][ 'default' ] ?? null );
		$this->assertSame( 'multiple_select', $options[ 'user_form_providers' ][ 'type' ] ?? null );
	}

	public function testProviderRegistriesDoNotShareDiscoverySlugs() :void {
		$spamSlugs = \array_keys( ( new SpamController() )->enumProviders() );
		$userFormSlugs = \array_keys( ( new UserFormsController() )->enumProviders() );

		$this->assertSame( [], \array_values( \array_intersect( $spamSlugs, $userFormSlugs ) ) );
	}

	/**
	 * @param array<string, string> $providers
	 */
	private function assertProviderRegistry( string $name, array $providers, string $expectedBaseClass ) :void {
		$this->assertNotEmpty( $providers, \sprintf( "Registry '%s' should not be empty.", $name ) );
		$this->assertTrue(
			\class_exists( $expectedBaseClass ),
			\sprintf( "Expected base class '%s' for registry '%s' should exist.", $expectedBaseClass, $name )
		);

		$keys = \array_keys( $providers );
		$this->assertCount(
			\count( \array_unique( $keys ) ),
			$keys,
			\sprintf( "Registry '%s' contains duplicate provider keys.", $name )
		);

		foreach ( $providers as $slug => $class ) {
			$this->assertIsString( $slug, \sprintf( "Registry '%s' has a non-string slug.", $name ) );
			$this->assertNotSame( '', \trim( $slug ), \sprintf( "Registry '%s' contains an empty slug.", $name ) );
			$this->assertIsString( $class, \sprintf( "Registry '%s' has a non-string class for slug '%s'.", $name, $slug ) );
			$this->assertNotSame( '', \trim( $class ), \sprintf( "Registry '%s' slug '%s' has an empty class.", $name, $slug ) );

			$this->assertTrue(
				\class_exists( $class ),
				\sprintf( "Registry '%s' class '%s' for slug '%s' does not exist.", $name, $class, $slug )
			);
			$this->assertTrue(
				\is_subclass_of( $class, $expectedBaseClass ),
				\sprintf(
					"Registry '%s' class '%s' for slug '%s' must extend '%s'.",
					$name,
					$class,
					$slug,
					$expectedBaseClass
				)
			);
			$this->assertTrue(
				\method_exists( $class, 'Slug' ),
				\sprintf( "Registry '%s' class '%s' should expose static Slug().", $name, $class )
			);
			$this->assertSame(
				$slug,
				$class::Slug(),
				\sprintf( "Registry '%s' class '%s' should expose slug '%s'.", $name, $class, $slug )
			);
		}
	}

	/**
	 * @param array<string,string> $providers
	 */
	private function assertProviderRegistryMatchesOption( string $optionKey, array $providers ) :void {
		$options = $this->sourceOptionsByKey();
		$this->assertArrayHasKey( $optionKey, $options );

		$valueOptions = $options[ $optionKey ][ 'value_options' ] ?? [];
		$this->assertIsArray( $valueOptions );
		$optionSlugs = \array_column( $valueOptions, 'value_key' );
		$providerSlugs = \array_keys( $providers );
		\sort( $optionSlugs );
		\sort( $providerSlugs );
		$this->assertSame(
			$optionSlugs,
			$providerSlugs,
			\sprintf( "Option '%s' value options should match its provider registry.", $optionKey )
		);

		foreach ( $valueOptions as $option ) {
			$this->assertIsString( $option[ 'text' ] ?? null );
			$this->assertNotSame(
				'',
				\trim( (string)$option[ 'text' ] ),
				\sprintf( "Option '%s' provider '%s' should expose a stable name.", $optionKey, $option[ 'value_key' ] ?? '' )
			);
		}
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function sourceOptionsByKey() :array {
		$options = $this->decodePluginJsonFile( 'plugin-spec/34_options.json', 'Source options spec' );
		$byKey = [];
		foreach ( $options as $option ) {
			$byKey[ (string)$option[ 'key' ] ] = $option;
		}
		return $byKey;
	}
}
