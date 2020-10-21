<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;

class OverviewCards {

	use Shield\Modules\ModConsumer;

	public function build() :array {
		return [];
	}

	protected function getModDisabledCard() :array {
		$mod = $this->getMod();
		return [
			'name'    => __( 'Module Disabled', 'wp-simple-firewall' ),
			'summary' => __( 'All features of this module are completely disabled', 'wp-simple-firewall' ),
			'state'   => -2,
			'href'    => $mod->getUrl_DirectLinkToOption( $mod->getEnableModOptKey() ),
		];
	}
}