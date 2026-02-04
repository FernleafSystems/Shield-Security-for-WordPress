<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Handler;

class FormIpRuleAdd extends BaseRender {

	public const SLUG = 'render_form_ip_rule_add';
	public const TEMPLATE = '/components/forms/ip_rule_add.twig';

	protected function getRenderData() :array {
		$con = self::con();
		return [
			'flags'   => [
				'is_blacklist_allowed' => $con->isPremiumActive(),
			],
			'strings' => [
				'add_to_list_block'       => __( 'Add To Block List', 'wp-simple-firewall' ),
				'add_to_list_block_help'  => __( 'Requests from this IP address will be blocked.', 'wp-simple-firewall' ),
				'add_to_list_bypass'      => __( 'Add To Bypass List', 'wp-simple-firewall' ),
				'add_to_list_bypass_help' => __( 'Requests from this IP address will bypass all security rules.', 'wp-simple-firewall' ),
				'label'                   => __( 'Label For This IP Rule', 'wp-simple-firewall' ),
				'label_help'              => __( 'A helpful label to describe this IP rule.', 'wp-simple-firewall' ),
				'label_help_max'          => sprintf( '%s: %s', __( '255 characters max', 'wp-simple-firewall' ), 'a-z,0-9' ),
				'ip_address'              => __( 'IP Address or IP Range', 'wp-simple-firewall' ),
				'ip_address_help'         => __( 'IPv4 or IPv6; Single Address or CIDR Range', 'wp-simple-firewall' ),
				'add_rule'                => __( 'Create New IP Rule', 'wp-simple-firewall' ),
				'confirm'                 => __( "I fully understand the significance of this action", 'wp-simple-firewall' ),
			],
			'vars'    => [
				'blacklist' => Handler::T_MANUAL_BLOCK,
				'whitelist' => Handler::T_MANUAL_BYPASS,
			],
		];
	}
}