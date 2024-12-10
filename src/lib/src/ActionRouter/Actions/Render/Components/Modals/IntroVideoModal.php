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
				'modal_title' => __( 'Demo: Important UI changes with Shield v20', 'wp-simple-firewall' ),
			],
		];
	}
}