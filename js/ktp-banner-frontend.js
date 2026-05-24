( function() {
	'use strict';

	const initialized = new WeakSet();

	function getRotatorItems( element ) {
		const items = element.querySelectorAll( '.ktp-banner-rotator-item' );
		if ( items.length >= 2 ) {
			return items;
		}

		return element.children;
	}

	function initRotator( element ) {
		if ( initialized.has( element ) ) {
			return;
		}

		const items = getRotatorItems( element );
		if ( items.length < 2 ) {
			return;
		}

		initialized.add( element );

		if ( ! items[ 0 ].classList.contains( 'is-active' ) ) {
			items[ 0 ].classList.add( 'is-active' );
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

	function scan( root ) {
		const scope = root && root.querySelectorAll ? root : document;
		scope.querySelectorAll( '.ktp-banner-rotator' ).forEach( initRotator );
	}

	function boot() {
		scan( document );

		if ( typeof MutationObserver === 'undefined' ) {
			return;
		}

		const observer = new MutationObserver( function( mutations ) {
			mutations.forEach( function( mutation ) {
				mutation.addedNodes.forEach( function( node ) {
					if ( node.nodeType !== 1 ) {
						return;
					}

					if ( node.classList && node.classList.contains( 'ktp-banner-rotator' ) ) {
						initRotator( node );
						return;
					}

					scan( node );
				} );
			} );
		} );

		observer.observe( document.documentElement, {
			childList: true,
			subtree: true,
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
