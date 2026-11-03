"""Kiểm tra file được Git theo dõi; chạy tại thư mục gốc."""
from pathlib import Path, PurePosixPath
import subprocess
import sys

ROOT_FILES = {
    'README.md', 'CONTRIBUTING.md', '.env.example', '.gitignore',
    '.gitattributes', '.editorconfig', '.htaccess',
}
PREFIXES = (
    '.github/', 'app/Core/', 'app/Middleware/', 'app/Shared/',
    'bootstrap/', 'config/', 'routes/', 'database/migrations/',
    'database/seeds/', 'database/samples/', 'resources/views/',
    'resources/js/', 'resources/css/', 'public/assets/', 'storage/',
    'tests/unit/', 'tests/integration/', 'tests/e2e/', 'desktop/',
    'docs/', 'scripts/',
)
MODULES = ('Auth', 'Users', 'Vocabulary', 'Learning', 'Reviews', 'Notifications', 'Admin')
LAYERS = ('Controllers', 'Services', 'Repositories', 'Requests')

def allowed(name):
    p = PurePosixPath(name)
    if p.is_absolute() or '..' in p.parts:
        return False
    if any(part in ('vendor', 'node_modules', 'dist', '.git', '__pycache__') for part in p.parts):
        return False
    if p.name.startswith('.env') and name != '.env.example':
        return False
    if name.startswith('storage/'):
        return p.name == '.gitkeep'
    if name in ROOT_FILES or name in ('public/index.php', 'public/.htaccess'):
        return True
    if name.startswith('app/Modules/'):
        return any(name.startswith(f'app/Modules/{m}/{layer}/') for m in MODULES for layer in LAYERS)
    return name.startswith(PREFIXES)

def main():
    root = Path(__file__).resolve().parents[1]
    result = subprocess.run(['git', 'ls-files', '-z'], cwd=root, check=True, capture_output=True)
    names = result.stdout.decode('utf-8').split('\0')
    errors = [name for name in names if name and not allowed(name)]
    for name in errors:
        print('Sai vị trí hoặc file không được commit:', name)
    print('Structure:', 'FAIL' if errors else 'PASS')
    return bool(errors)

if __name__ == '__main__':
    sys.exit(main())
