/* FCM Push Notify — Notification Composer interactions */
/* global tinymce, wp, fcmPushData */

wp.domReady( function () {
	var TITLE_MAX = 65;
	var BODY_MAX  = 140;

	// 1. Renomear botão Publicar e cabeçalho do metabox
	var publishBtn = document.getElementById( 'publish' );
	if ( publishBtn ) {
		publishBtn.value = 'Enviar Notificação';
	}
	var publishHndle = document.querySelector( '#submitdiv .hndle span' );
	if ( publishHndle ) {
		publishHndle.textContent = 'Enviar Notificação';
	}

	// 2. Ajustar placeholder do título
	var titlePrompt = document.getElementById( 'title-prompt-text' );
	if ( titlePrompt ) {
		titlePrompt.textContent = 'Assunto da notificação (max ' + TITLE_MAX + ' caracteres)';
	}

	// 3. Forçar modo Texto (sem TinyMCE visual) — só clica na aba se ela existir
	var textTab = document.getElementById( 'content-html' );
	if ( textTab ) {
		textTab.click();
	}

	// 4. Injetar contador de título após #titlediv
	var titlediv = document.getElementById( 'titlediv' );
	if ( titlediv ) {
		var titleCounterEl = document.createElement( 'div' );
		titleCounterEl.className = 'mp-counter-wrap';
		titleCounterEl.innerHTML =
			'<div class="mp-counter-bar"><div class="mp-counter-fill" id="mp-title-fill"></div></div>' +
			'<span class="mp-counter-text" id="mp-title-text">0 / ' + TITLE_MAX + '</span>';
		titlediv.appendChild( titleCounterEl );
	}

	// 5. Injetar contador de corpo + preview após o editor
	var postdivrich = document.getElementById( 'postdivrich' );
	if ( postdivrich ) {
		var bodyCounterEl = document.createElement( 'div' );
		bodyCounterEl.className = 'mp-counter-wrap';
		bodyCounterEl.style.marginTop = '10px';
		bodyCounterEl.innerHTML =
			'<div class="mp-counter-bar"><div class="mp-counter-fill" id="mp-body-fill"></div></div>' +
			'<span class="mp-counter-text" id="mp-body-text">0 / ' + BODY_MAX + '</span>';
		postdivrich.after( bodyCounterEl );

		var previewEl = document.createElement( 'div' );
		previewEl.id = 'mp-preview-wrapper';
		previewEl.innerHTML =
			'<p class="mp-preview-label">Preview no celular</p>' +
			'<div class="mp-phone">' +
				'<div class="mp-phone-screen">' +
					'<div class="mp-status-bar">' +
						'<span>9:41</span>' +
						'<span class="mp-status-icons">▌▌ ⬛</span>' +
					'</div>' +
					'<div class="mp-notification-card">' +
						'<div class="mp-notif-icon">🔔</div>' +
						'<div class="mp-notif-content">' +
							'<div class="mp-notif-header">' +
								'<span class="mp-notif-app">' + ( ( typeof fcmPushData !== 'undefined' && fcmPushData.siteName ) ? fcmPushData.siteName : 'My App' ) + '</span>' +
								'<span class="mp-notif-time">agora</span>' +
							'</div>' +
							'<div class="mp-notif-title mp-placeholder" id="mp-prev-title">Assunto da notificação</div>' +
							'<div class="mp-notif-body mp-placeholder" id="mp-prev-body">Corpo da mensagem...</div>' +
						'</div>' +
					'</div>' +
				'</div>' +
			'</div>';
		bodyCounterEl.after( previewEl );
	}

	// ── Helpers ────────────────────────────────────────────────

	function updateCounter( value, max, fillId, textId ) {
		var len  = value.length;
		var pct  = Math.min( ( len / max ) * 100, 100 );
		var fill = document.getElementById( fillId );
		var text = document.getElementById( textId );
		if ( ! fill || ! text ) return;

		fill.style.width = pct + '%';
		fill.classList.remove( 'mp-warn', 'mp-over' );
		text.classList.remove( 'mp-warn', 'mp-over' );

		if ( pct > 85 ) {
			fill.classList.add( 'mp-over' );
			text.classList.add( 'mp-over' );
		} else if ( pct > 60 ) {
			fill.classList.add( 'mp-warn' );
			text.classList.add( 'mp-warn' );
		}
		text.textContent = len + ' / ' + max;
	}

	function updatePreviewTitle( value ) {
		var el = document.getElementById( 'mp-prev-title' );
		if ( ! el ) return;
		if ( value.trim() ) {
			el.textContent = value;
			el.classList.remove( 'mp-placeholder' );
		} else {
			el.textContent = 'Assunto da notificação';
			el.classList.add( 'mp-placeholder' );
		}
	}

	function updatePreviewBody( value ) {
		var el = document.getElementById( 'mp-prev-body' );
		if ( ! el ) return;
		// Remove HTML que possa ter entrado via TinyMCE
		var plain = value.replace( /<[^>]+>/g, ' ' ).replace( /\s+/g, ' ' ).trim();
		if ( plain ) {
			el.textContent = plain;
			el.classList.remove( 'mp-placeholder' );
		} else {
			el.textContent = 'Corpo da mensagem...';
			el.classList.add( 'mp-placeholder' );
		}
	}

	// ── Título ─────────────────────────────────────────────────

	var titleInput = document.getElementById( 'title' );
	if ( titleInput ) {
		titleInput.addEventListener( 'input', function () {
			updateCounter( titleInput.value, TITLE_MAX, 'mp-title-fill', 'mp-title-text' );
			updatePreviewTitle( titleInput.value );
		} );
		// Estado inicial (edição de notificação existente)
		updateCounter( titleInput.value, TITLE_MAX, 'mp-title-fill', 'mp-title-text' );
		updatePreviewTitle( titleInput.value );
	}

	// ── Corpo — textarea (modo Texto) ──────────────────────────

	function attachBodyTextarea() {
		var contentArea = document.getElementById( 'content' );
		if ( ! contentArea ) return;
		contentArea.addEventListener( 'input', function () {
			updateCounter( contentArea.value, BODY_MAX, 'mp-body-fill', 'mp-body-text' );
			updatePreviewBody( contentArea.value );
		} );
		// Estado inicial
		updateCounter( contentArea.value, BODY_MAX, 'mp-body-fill', 'mp-body-text' );
		updatePreviewBody( contentArea.value );
	}

	attachBodyTextarea();

	// Fallback: TinyMCE pode carregar após domReady — escuta evento de inicialização
	if ( typeof tinymce !== 'undefined' ) {
		tinymce.on( 'AddEditor', function ( e ) {
			if ( e.editor.id !== 'content' ) return;
			e.editor.on( 'change keyup', function () {
				var plain = e.editor.getContent( { format: 'text' } );
				updateCounter( plain, BODY_MAX, 'mp-body-fill', 'mp-body-text' );
				updatePreviewBody( plain );
			} );
			// Sync estado inicial se o editor abrir em modo visual
			var initial = e.editor.getContent( { format: 'text' } );
			updateCounter( initial, BODY_MAX, 'mp-body-fill', 'mp-body-text' );
			updatePreviewBody( initial );
		} );
	}
} );
