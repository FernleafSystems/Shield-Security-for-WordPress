<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;

class BannerGoPro extends BaseRender {

	use Traits\SecurityAdminNotRequired;

	public const SLUG = 'render_banner_gopro';
	public const TEMPLATE = '/snippets/go_pro_banner.twig';

	protected function getRenderData() :array {
		$con = $this->con();
		$nav = $this->action_data[ Constants::NAV_ID ] ?? '';
		return [
			'flags' => [
				'show_promo' => \method_exists( $con, 'isPluginAdminPageRequest' )
								&& $con->isPluginAdminPageRequest()
								&& !$con->isPremiumActive()
								&& ( !in_array( $nav, [ PluginURLs::NAV_SCANS_RESULTS, PluginURLs::NAV_SCANS_RUN ] ) ),
			],
			'hrefs' => [
				'go_pro' => 'https://shsec.io/shieldgoprofeature',
			]
		];
	}
}