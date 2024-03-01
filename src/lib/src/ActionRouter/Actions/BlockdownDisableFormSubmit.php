<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteLockdown\SiteBlockdownCfg;
use FernleafSystems\Wordpress\Services\Services;

class BlockdownDisableFormSubmit extends BaseAction {

	public const SLUG = 'blockdown_disable_form_submit';

	protected function exec() {
		$con = self::con();
		try {
			$cfg = ( new SiteBlockdownCfg() )->applyFromArray( $con->comps->opts_lookup->getBlockdownCfg() );

			if ( !$cfg->isLockdownActive() ) {
				throw new \Exception( 'Invalid request - lockdown is not active.' );
			}

			$cfg->disabled_at = Services::Request()->ts();
			$cfg->exclusions = [];

			if ( $cfg->whitelist_me ) {
				$status = new IpRules\IpRuleStatus( $cfg->whitelist_me );
				if ( $status->isBypass() ) {
					$ipRules = $status->getRulesForBypass();
					foreach ( $ipRules as $ipRule ) {
						if ( !$ipRule->is_range && $ipRule->ip === $cfg->whitelist_me ) {
							( new IpRules\DeleteRule() )->byRecord( $ipRule );
						}
					}
				}
			}
			$cfg->whitelist_me = '';
			$con->opts->optSet( 'blockdown_cfg', $cfg->getRawData() );
			$con->fireEvent( 'site_blockdown_ended', [
				'audit_params' => [ 'user_login' => Services::WpUsers()->getCurrentWpUsername() ]
			] );

			$msg = __( 'Site lock down has been lifted!', 'wp-simple-firewall' );
			$success = true;
		}
		catch ( \Exception $e ) {
			$success = false;
			$msg = $e->getMessage();
		}

		$this->response()->action_response_data = [
			'success'     => $success,
			'page_reload' => true,
			'message'     => $msg,
		];
	}
}