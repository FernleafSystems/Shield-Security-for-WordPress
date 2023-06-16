<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Report\BaseZoneReport;

abstract class BaseZone {

	public const SLUG = '';

	/**
	 * @return array[] - key is user ID, values are arrays with keys: id, user_login, user_pass, user_email, is_admin
	 * @uses 2 SQL queries
	 */
	abstract public function snap() :array;

	/**
	 * @return BaseZoneReport|mixed
	 */
	public function getZoneReporter() {
		$class = $this->getZoneReporterClass();
		return new $class( static::SLUG );
	}

	/**
	 * @return BaseZoneReport|mixed
	 */
	abstract public function getZoneReporterClass() :string;

	public function getZoneDescription() :array {
		return [
			'TODO: Zone Description'
		];
	}
}