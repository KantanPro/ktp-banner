( function( $ ) {
	'use strict';

	let originalSendAttachment = null;

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

	function getImageType( $trigger ) {
		return $trigger.data( 'image-type' ) === 'mobile' ? 'mobile' : 'desktop';
	}

	function getImageField( $item, imageType ) {
		return imageType === 'mobile'
			? $item.find( '.ktp-banner-mobile-image-url' )
			: $item.find( '.ktp-banner-image-url' );
	}

	function getImagePreview( $item, imageType ) {
		return imageType === 'mobile'
			? $item.find( '.ktp-banner-mobile-image-preview' )
			: $item.find( '.ktp-banner-image-preview' );
	}

	function updatePreview( $item, imageUrl, imageType ) {
		const $preview = getImagePreview( $item, imageType );
		if ( ! $preview.length ) {
			return;
		}

		if ( imageUrl ) {
			$preview.attr( 'src', imageUrl ).show();
		} else {
			$preview.attr( 'src', '' ).hide();
		}
	}

	function setImageUrl( $item, imageUrl, imageType ) {
		const $field = getImageField( $item, imageType );
		if ( ! $field.length ) {
			return;
		}

		$field.val( imageUrl ).trigger( 'change' );
		updatePreview( $item, imageUrl, imageType );
	}

	function fetchUrlByAttachmentId( $item, attachmentId, imageType ) {
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
			setImageUrl( $item, imageUrl, imageType );
		} );
	}

	function applySelectedImage( $item, selection, imageType ) {
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
			fetchUrlByAttachmentId( $item, attachmentId, imageType );
			return;
		}

		setImageUrl( $item, imageUrl, imageType );
	}

	function openMediaLibrary( $item, imageType ) {
		if ( typeof wp === 'undefined' || ! wp.media ) {
			window.alert(
				window.ktpBannerAdmin && window.ktpBannerAdmin.media_error
					? window.ktpBannerAdmin.media_error
					: 'Media library unavailable.'
			);
			return;
		}

		const $trigger = $item.find( '.ktp-banner-select-image[data-image-type="' + imageType + '"]' );
		const frameTitle = imageType === 'mobile'
			? ( window.ktpBannerAdmin && window.ktpBannerAdmin.mobile_title ? window.ktpBannerAdmin.mobile_title : 'スマホ用バナー画像を選択' )
			: ( window.ktpBannerAdmin && window.ktpBannerAdmin.title ? window.ktpBannerAdmin.title : 'バナー画像を選択' );

		if ( wp.media.editor && wp.media.editor.open ) {
			if ( null === originalSendAttachment ) {
				originalSendAttachment = wp.media.editor.send.attachment;
			}

			wp.media.editor.send.attachment = function( props, attachment ) {
				const imageUrl = resolveAttachmentUrl( attachment );
				if ( imageUrl ) {
					setImageUrl( $item, imageUrl, imageType );
				} else {
					const attachmentId = resolveAttachmentId( attachment );
					fetchUrlByAttachmentId( $item, attachmentId, imageType );
				}

				if ( originalSendAttachment ) {
					wp.media.editor.send.attachment = originalSendAttachment;
				}
			};

			wp.media.editor.open( $trigger );
			return;
		}

		const mediaFrame = wp.media( {
			title: frameTitle,
			button: {
				text: window.ktpBannerAdmin && window.ktpBannerAdmin.button_text ? window.ktpBannerAdmin.button_text : 'この画像を使用',
			},
			library: {
				type: 'image',
			},
			multiple: false,
		} );

		mediaFrame.on( 'select', function() {
			const selection = mediaFrame.state().get( 'selection' );
			applySelectedImage( $item, selection, imageType );
		} );

		mediaFrame.on( 'insert', function() {
			const selection = mediaFrame.state().get( 'selection' );
			applySelectedImage( $item, selection, imageType );
		} );

		mediaFrame.open();
	}

	function reindexBannerItems() {
		$( '#ktp-banner-items .ktp-banner-item' ).each( function( index ) {
			const $item = $( this );
			$item.attr( 'data-index', index );
			$item.find( '.ktp-banner-item-title' ).text(
				( window.ktpBannerAdmin && window.ktpBannerAdmin.item_label ? window.ktpBannerAdmin.item_label : 'バナー' ) + ' #' + ( index + 1 )
			);

			$item.find( '[name]' ).each( function() {
				const $field = $( this );
				const name = $field.attr( 'name' );
				if ( ! name || name.indexOf( 'ktp_banner_options[banners][' ) !== 0 ) {
					return;
				}
				const fieldKey = name.replace( /^ktp_banner_options\[banners\]\[\d+\]\[/, '' ).replace( /\]$/, '' );
				$field.attr( 'name', 'ktp_banner_options[banners][' + index + '][' + fieldKey + ']' );
			} );
		} );
	}

	function createBannerItemFromTemplate() {
		const template = $( '#ktp-banner-item-template' ).html();
		if ( ! template ) {
			return null;
		}

		const index = $( '#ktp-banner-items .ktp-banner-item' ).length;
		const uniqueId = 'banner_' + Date.now() + '_' + Math.floor( Math.random() * 1000 );
		const html = template
			.replace( /\{\{INDEX\}\}/g, String( index ) )
			.replace( /\{\{ID\}\}/g, uniqueId );

		return $( html );
	}

	$( function() {
		$( document ).on( 'click', '.ktp-banner-select-image', function( event ) {
			event.preventDefault();
			const $item = $( this ).closest( '.ktp-banner-item' );
			openMediaLibrary( $item, getImageType( $( this ) ) );
		} );

		$( document ).on( 'click', '.ktp-banner-clear-image', function( event ) {
			event.preventDefault();
			const $item = $( this ).closest( '.ktp-banner-item' );
			setImageUrl( $item, '', getImageType( $( this ) ) );
		} );

		$( '#ktp-banner-add-item' ).on( 'click', function( event ) {
			event.preventDefault();
			const $item = createBannerItemFromTemplate();
			if ( ! $item ) {
				return;
			}
			$( '#ktp-banner-items' ).append( $item );
			reindexBannerItems();
		} );

		$( document ).on( 'click', '.ktp-banner-remove-item', function( event ) {
			event.preventDefault();
			const $items = $( '#ktp-banner-items .ktp-banner-item' );
			if ( $items.length <= 1 ) {
				window.alert(
					window.ktpBannerAdmin && window.ktpBannerAdmin.remove_last_error
						? window.ktpBannerAdmin.remove_last_error
						: '最低1件のバナーが必要です。'
				);
				return;
			}

			$( this ).closest( '.ktp-banner-item' ).remove();
			reindexBannerItems();
		} );
	} );
} )( jQuery );
