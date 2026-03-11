<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ScansResultsRailTabAvailability {

	use PluginControllerConsumer;

	/**
	 * @return array{
	 *   is_available:bool,
	 *   show_in_actions_queue:bool,
	 *   disabled_message:string,
	 *   disabled_status:string
	 * }
	 */
	public function build( string $tabKey ) :array {
		$tabKey = \strtolower( \trim( $tabKey ) );
		$state = [
			'is_available'        => false,
			'show_in_actions_queue' => false,
			'disabled_message'    => '',
			'disabled_status'     => 'neutral',
		];

		$afs = self::con()->comps->scans->AFS();
		$scansCon = self::con()->comps->scans;

		switch ( $tabKey ) {
			case 'wordpress':
				$state[ 'is_available' ] = $afs->isScanEnabledWpCore();
				$state[ 'show_in_actions_queue' ] = $state[ 'is_available' ];
				break;

			case 'plugins':
				$state[ 'is_available' ] = $afs->isScanEnabledPlugins();
				$state[ 'show_in_actions_queue' ] = true;
				if ( !$state[ 'is_available' ] ) {
					$state[ 'disabled_message' ] = $this->isPluginThemeScanRestricted()
						? $this->buildPluginThemeRestrictedMessage()
						: $this->buildNotEnabledMessage( __( 'Plugin File Scanning', 'wp-simple-firewall' ) );
				}
				break;

			case 'themes':
				$state[ 'is_available' ] = $afs->isScanEnabledThemes();
				$state[ 'show_in_actions_queue' ] = true;
				if ( !$state[ 'is_available' ] ) {
					$state[ 'disabled_message' ] = $this->isPluginThemeScanRestricted()
						? $this->buildPluginThemeRestrictedMessage()
						: $this->buildNotEnabledMessage( __( 'Theme File Scanning', 'wp-simple-firewall' ) );
				}
				break;

			case 'vulnerabilities':
				$state[ 'is_available' ] = $scansCon->WPV()->isEnabled() || $scansCon->APC()->isEnabled();
				$state[ 'show_in_actions_queue' ] = true;
				if ( !$state[ 'is_available' ] ) {
					$state[ 'disabled_message' ] = $this->buildNotEnabledMessage(
						__( 'Vulnerability Scanning', 'wp-simple-firewall' )
					);
				}
				break;

			case 'malware':
				$state[ 'is_available' ] = $afs->isEnabledMalwareScanPHP();
				$state[ 'show_in_actions_queue' ] = true;
				if ( !$state[ 'is_available' ] ) {
					$state[ 'disabled_message' ] = $this->buildNotEnabledMessage(
						__( 'Malware Scanning', 'wp-simple-firewall' )
					);
				}
				break;

			case 'file_locker':
				$state[ 'is_available' ] = true;
				$state[ 'show_in_actions_queue' ] = true;
				break;
		}

		return $state;
	}

	private function buildNotEnabledMessage( string $featureTitle ) :string {
		return \sprintf( __( '%s is not enabled.', 'wp-simple-firewall' ), $featureTitle );
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
}
