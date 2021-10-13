CREATE TABLE authors (
	id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
	name varchar(50) NOT NULL,
	web varchar(100) NOT NULL,
	born date DEFAULT NULL
);


CREATE TABLE publishers (
	id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
	name varchar(50) NOT NULL
);

CREATE UNIQUE INDEX publishes_name ON publishers (name);

CREATE TABLE tags (
	id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
	name varchar(50) NOT NULL
);

CREATE TABLE eans (
	id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
	code varchar(50) NOT NULL
);

CREATE TABLE books (
	id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
	author_id int NOT NULL,
	translator_id int,
	title varchar(50) NOT NULL,
	publisher_id int NOT NULL,
	ean_id int,
	CONSTRAINT books_authors FOREIGN KEY (author_id) REFERENCES authors (id),
	CONSTRAINT books_translator FOREIGN KEY (translator_id) REFERENCES authors (id),
	CONSTRAINT books_publisher FOREIGN KEY (publisher_id) REFERENCES publishers (id),
	CONSTRAINT books_ean FOREIGN KEY (ean_id) REFERENCES eans (id)
);

CREATE INDEX book_title ON books (title);

CREATE VIEW my_books AS SELECT * FROM books WHERE author_id = 1;

CREATE TABLE books_x_tags (
	book_id int NOT NULL,
	tag_id int NOT NULL,
	PRIMARY KEY (book_id, tag_id),
	CONSTRAINT books_x_tags_tag FOREIGN KEY (tag_id) REFERENCES tags (id),
	CONSTRAINT books_x_tags_book FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE
);

CREATE TABLE tag_followers (
	tag_id int NOT NULL,
	author_id int NOT NULL,
	created_at datetime NOT NULL,
	PRIMARY KEY (tag_id, author_id),
	CONSTRAINT tag_followers_tag FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT tag_followers_author FOREIGN KEY (author_id) REFERENCES authors (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE table_with_defaults (
	name VARCHAR(255) DEFAULT 'Jon Snow'
);
