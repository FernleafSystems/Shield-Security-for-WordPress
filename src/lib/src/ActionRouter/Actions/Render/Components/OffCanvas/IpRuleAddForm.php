<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IPs\FormIpRuleAdd;

class IpRuleAddForm extends OffCanvasBase {

	public const SLUG = 'offcanvas_form_ip_rule_add';

	protected function buildCanvasTitle() :string {
		return __( 'Create New IP Rule', 'wp-simple-firewall' );
	}

	protected function buildCanvasBody() :string {
		return self::con()->action_router->render( FormIpRuleAdd::SLUG );
	}
}