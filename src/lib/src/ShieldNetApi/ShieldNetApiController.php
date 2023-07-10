<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\ShieldNET\BuildData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Reputation\SendIPReputation;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property ShieldNetApiDataVO $vo
 */
class ShieldNetApiController extends DynPropertiesClass {

	use ExecOnce;
	use ModConsumer;
	use PluginCronsConsumer;

	protected function run() {
		$this->setupCronHooks();
	}

	/**
	 * Automatically throttles request because otherwise PRO-nulled versions of Shield will cause
	 * overload on our API.
	 *
	 * Note To Plugin 'Null'ers:
	 * PRO features that require handshaking won't work even if you null the plugin because our
	 * API will always reject those requests. Don't fiddle with this function, please.  You may get
	 * away with nulling the plugin for some PRO features, but you can't 'null' our API, sorry.
	 */
	public function canHandshake() :bool {
		$req = Services::Request();
		$now = $req->ts();

		$canAttempt = ( $this->vo->last_handshake_at === 0 || $now - $this->vo->last_handshake_at > \MONTH_IN_SECONDS )
					  && $now - MINUTE_IN_SECONDS*min( $this->vo->handshake_fail_count, 60 ) > $this->vo->last_handshake_attempt_at;

		if ( $canAttempt && $req->query( ActionData::FIELD_EXECUTE ) !== 'snapi_handshake' ) {
			$this->vo->last_handshake_attempt_at = $now;
			$this->storeVoData();

			$handshakeSuccess = ( new ShieldNetApi\Handshake\Verify() )
				->setMod( $this->mod() )
				->run();

			if ( $handshakeSuccess ) {
				$this->vo->last_handshake_at = $now;
				$this->vo->handshake_fail_count = 0;
			}
			else {
				$this->vo->handshake_fail_count++;
			}

			$this->storeVoData();
		}

		return $this->vo->last_handshake_at > 0;
	}

	public function storeVoData() {
		$this->vo->data_last_saved_at = Services::Request()->ts();
		$this->getOptions()->setOpt( 'snapi_data', $this->vo->getRawData() );
		$this->mod()->saveModOptions();
	}

	/**
	 * @inerhitDoc
	 */
	public function __get( string $key ) {
		/** @var Plugin\Options $opts */
		$opts = $this->getOptions();

		$value = parent::__get( $key );

		switch ( $key ) {

			case 'vo':
				if ( empty( $value ) ) {
					$data = $opts->getOpt( 'snapi_data', [] );
					$value = ( new ShieldNetApiDataVO() )->applyFromArray( is_array( $data ) ? $data : [] );
					$this->vo = $value;
				}
				break;

			default:
				break;
		}

		return $value;
	}

	public function runHourlyCron() {
		$con = $this->con();
		$modPlugin = $con->getModule_Plugin();
		/** @var Plugin\Options $modOpts */
		$modOpts = $modPlugin->getOptions();
		if ( is_main_network() && $modOpts->isOpt( 'enable_shieldnet', 'Y' ) && $con->isPremiumActive()
			 && $this->canStoreDataReliably() && $this->canHandshake() ) {

			$this->sendIPReputationData();
		}
	}

	private function sendIPReputationData() {
		$req = Services::Request();
		if ( $req->carbon()->subDay()->timestamp > $this->vo->last_send_iprep_at ) {
			$this->vo->last_send_iprep_at = $req->ts();
			$this->storeVoData();

			$data = ( new BuildData() )
				->setMod( $this->con()->getModule_IPs() )
				->build();
			if ( !empty( $data ) ) {
				( new SendIPReputation() )
					->setMod( $this->mod() )
					->send( $data );
			}
		}
	}

	/**
	 * Ensuring that previous timestamps are stored recently, we prevent spurious requests being
	 * sent to the API when websites can't actually store data to its own options stores.
	 *
	 * So if the timestamp for the last store is too far in the past, we believe we can't reliably
	 * store data.
	 */
	public function canStoreDataReliably() :bool {
		if ( Services::Request()->carbon()->subHours( 2 )->timestamp > $this->vo->data_last_saved_at ) {
			$can = false;
			$this->storeVoData();
		}
		else {
			$can = true;
		}
		return $can;
	}
}