=== KTP Banner ===
Contributors: kantanpro
Tags: banner, ads, kantanpro
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

KantanPro 向けに任意のバナー広告を表示するプラグインです。

== Description ==

KTP Banner は、KantanPro 画面にバナー広告を表示するためのプラグインです。
設定画面で画像URL、リンクURL、表示オプションを管理できます。

主な機能:

* バナー画像URL・リンクURL・代替テキストの設定
* メディアライブラリからの画像選択（アップロード/既存画像）
* KantanPro向けフック表示
* フック未登録時のショートコード出力フォールバック表示
* 管理画面通知表示

== Installation ==

1. プラグインを `wp-content/plugins/ktp-banner` に配置します。
2. WordPress管理画面の「プラグイン」から有効化します。
3. 「設定 > KTP Banner」でバナー内容を保存します。

== Frequently Asked Questions ==

= バナーが表示されません =

以下を確認してください。

* KTP Banner が有効化されている
* 画像URLが保存されている
* キャッシュを削除して再読み込みしている

== Changelog ==

= 1.0.1 (2026-03-27) =

* KantanPro表示位置変更に対応し、ヘッダー上表示経路を強化
* フック未登録時のフォールバック表示を追加
* メディアライブラリ選択の安定性を改善
* バナー余白と画像サイズ（50%表示）を調整

= 1.0.0 =

* KTP Bannerプラグインの初期実装を追加
