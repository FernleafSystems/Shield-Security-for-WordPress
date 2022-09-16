<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Changelog\Retrieve;

class PageDocs extends BasePluginAdminPage {

	use Actions\Traits\SecurityAdminNotRequired;

	const SLUG = 'admin_plugin_page_docs';

	protected function getDefaults() :array {
		return [
			'primary_mod_slug' => 'insights',
			'template'         => '/wpadmin_pages/insights/docs/index.twig',
		];
	}

	protected function getRenderData() :array {
		return [
			'content' => [
				'tab_updates' => $this->renderTabUpdates(),
				'tab_events'  => $this->renderTabEvents(),
			],
			'flags'   => [
				'is_pro' => $this->getCon()->isPremiumActive(),
			],
			'hrefs'   => [
				'free_trial' => 'https://shsec.io/shieldfreetrialinplugin',
			],
			'strings' => [
				'tab_updates'   => __( 'Updates and Changes', 'wp-simple-firewall' ),
				'tab_events'    => __( 'Event Details', 'wp-simple-firewall' ),
				'tab_freetrial' => __( 'Free Trial', 'wp-simple-firewall' ),
			],
		];
	}

	private function renderTabEvents() :string {
		$con = $this->getCon();
		$srvEvents = $con->loadEventsService();

		$eventsSortedByLevel = [
			'Alert'   => [],
			'Warning' => [],
			'Notice'  => [],
			'Info'    => [],
			'Debug'   => [],
		];
		foreach ( $srvEvents->getEvents() as $event ) {
			$level = ucfirst( strtolower( $event[ 'level' ] ) );
			$eventsSortedByLevel[ $level ][ $event[ 'key' ] ] = [
				'name' => $srvEvents->getEventName( $event[ 'key' ] ),
				'attr' => [
					'stat'    => sprintf( 'Stat: %s', empty( $event[ 'stat' ] ) ? 'No' : 'Yes' ),
					'offense' => sprintf( 'Offense: %s', empty( $event[ 'offense' ] ) ? 'No' : 'Yes' ),
					'module'  => sprintf( 'Module: %s', $con->getModule( $event[ 'module' ] )->getMainFeatureName() ),
				]
			];
		}
		foreach ( $eventsSortedByLevel as &$events ) {
			ksort( $events );
		}

		return $this->getMod()->renderTemplate( '/wpadmin_pages/insights/docs/events.twig', [
			'vars'    => [
				// the keys here must match the changelog item types
				'event_defs' => $eventsSortedByLevel
			],
			'strings' => [
				// the keys here must match the changelog item types
				'version'      => __( 'Version', 'wp-simple-firewall' ),
				'release_date' => __( 'Release Date', 'wp-simple-firewall' ),
				'pro_only'     => __( 'Pro Only', 'wp-simple-firewall' ),
				'full_release' => __( 'Full Release Announcement', 'wp-simple-firewall' ),
			],
		] );
	}

	private function renderTabUpdates() :string {
		try {
			$changelog = ( new Retrieve() )
				->setCon( $this->getCon() )
				->fromRepo();
		}
		catch ( \Exception $e ) {
			$changelog = ( new Retrieve() )
				->setCon( $this->getCon() )
				->fromFile();
		}

		return $this->getMod()->renderTemplate( '/wpadmin_pages/insights/overview/updates/index.twig', [
			'changelog' => $changelog,
			'strings'   => [
				// the keys here must match the changelog item types
				'version'      => __( 'Version', 'wp-simple-firewall' ),
				'release_date' => __( 'Release Date', 'wp-simple-firewall' ),
				'pro_only'     => __( 'Pro Only', 'wp-simple-firewall' ),
				'full_release' => __( 'Full Release Announcement', 'wp-simple-firewall' ),
			],
			'vars'      => [
				// the keys here must match the changelog item types
				'badge_types' => [
					'new'      => 'primary',
					'added'    => 'light',
					'improved' => 'info',
					'changed'  => 'warning',
					'fixed'    => 'danger',
				]
			],
		] );
	}
}