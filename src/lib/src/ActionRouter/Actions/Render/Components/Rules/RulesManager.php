<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\Rules\Ops as RulesDB;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class RulesManager extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_rules_rules_manager';
	public const TEMPLATE = '/components/rules/rules_manager.twig';

	protected function getRenderData() :array {
		$con = self::con();

		$dbh = $con->db_con->getDbH_Rules();
		/** @var RulesDB\Select $selector */
		$selector = self::con()->db_con->getDbH_Rules()->getQuerySelector();
		/** @var RulesDB\Record[] $records */
		$records = $selector->filterByType( $dbh::TYPE_CUSTOM )->queryWithResult();

		$customRules = [];
		foreach ( $records as $ruleRecord ) {
			$customRules[ $ruleRecord->id ] = [
				'rule_id'     => $ruleRecord->id,
				'name'        => $ruleRecord->name,
				'description' => $ruleRecord->description,
				'created_at'  => $ruleRecord->created_at ?? 0,
				'version'     => $ruleRecord->builder_version ?? '0',
				'is_active'   => $ruleRecord->is_active,
				'can_export'  => $ruleRecord->can_export,
				'href_edit'   => URL::Build(
					$con->plugin_urls->adminTopNav( PluginNavs::NAV_RULES, PluginNavs::SUBNAV_RULES_BUILD ),
					[
						'edit_rule_id' => $ruleRecord->id,
					]
				)
			];
		}

		return [
			'flags'   => [
				'has_custom_rules' => !empty( $customRules ),
				'can_create_rule'  => $con->isPremiumActive(),
			],
			'imgs'    => [
				'icon_delete' => $con->svgs->raw( 'trash3-fill.svg' ),
				'icon_edit'   => $con->svgs->raw( 'pencil-square.svg' ),
			],
			'strings' => [
				'activate'      => __( 'Activate Rule', 'wp-simple-firewall' ),
				'deactivate'    => __( 'Deactivate Rule', 'wp-simple-firewall' ),
				'set_to_export' => __( 'Allow Export', 'wp-simple-firewall' ),
				'set_no_export' => __( 'Prevent Export', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'custom_rules' => $customRules,
			],
		];
	}
}