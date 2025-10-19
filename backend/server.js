require('dotenv').config();
const express = require('express');
const cors = require('cors');
const sequelize = require('./config/database');
const app = express();

app.use(cors());
app.use(express.json());

// Тест эндпоинт
app.get('/health', (req, res) => res.json({ status: 'OK' }));

const PORT = process.env.PORT || 8280;
app.listen(PORT, async () => {
  try {
    await sequelize.authenticate();
    console.log(`Server ${PORT}-portda ishlamoqda, Postgres qosildi`);
  } catch (error) {
    console.error('Postgres qate:', error);
  }
});
