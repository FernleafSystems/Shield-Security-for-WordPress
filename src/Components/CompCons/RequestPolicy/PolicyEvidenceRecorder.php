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

	/**
	 * @var array<string, PolicyState>
	 */
	private array $dirty = [];

	public function __construct( ?PolicyStateRepository $repository = null ) {
		$this->repository = $repository ?? new PolicyStateRepository();
		add_action( self::con()->prefix( 'plugin_shutdown' ), [ $this, 'flush' ], 100 );
	}

	protected function run() {
		add_action( 'shield/event', function ( string $event, array $meta = [] ) {
			$evidence = $this->evidenceFromEvent( $event, $meta );
			if ( $evidence instanceof PolicyEvidence ) {
				$this->record( self::con()->this_req->ip, $evidence );
			}
		}, 10, 2 );
	}

	public function record( string $ip, PolicyEvidence $evidence ) :PolicyState {
		$state = $this->repository->forIp( $ip );
		$now = Services::Request()->ts();

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
		];
		$state->last_evidence_at = $now;
		$state->expires_at = $now + DAY_IN_SECONDS*2;
		$this->applyEvidenceRisk( $state, $evidence );
		$state->dirty = true;

		$this->dirty[ $ip ] = $state;
		return $state;
	}

	public function markDecision( string $ip, PolicyDecision $decision ) :void {
		$state = $this->repository->forIp( $ip );
		$state->touchDecision( Services::Request()->ts() );
		$state->risk_band = $decision->risk_band;
		$this->dirty[ $ip ] = $state;
	}

	public function flush() :void {
		foreach ( $this->dirty as $ip => $state ) {
			if ( $this->repository->save( $state ) ) {
				unset( $this->dirty[ $ip ] );
			}
		}
	}

	private function evidenceFromEvent( string $event, array $meta ) :?PolicyEvidence {
		if ( $event === 'request_policy_decision' ) {
			return null;
		}

		$map = [
			'bottrack_loginfailed'   => PolicyEvidence::TYPE_AUTH_FAILURE,
			'bottrack_logininvalid'  => PolicyEvidence::TYPE_AUTH_INVALID_USER,
			'bottrack_xmlrpc'        => PolicyEvidence::TYPE_XMLRPC,
			'block_xml'              => PolicyEvidence::TYPE_XMLRPC,
			'block_author_fishing'   => PolicyEvidence::TYPE_USERNAME_FISHING,
			'request_limit_exceeded' => PolicyEvidence::TYPE_RATE_LIMIT,
			'ip_offense'             => PolicyEvidence::TYPE_IP_OFFENSE,
			'ip_blocked'             => PolicyEvidence::TYPE_IP_BLOCKED,
		];

		if ( $event === 'firewall_block' ) {
			$category = (string)( $meta[ 'audit_params' ][ 'scan' ] ?? '' );
			$isCritical = \in_array( $category, RequestPolicyEvaluator::CRITICAL_FIREWALL_CATEGORIES, true );
			return new PolicyEvidence( [
				'detector'       => PolicyEvidence::DETECTOR_FIREWALL,
				'type'           => $isCritical ? PolicyEvidence::TYPE_FIREWALL_CRITICAL : PolicyEvidence::TYPE_FIREWALL_NOISY,
				'severity'       => $isCritical ? PolicyEvidence::SEVERITY_CRITICAL : PolicyEvidence::SEVERITY_NOISY,
				'condition_meta' => $meta[ 'audit_params' ] ?? [],
			] );
		}

		$type = $map[ $event ] ?? '';
		return empty( $type ) ? null : new PolicyEvidence( [
			'detector' => PolicyEvidence::DETECTOR_EVENT,
			'type'     => $type,
			'severity' => \in_array( $type, [
				PolicyEvidence::TYPE_USERNAME_FISHING,
				PolicyEvidence::TYPE_RATE_LIMIT,
				PolicyEvidence::TYPE_IP_BLOCKED,
			], true ) ? PolicyEvidence::SEVERITY_CRITICAL : PolicyEvidence::SEVERITY_SIGNAL,
		] );
	}

	private function applyEvidenceRisk( PolicyState $state, PolicyEvidence $evidence ) :void {
		if ( $evidence->severity === PolicyEvidence::SEVERITY_CRITICAL ) {
			$state->risk_band = PolicyState::BAND_HOSTILE;
			$state->risk_score = \max( $state->risk_score, 90 );
		}
		elseif ( $state->risk_band !== PolicyState::BAND_HOSTILE && $evidence->severity !== PolicyEvidence::SEVERITY_INFO ) {
			$state->risk_band = PolicyState::BAND_SUSPICIOUS;
			$state->risk_score = \max( $state->risk_score, $evidence->severity === PolicyEvidence::SEVERITY_NOISY ? 50 : 30 );
		}
	}
}
