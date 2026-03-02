<?php declare( strict_types=1 );

namespace {
	if ( !\class_exists( '\WP_User' ) ) {
		class WP_User {
			public int $ID = 0;
			public string $user_login = '';
			public string $user_email = '';
			public string $display_name = '';
		}
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render {

	use Brain\Monkey\Functions;
	use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;
	use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\InvestigateOverviewRowsBuilder;
	use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

	class InvestigateOverviewRowsBuilderTest extends BaseUnitTest {

		protected function setUp() :void {
			parent::setUp();
			Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		}

		public function test_user_rows_follow_canonical_order() :void {
			$user = new \WP_User();
			$user->ID = 7;
			$user->user_login = 'operator';
			$user->user_email = 'operator@example.com';
			$user->display_name = 'Operator User';

			$rows = ( new InvestigateOverviewRowsBuilder() )->forUser( $user, [
				'sessions' => [ 'count' => 2 ],
				'activity' => [ 'count' => 3 ],
				'requests' => [ 'count' => 4 ],
				'ips'      => [ 'count' => 5 ],
			] );

			$this->assertSame(
				[ 'User ID', 'Login', 'Email', 'Display Name', 'Sessions Count', 'Activity Count', 'Requests Count', 'IP Addresses Count' ],
				\array_column( $rows, 'label' )
			);
			$this->assertSame( [ '7', 'operator', 'operator@example.com', 'Operator User', '2', '3', '4', '5' ], \array_column( $rows, 'value' ) );
		}

		public function test_plugin_asset_rows_include_update_and_vulnerability_status() :void {
			$rows = ( new InvestigateOverviewRowsBuilder() )->forAsset(
				[
					'info'  => [
						'name' => 'Akismet',
						'slug' => 'akismet',
						'version' => '5.0',
						'author' => 'Automattic',
						'author_url' => 'https://example.com',
						'file' => 'akismet/akismet.php',
						'dir' => '/wp-content/plugins/akismet/',
						'installed_at' => '2026-02-27',
					],
					'flags' => [
						'is_active' => true,
						'has_update' => false,
					],
				],
				[
					'count' => 0,
				],
				InvestigationTableContract::SUBJECT_TYPE_PLUGIN,
				'File'
			);

			$this->assertSame(
				[ 'Name', 'Slug', 'Version', 'Author', 'File', 'Install Directory', 'Installed', 'Active Status', 'Update Available Status', 'Vulnerability Status' ],
				\array_column( $rows, 'label' )
			);
			$this->assertSame( 'No Known Vulnerabilities', (string)( $rows[ 9 ][ 'value' ] ?? '' ) );
		}

		public function test_theme_asset_rows_include_child_theme_status() :void {
			$rows = ( new InvestigateOverviewRowsBuilder() )->forAsset(
				[
					'info'  => [
						'name' => 'Twenty Twenty-Five',
						'slug' => 'twentytwentyfive',
						'version' => '1.0',
						'author' => 'WordPress.org',
						'author_url' => '',
						'file' => 'twentytwentyfive',
						'dir' => '/wp-content/themes/twentytwentyfive/',
						'installed_at' => '2026-02-27',
					],
					'flags' => [
						'is_active' => true,
						'is_child' => true,
					],
				],
				[],
				InvestigationTableContract::SUBJECT_TYPE_THEME,
				'Stylesheet'
			);

			$this->assertSame( 'Child Theme Status', (string)( $rows[ 8 ][ 'label' ] ?? '' ) );
			$this->assertSame( 'Yes', (string)( $rows[ 8 ][ 'value' ] ?? '' ) );
		}

		public function test_core_rows_include_version_update_and_directory() :void {
			$rows = ( new InvestigateOverviewRowsBuilder() )->forCore( '6.5.2', true, '/var/www/html/' );

			$this->assertSame(
				[ 'WordPress Version', 'Core Update Status', 'Install Directory' ],
				\array_column( $rows, 'label' )
			);
			$this->assertSame( 'An update is available.', (string)( $rows[ 1 ][ 'value' ] ?? '' ) );
		}
	}
}
