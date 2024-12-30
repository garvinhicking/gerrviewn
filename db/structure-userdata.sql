CREATE TABLE userdata (
     uid INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
     username INTEGER NOT NULL,
     hashed_password TEXT,
     serialized_data TEXT
);
