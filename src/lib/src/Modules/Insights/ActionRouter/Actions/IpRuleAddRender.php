<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\UI;

class IpRuleAddRender extends IpsBase {

	const SLUG = 'ip_rule_add_render';

	/**
	 * @inheritDoc
	 */
	protected function exec() {
		$resp = $this->response();
		/** @var UI $UI */
		$UI = $this->primary_mod->getUIHandler();
		$resp->action_response_data = [
			'success'      => true,
			'title'        => __( 'Add New IP Rule', 'wp-simple-firewall' ),
			'body'         => $UI->renderForm_IpAdd(),
			'modal_class'  => ' ',
			'modal_static' => true,
		];
	}
}