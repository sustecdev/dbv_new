module.exports = {
  apps: [
    {
      name: 'dbv-stellar-worker',
      script: 'scripts/node/withdrawal_worker_improved.js',
      cwd: __dirname,
      interpreter: 'node',
      node_args: '-r dotenv/config',
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '500M',
      env: {
        NODE_ENV: 'production',
        // API_BASE_URL: Auto-detected by node_config.js based on environment
        // Set explicitly to override auto-detection:
        //   - For localhost: API_BASE_URL='http://localhost/dbnew/public'
        //   - For production: API_BASE_URL='https://digitalbenefits.exchange/public'
        // If not set, node_config.js will auto-detect based on hostname and platform
        // API_BASE_URL: process.env.API_BASE_URL || undefined
      },
      error_file: './logs/pm2-error.log',
      out_file: './logs/pm2-out.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      merge_logs: true,
      min_uptime: '10s',
      max_restarts: 10
    },
    {
      name: 'dbv-binance-worker',
      script: 'scripts/node/binance_worker_loop.js',
      cwd: __dirname,
      interpreter: 'node',
      node_args: '-r dotenv/config',
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '500M',
      env: {
        NODE_ENV: 'production',
        // API_BASE_URL: Auto-detected by node_config.js based on environment
        // Set explicitly to override auto-detection:
        //   - For localhost: API_BASE_URL='http://localhost/dbnew/public'
        //   - For production: API_BASE_URL='https://digitalbenefits.exchange/public'
        // If not set, node_config.js will auto-detect based on hostname and platform
        // API_BASE_URL: process.env.API_BASE_URL || undefined
      },
      error_file: './logs/pm2-binance-error.log',
      out_file: './logs/pm2-binance-out.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      merge_logs: true,
      min_uptime: '10s',
      max_restarts: 10
    },
    {
      name: 'dbv-ethereum-worker',
      script: 'scripts/node/ethereum_worker_loop.js',
      cwd: __dirname,
      interpreter: 'node',
      node_args: '-r dotenv/config',
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '500M',
      env: {
        NODE_ENV: 'production',
        // API_BASE_URL: Auto-detected by node_config.js based on environment
        // Set explicitly to override auto-detection:
        //   - For localhost: API_BASE_URL='http://localhost/dbnew/public'
        //   - For production: API_BASE_URL='https://digitalbenefits.exchange/public'
        // If not set, node_config.js will auto-detect based on hostname and platform
        // API_BASE_URL: process.env.API_BASE_URL || undefined
      },
      error_file: './logs/pm2-ethereum-error.log',
      out_file: './logs/pm2-ethereum-out.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      merge_logs: true,
      min_uptime: '10s',
      max_restarts: 10
    }
  ]
};
