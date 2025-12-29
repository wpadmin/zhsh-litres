<?php
declare(strict_types=1);

/**
 * Plugin Name: Импорт книг из ЛитРес
 * Plugin URI: https://github.com/wpadmin/zhsh-litres
 * Description: Импорт книг из каталога ЛитРес с фильтрацией по жанрам
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Zhenya Sh.
 * Author URI: https://github.com/wpadmin
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace ZhSh\Litres;

// Блокировка прямого доступа
if (!defined('ABSPATH')) {
	exit;
}

// Константы плагина
define('ZHSH_LITRES_VERSION', '1.0.0');
define('ZHSH_LITRES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZHSH_LITRES_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZHSH_LITRES_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Автозагрузка классов
spl_autoload_register(function ($class) {
	$prefix = 'ZhSh\\Litres\\';
	$base_dir = ZHSH_LITRES_PLUGIN_DIR . 'includes/';

	$len = strlen($prefix);
	if (strncmp($prefix, $class, $len) !== 0) {
		return;
	}

	$relative_class = substr($class, $len);
	$file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';

	if (file_exists($file)) {
		require $file;
	}
});

/**
 * Главный класс плагина
 */
class Plugin {
	/**
	 * Единственный экземпляр класса
	 */
	private static ?Plugin $instance = null;

	/**
	 * Получить экземпляр плагина
	 */
	public static function get_instance(): Plugin {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Конструктор
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Инициализация хуков
	 */
	private function init_hooks(): void {
		register_activation_hook(__FILE__, [$this, 'activate']);
		register_deactivation_hook(__FILE__, [$this, 'deactivate']);

		add_action('plugins_loaded', [$this, 'load_textdomain']);
		add_action('init', [$this, 'init']);
	}

	/**
	 * Активация плагина
	 */
	public function activate(): void {
		// Регистрируем CPT для flush_rewrite_rules
		$cpt = new CPT();
		$cpt->register();

		flush_rewrite_rules();

		// Создаем таблицу для очереди импорта
		$this->create_queue_table();
	}

	/**
	 * Деактивация плагина
	 */
	public function deactivate(): void {
		flush_rewrite_rules();

		// Очищаем запланированные задачи
		wp_clear_scheduled_hook('zhsh_litres_process_batch');
		wp_clear_scheduled_hook('zhsh_litres_download_images');
	}

	/**
	 * Загрузка переводов (не используется)
	 */
	public function load_textdomain(): void {
		// Плагин использует русский язык напрямую
	}

	/**
	 * Инициализация плагина
	 */
	public function init(): void {
		// Регистрация CPT и таксономий
		$cpt = new CPT();
		$cpt->register();

		// Инициализация админки
		if (is_admin()) {
			new Admin();
		}

		// Инициализация батч-процессора
		new Batch_Processor();
	}

	/**
	 * Создание таблицы очереди
	 */
	private function create_queue_table(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'zhsh_litres_queue';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			book_data longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			processed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
	}
}

// Инициализация плагина
Plugin::get_instance();
