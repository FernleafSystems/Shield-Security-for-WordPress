<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class AdminPage extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\AdminPage {

	/**
	 * @uses echo()
	 */
	public function displayModuleAdminPage() {
		if ( $this->getMod()->canDisplayOptionsForm() ) {
			parent::displayModuleAdminPage();
		}
		else {
			echo $this->getMod()->renderRestrictedPage();
		}
	}
}