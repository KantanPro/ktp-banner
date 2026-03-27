# KTP Banner

KantanPro 向けに任意のバナー広告を表示する WordPress プラグインです。

## 機能

- 管理画面でバナー画像 URL / リンク URL / 代替テキストを設定
- 画像URLはメディアライブラリから選択可能（アップロード / ギャラリー選択）
- KantanPro の管理画面（`page=kantanpro...`）で自動表示
- ショートコード `[ktp_banner]` で任意の場所に表示
- 任意のフック名を設定して `do_action( 'your_hook' )` に差し込み可能

## セットアップ

1. WordPress 管理画面で `KTP Banner` を有効化
2. `設定 > KTP Banner` を開く
3. 画像 URL などを保存

## 表示方法

### 1) ページネーションとフッターの間に表示（デフォルト）

- デフォルトで `ktpwp_between_pagination_footer` フックに接続されます。
- KantanPro の一覧画面では、ページネーションの直後・フッター直前にバナーが表示されます。

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
