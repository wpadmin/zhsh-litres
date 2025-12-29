<?php
declare(strict_types=1);

namespace ZhSh\Litres;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Парсер CSV каталога LitRes
 */
class Parser {

	public function extract_genres(string $file_path): array {
		$genres = [];
		$handle = fopen($file_path, 'r');

		if (false === $handle) {
			return $genres;
		}

		// Удаляем BOM если есть
		$bom = fread($handle, 3);
		if ($bom !== "\xEF\xBB\xBF") {
			rewind($handle);
		}

		fgetcsv($handle, 0, ';');

		while (false !== ($data = fgetcsv($handle, 0, ';'))) {
			if (isset($data[3]) && !empty($data[3])) {
				$category = trim($data[3]);
				if ($category) {
					$genres[$category] = $category;
				}
			}
		}

		fclose($handle);
		return array_unique($genres);
	}

	public function extract_authors(string $file_path): array {
		$authors = [];
		$handle = fopen($file_path, 'r');

		if (false === $handle) {
			return $authors;
		}

		// Удаляем BOM если есть
		$bom = fread($handle, 3);
		if ($bom !== "\xEF\xBB\xBF") {
			rewind($handle);
		}

		fgetcsv($handle, 0, ';');

		while (false !== ($data = fgetcsv($handle, 0, ';'))) {
			if (isset($data[12]) && !empty($data[12])) {
				$author = trim($data[12]);
				if ($author) {
					$authors[$author] = $author;
				}
			}
		}

		fclose($handle);
		return array_unique($authors);
	}

	public function parse_books(string $file_path, array $genres, array $authors, int $offset = 0, int $limit = 500): array {
		$books = [];
		$count = 0;
		$skipped = 0;

		$handle = fopen($file_path, 'r');
		if (false === $handle) {
			return $books;
		}

		// Удаляем BOM если есть
		$bom = fread($handle, 3);
		if ($bom !== "\xEF\xBB\xBF") {
			rewind($handle);
		}

		fgetcsv($handle, 0, ';');

		while (false !== ($data = fgetcsv($handle, 0, ';')) && $count < $limit) {
			if (!isset($data[0], $data[1], $data[3])) {
				continue;
			}

			$category = trim($data[3]);
			$author = isset($data[12]) ? trim($data[12]) : '';

			$match_genre = empty($genres) || in_array($category, $genres, true);
			$match_author = empty($authors) || in_array($author, $authors, true);

			if ($match_genre && $match_author) {
				if ($skipped < $offset) {
					$skipped++;
					continue;
				}

				$books[] = [
					'id' => trim($data[0]),
					'title' => isset($data[1]) ? trim($data[1]) : '',
					'description' => isset($data[2]) ? trim($data[2]) : '',
					'category' => $category,
					'price' => isset($data[5]) ? (float) $data[5] : 0.0,
					'url' => isset($data[10]) ? trim($data[10]) : '',
					'image' => isset($data[11]) ? trim($data[11]) : '',
					'author' => $author,
				];

				$count++;
			}
		}

		fclose($handle);
		return $books;
	}
}
