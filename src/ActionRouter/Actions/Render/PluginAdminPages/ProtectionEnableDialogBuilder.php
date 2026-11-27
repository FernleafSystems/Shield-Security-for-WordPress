<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginFeaturesEnable;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScansEnable;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScansFileLockerEnableFile;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\BuildOptionsForDisplay;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * The dialog that switches a protection on. It lists one setting or several, each as it is now: off and able to
 * be turned on, already on, or locked because the site's plan doesn't include it. One can be required, so nothing
 * is saved while it is off. Saving sends the keys of those turned on.
 *
 * @phpstan-type ProtectionEnableSetting array{
 *   key:string,
 *   label:string,
 *   state:'off'|'on'|'locked',
 *   is_required:bool,
 *   is_nested:bool
 * }
 * @phpstan-type ProtectionEnableDialog array{
 *   title:string,description:string,icon_class:string,notice:string,
 *   settings:list<ProtectionEnableSetting>,locked_label:string,
 *   action:array<string,mixed>,path:string,save_label:string,
 *   cancel_label:string,saving_label:string,error_message:string
 * }
 */
class ProtectionEnableDialogBuilder {

	use PluginControllerConsumer;

	/**
	 * The kinds of file the file scanner covers, in the order they're listed.
	 */
	private const FILE_SCANS = [ 'wordpress', 'plugins', 'themes', 'malware' ];

	/**
	 * The checks made on plugins and themes as a whole, rather than on their files.
	 */
	private const ASSET_CHECKS = [ 'abandoned', 'vulnerabilities' ];

	/**
	 * Scans of one kind are switched on from one dialog, whichever of them it was opened from.
	 */
	public function forScan( string $key ) :string {
		if ( \in_array( $key, self::FILE_SCANS, true ) ) {
			$dialog = $this->forFileScans();
		}
		elseif ( \in_array( $key, self::ASSET_CHECKS, true ) ) {
			$dialog = $this->forAssetChecks();
		}
		else {
			$dialog = '';
		}
		return $dialog;
	}

	public function forFile( string $key, string $path ) :string {
		return $this->encode(
			__( 'Protect This File', 'wp-simple-firewall' ),
			__( 'This file is not currently protected.', 'wp-simple-firewall' ),
			'bi bi-file-lock2-fill',
			[ $this->setting( 'protection', __( 'Enable Protection', 'wp-simple-firewall' ), 'off' ) ],
			ActionData::Build( ScansFileLockerEnableFile::class, true, [ 'file_key' => $key ] ),
			$path
		);
	}

	/**
	 * What turning all protection back on is called, both on the dialog and on whatever opens it.
	 */
	public function pluginTitle() :string {
		return sprintf(
			/* translators: %s: plugin name */
			__( 'Turn %s protection back on', 'wp-simple-firewall' ),
			self::con()->labels->Name
		);
	}

	public function forPlugin() :string {
		$name = self::con()->labels->Name;
		return $this->encode(
			$this->pluginTitle(),
			sprintf(
				/* translators: %s: plugin name */
				__( 'All protection offered by %s is switched off.', 'wp-simple-firewall' ),
				$name
			),
			'bi bi-shield-check',
			[
				$this->setting(
					'protection',
					sprintf(
						/* translators: %s: plugin name */
						__( 'Enable %s Protection', 'wp-simple-firewall' ),
						$name
					),
					'off'
				),
			],
			ActionData::Build( PluginFeaturesEnable::class, true )
		);
	}

	/**
	 * The file scanner's own switch is only listed while it is off, with the kinds of file beneath it. A kind
	 * already chosen shows as on even then: it is scanned again as soon as the scanner is.
	 */
	private function forFileScans() :string {
		$afs = self::con()->comps->scans->AFS();
		$isScannerOff = !$afs->isEnabled();
		$chosenAreas = $afs->getFileScanAreas();
		$labels = [
			'wordpress' => __( 'WordPress core files', 'wp-simple-firewall' ),
			'plugins'   => __( 'Plugin files', 'wp-simple-firewall' ),
			'themes'    => __( 'Theme files', 'wp-simple-firewall' ),
			'malware'   => __( 'PHP malware', 'wp-simple-firewall' ),
		];

		$settings = $isScannerOff
			? [ $this->setting( ScansEnable::FILE_SCANNING, __( 'File scanning', 'wp-simple-firewall' ), 'off', true ) ]
			: [];
		foreach ( self::FILE_SCANS as $key ) {
			$settings[] = $this->scanSetting(
				$key,
				$labels[ $key ],
				\in_array( ScansEnable::SCANS[ $key ][ 'value' ], $chosenAreas, true ),
				$isScannerOff
			);
		}

		return $this->encode(
			__( 'Turn on file scanning', 'wp-simple-firewall' ),
			sprintf(
				/* translators: %s: plugin name */
				__( "Select %s's file scanning areas", 'wp-simple-firewall' ),
				self::con()->labels->Name
			),
			'bi bi-file-earmark-code',
			$settings,
			ActionData::Build( ScansEnable::class, true ),
			'',
			$isScannerOff ? __( 'File scanning is currently switched off for the whole site.', 'wp-simple-firewall' ) : ''
		);
	}

	private function forAssetChecks() :string {
		$scans = self::con()->comps->scans;
		$checks = [
			'abandoned'       => [ __( 'Abandoned plugins and themes', 'wp-simple-firewall' ), $scans->APC()->isEnabled() ],
			'vulnerabilities' => [ __( 'Known vulnerabilities', 'wp-simple-firewall' ), $scans->WPV()->isEnabled() ],
		];

		$settings = [];
		foreach ( self::ASSET_CHECKS as $key ) {
			[ $label, $isOn ] = $checks[ $key ];
			$settings[] = $this->scanSetting( $key, $label, $isOn, false );
		}

		return $this->encode(
			__( 'Turn on plugin and theme checks', 'wp-simple-firewall' ),
			sprintf(
				/* translators: %s: plugin name */
				__( "Configure %s's vulnerability scanner", 'wp-simple-firewall' ),
				self::con()->labels->Name
			),
			'bi bi-plug',
			$settings,
			ActionData::Build( ScansEnable::class, true )
		);
	}

	/**
	 * @return ProtectionEnableSetting
	 */
	private function scanSetting( string $key, string $label, bool $isOn, bool $isNested ) :array {
		$state = ScansEnable::isInPlan( $key ) ? ( $isOn ? 'on' : 'off' ) : 'locked';
		return $this->setting( $key, $label, $state, false, $isNested );
	}

	/**
	 * @param 'off'|'on'|'locked' $state
	 * @return ProtectionEnableSetting
	 */
	private function setting( string $key, string $label, string $state, bool $isRequired = false, bool $isNested = false ) :array {
		return [
			'key'         => $key,
			'label'       => $label,
			'state'       => $state,
			'is_required' => $isRequired,
			'is_nested'   => $isNested,
		];
	}

	/**
	 * @param list<ProtectionEnableSetting> $settings
	 */
	private function encode( string $title, string $description, string $icon, array $settings, array $action, string $path = '', string $notice = '' ) :string {
		/** @var ProtectionEnableDialog $dialog */
		$dialog = [
			'title' => $title, 'description' => $description, 'icon_class' => $icon, 'notice' => $notice,
			'settings' => $settings, 'locked_label' => BuildOptionsForDisplay::outsidePlanLabel(),
			'action' => $action, 'path' => $path,
			'save_label' => __( 'Save', 'wp-simple-firewall' ),
			'cancel_label' => __( 'Cancel', 'wp-simple-firewall' ),
			'saving_label' => __( 'Saving…', 'wp-simple-firewall' ),
			'error_message' => __( 'This protection could not be enabled. Please try again.', 'wp-simple-firewall' ),
		];
		return OperatorChromeContract::encodeJson( $dialog );
	}
}
