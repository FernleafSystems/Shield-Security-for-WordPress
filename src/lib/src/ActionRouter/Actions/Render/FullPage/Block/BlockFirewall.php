<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Firewall\FirewallCategoryNames;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\QueryRemainingOffenses;

class BlockFirewall extends BaseBlock {

	public const SLUG = 'render_block_firewall';

	protected function getRenderData() :array {
		$con = self::con();
		return [
			'strings' => [
				'page_title' => sprintf( '%s | %s', __( 'Request Blocked by Firewall', 'wp-simple-firewall' ), $con->labels->Name ),
				'title'      => __( 'Request Blocked', 'wp-simple-firewall' ),
				'subtitle'   => __( 'Firewall terminated the request because it triggered a firewall rule.', 'wp-simple-firewall' ),
			],
		];
	}

	protected function getRestrictionDetailsBlurb() :array {
		$messages = apply_filters( 'shield/firewall_die_message', [
			__( "Data scanned in this request matched at least 1 firewall rule and is considered potentially dangerous.", 'wp-simple-firewall' )
		] );
		return \array_merge(
			\is_array( $messages ) ? $messages : [],
			parent::getRestrictionDetailsBlurb()
		);
	}

	protected function getRestrictionDetailsPoints() :array {
		$blockMeta = $this->action_data[ 'block_meta_data' ];

		$remainingOffenses = \max( 0, ( new QueryRemainingOffenses() )->setIP( self::con()->this_req->ip )->run() );

		return \array_merge( [
			__( 'Remaining Offenses Allowed', 'wp-simple-firewall' ) => $remainingOffenses,
			__( 'Firewall Rule Category', 'wp-simple-firewall' )     =>
				( new FirewallCategoryNames() )->getFor( (string)$blockMeta[ 'match_category' ] ?? '' ),
			__( 'Request Parameter', 'wp-simple-firewall' )          => $blockMeta[ 'match_request_param' ],
			__( 'Request Parameter Value', 'wp-simple-firewall' )    => $blockMeta[ 'match_request_value' ],
			__( 'Firewall Pattern', 'wp-simple-firewall' )           => $blockMeta[ 'match_pattern' ],
		], parent::getRestrictionDetailsPoints() );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'block_meta_data'
		];
	}
}