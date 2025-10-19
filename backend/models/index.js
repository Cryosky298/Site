const sequelize = require('../config/database');
const Anime = require('./Anime');
const User = require('./User');

Anime.init(sequelize);
User.init(sequelize);

sequelize.sync({ force: false }).then(() => console.log('DB sync bolindi'));

module.exports = { Anime, User };
