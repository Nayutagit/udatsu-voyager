"""秘密の設定・予約データ・旧サービスを含めず、公開用ファイルだけをZIPにする。"""
from pathlib import Path
import hashlib
import json
import zipfile

root = Path(__file__).resolve().parents[2]
app = root / '_udatsu'
out = app / 'artifacts'
out.mkdir(exist_ok=True)
files = [root/'index.php', root/'.htaccess', app/'.htaccess', app/'lib.php', app/'app.php', app/'cron.php', app/'catalog.json']
files += sorted(p for p in (app/'public').iterdir() if p.is_file())
manifest = {}
with zipfile.ZipFile(out/'udatsu-xserver-preview.zip', 'w', zipfile.ZIP_DEFLATED) as archive:
    for file in files:
        name = str(file.relative_to(root))
        data = file.read_bytes()
        archive.writestr(name, data)
        manifest[name] = hashlib.sha256(data).hexdigest()
(out/'release-manifest.json').write_text(json.dumps(manifest, indent=2))
print('公開用ZIPを作成：', out/'udatsu-xserver-preview.zip')
print('収録ファイル数：', len(files), '（APIキー・予約データなし）')
