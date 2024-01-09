<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\Rules\RuleRecords;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class RulesManager extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_rules_rules_manager';
	public const TEMPLATE = '/components/rules/rules_manager.twig';

	protected function getRenderData() :array {
		$con = self::con();

		( new RuleRecords() )->deleteOldDrafts();

		$customRules = [];
		foreach ( ( new RuleRecords() )->getCustom() as $rule ) {
			$customRules[ $rule->id ] = [
				'rule_id'     => $rule->id,
				'name'        => $rule->name,
				'description' => $rule->description,
				'created_at'  => Services::WpGeneral()->getTimeStampForDisplay( $rule->updated_at ),
				'version'     => $rule->builder_version ?? '0',
				'is_active'   => $rule->is_active,
				'can_export'  => $rule->can_export,
				'is_viable'   => !empty( $rule->form ),
				'href_edit'   => URL::Build(
					$con->plugin_urls->adminTopNav( PluginNavs::NAV_RULES, PluginNavs::SUBNAV_RULES_BUILD ),
					[
						'edit_rule_id' => $rule->id,
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