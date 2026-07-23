<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Components\CompCons;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\AssetsCustomizer;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class AssetFilterBoundaryIntegrationTest extends ShieldIntegrationTestCase {

	private array $filterBackups = [];

	public function set_up() {
		parent::set_up();
		global $wp_filter;
		foreach ( [
			'shield/custom_enqueue_assets',
			'shield/custom_localisations',
			'shield/custom_localisations/components',
		] as $hook ) {
			$this->filterBackups[ $hook ] = $wp_filter[ $hook ] ?? null;
		}
		$this->requireController()->comps->assets_customizer->resetExecution()->execute();
	}

	public function tear_down() {
		global $wp_filter;
		foreach ( $this->filterBackups as $hook => $backup ) {
			if ( $backup === null ) {
				unset( $wp_filter[ $hook ] );
			}
			else {
				$wp_filter[ $hook ] = $backup;
			}
		}
		parent::tear_down();
	}

	public function test_asset_and_localisation_chains_recover_from_hostile_upstream_values() :void {
		add_filter( 'shield/custom_enqueue_assets', static fn() => (object)[ 'hostile' => true ], 1 );
		add_filter( 'shield/custom_localisations', static fn() => false, 1 );

		$assets = apply_filters( 'shield/custom_enqueue_assets', [], 'plugins.php' );
		$locals = apply_filters( 'shield/custom_localisations', [], 'plugins.php', [ 'wpadmin' ] );

		$this->assertIsArray( $assets );
		$this->assertContainsOnly( 'string', $assets );
		$this->assertIsArray( $locals );
		foreach ( $locals as $local ) {
			$this->assertIsArray( $local );
			$this->assertCount( 3, $local );
			$this->assertIsString( $local[ 0 ] );
			$this->assertIsString( $local[ 1 ] );
			$this->assertIsArray( $local[ 2 ] );
		}
	}

	/**
	 * @dataProvider provideHostileFilterValues
	 */
	public function test_asset_chain_canonicalizes_hostile_upstream_families( $hostile ) :void {
		add_filter( 'shield/custom_enqueue_assets', static fn() => $hostile, 1 );

		$assets = apply_filters( 'shield/custom_enqueue_assets', [], 'plugins.php' );

		$this->assertIsArray( $assets );
		$this->assertContainsOnly( 'string', $assets );
	}

	/**
	 * @dataProvider provideHostileFilterValues
	 */
	public function test_localisation_chain_canonicalizes_hostile_upstream_families( $hostile ) :void {
		add_filter( 'shield/custom_localisations', static fn() => $hostile, 1 );

		$this->assertIsArray( apply_filters(
			'shield/custom_localisations',
			[],
			(object)[],
			new \stdClass()
		) );
	}

	public function provideHostileFilterValues() :array {
		return [
			'null'    => [ null ],
			'false'   => [ false ],
			'integer' => [ 123 ],
			'object'  => [ new \stdClass() ],
			'mixed array' => [ [ ' valid ', [], new \stdClass() ] ],
		];
	}

	/**
	 * @dataProvider provideInvalidFinalAssetValues
	 */
	public function test_enqueue_owner_drops_hostile_final_asset_output( $hostile ) :void {
		add_filter( 'shield/custom_enqueue_assets', static fn() => $hostile, \PHP_INT_MAX );

		$method = new \ReflectionMethod( Enqueue::class, 'buildAssetsToEnqueue' );
		$method->setAccessible( true );

		$this->assertSame( [
			Enqueue::JS  => [],
			Enqueue::CSS => [],
		], $method->invoke( new Enqueue() ) );
	}

	public function provideInvalidFinalAssetValues() :array {
		return [
			'null'    => [ null ],
			'false'   => [ false ],
			'integer' => [ 123 ],
			'object'  => [ new \stdClass() ],
		];
	}

	public function test_enqueue_owner_drops_invalid_final_asset_members_and_keeps_valid_sibling() :void {
		add_filter( 'shield/custom_enqueue_assets', static fn() => [
			' main ',
			[],
			new \stdClass(),
			'',
			'main',
		], \PHP_INT_MAX );

		$method = new \ReflectionMethod( Enqueue::class, 'buildAssetsToEnqueue' );
		$method->setAccessible( true );
		$handle = $this->requireController()->prefix( 'main' );

		$this->assertSame( [
			Enqueue::JS  => [ $handle ],
			Enqueue::CSS => [ $handle ],
		], $method->invoke( new Enqueue() ) );
	}

	public function test_component_owner_drops_hostile_final_output() :void {
		add_filter( 'shield/custom_localisations/components', static fn() => new \stdClass(), \PHP_INT_MAX );

		$method = new \ReflectionMethod( AssetsCustomizer::class, 'components' );
		$method->setAccessible( true );

		$this->assertSame( [], $method->invoke( new AssetsCustomizer() ) );
	}

	public function test_component_owner_drops_invalid_definition_and_keeps_valid_sibling() :void {
		add_filter( 'shield/custom_localisations/components', static fn() => [
			'scalar-entry' => 'invalid',
			'object-entry' => new \stdClass(),
			'missing-handles' => [ 'data' => [] ],
			'non-array-handles' => [ 'handles' => 'main', 'data' => [] ],
			'empty-handles' => [ 'handles' => [ 123, [], ' ' ], 'data' => [] ],
			'missing-data' => [ 'handles' => [ 'main' ] ],
			'noncallable-data' => [ 'handles' => [ 'main' ], 'data' => new \stdClass() ],
			'valid' => [
				'key'     => 'wrong-upstream-key',
				'handles' => [ ' main ', 123 ],
				'data'    => [],
			],
			'invalid-required' => [
				'key'      => 'invalid-required',
				'handles'  => [ 'main' ],
				'data'     => [],
				'required' => null,
			],
		], \PHP_INT_MAX );

		$method = new \ReflectionMethod( AssetsCustomizer::class, 'components' );
		$method->setAccessible( true );
		$components = $method->invoke( new AssetsCustomizer() );

		$this->assertSame( [ 'valid' ], \array_keys( $components ) );
		$this->assertSame( 'valid', $components[ 'valid' ][ 'key' ] );
		$this->assertSame( [ 'main' ], $components[ 'valid' ][ 'handles' ] );
	}

	public function test_component_data_builder_drops_malformed_callable_output_and_keeps_valid_sibling() :void {
		add_filter( 'shield/custom_localisations/components', static fn() => [
			'valid' => [
				'handles' => [ 'boundary' ],
				'data'    => static fn() => [ 'ok' => true ],
			],
			'invalid' => [
				'handles' => [ 'boundary' ],
				'data'    => static fn() => new \stdClass(),
			],
		], \PHP_INT_MAX );

		$method = new \ReflectionMethod( AssetsCustomizer::class, 'buildCustomLocalisations' );
		$method->setAccessible( true );
		$locals = $method->invoke( new AssetsCustomizer(), [], 'plugins.php', [ 'boundary' ] );
		$local = \array_values( $locals )[ 0 ];

		$this->assertCount( 1, $locals );
		$this->assertSame( [ 'valid' ], \array_keys( $local[ 2 ][ 'comps' ] ) );
		$this->assertSame( [ 'ok' => true ], $local[ 2 ][ 'comps' ][ 'valid' ] );
	}

	public function test_enqueue_owner_drops_invalid_localisations_but_keeps_valid_sibling() :void {
		$con = $this->requireController();
		$handle = $con->prefix( 'boundary' );
		wp_register_script( $handle, 'https://example.test/boundary.js', [], $con->cfg->version(), true );
		add_filter( 'shield/custom_localisations', static fn() => [
			[ 'boundary', 'ShieldBoundary', [ 'ok' => true ] ],
			[ [], 'InvalidHandle', [] ],
			[ 'boundary', 123, [] ],
			[ 'boundary', 'InvalidData', new \stdClass() ],
			'not-a-tuple',
		], \PHP_INT_MAX );

		$method = new \ReflectionMethod( Enqueue::class, 'localise' );
		$method->setAccessible( true );
		$method->invoke( new Enqueue() );

		global $wp_scripts;
		$data = (string)$wp_scripts->get_data( $handle, 'data' );
		$this->assertStringContainsString( 'var ShieldBoundary', $data );
		$this->assertStringNotContainsString( 'InvalidHandle', $data );
		$this->assertStringNotContainsString( 'InvalidData', $data );
	}
}
