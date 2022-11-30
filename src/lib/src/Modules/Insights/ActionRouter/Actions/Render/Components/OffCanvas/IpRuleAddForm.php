<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\OffCanvas;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\IPs\FormIpRuleAdd;

class IpRuleAddForm extends OffCanvasBase {

	public const SLUG = 'offcanvas_ip_rule_add_form';

	protected function buildCanvasTitle() :string {
		return __( 'Add New IP Rule', 'wp-simple-firewall' );
	}

	protected function buildCanvasBody() :string {
		return $this->getCon()
					->getModule_Insights()
					->getActionRouter()
					->render( FormIpRuleAdd::SLUG );
	}
}