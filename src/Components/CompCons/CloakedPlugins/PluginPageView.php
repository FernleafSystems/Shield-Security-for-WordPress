<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class PluginPageView {

	use PluginControllerConsumer;

	public const STATUS = 'cloaked';

	public function addHooks() :void {
		add_action( 'pre_current_active_plugins', [ $this, 'setCurrentStatus' ], 1000 );
		add_filter( 'plugins_list', [ $this, 'addCloakedList' ], 1000 );
		add_filter( 'views_plugins', [ $this, 'addStatusViewLink' ], 1000 );
		add_filter( 'plugin_action_links', [ $this, 'filterActionLinks' ], 1000, 4 );
		add_filter( 'mu_plugin_action_links', [ $this, 'filterActionLinks' ], 1000, 4 );
	}

	public function setCurrentStatus() :void {
		if ( $this->isCloakedStatusRequest() ) {
			global $status;
			$status = self::STATUS;
		}
	}

	public function addStatusViewLink( $views ) {
		if ( !\is_array( $views ) ) {
			return $views;
		}

		$count = \count( $this->activeFindings() );
		if ( $count < 1 ) {
			return $views;
		}

		global $status;
		$views[ self::STATUS ] = \sprintf(
			"<a href='%s' %s>%s</a>",
			self::con()->plugin_urls->cloakedPlugins(),
			$status === self::STATUS ? ' class="current"' : '',
			\sprintf(
				'%s <span class="count">(%s)</span>',
				__( 'Cloaked', 'wp-simple-firewall' ),
				number_format_i18n( $count )
			)
		);
		return $views;
	}

	public function addCloakedList( $plugins ) {
		if ( !\is_array( $plugins ) ) {
			return $plugins;
		}

		$plugins[ self::STATUS ] = $this->buildRowsByFile( $plugins );
		return $plugins;
	}

	public function filterActionLinks( $actions, string $pluginFile = '', $pluginData = [], string $context = '' ) {
		unset( $pluginData, $context );

		if ( !$this->isCloakedStatusRequest() || !\is_array( $actions ) ) {
			return $actions;
		}

		$finding = $this->activeFindingForFile( $pluginFile );
		return $finding instanceof CloakedPluginFinding && $finding->entry->type === PluginType::MustUse
			? []
			: $actions;
	}

	private function isCloakedStatusRequest() :bool {
		return (string)Services::Request()->query( 'plugin_status' ) === self::STATUS;
	}

	/**
	 * @return list<CloakedPluginFinding>
	 */
	private function activeFindings() :array {
		return self::con()->comps->hidden_plugins->currentState()[ 'active' ];
	}

	/**
	 * @param array<string,array<string,array<string,mixed>>> $plugins
	 * @return array<string,array<string,mixed>>
	 */
	private function buildRowsByFile( array $plugins ) :array {
		$rows = [];
		foreach ( $this->activeFindings() as $finding ) {
			$rows[ $finding->entry->file ] = $this->rowDataForFinding( $finding, $plugins );
		}
		return $rows;
	}

	private function activeFindingForFile( string $pluginFile ) :?CloakedPluginFinding {
		foreach ( $this->activeFindings() as $finding ) {
			if ( $finding->entry->file === $pluginFile ) {
				return $finding;
			}
		}
		return null;
	}

	/**
	 * @param array<string,array<string,array<string,mixed>>> $plugins
	 * @return array<string,mixed>
	 */
	private function rowDataForFinding( CloakedPluginFinding $finding, array $plugins ) :array {
		foreach ( $plugins as $group ) {
			if ( \is_array( $group ) && \is_array( $group[ $finding->entry->file ] ?? null ) ) {
				return $group[ $finding->entry->file ];
			}
		}

		return $this->fallbackRowDataForFinding( $finding );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function fallbackRowDataForFinding( CloakedPluginFinding $finding ) :array {
		$data = $this->readPluginData( $finding->entry->path );
		$name = \trim( (string)( $data[ 'Name' ] ?? '' ) );
		if ( $name === '' ) {
			$name = \trim( $finding->entry->name ) !== '' ? $finding->entry->name : $finding->entry->file;
		}

		$version = \trim( (string)( $data[ 'Version' ] ?? '' ) );
		if ( $version === '' ) {
			$version = $finding->entry->version;
		}

		return \array_merge(
			[
				'Name'        => $name,
				'PluginURI'   => '',
				'Version'     => $version,
				'Description' => \sprintf(
					__( 'Cloaked %s plugin file detected by Shield.', 'wp-simple-firewall' ),
					PluginType::label( $finding->entry->type )
				),
				'Author'      => '',
				'AuthorURI'   => '',
				'TextDomain'  => '',
				'DomainPath'  => '',
				'Network'     => false,
				'RequiresWP'  => '',
				'RequiresPHP' => '',
				'UpdateURI'   => '',
				'Title'       => $name,
				'AuthorName'  => '',
			],
			$data,
			[
				'Name'    => $name,
				'Version' => $version,
				'Title'   => $name,
			]
		);
	}

	private function readPluginData( string $path ) :array {
		return \is_readable( $path ) && \function_exists( 'get_plugin_data' )
			? \get_plugin_data( $path, false, false )
			: [];
	}
}
