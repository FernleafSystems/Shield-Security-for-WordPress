<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Standing;

/**
 * The offense evidence gathered against an IP during one request, ready to be added to its record.
 */
class EvidenceLedger {

	/**
	 * @var array<string, array{strength: string, offenses: float}>
	 */
	private array $signals = [];

	public function add( string $signal, string $strength, float $offenses ) :void {
		$this->signals[ $signal ] ??= [
			'strength' => $strength,
			'offenses' => 0.0,
		];
		$this->signals[ $signal ][ 'offenses' ] += $offenses;
	}

	public function isEmpty() :bool {
		return empty( $this->signals );
	}

	public function offensesFor( string $strength ) :float {
		return (float)\array_sum( \array_column( $this->signalsFor( $strength ), 'offenses' ) );
	}

	/**
	 * @return array<string, float> weak offenses gathered per signal
	 */
	public function weakSignals() :array {
		return \array_map( fn( array $signal ) => $signal[ 'offenses' ], $this->signalsFor( EvidenceWeights::WEAK ) );
	}

	private function signalsFor( string $strength ) :array {
		return \array_filter( $this->signals, fn( array $signal ) => $signal[ 'strength' ] === $strength );
	}
}
