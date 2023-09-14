<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

	public const SLUG = 'firewall';

	protected function enumRuleBuilders() :array {
		/** @var Options $opts */
		$opts = $this->opts();

		return \array_filter(
			[
				Rules\Build\FirewallSqlQueries::class,
				Rules\Build\FirewallDirTraversal::class,
				Rules\Build\FirewallFieldTruncation::class,
				Rules\Build\FirewallWordpressTerms::class,
				Rules\Build\FirewallPhpCode::class,
				Rules\Build\FirewallAggressive::class,
				Rules\Build\FirewallExeFileUploads::class,
			],
			function ( $blockTypeClass ) use ( $opts ) {
				/** @var Rules\Build\BuildFirewallBase $blockTypeClass */
				return $opts->isOpt( 'block_'.$blockTypeClass::SCAN_CATEGORY, 'Y' );
			}
		);
	}

	public function getBlockResponse() :string {
		$response = $this->opts()->getOpt( 'block_response', '' );
		return !empty( $response ) ? $response : 'redirect_die_message'; // TODO: use default
	}
}