<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Utility\FileLockKeyApplicability;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class FileLocker extends Base {

	private ?FileLockKeyApplicability $fileLockKeyApplicability;

	public function __construct( ?FileLockKeyApplicability $fileLockKeyApplicability = null ) {
		$this->fileLockKeyApplicability = $fileLockKeyApplicability;
	}

	public function title() :string {
		return __( 'FileLocker: wp-config.php Protection', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( "Protect key WP core files that can't normally be protected.", 'wp-simple-firewall' );
	}

	protected function configureStatus() :array {
		$toLock = $this->selectedLockedFiles();
		$status = parent::status();

		if ( empty( $toLock ) ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = $this->explanationFixedBy( __( 'FileLocker is not configured for any files.', 'wp-simple-firewall' ), 'file_locker' );
		}
		if ( !\in_array( 'wpconfig', $toLock, true ) ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = $this->explanationFixedBy( __( "wp-config.php isn't protected against tampering.", 'wp-simple-firewall' ), 'file_locker' );
		}

		foreach ( $this->applicableFileLockDefinitions() as $fileKey => $definition ) {
			if ( $fileKey !== 'wpconfig' && !\in_array( $fileKey, $toLock, true ) ) {
				$status[ 'exp' ][] = $this->explanationFixedBy( $definition[ 'disabled_message' ], 'file_locker' );
			}
		}

		// Whatever files are chosen, File Locker can't work without these.
		foreach ( $this->unmetRequirements() as $requirement ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = $requirement;
		}

		if ( $status[ 'level' ] !== EnumEnabledStatus::BAD ) {
			$status[ 'level' ] = empty( $status[ 'exp' ] ) ? EnumEnabledStatus::GOOD : EnumEnabledStatus::OKAY;
		}

		return $status;
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$status = parent::status();
		$toLock = $this->selectedLockedFiles();
		if ( empty( $toLock ) ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
		}
		elseif ( \in_array( 'wpconfig', $toLock, true ) ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		else {
			$status[ 'exp' ][] = __( "wp-config.php file isn't protected against tampering.", 'wp-simple-firewall' );
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
		}

		if ( !\in_array( 'root_index', $toLock, true ) ) {
			$status[ 'exp' ][] = __( "Root index.php file isn't protected against tampering.", 'wp-simple-firewall' );
		}

		return $status;
	}

	/**
	 * @return array<string,array{disabled_message:string}>
	 */
	protected function applicableFileLockDefinitions() :array {
		return ( $this->fileLockKeyApplicability ?? FileLockKeyApplicability::fromCurrentEnvironment() )
			->filterApplicableDefinitions( $this->fileLockDefinitions() );
	}

	/**
	 * @return array<string,array{disabled_message:string}>
	 */
	private function fileLockDefinitions() :array {
		return [
			'wpconfig' => [
				'disabled_message' => __( "wp-config.php isn't protected against tampering.", 'wp-simple-firewall' ),
			],
			'theme_functions' => [
				'disabled_message' => __( "Theme functions.php isn't protected against tampering.", 'wp-simple-firewall' ),
			],
			'root_htaccess' => [
				'disabled_message' => __( "Root .htaccess isn't protected against tampering.", 'wp-simple-firewall' ),
			],
			'root_index' => [
				'disabled_message' => __( "Root index.php isn't protected against tampering.", 'wp-simple-firewall' ),
			],
			'root_webconfig' => [
				'disabled_message' => __( "Root web.config isn't protected against tampering.", 'wp-simple-firewall' ),
			],
		];
	}

	/**
	 * @return list<string>
	 */
	private function selectedLockedFiles() :array {
		return self::con()->comps->file_locker->getFilesToLock();
	}

	/**
	 * Why File Locker can't work on this site, if it can't: it encrypts each file's copy, and on Pro it also
	 * needs ShieldNet.
	 * @return list<string>
	 */
	private function unmetRequirements() :array {
		$con = self::con();
		$unmet = [];
		if ( !$con->comps->file_locker->canEncrypt() ) {
			$unmet[] = sprintf(
				/* translators: %s: PHP extension name */
				__( "File Locker can't work because this server can't encrypt files: it needs the PHP %s extension and a cipher it supports.", 'wp-simple-firewall' ),
				'OpenSSL'
			);
		}
		if ( $con->isPremiumActive() && !$con->comps->shieldnet->canHandshake() ) {
			$unmet[] = sprintf(
				/* translators: %s: name of the plugin's API service */
				__( "File Locker can't work because this site can't connect to %s.", 'wp-simple-firewall' ),
				$con->labels->getBrandName( 'shieldnet' ).' API'
			);
		}
		return $unmet;
	}
}
