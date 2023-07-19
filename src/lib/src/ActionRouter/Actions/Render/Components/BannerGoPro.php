<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;

/**
 * @deprecated 18.2
 */
class BannerGoPro extends BaseRender {

	use Traits\SecurityAdminNotRequired;

	public const SLUG = 'render_banner_gopro';
	public const TEMPLATE = '/snippets/go_pro_banner.twig';

	protected function getRenderData() :array {
		return [];
	}
}