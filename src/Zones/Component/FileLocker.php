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

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$con = self::con();
		$status = parent::status();

		$toLock = $con->comps->file_locker->getFilesToLock();
		if ( empty( $toLock ) ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
		}
		elseif ( \in_array( 'wpconfig', $toLock ) ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		else {
			$status[ 'exp' ][] = __( "wp-config.php file isn't protected against tampering.", 'wp-simple-firewall' );
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
		}

		if ( !\in_array( 'root_index', $toLock ) ) {
			$status[ 'exp' ][] = __( "Root index.php file isn't protected against tampering.", 'wp-simple-firewall' );
		}

		return $status;
	}

	public function postureSignals() :array {
		$toLock = self::con()->comps->file_locker->getFilesToLock();
		$defs = [
			'wpconfig'    => [ 'slug' => 'scan_enabled_filelocker_wpconfig', 'title' => 'wp-config.php', 'weight' => 6 ],
			'htaccess'    => [ 'slug' => 'scan_enabled_filelocker_htaccess', 'title' => '.htaccess', 'weight' => 5 ],
			'root_index'  => [ 'slug' => 'scan_enabled_filelocker_index', 'title' => 'index.php', 'weight' => 5 ],
			'webconfig'   => [ 'slug' => 'scan_enabled_filelocker_webconfig', 'title' => 'web.config', 'weight' => 5 ],
		];

		$signals = [];
		foreach ( $defs as $fileKey => $definition ) {
			$enabled = \in_array( $fileKey, $toLock, true );
			$signals[] = $this->buildPostureSignal(
				$definition[ 'slug' ],
				sprintf( __( 'Critical File Protection: %s', 'wp-simple-firewall' ), $definition[ 'title' ] ),
				$definition[ 'weight' ],
				$enabled ? $definition[ 'weight' ] : 0,
				$enabled ? 'good' : 'warning',
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
}
