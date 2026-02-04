if (typeof globalThis === 'undefined') {
  global.globalThis = global;
}

const { ApolloServer } = require('apollo-server');
const typeDefs = require('./graphql/schema');
const resolvers = require('./graphql/resolvers');
const authMiddleware = require('./middleware/auth');
const jwt = require('jsonwebtoken'); 
const fs = require('fs');
const path = require('path');
const mysql = require('mysql2/promise');
require('dotenv').config({ path: path.resolve(__dirname, '../.env') });

//  Pools de bases de datos
const primaryPool = mysql.createPool({
  host: process.env.DDBB_HOST,
  user: process.env.DDBB_USER,
  password: process.env.DDBB_PASSWORD,
  database: process.env.DDBB_NAME,
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
});

const secondaryPool = mysql.createPool({
  host: process.env.DDBB_HOST,
  user: process.env.DDBB_USER,
  password: process.env.DDBB_PASSWORD,
  database: process.env.DDBB_NAME_GENERAL,
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
});

// log
const logError = (error) => {
  const logPath = path.resolve(__dirname, '../logs/server-errors.log');
  const errorMessage = `[${new Date().toISOString()}] ${error.stack || error}\n`;
  fs.appendFileSync(logPath, errorMessage);
};

// context
const server = new ApolloServer({
  typeDefs,
  resolvers,
 context: ({ req }) => {
  console.log('Body:', req.body); // DEBUG

  let isLogin = false;


  if (req.body && req.body.query && typeof req.body.query === 'string') {
    const q = req.body.query.toLowerCase().trim();
    if (q.includes('mutation') && q.includes('login')) {
      isLogin = true;
    }
  }

  if (req.body && req.body.operationName === 'login') {
    isLogin = true;
  }

  if (isLogin) {
    return { primaryPool, secondaryPool };
  }

  const auth = req.headers.authorization || '';
  if (!auth.startsWith('Bearer ')) {
    throw new Error('Token de autenticación requerido');
  }

  const token = auth.split(' ')[1];
  try {
    const user = jwt.verify(token, process.env.JWT_SECRET);
    return { user, primaryPool, secondaryPool };
  } catch (err) {
    throw new Error('Token inválido o expirado');
  }
},
introspection: false,
  playground: false,
});

server.listen({ port: process.env.PORT || 4000 })
  .then(({ url }) => {
    console.log(`🚀 Servidor jalando 🚀 // ${url}`);
  })
  .catch(error => {
    console.error('Error al iniciar el servidor 🚒:', error);
    logError(error);
  });