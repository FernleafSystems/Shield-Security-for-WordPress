<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

/**
 * @property int  $last_updated_at
 * @property bool $is_abandoned
 */
class ResultItem extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultItem {

	public function __get( string $key ) {
		$value = parent::__get( $key );

		if ( \preg_match( '/_at$/i', $key ) ) {
			$value = (int)$value;
		}

		return $value;
	}

	public function getDescriptionForAudit() :string {
		return sprintf( '%s: %s', ( \strpos( $this->VO->item_id, '/' ) ? 'Plugin' : 'Theme' ), $this->VO->item_id );
	}
}