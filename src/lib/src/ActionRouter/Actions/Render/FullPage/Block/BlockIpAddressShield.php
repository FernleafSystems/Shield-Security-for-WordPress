<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\ByPassIpBlock;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
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
				'how_to_unblock' => 'https://shsec.io/shieldhowtounblock',
			],
			'strings' => [
				'page_title'    => sprintf( '%s | %s', __( 'Access Restricted', 'wp-simple-firewall' ),
					self::con()->getHumanName() ),
				'title'         => __( 'Access Restricted', 'wp-simple-firewall' ),
				'subtitle'      => __( 'Access from your IP address has been temporarily restricted.', 'wp-simple-firewall' ),
				'contact_admin' => __( 'Please contact site admin to request your IP address is unblocked.', 'wp-simple-firewall' ),
			],
		];
	}

	protected function renderAutoUnblock() :string {
		return self::con()->action_router->render( Components\AutoUnblockShield::SLUG );
	}

	protected function getRestrictionDetailsBlurb() :array {
		$blurb = \array_merge(
			[
				__( "Too many requests from your IP address have triggered the site's automated defenses.", 'wp-simple-firewall' ),
			],
			parent::getRestrictionDetailsBlurb()
		);
		unset( $blurb[ 'activity_recorded' ] );
		return $blurb;
	}

	protected function getRestrictionDetailsPoints() :array {
		/** @var IPs\Options $opts */
		$opts = self::con()->getModule_IPs()->getOptions();
		return \array_merge(
			[
				__( 'Restrictions Lifted', 'wp-simple-firewall' ) => Services::Request()
																			 ->carbon()
																			 ->addSeconds( $opts->getAutoExpireTime() )
																			 ->diffForHumans(),
			],
			parent::getRestrictionDetailsPoints()
		);
	}

	protected function renderEmailMagicLinkContent() :string {
		return self::con()->action_router->render( Components\MagicLink::SLUG );
	}
}