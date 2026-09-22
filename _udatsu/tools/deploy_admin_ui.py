"""画面まわりのファイル（admin.js / styles.css / app.js）だけを本番へ反映。事前にサーバー側へバックアップ。.env は読まない・触らない。"""
import datetime
import ftplib
import io
import secrets
import ssl
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import deploy

FILES = ['admin.js', 'styles.css', 'app.js']

def main():
    env = deploy.settings()
    ftp = ftplib.FTP_TLS(context=ssl.create_default_context(), timeout=30)
    ftp.connect(env['XSERVER_FTP_HOST'], int(env.get('XSERVER_FTP_PORT', '21')))
    ftp.login(env['XSERVER_FTP_USER'], env['XSERVER_FTP_PASSWORD'])
    ftp.prot_p()
    ftp.cwd(env['XSERVER_FTP_ROOT'])
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ') + '-adminui-' + secrets.token_hex(3)
    backup = '_udatsu_backups/' + stamp
    ftp.mkd(backup)
    ftp.mkd(backup + '/_udatsu')
    ftp.mkd(backup + '/_udatsu/public')
    new = {}
    for name in FILES:
        path = '_udatsu/public/' + name
        old = io.BytesIO()
        ftp.retrbinary('RETR ' + path, old.write)
        ftp.storbinary('STOR ' + backup + '/' + path, io.BytesIO(old.getvalue()))
        new[path] = (deploy.APP / 'public' / name).read_bytes()
        assert new[path], name
    print('サーバー側バックアップ作成：', backup)
    try:
        for path, content in new.items():
            ftp.storbinary('STOR ' + path, io.BytesIO(content))
        for name in ['/admin.js', '/styles.css', '/app.js', '/admin', '/']:
            status, _ = deploy.http(name)
            if status != 200:
                raise RuntimeError(name + ' が表示できません（' + str(status) + '）')
    except Exception:
        print('問題が起きたため、バックアップから元に戻します。')
        for path in new:
            data = io.BytesIO()
            ftp.retrbinary('RETR ' + backup + '/' + path, data.write)
            ftp.storbinary('STOR ' + path, io.BytesIO(data.getvalue()))
        raise
    ftp.quit()
    print('管理画面の見た目を反映しました。スマホで https://udatsuageteko.com/admin を開き、再読み込みしてください。')

if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print('反映できませんでした。種類：' + type(error).__name__ + ' ' + (str(error) if isinstance(error, RuntimeError) else ''))
        raise SystemExit(1)
