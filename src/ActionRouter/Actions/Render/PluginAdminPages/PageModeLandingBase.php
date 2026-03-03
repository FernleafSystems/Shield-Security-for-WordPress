<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

abstract class PageModeLandingBase extends BasePluginAdminPage {

	private const VALID_ACCENT_STATUSES = [
		'good',
		'warning',
		'critical',
		'info',
		'neutral',
	];
	private const VALID_HEADER_DENSITIES = [
		'compact',
		'default',
	];

	abstract protected function getLandingTitle() :string;

	abstract protected function getLandingSubtitle() :string;

	abstract protected function getLandingIcon() :string;

	protected function getLandingMode() :string {
		return '';
	}

	protected function getLandingAccentStatus() :string {
		switch ( $this->getLandingMode() ) {
			case PluginNavs::MODE_CONFIGURE:
				$status = 'good';
				break;
			case PluginNavs::MODE_INVESTIGATE:
				$status = 'info';
				break;
			case PluginNavs::MODE_REPORTS:
				$status = 'warning';
				break;
			case PluginNavs::MODE_ACTIONS:
				$status = 'critical';
				break;
			default:
				$status = 'neutral';
				break;
		}
		return $status;
	}

	protected function getLandingHeaderDensity() :string {
		return 'compact';
	}

	protected function isLandingInteractive() :bool {
		return false;
	}

	protected function getLandingTiles() :array {
		return [];
	}

	protected function getLandingPanel() :array {
		return [];
	}

	protected function getLandingContent() :array {
		return [];
	}

	protected function getLandingFlags() :array {
		return [];
	}

	protected function getLandingHrefs() :array {
		return [];
	}

	protected function getLandingStrings() :array {
		return [];
	}

	protected function getLandingVars() :array {
		return [];
	}

	protected function getRenderData() :array {
		$strings = [
			'inner_page_title'    => $this->getLandingTitle(),
			'inner_page_subtitle' => $this->getLandingSubtitle(),
		];
		if ( $this->isModeLandingPage() ) {
			$strings = \array_merge(
				$strings,
				[
					'mode_panel_title' => __( 'Details', 'wp-simple-firewall' ),
					'mode_panel_close' => __( 'Close', 'wp-simple-firewall' ),
				]
			);
		}

		$data = [
			'imgs'    => [
				'inner_page_title_icon' => $this->buildLandingIconClass( $this->getLandingIcon() ),
			],
			'strings' => \array_merge(
				$strings,
				$this->getLandingStrings()
			),
		];

		$content = $this->getLandingContent();
		if ( !empty( $content ) ) {
			$data[ 'content' ] = $content;
		}

		$flags = $this->getLandingFlags();
		if ( !empty( $flags ) ) {
			$data[ 'flags' ] = $flags;
		}

		$hrefs = $this->getLandingHrefs();
		if ( !empty( $hrefs ) ) {
			$data[ 'hrefs' ] = $hrefs;
		}

		$vars = $this->getLandingVars();
		$modeContractVars = $this->getModeContractVars();
		if ( !empty( $modeContractVars ) ) {
			$vars = \array_merge( $modeContractVars, $vars );
		}
		if ( !empty( $vars ) ) {
			$data[ 'vars' ] = $vars;
		}

		return $data;
	}

	protected function getModeContractVars() :array {
		if ( !$this->isModeLandingPage() ) {
			return [];
		}
		return [
			'mode_shell' => $this->normalizeLandingModeShell( [
				'mode'           => $this->getLandingMode(),
				'accent_status'  => $this->getLandingAccentStatus(),
				'header_density' => $this->getLandingHeaderDensity(),
				'is_interactive' => $this->isLandingInteractive(),
			] ),
			'mode_tiles' => $this->normalizeLandingTiles( $this->getLandingTiles() ),
			'mode_panel' => $this->normalizeLandingPanel( $this->getLandingPanel() ),
		];
	}

	private function isModeLandingPage() :bool {
		return !empty( $this->getLandingMode() );
	}

	private function normalizeLandingModeShell( array $modeShell ) :array {
		$headerDensity = (string)( $modeShell[ 'header_density' ] ?? '' );
		if ( !\in_array( $headerDensity, self::VALID_HEADER_DENSITIES, true ) ) {
			$headerDensity = 'compact';
		}

		return [
			'mode'           => sanitize_key( (string)( $modeShell[ 'mode' ] ?? '' ) ),
			'accent_status'  => $this->sanitizeModeAccentStatus( (string)( $modeShell[ 'accent_status' ] ?? '' ) ),
			'header_density' => $headerDensity,
			'is_mode_landing' => true,
			'is_interactive' => (bool)( $modeShell[ 'is_interactive' ] ?? false ),
		];
	}

	private function normalizeLandingTiles( array $tiles ) :array {
		$normalized = [];
		foreach ( $tiles as $tile ) {
			if ( !\is_array( $tile ) ) {
				continue;
			}

			$key = sanitize_key( (string)( $tile[ 'key' ] ?? '' ) );
			if ( empty( $key ) ) {
				continue;
			}

			$isEnabled = \array_key_exists( 'is_enabled', $tile ) ? (bool)$tile[ 'is_enabled' ] : true;
			$panelTarget = sanitize_key( (string)( $tile[ 'panel_target' ] ?? '' ) );
			if ( empty( $panelTarget ) ) {
				$panelTarget = $key;
			}

			$tile[ 'key' ] = $key;
			$tile[ 'panel_target' ] = $panelTarget;
			$tile[ 'is_enabled' ] = $isEnabled;
			$tile[ 'is_disabled' ] = !$isEnabled;

			$normalized[] = $tile;
		}
		return $normalized;
	}

	private function normalizeLandingPanel( array $panel ) :array {
		$activeTarget = sanitize_key( (string)( $panel[ 'active_target' ] ?? '' ) );
		$panel[ 'active_target' ] = $activeTarget;
		$panel[ 'is_open' ] = !empty( $activeTarget ) || (bool)( $panel[ 'is_open' ] ?? false );

		$closeLabel = (string)( $panel[ 'close_label' ] ?? '' );
		if ( empty( $closeLabel ) ) {
			$closeLabel = __( 'Close', 'wp-simple-firewall' );
		}
		$panel[ 'close_label' ] = $closeLabel;
		return $panel;
	}

	private function sanitizeModeAccentStatus( string $status ) :string {
		$status = sanitize_key( $status );
		if ( !\in_array( $status, self::VALID_ACCENT_STATUSES, true ) ) {
			$status = 'neutral';
		}
		return $status;
	}

	protected function buildLandingIconClass( string $icon ) :string {
		return self::con()->svgs->iconClass( $icon );
	}
}
