<?php
declare(strict_types=1);

namespace ZhSh\Litres;

// Блокировка прямого доступа
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Класс для регистрации Custom Post Type и таксономий
 */
class CPT {
	/**
	 * Регистрация CPT и таксономий
	 */
	public function register(): void {
		$this->register_post_type();
		$this->register_taxonomies();
	}

	/**
	 * Регистрация Custom Post Type для книг
	 */
	private function register_post_type(): void {
		$labels = [
			'name'               => 'Книги',
			'singular_name'      => 'Книга',
			'menu_name'          => 'Книги ЛитРес',
			'add_new'            => 'Добавить новую',
			'add_new_item'       => 'Добавить новую книгу',
			'edit_item'          => 'Редактировать книгу',
			'new_item'           => 'Новая книга',
			'view_item'          => 'Посмотреть книгу',
			'search_items'       => 'Искать книги',
			'not_found'          => 'Книги не найдены',
			'not_found_in_trash' => 'В корзине книг не найдено',
			'all_items'          => 'Все книги',
		];

		$args = [
			'labels'              => $labels,
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => true,
			'query_var'           => true,
			'rewrite'             => ['slug' => 'book'],
			'capability_type'     => 'post',
			'has_archive'         => true,
			'hierarchical'        => false,
			'menu_position'       => 5,
			'menu_icon'           => 'dashicons-book',
			'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
			'taxonomies'          => ['zhsh_litres_genre', 'zhsh_litres_author'],
		];

		register_post_type('zhsh_litres_book', $args);
	}

	/**
	 * Регистрация таксономий
	 */
	private function register_taxonomies(): void {
		$this->register_genre_taxonomy();
		$this->register_author_taxonomy();
	}

	/**
	 * Регистрация таксономии жанров
	 */
	private function register_genre_taxonomy(): void {
		$labels = [
			'name'              => 'Жанры',
			'singular_name'     => 'Жанр',
			'search_items'      => 'Искать жанры',
			'all_items'         => 'Все жанры',
			'parent_item'       => 'Родительский жанр',
			'parent_item_colon' => 'Родительский жанр:',
			'edit_item'         => 'Редактировать жанр',
			'update_item'       => 'Обновить жанр',
			'add_new_item'      => 'Добавить новый жанр',
			'new_item_name'     => 'Название нового жанра',
			'menu_name'         => 'Жанры',
		];

		$args = [
			'labels'            => $labels,
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => ['slug' => 'genre'],
		];

		register_taxonomy('zhsh_litres_genre', ['zhsh_litres_book'], $args);
	}

	/**
	 * Регистрация таксономии авторов
	 */
	private function register_author_taxonomy(): void {
		$labels = [
			'name'              => 'Авторы',
			'singular_name'     => 'Автор',
			'search_items'      => 'Искать авторов',
			'all_items'         => 'Все авторы',
			'edit_item'         => 'Редактировать автора',
			'update_item'       => 'Обновить автора',
			'add_new_item'      => 'Добавить нового автора',
			'new_item_name'     => 'Имя нового автора',
			'menu_name'         => 'Авторы',
		];

		$args = [
			'labels'            => $labels,
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => ['slug' => 'author'],
		];

		register_taxonomy('zhsh_litres_author', ['zhsh_litres_book'], $args);
	}
}
