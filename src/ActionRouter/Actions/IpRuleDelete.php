<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class IpRuleDelete extends BaseAction {

	public const SLUG = 'ip_rule_delete';

	protected function exec() {
		$ID = (int)$this->action_data[ 'rid' ] ?? -1;

		if ( $ID < 0 ) {
			$success = false;
			$msg = __( 'Invalid entry selected', 'wp-simple-firewall' );
		}
		else {
			$success = self::con()->db_con->ip_rules->getQueryDeleter()->deleteById( $ID );
			$msg = $success ? __( 'IP Rule deleted', 'wp-simple-firewall' ) : __( "IP Rule couldn't be deleted from the list", 'wp-simple-firewall' );
		}

		$this->response()->action_response_data = [
			'success'     => $success,
			'page_reload' => false,
			'message'     => $msg,
		];
	}
}