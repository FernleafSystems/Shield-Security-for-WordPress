<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\IpAutoUnblockShieldUserLinkRequest;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\ByPassIpBlock;
use FernleafSystems\Wordpress\Services\Services;

class BlockIpAddressShield extends BaseBlock {

	use ByPassIpBlock;

	public const SLUG = 'render_block_ip_address_shield';
	public const TEMPLATE = '/pages/block/block_page_ip.twig';

	protected function getRenderData() :array {
		$autoUnblock = \trim( $this->renderAutoUnblock() );
		$magicLink = \trim( $this->renderEmailMagicLinkContent() );
		return [
			'content' => [
				'auto_unblock'  => $autoUnblock,
				'email_unblock' => $magicLink,
			],
			'flags'   => [
				'has_autorecover' => !empty( $autoUnblock ),
				'has_magiclink'   => !empty( $magicLink ),
			],
			'hrefs'   => [
				'how_to_unblock' => 'https://clk.shldscrty.com/shieldhowtounblock',
			],
			'strings' => [
				'page_title'    => sprintf( '%s | %s', __( 'Access Restricted', 'wp-simple-firewall' ), self::con()->labels->Name ),
				'title'         => __( 'Access Restricted', 'wp-simple-firewall' ),
				'subtitle'      => __( 'Access from your IP address has been temporarily restricted.', 'wp-simple-firewall' ),
				'contact_admin' => __( 'Please contact site admin to request your IP address is unblocked.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'inline_js' => [
					sprintf( 'var shield_vars_blockpage = %s;', \wp_json_encode( [
						'magic_unblock' => [
							'ajax' => [
								'unblock_request' => ActionData::Build( IpAutoUnblockShieldUserLinkRequest::class, true, [
									'ip' => self::con()->this_req->ip
								] )
							],
						],
					] ) )
				],
			]
		];
	}

	protected function renderAutoUnblock() :string {
		return self::con()->action_router->render( Components\AutoUnblockShield::class );
	}

	protected function getRestrictionDetailsBlurb() :array {
		$blurb = \array_merge( [
			__( "Too many requests from your IP address have triggered the site's automated defenses.", 'wp-simple-firewall' ),
		], parent::getRestrictionDetailsBlurb() );
		unset( $blurb[ 'activity_recorded' ] );
		return $blurb;
	}

	protected function getRestrictionDetailsPoints() :array {
		return \array_merge(
			[
				__( 'Restrictions Lifted', 'wp-simple-firewall' ) =>
					Services::Request()
							->carbon()
							->addSeconds( self::con()->comps->opts_lookup->getIpAutoBlockTTL() )
							->diffForHumans(),
			],
			parent::getRestrictionDetailsPoints()
		);
	}

	protected function renderEmailMagicLinkContent() :string {
		return self::con()->action_router->render( Components\MagicLink::class );
	}
}