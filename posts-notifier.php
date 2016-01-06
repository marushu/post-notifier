<?php
/*
Plugin Name: Post Notifier
Version: 0.5
Description: Send information to the specified e-mail address when the post published.
Author: Shuhei Nishimura
Author URI: http://private.hibou-web.com
Plugin URI: https://github.com/marushu/post-notifier
Text Domain: post_notifier
Domain Path: /languages
*/

if( class_exists( 'Post_Notifier' ) )
	$post_notifier = new Post_Notifier();


class Post_Notifier {

	function __construct() {

		add_action( 'transition_post_status', array( $this, 'post_published_notification' ), 10, 3 );
		add_action("plugins_loaded", array( $this, 'plugins_loaded') );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'settings_init' ) );

	}

	public function plugins_loaded() {
		load_plugin_textdomain(
			"post_notifier",
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	public function post_published_notification( $new_status, $old_status, $post ) {

		$options = get_option( 'post_notifier_settings' );
		$emails  = isset( $options[ 'email_field' ] )
			? $options[ 'email_field' ]
			: '';
		$post_types = isset( $options[ 'post_type_field' ] )
			? $options[ 'post_type_field' ]
			: '';
		$sender_email = isset( $options[ 'sender_email_field' ] )
			? $options[ 'sender_email_field' ]
			: get_option( 'admin_email' );

		//$author  = $post->post_author; /* Post author ID. */
		//$name    = get_the_author_meta( 'display_name', $author );
		//$email     = array( 'trigger@recipe.ifttt.com', 'info@hibou-web.com' );

		$title     = wp_trim_words( esc_html( $post->post_title ), 100, '…' );
		$permalink = esc_url( get_permalink( intval( $post->ID ) ) );
		//$edit    = get_edit_post_link( $ID, '' );
		$message = '';

		/**
		 * get post's image ( postthumbnail or first image )
		 */
		$post_thumbnail = '';
		$post_content   = $post->post_content;

		if ( has_post_thumbnail() ) {
			$post_thumbnail_id    = get_post_thumbnail_id( $post->ID );
			$post_thumbnail_datas = wp_get_attachment_image_src( $post_thumbnail_id, 'full' );
			$attachments          = esc_url( $post_thumbnail_datas[ 0 ] );
		} else {
			$attachments = '';
		}

		$post_content = wp_strip_all_tags( $post->post_content );
		$post_content = wp_trim_words( $post_content, 50, '…' );

		foreach ( (array)$emails as $email ) {
			if ( ! is_email( $email ) )
				return;

			//$to[] = sprintf( '%s <%s>', $name, sanitize_email( $email ) );
			$to[] = sprintf( '%s', sanitize_email( $email ) );
			$headers[] = 'Bcc:' . sanitize_email( $email );
		}
		$subject = sprintf( '%s' . PHP_EOL, trim( $title ) );
		//$message = sprintf ('%s %s' . "\n\n", $title, $permalink );
		//$message .= sprintf( '%s' . "\r\n" . '%s', $permalink, esc_html( $post_content ) );
		$message .= sprintf( '%s' . PHP_EOL, trim( $permalink ) );
		//$message .= sprintf( '%s' . PHP_EOL, trim( esc_html( $post_content ) ) );
		$headers[] = 'From:' . sanitize_email( $sender_email );
		//$headers[] = 'Content-Type: text/plain; charset=UTF-8';

		/**
		 * check post status if post status is 'publish' is no fire
		 */
		if ( in_array( $post->post_type, $post_types ) && $new_status == 'publish' && $old_status != 'publish' ) {

			//$from_email = get_option( 'admin_email' );

			add_filter( 'wp_mail_from', function( $sender_email ) {

				return sanitize_email( $sender_email );

			});

			wp_mail( $to, $subject, $message, $headers, array( '/var/www/vhosts/test.hibou.jp/wp-content/uploads/2015/12/19513737491_a22632f7a3_o.jpg' ) );

		}
	}

	/**
	 * @param $emails
	 * @return mixed
	 */
	public function data_sanitize( $input ) {

		/**
		 * email
		 */
		$this->options = get_option( 'post_notifier_settings' );
		$new_input     = array();
		$shaped_emails = array();

		$emails = explode( ',', $input[ 'email_field' ] );
		if ( ! empty( $emails ) ) {
			foreach ( (array)$emails as $email ) {

				$email = sanitize_email( $email );

				if ( ! is_email( $email ) || empty( $email ) ) {

					add_settings_error(
						'post_notifier_settings',
						'email_field',
						__( 'Check your email address.', 'post_notifier' ),
						'error'
					);
					$new_input[ 'email_field' ] = isset( $this->options[ 'email_field' ] ) ? $shaped_emails : '';

				} else { // success!

					$shaped_emails[]            = $email;
					$new_input[ 'email_field' ] = isset( $this->options[ 'email_field' ] ) ? $shaped_emails : '';

				}
			}
		}

		/**
		 * post type
		 */
		$post_types = $input[ 'post_type_field' ];
		if( ! empty( $post_types ) ) {

			$selected_post_types = array();
			foreach ( $post_types as $post_type ) {

				$selected_post_types[] = $post_type;

			}

			$new_input[ 'post_type_field' ] = isset( $this->options[ 'post_type_field' ] ) ? $selected_post_types : '';

		} else {

			add_settings_error(
				'post_notifier_settings',
				'post_type_field',
				__( 'Select the post type', 'post_notifier' ),
				'error'
			);
			$new_input[ 'post_type_field' ] = '';

		}

		/**
		 * Sender email
		 */
		$sender_email = isset( $input[ 'sender_email_field' ] ) ? sanitize_email( $input[ 'sender_email_field' ] ) : '';
		$new_input[ 'sender_email_field' ] = ! empty( $sender_email ) ? $sender_email : '';


		return $new_input;

	}

	public function admin_menu() {
		add_options_page(
			'Post Notifier',
			'Post Notifier',
			'manage_options',
			'post_notifier',
			array( $this, 'post_notifier_options_page' )
		);
	}

	public function settings_init() {

		register_setting(
			'notifierpage',
			'post_notifier_settings',
			array( $this, 'data_sanitize' )
		);

		add_settings_section(
			'post_notifier_notifierpage_section',
			__( 'Post Notifier settings', 'post_notifier' ),
			array( $this, 'post_notifier_settings_section_callback' ),
			'notifierpage'
		);

		add_settings_field(
			'email_field',
			__( 'Set e-mail', 'post_notifier' ),
			array( $this, 'email_field_render' ),
			'notifierpage',
			'post_notifier_notifierpage_section'
		);

		add_settings_field(
			'post_type_field',
			__( 'Select the post type', 'post_notifier' ),
			array( $this, 'post_type_render' ),
			'notifierpage',
			'post_notifier_notifierpage_section'
		);

		add_settings_field(
			'sender_email_field',
			__( 'Set sender email', 'post_notifier' ),
			array( $this, 'from_email_render' ),
			'notifierpage',
			'post_notifier_notifierpage_section'
		);

	}

	public function post_notifier_settings_section_callback() {

		echo __( 'This section description', 'post_notifier' );

	}

	public function email_field_render() {

		$options = get_option( 'post_notifier_settings' );
		$emails  = isset( $options[ 'email_field' ] ) ? $options[ 'email_field' ] : '';
		$emails  = ! empty( $emails ) && is_array( $emails ) ? implode( ', ', $emails ) : '';

		?>
		<textarea name="post_notifier_settings[email_field]" id="" cols="60" width="auto" height="auto"
							rows="5"><?php echo $emails; ?></textarea>
		<?php

		echo __( '<p>Set e-mail you want to send in a <strong>comma-separated</strong>.</p>', 'post_notifier' );

	}

	public function post_type_render() {

		$options             = get_option( 'post_notifier_settings' );
		$selected_post_types = isset( $options[ 'post_type_field' ] ) ? $options[ 'post_type_field' ] : '';

		$args       = array(
			'public'  => true,
		);
		$output     = 'names';
		$post_types = array_values( get_post_types( $args, $output ) );
		$count = intval( count( $post_types ) );

		if ( ! empty( $post_types ) ) {

			for( $i = 0; $i < $count; $i++ ) {
				if( $post_types[ $i ] !== 'attachment' ) {
					?>

					<p>
						<input value="<?php echo esc_html( $post_types[ $i ] ); ?>" name="post_notifier_settings[post_type_field][]"
									 type="checkbox"
									 id="check-<?php echo esc_html( $post_types[ $i ] ); ?>"
							<?php
								if( ! empty( $selected_post_types ) && in_array( $post_types[ $i ], $selected_post_types ) )
									echo 'checked="selected"';
							?>>
						<label
							for="check-<?php echo esc_html( $post_types[ $i ] ); ?>"><?php echo esc_html( $post_types[ $i ] ); ?></label>
					</p>

					<?php
				}
			}

			echo __( '<p>Select the post type you want to send at the time of publication.</p>', 'post_notifier' );

		}

	}

	public function from_email_render() {

		$options      = get_option( 'post_notifier_settings' );
		$sender_email = isset( $options[ 'sender_email_field' ] ) ? sanitize_email( $options[ 'sender_email_field' ] ) : '';
		?>

		<input type="text" name="post_notifier_settings[sender_email_field]" value="<?php echo $sender_email; ?>" size="30" maxlength="30">

		<?php
		echo __( '<p>Sender e-mail address is <strong>single</strong>.</p>', 'post_notifier' );

	}

	public function post_notifier_options_page() {

		?>
		<form action='options.php' method='post'>

			<?php
			settings_fields( 'notifierpage' );
			do_settings_sections( 'notifierpage' );
			submit_button();
			?>

		</form>
		<?php
	}
}
