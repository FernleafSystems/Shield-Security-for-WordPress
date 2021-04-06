<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ParametersToScan {

	use ModConsumer;

	private $params = [];

	public function retrieve() :array {

		// Ensure strings and remove non-scalar entries
		$this->params = array_map( 'strval', array_filter(
			Services::Request()->getRawRequestParams( false ),
			function ( $value ) {
				return is_scalar( $value );
			}
		) );

		if ( !empty( $this->params ) ) {
			$this->removeParamsBasedOnPageName();
		}

		if ( !empty( $this->params ) ) {
			$this->removeAllPageParams();
		}

		return $this->params;
	}

	private function removeAllPageParams() {
		foreach ( $this->getAllPageWhitelistedParameters() as $listParam ) {

			if ( preg_match( '#^/.+/$#', $listParam ) ) {
				foreach ( array_keys( $this->params ) as $param ) {
					if ( preg_match( $listParam, $param ) ) {
						unset( $this->params[ $param ] );
					}
				}
			}
			elseif ( isset( $params[ $listParam ] ) ) {
				unset( $params[ $listParam ] );
			}
		}
	}

	private function removeParamsBasedOnPageName() {
		// Now we run through the list of whitelist pages
		$thePage = Services::Request()->getPath();
		foreach ( $this->getWhitelistedParameters() as $pageName => $pageParams ) {

			// if the page is white listed
			if ( $pageName !== '*' && strpos( $thePage, $pageName ) !== false ) {

				/**
				 * If the page has no parameters, then remove all parameters to scan
				 * Otherwise, remove only those parameter specified
				 */
				$this->params = empty( $pageParams ) ? []
					: array_diff_key( $this->params, array_flip( $pageParams ) );
				break;
			}
		}
	}

	private function getAllPageWhitelistedParameters() :array {
		$all = $this->getWhitelistedParameters()[ '*' ] ?? [];
		return is_array( $all ) ? $all : [];
	}

	private function getWhitelistedParameters() :array {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return Services::DataManipulation()
					   ->mergeArraysRecursive(
						   $opts->getDef( 'default_whitelist' ),
						   $opts->getCustomWhitelist()
					   );
	}
}