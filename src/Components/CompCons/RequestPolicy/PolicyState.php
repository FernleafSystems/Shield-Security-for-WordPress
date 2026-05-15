<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy;

class PolicyState {

	public const BAND_NORMAL = 'normal';
	public const BAND_SUSPICIOUS = 'suspicious';
	public const BAND_HOSTILE = 'hostile';

	public ?int $record_id = null;

	public string $ip = '';

	public int $ip_ref = 0;

	public string $risk_band = self::BAND_NORMAL;

	public int $risk_score = 0;

	public int $last_evidence_at = 0;

	public int $last_decision_at = 0;

	public int $expires_at = 0;

	public array $meta = [];

	public bool $dirty = false;

	public function __construct( array $data = [] ) {
		foreach ( $data as $key => $value ) {
			if ( \property_exists( $this, $key ) ) {
				$this->{$key} = $value;
			}
		}
		$this->risk_score = (int)\max( 0, \min( 100, $this->risk_score ) );
		if ( !\in_array( $this->risk_band, [ self::BAND_NORMAL, self::BAND_SUSPICIOUS, self::BAND_HOSTILE ], true ) ) {
			$this->risk_band = self::BAND_NORMAL;
		}
	}

	public function counter( string $type, string $window ) :int {
		return (int)( $this->meta[ 'evidence' ][ $type ][ $window ][ 'count' ] ?? 0 );
	}

	public function touchDecision( int $ts ) :void {
		$this->last_decision_at = $ts;
		$this->dirty = true;
	}
}
