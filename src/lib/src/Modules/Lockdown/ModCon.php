<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

	protected function enumRuleBuilders() :array {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return [
			$opts->isXmlrpcDisabled() ? Rules\Build\DisableXmlrpc::class : null,
			$opts->isOptFileEditingDisabled() ? Rules\Build\DisableFileEditing::class : null,
			$opts->isOpt( 'block_author_discovery', 'Y' ) ? Rules\Build\IsRequestAuthorDiscovery::class : null,
			$opts->isOpt( 'hide_wordpress_generator_tag', 'Y' ) ? Rules\Build\HideGeneratorTag::class : null,
			( $opts->isOpt( 'force_ssl_admin', 'Y' ) && function_exists( 'force_ssl_admin' ) ) ? Rules\Build\ForceSslAdmin::class : null,
		];
	}

	/**
	 * @param string $namespace
	 * @return bool
	 */
	public function isPermittedAnonRestApiNamespace( $namespace ) {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return in_array( $namespace, $opts->getRestApiAnonymousExclusions() );
	}

	protected function preProcessOptions() {
		$this->cleanApiExclusions();
	}

	private function cleanApiExclusions() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$opts->setOpt(
			'api_namespace_exclusions',
			$this->cleanStringArray( $opts->getRestApiAnonymousExclusions(), '#[^a-z0-9_-]#i' )
		);
	}
}