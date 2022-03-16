<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

/**
 * @property int    $rid
 * @property string $ip
 * @property string $message
 * @property string $wp_username
 * @property string $event
 * @property string $context
 * @property string $category
 * @property int    $count
 * @property string $data - do not access directly! Instead getAuditData()
 */
class EntryVO extends Base\EntryVO {

	/**
	 * @return array
	 */
	public function getAuditData() {
		return $this->meta;
	}
}