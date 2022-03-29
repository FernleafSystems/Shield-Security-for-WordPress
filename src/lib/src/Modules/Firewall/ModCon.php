<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

	protected function enumRuleBuilders() :array {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$rules = [];

		foreach (
			[
				Rules\Build\FirewallSqlQueries::class,
				Rules\Build\FirewallDirTraversal::class,
				Rules\Build\FirewallFieldTruncation::class,
				Rules\Build\FirewallWordpressTerms::class,
				Rules\Build\FirewallPhpCode::class,
				Rules\Build\FirewallLeadingSchema::class,
				Rules\Build\FirewallAggressive::class,
				Rules\Build\FirewallExeFileUploads::class,
			] as $blockTypeClass
		) {
			if ( $opts->isOpt( 'block_'.$blockTypeClass::SCAN_CATEGORY, 'Y' ) ) {
				$rules[] = $blockTypeClass;
			}
		}

		return $rules;
	}

	public function getBlockResponse() :string {
		$response = $this->getOptions()->getOpt( 'block_response', '' );
		return !empty( $response ) ? $response : 'redirect_die_message'; // TODO: use default
	}

	public function getTextOptDefault( string $key ) :string {

		switch ( $key ) {
			case 'text_firewalldie':
				$text = sprintf(
					__( "You were blocked by the %s Firewall.", 'wp-simple-firewall' ),
					'<a href="https://wordpress.org/plugins/wp-simple-firewall/" target="_blank">'.$this->getCon()
																										->getHumanName().'</a>'
				);
				break;

			default:
				$text = parent::getTextOptDefault( $key );
				break;
		}
		return $text;
	}
}