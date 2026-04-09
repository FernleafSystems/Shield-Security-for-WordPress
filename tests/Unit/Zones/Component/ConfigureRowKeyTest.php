<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Zones\Component;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\Base;

class ConfigureRowKeyTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text )
				? ( \preg_replace( '/[^a-z0-9_-]/', '', \strtolower( \trim( $text ) ) ) ?? '' )
				: ''
		);
	}

	public function test_configure_row_key_returns_first_valid_component_scope_slug() :void {
		$component = new class extends Base {
			protected function configZoneComponentSlugs() :array {
				return [ '  ', 'Primary Scope', 'secondary_scope' ];
			}
		};

		$this->assertSame( 'primaryscope', $component->configureRowKey() );
	}

	public function test_configure_row_key_can_be_explicitly_overridden() :void {
		$component = new class extends Base {
			public function configureRowKey() :string {
				return 'custom_row_key';
			}
		};

		$this->assertSame( 'custom_row_key', $component->configureRowKey() );
	}

	public function test_configure_row_key_rejects_empty_scope_identity() :void {
		$component = new class extends Base {
			protected function configZoneComponentSlugs() :array {
				return [ '!!!', '' ];
			}
		};

		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'stable non-empty component scope slug' );
		$component->configureRowKey();
	}
}
