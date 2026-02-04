const jwt = require('jsonwebtoken');
require('dotenv').config({ path: '../../.env' }); // Cargar .env desde la raíz

const authMiddleware = (context) => {
  const authHeader = context.req.headers.authorization;
  if (authHeader) {
    const token = authHeader.replace('Bearer ', '');
    try {
      const user = jwt.verify(token, process.env.JWT_SECRET);
      return { user };
    } catch (e) {
      console.error('Token inválido');
    }
  }
  return { user: null };
};

module.exports = authMiddleware;