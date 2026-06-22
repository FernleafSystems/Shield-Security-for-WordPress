<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts\{
	EmailInstantAlertAdminLogin,
	EmailInstantAlertAdmins,
	EmailInstantAlertFileLocker,
	EmailInstantAlertFirewallBlock,
	EmailInstantAlertCloakedPlugins,
	EmailInstantAlertShieldDeactivated,
	EmailInstantAlertVulnerabilities
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Text\SafeDisplayText;

class InstantAlertSafeDisplayRenderingIntegrationTest extends ShieldIntegrationTestCase {

	private array $createdPaths = [];

	public function tear_down() {
		$this->deleteCreatedPaths();
		$this->cleanPluginCache();
		$this->cleanThemeCache();
		parent::tear_down();
	}

	public function testInstantAlertEmailsEscapeMaliciousDisplayValuesAcrossRenderers() :void {
		$malicious = "Alpha <script>alert(2)</script>\nBeta <img src=x onerror=alert(1)>";
		$buttonPayload = '<button onclick=alert(3)>push</button>';
		$expectedEscaped = $this->escapedInline( $malicious );
		$expectedButtonEscaped = $this->escapedInline( $buttonPayload );

		foreach ( $this->instantAlertRenderCases( $malicious, $buttonPayload ) as $label => $case ) {
			$html = $this->requireController()->action_router->render( $case[ 'class' ], [
				'alert_data' => $case[ 'alert_data' ],
			] );

			$this->assertStringNotContainsString( 'Exception during render', $html, $label.' render should complete.' );
			$this->assertStringNotContainsString( '<script>alert(2)</script>', $html, $label.' should not render raw script tags.' );
			$this->assertStringNotContainsString( '<img src=x onerror=alert(1)>', $html, $label.' should not render raw image payloads.' );
			$this->assertStringNotContainsString( '<button onclick=alert(3)>', $html, $label.' should not render raw button payloads.' );
			$this->assertStringNotContainsString( 'href="javascript:', \strtolower( $html ), $label.' should not render javascript hrefs.' );

			foreach ( $case[ 'expected' ] as $expected ) {
				$this->assertStringContainsString( $expected, $html, $label.' should render escaped display text.' );
			}

			if ( $case[ 'contains_malicious_value' ] ) {
				$this->assertStringContainsString( $expectedEscaped, $html, $label.' should preserve escaped malicious text.' );
			}
			if ( $case[ 'contains_button_payload' ] ) {
				$this->assertStringContainsString( $expectedButtonEscaped, $html, $label.' should preserve escaped button text.' );
			}
		}
	}

	public function testCloakedPluginEmailLinksFindingsToCloakedPluginView() :void {
		$html = $this->requireController()->action_router->render( EmailInstantAlertCloakedPlugins::class, [
			'alert_data' => [
				'hidden_plugins' => [
					$this->cloakedPluginAlertFixture( 'plugin', 'active', 'hidden-plugin/hidden-plugin.php' ),
					$this->cloakedPluginAlertFixture( 'mu-plugin', 'must-use', 'hidden-mu-plugin.php' ),
				],
			],
		] );

		$pluginStatusHrefs = $this->anchorHrefsContaining( $html, 'plugin_status=' );
		$cloakedAnchorTexts = $this->anchorTextsByHrefContaining( $html, 'plugin_status=cloaked' );

		$this->assertNotEmpty( $pluginStatusHrefs );
		foreach ( $pluginStatusHrefs as $href ) {
			$this->assertStringContainsString( 'plugin_status=cloaked', $href );
			$this->assertStringNotContainsString( 'plugin_status=active', $href );
			$this->assertStringNotContainsString( 'plugin_status=inactive', $href );
			$this->assertStringNotContainsString( 'plugin_status=mustuse', $href );
		}

		$this->assertNotEmpty( $cloakedAnchorTexts );
		foreach ( $cloakedAnchorTexts as $anchorText ) {
			$this->assertStringNotContainsString( 'hidden-plugin/hidden-plugin.php', $anchorText );
			$this->assertStringNotContainsString( 'hidden-mu-plugin.php', $anchorText );
			$this->assertStringNotContainsString( 'File', $anchorText );
		}
	}

	private function instantAlertRenderCases( string $malicious, string $buttonPayload ) :array {
		$vulnerabilityFixtures = $this->createVulnerabilityFixtures( $malicious );
		$rolePayload = "Role <script>alert(9)</script>\nName";

		return [
			'admins'             => [
				'class'                   => EmailInstantAlertAdmins::class,
				'alert_data'              => [
					'added' => [ $malicious ],
				],
				'expected'                => [],
				'contains_malicious_value' => true,
				'contains_button_payload' => false,
			],
			'admin login'        => [
				'class'                   => EmailInstantAlertAdminLogin::class,
				'alert_data'              => [
					'admin_login' => [
						'role_name'   => $rolePayload,
						'username'    => $malicious,
						'user_email'  => $malicious,
						'ip'          => $buttonPayload,
						'ip_identity' => $malicious,
					],
				],
				'expected'                => [
					$this->escapedInline( $rolePayload ),
				],
				'contains_malicious_value' => true,
				'contains_button_payload' => true,
			],
			'firewall'           => [
				'class'                   => EmailInstantAlertFirewallBlock::class,
				'alert_data'              => [
					'firewall_block' => [
						'ip'                  => $malicious,
						'request_path'        => $buttonPayload,
						'firewall_rule_name'  => $malicious,
						'match_pattern'       => $buttonPayload,
						'match_request_param' => $malicious,
						'match_request_value' => $buttonPayload,
					],
				],
				'expected'                => [],
				'contains_malicious_value' => true,
				'contains_button_payload' => true,
			],
			'file locker'        => [
				'class'                   => EmailInstantAlertFileLocker::class,
				'alert_data'              => [
					'filelocker' => [
						'wpconfig' => $malicious,
					],
				],
				'expected'                => [],
				'contains_malicious_value' => true,
				'contains_button_payload' => false,
			],
			'cloaked plugins'     => [
				'class'                   => EmailInstantAlertCloakedPlugins::class,
				'alert_data'              => [
					'hidden_plugins' => [
						[
							'type'             => 'plugin',
							'type_label'       => $malicious,
							'file'             => $malicious,
							'name'             => $buttonPayload,
							'version'          => '1.2.3',
							'location'         => $malicious,
							'status'           => 'active',
							'hidden_by'        => [ 'all_plugins' ],
							'hidden_by_labels' => [ $malicious ],
							'detected_at'      => 1713278000,
						],
					],
				],
				'expected'                => [],
				'contains_malicious_value' => true,
				'contains_button_payload' => true,
			],
			'shield deactivated' => [
				'class'                   => EmailInstantAlertShieldDeactivated::class,
				'alert_data'              => [
					'deactivated' => [
						'user' => $malicious,
						'ip'   => $buttonPayload,
						'path' => $malicious,
						'time' => $buttonPayload,
					],
				],
				'expected'                => [],
				'contains_malicious_value' => true,
				'contains_button_payload' => true,
			],
			'vulnerabilities'    => [
				'class'                   => EmailInstantAlertVulnerabilities::class,
				'alert_data'              => [
					'plugins' => [ $vulnerabilityFixtures[ 'plugin_file' ] ],
					'themes'  => [ $vulnerabilityFixtures[ 'theme_stylesheet' ] ],
				],
				'expected'                => [
					'Safe Vuln Plugin',
					'Safe Vuln Theme',
					'Alpha &lt;script&gt;alert(2)&lt;/script&gt;',
				],
				'contains_malicious_value' => false,
				'contains_button_payload' => false,
			],
		];
	}

	private function createVulnerabilityFixtures( string $malicious ) :array {
		$pluginSlug = \uniqid( 'shield-safe-alert-vuln-plugin-', false );
		$pluginFile = $pluginSlug.'/'.$pluginSlug.'.php';
		$pluginDir = \wp_normalize_path( WP_PLUGIN_DIR.'/'.$pluginSlug );
		$this->ensureDirectory( $pluginDir, 'temporary vulnerability plugin directory' );
		$this->writeFixtureFile(
			\wp_normalize_path( WP_PLUGIN_DIR.'/'.$pluginFile ),
			"<?php\n/*\nPlugin Name: Safe Vuln Plugin {$malicious}\nVersion: 9.9.9\n*/\n"
		);
		$this->createdPaths[] = $pluginDir;
		$this->cleanPluginCache();

		$themeStylesheet = \uniqid( 'shield-safe-alert-vuln-theme-', false );
		$themeDir = \wp_normalize_path( \get_theme_root().'/'.$themeStylesheet );
		$this->ensureDirectory( $themeDir, 'temporary vulnerability theme directory' );
		$this->writeFixtureFile(
			$themeDir.'/style.css',
			"/*\nTheme Name: Safe Vuln Theme {$malicious}\nVersion: 8.8.8\n*/\n"
		);
		$this->writeFixtureFile( $themeDir.'/index.php', "<?php\n" );
		$this->createdPaths[] = $themeDir;
		$this->cleanThemeCache();

		return [
			'plugin_file'      => $pluginFile,
			'theme_stylesheet' => $themeStylesheet,
		];
	}

	private function cloakedPluginAlertFixture( string $type, string $status, string $file ) :array {
		return [
			'type'             => $type,
			'type_label'       => $type === 'mu-plugin' ? 'Must-Use Plugin' : 'Plugin',
			'file'             => $file,
			'name'             => 'Hidden Plugin',
			'version'          => '1.2.3',
			'location'         => WP_PLUGIN_DIR.'/'.$file,
			'status'           => $status,
			'hidden_by'        => [ 'all_plugins' ],
			'hidden_by_labels' => [ 'All Plugins' ],
			'detected_at'      => 1713278000,
		];
	}

	private function anchorTextsByHrefContaining( string $html, string $hrefNeedle ) :array {
		$texts = [];
		foreach ( $this->anchorData( $html ) as $anchor ) {
			if ( \strpos( $anchor[ 'href' ], $hrefNeedle ) !== false ) {
				$texts[] = $anchor[ 'text' ];
			}
		}

		return $texts;
	}

	private function anchorHrefsContaining( string $html, string $hrefNeedle ) :array {
		$hrefs = [];
		foreach ( $this->anchorData( $html ) as $anchor ) {
			if ( \strpos( $anchor[ 'href' ], $hrefNeedle ) !== false ) {
				$hrefs[] = $anchor[ 'href' ];
			}
		}

		return $hrefs;
	}

	private function anchorData( string $html ) :array {
		$previous = \libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$loaded = $dom->loadHTML( $html );
		\libxml_clear_errors();
		\libxml_use_internal_errors( $previous );

		if ( !$loaded ) {
			$this->fail( 'Rendered email HTML could not be parsed.' );
		}

		$anchors = [];
		foreach ( $dom->getElementsByTagName( 'a' ) as $anchor ) {
			$anchors[] = [
				'href' => \html_entity_decode( $anchor->getAttribute( 'href' ), \ENT_QUOTES | \ENT_HTML5, 'UTF-8' ),
				'text' => \trim( $anchor->textContent ),
			];
		}

		return $anchors;
	}

	private function escapedInline( string $value ) :string {
		return \htmlspecialchars( SafeDisplayText::inline( $value ), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8' );
	}

	private function ensureDirectory( string $dir, string $label ) :void {
		if ( !\is_dir( $dir ) && !\wp_mkdir_p( $dir ) ) {
			$this->markTestSkipped( 'Unable to create '.$label.'.' );
		}
	}

	private function writeFixtureFile( string $path, string $content ) :void {
		if ( \file_put_contents( $path, $content ) === false ) {
			$this->fail( 'Failed to write fixture file: '.$path );
		}
	}

	private function cleanPluginCache() :void {
		if ( !\function_exists( 'wp_clean_plugins_cache' ) && \defined( 'ABSPATH' ) ) {
			$pluginApi = \rtrim( \str_replace( '\\', '/', ABSPATH ), '/' ).'/wp-admin/includes/plugin.php';
			if ( \is_file( $pluginApi ) ) {
				require_once $pluginApi;
			}
		}
		if ( \function_exists( 'wp_clean_plugins_cache' ) ) {
			\wp_clean_plugins_cache( false );
		}
	}

	private function cleanThemeCache() :void {
		if ( \function_exists( 'wp_clean_themes_cache' ) ) {
			\wp_clean_themes_cache( true );
		}
	}

	private function deleteCreatedPaths() :void {
		foreach ( \array_reverse( $this->createdPaths ) as $path ) {
			$path = \wp_normalize_path( $path );
			if ( \is_file( $path ) ) {
				@\unlink( $path );
			}
			elseif ( \is_dir( $path ) ) {
				$this->deleteDirectoryRecursively( $path );
			}
		}
		$this->createdPaths = [];
	}

	private function deleteDirectoryRecursively( string $dir ) :void {
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			/** @var \SplFileInfo $item */
			$item->isDir() ? @\rmdir( $item->getPathname() ) : @\unlink( $item->getPathname() );
		}
		@\rmdir( $dir );
	}
}
