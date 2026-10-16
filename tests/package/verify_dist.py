"""Verify Composer archives exclude local dependencies, secrets and build outputs."""
import json
from pathlib import Path
import shutil
import subprocess
import tempfile
import zipfile

root = Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='shipper-cli-dist-') as directory:
    base = Path(directory)
    source = base / 'source'
    shutil.copytree(root, source, ignore=shutil.ignore_patterns('.git', 'vendor', 'node_modules', 'builds', '.env*', 'auth.json', 'box.phar', '__pycache__'))
    excluded = ['vendor/phpunit/synthetic.php', 'examples/sample/vendor/synthetic.php',
                'node_modules/synthetic.js', 'examples/sample/node_modules/synthetic.js',
                '.env', 'examples/sample/.env', 'auth.json', 'examples/sample/auth.json',
                'builds/shipper', 'box.phar', 'tests/synthetic.php', '.github/synthetic.yml']
    for name in excluded:
        target = source / name
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(json.dumps({'http-basic': {'example.invalid': {'username': 'synthetic-excluded-marker', 'password': 'synthetic'}}}) if target.name == 'auth.json' else 'synthetic-excluded-marker')
    subprocess.run(['composer', 'archive', '--format=zip', '--file=cli', '--dir=' + str(base), '--no-interaction'], cwd=source, check=True)
    with zipfile.ZipFile(base / 'cli.zip') as archive:
        names = set(archive.namelist())
        for required in ['shipper', 'composer.json', 'app/Config/ConfigLoader.php', 'bootstrap/app.php']:
            if required not in names:
                raise RuntimeError('Missing runtime file: ' + required)
        for name in names:
            parts = Path(name).parts
            if any(part in ['vendor', 'node_modules', 'tests', '.github', 'builds'] for part in parts):
                raise RuntimeError('Development content shipped: ' + name)
            if Path(name).name.startswith('.env') or Path(name).name in ['auth.json', 'box.phar']:
                raise RuntimeError('Local file shipped: ' + name)
            if not name.endswith('/') and b'synthetic-excluded-marker' in archive.read(name):
                raise RuntimeError('Excluded marker shipped: ' + name)
    print('CLI Composer archive excludes dependencies, secrets, tests and build outputs')
