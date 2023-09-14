<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Restrictions;

use FernleafSystems\Wordpress\Services\Services;

class WpOptions extends Base {

	protected function canRun() :bool {
		return $this->opts()->isRestrictWpOptions() && !Services::WpGeneral()->isLoginRequest();
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

		if ( !self::con()->isPluginAdmin()
			 && ( \in_array( $key, $this->opts()->getOptionsToRestrict() ) || $this->isPluginOption( $key ) )
		) {
			$newValue = $oldValue;
		}

		return $newValue;
	}

	private function isPluginOption( string $key ) :bool {
		return \preg_match( sprintf( '/^%s.*_options$/', self::con()->getOptionStoragePrefix() ), $key ) > 0;
	}
}