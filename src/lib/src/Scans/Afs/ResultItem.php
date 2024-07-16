<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Malware\Ops\Record;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

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
 * @property string $checksum_sha256
 */
class ResultItem extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultItem {

	use PluginControllerConsumer;

	/**
	 * @var ?Record
	 */
	private $record = null;

	public function getStatuses() :array {
		$statuses = [];

		if ( $this->is_unrecognised ) {
			$statuses[] = 'unrecognised';
		}
		elseif ( $this->is_mal ) {
			$statuses[] = 'malware';
		}
		elseif ( $this->is_missing ) {
			$statuses[] = 'missing';
		}
		elseif ( $this->is_checksumfail ) {
			$statuses[] = 'modified';
		}
		elseif ( $this->is_unidentified ) {
			$statuses[] = 'unidentified';
		}

		if ( $this->VO->item_repaired_at > 0 ) {
			$statuses[] = 'repaired';
		}
		elseif ( $this->VO->item_deleted_at > 0 ) {
			$statuses[] = 'deleted';
		}

		if ( $this->VO->ignored_at > 0 ) {
			$statuses[] = 'ignored';
		}

		return empty( $statuses ) ? [ 'unknown' ] : $statuses;
	}

	public function getStatusForHuman() :array {
		return \array_intersect_key( [
			'unrecognised' => __( 'Unrecognised', 'wp-simple-firewall' ),
			'malware'      => __( 'Potential Malware', 'wp-simple-firewall' ),
			'missing'      => __( 'Missing', 'wp-simple-firewall' ),
			'modified'     => __( 'Modified', 'wp-simple-firewall' ),
			'unidentified' => __( 'Unidentified', 'wp-simple-firewall' ),
			'repaired'     => __( 'Repaired', 'wp-simple-firewall' ),
			'deleted'      => __( 'Deleted', 'wp-simple-firewall' ),
			'ignored'      => __( 'Ignored', 'wp-simple-firewall' ),
			'unknown'      => __( 'Unknown', 'wp-simple-firewall' ),
		], \array_flip( $this->getStatuses() ) );
	}

	public function getDescriptionForAudit() :string {
		return $this->path_fragment;
	}

	public function getMalwareRecord() :?Record {
		if ( empty( $this->record ) && isset( $this->malware_record_id ) ) {
			$this->record = self::con()
				->db_con
				->malware
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