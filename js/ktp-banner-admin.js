( function( $ ) {
	'use strict';

	let originalSendAttachment = null;

	function getImageField() {
		return $( '#ktp-banner-image-url' );
	}

	function getImagePreview() {
		return $( '#ktp-banner-image-preview' );
	}

	function updatePreview( imageUrl ) {
		const $preview = getImagePreview();
		if ( ! $preview.length ) {
			return;
		}

		if ( imageUrl ) {
			$preview.attr( 'src', imageUrl ).show();
		} else {
			$preview.attr( 'src', '' ).hide();
		}
	}

	function resolveAttachmentUrl( attachment ) {
		if ( ! attachment ) {
			return '';
		}

		if ( attachment.url ) {
			return attachment.url;
		}

		if ( attachment.sizes && attachment.sizes.full && attachment.sizes.full.url ) {
			return attachment.sizes.full.url;
		}

		if ( attachment.attributes && attachment.attributes.url ) {
			return attachment.attributes.url;
		}

		return '';
	}

	function resolveAttachmentId( attachment ) {
		if ( ! attachment ) {
			return 0;
		}

		if ( attachment.id ) {
			return parseInt( attachment.id, 10 ) || 0;
		}

		if ( attachment.attributes && attachment.attributes.id ) {
			return parseInt( attachment.attributes.id, 10 ) || 0;
		}

		return 0;
	}

	function setImageUrl( imageUrl ) {
		if ( ! imageUrl ) {
			return;
		}
		getImageField().val( imageUrl ).trigger( 'change' );
		updatePreview( imageUrl );
	}

	function fetchUrlByAttachmentId( attachmentId ) {
		if ( ! attachmentId || ! wp.media || ! wp.media.attachment ) {
			return;
		}

		const attachmentModel = wp.media.attachment( attachmentId );
		if ( ! attachmentModel ) {
			return;
		}

		attachmentModel.fetch().then( function() {
			const attrs = attachmentModel.attributes || {};
			const imageUrl = resolveAttachmentUrl( attrs );
			setImageUrl( imageUrl );
		} );
	}

	function applySelectedImage( selection ) {
		if ( ! selection || ! selection.first ) {
			return;
		}

		const model = selection.first();
		if ( ! model ) {
			return;
		}

		const attachment = model.toJSON ? model.toJSON() : model;
		const imageUrl = resolveAttachmentUrl( attachment );
		if ( ! imageUrl ) {
			const attachmentId = resolveAttachmentId( attachment );
			fetchUrlByAttachmentId( attachmentId );
			return;
		}

		setImageUrl( imageUrl );
	}

	$( function() {
		$( '#ktp-banner-select-image' ).on( 'click', function( event ) {
			event.preventDefault();
			if ( typeof wp === 'undefined' || ! wp.media ) {
				window.alert( ( window.ktpBannerAdmin && window.ktpBannerAdmin.media_error ) ? window.ktpBannerAdmin.media_error : 'Media library unavailable.' );
				return;
			}

			// 互換性重視: 古い環境でも確実にURLを受け取れる editor API を優先
			if ( wp.media.editor && wp.media.editor.open ) {
				if ( null === originalSendAttachment ) {
					originalSendAttachment = wp.media.editor.send.attachment;
				}

				wp.media.editor.send.attachment = function( props, attachment ) {
					const imageUrl = resolveAttachmentUrl( attachment );
					if ( imageUrl ) {
						setImageUrl( imageUrl );
					} else {
						const attachmentId = resolveAttachmentId( attachment );
						fetchUrlByAttachmentId( attachmentId );
					}

					// 他画面への副作用を防ぐため、処理後に元へ戻す
					if ( originalSendAttachment ) {
						wp.media.editor.send.attachment = originalSendAttachment;
					}
				};

				wp.media.editor.open( $( '#ktp-banner-select-image' ) );
				return;
			}

			const mediaFrame = wp.media( {
				title: ( window.ktpBannerAdmin && window.ktpBannerAdmin.title ) ? window.ktpBannerAdmin.title : 'バナー画像を選択',
				button: {
					text: ( window.ktpBannerAdmin && window.ktpBannerAdmin.button_text ) ? window.ktpBannerAdmin.button_text : 'この画像を使用'
				},
				library: {
					type: 'image'
				},
				multiple: false
			} );

			mediaFrame.on( 'select', function() {
				const selection = mediaFrame.state().get( 'selection' );
				applySelectedImage( selection );
			} );

			// 一部環境では insert イベントのみ発火するため両対応
			mediaFrame.on( 'insert', function() {
				const selection = mediaFrame.state().get( 'selection' );
				applySelectedImage( selection );
			} );

			mediaFrame.open();
		} );

		$( '#ktp-banner-clear-image' ).on( 'click', function( event ) {
			event.preventDefault();
			getImageField().val( '' );
			updatePreview( '' );
		} );
	} );
} )( jQuery );
