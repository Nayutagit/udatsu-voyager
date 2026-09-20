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

## 次の一手
本人のSSL操作結果を確認し、HTTPS証明書を検証する。その後、既存ファイルの退避と公開先照合を行う公開スクリプトでpreview公開へ進む。

## 引き継ぎ時の注意
- `_udatsu/README.md` のDNS・FTP未接続という記載は古い。現状は本メモを優先する。技術手順はREADMEを参照。
- Gitのコミットはローカル保存であり、サーバーのバックアップ済みを意味しない。先の会話の「バックアップがある」という表現を本番バックアップ済みと解釈しない。
- 設定ファイルの内容やパスワードを引き継ぎ文へ転載しない。
- 今回の必読ファイル整備ではアプリのコードや公開サーバーは変更していない。
