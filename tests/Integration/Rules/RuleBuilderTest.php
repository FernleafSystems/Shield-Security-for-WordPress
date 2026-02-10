<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Tests that the rule builder produces valid RuleVO objects and that
 * rules survive the store/load round-trip.
 */
class RuleBuilderTest extends ShieldIntegrationTestCase {

	public function test_builder_produces_non_empty_rules() {
		$con = $this->requireController();

		$rules = $con->rules->getRules();
		$this->assertIsArray( $rules );
		$this->assertNotEmpty( $rules, 'Rule builder should produce at least some rules' );
	}

	public function test_each_rule_has_slug_and_conditions() {
		$con = $this->requireController();

		$rules = $con->rules->getRules();
		$broken = [];

		foreach ( $rules as $rule ) {
			if ( !$rule instanceof RuleVO ) {
				$broken[] = 'Not a RuleVO instance';
				continue;
			}
			if ( empty( $rule->slug ) ) {
				$broken[] = 'Rule missing slug';
			}
			if ( empty( $rule->getRawData()['conditions'] ?? [] ) ) {
				$broken[] = $rule->slug.' has empty conditions';
			}
		}

		$this->assertEmpty( $broken, 'Rule issues: '.\implode( '; ', $broken ) );
	}

	public function test_rules_round_trip_storage() {
		$con = $this->requireController();

		$originalRules = $con->rules->getRules();
		$this->assertNotEmpty( $originalRules );

		$originalSlugs = \array_map( fn( $r ) => $r->slug, $originalRules );
		\sort( $originalSlugs );

		// Force a rebuild and re-store
		$con->rules->buildAndStore();
		$reloaded = $con->rules->getRules();

		$this->assertNotEmpty( $reloaded );

		$reloadedSlugs = \array_map( fn( $r ) => $r->slug, $reloaded );
		\sort( $reloadedSlugs );

		$this->assertSame( $originalSlugs, $reloadedSlugs,
			'Rule slugs should be identical after round-trip storage' );
	}
}
