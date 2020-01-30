<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Services\Services;

class BaseService {

	/**
	 * @var static
	 */
	private static $oInst;

	/**
	 * @return static
	 */
	public static function Instance() {
		if ( !isset( self::$oInst ) ) {
			self::$oInst = new static();
		}
		return self::$oInst;
	}

	/**
	 * BaseService constructor.
	 */
	protected function __construct() {
		$this->start();
	}

	/**
	 *
	 */
	protected function start() {
	}
}