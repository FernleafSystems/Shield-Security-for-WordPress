<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class BotSignalsController {

	use ModConsumer;
	use ExecOnce;

	/**
	 * @var NotBot\NotBotHandler
	 */
	private $handlerNotBot;

	public function getHandlerNotBot() :NotBot\NotBotHandler {
		if ( !isset( $this->handlerNotBot ) ) {
			$this->handlerNotBot = ( new NotBot\NotBotHandler() )->setMod( $this->getMod() );
		}
		return $this->handlerNotBot;
	}

	protected function run() {
		( new EventListener() )
			->setMod( $this->getMod() )
			->execute();
		add_action( 'init', function () {
			$this->getHandlerNotBot()->execute();
		} );
	}
}