<?php
/**
 * Plugin Name: Countdown Evento
 * Description: Imposta titolo e scadenza di un countdown da mostrare come widget o con lo shortcode [countdown].
 * Version: 1.1.2
 * Requires at least: 5.3
 * Requires PHP: 7.4
 */

namespace CountdownEvento;

defined( 'ABSPATH' ) || exit;

const OPTION = 'countdown_evento_settings';
const PAGE   = 'countdown-evento';

add_action( 'admin_init', __NAMESPACE__ . '\\register_settings' );
add_action( 'admin_menu', __NAMESPACE__ . '\\register_page' );
add_action( 'init', __NAMESPACE__ . '\\register_shortcode', 20 );
add_action( 'widgets_init', __NAMESPACE__ . '\\register_countdown_widget' );

function register_page() {
	add_options_page( 'Countdown', 'Countdown', 'manage_options', PAGE, __NAMESPACE__ . '\\render_page' );
}

function register_settings() {
	register_setting(
		PAGE,
		OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_settings',
		)
	);
}

/**
 * Read the active theme's Customizer values until the first save in the plugin.
 */
function get_settings() {
	$settings = get_option( OPTION );
	if ( is_array( $settings ) ) {
		return wp_parse_args( $settings, array( 'title' => '', 'deadline' => '' ) );
	}

	$legacy_title = get_theme_mod( 'countdown_title', '' );
	$legacy_date  = get_theme_mod( 'countdown', '' );
	$deadline     = '';

	if ( is_string( $legacy_date ) && preg_match( '/\A(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})(?::\d{2})?\z/', $legacy_date, $parts ) ) {
		$deadline = $parts[1] . 'T' . $parts[2];
	}

	return array(
		'title'    => is_string( $legacy_title ) ? $legacy_title : '',
		'deadline' => parse_deadline( $deadline ) ? $deadline : '',
	);
}

function parse_deadline( $value ) {
	if ( ! is_string( $value ) || ! preg_match( '/\A(?!0000)\d{4}-\d{2}-\d{2}T\d{2}:\d{2}\z/', $value ) ) {
		return null;
	}

	$deadline = \DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $value, wp_timezone() );

	// PHP can normalize invalid dates and local times skipped by daylight saving.
	if ( ! $deadline || $deadline->format( 'Y-m-d\TH:i' ) !== $value ) {
		return null;
	}

	return $deadline;
}

function sanitize_settings( $input ) {
	$title    = $input['title'] ?? '';
	$deadline = $input['deadline'] ?? '';

	if ( '' !== $deadline && ! parse_deadline( $deadline ) ) {
		add_settings_error( OPTION, 'invalid_deadline', 'Inserisci una data e un’ora valide per il fuso orario del sito.' );
		return get_option( OPTION );
	}

	return array(
		'title'    => is_string( $title ) ? sanitize_text_field( $title ) : '',
		'deadline' => $deadline,
	);
}

function render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = get_settings();
	?>
	<div class="wrap">
		<h1>Countdown</h1>
		<form action="options.php" method="post">
			<?php settings_fields( PAGE ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="countdown-evento-title">Titolo</label></th>
					<td>
						<input id="countdown-evento-title" name="<?php echo esc_attr( OPTION ); ?>[title]"
							type="text" class="regular-text" value="<?php echo esc_attr( $settings['title'] ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="countdown-evento-deadline">Data e ora</label></th>
					<td>
						<input id="countdown-evento-deadline" name="<?php echo esc_attr( OPTION ); ?>[deadline]"
							type="datetime-local" step="60" value="<?php echo esc_attr( $settings['deadline'] ); ?>"
							aria-describedby="countdown-evento-timezone">
						<p id="countdown-evento-timezone" class="description">
							Fuso orario del sito (<?php echo esc_html( wp_timezone_string() ); ?>)
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	<p>Inserisci <code>[countdown]</code> nel contenuto o aggiungi il widget <strong>Countdown Evento</strong> da <strong>Aspetto → Widget</strong>.</p>
	</div>
	<?php
}

function register_shortcode() {
	add_shortcode( 'countdown', __NAMESPACE__ . '\\render_shortcode' );
}

function register_countdown_widget() {
	register_widget( Countdown_Widget::class );
}

class Countdown_Widget extends \WP_Widget {
	public function __construct() {
		parent::__construct(
			'countdown_evento',
			'Countdown Evento',
			array( 'description' => 'Mostra il conto alla rovescia configurato in Impostazioni → Countdown.' )
		);
	}

	public function widget( $args, $instance ) {
		$settings  = get_settings();
		$countdown = render_countdown( $settings['title'], parse_deadline( $settings['deadline'] ) );

		if ( '' === $countdown ) {
			return;
		}

		// The theme supplies the surrounding widget markup.
		echo $args['before_widget'] . $countdown . $args['after_widget'];
	}

	public function form( $instance ) {
		echo '<p>Imposta titolo e scadenza in <strong>Impostazioni → Countdown</strong>.</p>';
		return 'noform';
	}
}

function render_shortcode( $atts ) {
	$settings = get_settings();
	$parts    = explode( 'T', $settings['deadline'] );
	$atts     = (array) $atts;
	$atts     = shortcode_atts(
		array(
			'title' => $settings['title'],
			'date'  => $parts[0],
			'time'  => isset( $atts['date'] ) ? '00:00' : ( $parts[1] ?? '00:00' ),
		),
		$atts,
		'countdown'
	);
	return render_countdown( $atts['title'], parse_deadline( $atts['date'] . 'T' . $atts['time'] ) );
}

function render_countdown( $title, $deadline ) {
	if ( ! $deadline ) {
		return '';
	}

	$is_italian  = 1 === preg_match( '/^it(?:[_-]|$)/i', get_locale() );
	$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
	$date_text   = $is_italian ? wp_date( $date_format, $deadline->getTimestamp() ) : $deadline->format( $date_format );

	wp_enqueue_script( PAGE, plugins_url( 'assets/countdown.js', __FILE__ ), array(), '1.1.2', true );
	wp_enqueue_style( PAGE, plugins_url( 'assets/countdown.css', __FILE__ ), array(), '1.0.0' );

	ob_start();
	?>
	<div class="countdown-wrapper">
		<?php if ( '' !== $title ) : ?>
			<h4 class="countdown-title"><?php echo esc_html( $title ); ?></h4>
		<?php endif; ?>
		<div class="countdown-evento" role="timer" aria-live="off" lang="<?php echo $is_italian ? 'it' : 'en'; ?>"
			data-deadline="<?php echo esc_attr( $deadline->getTimestamp() ); ?>">
			<time datetime="<?php echo esc_attr( $deadline->format( DATE_ATOM ) ); ?>">
				<?php echo esc_html( $date_text ); ?>
			</time>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
