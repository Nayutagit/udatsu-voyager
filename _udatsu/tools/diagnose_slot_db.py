"""本番の予約データベース（private/<mode>.sqlite）を手元に写し、枠登録と同じ書き込みを「写しの方で」試す（本人が ! で実行）。
本番には何も書き込まない。.env は読まない。結果（壊れていないか・書き込めるか）だけを表示する。
使い方： python3 …/diagnose_slot_db.py test
        python3 …/diagnose_slot_db.py log   （本番 private/error.log の最新10行を表示）
"""
import ftplib
import shutil
import sqlite3
import ssl
import sys
import tempfile
from pathlib import Path

from deploy import settings  # noqa: E402


def main():
    mode = sys.argv[1] if len(sys.argv) > 1 else 'test'
    env = settings()
    ftp = ftplib.FTP_TLS(context=ssl.create_default_context(), timeout=30)
    ftp.connect(env['XSERVER_FTP_HOST'], int(env.get('XSERVER_FTP_PORT', '21')))
    ftp.login(env['XSERVER_FTP_USER'], env['XSERVER_FTP_PASSWORD'])
    ftp.prot_p()
    ftp.cwd(env['XSERVER_FTP_ROOT'])
    if mode == 'log':
        lines = []
        try:
            ftp.retrlines('RETR _udatsu/private/error.log', lines.append)
        except ftplib.error_perm:
            print('error.log はまだありません（エラーが記録されていません）。')
        print('\n'.join(lines[-10:]))
        ftp.quit()
        return
    work = Path(tempfile.mkdtemp())
    src = work / (mode + '.sqlite')
    names = []
    ftp.retrlines('NLST _udatsu/private', names.append)
    print('サーバーのprivate内：', ', '.join(sorted(n.split('/')[-1] for n in names if not n.endswith('.env'))))
    with open(src, 'wb') as f:
        ftp.retrbinary('RETR _udatsu/private/' + mode + '.sqlite', f.write)
    ftp.quit()
    print('写したファイル：', src.stat().st_size, 'バイト')

    db = sqlite3.connect(src)
    print('壊れていないか：', db.execute('PRAGMA integrity_check').fetchone()[0])
    print('書き込み方式：', db.execute('PRAGMA journal_mode').fetchone()[0])
    print('slotsの列：', [r[1] for r in db.execute('PRAGMA table_info(slots)')])
    print('枠の数：', db.execute('SELECT count(*) FROM slots').fetchone()[0],
          '／注文の数：', db.execute('SELECT count(*) FROM orders').fetchone()[0])
    print('講師：', db.execute('SELECT id, active FROM instructors').fetchall())
    db.close()

    trial = work / 'trial.sqlite'
    shutil.copy(src, trial)
    db = sqlite3.connect(trial, isolation_level=None)
    try:
        db.execute('PRAGMA foreign_keys=ON')
        db.execute('BEGIN IMMEDIATE')
        db.execute("INSERT INTO slots(id,start,end,format,location,meeting_url,instructor_id,allowed) VALUES('diag','2026-12-01T01:00:00.000Z','2026-12-01T02:00:00.000Z','オンライン','オンライン（Google Meet）','','nayuta','')")
        db.execute('COMMIT')
        print('写しへの枠登録：成功（データの形は問題なし）')
    except Exception as error:
        print('写しへの枠登録：失敗 →', type(error).__name__, error)
    db.close()


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print('診断できませんでした：', type(error).__name__, error)
        raise SystemExit(1)
