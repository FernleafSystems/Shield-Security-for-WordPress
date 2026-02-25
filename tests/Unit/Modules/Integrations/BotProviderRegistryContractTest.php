<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Integrations;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers\Base as SpamBase;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\SpamController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers\Base as UserFormsBase;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\UserFormsController;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class BotProviderRegistryContractTest extends TestCase {

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
		}
	}
}

