<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Components\CompCons;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\AutoUpdatesCon;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\OptsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class AutoUpdatesConBoundaryIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionSnapshot = [];

	private ?AutoUpdatesCon $subject = null;

	private int $priority = 10;

	public function set_up() {
		parent::set_up();
		$con = $this->requireController();
		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'autoupdate_plugin_self',
			'delay_tracking',
			'update_delay',
		] );
		$this->priority = (int)$con->cfg->configuration->def( 'action_hook_priority' );
		$this->subject = new AutoUpdatesCon();
		$this->subject->execute();
	}

	public function tear_down() {
		if ( $this->subject !== null ) {
			remove_filter( 'auto_update_plugin', [ $this->subject, 'autoupdate_plugins' ], $this->priority );
			remove_filter( 'auto_update_theme', [ $this->subject, 'autoupdate_themes' ], $this->priority );
			remove_filter( 'auto_update_core', [ $this->subject, 'autoupdate_core' ], $this->priority );
			remove_filter( 'auto_core_update_email', [ $this->subject, 'autoupdate_email_override' ], $this->priority );
			remove_filter( 'auto_plugin_theme_update_email', [ $this->subject, 'autoupdate_email_override' ], $this->priority );
			remove_action( 'set_site_transient_update_core', [ $this->subject, 'trackUpdateTimesCore' ] );
			remove_action( 'set_site_transient_update_plugins', [ $this->subject, 'trackUpdateTimesPlugins' ] );
			remove_action( 'set_site_transient_update_themes', [ $this->subject, 'trackUpdateTimesThemes' ] );
			remove_filter( 'plugins_list', [ $this->subject, 'indicateAutoUpdate' ] );
		}
		$this->restoreSelectedOptions( $this->optionSnapshot );
		parent::tear_down();
	}

	public function test_registered_filters_preserve_upstream_decisions_for_hostile_values() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'autoupdate_plugin_self', 'auto' )
			->optSet( 'update_delay', 7 );

		$this->assertSame( 'upstream', apply_filters(
			'auto_update_plugin',
			'upstream',
			(object)[ 'plugin' => [] ]
		) );
		$this->assertSame( [
			'cloaked' => [],
		], apply_filters( 'plugins_list', 'hostile-outer-value' ) );
		$this->assertSame( [
			'all'     => [],
			'cloaked' => [],
		], apply_filters( 'plugins_list', [
			'all' => [ $con->base_file => 'hostile-row' ],
		] ) );
	}

	public function test_registered_tracking_hook_persists_and_reloads_canonical_delay_tree() :void {
		$con = $this->requireController();
		$futureTimestamp = time() + 300;
		$numericThemeTimestamp = time() - 10;
		$con->opts->optSet( 'delay_tracking', [
			'plugins' => [
				' '.$con->base_file.' ' => [
					' 22.1.9 ' => time() - 20,
					'22.2.0'    => $futureTimestamp,
				],
			],
			'themes' => [
				2024 => [ 2 => $numericThemeTimestamp ],
			],
			'unknown-context' => [ 'discard-me' ],
		] );
		$con->opts->store();
		$con->opts = new OptsHandler();

		do_action( 'set_site_transient_update_plugins', (object)[ 'response' => [
			'akismet/akismet.php' => (object)[ 'new_version' => '5.4.0' ],
			'bad-version.php'     => (object)[ 'new_version' => 5.4 ],
		] ] );
		$con->opts->store();
		$con->opts = new OptsHandler();

		$tracking = $con->opts->optGet( 'delay_tracking' );
		$this->assertIsArray( $tracking );
		$this->assertSame( [ 'core', 'plugins', 'themes' ], \array_keys( $tracking ) );
		$this->assertSame( $futureTimestamp, $tracking[ 'plugins' ][ $con->base_file ][ '22.2.0' ] );
		$this->assertArrayHasKey( '5.4.0', $tracking[ 'plugins' ][ 'akismet/akismet.php' ] );
		$this->assertSame( $numericThemeTimestamp, $tracking[ 'themes' ][ '2024' ][ '2' ] );
		$this->assertArrayNotHasKey( 'bad-version.php', $tracking[ 'plugins' ] );
	}
}
