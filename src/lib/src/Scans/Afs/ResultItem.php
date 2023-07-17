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
 * @property bool   $is_in_wproot
 * @property bool   $is_in_wpcontent
 * @property bool   $is_checksumfail
 * @property bool   $is_unrecognised
 * @property bool   $is_unidentified
 * @property bool   $is_missing
 * @property bool   $is_mal
 * @property int    $malware_record_id
 * @property string $ptg_slug
 */
class ResultItem extends Base\ResultItem {

	use ModConsumer;

	/**
	 * @var ?Record
	 */
	private $record = null;

	public function getStatusForHuman() :string {
		if ( $this->is_unrecognised ) {
			$status = __( 'Unrecognised', 'wp-simple-firewall' );
		}
		elseif ( $this->is_mal ) {
			$status = __( 'Potential Malware', 'wp-simple-firewall' );
		}
		elseif ( $this->is_missing ) {
			$status = __( 'Missing', 'wp-simple-firewall' );
		}
		elseif ( $this->is_checksumfail ) {
			$status = __( 'Modified', 'wp-simple-firewall' );
		}
		elseif ( $this->is_unidentified ) {
			$status = __( 'Unidentified', 'wp-simple-firewall' );
		}
		else {
			$status = __( 'Unknown', 'wp-simple-firewall' );
		}
		return $status;
	}

	public function getDescriptionForAudit() :string {
		return $this->path_fragment;
	}

	public function getMalwareRecord() :?Record {
		if ( empty( $this->record ) && isset( $this->malware_record_id ) ) {
			$this->record = $this->con()
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
				$value = \base64_decode( $value );
				break;
			default:
				break;
		}

		if ( \preg_match( '/^is_/i', $key ) ) {
			$value = (bool)$value;
		}

		return $value;
	}

	public function __set( string $key, $value ) {
		switch ( $key ) {
			case 'mal_sig':
				$value = \base64_encode( $value );
				break;
			default:
				break;
		}

		if ( \preg_match( '/^is_/i', $key ) ) {
			$value = (bool)$value;
		}

		parent::__set( $key, $value );
	}
}