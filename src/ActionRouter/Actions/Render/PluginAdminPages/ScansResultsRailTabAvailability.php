<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\GetFileLockCandidateDisplays;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-import-type DisabledPaneAction from ScansResultsViewBuilder
 */
class ScansResultsRailTabAvailability {

	use PluginControllerConsumer;

	/**
	 * @var array<string,array{
	 *   is_available:bool,
	 *   show_in_actions_queue:bool,
	 *   show_in_fix_now:bool,
	 *   disabled_reason:''|'not_enabled'|'upgrade_required',
	 *   disabled_message:string,
	 *   disabled_status:string,
	 *   disabled_actions:list<DisabledPaneAction>
	 * }>
	 */
	private array $states = [];

	/**
	 * On Pro, the critical files left unlocked, which are something to review: how many, and what to say about
	 * them. Null when every file is locked or the plan doesn't include File Locker.
	 * @return array{not_locked_count:int,message:string}|null
	 */
	public function fileLockerReviewState() :?array {
		if ( !self::con()->caps->hasCap( 'scan_file_locker' ) ) {
			return null;
		}

		// Only the critical files this site has, and that apply to how it runs, count; those still to lock are
		// the ones File Locker's panel offers to lock.
		$candidates = new GetFileLockCandidateDisplays();
		$notLocked = \count( $candidates->notSelected() );
		if ( $notLocked < 1 ) {
			return null;
		}
		$lockable = \count( $candidates->run() );
		$locked = $lockable - $notLocked;

		// A warning says what's wrong: the files left unlocked, rather than the ones that are locked.
		return [
			'not_locked_count' => $notLocked,
			'message'          => $locked > 0
				? \sprintf(
					/* translators: %1$s: files not locked, %2$s: files that can be locked */
					_n(
						'%1$s of your %2$s critical files is not locked yet.',
						'%1$s of your %2$s critical files are not locked yet.',
						$notLocked,
						'wp-simple-firewall'
					),
					$notLocked,
					$lockable
				)
				: __( 'None of your critical files are locked yet.', 'wp-simple-firewall' ),
		];
	}

	/**
	 * @return array{
	 *   is_available:bool,
	 *   show_in_actions_queue:bool,
	 *   show_in_fix_now:bool,
	 *   disabled_reason:''|'not_enabled'|'upgrade_required',
	 *   disabled_message:string,
	 *   disabled_status:string,
	 *   disabled_actions:list<DisabledPaneAction>
	 * }
	 */
	public function build( string $tabKey ) :array {
		$tabKey = \strtolower( \trim( $tabKey ) );
		if ( isset( $this->states[ $tabKey ] ) ) {
			return $this->states[ $tabKey ];
		}

		$state = [
			'is_available'          => false,
			'show_in_actions_queue' => false,
			'show_in_fix_now'       => false,
			'disabled_reason'       => '',
			'disabled_message'      => '',
			'disabled_status'       => 'neutral',
			'disabled_actions'      => [],
		];

		if ( $tabKey === 'hidden_plugins' ) {
			$state[ 'is_available' ] = self::con()->caps->canDetectCloakedPlugins();
			$state[ 'show_in_fix_now' ] = $state[ 'is_available' ];
			$state[ 'show_in_actions_queue' ] = $state[ 'is_available' ];
			if ( !$state[ 'is_available' ] ) {
				$state = \array_replace( $state, $this->buildDisabledState(
					'not_enabled',
					$this->buildNotEnabledMessage( __( 'Cloaked Plugin Detection', 'wp-simple-firewall' ) ),
					[]
				) );
			}

			return $this->states[ $tabKey ] = $state;
		}

		$afs = self::con()->comps->scans->AFS();
		$scansCon = self::con()->comps->scans;

		switch ( $tabKey ) {
			case 'wordpress':
				$state[ 'show_in_fix_now' ] = true;
				$state[ 'is_available' ] = $afs->isScanEnabledWpCore();
				$state[ 'show_in_actions_queue' ] = $state[ 'is_available' ];
				if ( !$state[ 'is_available' ] ) {
					$state = \array_replace( $state, $this->buildDisabledState(
						'not_enabled',
						__( 'Detect changes to WordPress core files', 'wp-simple-firewall' ),
						[
							$this->buildEnableAction( __( 'Turn On Scanning', 'wp-simple-firewall' ), 'wordpress' ),
						]
					) );
				}
				break;

			case 'plugins':
				$state[ 'show_in_fix_now' ] = true;
				$state[ 'is_available' ] = $afs->isScanEnabledPlugins();
				$state[ 'show_in_actions_queue' ] = true;
				if ( !$state[ 'is_available' ] ) {
					$state = \array_replace( $state, $this->isPluginThemeScanRestricted()
						? $this->buildDisabledState(
							'upgrade_required',
							$this->buildPluginThemeRestrictedMessage(),
							[
								$this->buildUpgradeAction(),
							]
						)
						: $this->buildDisabledState(
							'not_enabled',
							__( 'Detect changes to plugin files', 'wp-simple-firewall' ),
							[
								$this->buildEnableAction( __( 'Turn On Scanning', 'wp-simple-firewall' ), 'plugins' ),
							]
						) );
				}
				break;

			case 'themes':
				$state[ 'show_in_fix_now' ] = true;
				$state[ 'is_available' ] = $afs->isScanEnabledThemes();
				$state[ 'show_in_actions_queue' ] = true;
				if ( !$state[ 'is_available' ] ) {
					$state = \array_replace( $state, $this->isPluginThemeScanRestricted()
						? $this->buildDisabledState(
							'upgrade_required',
							$this->buildPluginThemeRestrictedMessage(),
							[
								$this->buildUpgradeAction(),
							]
						)
						: $this->buildDisabledState(
							'not_enabled',
							__( 'Detect changes to theme files', 'wp-simple-firewall' ),
							[
								$this->buildEnableAction( __( 'Turn On Scanning', 'wp-simple-firewall' ), 'themes' ),
							]
						) );
				}
				break;

			case 'vulnerabilities':
				$state[ 'show_in_fix_now' ] = true;
				$state[ 'is_available' ] = $scansCon->WPV()->isEnabled();
				$state[ 'show_in_actions_queue' ] = true;
				if ( !$state[ 'is_available' ] ) {
					$state = \array_replace( $state, !self::con()->caps->canScanVulnerabilities()
						? $this->buildDisabledState(
							'upgrade_required',
							$this->buildRestrictedMessage( __( 'Vulnerability Scanning', 'wp-simple-firewall' ) ),
							[
								$this->buildUpgradeAction(),
							]
						)
						: $this->buildDisabledState(
							'not_enabled',
							__( 'Detect vulnerabilities in plugins and themes', 'wp-simple-firewall' ),
							[
								$this->buildEnableAction( __( 'Turn On Scanning', 'wp-simple-firewall' ), 'vulnerabilities' ),
							]
						) );
				}
				break;

			case 'abandoned':
				$state[ 'show_in_fix_now' ] = true;
				$state[ 'is_available' ] = $scansCon->APC()->isEnabled();
				$state[ 'show_in_actions_queue' ] = true;
				if ( !$state[ 'is_available' ] ) {
					$state = \array_replace( $state, $this->buildDisabledState(
						'not_enabled',
						__( 'Identify abandoned plugins and themes', 'wp-simple-firewall' ),
						[
							$this->buildEnableAction( __( 'Turn On Checks', 'wp-simple-firewall' ), 'abandoned' ),
						]
					) );
				}
				break;

			case 'malware':
				$state[ 'show_in_fix_now' ] = true;
				$state[ 'is_available' ] = $afs->isEnabledMalwareScanPHP();
				$state[ 'show_in_actions_queue' ] = true;
				if ( !$state[ 'is_available' ] ) {
					$state = \array_replace( $state, !self::con()->caps->canScanMalwareLocal()
						? $this->buildDisabledState(
							'upgrade_required',
							$this->buildRestrictedMessage( __( 'Malware Scanning', 'wp-simple-firewall' ) ),
							[
								$this->buildUpgradeAction(),
							]
						)
						: $this->buildDisabledState(
							'not_enabled',
							__( 'Detect malicious code in PHP files', 'wp-simple-firewall' ),
							[
								$this->buildEnableAction( __( 'Turn On Scanning', 'wp-simple-firewall' ), 'malware' ),
							]
						) );
				}
				break;

			case 'file_locker':
				$state[ 'show_in_fix_now' ] = true;
				$hasFileLocker = self::con()->caps->hasCap( 'scan_file_locker' );
				$state[ 'is_available' ] = self::con()->comps->file_locker->isEnabled() && $hasFileLocker;
				$state[ 'show_in_actions_queue' ] = $state[ 'is_available' ];
				if ( !$state[ 'is_available' ] ) {
					$state = \array_replace( $state, $hasFileLocker
						? $this->buildDisabledState(
							'not_enabled',
							__( 'Detect changes to critical files', 'wp-simple-firewall' ),
							[]
						)
						: $this->buildDisabledState(
							'upgrade_required',
							\sprintf(
								/* translators: %s: plugin name */
								__( 'File Locker is available only with the Pro version of %s.', 'wp-simple-firewall' ),
								self::con()->labels->Name
							),
							[
								$this->buildUpgradeAction(),
							]
						) );
				}
				break;
		}

		return $this->states[ $tabKey ] = $state;
	}

	/**
	 * @return array{
	 *   disabled_reason:'not_enabled'|'upgrade_required',
	 *   disabled_message:string,
	 *   disabled_status:string,
	 *   disabled_actions:list<DisabledPaneAction>
	 * }
	 */
	private function buildDisabledState( string $reason, string $message, array $actions ) :array {
		return [
			'disabled_reason'  => $reason,
			'disabled_message' => $message,
			'disabled_status'  => 'neutral',
			'disabled_actions' => \array_values( \array_filter( $actions ) ),
		];
	}

	private function buildNotEnabledMessage( string $featureTitle ) :string {
		/* translators: %s: name of the feature */
		return \sprintf( __( '%s is not enabled.', 'wp-simple-firewall' ), $featureTitle );
	}

	private function buildRestrictedMessage( string $featureTitle ) :string {
		return \sprintf(
			/* translators: %1$s: name of the feature, %2$s: plugin name */
			__( '%1$s is available only with the Pro version of %2$s.', 'wp-simple-firewall' ),
			$featureTitle,
			self::con()->labels->Name
		);
	}

	private function buildPluginThemeRestrictedMessage() :string {
		return \sprintf(
			/* translators: %s: plugin name */
			__( 'Scanning Plugin & Theme Files is available only with the Pro version of %s.', 'wp-simple-firewall' ),
			self::con()->labels->Name
		);
	}

	private function isPluginThemeScanRestricted() :bool {
		return !self::con()->caps->canScanPluginsThemesLocal();
	}

	/**
	 * Switching a scan on opens the same one-switch dialog wherever it's offered.
	 * @return DisabledPaneAction
	 */
	private function buildEnableAction( string $label, string $scanKey ) :array {
		return [
			'type'         => 'navigate',
			'label'        => $label,
			'href'         => '',
			'is_action'    => true,
			'icon_class'   => 'bi bi-toggle-on',
			'tooltip_attr' => '',
			'class_name'   => '',
			'target'       => '',
			'rel'          => '',
			'attributes'   => [
				'data-enable-protection' => ( new ProtectionEnableDialogBuilder() )->forScan( $scanKey, 'bi bi-shield-check' ),
				'aria-haspopup'          => 'dialog',
			],
		];
	}

	/**
	 * @return DisabledPaneAction
	 */
	private function buildUpgradeAction() :array {
		return [
			'type'         => 'navigate',
			'label'        => __( 'View Plans', 'wp-simple-firewall' ),
			'href'         => BaseRender::GO_PRO_URL,
			'is_action'    => false,
			'icon_class'   => 'bi bi-arrow-right-circle-fill',
			'tooltip_attr' => '',
			'class_name'   => '',
			'target'       => '_blank',
			'rel'          => 'noopener noreferrer',
			'attributes'   => [],
		];
	}
}
