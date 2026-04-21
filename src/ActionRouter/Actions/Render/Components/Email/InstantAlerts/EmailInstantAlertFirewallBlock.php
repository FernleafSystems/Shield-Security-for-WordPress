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
			'firewall_block' => [
				'title' => __( 'Request Details', 'wp-simple-firewall' ),
				'items' => [
					'ip' => [
						'text' => sprintf( '%s: <code>%s</code>', $labels[ 'ip_address_label' ], $alertData[ 'ip' ] ),
					],
					'firewall_rule_name' => [
						'text' => sprintf( '%s: <code>%s</code>', __( 'Firewall Rule', 'wp-simple-firewall' ), $alertData[ 'firewall_rule_name' ] ),
					],
					'match_pattern' => [
						'text' => sprintf( '%s: <code>%s</code>', __( 'Firewall Pattern', 'wp-simple-firewall' ), $alertData[ 'match_pattern' ] ),
					],
					'request_path' => [
						'text' => sprintf( '%s: <code>%s</code>', $labels[ 'request_path_label' ], $alertData[ 'request_path' ] ),
					],
					'match_request_param' => [
						'text' => sprintf( '%s: <code>%s</code>', __( 'Parameter Name', 'wp-simple-firewall' ), $alertData[ 'match_request_param' ] ),
					],
					'match_request_value' => [
						'text' => sprintf( '%s: <code>%s</code>', __( 'Parameter Value', 'wp-simple-firewall' ), $alertData[ 'match_request_value' ] ),
					],
					'ip_lookup' => [
						'text' => __( 'IP Address Lookup', 'wp-simple-firewall' ),
						'href' => URL::Build( 'https://clk.shldscrty.com/botornot', [ 'ip' => $alertData[ 'ip' ] ] ),
					],
				],
			],
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
