<?php
/**
 * Тестовый скрипт для проверки импорта
 * Запуск: php test-import.php
 */

// Загружаем WordPress
require_once __DIR__ . '/../../../wp-load.php';

if (!defined('ABSPATH')) {
	die('WordPress не загружен');
}

echo "=== Тест импорта книг ===\n\n";

// Путь к CSV файлу
$file_path = get_option('zhsh_litres_uploaded_file');

if (!$file_path || !file_exists($file_path)) {
	echo "ОШИБКА: Файл не найден: $file_path\n";
	echo "Проверьте, загружен ли файл через админку\n";
	exit(1);
}

echo "Файл найден: $file_path\n";
echo "Размер файла: " . number_format(filesize($file_path) / 1024 / 1024, 2) . " MB\n\n";

// Загружаем классы плагина
require_once __DIR__ . '/includes/class-parser.php';
require_once __DIR__ . '/includes/class-batch-processor.php';

use ZhSh\Litres\Parser;
use ZhSh\Litres\Batch_Processor;

$parser = new Parser();

// Тест 1: Извлечение жанров
echo "=== Тест 1: Извлечение жанров ===\n";
$genres = $parser->extract_genres($file_path);
echo "Найдено жанров: " . count($genres) . "\n";
if (count($genres) > 0) {
	echo "Первые 5 жанров:\n";
	$i = 0;
	foreach ($genres as $genre) {
		if ($i++ >= 5) break;
		echo "  - $genre\n";
	}
}
echo "\n";

// Тест 2: Извлечение авторов
echo "=== Тест 2: Извлечение авторов ===\n";
$authors = $parser->extract_authors($file_path);
echo "Найдено авторов: " . count($authors) . "\n";
if (count($authors) > 0) {
	echo "Первые 5 авторов:\n";
	$i = 0;
	foreach ($authors as $author) {
		if ($i++ >= 5) break;
		echo "  - $author\n";
	}
}
echo "\n";

// Тест 3: Парсинг книг (первые 10)
echo "=== Тест 3: Парсинг книг ===\n";
$test_genres = array_slice($genres, 0, 1); // Берем только первый жанр
$books = $parser->parse_books($file_path, $test_genres, [], 0, 10);
echo "Распарсено книг: " . count($books) . "\n";
if (count($books) > 0) {
	echo "Первая книга:\n";
	$book = $books[0];
	echo "  ID: {$book['id']}\n";
	echo "  Название: {$book['title']}\n";
	echo "  Автор: {$book['author']}\n";
	echo "  Жанр: {$book['category']}\n";
	echo "  Цена: {$book['price']}\n";
}
echo "\n";

// Тест 4: Подсчет книг по выбранным жанрам
echo "=== Тест 4: Подсчет книг ===\n";
$selected_genres = get_option('zhsh_litres_import_genres', []);
$selected_authors = get_option('zhsh_litres_import_authors', []);

if (empty($selected_genres)) {
	echo "Жанры не выбраны. Используем все жанры для теста.\n";
	$selected_genres = $genres;
}

echo "Выбрано жанров: " . count($selected_genres) . "\n";
echo "Выбрано авторов: " . count($selected_authors) . "\n";

// Считаем книги (первые 100 строк для теста)
$test_books = $parser->parse_books($file_path, $selected_genres, $selected_authors, 0, 100);
echo "Найдено книг в первых 100 строках: " . count($test_books) . "\n\n";

// Тест 5: Проверка статуса импорта
echo "=== Тест 5: Статус импорта ===\n";
$status = get_option('zhsh_litres_import_status', 'idle');
$processed = get_option('zhsh_litres_import_processed', 0);
$total = get_option('zhsh_litres_import_total', 0);
$offset = get_option('zhsh_litres_import_offset', 0);

echo "Статус: $status\n";
echo "Обработано: $processed\n";
echo "Всего: $total\n";
echo "Смещение: $offset\n\n";

// Тест 6: Ручной запуск одной итерации импорта
echo "=== Тест 6: Ручной запуск импорта ===\n";
$processor = new Batch_Processor();

if ($status === 'idle' || $status === 'stopped') {
	echo "Импорт не запущен. Запускаем...\n";
	if (empty($selected_genres)) {
		$selected_genres = array_slice($genres, 0, 3); // Берем первые 3 жанра
	}
	$total = $processor->start_import($file_path, $selected_genres, $selected_authors);
	echo "Импорт запущен. Будет импортировано до $total книг\n";
}

echo "Запускаем одну итерацию обработки...\n";
$processor->process_batch();

// Проверяем результат
$new_status = get_option('zhsh_litres_import_status', 'idle');
$new_processed = get_option('zhsh_litres_import_processed', 0);
$new_offset = get_option('zhsh_litres_import_offset', 0);

echo "Новый статус: $new_status\n";
echo "Обработано: $new_processed\n";
echo "Новое смещение: $new_offset\n";

if ($new_processed > $processed) {
	echo "\n✓ УСПЕХ! Импортировано " . ($new_processed - $processed) . " книг\n";
} else {
	echo "\n✗ ВНИМАНИЕ: Книги не импортированы. Проверьте фильтры жанров/авторов\n";
}

echo "\n=== Тест завершен ===\n";
