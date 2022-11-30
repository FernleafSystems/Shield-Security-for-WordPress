<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\FullPage\Block;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

class BlockIpAddressShield extends BaseBlock {

	public const PRIMARY_MOD = 'ips';
	public const SLUG = 'render_block_ip_address_shield';
	public const TEMPLATE = '/pages/block/block_page_ip.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();

		$autoUnblock = trim( $this->renderAutoUnblock() );
		$magicLink = trim( $this->renderEmailMagicLinkContent() );

		return [
			'content' => [
				'auto_unblock'  => $autoUnblock,
				'email_unblock' => $magicLink,
			],
			'flags'   => [
				'has_magiclink'   => !empty( $magicLink ),
				'has_autorecover' => !empty( $autoUnblock ),
			],
			'hrefs'   => [
				'how_to_unblock' => 'https://shsec.io/shieldhowtounblock',
			],
			'strings' => [
				'page_title' => sprintf( '%s | %s', __( 'Access Restricted', 'wp-simple-firewall' ), $con->getHumanName() ),
				'title'      => __( 'Access Restricted', 'wp-simple-firewall' ),
				'subtitle'   => __( 'Access from your IP address has been temporarily restricted.', 'wp-simple-firewall' ),
			],
		];
	}

	protected function renderAutoUnblock() :string {
		return $this->getCon()
					->getModule_Insights()
					->getActionRouter()
					->render( Components\AutoUnblockShield::SLUG );
	}

	protected function getRestrictionDetailsBlurb() :array {
		$blurb = array_merge(
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
		$opts = $this->primary_mod->getOptions();
		return array_merge(
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
		return $this->getCon()
					->getModule_Insights()
					->getActionRouter()
					->render( Components\MagicLink::SLUG );
	}
}