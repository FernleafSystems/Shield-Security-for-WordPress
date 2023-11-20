<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Storage {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function loadOptions() :array {
		if ( self::con()->plugin_reset ) {
			throw new \Exception( 'Resetting plugin - not loading stored options' );
		}
		return $this->loadFromWP();
	}

	/**
	 * @throws \Exception
	 */
	private function loadFromWP() :array {
		$values = Services::WpGeneral()->getOption( $this->mod()->getOptionsStorageKey(), [] );
		if ( empty( $values ) || !\is_array( $values ) ) {
			throw new \Exception( 'no values stored' );
		}
		return $values;
	}
}