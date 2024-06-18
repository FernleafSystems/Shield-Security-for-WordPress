<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\SecurityRules;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Rules\{
	Ops as SecurityRulesDB,
	RuleRecords
};
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class BuildSecurityRulesTableData extends \FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildTableData {

	protected function loadRecordsWithSearch() :array {
		return $this->loadRecordsWithDirectQuery();
	}

	protected function getSearchPanesData() :array {
		return ( new BuildSearchPanesData() )->build();
	}

	/**
	 * @param array[] $records
	 */
	protected function buildTableRowsFromRawRecords( array $records ) :array {
		return \array_values( \array_filter( \array_map(
			function ( SecurityRulesDB\Record $rule ) {
				$data = $rule->getRawData();
				$data[ 'rid' ] = $rule->id;
				$data[ 'active' ] = $this->colActive( $rule );
				$data[ 'actions' ] = $this->colActions( $rule );
				$data[ 'details' ] = $this->colDetails( $rule );
				$data[ 'drag' ] = $this->colDrag( $rule );
				$data[ 'version' ] = $rule->builder_version ?? '0';
				$data[ 'created_since' ] = Services::WpGeneral()->getTimeStampForDisplay( $rule->updated_at );
				$data[ 'is_viable' ] = !empty( $rule->form );
				return $data;
			},
			$records
		) ) );
	}

	private function colActive( SecurityRulesDB\Record $rule ) :string {
		return self::con()
			->comps
			->render
			->setTemplate( '/wpadmin/components/rules/activate_switch.twig' )
			->setData( [
				'strings' => [
					'title' => $rule->is_active ? __( 'Deactivate Rule', 'wp-simple-firewall' ) : __( 'Activate Rule', 'wp-simple-firewall' ),
				],
				'flags'   => [
					'is_checked' => $rule->is_active,
					'is_viable'  => !empty( $rule->form ),
				],
				'vars'    => [
					'action' => $rule->is_active ? 'deactivate' : 'activate',
					'rid'    => $rule->id,
				],
			] )
			->render();
	}

	private function colActions( SecurityRulesDB\Record $rule ) :string {
		$con = self::con();
		return $con
			->comps
			->render
			->setTemplate( '/wpadmin/components/rules/action_buttons.twig' )
			->setData( [
				'strings' => [
					'edit'   => __( 'Edit Rule', 'wp-simple-firewall' ),
					'delete' => __( 'Delete Rule', 'wp-simple-firewall' ),
				],
				'hrefs'   => [
					'edit' => URL::Build(
						self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_RULES, PluginNavs::SUBNAV_RULES_BUILD ),
						[ 'edit_rule_id' => $rule->id, ]
					),
				],
				'imgs'    => [
					'icon_delete' => $con->svgs->raw( 'trash3-fill.svg' ),
					'icon_edit'   => $con->svgs->raw( 'pencil-square.svg' ),
				],
				'vars'    => [
					'rid' => $rule->id,
				],
			] )
			->render();
	}

	private function colDetails( SecurityRulesDB\Record $rule ) :string {
		return sprintf( '<h6>%s</h6><p class="m-0">%s</p>', $rule->name, $rule->description );
	}

	private function colDrag( SecurityRulesDB\Record $rule ) :string {
		return sprintf( '<div class="h-100 d-flex justify-content-center align-items-center" data-rid="%s">%s</div>',
			$rule->id, self::con()->svgs->raw( 'arrows-move.svg' ) );
	}

	protected function countTotalRecords() :int {
		return \count( $this->getRecordsLoader()->getCustom() );
	}

	protected function countTotalRecordsFiltered() :int {
		return \count( $this->getRecordsLoader()->getCustom() );
	}

	protected function getRecordsLoader() :RuleRecords {
		return new RuleRecords();
	}

	/**
	 * @return array[]
	 */
	protected function getRecords( array $wheres = [], int $offset = 0, int $limit = 0 ) :array {
		$loader = $this->getRecordsLoader();
		$loader->deleteOldDrafts();
		return \array_slice( $loader->getCustom(), $offset, $limit );
	}
}