<?php

use FernleafSystems\Wordpress\Plugin\Shield;

class ICWP_WPSF_FeatureHandler_Lockdown extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @param string $namespace
	 * @return bool
	 */
	public function isPermittedAnonRestApiNamespace( $namespace ) {
		/** @var Shield\Modules\Lockdown\Options $opts */
		$opts = $this->getOptions();
		return in_array( $namespace, $opts->getRestApiAnonymousExclusions() );
	}

	protected function preProcessOptions() {
		$this->cleanApiExclusions();
	}

	private function cleanApiExclusions() {
		/** @var Shield\Modules\Lockdown\Options $opts */
		$opts = $this->getOptions();
		$opts->setOpt(
			'api_namespace_exclusions',
			$this->cleanStringArray( $opts->getRestApiAnonymousExclusions(), '#[^a-z0-9_-]#i' )
		);
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() :string {
		return 'Lockdown';
	}
}