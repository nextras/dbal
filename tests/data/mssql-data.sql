DELETE FROM books_x_tags DBCC CHECKIDENT ('books_x_tags', RESEED, 0);
DELETE FROM books DBCC CHECKIDENT ('books', RESEED, 0);
DELETE FROM tags DBCC CHECKIDENT ('tags', RESEED, 0);
DELETE FROM authors DBCC CHECKIDENT ('authors', RESEED, 0);
DELETE FROM publishers DBCC CHECKIDENT ('publishers', RESEED, 0);
DELETE FROM tag_followers DBCC CHECKIDENT ('tag_followers', RESEED, 0);


SET IDENTITY_INSERT authors ON;
INSERT INTO authors (id, name, web, born) VALUES (1, 'Writer 1', 'http://example.com/1', NULL);
INSERT INTO authors (id, name, web, born) VALUES (2, 'Writer 2', 'http://example.com/2', NULL);
SET IDENTITY_INSERT authors OFF;


SET IDENTITY_INSERT publishers ON;
INSERT INTO publishers (id, name) VALUES (1, 'Nextras publisher');
SET IDENTITY_INSERT publishers OFF;

SET IDENTITY_INSERT tags ON;
INSERT INTO tags (id, name) VALUES (1, 'Tag 1');
INSERT INTO tags (id, name) VALUES (2, 'Tag 2');
INSERT INTO tags (id, name) VALUES (3, 'Tag 3');
SET IDENTITY_INSERT tags OFF;


SET IDENTITY_INSERT books ON;
INSERT INTO books (id, author_id, translator_id, title, publisher_id) VALUES (1, 1, 1, 'Book 1', 1);
INSERT INTO books (id, author_id, translator_id, title, publisher_id) VALUES (2, 1, NULL, 'Book 2', 1);
INSERT INTO books (id, author_id, translator_id, title, publisher_id) VALUES (3, 2, 2, 'Book 3', 1);
INSERT INTO books (id, author_id, translator_id, title, publisher_id) VALUES (4, 2, 2, 'Book 4', 1);
SET IDENTITY_INSERT books OFF;


INSERT INTO books_x_tags (book_id, tag_id) VALUES (1, 1);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (1, 2);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (2, 2);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (2, 3);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (3, 3);


-- there is nothing as connection timezone, so we have to explictly express the timezone
INSERT INTO tag_followers (tag_id, author_id, created_at) VALUES (1, 1, '2014-01-01 00:10:00+01:00');
INSERT INTO tag_followers (tag_id, author_id, created_at) VALUES (3, 1, '2014-01-01 00:10:00+01:00');
INSERT INTO tag_followers (tag_id, author_id, created_at) VALUES (2, 2, '2014-01-01 00:10:00+01:00');
