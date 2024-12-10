<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules;
use IPLib\Factory;

class IpRuleAddSubmit extends BaseAction {

	public const SLUG = 'ip_rule_add_submit';

	protected function exec() {
		$con = self::con();
		$dbh = $con->db_con->ip_rules;
		$form = $this->action_data[ 'form_data' ];
		try {
			if ( empty( $form ) || !\is_array( $form ) ) {
				throw new \Exception( 'No data. Please retry' );
			}
			$label = \trim( $form[ 'label' ] ?? '' );
			if ( !empty( $label ) && \preg_match( '#[^a-z\d\s_-]#i', $label ) ) {
				throw new \Exception( 'The label must be alphanumeric with no special characters' );
			}
			if ( empty( $form[ 'type' ] ) ) {
				throw new \Exception( 'Please select one of the IP Rule Types - Block or Bypass' );
			}
			if ( ( $form[ 'confirm' ] ?? '' ) !== 'Y' ) {
				throw new \Exception( 'Please check the box to confirm this action' );
			}
			if ( empty( $form[ 'ip' ] ) ) {
				throw new \Exception( 'Please provide an IP Address' );
			}

			$formIP = \preg_replace( '#[^a-f\d:./]#i', '', $form[ 'ip' ] );
			$range = Factory::parseRangeString( $formIP );

			// You can't manually block your own IP if your IP isn't whitelisted.
			if ( $form[ 'type' ] === $dbh::T_MANUAL_BLOCK
				 && !empty( $range )
				 && !( new IpRules\IpRuleStatus( $con->this_req->ip ) )->isBypass()
				 && Factory::parseAddressString( $con->this_req->ip )->matches( $range ) ) {
				throw new \Exception( "Manually blocking your own IP address isn't supported." );
			}

			$ipAdder = ( new IpRules\AddRule() )->setIP( $formIP );
			switch ( $form[ 'type' ] ) {
				case $dbh::T_MANUAL_BYPASS:
					$ipAdder->toManualWhitelist( $label );
					break;

				case $dbh::T_MANUAL_BLOCK:
					$ipAdder->toManualBlacklist( $label );
					break;

				default:
					throw new \Exception( 'Please select one of the IP Rule Types - Block or Bypass' );
			}

			$msg = __( 'IP address added successfully', 'wp-simple-firewall' );
			$success = true;
		}
		catch ( \Exception $e ) {
			$success = false;
			$msg = $e->getMessage();
		}

		$this->response()->action_response_data = [
			'success'     => $success,
			'page_reload' => false,
			'message'     => $msg,
		];
	}
}