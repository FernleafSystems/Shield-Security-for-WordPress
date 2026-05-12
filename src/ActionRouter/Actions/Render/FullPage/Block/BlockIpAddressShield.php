<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\IpAutoUnblockShieldUserLinkRequest;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\ByPassIpBlock;
use FernleafSystems\Wordpress\Services\Services;

class BlockIpAddressShield extends BaseBlock {

	use ByPassIpBlock;
	use BlockRecoveryRenderContracts;

	public const SLUG = 'render_block_ip_address_shield';
	public const TEMPLATE = '/pages/block/block_page_ip.twig';

	protected function getRenderData() :array {
		$recovery = $this->buildBlockRecoveryContract(
			$this->getBlockRecoveryPageKey(),
			$this->buildBlockRecoveryCandidates()
		);

		return [
			'hrefs'   => [
				'how_to_unblock' => 'https://clk.shldscrty.com/shieldhowtounblock',
			],
			'strings' => [
				'page_title'    => sprintf( '%s | %s', CommonDisplayStrings::get( 'access_restricted_label' ), self::con()->labels->Name ),
				'title'         => CommonDisplayStrings::get( 'access_restricted_label' ),
				'subtitle'      => __( 'Access from your IP address has been temporarily restricted.', 'wp-simple-firewall' ),
				'contact_admin' => __( 'Please contact site admin to request your IP address is unblocked.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'recovery'  => $recovery,
				'inline_js' => $this->getBlockRecoveryInlineJs( $recovery ),
			],
		];
	}

	/**
	 * @return list<array{recovery:array<string,mixed>,content:string}>
	 */
	protected function buildBlockRecoveryCandidates() :array {
		$emailRecovery = $this->buildBlockRecoveryActionContract( $this->getBlockRecoveryPageKey(), 'email-unblock' );
		$autoRecovery = $this->buildBlockRecoveryActionContract( $this->getBlockRecoveryPageKey(), 'auto-recover' );

		return [
			$this->buildBlockRecoveryCandidate(
				$emailRecovery,
				$this->renderEmailMagicLinkContent( $emailRecovery )
			),
			$this->buildBlockRecoveryCandidate(
				$autoRecovery,
				$this->renderAutoUnblock( $autoRecovery )
			),
		];
	}

	protected function getBlockRecoveryPageKey() :string {
		return 'ip-shield';
	}

	/**
	 * @param array{action:string} $recovery
	 * @return list<string>
	 */
	protected function getBlockRecoveryInlineJs( array $recovery ) :array {
		if ( $recovery[ 'action' ] !== 'email-unblock' ) {
			return [];
		}

		return [
			sprintf( 'var shield_vars_blockpage = %s;', \wp_json_encode( [
				'magic_unblock' => [
					'ajax' => [
						'unblock_request' => ActionData::Build( IpAutoUnblockShieldUserLinkRequest::class, true, [
							'ip' => self::con()->this_req->ip
						] )
					],
					'strings' => [
						'request_failed' => __( 'Request Failed', 'wp-simple-firewall' ),
					],
				],
			] ) )
		];
	}

	protected function renderAutoUnblock( array $recovery ) :string {
		return self::con()->action_router->render( Components\AutoUnblockShield::class, [
			'vars' => [
				'recovery' => $recovery,
			],
		] );
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

	protected function renderEmailMagicLinkContent( array $recovery ) :string {
		return self::con()->action_router->render( Components\MagicLink::class, [
			'vars' => [
				'recovery' => $recovery,
			],
		] );
	}
}
