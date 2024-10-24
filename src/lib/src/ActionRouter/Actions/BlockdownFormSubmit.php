<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\LoadIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteLockdown\SiteBlockdownCfg;
use FernleafSystems\Wordpress\Services\Services;

class BlockdownFormSubmit extends BaseAction {

	public const SLUG = 'blockdown_form_submit';

	protected function exec() {
		$con = self::con();

		$form = $this->action_data[ 'form_data' ];
		try {
			if ( !$con->caps->canSiteBlockdown() ) {
				throw new \Exception( 'Please upgrade your ShieldPRO plan to make use of this feature.' );
			}

			if ( empty( $form ) || !\is_array( $form ) ) {
				throw new \Exception( 'Please complete the form.' );
			}

			$cfg = ( new SiteBlockdownCfg() )->applyFromArray( $con->comps->opts_lookup->getBlockdownCfg() );
			if ( $cfg->isLockdownActive() ) {
				throw new \Exception( 'Invalid request - lockdown is already active.' );
			}

			$confirm = $form[ 'confirm' ] ?? [];
			if ( !empty( \array_diff( [ 'consequences', 'authority', 'access', 'cache' ], $confirm ) ) ) {
				throw new \Exception( 'Please check all confirmation boxes.' );
			}

			$whitelistMe = ( $form[ 'whitelist_me' ] ?? 'N' ) === 'Y';
			$alreadyWhitelisted = ( new IpRules\IpRuleStatus( $con->this_req->ip ) )->isBypass();
			if ( $whitelistMe && !$alreadyWhitelisted ) {
				( new IpRules\AddRule() )
					->setIP( $con->this_req->ip )
					->toManualWhitelist( 'Whitelist for Site Lockdown' );
			}

			$ruleLoader = new LoadIpRules();
			$ruleLoader->wheres = [
				sprintf( "`ir`.`type`='%s'", $con->db_con->ip_rules::T_MANUAL_BYPASS )
			];
			if ( $ruleLoader->countAll() === 0 ) {
				throw new \Exception( 'There are no whitelisted IPs for exclusion.' );
			}

			$cfg->activated_at = Services::Request()->ts();
			$cfg->activated_by = Services::WpUsers()->getCurrentWpUsername();
			$cfg->exclusions = $form[ 'exclusions' ] ?? [];
			$cfg->whitelist_me = ( $whitelistMe && !$alreadyWhitelisted ) ? $con->this_req->ip : '';

			$con->opts->optSet( 'blockdown_cfg', $cfg->getRawData() );

			self::con()->fireEvent( 'site_blockdown_started', [
				'audit_params' => [ 'user_login' => Services::WpUsers()->getCurrentWpUsername() ]
			] );

			$msg = __( 'Site has been locked down!', 'wp-simple-firewall' );
			$success = true;
		}
		catch ( \Exception $e ) {
			$success = false;
			$msg = $e->getMessage();
		}

		$this->response()->action_response_data = [
			'success'     => $success,
			'page_reload' => $success,
			'message'     => $msg,
		];
	}
}