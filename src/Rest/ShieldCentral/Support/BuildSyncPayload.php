<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support;

use FernleafSystems\Wordpress\Services\Services;

class BuildSyncPayload {

	public const CHANNEL_REST = 'rest';

	public const ACTIONS = [
		'pair/bootstrap',
		'pair/health',
		'pair/finalize',
		'pair/cleanup',
		'site/unpair',
		'sync/collect',
	];

	public function build() :array {
		return [
			'success'            => true,
			'site_name'          => $this->siteName(),
			'wordpress_home_url' => Services::WpGeneral()->getHomeUrl(),
			'wordpress_site_url' => \site_url( '/' ),
			'wordpress_info'     => $this->wordpressInfo(),
			'capability_report'  => ( new BuildCapabilityReport() )->build(),
			'reported_channels'  => [ self::CHANNEL_REST ],
			'reported_actions'   => self::ACTIONS,
			'plugins'            => $this->plugins(),
			'themes'             => $this->themes(),
			'security_issues'    => ( new BuildSecurityIssues() )->build(),
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function wordpressInfo() :array {
		return [
			'wordpress_version'        => $this->wordpressVersion(),
			'available_core_upgrades' => $this->availableCoreUpgrades(),
			'vulnerabilities'         => [],
		];
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function plugins() :array {
		if ( !\function_exists( 'get_plugins' ) ) {
			require_once \ABSPATH.'wp-admin/includes/plugin.php';
		}

		$updates = \get_site_transient( 'update_plugins' );
		$active = \function_exists( 'is_plugin_active' ) ? null : [];
		$plugins = [];
		foreach ( \get_plugins() as $file => $plugin ) {
			$updateInfo = \is_object( $updates ) && isset( $updates->response[ $file ] )
				? (array)$updates->response[ $file ]
				: [];

			$plugins[] = [
				'file'             => (string)$file,
				'slug'             => $this->pluginSlug( (string)$file ),
				'name'             => (string)( $plugin[ 'Name' ] ?? '' ),
				'plugin_uri'       => (string)( $plugin[ 'PluginURI' ] ?? '' ),
				'version'          => (string)( $plugin[ 'Version' ] ?? '' ),
				'active'           => $active === null ? \is_plugin_active( (string)$file ) : false,
				'update_available' => !empty( $updateInfo ),
				'update_info'      => $updateInfo,
				'vulnerabilities'  => [],
			];
		}

		return $plugins;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function themes() :array {
		$updates = \get_site_transient( 'update_themes' );
		$current = \wp_get_theme();
		$currentStylesheet = \is_object( $current ) && \method_exists( $current, 'get_stylesheet' )
			? (string)$current->get_stylesheet()
			: '';

		$themes = [];
		foreach ( \wp_get_themes() as $stylesheet => $theme ) {
			$updateInfo = \is_object( $updates ) && isset( $updates->response[ $stylesheet ] )
				? (array)$updates->response[ $stylesheet ]
				: [];

			$themes[] = [
				'stylesheet'       => (string)$stylesheet,
				'template'         => \is_object( $theme ) && \method_exists( $theme, 'get_template' ) ? (string)$theme->get_template() : '',
				'name'             => \is_object( $theme ) && \method_exists( $theme, 'get' ) ? (string)$theme->get( 'Name' ) : '',
				'version'          => \is_object( $theme ) && \method_exists( $theme, 'get' ) ? (string)$theme->get( 'Version' ) : '',
				'active'           => (string)$stylesheet === $currentStylesheet,
				'is_child'         => \is_object( $theme ) && \method_exists( $theme, 'parent' ) && $theme->parent() !== false,
				'update_available' => !empty( $updateInfo ),
				'update_info'      => $updateInfo,
				'vulnerabilities'  => [],
			];
		}

		return $themes;
	}

	/**
	 * @return list<string>
	 */
	private function availableCoreUpgrades() :array {
		if ( !\function_exists( 'get_core_updates' ) ) {
			require_once \ABSPATH.'wp-admin/includes/update.php';
		}

		$versions = [];
		foreach ( \function_exists( 'get_core_updates' ) ? ( \get_core_updates() ?: [] ) : [] as $update ) {
			if ( \is_object( $update ) && !empty( $update->version ) && ( $update->response ?? '' ) === 'upgrade' ) {
				$versions[] = (string)$update->version;
			}
		}

		return \array_values( \array_unique( $versions ) );
	}

	private function pluginSlug( string $file ) :string {
		$parts = \explode( '/', $file, 2 );
		return $parts[ 0 ] !== '' ? $parts[ 0 ] : $file;
	}

	private function siteName() :string {
		return (string)\get_bloginfo( 'name' );
	}

	private function wordpressVersion() :string {
		return \function_exists( 'wp_get_wp_version' ) ? (string)\wp_get_wp_version() : (string)\get_bloginfo( 'version' );
	}
}
