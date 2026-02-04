<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

/**
 * @property bool $is_vulnerable
 */
class ResultItem extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultItem {

	public function getDescriptionForAudit() :string {
		return sprintf( '%s: %s', ( \strpos( $this->VO->item_id, '/' ) ? 'Plugin' : 'Theme' ), $this->VO->item_id );
	}
}