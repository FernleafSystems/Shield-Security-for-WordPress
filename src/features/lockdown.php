<?php

use FernleafSystems\Wordpress\Plugin\Shield;

class ICWP_WPSF_FeatureHandler_Lockdown extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return string[]
	 * @deprecated 9.2.0
	 */
	private function getRestApiAnonymousExclusions() {
		$aExcl = $this->getOpt( 'api_namespace_exclusions' );
		if ( !is_array( $aExcl ) ) {
			$aExcl = [];
		}
		return array_merge( $this->getDef( 'default_restapi_exclusions' ), $aExcl );
	}

	/**
	 * @param string $namespace
	 * @return bool
	 */
	public function isPermittedAnonRestApiNamespace( $namespace ) {
		/** @var Shield\Modules\Lockdown\Options $opts */
		$opts = $this->getOptions();
		return in_array( $namespace, $opts->getRestApiAnonymousExclusions() );
	}

	/**
	 * @return bool
	 * @deprecated 9.2.0
	 */
	public function isOptFileEditingDisabled() {
		return $this->isOpt( 'disable_file_editing', 'Y' );
	}

	/**
	 * @return bool
	 * @deprecated 9.2.0
	 */
	public function isRestApiAnonymousAccessDisabled() {
		return $this->isOpt( 'disable_anonymous_restapi', 'Y' );
	}

	/**
	 * @return bool
	 * @deprecated 9.2.0
	 */
	public function isXmlrpcDisabled() {
		return $this->isOpt( 'disable_xmlrpc', 'Y' );
	}

	protected function preProcessOptions() {
		$this->cleanApiExclusions();
	}

	/**
	 * @return $this
	 */
	private function cleanApiExclusions() {
		/** @var Shield\Modules\Lockdown\Options $opts */
		$opts = $this->getOptions();
		$aExt = $this->cleanStringArray( $opts->getRestApiAnonymousExclusions(), '#[^a-z0-9_-]#i' );
		return $this->setOpt( 'api_namespace_exclusions', $aExt );
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'Lockdown';
	}
}