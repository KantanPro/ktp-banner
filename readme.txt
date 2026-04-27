=== KTP Banner ===
Contributors: kantanpro
Tags: banner, ads, kantanpro
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.10
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
* KantanProがないサイト向けに wp_footer / wp_body_open への任意表示
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
* 「有効化」にチェックが入っている
* 画像URLが保存されている（https の絶対URL推奨）
* KantanPro がない、またはショートコードを置いていないサイトでは、従来の「追加表示フック」だけでは表示されません。設定の「サイト全体の表示位置」で wp_footer を選ぶか、固定ページ等に [ktp_banner] を配置してください。
* キャッシュを削除して再読み込みしている

== Changelog ==

= 1.0.10 (2026-04-28) =

* KantanProEX 判定を `KTPWP_EDITION` 依存から切り離し、EX プラグイン実体の有効化状態のみで判定するよう修正
* KantanPro（WordPress版）が `pro` 扱いでもバナー表示できるように改善

= 1.0.9 (2026-04-28) =

* KantanProEX の有効化状態を判定する機能を追加
* KantanProEX の有効化判定を複数のベースネームで行うように修正

= 1.0.8 (2026-04-27) =

* KantanProEX（`KTPWP_EDITION` が `pro`）が有効な環境では、バナーを表示しない（ショートコード・フック・管理画面通知・KantanPro ショートコード出力への差し込みをすべて無効化）
* 差し込み対象ショートコードに `kantanpro_ex` を追加（無料版 KantanPro で同タグを利用する場合のフォールバック表示向け。EX では上記により非表示）

= 1.0.7 (2026-04-08) =

* KantanPro管理画面のメニュースラッグ判定条件を修正

= 1.0.6 (2026-04-08) =

* KantanPro側の表示位置変更に追従し、メニュースラッグ判定を `ktp-` / `ktpwp` 系にも対応

= 1.0.5 (2026-04-08) =

* KantanPro がない外部サイト向けに、WordPress 標準フック（wp_footer / wp_body_open）への表示オプションを追加

= 1.0.4 (2026-03-28) =

* README.md のプラグイン情報（バージョン・更新日・更新履歴）を readme.txt と整合

= 1.0.3 (2026-03-28) =

* バナー画像を親幅いっぱい（フルワイド）表示に変更

= 1.0.2 (2026-03-27) =

* KantanProショートコード差し込み時の重複表示判定を強化
* 既存のバナー描画経路（ヘッダー上・フック・フォールバック）検知時は再挿入を抑止

= 1.0.1 (2026-03-27) =

* KantanPro表示位置変更に対応し、ヘッダー上表示経路を強化
* フック未登録時のフォールバック表示を追加
* メディアライブラリ選択の安定性を改善
* バナー余白と画像サイズ（50%表示）を調整

= 1.0.0 =

* KTP Bannerプラグインの初期実装を追加
