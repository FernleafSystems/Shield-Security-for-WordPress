<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpPolicyState\LoadPolicyStateRecords;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpPolicyState\Ops as PolicyStateDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class PolicyStateRepository {

	use PluginControllerConsumer;

	private const DEFAULT_TTL = DAY_IN_SECONDS*2;

	/**
	 * @var array<string, PolicyState>
	 */
	private array $cache = [];

	public function forIp( string $ip ) :PolicyState {
		$ip = \trim( $ip );
		if ( !isset( $this->cache[ $ip ] ) ) {
			$this->cache[ $ip ] = $this->load( $ip );
		}
		return $this->cache[ $ip ];
	}

	public function save( PolicyState $state ) :bool {
		if ( !$state->dirty ) {
			return true;
		}
		if ( !self::con()->db_con->ip_policy_state->isReady() ) {
			return false;
		}

		try {
			if ( !$this->isValidIp( $state->ip ) ) {
				return false;
			}
			if ( $state->ip !== '' ) {
				$state->ip_ref = ( new IPRecords() )->loadIP( $state->ip )->id;
			}
			if ( $state->ip_ref <= 0 ) {
				return false;
			}
			$metaRecord = self::con()->db_con->ip_policy_state->getRecord();
			$meta = \method_exists( $metaRecord, 'arrayDataWrap' )
				? ( $metaRecord->arrayDataWrap( $state->meta ) ?? '' )
				: '';
			$state->expires_at = \max( $state->expires_at, Services::Request()->ts() + self::DEFAULT_TTL );
			$data = [
				'ip_ref'           => $state->ip_ref,
				'risk_band'        => $state->risk_band,
				'risk_score'       => $state->risk_score,
				'last_evidence_at' => $state->last_evidence_at,
				'last_decision_at' => $state->last_decision_at,
				'expires_at'       => $state->expires_at,
				'meta'             => $meta,
				'updated_at'       => Services::Request()->ts(),
			];

			if ( !empty( $state->record_id ) ) {
				$success = self::con()
					->db_con
					->ip_policy_state
					->getQueryUpdater()
					->updateById( $state->record_id, $data );
			}
			else {
				/** @var PolicyStateDB\Record $record */
				$record = self::con()
					->db_con
					->ip_policy_state
					->getRecord()
					->applyFromArray( $data );
				$success = self::con()
					->db_con
					->ip_policy_state
					->getQueryInserter()
					->insert( $record );
				$state->record_id = (int)$record->id;
			}
		}
		catch ( \Exception $e ) {
			$success = false;
		}

		if ( $success ) {
			$state->dirty = false;
		}
		return $success;
	}

	private function load( string $ip ) :PolicyState {
		if ( !$this->isValidIp( $ip ) ) {
			return $this->defaultState( $ip );
		}

		$now = Services::Request()->ts();
		try {
			$record = ( new LoadPolicyStateRecords() )
				->setIP( $ip )
				->loadRecord();
			$state = new PolicyState( [
				'record_id'        => (int)$record->id,
				'ip'               => $ip,
				'ip_ref'           => $record->ip_ref,
				'risk_band'        => $record->risk_band,
				'risk_score'       => $record->risk_score,
				'last_evidence_at' => $record->last_evidence_at,
				'last_decision_at' => $record->last_decision_at,
				'expires_at'       => $record->expires_at,
				'meta'             => $record->meta,
			] );

			if ( $state->expires_at > 0 && $state->expires_at < $now ) {
				$state = $this->defaultState( $ip, $state->record_id, $state->ip_ref );
				$state->dirty = true;
			}
		}
		catch ( \Exception $e ) {
			$state = $this->defaultState( $ip );
		}

		return $state;
	}

	private function defaultState( string $ip, ?int $recordID = null, int $ipRef = 0 ) :PolicyState {
		return new PolicyState( [
			'record_id'  => $recordID,
			'ip'         => $ip,
			'ip_ref'     => $ipRef,
			'risk_band'  => PolicyState::BAND_NORMAL,
			'risk_score' => 0,
			'expires_at' => Services::Request()->ts() + self::DEFAULT_TTL,
			'meta'       => [],
		] );
	}

	private function isValidIp( string $ip ) :bool {
		return $ip !== '' && \filter_var( $ip, \FILTER_VALIDATE_IP ) !== false;
	}
}
