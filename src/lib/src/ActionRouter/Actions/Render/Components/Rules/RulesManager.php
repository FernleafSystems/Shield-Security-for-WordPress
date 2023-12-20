<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\RuleFormBuilderVO;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class RulesManager extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_rules_rules_manager';
	public const TEMPLATE = '/components/rules/rules_manager.twig';

	protected function getRenderData() :array {
		$con = self::con();

		// TODO: move this to dedicated action handler
		$managerAction = $this->action_data[ 'manager_action' ] ?? [];
		if ( !empty( $managerAction ) ) {
			switch ( $managerAction[ 'action' ] ?? '' ) {
				case 'delete':
					$con->db_con
						->getDbH_Rules()
						->getQueryDeleter()
						->deleteById( $managerAction[ 'rule_id' ] );
					break;
				default:
					break;
			}
		}

		$customRules = [];
		foreach ( self::con()->rules->getCustomRuleForms() as $ruleRecord ) {
			$customRules[ $ruleRecord->id ] = [
				'rule_id'     => $ruleRecord->id,
				'name'        => $ruleRecord->name,
				'description' => $ruleRecord->description,
				'created_at'  => $ruleRecord->created_at ?? 0,
				'version'     => $ruleRecord->builder_version ?? '0',
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
			'hrefs'   => [
				'rules_builder' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_RULES, PluginNavs::SUBNAV_RULES_BUILD ),
			],
			'imgs'    => [
				'icon_delete' => $con->svgs->raw( 'trash3-fill.svg' ),
				'icon_edit'   => $con->svgs->raw( 'pencil-square.svg' ),
			],
			'strings' => [
				'create_custom_rule'       => __( 'Create Custom Rule', 'wp-simple-firewall' ),
				'custom_rules_unavailable' => __( 'Create Custom Rule', 'wp-simple-firewall' ),
				'title'                    => __( 'Custom Rules Manager', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'custom_rules' => $customRules,
			],
		];
	}
}