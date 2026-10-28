<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Standing;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * How much offense evidence counts against an IP's visitor standing, in offenses, and how quickly it fades.
 */
class EvidenceWeights {

	use PluginControllerConsumer;

	public const WEAK = 'weak';
	public const NORMAL = 'normal';
	public const STRONG = 'strong';

	public const OFFENSES = [
		self::WEAK   => 0.5,
		self::NORMAL => 1.0,
		self::STRONG => 3.0,
	];

	public const HALF_LIFE = [
		self::WEAK   => \DAY_IN_SECONDS,
		self::NORMAL => \DAY_IN_SECONDS,
		self::STRONG => \WEEK_IN_SECONDS,
	];

	/**
	 * Repeating one weak signal is not stronger evidence, so each weak signal is capped per IP.
	 */
	public const WEAK_SIGNAL_CAP = 2.0;

	public const SIGNAL_CUSTOM_RULE = 'custom_rule';

	/**
	 * @param array $def the event definition, carrying its evidence_strength
	 */
	public function addEvent( EvidenceLedger $ledger, string $event, array $meta, array $def ) :void {
		$strength = (string)( $def[ 'evidence_strength' ] ?? '' );
		if ( isset( self::OFFENSES[ $strength ] ) ) {
			$signal = $event;

			if ( $event === 'firewall_block' ) {
				$category = (string)( $meta[ 'audit_params' ][ 'scan' ] ?? '' );
				$categoryStrength = (string)( self::con()->cfg->configuration->def( 'firewall_evidence_strengths' )[ $category ] ?? '' );
				if ( isset( self::OFFENSES[ $categoryStrength ] ) ) {
					$strength = $categoryStrength;
					$signal .= ':'.$category;
				}
			}

			$ledger->add( $signal, $strength, self::OFFENSES[ $strength ] );
		}
	}

	public function addCustomRuleOffenses( EvidenceLedger $ledger, int $count ) :void {
		if ( $count > 0 ) {
			$ledger->add( self::SIGNAL_CUSTOM_RULE, self::NORMAL, self::OFFENSES[ self::NORMAL ]*$count );
		}
	}

	public static function Fade( float $offenses, int $since, int $now, string $strength ) :float {
		return ( $offenses > 0 && $since > 0 )
			? $offenses*\pow( 0.5, \max( 0, $now - $since )/self::HALF_LIFE[ $strength ] )
			: 0.0;
	}
}
