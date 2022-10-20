<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Email;

use FernleafSystems\Wordpress\Services\Services;

class FirewallBlockAlert extends Base {

	const SLUG = 'email_firewall_block_alert';
	const TEMPLATE = '/email/firewall_block.twig';

	protected function getBodyData() :array {
		$ip = $this->action_data[ 'ip' ];
		$blockMeta = $this->action_data[ 'block_meta' ];

		return [
			'strings' => [
				'shield_blocked'  => sprintf( __( '%s Firewall has blocked a request to your WordPress site.', 'wp-simple-firewall' ),
					$this->getCon()->getHumanName() ),
				'details_below'   => __( 'Details for the request are given below:', 'wp-simple-firewall' ),
				'details'         => __( 'Request Details', 'wp-simple-firewall' ),
				'ip_lookup'       => __( 'IP Address Lookup' ),
				'this_is_info'    => __( 'This is for informational purposes only.' ),
				'already_blocked' => sprintf( __( '%s has already taken the necessary action of blocking the request.' ),
					$this->getCon()->getHumanName() ),
			],
			'hrefs'   => [
				'ip_lookup' => add_query_arg( [ 'ip' => $ip ], 'https://shsec.io/botornot' )
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