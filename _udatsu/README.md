# Udatsu 公開講座 — 運営：ニストスタジオ

公開予定URL：**https://udatsuageteko.com**

## 現在の状態（2026-09-20）

- 公開講座8種類と個別相談のサイトを作成済み。
- 公開講座は60分・4,400円。1人目の決済成功で開催テーマが確定し、定員まで相乗り参加。
- 個別相談は60分・4,400円・1対1。
- 自分で募集枠を登録。Googleカレンダーと照合する処理を実装。
- Xサーバーで使うPHP版を作成し、予約・決済のロジック15件とブラウザ操作を検証済み。
- **実際のStripe決済、Google接続、メール、本番公開は未完了。**
- 既存Stripe本番キーは、読み取り確認で `401 / api_key_expired`。新しいテストキーが必要。
- ドメインの現在のAレコードは `34.111.141.225`。STUDIO側からHTTP 404を返す。
- ネームサーバーは `01.dnsv.jp` 〜 `04.dnsv.jp`（お名前.com系）。Xサーバーにファイルを置くだけでは表示先が切り替わらない。
- 既存のUdatsuコードが参照するドメインは `udatsu-voyager.com`。新しい公開先と異なるため、既存GitHub ActionsのFTP設定をそのまま流用していない。

## 作業場所

`Nayuta_Brain/.worktrees/udatsu-public-classes`

Gitブランチ：`codex/udatsu-public-classes`。開始コミット：`d0ad0d6`。

元の `01_Projects/Udatsu/Udatsu_Voyager_Project/Web_Backend/public_html` には未コミット変更があったため、別のGit worktreeで作業している。元の作業ファイルを移動・削除・上書きしていない。

最初に作ったNode.js版は `Nayuta_Brain/.worktrees/nyctstudio-public-workshops/workshops` に残してある。**今回のXサーバー公開には、このPHP版を使う。** Node.jsサーバーや新しいホスティング契約は本番で不要。

## 本体の構成

| ファイル | 役割 |
|---|---|
| ルート `index.php` | Udatsuの入口。新しいアプリを読み込む |
| ルート `.htaccess` | Udatsuの画面とAPIのURLを振り分ける |
| `_udatsu/public/` | 講座一覧、申込、管理画面、デザイン、講師写真 |
| `_udatsu/catalog.json` | 講座名、説明、成果、料金、定員 |
| `_udatsu/app.php` | 画面配信、予約API、管理API、Stripe通知 |
| `_udatsu/lib.php` | 予約データ、競合・定員管理、Stripe・Google・メール連携 |
| `_udatsu/cron.php` | 決済の再照合と、確認メール・Google登録の再試行 |
| `_udatsu/private/` | 設定・予約データ。Git除外・Web非公開 |
| `_udatsu/test.php` | 支払い・相乗り・重複通知などのテスト |
| `_udatsu/tools/deploy.py` | 接続先照合・バックアップ付きの限定FTPS公開 |

PHP 8.2以降、PDO_SQLite、cURL、mbstringを使用。ComposerやWordPressは不要。

## まず画面を確認

今回のMacでのPHP体験版：**http://127.0.0.1:8793**

管理画面は `/admin`。体験版は請求・Google書き込み・メール送信をしない。日程と検証予約はサンプル。

PHPがインストール済みの環境では：

```sh
UDATSU_BASE_URL=http://127.0.0.1:8793 php -S 127.0.0.1:8793 _udatsu/local-router.php
```

このMacではPHPがなかったため、WordPress Playgroundの `@php-wasm/cli` を使って検証した。本番にそのランタイムをアップロードする必要はない。

## Xサーバーで確認すること

1. [XServerアカウント](https://secure.xserver.ne.jp/xapanel/login/xserver/) → 対象サーバーの「サーバー管理」。
2. 「ドメイン設定」に `udatsuageteko.com` があるか確認する。なければ追加する。**既存ドメインの削除はしない。**
3. 「サーバー情報」でサーバー番号とIPアドレスを確認する。
4. 現在のDNS管理先（お名前.com）で、このドメインのWeb用AレコードをXサーバーのIPへ変更する。メール用MXや認証用TXTなどはこのサイトの変更対象ではない。
5. DNS反映後、Xサーバーで無料独自SSLを設定・確認する。
6. このドメインの `public_html` に対するFTP接続先を設定して、ファイルを反映する。

参考：[Xサーバーのドメイン設定](https://www.xserver.ne.jp/manual/man_domain_setting.php)、[お名前.com DNS設定](https://help.onamae.com/answer/14353)。

### FTPSでの公開

`deploy.example.env` を `private/deploy.env` にコピーし、FTPホスト・ユーザー・パスワード・**udatsuageteko.com用のpublic_htmlのパス**を入力する。チャットにパスワードを貼らない。

```sh
python3 _udatsu/tools/deploy.py --check
python3 _udatsu/tools/deploy.py --deploy
```

`--check` は接続先を読むだけ。`--deploy` は次の順で作業する。

1. 設定したFTPルートに一時的な照合ファイルを置き、`udatsuageteko.com` から同じ内容を読めるか確認する。
2. ドメインと公開先が一致しなければ、既存サイトを変更せず停止する。一時ファイルは削除する。
3. Webから読めないことを確認した `_udatsu_backups/日時/` に、上書き対象の既存ファイルを保存する。
4. 今回のアプリと入口だけを反映する。既存の写真、マイページ、データ、他のPHPファイルを削除しない。
5. 公開後に講座一覧API・トップ・非公開領域を確認する。失敗した場合は元々あったファイルを復元する。

GitHub Actionsもこの作業ブランチでは手動実行に変更済み。mainへのpushだけで旧ドメインへ自動公開しない。接続先確認にはリポジトリ変数 `UDATSU_FTP_ROOT` が必要。リモートへのpushやActions実行はまだ行っていない。

初回は **preview（案内のみ・予約受付前）** で公開する。販売者情報や接続設定がない状態で料金を受け取らない。

`tools/deploy.py` は構文確認済みだが、実際のFTPS接続・バックアップ・反映・復元はまだ未実行。接続情報とDNS切り替え待ち。

## Stripe設定

今回ユーザーに案内した入力先は、引き続きこちら：

`Nayuta_Brain/.worktrees/nyctstudio-public-workshops/workshops/.env`

ローカルPHP版の `private/.env` はそこへのリンクなので、案内済みファイルの編集がPHP版にも反映される。このリンク自体や秘密の設定を公開ZIPに含めない。

1. [Stripeテスト用APIキー](https://dashboard.stripe.com/test/apikeys)で `sk_test_` を取得する。
2. `STRIPE_SECRET_KEY=` の右側に入れて保存する。チャットには貼らない。
3. StripeのテストWebhookを `/api/stripe/webhook` に接続する。本番では `https://udatsuageteko.com/api/stripe/webhook`。
4. 新しいエンドポイントの署名キーを `STRIPE_WEBHOOK_SECRET` に入れる。古いUdatsuのWebhook署名キーは流用しない。
5. 購読イベントは `checkout.session.completed`、`checkout.session.expired`、`checkout.session.async_payment_succeeded`。

Checkoutはサーバー側の金額で作成する。決済成功は署名・金額・通貨・予約ID・テスト／本番モードを照合する。戻りURLを開いただけでは支払済みにしない。

最初の支払い中は枠を仮押さえする。Stripeで期限切れが確認できた場合だけ解放する。通信失敗で決済状態が不明な場合は保持し、管理画面に再確認を表示する。

## Googleカレンダー設定

CodexからGoogle Calendarを見る接続と、予約サイトが自動照合する接続は別。

- Google CloudでCalendar APIを有効化し、OAuthクライアントを作る。
- 認可のリダイレクト先は `http://127.0.0.1:8792/callback`。
- `GOOGLE_CLIENT_ID`、`GOOGLE_CLIENT_SECRET` を非公開設定に入れる。
- `_udatsu` フォルダで `node --env-file=private/.env tools/connect-google.mjs` を実行し、自分のGoogleアカウントで認可する。
- `GOOGLE_BUSY_CALENDAR_IDS` に照合対象を指定する。複数はカンマ区切り。
- 講座の書き込み先として専用カレンダーを作り、`GOOGLE_BOOKING_CALENDAR_ID` に指定する。

カレンダーを一般公開する必要はない。Googleの確認に失敗した場合は予約を受け付けない。開催確定済みの講座は自身の予定で隠れないようにする。確定後の別予定との衝突は自動キャンセルせず、講師が参加者と調整する。

## 確認メール・定期処理

標準はXサーバーのPHPメール送信（`MAIL_TRANSPORT=php`）。`MAIL_FROM` に送信元メールアドレス、`SUPPORT_EMAIL` に問い合わせ先を設定し、実際の受信まで確認する。希望する場合だけ `resend` に切り替えられる。

Xサーバーの「Cron設定」で、`_udatsu/cron.php` を毎分実行する。PHPコマンドと絶対パスは「サーバー情報」に表示されたものに合わせる。設定・実行確認後に `CRON_ENABLED=true` とする。

決済済み予約の確認メールとカレンダー登録は永続ジョブで再試行する。管理画面でも再試行できる。PHPメールは送信処理の受付までしか判定できないため、実際の到達と迷惑メール振り分けを確認する。

## 本番受付開始前の残り

- 有効なStripeキーで実際のテスト決済・Webhookを確認。
- 実際のGoogleカレンダーで予定除外と予約書き込みを確認。
- 定員（現在の仮設定6人）、募集する日時、会場・参加URLを確定。
- 販売事業者の名称・住所・連絡先、キャンセル条件、個人情報の取り扱いを確定。
- メールとCronの動作を確認。
- `settings.example.env` を元に本番の `private/.env` を用意し、`APP_MODE=live` と `LIVE_BOOKING_ENABLED=true` を設定する。

現時点の返金・キャンセルは手動運用。Stripeダッシュボードで返金しても、自動で座席を再販しない。申込者のいる枠は管理画面から削除できない。会員機能、レビュー掲載、Meet自動発行、自動返金、キャンセル通知の同期は未実装。

## 検証結果

```sh
php _udatsu/test.php
```

15件：仮押さえ、相乗り、定員、個別相談、期限終了、金額等の不一致、Webhook再送、時間重複、重複メール申込、署名改ざん、個人情報非公開、Stripe通信失敗、再照合、体験環境の外部書き込み防止、価格・冪等キー。

ブラウザでは8講座表示、カテゴリ絞り込み、フォーム、体験決済、相乗り公開、390px画面、個別相談60分4,400円、管理画面認証、JavaScriptエラーなしを確認。

APIの自動テストは外部サービスを模したもの。StripeやGoogleの実接続が完了したことを意味しない。スクリーンショットは `artifacts/` に保存。

## 内容の根拠

既存の事業定義、Udatsu事業計画、「先生という存在の定義」「手を動かす研修の意義」「講師のやりがい」などの思考資料と、既存プロフィールを参照。すべての思考資料を精読したわけではない。過去の料金や別事業の構想より、この会話で指定された「60分」「2,500円／4,400円」「最初の決済で開催」「相乗り」「Udatsu／ニスト運営」を優先。
