<?php
declare(strict_types=1);

// Блокировка прямого доступа
if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html(get_admin_page_title()); ?></h1>

	<div class="zhsh-litres-import-wrapper">
		<!-- Шаг 1: Загрузка файла -->
		<div class="card zhsh-litres-step" id="step-upload">
			<h2>Шаг 1: Загрузка файла каталога</h2>
			<p>Загрузите CSV файл каталога из ЛитРес</p>

			<form id="zhsh-litres-upload-form" method="post" enctype="multipart/form-data">
				<input type="file" name="file" id="zhsh-litres-file" accept=".csv" required>
				<button type="submit" class="button button-primary">
					Загрузить файл
				</button>
			</form>

			<div class="zhsh-litres-progress-wrapper" id="upload-progress-bar" style="display:none;">
				<div class="zhsh-litres-progress-bar">
					<div class="zhsh-litres-progress-fill"></div>
				</div>
				<div class="zhsh-litres-progress-text">
					<span id="upload-progress-percent">0%</span>
				</div>
			</div>

			<div class="zhsh-litres-message" id="upload-message"></div>
		</div>

		<!-- Шаг 2: Сканирование и выбор -->
		<div class="card zhsh-litres-step" id="step-select" style="display:none;">
			<h2>Шаг 2: Выбор жанров и авторов</h2>

			<button type="button" class="button" id="zhsh-litres-scan-btn">
				Сканировать файл
			</button>

			<div class="zhsh-litres-progress-wrapper" id="scan-progress-bar" style="display:none;">
				<div class="zhsh-litres-progress-bar">
					<div class="zhsh-litres-progress-fill" style="width: 100%; animation: zhsh-litres-pulse 1.5s ease-in-out infinite;"></div>
				</div>
				<div class="zhsh-litres-progress-text">Сканирование файла...</div>
			</div>

			<div class="zhsh-litres-message" id="scan-message"></div>

			<div id="zhsh-litres-filters" style="display:none;">
				<div class="zhsh-litres-filter-section">
					<h3>Жанры</h3>
					<div class="zhsh-litres-filter-search">
						<input type="text" id="genre-search" placeholder="Поиск жанров..." class="regular-text">
					</div>
					<div class="zhsh-litres-checkbox-list" id="genre-list"></div>
				</div>

				<div class="zhsh-litres-filter-section">
					<h3>Авторы (необязательно)</h3>
					<div class="zhsh-litres-filter-search">
						<input type="text" id="author-search" placeholder="Поиск авторов..." class="regular-text">
					</div>
					<div class="zhsh-litres-checkbox-list" id="author-list"></div>
				</div>

				<button type="button" class="button button-primary button-large" id="zhsh-litres-start-btn">
					Начать импорт
				</button>
			</div>
		</div>

		<!-- Шаг 3: Прогресс импорта -->
		<div class="card zhsh-litres-step" id="step-progress" style="display:none;">
			<h2>Шаг 3: Прогресс импорта</h2>

			<div class="zhsh-litres-progress-wrapper">
				<div class="zhsh-litres-progress-bar">
					<div class="zhsh-litres-progress-fill" id="progress-fill"></div>
				</div>
				<div class="zhsh-litres-progress-text">
					<span id="progress-percent">0%</span> -
					<span id="progress-status">Запуск...</span>
				</div>
				<div class="zhsh-litres-progress-details">
					<span id="progress-count">0</span> книг импортировано
				</div>
			</div>

			<div class="zhsh-litres-message" id="progress-message"></div>

			<button type="button" class="button button-secondary" id="zhsh-litres-stop-btn">
				Остановить импорт
			</button>
		</div>
	</div>
</div>
