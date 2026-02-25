<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;
use FernleafSystems\Wordpress\Services\Services;

trait InvestigationContextLinks {

	protected function getUserHref( int $uid ) :string {
		$user = $this->resolveUser( $uid );
		return empty( $user )
			? \sprintf( 'Unavailable (ID:%s)', $uid )
			: $this->renderAnchorSpec(
				$this->buildAnchorSpec(
					self::con()->plugin_urls->investigateByUser( (string)$uid ),
					(string)$user->user_login
				)
			);
	}

	protected function getIpAnalysisLink( string $ip ) :string {
		$content = parent::getIpAnalysisLink( $ip );
		if ( Services::IP()->isValidIp( $ip ) ) {
			$content .= $this->renderAnchorSpec( $this->buildInvestigateIpDeeplinkSpec( $ip ) );
		}
		return $content;
	}

	protected function buildInvestigateAssetLinkSpec( string $subjectType, string $subjectId ) :array {
		$subjectId = \trim( $subjectId );
		if ( empty( $subjectId ) ) {
			return [];
		}

		$subjectType = \strtolower( \trim( $subjectType ) );
		if ( $subjectType === InvestigationTableContract::SUBJECT_TYPE_PLUGIN ) {
			return $this->buildAnchorSpec(
				self::con()->plugin_urls->investigateByPlugin( $subjectId ),
				__( 'Investigate Plugin', 'wp-simple-firewall' )
			);
		}
		if ( $subjectType === InvestigationTableContract::SUBJECT_TYPE_THEME ) {
			return $this->buildAnchorSpec(
				self::con()->plugin_urls->investigateByTheme( $subjectId ),
				__( 'Investigate Theme', 'wp-simple-firewall' )
			);
		}
		return [];
	}

	protected function renderAnchorSpecs( array $specs, string $separator = ' | ' ) :string {
		return \implode( $separator, \array_values( \array_filter( \array_map(
			fn( array $spec ) => $this->renderAnchorSpec( $spec ),
			$specs
		), '\strlen' ) ) );
	}

	protected function renderAnchorSpec( array $spec ) :string {
		$href = \trim( (string)( $spec[ 'href' ] ?? '' ) );
		$label = (string)( $spec[ 'label' ] ?? '' );
		$icon = (string)( $spec[ 'icon' ] ?? '' );

		if ( empty( $href ) || ( $label === '' && $icon === '' ) ) {
			return '';
		}
		$href = esc_url( $href );
		if ( empty( $href ) ) {
			return '';
		}

		$classAttr = \trim( (string)( $spec[ 'class' ] ?? '' ) );
		$titleAttr = \trim( (string)( $spec[ 'title' ] ?? '' ) );

		$inner = $icon === ''
			? esc_html( $label )
			: ( $label === ''
				? \sprintf( '<i class="%s" aria-hidden="true"></i>', esc_attr( $icon ) )
				: \sprintf( '<i class="%s" aria-hidden="true"></i> %s', esc_attr( $icon ), esc_html( $label ) ) );

		return \sprintf(
			'<a href="%s"%s%s>%s</a>',
			$href,
			$classAttr === '' ? '' : \sprintf( ' class="%s"', esc_attr( $classAttr ) ),
			$titleAttr === '' ? '' : \sprintf( ' title="%s"', esc_attr( $titleAttr ) ),
			$inner
		);
	}

	protected function buildAnchorSpec(
		string $href,
		string $label,
		string $class = '',
		string $title = '',
		string $icon = ''
	) :array {
		return [
			'href'  => $href,
			'label' => $label,
			'class' => $class,
			'title' => $title,
			'icon'  => $icon,
		];
	}

	private function buildInvestigateIpDeeplinkSpec( string $ip ) :array {
		return $this->buildAnchorSpec(
			self::con()->plugin_urls->investigateByIp( $ip ),
			'',
			'ms-1 investigate-ip-deeplink',
			__( 'Investigate IP', 'wp-simple-firewall' ),
			self::con()->svgs->iconClass( 'box-arrow-up-right' )
		);
	}
}
