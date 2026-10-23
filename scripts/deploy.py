#!/usr/bin/env python3
"""FMTRX artifact deployment. Python 3.6+; no Node, GitHub CLI, or sudo required."""
import argparse
import datetime
import fcntl
import getpass
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import shutil
import stat
import subprocess
import sys
import tempfile
import urllib.error
import urllib.parse
import urllib.request
import zipfile

REPOSITORY = 'TDimi21/Fungometrics-Website'
API_ENDPOINT = 'https://app.fmtrx.com/api/'
SITE = 'https://app.fmtrx.com'
MAX_ARCHIVE = 100 * 1024 * 1024
MAX_EXPANDED = 300 * 1024 * 1024
TOKEN_FILE = Path.home() / '.config/fmtrx/deploy-token'


class Stop(Exception):
    pass


def run(args, cwd, capture=False):
    result = subprocess.run([str(arg) for arg in args], cwd=str(cwd),
                            stdout=subprocess.PIPE if capture else None,
                            universal_newlines=True)
    if result.returncode:
        raise Stop('Command failed: ' + str(args[0]) + ' ' + str(args[1]) + '. Review the output above.')
    return result.stdout.strip() if capture else None


def sha256(path):
    digest = hashlib.sha256()
    with open(str(path), 'rb') as stream:
        for block in iter(lambda: stream.read(1024 * 1024), b''):
            digest.update(block)
    return digest.hexdigest()


def relative_file(name):
    path = PurePosixPath(name)
    if not name or path.is_absolute() or '\\' in name or '..' in path.parts or str(path) != name:
        raise Stop('Unsafe artifact path.')
    return path


def prepare_artifact(root, commit, repository):
    if not re.fullmatch('[0-9a-f]{40}', commit):
        raise Stop('A full commit SHA is required.')
    if (os.environ.get('API_ENDPOINT') != API_ENDPOINT
            or os.environ.get('VITE_API_ENDPOINT', API_ENDPOINT) != API_ENDPOINT):
        raise Stop('Production artifact must use the production API endpoint.')
    files = {}
    for path in sorted(root.rglob('*')):
        if path.is_symlink():
            raise Stop('Artifact cannot contain symbolic links.')
        if path.is_file() and path.name != 'release.json':
            files[path.relative_to(root).as_posix()] = sha256(path)
    if 'manifest.json' not in files:
        raise Stop('Frontend manifest is missing.')
    data = dict(schema=1, commit=commit, repository=repository,
                api_endpoint=API_ENDPOINT, files=files)
    (root / 'release.json').write_text(json.dumps(data, indent=2) + '\n')


class SafeRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        if urllib.parse.urlsplit(newurl).scheme != 'https':
            raise Stop('Refusing a non-HTTPS redirect.')
        redirected = super().redirect_request(req, fp, code, msg, headers, newurl)
        if urllib.parse.urlsplit(newurl).netloc != urllib.parse.urlsplit(req.full_url).netloc:
            redirected.remove_header('Authorization')
        return redirected


def request(url, token=None, binary=False):
    headers = {'User-Agent': 'FMTRX-deploy', 'Accept': 'application/vnd.github+json',
               'X-GitHub-Api-Version': '2022-11-28'}
    if token:
        headers['Authorization'] = 'Bearer ' + token
    try:
        opener = urllib.request.build_opener(SafeRedirect())
        with opener.open(urllib.request.Request(url, headers=headers), timeout=90) as response:
            data = response.read(MAX_ARCHIVE + 1)
        if len(data) > MAX_ARCHIVE:
            raise Stop('GitHub response exceeded the size limit.')
        return data if binary else json.loads(data.decode('utf-8'))
    except urllib.error.HTTPError as error:
        raise Stop('GitHub HTTP {}. Check repository access, token expiry, and Actions read permission.'.format(error.code))
    except urllib.error.URLError:
        raise Stop('Could not reach GitHub over HTTPS. No credentials were printed.')


def read_token():
    value = os.environ.get('FMTRX_GITHUB_TOKEN', '').strip()
    if value:
        return value
    if TOKEN_FILE.exists():
        if TOKEN_FILE.is_symlink() or TOKEN_FILE.stat().st_mode & 0o077:
            raise Stop('Token file must be a regular private file (chmod 600).')
        return TOKEN_FILE.read_text().strip()
    raise Stop('Run python3 scripts/deploy.py --configure-token once before deploying.')


def configure_token():
    if not sys.stdin.isatty():
        raise Stop('Configure the token in an interactive SSH terminal so input stays hidden.')
    token = getpass.getpass('GitHub token with Actions read access (hidden): ').strip()
    if not token:
        raise Stop('No token entered.')
    request('https://api.github.com/repos/' + REPOSITORY + '/actions/workflows/ci.yml', token)
    TOKEN_FILE.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    if TOKEN_FILE.is_symlink():
        raise Stop('Refusing to overwrite a symbolic link.')
    descriptor = os.open(str(TOKEN_FILE), os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    with os.fdopen(descriptor, 'w') as stream:
        os.fchmod(stream.fileno(), 0o600)
        stream.write(token + '\n')
    print('GitHub read access verified. Token saved outside the application, with mode 600.')


def successful_run(runs, commit):
    matches = [item for item in runs if item.get('head_sha') == commit
               and item.get('head_branch') == 'main' and item.get('event') == 'push'
               and (item.get('head_repository') or {}).get('full_name', '').lower() == REPOSITORY.lower()]
    if not matches:
        raise Stop('No main-branch CI run exists for this commit. Push it and wait for CI.')
    latest = max(matches, key=lambda item: item['id'])
    if latest.get('status') != 'completed' or latest.get('conclusion') != 'success':
        raise Stop('CI is not successful for the selected commit. Wait for or repair run {}.'.format(latest['id']))
    return latest


def download_artifact(commit, destination, token):
    base = 'https://api.github.com/repos/' + REPOSITORY
    query = urllib.parse.urlencode(dict(head_sha=commit, branch='main', event='push', per_page=100))
    data = request(base + '/actions/workflows/ci.yml/runs?' + query, token)
    workflow = successful_run(data['workflow_runs'], commit)
    data = request(base + '/actions/runs/{}/artifacts?per_page=100'.format(workflow['id']), token)
    artifacts = [item for item in data['artifacts'] if item['name'] == 'frontend-build' and not item['expired']]
    if len(artifacts) != 1:
        raise Stop('A unique, unexpired frontend-build artifact is required. Rerun CI if expired.')
    artifact = artifacts[0]
    if (artifact.get('workflow_run') or {}).get('head_sha', commit) != commit:
        raise Stop('Artifact commit does not match the selected release.')
    destination.write_bytes(request(base + '/actions/artifacts/{}/zip'.format(artifact['id']), token, binary=True))
    digest = artifact.get('digest')
    if not digest or digest != 'sha256:' + sha256(destination):
        raise Stop('Artifact SHA-256 digest is missing or does not match GitHub.')
    print('Verified GitHub artifact from successful CI run {}.'.format(workflow['id']))


def extract_artifact(archive, destination, commit):
    with zipfile.ZipFile(str(archive)) as bundle:
        files = {}
        expanded = 0
        for info in bundle.infolist():
            name = info.filename.rstrip('/') if info.is_dir() else info.filename
            relative_file(name)
            if stat.S_ISLNK(info.external_attr >> 16):
                raise Stop('Artifact contains a symbolic link.')
            expanded += info.file_size
            if expanded > MAX_EXPANDED:
                raise Stop('Artifact expands beyond the size limit.')
            if not info.is_dir():
                if name in files:
                    raise Stop('Duplicate artifact file.')
                files[name] = info
        if 'release.json' not in files:
            raise Stop('Artifact lacks release metadata. Use a CI run with the new deployment workflow.')
        metadata = json.loads(bundle.read('release.json').decode('utf-8'))
        if (metadata.get('schema') != 1 or metadata.get('commit') != commit
                or metadata.get('repository', '').lower() != REPOSITORY.lower()
                or metadata.get('api_endpoint') != API_ENDPOINT):
            raise Stop('Artifact release identity or API endpoint does not match production.')
        hashes = metadata.get('files', {})
        if set(hashes) != set(files) - {'release.json'} or 'manifest.json' not in hashes:
            raise Stop('Artifact inventory does not match its release metadata.')
        for name, expected in hashes.items():
            if name != 'manifest.json' and not name.startswith('assets/'):
                raise Stop('Unexpected frontend artifact file: ' + name)
            contents = bundle.read(files[name])
            if hashlib.sha256(contents).hexdigest() != expected:
                raise Stop('Frontend file checksum failed: ' + name)
            target = destination.joinpath(*relative_file(name).parts)
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(contents)
            target.chmod(0o644)
        manifest = json.loads((destination / 'manifest.json').read_text())
        if 'resources/js/app.js' not in manifest:
            raise Stop('Application entry is missing from the manifest.')
        for item in manifest.values():
            for name in [item['file']] + item.get('css', []) + item.get('assets', []):
                relative_file(name)
                if name not in hashes:
                    raise Stop('Manifest references an absent asset: ' + name)
        return manifest


def preflight(root):
    if run(['git', 'symbolic-ref', '--short', 'HEAD'], root, True) != 'main':
        raise Stop('Deployment requires the existing main branch. No branches were switched.')
    remote = run(['git', 'remote', 'get-url', 'origin'], root, True)
    allowed = ['https://github.com/' + REPOSITORY, 'git@github.com:' + REPOSITORY]
    normalized = remote[:-4] if remote.endswith('.git') else remote
    if normalized.lower() not in [value.lower() for value in allowed]:
        raise Stop('Origin is not the expected FMTRX GitHub repository.')
    if run(['git', 'status', '--porcelain', '--untracked-files=no'], root, True):
        raise Stop('Tracked server changes exist. Preserve and review them before deploying; nothing was discarded.')
    if (root / 'storage/framework/down').exists() or (root / 'storage/framework/maintenance.php').exists():
        raise Stop('Application is already in maintenance mode. Resolve that deployment before starting another.')
    if (root / 'public/hot').exists():
        raise Stop('public/hot exists. Review the development-server marker before production deployment.')
    run(['git', 'fetch', 'origin', 'main'], root)
    current = run(['git', 'rev-parse', 'HEAD'], root, True)
    target = run(['git', 'rev-parse', 'origin/main'], root, True)
    run(['git', 'merge-base', '--is-ancestor', current, target], root)
    changes = run(['git', 'diff', '--name-only', current, target, '--', 'database/migrations'], root, True)
    if changes:
        raise Stop('This release changes migrations. A database backup and reviewed migration deployment are required first:\n' + changes)
    tracked = set(run(['git', 'ls-files'], root, True).splitlines())
    incoming = run(['git', 'ls-tree', '-r', '--name-only', target], root, True).splitlines()
    for name in incoming:
        if name not in tracked and os.path.lexists(str(root / name)):
            raise Stop('An existing untracked server file conflicts with the release: ' + name)
    return current, target


def copy_assets(stage, build):
    for source in (stage / 'assets').rglob('*'):
        if source.is_file():
            target = build / source.relative_to(stage)
            # Never follow a pre-existing symlink inside the live build.
            if any(parent.is_symlink() for parent in [target] + list(target.parents)):
                raise Stop('Symbolic link in live build path; review before deployment.')
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(str(source), str(target))
            target.chmod(0o644)
    next_manifest = build / 'manifest.json.next'
    if next_manifest.is_symlink() or (build / 'manifest.json').is_symlink():
        raise Stop('Symbolic link in manifest path; review before deployment.')
    shutil.copyfile(str(stage / 'manifest.json'), str(next_manifest))
    next_manifest.chmod(0o644)
    os.replace(str(next_manifest), str(build / 'manifest.json'))


def verify_http(manifest, commit):
    entry = manifest['resources/js/app.js']['file']
    url = SITE + '/login/player?deployment=' + commit
    headers = {'User-Agent': 'FMTRX-deploy', 'Cache-Control': 'no-cache'}
    with urllib.request.urlopen(urllib.request.Request(url, headers=headers), timeout=30) as response:
        if response.status != 200 or entry not in response.read(2 * 1024 * 1024).decode('utf-8'):
            raise Stop('Login page did not serve the new frontend. Check web/PHP caches and document root.')
    with urllib.request.urlopen(SITE + '/build/' + entry, timeout=30) as response:
        if response.status != 200 or 'javascript' not in response.headers.get('Content-Type', '').lower():
            raise Stop('The new JavaScript asset is not being served correctly.')


def deploy(args):
    root = Path(args.app_dir).resolve()
    if run(['git', 'rev-parse', '--show-toplevel'], root, True) != str(root):
        raise Stop('--app-dir must point to the application repository root.')
    backups = Path.home() / 'fmtrx-deploy-backups'
    if root == backups.resolve() or root in backups.resolve().parents:
        raise Stop('Backups must be outside the application directory.')
    backups.mkdir(mode=0o700, parents=True, exist_ok=True)
    with open(str(backups / '.deployment.lock'), 'a') as lock:
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            raise Stop('Another deployment/check is already running.')
        current, target = preflight(root)
        php = args.php or ('/opt/plesk/php/8.2/bin/php' if Path('/opt/plesk/php/8.2/bin/php').exists() else shutil.which('php'))
        composer = args.composer or shutil.which('composer')
        if not php or not composer:
            raise Stop('PHP and Composer are required. Node is not required.')
        php = str(Path(php).resolve())
        composer = str(Path(composer).resolve())
        if int(run([php, '-r', 'echo PHP_VERSION_ID;'], root, True)) < 80200:
            raise Stop('This release requires PHP 8.2 or newer.')
        token = read_token()
        with tempfile.TemporaryDirectory(prefix='fmtrx-preflight-', dir=str(backups)) as temporary:
            temporary = Path(temporary)
            archive = temporary / 'frontend.zip'
            download_artifact(target, archive, token)
            stage = temporary / 'frontend'
            manifest = extract_artifact(archive, stage, target)
            # Validate the target lockfile on this server before maintenance/code changes.
            for name in ['composer.json', 'composer.lock']:
                (temporary / name).write_text(run(['git', 'show', target + ':' + name], root, True) + '\n')
            run([php, composer, 'install', '--dry-run', '--no-dev', '--no-scripts', '--no-plugins', '--no-interaction'], temporary)
            print('Preflight passed for {}. No migrations or npm build will run.'.format(target), flush=True)
            if args.check:
                return
            backup = backups / (datetime.datetime.utcnow().strftime('%Y%m%dT%H%M%SZ') + '-' + target[:12])
            backup.mkdir(mode=0o700)
            (backup / 'deployed-commit.txt').write_text(current + '\n')
            (backup / 'target-commit.txt').write_text(target + '\n')
            print('Backing up to {}. This may take a few minutes.'.format(backup), flush=True)
            # Explicit private mode, including secrets inside .env; never place this in public/.
            with open(str(backup / 'application.tar.gz'), 'xb') as output:
                os.fchmod(output.fileno(), 0o600)
                result = subprocess.run(['tar', '--exclude=./node_modules', '--exclude=./vendor', '--exclude=./.git', '--exclude=./storage/logs', '--exclude=./storage/framework/cache', '--exclude=./storage/framework/sessions', '--exclude=./storage/framework/views', '-czf', '-', '.'], cwd=str(root), stdout=output)
            if result.returncode:
                raise Stop('Application backup failed. Live code has not changed.')
            run(['gzip', '-t', str(backup / 'application.tar.gz')], root)
            shutil.copyfile(str(archive), str(backup / 'frontend.zip'))
            # Recheck local edits after the potentially lengthy build download/backup.
            if run(['git', 'rev-parse', 'HEAD'], root, True) != current or run(['git', 'status', '--porcelain', '--untracked-files=no'], root, True):
                raise Stop('Working tree changed during preparation. No live update performed.')
            maintenance_started = False
            try:
                run([php, 'artisan', 'down'], root)
                maintenance_started = True
                run(['git', 'merge', '--ff-only', target], root)
                run([php, composer, 'install', '--no-dev', '--prefer-dist', '--no-interaction', '--optimize-autoloader'], root)
                run([php, composer, 'check-platform-reqs', '--no-dev'], root)
                copy_assets(stage, root / 'public/build')
                for command in ['route:clear', 'config:cache', 'view:cache', 'queue:restart', 'up']:
                    run([php, 'artisan', command], root)
                verify_http(manifest, target)
            except (Exception, KeyboardInterrupt):
                if maintenance_started:
                    subprocess.run([php, 'artisan', 'down'], cwd=str(root))
                    print('STOP: deployment failed. Maintenance mode requested. Backup: {}. Previous commit: {}. Do not reset/delete server files.'.format(backup, current), file=sys.stderr)
                raise
            (backup / 'success.txt').write_text(target + '\n')
            print('Deployment verified: {}. Login page and new JavaScript return HTTP 200. Test authenticated coach/player flows in your browser.'.format(target))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--app-dir', default=os.getcwd())
    parser.add_argument('--check', action='store_true', help='Download/validate release and dependencies only; do not deploy.')
    parser.add_argument('--configure-token', action='store_true', help='Save a GitHub Actions read token outside the app (hidden prompt).')
    parser.add_argument('--prepare-artifact', metavar='BUILD_DIR', help='CI only: create release metadata.')
    parser.add_argument('--commit', default=os.environ.get('GITHUB_SHA', ''))
    parser.add_argument('--repository', default=os.environ.get('GITHUB_REPOSITORY', REPOSITORY))
    parser.add_argument('--php', help='Absolute PHP CLI binary path.')
    parser.add_argument('--composer', help='Absolute Composer PHP script/PHAR path.')
    args = parser.parse_args()
    try:
        if args.prepare_artifact:
            prepare_artifact(Path(args.prepare_artifact), args.commit, args.repository)
        elif args.configure_token:
            configure_token()
        else:
            deploy(args)
    except KeyboardInterrupt:
        print('STOP: interrupted. If deployment had started, check maintenance mode before reopening.', file=sys.stderr)
        return 130
    except (Stop, OSError, ValueError, KeyError, TypeError, zipfile.BadZipFile) as error:
        # URLError may contain signed storage URLs; do not echo them or credentials.
        if isinstance(error, urllib.error.URLError):
            print('STOP: HTTP verification failed. Check server connectivity and the live login page.', file=sys.stderr)
        else:
            print('STOP: ' + str(error), file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())
