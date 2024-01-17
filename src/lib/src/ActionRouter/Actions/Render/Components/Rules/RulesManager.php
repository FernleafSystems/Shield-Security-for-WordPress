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

		$customRules = \array_map(
			function ( $rule ) {
				return [
					'rule_id'     => $rule->id,
					'name'        => $rule->name,
					'description' => $rule->description,
					'created_at'  => Services::WpGeneral()->getTimeStampForDisplay( $rule->updated_at ),
					'version'     => $rule->builder_version ?? '0',
					'is_active'   => $rule->is_active,
					'can_export'  => $rule->can_export,
					'is_viable'   => !empty( $rule->form ),
					'href_edit'   => URL::Build(
						self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_RULES, PluginNavs::SUBNAV_RULES_BUILD ),
						[
							'edit_rule_id' => $rule->id,
						]
					)
				];
			},
			( new RuleRecords() )->getCustom()
		);

		return [
			'flags'   => [
				'has_custom_rules' => !empty( $customRules ),
				'show_export'      => false,
			],
			'imgs'    => [
				'icon_delete' => $con->svgs->raw( 'trash3-fill.svg' ),
				'icon_edit'   => $con->svgs->raw( 'pencil-square.svg' ),
				'drag_handle' => $con->svgs->raw( 'arrows-move.svg' ),
			],
			'strings' => [
				'activate'      => __( 'Activate Rule', 'wp-simple-firewall' ),
				'deactivate'    => __( 'Deactivate Rule', 'wp-simple-firewall' ),
				'set_to_export' => __( 'Allow Export', 'wp-simple-firewall' ),
				'set_no_export' => __( 'Prevent Export', 'wp-simple-firewall' ),
				'name'          => __( 'Name' ),
				'description'   => __( 'Description' ),
				'updated'       => __( 'Updated' ),
				'active'        => __( 'Active' ),
				'action'        => __( 'Action', 'wp-simple-firewall' ),
				'export'        => __( 'Export' ),
				'order'         => __( 'Order' ),
			],
			'vars'    => [
				'custom_rules' => $customRules,
			],
		];
	}
}