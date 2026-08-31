"""Pinned Git inventory and deliberately limited exports for MT-1.1 characterization.

No source application is executed and no source working tree is written.
Exports live only under the ignored .local/mt11/sources directory.
"""
from pathlib import Path, PurePosixPath
import argparse
import collections
import hashlib
import json
import subprocess

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'docs/migration'
SOURCES = {
    'pos': ('C:/mobiST/mobiST-POS', 'c61e47394e7b3db8a49cfe443c85b63835febc9b'),
    'website': ('C:/mobiST/mobiST-Website', '04e7c49518f9f11f60c83ad44f9f4e2fd2539066'),
}
MASTER_TEST_ASSETS = {
    'Brandkit - mobiST/Wordmark/Wordmark_mobiST_Editable.svg',
    'Brandkit - mobiST/Logo/Logo_mobiST_Master_Editable.svg',
}


def git_args(path):
    return ['git', '--no-optional-locks', '-c', 'safe.directory=' + path, '-C', path]


def classify(name):
    p = PurePosixPath(name)
    first, suffix = p.parts[0], p.suffix.lower()
    if name == '.env' or (p.name.startswith('.env.') and 'example' not in p.name) or suffix in ('.sqlite','.sqlite3','.sql','.key','.pem','.pfx'):
        return 'sensitive-runtime', 'exclude', 'Never export private configuration, business data or key material', False
    if first in ('vendor','node_modules') or name.startswith(('public/build/','bootstrap/cache/')) and p.name != '.gitignore':
        return 'generated-dependency', 'reinstall-or-regenerate', 'Do not copy installed dependencies/build output', False
    if first == 'app':
        role = p.parts[1] if len(p.parts) > 1 else 'app'
        return 'application/' + role, 'assess-reuse-adapt-refactor-migrate', 'Preserve verified behavior; replacement requires parity evidence', True
    if first == 'database':
        return 'database/' + p.parts[1], 'map-and-migrate', 'Preserve schema/data identifiers and relationships; no live data exported', True
    if first == 'tests':
        return 'characterization-tests', 'adapt-test-contracts', 'Retain source assertions as migration acceptance evidence', True
    if first == 'routes':
        return 'route-contracts', 'adapt-route-ownership', 'Preserve roles, validation and URLs/contracts where required', True
    if first == 'resources':
        return 'presentation/' + p.parts[1], 'adapt-presentation-preserve-behavior', 'Framework-specific Blade surface changes; behavior/content rules remain', True
    if first == 'public':
        return 'runtime-assets', 'inspect-runtime-dependencies', 'Retain required runtime assets/licenses; canonical master moves to brand', suffix not in ('.exe','.dll','.zip')
    if first == 'Brandkit - mobiST':
        return 'brand-master-reference', 'deduplicate-to-root-brand', 'Single canonical approved master; preserve relevant source/reference files', name in MASTER_TEST_ASSETS
    if first == 'mobiST Control Center':
        exported = suffix in ('.cs','.bat','.txt') and 'Desktop Commander Launcher Backup' not in p.parts
        return 'control-reference', ('adapt-single-control' if exported else 'inspect-do-not-auto-copy'), 'Consolidate useful source; rebuild binary and replace legacy logo after approval mapping', exported
    if first in ('docs',) or suffix in ('.md','.docx'):
        return 'historical-documentation', 'retain-provenance-selectively', 'Old decisions are evidence, not new-project authority', False
    if first == 'scripts':
        return 'legacy-operations-tooling', 'review-before-adaptation', 'Inspect paths, process ownership and side effects; never execute source launch/reset tools', True
    if first in ('config','bootstrap'):
        return 'framework-configuration', 'adapt-isolated-configuration', 'No source secrets/runtime cache; shared backend owns resulting configuration', True
    if first == '.github':
        return 'legacy-ci', 'adapt-monorepo-ci', 'New remote CI only; do not operate old workflows', False
    if name in ('composer.json','composer.lock','package.json','package-lock.json','phpunit.xml','artisan','vite.config.js','.env.example','.npmrc','.nvmrc'):
        return 'build-test-contracts', 'adapt-pinned-toolchain', 'Preserve reproducibility; install fresh dependencies only in isolated copy', True
    return 'root-metadata', 'review-retain-needed', 'Retain required identity/license/editor metadata; no automatic wholesale copy', name in ('.gitignore','.gitattributes','LICENSE','NOTICE.md')


def inventory(label, path, commit, export):
    tree = subprocess.check_output(git_args(path) + ['ls-tree','-rlz',commit])
    records = []
    objects = subprocess.Popen(git_args(path) + ['cat-file','--batch'], stdin=subprocess.PIPE, stdout=subprocess.PIPE)
    destination = (ROOT / '.local/mt11/sources' / label).resolve()
    assert destination.is_relative_to(ROOT.resolve())
    for entry in tree.split(b'\0'):
        if not entry:
            continue
        meta, raw_name = entry.split(b'\t',1)
        mode, kind, blob, size = meta.decode().split()
        name = raw_name.decode('utf-8')
        if kind != 'blob' or mode not in ('100644','100755'):
            raise RuntimeError('Unexpected gitlink/symlink: ' + name)
        category, decision, reason, include = classify(name)
        record = {'source':label,'path':name,'git_blob':blob,'bytes':int(size),'category':category,'decision':decision,'reason':reason,'characterization_export':include}
        if category == 'sensitive-runtime':
            raise RuntimeError('Unexpected sensitive tracked file; do not export: ' + name)
        objects.stdin.write((blob+'\n').encode()); objects.stdin.flush()
        header = objects.stdout.readline().decode().split()
        data = objects.stdout.read(int(header[2])); assert objects.stdout.read(1) == b'\n'
        record['sha256'] = hashlib.sha256(data).hexdigest()
        records.append(record)
        if export and include:
            target = (destination/name).resolve()
            assert target.is_relative_to(destination)
            target.parent.mkdir(parents=True,exist_ok=True)
            if target.exists() and target.read_bytes() != data:
                raise RuntimeError('Existing isolated file differs; refuse overwrite: ' + str(target))
            target.write_bytes(data)
    objects.stdin.close(); objects.wait()
    assert objects.returncode == 0
    return {'source':label,'path':path,'commit':commit,'files':records,'counts':dict(collections.Counter(r['category'] for r in records)),'exported_files':sum(r['characterization_export'] for r in records),'total_files':len(records)}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--export',action='store_true')
    args = parser.parse_args()
    results = [inventory(label,*source,args.export) for label,source in SOURCES.items()]
    OUT.mkdir(parents=True,exist_ok=True)
    (OUT/'SOURCE_FILE_INVENTORY.json').write_text(json.dumps({'schema':1,'point':'MT-1.1','sources':results},indent=2)+'\n',encoding='utf-8',newline='\n')
    for result in results:
        print(result['source'],result['total_files'],'tracked files;',result['exported_files'],'selected characterization files')
        print(json.dumps(result['counts'],sort_keys=True))


if __name__ == '__main__':
    main()
