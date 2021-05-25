<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights;

class AdminPage extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\AdminPage {

	protected function renderModulePage( array $data = [] ) :string {
		/** @var UI $UI */
		$UI = $this->getMod()->getUIHandler();
		return $UI->renderPages();
	}
}