<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SetupGuideDataBuilder;

/**
 * The setup guide bar, rendered again for a page that's showing it after settings were saved on that page, so
 * its steps reflect what was just changed.
 */
class SetupGuideBar extends BaseRender {

	public const SLUG = 'render_setup_guide_bar';
	public const TEMPLATE = '/wpadmin/components/setup_guide_bar.twig';

	protected function getRenderData() :array {
		return [
			'guide' => ( new SetupGuideDataBuilder() )->buildForRefresh( $this->fields()->flag( 'with_every_form', '1', '0', false ) ),
		];
	}
}
