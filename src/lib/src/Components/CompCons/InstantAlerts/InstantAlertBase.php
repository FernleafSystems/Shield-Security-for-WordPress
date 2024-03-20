<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\InstantAlerts;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Email\EmailVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class InstantAlertBase {

	use ExecOnce;
	use PluginControllerConsumer;

	protected $alertActionData = [];

	abstract protected function alertAction() :string;

	protected function run() {
		add_action( self::con()->prefix( 'plugin_shutdown' ), function () {
			if ( $this->isSendAlert() ) {
				self::con()->email_con->sendVO(
					EmailVO::Factory(
						self::con()->comps->opts_lookup->getReportEmail(),
						sprintf( '%s: %s', __( 'Alert', 'wp-simple-firewall' ), $this->alertTitle() ),
						self::con()->action_router->render( $this->alertAction(), [ 'alert_data' => $this->getAlertActionData() ] )
					)
				);
			}
		} );
	}

	protected function isSendAlert() :bool {
		return !empty( \array_filter( $this->getAlertActionData() ) );
	}

	protected function getAlertActionData() :array {
		return $this->alertActionData ?? ( $this->alertActionData = [] );
	}

	protected function alertTitle() :string {
		return 'Alert!';
	}
}