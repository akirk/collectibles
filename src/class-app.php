<?php
/**
 * Main plugin class and bootstrap.
 *
 * @package Collectibles
 */

namespace Collectibles;

use WpApp\WpApp;
use WpApp\BaseApp;

/**
 * Main plugin class. Initializes the app, registers routes, and sets up storage.
 */
class App extends BaseApp {
	/**
	 * Set up hooks and the underlying WpApp instance.
	 */
	public function __construct() {
		// See https://github.com/akirk/wp-app for documentation.
		$this->app = new WpApp(
			$this->get_template_dir(),
			$this->get_url_path(),
			array(
				'require_login'       => true,
				'require_capability'  => 'edit_posts',
				'app_name'            => 'Collectibles',
				'app_name_textdomain' => 'collectibles',
				'app_icon'            => self::get_asset_url( 'icon.svg' ),
				// Owned content: REST reads are gated with the app's capability and
				// OpenStation keeps these menus out of its dock.
				'post_types'          => array( Collection::POST_TYPE, Item::POST_TYPE ),
				'taxonomies'          => array( Item::TAXONOMY ),
			)
		);

		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_action( 'init', array( $this, 'register_meta' ) );

		add_action( 'template_redirect', array( $this, 'maybe_setup_assets' ) );
		add_action( 'before_delete_post', array( $this, 'delete_related_resources' ), 10, 2 );

		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_ability_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
		add_filter( 'ai_assistant_ability_domains', array( $this, 'register_ai_assistant_ability_domains' ) );
	}

	/**
	 * Get the base URL path for the app. This is used to route requests and generate links.
	 */
	protected function get_url_path(): string {
		return 'collectibles';
	}

	/**
	 * Get the directory path for the app's templates.
	 */
	protected function get_template_dir(): string {
		return dirname( __DIR__ ) . '/templates';
	}

	/**
	 * Enqueue the shared stylesheet for every app template.
	 */
	public function maybe_setup_assets(): void {
		if ( ! $this->app->is_app_request() ) {
			return;
		}

		$asset_path = dirname( __DIR__ ) . '/assets/collectibles.css';
		$version    = file_exists( $asset_path ) ? (string) filemtime( $asset_path ) : '1.0.0';

		wp_app_enqueue_style(
			'collectibles',
			plugins_url( 'assets/collectibles.css', dirname( __DIR__ ) . '/collectibles.php' ),
			array(),
			$version,
			$this->get_url_path()
		);
	}

	/**
	 * Declare the app's routes. BaseApp calls this during init().
	 */
	protected function setup_routes(): void {
		$this->app->route( '' );
		$this->app->route( 'search', 'search.php' );
		$this->app->route( 'settings', 'settings.php' );

		$this->app->route( 'collection/new', 'collection-form.php' );
		$this->app->route( 'collection/{id}', 'collection.php' );
		$this->app->route( 'collection/{id}/edit', 'collection-form.php' );
		$this->app->route( 'collection/{id}/export', 'collection-export.php' );

		$this->app->route( 'collection/{collection_id}/item/new', 'item-form.php' );
		$this->app->route( 'collection/{collection_id}/item/{id}', 'item.php' );
		$this->app->route( 'collection/{collection_id}/item/{id}/edit', 'item-form.php' );
	}

	/**
	 * Add the app's menu items.
	 */
	protected function setup_menu(): void {
		$this->app->add_menu_item( 'collections', __( 'Collections', 'collectibles' ), self::get_url() );
		$this->app->add_menu_item( 'search', __( 'Search', 'collectibles' ), self::get_url( 'search' ) );
		$this->app->add_menu_item( 'settings', __( 'Settings', 'collectibles' ), self::get_url( 'settings' ) );
	}

	/**
	 * No custom tables: everything is posts, post meta and terms.
	 */
	protected function setup_database(): void {
	}

	/**
	 * Register the custom post types.
	 */
	public function register_post_types(): void {
		Collection::register_post_types();
		Item::register_post_types();
	}

	/**
	 * Register the item tag taxonomy.
	 */
	public function register_taxonomies(): void {
		Item::register_taxonomies();
	}

	/**
	 * Register the post meta for collections and items.
	 */
	public function register_meta(): void {
		Collection::register_meta();
		Item::register_meta();
	}

	/**
	 * Delete child resources before a parent post is permanently deleted:
	 * a collection takes its items with it, an item its photos.
	 *
	 * @param int           $post_id The post being deleted.
	 * @param \WP_Post|null $post    The post object being deleted.
	 */
	public function delete_related_resources( int $post_id, ?\WP_Post $post = null ): void {
		$post = $post instanceof \WP_Post ? $post : get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		if ( Collection::POST_TYPE === $post->post_type ) {
			$item_ids = get_posts(
				array(
					'post_type'        => Item::POST_TYPE,
					'post_status'      => get_post_stati( array(), 'names' ),
					'post_parent'      => $post_id,
					'numberposts'      => -1,
					'fields'           => 'ids',
					'suppress_filters' => false,
				)
			);

			foreach ( $item_ids as $item_id ) {
				$item_id = absint( $item_id );

				if ( $item_id && $item_id !== $post_id ) {
					wp_delete_post( $item_id, true );
				}
			}
		}

		if ( Item::POST_TYPE === $post->post_type ) {
			Item::delete_photos( $post_id );
		}
	}

	/**
	 * Register an Abilities API category for this plugin.
	 */
	public function register_ability_category(): void {
		Abilities::register_category();
	}

	/**
	 * Register the read-only abilities that let an assistant look things up.
	 */
	public function register_abilities(): void {
		Abilities::register();
	}

	/**
	 * Tell AI Assistant which user terms belong to this plugin.
	 *
	 * @param array $domains Domain map.
	 */
	public function register_ai_assistant_ability_domains( array $domains ): array {
		$domains['collectibles'] = 'collectibles, collection, coins, stamps, banknotes, trading cards, records, books, catalog, inventory';

		return $domains;
	}

	/**
	 * Activation hook: register everything, then flush rewrite rules.
	 */
	public function activate(): void {
		$this->register_post_types();
		$this->register_taxonomies();
		$this->register_meta();

		flush_rewrite_rules();
	}

	/**
	 * Deactivation hook: flush rewrite rules.
	 */
	public function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Build a URL inside the app.
	 *
	 * @param string $path Path relative to the app's base URL.
	 */
	public static function get_url( string $path = '' ): string {
		return trailingslashit( home_url( '/collectibles/' . ltrim( $path, '/' ) ) );
	}

	/**
	 * Build a URL to a file in the plugin's assets directory, with a filemtime
	 * cache buster when available.
	 *
	 * @param string $relative Path relative to the assets/ directory.
	 */
	public static function get_asset_url( string $relative ): string {
		$relative   = ltrim( $relative, '/' );
		$asset_path = dirname( __DIR__ ) . '/assets/' . $relative;
		$asset_url  = plugins_url( 'assets/' . $relative, dirname( __DIR__ ) . '/collectibles.php' );

		if ( file_exists( $asset_path ) ) {
			$asset_url = add_query_arg( 'ver', (string) filemtime( $asset_path ), $asset_url );
		}

		return $asset_url;
	}
}
