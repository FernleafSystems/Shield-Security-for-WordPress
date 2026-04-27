<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class FileLocker extends Base {

	public function title() :string {
		return __( 'FileLocker: wp-config.php Protection', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( "Protect key WP core files that can't normally be protected.", 'wp-simple-firewall' );
	}

	protected function tooltip() :string {
		return __( 'Edit which key files to protect', 'wp-simple-firewall' );
	}

	protected function configureStatus() :array {
		$toLock = $this->selectedLockedFiles();
		$status = parent::status();

		if ( empty( $toLock ) ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( 'FileLocker is not configured for any files.', 'wp-simple-firewall' );
		}
		if ( !\in_array( 'wpconfig', $toLock, true ) ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( "wp-config.php isn't protected against tampering.", 'wp-simple-firewall' );
		}

		foreach ( $this->fileLockDefinitions() as $fileKey => $definition ) {
			if ( $fileKey !== 'wpconfig' && !\in_array( $fileKey, $toLock, true ) ) {
				$status[ 'exp' ][] = $definition[ 'disabled_message' ];
			}
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

	public function postureSignals() :array {
		$toLock = $this->selectedLockedFiles();
		$signals = [];
		foreach ( $this->fileLockDefinitions() as $fileKey => $definition ) {
			$enabled = \in_array( $fileKey, $toLock, true );
			$signals[] = $this->buildPostureSignal(
				$definition[ 'slug' ],
				sprintf( __( 'Critical File Protection: %s', 'wp-simple-firewall' ), $definition[ 'title' ] ),
				$definition[ 'weight' ],
				$enabled ? $definition[ 'weight' ] : 0,
				$enabled ? 'good' : $definition[ 'severity' ],
				$enabled,
				[
					$enabled
						? sprintf( __( '%s is protected against tampering.', 'wp-simple-firewall' ), $definition[ 'title' ] )
						: sprintf( __( '%s is not protected against tampering.', 'wp-simple-firewall' ), $definition[ 'title' ] ),
				]
			);
		}
		return $signals;
	}

	/**
	 * @return array<string,array{slug:string,title:string,weight:int,severity:string,disabled_message:string}>
	 */
	private function fileLockDefinitions() :array {
		return [
			'wpconfig' => [
				'slug'             => 'scan_enabled_filelocker_wpconfig',
				'title'            => 'wp-config.php',
				'weight'           => 6,
				'severity'         => 'critical',
				'disabled_message' => __( "wp-config.php isn't protected against tampering.", 'wp-simple-firewall' ),
			],
			'theme_functions' => [
				'slug'             => 'scan_enabled_filelocker_theme_functions',
				'title'            => 'functions.php',
				'weight'           => 3,
				'severity'         => 'warning',
				'disabled_message' => __( "Theme functions.php isn't protected against tampering.", 'wp-simple-firewall' ),
			],
			'root_htaccess' => [
				'slug'             => 'scan_enabled_filelocker_htaccess',
				'title'            => '.htaccess',
				'weight'           => 5,
				'severity'         => 'warning',
				'disabled_message' => __( "Root .htaccess isn't protected against tampering.", 'wp-simple-firewall' ),
			],
			'root_index' => [
				'slug'             => 'scan_enabled_filelocker_index',
				'title'            => 'index.php',
				'weight'           => 5,
				'severity'         => 'warning',
				'disabled_message' => __( "Root index.php isn't protected against tampering.", 'wp-simple-firewall' ),
			],
			'root_webconfig' => [
				'slug'             => 'scan_enabled_filelocker_webconfig',
				'title'            => 'web.config',
				'weight'           => 5,
				'severity'         => 'warning',
				'disabled_message' => __( "Root web.config isn't protected against tampering.", 'wp-simple-firewall' ),
			],
		];
	}

	/**
	 * @return list<string>
	 */
	private function selectedLockedFiles() :array {
		return \array_values( \array_filter( self::con()->comps->file_locker->getFilesToLock(), '\is_string' ) );
	}
}
