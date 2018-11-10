<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\WpCore;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class ResultItem
 * @property string path_full
 * @property string path_fragment
 * @property string md5_file
 * @property string md5_file_converted
 * @property string md5_file_wp
 * @property bool   file_exists
 * @property bool   is_autorepair
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpCore
 */
class ResultItem extends Base\BaseResultItem {

	/**
	 * @return bool
	 */
	public function isReady() {
		return !empty( $this->path_full ) && !empty( $this->md5_file_wp ) && !empty( $this->path_fragment );
	}

	/**
	 * @return string
	 */
	public function getFileMd5() {
		if ( $this->fileExists() && !isset( $this->md5_file ) ) {
			$this->md5_file = md5_file( $this->path_full );
		}
		return $this->md5_file;
	}

	/**
	 * @return string
	 */
	public function getFileMd5Converted() {
		if ( $this->fileExists() && !isset( $this->md5_file_converted ) ) {
			$this->md5_file_converted = str_replace( [ "\r\n", "\r" ], "\n", file_get_contents( $this->path_full ) );
		}
		return $this->md5_file_converted;
	}

	/**
	 * @return string
	 */
	public function getFileMd5Wp() {
		return $this->md5_file_wp;
	}

	/**
	 * @return bool
	 */
	public function fileExists() {
		if ( !isset( $this->file_exists ) ) {
			$this->file_exists = file_exists( $this->path_full );
		}
		return $this->file_exists;
	}

	/**
	 * @return bool
	 */
	public function isFileMissing() {
		return !$this->fileExists();
	}

	/**
	 * @return bool
	 */
	public function isAutoRepair() {
		return (bool)$this->is_autorepair;
	}

	/**
	 * @return bool
	 */
	public function isChecksumValid() {
		return $this->fileExists() && !$this->isChecksumFail();
	}

	/**
	 * @return bool
	 */
	public function isChecksumFail() {
		return $this->fileExists() && ( $this->md5_file_wp != $this->getFileMd5() )
			   && ( strpos( $this->path_full, '.php' ) > 0 ) && ( $this->md5_file_wp != $this->getFileMd5Converted() );
	}
}