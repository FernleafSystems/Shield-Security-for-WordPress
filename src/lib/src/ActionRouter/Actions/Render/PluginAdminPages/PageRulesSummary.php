<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\ConditionsVO;
use FernleafSystems\Wordpress\Services\Services;

class PageRulesSummary extends PageRulesBase {

	public const SLUG = 'admin_plugin_page_rules_summary';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/rules_summary.twig';

	protected function getPageContextualHrefs() :array {
		$con = self::con();
		return [
			[
				'title' => __( 'Rules Builder', 'wp-simple-firewall' ),
				'href'  => $con->plugin_urls->adminTopNav( PluginNavs::NAV_RULES, PluginNavs::SUBNAV_RULES_BUILD ),
			],
		];
	}

	protected function getRenderData() :array {
		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getRenderData(),
			$this->getRenderDataRules()
		);
	}

	protected function getRenderDataRules() :array {
		$components = [
			'hooks' => [
				'immediate'
			],
		];
		add_action( 'apto/services/pre_render_twig', function ( $env ) {
			/** @var \Twig\Environment $env */
			$env->addExtension( new \Twig\Extension\DebugExtension() );
		} );

		$simpleID = 0;

		$rules = [];
		foreach ( self::con()->rules->getRules() as $rule ) {

			if ( empty( $rule->wp_hook ) ) {
				$rule->wp_hook = 'immediate';
			}

			$components[ 'hooks' ][] = $rule->wp_hook;

			try {
				$data = $rule->getRawData();
				$data[ 'simple_id' ] = $simpleID++;
				$data[ 'conditions' ] = $rule->conditions;
				$data[ 'conditions_parsed' ] = $this->parseConditionsForDisplay( $rule->conditions );
				$rules[ $data[ 'simple_id' ] ] = $data;
			}
			catch ( \Exception $e ) {
			}
		}

		$components[ 'hooks' ] = \array_unique( $components[ 'hooks' ] );

		return [
			'vars' => [
				'components' => $components,
				'rules'      => $rules,
			]
		];
	}

	private function parseConditionsForDisplay( ConditionsVO $conditionsVO ) :array {
		$parsed = [
			'type'   => $conditionsVO->type,
			'logic'  => $conditionsVO->logic,
			'params' => $conditionsVO->params,
		];
		if ( $parsed[ 'type' ] === 'single' ) {
			$conditionClass = $conditionsVO->conditions;
			/** @var Base $condition */
			$condition = new $conditionClass();
			$parsed[ 'conditions' ] = [
				'name'        => $condition->getName(),
				'slug'        => $condition->getSlug(),
				'class'       => $conditionClass,
				'description' => $condition->getDescription(),
			];
		}
		elseif ( $parsed[ 'type' ] === 'group' ) {
			$parsed[ 'conditions' ] = [];
			foreach ( $conditionsVO->conditions as $conditions ) {
				$parsed[ 'conditions' ][] = $this->parseConditionsForDisplay( $conditions );
			}
		}
		/** else callable */
		return $parsed;
	}

	protected function getInnerPageTitle() :string {
		return __( 'Active Rules Summary', 'wp-simple-firewall' );
	}

	protected function getInnerPageSubTitle() :string {
		return __( 'View all active rules on your site at-a-glance', 'wp-simple-firewall' );
	}
}