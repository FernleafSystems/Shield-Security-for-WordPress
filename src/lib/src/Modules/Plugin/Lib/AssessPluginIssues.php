<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies\Monolog;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;

class AssessPluginIssues {

	use ModConsumer;

	public function run() :array {
		$issues = array_filter( [
			$this->ipStatus(),
			$this->monolog(),
			$this->dbPrechecks(),
			$this->rulesEngine(),
		] );

		$normalised = [];
		foreach ( $issues as $issue ) {
			if ( empty( $issue[ 'id' ] ) ) {
				error_log( sprintf( 'Invalid issue defined without ID: %s', var_export( $issue, true ) ) );
				continue;
			}
			if ( isset( $normalised[ $issue[ 'id' ] ] ) ) {
				error_log( sprintf( 'Duplicate issue ID: %s', var_export( $issue, true ) ) );
				continue;
			}
			$normalised[ $issue[ 'id' ] ] = \array_merge( [
				'type'      => 'warning',
				'text'      => 'no text provided',
				'locations' => [],
				'flags'     => [],
			], $issue );
		}
		return $normalised;
	}

	private function rulesEngine() :?array {
		if ( !$this->con()->rules->isRulesEngineReady() || !$this->con()->rules->processComplete ) {
			$issue = [
				'id'        => 'rules_engine_not_running',
				'type'      => 'danger',
				'text'      => [
					__( "Shield core Rules Engine isn't running.", 'wp-simple-firewall' ),
					__( "If this message still appears after refreshing this page, please reinstall the plugin.", 'wp-simple-firewall' ),
				],
				'locations' => [
					'shield_admin_top_page',
				]
			];
		}
		return $issue ?? null;
	}

	private function dbPrechecks() :?array {
		$issue = null;

		$dbPreChecks = $this->con()->prechecks[ 'dbs' ];
		if ( count( $dbPreChecks ) !== count( array_filter( $dbPreChecks ) ) ) {
			$issue = [
				'id'        => 'db_prechecks_fail',
				'type'      => 'danger',
				'text'      => [
					sprintf(
						'%s %s',
						__( "The Shield database needs to be repaired as certain features won't be available without a valid database.", 'wp-simple-firewall' ),
						sprintf( '<a href="%s" data-notice_action="auto_db_repair" class="shield_admin_notice_action text-white">%s</a>',
							'#',
							__( 'Run Database Repair', 'wp-simple-firewall' )
						)
					)
				],
				'locations' => [
					'shield_admin_top_page',
				]
			];
		}
		return $issue;
	}

	private function monolog() :?array {
		try {
			( new Monolog() )->assess();
			$issue = null;
		}
		catch ( \Exception $e ) {
			$issue = [
				'id'        => 'conflict_monolog',
				'type'      => 'warning',
				'text'      => [
					__( 'You have a PHP library conflict with the Monolog library. Likely another plugin is using an incompatible version of the library.', 'wp-simple-firewall' ),
					$e->getMessage(),
				],
				'locations' => [
					'shield_admin_top_page',
				],
				'flags'     => [
					'conflict',
				]
			];
		}
		return $issue;
	}

	private function ipStatus() :?array {
		$con = $this->con();
		$ip = $con->this_req->ip;

		$issue = null;

		$ipStatus = new IpRuleStatus( $ip );
		if ( $ipStatus->isBypass() ) {
			$issue = [
				'id'        => 'self_ip_bypass',
				'type'      => 'warning',
				'text'      => [
					sprintf( __( 'Something not working? No security features apply to you because your IP (%s) is whitelisted.', 'wp-simple-firewall' ),
						sprintf( '<a href="%s" class="render_ip_analysis" data-ip="%s">%s</a>', $con->plugin_urls->ipAnalysis( $ip ), $ip, $ip ) )
				],
				'locations' => [
					'shield_admin_top_page',
				]
			];
		}
		elseif ( $ipStatus->isBlocked() ) {
			$issue = [
				'id'        => 'self_ip_blocked',
				'type'      => 'danger',
				'text'      => [
					sprintf( __( 'It looks like your IP (%s) is currently blocked.', 'wp-simple-firewall' ),
						sprintf( '<a href="%s" class="render_ip_analysis" data-ip="%s">%s</a>', $con->plugin_urls->ipAnalysis( $ip ), $ip, $ip ) )
				],
				'locations' => [
					'shield_admin_top_page',
				]
			];
		}

		return $issue;
	}
}
