<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Components\CompCons\Mcp;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Abilities\AbilityDefinitions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildScanFindings;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class AbilitiesRegistrationIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_items' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );

		$this->enablePremiumCapabilities( [ 'rest_api_level_2' ] );
		$this->loginAsAdministrator();
	}

	public function tear_down() {
		$this->unregisterShieldAbilities();
		parent::tear_down();
	}

	public function test_registers_shield_abilities_and_executes_scan_findings_against_live_abilities_api() :void {
		if ( !\function_exists( '\wp_register_ability' )
			 || !\function_exists( '\wp_register_ability_category' )
			 || !\function_exists( '\wp_has_ability' )
			 || !\function_exists( '\wp_get_ability' ) ) {
			$this->markTestSkipped( 'WordPress Abilities API is unavailable in this test environment.' );
		}

		self::con()->comps->mcp->execute();
		$integration = self::con()->comps->mcp->getIntegration();
		$integration->registerAbilityCategory();
		$integration->registerAbilities();

		$this->assertTrue( \wp_has_ability_category( AbilityDefinitions::CATEGORY_SLUG ) );

		foreach ( AbilityDefinitions::MCP_ABILITY_NAMES as $abilityName ) {
			$this->assertTrue( \wp_has_ability( $abilityName ), $abilityName );
		}

		$ability = \wp_get_ability( AbilityDefinitions::NAME_SCAN_FINDINGS );
		$this->assertInstanceOf( \WP_Ability::class, $ability );
		$this->assertSame( AbilityDefinitions::CATEGORY_SLUG, $ability->get_category() );
		$this->assertFalse( $ability->get_meta_item( 'show_in_rest', true ) );
		$this->assertSame( BuildScanFindings::SUPPORTED_STATES, $ability->get_input_schema()[ 'properties' ][ 'filter_item_state' ][ 'items' ][ 'enum' ] ?? [] );
		$this->assertTrue( $ability->get_meta()[ 'annotations' ][ 'readonly' ] ?? false );
		$this->assertTrue( $ability->get_meta()[ 'annotations' ][ 'idempotent' ] ?? false );
		$this->assertFalse( $ability->get_meta()[ 'annotations' ][ 'destructive' ] ?? true );

		$wpvId = TestDataFactory::insertCompletedScan( 'wpv' );
		TestDataFactory::insertScanResultItem( $wpvId, [
			'item_id'       => 'plugin-vulnerable',
			'is_vulnerable' => 1,
		] );

		$apcId = TestDataFactory::insertCompletedScan( 'apc' );
		TestDataFactory::insertScanResultItem( $apcId, [
			'item_id'      => 'plugin-abandoned',
			'is_abandoned' => 1,
		] );

		$result = $ability->execute( [
			'scan_slugs'        => [ 'wpv', 'apc' ],
			'filter_item_state' => [ 'is_vulnerable' ],
		] );

		$this->assertIsArray( $result );
		$this->assertTrue( $result[ 'is_available' ] );
		$this->assertSame( [ 'wpv', 'apc' ], $result[ 'filters' ][ 'scan_slugs' ] );
		$this->assertSame( [ 'is_vulnerable' ], $result[ 'filters' ][ 'states' ] );
		$this->assertSame( 1, $result[ 'results' ][ 'wpv' ][ 'total' ] );
		$this->assertSame( 0, $result[ 'results' ][ 'apc' ][ 'total' ] );
		$this->assertSame( 'plugin-vulnerable', $result[ 'results' ][ 'wpv' ][ 'items' ][ 0 ][ 'item_id' ] );
	}

	private function unregisterShieldAbilities() :void {
		if ( \function_exists( '\wp_unregister_ability' ) ) {
			foreach ( AbilityDefinitions::MCP_ABILITY_NAMES as $abilityName ) {
				if ( !\function_exists( '\wp_has_ability' ) || \wp_has_ability( $abilityName ) ) {
					\wp_unregister_ability( $abilityName );
				}
			}
		}

		if ( \function_exists( '\wp_unregister_ability_category' )
			 && ( !\function_exists( '\wp_has_ability_category' ) || \wp_has_ability_category( AbilityDefinitions::CATEGORY_SLUG ) ) ) {
			\wp_unregister_ability_category( AbilityDefinitions::CATEGORY_SLUG );
		}
	}
}
