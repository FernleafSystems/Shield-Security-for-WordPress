<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\{
	IpRuleRecord,
	LoadIpRules
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\DeleteRule;

class IpRuleDelete extends BaseAction {

	public const SLUG = 'ip_rule_delete';

	protected function exec() {
		$ID = (int)( $this->action_data[ 'rid' ] ?? -1 );

		if ( $ID < 0 ) {
			$success = false;
			$msg = __( 'Invalid entry selected', 'wp-simple-firewall' );
		}
		else {
			$record = $this->loadIpRule( $ID );
			$success = !empty( $record ) && ( new DeleteRule() )->byRecord( $record );
			$msg = $success ? __( 'IP Rule deleted', 'wp-simple-firewall' ) : __( "IP Rule couldn't be deleted from the list", 'wp-simple-firewall' );
		}

		$this->response()->setPayload( [
			'page_reload' => false,
			'message'     => $msg,
		] )->setPayloadSuccess( $success );
	}

	private function loadIpRule( int $ID ) :?IpRuleRecord {
		$loader = new LoadIpRules();
		$loader->wheres = [ \sprintf( '`ir`.`id`=%d', $ID ) ];
		$loader->limit = 1;

		$record = \current( $loader->select() );
		return $record instanceof IpRuleRecord ? $record : null;
	}
}
