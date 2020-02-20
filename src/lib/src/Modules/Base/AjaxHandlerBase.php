<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandlerBase {

	use ModConsumer;

	public function __construct() {
		add_action( 'wp_loaded', [ $this, 'init' ] );
	}

	/**
	 */
	public function init() {
		$oMod = $this->getMod();
		if ( $oMod->isModuleRequest() ) {
			add_filter( $oMod->prefix( 'ajaxAuthAction' ), [ $this, 'handleAjaxAuth' ], 10, 2 );
			add_filter( $oMod->prefix( 'ajaxNonAuthAction' ), [ $this, 'handleAjaxNonAuth' ], 10, 2 );
		}
	}

	/**
	 * @param array  $aAjaxResponse
	 * @param string $sAjaxAction
	 * @return array
	 */
	public function handleAjaxAuth( $aAjaxResponse, $sAjaxAction ) {
		if ( !empty( $sAjaxAction ) && ( empty( $aAjaxResponse ) || !is_array( $aAjaxResponse ) ) ) {
			$aAjaxResponse = $this->normaliseAjaxResponse( $this->processAjaxAction( $sAjaxAction ) );
		}
		return $aAjaxResponse;
	}

	/**
	 * @param array  $aAjaxResponse
	 * @param string $sAjaxAction
	 * @return array
	 */
	public function handleAjaxNonAuth( $aAjaxResponse, $sAjaxAction ) {
		if ( !empty( $sAjaxAction ) && ( empty( $aAjaxResponse ) || !is_array( $aAjaxResponse ) ) ) {
			$aAjaxResponse = $this->normaliseAjaxResponse( $this->processAjaxAction( $sAjaxAction ) );
		}
		return $aAjaxResponse;
	}

	/**
	 * @param string $sEncoding
	 * @return array
	 */
	protected function getAjaxFormParams( $sEncoding = 'none' ) {
		$oReq = Services::Request();
		$aFormParams = [];
		$sRaw = $oReq->post( 'form_params', '' );

		if ( !empty( $sRaw ) ) {

			$sMaybeEncoding = $oReq->post( 'enc_params' );
			if ( in_array( $sMaybeEncoding, [ 'none', 'lz-string', 'b64' ] ) ) {
				$sEncoding = $sMaybeEncoding;
			}

			switch ( $sEncoding ) {
				case 'lz-string':
					$sRaw = \LZCompressor\LZString::decompress( base64_decode( $sRaw ) );
					break;

				case 'b64':
					$sRaw = base64_decode( $sRaw );
					break;

				case 'none':
				default:
					break;
			}

			parse_str( $sRaw, $aFormParams );
		}
		return $aFormParams;
	}

	/**
	 * @param string $sAjaxAction
	 * @return array
	 */
	protected function processAjaxAction( $sAjaxAction ) {
		return [];
	}

	/**
	 * We check for empty since if it's empty, there's nothing to normalize. It's a filter,
	 * so if we send something back non-empty, it'll be treated like a "handled" response and
	 * processing will finish
	 * @param array $aAjaxResponse
	 * @return array
	 */
	protected function normaliseAjaxResponse( $aAjaxResponse ) {
		if ( !empty( $aAjaxResponse ) ) {
			$aAjaxResponse = array_merge(
				[
					'success'     => false,
					'page_reload' => false,
					'message'     => 'Unknown',
					'html'        => '',
				],
				$aAjaxResponse
			);
		}
		return $aAjaxResponse;
	}
}