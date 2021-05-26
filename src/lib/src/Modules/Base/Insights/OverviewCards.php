<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;

class OverviewCards {

	use Shield\Modules\ModConsumer;

	public function build() :array {
		$mod = $this->getMod();
		return [
			$this->getMod()->getModSlug( false ) => [
				'title'        => $this->getSectionTitle(),
				'subtitle'     => $mod->getStrings()->getModTagLine(),
				'href_options' => $mod->getUrl_AdminPage(),
				'cards'        => $this->buildCards()
			]
		];
	}

	protected function buildCards() :array {
		return array_filter( array_merge( $this->buildCommonCards(), $this->buildModCards() ) );
	}

	protected function buildModCards() :array {
		return [];
	}

	protected function buildCommonCards() :array {
		return [
			'mod' => $this->getModDisabledCard()
		];
	}

	protected function getSectionTitle() :string {
		return $this->getMod()->getMainFeatureName();
	}

	protected function getSectionSubTitle() :string {
		return $this->getMod()->getStrings()->getModTagLine();
	}

	protected function getModDisabledCard() :array {
		$mod = $this->getMod();
		$card = [];
		if ( $mod->getOptions()->optExists( $mod->getEnableModOptKey() ) && !$mod->isModOptEnabled() ) {
			$card = [
				'name'    => sprintf( '%s: %s',
					$this->getMod()->getMainFeatureName(), __( 'Disabled', 'wp-simple-firewall' ) ),
				'summary' => __( 'All features of this module are completely disabled', 'wp-simple-firewall' ),
				'state'   => -2,
				'href'    => $mod->getUrl_DirectLinkToOption( $mod->getEnableModOptKey() ),
			];
		}
		return $card;
	}
}