<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;

/**
 * @deprecated 17.0
 */
class AdminPage extends ExecOnceModConsumer {

	protected $screenID;

	protected function canRun() :bool {
		return false;
	}

	protected function run() {
	}

	protected function addSubMenuItem() {
	}

	/**
	 * @uses echo()
	 */
	public function displayModuleAdminPage() {
		echo '';
	}

	public function getScreenID() :string {
		return (string)$this->screenID;
	}

	public function getCap() :string {
		return $this->getCon()->getBasePermissions();
	}

	public function isCurrentPage() :bool {
		return false;
	}

	public function getMenuTitle( bool $markup = true ) :string {
		return '';
	}

	public function getPageTitle() :string {
		return '';
	}

	public function getSlug() :string {
		return $this->getMod()->getModSlug();
	}
}