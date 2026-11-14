/**
 * HA Image Optimizer Frontend & Bulk Runner Script.
 */

(function ($) {
	'use strict';

	$(function () {
		// -------------------------------------------------------------
		// 1. Single Image Actions (Media Library & Detail Modal)
		// -------------------------------------------------------------
		$(document).on('click', '.ha-io-btn-optimize', function (e) {
			e.preventDefault();
			const $btn = $(this);
			const attachmentId = $btn.data('id');
			if (!attachmentId) return;

			// Find level select in nearby context or by data-id
			let level = '';
			const $container = $btn.closest('.ha-io-col-optimized, .ha-io-col-unoptimized, .ha-io-meta-card, tr, .attachment-details');
			let $select = $container.find('.ha-io-level-select');
			if (!$select.length) {
				$select = $('.ha-io-level-select[data-id="' + attachmentId + '"]');
			}
			if ($select.length) {
				level = $select.val() || '';
			}

			const origText = $btn.text();
			$btn.prop('disabled', true).text(ha_io_vars.i18n.optimizing || 'Optimiere...');

			$.ajax({
				url: ha_io_vars.ajax_url,
				method: 'POST',
				data: {
					action: 'ha_io_optimize_single',
					nonce: ha_io_vars.nonce,
					attachment_id: attachmentId,
					level: level
				},
				success: function (res) {
					if (res.success) {
						const inPostEditor = $('#post').length > 0 || $('body').hasClass('block-editor-page');
						if (inPostEditor) {
							$btn.text('✓ ' + (ha_io_vars.i18n.completed || 'Optimiert'));
							if (res.data && res.data.data && res.data.data.total && typeof res.data.data.total.percent !== 'undefined') {
								$btn.closest('td, .compat-field-ha_io_stats').find('.ha-io-status-badge').text(res.data.data.total.percent + '% ' + (ha_io_vars.i18n.saved || 'gespart'));
							}
						} else {
							location.reload();
						}
					} else {
						alert(res.data.message || ha_io_vars.i18n.error);
						$btn.prop('disabled', false).text(origText);
					}
				},
				error: function () {
					alert(ha_io_vars.i18n.error);
					$btn.prop('disabled', false).text(origText);
				}
			});
		});

		$(document).on('click', '.ha-io-btn-restore', function (e) {
			e.preventDefault();
			const $btn = $(this);
			const attachmentId = $btn.data('id');
			if (!attachmentId) return;

			if (!confirm(ha_io_vars.i18n.confirm_rest || 'Original wiederherstellen?')) {
				return;
			}

			const origText = $btn.text();
			$btn.prop('disabled', true).text(ha_io_vars.i18n.restoring || 'Stelle wieder her...');

			$.ajax({
				url: ha_io_vars.ajax_url,
				method: 'POST',
				data: {
					action: 'ha_io_restore_single',
					nonce: ha_io_vars.nonce,
					attachment_id: attachmentId
				},
				success: function (res) {
					if (res.success) {
						const inPostEditor = $('#post').length > 0 || $('body').hasClass('block-editor-page');
						if (inPostEditor) {
							$btn.text('✓ ' + ((res.data && res.data.message) || 'Original'));
						} else {
							location.reload();
						}
					} else {
						alert(res.data.message || ha_io_vars.i18n.error);
						$btn.prop('disabled', false).text(origText);
					}
				},
				error: function () {
					alert(ha_io_vars.i18n.error);
					$btn.prop('disabled', false).text(origText);
				}
			});
		});

		// -------------------------------------------------------------
		// 2. Enhanced Bulk Optimization Runner
		// -------------------------------------------------------------
		let bulkQueue = [];
		let bulkTotal = 0;
		let bulkProcessed = 0;
		let totalSavedBytes = 0;
		let isPaused = false;
		let isRunning = false;
		let forceAll = false;
		let bulkTarget = 'media'; // 'media' or 'custom'
		// Process 1 media attachment per AJAX request (1 attachment contains 8-15 responsive thumbnail sizes,
		// processing 2+ attachments exceeds webserver gateway timeouts of 30-40s).
		// For custom folder standalone files, 5 files are processed per request.
		const mediaBatchSize = 1;
		const customBatchSize = 5;

		$('#ha-io-btn-start-bulk').on('click', function () {
			forceAll = false;
			bulkTarget = 'media';
			startBulk();
		});

		$('#ha-io-btn-start-bulk-all').on('click', function () {
			forceAll = true;
			bulkTarget = 'media';
			startBulk();
		});

		$('#ha-io-btn-start-custom').on('click', function () {
			forceAll = false;
			bulkTarget = 'custom';
			startBulk();
		});

		$('#ha-io-btn-pause-bulk').on('click', function () {
			if (!isPaused) {
				isPaused = true;
				$(this).text(ha_io_vars.i18n.resume || 'Fortsetzen');
				$('#ha-io-current-action-title').text('Pausiert');
				$('#ha-io-current-action-desc').text('Klicke auf "Fortsetzen", um die Verarbeitung weiterzuführen.');
			} else {
				isPaused = false;
				$(this).text(ha_io_vars.i18n.pause || 'Pausieren');
				$('#ha-io-current-action-title').text('Wird fortgesetzt...');
				processNextChunk();
			}
		});

		function setStep(stepNum) {
			$('.ha-io-step').removeClass('active completed');
			for (let i = 1; i < stepNum; i++) {
				$('#ha-io-step-' + i).addClass('completed');
			}
			$('#ha-io-step-' + stepNum).addClass('active');
		}

		function startBulk() {
			if (isRunning) return;
			isRunning = true;
			isPaused = false;
			totalSavedBytes = 0;

			$('#ha-io-btn-start-bulk, #ha-io-btn-start-bulk-all, #ha-io-btn-start-custom').prop('disabled', true);
			$('#ha-io-btn-pause-bulk').show().text(ha_io_vars.i18n.pause || 'Pausieren');
			
			$('#ha-io-stepper').slideDown();
			$('#ha-io-live-action-box').slideDown();
			$('#ha-io-progress-container').slideDown();
			$('#ha-io-history-dropdown').slideDown();
			$('#ha-io-log-container').slideDown();

			$('#ha-io-progress-bar').css('width', '0%');
			$('#ha-io-history-tbody').empty();
			$('#ha-io-history-count').text('0');

			// Step 1: Crawl library or custom folders
			setStep(1);
			const isCustom = (bulkTarget === 'custom');
			const step1Title = isCustom
				? 'Schritt 1: Theme- & Custom-Ordner scannen...'
				: 'Schritt 1: Mediathek scannen & Queue aufbauen...';
			const step1Desc = isCustom
				? 'Ermittle alle nicht optimierten Bilddateien in den konfigurierten Ordnern...'
				: 'Ermittle alle nicht optimierten Bilddateien aus der Datenbank...';

			$('#ha-io-current-action-title').text(step1Title);
			$('#ha-io-current-action-desc').text(step1Desc);
			$('#ha-io-current-file-badge').hide();

			appendLog(isCustom ? '🔍 Starte Scannen von Theme- und benutzerdefinierten Verzeichnissen...' : '🔍 Starte Crawling der WordPress-Mediathek...');

			const queueAction = isCustom ? 'ha_io_get_custom_queue' : 'ha_io_get_bulk_queue';

			$.ajax({
				url: ha_io_vars.ajax_url,
				method: 'POST',
				data: {
					action: queueAction,
					nonce: ha_io_vars.nonce,
					all: forceAll ? 1 : 0
				},
				success: function (res) {
					if (res.success && res.data.queue) {
						bulkQueue = res.data.queue;
						bulkTotal = bulkQueue.length;
						bulkProcessed = 0;

						if (bulkTotal === 0) {
							setStep(4);
							$('#ha-io-current-action-title').text('Bereits alles auf dem neuesten Stand!');
							$('#ha-io-current-action-desc').text('Es wurden keine Bilder gefunden, die optimiert werden müssen.');
							finishBulk();
							return;
						}

						appendLog('✅ Queue erfolgreich erstellt: ' + bulkTotal + ' Dateien gefunden.');
						setStep(2);
						updateProgress();
						processNextChunk();
					} else {
						alert(res.data ? res.data.message : 'Fehler beim Laden der Queue.');
						finishBulk();
					}
				},
				error: function () {
					alert(ha_io_vars.i18n.error);
					finishBulk();
				}
			});
		}

		function processNextChunk() {
			if (isPaused || bulkQueue.length === 0) {
				if (bulkQueue.length === 0) {
					finishBulk();
				}
				return;
			}

			const isCustom = (bulkTarget === 'custom');
			const currentBatchSize = isCustom ? customBatchSize : mediaBatchSize;
			const chunk = bulkQueue.splice(0, currentBatchSize);

			// Step 2 & 3: Optimizing
			setStep(2);
			const labelType = isCustom ? 'Dateien' : 'Bilder';
			$('#ha-io-current-action-title').text('Schritt 2: Optimiere ' + labelType + ' (' + (bulkProcessed + 1) + ' bis ' + Math.min(bulkTotal, bulkProcessed + chunk.length) + ' von ' + bulkTotal + ')...');
			$('#ha-io-current-action-desc').text('Komprimiere Originale verlustfrei und erzeuge Smart-Zwischengrößen...');
			$('#ha-io-current-file-badge').show();
			$('#ha-io-current-filename').text(chunk.join(', '));

			const batchAction = isCustom ? 'ha_io_bulk_custom_batch' : 'ha_io_bulk_batch';
			const postData = {
				action: batchAction,
				nonce: ha_io_vars.nonce,
				force: forceAll ? 1 : 0
			};

			if (isCustom) {
				postData.files = chunk;
			} else {
				postData.ids = chunk;
			}

			$.ajax({
				url: ha_io_vars.ajax_url,
				method: 'POST',
				data: postData,
				success: function (res) {
					if (res.success) {
						bulkProcessed += chunk.length;
						const saved = res.data.saved_bytes || 0;
						totalSavedBytes += saved;

						// Step 3 NextGen visual pulse
						setStep(3);

						// Append each item into the history dropdown
						if (res.data.items && res.data.items.length) {
							res.data.items.forEach(function (item) {
								appendHistoryRow(item);
								$('#ha-io-current-filename').text(item.filename);
							});
						}

						appendLog('Batch abgeschlossen (' + chunk.length + ' ' + labelType + '). Gespart: ' + formatBytes(saved));

						if (res.data.errors && res.data.errors.length) {
							res.data.errors.forEach(function (err) {
								const errTarget = err.id ? ('ID ' + err.id) : (err.file || '?');
								appendLog('⚠️ Fehler bei ' + errTarget + ': ' + (err.message || 'Unbekannt'));
							});
						}
					} else {
						appendLog('Fehler bei Batch (' + chunk.join(',') + '): ' + (res.data ? res.data.message : 'Unbekannt'));
						bulkProcessed += chunk.length;
					}

					updateProgress();
					processNextChunk();
				},
				error: function (xhr, textStatus, errorThrown) {
					let errorMsg = 'Server-Fehler';
					if (xhr.status === 504 || xhr.status === 408 || textStatus === 'timeout') {
						errorMsg = 'Gateway Timeout (HTTP ' + (xhr.status || 504) + ' - Server/Proxy hat Verbindung nach Timeout gekappt)';
					} else if (xhr.status === 502) {
						errorMsg = 'Bad Gateway (HTTP 502 - PHP-FPM Prozess abgebrochen)';
					} else if (xhr.status === 500) {
						errorMsg = 'Server-Fehler 500 (Memory-Limit oder PHP Fatal Error)';
					} else if (xhr.status === 0) {
						errorMsg = 'Verbindungsabbruch / Netzwerkfehler';
					} else if (xhr.status) {
						errorMsg = 'HTTP-Fehler ' + xhr.status;
					}
					appendLog('⚠️ ' + errorMsg + ' bei Batch (' + chunk.join(',') + '). Fahre fort...');
					bulkProcessed += chunk.length;
					updateProgress();
					processNextChunk();
				}
			});
		}

		function appendHistoryRow(item) {
			const nextgenPills = [];
			if (item.has_webp) {
				nextgenPills.push('<span class="ha-io-badge ha-io-badge-blue">WebP ✅</span>');
			}
			if (item.has_avif) {
				nextgenPills.push('<span class="ha-io-badge ha-io-badge-purple">AVIF ✅</span>');
			}
			if (!nextgenPills.length) {
				nextgenPills.push('<span class="ha-io-badge ha-io-badge-gray">—</span>');
			}

			const rowHtml = '<tr class="ha-io-row-new">' +
				'<td><strong>' + escapeHtml(item.filename) + '</strong></td>' +
				'<td>' + escapeHtml(item.before) + '</td>' +
				'<td>' + escapeHtml(item.after) + '</td>' +
				'<td><span class="ha-io-text-green">-' + escapeHtml(item.percent) + '%</span> (' + escapeHtml(item.saved) + ' ' + (ha_io_vars.i18n.saved || 'gespart') + ')</td>' +
				'<td>' + nextgenPills.join(' ') + '</td>' +
				'</tr>';

			const $tbody = $('#ha-io-history-tbody');
			$tbody.prepend(rowHtml);

			const currentCount = parseInt($('#ha-io-history-count').text() || '0', 10) + 1;
			$('#ha-io-history-count').text(currentCount);
		}

		function updateProgress() {
			const percent = bulkTotal > 0 ? Math.min(100, Math.round((bulkProcessed / bulkTotal) * 100)) : 100;
			$('#ha-io-progress-bar').css('width', percent + '%');
			$('#ha-io-progress-counts').text(bulkProcessed + ' / ' + bulkTotal + ' (' + percent + '%)');
			$('#ha-io-progress-status').text('Fortschritt: ' + percent + '% (' + bulkProcessed + ' von ' + bulkTotal + ' Bildern)');
		}

		function finishBulk() {
			isRunning = false;
			isPaused = false;
			setStep(4);

			$('#ha-io-btn-pause-bulk').hide();
			$('#ha-io-btn-start-bulk, #ha-io-btn-start-bulk-all').prop('disabled', false);
			$('#ha-io-progress-bar').css('width', '100%');
			$('#ha-io-current-action-title').text('🎉 ' + (ha_io_vars.i18n.completed || 'Optimierung vollständig abgeschlossen!'));
			$('#ha-io-current-action-desc').text('Insgesamt wurden ' + formatBytes(totalSavedBytes) + ' Speicherplatz auf dem Server gespart. Alle Caches wurden bereinigt.');
			$('#ha-io-current-file-badge').hide();

			appendLog('🏁 Massenoptimierung beendet. Insgesamt ' + formatBytes(totalSavedBytes) + ' gespart.');

			// Refresh top overview cards dynamically
			$.ajax({
				url: ha_io_vars.ajax_url,
				method: 'POST',
				data: {
					action: 'ha_io_get_bulk_stats',
					nonce: ha_io_vars.nonce
				},
				success: function (res) {
					if (res.success && res.data && res.data.stats) {
						const s = res.data.stats;
						$('#ha-io-stat-total').text(s.total_images);
						$('#ha-io-stat-optimized').text(s.optimized_images);
						$('#ha-io-stat-unoptimized').text(s.unoptimized);
						$('#ha-io-stat-saved').text(s.saved_human);
						$('#ha-io-stat-percent').text(s.saved_percent);

						if (s.unoptimized === 0) {
							$('#ha-io-btn-start-bulk').prop('disabled', true);
						}
					}
					if (res.success && res.data && res.data.custom_stats) {
						const cs = res.data.custom_stats;
						const $customBtn = $('#ha-io-btn-start-custom');
						if ($customBtn.length) {
							$customBtn.prop('disabled', cs.unoptimized === 0);
						}
					}
				}
			});
		}

		function appendLog(text) {
			const time = new Date().toLocaleTimeString();
			const $item = $('<li>').text('[' + time + '] ' + text);
			$('#ha-io-log-list').prepend($item);
		}

		function formatBytes(bytes) {
			if (bytes === 0) return '0 B';
			const k = 1024;
			const sizes = ['B', 'KB', 'MB', 'GB'];
			const i = Math.floor(Math.log(bytes) / Math.log(k));
			return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
		}

		function escapeHtml(str) {
			return $('<div>').text(str).html();
		}
	});
})(jQuery);
