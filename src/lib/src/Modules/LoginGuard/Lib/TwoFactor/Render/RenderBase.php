<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Render;

use FernleafSystems\Utilities\Data\Adapter\DynProperties;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string $login_nonce
 * @property string $rememberme
 * @property string $redirect_to
 * @property string $msg_error
 * @property string $interim_message
 */
abstract class RenderBase {

	use Shield\Modules\ModConsumer;
	use Shield\Utilities\Consumer\WpUserConsumer;
	use DynProperties;

	public function render() {
		echo $this->buildPage();
		die();
	}

	abstract protected function buildPage() :string;

	protected function getHiddenFields() :array {
		$req = Services::Request();

		$referUrl = $req->server( 'HTTP_REFERER', '' );
		if ( strpos( $referUrl, '?' ) ) {
			list( $referUrl, $referQuery ) = explode( '?', $referUrl, 2 );
		}
		else {
			$referQuery = '';
		}

		$redirectTo = $this->redirect_to;
		if ( empty( $redirectTo ) ) {

			if ( !empty( $referQuery ) ) {
				parse_str( $referQuery, $aReferQueryItems );
				if ( !empty( $aReferQueryItems[ 'redirect_to' ] ) ) {
					$redirectTo = rawurlencode( $aReferQueryItems[ 'redirect_to' ] );
				}
			}
		}

		if ( !empty( $redirectTo ) ) {
			$redirectTo = rawurlencode( $this->redirect_to );
		}

		$cancelHref = $req->post( 'cancel_href', '' );
		if ( empty( $cancelHref ) && Services::Data()->isValidWebUrl( $referUrl ) ) {
			$cancelHref = parse_url( $referUrl, PHP_URL_PATH );
		}

		global $interim_login;

		$fields = array_filter( [
			'interim-login' => $interim_login ? '1' : false,
			'login_nonce'   => $this->login_nonce,
			'rememberme'    => esc_attr( $this->rememberme ),
			'redirect_to'   => esc_attr( esc_url( $redirectTo ) ),
			'cancel_href'   => esc_attr( esc_url( $cancelHref ) ),
		] );
		$fields[ 'wp_user_id' ] = $this->getWpUser()->ID;
		return $fields;
	}

	protected function getLoginIntentExpiresAt() :int {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		$mfaCon = $mod->getMfaController();
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		return Services::Request()
					   ->carbon()
					   ->setTimestamp( $mfaCon->getActiveLoginIntents( $this->getWpUser() )[ $this->login_nonce ] ?? 0 )
					   ->addMinutes( $opts->getLoginIntentMinutes() )->timestamp;
	}
}