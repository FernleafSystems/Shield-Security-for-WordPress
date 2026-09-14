<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	RuleBuilderAction,
	RulesManagerTableAction
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block\{
	BlockAuthorFishing,
	BlockFirewall
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Rules\RuleRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\{
	IpRulesCache,
	IpRuleStatus
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions\IsRequestStatus404,
	Conditions\MatchRequestPath,
	Enum\EnumLogic,
	Enum\EnumMatchTypes,
	Responses\DisplayBlockPage,
	Responses\FirewallBlock
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;

class CustomRulesTerminalFinalizationFixtureBuilder {

	private const RUNTIME_OPTION = 'shield_browser_fixture_custom_rules_terminal_finalization_runtime';

	private const OPTION_KEYS = [
		'block_send_email_address',
		'instant_alert_firewall_block',
		'instant_alerts_data',
		'license_activated_at',
		'license_data',
		'license_deactivated_at',
		'track_404',
		'visitor_address_source',
	];

	private const IP_MAP = [
		'custom-404'      => [ 'control' => '1.1.1.1', 'scenario' => '8.8.8.8' ],
		'custom-firewall' => [ 'control' => '1.0.0.1', 'scenario' => '9.9.9.9' ],
	];

	/**
	 * @return array{contract:array<string,mixed>,state:array<string,mixed>}
	 */
	public function seed( string $scenario, string $token ) :array {
		$this->assertScenario( $scenario );
		$this->assertToken( $token );
		RuntimeTestState::loginAsSecurityAdmin();
		RuntimeTestState::ensureDb( [ 'rules', 'ips', 'ip_rules', 'req_logs', 'activity_logs', 'activity_logs_meta' ] );

		$this->removeRuntime();
		\delete_option( self::RUNTIME_OPTION );
		$this->deleteTokenRows( $token );
		$this->deleteTokenRules( $token );

		$state = [
			'scenario'          => $scenario,
			'token'             => $token,
			'rule_id'           => 0,
			'rule_name'         => $this->ruleName( $token ),
			'control_path'      => '/shield-terminal-control-'.$token,
			'scenario_path'     => '/shield-terminal-'.( $scenario === 'custom-404' ? '404-' : 'firewall-' ).$token,
			'control_ip'        => self::IP_MAP[ $scenario ][ 'control' ],
			'scenario_ip'       => self::IP_MAP[ $scenario ][ 'scenario' ],
			'options_snapshot'  => RuntimeTestState::snapshotOptions( self::OPTION_KEYS ),
			'ip_rule_snapshots' => [],
			'phase'             => 'control',
			'baseline'          => [],
		];

		try {
			foreach ( [ $state[ 'control_ip' ], $state[ 'scenario_ip' ] ] as $ip ) {
				$state[ 'ip_rule_snapshots' ][ $ip ] = $this->snapshotExactIpRules( $ip );
				$this->clearExactIpRules( $ip );
				$this->assertNoInvalidatingIpState( $ip );
			}

			$capabilities = [ 'custom_security_rules' ];
			if ( $scenario === 'custom-firewall' ) {
				$capabilities[] = 'instant_alerts';
			}
			RuntimeTestState::applyPremiumCapabilities( $capabilities );
			$this->setFixtureOptions( $scenario, $token );
			$state[ 'rule_id' ] = $this->createRule( $state );
			if ( (bool)( new RuleRecords() )->byID( $state[ 'rule_id' ] )->is_active ) {
				throw new \RuntimeException( 'fixture_or_baseline_failure: seeded task rule is already active.' );
			}

			\update_option( self::RUNTIME_OPTION, [
				'token'         => $token,
				'target_paths'  => [ $state[ 'control_path' ], $state[ 'scenario_path' ] ],
				'mail_path'     => $scenario === 'custom-firewall' ? $state[ 'scenario_path' ] : '',
				'lifecycle'     => [],
				'mail_attempts' => [],
				'alert_results' => [],
			], false );
			$this->installRuntime();
			$state[ 'baseline' ] = $this->captureBaseline( $state[ 'control_ip' ] );

			return [ 'state' => $state, 'contract' => $this->contract( $state, false ) ];
		}
		catch ( \Throwable $throwable ) {
			$this->cleanup( $state );
			throw $throwable;
		}
	}

	/**
	 * @param array<string,mixed> $state
	 * @return array{contract:array<string,mixed>,state:array<string,mixed>}
	 */
	public function activate( array $state, string $scenario ) :array {
		$state = $this->normalise( $state );
		$this->assertScenario( $scenario );
		RuntimeTestState::loginAsSecurityAdmin();
		if ( $state[ 'scenario' ] !== $scenario || $state[ 'phase' ] !== 'control' || $state[ 'rule_id' ] < 1 ) {
			throw new \RuntimeException( 'Fixture is not seeded for this control.' );
		}
		$control = $this->inspect( $state, 'control' );
		if ( !( $control[ 'control_valid' ] ?? false ) ) {
			throw new \RuntimeException( 'fixture_or_baseline_failure: ordinary 404 control is invalid.' );
		}

		$state[ 'phase' ] = 'scenario';
		$state[ 'baseline' ] = $this->captureBaseline( $state[ 'scenario_ip' ] );
		$payload = ( new ActionProcessor() )->processAction( RulesManagerTableAction::SLUG, [
			'sub_action' => 'activate',
			'rids'       => [ $state[ 'rule_id' ] ],
		] )->payload();
		if ( !( $payload[ 'success' ] ?? false )
			 || !(bool)( new RuleRecords() )->byID( $state[ 'rule_id' ] )->is_active
		) {
			throw new \RuntimeException( 'Unable to activate exactly one terminal fixture rule.' );
		}

		return [ 'state' => $state, 'contract' => $this->contract( $state, true ) ];
	}

	/**
	 * @param array<string,mixed> $state
	 * @return array<string,mixed>
	 */
	public function inspect( array $state, string $phase ) :array {
		$state = $this->normalise( $state );
		if ( !\in_array( $phase, [ 'control', 'scenario' ], true ) || $state[ 'phase' ] !== $phase ) {
			throw new \RuntimeException( 'Inspect phase does not match the active fixture phase.' );
		}

		$path = $phase === 'control' ? $state[ 'control_path' ] : $state[ 'scenario_path' ];
		$ip = $phase === 'control' ? $state[ 'control_ip' ] : $state[ 'scenario_ip' ];
		$event = $phase === 'control' || $state[ 'scenario' ] === 'custom-404' ? 'bottrack_404' : 'firewall_block';
		$requests = $this->newRequests( $state[ 'baseline' ], $path );
		$activities = $this->newActivities( $state[ 'baseline' ], $event );
		$runtime = $this->runtimeData();
		$alertResults = \array_slice(
			$runtime[ 'alert_results' ],
			(int)( $state[ 'baseline' ][ 'alert_result_count' ] ?? 0 )
		);
		$lifecycle = \array_slice( $runtime[ 'lifecycle' ], (int)( $state[ 'baseline' ][ 'lifecycle_count' ] ?? 0 ) );
		$mailAttempts = \array_slice( $runtime[ 'mail_attempts' ], (int)( $state[ 'baseline' ][ 'mail_count' ] ?? 0 ) );
		$linked = $this->linkedCount( $activities, $requests );
		$offense = $this->offenseEvidence( $ip, $state[ 'baseline' ] );
		$ruleActive = $this->ruleActive( $state[ 'rule_id' ] );
		$expectedHooks = [
			RuntimeTestState::controller()->prefix( 'pre_plugin_shutdown' ),
			RuntimeTestState::controller()->prefix( 'plugin_shutdown' ),
		];
		$hooks = \array_map( static fn( array $item ) :string => (string)( $item[ 'hook' ] ?? '' ), $lifecycle );
		$lifecycleValid = \array_values( $hooks ) === $expectedHooks;
		$requestRuntimeValid = \count( $lifecycle ) === 2;
		foreach ( $lifecycle as $observation ) {
			$requestRuntimeValid = $requestRuntimeValid
				&& ( $observation[ 'path' ] ?? '' ) === $path
				&& ( $observation[ 'ip' ] ?? '' ) === $ip
				&& ( $observation[ 'ip_is_public' ] ?? false ) === true
				&& ( $observation[ 'bypasses' ] ?? true ) === false;
		}
		$persistedRequestValid = \count( $requests ) === 1
			&& ( $requests[ 0 ][ 'ip' ] ?? '' ) === $ip;
		$requestValid = $persistedRequestValid
			&& (int)( $requests[ 0 ][ 'code' ] ?? 0 ) === ( $phase === 'control' ? 404 : 503 );
		$alertResultContractMatches = $this->alertResultsValid( $state, $phase, $alertResults );
		$mailAttemptContractMatches = $this->mailAttemptsValid( $state, $phase, $mailAttempts );
		$auditValid = $this->auditValid( $state, $phase, $activities );
		$outcomeMatches = \count( $activities ) === 1
			&& $linked === 1
			&& $requestValid
			&& $offense[ 'delta' ] === 1
			&& $alertResultContractMatches
			&& $mailAttemptContractMatches
			&& $auditValid;
		$controlValid = $phase === 'control'
			&& !$ruleActive
			&& $requestRuntimeValid
			&& $lifecycleValid
			&& $outcomeMatches;

		return [
			'scenario'                    => $state[ 'scenario' ],
			'phase'                       => $phase,
			'expected_path'               => $path,
			'expected_ip'                 => $ip,
			'primary_event'               => $event,
			'requests'                    => $requests,
			'primary_activities'          => $activities,
			'linked_primary_count'        => $linked,
			'alert_result_events'         => $alertResults,
			'alert_result_contract_matches' => $alertResultContractMatches,
			'offense'                     => $offense,
			'lifecycle'                   => $lifecycle,
			'mail_attempts'               => $mailAttempts,
			'task_rule_active'            => $ruleActive,
			'audit_contract_matches'      => $auditValid,
			'lifecycle_sequence_valid'    => $lifecycleValid,
			'runtime_request_valid'       => $requestRuntimeValid,
			'control_valid'               => $controlValid,
			'outcome_matches'             => $outcomeMatches,
			'fixture_or_baseline_failure' => $phase === 'control'
				? !$controlValid
				: !$requestRuntimeValid || !$persistedRequestValid || !$ruleActive,
			'lifecycle_failure'           => $requestRuntimeValid && !$lifecycleValid,
		];
	}

	/**
	 * @param array<string,mixed> $state
	 */
	public function cleanup( array $state ) :void {
		$state = $this->normalise( $state );
		if ( $state[ 'token' ] === '' ) {
			\delete_option( self::RUNTIME_OPTION );
			$this->removeRuntime();
			return;
		}

		RuntimeTestState::ensureDb( [ 'rules', 'ips', 'ip_rules', 'req_logs', 'activity_logs', 'activity_logs_meta' ] );
		$this->deleteTokenRows( $state[ 'token' ] );
		if ( $state[ 'rule_id' ] > 0 ) {
			RuntimeTestState::controller()->db_con->rules->getQueryDeleter()->deleteById( $state[ 'rule_id' ] );
		}
		$this->deleteTokenRules( $state[ 'token' ] );
		RuntimeTestState::controller()->rules->buildAndStore();

		foreach ( [ $state[ 'control_ip' ], $state[ 'scenario_ip' ] ] as $ip ) {
			if ( $ip === '' ) {
				continue;
			}
			$this->clearExactIpRules( $ip );
			foreach ( $state[ 'ip_rule_snapshots' ][ $ip ] ?? [] as $snapshot ) {
				$record = RuntimeTestState::controller()->db_con->ip_rules->getRecord()->applyFromArray( $snapshot );
				if ( !RuntimeTestState::controller()->db_con->ip_rules->getQueryInserter()->insert( $record ) ) {
					throw new \RuntimeException( 'Failed to restore an IP-rule fixture snapshot.' );
				}
			}
			$this->clearIpCaches( $ip );
		}

		RuntimeTestState::restoreOptions( $state[ 'options_snapshot' ] );
		\delete_option( self::RUNTIME_OPTION );
		$this->removeRuntime();
	}

	/** @param array<string,mixed> $state */
	private function createRule( array $state ) :int {
		$form = [
			'edit_rule_id'                  => -1,
			'rule_name'                     => $state[ 'rule_name' ],
			'rule_description'              => 'Terminal finalization fixture '.$state[ 'token' ],
			'conditions_logic'              => EnumLogic::LOGIC_AND,
			'condition_1'                   => $state[ 'scenario' ] === 'custom-404' ? IsRequestStatus404::Slug() : MatchRequestPath::Slug(),
			'checkbox_auto_include_bypass'  => 'Y',
			'checkbox_accept_rules_warning' => 'Y',
		];
		if ( $state[ 'scenario' ] === 'custom-404' ) {
			$form[ 'response_1' ] = DisplayBlockPage::SLUG;
			$form[ 'response_1_param_block_page_slug' ] = BlockAuthorFishing::SLUG;
		}
		else {
			$form[ 'condition_1_param_match_type' ] = EnumMatchTypes::MATCH_TYPE_EQUALS;
			$form[ 'condition_1_param_match_path' ] = $state[ 'scenario_path' ];
			$form[ 'response_1' ] = FirewallBlock::SLUG;
		}

		$payload = ( new ActionProcessor() )->processAction( RuleBuilderAction::SLUG, [
			'builder_action' => 'create_rule',
			'rule_form'      => $form,
		] )->payload();
		$id = (int)( $payload[ 'edit_rule_id' ] ?? 0 );
		if ( !( $payload[ 'success' ] ?? false ) || $id < 1 ) {
			throw new \RuntimeException( 'Unable to create terminal fixture rule.' );
		}
		return $id;
	}

	private function setFixtureOptions( string $scenario, string $token ) :void {
		$opts = RuntimeTestState::controller()->opts;
		$opts->optSet( 'visitor_address_source', 'HTTP_X_FORWARDED_FOR' )
			 ->optSet( 'track_404', 'transgression-single' );
		if ( $scenario === 'custom-firewall' ) {
			$opts->optSet( 'instant_alert_firewall_block', 'email' )
				 ->optSet( 'block_send_email_address', 'terminal-fixture-'.$token.'@example.test' )
				 ->optSet( 'instant_alerts_data', [] );
		}
		$opts->store();
		RuntimeTestState::resetOptionsRuntimeCache();
	}

	/** @param array<string,mixed> $state */
	private function contract( array $state, bool $active ) :array {
		return [
			'scenario' => $state[ 'scenario' ],
			'token'    => $state[ 'token' ],
			'rule'     => [
				'id'                  => $state[ 'rule_id' ],
				'name'                => $state[ 'rule_name' ],
				'active'              => $active,
				'response_class'      => $state[ 'scenario' ] === 'custom-404' ? DisplayBlockPage::class : FirewallBlock::class,
				'expected_render_slug' => $state[ 'scenario' ] === 'custom-404' ? BlockAuthorFishing::SLUG : BlockFirewall::SLUG,
			],
			'control'  => [
				'url'             => \home_url( $state[ 'control_path' ] ),
				'path'            => $state[ 'control_path' ],
				'ip'              => $state[ 'control_ip' ],
				'expected_status' => 404,
			],
			'scenario_request' => [
				'url'             => \home_url( $state[ 'scenario_path' ] ),
				'path'            => $state[ 'scenario_path' ],
				'ip'              => $state[ 'scenario_ip' ],
				'expected_status' => 503,
			],
		];
	}

	private function expectedAlertCount( array $state, string $phase ) :int {
		return $phase === 'scenario' && $state[ 'scenario' ] === 'custom-firewall' ? 1 : 0;
	}

	private function alertResultsValid( array $state, string $phase, array $events ) :bool {
		$expected = $this->expectedAlertCount( $state, $phase );
		if ( \count( $events ) !== $expected ) {
			return false;
		}
		if ( $expected === 0 ) {
			return true;
		}
		$to = 'terminal-fixture-'.$state[ 'token' ].'@example.test';
		foreach ( $events as $event ) {
			if ( \array_keys( $event ) !== [ 'event', 'to', 'level' ]
					|| ( $event[ 'event' ] ?? '' ) !== 'fw_email_success'
					|| ( $event[ 'to' ] ?? '' ) !== $to
					|| ( $event[ 'level' ] ?? '' ) !== 'info' ) {
				return false;
			}
		}
		return true;
	}

	private function mailAttemptsValid( array $state, string $phase, array $attempts ) :bool {
		$expected = $this->expectedAlertCount( $state, $phase );
		if ( \count( $attempts ) !== $expected ) {
			return false;
		}
		if ( $expected === 0 ) {
			return true;
		}
		$to = 'terminal-fixture-'.$state[ 'token' ].'@example.test';
		$recipient = $attempts[ 0 ][ 'to' ] ?? null;
		if ( \is_string( $recipient ) ) {
			return $recipient === $to;
		}
		return \is_array( $recipient ) && \array_values( $recipient ) === [ $to ];
	}

	/** @return array<string,int> */
	private function captureBaseline( string $ip ) :array {
		global $wpdb;
		$con = RuntimeTestState::controller();
		$runtime = $this->runtimeData();
		return [
			'activity_id'     => (int)$wpdb->get_var( 'SELECT COALESCE(MAX(id),0) FROM `'.$con->db_con->activity_logs->getTable().'`' ),
			'request_id'      => (int)$wpdb->get_var( 'SELECT COALESCE(MAX(id),0) FROM `'.$con->db_con->req_logs->getTable().'`' ),
			'lifecycle_count' => \count( $runtime[ 'lifecycle' ] ),
			'mail_count'      => \count( $runtime[ 'mail_attempts' ] ),
			'alert_result_count' => \count( $runtime[ 'alert_results' ] ),
			'offenses'        => $this->offenseCount( $ip ),
		];
	}

	/** @param array<string,mixed> $baseline @return list<array<string,mixed>> */
	private function newRequests( array $baseline, string $path ) :array {
		global $wpdb;
		$con = RuntimeTestState::controller();
		$records = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM `'.$con->db_con->req_logs->getTable().'` WHERE id > %d AND path = %s ORDER BY id',
			(int)( $baseline[ 'request_id' ] ?? 0 ),
			$path
		) );
		$evidence = [];
		foreach ( \is_array( $records ) ? $records : [] as $record ) {
			$ipRecord = $con->db_con->ips->getQuerySelector()->byId( (int)$record->ip_ref );
			$evidence[] = [
				'id'      => (int)$record->id,
				'req_id'  => (string)$record->req_id,
				'ip'      => \is_object( $ipRecord ) ? (string)$ipRecord->ip : '',
				'path'    => (string)$record->path,
				'code'    => (int)$record->code,
				'offense' => (bool)$record->offense,
			];
		}
		return $evidence;
	}

	/** @param array<string,mixed> $baseline @return list<array<string,mixed>> */
	private function newActivities( array $baseline, string $event ) :array {
		global $wpdb;
		$con = RuntimeTestState::controller();
		$records = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM `'.$con->db_con->activity_logs->getTable().'` WHERE id > %d AND event_slug = %s ORDER BY id',
			(int)( $baseline[ 'activity_id' ] ?? 0 ),
			$event
		) );
		$evidence = [];
		foreach ( \is_array( $records ) ? $records : [] as $record ) {
			$evidence[] = [
				'id'         => (int)$record->id,
				'event_slug' => (string)$record->event_slug,
				'req_ref'    => (int)$record->req_ref,
				'meta'       => $this->activityMeta( (int)$record->id ),
			];
		}
		return $evidence;
	}

	/** @return array<string,string> */
	private function activityMeta( int $logId ) :array {
		global $wpdb;
		$table = RuntimeTestState::controller()->db_con->activity_logs_meta->getTable();
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT meta_key, meta_value FROM `'.$table.'` WHERE log_ref = %d', $logId ) );
		$meta = [];
		foreach ( \is_array( $rows ) ? $rows : [] as $row ) {
			$meta[ (string)$row->meta_key ] = (string)$row->meta_value;
		}
		return $meta;
	}

	private function linkedCount( array $activities, array $requests ) :int {
		$requestIds = \array_map( static fn( array $request ) :int => (int)$request[ 'id' ], $requests );
		return \count( \array_filter(
			$activities,
			static fn( array $activity ) :bool => \in_array( (int)$activity[ 'req_ref' ], $requestIds, true )
		) );
	}

	/** @param array<string,mixed> $baseline @return array<string,mixed> */
	private function offenseEvidence( string $ip, array $baseline ) :array {
		$count = $this->offenseCount( $ip );
		return [
			'ip'        => $ip,
			'is_public' => \filter_var( $ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE ) !== false,
			'offenses'  => $count,
			'delta'     => $count - (int)( $baseline[ 'offenses' ] ?? 0 ),
			'rules'     => $this->currentAutoRules( $ip ),
		];
	}

	private function offenseCount( string $ip ) :int {
		return \array_sum( \array_column( $this->currentAutoRules( $ip ), 'offenses' ) );
	}

	/** @return list<array<string,mixed>> */
	private function currentAutoRules( string $ip ) :array {
		$record = $this->ipRecord( $ip );
		if ( $record === null ) {
			return [];
		}
		$records = RuntimeTestState::controller()->db_con->ip_rules->getQuerySelector()
			->filterByIPRef( (int)$record->id )->queryWithResult();
		$rules = [];
		foreach ( \is_array( $records ) ? $records : [] as $rule ) {
			if ( (string)$rule->type === RuntimeTestState::controller()->db_con->ip_rules::T_AUTO_BLOCK ) {
				$rules[] = [ 'id' => (int)$rule->id, 'offenses' => (int)$rule->offenses, 'blocked_at' => (int)$rule->blocked_at ];
			}
		}
		return $rules;
	}

	private function auditValid( array $state, string $phase, array $activities ) :bool {
		if ( $phase !== 'scenario' || $state[ 'scenario' ] !== 'custom-firewall' ) {
			return true;
		}
		if ( \count( $activities ) !== 1 ) {
			return false;
		}
		$expected = [
			'name'  => $state[ 'rule_name' ],
			'term'  => $state[ 'scenario_path' ],
			'param' => 'path',
			'value' => $state[ 'scenario_path' ],
			'scan'  => 'custom_rule',
			'type'  => EnumMatchTypes::MATCH_TYPE_EQUALS,
		];
		return \array_intersect_key( $activities[ 0 ][ 'meta' ] ?? [], $expected ) == $expected;
	}

	/** @return list<array<string,mixed>> */
	private function snapshotExactIpRules( string $ip ) :array {
		$record = $this->ipRecord( $ip );
		if ( $record === null ) {
			return [];
		}
		$rules = RuntimeTestState::controller()->db_con->ip_rules->getQuerySelector()
			->filterByIPRef( (int)$record->id )->queryWithResult();
		return \array_values( \array_map( static fn( $rule ) :array => $rule->getRawData(), \is_array( $rules ) ? $rules : [] ) );
	}

	private function clearExactIpRules( string $ip ) :void {
		$record = $this->ipRecord( $ip );
		if ( $record !== null ) {
			$rules = RuntimeTestState::controller()->db_con->ip_rules->getQuerySelector()
				->filterByIPRef( (int)$record->id )->queryWithResult();
			foreach ( \is_array( $rules ) ? $rules : [] as $rule ) {
				RuntimeTestState::controller()->db_con->ip_rules->getQueryDeleter()->deleteById( (int)$rule->id );
			}
		}
		$this->clearIpCaches( $ip );
	}

	private function assertNoInvalidatingIpState( string $ip ) :void {
		$status = new IpRuleStatus( $ip );
		if ( $status->hasRules() || $status->isBypass() || $status->isBlocked() ) {
			throw new \RuntimeException( 'fixture_or_baseline_failure: an IP-rule state invalidates the public request.' );
		}
	}

	private function clearIpCaches( string $ip ) :void {
		IpRuleStatus::ClearStatusForIP( $ip );
		IpRulesCache::Delete( IpRulesCache::COLLECTION_RANGES, IpRulesCache::GROUP_COLLECTIONS );
		IpRulesCache::ResetGroup( IpRulesCache::GROUP_NO_RULES );
	}

	private function ipRecord( string $ip ) :?object {
		$record = RuntimeTestState::controller()->db_con->ips->getQuerySelector()
			->filterByIPHuman( $ip )->setNoOrderBy()->first();
		return \is_object( $record ) ? $record : null;
	}

	private function ruleActive( int $ruleId ) :bool {
		try {
			return $ruleId > 0 && (bool)( new RuleRecords() )->byID( $ruleId )->is_active;
		}
		catch ( \Exception $e ) {
			return false;
		}
	}

	private function deleteTokenRules( string $token ) :void {
		if ( $token === '' ) {
			return;
		}
		global $wpdb;
		$table = RuntimeTestState::controller()->db_con->rules->getTable();
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM `'.$table.'` WHERE name = %s', $this->ruleName( $token ) ) );
		foreach ( \is_array( $ids ) ? $ids : [] as $id ) {
			RuntimeTestState::controller()->db_con->rules->getQueryDeleter()->deleteById( (int)$id );
		}
	}

	private function deleteTokenRows( string $token ) :void {
		if ( $token === '' ) {
			return;
		}
		global $wpdb;
		$con = RuntimeTestState::controller();
		$reqTable = $con->db_con->req_logs->getTable();
		$activityTable = $con->db_con->activity_logs->getTable();
		$metaTable = $con->db_con->activity_logs_meta->getTable();
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM `'.$reqTable.'` WHERE path LIKE %s', '%'.$wpdb->esc_like( $token ).'%' ) );
		$ids = \array_values( \array_filter( \array_map( '\intval', \is_array( $ids ) ? $ids : [] ) ) );
		if ( $ids === [] ) {
			return;
		}
		$list = \implode( ',', $ids );
		$activityIds = $wpdb->get_col( 'SELECT id FROM `'.$activityTable.'` WHERE req_ref IN ('.$list.')' );
		$activityIds = \array_values( \array_filter( \array_map( '\intval', \is_array( $activityIds ) ? $activityIds : [] ) ) );
		if ( $activityIds !== [] ) {
			$activityList = \implode( ',', $activityIds );
			$wpdb->query( 'DELETE FROM `'.$metaTable.'` WHERE log_ref IN ('.$activityList.')' );
			$wpdb->query( 'DELETE FROM `'.$activityTable.'` WHERE id IN ('.$activityList.')' );
		}
		$wpdb->query( 'DELETE FROM `'.$reqTable.'` WHERE id IN ('.$list.')' );
	}

	/** @return array{lifecycle:list<array<string,mixed>>,mail_attempts:list<array<string,mixed>>,alert_results:list<array<string,mixed>>} */
	private function runtimeData() :array {
		$data = \get_option( self::RUNTIME_OPTION, [] );
		$data = \is_array( $data ) ? $data : [];
		return [
			'lifecycle'     => \is_array( $data[ 'lifecycle' ] ?? null ) ? \array_values( $data[ 'lifecycle' ] ) : [],
			'mail_attempts' => \is_array( $data[ 'mail_attempts' ] ?? null ) ? \array_values( $data[ 'mail_attempts' ] ) : [],
			'alert_results' => \is_array( $data[ 'alert_results' ] ?? null ) ? \array_values( $data[ 'alert_results' ] ) : [],
		];
	}

	private function runtimePath() :string {
		return \WP_CONTENT_DIR.'/mu-plugins/shield-terminal-finalization-runtime.php';
	}

	private function installRuntime() :void {
		$source = __DIR__.'/CustomRulesTerminalFinalizationRuntime.php';
		if ( !\is_file( $source ) || !\wp_mkdir_p( \dirname( $this->runtimePath() ) )
			 || \file_put_contents( $this->runtimePath(), (string)\file_get_contents( $source ) ) === false
		) {
			throw new \RuntimeException( 'Unable to install terminal fixture runtime helper.' );
		}
	}

	private function removeRuntime() :void {
		if ( \is_file( $this->runtimePath() ) && !\unlink( $this->runtimePath() ) ) {
			throw new \RuntimeException( 'Unable to remove terminal fixture runtime helper.' );
		}
	}

	private function ruleName( string $token ) :string {
		return 'terminal_fixture_'.\str_replace( '-', '_', $token );
	}

	private function assertScenario( string $scenario ) :void {
		if ( !\array_key_exists( $scenario, self::IP_MAP ) ) {
			throw new \RuntimeException( 'Unknown custom-rule terminal fixture scenario.' );
		}
	}

	private function assertToken( string $token ) :void {
		if ( \preg_match( '/^[a-z0-9][a-z0-9-]{7,63}$/', $token ) !== 1 ) {
			throw new \RuntimeException( 'Fixture token must be 8-64 lowercase alphanumeric/hyphen characters.' );
		}
	}

	/** @param array<string,mixed> $state @return array<string,mixed> */
	private function normalise( array $state ) :array {
		return [
			'scenario'          => (string)( $state[ 'scenario' ] ?? '' ),
			'token'             => (string)( $state[ 'token' ] ?? '' ),
			'rule_id'           => (int)( $state[ 'rule_id' ] ?? 0 ),
			'rule_name'         => (string)( $state[ 'rule_name' ] ?? '' ),
			'control_path'      => (string)( $state[ 'control_path' ] ?? '' ),
			'scenario_path'     => (string)( $state[ 'scenario_path' ] ?? '' ),
			'control_ip'        => (string)( $state[ 'control_ip' ] ?? '' ),
			'scenario_ip'       => (string)( $state[ 'scenario_ip' ] ?? '' ),
			'options_snapshot'  => \is_array( $state[ 'options_snapshot' ] ?? null ) ? $state[ 'options_snapshot' ] : [],
			'ip_rule_snapshots' => \is_array( $state[ 'ip_rule_snapshots' ] ?? null ) ? $state[ 'ip_rule_snapshots' ] : [],
			'phase'             => (string)( $state[ 'phase' ] ?? '' ),
			'baseline'          => \is_array( $state[ 'baseline' ] ?? null ) ? $state[ 'baseline' ] : [],
		];
	}
}
