<?php
declare(strict_types=1);

namespace ZhSh\Litres;

if (!defined('ABSPATH')) {
	exit;
}

class Batch_Processor {
	private const BATCH_SIZE = 500;
	private const HOOK_NAME = 'zhsh_litres_process_batch';

	public function __construct() {
		add_action(self::HOOK_NAME, [$this, 'process_batch']);
	}

	public function start_import(string $file_path, array $genres, array $authors): int {
		// Увеличиваем лимиты PHP для работы с большими файлами
		@ini_set('memory_limit', '2048M');
		@ini_set('max_execution_time', '600');

		// Считаем общее количество книг для импорта
		$total = $this->count_matching_books($file_path, $genres, $authors);

		update_option('zhsh_litres_import_file', $file_path);
		update_option('zhsh_litres_import_genres', $genres);
		update_option('zhsh_litres_import_authors', $authors);
		update_option('zhsh_litres_import_offset', 0);
		update_option('zhsh_litres_import_processed', 0);
		update_option('zhsh_litres_import_total', $total);
		update_option('zhsh_litres_import_status', 'running');

		// Запускаем первую обработку немедленно
		if (!wp_next_scheduled(self::HOOK_NAME)) {
			wp_schedule_single_event(time(), self::HOOK_NAME);
		}

		// Принудительно запускаем WP Cron
		spawn_cron();

		return $total;
	}

	private function count_matching_books(string $file_path, array $genres, array $authors): int {
		$handle = fopen($file_path, 'r');
		if (false === $handle) {
			return 0;
		}

		// Удаляем BOM если есть
		$bom = fread($handle, 3);
		if ($bom !== "\xEF\xBB\xBF") {
			rewind($handle);
		}

		fgetcsv($handle, 0, ';');
		$count = 0;

		while (false !== ($data = fgetcsv($handle, 0, ';'))) {
			if (!isset($data[3])) {
				continue;
			}

			$category = trim($data[3]);
			$author = isset($data[12]) ? trim($data[12]) : '';

			$match_genre = empty($genres) || in_array($category, $genres, true);
			$match_author = empty($authors) || in_array($author, $authors, true);

			if ($match_genre && $match_author) {
				$count++;
			}
		}

		fclose($handle);
		return $count;
	}

	public function process_batch(): void {
		$file_path = get_option('zhsh_litres_import_file');
		$genres = get_option('zhsh_litres_import_genres', []);
		$authors = get_option('zhsh_litres_import_authors', []);
		$offset = (int) get_option('zhsh_litres_import_offset', 0);
		$processed = (int) get_option('zhsh_litres_import_processed', 0);

		if (!file_exists($file_path)) {
			update_option('zhsh_litres_import_status', 'error');
			update_option('zhsh_litres_import_error', 'Файл не найден');
			return;
		}

		$parser = new Parser();
		$books = $parser->parse_books($file_path, $genres, $authors, $offset, self::BATCH_SIZE);

		if (empty($books)) {
			update_option('zhsh_litres_import_status', 'completed');
			return;
		}

		$imported = $this->import_books_batch($books);

		$new_offset = $offset + self::BATCH_SIZE;
		$new_processed = $processed + $imported;

		update_option('zhsh_litres_import_offset', $new_offset);
		update_option('zhsh_litres_import_processed', $new_processed);

		// Планируем следующую задачу и сразу запускаем cron
		wp_schedule_single_event(time() + 2, self::HOOK_NAME);
		spawn_cron();
	}

	private function import_books_batch(array $books): int {
		$imported = 0;

		foreach ($books as $book) {
			// Проверяем существование книги
			$existing_id = get_posts([
				'post_type' => 'zhsh_litres_book',
				'meta_key' => 'zhsh_litres_book_id',
				'meta_value' => $book['id'],
				'posts_per_page' => 1,
				'fields' => 'ids',
			]);

			if (!empty($existing_id)) {
				continue;
			}

			// Создаем пост через wp_insert_post для правильной генерации slug
			$post_id = wp_insert_post([
				'post_title' => wp_strip_all_tags($book['title']),
				'post_content' => wp_kses_post($book['description']),
				'post_excerpt' => wp_trim_words($book['description'], 55),
				'post_status' => 'publish',
				'post_type' => 'zhsh_litres_book',
				'post_author' => 1,
			], true);

			if (is_wp_error($post_id)) {
				continue;
			}

			add_post_meta($post_id, 'zhsh_litres_book_id', $book['id'], true);
			add_post_meta($post_id, 'zhsh_litres_price', $book['price'], true);
			add_post_meta($post_id, 'zhsh_litres_url', $book['url'], true);
			add_post_meta($post_id, 'zhsh_litres_image', $book['image'], true);

			// Добавляем жанр
			if (!empty($book['category'])) {
				$term = get_term_by('name', $book['category'], 'zhsh_litres_genre');
				if (!$term) {
					$term_data = wp_insert_term($book['category'], 'zhsh_litres_genre');
					if (!is_wp_error($term_data)) {
						wp_set_object_terms($post_id, [$term_data['term_id']], 'zhsh_litres_genre');
					}
				} else {
					wp_set_object_terms($post_id, [$term->term_id], 'zhsh_litres_genre');
				}
			}

			// Добавляем автора
			if (!empty($book['author'])) {
				$term = get_term_by('name', $book['author'], 'zhsh_litres_author');
				if (!$term) {
					$term_data = wp_insert_term($book['author'], 'zhsh_litres_author');
					if (!is_wp_error($term_data)) {
						wp_set_object_terms($post_id, [$term_data['term_id']], 'zhsh_litres_author');
					}
				} else {
					wp_set_object_terms($post_id, [$term->term_id], 'zhsh_litres_author');
				}
			}

			$imported++;

			// Очищаем кэш WordPress каждые 100 книг
			if ($imported % 100 === 0) {
				wp_cache_flush();
			}
		}

		// Финальная очистка кэша
		wp_cache_flush();

		return $imported;
	}

	public function get_import_status(): array {
		return [
			'status' => get_option('zhsh_litres_import_status', 'idle'),
			'processed' => (int) get_option('zhsh_litres_import_processed', 0),
			'total' => (int) get_option('zhsh_litres_import_total', 0),
			'error' => get_option('zhsh_litres_import_error', ''),
		];
	}

	public function stop_import(): void {
		wp_clear_scheduled_hook(self::HOOK_NAME);
		update_option('zhsh_litres_import_status', 'stopped');
	}
}
