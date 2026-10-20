<?php
/**
 * Per-user settings: the Numista API credentials and this month's usage.
 *
 * @package Collectibles
 */

namespace Collectibles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$coll_form_error  = '';
$coll_form_notice = '';
$coll_action      = isset( $_POST['coll_action'] ) ? sanitize_key( wp_unslash( $_POST['coll_action'] ) ) : '';

if ( 'save_settings' === $coll_action ) {
	$coll_nonce       = isset( $_POST['coll_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['coll_nonce'] ) ) : '';
	$coll_lego_locale = isset( $_POST['coll_lego_locale'] ) ? sanitize_text_field( wp_unslash( $_POST['coll_lego_locale'] ) ) : '';

	if ( ! wp_verify_nonce( $coll_nonce, 'coll_save_settings' ) ) {
		$coll_form_error = __( 'The settings could not be saved. Reload and try again.', 'collectibles' );
	} elseif ( '' === Rebrickable::sanitize_lego_locale( $coll_lego_locale ) ) {
		$coll_form_error = __( 'Enter a LEGO language-region code such as en-us, en-gb or de-at.', 'collectibles' );
	} else {
		Numista::save_credentials(
			array(
				'client_name' => isset( $_POST['coll_numista_client_name'] ) ? sanitize_text_field( wp_unslash( $_POST['coll_numista_client_name'] ) ) : '',
				'client_id'   => isset( $_POST['coll_numista_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['coll_numista_client_id'] ) ) : '',
				'key'         => isset( $_POST['coll_numista_key'] ) ? sanitize_text_field( wp_unslash( $_POST['coll_numista_key'] ) ) : '',
			)
		);

		Rebrickable::save_api_key( isset( $_POST['coll_rebrickable_key'] ) ? sanitize_text_field( wp_unslash( $_POST['coll_rebrickable_key'] ) ) : '' );
		Rebrickable::save_lego_locale( $coll_lego_locale );

		if ( current_user_can( 'manage_options' ) ) {
			update_option( 'coll_public_directory', isset( $_POST['coll_public_directory'] ) );
			update_option( 'coll_index_directory', isset( $_POST['coll_index_directory'] ) );
			update_option( 'coll_index_collections', isset( $_POST['coll_index_collections'] ) );
		}

		$coll_form_notice = __( 'Settings saved.', 'collectibles' );
	}
}

$coll_credentials = Numista::get_credentials();
$coll_key_fixed   = Numista::is_api_key_fixed();
$coll_used        = Numista::get_usage();
$coll_budget      = Numista::get_monthly_budget();
$coll_remaining   = Numista::get_remaining();

$coll_page_title  = __( 'Settings', 'collectibles' );
$coll_shell_class = 'shell shell-mid';

require __DIR__ . '/_head.php';
?>

		<header class="topbar">
			<div>
				<a class="crumb" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Collections', 'collectibles' ); ?></a>
				<h1><?php echo esc_html( $coll_page_title ); ?></h1>
			</div>
		</header>

		<?php if ( '' !== $coll_form_error ) : ?>
			<div class="notice notice-error"><?php echo esc_html( $coll_form_error ); ?></div>
		<?php endif; ?>

		<?php if ( '' !== $coll_form_notice ) : ?>
			<div class="notice notice-success"><?php echo esc_html( $coll_form_notice ); ?></div>
		<?php endif; ?>

		<form class="settings-form" method="post" action="<?php echo esc_url( App::get_url( 'settings' ) ); ?>">
			<input type="hidden" name="coll_action" value="save_settings">
			<?php wp_nonce_field( 'coll_save_settings', 'coll_nonce' ); ?>

			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<section class="panel" aria-labelledby="coll-sharing-settings">
					<h2 id="coll-sharing-settings"><?php echo esc_html__( 'Shared collections', 'collectibles' ); ?></h2>
					<?php $coll_shared_collections = Sharing::get_directory(); ?>
					<label class="settings-checkbox">
						<input type="checkbox" name="coll_public_directory" value="1" <?php checked( Sharing::directory_enabled() ); ?>>
						<?php
						printf(
							/* translators: %s: number of shared collections */
							esc_html( _n( 'Enable public directory (%s shared collection)', 'Enable public directory (%s shared collections)', count( $coll_shared_collections ), 'collectibles' ) ),
							esc_html( number_format_i18n( count( $coll_shared_collections ) ) )
						);
						?>
					</label>
					<?php if ( $coll_shared_collections ) : ?>
						<ul>
							<?php foreach ( $coll_shared_collections as $coll_shared_collection ) : ?>
								<li>
									<a href="<?php echo esc_url( $coll_shared_collection['url'] ); ?>"><?php echo esc_html( $coll_shared_collection['title'] ); ?></a>
									<span class="field-hint">— <?php echo esc_html( $coll_shared_collection['owner'] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<p class="field-hint">
						<?php
						printf(
							/* translators: %s: link to the public directory */
							esc_html__( 'Visitors at %s see public collections and their owners’ display names. Each owner chooses which collections to share. Signed-in collectors keep their private dashboard.', 'collectibles' ),
							'<a href="' . esc_url( add_query_arg( 'view', 'shared', App::get_url() ) ) . '">/collectibles/</a>'
						);
						?>
					</p>
					<details class="settings-indexing">
						<summary><?php echo esc_html__( 'Search engine visibility', 'collectibles' ); ?></summary>
						<div class="settings-indexing-content">
							<p class="field-hint"><?php echo esc_html__( 'These options follow WordPress’s search engine visibility setting. If WordPress discourages indexing, these pages do too. Unlisted collections and previews always discourage indexing.', 'collectibles' ); ?></p>
							<p><a href="<?php echo esc_url( admin_url( 'options-reading.php' ) ); ?>"><?php echo esc_html__( 'WordPress Reading settings', 'collectibles' ); ?></a> — <?php echo get_option( 'blog_public' ) ? esc_html__( 'Site indexing is allowed.', 'collectibles' ) : esc_html__( 'Site indexing is discouraged.', 'collectibles' ); ?></p>
							<label class="settings-checkbox"><input type="checkbox" name="coll_index_directory" value="1" <?php checked( (bool) get_option( 'coll_index_directory', false ) ); ?>> <?php echo esc_html__( 'Allow search engines to index the public directory', 'collectibles' ); ?></label>
							<label class="settings-checkbox"><input type="checkbox" name="coll_index_collections" value="1" <?php checked( (bool) get_option( 'coll_index_collections', false ) ); ?>> <?php echo esc_html__( 'Allow search engines to index public collections and their shared item pages', 'collectibles' ); ?></label>
						</div>
					</details>
				</section>
			<?php endif; ?>

			<section class="panel settings-numista" aria-labelledby="coll-numista-settings">
				<div>
					<h2 id="coll-numista-settings"><?php echo esc_html__( 'Numista', 'collectibles' ); ?></h2>
					<p class="field-hint settings-intro">
						<?php echo esc_html__( 'Fill in coins and banknotes from Numista. Create a free account, get an API key, then copy the three details below from Numista.', 'collectibles' ); ?>
						<a href="<?php echo esc_url( Numista::get_api_key_url() ); ?>" target="_blank" rel="noreferrer noopener">
							<?php echo esc_html__( 'Get a Numista API key', 'collectibles' ); ?>
						</a>
					</p>

					<div class="field">
						<label for="coll_numista_client_name"><?php echo esc_html__( 'Client name', 'collectibles' ); ?></label>
						<input
							id="coll_numista_client_name"
							name="coll_numista_client_name"
							type="text"
							value="<?php echo esc_attr( $coll_credentials['client_name'] ); ?>"
							autocomplete="off"
						>
						<p class="field-hint"><?php echo esc_html__( 'Copy the client name shown on Numista.', 'collectibles' ); ?></p>
					</div>

					<div class="field">
						<label for="coll_numista_client_id"><?php echo esc_html__( 'Client ID', 'collectibles' ); ?></label>
						<input
							id="coll_numista_client_id"
							name="coll_numista_client_id"
							type="text"
							value="<?php echo esc_attr( $coll_credentials['client_id'] ); ?>"
							autocomplete="off"
						>
					</div>

					<div class="field">
						<label for="coll_numista_key"><?php echo esc_html__( 'API key', 'collectibles' ); ?></label>
						<input
							id="coll_numista_key"
							name="coll_numista_key"
							type="password"
							value="<?php echo esc_attr( $coll_key_fixed ? '' : $coll_credentials['key'] ); ?>"
							autocomplete="off"
							spellcheck="false"
							<?php disabled( $coll_key_fixed ); ?>
						>
						<?php if ( $coll_key_fixed ) : ?>
							<p class="field-hint"><?php echo esc_html__( 'Your site administrator has set this key.', 'collectibles' ); ?></p>
						<?php endif; ?>
					</div>

				</div>

				<aside class="settings-usage" aria-labelledby="coll-numista-usage">
					<h3 id="coll-numista-usage"><?php echo esc_html__( 'Numista lookups this month', 'collectibles' ); ?></h3>
					<p>
						<?php
						printf(
							/* translators: 1: lookups used, 2: monthly allowance */
							esc_html__( '%1$d of %2$d used.', 'collectibles' ),
							absint( $coll_used ),
							absint( $coll_budget )
						);
						?>
						<?php if ( 0 === $coll_remaining ) : ?>
							<?php echo esc_html__( 'Lookups resume at the start of next month.', 'collectibles' ); ?>
						<?php endif; ?>
					</p>
					<p class="field-hint">
						<?php echo esc_html__( 'New items can use more than one lookup. Repeated lookups do not count towards the limit.', 'collectibles' ); ?>
					</p>
				</aside>

			</section>

			<section class="panel" aria-labelledby="coll-rebrickable-settings">
				<h2 id="coll-rebrickable-settings"><?php echo esc_html__( 'Rebrickable', 'collectibles' ); ?></h2>
				<p class="field-hint settings-intro">
					<?php
					printf(
						/* translators: %s: link to Rebrickable */
						esc_html__( 'Fill in LEGO sets from %s.', 'collectibles' ),
						'<a href="https://rebrickable.com/" target="_blank" rel="noreferrer noopener">Rebrickable</a>'
					);
					?>
					<?php echo esc_html__( 'Create a free account or sign in, go to Account > Settings > API, then create a key and paste it below.', 'collectibles' ); ?>
				</p>
				<div class="field">
					<label for="coll_rebrickable_key"><?php echo esc_html__( 'API key', 'collectibles' ); ?></label>
					<input id="coll_rebrickable_key" name="coll_rebrickable_key" type="password" autocomplete="off" spellcheck="false" value="<?php echo esc_attr( Rebrickable::is_api_key_fixed() ? '' : Rebrickable::get_api_key() ); ?>" <?php disabled( Rebrickable::is_api_key_fixed() ); ?>>
					<?php if ( Rebrickable::is_api_key_fixed() ) : ?>
						<p class="field-hint"><?php echo esc_html__( 'Your site administrator has set this key.', 'collectibles' ); ?></p>
					<?php endif; ?>
				</div>

				<p class="field-hint">
					<?php echo esc_html__( 'Rebrickable limits how quickly you can look up sets. If asked to wait, try again shortly. Repeated lookups do not count towards the limit.', 'collectibles' ); ?>
					<a href="https://rebrickable.com/api/v3/docs/" target="_blank" rel="noreferrer noopener"><?php echo esc_html__( 'Details', 'collectibles' ); ?></a>
				</p>
			</section>

			<section class="panel" aria-labelledby="coll-lego-settings">
				<h2 id="coll-lego-settings"><?php echo esc_html__( 'LEGO.com', 'collectibles' ); ?></h2>
				<div class="field">
					<label for="coll_lego_locale"><?php echo esc_html__( 'Language and region', 'collectibles' ); ?></label>
					<input id="coll_lego_locale" name="coll_lego_locale" type="text" value="<?php echo esc_attr( $coll_lego_locale ?? Rebrickable::get_lego_locale_override() ); ?>" placeholder="<?php echo esc_attr( Rebrickable::get_default_lego_locale() ); ?>" pattern="[A-Za-z]{2}-[A-Za-z]{2}" maxlength="5" spellcheck="false" autocomplete="off">
					<p class="field-hint"><?php echo esc_html__( 'For LEGO.com links, use the code from your preferred address, such as en-us, en-gb or de-at. Leave blank to use your site language and region.', 'collectibles' ); ?></p>
				</div>

			</section>

			<div class="form-actions">
				<button class="button button-primary" type="submit"><?php echo esc_html__( 'Save settings', 'collectibles' ); ?></button>
				<a class="button button-quiet" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back', 'collectibles' ); ?></a>
			</div>
		</form>

<?php
require __DIR__ . '/_foot.php';
