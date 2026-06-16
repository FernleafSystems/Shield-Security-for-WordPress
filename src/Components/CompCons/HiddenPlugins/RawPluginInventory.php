<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\HiddenPlugins;

class RawPluginInventory {

	public function __construct(
		private ?string $pluginDir = null,
		private ?string $muPluginDir = null
	) {
	}

	/**
	 * @return list<PluginEntry>
	 */
	public function scan() :array {
		return \array_merge(
			$this->scanStandardPlugins(),
			$this->scanMustUsePlugins()
		);
	}

	/**
	 * @return list<PluginEntry>
	 */
	public function scanStandardPlugins() :array {
		$pluginDir = $this->pluginDir();
		if ( $pluginDir === '' || !\is_dir( $pluginDir ) ) {
			return [];
		}

		$entries = [];
		foreach ( $this->standardPluginFiles( $pluginDir ) as $file ) {
			$path = $pluginDir.'/'.$file;
			if ( !\is_readable( $path ) ) {
				$entries[] = new PluginEntry( PluginType::Standard, $file, $file, '', $path );
				continue;
			}

			$data = $this->readPluginData( $path );
			if ( empty( $data[ 'Name' ] ) ) {
				continue;
			}

			$entries[] = new PluginEntry(
				PluginType::Standard,
				$file,
				(string)$data[ 'Name' ],
				(string)( $data[ 'Version' ] ?? '' ),
				$path
			);
		}

		return $entries;
	}

	/**
	 * @return list<PluginEntry>
	 */
	public function scanMustUsePlugins() :array {
		$muDir = $this->muPluginDir();
		if ( $muDir === '' || !\is_dir( $muDir ) ) {
			return [];
		}

		$entries = [];
		foreach ( $this->mustUsePluginFiles( $muDir ) as $file ) {
			$path = $muDir.'/'.$file;
			if ( \is_readable( $path ) ) {
				$data = $this->readPluginData( $path );
				$name = (string)( $data[ 'Name' ] ?: $file );
				$version = (string)( $data[ 'Version' ] ?? '' );
			}
			else {
				$name = $file;
				$version = '';
			}

			$entries[] = new PluginEntry( PluginType::MustUse, $file, $name, $version, $path );
		}

		return $entries;
	}

	/**
	 * @return list<string>
	 */
	private function standardPluginFiles( string $pluginDir ) :array {
		$files = [];
		$items = \scandir( $pluginDir );
		if ( !\is_array( $items ) ) {
			return [];
		}

		foreach ( $items as $item ) {
			if ( $this->isDotFile( $item ) ) {
				continue;
			}

			$path = $pluginDir.'/'.$item;
			if ( \is_dir( $path ) ) {
				$subItems = \scandir( $path );
				if ( !\is_array( $subItems ) ) {
					continue;
				}
				foreach ( $subItems as $subItem ) {
					if ( $this->isDotFile( $subItem ) ) {
						continue;
					}
					if ( $this->endsWith( $subItem, '.php' ) ) {
						$files[] = $item.'/'.$subItem;
					}
				}
			}
			elseif ( $this->endsWith( $item, '.php' ) ) {
				$files[] = $item;
			}
		}

		\sort( $files );
		return $files;
	}

	/**
	 * @return list<string>
	 */
	private function mustUsePluginFiles( string $muDir ) :array {
		$files = [];
		$items = \scandir( $muDir );
		if ( \is_array( $items ) ) {
			foreach ( $items as $item ) {
				if ( $item === '.' || $item === '..' ) {
					continue;
				}
				if ( $this->endsWith( $item, '.php' ) && \is_file( $muDir.'/'.$item ) ) {
					$files[] = $item;
				}
			}
		}

		\sort( $files );
		return $files;
	}

	private function pluginDir() :string {
		return $this->normalizeDir( $this->pluginDir ?? ( \defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : '' ) );
	}

	private function muPluginDir() :string {
		return $this->normalizeDir( $this->muPluginDir ?? ( \defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : '' ) );
	}

	private function normalizeDir( string $dir ) :string {
		return \rtrim( \str_replace( '\\', '/', $dir ), '/' );
	}

	private function isDotFile( string $name ) :bool {
		return $name === '.' || $name === '..' || \strncmp( $name, '.', 1 ) === 0;
	}

	private function endsWith( string $value, string $suffix ) :bool {
		$suffixLength = \strlen( $suffix );
		return $suffixLength === 0 || \substr( $value, -$suffixLength ) === $suffix;
	}

	private function readPluginData( string $path ) :array {
		$this->ensurePluginApiLoaded();

		if ( \function_exists( 'get_plugin_data' ) ) {
			return \get_plugin_data( $path, false, false );
		}

		return $this->readPluginDataFromHeaders( $path );
	}

	private function ensurePluginApiLoaded() :void {
		if ( !\function_exists( 'get_plugin_data' ) && \defined( 'ABSPATH' ) ) {
			$pluginApi = \rtrim( \str_replace( '\\', '/', ABSPATH ), '/' ).'/wp-admin/includes/plugin.php';
			if ( \is_file( $pluginApi ) ) {
				require_once $pluginApi;
			}
		}
	}

	private function readPluginDataFromHeaders( string $path ) :array {
		$content = (string)\file_get_contents( $path, false, null, 0, 8192 );
		return [
			'Name'    => $this->headerValue( $content, 'Plugin Name' ),
			'Version' => $this->headerValue( $content, 'Version' ),
		];
	}

	private function headerValue( string $content, string $header ) :string {
		return \preg_match( '~^[ \t/*#@]*'.\preg_quote( $header, '~' ).':\s*(.+)$~mi', $content, $matches ) === 1
			? \trim( (string)$matches[ 1 ] )
			: '';
	}
}
