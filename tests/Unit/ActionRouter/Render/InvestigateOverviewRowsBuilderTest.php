<?php declare( strict_types=1 );

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

			$rows = ( new InvestigateOverviewRowsBuilder() )->forUser(
				$user,
				[
					'role'            => 'Administrator',
					'last_login_ip'   => '203.0.113.77',
					'recent_ips'      => [ '203.0.113.77', '198.51.100.12' ],
					'shield_status'   => 'Active',
					'wp_profile_href' => '/wp-admin/user-edit.php?user_id=7',
				]
			);

			$this->assertSame(
				[ 'Username', 'Display Name', 'Email', 'Role', 'Last Login IP', 'Recent IPs', 'Shield Status', 'WordPress Profile' ],
				\array_column( $rows, 'label' )
			);
			$this->assertSame(
				[
					'operator',
					'Operator User',
					'operator@example.com',
					'Administrator',
					'203.0.113.77',
					'203.0.113.77, 198.51.100.12',
					'Active',
					'Open Profile',
				],
				\array_column( $rows, 'value' )
			);
			$this->assertSame( '/wp-admin/user-edit.php?user_id=7', (string)( $rows[ 7 ][ 'value_href' ] ?? '' ) );
		}

		public function test_user_rows_omit_profile_row_when_profile_href_missing() :void {
			$user = new \WP_User();
			$user->ID = 9;
			$user->user_login = 'reviewer';
			$user->user_email = 'reviewer@example.com';
			$user->display_name = 'Review User';

			$rows = ( new InvestigateOverviewRowsBuilder() )->forUser(
				$user,
				[
					'role'          => 'Editor',
					'last_login_ip' => '198.51.100.19',
					'recent_ips'    => [],
					'shield_status' => 'Suspended',
				]
			);

			$this->assertSame(
				[ 'Username', 'Display Name', 'Email', 'Role', 'Last Login IP', 'Recent IPs', 'Shield Status' ],
				\array_column( $rows, 'label' )
			);
			$this->assertNotContains( 'WordPress Profile', \array_column( $rows, 'label' ) );
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
