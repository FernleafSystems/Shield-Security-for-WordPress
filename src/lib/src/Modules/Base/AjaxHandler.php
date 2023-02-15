<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

/**
 * @deprecated 17.0
 */
abstract class AjaxHandler {

	use ModConsumer;

	/**
	 * @param ModCon|mixed $mod
	 */
	public function __construct( $mod ) {
		$this->setMod( $mod );
	}

	/**
	 * @return callable[]
	 */
	protected function getAjaxActionCallbackMap( bool $isAuth ) :array {
		return [];
	}
}