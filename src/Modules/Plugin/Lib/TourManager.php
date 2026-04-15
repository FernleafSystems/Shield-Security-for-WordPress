<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class TourManager {

	use PluginControllerConsumer;

	public const TOUR_DASHBOARD = 'dashboard_v1';

	public function getAllTours() :array {
		return [
			self::TOUR_DASHBOARD,
		];
	}

	/**
	 * @return array{
	 *   key:string,
	 *   is_available:bool,
	 *   steps:list<array{selector:string,title:string,intro:string,position:string,required:bool}>,
	 *   options:array<string,mixed>
	 * }
	 */
	public function getTour() :array {
		$tourKey = self::TOUR_DASHBOARD;
		return [
			'key'          => $tourKey,
			'is_available' => $this->isTourAvailable( $tourKey ),
			'steps'        => $this->getDashboardTourSteps(),
			'options'      => [
				'overlayOpacity'  => 0.7,
				'highlightClass'  => 'shield-dashboard-tour-highlight',
				'tooltipClass'    => 'shield_tour_tooltip',
				'showProgress'    => true,
				'showBullets'     => true,
				'scrollToElement' => true,
				'scrollPadding'   => 40,
			],
		];
	}

	public function setCompleted( string $tourKey ) :bool {
		$tourKey = sanitize_key( $tourKey );
		$meta = self::con()->user_metas->current();
		if ( empty( $tourKey ) || empty( $meta ) || !\in_array( $tourKey, $this->getAllTours(), true ) ) {
			return false;
		}

		$tours = $this->getUserTourStates();
		$tours[ $tourKey ] = Services::Request()->ts();
		$meta->tours = \array_intersect_key( $tours, \array_flip( $this->getAllTours() ) );
		return true;
	}

	public function getUserTourStates() :array {
		$meta = self::con()->user_metas->current();
		return ( !empty( $meta ) && \is_array( $meta->tours ) ) ? $meta->tours : [];
	}

	public function userSeenTour( string $tour ) :bool {
		return ( $this->getUserTourStates()[ $tour ] ?? 0 ) > 0;
	}

	private function isTourAvailable( string $tourKey ) :bool {
		return \in_array( $tourKey, $this->getAllTours(), true )
			   && $this->isLaunchAllowed()
			   && ( $this->isForcedTour( $tourKey ) || !$this->userSeenTour( $tourKey ) );
	}

	private function isLaunchAllowed() :bool {
		if ( !self::con()->isPluginAdminPageRequest() || !self::con()->isPluginAdmin() ) {
			return false;
		}

		$req = Services::Request();
		$nav = sanitize_key( (string)$req->query( PluginNavs::FIELD_NAV ) );
		if ( empty( $nav ) ) {
			$nav = PluginNavs::NAV_DASHBOARD;
		}
		if ( $nav !== PluginNavs::NAV_DASHBOARD ) {
			return false;
		}

		$subNav = sanitize_key( (string)$req->query( PluginNavs::FIELD_SUBNAV ) );
		if ( empty( $subNav ) ) {
			$subNav = PluginNavs::SUBNAV_DASHBOARD_OVERVIEW;
		}
		return $subNav === PluginNavs::SUBNAV_DASHBOARD_OVERVIEW;
	}

	private function isForcedTour( string $tourKey ) :bool {
		$forceTour = sanitize_key( (string)Services::Request()->query( 'force_tour' ) );
		return $forceTour === '1' || $forceTour === $tourKey;
	}

	private function getDashboardTourSteps() :array {
		return [
			[
				'selector' => '[data-shield-tour="sidebar-menu"]',
				'title'    => __( 'Sidebar Menu', 'wp-simple-firewall' ),
				'intro'    => __( 'Use the sidebar to move between Shield operator areas without leaving the dashboard.', 'wp-simple-firewall' ),
				'position' => 'right',
				'required' => true,
			],
			[
				'selector' => '[data-shield-tour="dashboard-actions"]',
				'title'    => __( 'Actions Queue', 'wp-simple-firewall' ),
				'intro'    => __( 'Start here when Shield has security actions that need your attention.', 'wp-simple-firewall' ),
				'position' => 'bottom',
				'required' => true,
			],
			[
				'selector' => '[data-shield-tour="dashboard-queue"]',
				'title'    => __( 'Queue Details', 'wp-simple-firewall' ),
				'intro'    => __( 'When there are queued items, Shield groups them here so you can choose what to handle first.', 'wp-simple-firewall' ),
				'position' => 'bottom',
				'required' => false,
			],
			[
				'selector' => '[data-shield-tour="dashboard-investigate"]',
				'title'    => __( 'Investigate', 'wp-simple-firewall' ),
				'intro'    => __( 'Use Investigate for deeper review of users, IPs, plugins, themes, and site activity.', 'wp-simple-firewall' ),
				'position' => 'left',
				'required' => true,
			],
			[
				'selector' => '[data-shield-tour="dashboard-configure"]',
				'title'    => __( 'Configure', 'wp-simple-firewall' ),
				'intro'    => __( 'Use Configure to tune Shield protections and security zones.', 'wp-simple-firewall' ),
				'position' => 'left',
				'required' => true,
			],
			[
				'selector' => '[data-shield-tour="dashboard-reports"]',
				'title'    => __( 'Reports', 'wp-simple-firewall' ),
				'intro'    => __( 'Use Reports to review delivered reports, reporting settings, and security trends.', 'wp-simple-firewall' ),
				'position' => 'left',
				'required' => true,
			],
			[
				'selector' => '[data-shield-tour="dashboard-live-monitor"]',
				'title'    => __( 'Live Monitor', 'wp-simple-firewall' ),
				'intro'    => __( 'The live monitor shows recent WordPress activity and traffic while you work.', 'wp-simple-firewall' ),
				'position' => 'top',
				'required' => true,
			],
			[
				'selector' => '[data-shield-tour="context-box"]',
				'title'    => __( 'Context Box', 'wp-simple-firewall' ),
				'intro'    => __( 'The context box explains the current area, focus, and next step.', 'wp-simple-firewall' ),
				'position' => 'left',
				'required' => true,
			],
			[
				'selector' => '[data-shield-tour="breadcrumbs"]',
				'title'    => __( 'Breadcrumbs', 'wp-simple-firewall' ),
				'intro'    => __( 'Breadcrumbs show where you are and let you move back through dashboard layers.', 'wp-simple-firewall' ),
				'position' => 'bottom',
				'required' => true,
			],
		];
	}
}
