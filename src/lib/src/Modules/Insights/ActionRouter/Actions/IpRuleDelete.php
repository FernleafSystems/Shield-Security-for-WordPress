<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class IpRuleDelete extends IpsBase {

	public const SLUG = 'ip_rule_delete';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$ID = (int)Services::Request()->post( 'rid', -1 );

		if ( $ID < 0 ) {
			$success = false;
			$msg = __( 'Invalid entry selected', 'wp-simple-firewall' );
		}
		else {
			$success = $mod->getDbH_IPRules()
						   ->getQueryDeleter()
						   ->deleteById( $ID );
			$msg = $success ? __( 'IP Rule deleted', 'wp-simple-firewall' ) : __( "IP Rule couldn't be deleted from the list", 'wp-simple-firewall' );
		}

		$this->response()->action_response_data = [
			'success'     => $success,
			'page_reload' => $success,
			'message'     => $msg,
		];
	}
}