jQuery( document ).ready( function ( $ ) {
	$('.logs > li').each( function () {
		var $item = $( this );
		$item.find( '.the-mighty-expando' ).on( 'click', function () {
			$item.find( '.content' ).toggle();
			$( this ).find( 'i' )
				.toggleClass( 'dashicons-arrow-up-alt2' )
				.toggleClass( 'dashicons-arrow-down-alt2' );
		});
	});
	$('.arachnid-entry .nav-tab').on( 'click', function ( e ) {
		var target = this.dataset.target;
		var $this = $(this);
		var $entry = $this.closest( '.arachnid-entry' );
		var $section = $entry.find( '.section-' + target );
		var $others = $section.siblings( '.section' );
		var $otherlinks = $this.siblings( '.nav-tab' );

		$otherlinks.removeClass( 'nav-tab-active' )
		$this.addClass( 'nav-tab-active' );
		$others.removeClass( 'active' );
		$section.addClass( 'active' );
	});

	var clipboard = new Clipboard( '.arachnid-copy' );
	var showMessage = function ( parent, text ) {
		var msg = document.createElement( 'span' );
		msg.className = 'message';
		msg.textContent = text;
		$(parent).prepend( msg );

		setTimeout( function () {
			$(msg).fadeOut( function () {
				$(msg).remove();
			});
		}, 600 );
	};
	clipboard.on( 'success', function ( e ) {
		showMessage( e.trigger, 'Copied!' );
		e.clearSelection();
	});

	clipboard.on( 'error', function ( e ) {
		showMessage( e.trigger, 'Press Ctrl+C to copy' );
	});
});
