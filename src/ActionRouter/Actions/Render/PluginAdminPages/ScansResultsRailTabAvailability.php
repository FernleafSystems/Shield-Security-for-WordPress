<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas\ZoneComponentConfig;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ScansResultsRailTabAvailability {

	use PluginControllerConsumer;

	/**
	 * @phpstan-type DisabledPaneAction array{
	 *   type:string,
	 *   label:string,
	 *   href:string,
	 *   icon_class:string,
	 *   tooltip_attr:string,
	 *   class_name:string,
	 *   target:string,
	 *   rel:string,
	 *   attributes:array<string,string>
	 * }
	 */

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
						$this->buildNotEnabledMessage( __( 'WordPress Core File Scanning', 'wp-simple-firewall' ) ),
						[
							$this->buildZoneComponentAction(
								__( 'Turn On Scanning', 'wp-simple-firewall' ),
								'file_scanning',
								[ 'enable_core_file_integrity_scan', 'file_scan_areas' ],
								'enable_core_file_integrity_scan'
							),
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
							$this->buildNotEnabledMessage( __( 'Plugin File Scanning', 'wp-simple-firewall' ) ),
							[
								$this->buildZoneComponentAction(
									__( 'Turn On Scanning', 'wp-simple-firewall' ),
									'file_scanning',
									[ 'enable_core_file_integrity_scan', 'file_scan_areas' ],
									'file_scan_areas'
								),
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
							$this->buildNotEnabledMessage( __( 'Theme File Scanning', 'wp-simple-firewall' ) ),
							[
								$this->buildZoneComponentAction(
									__( 'Turn On Scanning', 'wp-simple-firewall' ),
									'file_scanning',
									[ 'enable_core_file_integrity_scan', 'file_scan_areas' ],
									'file_scan_areas'
								),
							]
						) );
				}
				break;

			case 'vulnerabilities':
				$state[ 'show_in_fix_now' ] = true;
				$state[ 'is_available' ] = $scansCon->WPV()->isEnabled();
				$state[ 'show_in_actions_queue' ] = true;
				if ( !$state[ 'is_available' ] ) {
					$state = \array_replace( $state, $scansCon->WPV()->isRestricted()
						? $this->buildDisabledState(
							'upgrade_required',
							$this->buildRestrictedMessage( __( 'Vulnerability Scanning', 'wp-simple-firewall' ) ),
							[
								$this->buildUpgradeAction(),
							]
						)
						: $this->buildDisabledState(
							'not_enabled',
							$this->buildNotEnabledMessage( __( 'Vulnerability Scanning', 'wp-simple-firewall' ) ),
							[
								$this->buildZoneComponentAction(
									__( 'Turn On Scanning', 'wp-simple-firewall' ),
									'vulnerability_scanning',
									[ 'enable_wpvuln_scan' ],
									'enable_wpvuln_scan'
								),
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
						$this->buildNotEnabledMessage( __( 'Abandoned Asset Scanning', 'wp-simple-firewall' ) ),
						[
							$this->buildZoneComponentAction(
								__( 'Turn On Checks', 'wp-simple-firewall' ),
								'vulnerability_scanning',
								[ 'enabled_scan_apc' ],
								'enabled_scan_apc'
							),
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
							$this->buildNotEnabledMessage( __( 'Malware Scanning', 'wp-simple-firewall' ) ),
							[
								$this->buildZoneComponentAction(
									__( 'Turn On Scanning', 'wp-simple-firewall' ),
									'file_scanning',
									[ 'enable_core_file_integrity_scan', 'file_scan_areas' ],
									'file_scan_areas'
								),
							]
						) );
				}
				break;

			case 'file_locker':
				$state[ 'show_in_fix_now' ] = true;
				$state[ 'is_available' ] = self::con()->comps->file_locker->isEnabled()
					&& self::con()->isPremiumActive();
				$state[ 'show_in_actions_queue' ] = $state[ 'is_available' ];
				if ( !$state[ 'is_available' ] ) {
					$state = \array_replace( $state, self::con()->isPremiumActive()
						? $this->buildDisabledState(
							'not_enabled',
							$this->buildNotEnabledMessage( __( 'File Locker', 'wp-simple-firewall' ) ),
							[
								$this->buildZoneComponentAction(
									__( 'Protect Files', 'wp-simple-firewall' ),
									'file_locker',
									[ 'file_locker' ],
									'file_locker'
								),
							]
						)
						: $this->buildDisabledState(
							'upgrade_required',
							\sprintf(
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
		return \sprintf( __( '%s is not enabled.', 'wp-simple-firewall' ), $featureTitle );
	}

	private function buildRestrictedMessage( string $featureTitle ) :string {
		return \sprintf(
			__( '%s is available only with the Pro version of %s.', 'wp-simple-firewall' ),
			$featureTitle,
			self::con()->labels->Name
		);
	}

	private function buildPluginThemeRestrictedMessage() :string {
		return \sprintf(
			__( 'Scanning Plugin & Theme Files is available only with the Pro version of %s.', 'wp-simple-firewall' ),
			self::con()->labels->Name
		);
	}

	private function isPluginThemeScanRestricted() :bool {
		return !self::con()->isPremiumActive() || !self::con()->caps->canScanPluginsThemesLocal();
	}

	/**
	 * @param list<string> $optionKeys
	 * @return DisabledPaneAction
	 */
	private function buildZoneComponentAction(
		string $label,
		string $zoneComponentSlug,
		array $optionKeys = [],
		string $configItem = ''
	) :array {
		$attributes = [
			'data-zone_component_action' => ZoneComponentConfig::SLUG,
			'data-zone_component_slug'   => $zoneComponentSlug,
			'data-form_context'          => 'offcanvas',
		];
		if ( !empty( $optionKeys ) ) {
			$attributes[ 'data-option_keys' ] = \implode( ',', $optionKeys );
		}
		if ( $configItem !== '' ) {
			$attributes[ 'data-config_item' ] = $configItem;
		}

		return [
			'type'         => 'navigate',
			'label'        => $label,
			'href'         => 'javascript:{}',
			'icon_class'   => 'bi bi-gear-fill',
			'tooltip_attr' => '',
			'class_name'   => 'zone_component_action',
			'target'       => '',
			'rel'          => '',
			'attributes'   => $attributes,
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
			'icon_class'   => 'bi bi-arrow-right-circle-fill',
			'tooltip_attr' => '',
			'class_name'   => '',
			'target'       => '_blank',
			'rel'          => 'noopener noreferrer',
			'attributes'   => [],
		];
	}
}
