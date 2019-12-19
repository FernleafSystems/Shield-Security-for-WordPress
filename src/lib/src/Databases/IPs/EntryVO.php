<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

/**
 * Class EntryVO
 * @property string $ip
 * @property int    $transgressions
 * @property bool   $is_range
 * @property string $label
 * @property string $list
 * @property int    $last_access_at
 * @property int    $blocked_at
 */
class EntryVO extends Base\EntryVO {

	/**
	 * @return string
	 * @deprecated 8.5
	 */
	public function getLabel() {
		return (string)$this->label;
	}

	/**
	 * @return int
	 * @deprecated 8.5
	 */
	public function getLastAccessAt() {
		return (int)$this->last_access_at;
	}

	/**
	 * @return string
	 * @deprecated 8.5
	 */
	public function getList() {
		return (string)$this->list;
	}

	/**
	 * @return int
	 * @deprecated 8.5
	 */
	public function getTransgressions() {
		return (int)$this->transgressions;
	}

	/**
	 * @return bool
	 * @deprecated 8.5
	 */
	public function hasTransgressions() {
		return (int)$this->transgressions > 0;
	}
}