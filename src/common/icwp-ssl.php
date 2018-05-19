<?php
if ( class_exists( 'ICWP_WPSF_Ssl', false ) ) {
	return;
}

/**
 */
class ICWP_WPSF_Ssl extends ICWP_WPSF_Foundation {

	/**
	 * @var ICWP_WPSF_Ip
	 */
	protected static $oInstance = null;

	/**
	 * @return ICWP_WPSF_Ip
	 */
	public static function GetInstance() {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self();
		}
		return self::$oInstance;
	}

	/**
	 * @return bool
	 */
	public function isEnvSupported() {
		$aFunctions = array(
			'stream_context_create',
			'stream_socket_client',
			'stream_context_get_params',
			'openssl_x509_parse',
		);

		$bFunctionsAvailable = true;
		foreach ( $aFunctions as $sFunction ) {
			$bFunctionsAvailable = $bFunctionsAvailable && function_exists( $sFunction )
								   && is_callable( $bFunctionsAvailable );
		}

		return $bFunctionsAvailable;
	}

	/**
	 * @param string $sDomain
	 * @throws Exception
	 */
	public function getCertForDomain( $sDomain ) {
		if ( !$this->isEnvSupported() ) {
			throw new Exception( 'The environment does not support this' );
		}

		$rSocketClient = stream_socket_client(
			sprintf( 'ssl://%s:443', $sDomain ),
			$errno, $errstr, 2,
			STREAM_CLIENT_CONNECT,
			stream_context_create(
				array( 'ssl' => array( 'capture_peer_cert' => true ) )
			)
		);
		$aResponseParams = stream_context_get_params( $rSocketClient );
		if ( empty( $aResponseParams[ 'options' ][ 'ssl' ][ 'peer_certificate' ] ) ) {
			throw new Exception( 'Peer Certificate field was empty in the response.' );
		}
		$oParsed = openssl_x509_parse( $aResponseParams[ 'options' ][ 'ssl' ][ 'peer_certificate' ] );
		var_dump( $oParsed );
	}
}