<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Utilities\Net\RequestIpDetect;

class IpDetect extends Base {

	public const SLUG = 'ip_detect';

	public function getName() :string {
		return __( 'Visitor IP', 'wp-simple-firewall' );
	}

	protected function getStepRenderData() :array {
		$allIPs = $this->gatherUniqueIpSources();
		return [
			'hrefs'   => [
				'visitor_ip' => 'https://clk.shldscrty.com/visitorip',
			],
			'flags'   => [
				'has_none'     => \count( $allIPs ) === 0,
				'has_only_1'   => \count( $allIPs ) === 1, // step is skipped
				'has_multiple' => \count( $allIPs ) > 1,
			],
			'vars'    => [
				'video_id' => '269189603',
				'the_ip'   => self::con()->this_req->ip,
				'all_ips'  => $allIPs,
			],
			'strings' => [
				'step_title' => 'Setup Correct IP Address Detection',
			],
		];
	}

	public function processStepFormSubmit( array $form ) :Shield\Utilities\Response {
		$source = $form[ 'ip_source' ] ?? '';
		if ( empty( $source ) ) {
			throw new \Exception( 'Not a valid request' );
		}

		self::con()
			->opts
			->optSet( 'visitor_address_source', $source )
			->store();

		$resp = parent::processStepFormSubmit( $form );
		$resp->success = true;
		$resp->message = __( 'Visitor IP address source set', 'wp-simple-firewall' );
		return $resp;
	}

	private function gatherUniqueIpSources() :array {
		$allIPs = [];
		foreach ( ( new RequestIpDetect() )->getPublicRequestIPData()[ 'all_ips' ] as $source => $ips ) {
			$allIPs[ $source ] = \current( $ips );
		}
		return \array_unique( $allIPs );
	}

	public function skipStep() :bool {
		return \count( $this->gatherUniqueIpSources() ) === 1;
	}
}