<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Configuration;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Every offense event declares how strongly it counts against an IP's visitor standing,
 * and only offense events do. Firewall blocks are weighted by firewall category.
 */
class EvidenceStrengthConfigTest extends TestCase {

	use PluginPathsTrait;

	private const STRENGTHS = [ 'weak', 'normal', 'strong' ];

	private array $config;

	protected function set_up() :void {
		parent::set_up();
		$this->config = $this->decodePluginJsonFile( 'plugin.json', 'Plugin configuration' );
	}

	public function test_offense_events_and_only_offense_events_declare_a_valid_evidence_strength() :void {
		$events = \array_filter( $this->config[ 'config_spec' ][ 'events' ] ?? [], '\is_array' );
		$this->assertNotEmpty( $events );

		$problems = [];
		foreach ( $events as $eventKey => $eventDef ) {
			$isOffense = !empty( $eventDef[ 'offense' ] );
			$strength = $eventDef[ 'evidence_strength' ] ?? null;
			if ( $isOffense && !\in_array( $strength, self::STRENGTHS, true ) ) {
				$problems[] = $eventKey.': offense event without a valid evidence_strength';
			}
			elseif ( !$isOffense && $strength !== null ) {
				$problems[] = $eventKey.': evidence_strength on an event that is not an offense';
			}
		}

		$this->assertSame( [], $problems );
	}

	public function test_every_firewall_category_declares_a_valid_evidence_strength() :void {
		$defs = $this->config[ 'config_spec' ][ 'defs' ] ?? [];
		$categories = \array_keys( $defs[ 'firewall_patterns' ] ?? [] );
		$strengths = $defs[ 'firewall_evidence_strengths' ] ?? [];
		$this->assertNotEmpty( $categories );

		\sort( $categories );
		$weighted = \array_keys( $strengths );
		\sort( $weighted );
		$this->assertSame( $categories, $weighted );

		foreach ( $strengths as $category => $strength ) {
			$this->assertContains( $strength, self::STRENGTHS, 'Firewall category: '.$category );
		}
	}
}
