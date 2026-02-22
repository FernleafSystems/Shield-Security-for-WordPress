<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

abstract class PageModeLandingBase extends BasePluginAdminPage {

	abstract protected function getLandingTitle() :string;

	abstract protected function getLandingSubtitle() :string;

	abstract protected function getLandingIcon() :string;

	protected function getLandingContent() :array {
		return [];
	}

	protected function getLandingFlags() :array {
		return [];
	}

	protected function getLandingHrefs() :array {
		return [];
	}

	protected function getLandingStrings() :array {
		return [];
	}

	protected function getLandingVars() :array {
		return [];
	}

	protected function getRenderData() :array {
		$data = [
			'imgs'    => [
				'inner_page_title_icon' => $this->buildLandingIconClass( $this->getLandingIcon() ),
			],
			'strings' => \array_merge(
				[
					'inner_page_title'    => $this->getLandingTitle(),
					'inner_page_subtitle' => $this->getLandingSubtitle(),
				],
				$this->getLandingStrings()
			),
		];

		$content = $this->getLandingContent();
		if ( !empty( $content ) ) {
			$data[ 'content' ] = $content;
		}

		$flags = $this->getLandingFlags();
		if ( !empty( $flags ) ) {
			$data[ 'flags' ] = $flags;
		}

		$hrefs = $this->getLandingHrefs();
		if ( !empty( $hrefs ) ) {
			$data[ 'hrefs' ] = $hrefs;
		}

		$vars = $this->getLandingVars();
		if ( !empty( $vars ) ) {
			$data[ 'vars' ] = $vars;
		}

		return $data;
	}

	protected function buildLandingIconClass( string $icon ) :string {
		return self::con()->svgs->iconClass( $icon );
	}
}
