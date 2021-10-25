<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

/**
 * @property string $slug
 * @property int    $last_updated_at
 */
class ResultItem extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultItem {

	/**
	 * @inheritDoc
	 */
	public function __get( string $key ) {
		$value = parent::__get( $key );

		if ( preg_match( '/_at$/i', $key ) ) {
			$value = (int)$value;
		}

		return $value;
	}

	public function getDescriptionForAudit() :string {
		return sprintf( '%s: %s', ( strpos( $this->slug, '/' ) ? 'Plugin' : 'Theme' ), $this->slug );
	}
}