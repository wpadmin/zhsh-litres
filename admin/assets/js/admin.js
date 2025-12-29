(function($) {
	'use strict';

	const ZhshLitres = {
		scannedData: {
			genres: {},
			authors: {}
		},
		selectedFilePath: null,

		init: function() {
			this.bindEvents();
			this.loadUploadedFiles();
		},

		bindEvents: function() {
			$('#zhsh-litres-upload-form').on('submit', this.handleUpload.bind(this));
			$('#zhsh-litres-scan-btn').on('click', this.handleScan.bind(this));
			$('#zhsh-litres-start-btn').on('click', this.handleStart.bind(this));
			$('#zhsh-litres-stop-btn').on('click', this.handleStop.bind(this));
			$('#genre-search').on('keyup', this.filterList.bind(this, 'genre'));
			$('#author-search').on('keyup', this.filterList.bind(this, 'author'));
		},

		loadUploadedFiles: function() {
			$.ajax({
				url: zhshLitres.ajaxUrl,
				type: 'POST',
				data: {
					action: 'zhsh_litres_get_files',
					nonce: zhshLitres.nonce
				},
				success: (response) => {
					if (response.success && response.data.files.length > 0) {
						this.renderFilesList(response.data.files);
					} else {
						$('#uploaded-files-section').hide();
					}
				}
			});
		},

		renderFilesList: function(files) {
			const $list = $('#uploaded-files-list');
			$list.empty();

			files.forEach(file => {
				const $item = $(`
					<div class="zhsh-litres-file-item" data-path="${file.path}">
						<div class="zhsh-litres-file-info">
							<div class="zhsh-litres-file-name">${file.name}</div>
							<div class="zhsh-litres-file-meta">${file.date} • ${file.size}</div>
						</div>
						<button class="zhsh-litres-file-delete" title="Удалить">×</button>
					</div>
				`);

				$item.on('click', (e) => {
					if (!$(e.target).hasClass('zhsh-litres-file-delete')) {
						this.selectFile(file.path, $item);
					}
				});

				$item.find('.zhsh-litres-file-delete').on('click', (e) => {
					e.stopPropagation();
					this.deleteFile(file.path, file.name);
				});

				$list.append($item);
			});

			$('#uploaded-files-section').show();
		},

		selectFile: function(filePath, $item) {
			$('.zhsh-litres-file-item').removeClass('selected');
			$item.addClass('selected');
			this.selectedFilePath = filePath;

			this.hideMessage('upload-message');
			this.showMessage('upload-message', 'Файл выбран: ' + $item.find('.zhsh-litres-file-name').text(), 'success');

			$('#step-select').show();
		},

		deleteFile: function(filePath, fileName) {
			if (!confirm('Удалить файл "' + fileName + '"?')) {
				return;
			}

			$.ajax({
				url: zhshLitres.ajaxUrl,
				type: 'POST',
				data: {
					action: 'zhsh_litres_delete_file',
					nonce: zhshLitres.nonce,
					file_path: filePath
				},
				success: (response) => {
					if (response.success) {
						if (this.selectedFilePath === filePath) {
							this.selectedFilePath = null;
						}
						this.loadUploadedFiles();
					} else {
						alert('Ошибка: ' + response.data.message);
					}
				}
			});
		},

		showMessage: function(elementId, message, type) {
			const $msg = $('#' + elementId);
			$msg.removeClass('error success')
				.addClass(type)
				.html(message)
				.show();
		},

		hideMessage: function(elementId) {
			$('#' + elementId).hide();
		},

		addLoading: function($btn) {
			$btn.prop('disabled', true)
				.append('<span class="zhsh-litres-loading"></span>');
		},

		removeLoading: function($btn) {
			$btn.prop('disabled', false)
				.find('.zhsh-litres-loading').remove();
		},

		handleUpload: function(e) {
			e.preventDefault();

			const $form = $(e.target);
			const $btn = $form.find('button[type="submit"]');
			const formData = new FormData($form[0]);

			formData.append('action', 'zhsh_litres_upload_file');
			formData.append('nonce', zhshLitres.nonce);

			this.hideMessage('upload-message');
			this.addLoading($btn);

			const $progressBar = $('#upload-progress-bar');
			const $progressPercent = $('#upload-progress-percent');
			$progressBar.show();

			$.ajax({
				url: zhshLitres.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				xhr: function() {
					const xhr = new window.XMLHttpRequest();
					xhr.upload.addEventListener('progress', function(evt) {
						if (evt.lengthComputable) {
							const percentComplete = Math.round((evt.loaded / evt.total) * 100);
							$progressBar.find('.zhsh-litres-progress-fill').css('width', percentComplete + '%');
							$progressPercent.text(percentComplete + '%');
						}
					}, false);
					return xhr;
				},
				success: (response) => {
					this.removeLoading($btn);
					$progressBar.hide();

					if (response.success) {
						this.showMessage('upload-message', response.data.message, 'success');
						this.selectedFilePath = response.data.filePath;
						this.loadUploadedFiles();
						$('#step-select').slideDown();
						$form[0].reset();
					} else {
						this.showMessage('upload-message', response.data.message, 'error');
					}
				},
				error: () => {
					this.removeLoading($btn);
					$progressBar.hide();
					this.showMessage('upload-message', zhshLitres.strings.error, 'error');
				}
			});
		},

		handleScan: function(e) {
			const $btn = $(e.target);

			if (!this.selectedFilePath) {
				this.showMessage('scan-message', 'Выберите файл для сканирования', 'error');
				return;
			}

			this.hideMessage('scan-message');
			this.addLoading($btn);

			const $scanProgress = $('#scan-progress-bar');
			$scanProgress.show();

			$.ajax({
				url: zhshLitres.ajaxUrl,
				type: 'POST',
				data: {
					action: 'zhsh_litres_scan_file',
					nonce: zhshLitres.nonce,
					file_path: this.selectedFilePath
				},
				success: (response) => {
					this.removeLoading($btn);
					$scanProgress.hide();

					if (response.success) {
						this.scannedData.genres = response.data.genres;
						this.scannedData.authors = response.data.authors;

						this.renderGenreList();
						this.renderAuthorList();

						$('#zhsh-litres-filters').slideDown();
						$btn.hide();
					} else {
						this.showMessage('scan-message', response.data.message, 'error');
					}
				},
				error: () => {
					this.removeLoading($btn);
					$scanProgress.hide();
					this.showMessage('scan-message', zhshLitres.strings.error, 'error');
				}
			});
		},

		renderGenreList: function() {
			const $list = $('#genre-list');
			$list.empty();

			$.each(this.scannedData.genres, (id, name) => {
				$list.append(
					`<label>
						<input type="checkbox" name="genre_ids[]" value="${id}">
						${this.escapeHtml(name)}
					</label>`
				);
			});
		},

		renderAuthorList: function() {
			const $list = $('#author-list');
			$list.empty();

			$.each(this.scannedData.authors, (id, name) => {
				$list.append(
					`<label>
						<input type="checkbox" name="author_ids[]" value="${id}">
						${this.escapeHtml(name)}
					</label>`
				);
			});
		},

		filterList: function(type, e) {
			const query = $(e.target).val().toLowerCase();
			const $list = $('#' + type + '-list');

			$list.find('label').each(function() {
				const text = $(this).text().toLowerCase();
				$(this).toggle(text.includes(query));
			});
		},

		handleStart: function(e) {
			const $btn = $(e.target);
			const genreIds = [];
			const authorIds = [];

			$('input[name="genre_ids[]"]:checked').each(function() {
				genreIds.push($(this).val());
			});

			$('input[name="author_ids[]"]:checked').each(function() {
				authorIds.push($(this).val());
			});

			if (genreIds.length === 0) {
				alert(zhshLitres.strings.selectGenres);
				return;
			}

			this.addLoading($btn);

			$.ajax({
				url: zhshLitres.ajaxUrl,
				type: 'POST',
				data: {
					action: 'zhsh_litres_start_import',
					nonce: zhshLitres.nonce,
					genre_ids: genreIds,
					author_ids: authorIds
				},
				success: (response) => {
					this.removeLoading($btn);

					if (response.success) {
						$('#step-progress').slideDown();
						$('#step-select').slideUp();
						this.startProgressMonitoring();
					} else {
						alert(response.data.message);
					}
				},
				error: () => {
					this.removeLoading($btn);
					alert(zhshLitres.strings.error);
				}
			});
		},

		startProgressMonitoring: function() {
			// Сразу запускаем первую обработку
			this.processBatch();
		},

		processBatch: function() {
			$.ajax({
				url: zhshLitres.ajaxUrl,
				type: 'POST',
				data: {
					action: 'zhsh_litres_process_batch',
					nonce: zhshLitres.nonce
				},
				success: (response) => {
					if (response.success) {
						this.updateProgress(response.data);

						// Если импорт не завершен, запускаем следующую итерацию
						if (response.data.status === 'running') {
							setTimeout(() => this.processBatch(), 500);
						}
					}
				},
				error: () => {
					// При ошибке переключаемся на режим мониторинга
					this.checkProgress();
					this.progressInterval = setInterval(() => {
						this.checkProgress();
					}, 2000);
				}
			});
		},

		checkProgress: function() {
			$.ajax({
				url: zhshLitres.ajaxUrl,
				type: 'POST',
				data: {
					action: 'zhsh_litres_get_status',
					nonce: zhshLitres.nonce
				},
				success: (response) => {
					if (response.success) {
						this.updateProgress(response.data);
					}
				}
			});
		},

		updateProgress: function(status) {
			let percent = 0;
			let percentText = '';

			if (status.total > 0) {
				percent = Math.round((status.processed / status.total) * 100);
				percentText = percent + '%';
			} else {
				// Для больших файлов показываем анимированный прогресс
				percent = 100;
				percentText = 'Обработка...';
			}

			$('#progress-fill').css('width', percent + '%');
			$('#progress-percent').text(percentText);
			$('#progress-count').text(status.processed);
			$('#progress-status').text(status.status);

			if (status.status === 'completed') {
				clearInterval(this.progressInterval);
				$('#progress-fill').css('width', '100%');
				$('#progress-percent').text('100%');
				this.showMessage('progress-message', zhshLitres.strings.completed, 'success');
				$('#zhsh-litres-stop-btn').hide();
			} else if (status.status === 'error') {
				clearInterval(this.progressInterval);
				this.showMessage('progress-message', status.error, 'error');
			}
		},

		handleStop: function(e) {
			if (!confirm('Вы уверены, что хотите остановить импорт?')) {
				return;
			}

			const $btn = $(e.target);
			this.addLoading($btn);

			$.ajax({
				url: zhshLitres.ajaxUrl,
				type: 'POST',
				data: {
					action: 'zhsh_litres_stop_import',
					nonce: zhshLitres.nonce
				},
				success: (response) => {
					this.removeLoading($btn);
					clearInterval(this.progressInterval);

					if (response.success) {
						this.showMessage('progress-message', response.data.message, 'success');
					}
				},
				error: () => {
					this.removeLoading($btn);
					alert(zhshLitres.strings.error);
				}
			});
		},

		escapeHtml: function(text) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text.replace(/[&<>"']/g, (m) => map[m]);
		}
	};

	$(document).ready(function() {
		ZhshLitres.init();
	});

})(jQuery);
