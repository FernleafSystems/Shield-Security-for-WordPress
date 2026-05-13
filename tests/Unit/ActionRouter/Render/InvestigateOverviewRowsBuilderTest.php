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
				[
					'username',
					'display_name',
					'email',
					'role',
					'last_login_ip',
					'recent_ips',
					'shield_status',
					'wp_profile',
				],
				\array_column( $rows, 'key' )
			);
			$rowsByKey = $this->rowsByKey( $rows );
			$this->assertSame( 'operator', (string)( $rowsByKey[ 'username' ][ 'value' ] ?? '' ) );
			$this->assertSame( 'operator@example.com', (string)( $rowsByKey[ 'email' ][ 'value' ] ?? '' ) );

			$query = [];
			\parse_str(
				(string)\parse_url( (string)( $rowsByKey[ 'wp_profile' ][ 'value_href' ] ?? '' ), \PHP_URL_QUERY ),
				$query
			);
			$this->assertSame( '7', (string)( $query[ 'user_id' ] ?? '' ) );
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
				[ 'username', 'display_name', 'email', 'role', 'last_login_ip', 'recent_ips', 'shield_status' ],
				\array_column( $rows, 'key' )
			);
			$this->assertNotContains( 'wp_profile', \array_column( $rows, 'key' ) );
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
				[
					'name',
					'slug',
					'version',
					'author',
					'asset_identifier',
					'install_directory',
					'installed_at',
					'active_status',
					'update_available_status',
					'vulnerability_status',
				],
				\array_column( $rows, 'key' )
			);
			$this->assertSame( 'no_known_vulnerabilities', (string)( $rows[ 9 ][ 'value_key' ] ?? '' ) );
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

			$this->assertSame( 'child_theme_status', (string)( $rows[ 8 ][ 'key' ] ?? '' ) );
			$this->assertSame( 'child_theme', (string)( $rows[ 8 ][ 'value_key' ] ?? '' ) );
		}

		public function test_core_rows_include_version_update_and_directory() :void {
			$rows = ( new InvestigateOverviewRowsBuilder() )->forCore( '6.5.2', true, '/var/www/html/' );

			$this->assertSame(
				[ 'wordpress_version', 'core_update_status', 'install_directory' ],
				\array_column( $rows, 'key' )
			);
			$this->assertSame( 'update_available', (string)( $rows[ 1 ][ 'value_key' ] ?? '' ) );
		}

		private function rowsByKey( array $rows ) :array {
			$byKey = [];
			foreach ( $rows as $row ) {
				$byKey[ (string)( $row[ 'key' ] ?? '' ) ] = $row;
			}
			return $byKey;
		}
	}
}
