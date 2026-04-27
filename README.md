# KTP Banner

KantanPro 向けに任意のバナー広告を表示する WordPress プラグインです。

## プラグイン情報

- プラグイン名: KTP Banner
- バージョン: 1.0.9
- 最終更新日: 2026-04-28
- 対象: WordPress + KantanPro

## 機能

- 管理画面でバナー画像 URL / リンク URL / 代替テキストを設定
- 画像URLはメディアライブラリから選択可能（アップロード / ギャラリー選択）
- KantanPro 管理画面への表示（通知表示）
- KantanPro ショートコード出力へのバナー差し込みフォールバック
- ショートコード `[ktp_banner]` で任意の場所に表示
- 任意のフック名を設定して `do_action( 'your_hook' )` に差し込み可能
- KantanPro がないサイトでは **サイト全体の表示位置**（`wp_footer` / `wp_body_open`）で自動表示可能

## セットアップ

1. WordPress 管理画面で `KTP Banner` を有効化
2. `設定 > KTP Banner` を開く
3. 画像 URL などを保存

## 表示方法

### 1) KantanProの画面に表示

- `display_hook` 設定によるフック表示
- フック未登録時は `ktpwp_all_tab` / `kantanAllTab` / `kantanpro_ex` 出力へ自動フォールバック差し込み（KantanProEX では非表示）

### 2) KantanPro 管理画面に通知として自動表示

- `KantanPro管理画面で表示` をオンにすると、`page=kantanpro...` の画面で通知エリアに表示されます。

### 3) ショートコード

```text
[ktp_banner]
```

任意クラスを付ける場合:

```text
[ktp_banner class="my-banner"]
```

### 4) 任意フックに表示

設定画面の `追加表示フック名（任意）` に、例: `kantanpro_after_header` を設定。
その後、KantanPro 側で下記が実行されると表示されます。

```php
do_action( 'kantanpro_after_header' );
```

## 更新履歴

### 1.0.9 (2026-04-28)

- KantanProEX の有効化状態を判定する機能を追加
- KantanProEX の有効化判定を複数のベースネームで行うように修正

### 1.0.8 (2026-04-27)

- KantanProEX（`KTPWP_EDITION` が `pro`）が有効なときはバナーを一切表示しない（フック・`[ktp_banner]`・管理画面通知・ショートコード出力への差し込みを無効化）
- 差し込み対象に `kantanpro_ex` を追加（無料版向け。EX では非表示）

### 1.0.7 (2026-04-08)

- KantanPro 管理画面のメニュースラッグ判定条件を修正

### 1.0.6 (2026-04-08)

- KantanPro 側の表示位置変更に追従し、メニュースラッグ判定を `ktp-` / `ktpwp` 系にも対応

### 1.0.5 (2026-04-08)

- KantanPro がない設置先向けに `wp_footer` / `wp_body_open` 表示オプションを追加

### 1.0.4 (2026-03-28)

- README.md のプラグイン情報（バージョン・更新日・更新履歴）を readme.txt と整合

### 1.0.3 (2026-03-28)

- バナー画像を親幅いっぱい（フルワイド）表示に変更

### 1.0.2 (2026-03-27)

- KantanPro ショートコード差し込み時の重複表示判定を強化
- ヘッダー上表示・フック表示・フォールバック表示がある場合の再挿入を抑止

### 1.0.1 (2026-03-27)

- KantanPro 側の表示位置調整に対応し、ヘッダー上表示経路を強化
- フック未登録時のフォールバック表示を追加
- メディアライブラリ選択機能と画像プレビューの安定性を改善
- バナー余白と画像サイズ（50%表示）を調整

### 1.0.0 (初回リリース)

- KTP Bannerプラグインの初期実装を追加
