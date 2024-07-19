<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

/**
 * @deprecated 19.2
 */
class Options {

	use ModConsumer;

	/**
	 * @return mixed|null
	 * @deprecated 19.2
	 */
	public function getDef( string $key ) {
		return self::con()->cfg->configuration->def( $key );
	}

	/**
	 * @param mixed $mDefault
	 * @return mixed
	 * @deprecated 19.2
	 */
	public function getOpt( string $key, $mDefault = false ) {
		return self::con()->opts->optGet( $key );
	}

	/**
	 * @param mixed $value
	 * @deprecated 19.2
	 */
	public function isOpt( string $key, $value ) :bool {
		return self::con()->opts->optIs( $key, $value );
	}

	/**
	 * @param mixed $newValue
	 * @return $this
	 * @deprecated 19.2
	 */
	public function setOpt( string $key, $newValue ) :self {
		self::con()->opts->optSet( $key, $newValue );
		return $this;
	}
}