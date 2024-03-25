<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Rules\RuleRecords;

class RulesManager extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_rules_rules_manager';
	public const TEMPLATE = '/components/rules/rules_manager.twig';

	protected function getRenderData() :array {
		$records = new RuleRecords();
		$records->deleteOldDrafts();
		return [
			'flags'   => [
				'has_custom_rules' => !empty( $records->getCustom() ),
			],
			'strings' => [
				'set_to_export' => __( 'Allow Export', 'wp-simple-firewall' ),
				'set_no_export' => __( 'Prevent Export', 'wp-simple-firewall' ),
			],
		];
	}
}