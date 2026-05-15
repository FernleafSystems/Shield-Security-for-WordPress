<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class PolicyEvidenceRecorder {

	use ExecOnce;
	use PluginControllerConsumer;

	private const WINDOW_15M = '15m';
	private const WINDOW_24H = '24h';

	private PolicyStateRepository $repository;

	private PolicyEvidenceMapper $mapper;

	/**
	 * @var array<string, PolicyState>
	 */
	private array $dirty = [];

	public function __construct( ?PolicyStateRepository $repository = null, ?PolicyEvidenceMapper $mapper = null ) {
		$this->repository = $repository ?? new PolicyStateRepository();
		$this->mapper = $mapper ?? new PolicyEvidenceMapper();
		add_action( self::con()->prefix( 'plugin_shutdown' ), [ $this, 'flush' ], 100 );
	}

	protected function run() {
		add_action( 'shield/event', function ( string $event, array $meta = [] ) {
			foreach ( $this->mapper->fromEvent( $event, $meta ) as $evidence ) {
				$this->record( self::con()->this_req->ip, $evidence );
			}
		}, 10, 2 );
	}

	public function record( string $ip, PolicyEvidence $evidence ) :PolicyState {
		$state = $this->repository->forIp( $ip );
		$now = $this->now();

		foreach ( [
			self::WINDOW_15M => MINUTE_IN_SECONDS*15,
			self::WINDOW_24H => DAY_IN_SECONDS,
		] as $window => $seconds ) {
			$current = $state->meta[ 'evidence' ][ $evidence->type ][ $window ] ?? [];
			$started = (int)( $current[ 'started_at' ] ?? 0 );
			$count = (int)( $current[ 'count' ] ?? 0 );
			if ( $started <= 0 || $started < $now - $seconds ) {
				$started = $now;
				$count = 0;
			}
			$state->meta[ 'evidence' ][ $evidence->type ][ $window ] = [
				'started_at' => $started,
				'count'      => $count + 1,
			];
		}

		$state->meta[ 'last_evidence' ][ $evidence->type ] = [
			'at'       => $now,
			'detector' => $evidence->detector,
			'rule'     => $evidence->rule_slug,
			'severity' => $evidence->severity,
			'event'    => $evidence->source_event,
		];
		$state->last_evidence_at = $now;
		$state->expires_at = $now + DAY_IN_SECONDS*2;
		$this->applyEvidenceRisk( $state, $evidence );
		$state->dirty = true;

		$this->dirty[ $ip ] = $state;
		return $state;
	}

	public function flush() :void {
		foreach ( $this->dirty as $ip => $state ) {
			if ( $this->repository->save( $state ) ) {
				unset( $this->dirty[ $ip ] );
			}
		}
	}

	private function applyEvidenceRisk( PolicyState $state, PolicyEvidence $evidence ) :void {
		if ( $evidence->severity === PolicyEvidence::SEVERITY_CRITICAL ) {
			$state->risk_band = PolicyState::BAND_HOSTILE;
		}
	}

	protected function now() :int {
		return Services::Request()->ts();
	}
}
