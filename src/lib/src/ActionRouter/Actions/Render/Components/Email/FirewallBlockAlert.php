<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\WebApplicationFirewall;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class FirewallBlockAlert extends EmailBase {

	public const SLUG = 'email_firewall_block_alert';
	public const TEMPLATE = '/email/firewall_block.twig';

	protected function getBodyData() :array {
		$con = self::con();
		$ip = $this->action_data[ 'ip' ];
		$blockMeta = $this->action_data[ 'block_meta' ];

		return [
			'strings' => [
				'shield_blocked'  => sprintf( __( '%s Firewall has blocked a request to your WordPress site.', 'wp-simple-firewall' ), $con->labels->Name ),
				'details_below'   => __( 'Details for the request are given below:', 'wp-simple-firewall' ),
				'details'         => __( 'Request Details', 'wp-simple-firewall' ),
				'ip_lookup'       => __( 'IP Address Lookup' ),
				'this_is_info'    => __( 'This is for informational purposes only.' ),
				'unsubscribe_1'   => __( "Don't want these email alerts?", 'wp-simple-firewall' ),
				'unsubscribe_2'   => __( 'Configure Firewall Block alert emails', 'wp-simple-firewall' ),
				'already_blocked' => sprintf( __( '%s has already taken the necessary action of blocking the request.' ), $con->labels->Name ),
			],
			'hrefs'   => [
				'ip_lookup'   => URL::Build( 'https://clk.shldscrty.com/botornot', [ 'ip' => $ip ] ),
				'unsubscribe' => $con->plugin_urls->cfgForZoneComponent( WebApplicationFirewall::Slug() ),
			],
			'vars'    => [
				'req_details' => [
					__( 'Visitor IP Address', 'wp-simple-firewall' ) => $ip,
					__( 'Firewall Rule', 'wp-simple-firewall' )      => $blockMeta[ 'firewall_rule_name' ],
					__( 'Firewall Pattern', 'wp-simple-firewall' )   => $blockMeta[ 'match_pattern' ] ?? 'Unavailable',
					__( 'Request Path', 'wp-simple-firewall' )       => Services::Request()->getPath(),
					__( 'Parameter Name', 'wp-simple-firewall' )     => $blockMeta[ 'match_request_param' ] ?? 'Unavailable',
					__( 'Parameter Value', 'wp-simple-firewall' )    => $blockMeta[ 'match_request_value' ] ?? 'Unavailable',
				]
			]
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'ip',
			'block_meta',
		];
	}
}