<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Traits;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Constants;

class BannerGoPro extends BasePlugin {

	use Traits\SecurityAdminNotRequired;

	const SLUG = 'render_banner_gopro';
	const TEMPLATE = '/snippets/go_pro_banner.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();
		$nav = $this->action_data[ Constants::NAV_ID ] ?? '';
		return [
			'flags' => [
				'show_promo' => $con->isModulePage()
								&& !$con->isPremiumActive()
								&& ( !in_array( $nav, [ 'scans_results', 'scans_run' ] ) ),
			],
			'hrefs' => [
				'go_pro' => 'https://shsec.io/shieldgoprofeature',
			]
		];
	}
}