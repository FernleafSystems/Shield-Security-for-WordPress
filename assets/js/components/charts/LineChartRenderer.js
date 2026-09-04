import {
	CategoryScale,
	Chart as ChartJS,
	LineController,
	LineElement,
	LinearScale,
	PointElement,
	Tooltip
} from 'chart.js';

ChartJS.register( LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip );

const CHART_PALETTE = [
	'#0d6efd',
	'#198754',
	'#fd7e14',
	'#6f42c1',
	'#dc3545',
	'#20c997',
	'#6610f2'
];

export class LineChartRenderer {

	constructor( outputEl, legendEl = null ) {
		this.outputEl = outputEl;
		this.legendEl = legendEl;
		this.tooltipOutputEl = null;
		this.tooltipLabel = '';
		this.chart = null;
		this.canvas = null;
	}

	clear() {
		if ( this.chart ) {
			this.chart.destroy();
			this.chart = null;
		}
		if ( this.outputEl ) {
			this.outputEl.innerHTML = '';
		}
		if ( this.legendEl ) {
			this.legendEl.innerHTML = '';
		}
		if ( this.tooltipOutputEl ) {
			this.tooltipOutputEl.textContent = '';
		}
		this.tooltipOutputEl = null;
		this.tooltipLabel = '';
		this.canvas = null;
	}

	render( chartData = {}, renderOptions = {} ) {
		if ( !this.outputEl ) {
			return;
		}
		const isCompact = !!renderOptions.compact;

		this.clear();
		this.tooltipOutputEl = renderOptions.tooltipOutputEl || null;
		this.tooltipLabel = renderOptions.tooltipLabel || '';
		this.renderFixedTooltipContent();
		this.canvas = document.createElement( 'canvas' );
		this.canvas.style.width = '100%';
		this.canvas.style.height = '100%';
		this.outputEl.appendChild( this.canvas );

		const datasets = ( chartData.series || [] ).map( ( series, index ) => {
			const color = renderOptions.lineColor || CHART_PALETTE[ index % CHART_PALETTE.length ];
			return {
				label: series.label,
				data: series.data || [],
				borderColor: color,
				borderWidth: 2,
				pointBackgroundColor: color,
				pointBorderColor: color,
				pointRadius: isCompact ? 1.5 : 2,
				pointHoverRadius: isCompact ? 3 : 4,
				pointHitRadius: isCompact ? 6 : 8,
				tension: 0.2,
				fill: false
			};
		} );

		this.chart = new ChartJS( this.canvas, {
			type: 'line',
			data: {
				labels: chartData.labels || [],
				datasets,
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: {
					mode: 'nearest',
					axis: 'x',
					intersect: false,
				},
				plugins: {
					legend: {
						display: false,
					},
					tooltip: {
						enabled: !this.tooltipOutputEl,
						external: this.tooltipOutputEl
							? ( context ) => this.renderFixedTooltip( context )
							: null,
					},
				},
				scales: {
					x: {
						grid: {
							display: false,
						},
						ticks: {
							display: !isCompact,
							maxRotation: 0,
							autoSkip: true,
						},
					},
					y: {
						display: !isCompact,
						beginAtZero: true,
						ticks: {
							maxTicksLimit: 8,
							precision: 0,
							callback: ( value ) => Number.isFinite( Number( value ) )
								? Math.round( Number( value ) ).toLocaleString()
								: value,
						},
					},
				},
				layout: {
					padding: isCompact ? {
						top: 16,
						right: 5,
						bottom: 5,
						left: 5,
					} : {},
				},
			},
		} );

		this.renderLegend( datasets );
	}

	renderFixedTooltip( context ) {
		const tooltip = context.tooltip;
		if ( !this.tooltipOutputEl || tooltip.opacity === 0 ) {
			this.renderFixedTooltipContent();
			return;
		}

		const dataPoints = tooltip.dataPoints || [];
		const label = dataPoints[ 0 ]?.dataset.label || this.tooltipLabel;
		const values = dataPoints.map( ( dataPoint ) => {
			const value = dataPoint.formattedValue;
			return dataPoints.length > 1 && dataPoint.dataset.label
				? `${dataPoint.dataset.label}: ${value}`
				: value;
		} );
		const date = ( tooltip.title || [] ).join( ' ' );
		this.renderFixedTooltipContent( label, values.join( ', ' ), date );
	}

	renderFixedTooltipContent( label = this.tooltipLabel, value = '', date = '' ) {
		if ( !this.tooltipOutputEl ) {
			return;
		}

		this.tooltipOutputEl.replaceChildren();
		if ( label ) {
			const summaryEl = document.createElement( 'span' );
			summaryEl.className = 'summary-chart-tooltip__summary';

			const labelEl = document.createElement( 'span' );
			labelEl.className = 'stat-title summary-chart-tooltip__label';
			labelEl.textContent = value ? `${label}:` : label;
			summaryEl.appendChild( labelEl );

			if ( value ) {
				const valueEl = document.createElement( 'span' );
				valueEl.className = 'stat-title summary-chart-tooltip__value';
				valueEl.textContent = value;
				summaryEl.appendChild( valueEl );
			}

			this.tooltipOutputEl.appendChild( summaryEl );
		}
		if ( date ) {
			const separatorEl = document.createElement( 'span' );
			separatorEl.className = 'stat-title summary-chart-tooltip__separator';
			separatorEl.textContent = '·';
			this.tooltipOutputEl.appendChild( separatorEl );

			const dateEl = document.createElement( 'span' );
			dateEl.className = 'stat-title summary-chart-tooltip__date';
			dateEl.textContent = date;
			this.tooltipOutputEl.appendChild( dateEl );
		}
	}

	renderLegend( datasets ) {
		if ( !this.legendEl ) {
			return;
		}

		const legendWrap = this.legendEl.closest( '[data-reports-chart-legend-wrap="1"]' );
		this.legendEl.innerHTML = '';
		if ( datasets.length <= 1 ) {
			legendWrap?.classList.add( 'd-none' );
			return;
		}

		legendWrap?.classList.remove( 'd-none' );
		datasets.forEach( ( dataset ) => {
			const item = document.createElement( 'span' );
			item.className = 'shield-reports-trends-legend-item';

			const swatch = document.createElement( 'span' );
			swatch.className = 'shield-reports-trends-legend-swatch';
			swatch.style.background = dataset.borderColor;

			const label = document.createElement( 'span' );
			label.textContent = dataset.label;

			item.appendChild( swatch );
			item.appendChild( label );
			this.legendEl.appendChild( item );
		} );
	}

	resize() {
		this.chart?.resize();
	}
}
