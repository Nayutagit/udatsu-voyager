"""Udatsu専用FTPS公開。--checkは読み取りのみ、--deployは接続先をHTTPで照合後に限定更新。"""
import argparse
import datetime
import ftplib
import io
import json
import os
from pathlib import Path
import secrets
import ssl
import urllib.error
import urllib.request

BASE = 'https://udatsuageteko.com'
ROOT = Path(__file__).resolve().parents[2]
APP = ROOT / '_udatsu'

def settings():
    values = {}
    file = APP / 'private' / 'deploy.env'
    if file.exists():
        for line in file.read_text().splitlines():
            if '=' in line and not line.lstrip().startswith('#'):
                key, value = line.split('=', 1)
                values[key.strip()] = value.strip().strip('"\'')
    return {**values, **os.environ}

def http(path):
    try:
        with urllib.request.urlopen(BASE + path, timeout=20) as response:
            return response.status, response.read()
    except urllib.error.HTTPError as error:
        return error.code, error.read()

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--check', action='store_true')
    parser.add_argument('--deploy', action='store_true')
    args = parser.parse_args()
    if args.check == args.deploy:
        parser.error('--check または --deploy の一方を指定してください。')
    env = settings()
    for key in ['XSERVER_FTP_HOST', 'XSERVER_FTP_USER', 'XSERVER_FTP_PASSWORD', 'XSERVER_FTP_ROOT']:
        if not env.get(key):
            raise RuntimeError('接続設定が必要です：' + key + '（private/deploy.env）')
    ftp = ftplib.FTP_TLS(context=ssl.create_default_context(), timeout=30)
    ftp.connect(env['XSERVER_FTP_HOST'], int(env.get('XSERVER_FTP_PORT', '21')))
    ftp.login(env['XSERVER_FTP_USER'], env['XSERVER_FTP_PASSWORD'])
    ftp.prot_p()
    ftp.cwd(env['XSERVER_FTP_ROOT'])
    print('FTPS接続成功。設定された公開フォルダに接続しました。')
    print('既存エントリ数：', len(ftp.nlst()))
    if args.check:
        ftp.quit()
        return

    def upload(name, content):
        ftp.storbinary('STOR ' + name, io.BytesIO(content))

    def read(name):
        content = io.BytesIO()
        try:
            ftp.retrbinary('RETR ' + name, content.write)
            return content.getvalue()
        except ftplib.error_perm as error:
            if str(error).startswith('550'):
                return None
            raise

    def mkdir(name):
        current = ''
        for part in name.split('/'):
            current = current + '/' + part if current else part
            try:
                ftp.mkd(current)
            except ftplib.error_perm:
                # ディレクトリの存在を確認。権限エラーを握りつぶして先へ進まない。
                original = ftp.pwd()
                ftp.cwd(current)
                ftp.cwd(original)

    # 他ドメインのFTPルートを誤って更新しない。DNSがSTUDIOを向いている間はここで止まる。
    nonce = secrets.token_hex(24)
    probe = '_udatsu-connection-' + nonce + '.txt'
    upload(probe, nonce.encode())
    try:
        status, payload = http('/' + probe)
        if status != 200 or payload != nonce.encode():
            raise RuntimeError('ドメインとFTP公開先が一致しません。DNSとXサーバーのドメイン設定を確認してください。既存サイトは変更していません。')
    finally:
        ftp.delete(probe)

    # 秘密の設定がある運用中の予約システムを、previewに無断で置き換えない。
    if read('_udatsu/private/.env') is not None:
        raise RuntimeError('既にUdatsuの接続設定があります。稼働状況を確認したうえで更新してください。')
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ') + '-' + secrets.token_hex(3)
    backup = '_udatsu_backups/' + stamp
    mkdir('_udatsu_backups')
    upload('_udatsu_backups/.htaccess', b'Require all denied\n')
    upload('_udatsu_backups/protection-test.txt', b'private')
    status, _ = http('/_udatsu_backups/protection-test.txt')
    ftp.delete('_udatsu_backups/protection-test.txt')
    if status != 403:
        raise RuntimeError('バックアップ領域の非公開を確認できないため停止しました。')
    mkdir(backup)
    files = {'_udatsu/.htaccess': (APP / '.htaccess').read_bytes()}
    for name in ['lib.php', 'app.php', 'cron.php', 'catalog.json']:
        files['_udatsu/' + name] = (APP / name).read_bytes()
    for file in sorted((APP / 'public').iterdir()):
        if file.is_file():
            files['_udatsu/public/' + file.name] = file.read_bytes()
    old_htaccess = read('.htaccess') or b''
    # ルートとUdatsuの既知のパスだけを新アプリへ。それ以外の既存ファイルは保持する。
    prefix = b'''# BEGIN UD ATSU PUBLIC CLASSES
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
RewriteRule ^_udatsu(?:_backups)?(?:/|$) - [F,L]
RewriteRule ^(?:index\\.php|api/(?:catalog|slots|checkout|stripe/webhook|bookings/[a-f0-9]{40}|demo/pay|admin/(?:overview|slots|close|retry|busy|instructors))|admin|styles\\.css|app\\.js|admin\\.js|instructor\\.png|og\\.png)?$ index.php [END]
# END UD ATSU PUBLIC CLASSES
'''
    files['.htaccess'] = prefix + old_htaccess
    files['index.php'] = (ROOT / 'index.php').read_bytes()
    previous = {}
    for name in files:
        previous[name] = read(name)
        if previous[name] is not None:
            destination = backup + '/' + name
            mkdir(str(Path(destination).parent))
            upload(destination, previous[name])
    upload(backup + '/manifest.json', json.dumps({'time': stamp, 'domain': BASE, 'files': {k: v is not None for k, v in previous.items()}}, ensure_ascii=False).encode())
    try:
        for name, content in files.items():
            if '/' in name:
                mkdir(str(Path(name).parent))
            upload(name, content)
        status, payload = http('/api/catalog')
        catalog = json.loads(payload)
        if status != 200 or catalog.get('mode') != 'preview' or len(catalog.get('courses', [])) != len(json.loads((APP / 'catalog.json').read_text(encoding='utf-8'))):
            raise RuntimeError('公開後の講座API検証に失敗しました。')
        for private in ['/_udatsu/lib.php', '/_udatsu/private/.env', '/_udatsu_backups/']:
            if http(private)[0] != 403:
                raise RuntimeError('非公開領域の保護を確認できませんでした。')
        if http('/')[0] != 200:
            raise RuntimeError('トップページの表示確認に失敗しました。')
    except Exception:
        for name, content in reversed(list(previous.items())):
            if content is not None:
                upload(name, content)
        print('既存ファイルを公開前の内容に戻しました。追加ファイルは保護領域に残しています。')
        raise
    print('Udatsuの講座案内を公開しました。予約・決済受付はまだ開始していません。')
    print('復元用バックアップ：', backup)
    ftp.quit()

if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        # 接続情報・APIキーを例外ログに出さない。
        if isinstance(error, RuntimeError):
            print(str(error))
        else:
            print('公開処理を完了できませんでした。接続・権限・HTTPSを確認してください。種類：' + type(error).__name__)
        raise SystemExit(1)
