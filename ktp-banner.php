<?php
/**
 * Plugin Name: KTP Banner
 * Plugin URI: https://example.com
 * Description: KantanPro 向けに任意のバナー広告を表示するプラグインです。
 * Version: 1.0.5
 * Author: KantanPro
 * License: GPL-2.0-or-later
 * Text Domain: ktp-banner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KTP_Banner_Plugin {
	const OPTION_KEY = 'ktp_banner_options';

	/**
	 * @var KTP_Banner_Plugin|null
	 */
	private static $instance = null;

	/**
	 * @return KTP_Banner_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * KTP_Banner_Plugin constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'init', array( $this, 'register_display_hook_from_settings' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_banner_notice' ) );
		add_filter( 'do_shortcode_tag', array( $this, 'inject_banner_into_kantanpro_shortcode_output' ), 20, 4 );
		add_shortcode( 'ktp_banner', array( $this, 'render_banner_shortcode' ) );
	}

	/**
	 * プラグイン有効化時の初期値を設定。
	 *
	 * @return void
	 */
	public static function activate() {
		$defaults = array(
			'enabled'        => 1,
			'image_url'      => '',
			'link_url'       => '',
			'alt_text'       => '',
			'open_new_tab'   => 1,
			'display_admin'  => 1,
			'display_hook'   => 'ktpwp_between_pagination_footer',
			'frontend_hook'  => '',
		);

		if ( false === get_option( self::OPTION_KEY ) ) {
			add_option( self::OPTION_KEY, $defaults );
		}
	}

	/**
	 * @return void
	 */
	public function register_admin_menu() {
		add_options_page(
			__( 'KTP Banner 設定', 'ktp-banner' ),
			__( 'KTP Banner', 'ktp-banner' ),
			'manage_options',
			'ktp-banner',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'ktp_banner_group',
			self::OPTION_KEY,
			array( $this, 'sanitize_options' )
		);

		add_settings_section(
			'ktp_banner_main_section',
			__( 'バナー設定', 'ktp-banner' ),
			'__return_false',
			'ktp-banner'
		);

		$fields = array(
			'enabled'        => __( '有効化', 'ktp-banner' ),
			'image_url'      => __( '画像URL', 'ktp-banner' ),
			'link_url'       => __( 'リンクURL', 'ktp-banner' ),
			'alt_text'       => __( '代替テキスト', 'ktp-banner' ),
			'open_new_tab'   => __( '新しいタブで開く', 'ktp-banner' ),
			'display_admin'  => __( 'KantanPro管理画面で表示', 'ktp-banner' ),
			'frontend_hook'  => __( 'サイト全体の表示位置（KantanProなし向け）', 'ktp-banner' ),
			'display_hook'   => __( '追加表示フック名（任意）', 'ktp-banner' ),
		);

		foreach ( $fields as $field_key => $label ) {
			add_settings_field(
				'ktp_banner_field_' . $field_key,
				$label,
				array( $this, 'render_field' ),
				'ktp-banner',
				'ktp_banner_main_section',
				array( 'field_key' => $field_key )
			);
		}
	}

	/**
	 * 設定画面で使用するメディアライブラリ関連スクリプトを読み込む。
	 *
	 * @param string $hook_suffix 現在の管理画面フック
	 *
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'ktp-banner' !== $page && 'settings_page_ktp-banner' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'ktp-banner-admin',
			plugins_url( 'js/ktp-banner-admin.js', __FILE__ ),
			array( 'jquery', 'media-editor', 'media-views' ),
			'1.1.3',
			true
		);
		wp_localize_script(
			'ktp-banner-admin',
			'ktpBannerAdmin',
			array(
				'title'       => __( 'バナー画像を選択', 'ktp-banner' ),
				'button_text' => __( 'この画像を使用', 'ktp-banner' ),
				'media_error' => __( 'メディアライブラリの読み込みに失敗しました。ページを再読み込みしてください。', 'ktp-banner' ),
			)
		);
	}

	/**
	 * @param array $input 入力値
	 *
	 * @return array
	 */
	public function sanitize_options( $input ) {
		$frontend_raw = isset( $input['frontend_hook'] ) ? $input['frontend_hook'] : '';
		$frontend_ok  = in_array( $frontend_raw, array( 'wp_footer', 'wp_body_open' ), true ) ? $frontend_raw : '';

		$output = array(
			'enabled'        => empty( $input['enabled'] ) ? 0 : 1,
			'image_url'      => empty( $input['image_url'] ) ? '' : esc_url_raw( $input['image_url'] ),
			'link_url'       => empty( $input['link_url'] ) ? '' : esc_url_raw( $input['link_url'] ),
			'alt_text'       => empty( $input['alt_text'] ) ? '' : sanitize_text_field( $input['alt_text'] ),
			'open_new_tab'   => empty( $input['open_new_tab'] ) ? 0 : 1,
			'display_admin'  => empty( $input['display_admin'] ) ? 0 : 1,
			'frontend_hook'  => $frontend_ok,
			'display_hook'   => empty( $input['display_hook'] ) ? 'ktpwp_between_pagination_footer' : sanitize_key( $input['display_hook'] ),
		);

		return $output;
	}

	/**
	 * @param array $args フィールド情報
	 *
	 * @return void
	 */
	public function render_field( $args ) {
		$options   = $this->get_options();
		$field_key = $args['field_key'];
		$value     = isset( $options[ $field_key ] ) ? $options[ $field_key ] : '';
		$name_attr = self::OPTION_KEY . '[' . $field_key . ']';

		switch ( $field_key ) {
			case 'enabled':
			case 'open_new_tab':
			case 'display_admin':
				printf(
					'<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
					esc_attr( $name_attr ),
					checked( 1, (int) $value, false ),
					esc_html__( '有効', 'ktp-banner' )
				);
				break;
			case 'image_url':
				printf(
					'<input type="url" class="regular-text" id="ktp-banner-image-url" name="%1$s" value="%2$s" placeholder="https://example.com/" />',
					esc_attr( $name_attr ),
					esc_attr( $value )
				);
				echo ' <button type="button" class="button" id="ktp-banner-select-image">' . esc_html__( '画像を選択', 'ktp-banner' ) . '</button>';
				echo ' <button type="button" class="button" id="ktp-banner-clear-image">' . esc_html__( 'クリア', 'ktp-banner' ) . '</button>';
				echo '<div style="margin-top:10px;">';
				if ( '' !== $value ) {
					echo '<img id="ktp-banner-image-preview" src="' . esc_url( $value ) . '" alt="" style="max-width:300px;height:auto;border:1px solid #ddd;padding:4px;background:#fff;" />';
				} else {
					echo '<img id="ktp-banner-image-preview" src="" alt="" style="display:none;max-width:300px;height:auto;border:1px solid #ddd;padding:4px;background:#fff;" />';
				}
				echo '</div>';
				echo '<p class="description">' . esc_html__( '「画像を選択」からアップロード、またはメディアライブラリ（ギャラリー）から選択できます。', 'ktp-banner' ) . '</p>';
				break;
			case 'link_url':
				printf(
					'<input type="url" class="regular-text" name="%1$s" value="%2$s" placeholder="https://example.com/" />',
					esc_attr( $name_attr ),
					esc_attr( $value )
				);
				break;
			case 'frontend_hook':
				$choices = array(
					''              => __( '表示しない（ショートコード [ktp_banner] または KantanPro のみ）', 'ktp-banner' ),
					'wp_footer'     => __( '全ページ・フッター直前（wp_footer）', 'ktp-banner' ),
					'wp_body_open'  => __( '全ページ・body 開始直後（wp_body_open・テーマ対応が必要）', 'ktp-banner' ),
				);
				echo '<select name="' . esc_attr( $name_attr ) . '" id="ktp-banner-frontend-hook">';
				foreach ( $choices as $val => $label ) {
					printf(
						'<option value="%1$s" %3$s>%2$s</option>',
						esc_attr( $val ),
						esc_html( $label ),
						selected( $value, $val, false )
					);
				}
				echo '</select>';
				echo '<p class="description">' . esc_html__( 'KantanPro がないサイトでは、従来の「追加表示フック」だけでは表示されません。外部サイト全体にバナーを出す場合は「wp_footer」などを選んでください。KantanPro と併用する場合は重複しないよう、どちらか一方にしてください。', 'ktp-banner' ) . '</p>';
				break;
			case 'display_hook':
				printf(
					'<input type="text" class="regular-text" name="%1$s" value="%2$s" placeholder="ktpwp_between_pagination_footer" />',
					esc_attr( $name_attr ),
					esc_attr( $value )
				);
				echo '<p class="description">' . esc_html__( '指定したフック名で add_action されます。KantanPro 側に同名 do_action がある場合のみ表示されます。', 'ktp-banner' ) . '</p>';
				break;
			default:
				printf(
					'<input type="text" class="regular-text" name="%1$s" value="%2$s" />',
					esc_attr( $name_attr ),
					esc_attr( $value )
				);
				break;
		}
	}

	/**
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'KTP Banner 設定', 'ktp-banner' ); ?></h1>
			<p><?php echo esc_html__( 'KantanPro 画面向けのバナー表示を設定します。', 'ktp-banner' ); ?></p>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'ktp_banner_group' );
				do_settings_sections( 'ktp-banner' );
				submit_button();
				?>
			</form>
			<h2><?php echo esc_html__( '利用方法', 'ktp-banner' ); ?></h2>
			<p><?php echo esc_html__( 'ショートコード: [ktp_banner]', 'ktp-banner' ); ?></p>
		</div>
		<?php
	}

	/**
	 * @param array $atts ショートコード属性
	 *
	 * @return string
	 */
	public function render_banner_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'class' => '',
			),
			$atts,
			'ktp_banner'
		);

		return $this->get_banner_html( sanitize_html_class( $atts['class'] ) );
	}

	/**
	 * KantanProショートコード出力の先頭にバナーを差し込む。
	 * 本番環境でKantanPro側修正が未適用でも表示できるようにする。
	 *
	 * @param string $output ショートコード出力
	 * @param string $tag ショートコードタグ
	 * @param array  $attr 属性
	 * @param array  $m マッチ情報
	 *
	 * @return string
	 */
	public function inject_banner_into_kantanpro_shortcode_output( $output, $tag, $attr, $m ) {
		$target_tags = array( 'ktpwp_all_tab', 'kantanAllTab' );
		if ( ! in_array( $tag, $target_tags, true ) ) {
			return $output;
		}

		// 既にKantanPro側または他経路でバナーが描画済みなら、差し込みしない
		if (
			false !== strpos( $output, 'ktp-before-header-banner' ) ||
			false !== strpos( $output, 'ktp-banner-hook' ) ||
			false !== strpos( $output, 'ktp-banner-fallback' ) ||
			false !== strpos( $output, 'ktp-banner-shortcode-inject' )
		) {
			return $output;
		}

		$banner_html = $this->get_banner_html( 'ktp-banner-shortcode-inject' );
		if ( '' === $banner_html ) {
			return $output;
		}

		$wrapped_banner = '<div class="ktp-before-header-banner" style="width:100%;max-width:100%;margin:0;text-align:center;box-sizing:border-box;">' . $banner_html . '</div>';
		return $wrapped_banner . $output;
	}

	/**
	 * KantanPro 管理画面でのみバナーを表示。
	 *
	 * @return void
	 */
	public function render_admin_banner_notice() {
		$options = $this->get_options();

		if ( empty( $options['display_admin'] ) ) {
			return;
		}

		if ( ! $this->is_kantanpro_admin_screen() ) {
			return;
		}

		$html = $this->get_banner_html( 'ktp-banner-admin-notice' );
		if ( '' === $html ) {
			return;
		}

		echo '<div class="notice notice-info is-dismissible"><p>' . wp_kses_post( $html ) . '</p></div>';
	}

	/**
	 * 設定済みフックにバナー描画を登録。
	 *
	 * @return void
	 */
	public function register_display_hook_from_settings() {
		$options = $this->get_options();
		$this->register_optional_hook( $options );
		$this->register_frontend_wordpress_hook( $options );
	}

	/**
	 * @return array
	 */
	private function get_options() {
		$defaults = array(
			'enabled'        => 1,
			'image_url'      => '',
			'link_url'       => '',
			'alt_text'       => '',
			'open_new_tab'   => 1,
			'display_admin'  => 1,
			'display_hook'   => 'ktpwp_between_pagination_footer',
			'frontend_hook'  => '',
		);

		$options = get_option( self::OPTION_KEY, array() );
		$options = wp_parse_args( $options, $defaults );

		return $options;
	}

	/**
	 * 任意フックが設定されている場合に add_action する。
	 *
	 * @param array $options 保存済みオプション
	 *
	 * @return void
	 */
	private function register_optional_hook( $options ) {
		static $registered_hooks = array();

		$hook_name = isset( $options['display_hook'] ) ? $options['display_hook'] : '';
		if ( '' === $hook_name ) {
			// 旧バージョンの保存データ互換: 空の場合はデフォルトフックを使う
			$hook_name = 'ktpwp_between_pagination_footer';
		}

		if ( isset( $registered_hooks[ $hook_name ] ) ) {
			return;
		}

		add_action(
			$hook_name,
			function () {
				echo wp_kses_post( $this->get_banner_html( 'ktp-banner-hook' ) );
			}
		);

		$registered_hooks[ $hook_name ] = true;
	}

	/**
	 * KantanPro 以外の通常テーマ向けに、WordPress 標準フックへバナーを登録する。
	 *
	 * @param array $options 保存済みオプション
	 *
	 * @return void
	 */
	private function register_frontend_wordpress_hook( $options ) {
		static $registered = false;
		if ( $registered ) {
			return;
		}

		$hook_name = isset( $options['frontend_hook'] ) ? $options['frontend_hook'] : '';
		if ( ! in_array( $hook_name, array( 'wp_footer', 'wp_body_open' ), true ) ) {
			return;
		}

		add_action(
			$hook_name,
			function () {
				if ( is_admin() ) {
					return;
				}
				echo wp_kses_post( $this->get_banner_html( 'ktp-banner-frontend' ) );
			},
			5
		);

		$registered = true;
	}

	/**
	 * @param string $extra_class 追加クラス
	 *
	 * @return string
	 */
	private function get_banner_html( $extra_class = '' ) {
		$options = $this->get_options();

		if ( empty( $options['enabled'] ) ) {
			return '';
		}

		$image_url = isset( $options['image_url'] ) ? esc_url( $options['image_url'] ) : '';
		if ( '' === $image_url ) {
			return '';
		}

		$link_url = isset( $options['link_url'] ) ? esc_url( $options['link_url'] ) : '';
		$alt_text = isset( $options['alt_text'] ) ? esc_attr( $options['alt_text'] ) : '';
		$target   = ! empty( $options['open_new_tab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';

		$class = 'ktp-banner';
		if ( '' !== $extra_class ) {
			$class .= ' ' . sanitize_html_class( $extra_class );
		}

		$image_tag = sprintf(
			'<img src="%1$s" alt="%2$s" style="width:100%%;max-width:100%%;height:auto;display:block;vertical-align:top;" />',
			$image_url,
			$alt_text
		);

		$wrap_style = 'width:100%;max-width:100%;box-sizing:border-box;';

		if ( '' !== $link_url ) {
			return sprintf(
				'<div class="%1$s" style="%2$s"><a href="%3$s"%4$s style="display:block;width:100%%;line-height:0;">%5$s</a></div>',
				esc_attr( $class ),
				esc_attr( $wrap_style ),
				$link_url,
				$target,
				$image_tag
			);
		}

		return sprintf(
			'<div class="%1$s" style="%2$s">%3$s</div>',
			esc_attr( $class ),
			esc_attr( $wrap_style ),
			$image_tag
		);
	}

	/**
	 * 現在の管理画面が KantanPro 系か判定。
	 *
	 * @return bool
	 */
	private function is_kantanpro_admin_screen() {
		if ( ! is_admin() ) {
			return false;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( '' === $page ) {
			return false;
		}

		return 0 === strpos( $page, 'kantanpro' );
	}
}

register_activation_hook( __FILE__, array( 'KTP_Banner_Plugin', 'activate' ) );
KTP_Banner_Plugin::instance();
