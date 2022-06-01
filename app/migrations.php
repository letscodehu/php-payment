<?php

return [
    "CREATE TABLE IF NOT EXISTS user (id INTEGER PRIMARY KEY AUTOINCREMENT, username varchar(32), password varchar(32))",
    "CREATE TABLE IF NOT EXISTS password_reset_token (id VARCHAR(64) PRIMARY KEY, user_id INT(11), expires TIMESTAMP, FOREIGN KEY(user_id) REFERENCES user(id))",
    "CREATE TABLE IF NOT EXISTS product (id VARCHAR(10) PRIMARY KEY, name VARCHAR(32), price INT(11))",
    "CREATE TABLE IF NOT EXISTS subscription (id VARCHAR(20) PRIMARY KEY, user_id INT(11), product_id VARCHAR(10), status VARCHAR(10), FOREIGN KEY(user_id) REFERENCES user(id), FOREIGN KEY(product_id) REFERENCES product(id))",
    "CREATE TABLE IF NOT EXISTS subscription_period (txn_id VARCHAR(30) PRIMARY KEY, subscription_id VARCHAR(20), start TIMESTAMP, end TIMESTAMP, FOREIGN KEY(subscription_id) REFERENCES subscription(id))",
    "INSERT INTO product (id, name, price) VALUES ('basic', 'Basic', 3490)"
];
