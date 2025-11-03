// Script pour démarrer Next.js avec vérification de port
const { spawn } = require('child_process');
const net = require('net');

function checkPort(port) {
  return new Promise((resolve) => {
    const server = net.createServer();
    
    server.listen(port, () => {
      server.once('close', () => {
        resolve(true);
      });
      server.close();
    });
    
    server.on('error', () => {
      resolve(false);
    });
  });
}

async function findAvailablePort(startPort) {
  const fallbacks = [startPort, 3001, 3002, 4000, 4001, 5000];
  
  for (const port of fallbacks) {
    const available = await checkPort(port);
    if (available) {
      return port;
    }
  }
  
  return startPort; // Next.js gérera l'erreur si nécessaire
}

async function start() {
  const requestedPort = parseInt(process.env.PORT || '3000');
  const port = await findAvailablePort(requestedPort);
  
  if (port !== requestedPort) {
    console.log(`⚠️  Port ${requestedPort} occupé, utilisation du port ${port}`);
  }
  
  const nextProcess = spawn('npx', ['next', 'dev', '-p', port.toString()], {
    stdio: 'inherit',
    shell: true,
  });
  
  nextProcess.on('error', (err) => {
    console.error('Erreur:', err);
    process.exit(1);
  });
  
  process.on('SIGINT', () => {
    nextProcess.kill();
    process.exit(0);
  });
}

start();

