<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScansEnable;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScansFileLockerEnableFile;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-type ProtectionEnableDialog array{
 *   title:string,description:string,setting_label:string,icon_class:string,
 *   action:array<string,mixed>,path:string,save_label:string,
 *   cancel_label:string,saving_label:string,error_message:string
 * }
 */
class ProtectionEnableDialogBuilder {

	use PluginControllerConsumer;

	public function forScan( string $key, string $iconClass ) :string {
		if ( !self::con()->isPremiumActive() || !isset( ScansEnable::SCANS[ $key ] ) ) {
			return '';
		}
		$copy = [
			'malware' => [ __( 'Enable PHP Malware Scanning', 'wp-simple-firewall' ), __( 'Check PHP files for malicious code.', 'wp-simple-firewall' ), __( 'PHP Malware Scanning', 'wp-simple-firewall' ) ],
			'plugins' => [ __( 'Enable Plugin File Scanning', 'wp-simple-firewall' ), __( 'Detect modified files in your installed plugins.', 'wp-simple-firewall' ), __( 'Plugin File Scanning', 'wp-simple-firewall' ) ],
			'themes' => [ __( 'Enable Theme File Scanning', 'wp-simple-firewall' ), __( 'Detect modified files in your installed themes.', 'wp-simple-firewall' ), __( 'Theme File Scanning', 'wp-simple-firewall' ) ],
			'wordpress' => [ __( 'Enable WordPress File Scanning', 'wp-simple-firewall' ), __( 'Scan WordPress core files for unexpected changes.', 'wp-simple-firewall' ), __( 'WordPress File Scanning', 'wp-simple-firewall' ) ],
			'vulnerabilities' => [ __( 'Enable Vulnerability Scanning', 'wp-simple-firewall' ), __( 'Scan plugins and themes for known vulnerabilities.', 'wp-simple-firewall' ), __( 'Vulnerability Scanning', 'wp-simple-firewall' ) ],
			'abandoned' => [ __( 'Enable Abandoned Assets Scanning', 'wp-simple-firewall' ), __( 'Identify abandoned plugins and themes.', 'wp-simple-firewall' ), __( 'Abandoned Assets Scanning', 'wp-simple-firewall' ) ],
		][ $key ];
		return $this->encode( $copy[ 0 ], $copy[ 1 ], $copy[ 2 ], $iconClass,
			ActionData::Build( ScansEnable::class, true, [ 'scan' => $key ] ), '' );
	}

	public function forFile( string $key, string $path ) :string {
		return $this->encode(
			__( 'Protect This File', 'wp-simple-firewall' ),
			__( 'This file is not currently protected.', 'wp-simple-firewall' ),
			__( 'Enable Protection', 'wp-simple-firewall' ), 'bi bi-file-lock2-fill',
			ActionData::Build( ScansFileLockerEnableFile::class, true, [ 'file_key' => $key ] ), $path
		);
	}

	private function encode( string $title, string $description, string $label, string $icon, array $action, string $path ) :string {
		/** @var ProtectionEnableDialog $dialog */
		$dialog = [
			'title' => $title, 'description' => $description, 'setting_label' => $label,
			'icon_class' => $icon, 'action' => $action, 'path' => $path,
			'save_label' => __( 'Save', 'wp-simple-firewall' ),
			'cancel_label' => __( 'Cancel', 'wp-simple-firewall' ),
			'saving_label' => __( 'Saving…', 'wp-simple-firewall' ),
			'error_message' => __( 'This protection could not be enabled. Please try again.', 'wp-simple-firewall' ),
		];
		return OperatorChromeContract::encodeJson( $dialog );
	}
}
