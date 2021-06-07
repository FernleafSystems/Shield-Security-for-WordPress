<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Restrictions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Options;
use FernleafSystems\Wordpress\Services\Services;

class WpOptions extends Base {

	protected function canRun() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->getAdminAccessArea_Options()
			   && !$this->getMod()->isUpgrading() && !Services::WpGeneral()->isLoginRequest();
	}

	protected function run() {
		add_filter( 'pre_update_option', [ $this, 'blockOptionsSaves' ], 1, 3 );
	}

	/**
	 * Need to always re-test isPluginAdmin() because there's a dynamic filter in there to
	 * permit saving by the plugin itself.
	 *
	 * Right before a plugin option is due to update it will check that we have permissions to do so
	 * and if not, will * revert the option to save to the previous one.
	 * @param mixed  $newValue
	 * @param string $key
	 * @param mixed  $oldValue
	 * @return mixed
	 */
	public function blockOptionsSaves( $newValue, $key, $oldValue ) {
		/** @var Options $opts */
		$opts = $this->getOptions();

		if ( !$this->getCon()->isPluginAdmin() && is_string( $key )
			 && ( in_array( $key, $opts->getOptionsToRestrict() ) || $this->isPluginOption( $key ) ) ) {
			$newValue = $oldValue;
		}

		return $newValue;
	}

	private function isPluginOption( string $key ) :bool {
		return preg_match( sprintf( '/^%s.*_options$/', $this->getCon()->getOptionStoragePrefix() ), $key ) > 0;
	}
}