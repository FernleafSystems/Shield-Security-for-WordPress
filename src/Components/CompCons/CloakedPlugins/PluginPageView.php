<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class PluginPageView {

	use PluginControllerConsumer;

	public const STATUS = 'cloaked';

	/**
	 * Whether the table being drawn shows the cloaked list. It's settled once, as the table's rows are chosen, and
	 * the tab and every row go by it. WordPress isn't asked as each is drawn, because its answer can change part-way
	 * down the table: to draw a row's update notice it works the list out again, from the request alone.
	 */
	private bool $showsCloakedList = false;

	/**
	 * A network's own Plugins page asks for its tabs and for a row's action links under names of its own, so each is
	 * answered under both names. The plugin lists and a row's details go by one name on either page.
	 */
	public function addHooks() :void {
		add_filter( 'plugins_list', [ $this, 'addCloakedList' ], \PHP_INT_MAX );
		foreach ( [ 'views_plugins', 'views_plugins-network' ] as $hook ) {
			add_filter( $hook, [ $this, 'addStatusViewLink' ], 1000 );
		}
		foreach ( [ 'plugin_action_links', 'network_admin_plugin_action_links' ] as $hook ) {
			add_filter( $hook, [ $this, 'filterActionLinks' ], 1000, 2 );
		}
		add_filter( 'plugin_row_meta', [ $this, 'addRowMeta' ], 1000, 2 );
	}

	public function addStatusViewLink( $views ) {
		if ( !\is_array( $views ) ) {
			return $views;
		}

		$count = \count( $this->activeFindings() );
		if ( $count < 1 ) {
			return $views;
		}

		$views[ self::STATUS ] = \sprintf(
			"<a href='%s' %s>%s</a>",
			self::con()->plugin_urls->cloakedPlugins(),
			$this->showsCloakedList ? ' class="current"' : '',
			\sprintf(
				'%s <span class="count">(%s)</span>',
				__( 'Cloaked', 'wp-simple-firewall' ),
				number_format_i18n( $count )
			)
		);
		return $views;
	}

	/**
	 * Adds the cloaked plugins as a list of their own, and has the table show it when the page was asked for it.
	 *
	 * WordPress settles which list to show from the request before this runs, and up to 6.9 it takes only the
	 * lists it knows of, so a request for this one is left showing every plugin. This is the last point before
	 * the table's rows are chosen, so the list is chosen here, and it's the only place that reads the request.
	 * The request is the page's own, or the one the page's search box sends in the background, which posts the
	 * list it's on. A search is left as WordPress has it, as on any other tab. With nothing cloaked there is no
	 * list to show, and WordPress shows every plugin, as it does when asked for any list that's empty. What the
	 * table shows is then taken from WordPress, which from 7.0 may already have chosen this list by itself.
	 */
	public function addCloakedList( $plugins ) {
		if ( !\is_array( $plugins ) ) {
			return $plugins;
		}

		$plugins[ self::STATUS ] = $this->buildRowsByFile( $plugins );

		$isListed = !empty( $plugins[ self::STATUS ] );

		global $status;
		if ( $isListed && $status !== 'search' && Services::Request()->request( 'plugin_status' ) === self::STATUS ) {
			$status = self::STATUS;
		}
		$this->showsCloakedList = $isListed && $status === self::STATUS;
		return $plugins;
	}

	public function filterActionLinks( $actions, string $pluginFile = '' ) {
		if ( !$this->showsCloakedList || !\is_array( $actions ) ) {
			return $actions;
		}

		$finding = $this->activeFindingForFile( $pluginFile );
		return $finding instanceof CloakedPluginFinding && $finding->entry->type === PluginType::MustUse
			? []
			: $actions;
	}

	public function addRowMeta( $pluginMeta, string $pluginFile = '' ) {
		if ( !$this->showsCloakedList || !\is_array( $pluginMeta ) ) {
			return $pluginMeta;
		}

		$finding = $this->activeFindingForFile( $pluginFile );
		if ( !$finding instanceof CloakedPluginFinding ) {
			return $pluginMeta;
		}

		$pluginMeta[ 'shield-cloaked-path' ] = \sprintf(
			'%s: <code>%s</code>',
			$this->escapeHtml( __( 'Path', 'wp-simple-firewall' ) ),
			$this->escapeHtml( $finding->relativePath() )
		);
		$pluginMeta[ 'shield-cloaked-reason' ] = \sprintf(
			'%s: %s',
			$this->escapeHtml( __( 'Reason', 'wp-simple-firewall' ) ),
			$this->escapeHtml( $finding->cloakReasonSummary() )
		);

		return $pluginMeta;
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
				return $this->normalizeRowDataForFinding( $finding, $group[ $finding->entry->file ] );
			}
		}

		return $this->normalizeRowDataForFinding( $finding, $this->readPluginData( $finding->entry->path ) );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function normalizeRowDataForFinding( CloakedPluginFinding $finding, array $data ) :array {
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
				'Description' => '',
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
				'Name'        => $name,
				'Version'     => $version,
				'Description' => $this->rowDescription( $finding ),
				'Title'       => $name,
			]
		);
	}

	private function rowDescription( CloakedPluginFinding $finding ) :string {
		return $finding->entry->type === PluginType::MustUse
			? __( 'Shield found this must-use plugin file on disk, but WordPress is not listing it in the Must-Use plugins view.', 'wp-simple-firewall' )
			: __( 'Shield found this plugin file on disk, but WordPress is not listing it on the Plugins page.', 'wp-simple-firewall' );
	}

	private function readPluginData( string $path ) :array {
		return \is_readable( $path ) && \function_exists( 'get_plugin_data' )
			? \get_plugin_data( $path, false, false )
			: [];
	}

	private function escapeHtml( string $value ) :string {
		return \htmlspecialchars( $value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8' );
	}
}
