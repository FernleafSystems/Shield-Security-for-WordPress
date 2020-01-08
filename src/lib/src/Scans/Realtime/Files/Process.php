<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Realtime\Files;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Process
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpConfig
 * @property string $priv_key
 * @property string $backup_dir
 * @property string $backup_file
 * @property string $original_path
 * @property string $original_path_hash
 */
class Process {

	use StdClassAdapter;

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function run() {
		$oFs = Services::WpFs();

		if ( empty( $this->backup_file ) ) {
			// we haven't created a backup yet.
			$this->createBackup();
		}
		elseif ( !$oFs->isFile( $this->getFullBackupPath() ) ) {
			throw new \Exception( 'Backup file is missing', 1 );
		}
		elseif ( !( new Verify() )->run( $this->original_path, $this->original_path_hash ) ) {
			( new Revert() )->run( $this->original_path, $this->getFullBackupPath(), $this->priv_key );
		}

		return true;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function createBackup() {
		$this->backup_file = '.shieldbak-'.wp_rand( 1000, 999999 );

		if ( !( new TestWritable() )->run( $this->original_path ) ) {
			throw new \Exception( 'Cannot write to path: '.$this->original_path, 2 );
		}

		try {
			$bSuccess = ( new Backup() )->run(
				Services::WpGeneral()->getPath_WpConfig(),
				$this->getFullBackupPath(),
				Services::Encrypt()->getPublicKeyFromPrivateKey( $this->priv_key )
			);
			if ( $bSuccess ) {
				$this->original_path_hash = sha1_file( Services::WpGeneral()->getPath_WpConfig() );
			}
		}
		catch ( \Exception $oE ) {
			throw new \Exception( $oE->getMessage(), 3 );
		}
		return $bSuccess;
	}

	/**
	 * @return string
	 */
	protected function getFullBackupPath() {
		return path_join( $this->backup_dir, $this->backup_file );
	}
}