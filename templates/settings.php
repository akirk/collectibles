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

		$coll_form_notice = __( 'Settings saved.', 'collectibles' );
	}
}

$coll_credentials = Numista::get_credentials();
$coll_key_fixed   = Numista::is_api_key_fixed();
$coll_used        = Numista::get_usage();
$coll_budget      = Numista::get_monthly_budget();
$coll_remaining   = Numista::get_remaining();

$coll_page_title  = __( 'Settings', 'collectibles' );
$coll_shell_class = 'shell shell-narrow';

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

		<form class="panel" method="post" action="<?php echo esc_url( App::get_url( 'settings' ) ); ?>">
			<input type="hidden" name="coll_action" value="save_settings">
			<?php wp_nonce_field( 'coll_save_settings', 'coll_nonce' ); ?>

			<h2><?php echo esc_html__( 'Numista', 'collectibles' ); ?></h2>
			<p class="field-hint">
				<?php echo esc_html__( 'Coins and banknotes can be filled in from the Numista catalogue. Register a client on Numista to get these three; they belong to your account, not to the site.', 'collectibles' ); ?>
				<a href="<?php echo esc_url( Numista::get_api_key_url() ); ?>" target="_blank" rel="noreferrer noopener">
					<?php echo esc_html__( 'Numista API documentation', 'collectibles' ); ?>
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
				<p class="field-hint"><?php echo esc_html__( 'The name you registered the client under. It identifies the lookups as yours.', 'collectibles' ); ?></p>
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
					<p class="field-hint"><?php echo esc_html__( 'The key is set in wp-config.php, so it cannot be changed here.', 'collectibles' ); ?></p>
				<?php endif; ?>
			</div>

			<h2><?php echo esc_html__( 'Rebrickable', 'collectibles' ); ?></h2>
			<p class="field-hint">
				<?php echo esc_html__( 'Rebrickable allows an average of one API request per second per account, with a small allowance for bursts. It does not publish a monthly quota. Lookups are cached for a year; if requests are throttled, wait before trying again.', 'collectibles' ); ?>
				<a href="https://rebrickable.com/api/v3/docs/" target="_blank" rel="noreferrer noopener"><?php echo esc_html__( 'API limits', 'collectibles' ); ?></a>
			</p>
			<p class="field-hint">
				<?php echo esc_html__( 'Fill in LEGO sets using a personal Rebrickable API key. Other brands can be entered manually. Prices, completeness and condition are yours to record.', 'collectibles' ); ?>
				<a href="https://rebrickable.com/api/" target="_blank" rel="noreferrer noopener"><?php echo esc_html__( 'Get a Rebrickable API key', 'collectibles' ); ?></a>
			</p>
			<div class="field">
				<label for="coll_rebrickable_key"><?php echo esc_html__( 'API key', 'collectibles' ); ?></label>
				<input id="coll_rebrickable_key" name="coll_rebrickable_key" type="password" autocomplete="off" spellcheck="false" value="<?php echo esc_attr( Rebrickable::is_api_key_fixed() ? '' : Rebrickable::get_api_key() ); ?>" <?php disabled( Rebrickable::is_api_key_fixed() ); ?>>
				<?php if ( Rebrickable::is_api_key_fixed() ) : ?>
					<p class="field-hint"><?php echo esc_html__( 'The key is set in wp-config.php, so it cannot be changed here.', 'collectibles' ); ?></p>
				<?php endif; ?>
			</div>

			<h2><?php echo esc_html__( 'LEGO.com', 'collectibles' ); ?></h2>
			<div class="field">
				<label for="coll_lego_locale"><?php echo esc_html__( 'Language and region', 'collectibles' ); ?></label>
				<input id="coll_lego_locale" name="coll_lego_locale" type="text" value="<?php echo esc_attr( $coll_lego_locale ?? Rebrickable::get_lego_locale_override() ); ?>" placeholder="<?php echo esc_attr( Rebrickable::get_default_lego_locale() ); ?>" pattern="[A-Za-z]{2}-[A-Za-z]{2}" maxlength="5" spellcheck="false" autocomplete="off">
				<p class="field-hint"><?php echo esc_html__( 'Use the code from your preferred LEGO.com address, such as en-us, en-gb or de-at. This applies to product and instructions links. Leave blank to follow your WordPress language and region. LEGO may not support that combination; enter an override if needed.', 'collectibles' ); ?></p>
			</div>

			<div class="form-actions">
				<button class="button button-primary" type="submit"><?php echo esc_html__( 'Save settings', 'collectibles' ); ?></button>
				<a class="button button-quiet" href="<?php echo esc_url( App::get_url() ); ?>"><?php echo esc_html__( 'Back', 'collectibles' ); ?></a>
			</div>
		</form>

		<section class="panel">
			<h2><?php echo esc_html__( 'Numista lookups this month', 'collectibles' ); ?></h2>
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
				<?php echo esc_html__( 'Every catalogue entry is kept once it has been fetched, so looking the same piece up again is free. Only a new entry costs a lookup.', 'collectibles' ); ?>
			</p>
		</section>

<?php
require __DIR__ . '/_foot.php';
