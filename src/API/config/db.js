const mysql = require('mysql2/promise');
require('dotenv').config();

// Pool para la base de datos principal
const primaryPool = mysql.createPool({
  host: process.env.DDBB_HOST,
  user: process.env.DDBB_USER,
  password: process.env.DDBB_PASSWORD,
  database: process.env.DDBB_NAME, // bd principal (jpxdcegu_bd_coope_fape)
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0, // sin límite en la cola
});

// Pool para la base de datos secundaria
const secondaryPool = mysql.createPool({
  host: process.env.DDBB_HOST,
  user: process.env.DDBB_USER,
  password: process.env.DDBB_PASSWORD,
  database: process.env.DDBB_NAME_GENERAL, // bd secundaria (jpxdcegu_bd_general_coopera)
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0, // sin límite en la cola
});

module.exports = { primaryPool, secondaryPool };