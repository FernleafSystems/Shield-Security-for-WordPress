<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class EmailInstantAlertFirewallBlock extends EmailInstantAlertBase {

	public const SLUG = 'email_instant_alert_firewall_block';

	protected function getBodyData() :array {
		return \FernleafSystems\Wordpress\Services\Services::DataManipulation()->mergeArraysRecursive( parent::getBodyData(), [
			'strings' => [
				'intro' => [
					sprintf( __( '%s Firewall has blocked a request to your WordPress site.', 'wp-simple-firewall' ), self::con()->labels->Name ),
					__( 'This is for informational purposes only.', 'wp-simple-firewall' )
					.' '.sprintf( __( '%s has already taken the necessary action of blocking the request.', 'wp-simple-firewall' ), self::con()->labels->Name ),
				],
			],
		] );
	}

	protected function buildAlertGroups() :array {
		$alertData = $this->firewallBlockAlertData();
		$labels = CommonDisplayStrings::pick( [
			'ip_address_label',
			'request_path_label',
		] );

		return [
			'firewall_block' => $this->alertGroup(
				__( 'Request Details', 'wp-simple-firewall' ),
				[
					$this->alertItem( [ $this->alertLine( $labels[ 'ip_address_label' ], $alertData[ 'ip' ], self::LINE_STYLE_CODE ) ] ),
					$this->alertItem( [ $this->alertLine( __( 'Firewall Rule', 'wp-simple-firewall' ), $alertData[ 'firewall_rule_name' ], self::LINE_STYLE_CODE ) ] ),
					$this->alertItem( [ $this->alertLine( __( 'Firewall Pattern', 'wp-simple-firewall' ), $alertData[ 'match_pattern' ], self::LINE_STYLE_CODE ) ] ),
					$this->alertItem( [ $this->alertLine( $labels[ 'request_path_label' ], $alertData[ 'request_path' ], self::LINE_STYLE_CODE ) ] ),
					$this->alertItem( [ $this->alertLine( __( 'Parameter Name', 'wp-simple-firewall' ), $alertData[ 'match_request_param' ], self::LINE_STYLE_CODE ) ] ),
					$this->alertItem( [ $this->alertLine( __( 'Parameter Value', 'wp-simple-firewall' ), $alertData[ 'match_request_value' ], self::LINE_STYLE_CODE ) ] ),
					$this->alertItem(
						[ $this->alertTextLine( __( 'IP Address Lookup', 'wp-simple-firewall' ) ) ],
						URL::Build( 'https://clk.shldscrty.com/botornot', [ 'ip' => $alertData[ 'ip' ] ] )
					),
				]
			),
		];
	}

	/**
	 * @return array{
	 *   ip:string,
	 *   request_path:string,
	 *   firewall_rule_name:string,
	 *   match_pattern:string,
	 *   match_request_param:string,
	 *   match_request_value:string
	 * }
	 */
	private function firewallBlockAlertData() :array {
		/** @var array{
		 *   ip:string,
		 *   request_path:string,
		 *   firewall_rule_name:string,
		 *   match_pattern:string,
		 *   match_request_param:string,
		 *   match_request_value:string
		 * } $alertData
		 */
		$alertData = $this->action_data[ 'alert_data' ][ 'firewall_block' ];
		return $alertData;
	}
}
