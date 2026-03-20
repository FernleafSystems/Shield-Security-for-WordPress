<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\{
	FileLocker,
	Maintenance,
	Malware,
	Plugins,
	Themes,
	Vulnerabilities,
	Wordpress
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

/**
 * @phpstan-type GroupDefinition array{
 *   key:string,
 *   label:string,
 *   icon_class:string,
 *   detail_shell:'asset_cards'|'direct_table'|'maintenance',
 *   card_type:'expandable'|'linked'|'category',
 *   drill_hint_single:string,
 *   drill_hint_plural:string,
 *   summary_keys:list<string>,
 *   render_action_class:class-string<BaseAction>,
 *   render_action_data:array<string,mixed>
 * }
 */
class ActionsQueueGroupDefinitions {

	/**
	 * @var array<string,GroupDefinition>|null
	 */
	private ?array $definitions = null;

	/**
	 * @return array<string,GroupDefinition>
	 */
	public function all() :array {
		if ( $this->definitions !== null ) {
			return $this->definitions;
		}

		$definitions = [];

		foreach ( PluginNavs::actionsLandingScanDefinitions() as $key => $scanDefinition ) {
			$definitions[ $key ] = [
				'key'                 => $key,
				'label'               => $this->groupLabel( $key, $scanDefinition[ 'label' ] ),
				'icon_class'          => $scanDefinition[ 'rail_icon_class' ],
				'detail_shell'        => $this->detailShell( $key ),
				'card_type'           => $this->cardType( $key ),
				'drill_hint_single'   => $this->drillHintSingle( $key ),
				'drill_hint_plural'   => $this->drillHintPlural( $key ),
				'summary_keys'        => $scanDefinition[ 'summary_keys' ],
				'render_action_class' => $this->renderActionClass( $key ),
				'render_action_data'  => $this->renderActionData( $key ),
			];
		}

		$definitions[ 'maintenance' ] = [
			'key'                 => 'maintenance',
			'label'               => __( 'Maintenance Items', 'wp-simple-firewall' ),
			'icon_class'          => 'bi bi-wrench',
			'detail_shell'        => 'maintenance',
			'card_type'           => 'category',
			'drill_hint_single'   => '',
			'drill_hint_plural'   => '',
			'summary_keys'        => [],
			'render_action_class' => Maintenance::class,
			'render_action_data'  => [],
		];

		$this->definitions = $definitions;
		return $this->definitions;
	}

	public function groupKeyForSummaryKey( string $summaryKey ) :string {
		$definition = PluginNavs::actionsLandingScanDefinitionForSummaryKey( $summaryKey );
		return $definition === null
			? 'maintenance'
			: $definition[ 'slug' ];
	}

	private function groupLabel( string $groupKey, string $defaultLabel ) :string {
		switch ( $groupKey ) {
			case 'malware':
				return __( 'Malware Detections', 'wp-simple-firewall' );

			case 'file_locker':
				return __( 'File Changes', 'wp-simple-firewall' );

			default:
				return $defaultLabel;
		}
	}

	/**
	 * @return class-string<BaseAction>
	 */
	private function renderActionClass( string $groupKey ) :string {
		switch ( $groupKey ) {
			case 'wordpress':
				return Wordpress::class;

			case 'plugins':
				return Plugins::class;

			case 'themes':
				return Themes::class;

			case 'vulnerabilities':
				return Vulnerabilities::class;

			case 'malware':
				return Malware::class;

			case 'file_locker':
				return FileLocker::class;

			default:
				return Maintenance::class;
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private function renderActionData( string $groupKey ) :array {
		return \in_array( $groupKey, [
			'wordpress',
			'plugins',
			'themes',
			'malware',
			'file_locker',
		], true )
			? ( new ActionsQueueScanResultsOptions() )->buildActionData()
			: [];
	}

	/**
	 * @return 'asset_cards'|'direct_table'|'maintenance'
	 */
	private function detailShell( string $groupKey ) :string {
		switch ( $groupKey ) {
			case 'plugins':
			case 'themes':
			case 'file_locker':
				return 'asset_cards';

			case 'maintenance':
				return 'maintenance';

			default:
				return 'direct_table';
		}
	}

	private function drillHintSingle( string $groupKey ) :string {
		switch ( $groupKey ) {
			case 'plugins':
			case 'themes':
				return __( 'View %s file', 'wp-simple-firewall' );

			case 'wordpress':
			case 'malware':
			case 'file_locker':
				return __( 'View %s file', 'wp-simple-firewall' );

			default:
				return '';
		}
	}

	private function drillHintPlural( string $groupKey ) :string {
		switch ( $groupKey ) {
			case 'plugins':
			case 'themes':
				return __( 'View %s files', 'wp-simple-firewall' );

			case 'wordpress':
			case 'malware':
			case 'file_locker':
				return __( 'View %s files', 'wp-simple-firewall' );

			default:
				return '';
		}
	}

	/**
	 * @return 'expandable'|'linked'|'category'
	 */
	private function cardType( string $groupKey ) :string {
		switch ( $groupKey ) {
			case 'vulnerabilities':
				return 'linked';
			case 'maintenance':
				return 'category';
			default:
				return 'expandable';
		}
	}
}
