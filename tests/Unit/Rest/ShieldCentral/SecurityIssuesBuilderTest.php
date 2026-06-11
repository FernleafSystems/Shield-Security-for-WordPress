<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\ShieldCentral;

use FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support\BuildSecurityIssues;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class SecurityIssuesBuilderTest extends BaseUnitTest {

	public function test_builder_maps_scan_file_locker_and_maintenance_state_to_canonical_rows() :void {
		$builder = new ShieldCentralSecurityIssuesBuilderFixture(
			[
				'wpv' => [
					[
						'scan'         => 'wpv',
						'asset_type'   => 'plugin',
						'asset_key'    => 'vulnerable/plugin.php',
						'states'       => [ 'is_vulnerable' ],
						'last_seen_at' => 1700000000,
					],
				],
				'apc' => [
					[
						'scan'         => 'apc',
						'asset_type'   => 'theme',
						'asset_key'    => 'abandoned-theme',
						'states'       => [ 'is_abandoned' ],
						'last_seen_at' => 1700000100,
					],
				],
				'afs' => [
					[
						'scan'         => 'afs',
						'asset_type'   => 'core',
						'asset_key'    => 'core',
						'states'       => [ 'is_checksumfail' ],
						'last_seen_at' => 1700000200,
					],
					[
						'scan'         => 'afs',
						'asset_type'   => 'plugin',
						'asset_key'    => 'changed/plugin.php',
						'states'       => [ 'is_mal', 'is_unrecognised' ],
						'last_seen_at' => 1700000300,
					],
				],
			],
			[
				(object)[ 'detected_at' => 1700000400 ],
			],
			[
				'wp_plugins_updates' => [
					'key'                => 'wp_plugins_updates',
					'active_identifiers' => [ 'needs-update/plugin.php' ],
				],
				'default_admin_user' => [
					'key'                => 'default_admin_user',
					'active_identifiers' => [ '__self__' ],
				],
			]
		);

		$issues = $builder->build();

		$this->assertIssueExists( $issues, 'scans', 'vulnerable_assets', 'critical', 'plugin', 'vulnerable/plugin.php' );
		$this->assertIssueExists( $issues, 'scans', 'abandoned', 'critical', 'theme', 'abandoned-theme' );
		$this->assertIssueExists( $issues, 'scans', 'wp_files', 'critical', 'core', 'wordpress_core' );
		$this->assertIssueExists( $issues, 'scans', 'malware', 'critical', 'plugin', 'changed/plugin.php' );
		$this->assertIssueExists( $issues, 'scans', 'plugin_files', 'critical', 'plugin', 'changed/plugin.php' );
		$this->assertIssueExists( $issues, 'scans', 'file_locker', 'warning', 'site', 'site' );
		$this->assertIssueExists( $issues, 'maintenance', 'wp_plugins_updates', 'warning', 'plugin', 'needs-update/plugin.php' );
		$this->assertIssueExists( $issues, 'maintenance', 'default_admin_user', 'warning', 'site', 'site' );
	}

	public function test_duplicate_identity_prefers_critical_and_latest_detected_time() :void {
		$builder = new ShieldCentralSecurityIssuesBuilderFixture(
			[
				'afs' => [
					[
						'scan'         => 'afs',
						'asset_type'   => 'core',
						'asset_key'    => 'core',
						'states'       => [ 'is_checksumfail' ],
						'last_seen_at' => 1700000200,
					],
					[
						'scan'         => 'afs',
						'asset_type'   => 'core',
						'asset_key'    => 'core',
						'states'       => [ 'is_missing' ],
						'last_seen_at' => 1700000900,
					],
				],
			],
			[],
			[]
		);

		$issues = $builder->build();
		$this->assertCount( 1, $issues );
		$this->assertSame( 'critical', $issues[ 0 ][ 'severity' ] );
		$this->assertSame( '2023-11-14T22:28:20+00:00', $issues[ 0 ][ 'detected_at' ] );
	}

	public function test_unknown_maintenance_keys_are_skipped_without_fallback_issue() :void {
		$builder = new ShieldCentralSecurityIssuesBuilderFixture(
			[],
			[],
			[
				'future_unknown_key' => [
					'key'                => 'future_unknown_key',
					'active_identifiers' => [ 'site' ],
				],
			]
		);

		$this->assertSame( [], $builder->build() );
	}

	private function assertIssueExists(
		array $issues,
		string $category,
		string $issueKey,
		string $severity,
		string $assetType,
		string $assetKey
	) :void {
		foreach ( $issues as $issue ) {
			if ( ( $issue[ 'category' ] ?? '' ) === $category
				 && ( $issue[ 'issue_key' ] ?? '' ) === $issueKey
				 && ( $issue[ 'severity' ] ?? '' ) === $severity
				 && ( $issue[ 'asset_type' ] ?? '' ) === $assetType
				 && ( $issue[ 'asset_key' ] ?? '' ) === $assetKey ) {
				$this->assertSame( 'shield_plugin', $issue[ 'source' ] );
				return;
			}
		}

		$this->fail( \sprintf(
			'Missing issue %s/%s/%s/%s/%s',
			$category,
			$issueKey,
			$severity,
			$assetType,
			$assetKey
		) );
	}
}

class ShieldCentralSecurityIssuesBuilderFixture extends BuildSecurityIssues {

	private array $scanItems;

	private array $fileLocks;

	private array $maintenanceStates;

	public function __construct( array $scanItems, array $fileLocks, array $maintenanceStates ) {
		$this->scanItems = $scanItems;
		$this->fileLocks = $fileLocks;
		$this->maintenanceStates = $maintenanceStates;
	}

	protected function getRawScanItems( string $scanSlug, array $stateMetaKeys ) :array {
		unset( $stateMetaKeys );
		return $this->scanItems[ $scanSlug ] ?? [];
	}

	protected function getProblemFileLocks() :array {
		return $this->fileLocks;
	}

	protected function getMaintenanceStates() :array {
		return $this->maintenanceStates;
	}
}
