"""画面まわりのファイル（admin.js / admin.html / styles.css / app.js / index.html / og.png）と app.php だけを本番へ反映。事前にサーバー側へバックアップ。.env は読まない・触らない。"""
import datetime
import ftplib
import io
import secrets
import ssl
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import deploy

FILES = ['admin.js', 'admin.html', 'styles.css', 'app.js', 'index.html', 'og.png']
SERVER_FILES = ['app.php']  # _udatsu 直下のサーバー側ファイル

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
    added = []
    for path, local in [('_udatsu/public/' + n, deploy.APP / 'public' / n) for n in FILES] + [('_udatsu/' + n, deploy.APP / n) for n in SERVER_FILES]:
        old = io.BytesIO()
        try:
            ftp.retrbinary('RETR ' + path, old.write)
            ftp.storbinary('STOR ' + backup + '/' + path, io.BytesIO(old.getvalue()))
        except ftplib.error_perm:
            added.append(path)  # サーバーにまだ無い新しいファイル
            old = io.BytesIO()
        new[path] = local.read_bytes()
        assert new[path], path
    # 入口の .htaccess で og.png も Udatsu に渡す（無ければ1語だけ追加）
    # 過去の公開でUdatsuの設定が重複しているため、実際に効く先頭の行だけを直す
    old = io.BytesIO()
    ftp.retrbinary('RETR .htaccess', old.write)
    rule_old = rb'instructor\.png)?$ index.php [END]'
    rule_new = rb'instructor\.png|og\.png)?$ index.php [END]'
    if rule_new not in old.getvalue():
        if rule_old not in old.getvalue():
            raise RuntimeError('.htaccess の Udatsu の行が見つからないため停止しました')
        ftp.storbinary('STOR ' + backup + '/.htaccess', io.BytesIO(old.getvalue()))
        new['.htaccess'] = old.getvalue().replace(rule_old, rule_new, 1)
    print('サーバー側バックアップ作成：', backup)
    try:
        for path, content in new.items():
            ftp.storbinary('STOR ' + path, io.BytesIO(content))
        for name in ['/admin.js', '/styles.css', '/app.js', '/admin', '/', '/og.png', '/api/catalog']:
            status, _ = deploy.http(name)
            if status != 200:
                raise RuntimeError(name + ' が表示できません（' + str(status) + '）')
    except Exception:
        print('問題が起きたため、バックアップから元に戻します。')
        for path in new:
            if path in added:
                try:
                    ftp.delete(path)
                except ftplib.error_perm:
                    pass
                continue
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
