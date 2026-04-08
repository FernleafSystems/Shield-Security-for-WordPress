<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

/**
 * @phpstan-import-type OperatorChromeStepInput from OperatorChromeContract
 * @phpstan-import-type OperatorChromeStep from OperatorChromeContract
 * @phpstan-type ModeShell array{
 *   mode:string,
 *   header_density:string,
 *   home_href:string,
 *   home_label:string,
 *   is_mode_landing:bool,
 *   is_interactive:bool,
 *   use_operator_chrome:bool,
 *   root_step:OperatorChromeStep,
 *   root_step_json:string
 * }
 */
abstract class PageModeLandingBase extends BasePluginAdminPage {

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

	protected function getLandingBadgeStatus() :string {
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
		$status = $this->getLandingBadgeStatus();

		return [
			'breadcrumb_label' => $title,
			'title'            => $title,
			'summary'          => $this->getLandingSubtitle(),
			'focus'            => '',
			'next_step'        => '',
			'icon_class'       => $this->buildLandingIconClass( $this->getLandingIcon() ),
			'badge'            => '',
			'badge_status'     => $status,
			'color_key'        => $this->getLandingChromeColorKey(),
		];
	}

	protected function getLandingChromeColorKey() :string {
		switch ( $this->getLandingMode() ) {
			case PluginNavs::NAV_DASHBOARD:
				$colorKey = 'home';
				break;
			case PluginNavs::MODE_ACTIONS:
				$colorKey = 'actions';
				break;
			case PluginNavs::MODE_CONFIGURE:
				$colorKey = 'configure';
				break;
			case PluginNavs::MODE_INVESTIGATE:
				$colorKey = 'investigate';
				break;
			case PluginNavs::MODE_REPORTS:
				$colorKey = 'reports';
				break;
			default:
				$colorKey = 'neutral';
				break;
		}
		return $colorKey;
	}

	protected function getOperatorHomeLabel() :string {
		return __( 'Dashboard', 'wp-simple-firewall' );
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
					'operator_context_actions' => __( 'Actions', 'wp-simple-firewall' ),
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
				'mode'                => $this->getLandingMode(),
				'header_density'      => $this->getLandingHeaderDensity(),
				'home_href'           => self::con()->plugin_urls->adminHome(),
				'home_label'          => $this->getOperatorHomeLabel(),
				'is_interactive'      => $this->isLandingInteractive(),
				'use_operator_chrome' => $this->usesOperatorChrome(),
				'root_step'           => $this->getOperatorRootStep(),
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
		$homeLabel = \trim( (string)( $modeShell[ 'home_label' ] ?? '' ) );
		if ( $homeLabel === '' ) {
			$homeLabel = $this->getOperatorHomeLabel();
		}

		$rootStep = $this->normalizeOperatorChromeStep( \is_array( $modeShell[ 'root_step' ] ?? null ) ? $modeShell[ 'root_step' ] : [] );

		return [
			'mode'                => sanitize_key( (string)( $modeShell[ 'mode' ] ?? '' ) ),
			'header_density'      => $headerDensity,
			'home_href'           => (string)( $modeShell[ 'home_href' ] ?? '' ),
			'home_label'          => $homeLabel,
			'is_mode_landing'     => true,
			'is_interactive'      => (bool)( $modeShell[ 'is_interactive' ] ?? false ),
			'use_operator_chrome' => (bool)( $modeShell[ 'use_operator_chrome' ] ?? false ),
			'root_step'           => $rootStep,
			'root_step_json'      => OperatorChromeContract::encodeJson( $rootStep ),
		];
	}

	/**
	 * @param OperatorChromeStepInput $step
	 * @return OperatorChromeStep
	 */
	protected function normalizeOperatorChromeStep( array $step ) :array {
		return OperatorChromeContract::normalizeStep( $step );
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

	protected function encodeJson( array $data ) :string {
		return (string)( \json_encode( $data ) ?: '' );
	}

	protected function buildLandingIconClass( string $icon ) :string {
		return self::con()->svgs->iconClass( $icon );
	}
}
