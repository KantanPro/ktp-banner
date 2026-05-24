( function() {
	'use strict';

	function initRotator( element ) {
		const items = element.querySelectorAll( '.ktp-banner-rotator__item' );
		if ( items.length < 2 ) {
			return;
		}

		const intervalSeconds = parseInt( element.getAttribute( 'data-interval' ), 10 );
		const intervalMs = ( Number.isFinite( intervalSeconds ) && intervalSeconds >= 2 ? intervalSeconds : 5 ) * 1000;
		let currentIndex = 0;

		setInterval( function() {
			items[ currentIndex ].classList.remove( 'is-active' );
			currentIndex = ( currentIndex + 1 ) % items.length;
			items[ currentIndex ].classList.add( 'is-active' );
		}, intervalMs );
	}

	function boot() {
		document.querySelectorAll( '.ktp-banner-rotator' ).forEach( initRotator );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
