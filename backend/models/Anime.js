const { Model, DataTypes } = require('sequelize');

class Anime extends Model {
  static init(sequelize) {
    super.init({
      name: { type: DataTypes.STRING, allowNull: false },
      code: { type: DataTypes.STRING, allowNull: false },
      description: DataTypes.TEXT,
      genre: DataTypes.STRING,
      imageUrl: DataTypes.STRING,
      videoUrls: { type: DataTypes.ARRAY(DataTypes.STRING), defaultValue: [] },
    }, { sequelize, modelName: 'Anime' });
  }
}

module.exports = Anime;
