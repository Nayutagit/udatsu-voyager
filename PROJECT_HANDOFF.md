# Udatsu 再開メモ

[[やっていること]] に戻る。全AI共通の方針は `PROJECT_RULES.md`。

## 2026-09-20の確認済み状態
- PHP版の実装はコミット `cd23a18`。ブランチ `codex/udatsu-public-classes`。15件の模擬テストとブラウザ検証が合格。実決済・本番運用の成功を意味しない。
- 本人は今のサービスを維持したい。Codexの利用量を節約し、Claude Code / Antigravityとの分担を希望。共通必読と各AI入口を追加した。
- お名前.comのAレコードは183.181.82.18へ変更・公開DNSで確認済み。NSは01〜04.dnsv.jp。
- Xサーバーはsv10177.xserver.jp。公開先は `/home/xs413084/udatsuageteko.com/public_html/`。
- 専用FTPアカウント作成済み。非公開設定を使った `python3 _udatsu/tools/deploy.py --check` 成功。既存エントリ7件。アップロード・サーバーバックアップはまだ未実行。
- SSLのDNS認証用TXT2件は公開DNSで確認済み。本人にXサーバーの「ONにする」を案内したところ。SSL有効化の結果は未確認。
- 新しいStripeテストキー、Webhook、Googleカレンダー、メール、Cron、本番受付は未確認・未完了。
- ローカルプレビューはポート8793（PHP）と8790（旧Node）を利用していた。現在のプロセス稼働は未確認。

## 2026-09-20 夕方の更新（Claude Code）
- 料金を公開講座・個別相談とも一律4,400円に変更（catalog.json / public / test.php / README）。模擬テスト15件合格。未コミット。
- preview（案内のみ・予約と決済は未開始）を https://udatsuageteko.com/ に公開済み。サーバー側バックアップ： `_udatsu_backups/20260920T085359Z-9f761d`。
- ブランチ `codex/udatsu-public-classes` を origin にpush済み（cd23a18まで）。mainは未変更。
- Xサーバー画面の状態は「NS相違」表示だが、公開・SSLは動作。
- 最優先の次の一手：Stripeテストキー／Webhook、Googleカレンダー連携、メール、Cron。

## 2026-09-20 夜の方針メモ（本人の発言より）
- 基本オンライン開催をサイトに明記（app.js / index.html。本番未反映）。
- Googleカレンダーはまず nytaffi@gmail.com のみ連携。HAIのカレンダーは共有しない。HAI側の「不在」はUdatsu・個人事業・副業の時間確保の印なので、除外に使うと空き時間が消える。HAI研修が増えたら「HAI研修」専用カレンダーを分けて時間のみ共有。
- 将来は他の講師を採用したい意向。講師別カレンダー・支払い分配・料金設計が必要。まず現行4,400円で1回実施して様子を見る。

## 2026-09-20 22:00 Googleカレンダー認証（Claude Code）
- Google Cloud に新プロジェクト「Udatsu」（ID: udatsu）を作成。Calendar API有効化、OAuth同意画面（外部・テスト中・テストユーザー nytaffi@gmail.com）、Webクライアント作成。
- connect-google.mjs による認証が完了し、リフレッシュトークンを private/.env に保存済み（値は誰も表示していない）。実際にカレンダーの空き取得ができるかは未検証。
- 未設定・未確認：GOOGLE_BOOKING_CALENDAR_ID（予約を書き込む先）、Stripe、メール、Cron、Udatsu受付可能カレンダー方式の実装。
- 注意：OAuth同意画面が「テスト中」のため、リフレッシュトークンは約7日で失効する。本番運用前に「本番公開」への切替を検討する。
- 実行の注意：接続スクリプトは _udatsu フォルダ内で、private/.env を読み込んで実行する。

## 2026-09-20 22:20 HAIカレンダーの共有（本人が設定・確認済み）
- HAI(akiyama@hiroshimai.co.jp)のカレンダーを nytaffi@gmail.com に「時間のみ」で共有。最初は詳細が見えていたが、権限を直して件名・クライアント名は非表示になった。
- 表示は「不在」と「予定あり」のみ。「不在」にはUdatsuに使える時間も含まれるため、アプリの自動除外（GOOGLE_BUSY_CALENDAR_IDS）にHAIは入れず primary のみとする。枠の提案時にHAIの空きを参照し、「不在」は本人に確認する。
- 私（Claude Code）のGoogleカレンダー連携はHAI側アカウントに接続されており、個人側(nytaffi)は未接続。HAIの他メンバーのカレンダーは見ない。

## 2026-09-20 SSL検証（Claude Code）
- https://udatsuageteko.com/ は 200・証明書検証OK。発行元 Let's Encrypt、有効期間 2026-09-20〜2026-12-19（自動更新の有無は未確認）。http は https へ301転送。
- www.udatsuageteko.com は名前解決できない（DNS未設定）。wwwで開く導線が必要なら追加の判断が要る。

## 2026-09-20 22:57 Stripe MCP接続（Claude Code）
- Stripe公式MCP（https://mcp.stripe.com）をuserスコープで登録し、OAuth認証済み（権限は「読み取り」のみ）。`claude mcp list` で Connected。
- ツールの読み込みにはClaude Codeの開き直しが必要。開き直し後にテスト商品・価格（4,400円）・Webhookの状態を確認する。
- 商品作成など書き込みが要る場合は、その時に認証をやり直して権限を上げる。Webhook署名シークレットはMCPで取れない可能性があり、その場合はダッシュボードから本人が `private/.env` へ入れる。

## 2026-09-20 夜の現在地（Claude Codeが記録）
- 本番 https://udatsuageteko.com/ に公開済み（モード preview）。講座10件（「声からサイトを作る！バイブコーディング講座」を追加。説明文はClaudeの下書きで本人の確認待ち）。サーバー側バックアップ最新： `_udatsu_backups/20260920T134652Z-29e8be`。
- 料金は一律4,400円。基本オンラインを明記。各講義カードに「受講予約」→月カレンダー→同意→送信（4クリック）。決済未接続の間は「予約リクエスト」（Formspree共通フォーム→contact@nyct.jp に着信確認済み、料金なし・枠確保なし）。`app.js` の `BOOKING_MODE`（inquiry / request / live）で切替。
- 管理画面（/admin）：全幅カレンダーで枠を選んで登録、講師選択（初期は秋山 那由他、追加可）、受け付ける講義の絞り込み（初期は全部）、参考カレンダー（HAI）を斜線で重ね表示。テスト17件合格。ログイン後の実画面は本人がまだ確認していない部分あり。
- 本番の /api/slots は空。カレンダーは「受付可能な日程なし＋問い合わせリンク」を表示する。
- Git：ブランチ codex/udatsu-public-classes は b8a5c32 までpush済み。mainは未変更。
- 公開スクリプト deploy.py は「講座数はローカルcatalogと一致」「管理API（busy/instructors）の通し」に修正済み。
- 未検証：予約リクエストの最終送信（チェック→送信）を実際に押した確認、スマホ表示、確定メール・カレンダー予定への講師名記載（未実装）。
- OAuth同意画面が「テスト中」のため、Googleのトークンは約7日で失効する可能性。

## 次の一手
**本番の管理画面（https://udatsuageteko.com/admin）にログインできるようにする。**
- 今は本番サーバーに管理トークン（ADMIN_TOKEN）が未設定で、ログインできない（401）。
- 案：Claude Codeが、既存のFTPS接続で本番の `_udatsu/private/.env` に、ランダムな管理トークンを置く（既存 `.env` があれば上書きせず停止）。値は画面・チャットに出さず、Mac内 `_udatsu/private/production_admin_token.txt` に保存。**本人の「やって」待ち**（サーバー設定の新規作成のため確認中）。
- そのあと：本番管理画面でカレンダーから募集枠を登録 → Googleカレンダー連携（本番の .env に GOOGLE_* を手入力。GOOGLE_BOOKING_CALENDAR_ID・GOOGLE_REFERENCE_CALENDAR_IDS=akiyama@hiroshimai.co.jp も）→ Stripe → メール・Cron。

## 引き継ぎ時の注意
- `_udatsu/README.md` のDNS・FTP未接続という記載は古い。現状は本メモを優先する。技術手順はREADMEを参照。
- Gitのコミットはローカル保存であり、サーバーのバックアップ済みを意味しない。先の会話の「バックアップがある」という表現を本番バックアップ済みと解釈しない。
- 設定ファイルの内容やパスワードを引き継ぎ文へ転載しない。
- 今回の必読ファイル整備ではアプリのコードや公開サーバーは変更していない。
