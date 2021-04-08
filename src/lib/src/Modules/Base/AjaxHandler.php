<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request\FormParams;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

abstract class AjaxHandler {

	use ModConsumer;

	public function __construct() {
		add_action( 'wp_loaded', [ $this, 'init' ] );
	}

	public function init() {
		add_filter( $this->getCon()->prefix( 'ajaxAuthAction' ), [ $this, 'handleAjaxAuth' ], 10, 2 );
		add_filter( $this->getCon()->prefix( 'ajaxNonAuthAction' ), [ $this, 'handleAjaxNonAuth' ], 10, 2 );
	}

	public function handleAjaxAuth( array $ajaxResponse, string $ajaxAction ) :array {
		if ( !empty( $ajaxAction ) && ( empty( $ajaxResponse ) || !is_array( $ajaxResponse ) ) ) {
			$ajaxResponse = $this->normaliseAjaxResponse( $this->processAjaxAction( $ajaxAction ) );
		}
		return $ajaxResponse;
	}

	public function handleAjaxNonAuth( array $ajaxResponse, string $ajaxAction ) :array {
		if ( !empty( $ajaxAction ) && ( empty( $ajaxResponse ) || !is_array( $ajaxResponse ) ) ) {
			$ajaxResponse = $this->normaliseAjaxResponse( $this->processNonAuthAjaxAction( $ajaxAction ) );
		}
		return $ajaxResponse;
	}

	protected function processAjaxAction( string $action ) :array {
		return [];
	}

	protected function processNonAuthAjaxAction( string $action ) :array {
		return [];
	}

	/**
	 * We check for empty since if it's empty, there's nothing to normalize. It's a filter,
	 * so if we send something back non-empty, it'll be treated like a "handled" response and
	 * processing will finish
	 * @param array $ajaxResponse
	 * @return array
	 */
	protected function normaliseAjaxResponse( array $ajaxResponse ) :array {
		if ( !empty( $ajaxResponse ) ) {
			$ajaxResponse = array_merge(
				[
					'success'     => false,
					'page_reload' => false,
					'message'     => 'Unknown',
					'html'        => '',
				],
				$ajaxResponse
			);
		}
		return $ajaxResponse;
	}
}