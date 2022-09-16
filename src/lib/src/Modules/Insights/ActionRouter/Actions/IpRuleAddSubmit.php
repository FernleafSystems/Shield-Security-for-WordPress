<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Services\Services;
use IPLib\Factory;

class IpRuleAddSubmit extends IpsBase {

	const SLUG = 'ip_rule_add_submit';

	/**
	 * @inheritDoc
	 */
	protected function exec() {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$dbh = $mod->getDbH_IPRules();
		$form = Services::Request()->post( 'form_data' );
		try {
			if ( empty( $form ) || !is_array( $form ) ) {
				throw new \Exception( 'No data. Please retry' );
			}
			$label = trim( $form[ 'label' ] ?? '' );
			if ( !empty( $label ) && preg_match( '#[^a-z\d\s_-]#i', $label ) ) {
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

			$formIP = preg_replace( '#[^a-f\d:./]#i', '', $form[ 'ip' ] );
			$range = Factory::parseRangeString( $formIP );
			$iBypass = ( new IpRules\IpRuleStatus( $con->this_req->ip ) )
				->setMod( $mod )
				->isBypass();

			// You can't manually block your own IP if your IP isn't whitelisted.
			if ( $form[ 'type' ] === $dbh::T_MANUAL_BLOCK
				 && !empty( $range )
				 && !$iBypass
				 && Factory::parseAddressString( $con->this_req->ip )->matches( $range ) ) {
				throw new \Exception( "Manually blocking your own IP address isn't supported." );
			}

			$ipAdder = ( new IpRules\AddRule() )
				->setMod( $mod )
				->setIP( $formIP );
			switch ( $form[ 'type' ] ) {
				case $dbh::T_MANUAL_BYPASS:
					$IP = $ipAdder->toManualWhitelist( $label );
					break;

				case $dbh::T_MANUAL_BLOCK:
					$IP = $ipAdder->toManualBlacklist( $label );
					break;

				default:
					throw new \Exception( 'Please select one of the IP Rule Types - Block or Bypass' );
			}

			if ( empty( $IP ) ) {
				throw new \Exception( 'There appears to have been a problem adding the IP rule' );
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
			'page_reload' => $success,
			'message'     => $msg,
		];
	}
}