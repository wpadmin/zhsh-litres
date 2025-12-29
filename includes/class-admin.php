<?php
declare(strict_types=1);

namespace ZhSh\Litres;

// Блокировка прямого доступа
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Класс админ-панели
 */
class Admin {
	private Batch_Processor $processor;
	private Parser $parser;

	public function __construct() {
		$this->processor = new Batch_Processor();
		$this->parser = new Parser();

		add_action('admin_menu', [$this, 'add_admin_menu']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
		add_action('wp_ajax_zhsh_litres_upload_file', [$this, 'ajax_upload_file']);
		add_action('wp_ajax_zhsh_litres_scan_file', [$this, 'ajax_scan_file']);
		add_action('wp_ajax_zhsh_litres_start_import', [$this, 'ajax_start_import']);
		add_action('wp_ajax_zhsh_litres_get_status', [$this, 'ajax_get_status']);
		add_action('wp_ajax_zhsh_litres_stop_import', [$this, 'ajax_stop_import']);
		add_action('wp_ajax_zhsh_litres_process_batch', [$this, 'ajax_process_batch']);
		add_action('wp_ajax_zhsh_litres_get_files', [$this, 'ajax_get_files']);
		add_action('wp_ajax_zhsh_litres_delete_file', [$this, 'ajax_delete_file']);
		add_action('admin_notices', [$this, 'maybe_show_flush_notice']);
	}

	/**
	 * Добавление пункта меню
	 */
	public function add_admin_menu(): void {
		add_submenu_page(
			'edit.php?post_type=zhsh_litres_book',
			'Импорт из ЛитРес',
			'Импорт',
			'manage_options',
			'zhsh-litres-import',
			[$this, 'render_import_page']
		);
	}

	/**
	 * Подключение стилей и скриптов
	 */
	public function enqueue_assets(string $hook): void {
		if ('zhsh_litres_book_page_zhsh-litres-import' !== $hook) {
			return;
		}

		wp_enqueue_style(
			'zhsh-litres-admin',
			ZHSH_LITRES_PLUGIN_URL . 'admin/assets/css/admin.css',
			[],
			ZHSH_LITRES_VERSION
		);

		wp_enqueue_script(
			'zhsh-litres-admin',
			ZHSH_LITRES_PLUGIN_URL . 'admin/assets/js/admin.js',
			['jquery'],
			ZHSH_LITRES_VERSION,
			true
		);

		wp_localize_script('zhsh-litres-admin', 'zhshLitres', [
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'nonce'   => wp_create_nonce('zhsh_litres_nonce'),
			'strings' => [
				'uploadingFile'  => 'Загрузка файла...',
				'scanningFile'   => 'Сканирование файла...',
				'importing'      => 'Импорт...',
				'completed'      => 'Импорт завершен!',
				'error'          => 'Произошла ошибка',
				'selectGenres'   => 'Выберите хотя бы один жанр',
				'uploadFileReq'  => 'Сначала загрузите файл',
			],
		]);
	}

	/**
	 * Отрисовка страницы импорта
	 */
	public function render_import_page(): void {
		if (!current_user_can('manage_options')) {
			wp_die('У вас недостаточно прав для доступа к этой странице.');
		}

		require_once ZHSH_LITRES_PLUGIN_DIR . 'admin/views/import.php';
	}

	/**
	 * AJAX: Загрузка файла
	 */
	public function ajax_upload_file(): void {
		check_ajax_referer('zhsh_litres_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Доступ запрещен']);
		}

		if (empty($_FILES['file'])) {
			wp_send_json_error(['message' => 'Файл не загружен']);
		}

		$file = $_FILES['file'];

		// Валидация файла
		$allowed_types = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
		if (!in_array($file['type'], $allowed_types, true)) {
			wp_send_json_error(['message' => 'Разрешены только CSV файлы']);
		}

		// Сохранение файла
		$upload_dir = wp_upload_dir();
		$target_dir = $upload_dir['basedir'] . '/zhsh-litres/';

		if (!file_exists($target_dir)) {
			wp_mkdir_p($target_dir);
		}

		$file_path = $target_dir . 'catalog-' . time() . '.csv';

		if (!move_uploaded_file($file['tmp_name'], $file_path)) {
			wp_send_json_error(['message' => 'Не удалось сохранить файл']);
		}

		update_option('zhsh_litres_uploaded_file', $file_path);

		wp_send_json_success([
			'message'  => 'Файл успешно загружен',
			'filePath' => $file_path,
		]);
	}

	/**
	 * AJAX: Сканирование файла
	 */
	public function ajax_scan_file(): void {
		check_ajax_referer('zhsh_litres_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Доступ запрещен']);
		}

		$file_path = '';
		if (isset($_POST['file_path'])) {
			$file_path = sanitize_text_field($_POST['file_path']);
		}

		if (empty($file_path)) {
			$file_path = get_option('zhsh_litres_uploaded_file');
		}

		if (!$file_path || !file_exists($file_path)) {
			wp_send_json_error(['message' => 'Файл не найден']);
		}

		// Сохраняем путь к файлу для дальнейшего использования
		update_option('zhsh_litres_uploaded_file', $file_path);

		// Увеличиваем лимиты для больших файлов
		set_time_limit(300);
		ini_set('memory_limit', '512M');

		// Извлекаем жанры и авторов (первые 50000 строк)
		$genres = $this->parser->extract_genres($file_path, 50000);
		$authors = $this->parser->extract_authors($file_path, 50000);

		// Сохраняем результаты сканирования
		update_option('zhsh_litres_scanned_genres', $genres);
		update_option('zhsh_litres_scanned_authors', $authors);

		wp_send_json_success([
			'genres'  => $genres,
			'authors' => $authors,
		]);
	}

	/**
	 * AJAX: Запуск импорта
	 */
	public function ajax_start_import(): void {
		check_ajax_referer('zhsh_litres_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Доступ запрещен']);
		}

		$file_path = get_option('zhsh_litres_uploaded_file');
		$genre_ids = isset($_POST['genre_ids']) ? array_map('sanitize_text_field', (array) $_POST['genre_ids']) : [];
		$author_ids = isset($_POST['author_ids']) ? array_map('sanitize_text_field', (array) $_POST['author_ids']) : [];

		if (!$file_path || !file_exists($file_path)) {
			wp_send_json_error(['message' => 'Файл не найден']);
		}

		if (empty($genre_ids)) {
			wp_send_json_error(['message' => 'Выберите хотя бы один жанр']);
		}

		// Запускаем импорт
		$this->processor->start_import($file_path, $genre_ids, $author_ids);

		wp_send_json_success(['message' => 'Импорт запущен']);
	}

	/**
	 * AJAX: Получение статуса
	 */
	public function ajax_get_status(): void {
		check_ajax_referer('zhsh_litres_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Доступ запрещен']);
		}

		$status = $this->processor->get_import_status();
		wp_send_json_success($status);
	}

	/**
	 * AJAX: Остановка импорта
	 */
	public function ajax_stop_import(): void {
		check_ajax_referer('zhsh_litres_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Доступ запрещен']);
		}

		$this->processor->stop_import();
		wp_send_json_success(['message' => 'Импорт остановлен']);
	}

	/**
	 * AJAX: Прямая обработка батча (альтернатива WP Cron)
	 */
	public function ajax_process_batch(): void {
		check_ajax_referer('zhsh_litres_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Доступ запрещен']);
		}

		// Увеличиваем лимиты для обработки батча
		set_time_limit(300);
		ini_set('memory_limit', '512M');

		try {
			$this->processor->process_batch();
			$status = $this->processor->get_import_status();
			wp_send_json_success($status);
		} catch (\Exception $e) {
			wp_send_json_error([
				'message' => 'Ошибка обработки: ' . $e->getMessage()
			]);
		}
	}

	/**
	 * AJAX: Получить список загруженных файлов
	 */
	public function ajax_get_files(): void
	{
		check_ajax_referer('zhsh_litres_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Доступ запрещен']);
		}

		$upload_dir = wp_upload_dir();
		$target_dir = $upload_dir['basedir'] . '/zhsh-litres/';
		$files      = [];

		if (is_dir($target_dir)) {
			$items = scandir($target_dir);
			foreach ($items as $item) {
				if ($item === '.' || $item === '..') {
					continue;
				}

				$file_path = $target_dir . $item;
				$extension = pathinfo($file_path, PATHINFO_EXTENSION);

				if (is_file($file_path) && $extension === 'csv') {
					$files[] = [
						'name' => $item,
						'path' => $file_path,
						'date' => date('Y-m-d H:i:s', filemtime($file_path)),
						'size' => size_format(filesize($file_path)),
					];
				}
			}
		}

		usort($files, fn($a, $b) => strcmp($b['date'], $a['date']));

		wp_send_json_success(['files' => $files]);
	}

	/**
	 * AJAX: Удалить файл
	 */
	public function ajax_delete_file(): void {
		check_ajax_referer('zhsh_litres_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Доступ запрещен']);
		}

		$file_path = $_POST['file_path'] ?? '';

		if (empty($file_path)) {
			wp_send_json_error(['message' => 'Путь к файлу не указан']);
		}

		$upload_dir = wp_upload_dir();
		$target_dir = $upload_dir['basedir'] . '/zhsh-litres/';

		if (strpos($file_path, $target_dir) !== 0) {
			wp_send_json_error(['message' => 'Некорректный путь к файлу']);
		}

		if (!file_exists($file_path)) {
			wp_send_json_error(['message' => 'Файл не найден']);
		}

		if (unlink($file_path)) {
			wp_send_json_success(['message' => 'Файл удален']);
		} else {
			wp_send_json_error(['message' => 'Не удалось удалить файл']);
		}
	}

	/**
	 * Показать уведомление о необходимости сброса permalinks
	 */
	public function maybe_show_flush_notice(): void {
		if (!current_user_can('manage_options')) {
			return;
		}

		if (get_option('zhsh_litres_permalinks_flushed')) {
			return;
		}

		$screen = get_current_screen();
		if (!$screen || strpos($screen->id, 'zhsh_litres') === false) {
			return;
		}

		echo '<div class="notice notice-warning is-dismissible">';
		echo '<p><strong>Книги ЛитРес:</strong> Для корректной работы ссылок на книги, ';
		echo '<a href="' . esc_url(admin_url('options-permalink.php')) . '">перейдите в настройки постоянных ссылок</a> ';
		echo 'и нажмите "Сохранить изменения" (ничего не меняя).</p>';
		echo '</div>';
	}
}
