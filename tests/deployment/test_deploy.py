"""Offline tests; no production requests, emails, database changes, or git pushes."""
import ast
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import stat
import sys
import tempfile
import unittest
from unittest.mock import patch
import urllib.request
import zipfile

SCRIPT = Path(__file__).resolve().parents[2] / 'scripts/deploy.py'
spec = importlib.util.spec_from_file_location('fmtrx_deploy', str(SCRIPT))
deploy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(deploy)
COMMIT = 'a' * 40


class ArtifactTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.base = Path(self.temp.name).resolve()
        self.build = self.base / 'build'
        (self.build / 'assets').mkdir(parents=True)
        (self.build / 'assets/app-hash.js').write_text('console.log("app")')
        (self.build / 'manifest.json').write_text(json.dumps({
            'resources/js/app.js': {'file': 'assets/app-hash.js'},
        }))
        with patch.dict(os.environ, {'API_ENDPOINT': deploy.API_ENDPOINT}):
            deploy.prepare_artifact(self.build, COMMIT, deploy.REPOSITORY)

    def archive(self):
        archive = self.base / 'artifact.zip'
        with zipfile.ZipFile(str(archive), 'w') as bundle:
            for file in self.build.rglob('*'):
                if file.is_file():
                    bundle.write(str(file), file.relative_to(self.build).as_posix())
        return archive

    def test_valid_commit_checksums_and_manifest(self):
        stage = self.base / 'stage'
        manifest = deploy.extract_artifact(self.archive(), stage, COMMIT)
        self.assertEqual('assets/app-hash.js', manifest['resources/js/app.js']['file'])
        self.assertEqual('console.log("app")', (stage / 'assets/app-hash.js').read_text())

    def test_rejects_wrong_commit(self):
        with self.assertRaisesRegex(deploy.Stop, 'identity'):
            deploy.extract_artifact(self.archive(), self.base / 'stage', 'b' * 40)

    def test_rejects_corrupt_asset(self):
        (self.build / 'assets/app-hash.js').write_text('tampered')
        with self.assertRaisesRegex(deploy.Stop, 'checksum'):
            deploy.extract_artifact(self.archive(), self.base / 'stage', COMMIT)

    def test_rejects_missing_metadata(self):
        (self.build / 'release.json').unlink()
        with self.assertRaisesRegex(deploy.Stop, 'metadata'):
            deploy.extract_artifact(self.archive(), self.base / 'stage', COMMIT)

    def test_rejects_unlisted_files(self):
        (self.build / 'assets/unlisted.js').write_text('extra')
        with self.assertRaisesRegex(deploy.Stop, 'inventory'):
            deploy.extract_artifact(self.archive(), self.base / 'stage', COMMIT)

    def test_rejects_missing_manifest_target(self):
        manifest = self.build / 'manifest.json'
        manifest.write_text(json.dumps({'resources/js/app.js': {'file': 'assets/missing.js'}}))
        with patch.dict(os.environ, {'API_ENDPOINT': deploy.API_ENDPOINT}):
            deploy.prepare_artifact(self.build, COMMIT, deploy.REPOSITORY)
        with self.assertRaisesRegex(deploy.Stop, 'absent asset'):
            deploy.extract_artifact(self.archive(), self.base / 'stage', COMMIT)

    def test_rejects_traversal_and_symlinks(self):
        for name, mode in [('../outside', 0), ('assets/link', stat.S_IFLNK | 0o777)]:
            archive = self.archive()
            with zipfile.ZipFile(str(archive), 'a') as bundle:
                info = zipfile.ZipInfo(name)
                info.external_attr = mode << 16
                bundle.writestr(info, '../outside')
            with self.assertRaises(deploy.Stop):
                deploy.extract_artifact(archive, self.base / 'stage', COMMIT)
            self.assertFalse((self.base / 'outside').exists())

    def test_prepare_requires_production_api(self):
        with patch.dict(os.environ, {'API_ENDPOINT': 'http://localhost/api/'}):
            with self.assertRaisesRegex(deploy.Stop, 'production API'):
                deploy.prepare_artifact(self.build, COMMIT, deploy.REPOSITORY)

    def test_copy_preserves_old_assets_and_replaces_manifest(self):
        live = self.base / 'live'
        (live / 'assets').mkdir(parents=True)
        (live / 'assets/old.js').write_text('old')
        (live / 'manifest.json').write_text('{}')
        deploy.copy_assets(self.build, live)
        self.assertEqual('old', (live / 'assets/old.js').read_text())
        self.assertEqual((self.build / 'manifest.json').read_text(), (live / 'manifest.json').read_text())
        self.assertFalse((live / 'manifest.json.next').exists())

    def test_copy_rejects_existing_symlink(self):
        live = self.base / 'live'
        live.mkdir()
        outside = self.base / 'outside'
        outside.mkdir()
        (live / 'assets').symlink_to(outside)
        with self.assertRaisesRegex(deploy.Stop, 'Symbolic link'):
            deploy.copy_assets(self.build, live)
        self.assertEqual([], list(outside.iterdir()))


class GithubTests(unittest.TestCase):
    def workflow(self, **changes):
        workflow = dict(id=12, head_sha=COMMIT, head_branch='main', event='push',
                        head_repository={'full_name': deploy.REPOSITORY},
                        status='completed', conclusion='success')
        workflow.update(changes)
        return workflow

    def test_only_exact_successful_main_push_is_allowed(self):
        self.assertEqual(12, deploy.successful_run([self.workflow()], COMMIT)['id'])
        for changes in [dict(head_sha='b' * 40), dict(head_branch='other'), dict(event='pull_request'),
                        dict(status='in_progress'), dict(conclusion='failure'),
                        dict(head_repository={'full_name': 'attacker/fork'})]:
            with self.assertRaises(deploy.Stop):
                deploy.successful_run([self.workflow(**changes)], COMMIT)

    def test_latest_failed_run_cannot_fall_back_to_older_success(self):
        with self.assertRaises(deploy.Stop):
            deploy.successful_run([self.workflow(), self.workflow(id=13, conclusion='failure')], COMMIT)

    def test_auth_is_not_forwarded_to_artifact_storage(self):
        request = urllib.request.Request('https://api.github.com/artifact', headers={'Authorization': 'Bearer fake-test-token'})
        redirected = deploy.SafeRedirect().redirect_request(request, None, 302, 'redirect', {}, 'https://storage.example/archive')
        self.assertFalse(redirected.has_header('Authorization'))
        with self.assertRaises(deploy.Stop):
            deploy.SafeRedirect().redirect_request(request, None, 302, 'redirect', {}, 'http://storage.example/archive')

    def test_download_checks_digest(self):
        with tempfile.TemporaryDirectory() as temporary:
            archive = Path(temporary) / 'archive.zip'
            data = b'test-archive'
            artifact = dict(id=1, name='frontend-build', expired=False,
                            digest='sha256:' + hashlib.sha256(data).hexdigest(),
                            workflow_run={'head_sha': COMMIT})
            responses = [{'workflow_runs': [self.workflow()]}, {'artifacts': [artifact]}, data]
            with patch.object(deploy, 'request', side_effect=responses):
                deploy.download_artifact(COMMIT, archive, 'fake-test-token')
            artifact['digest'] = 'sha256:wrong'
            with patch.object(deploy, 'request', side_effect=responses):
                with self.assertRaisesRegex(deploy.Stop, 'digest'):
                    deploy.download_artifact(COMMIT, archive, 'fake-test-token')


class PreflightTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.calls = []
        self.branch = 'main'
        self.dirty = ''
        self.migrations = ''
        self.incoming = 'tracked.php'

    def command(self, command, cwd, capture=False):
        self.calls.append(command)
        key = ' '.join(command)
        if 'symbolic-ref' in key:
            return self.branch
        if 'remote get-url' in key:
            return 'https://github.com/' + deploy.REPOSITORY + '.git'
        if 'status' in key:
            return self.dirty
        if 'rev-parse' in key:
            return COMMIT
        if 'diff --name-only' in key:
            return self.migrations
        if 'ls-files' in key:
            return 'tracked.php'
        if 'ls-tree' in key:
            return self.incoming
        return ''

    def test_preflight_is_non_destructive(self):
        with patch.object(deploy, 'run', side_effect=self.command):
            self.assertEqual((COMMIT, COMMIT), deploy.preflight(self.root))
        self.assertFalse(any('merge' in command or 'reset' in command or 'artisan' in command for command in self.calls))

    def test_dirty_branch_migration_and_untracked_conflicts_stop(self):
        cases = [('branch', 'other'), ('dirty', ' M yarn.lock'), ('migrations', 'database/migrations/new.php'), ('incoming', 'collision.php')]
        (self.root / 'collision.php').write_text('preserve me')
        for attribute, value in cases:
            previous = getattr(self, attribute)
            setattr(self, attribute, value)
            with patch.object(deploy, 'run', side_effect=self.command):
                with self.assertRaises(deploy.Stop):
                    deploy.preflight(self.root)
            setattr(self, attribute, previous)
        self.assertEqual('preserve me', (self.root / 'collision.php').read_text())

    def test_existing_maintenance_stops(self):
        (self.root / 'storage/framework').mkdir(parents=True)
        (self.root / 'storage/framework/down').write_text('{}')
        with patch.object(deploy, 'run', side_effect=self.command):
            with self.assertRaisesRegex(deploy.Stop, 'already in maintenance'):
                deploy.preflight(self.root)

    def test_python36_syntax(self):
        # Available on Python 3.8+; real Python 3.6 compiles the module on import.
        if sys.version_info >= (3, 8):
            ast.parse(SCRIPT.read_text(), feature_version=(3, 6))


class DeploymentFlowTests(unittest.TestCase):
    setUp = ArtifactTests.setUp
    archive = ArtifactTests.archive
    # Inherit real artifact verification; only external services/commands are mocked.
    def flow(self, check=False, fail=None, migrate=False):
        from types import SimpleNamespace
        root = self.base / 'app'
        root.mkdir()
        (root / 'tracked.txt').write_text('old application')
        home = self.base / 'home'
        home.mkdir()
        archive = self.archive()
        calls = []
        def command(arguments, cwd, capture=False):
            calls.append([str(value) for value in arguments])
            if arguments[:3] == ['git', 'rev-parse', '--show-toplevel']:
                return str(root)
            if arguments[:3] == ['git', 'rev-parse', 'HEAD']:
                return 'b' * 40
            if '--version' in arguments:
                return 'Composer version 2.2.25'
            if 'PHP_VERSION_ID' in str(arguments):
                return '80229'
            if fail == 'dependency' and '--dry-run' in arguments:
                raise deploy.Stop('incompatible dependency')
            if fail == 'migration' and 'migrate' in arguments:
                raise deploy.Stop('migration failed')
            if fail == 'install' and '--optimize-autoloader' in arguments:
                raise deploy.Stop('install failed')
            return ''
        def download(commit, destination, token):
            if fail == 'artifact':
                raise deploy.Stop('bad artifact')
            destination.write_bytes(archive.read_bytes())
        args = SimpleNamespace(app_dir=str(root), php='/usr/bin/php', composer='/usr/bin/composer', check=check, migrate=migrate)
        with patch.object(Path, 'home', return_value=home), \
             patch.object(deploy, 'preflight', return_value=('b' * 40, COMMIT)), \
             patch.object(deploy, 'database_settings', return_value={}), \
             patch.object(deploy.shutil, 'which', return_value='/usr/bin/mysqldump'), \
             patch.object(deploy, 'backup_database', side_effect=lambda *args: command(['database-backup'], root)), \
             patch.object(deploy, 'read_token', return_value='fake-token'), \
             patch.object(deploy, 'download_artifact', side_effect=download), \
             patch.object(deploy, 'run', side_effect=command), \
             patch.object(deploy, 'verify_http', side_effect=deploy.Stop('HTTP failed') if fail == 'http' else None), \
             patch.object(deploy.subprocess, 'run') as external:
            # Only the tar stream and fallback artisan down reach subprocess directly.
            external.return_value.returncode = 0
            if fail:
                with self.assertRaises(deploy.Stop):
                    deploy.deploy(args)
            else:
                deploy.deploy(args)
            fallback = list(external.call_args_list)
        return root, home, calls, fallback

    def test_check_mode_never_enters_maintenance_or_merges(self):
        root, home, calls, fallback = self.flow(check=True)
        self.assertFalse(any('artisan' in command or 'merge' in command for command in calls))
        self.assertFalse((root / 'public/build').exists())
        self.assertEqual([], fallback)

    def test_artifact_failure_happens_before_maintenance(self):
        _, _, calls, fallback = self.flow(fail='artifact')
        self.assertFalse(any('artisan' in command or 'merge' in command for command in calls))
        self.assertEqual([], fallback)

    def test_platform_failure_happens_before_maintenance(self):
        _, _, calls, fallback = self.flow(fail='dependency')
        self.assertFalse(any('artisan' in command or 'merge' in command for command in calls))
        self.assertEqual([], fallback)

    def test_install_failure_requests_maintenance_and_does_not_publish_manifest(self):
        root, _, calls, fallback = self.flow(fail='install')
        self.assertFalse((root / 'public/build/manifest.json').exists())
        self.assertEqual(['artisan', 'down'], fallback[-1][0][0][-2:])
        self.assertFalse(any(command[-2:] == ['artisan', 'up'] for command in calls))

    def test_failed_http_check_requests_maintenance(self):
        _, home, _, fallback = self.flow(fail='http')
        self.assertEqual(['artisan', 'down'], fallback[-1][0][0][-2:])
        self.assertEqual([], list(home.rglob('success.txt')))

    def test_successful_flow_checks_platform_and_publishes_matching_assets(self):
        root, home, calls, _ = self.flow()
        self.assertEqual((self.build / 'manifest.json').read_text(), (root / 'public/build/manifest.json').read_text())
        self.assertTrue(any('check-platform-reqs' in command for command in calls))
        self.assertTrue(any(command == ['git', 'merge', '--ff-only', COMMIT] for command in calls))
        self.assertEqual(1, len(list(home.rglob('success.txt'))))
        self.assertFalse(any('migrate' in command or 'npm' in command for command in calls))

    def test_migration_backup_precedes_merge_and_migration(self):
        _, _, calls, _ = self.flow(migrate=True)
        backup = next(i for i, c in enumerate(calls) if 'database-backup' in c)
        down = next(i for i, c in enumerate(calls) if c[-2:] == ['artisan', 'down'])
        merge = next(i for i, c in enumerate(calls) if 'merge' in c)
        migration = next(i for i, c in enumerate(calls) if 'migrate' in c)
        up = next(i for i, c in enumerate(calls) if c[-2:] == ['artisan', 'up'])
        self.assertLess(down, backup)
        self.assertLess(backup, merge)
        self.assertLess(merge, migration)
        self.assertLess(migration, up)

    def test_migration_failure_keeps_maintenance_and_old_assets(self):
        root, home, calls, fallback = self.flow(migrate=True, fail='migration')
        self.assertFalse((root / 'public/build/manifest.json').exists())
        self.assertFalse(any(c[-2:] == ['artisan', 'up'] for c in calls))
        self.assertEqual(['artisan', 'down'], fallback[-1][0][0][-2:])
        self.assertEqual([], list(home.rglob('success.txt')))

    def test_migration_check_never_backs_up_or_migrates(self):
        _, _, calls, _ = self.flow(migrate=True, check=True)
        self.assertFalse(any('migrate' in c or 'database-backup' in c for c in calls))


class DatabaseBackupTests(unittest.TestCase):
    def test_dump_is_private_verified_and_credentials_removed(self):
        import gzip
        from types import SimpleNamespace
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            cfg = dict(database='test_database', username='test_user', password='secret\"\\value', host='localhost')
            credentials_seen = []
            def dump(command, **kwargs):
                self.assertNotIn(cfg['password'], ' '.join(command))
                credentials = Path(command[1].split('=', 1)[1])
                credentials_seen.append(credentials)
                self.assertEqual(0o600, stat.S_IMODE(credentials.stat().st_mode))
                self.assertIn('single-transaction', ' '.join(command))
                kwargs['stdout'].write(b'CREATE TABLE example (id int);\n-- Dump completed on today\n')
                return SimpleNamespace(returncode=0)
            with patch.object(deploy, 'database_settings', return_value=cfg), patch.object(deploy.subprocess, 'run', side_effect=dump):
                deploy.backup_database(root, 'php', root, 'mysqldump')
            archive = root / 'database.sql.gz'
            self.assertEqual(0o600, stat.S_IMODE(archive.stat().st_mode))
            data = gzip.open(str(archive), 'rb').read()
            metadata = json.loads((root / 'database-backup.json').read_text())
            self.assertEqual(hashlib.sha256(data).hexdigest(), metadata['sql_sha256'])
            self.assertFalse(credentials_seen[0].exists())
            self.assertFalse((root / 'database.sql.partial').exists())

    def test_failed_or_incomplete_dump_does_not_produce_verified_archive(self):
        from types import SimpleNamespace
        for returncode in [1, 0]:
            with tempfile.TemporaryDirectory() as directory:
                root = Path(directory)
                with patch.object(deploy, 'database_settings', return_value=dict(database='test', username='test')), patch.object(deploy.subprocess, 'run', return_value=SimpleNamespace(returncode=returncode)):
                    with self.assertRaises(deploy.Stop):
                        deploy.backup_database(root, 'php', root, 'mysqldump')
                self.assertFalse((root / 'database.sql.gz').exists())

    def test_connection_errors_are_redacted(self):
        from types import SimpleNamespace
        for output in ['secret_password', json.dumps(dict(driver='mysql', _transactional=False))]:
            with patch.object(deploy.subprocess, 'run', return_value=SimpleNamespace(returncode=0, stdout=output)):
                with self.assertRaises(deploy.Stop) as context:
                    deploy.database_settings(Path('.'), 'php')
                self.assertNotIn('secret_password', str(context.exception))


if __name__ == '__main__':
    unittest.main()
