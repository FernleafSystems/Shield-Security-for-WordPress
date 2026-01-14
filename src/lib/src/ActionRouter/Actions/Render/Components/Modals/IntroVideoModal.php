<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Modals;

class IntroVideoModal extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_intro_video_modal';
	public const TEMPLATE = '/components/modals/intro_video_modal.twig';

	protected function getRenderData() :array {
		return [
			'hrefs'   => [
				'release_guide' => 'https://clk.shldscrty.com/ob',
			],
			'strings' => [
				'modal_title' => sprintf( __( 'Demo: Important UI changes with %s v20', 'wp-simple-firewall' ), self::con()->labels->Name ),
				'video_title' => sprintf( __( '%s for WordPress v20 Introduction', 'wp-simple-firewall' ), self::con()->labels->Name ),
			],
		];
	}
}