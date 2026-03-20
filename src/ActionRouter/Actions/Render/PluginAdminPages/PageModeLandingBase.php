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
	/**
	 * @phpstan-type OperatorChromeStepInput array{
	 *   breadcrumb_label?:string,
	 *   title?:string,
	 *   summary?:string,
	 *   focus?:string,
	 *   next_step?:string,
	 *   icon_class?:string,
	 *   badge?:string,
	 *   badge_status?:string,
	 *   color_key?:string
	 * }
	 * @phpstan-type OperatorChromeStep array{
	 *   breadcrumb_label:string,
	 *   title:string,
	 *   summary:string,
	 *   focus:string,
	 *   next_step:string,
	 *   icon_class:string,
	 *   badge:string,
	 *   badge_status:string,
	 *   color_key:string
	 * }
	 */

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

	/**
	 * @return OperatorChromeStepInput
	 */
	protected function getOperatorRootStep() :array {
		$title = $this->getLandingTitle();
		$status = $this->getLandingAccentStatus();

		return [
			'breadcrumb_label' => $title,
			'title'            => $title,
			'summary'          => $this->getLandingSubtitle(),
			'focus'            => '',
			'next_step'        => '',
			'icon_class'       => $this->buildLandingIconClass( $this->getLandingIcon() ),
			'badge'            => '',
			'badge_status'     => $status,
			'color_key'        => $status,
		];
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

	protected function usesOperatorChrome() :bool {
		return $this->isModeLandingPage();
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
					'mode_panel_title'         => __( 'Details', 'wp-simple-firewall' ),
					'mode_panel_close'         => __( 'Close', 'wp-simple-firewall' ),
					'operator_context_title'   => __( 'Current Context', 'wp-simple-firewall' ),
					'operator_context_focus'   => __( 'Focus', 'wp-simple-firewall' ),
					'operator_context_next'    => __( 'Next Step', 'wp-simple-firewall' ),
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
				'mode'               => $this->getLandingMode(),
				'accent_status'      => $this->getLandingAccentStatus(),
				'header_density'     => $this->getLandingHeaderDensity(),
				'is_interactive'     => $this->isLandingInteractive(),
				'use_operator_chrome' => $this->usesOperatorChrome(),
				'root_step'          => $this->getOperatorRootStep(),
			] ),
			'mode_tiles' => $this->normalizeLandingTiles( $this->getLandingTiles() ),
			'mode_panel' => $this->normalizeLandingPanel( $this->getLandingPanel() ),
		];
	}

	protected function isModeLandingPage() :bool {
		return !empty( $this->getLandingMode() );
	}

	private function normalizeLandingModeShell( array $modeShell ) :array {
		$headerDensity = (string)( $modeShell[ 'header_density' ] ?? '' );
		if ( !\in_array( $headerDensity, self::VALID_HEADER_DENSITIES, true ) ) {
			$headerDensity = 'compact';
		}

		$rootStep = $this->normalizeOperatorChromeStep( \is_array( $modeShell[ 'root_step' ] ?? null ) ? $modeShell[ 'root_step' ] : [] );

		return [
			'mode'               => sanitize_key( (string)( $modeShell[ 'mode' ] ?? '' ) ),
			'accent_status'      => $this->sanitizeModeAccentStatus( (string)( $modeShell[ 'accent_status' ] ?? '' ) ),
			'header_density'     => $headerDensity,
			'is_mode_landing'    => true,
			'is_interactive'     => (bool)( $modeShell[ 'is_interactive' ] ?? false ),
			'use_operator_chrome' => (bool)( $modeShell[ 'use_operator_chrome' ] ?? false ),
			'root_step'          => $rootStep,
			'root_step_json'     => $this->encodeJson( $rootStep ),
		];
	}

	/**
	 * @param OperatorChromeStepInput $step
	 * @return OperatorChromeStep
	 */
	protected function normalizeOperatorChromeStep( array $step ) :array {
		$badgeStatus = $this->sanitizeModeAccentStatus( (string)( $step[ 'badge_status' ] ?? '' ) );
		$colorKey = $this->sanitizeModeAccentStatus( (string)( $step[ 'color_key' ] ?? $badgeStatus ) );

		return [
			'breadcrumb_label' => \trim( (string)( $step[ 'breadcrumb_label' ] ?? '' ) ),
			'title'            => \trim( (string)( $step[ 'title' ] ?? '' ) ),
			'summary'          => \trim( (string)( $step[ 'summary' ] ?? '' ) ),
			'focus'            => \trim( (string)( $step[ 'focus' ] ?? '' ) ),
			'next_step'        => \trim( (string)( $step[ 'next_step' ] ?? '' ) ),
			'icon_class'       => \trim( (string)( $step[ 'icon_class' ] ?? '' ) ),
			'badge'            => \trim( (string)( $step[ 'badge' ] ?? '' ) ),
			'badge_status'     => $badgeStatus,
			'color_key'        => $colorKey,
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

	protected function encodeJson( array $data ) :string {
		return (string)( \json_encode( $data ) ?: '' );
	}

	protected function buildLandingIconClass( string $icon ) :string {
		return self::con()->svgs->iconClass( $icon );
	}
}
