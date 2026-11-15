"""Exercise release packaging and the subsequent Composer git-archive export.

Requires git and shopware-cli. Builds run only in a temporary checkout; no tags,
network services, or publishing are involved. Asset/dependency builds are skipped:
a prebuilt asset fixture checks that packaging preserves distributable assets.
"""

import io
import json
import pathlib
import shutil
import subprocess
import tempfile
import zipfile


ROOT = pathlib.Path(__file__).resolve().parents[2]
ASSET = 'src/Resources/public/administration/js/packaging-fixture.js'
ASSET_CONTENT = b'/* prebuilt administration asset */\n'


def run(*args, cwd=None):
    return subprocess.check_output(args, cwd=cwd)


def read_archive(data):
    with zipfile.ZipFile(io.BytesIO(data)) as archive:
        return {name.split('/', 1)[1]: archive.read(name)
                for name in archive.namelist() if not name.endswith('/')}


def check_manifest(files):
    manifest = json.loads(files['checksum.json'])
    missing = set(manifest['hashes']) - files.keys()
    assert not missing, f'Checksum references missing files: {sorted(missing)}'
    assert not any(path.startswith('workers/') for path in files), 'Worker sources shipped'
    assert not any(path.startswith('workers/') for path in manifest['hashes'])
    assert files[ASSET] == ASSET_CONTENT, 'Prebuilt administration asset was lost'
    assert 'src/FroshTools.php' in manifest['hashes'], 'Plugin code must remain covered'
    return manifest


with tempfile.TemporaryDirectory(prefix='froshtools-packaging-') as temporary:
    temp = pathlib.Path(temporary)
    source = temp / 'source'
    source.mkdir()
    # Copy working-tree content, not git archive: export-ignore must NOT hide
    # workers before shopware-cli's --disable-git packaging path is exercised.
    for name in run('git', 'ls-files', '-z', cwd=ROOT).decode().split('\0'):
        if name:
            destination = source / name
            destination.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(ROOT / name, destination)
    assert (source / 'workers/app-url-reachability/src/index.mjs').is_file()
    config_path = source / '.shopware-extension.yml'
    config = config_path.read_text()
    assert config.count('    zip:\n') == 1 and config.count('        assets:\n') == 1
    config = config.replace('    zip:\n', '    zip:\n        composer:\n            enabled: false\n')
    config = config.replace('        assets:\n', '        assets:\n            enabled: false\n')
    config_path.write_text(config)
    asset = source / ASSET
    asset.parent.mkdir(parents=True, exist_ok=True)
    asset.write_bytes(ASSET_CONTENT)
    version = json.loads((source / 'composer.json').read_text())['version']
    (source / 'CHANGELOG_en-GB.md').write_text(f'# {version}\n\nPackaging test fixture.\n')
    run('shopware-cli', 'extension', 'package', str(source), '--disable-git',
        '--release', '--filename', 'FroshTools.zip', '--output-directory', str(temp))
    release = read_archive((temp / 'FroshTools.zip').read_bytes())
    manifest = check_manifest(release)
    # Match the publishing workflow's extracted build commit, then simulate
    # GitHub's Composer zipball. Keep .gitattributes so export-ignore applies.
    built = temp / 'built'
    built.mkdir()
    for name, content in release.items():
        destination = built / name
        destination.parent.mkdir(parents=True, exist_ok=True)
        destination.write_bytes(content)
    run('git', 'init', '-q', cwd=built)
    run('git', 'add', '--all', '--force', '.', cwd=built)
    tree = run('git', 'write-tree', cwd=built).decode().strip()
    composer = read_archive(run('git', 'archive', '--format=zip', '--prefix=FroshTools/', tree, cwd=built))
    assert check_manifest(composer) == manifest
    for name in manifest['hashes']:
        assert composer[name] == release[name], f'Export changed checksummed file: {name}'
    print(f'Release and Composer archives: {len(manifest["hashes"])} manifest entries present; '
          'worker sources excluded; prebuilt administration asset preserved.')
