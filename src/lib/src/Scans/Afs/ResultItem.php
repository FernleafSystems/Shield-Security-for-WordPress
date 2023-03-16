<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\Malware\Ops\Record;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * @property string $path_full
 * @property string $path_fragment  - relative to ABSPATH
 * @property bool   $is_in_core
 * @property bool   $is_in_plugin
 * @property bool   $is_in_theme
 * @property bool   $is_checksumfail
 * @property bool   $is_unrecognised
 * @property bool   $is_missing
 * @property bool   $is_mal
 * @property bool   $is_realtime
 * @property int    $malware_record_id
 * @property string $ptg_slug
 */
class ResultItem extends Base\ResultItem {

	use ModConsumer;

	/**
	 * @var ?Record
	 */
	private $record = null;

	public function getDescriptionForAudit() :string {
		return $this->path_fragment;
	}

	public function getMalwareRecord() :?Record {
		if ( empty( $this->record ) && isset( $this->malware_record_id ) ) {
			$this->record = $this->getCon()
								 ->getModule_HackGuard()
								 ->getDbH_Malware()
								 ->getQuerySelector()
								 ->byId( $this->malware_record_id );
		}
		return $this->record;
	}

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {
			case 'path_full':
				if ( empty( $value ) ) {
					$value = path_join( wp_normalize_path( ABSPATH ), $this->path_fragment );
				}
				break;
			case 'mal_sig':
				$value = base64_decode( $value );
				break;
			case 'mal_fp_confidence':
				/** @deprecated 17.1 */
				$value = (int)( $value );
				break;
			default:
				break;
		}

		if ( preg_match( '/^is_/i', $key ) ) {
			$value = (bool)$value;
		}

		return $value;
	}

	public function __set( string $key, $value ) {
		switch ( $key ) {
			case 'mal_sig':
				$value = base64_encode( $value );
				break;
			case 'mal_fp_confidence':
				/** @deprecated 17.1 */
				$value = (int)( $value );
				break;
			default:
				break;
		}

		if ( preg_match( '/^is_/i', $key ) ) {
			$value = (bool)$value;
		}

		parent::__set( $key, $value );
	}
}