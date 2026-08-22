import { execSync, spawn } from 'child_process';
import { createRequire } from 'module';

const require = createRequire(import.meta.url);

const PORTS = {
  backend: 8000,
  frontend: 3000,
};

function killPort(port) {
  try {
    if (process.platform === 'win32') {
      const output = execSync(`netstat -ano | findstr :${port}`, { encoding: 'utf8', stdio: 'pipe' });
      const lines = output.split('\n').filter(l => l.includes('LISTENING'));
      const pids = [...new Set(lines.map(l => l.trim().split(/\s+/).pop()).filter(Boolean))];
      pids.forEach(pid => {
        try { execSync(`taskkill /PID ${pid} /F`, { stdio: 'pipe' }); } catch {}
      });
      if (pids.length) console.log(`  Killed ${pids.length} process(es) on port ${port}`);
    } else {
      try { execSync(`lsof -ti:${port} | xargs kill -9 2>/dev/null`, { stdio: 'pipe' }); } catch {}
    }
  } catch {}
}

console.log('\n🚀 Starting dev environment...\n');

console.log('  Checking ports...');
killPort(PORTS.backend);
killPort(PORTS.frontend);
console.log('  Ports cleared.\n');

const concurrently = require('concurrently');

concurrently([
  {
    name: 'LARAVEL',
    command: 'php artisan serve --port=' + PORTS.backend,
    cwd: '.',
    prefixColor: 'cyan',
  },
  {
    name: 'NEXTJS',
    command: 'npm run dev',
    cwd: './frontend',
    env: { PORT: String(PORTS.frontend) },
    prefixColor: 'green',
  },
], {
  prefix: '[{name}]',
  killOthers: ['failure'],
  restartTries: 0,
});
