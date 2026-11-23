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
		$applicability = $this->applicability();
		$status = parent::status();

		if ( empty( $toLock ) ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = $this->explanationFixedBy( __( 'FileLocker is not configured for any files.', 'wp-simple-firewall' ), 'file_locker' );
		}

		foreach ( $this->fileLockDefinitions() as $fileKey => $definition ) {
			if ( \in_array( $fileKey, $toLock, true ) ) {
				// Selected, but File Locker can't find it: it isn't protected. A locked file that's gone also shows
				// as a File Locker result to review.
				$exp = $applicability->isServerSupported( $fileKey ) && !$applicability->isApplicable( $fileKey )
					? $definition[ 'missing_message' ] : '';
			}
			else {
				$exp = $fileKey === 'wpconfig' || $applicability->isApplicable( $fileKey ) ? $definition[ 'disabled_message' ] : '';
			}

			if ( $exp !== '' ) {
				$status[ 'exp' ][] = $this->explanationFixedBy( $exp, 'file_locker' );
				if ( $fileKey === 'wpconfig' ) {
					$status[ 'level' ] = EnumEnabledStatus::BAD;
				}
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
		$protected = $this->protectedFiles( $toLock );
		if ( empty( $toLock ) ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
		}
		elseif ( \in_array( 'wpconfig', $protected, true ) ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		else {
			$status[ 'exp' ][] = __( "wp-config.php file isn't protected against tampering.", 'wp-simple-firewall' );
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
		}

		if ( !\in_array( 'root_index', $protected, true ) ) {
			$status[ 'exp' ][] = __( "Root index.php file isn't protected against tampering.", 'wp-simple-firewall' );
		}

		return $status;
	}

	private function applicability() :FileLockKeyApplicability {
		return $this->fileLockKeyApplicability ??= FileLockKeyApplicability::fromCurrentEnvironment();
	}

	/**
	 * @return array<string,array{disabled_message:string,missing_message:string}>
	 */
	private function fileLockDefinitions() :array {
		return [
			'wpconfig' => [
				'disabled_message' => __( "wp-config.php isn't protected against tampering.", 'wp-simple-firewall' ),
				'missing_message'  => __( "wp-config.php is selected but File Locker can't find it, so it isn't protected.", 'wp-simple-firewall' ),
			],
			'theme_functions' => [
				'disabled_message' => __( "Theme functions.php isn't protected against tampering.", 'wp-simple-firewall' ),
				'missing_message'  => __( "Theme functions.php is selected but File Locker can't find it, so it isn't protected.", 'wp-simple-firewall' ),
			],
			'root_htaccess' => [
				'disabled_message' => __( "Root .htaccess isn't protected against tampering.", 'wp-simple-firewall' ),
				'missing_message'  => __( "Root .htaccess is selected but File Locker can't find it, so it isn't protected.", 'wp-simple-firewall' ),
			],
			'root_index' => [
				'disabled_message' => __( "Root index.php isn't protected against tampering.", 'wp-simple-firewall' ),
				'missing_message'  => __( "Root index.php is selected but File Locker can't find it, so it isn't protected.", 'wp-simple-firewall' ),
			],
			'root_webconfig' => [
				'disabled_message' => __( "Root web.config isn't protected against tampering.", 'wp-simple-firewall' ),
				'missing_message'  => __( "Root web.config is selected but File Locker can't find it, so it isn't protected.", 'wp-simple-firewall' ),
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
	 * The selected files File Locker can actually protect: a selected file it can't find isn't protected.
	 * @param list<string> $toLock
	 * @return list<string>
	 */
	private function protectedFiles( array $toLock ) :array {
		return \array_values( \array_filter( $toLock, fn( string $fileKey ) :bool => $this->applicability()->isApplicable( $fileKey ) ) );
	}

	/**
	 * Why File Locker can't work on this site, if it can't: it encrypts each file's copy, and on Pro it also
	 * needs ShieldNet.
	 * @return list<string>
	 */
	private function unmetRequirements() :array {
		$con = self::con();
		$unmet = [];
		// Only what the last check found: checking again here would hold up the page on a remote request.
		if ( $con->comps->file_locker->lastCipherCheckFoundNone() ) {
			$unmet[] = sprintf(
				/* translators: %s: PHP extension name */
				__( "File Locker can't work because the last check found no encryption cipher it can use. It needs the PHP %s extension and a cipher it supports.", 'wp-simple-firewall' ),
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
