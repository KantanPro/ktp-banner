<?php
/**
 * Plugin Name: KTP Banner
 * Plugin URI: https://example.com
 * Description: KantanPro 向けに任意のバナー広告を表示するプラグインです。
 * Version: 1.3.9
 * Author: KantanPro
 * License: GPL-2.0-or-later
 * Text Domain: ktp-banner
 * Update URI: https://github.com/KantanPro/ktp-banner
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
		add_action( 'admin_init', array( $this, 'maybe_migrate_options' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_frontend_assets' ) );
		add_action( 'init', array( $this, 'register_display_hook_from_settings' ) );
		add_action( 'init', array( $this, 'register_banner_block' ) );
		add_action( 'widgets_init', array( $this, 'register_widget' ) );
		add_filter( 'widget_display_callback', array( $this, 'prepare_widget_assets' ), 10, 3 );
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
		if ( false === get_option( self::OPTION_KEY ) ) {
			add_option( self::OPTION_KEY, self::get_default_options() );
		}
	}

	/**
	 * デフォルトオプション。
	 *
	 * @return array
	 */
	private static function get_default_options() {
		return array(
			'enabled'            => 1,
			'display_admin'      => 1,
			'display_hook'       => 'ktpwp_between_pagination_footer',
			'frontend_hook'      => '',
			'rotation_interval'  => 5,
			'banners'            => array(
				array(
					'id'               => 'banner_default',
					'title'            => '',
					'image_url'        => '',
					'mobile_image_url' => '',
					'link_url'         => '',
					'alt_text'         => '',
					'open_new_tab'     => 1,
					'enabled'          => 1,
				),
			),
		);
	}

	/**
	 * 旧形式（単一バナー）から複数バナー形式へ移行する。
	 *
	 * @return void
	 */
	public function maybe_migrate_options() {
		$options = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $options ) ) {
			return;
		}

		if ( ! empty( $options['banners'] ) && is_array( $options['banners'] ) ) {
			return;
		}

		if ( empty( $options['image_url'] ) ) {
			$options['banners'] = array(
				array(
					'id'               => 'banner_default',
					'title'            => '',
					'image_url'        => '',
					'mobile_image_url' => '',
					'link_url'         => '',
					'alt_text'         => '',
					'open_new_tab'     => 1,
					'enabled'          => 1,
				),
			);
		} else {
			$options['banners'] = array(
				array(
					'id'               => 'banner_migrated',
					'title'            => '',
					'image_url'        => isset( $options['image_url'] ) ? $options['image_url'] : '',
					'mobile_image_url' => '',
					'link_url'         => isset( $options['link_url'] ) ? $options['link_url'] : '',
					'alt_text'         => isset( $options['alt_text'] ) ? $options['alt_text'] : '',
					'open_new_tab'     => ! empty( $options['open_new_tab'] ) ? 1 : 0,
					'enabled'          => 1,
				),
			);
		}

		if ( ! isset( $options['rotation_interval'] ) ) {
			$options['rotation_interval'] = 5;
		}

		update_option( self::OPTION_KEY, $options );
	}

	/**
	 * @return void
	 */
	public function register_admin_menu() {
		add_menu_page(
			__( 'KTP Banner 設定', 'ktp-banner' ),
			__( 'KTP Banner', 'ktp-banner' ),
			'manage_options',
			'ktp-banner',
			array( $this, 'render_settings_page' ),
			'dashicons-format-image',
			58
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
			'enabled'           => __( '有効化', 'ktp-banner' ),
			'display_admin'     => __( 'KantanPro管理画面で表示', 'ktp-banner' ),
			'frontend_hook'     => __( 'サイト全体の表示位置（KantanProなし向け）', 'ktp-banner' ),
			'display_hook'      => __( '追加表示フック名（任意）', 'ktp-banner' ),
			'rotation_interval' => __( 'ローテーション間隔（秒）', 'ktp-banner' ),
			'banners'           => __( 'バナー一覧', 'ktp-banner' ),
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
		if ( 'ktp-banner' !== $page && 'toplevel_page_ktp-banner' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'ktp-banner-admin',
			plugins_url( 'js/ktp-banner-admin.js', __FILE__ ),
			array( 'jquery', 'media-editor', 'media-views' ),
			'1.3.0',
			true
		);
		wp_localize_script(
			'ktp-banner-admin',
			'ktpBannerAdmin',
			array(
				'title'             => __( 'バナー画像を選択', 'ktp-banner' ),
				'mobile_title'      => __( 'スマホ用バナー画像を選択', 'ktp-banner' ),
				'button_text'       => __( 'この画像を使用', 'ktp-banner' ),
				'media_error'       => __( 'メディアライブラリの読み込みに失敗しました。ページを再読み込みしてください。', 'ktp-banner' ),
				'item_label'        => __( 'バナー', 'ktp-banner' ),
				'remove_last_error' => __( '最低1件のバナーが必要です。', 'ktp-banner' ),
			)
		);
	}

	/**
	 * 複数バナーのローテーション用アセットを必要時のみ読み込む。
	 *
	 * @return void
	 */
	public function maybe_enqueue_frontend_assets() {
		if ( is_admin() || ! $this->should_enqueue_banner_frontend_assets() ) {
			return;
		}

		if ( $this->should_load_rotation_assets() ) {
			$this->enqueue_rotation_assets_once();
			return;
		}

		$this->enqueue_banner_frontend_css_once();
	}

	/**
	 * フロントエンドでバナー用アセットを読み込む必要があるか。
	 *
	 * @return bool
	 */
	private function should_enqueue_banner_frontend_assets() {
		$options = $this->get_options();
		if ( empty( $options['enabled'] ) || empty( $this->get_active_banners( $options ) ) ) {
			return false;
		}

		if ( is_active_widget( false, false, 'ktp_banner_widget', true ) || $this->is_ktp_banner_widget_placed() ) {
			return true;
		}

		if ( $this->should_load_rotation_assets() ) {
			return true;
		}

		if ( $this->has_mobile_banner_images( $options ) ) {
			return true;
		}

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				if ( function_exists( 'has_block' ) && has_block( 'ktp-banner/banner', $post ) ) {
					return true;
				}
				if ( has_shortcode( $post->post_content, 'ktp_banner' ) ) {
					return true;
				}
			}
		}

		$hook = isset( $options['frontend_hook'] ) ? $options['frontend_hook'] : '';
		return in_array( $hook, array( 'wp_footer', 'wp_body_open' ), true );
	}

	/**
	 * スマホ用画像が1件以上設定されているか。
	 *
	 * @param array|null $options オプション
	 *
	 * @return bool
	 */
	private function has_mobile_banner_images( $options = null ) {
		if ( null === $options ) {
			$options = $this->get_options();
		}

		foreach ( $this->get_active_banners( $options ) as $banner ) {
			if ( ! empty( $banner['mobile_image_url'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * KTP Banner ウィジェットがどこかのサイドバーに配置されているか。
	 *
	 * @return bool
	 */
	private function is_ktp_banner_widget_placed() {
		$sidebars_widgets = wp_get_sidebars_widgets();
		if ( ! is_array( $sidebars_widgets ) ) {
			return false;
		}

		foreach ( $sidebars_widgets as $widgets ) {
			if ( ! is_array( $widgets ) ) {
				continue;
			}

			foreach ( $widgets as $widget_id ) {
				if ( ! is_string( $widget_id ) ) {
					continue;
				}

				if ( 0 === strpos( $widget_id, 'ktp_banner_widget-' ) ) {
					return true;
				}

				if ( 0 === strpos( $widget_id, 'block-' ) && $this->is_ktp_banner_in_block_widget( $widget_id ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * ブロックウィジェット内に KTP Banner レガシーウィジェットが含まれるか。
	 *
	 * @param string $widget_id ウィジェット ID（block-*）
	 *
	 * @return bool
	 */
	private function is_ktp_banner_in_block_widget( $widget_id ) {
		if ( ! preg_match( '/^block-(\d+)$/', $widget_id, $matches ) ) {
			return false;
		}

		$block_widgets = get_option( 'widget_block', array() );
		if ( ! is_array( $block_widgets ) || empty( $block_widgets[ $matches[1] ]['content'] ) ) {
			return false;
		}

		$content = (string) $block_widgets[ $matches[1] ]['content'];
		if ( false !== strpos( $content, 'wp:legacy-widget' ) && false !== strpos( $content, 'ktp_banner_widget' ) ) {
			return true;
		}

		if ( ! function_exists( 'parse_blocks' ) ) {
			return false;
		}

		foreach ( parse_blocks( $content ) as $block ) {
			if ( $this->legacy_widget_block_is_ktp_banner( $block ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * legacy-widget ブロックが KTP Banner か判定する（ネスト対応）。
	 *
	 * @param array $block ブロック配列
	 *
	 * @return bool
	 */
	private function legacy_widget_block_is_ktp_banner( $block ) {
		if ( ! is_array( $block ) ) {
			return false;
		}

		if ( 'core/legacy-widget' === ( $block['blockName'] ?? '' ) ) {
			$id_base = $block['attrs']['idBase'] ?? '';
			if ( 'ktp_banner_widget' === $id_base ) {
				return true;
			}
		}

		if ( empty( $block['innerBlocks'] ) || ! is_array( $block['innerBlocks'] ) ) {
			return false;
		}

		foreach ( $block['innerBlocks'] as $inner_block ) {
			if ( $this->legacy_widget_block_is_ktp_banner( $inner_block ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * ウィジェット描画直前に CSS/JS を読み込む。
	 *
	 * @param array     $instance ウィジェット設定
	 * @param WP_Widget $widget   ウィジェット
	 * @param array     $args     表示引数
	 *
	 * @return array
	 */
	public function prepare_widget_assets( $instance, $widget, $args ) {
		unset( $args );

		if ( $widget instanceof KTP_Banner_Widget || ( is_object( $widget ) && ! empty( $widget->id_base ) && 'ktp_banner_widget' === $widget->id_base ) ) {
			$this->ensure_widget_assets();
		}

		return $instance;
	}

	/**
	 * ウィジェット表示に必要なフロントエンドアセットを読み込む。
	 *
	 * @return void
	 */
	public function ensure_widget_assets() {
		if ( is_admin() ) {
			return;
		}

		if ( $this->should_load_rotation_assets() ) {
			$this->enqueue_rotation_assets_once();
		} else {
			$this->enqueue_banner_frontend_css_once();
		}

		$this->schedule_footer_banner_styles();
	}

	/**
	 * ウィジェット描画後に CSS が未出力なら wp_footer で出力する。
	 *
	 * @return void
	 */
	private function schedule_footer_banner_styles() {
		static $scheduled = false;
		if ( $scheduled ) {
			return;
		}

		$scheduled = true;
		add_action( 'wp_footer', array( $this, 'print_pending_banner_styles' ), 1 );
	}

	/**
	 * wp_head 以降に enqueue されたバナー CSS をフッターで出力する。
	 *
	 * @return void
	 */
	public function print_pending_banner_styles() {
		if ( ! wp_style_is( 'ktp-banner-frontend', 'enqueued' ) || wp_style_is( 'ktp-banner-frontend', 'done' ) ) {
			return;
		}

		wp_print_styles( array( 'ktp-banner-frontend' ) );
	}

	/**
	 * ローテーション用アセットの読み込みが必要か。
	 *
	 * @return bool
	 */
	private function should_load_rotation_assets() {
		$options = $this->get_options();
		if ( empty( $options['enabled'] ) ) {
			return false;
		}

		return count( $this->get_active_banners( $options ) ) > 1;
	}

	/**
	 * @param array $input 入力値
	 *
	 * @return array
	 */
	public function sanitize_options( $input ) {
		if ( ! is_array( $input ) ) {
			return self::get_default_options();
		}

		$frontend_raw = isset( $input['frontend_hook'] ) ? $input['frontend_hook'] : '';
		$frontend_ok  = in_array( $frontend_raw, array( 'wp_footer', 'wp_body_open' ), true ) ? $frontend_raw : '';

		$rotation_interval = isset( $input['rotation_interval'] ) ? absint( $input['rotation_interval'] ) : 5;
		$rotation_interval = max( 2, min( 60, $rotation_interval ) );

		$output = array(
			'enabled'           => empty( $input['enabled'] ) ? 0 : 1,
			'display_admin'     => empty( $input['display_admin'] ) ? 0 : 1,
			'frontend_hook'     => $frontend_ok,
			'display_hook'      => empty( $input['display_hook'] ) ? 'ktpwp_between_pagination_footer' : sanitize_key( $input['display_hook'] ),
			'rotation_interval' => $rotation_interval,
			'banners'           => array(),
		);

		$banners_input = isset( $input['banners'] ) && is_array( $input['banners'] ) ? $input['banners'] : array();
		foreach ( $banners_input as $banner_input ) {
			if ( ! is_array( $banner_input ) ) {
				continue;
			}

			$banner = $this->sanitize_banner_item( $banner_input );
			if ( '' === $banner['image_url'] ) {
				continue;
			}

			$output['banners'][] = $banner;
		}

		if ( empty( $output['banners'] ) ) {
			$output['banners'][] = array(
				'id'               => 'banner_' . wp_generate_password( 8, false, false ),
				'title'            => '',
				'image_url'        => '',
				'mobile_image_url' => '',
				'link_url'         => '',
				'alt_text'         => '',
				'open_new_tab'     => 1,
				'enabled'          => 1,
			);
		}

		return $output;
	}

	/**
	 * 単一バナー設定をサニタイズする。
	 *
	 * @param array $banner_input 入力値
	 *
	 * @return array
	 */
	private function sanitize_banner_item( $banner_input ) {
		$id = isset( $banner_input['id'] ) ? sanitize_key( $banner_input['id'] ) : '';
		if ( '' === $id ) {
			$id = 'banner_' . wp_generate_password( 8, false, false );
		}

		return array(
			'id'               => $id,
			'title'            => isset( $banner_input['title'] ) ? sanitize_text_field( $banner_input['title'] ) : '',
			'image_url'        => empty( $banner_input['image_url'] ) ? '' : esc_url_raw( $banner_input['image_url'] ),
			'mobile_image_url' => empty( $banner_input['mobile_image_url'] ) ? '' : esc_url_raw( $banner_input['mobile_image_url'] ),
			'link_url'         => empty( $banner_input['link_url'] ) ? '' : esc_url_raw( $banner_input['link_url'] ),
			'alt_text'         => empty( $banner_input['alt_text'] ) ? '' : sanitize_text_field( $banner_input['alt_text'] ),
			'open_new_tab'     => empty( $banner_input['open_new_tab'] ) ? 0 : 1,
			'enabled'          => empty( $banner_input['enabled'] ) ? 0 : 1,
		);
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
			case 'display_admin':
				printf(
					'<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
					esc_attr( $name_attr ),
					checked( 1, (int) $value, false ),
					esc_html__( '有効', 'ktp-banner' )
				);
				break;
			case 'rotation_interval':
				printf(
					'<input type="number" class="small-text" min="2" max="60" step="1" name="%1$s" value="%2$d" /> %3$s',
					esc_attr( $name_attr ),
					max( 2, min( 60, (int) $value ) ),
					esc_html__( '秒', 'ktp-banner' )
				);
				echo '<p class="description">' . esc_html__( '複数バナー登録時、指定秒数ごとに切り替えて表示します（2〜60秒）。', 'ktp-banner' ) . '</p>';
				break;
			case 'banners':
				$this->render_banners_field( $options );
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
	 * 複数バナー入力 UI。
	 *
	 * @param array $options 保存済みオプション
	 *
	 * @return void
	 */
	private function render_banners_field( $options ) {
		$banners = isset( $options['banners'] ) && is_array( $options['banners'] ) ? $options['banners'] : array();
		if ( empty( $banners ) ) {
			$banners = array(
				array(
					'id'               => 'banner_default',
					'title'            => '',
					'image_url'        => '',
					'mobile_image_url' => '',
					'link_url'         => '',
					'alt_text'         => '',
					'open_new_tab'     => 1,
					'enabled'          => 1,
				),
			);
		}
		?>
		<div id="ktp-banner-items">
			<?php foreach ( $banners as $index => $banner ) : ?>
				<?php $this->render_banner_item_field( (int) $index, $banner ); ?>
			<?php endforeach; ?>
		</div>
		<p>
			<button type="button" class="button" id="ktp-banner-add-item"><?php echo esc_html__( 'バナーを追加', 'ktp-banner' ); ?></button>
		</p>
		<p class="description"><?php echo esc_html__( '複数登録すると、表示時に自動でローテーションします。画像URLが空の行は保存されません。', 'ktp-banner' ); ?></p>
		<script type="text/html" id="ktp-banner-item-template">
			<?php
			$this->render_banner_item_field(
				'{{INDEX}}',
				array(
					'id'               => '{{ID}}',
					'title'            => '',
					'image_url'        => '',
					'mobile_image_url' => '',
					'link_url'         => '',
					'alt_text'         => '',
					'open_new_tab'     => 1,
					'enabled'          => 1,
				)
			);
			?>
		</script>
		<?php
	}

	/**
	 * 単一バナー行 UI。
	 *
	 * @param int|string $index  インデックス
	 * @param array      $banner バナー設定
	 *
	 * @return void
	 */
	private function render_banner_item_field( $index, $banner ) {
		$defaults = array(
			'id'               => 'banner_' . wp_generate_password( 8, false, false ),
			'title'            => '',
			'image_url'        => '',
			'mobile_image_url' => '',
			'link_url'         => '',
			'alt_text'         => '',
			'open_new_tab'     => 1,
			'enabled'          => 1,
		);
		$banner   = wp_parse_args( $banner, $defaults );
		$label    = is_numeric( $index ) ? ( (int) $index + 1 ) : '{{INDEX}}';
		?>
		<div class="ktp-banner-item" data-index="<?php echo esc_attr( (string) $index ); ?>" style="border:1px solid #ccd0d4;background:#fff;padding:16px;margin:0 0 12px;max-width:860px;">
			<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
				<strong class="ktp-banner-item-title"><?php echo esc_html( sprintf( __( 'バナー #%s', 'ktp-banner' ), $label ) ); ?></strong>
				<button type="button" class="button-link-delete ktp-banner-remove-item"><?php echo esc_html__( '削除', 'ktp-banner' ); ?></button>
			</div>
			<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY . '[banners][' . $index . '][id]' ); ?>" value="<?php echo esc_attr( $banner['id'] ); ?>" />
			<p>
				<label><?php echo esc_html__( '管理用タイトル（任意）', 'ktp-banner' ); ?></label><br />
				<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY . '[banners][' . $index . '][title]' ); ?>" value="<?php echo esc_attr( $banner['title'] ); ?>" />
			</p>
			<p>
				<label><?php echo esc_html__( '画像URL（PC・タブレット）', 'ktp-banner' ); ?></label><br />
				<input type="url" class="regular-text ktp-banner-image-url" name="<?php echo esc_attr( self::OPTION_KEY . '[banners][' . $index . '][image_url]' ); ?>" value="<?php echo esc_attr( $banner['image_url'] ); ?>" placeholder="https://example.com/" />
				<button type="button" class="button ktp-banner-select-image" data-image-type="desktop"><?php echo esc_html__( '画像を選択', 'ktp-banner' ); ?></button>
				<button type="button" class="button ktp-banner-clear-image" data-image-type="desktop"><?php echo esc_html__( 'クリア', 'ktp-banner' ); ?></button>
			</p>
			<div style="margin:0 0 12px;">
				<?php if ( '' !== $banner['image_url'] ) : ?>
					<img class="ktp-banner-image-preview" src="<?php echo esc_url( $banner['image_url'] ); ?>" alt="" style="max-width:300px;height:auto;border:1px solid #ddd;padding:4px;background:#fff;" />
				<?php else : ?>
					<img class="ktp-banner-image-preview" src="" alt="" style="display:none;max-width:300px;height:auto;border:1px solid #ddd;padding:4px;background:#fff;" />
				<?php endif; ?>
			</div>
			<p>
				<label><?php echo esc_html__( 'スマホ用画像URL（任意）', 'ktp-banner' ); ?></label><br />
				<input type="url" class="regular-text ktp-banner-mobile-image-url" name="<?php echo esc_attr( self::OPTION_KEY . '[banners][' . $index . '][mobile_image_url]' ); ?>" value="<?php echo esc_attr( $banner['mobile_image_url'] ); ?>" placeholder="https://example.com/" />
				<button type="button" class="button ktp-banner-select-image" data-image-type="mobile"><?php echo esc_html__( '画像を選択', 'ktp-banner' ); ?></button>
				<button type="button" class="button ktp-banner-clear-image" data-image-type="mobile"><?php echo esc_html__( 'クリア', 'ktp-banner' ); ?></button>
			</p>
			<div style="margin:0 0 12px;">
				<?php if ( '' !== $banner['mobile_image_url'] ) : ?>
					<img class="ktp-banner-mobile-image-preview" src="<?php echo esc_url( $banner['mobile_image_url'] ); ?>" alt="" style="max-width:300px;height:auto;border:1px solid #ddd;padding:4px;background:#fff;" />
				<?php else : ?>
					<img class="ktp-banner-mobile-image-preview" src="" alt="" style="display:none;max-width:300px;height:auto;border:1px solid #ddd;padding:4px;background:#fff;" />
				<?php endif; ?>
				<span class="description" style="display:block;margin-top:6px;"><?php echo esc_html__( '未設定の場合はPC用画像をスマホでも表示します。', 'ktp-banner' ); ?></span>
			</div>
			<p>
				<label><?php echo esc_html__( 'リンクURL', 'ktp-banner' ); ?></label><br />
				<input type="url" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY . '[banners][' . $index . '][link_url]' ); ?>" value="<?php echo esc_attr( $banner['link_url'] ); ?>" placeholder="https://example.com/" />
			</p>
			<p>
				<label><?php echo esc_html__( '代替テキスト', 'ktp-banner' ); ?></label><br />
				<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY . '[banners][' . $index . '][alt_text]' ); ?>" value="<?php echo esc_attr( $banner['alt_text'] ); ?>" />
			</p>
			<p>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY . '[banners][' . $index . '][open_new_tab]' ); ?>" value="1" <?php checked( 1, (int) $banner['open_new_tab'] ); ?> />
					<?php echo esc_html__( '新しいタブで開く', 'ktp-banner' ); ?>
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY . '[banners][' . $index . '][enabled]' ); ?>" value="1" <?php checked( 1, (int) $banner['enabled'] ); ?> />
					<?php echo esc_html__( 'このバナーを有効', 'ktp-banner' ); ?>
				</label>
			</p>
		</div>
		<?php
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
			<p><?php echo esc_html__( 'KantanPro 画面向けのバナー表示を設定します。複数バナーを登録するとローテーション表示されます。', 'ktp-banner' ); ?></p>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'ktp_banner_group' );
				do_settings_sections( 'ktp-banner' );
				submit_button();
				?>
			</form>
			<h2><?php echo esc_html__( '利用方法', 'ktp-banner' ); ?></h2>
			<p><?php echo esc_html__( 'ブロックエディター: 「+」→「KTP Banner」ブロックを本文の任意位置に追加', 'ktp-banner' ); ?></p>
			<p><?php echo esc_html__( 'ショートコード: [ktp_banner]', 'ktp-banner' ); ?></p>
			<p><?php echo esc_html__( 'ウィジェット: 外観 > ウィジェット から「KTP Banner」を追加', 'ktp-banner' ); ?></p>
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

		$extra_class = 'ktp-banner-shortcode';
		if ( '' !== $atts['class'] ) {
			$extra_class .= ' ' . sanitize_html_class( $atts['class'] );
		}

		return $this->get_banner_markup( $extra_class, true );
	}

	/**
	 * ウィジェット等の明示配置向けにバナー HTML を返す。
	 *
	 * @param string $extra_class 追加クラス
	 * @param bool   $allow_kantanproex KantanProEX 環境でも表示を許可するか
	 *
	 * @return string
	 */
	public function get_banner_markup( $extra_class = '', $allow_kantanproex = false, $enable_rotation = true ) {
		return $this->get_banner_html( $extra_class, $allow_kantanproex, $enable_rotation );
	}

	/**
	 * KTP Banner ウィジェットを登録する。
	 *
	 * @return void
	 */
	public function register_widget() {
		register_widget( 'KTP_Banner_Widget' );
	}

	/**
	 * Gutenberg ブロックを登録する。
	 *
	 * @return void
	 */
	public function register_banner_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'ktp-banner-block-editor',
			plugins_url( 'blocks/ktp-banner/index.js', __FILE__ ),
			array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-i18n',
				'wp-server-side-render',
			),
			'1.2.1',
			true
		);

		wp_register_style(
			'ktp-banner-block-editor',
			plugins_url( 'blocks/ktp-banner/editor.css', __FILE__ ),
			array(),
			'1.2.1'
		);

		register_block_type(
			plugin_dir_path( __FILE__ ) . 'blocks/ktp-banner/block.json',
			array(
				'editor_script'   => 'ktp-banner-block-editor',
				'editor_style'    => 'ktp-banner-block-editor',
				'render_callback' => array( $this, 'render_banner_block' ),
			)
		);
	}

	/**
	 * Gutenberg ブロックのフロントエンド出力。
	 *
	 * @param array    $attributes ブロック属性
	 * @param string   $content    ブロックコンテンツ
	 * @param WP_Block $block      ブロックインスタンス
	 *
	 * @return string
	 */
	public function render_banner_block( $attributes, $content, $block ) {
		unset( $content, $block );

		$html = $this->get_banner_markup( 'ktp-banner-block', true );
		if ( '' === $html ) {
			return '';
		}

		return sprintf(
			'<div %1$s>%2$s</div>',
			get_block_wrapper_attributes(
				array(
					'class' => 'ktp-banner-block-wrap',
				)
			),
			$html
		);
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
		if ( $this->is_kantanproex_active() ) {
			return $output;
		}

		$target_tags = array( 'ktpwp_all_tab', 'kantanAllTab', 'kantanpro_ex' );
		if ( ! in_array( $tag, $target_tags, true ) ) {
			return $output;
		}

		// 既にKantanPro側または他経路でバナーが描画済みなら、差し込みしない
		if (
			false !== strpos( $output, 'ktp-before-header-banner' ) ||
			false !== strpos( $output, 'ktp-banner-hook' ) ||
			false !== strpos( $output, 'ktp-banner-fallback' ) ||
			false !== strpos( $output, 'ktp-banner-shortcode-inject' ) ||
			false !== strpos( $output, 'ktp-banner-rotator' )
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

		$html = $this->get_banner_html( 'ktp-banner-admin-notice', false, false );
		if ( '' === $html ) {
			return;
		}

		echo '<div class="notice notice-info is-dismissible"><p>' . $html . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses_banner_html 済み
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
		$defaults = self::get_default_options();
		$options  = get_option( self::OPTION_KEY, array() );
		$options  = wp_parse_args( $options, $defaults );

		if ( empty( $options['banners'] ) || ! is_array( $options['banners'] ) ) {
			$options['banners'] = $defaults['banners'];
		}

		return $options;
	}

	/**
	 * 表示対象の有効バナー一覧を返す。
	 *
	 * @param array|null $options オプション（省略時は取得）
	 *
	 * @return array
	 */
	private function get_active_banners( $options = null ) {
		if ( null === $options ) {
			$options = $this->get_options();
		}

		$banners = isset( $options['banners'] ) && is_array( $options['banners'] ) ? $options['banners'] : array();
		$active  = array();

		foreach ( $banners as $banner ) {
			if ( ! is_array( $banner ) ) {
				continue;
			}

			$banner = wp_parse_args(
				$banner,
				array(
					'enabled'          => 1,
					'image_url'        => '',
					'mobile_image_url' => '',
				)
			);

			if ( empty( $banner['enabled'] ) ) {
				continue;
			}

			$image_url = isset( $banner['image_url'] ) ? esc_url( $banner['image_url'] ) : '';
			if ( '' === $image_url ) {
				continue;
			}

			$active[] = $banner;
		}

		return $active;
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
				echo $this->get_banner_html( 'ktp-banner-hook' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses_banner_html 済み
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
				echo $this->get_banner_html( 'ktp-banner-frontend' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses_banner_html 済み
			},
			5
		);

		$registered = true;
	}

	/**
	 * @param string $extra_class 追加クラス
	 * @param bool   $allow_kantanproex KantanProEX環境でも明示表示を許可するか
	 *
	 * @return string
	 */
	private function get_banner_html( $extra_class = '', $allow_kantanproex = false, $enable_rotation = true ) {
		if ( ! $allow_kantanproex && $this->is_kantanproex_active() ) {
			return '';
		}

		$options = $this->get_options();

		if ( empty( $options['enabled'] ) ) {
			return '';
		}

		$banners = $this->get_active_banners( $options );
		if ( empty( $banners ) ) {
			return '';
		}

		$this->enqueue_banner_frontend_css_once();

		if ( ! $enable_rotation ) {
			$banner = $banners[ array_rand( $banners ) ];
			return $this->wrap_banner_html(
				$this->build_banner_item_html( $banner, $extra_class ),
				$options
			);
		}

		if ( 1 === count( $banners ) ) {
			return $this->wrap_banner_html(
				$this->build_banner_item_html( $banners[0], $extra_class ),
				$options
			);
		}

		$rotation_interval = isset( $options['rotation_interval'] ) ? absint( $options['rotation_interval'] ) : 5;
		$rotation_interval = max( 2, min( 60, $rotation_interval ) );

		$class = 'ktp-banner-rotator';
		if ( '' !== $extra_class ) {
			$class .= ' ' . sanitize_html_class( $extra_class );
		}

		$html = sprintf(
			'<div class="%1$s" data-interval="%2$d">',
			esc_attr( $class ),
			$rotation_interval
		);

		foreach ( $banners as $index => $banner ) {
			$item_class = 'ktp-banner-rotator-item';
			if ( 0 === $index ) {
				$item_class .= ' is-active';
			}
			$html .= $this->build_banner_item_html( $banner, 'ktp-banner', $item_class );
		}

		$html .= '</div>';

		$this->enqueue_rotation_assets_once();

		return $this->wrap_banner_html( $html, $options );
	}

	/**
	 * バナー HTML をラップし、必要ならレスポンシブ用インライン CSS を付与する。
	 *
	 * @param string $html    バナー HTML
	 * @param array  $options プラグイン設定
	 *
	 * @return string
	 */
	private function wrap_banner_html( $html, $options ) {
		if ( '' === $html ) {
			return '';
		}

		$sanitized = $this->kses_banner_html( $html );
		if ( '' === $sanitized ) {
			return '';
		}

		if ( ! $this->has_mobile_banner_images( $options ) ) {
			return $sanitized;
		}

		return $this->get_responsive_style_markup() . $sanitized;
	}

	/**
	 * スマホ用画像切替 CSS（外部ファイルとインラインで共通利用）。
	 *
	 * @return string
	 */
	private function get_responsive_banner_css() {
		return '.ktp-banner-media--desktop{display:block;width:100%;max-width:100%}.ktp-banner-media--mobile{display:none;width:100%;max-width:100%}@media (max-width:767px){.ktp-banner-media--desktop{display:none!important}.ktp-banner-media--mobile{display:block!important}}';
	}

	/**
	 * レスポンシブ切替用インライン style を1ページ1回だけ返す。
	 *
	 * @return string
	 */
	private function get_responsive_style_markup() {
		static $printed = false;
		if ( $printed ) {
			return '';
		}

		$printed = true;

		return sprintf(
			'<style id="ktp-banner-responsive-css">%s</style>',
			$this->get_responsive_banner_css()
		);
	}

	/**
	 * バナー HTML をサニタイズする（picture / source を許可）。
	 *
	 * @param string $html バナー HTML
	 *
	 * @return string
	 */
	private function kses_banner_html( $html ) {
		if ( '' === $html ) {
			return '';
		}

		$allowed = wp_kses_allowed_html( 'post' );
		$allowed['picture'] = array();
		$allowed['source']  = array(
			'media'  => true,
			'srcset' => true,
			'sizes'  => true,
			'type'   => true,
		);
		$allowed['span']    = array(
			'class' => true,
			'style' => true,
		);

		if ( ! isset( $allowed['img'] ) || ! is_array( $allowed['img'] ) ) {
			$allowed['img'] = array();
		}
		$allowed['img']['style']    = true;
		$allowed['img']['src']      = true;
		$allowed['img']['alt']      = true;
		$allowed['img']['loading']  = true;
		$allowed['img']['decoding'] = true;

		if ( ! isset( $allowed['a'] ) || ! is_array( $allowed['a'] ) ) {
			$allowed['a'] = array();
		}
		$allowed['a']['href']   = true;
		$allowed['a']['target'] = true;
		$allowed['a']['rel']    = true;
		$allowed['a']['style']  = true;

		if ( ! isset( $allowed['div'] ) || ! is_array( $allowed['div'] ) ) {
			$allowed['div'] = array();
		}
		$allowed['div']['class']        = true;
		$allowed['div']['style']        = true;
		$allowed['div']['data-interval'] = true;

		return wp_kses( $html, $allowed );
	}

	/**
	 * スペース区切りの HTML クラス文字列をサニタイズする。
	 *
	 * @param string $classes クラス文字列
	 *
	 * @return string
	 */
	private function sanitize_html_classes( $classes ) {
		$parts     = preg_split( '/\s+/', trim( (string) $classes ) );
		$sanitized = array();

		foreach ( $parts as $part ) {
			if ( '' === $part ) {
				continue;
			}

			$class = sanitize_html_class( $part );
			if ( '' !== $class ) {
				$sanitized[] = $class;
			}
		}

		return implode( ' ', array_unique( $sanitized ) );
	}

	/**
	 * バナー表示用 CSS を1リクエストにつき1回だけ読み込む。
	 *
	 * @return void
	 */
	private function enqueue_banner_frontend_css_once() {
		static $enqueued = false;
		if ( $enqueued || is_admin() ) {
			return;
		}

		$enqueued = true;

		wp_enqueue_style(
			'ktp-banner-frontend',
			plugins_url( 'css/ktp-banner-frontend.css', __FILE__ ),
			array(),
			'1.3.7'
		);

		if ( $this->has_mobile_banner_images() ) {
			wp_add_inline_style( 'ktp-banner-frontend', $this->get_responsive_banner_css() );
		}
	}

	/**
	 * ローテーション用 CSS/JS を1リクエストにつき1回だけ読み込む。
	 *
	 * @return void
	 */
	private function enqueue_rotation_assets_once() {
		static $enqueued = false;
		if ( $enqueued || is_admin() ) {
			return;
		}

		$enqueued = true;

		$this->enqueue_banner_frontend_css_once();

		wp_enqueue_script(
			'ktp-banner-frontend',
			plugins_url( 'js/ktp-banner-frontend.js', __FILE__ ),
			array(),
			'1.3.7',
			true
		);
	}

	/**
	 * バナー画像 HTML を生成する（スマホ用画像があれば CSS で切り替え）。
	 *
	 * @param array $banner バナー設定
	 *
	 * @return string
	 */
	private function build_banner_image_markup( $banner ) {
		$image_url        = isset( $banner['image_url'] ) ? esc_url( $banner['image_url'] ) : '';
		$mobile_image_url = isset( $banner['mobile_image_url'] ) ? esc_url( $banner['mobile_image_url'] ) : '';
		$alt_text         = isset( $banner['alt_text'] ) ? esc_attr( $banner['alt_text'] ) : '';
		$img_style        = 'width:100%;max-width:100%;height:auto;display:block;vertical-align:top;';

		if ( '' !== $mobile_image_url && $mobile_image_url !== $image_url ) {
			return sprintf(
				'<span class="ktp-banner-media ktp-banner-media--desktop">%1$s</span><span class="ktp-banner-media ktp-banner-media--mobile">%2$s</span>',
				$this->build_single_image_tag( $image_url, $alt_text, $img_style ),
				$this->build_single_image_tag( $mobile_image_url, $alt_text, $img_style )
			);
		}

		return $this->build_single_image_tag( $image_url, $alt_text, $img_style );
	}

	/**
	 * img タグ1枚分の HTML。
	 *
	 * @param string $image_url  画像URL
	 * @param string $alt_text   代替テキスト
	 * @param string $img_style  インラインスタイル
	 *
	 * @return string
	 */
	private function build_single_image_tag( $image_url, $alt_text, $img_style ) {
		return sprintf(
			'<img src="%1$s" alt="%2$s" style="%3$s" loading="lazy" decoding="async" />',
			$image_url,
			$alt_text,
			esc_attr( $img_style )
		);
	}

	/**
	 * 単一バナーの HTML を生成する。
	 *
	 * @param array  $banner      バナー設定
	 * @param string $extra_class 追加クラス
	 * @param string $wrap_class  ラッパークラス
	 *
	 * @return string
	 */
	private function build_banner_item_html( $banner, $extra_class = '', $wrap_class = '' ) {
		$image_url = isset( $banner['image_url'] ) ? esc_url( $banner['image_url'] ) : '';
		if ( '' === $image_url ) {
			return '';
		}

		$link_url = isset( $banner['link_url'] ) ? esc_url( $banner['link_url'] ) : '';
		$target   = ! empty( $banner['open_new_tab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';

		$class = 'ktp-banner';
		if ( '' !== $extra_class ) {
			$class .= ' ' . sanitize_html_class( $extra_class );
		}
		if ( '' !== $wrap_class ) {
			$class .= ' ' . $this->sanitize_html_classes( $wrap_class );
		}

		$image_tag = $this->build_banner_image_markup( $banner );

		$wrap_style = 'width:100%;max-width:100%;box-sizing:border-box;';

		if ( '' !== $link_url ) {
			return sprintf(
				'<div class="%1$s" style="%2$s"><a href="%3$s"%4$s style="display:block;width:100%%;">%5$s</a></div>',
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
	 * KantanProEX（WP）が有効な場合のみ true を返す。
	 * KTPWP_EDITION の値には依存せず、プラグイン実体で判定する。
	 *
	 * @return bool
	 */
	private function is_kantanproex_active() {
		if ( defined( 'KANTANPRO_PLUGIN_NAME' ) && 'KantanProEX' === KANTANPRO_PLUGIN_NAME ) {
			return true;
		}

		if ( defined( 'KTPWP_EDITION' ) && 'pro' === KTPWP_EDITION && defined( 'KANTANPRO_PLUGIN_NAME' ) && 'KantanProEX' === KANTANPRO_PLUGIN_NAME ) {
			return true;
		}

		$possible_ex_basenames = array(
			'KantanProEX/ktpwp.php',
			'KantanProEx/ktpwp.php',
			'kantanproex/ktpwp.php',
			'kantanpro-ex/ktpwp.php',
			'KantanPro-EX/ktpwp.php',
		);

		foreach ( $possible_ex_basenames as $basename ) {
			if ( $this->is_plugin_active_by_basename( $basename ) ) {
				return true;
			}
		}

		foreach ( $this->get_active_plugin_basenames() as $active_plugin ) {
			$plugin_file = basename( $active_plugin );
			$plugin_dir  = dirname( $active_plugin );
			$normalized_dir = strtolower( str_replace( array( '-', '_' ), '', $plugin_dir ) );

			if ( 'ktpwp.php' === $plugin_file && 'kantanproex' === $normalized_dir ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * 有効化済みプラグインのベースネーム一覧を取得（マルチサイト対応）。
	 *
	 * @return array
	 */
	private function get_active_plugin_basenames() {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$network_active_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
			$active_plugins = array_merge( $active_plugins, array_keys( $network_active_plugins ) );
		}

		return array_values( array_unique( array_filter( $active_plugins ) ) );
	}

	/**
	 * 指定プラグインが有効化済みか判定（マルチサイト対応）。
	 *
	 * @param string $plugin_basename プラグインベース名
	 *
	 * @return bool
	 */
	private function is_plugin_active_by_basename( $plugin_basename ) {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		if ( in_array( $plugin_basename, $active_plugins, true ) ) {
			return true;
		}

		if ( is_multisite() ) {
			$network_active_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
			if ( isset( $network_active_plugins[ $plugin_basename ] ) ) {
				return true;
			}
		}

		return false;
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

		// KantanPro のメニュースラッグは ktp-settings / ktp-dummy-data 等（kantanpro ではない）
		return (
			0 === strpos( $page, 'kantanpro' )
			|| 0 === strpos( $page, 'ktp-' )
			|| 0 === strpos( $page, 'ktpwp' )
		);
	}
}

register_activation_hook( __FILE__, array( 'KTP_Banner_Plugin', 'activate' ) );
KTP_Banner_Plugin::instance();

/**
 * KTP Banner ウィジェット。
 */
class KTP_Banner_Widget extends WP_Widget {
	/**
	 * KTP_Banner_Widget constructor.
	 */
	public function __construct() {
		parent::__construct(
			'ktp_banner_widget',
			__( 'KTP Banner', 'ktp-banner' ),
			array(
				'description' => __( '設定画面で登録したバナー広告を表示します。スマホ用画像・ローテーションに対応。', 'ktp-banner' ),
				'classname'   => 'widget_ktp_banner',
			)
		);
	}

	/**
	 * フロントエンド表示。
	 *
	 * @param array $args     ウィジェットエリア引数
	 * @param array $instance ウィジェット設定
	 *
	 * @return void
	 */
	public function widget( $args, $instance ) {
		KTP_Banner_Plugin::instance()->ensure_widget_assets();

		$html = KTP_Banner_Plugin::instance()->get_banner_markup( 'ktp-banner-widget', true );
		if ( '' === $html ) {
			return;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses_banner_html 済み
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * 管理画面フォーム。
	 *
	 * @param array $instance ウィジェット設定
	 *
	 * @return void
	 */
	public function form( $instance ) {
		echo '<p>' . esc_html__( 'バナー内容は管理画面の「KTP Banner」メニューで管理します。スマホ用画像・ローテーションに対応しています。', 'ktp-banner' ) . '</p>';
	}

	/**
	 * 設定保存。
	 *
	 * @param array $new_instance 新しい設定
	 * @param array $old_instance 旧設定
	 *
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		return $old_instance;
	}
}

final class KTP_Banner_Update_Checker {
	const GITHUB_REPO        = 'KantanPro/ktp-banner';
	const CACHE_KEY          = 'ktp_banner_github_latest_release';
	const CACHE_BACKUP_KEY   = 'ktp_banner_github_latest_release_backup';
	const CACHE_TTL          = 900; // 15 minutes.

	/**
	 * @var string
	 */
	private $plugin_file;

	/**
	 * @var string
	 */
	private $plugin_basename;

	/**
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * @param string $plugin_file メインプラグインファイル
	 */
	public function __construct( $plugin_file ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_basename = plugin_basename( $plugin_file );
		$this->plugin_slug     = dirname( $this->plugin_basename );

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'filter_update_transient' ) );
		add_filter( 'site_transient_update_plugins', array( $this, 'filter_update_transient' ) );
		add_filter( 'update_plugins_github.com', array( $this, 'filter_update_plugins_github_com' ), 10, 4 );
		add_filter( 'plugins_api', array( $this, 'filter_plugins_api' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'rename_github_source' ), 10, 4 );
		add_filter( 'upgrader_pre_download', array( $this, 'upgrader_pre_download' ), 10, 3 );
		add_filter( 'auto_update_plugin', array( $this, 'filter_auto_update_plugin' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_release_cache' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'maybe_force_update_check' ) );
		add_action( 'load-plugins.php', array( $this, 'maybe_refresh_on_plugins_screen' ) );
		add_action( 'load-update-core.php', array( $this, 'maybe_refresh_on_update_core_screen' ) );
		add_action( 'in_plugin_update_message-' . $this->plugin_basename, array( $this, 'render_update_message' ), 10, 2 );
	}

	/**
	 * 更新チェックを実行すべきコンテキストか。
	 *
	 * @return bool
	 */
	private function should_check_updates() {
		return is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON );
	}

	/**
	 * WordPress標準のプラグイン更新通知に GitHub Release を接続する。
	 *
	 * @param object|false $transient 更新 transient
	 * @return object
	 */
	public function filter_update_transient( $transient ) {
		if ( ! $this->should_check_updates() ) {
			return $transient;
		}

		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}

		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = array();
		}

		if ( ! isset( $transient->checked ) || ! is_array( $transient->checked ) ) {
			$transient->checked = array();
		}

		$current_version = $this->detect_current_version();
		$transient->checked[ $this->plugin_basename ] = $current_version;

		$release = $this->get_latest_release();
		if ( ! $release || empty( $release['version'] ) || empty( $release['package'] ) ) {
			return $transient;
		}

		if ( version_compare( $release['version'], $current_version, '<=' ) ) {
			unset( $transient->response[ $this->plugin_basename ] );
			$transient->no_update[ $this->plugin_basename ] = $this->build_update_object( $release, false );
			return $transient;
		}

		$transient->response[ $this->plugin_basename ] = $this->build_update_object( $release, true );
		unset( $transient->no_update[ $this->plugin_basename ] );

		return $transient;
	}

	/**
	 * Update URI ヘッダー向け（update_plugins_github.com）の更新情報。
	 *
	 * @param array|false $update      既存更新情報
	 * @param array       $plugin_data プラグインヘッダー
	 * @param string      $plugin_file プラグインファイル
	 * @param string[]    $locales     ロケール
	 *
	 * @return array|false
	 */
	public function filter_update_plugins_github_com( $update, $plugin_data, $plugin_file, $locales ) {
		unset( $locales );

		if ( $plugin_file !== $this->plugin_basename ) {
			return $update;
		}

		$release = $this->get_latest_release();
		if ( ! $release || empty( $release['version'] ) ) {
			return $update;
		}

		$current_version = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : $this->detect_current_version();
		if ( version_compare( $release['version'], $current_version, '<=' ) ) {
			return $update;
		}

		return array(
			'slug'         => $this->plugin_slug,
			'version'      => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'requires'     => '6.0',
			'tested'       => '6.8',
			'requires_php' => '7.4',
		);
	}

	/**
	 * 「更新を確認」クリック時に GitHub Release キャッシュを破棄する。
	 *
	 * @return void
	 */
	public function maybe_force_update_check() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		if ( empty( $_GET['force-check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		delete_site_transient( self::CACHE_KEY );
		delete_site_transient( self::CACHE_BACKUP_KEY );
	}

	/**
	 * プラグイン一覧表示時に GitHub Release を再確認する。
	 *
	 * @return void
	 */
	public function maybe_refresh_on_plugins_screen() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		delete_site_transient( self::CACHE_KEY );
		$this->refresh_update_transient();
	}

	/**
	 * 更新画面表示時に GitHub Release を再確認する。
	 *
	 * @return void
	 */
	public function maybe_refresh_on_update_core_screen() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		delete_site_transient( self::CACHE_KEY );
		delete_site_transient( self::CACHE_BACKUP_KEY );
		$this->refresh_update_transient();
	}

	/**
	 * 更新 transient を再構築する。
	 *
	 * @return void
	 */
	private function refresh_update_transient() {
		$transient = get_site_transient( 'update_plugins' );
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		$transient = $this->filter_update_transient( $transient );
		set_site_transient( 'update_plugins', $transient );
	}

	/**
	 * GitHub ダウンロード向け HTTP 引数を調整する。
	 *
	 * @param mixed  $reply   既存応答
	 * @param string $package パッケージ URL
	 * @param object $upgrader アップグレーダー
	 *
	 * @return mixed
	 */
	public function upgrader_pre_download( $reply, $package, $upgrader ) {
		unset( $upgrader );

		if ( false !== strpos( $package, 'github.com' ) ) {
			add_filter( 'http_request_args', array( $this, 'filter_github_download_args' ), 10, 2 );
		}

		return $reply;
	}

	/**
	 * GitHub 向け HTTP リクエスト引数。
	 *
	 * @param array  $args リクエスト引数
	 * @param string $url  URL
	 *
	 * @return array
	 */
	public function filter_github_download_args( $args, $url ) {
		if ( false === strpos( $url, 'github.com' ) ) {
			return $args;
		}

		$args['timeout'] = 60;
		$args['headers'] = array_merge(
			isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array(),
			$this->get_github_api_headers()
		);

		return $args;
	}

	/**
	 * WordPress 標準の自動更新判定に GitHub Release 更新を反映する。
	 *
	 * @param bool   $should_update 自動更新するか
	 * @param object $plugin        プラグイン情報
	 *
	 * @return bool
	 */
	public function filter_auto_update_plugin( $should_update, $plugin ) {
		if ( ! is_object( $plugin ) || empty( $plugin->plugin ) || $plugin->plugin !== $this->plugin_basename ) {
			return $should_update;
		}

		$auto_update_plugins = (array) get_site_option( 'auto_update_plugins', array() );
		if ( in_array( $this->plugin_basename, $auto_update_plugins, true ) ) {
			return true;
		}

		return $should_update;
	}

	/**
	 * プラグイン一覧の更新メッセージを表示する。
	 *
	 * @param array  $plugin_data プラグインデータ
	 * @param object $response    更新情報
	 *
	 * @return void
	 */
	public function render_update_message( $plugin_data, $response ) {
		unset( $plugin_data, $response );

		$release = $this->get_latest_release();
		if ( ! $release || empty( $release['url'] ) ) {
			return;
		}

		printf(
			' <a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( $release['url'] ),
			esc_html__( 'GitHub Release の変更内容を見る', 'ktp-banner' )
		);
	}

	/**
	 * 「詳細を表示」モーダルへ GitHub Release の情報を返す。
	 *
	 * @param false|object|array $result 既存結果
	 * @param string             $action APIアクション
	 * @param object             $args   API引数
	 * @return false|object|array
	 */
	public function filter_plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		$info = new stdClass();
		$info->name          = 'KTP Banner';
		$info->slug          = $this->plugin_slug;
		$info->version       = $release['version'];
		$info->author        = 'KantanPro';
		$info->homepage      = 'https://github.com/' . self::GITHUB_REPO;
		$info->download_link = $release['package'];
		$info->trunk         = $release['package'];
		$info->requires      = '6.0';
		$info->tested        = '6.8';
		$info->requires_php  = '7.4';
		$info->last_updated  = $release['published_at'];
		$info->sections      = array(
			'description' => 'KantanPro 向けに任意のバナー広告を表示するプラグインです。',
			'changelog'   => nl2br( esc_html( $release['body'] ) ),
		);

		return $info;
	}

	/**
	 * 更新完了時に GitHub Release キャッシュを削除する。
	 *
	 * @param object $upgrader_object アップグレーダー
	 * @param array  $options         更新オプション
	 * @return void
	 */
	public function clear_release_cache( $upgrader_object, $options ) {
		unset( $upgrader_object );

		if ( empty( $options['action'] ) || empty( $options['type'] ) || 'update' !== $options['action'] || 'plugin' !== $options['type'] ) {
			return;
		}

		if ( ! empty( $options['plugins'] ) && in_array( $this->plugin_basename, (array) $options['plugins'], true ) ) {
			delete_site_transient( self::CACHE_KEY );
			delete_site_transient( self::CACHE_BACKUP_KEY );
		}
	}

	/**
	 * GitHub zipball の展開フォルダをプラグインslugへ補正する。
	 *
	 * @param string      $source        展開元ディレクトリ
	 * @param string      $remote_source 一時ディレクトリ
	 * @param WP_Upgrader $upgrader      アップグレーダー
	 * @param array       $hook_extra    更新情報
	 * @return string|WP_Error
	 */
	public function rename_github_source( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		unset( $upgrader );

		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
			return $source;
		}

		if ( basename( untrailingslashit( $source ) ) === $this->plugin_slug ) {
			return $source;
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			return $source;
		}

		$destination = trailingslashit( $remote_source ) . $this->plugin_slug;
		if ( $wp_filesystem->exists( $destination ) ) {
			$wp_filesystem->delete( $destination, true );
		}

		if ( ! $wp_filesystem->move( $source, $destination, true ) ) {
			return new WP_Error( 'ktp_banner_github_source_rename_failed', __( 'KTP Banner の更新フォルダ名を補正できませんでした。', 'ktp-banner' ) );
		}

		return $destination;
	}

	/**
	 * 更新通知用オブジェクトを生成する。
	 *
	 * @param array $release     Release情報
	 * @param bool  $has_package ダウンロード URL を含めるか
	 *
	 * @return object
	 */
	private function build_update_object( $release, $has_package = true ) {
		$update               = new stdClass();
		$update->id           = 'github.com/' . self::GITHUB_REPO;
		$update->slug         = $this->plugin_slug;
		$update->plugin       = $this->plugin_basename;
		$update->new_version  = $release['version'];
		$update->url          = $release['url'];
		$update->package      = $has_package ? $release['package'] : '';
		$update->requires     = '6.0';
		$update->tested       = '6.8';
		$update->requires_php = '7.4';
		$update->last_updated = isset( $release['published_at'] ) ? $release['published_at'] : '';

		return $update;
	}

	/**
	 * GitHub API リクエストヘッダー。
	 *
	 * @return array
	 */
	private function get_github_api_headers() {
		$headers = array(
			'Accept'        => 'application/vnd.github+json',
			'User-Agent'    => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) . '; KTP-Banner-Updater',
			'Cache-Control' => 'no-cache',
		);

		$token = $this->get_github_token();
		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		return $headers;
	}

	/**
	 * GitHub Personal Access Token を取得する。
	 *
	 * @return string
	 */
	private function get_github_token() {
		if ( defined( 'KTP_BANNER_GITHUB_TOKEN' ) && KTP_BANNER_GITHUB_TOKEN ) {
			return (string) KTP_BANNER_GITHUB_TOKEN;
		}

		if ( defined( 'KP_GITHUB_TOKEN' ) && KP_GITHUB_TOKEN ) {
			return (string) KP_GITHUB_TOKEN;
		}

		return '';
	}

	/**
	 * GitHub Releases API から最新Releaseを取得する。
	 *
	 * @param bool $force_refresh キャッシュを無視するか
	 *
	 * @return array|null
	 */
	private function get_latest_release( $force_refresh = false ) {
		if ( ! $force_refresh ) {
			$cached = get_site_transient( self::CACHE_KEY );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$data = $this->fetch_github_json( 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest' );

		if ( ( ! is_array( $data ) || empty( $data['tag_name'] ) ) ) {
			$list = $this->fetch_github_json( 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases' );
			if ( is_array( $list ) ) {
				foreach ( $list as $release_item ) {
					if ( ! is_array( $release_item ) || ! empty( $release_item['draft'] ) || ! empty( $release_item['prerelease'] ) ) {
						continue;
					}
					if ( ! empty( $release_item['tag_name'] ) ) {
						$data = $release_item;
						break;
					}
				}
			}
		}

		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			$backup = get_site_transient( self::CACHE_BACKUP_KEY );
			return is_array( $backup ) ? $backup : null;
		}

		$release = $this->normalize_release_payload( $data );
		if ( empty( $release['package'] ) ) {
			$backup = get_site_transient( self::CACHE_BACKUP_KEY );
			return is_array( $backup ) ? $backup : null;
		}

		set_site_transient( self::CACHE_KEY, $release, self::CACHE_TTL );
		set_site_transient( self::CACHE_BACKUP_KEY, $release, DAY_IN_SECONDS );

		return $release;
	}

	/**
	 * GitHub API JSON を取得する。
	 *
	 * @param string $url API URL
	 *
	 * @return array|null
	 */
	private function fetch_github_json( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => $this->get_github_api_headers(),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * GitHub Release API レスポンスを正規化する。
	 *
	 * @param array $data API レスポンス
	 *
	 * @return array
	 */
	private function normalize_release_payload( $data ) {
		$download_url = ! empty( $data['zipball_url'] ) ? esc_url_raw( $data['zipball_url'] ) : '';

		if ( ! empty( $data['assets'] ) && is_array( $data['assets'] ) ) {
			foreach ( $data['assets'] as $asset ) {
				if ( empty( $asset['browser_download_url'] ) || ! preg_match( '/\.zip$/i', $asset['browser_download_url'] ) ) {
					continue;
				}
				if ( ! empty( $asset['name'] ) && false !== stripos( $asset['name'], $this->plugin_slug ) ) {
					$download_url = esc_url_raw( $asset['browser_download_url'] );
					break;
				}
				$download_url = esc_url_raw( $asset['browser_download_url'] );
			}
		}

		return array(
			'version'      => ltrim( sanitize_text_field( $data['tag_name'] ), 'v' ),
			'url'          => isset( $data['html_url'] ) ? esc_url_raw( $data['html_url'] ) : 'https://github.com/' . self::GITHUB_REPO,
			'package'      => $download_url,
			'body'         => isset( $data['body'] ) ? wp_kses_post( $data['body'] ) : '',
			'published_at' => isset( $data['published_at'] ) ? sanitize_text_field( $data['published_at'] ) : '',
		);
	}

	/**
	 * プラグインヘッダーから現在バージョンを取得する。
	 *
	 * @return string
	 */
	private function detect_current_version() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_data = get_plugin_data( $this->plugin_file, false, false );

		return ! empty( $plugin_data['Version'] ) ? $plugin_data['Version'] : '0.0.0';
	}
}

add_action(
	'plugins_loaded',
	static function () {
		if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			new KTP_Banner_Update_Checker( __FILE__ );
		}
	},
	1
);
