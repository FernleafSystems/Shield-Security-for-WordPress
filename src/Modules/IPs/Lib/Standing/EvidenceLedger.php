<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Standing;

/**
 * The offense evidence gathered against an IP during one request, ready to be added to its record.
 */
class EvidenceLedger {

	/**
	 * @var array<string, array{strength: string, units: int}>
	 */
	private array $signals = [];

	public function add( string $signal, string $strength, int $units ) :void {
		$this->signals[ $signal ] ??= [
			'strength' => $strength,
			'units'    => 0,
		];
		$this->signals[ $signal ][ 'units' ] += $units;
	}

	public function isEmpty() :bool {
		return empty( $this->signals );
	}

	public function unitsFor( string $strength ) :int {
		return (int)\array_sum( \array_column( $this->signalsFor( $strength ), 'units' ) );
	}

	/**
	 * @return array<string, int> weak units gathered per signal
	 */
	public function weakSignals() :array {
		return \array_map( fn( array $signal ) => $signal[ 'units' ], $this->signalsFor( EvidenceWeights::WEAK ) );
	}

	private function signalsFor( string $strength ) :array {
		return \array_filter( $this->signals, fn( array $signal ) => $signal[ 'strength' ] === $strength );
	}
}
